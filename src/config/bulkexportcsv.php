<?php

return [
    'records_per_job' => 500,
    'chunks_of_records_per_job' => 1,
    'db_connection' => env('DB_CONNECTION', 'mysql'),
    'queue_connection' => env('QUEUE_CONNECTION', 'sync'),
    'queue' => 'default',
    'dir' => 'exportCSV',
    'batch_name' => 'Bulk Export CSV',
    'job_timeout' => null, //The number of seconds the job can run before timing out.
    //The pcntl PHP extension must be installed in order to specify job timeouts.
    'delete_csv_if_job_failed' => false,
    'call_on_csv_success' => [
        'namespace' => 'App\Http\Controllers\GetCSVController', 
        'method' => 'getcsv'
    ],
    'call_on_csv_failure' => [
        'namespace' => 'App\Http\Controllers\GetCSVController', 
        'method' => 'errorcsv'
    ]
];