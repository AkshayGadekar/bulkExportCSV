<?php

namespace Akki\BulkExportCSV;

use Illuminate\Support\ServiceProvider as SupportServiceProvider;
use Akki\BulkExportCSV\Providers\EventServiceProvider;

class ServiceProvider extends SupportServiceProvider 
{   

    public function boot() {
        //config
        $this->publishes([
            __DIR__.'/config/bulkexportcsv.php' => 
            config_path('bulkexportcsv.php'),
        ], 'config');
        
        //migration
        $this->publishes([
            __DIR__.'/database/migrations/create_bulk_export_csv_table.txt' =>
            $this->getMigrationFileName("create_bulk_export_csv_table.php")
        ], 'migrations');

        //model
        $this->publishes([
            __DIR__.'/Models/BulkExportCSV.txt' =>
            app_path('Models/BulkExportCSV.php')
        ], 'models');

        //events
        $this->publishes([
            __DIR__.'/Events/BulkExportCSVStarted.txt' =>
            app_path('Events/BulkExportCSVStarted.php'),
            __DIR__.'/Events/BulkExportCSVJobCompleted.txt' =>
            app_path('Events/BulkExportCSVJobCompleted.php'),
            __DIR__.'/Events/BulkExportCSVSucceeded.txt' =>
            app_path('Events/BulkExportCSVSucceeded.php'),
            __DIR__.'/Events/BulkExportCSVFailed.txt' =>
            app_path('Events/BulkExportCSVFailed.php')
        ], 'events');
        //listeners
        $this->publishes([
            __DIR__.'/Events/ListenBulkExportCSVStarted.txt' =>
            app_path('Events/ListenBulkExportCSVStarted.php'),
            __DIR__.'/Events/ListenBulkExportCSVJobCompleted.txt' =>
            app_path('Events/ListenBulkExportCSVJobCompleted.php'),
            __DIR__.'/Events/ListenBulkExportCSVSucceeded.txt' =>
            app_path('Events/ListenBulkExportCSVSucceeded.php'),
            __DIR__.'/Events/ListenBulkExportCSVFailed.txt' =>
            app_path('Events/ListenBulkExportCSVFailed.php')
        ], 'listeners');

        //$this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    public function register()
    {
        $this->app->register(EventServiceProvider::class);
        
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