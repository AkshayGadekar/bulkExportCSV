<?php

namespace App\Listeners;

use App\Events\BulkExportCSVStarted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ListenBulkExportCSVStarted
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\BulkExportCSVStarted  $event
     * @return void
     */
    public function handle(BulkExportCSVStarted $event)
    {
        $bulkExportModal = $event->bulkExportModal;
        //
    }
}
