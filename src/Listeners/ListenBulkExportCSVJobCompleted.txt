<?php

namespace App\Listeners;

use App\Events\BulkExportCSVJobCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ListenBulkExportCSVJobCompleted
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
     * @param  \App\Events\BulkExportCSVJobCompleted  $event
     * @return void
     */
    public function handle(BulkExportCSVJobCompleted $event)
    {
        $bulkExportModal = $event->bulkExportModal;
        //
    }
}
