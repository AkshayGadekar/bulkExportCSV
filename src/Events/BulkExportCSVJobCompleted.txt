<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Akki\BulkExportCSV\Models\BulkExportCSVModel;

class BulkExportCSVJobCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $bulkExportModal;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(BulkExportCSVModel $bulkExportModal)
    {
        $this->bulkExportModal = $bulkExportModal;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }

    /**
     * The name of the queue on which to place the broadcasting job.
     */
    public function broadcastQueue()
    {
        return 'default';
    }
}
