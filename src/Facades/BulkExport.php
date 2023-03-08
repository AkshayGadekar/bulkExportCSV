<?php

namespace Akki\BulkExportCSV\Facades;

use Akki\BulkExportCSV\Classes\BulkExport as BulkExportClass;
use Illuminate\Support\Facades\Facade;

class BulkExport extends Facade
{
    protected static function getFacadeAccessor()
    {
        return BulkExportClass::class;   
    }
}