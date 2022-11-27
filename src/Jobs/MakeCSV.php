<?php

namespace Akshay\BulkExportCSV\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Akshay\BulkExportCSV\Classes\Traits\Helpers;
use EloquentSerialize;
use Throwable;

class MakeCSV implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Helpers;

    public $query, $resource_namespace, $columns, $config, $data, $this_job_no, $bulkExportModal;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($query, $resource_namespace, $columns, $config, $data)
    {
        $this->query = $query;
        $this->resource_namespace = $resource_namespace;
        $this->columns = $columns;
        $this->config = $config;
        $this->data = $data;

        if ($config->job_timeout) {
            $this->timeout = $config->job_timeout;
        }
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->batch()->cancelled()) {
            return;
        }
            
        //try {
            $start_time = strtotime("now");

            $query = $this->unserializeEloquent($this->query);
            $resource_namespace = $this->resource_namespace;
            $columns = $this->columns;
            $config = $this->config;
            
            $records_count = $config->records_count;
            $records_per_job = $config->records_per_job;
            $chunks_of_records_per_job = $config->chunks_of_records_per_job;
            $this->this_job_no = $config->this_job_no;

            $this->bulkExportModal = $this->getBulkExportModal($config->jobs_id);

            $prev_no_of_records_processed = ($this->this_job_no - 1) * $records_per_job;
            $records_per_chunk = (int)ceil($records_per_job/$chunks_of_records_per_job);  //(int)ceil() not necessary, checkRecordsPerChunk() solves this
            $each_chunks = array_fill(0, $chunks_of_records_per_job, $records_per_chunk);
            
            foreach ($each_chunks as $key => $chunk) {
                $this_job_processed_records = $key*$chunk;
                $skip = $this_job_processed_records + $prev_no_of_records_processed;
                $limit = $chunk;
                if ($this->isLastLoop($each_chunks, $key)) {
                    $limit = $records_per_job - ($this_job_processed_records);  //not necessary, checkRecordsPerChunk() solves this
                    //$this->this_job_no==2 ? dd($records_per_job, $skip, $limit) : "";
                }
                
                $data = $query->skip($skip)->limit($limit)->get();
                $this->prepareCSV($data);

                $current_no_of_records_processed = $skip + $limit;
                if ($current_no_of_records_processed > $records_count) {
                    break;
                }
            }
        // } catch (Throwable $e) {
        //     $this->jobError($e->getMessage());    
        // }
        
        $this->jobCompleted($start_time);
    }

    public function prepareCSV($data) {
        if (!$data->count()) {
            return ;
        }

        $resource = $this->resource_namespace;
        $columns = $this->columns;
        
        config(['bulkexportcsv.data' => $this->data]);
        $data = $this->mapDataToResource($data, $resource);
        
        $csv_name = $this->bulkExportModal->csv_name;
        $this->config->is_csv_exists = !!$csv_name;
        if (!$csv_name) {
            $csv_name = $this->getCSVName();
            $data = $this->appendColumns($data, $columns);
        }
        $this->config->csv_name = $csv_name;
        
        $csv_data = $this->makeCSVData($data);
        
        $file = $this->makeCSV($csv_data, $this->config);
    }
    
    public function getCSVName () {
        $csv_name = date('Y_m_d_H_i_s_') . uniqid() . ".csv";

        $newConfig = clone $this->config;
        unset($newConfig->this_job_no);
        unset($newConfig->is_csv_exists);
        $newConfig->csv_path = storage_path("$newConfig->dir/$csv_name");
        $newConfig->batch_id = $this->batch()->id;

        $this->bulkExportModal->csv_name = $csv_name;
        $this->bulkExportModal->config = $newConfig;
        $this->bulkExportModal->save();
        
        return $csv_name;
    }

    public function jobError($error)
    {
        $each_jobs_error = $this->bulkExportModal->each_jobs_error;
        $each_jobs_error[$this->this_job_no] = $error;
        $this->bulkExportModal->each_jobs_error = $each_jobs_error;
        $this->bulkExportModal->save();
    }

    public function jobCompleted($start_time)
    {
        $this->bulkExportModal->completed_jobs = $this->this_job_no;

        $end_time = strtotime("now");
        $each_jobs_time = $this->bulkExportModal->each_jobs_time;
        $each_jobs_time[$this->this_job_no] = $end_time - $start_time;
        $this->bulkExportModal->each_jobs_time = $each_jobs_time;

        $this->bulkExportModal->average_jobs_time = $this->calculateAvgTime($each_jobs_time);

        if ($this->bulkExportModal->total_jobs == $this->this_job_no) {
            //$this->bulkExportModal->export_status = "Completed";
        }

        if ($this->this_job_no == 1) {
            // unset($this->config->this_job_no);
            // unset($this->config->is_csv_exists);
            // $this->bulkExportModal->config = $this->config;
        }

        $this->bulkExportModal->save();
    }
    
}
