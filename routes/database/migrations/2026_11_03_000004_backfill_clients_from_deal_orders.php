<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Create client profiles and links for Deal Orders that existed before
     * automatic synchronization was enabled. WhatsApp is the deduplication key.
     */
    public function up(): void
    {
        DB::table('jaspest_enquiries')
            ->where('status', 'converted')
            ->orderBy('id')
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    $normalized = $this->normalizeWhatsapp((string) ($order->whatsapp ?? ''));
                    if (! preg_match('/^628[1-9][0-9]{7,11}$/', $normalized)) {
                        // Invalid legacy contact data is intentionally not turned
                        // into a false client identity. The admin API reports it.
                        continue;
                    }

                    DB::transaction(function () use ($order, $normalized): void {
                        $existingLink = DB::table('jaspest_client_enquiry_links')
                            ->where('enquiry_id', $order->id)
                            ->first();
                        if ($existingLink) {
                            return;
                        }

                        $client = DB::table('jaspest_clients')
                            ->where('whatsapp_normalized', $normalized)
                            ->first();

                        if ($client) {
                            $clientId = (int) $client->id;
                        } else {
                            do {
                                $clientCode = 'KLI-'.Str::upper(Str::random(10));
                            } while (DB::table('jaspest_clients')->where('client_code', $clientCode)->exists());

                            $now = now();
                            $clientId = (int) DB::table('jaspest_clients')->insertGetId([
                                'client_code' => $clientCode,
                                'full_name' => (string) $order->full_name,
                                'company_name' => $order->company_name,
                                'whatsapp' => (string) $order->whatsapp,
                                'whatsapp_normalized' => $normalized,
                                'email' => null,
                                'address' => $order->address,
                                'status' => 'active',
                                'notes' => null,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }

                        DB::table('jaspest_client_enquiry_links')->insertOrIgnore([
                            'client_id' => $clientId,
                            'enquiry_id' => (int) $order->id,
                            'linked_by' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    });
                }
            });
    }

    /** Data backfill is intentionally not reversed; deleting generated clients
     * during rollback could destroy legitimate customer/order history. */
    public function down(): void
    {
        // No-op by design.
    }

    private function normalizeWhatsapp(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }
        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }
};
