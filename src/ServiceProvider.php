<?php

namespace Akshay\BulkExportCSV;

use Illuminate\Support\ServiceProvider as SupportServiceProvider;

class ServiceProvider extends SupportServiceProvider 
{   

    public function boot() {
        $this->publishes([
            __DIR__.'/config/bulkexportcsv.php' => 
            config_path('bulkexportcsv.php'),
        ], 'config');
        
        $this->publishes([
            __DIR__.'/database/migrations/create_bulk_export_csv_table.php.stub' =>
            $this->getMigrationFileName("create_bulk_export_csv_table.php")
        ], 'migrations');

        //$this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/bulkexportcsv.php', 'bulkexportcsv'
        );
    }

    protected function getMigrationFileName($migrationFileName)
    {
        $timestamp = date("Y-m-d_His");
        return database_path("migrations/{$timestamp}_{$migrationFileName}");
    }
    
}