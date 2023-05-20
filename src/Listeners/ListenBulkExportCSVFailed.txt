<?php

namespace App\Listeners;

use App\Events\BulkExportCSVFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ListenBulkExportCSVFailed
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
     * @param  \App\Events\BulkExportCSVFailed  $event
     * @return void
     */
    public function handle(BulkExportCSVFailed $event)
    {
        $bulkExportModal = $event->bulkExportModal;
        $error = $bulkExportModal->error;
        //
    }
}
