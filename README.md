# Export Unlimited Records into CSV
<a href="https://packagist.org/packages/akki/bulkexportcsv"><img src="https://img.shields.io/packagist/v/akki/bulkexportcsv" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/akki/bulkexportcsv"><img src="https://img.shields.io/packagist/l/akki/bulkexportcsv" alt="License"></a>

## Introduction

This package will help you in exporting unlimited data using laravel's eloquent query and json resource. It is based on queue batching of laravel. It supports all databases eloquent supports. 

## Installation

Install **BulkExportCSV**:

```bash
composer require akki/bulkexportcsv
```

Publish the config file `config/bulkexportcsv.php`, model `App\Models\BulkExportCSV.php` and migration `bulk_export_csv` table:

```bash
php artisan vendor:publish --provider="Akki\BulkExportCSV\ServiceProvider"
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
`build` method will start the export CSV process. 
```php
$query = \App\Models\User::query();
$query->with('serviceProvider')->where('country', 'IN');

$resource_namespace = 'App\Http\Resources\UserResource';

$bulkExportCSV = \BulkExportCSV::build($query, $resource_namespace);
```
`build` method returns bulk export configuration used for export CSV, it also gives records_count (total number of records to export), jobs_id (unique ID generated for an export request) and batch_id (batch_id of job batching). Once export csv process starts, you will see its entry in `bulk_export_csv` table, that entry will continously update itself until export csv gets completed, cancelled or fails. You can use `jobs_id` to get that entry using query, and broadcast the infromation on your frontend.   
But, Before exporting into CSV, Make sure to fill up `config/bulkexportcsv.php` correctly which is shown below.

### Configuration
Edit `config/bulkexportcsv.php` to suit your needs.

```php
/** @file config/bulkexportcsv.php */

return [
    /*
    * Number of Records to be fetched per job
    */
    'records_per_job' => 10000,

    /*
    * records will be fetched in chunks for better performance
    */
    'chunks_of_records_per_job' => 2,

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
    'queue_connection' => env('QUEUE_CONNECTION', 'database'),

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
```

### Method to call on CSV success or failure 
From `config/bulkexportcsv.php`, methods mentioned at 'call_on_csv_success' and 'call_on_csv_failure' will be called. If CSV gets prepared successfully 'call_on_csv_success' method will be called, on failure 'call_on_csv_failure' will be called, Methods will receive bulk export configuration as only parameter. 
```php
class BulkExportCSV extends Model
{   
    
    ................
    ................


    public function handleCSV($bulkExportConfig)
    {
        $csv_path = $bulkExportConfig->csv_path;
        ................
    }

    public function handleFailedCSV($bulkExportConfig)
    {
        $csv_path = $bulkExportConfig->csv_path; //CSV may not exist if 'delete_csv_if_job_failed' mention in configuration is true
        $error = \App\Models\BulkExportCSV::where('jobs_id', $bulkExportConfig->jobs_id)->first()->error;
        //If job was failed, error will be "Jobs Exception: ......."
        //If this method itself thrown an exception error will be "Method Exception: ......."
        ................
    }

}
```
`$bulkExportConfig` in above methods has all values from `config/bulkexportcsv.php` which were used to export CSV, it also has jobs_id (unique ID generated for an export request), records_count (total number of records exported), batch_id (batch_id of job batching), csv_path (path of CSV). One then can take CSV and upload it to s3 or email it to user as per requirement.

### bulk_export_csv table 
When CSV gets prepared, you can access its status using published "bulk_export_csv" table which has following columns:
```php
[
    'jobs_id' => unique ID generated for an export request
    'csv_name' => CSV file name
    'total_records' => total number of records exported
    'total_jobs' => total jobs required to export CSV
    'completed_jobs' => when export CSV starts, this column gets updated with number of completed jobs
    'progress' => the completion percentage of the CSV export
    'export_status' => export status of CSV as 'InProgress', 'Completed', 'Error' or 'Cancelled'
    'each_jobs_time' => time taken by each job processed
    'average_jobs_time' => average time all jobs taken
    'error' => Exception error if any job fails or 'call_on_csv_success' or 'call_on_csv_failure' methods threw exception
    'config' => bulk export configuration used for an export request
    'batch_id' => batch_id of job batching process
]
```

### Queue Configuration
Make sure you have filled up `config/queue.php` correctly. Install [Supervisor](https://laravel.com/docs/8.x/queues#supervisor-configuration), in its configuration file, command must mention queue name used for bulkExportCSV. For example, in `config/bulkexportcsv.php` if `queue` name is `bulkExportCSV` then command must be:
```bash
php artisan queue:work --queue=bulkExportCSV,default
```
Of course, You can specify which queues queue worker should process by priority depending on your needs.

## More Options in 'build' method of 'BulkExportCSV' 
### Define Columns for Export CSV
By default, package takes columns names from json resource itself. But one can define custom columns as required:
```php
$columns = ['Name', 'Email', 'Service Provider', 'Contact Number'];
$bulkExportCSV = \BulkExportCSV::build($query, $resource_namespace, $columns);
```

### Access Request Data in Resource
Often times, we need authenticated user data or request data in json resource. As export CSV happens in background, there is no access to request, but one can send data to json resource or even eloquent model accessors or in `call_on_csv_success`, `call_on_csv_failure` methods by using `config('bulkexportcsv.data')`:
```php
$user = auth()->user();
$data = ['user' => $user, 'request' => $request->all()];
$columns = []; //if columns are defined as empty, then columns will be taken from json resource itself
$bulkExportCSV = \BulkExportCSV::build($query, $resource_namespace, $columns, $data);
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

## Extra Methods
Package uses `Akki\BulkExportCSV\Models\BulkExportCSVModel` model to access "bulk_export_csv" table. This model extends from published `App\Models\BulkExportCSV` model.

### findByJobsId 
To fetch record from "bulk_export_csv" table using jobs_id, one can use `findByJobsId` method:
```php
use Akki\BulkExportCSV\Models\BulkExportCSVModel;

$bulkExportCSVInfo = BulkExportCSVModel::findByJobsId($jobs_id);
```

### cancelExportCSVProcess
To cancel ongoing export csv process, one can use `cancelExportCSVProcess` method:
```php
use Akki\BulkExportCSV\Models\BulkExportCSVModel;

BulkExportCSVModel::cancelExportCSVProcess($jobs_id);
```


## Installation in LUMEN

Install **BulkExportCSV**:

```bash
composer require akki/bulkexportcsv
```

Service provider should be registered manually as follow in `bootstrap/app.php` with enabling some additional required options:
```php
// regiser service provider
$app->register(Akki\BulkExportCSV\ServiceProvider::class);
// Enable Facades
$app->withFacades();
// Enable Eloquent
$app->withEloquent();
// Enable bulk export configuration
$app->configure('bulkexportcsv');
// BulkExportCSV class alias
if (!class_exists('BulkExportCSV')) {
    class_alias('Akki\\BulkExportCSV\\Facades\\BulkExport', 'BulkExportCSV');
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

Now you can follow the same [Usage](https://github.com/AkshayGadekar/bulkExportCSV#usage) mentioned for Laravel above.

## Contribution

You can contribute to this package by discovering bugs and opening issues. Please, add to which version of package you create pull request or issue. (e.g. [1.0.0] Fatal error on `build` method)
