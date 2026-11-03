<?php

namespace App\Services;

use App\Models\JaspestContentItem;
use App\Models\JaspestEnquiry;
use App\Models\JaspestWhatsappNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class JaspestFonnteService
{
    /**
     * Send one Deal Order confirmation through Fonnte. Initial booking
     * Follow-up is handled manually by the admin through a wa.me link.
     */
    public function sendDealOrderConfirmation(
        JaspestEnquiry $enquiry,
        JaspestWhatsappNotification $notification,
        bool $manualRetry = false,
    ): JaspestWhatsappNotification {
        if ($enquiry->status !== 'converted') {
            return $notification->fresh();
        }

        return $this->sendNotification(
            $enquiry,
            $notification,
            'deal_order_confirmation',
            ['converted'],
            $this->buildDealOrderMessage($enquiry),
            $manualRetry,
        );
    }

    /**
     * Idempotent Fonnte transport for Deal Order confirmation notifications.
     *
     * @param array<int, string> $allowedStatuses
     */
    private function sendNotification(
        JaspestEnquiry $enquiry,
        JaspestWhatsappNotification $notification,
        string $notificationType,
        array $allowedStatuses,
        string $message,
        bool $manualRetry,
    ): JaspestWhatsappNotification {
        if ($notification->notification_type !== $notificationType) {
            return $notification->fresh();
        }

        if (! $enquiry->consent) {
            $notification->forceFill([
                'status' => 'skipped_consent',
                'last_error' => 'Notifikasi tidak dikirim karena pelanggan belum memberikan persetujuan untuk dihubungi.',
                'failed_at' => null,
            ])->save();

            return $notification->fresh();
        }

        $recipient = $this->normalizeIndonesianMobile((string) $enquiry->whatsapp);
        if ($recipient === null) {
            $notification->forceFill([
                'status' => 'skipped_invalid_number',
                'last_error' => 'Nomor WhatsApp pelanggan tidak valid. Perbaiki nomor pada sumber booking sebelum mengirim notifikasi.',
                'failed_at' => null,
            ])->save();

            return $notification->fresh();
        }

        $token = trim((string) config('services.fonnte.token'));
        if ($token === '') {
            $notification->forceFill([
                'status' => 'not_configured',
                'last_error' => 'Token Fonnte belum dikonfigurasi. Isi FONNTE_TOKEN pada file .env, lalu gunakan tombol Kirim Ulang di detail booking.',
                'failed_at' => null,
            ])->save();

            return $notification->fresh();
        }

        // Lock the enquiry and notification before contacting the provider.
        // This prevents duplicate sends and avoids messaging a booking that was
        // converted/lost while the post-response callback was waiting to run.
        $locked = DB::transaction(function () use (
            $enquiry,
            $notification,
            $notificationType,
            $allowedStatuses,
            $recipient,
            $message,
            $manualRetry,
        ): ?JaspestWhatsappNotification {
            $row = JaspestWhatsappNotification::query()
                ->lockForUpdate()
                ->findOrFail($notification->id);

            if ($row->notification_type !== $notificationType) {
                return null;
            }

            $currentEnquiry = JaspestEnquiry::query()
                ->lockForUpdate()
                ->findOrFail($row->enquiry_id);

            if (! in_array($currentEnquiry->status, $allowedStatuses, true)) {
                return null;
            }

            if (in_array($row->status, ['accepted', 'sent', 'sending'], true)) {
                return null;
            }

            if ($row->status === 'unknown') {
                // A network timeout may happen after Fonnte accepted a message.
                // Never resend automatically because it could duplicate a chat.
                return null;
            }

            if (! in_array($row->status, ['queued', 'failed', 'not_configured'], true)) {
                return null;
            }

            if (! $manualRetry && $row->attempts > 0 && $row->status !== 'queued') {
                return null;
            }

            $row->forceFill([
                'recipient' => $recipient,
                'message' => $message,
                'status' => 'sending',
                'attempts' => $row->attempts + 1,
                'provider_message_id' => null,
                'provider_request_id' => null,
                'provider_status' => null,
                'provider_state' => null,
                'provider_state_id' => null,
                'provider_response' => null,
                'accepted_at' => null,
                'sent_at' => null,
                'last_error' => null,
                'failed_at' => null,
            ])->save();

            return $row->fresh();
        });

        if (! $locked) {
            return $notification->fresh();
        }

        try {
            $response = Http::asForm()
                ->withHeaders(['Authorization' => $token])
                ->connectTimeout(5)
                ->timeout((int) config('services.fonnte.timeout', 20))
                ->post((string) config('services.fonnte.endpoint', 'https://api.fonnte.com/send'), [
                    'target' => $recipient,
                    'message' => $message,
                    // The number is already normalized to 62; disable provider-side rewriting.
                    'countryCode' => '0',
                    'connectOnly' => true,
                    'typing' => false,
                ]);
        } catch (Throwable $exception) {
            report($exception);
            $locked->forceFill([
                'status' => 'unknown',
                'last_error' => 'Koneksi ke Fonnte terputus sebelum status pengiriman dapat dipastikan. Periksa riwayat pesan di dashboard Fonnte sebelum mengirim ulang agar tidak terjadi pesan ganda.',
                'provider_response' => ['exception' => class_basename($exception)],
                'failed_at' => now(),
            ])->save();

            return $locked->fresh();
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            $payload = ['raw_response' => Str::limit($response->body(), 1500)];
        }

        $providerAccepted = $response->successful()
            && (($payload['status'] ?? false) === true
                || ($payload['status'] ?? null) === 1
                || ($payload['status'] ?? null) === 'true');

        $locked->refresh();
        if ($locked->status === 'sent' || ($locked->status === 'failed' && $locked->provider_status !== null)) {
            return $locked;
        }

        if (! $providerAccepted) {
            $providerMessage = (string) ($payload['detail'] ?? $payload['reason'] ?? $payload['message'] ?? 'Fonnte menolak permintaan pengiriman.');
            $locked->forceFill([
                'status' => 'failed',
                'provider_status' => isset($payload['process']) ? Str::lower((string) $payload['process']) : null,
                'provider_request_id' => isset($payload['requestid']) ? (string) $payload['requestid'] : null,
                'provider_response' => $payload,
                'last_error' => Str::limit($providerMessage, 1500),
                'failed_at' => now(),
            ])->save();

            return $locked->fresh();
        }

        $messageIds = $payload['id'] ?? [];
        $messageId = is_array($messageIds) ? ($messageIds[0] ?? null) : $messageIds;

        $locked->forceFill([
            'status' => 'accepted',
            'provider_message_id' => $messageId !== null ? (string) $messageId : null,
            'provider_request_id' => isset($payload['requestid']) ? (string) $payload['requestid'] : null,
            'provider_status' => isset($payload['process']) ? Str::lower((string) $payload['process']) : 'pending',
            'provider_response' => $payload,
            'last_error' => null,
            'accepted_at' => now(),
            'failed_at' => null,
        ])->save();


        return $locked->fresh();
    }

    public function buildDealOrderMessage(JaspestEnquiry $enquiry): string
    {
        $serviceTitle = JaspestContentItem::query()
            ->where('type', 'services')
            ->where('slug', $enquiry->need)
            ->value('title');

        $serviceTitle = $serviceTitle ?: Str::of((string) $enquiry->need)
            ->replace(['-', '_'], ' ')
            ->title()
            ->toString();

        $scheduledDate = $enquiry->scheduled_date
            ? Carbon::parse($enquiry->scheduled_date)->locale('id')->translatedFormat('d F Y')
            : '-';

        $recipientName = trim((string) ($enquiry->company_name ?: $enquiry->full_name));
        $price = 'Rp. '.number_format((float) $enquiry->order_price, 0, ',', '.');
        $area = number_format((float) $enquiry->order_area, 2, ',', '.');

        return implode("\n", [
            'Halo Bapak/Ibu *'.$recipientName.'* 👋',
            '',
            'Terima kasih telah mempercayakan kebutuhan pest control Anda kepada *CV Jaya Abadi Solusi (JASPEST)*.',
            '',
            'Dengan senang hati kami informasikan bahwa pesanan Anda telah *berhasil dikonfirmasi*.',
            '',
            '*DETAIL PESANAN*',
            'Nomor Order: *'.$enquiry->reference.'*',
            'Layanan: '.$serviceTitle,
            'Luasan: '.$area.' m²',
            'Harga Kesepakatan: *'.$price.'*',
            'Tanggal Pelaksanaan: '.$scheduledDate,
            'Teknisi: '.($enquiry->assigned_technician_name ?: '-'),
            '',
            'Tim kami akan mempersiapkan kebutuhan operasional untuk pelaksanaan pekerjaan tersebut.',
            'Apabila terdapat pertanyaan atau perubahan jadwal, silakan membalas pesan ini.',
            '',
            'Terima kasih atas kepercayaan Anda.',
            '',
            '*CV JAYA ABADI SOLUSI*',
            '*JASPEST — Professional Pest Control*',
        ]);
    }


    public function normalizeIndonesianMobile(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return preg_match('/^628[1-9][0-9]{7,11}$/', $digits) ? $digits : null;
    }
}
