<?php

namespace Akshay\BulkExportCSV;

use Illuminate\Support\ServiceProvider as SupportServiceProvider;

class ServiceProvider extends SupportServiceProvider 
{   

    public function boot() {
        $this->publishes([
            __DIR__.'/config/bulkexportcsv.php' => config_path('bulkexportcsv.php'),
            __DIR__.'/database/create_bulk_export_jobs_table.php' 
            => database_path("migrations/".date("Y-m-d_His_")."create_bulk_export_jobs_table.php"),
        ]);
        //$this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    public function register()
    {
        // $this->mergeConfigFrom(
        //     __DIR__.'/config/bulkexportcsv.php', 'bulkexportcsv'
        // );
    }
    
}