<?php

namespace Akshay\BulkExportCSV\Facades;

use Akshay\BulkExportCSV\Classes\BulkExport as BulkExportClass;
use Illuminate\Support\Facades\Facade;

class BulkExport extends Facade
{
    protected static function getFacadeAccessor()
    {
        return BulkExportClass::class;   
    }
}