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
        
        $config = $this->getCongifObj($data);
        
        $columns = count($columns) ? $columns : $this->getColumns($query, $resource_namespace, $data);
        
        $records_count = $this->getCount($query);
        $records_per_job = $config->records_per_job;
        $total_jobs = (int)ceil($records_count/$records_per_job);
        $this->checkRecordsPerChunk($config);
        
        $config->records_count = $records_count;
        $config->jobs_id = strtotime('now').uniqid('csv');

        $bulkExportModal = $this->insertIntoBulkExportCSVTable(['jobs_id' => $config->jobs_id, 'total_records' => $records_count, 
        'total_jobs' => $total_jobs, "user_id" => $this->getAuthUserId()]);
        
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

            if ($bulkExportModal->export_status == 'Completed') {
                //$this->callMethod($config, $bulkExportModal, 'success');
                $this->triggerEvent("SUCCESS", $bulkExportModal);
            }
            
        })->catch(function (Batch $batch, Throwable $e) use ($config) {
            // First batch job failure detected...
            $error = $e->getMessage();
            
            $bulkExportModal = $this->getBulkExportModal($config->jobs_id);
            $bulkExportModal->export_status = 'Error';
            $bulkExportModal->error = "Jobs Exception: $error.";
            $bulkExportModal->save();

            $config = $bulkExportModal->config;
                
            if ($config->delete_csv_if_job_failed) {
                $csv_name = $bulkExportModal->csv_name;
                $csv_path = storage_path("$config->dir/$csv_name");
                if ($csv_path && file_exists($csv_path)) {
                    unlink($csv_path);
                }
            }

            //$this->callMethod($config, $bulkExportModal, 'fail');
            $this->triggerEvent("FAIL", $bulkExportModal);

        })->finally(function (Batch $batch) {
            // The batch has finished executing...
        })->name($config->batch_name)->onConnection($config->queue_connection)->onQueue($config->queue)->dispatch();
        
        //$config->csv_path = null; 
        
        $bulkExportModal->config = $config;
        $bulkExportModal->batch_id = $batch->id;
        $bulkExportModal->save();
        

        return $bulkExportModal;
    }

    public function download($query, $resource_namespace, $columns=[], $data=null)
    {
        $clonedQuery = clone $query;
        config(['bulkexportcsv.data' => $data]);

        $resource = $resource_namespace;
        $columns = count($columns) ? $columns : $this->getColumns($query, $resource_namespace, $data);
        
        $data = $this->getAllDataFromQuery($clonedQuery);
        $data = $this->mapDataToResource($data, $resource);
        $data = $this->appendColumns($data, $columns);
        
        $csv_data = $this->makeCSVData($data);
        $csv_name = date('Y_m_d_H_i_s_') . uniqid() . ".csv";

        $headers = [
            "Content-Type" => "text/csv"
        ];

        return response()->streamDownload(function () use ($csv_data) {
            echo $csv_data;
        }, $csv_name, $headers);
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
