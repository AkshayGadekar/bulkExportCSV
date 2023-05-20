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

Publish the config file `config/bulkexportcsv.php`, model `App\Models\BulkExportCSV.php`, migration `bulk_export_csv` table, events and listeners:

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
Prepare an eloquent query, make sure query does not have get(), first(), skip(), limit() methods. By Default package will export all records query gives. 
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
`build` method returns `BulkExportCSV` modal which is pointed to published `bulk_export_csv` table, it gives all information regarding CSV request. If data to export is less, one can use [download](https://github.com/AkshayGadekar/bulkExportCSV/tree/develop#download-csv) method.   
But, Before exporting into CSV using `build` method, Make sure to fill up `config/bulkexportcsv.php` correctly which is shown below.

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
    'dir' => 'app/public/exportCSV',

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

### Events 
When CSV starts to get prepared throught queue jobs, these are typical events that happens: CSV starts to get prepared => Queue jobs gets completed => Queue batching has completed and CSV is prepared successfully or Queue batching has stopped because of exception.
To handle this each event, package publishes events and their respective listeners:
1) BulkExportCSVStarted: This event gets triggered when CSV starts to get prepared
2) BulkExportCSVJobCompleted: This event gets triggered for each queue job when that particular job gets completed.
3) BulkExportCSVSucceeded: This event gets triggered when CSV gets perpared successfully, i.e. queue batching has successfully completed.
4) BulkExportCSVFailed: This event gets triggered when any particular queue job throws an exception and so stops queue batching process, so CSV does not get prepared successfully.    
Each of these events gets `BulkExportCSV` modal as a parameter. You can broadcast this events, we recommend using `ShouldBroadcastNow` interface so event gets broadcast in sync with queue jobs.

### bulk_export_csv table 
When CSV starts to get prepared, you can access its current status using published "bulk_export_csv" table which has following columns. `BulkExportCSV` modal points this this table:
```php
[
    'jobs_id' => unique ID generated for an export CSV request
    'csv_name' => CSV file name
    'total_records' => total number of records exported
    'total_jobs' => total jobs required to export CSV
    'completed_jobs' => when export CSV starts, this column gets updated with number of completed jobs
    'progress' => the completion percentage of the CSV export
    'export_status' => export status of CSV as 'InProgress', 'Completed', 'Error' or 'Cancelled'
    'each_jobs_time' => time taken by each job processed
    'average_jobs_time' => average time all jobs taken
    'error' => Exception error if any job fails or 'BulkExportCSVSucceeded' or 'BulkExportCSVFailed' events threw exception
    'config' => bulk export configuration used for an export CSV request
    'user_id' => ID of auth user requesting export CSV
    'batch_id' => batch_id of job batching process
]
```

### Queue Configuration
Make sure you have filled up `config/queue.php` correctly. Install [Supervisor](https://laravel.com/docs/8.x/queues#supervisor-configuration), in its configuration file, command must mention queue name used for bulkExportCSV. For example, in `config/bulkexportcsv.php` if `queue` name is `bulkExportCSV` then command must be:
```bash
php artisan queue:work --queue=bulkExportCSV,default
```
Of course, You can specify which queues queue worker should process by priority depending on your needs. If you are broadcasting events using `ShouldBroadcast` interface make sure to add its queue name here too on which it is broadcasting.

## More Options in 'build' method of 'BulkExportCSV' 
### Define Columns for Export CSV
By default, package takes columns names from json resource itself. But one can define custom columns as required:
```php
$columns = ['Name', 'Email', 'Service Provider', 'Contact Number'];
$bulkExportCSV = \BulkExportCSV::build($query, $resource_namespace, $columns);
```

### Access Request Data in Resource
Often times, we need authenticated user data or request data in json resource. As export CSV happens in background, there is no access to request, but one can send data to json resource or even eloquent model accessors or event listeners by using `config('bulkexportcsv.data')`:
```php
$user = auth()->user();
$data = ['user' => $user, 'request' => $request->all(), 'csv_info' => 'Export Users'];
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
`csv_info` key in data array is specifically accessible on `BulkExportCSV` model as `$bulkExportModal->config->csv_info`.
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

## Download CSV
If one wants to download CSV directly instead of going though queue job process, then use `download` method:
```php
return \BulkExportCSV::download($query, $resource_namespace);
```
Here, one can also pass `Columns` and `Data` parameters similar to `build` method. `download` method creates CSV on the fly i.e. without writing CSV on the server disk and returns downloadable CSV file to the browser. In frontend side, to force browser to download CSV directly, you need to let browser call the API, you can use `location.href` for it. If one prefers to call API from AJAX then in response `download` method gives content of CSV, so in frontend one can make CSV using blob.

If one is to use `download` method only, then there is no need of any configuration. One can use `build` and `download` method based on their prefer choice, if data to export is huge which one can know using `count()` method on eloquent, then better to go with `build` method otherwise `download` method can also be right choice. 

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
cp vendor/akki/bulkexportcsv/src/Events/BulkExportCSVStarted.txt app/Events/BulkExportCSVStarted.php
cp vendor/akki/bulkexportcsv/src/Events/BulkExportCSVJobCompleted.txt app/Events/BulkExportCSVJobCompleted.php
cp vendor/akki/bulkexportcsv/src/Events/BulkExportCSVSucceeded.txt app/Events/BulkExportCSVSucceeded.php
cp vendor/akki/bulkexportcsv/src/Events/BulkExportCSVFailed.txt app/Events/BulkExportCSVFailed.php
cp vendor/akki/bulkexportcsv/src/Listeners/ListenBulkExportCSVStarted.txt app/Listeners/ListenBulkExportCSVStarted.php
cp vendor/akki/bulkexportcsv/src/Listeners/ListenBulkExportCSVJobCompleted.txt app/Listeners/ListenBulkExportCSVJobCompleted.php
cp vendor/akki/bulkexportcsv/src/Listeners/ListenBulkExportCSVSucceeded.txt app/Listeners/ListenBulkExportCSVSucceeded.php
cp vendor/akki/bulkexportcsv/src/Listeners/ListenBulkExportCSVFailed.txt app/Listeners/ListenBulkExportCSVFailed.php
```

copy `queue:table`, `queue:batches-table` from laravel itself, migrate the tables:
```bash
php artisan migrate
```

Now you can follow the same [Usage](https://github.com/AkshayGadekar/bulkExportCSV#usage) mentioned for Laravel above.

## Learning Resource
There is a video series available on youtube to know how to use this package,
<a href="https://www.youtube.com/watch?v=NacyPrRzxeQ&list=PLlE-aom9wrdk7096yCUYh9t-ob1pY_QYp">Click Here</a>.


## Contribution
You can contribute to this package by discovering bugs and opening issues. Please, add to which version of package you create pull request or issue. (e.g. [1.0.0] Fatal error on `build` method)
