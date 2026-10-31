<?php

namespace App\Events;

use App\Models\JaspestEnquiry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Broadcast a minimal booking-status update to authenticated JASPEST admins. */
class JaspestEnquiryUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public JaspestEnquiry $enquiry)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('jaspest.admin')];
    }

    public function broadcastAs(): string
    {
        return 'jaspest.enquiry.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->enquiry->getKey(),
            'reference' => $this->enquiry->reference,
            'status' => $this->enquiry->status,
            'updated_at' => $this->enquiry->updated_at?->toIso8601String(),
        ];
    }
}
