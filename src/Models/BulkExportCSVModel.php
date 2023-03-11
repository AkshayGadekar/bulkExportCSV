<?php

namespace Akki\BulkExportCSV\Models;

use Illuminate\Support\Facades\Bus;
use App\Models\BulkExportCSV;

class BulkExportCSVModel extends BulkExportCSV
{   
    public function getEachJobsTimeAttribute($value) {
        return $value ? unserialize($value) : [];
    }

    public function setEachJobsTimeAttribute($value) {
        $this->attributes['each_jobs_time'] = serialize($value);
    }

    public function getConfigAttribute($value) {
        return $value ? unserialize($value) : null;
    }

    public function setConfigAttribute($value) {
        $this->attributes['config'] = serialize($value);
    }

    public function getEachJobsErrorAttribute($value) {
        return $value ? unserialize($value) : [];
    }

    public function setEachJobsErrorAttribute($value) {
        $this->attributes['each_jobs_error'] = serialize($value);
    }

    public function setErrorAttribute($value) {
        $this->attributes['error'] = substr($value, 0, 250);
    }

    public static function findByJobsId($jobs_id)
    {
        return self::where("jobs_id", $jobs_id)->first();
    }

    public static function cancelExportCSVProcess($jobs_id)
    {
        $bulkExportCSV = self::findByJobsId($jobs_id);
        if ($bulkExportCSV) {
            $batch_id = $bulkExportCSV->batch_id;
            $batch = Bus::findBatch($batch_id);
            $batch->cancel();
        }   
    }
}
