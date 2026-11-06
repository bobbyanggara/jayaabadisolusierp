<?php

namespace App\Http\Controllers;

use App\Models\JaspestContentItem;
use App\Models\JaspestContract;
use App\Models\JaspestContractVisit;
use App\Models\JaspestEnquiry;
use App\Models\JaspestEnquiryEvent;
use App\Models\JaspestTechnician;
use App\Models\JaspestWhatsappNotification;
use App\Services\JaspestContractService;
use App\Services\JaspestFonnteService;
use App\Support\JaspestTechnicianWorkload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manajemen kontrak berkala (mingguan / bulanan / tahunan).
 * Mewarisi JaspestAdminApiController hanya untuk memakai sinkronisasi profil klien yang sama.
 */
class JaspestContractApiController extends JaspestAdminApiController
{
    private const STATUS_FILTERS = ['all', 'active', 'completed', 'cancelled', 'attention'];

    public function contractIndex(Request $request, JaspestContractService $service): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(self::STATUS_FILTERS)],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $service->refreshAllStatuses();
        $today = JaspestContractService::today();

        $query = JaspestContract::query()->with('enquiry');
        $status = $validated['status'] ?? 'all';
        if (in_array($status, ['active', 'completed', 'cancelled'], true)) {
            $query->where('status', $status);
        } elseif ($status === 'attention') {
            $query->where('status', 'active')->whereHas('visits', fn ($visits) => $this->overdueScope($visits, $today));
        }

        $term = trim((string) ($validated['q'] ?? ''));
        if ($term !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('contract_number', 'like', $like)
                    ->orWhereHas('enquiry', fn (Builder $enquiry) => $enquiry
                        ->where('full_name', 'like', $like)
                        ->orWhere('company_name', 'like', $like)
                        ->orWhere('whatsapp', 'like', $like));
            });
        }

        $page = $this->withContractCounts($query, $today)->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'completed' THEN 1 ELSE 2 END")
            ->orderBy('end_date')->orderByDesc('id')->paginate(12);

        $titles = $this->serviceTitles();
        $counts = [
            'active' => JaspestContract::where('status', 'active')->count(),
            'completed' => JaspestContract::where('status', 'completed')->count(),
            'cancelled' => JaspestContract::where('status', 'cancelled')->count(),
            'attention' => JaspestContract::where('status', 'active')
                ->whereHas('visits', fn ($visits) => $this->overdueScope($visits, $today))->count(),
        ];

        return response()->json([
            'data' => collect($page->items())->map(fn (JaspestContract $contract): array => $this->contractResource($contract, $titles))->values(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'counts' => $counts,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function contractShow(JaspestContract $contract, JaspestContractService $service): JsonResponse
    {
        $service->refreshStatus($contract);

        return response()->json($this->contractDetail($contract->fresh()))->header('Cache-Control', 'no-store, private');
    }

    /**
     * Satu pintu "Tambah Order Manual": order sekali jalan atau kontrak berkala,
     * dibedakan lewat field order_type (once | contract).
     */
    public function storeManualOrder(Request $request, JaspestContractService $service, JaspestFonnteService $fonnte): JsonResponse
    {
        $type = (string) $request->input('order_type', 'once');
        if (! in_array($type, ['once', 'contract'], true)) {
            throw ValidationException::withMessages(['order_type' => 'Jenis order tidak dikenal.']);
        }

        return $type === 'contract'
            ? $this->contractStore($request, $service, $fonnte)
            : $this->storeManualDealOrder($request, $fonnte);
    }

    public function contractStore(Request $request, JaspestContractService $service, JaspestFonnteService $fonnte): JsonResponse
    {
        if (is_string($request->input('whatsapp'))) {
            $request->merge(['whatsapp' => preg_replace('/[\s().-]+/', '', trim($request->input('whatsapp')))]);
        }

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:150'],
            'whatsapp' => ['required', 'string', 'max:24', 'regex:/^\+?[0-9]{8,15}$/'],
            'address' => ['required', 'string', 'min:5', 'max:1000'],
            'property_type' => ['required', Rule::in(['rumah', 'bisnis'])],
            'company_name' => ['required_if:property_type,bisnis', 'nullable', 'string', 'max:200'],
            'need' => ['required_without:services', 'nullable', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== null && ! JaspestContentItem::query()
                    ->where('type', 'services')->where('status', 'active')->where('slug', $value)->exists()) {
                    $fail('Pilih layanan yang masih tersedia.');
                }
            }],
            'services' => ['nullable', 'array', 'min:1', 'max:10'],
            'services.*' => [
                'required', 'string', 'max:100', 'distinct',
                Rule::exists('jaspest_content_items', 'slug')->where(fn ($query) => $query->where('type', 'services')->where('status', 'active')),
            ],
            'description' => ['nullable', 'string', 'max:3000'],
            'order_area' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'period_type' => ['required', Rule::in(array_keys(JaspestContractService::PERIODS))],
            'visits_per_period' => ['required', 'integer', 'min:1', 'max:200'],
            'duration_periods' => ['required', 'integer', 'min:1', 'max:200'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'price_basis' => ['nullable', Rule::in(['total', 'per_visit'])],
            'order_price' => ['required', 'numeric', 'min:1', 'max:999999999999.99'],
            'assigned_technician_id' => [
                'nullable', 'integer',
                Rule::exists('jaspest_technicians', 'id')->where(fn ($query) => $query->where('operational_status', '<>', 'off_duty')),
            ],
            'consent' => ['accepted'],
        ], [
            'full_name.required' => 'Nama pelanggan wajib diisi.',
            'whatsapp.required' => 'Nomor WhatsApp wajib diisi.',
            'whatsapp.regex' => 'Masukkan nomor WhatsApp yang valid dengan 8–15 digit.',
            'address.required' => 'Alamat pelanggan wajib diisi.',
            'company_name.required_if' => 'Nama perusahaan wajib diisi untuk pelanggan bisnis.',
            'need.required_without' => 'Pilih minimal satu layanan kontrak.',
            'services.min' => 'Pilih minimal satu layanan kontrak.',
            'services.max' => 'Maksimal 10 layanan dapat dimasukkan dalam satu kontrak.',
            'services.*.distinct' => 'Layanan yang sama tidak boleh dipilih lebih dari satu kali.',
            'services.*.exists' => 'Salah satu layanan sudah tidak aktif atau tidak tersedia. Muat ulang daftar layanan.',
            'order_area.required' => 'Luasan pekerjaan wajib diisi.',
            'order_area.gt' => 'Luasan pekerjaan harus lebih dari 0 m².',
            'start_date.required' => 'Tanggal mulai kontrak wajib dipilih.',
            'order_price.required' => 'Nilai kontrak wajib diisi.',
            'assigned_technician_id.exists' => 'Teknisi yang dipilih tidak tersedia. Pilih teknisi lain.',
            'consent.accepted' => 'Persetujuan pelanggan wajib dicentang.',
        ]);

        $serviceSlugs = array_values(array_unique(array_filter(array_map('strval', $validated['services'] ?? [$validated['need'] ?? '']))));
        if ($serviceSlugs === []) {
            throw ValidationException::withMessages(['services' => 'Pilih minimal satu layanan kontrak.']);
        }
        $validated['need'] = $serviceSlugs[0];
        $contractServices = JaspestContentItem::query()
            ->where('type', 'services')->where('status', 'active')->whereIn('slug', $serviceSlugs)
            ->get(['slug', 'title'])->map(fn (JaspestContentItem $item): array => ['slug' => $item->slug, 'title' => $item->title])->values()->all();
        if (count($contractServices) !== count($serviceSlugs)) {
            throw ValidationException::withMessages(['services' => 'Daftar layanan berubah atau tidak lagi aktif. Muat ulang dan pilih layanan yang tersedia.']);
        }

        $validated['price_basis'] ??= 'per_visit'; // harga paket kontrak dihitung per kunjungan
        $perPeriod = (int) $validated['visits_per_period'];
        $periods = (int) $validated['duration_periods'];
        $service->assertPlan($validated['period_type'], $perPeriod, $periods);

        $normalizedPhone = $fonnte->normalizeIndonesianMobile((string) $validated['whatsapp']);
        if ($normalizedPhone === null) {
            throw ValidationException::withMessages([
                'whatsapp' => 'Masukkan nomor WhatsApp Indonesia yang valid, misalnya 081234567890.',
            ]);
        }

        $amount = round((float) $validated['order_price'], 2);
        $area = round((float) $validated['order_area'], 2);
        $totalVisits = $perPeriod * $periods;
        $totalPrice = $validated['price_basis'] === 'per_visit' ? round($amount * $totalVisits, 2) : $amount;
        if ($totalPrice > 9999999999999.0) {
            throw ValidationException::withMessages(['order_price' => 'Total nilai kontrak terlalu besar. Periksa kembali harga dan jumlah kunjungan.']);
        }

        $companyName = $validated['property_type'] === 'bisnis' ? trim((string) ($validated['company_name'] ?? '')) : null;
        do {
            $reference = 'JAS-'.now('Asia/Jakarta')->format('ymd').'-'.Str::upper(Str::random(6));
        } while (JaspestEnquiry::query()->where('reference', $reference)->exists());

        $contractId = DB::transaction(function () use (
            $validated, $contractServices, $service, $normalizedPhone, $companyName, $area, $amount, $totalPrice, $reference
        ): int {
            $technician = ! empty($validated['assigned_technician_id'])
                ? JaspestTechnician::query()->find((int) $validated['assigned_technician_id'])
                : null;

            $enquiry = JaspestEnquiry::create([
                'reference' => $reference,
                'full_name' => trim($validated['full_name']),
                'whatsapp' => $normalizedPhone,
                'address' => trim($validated['address']),
                'estimated_area' => $area,
                'need' => $validated['need'],
                'property_type' => $validated['property_type'],
                'company_name' => $companyName,
                'description' => trim((string) ($validated['description'] ?? '')),
                'consent' => true,
                'status' => 'converted',
                'converted_at' => now(),
                'order_price' => $totalPrice,
                'order_area' => $area,
                'scheduled_date' => null,
                'service_schedules' => array_map(static fn (array $item): array => [
                    'slug' => $item['slug'],
                    'scheduled_date' => null,
                ], $contractServices),
                'assigned_technician_id' => $technician?->id,
                'assigned_technician_name' => $technician?->full_name,
            ]);

            $client = $this->synchronizeDealOrderClient($enquiry);

            $contract = $service->createContract($enquiry, $client?->id, [
                'period_type' => $validated['period_type'],
                'visits_per_period' => (int) $validated['visits_per_period'],
                'duration_periods' => (int) $validated['duration_periods'],
                'start_date' => $validated['start_date'],
                'price_basis' => $validated['price_basis'],
                'price_amount' => $amount,
                'area' => $area,
                'service' => $validated['need'],
                'services' => $contractServices,
                'notes' => trim((string) ($validated['description'] ?? '')) ?: null,
            ], $technician, auth()->id());

            JaspestEnquiryEvent::create([
                'enquiry_id' => $enquiry->id,
                'user_id' => auth()->id(),
                'event' => 'contract_created',
                'from_status' => null,
                'to_status' => 'converted',
                'note' => 'Kontrak berkala '.$contract->contract_number.' dibuat ('
                    .JaspestContractService::PERIODS[$contract->period_type]['label'].', '
                    .$contract->visits_per_period.'x per '.strtolower(JaspestContractService::PERIODS[$contract->period_type]['unit'])
                    .', '.$contract->duration_periods.' periode).',
                'metadata' => [
                    'contract_id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'order_price' => $totalPrice,
                ],
            ]);

            return $contract->id;
        }, 3);

        $contract = JaspestContract::query()->findOrFail($contractId);

        // Notifikasi WhatsApp hanya sekali saat kontrak pertama dibuat (idempoten per order).
        $notification = JaspestWhatsappNotification::query()->firstOrCreate(
            ['enquiry_id' => $contract->enquiry_id, 'notification_type' => 'deal_order_confirmation'],
            ['recipient' => $normalizedPhone, 'message' => '', 'status' => 'queued']
        );
        try {
            $notification = $fonnte->sendDealOrderConfirmation(JaspestEnquiry::query()->findOrFail($contract->enquiry_id), $notification);
        } catch (\Throwable $exception) {
            report($exception);
            try {
                $notification->forceFill([
                    'status' => 'failed',
                    'last_error' => 'Kontrak berhasil disimpan, tetapi pesan konfirmasi belum dapat diproses. Periksa log aplikasi sebelum mencoba kembali.',
                    'failed_at' => now(),
                ])->save();
                $notification->refresh();
            } catch (\Throwable $loggingException) {
                report($loggingException);
            }
        }

        $detail = $this->contractDetail($contract);
        $detail['whatsapp_notification'] = $this->whatsappNotificationResource($notification);
        $detail['technician_warning'] = $this->technicianWarningById($validated['assigned_technician_id'] ?? null);

        return response()->json($detail, 201);
    }

    public function contractVisitUpdate(Request $request, JaspestContractVisit $visit, JaspestContractService $service): JsonResponse
    {
        $validated = $request->validate([
            'scheduled_date' => ['nullable', 'date_format:Y-m-d'],
            'technician_id' => ['nullable', 'integer', Rule::exists('jaspest_technicians', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'scheduled_date.date_format' => 'Format tanggal tidak valid.',
            'technician_id.exists' => 'Teknisi tidak ditemukan.',
        ]);

        $service->schedule(
            $visit,
            $validated['scheduled_date'] ?? null,
            isset($validated['technician_id']) ? (int) $validated['technician_id'] : null,
            $validated['notes'] ?? null,
        );

        $detail = $this->contractDetail(JaspestContract::query()->findOrFail($visit->contract_id));
        $detail['technician_warning'] = $this->technicianWarningById($validated['technician_id'] ?? null);

        return response()->json($detail);
    }

    public function contractVisitAction(Request $request, JaspestContractVisit $visit, JaspestContractService $service): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['complete', 'skip', 'reopen'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = match ($validated['action']) {
            'complete' => $service->complete($visit, $validated['note'] ?? null),
            'skip' => $service->skip($visit, $validated['note'] ?? null),
            default => $service->reopen($visit),
        };

        $contract = JaspestContract::query()->findOrFail($updated->contract_id);
        $label = ['complete' => 'selesai', 'skip' => 'dilewati', 'reopen' => 'dibuka kembali'][$validated['action']];
        $this->logContractEvent($contract, 'contract_visit', 'Kunjungan '.($updated->is_extra ? 'tambahan #' : 'periode '.$updated->period_index.' slot ').$updated->slot_no.' ditandai '.$label.'.', [
            'visit_id' => $updated->id,
            'action' => $validated['action'],
        ]);

        return response()->json($this->contractDetail($contract->fresh()));
    }

    public function contractExtend(Request $request, JaspestContract $contract, JaspestContractService $service): JsonResponse
    {
        $validated = $request->validate([
            'periods' => ['required', 'integer', 'min:1', 'max:200'],
            'added_price' => ['nullable', 'numeric', 'min:1', 'max:999999999999.99'],
        ], [
            'periods.required' => 'Jumlah periode tambahan wajib diisi.',
        ]);

        $updated = $service->extend(
            $contract,
            (int) $validated['periods'],
            isset($validated['added_price']) ? (float) $validated['added_price'] : null,
        );
        $this->logContractEvent($updated, 'contract_updated', 'Kontrak diperpanjang '.$validated['periods'].' periode. Berlaku sampai '.$updated->end_date->format('d-m-Y').'.', [
            'added_periods' => (int) $validated['periods'],
        ]);

        return response()->json($this->contractDetail($updated));
    }

    public function contractCancel(Request $request, JaspestContract $contract, JaspestContractService $service): JsonResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $updated = $service->cancel($contract, $validated['note'] ?? null);
        $this->logContractEvent($updated, 'contract_updated', 'Kontrak dibatalkan. Kunjungan yang belum selesai dibatalkan.', []);

        return response()->json($this->contractDetail($updated));
    }

    // ------------------------------------------------------------------ helpers

    /** Peringatan (bukan blokir) bila teknisi sudah memegang banyak job aktif. */
    private function technicianWarningById(mixed $technicianId): ?string
    {
        if (empty($technicianId)) {
            return null;
        }
        $technician = JaspestTechnician::query()->find((int) $technicianId);

        return $technician ? JaspestTechnicianWorkload::warningFor($technician) : null;
    }

    private function logContractEvent(JaspestContract $contract, string $event, string $note, array $metadata): void
    {
        if (! $contract->enquiry_id) {
            return;
        }
        JaspestEnquiryEvent::create([
            'enquiry_id' => $contract->enquiry_id,
            'user_id' => auth()->id(),
            'event' => $event,
            'from_status' => 'converted',
            'to_status' => 'converted',
            'note' => $note,
            'metadata' => $metadata + ['contract_id' => $contract->id, 'contract_number' => $contract->contract_number],
        ]);
    }

    private function overdueScope($visits, string $today)
    {
        return $visits->where('is_extra', false)
            ->whereIn('status', ['unscheduled', 'scheduled'])
            ->where(function ($when) use ($today): void {
                $when->where('scheduled_date', '<', $today)
                    ->orWhere(function ($unscheduled) use ($today): void {
                        $unscheduled->whereNull('scheduled_date')->where('period_end', '<', $today);
                    });
            });
    }

    private function withContractCounts($query, string $today)
    {
        return $query->withCount([
            'visits as total_regular' => fn ($q) => $q->where('is_extra', false)->where('status', '<>', 'cancelled'),
            'visits as done_regular' => fn ($q) => $q->where('is_extra', false)->where('status', 'done'),
            'visits as skipped_regular' => fn ($q) => $q->where('is_extra', false)->where('status', 'skipped'),
            'visits as scheduled_regular' => fn ($q) => $q->where('is_extra', false)->where('status', 'scheduled'),
            'visits as unscheduled_regular' => fn ($q) => $q->where('is_extra', false)->where('status', 'unscheduled'),
            'visits as overdue_regular' => fn ($q) => $this->overdueScope($q, $today),
            'visits as extra_count' => fn ($q) => $q->where('is_extra', true)->where('status', '<>', 'cancelled'),
        ]);
    }

    private function serviceTitles(): array
    {
        return JaspestContentItem::query()->where('type', 'services')->pluck('title', 'slug')->all();
    }

    private function contractResource(JaspestContract $contract, array $titles): array
    {
        $rule = JaspestContractService::PERIODS[$contract->period_type];
        $enquiry = $contract->enquiry;
        $totalPlanned = $contract->visits_per_period * $contract->duration_periods;
        $storedServices = is_array($contract->services) ? $contract->services : [];
        if ($storedServices === []) {
            $storedServices = [['slug' => $contract->service, 'title' => null]];
        }
        $services = collect($storedServices)->map(function ($item) use ($titles): array {
            $slug = is_array($item) ? (string) ($item['slug'] ?? '') : (string) $item;
            $savedTitle = is_array($item) ? trim((string) ($item['title'] ?? '')) : '';
            $title = $savedTitle !== '' ? $savedTitle : ($titles[$slug] ?? Str::of($slug)->replace('-', ' ')->title()->toString());

            return ['slug' => $slug, 'title' => $title];
        })->filter(fn (array $item): bool => $item['slug'] !== '')
            ->unique('slug')->values();
        if ($services->isEmpty()) {
            $services = collect([['slug' => $contract->service, 'title' => $titles[$contract->service] ?? Str::of($contract->service)->replace('-', ' ')->title()->toString()]]);
        }
        $serviceLabel = $services->pluck('title')->implode(' + ');

        return [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'enquiry_reference' => $enquiry?->reference,
            'customer' => $enquiry?->company_name ?: $enquiry?->full_name,
            'contact_name' => $enquiry?->full_name,
            'company' => $enquiry?->company_name,
            'whatsapp' => $enquiry?->whatsapp,
            'address' => $enquiry?->address,
            'property' => $enquiry?->property_type,
            'service_code' => $contract->service,
            'service_codes' => $services->pluck('slug')->all(),
            'services' => $services->all(),
            'service' => $serviceLabel,
            'period_type' => $contract->period_type,
            'period_label' => $rule['label'],
            'period_unit' => $rule['unit'],
            'visits_per_period' => $contract->visits_per_period,
            'duration_periods' => $contract->duration_periods,
            'total_visits' => $totalPlanned,
            'start_date' => $contract->start_date->format('Y-m-d'),
            'end_date' => $contract->end_date->format('Y-m-d'),
            'price_basis' => $contract->price_basis,
            'price_amount' => (string) $contract->price_amount,
            'total_price' => (string) $contract->total_price,
            'area' => $contract->area !== null ? (string) $contract->area : null,
            'default_technician_id' => $contract->default_technician_id,
            'default_technician_name' => $contract->default_technician_name,
            'status' => $contract->status,
            'notes' => $contract->notes,
            'progress' => [
                'total' => (int) ($contract->total_regular ?? 0),
                'done' => (int) ($contract->done_regular ?? 0),
                'skipped' => (int) ($contract->skipped_regular ?? 0),
                'scheduled' => (int) ($contract->scheduled_regular ?? 0),
                'unscheduled' => (int) ($contract->unscheduled_regular ?? 0),
                'overdue' => (int) ($contract->overdue_regular ?? 0),
                'extra' => (int) ($contract->extra_count ?? 0),
            ],
        ];
    }

    private function visitResource(JaspestContractVisit $visit, string $today): array
    {
        $open = in_array($visit->status, ['unscheduled', 'scheduled'], true);
        $date = $visit->scheduled_date?->format('Y-m-d');

        return [
            'id' => $visit->id,
            'period_index' => $visit->period_index,
            'slot_no' => $visit->slot_no,
            'scheduled_date' => $date,
            'technician_id' => $visit->technician_id,
            'technician_name' => $visit->technician_name,
            'status' => $visit->status,
            'is_extra' => (bool) $visit->is_extra,
            'notes' => $visit->notes,
            'completed_at' => $visit->completed_at?->copy()->timezone(JaspestContractService::TZ)->format('Y-m-d'),
            'is_overdue' => ! $visit->is_extra && $open && ($date ? $date < $today : $visit->period_end->format('Y-m-d') < $today),
            'can_complete' => $visit->status === 'scheduled' && $date !== null && $date <= $today,
        ];
    }

    private function contractDetail(JaspestContract $contract): array
    {
        $today = JaspestContractService::today();
        $contract = $this->withContractCounts(JaspestContract::query()->with('enquiry'), $today)->findOrFail($contract->id);
        $rule = JaspestContractService::PERIODS[$contract->period_type];
        $visits = $contract->visits()->orderBy('period_index')->orderBy('slot_no')->get();

        $periods = $visits->where('is_extra', false)->groupBy('period_index')->map(function ($group, $index) use ($rule, $today, $contract): array {
            $first = $group->first();
            $start = $first->period_start->format('Y-m-d');
            $end = $first->period_end->format('Y-m-d');
            $active = $group->where('status', '<>', 'cancelled');

            return [
                'index' => (int) $index,
                'label' => $rule['unit'].' ke-'.$index,
                'start' => $start,
                'end' => $end,
                'is_current' => $today >= $start && $today <= $end,
                'is_past' => $end < $today,
                'quota' => $contract->visits_per_period,
                'done' => $group->where('status', 'done')->count(),
                'resolved' => $group->whereIn('status', ['done', 'skipped'])->count(),
                'planned' => $active->count(),
                'visits' => $group->map(fn (JaspestContractVisit $visit): array => $this->visitResource($visit, $today))->values(),
            ];
        })->values();

        $extras = $visits->where('is_extra', true)->map(fn (JaspestContractVisit $visit): array => $this->visitResource($visit, $today))->values();

        return $this->contractResource($contract, $this->serviceTitles()) + [
            'periods' => $periods,
            'extra_visits' => $extras,
            'today' => $today,
        ];
    }
}
