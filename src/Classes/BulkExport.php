<?php

namespace Akki\BulkExportCSV\Classes;

use Akki\BulkExportCSV\Classes\Traits\Helpers;
use Akki\BulkExportCSV\Jobs\MakeCSV;
use Throwable;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

class BulkExport
{
    use Helpers;
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function build($query, $resource_namespace, $columns=[], $data=null)
    {   

        $db_connection = $this->getDBConnection();
        
        $config = $this->getCongifObj();
        
        $columns = count($columns) ? $columns : $this->getColumns($query, $resource_namespace, $data);
        
        $records_count = $this->getCount($query);
        $records_per_job = $config->records_per_job;
        $total_jobs = (int)ceil($records_count/$records_per_job);
        $this->checkRecordsPerChunk($config);
        
        $config->records_count = $records_count;
        $config->jobs_id = strtotime('now').uniqid('csv');

        $bulkExportModal = $this->insertIntoBulkExportCSVTable(['jobs_id' => $config->jobs_id, 'total_records' => $records_count, 'total_jobs' => $total_jobs]);
        
        $query = $this->serializeEloquent($query); 
        
        $no_of_jobs = array_fill(1, $total_jobs, $records_per_job);
        $jobs = [];
        foreach ($no_of_jobs as $key => $value) {
            $jobConfig = clone $config;
            $jobConfig->this_job_no = $key;
            $jobs[] = new MakeCSV($query, $resource_namespace, $columns, $jobConfig, $data);
        }
        
        //Bus::chain($jobs)->onConnection($config->queue_connection)->onQueue($config->queue)->dispatch();
        $batch = Bus::batch([$jobs])->then(function (Batch $batch) use ($config) {
            $bulkExportModal = $this->getBulkExportModal($config->jobs_id);
            !$batch->cancelledAt ?  $bulkExportModal->export_status = 'Completed' : $bulkExportModal->export_status = 'Cancelled';
            $bulkExportModal->save();

            $config = $bulkExportModal->config;

            if ($bulkExportModal->export_status = 'Completed') {
                $this->callMethod($config, $bulkExportModal, 'success');
            }
            
        })->catch(function (Batch $batch, Throwable $e) use ($config) {
            // First batch job failure detected...
            $error = $e->getMessage();
            
            $bulkExportModal = $this->getBulkExportModal($config->jobs_id);
            $bulkExportModal->export_status = 'Error';
            $bulkExportModal->error = "Jobs Exception: $error.";
            $bulkExportModal->save();

            if ($bulkExportModal->config) {
                $config = $bulkExportModal->config;
                
                if ($config->delete_csv_if_job_failed) {
                    $csv_path = $config->csv_path;
                    if (file_exists($csv_path)) {
                        unlink($csv_path);
                    }
                }
                
            } else {
                $config->batch_id = $batch->id;
                $config->csv_path = null;
            }

            $this->callMethod($config, $bulkExportModal, 'fail');

        })->finally(function (Batch $batch) {
            // The batch has finished executing...
        })->name($config->batch_name)->onConnection($config->queue_connection)->onQueue($config->queue)->dispatch();
        
        //add batch id to bulk export table 
        $bulkExportModal->batch_id = $batch->id;
        $bulkExportModal->save();

        // $batch->bulkExportConfig = $config;
        // return $this->getPublicProperties($batch);

        $config->batch_id = $batch->id;
        return $config;
        
    }

    /*
    public function buildCSV($query, $resource_namespace, $columns=[])
    {    
        $data = User::limit(20)->get();
        
        $resource = "App\Http\Resources\UserResource";
        $data = $this->mapDataToResource($data, $resource);
        
        $columns = ["First Name", "Last Name", "Email", "Contact Number"];
        $data = $this->appendColumns($data, $columns);
        
        $csv_data = $this->makeCSVData($data);
        
        $file = $this->makeCSV($csv_data);
    }
    */

}
