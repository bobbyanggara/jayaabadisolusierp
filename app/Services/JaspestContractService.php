<?php

namespace App\Services;

use App\Models\JaspestContract;
use App\Models\JaspestContractVisit;
use App\Models\JaspestContentItem;
use App\Models\JaspestEnquiry;
use App\Models\JaspestTechnician;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aturan kontrak berkala: kontrak menentukan periode (mingguan/bulanan/tahunan) dan
 * kuota kunjungan per periode. Slot kunjungan dibuat otomatis, lalu admin mengisi
 * tanggal dan teknisi di dalam periode masing-masing.
 */
class JaspestContractService
{
    public const TZ = 'Asia/Jakarta';

    public const PERIODS = [
        'weekly' => ['label' => 'Mingguan', 'unit' => 'Minggu', 'max_visits' => 14, 'max_periods' => 104],
        'monthly' => ['label' => 'Bulanan', 'unit' => 'Bulan', 'max_visits' => 31, 'max_periods' => 60],
        'yearly' => ['label' => 'Tahunan', 'unit' => 'Tahun', 'max_visits' => 120, 'max_periods' => 10],
    ];

    public const MAX_TOTAL_VISITS = 1000;

    public static function today(): string
    {
        return Carbon::now(self::TZ)->format('Y-m-d');
    }

    /** Awal periode ke-$index (0-based), selalu dihitung dari tanggal mulai kontrak. */
    public function periodStart(Carbon $start, string $type, int $index): Carbon
    {
        $base = $start->copy()->startOfDay();

        return match ($type) {
            'weekly' => $base->addWeeks($index),
            'monthly' => $base->addMonthsNoOverflow($index),
            'yearly' => $base->addYearsNoOverflow($index),
            default => $base,
        };
    }

    public function periodEnd(Carbon $start, string $type, int $index): Carbon
    {
        return $this->periodStart($start, $type, $index + 1)->subDay();
    }

    public function assertPlan(string $type, int $perPeriod, int $periods, int $alreadyPlanned = 0): void
    {
        $rule = self::PERIODS[$type] ?? null;
        if (! $rule) {
            throw ValidationException::withMessages(['period_type' => 'Jenis periode kontrak tidak dikenal.']);
        }
        if ($perPeriod < 1 || $perPeriod > $rule['max_visits']) {
            throw ValidationException::withMessages([
                'visits_per_period' => 'Kunjungan per '.strtolower($rule['unit']).' harus antara 1 dan '.$rule['max_visits'].'.',
            ]);
        }
        if ($periods < 1 || $periods > $rule['max_periods']) {
            throw ValidationException::withMessages([
                'duration_periods' => 'Durasi kontrak harus antara 1 dan '.$rule['max_periods'].' '.strtolower($rule['unit']).'.',
            ]);
        }
        if ($alreadyPlanned + ($perPeriod * $periods) > self::MAX_TOTAL_VISITS) {
            throw ValidationException::withMessages([
                'duration_periods' => 'Total kunjungan kontrak tidak boleh lebih dari '.self::MAX_TOTAL_VISITS.'.',
            ]);
        }
    }

    /**
     * Dipanggil di dalam transaksi pemanggil. Membuat kontrak beserta slot kunjungan kosong.
     *
     * @param array{period_type:string,visits_per_period:int,duration_periods:int,start_date:string,price_basis:string,price_amount:float,area:?float,service:string,services?:array<int,string|array{slug:string,title?:string}>,notes:?string} $plan
     */
    public function createContract(JaspestEnquiry $enquiry, ?int $clientId, array $plan, ?JaspestTechnician $technician, ?int $userId): JaspestContract
    {
        $type = $plan['period_type'];
        $perPeriod = (int) $plan['visits_per_period'];
        $periods = (int) $plan['duration_periods'];
        $this->assertPlan($type, $perPeriod, $periods);

        $start = Carbon::parse($plan['start_date'], self::TZ)->startOfDay();
        $end = $this->periodEnd($start, $type, $periods - 1);
        $totalVisits = $perPeriod * $periods;
        $amount = round((float) $plan['price_amount'], 2);
        $total = $plan['price_basis'] === 'per_visit' ? round($amount * $totalVisits, 2) : $amount;

        // Kontrak dapat memuat beberapa layanan. Simpan snapshot judul agar nama layanan
        // historis tidak berubah ketika master Konten diperbarui atau layanan dihapus.
        $rawServices = $plan['services'] ?? [];
        if (! is_array($rawServices) || $rawServices === []) {
            $rawServices = [['slug' => (string) $plan['service']]];
        }
        $serviceSlugs = array_values(array_unique(array_filter(array_map(
            static fn ($item): string => is_array($item) ? trim((string) ($item['slug'] ?? '')) : trim((string) $item),
            $rawServices
        ))));
        if ($serviceSlugs === []) {
            $serviceSlugs = [(string) $plan['service']];
        }
        $serviceTitles = JaspestContentItem::query()
            ->where('type', 'services')->whereIn('slug', $serviceSlugs)->pluck('title', 'slug');
        $serviceSnapshots = array_map(function (string $slug) use ($serviceTitles, $rawServices): array {
            $providedTitle = null;
            foreach ($rawServices as $item) {
                if (is_array($item) && (string) ($item['slug'] ?? '') === $slug) {
                    $providedTitle = trim((string) ($item['title'] ?? '')) ?: null;
                    break;
                }
            }

            return [
                'slug' => $slug,
                'title' => $providedTitle ?: (string) ($serviceTitles[$slug] ?? \Illuminate\Support\Str::of($slug)->replace(['-', '_'], ' ')->title()->toString()),
            ];
        }, $serviceSlugs);
        $primaryService = $serviceSnapshots[0]['slug'];

        $contract = JaspestContract::create([
            'contract_number' => $this->nextNumber(),
            'enquiry_id' => $enquiry->id,
            'client_id' => $clientId,
            'service' => $primaryService,
            'services' => $serviceSnapshots,
            'period_type' => $type,
            'visits_per_period' => $perPeriod,
            'duration_periods' => $periods,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'price_basis' => $plan['price_basis'],
            'price_amount' => $amount,
            'total_price' => $total,
            'area' => $plan['area'] ?? null,
            'default_technician_id' => $technician?->id,
            'default_technician_name' => $technician?->full_name,
            'status' => 'active',
            'notes' => $plan['notes'] ?? null,
            'created_by' => $userId,
        ]);

        $this->generateSlots($contract, 0, $periods);

        return $contract;
    }

    /** Buat slot kosong untuk periode ke-$fromIndex sampai sebelum $toIndex (0-based). */
    public function generateSlots(JaspestContract $contract, int $fromIndex, int $toIndex): void
    {
        $start = Carbon::parse($contract->start_date->format('Y-m-d'), self::TZ);
        $now = now();
        $rows = [];
        for ($i = $fromIndex; $i < $toIndex; $i++) {
            $periodStart = $this->periodStart($start, $contract->period_type, $i)->format('Y-m-d');
            $periodEnd = $this->periodEnd($start, $contract->period_type, $i)->format('Y-m-d');
            for ($slot = 1; $slot <= $contract->visits_per_period; $slot++) {
                $rows[] = [
                    'contract_id' => $contract->id,
                    'period_index' => $i + 1,
                    'slot_no' => $slot,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'status' => 'unscheduled',
                    'technician_id' => $contract->default_technician_id,
                    'technician_name' => $contract->default_technician_name,
                    'is_extra' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            JaspestContractVisit::insert($chunk);
        }
    }

    private function nextNumber(): string
    {
        $prefix = 'KTR/'.Carbon::now(self::TZ)->format('Y/m').'/';
        $last = JaspestContract::query()
            ->where('contract_number', 'like', $prefix.'%')
            ->orderByDesc('contract_number')
            ->value('contract_number');
        $sequence = $last ? ((int) substr((string) $last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /** Isi atau kosongkan tanggal dan teknisi sebuah kunjungan. */
    public function schedule(JaspestContractVisit $visit, ?string $date, ?int $technicianId, ?string $notes): JaspestContractVisit
    {
        return DB::transaction(function () use ($visit, $date, $technicianId, $notes): JaspestContractVisit {
            $locked = JaspestContractVisit::query()->lockForUpdate()->findOrFail($visit->id);
            $contract = JaspestContract::query()->findOrFail($locked->contract_id);
            $this->assertContractActive($contract);
            if (! in_array($locked->status, ['unscheduled', 'scheduled'], true)) {
                throw ValidationException::withMessages(['status' => 'Kunjungan yang sudah selesai atau dilewati tidak dapat diubah. Buka kembali terlebih dahulu.']);
            }

            $technician = null;
            if ($technicianId) {
                $technician = JaspestTechnician::query()->find($technicianId);
                if (! $technician || $technician->operational_status === 'off_duty') {
                    throw ValidationException::withMessages(['technician_id' => 'Teknisi tidak tersedia. Pilih teknisi lain.']);
                }
            }

            // Kunjungan reguler boleh dijadwalkan di luar periodenya selama masih dalam masa kontrak:
            // klien kadang minta digabung di bulan ini atau digeser penuh ke bulan berikutnya.
            // Kunjungan lalu dipindah ke periode yang memuat tanggal baru.
            $movedToIndex = null;
            if ($date) {
                if (! $locked->is_extra && ($date < $locked->period_start->format('Y-m-d') || $date > $locked->period_end->format('Y-m-d'))) {
                    $contractStart = $contract->start_date->format('Y-m-d');
                    $contractEnd = $contract->end_date->format('Y-m-d');
                    if ($date < $contractStart || $date > $contractEnd) {
                        throw ValidationException::withMessages([
                            'scheduled_date' => 'Tanggal harus berada dalam masa kontrak ('.$contract->start_date->format('d-m-Y').' s/d '.$contract->end_date->format('d-m-Y').').',
                        ]);
                    }
                    $movedToIndex = $this->periodIndexForDate($contract, $date);
                    if ($movedToIndex === null) {
                        throw ValidationException::withMessages(['scheduled_date' => 'Tanggal tidak termasuk dalam periode kontrak mana pun.']);
                    }
                }
                if ($technician) {
                    $this->assertTechnicianCapacity($technician, $date, $locked->id);
                }
            }

            $newNotes = $notes !== null ? (trim($notes) ?: null) : $locked->notes;
            $attributes = [];
            if ($movedToIndex !== null) {
                $unit = self::PERIODS[$contract->period_type]['unit'] ?? 'Periode';
                $start = Carbon::parse($contract->start_date->format('Y-m-d'), self::TZ);
                $nextSlot = 1 + (int) JaspestContractVisit::query()
                    ->where('contract_id', $contract->id)->where('is_extra', false)
                    ->where('period_index', $movedToIndex + 1)->where('id', '<>', $locked->id)->max('slot_no');
                $moveNote = 'Dipindah dari '.$unit.' ke-'.$locked->period_index.' ke '.$unit.' ke-'.($movedToIndex + 1).' (jadwal '.Carbon::parse($date)->format('d-m-Y').').';
                $newNotes = trim(($newNotes ? $newNotes."\n" : '').$moveNote);
                $attributes = [
                    'period_index' => $movedToIndex + 1,
                    'slot_no' => $nextSlot,
                    'period_start' => $this->periodStart($start, $contract->period_type, $movedToIndex)->format('Y-m-d'),
                    'period_end' => $this->periodEnd($start, $contract->period_type, $movedToIndex)->format('Y-m-d'),
                ];
            }

            $locked->forceFill($attributes + [
                'scheduled_date' => $date,
                'technician_id' => $technician?->id,
                'technician_name' => $technician?->full_name,
                'status' => $date ? 'scheduled' : 'unscheduled',
                'notes' => $newNotes,
            ])->save();

            return $locked->fresh();
        }, 3);
    }

    /** Indeks periode (0-based) yang memuat tanggal tersebut, atau null bila di luar kontrak. */
    private function periodIndexForDate(JaspestContract $contract, string $date): ?int
    {
        $start = Carbon::parse($contract->start_date->format('Y-m-d'), self::TZ);
        for ($i = 0; $i < (int) $contract->duration_periods; $i++) {
            if ($date >= $this->periodStart($start, $contract->period_type, $i)->format('Y-m-d')
                && $date <= $this->periodEnd($start, $contract->period_type, $i)->format('Y-m-d')) {
                return $i;
            }
        }

        return null;
    }

    private function assertTechnicianCapacity(JaspestTechnician $technician, string $date, int $exceptVisitId): void
    {
        $limit = max(1, (int) config('jaspest_admin.max_visits_per_technician_per_day', 3));
        $visits = JaspestContractVisit::query()
            ->where('technician_id', $technician->id)
            ->where('scheduled_date', $date)
            ->whereIn('status', ['scheduled', 'done'])
            ->where('id', '<>', $exceptVisitId)
            ->count();
        $oneOff = JaspestEnquiry::query()
            ->where('assigned_technician_id', $technician->id)
            ->where('status', 'converted')
            ->where('scheduled_date', $date)
            ->count();

        if (($visits + $oneOff) >= $limit) {
            throw ValidationException::withMessages([
                'technician_id' => $technician->full_name.' sudah memiliki '.($visits + $oneOff).' jadwal pada tanggal tersebut (batas '.$limit.' per hari). Pilih tanggal atau teknisi lain.',
            ]);
        }
    }

    public function complete(JaspestContractVisit $visit, ?string $note): JaspestContractVisit
    {
        return DB::transaction(function () use ($visit, $note): JaspestContractVisit {
            $locked = JaspestContractVisit::query()->lockForUpdate()->findOrFail($visit->id);
            $contract = JaspestContract::query()->findOrFail($locked->contract_id);
            $this->assertContractActive($contract);
            if ($locked->status !== 'scheduled' || ! $locked->scheduled_date) {
                throw ValidationException::withMessages(['status' => 'Isi tanggal kunjungan terlebih dahulu sebelum menandai selesai.']);
            }
            if ($locked->scheduled_date->format('Y-m-d') > self::today()) {
                throw ValidationException::withMessages(['status' => 'Kunjungan belum dapat ditandai selesai karena tanggalnya belum tiba.']);
            }
            $locked->forceFill([
                'status' => 'done',
                'completed_at' => now(),
                'notes' => $note !== null && trim($note) !== '' ? trim($note) : $locked->notes,
            ])->save();
            $this->refreshStatus($contract);

            return $locked->fresh();
        }, 3);
    }

    public function skip(JaspestContractVisit $visit, ?string $note): JaspestContractVisit
    {
        return DB::transaction(function () use ($visit, $note): JaspestContractVisit {
            $locked = JaspestContractVisit::query()->lockForUpdate()->findOrFail($visit->id);
            $contract = JaspestContract::query()->findOrFail($locked->contract_id);
            $this->assertContractActive($contract);
            if (! in_array($locked->status, ['unscheduled', 'scheduled'], true)) {
                throw ValidationException::withMessages(['status' => 'Hanya kunjungan yang belum selesai yang dapat dilewati.']);
            }
            if ($locked->invoice_id !== null) {
                throw ValidationException::withMessages(['status' => 'Kunjungan ini sudah ditagih pada sebuah invoice dan tidak dapat dilewati agar tagihan tetap sesuai.']);
            }
            $locked->forceFill([
                'status' => 'skipped',
                'notes' => $note !== null && trim($note) !== '' ? trim($note) : $locked->notes,
            ])->save();
            $this->refreshStatus($contract);

            return $locked->fresh();
        }, 3);
    }

    /** Koreksi: kembalikan kunjungan selesai/dilewati menjadi terjadwal atau belum dijadwalkan. */
    public function reopen(JaspestContractVisit $visit): JaspestContractVisit
    {
        return DB::transaction(function () use ($visit): JaspestContractVisit {
            $locked = JaspestContractVisit::query()->lockForUpdate()->findOrFail($visit->id);
            $contract = JaspestContract::query()->findOrFail($locked->contract_id);
            if ($contract->status === 'cancelled') {
                throw ValidationException::withMessages(['status' => 'Kontrak sudah dibatalkan.']);
            }
            if (! in_array($locked->status, ['done', 'skipped'], true)) {
                throw ValidationException::withMessages(['status' => 'Kunjungan ini tidak perlu dibuka kembali.']);
            }
            if ($locked->invoice_id !== null) {
                $number = $locked->invoice?->invoice_number;
                throw ValidationException::withMessages(['status' => 'Kunjungan ini sudah ditagih pada invoice'.($number ? ' '.$number : '').' dan tidak dapat dibuka kembali agar tagihan tetap sesuai.']);
            }
            $locked->forceFill([
                'status' => $locked->scheduled_date ? 'scheduled' : 'unscheduled',
                'completed_at' => null,
            ])->save();
            $this->refreshStatus($contract);

            return $locked->fresh();
        }, 3);
    }

    /** Perpanjang kontrak dengan menambah periode (jalur perpanjangan/renewal). */
    public function extend(JaspestContract $contract, int $addPeriods, ?float $addedPrice): JaspestContract
    {
        return DB::transaction(function () use ($contract, $addPeriods, $addedPrice): JaspestContract {
            $locked = JaspestContract::query()->lockForUpdate()->findOrFail($contract->id);
            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages(['periods' => 'Kontrak yang dibatalkan tidak dapat diperpanjang.']);
            }
            if ($addPeriods < 1) {
                throw ValidationException::withMessages(['periods' => 'Jumlah periode tambahan minimal 1.']);
            }
            $rule = self::PERIODS[$locked->period_type];
            $newDuration = $locked->duration_periods + $addPeriods;
            $planned = $locked->duration_periods * $locked->visits_per_period;
            $this->assertPlan($locked->period_type, $locked->visits_per_period, $addPeriods, $planned);
            if ($newDuration > $rule['max_periods']) {
                throw ValidationException::withMessages(['periods' => 'Total durasi kontrak maksimal '.$rule['max_periods'].' '.strtolower($rule['unit']).'.']);
            }

            $addedVisits = $addPeriods * $locked->visits_per_period;
            if ($locked->price_basis === 'per_visit') {
                $newTotal = round((float) $locked->total_price + ((float) $locked->price_amount * $addedVisits), 2);
            } else {
                if ($addedPrice === null || $addedPrice <= 0) {
                    throw ValidationException::withMessages(['added_price' => 'Isi nilai tambahan untuk perpanjangan kontrak dengan harga total.']);
                }
                $newTotal = round((float) $locked->total_price + $addedPrice, 2);
            }

            $start = Carbon::parse($locked->start_date->format('Y-m-d'), self::TZ);
            $this->generateSlots($locked, $locked->duration_periods, $newDuration);
            $locked->forceFill([
                'duration_periods' => $newDuration,
                'end_date' => $this->periodEnd($start, $locked->period_type, $newDuration - 1)->format('Y-m-d'),
                'total_price' => $newTotal,
                'status' => 'active',
            ])->save();

            return $locked->fresh();
        }, 3);
    }

    public function cancel(JaspestContract $contract, ?string $note): JaspestContract
    {
        return DB::transaction(function () use ($contract, $note): JaspestContract {
            $locked = JaspestContract::query()->lockForUpdate()->findOrFail($contract->id);
            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages(['status' => 'Kontrak sudah dibatalkan.']);
            }
            JaspestContractVisit::query()
                ->where('contract_id', $locked->id)
                ->whereIn('status', ['unscheduled', 'scheduled'])
                ->update(['status' => 'cancelled', 'updated_at' => now()]);
            $locked->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'notes' => $note !== null && trim($note) !== ''
                    ? trim(($locked->notes ? $locked->notes."\n" : '').'Dibatalkan: '.trim($note))
                    : $locked->notes,
            ])->save();

            return $locked->fresh();
        }, 3);
    }

    /** Kontrak aktif yang sudah lewat masa berlaku dan semua kunjungannya tuntas menjadi "selesai". */
    public function refreshStatus(JaspestContract $contract): void
    {
        if ($contract->status === 'cancelled') {
            return;
        }
        $open = JaspestContractVisit::query()
            ->where('contract_id', $contract->id)->where('is_extra', false)
            ->whereIn('status', ['unscheduled', 'scheduled'])->exists();
        $ended = $contract->end_date->format('Y-m-d') < self::today();
        $next = ($ended && ! $open) ? 'completed' : 'active';
        if ($next !== $contract->status) {
            $contract->forceFill(['status' => $next])->save();
        }
    }

    public function refreshAllStatuses(): void
    {
        JaspestContract::query()
            ->where('status', 'active')
            ->where('end_date', '<', self::today())
            ->whereDoesntHave('visits', fn ($query) => $query
                ->where('is_extra', false)->whereIn('status', ['unscheduled', 'scheduled']))
            ->update(['status' => 'completed']);
    }

    private function assertContractActive(JaspestContract $contract): void
    {
        if ($contract->status === 'cancelled') {
            throw ValidationException::withMessages(['status' => 'Kontrak ini sudah dibatalkan.']);
        }
    }
}
