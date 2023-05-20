<?php

namespace Akki\BulkExportCSV\Classes\Traits;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use EloquentSerialize;
//use Akki\BulkExportCSV\Models\BulkExportCSV;
use Akki\BulkExportCSV\Models\BulkExportCSVModel;
use Illuminate\Support\Str;
use Throwable;
use Exception;
use DB;
use Illuminate\Support\Facades\Schema;
use App\Events\BulkExportCSVSucceeded;	
use App\Events\BulkExportCSVFailed;

trait Helpers {

    public function makeCollection($rows) {
        if (!$this->isCollection($rows)) {
            $rows = collect($rows);
        }
        return $rows;
    }

    public function isCollection($rows) {
        return $rows instanceof EloquentCollection || $rows instanceof Collection;
    }

    public function isEloquent($query) {
        if (!($query instanceof EloquentBuilder)) {
            throw new Exception("Query must be an eloquent query.", 422);
        }
    }

    public function serializeEloquent($query) {
        return EloquentSerialize::serialize($query);
    }

    public function unserializeEloquent($query) {
        return EloquentSerialize::unserialize($query);
    }

    public function makeCSVRow($row) {
        return '"' . implode('","', $row) . '"';
    }

    public function mapDataToResource($data, $resource) {
        $dataFromResource = $resource::collection($data);
        return json_decode($dataFromResource->toJson(), true);
    }

    public function makeCSVData($data) {
        $csv_rows = array_map(function ($dataRow) {
            return $this->makeCSVRow($dataRow);
        }, $data);
        $csv_rows_with_breakline = implode(PHP_EOL, $csv_rows);
        return $csv_rows_with_breakline;
    }

    public function appendColumns($data, $columns) {
        return array_merge([$columns], $data);
    }

    public function makeCSV($csv_data, $config) {
        $dir_name = $config->dir;
        $csv_name = $config->csv_name;
        
        if (!$config->is_csv_exists) {
            if (!is_dir(storage_path($dir_name))) {
                mkdir(storage_path($dir_name), 0777, true);
            }
        }
        
        //if (file_exists(storage_path("$dir_name/$csv_name"))) {
        if ($config->is_csv_exists) {
            $csv_data = PHP_EOL.$csv_data;
        }

        file_put_contents(storage_path("$dir_name/$csv_name"), $csv_data, FILE_APPEND | LOCK_EX);
    }

    public function getCount($query) {
        $records_count = $query->count();
        if (!$records_count) {
            throw new Exception('No records found to export into CSV.', 404);
        }
        return $records_count;
    }

    public function getDBConnection() {
        $db_connection = config('bulkexportcsv.db_connection');
        $isTableExists = Schema::connection($db_connection)->hasTable('bulk_export_csv');
        if(!$isTableExists){
            throw new Exception('bulk_export_csv table does not exists, please run php artisan migrate.', 403);
        }
        return $db_connection;
    }

    public function getColumns($query, $resource_namespace, $data) {
        config(['bulkexportcsv.data' => $data]);
        
        $firstRecord = $query->first();
        $columns_keys = array_keys(json_decode((new $resource_namespace($firstRecord))->toJson(), true));
        $columns = array_map(function($column){
            return Str::headline($column);
        }, $columns_keys);
        return $columns;
    }

    public function getCongifObj($data) {
        $csv_info = $data['csv_info']??null;
        
        $config = config('bulkexportcsv');
        $config['csv_info'] = $csv_info;
        $obj = new \stdClass();
        foreach ($config as $key => $value) {
            $obj->$key = $value;
        }
        return $obj;
    }

    public function getPublicProperties($instance) {
        return get_object_vars($instance);
    }

    public function isLastLoop($array, $index) {
        return $isLastLoop = count($array) == ($index+1);
    }

    public function checkRecordsPerChunk($config) {
        $records_per_chunk = $config->records_per_job/$config->chunks_of_records_per_job;
        if (is_float($records_per_chunk)) {
            throw new Exception("records_per_chunk $config->records_per_job/$config->chunks_of_records_per_job
            must be a whole number.", 422);
        }

        //check if on success func is proper
        // $method_info = $config->call_on_csv_success;
        // $this->checkIfMethodIsProper($method_info);

        // $method_info = $config->call_on_csv_failure;
        // $this->checkIfMethodIsProper($method_info);
        
    }
    
    public function checkIfMethodIsProper($method_info)
    {
        $class_namespace = $method_info['namespace'];
        $method_name = $method_info['method'];
        
        if (!class_exists($class_namespace)) {
            throw new Exception("class $class_namespace does not exist.", 422);
        }
        if (!method_exists($class_namespace, $method_name)) {
            throw new Exception("method $method_name on class $class_namespace does not exist.", 422);
        }

        try {
            //check if class constructor has arguements which should be optional
            $reflector = new \ReflectionClass( $class_namespace );
            if ($constructor = $reflector->getConstructor()) {
                if ( $paramsArray = $constructor->getParameters() ) {
                    foreach ($paramsArray as $key => $param) {
                        if (!$param->isDefaultValueAvailable()) {
                            throw new Exception("constructor parameters of class $class_namespace must have default value set.", 422);
                        }
                    }
                }                   
            }
            //check function is public and has only one param required to receive config as arguement
            $methods = $reflector->getMethods();
            foreach ($methods as $method) {
                if ($method->getName() == $method_name) {
                    if (!($method->isPublic() && !$method->isStatic())) {
                        throw new Exception("method $method_name must be public.", 422);
                    }
                    if ($method->getNumberOfParameters() !== 1) {
                        throw new Exception("method $method_name must require only one parameter which will receive bulkExportConfig as arguement.", 422);
                    }
                }
            }
        } catch ( \ReflectionException $e ) {
            throw new Exception($e->getMessage(), 422);
        }

    }

    public function insertIntoBulkExportCSVTable($data) {
        return BulkExportCSVModel::create($data);
    }

    public function getAuthUserId()
    {
        $user = auth()->user();
        
        $user_id = null;
        if ($user) {
            $user_id = $user->id??null;
        }
        return $user_id;
    }

    public function getBulkExportModal($jobs_id) {
        $bulkExportModal = BulkExportCSVModel::where("jobs_id", $jobs_id)->first();
        if (!$bulkExportModal) {
            throw new Exception("Record with jobs_id $jobs_id does not exist.", 404);
        }
        return $bulkExportModal;
    }

    public function calculateAvgTime($each_jobs_time) {
        $sum = array_sum($each_jobs_time);
        $avg_time = $sum/count($each_jobs_time);
        return $avg_time;
    }

    public function calculateProgress($this_job_no, $total_jobs) {
        $progress_in_percentage = ($this_job_no/$total_jobs) * 100;
        return $progress_in_percentage;
    }

    public function callMethod($config, $bulkExportModal, $status)
    {
        try {
            if ($status == 'success') {
                $method_info = $config->call_on_csv_success;    
            } else if ($status == 'fail') {
                $method_info = $config->call_on_csv_failure;
            }
            $class_namespace = $method_info['namespace'];
            $method_name = $method_info['method'];
            $obj = new $class_namespace;
            $call_func = $obj->$method_name($config);
        } catch (Throwable $e) {
            $errored = $bulkExportModal->error;
            $error = $e->getMessage();
            $bulkExportModal->error = $errored."Method Exception: $error.";
            $bulkExportModal->save();
        }
    }

    public function triggerEvent($event, $bulkExportModal) {
        try {
        
            switch ($event) {
                case 'SUCCESS':
                    event(new BulkExportCSVSucceeded($bulkExportModal));
                    break;
                case 'FAIL':
                    event(new BulkExportCSVFailed($bulkExportModal));
                    break;
                default:
                    # code...
                    break;
            }

        } catch (Throwable $e) {
            $errored = $bulkExportModal->error;
            $error = $e->getMessage();
            $bulkExportModal->error = $errored."Event Exception: $error.";
            $bulkExportModal->save();
        }
    }

    public function getAllDataFromQuery($query) {
        $query = clone $query;
        return $query->get();
    }

}