<?php

return [
    /*
    * Number of Records to be fetched per job
    */
    'records_per_job' => 500,

    /*
    * records will be fetched in chunks for better performance
    */
    'chunks_of_records_per_job' => 1,

    /*
    * Directory where CSV will be prepared inside storage folder   
    */
    'dir' => 'exportCSV',

    /*
    * When CSV gets prepared successfully, mention the public method to call
    * method will receive bulkExport configuration used at the time of export as a parameter
    * Method given below is an examaple but it does exist at BulkExportCSV model
    */
    'call_on_csv_success' => [
        'namespace' => 'App\Models\BulkExportCSV', 
        'method' => 'handleCSV'
    ],
    
    /*
    * When CSV gets failed i.e. if any job fails, mention the public method to call
    * method will receive bulkExport configuration used at the time of export as a parameter 
    * Method given below is an examaple but it does exist at BulkExportCSV model
    */
    'call_on_csv_failure' => [
        'namespace' => 'App\Models\BulkExportCSV', 
        'method' => 'handleFailedCSV'
    ],

    /*
    * Database connection for bulk_export_csv table  
    */
    'db_connection' => env('DB_CONNECTION', 'mysql'),

    /*
    * Queue connection for jobs  
    */
    'queue_connection' => env('QUEUE_CONNECTION', 'sync'),

    /*
    * Name of queue where job will be dispatched  
    */
    'queue' => 'default',

    /*
    * Name of queue job batch   
    */
    'batch_name' => 'Bulk Export CSV',

    /*
    * The number of seconds the job can run before timing out
    * null takes default value
    * The pcntl PHP extension must be installed in order to specify job timeouts   
    */
    'job_timeout' => null,

    /*
    * if any job fails, it stops CSV preparation process
    * Decide whether partial CSV prepared should get deleted or not   
    */
    'delete_csv_if_job_failed' => false

];