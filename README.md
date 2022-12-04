# Export Unlimited Records into CSV
[![Latest Stable Version](https://img.shields.io/badge/stable-v1.1.0-blue)](https://packagist.org/packages/akki/bulkexportcsv)
[![Total Downloads](https://img.shields.io/badge/downloads-25-blue)](https://packagist.org/packages/akki/bulkexportcsv)
[![License](https://img.shields.io/badge/license-MIT-teal)](https://packagist.org/packages/akki/bulkexportcsv)

## Introduction

This package will help you in exporting unlimited data using laravel's eloquent query and json resource. It is based on queue batching of laravel. It supports all databases eloquent supports. 

## Installation

Install **BulkExportCSV**:

```bash
composer require akki/bulkexportcsv
```

Publish the config file (config/bulkexportcsv.php), model (App\Models\BulkExportCSV.php) and migration (bulk_export_csv table):

```bash
php artisan vendor:publish --provider="Akshay\BulkExportCSV\ServiceProvider"
```

Prepare Migration of queue tables:

```bash
php artisan queue:table
php artisan queue:batches-table
```

Miragte tables:

```bash
php artisan migrate
```

## Usage

### Make a query
Prepare an eloquent query, make sure query does not have get(), first(), skip(), limit() methods. By Default package will export all records. 
```php
$query = \App\Models\User::query();
$query->with('serviceProvider')->where('country', 'IN');
```

### Make a JSON resource
```bash
php artisan make:resource UserResource
```
UserResource.php:
```php
public function toArray($request)
{
    return [
        'name' => $this->name,
        'email' => $this->email,
        'service_provider' => $this->serviceProvider->org_name,
        'contact_number' => $this->contact_number,
    ];
}
```

### Export into CSV
Before this Make sure to fill up `config/bulkexportcsv.php` correctly. This will start the export CSV process. 
```php
$query = \App\Models\User::query();
$query->with('serviceProvider')->where('country', 'IN');

$resource_namespace = 'App\Http\Resources\UserResource';

$bulkExportCSV = \BulkExportCSV::build($query, $resource_namespace);
```
`build` method returns `Illuminate\Bus\Batch` instance of job batching, one can [Inspect Batch](https://laravel.com/docs/8.x/queues#inspecting-batches).
Also, package gives bulk export configuration used for export CSV by accessing `bulkExportConfig` on batch instance i.e. `$bulkExportCSV->bulkExportConfig` 

### Configuration
Edit `config/bulkexportcsv.php` to suit your needs.

```php
/** @file config/bulkexportcsv.php */

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
    * When CSV gets prepared successfully, mention the method to call
    * method will receive bulkExport configuration used at the time of export as parameter
    */
    'call_on_csv_success' => [
        'namespace' => 'App\Http\Controllers\BulkExportCSVController', 
        'method' => 'getCSV'
    ],
    
    /*
    * When CSV gets failed i.e. if any job fails, mention the method to call
    * method will receive bulkExport configuration used at the time of export as parameter   
    */
    'call_on_csv_failure' => [
        'namespace' => 'App\Http\Controllers\BulkExportCSVController', 
        'method' => 'errorCSV'
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
    * Name of batch   
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
```

### Method to call on CSV success or failure 
From `config/bulkexportcsv.php`, methods mentioned at 'call_on_csv_success' and 'call_on_csv_failure' will be called. If CSV gets prepared successfully 'call_on_csv_success' method will be called, on failure 'call_on_csv_failure' will be called, Methods will receive bulk export configuration as only parameter. 
```php
class BulkExportCSVController extends Controller
{   
    public function getCSV($bulkExportConfig)
    {
        $csv_path = $bulkExportConfig->csv_path;
        ................
    }

    public function errorCSV($bulkExportConfig)
    {
        $csv_path = $bulkExportConfig->csv_path; //CSV may not exist if 'delete_csv_if_job_failed' mention in configuration is true
        $error = \App\Models\BulkExportCSV::where('jobs_id', $bulkExportConfig->jobs_id)->first()->error;
        //If job was failed, error will be "Jobs Exception: ......."
        //If this method itself thrown an exception error will be "Method Exception: ......."
        ................
    }

}
```
`$bulkExportConfig` in above methods has all values from `config/bulkexportcsv.php` which were used to export CSV, it also has jobs_id (unique ID generated for export), records_count (total records exported), batch_id (batch_id of job process), csv_path (path of CSV). One then can take CSV and upload it to s3 or email it to user as per requirement.

### bulk_export_csv table 
When CSV gets prepared, you can access its process using "job_batches" table, but package also ships with its own table "bulk_export_csv" which has following columns:
```php
[
    'jobs_id' => unique ID generated for export
    'csv_name' => CSV file name
    'total_records' => total records exported
    'total_jobs' => total jobs required to export CSV
    'completed_jobs' => when export CSV starts, this column gets updated with number of completed jobs
    'export_status' => export status of CSV as 'InProgress', 'Completed' or 'Error'
    'each_jobs_time' => time taken by each job processed
    'average_jobs_time' => average time all jobs taken
    'error' => Exception error if any job fails or 'call_on_csv_success' or 'call_on_csv_failure' methods threw exception
    'config' => bulk export configuration used for particular export
    'batch_id' => batch_id of job process
]
```

## More Options in 'build' method of 'BulkExportCSV' 
### Define Columns for Export CSV
By default, package takes columns names from json resource itself. But one can define custom columns as required:
```php
$columns = ['Name', 'Email', 'Service Provider', 'Contact Number'];
$bulkExportCSV = BulkExportCSV::build($query, $resource_namespace, $columns);
```

### Access Request Data in Resource
Often times, we need authenticated user data or request data in json resource. As export CSV happens in background, there is no access to request, but one can send data to json resource or even eloquent model accessors or in `call_on_csv_success`, `call_on_csv_failure` methods by using `config('bulkexportcsv.data')`:
```php
$user = auth()->user();
$data = ['user' => $user, 'request' => $request->all()];
$columns = []; //if columns are defined as empty, then columns will be taken from json resource itself
$bulkExportCSV = BulkExportCSV::build($query, $resource_namespace, $columns, $data);
```
JSON Resource:
```php
public function toArray($request)
{
    $data = config('bulkexportcsv.data');
    $user = $data['user'];
    $request = $data['request'];

    return [
        'name' => $this->name,
        'email' => $this->email,
        'service_provider' => $this->when($user->role == 'admin', $this->serviceProvider->org_name??"-"),
        'contact_number' => $request['contact_number'],
    ];
}
```
Make sure to restart queue workers, if one does changes in json resource.

## Installation in LUMEN

Install **BulkExportCSV**:

```bash
composer require akki/bulkexportcsv
```

Service provider should be registered manually as follow in `bootstrap/app.php` with enabling some additional required options:
```php
// regiser service provider
$app->register(Akshay\BulkExportCSV\ServiceProvider::class);
// Enable Facades
$app->withFacades();
// Enable Eloquent
$app->withEloquent();
// Enable bulk export configuration
$app->configure('bulkexportcsv');
// BulkExportCSV class alias
if (!class_exists('BulkExportCSV')) {
    class_alias('Akshay\\BulkExportCSV\\Facades\\BulkExport', 'BulkExportCSV');
}
// EloquentSerialize class alias
if (!class_exists('EloquentSerialize')) {
    class_alias('AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade', 'EloquentSerialize');
}
```

If one gets error `'ReflectionException' with message 'Class path.storage does not exist'`,
declare storage folder path in `bootstrap/app.php` right after `$app` definition:
```php
$app = new Laravel\Lumen\Application(
    dirname(__DIR__)
);
// declare path to storage folder
$app->instance('path.storage', app()->basePath() . DIRECTORY_SEPARATOR . 'storage');
```

If one gets error `Target [Illuminate\Contracts\Routing\ResponseFactory] is not instantiable`,
add this in `AppServiceProvider.php`:
```php
public function register()
{
    $this->app->singleton(\Illuminate\Contracts\Routing\ResponseFactory::class, function() {
        return new \Laravel\Lumen\Http\ResponseFactory();
    });
}
```

Copy the required files:
```bash
mkdir -p config
cp vendor/akki/bulkexportcsv/src/config/bulkexportcsv.php config/bulkexportcsv.php
cp vendor/akki/bulkexportcsv/src/Models/BulkExportCSV.txt app/Models/BulkExportCSV.php
cp vendor/akki/bulkexportcsv/src/database/migrations/create_bulk_export_csv_table.txt database/migrations/2023_01_01_000000_create_bulk_export_csv_table.php
```

copy `queue:table`, `queue:batches-table` from laravel itself, migrate the tables:
```bash
php artisan migrate
```

Now you can follow the same [Usage](https://github.com/AkshayGadekar/bulkExportCSV#usage).

## Contribution

You can contribute to this package by discovering bugs and opening issues. Please, add to which version of package you create pull request or issue. (e.g. [1.0.0] Fatal error on `build` method)
