<?php

namespace App\Events;

use App\Models\JaspestEnquiry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast a minimal, non-sensitive notification to authenticated JASPEST admins.
 * ShouldBroadcastNow keeps the booking request independent from a queue worker.
 */
class JaspestEnquiryCreated implements ShouldBroadcastNow
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
        return 'jaspest.enquiry.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->enquiry->getKey(),
            'reference' => $this->enquiry->reference,
            'need' => $this->enquiry->need,
            'property_type' => $this->enquiry->property_type,
            'status' => $this->enquiry->status,
            'created_at' => $this->enquiry->created_at?->toIso8601String(),
        ];
    }
}
