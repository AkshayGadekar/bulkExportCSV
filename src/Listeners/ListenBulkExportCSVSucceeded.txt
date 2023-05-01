<?php

namespace App\Listeners;

use App\Events\BulkExportCSVSucceeded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ListenBulkExportCSVSucceeded
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
     * @param  \App\Events\BulkExportCSVSucceeded  $event
     * @return void
     */
    public function handle(BulkExportCSVSucceeded $event)
    {
        $bulkExportModal = $event->bulkExportModal;
        //
    }
}
