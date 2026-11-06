<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Insert the technician roster supplied by the owner into the active
     * Laravel database connection. Existing records are matched by email and
     * updated by name only; operational status and assignments are preserved.
     */
    public function up(): void
    {
        if (! Schema::hasTable('jaspest_technicians')) {
            throw new \RuntimeException(
                'Tabel jaspest_technicians belum tersedia. Pastikan migrasi Data Teknisi dijalankan terlebih dahulu.'
            );
        }

        if (! Schema::hasColumn('jaspest_technicians', 'email')) {
            throw new \RuntimeException(
                'Kolom email belum tersedia pada jaspest_technicians. Jalankan pembaruan struktur Data Teknisi terlebih dahulu.'
            );
        }

        $technicians = [
            ['name' => 'MOCH. GANANG S', 'email' => 'mochganang016@gmail.com'],
            ['name' => 'FATCHUL QORIB', 'email' => 'fatchulqorib@gmail.com'],
            ['name' => 'M. AYUHAN', 'email' => 'al.juan.yakuza@gmail.com'],
            ['name' => 'NURHADI', 'email' => 'qierorahisa@gmail.com'],
            ['name' => 'Rianto', 'email' => 'riantoahmad914@gmail.com'],
            ['name' => 'Rizal Hidayat', 'email' => 'rizalhdyt28@gmail.com'],
            ['name' => 'Widiasih', 'email' => 'widiasih2707@gmail.com'],
            ['name' => 'Rachmad Bagus P', 'email' => 'gandromax723@gmail.com'],
            ['name' => 'Ahmad Ridho', 'email' => 'ridhoreyhansa@gmail.com'],
            ['name' => 'M. Badri', 'email' => 'romy.hansem@gmail.com'],
            ['name' => 'Soukma Dwi P', 'email' => 'londoo420@gmail.com'],
            ['name' => 'Rendy', 'email' => 'leonardorendy36@gmail.com'],
        ];

        DB::transaction(function () use ($technicians): void {
            foreach ($technicians as $row) {
                $email = mb_strtolower(trim($row['email']));
                $existing = DB::table('jaspest_technicians')
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->first();

                if ($existing) {
                    $updates = [];
                    if (($existing->full_name ?? null) !== $row['name']) {
                        $updates['full_name'] = $row['name'];
                    }
                    if (($existing->email ?? null) !== $email) {
                        $updates['email'] = $email;
                    }

                    if ($updates !== []) {
                        $updates['updated_at'] = now();
                        DB::table('jaspest_technicians')->where('id', $existing->id)->update($updates);
                    }

                    // Never reset operational_status, current assignments, stock, or history.
                    continue;
                }

                $codeSeed = 'JASPEST-TECHNICIAN-'.$email;
                $code = 'TKN-'.strtoupper(substr(hash('sha256', $codeSeed), 0, 12));
                $attempt = 0;
                while (DB::table('jaspest_technicians')->where('technician_code', $code)->exists()) {
                    $attempt++;
                    $code = 'TKN-'.strtoupper(substr(hash('sha256', $codeSeed.'-'.$attempt), 0, 12));
                }

                DB::table('jaspest_technicians')->insert([
                    'full_name' => $row['name'],
                    'whatsapp' => null,
                    'email' => $email,
                    'address' => null,
                    'technician_code' => $code,
                    'specialization' => 'Pest Control Umum',
                    'service_area' => null,
                    'certification' => null,
                    'operational_status' => 'available',
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }, 3);
    }

    /**
     * Intentionally keep imported personnel records on rollback. These are
     * operational master data and may already be referenced by orders/history.
     */
    public function down(): void
    {
        // No destructive rollback for personnel master data.
    }
};
