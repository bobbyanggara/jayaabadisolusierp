<?php

namespace App\Http\Controllers;

use App\Models\JaspestWhatsappNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class JaspestFonnteWebhookController extends Controller
{
    public function updateStatus(Request $request, string $secret): JsonResponse
    {
        $expectedSecret = (string) config('services.fonnte.webhook_secret');
        abort_if($expectedSecret === '' || ! hash_equals($expectedSecret, $secret), 404);

        $payload = $request->validate([
            'id' => ['nullable', 'string', 'max:100'],
            'stateid' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'state' => ['nullable', 'string', 'max:100'],
        ]);

        $messageId = (string) ($payload['id'] ?? '');
        $stateId = (string) ($payload['stateid'] ?? '');
        if ($messageId === '' && $stateId === '') {
            return response()->json(['ok' => true, 'matched' => false]);
        }

        $notificationQuery = JaspestWhatsappNotification::query();
        if ($messageId !== '') {
            $notificationQuery->where('provider_message_id', $messageId);
        } else {
            $notificationQuery->where('provider_state_id', $stateId);
        }
        $notification = $notificationQuery->first();

        if (! $notification) {
            return response()->json(['ok' => true, 'matched' => false]);
        }

        $providerStatus = Str::lower(trim((string) ($payload['status'] ?? '')));
        $providerState = Str::lower(trim((string) ($payload['state'] ?? '')));
        $normalizedValues = array_filter([$providerStatus, $providerState]);

        $updates = [
            'provider_status' => $providerStatus !== '' ? $providerStatus : $notification->provider_status,
            'provider_state' => $providerState !== '' ? $providerState : $notification->provider_state,
            'provider_state_id' => $stateId !== '' ? $stateId : $notification->provider_state_id,
        ];

        if (array_intersect($normalizedValues, ['failed', 'invalid', 'expired', 'url unreachable', 'error']) !== []) {
            $updates['status'] = 'failed';
            $updates['last_error'] = 'Fonnte melaporkan status pengiriman: '.($providerState ?: $providerStatus);
            $updates['failed_at'] = now();
        } elseif (array_intersect($normalizedValues, ['sent', 'delivered', 'read', 'received', 'success']) !== []) {
            $updates['status'] = 'sent';
            $updates['sent_at'] = $notification->sent_at ?: now();
            $updates['last_error'] = null;
            $updates['failed_at'] = null;
        } elseif (array_intersect($normalizedValues, ['pending', 'processing', 'waiting', 'queued']) !== []) {
            // Do not downgrade a message already reported as sent.
            if ($notification->status !== 'sent') {
                $updates['status'] = 'accepted';
            }
        }

        $notification->forceFill($updates)->save();

        return response()->json(['ok' => true, 'matched' => true]);
    }
}
