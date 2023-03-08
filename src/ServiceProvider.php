<?php

namespace Akki\BulkExportCSV;

use Illuminate\Support\ServiceProvider as SupportServiceProvider;

class ServiceProvider extends SupportServiceProvider 
{   

    public function boot() {
        $this->publishes([
            __DIR__.'/config/bulkexportcsv.php' => 
            config_path('bulkexportcsv.php'),
        ], 'config');
        
        $this->publishes([
            __DIR__.'/database/migrations/create_bulk_export_csv_table.txt' =>
            $this->getMigrationFileName("create_bulk_export_csv_table.php")
        ], 'migrations');

        $this->publishes([
            __DIR__.'/Models/BulkExportCSV.txt' =>
            app_path('Models/BulkExportCSV.php')
        ], 'models');

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
        $timestamp = date("Y_m_d_His");
        return database_path("migrations/{$timestamp}_{$migrationFileName}");
    }
    
}