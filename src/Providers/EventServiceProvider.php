<?php

namespace Akki\BulkExportCSV\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        "App\Events\BulkExportCSVStarted" => [
            "App\Listeners\ListenBulkExportCSVStarted",
        ],
        "App\Events\BulkExportCSVJobCompleted" => [
            "App\Listeners\ListenBulkExportCSVJobCompleted",
        ],
        'App\Events\BulkExportCSVSucceeded' => [
            'App\Listeners\ListenBulkExportCSVSucceeded',
        ],
        'App\Events\BulkExportCSVFailed' => [
            'App\Listeners\ListenBulkExportCSVFailed',
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
        parent::boot();
    }
}
