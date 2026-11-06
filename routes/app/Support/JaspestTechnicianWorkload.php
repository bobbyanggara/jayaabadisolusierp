<?php

namespace App\Support;

use App\Models\JaspestContractVisit;
use App\Models\JaspestEnquiry;
use App\Models\JaspestTechnician;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung job aktif teknisi. Teknisi yang sudah bertugas tetap boleh menerima job baru;
 * bila job aktifnya melebihi ambang, sistem hanya menampilkan peringatan (tidak memblokir).
 *
 * Job aktif = order sekali jalan yang tanggal pelaksanaannya belum lewat
 *           + kunjungan kontrak berstatus terjadwal pada kontrak yang masih aktif.
 */
class JaspestTechnicianWorkload
{
    public static function threshold(): int
    {
        return max(1, (int) config('jaspest_admin.technician_job_warning_threshold', 5));
    }

    /** @return array<int,int> technician_id => jumlah job aktif */
    public static function activeJobCounts(?array $technicianIds = null): array
    {
        $today = now('Asia/Jakarta')->format('Y-m-d');

        $oneOff = JaspestEnquiry::query()
            ->where('status', 'converted')
            ->whereNotNull('assigned_technician_id')
            ->whereDate('scheduled_date', '>=', $today)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('jaspest_contracts')->whereColumn('jaspest_contracts.enquiry_id', 'jaspest_enquiries.id'))
            ->when($technicianIds !== null, fn ($q) => $q->whereIn('assigned_technician_id', $technicianIds))
            ->groupBy('assigned_technician_id')
            ->selectRaw('assigned_technician_id as technician_id, count(*) as total')
            ->pluck('total', 'technician_id');

        $visits = JaspestContractVisit::query()
            ->where('status', 'scheduled')
            ->whereNotNull('technician_id')
            ->whereHas('contract', fn ($q) => $q->where('status', 'active'))
            ->when($technicianIds !== null, fn ($q) => $q->whereIn('technician_id', $technicianIds))
            ->groupBy('technician_id')
            ->selectRaw('technician_id, count(*) as total')
            ->pluck('total', 'technician_id');

        $result = [];
        foreach ($oneOff as $id => $total) {
            $result[(int) $id] = ($result[(int) $id] ?? 0) + (int) $total;
        }
        foreach ($visits as $id => $total) {
            $result[(int) $id] = ($result[(int) $id] ?? 0) + (int) $total;
        }

        return $result;
    }

    public static function activeJobs(JaspestTechnician $technician): int
    {
        return self::activeJobCounts([$technician->id])[$technician->id] ?? 0;
    }

    /** Teks peringatan bila job aktif melebihi ambang, atau null. */
    public static function warningFor(JaspestTechnician $technician, ?int $jobs = null): ?string
    {
        $jobs ??= self::activeJobs($technician);
        if ($jobs <= self::threshold()) {
            return null;
        }

        return $technician->full_name.' sudah memiliki '.$jobs.' job aktif (lebih dari '.self::threshold().'). Penugasan tetap diproses.';
    }
}
