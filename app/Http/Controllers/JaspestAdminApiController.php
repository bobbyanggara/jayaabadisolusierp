<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\JaspestContentItem;
use App\Models\JaspestClient;
use App\Models\JaspestClientEnquiryLink;
use App\Models\JaspestEnquiry;
use App\Models\JaspestEnquiryEvent;
use App\Models\JaspestInventoryBatch;
use App\Models\JaspestInventoryItem;
use App\Models\JaspestInventoryMaintenance;
use App\Models\JaspestInventoryMovement;
use App\Models\JaspestInventoryTechnicianStock;
use App\Models\JaspestInventoryTechnicianBatch;
use App\Models\JaspestTechnician;
use App\Models\JaspestWhatsappNotification;
use App\Services\JaspestFonnteService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JaspestAdminApiController extends Controller
{
    private const CONTENT_TYPES = ['pages', 'services', 'pests', 'methods', 'articles', 'gallery', 'faqs', 'settings'];
    private const STATUSES = ['new', 'followup', 'converted', 'lost'];
    private const STATUS_ACTIONS = ['converted', 'lost'];
    private const DIVISION_ROLES = ['direksi', 'finance', 'logistik', 'marketing'];

    public function dashboard(): JsonResponse
    {
        $counts = [];
        foreach (self::STATUSES as $status) {
            $counts[$status] = $status === 'followup'
                ? JaspestEnquiry::whereIn('status', ['followup', 'contacted'])->count()
                : JaspestEnquiry::where('status', $status)->count();
        }

        // DB timestamps remain UTC; chart labels and day boundaries follow WIB (Asia/Jakarta).
        $days = collect(range(6, 0))->map(function (int $ago): array {
            $day = now('Asia/Jakarta')->subDays($ago);
            $startUtc = $day->copy()->startOfDay()->utc();
            $endUtc = $day->copy()->endOfDay()->utc();
            return [
                'date' => $day->toDateString(),
                'label' => $day->locale('id')->translatedFormat('D'),
                'total' => JaspestEnquiry::whereBetween('created_at', [$startUtc, $endUtc])->count(),
            ];
        })->values();

        $recent = JaspestEnquiry::withCount('photos')->latest()->limit(5)->get()
            ->map(fn (JaspestEnquiry $item): array => $this->bookingResource($item));

        return response()->json([
            'stats' => [
                'total' => JaspestEnquiry::count(),
                'new' => $counts['new'],
                'followup' => $counts['followup'],
                'converted' => $counts['converted'],
                'lost' => $counts['lost'],
            ],
            'series' => $days,
            'recent' => $recent,
        ]);
    }

    public function bookings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_merge(['all'], self::STATUSES))],
            'type' => ['nullable', Rule::in(['all', 'rumah', 'bisnis'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = JaspestEnquiry::query()->withCount('photos')->latest();
        if (($validated['status'] ?? 'all') !== 'all') {
            if ($validated['status'] === 'followup') {
                // Compatibilitas untuk record lama yang masih memakai status 'contacted'.
                $query->whereIn('status', ['followup', 'contacted']);
            } else {
                $query->where('status', $validated['status']);
            }
        }
        if (($validated['type'] ?? 'all') !== 'all') {
            $query->where('property_type', $validated['type']);
        }
        if (filled($validated['search'] ?? null)) {
            $term = '%'.addcslashes($validated['search'], '%_\\').'%';
            $query->where(fn ($sub) => $sub->where('reference', 'like', $term)
                ->orWhere('full_name', 'like', $term)
                ->orWhere('whatsapp', 'like', $term)
                ->orWhere('need', 'like', $term)
                ->orWhere('address', 'like', $term));
        }

        $this->applyDateRange($query, $validated);

        $page = $query->paginate(30);

        return response()->json([
            'data' => $page->getCollection()->map(fn (JaspestEnquiry $item): array => $this->bookingResource($item)),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** Download all matching bookings as a formatted, dependency-free XLSX workbook. */
    public function exportBookings(Request $request): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_merge(['all'], self::STATUSES))],
            'type' => ['nullable', Rule::in(['all', 'rumah', 'bisnis'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $query = JaspestEnquiry::query()->latest();
        if (($validated['status'] ?? 'all') !== 'all') {
            if ($validated['status'] === 'followup') {
                // Compatibilitas untuk record lama yang masih memakai status 'contacted'.
                $query->whereIn('status', ['followup', 'contacted']);
            } else {
                $query->where('status', $validated['status']);
            }
        }
        if (($validated['type'] ?? 'all') !== 'all') {
            $query->where('property_type', $validated['type']);
        }
        if (filled($validated['search'] ?? null)) {
            $term = '%'.addcslashes($validated['search'], '%_\\').'%';
            $query->where(fn ($sub) => $sub->where('reference', 'like', $term)
                ->orWhere('full_name', 'like', $term)
                ->orWhere('whatsapp', 'like', $term)
                ->orWhere('need', 'like', $term)
                ->orWhere('address', 'like', $term));
        }

        $this->applyDateRange($query, $validated);

        $items = $query->get();
        $headers = [
            'No.', 'No. Referensi', 'Nama Klien', 'Nomor WhatsApp', 'Alamat',
            'Luas (m²)', 'Kebutuhan Layanan', 'Jenis Lokasi', 'Nama Perusahaan',
            'Keterangan', 'Tanggal Masuk', 'Jam Masuk (WIB)', 'Status', 'Persetujuan Kontak',
        ];

        $rows = [];
        foreach ($items as $index => $item) {
            $createdAt = $item->created_at?->copy()->timezone('Asia/Jakarta');
            $service = JaspestContentItem::where('type', 'services')->where('slug', $item->need)->value('title');
            $need = $service ?: (str_contains((string) $item->need, '-')
                ? Str::of($item->need)->replace('-', ' ')->title()->toString()
                : $item->need);

            $rows[] = [
                (string) ($index + 1),
                (string) $item->reference,
                (string) $item->full_name,
                (string) $item->whatsapp,
                (string) $item->address,
                $item->estimated_area === null ? '' : (string) $item->estimated_area,
                (string) $need,
                $item->property_type === 'bisnis' ? 'Bisnis' : 'Rumah',
                (string) ($item->company_name ?? ''),
                (string) ($item->description ?? ''),
                $createdAt?->format('d/m/Y') ?? '',
                $createdAt?->format('H:i:s') ?? '',
                $this->bookingStatusLabel($item->status),
                $item->consent ? 'Ya' : 'Tidak',
            ];
        }

        $exportedAt = now('Asia/Jakarta')->locale('id')->translatedFormat('d F Y H:i');
        $filterText = 'Status: '.(($validated['status'] ?? 'all') === 'all' ? 'Semua' : $this->bookingStatusLabel($validated['status']))
            .' | Lokasi: '.(($validated['type'] ?? 'all') === 'all' ? 'Semua' : ucfirst($validated['type']))
            .' | Pencarian: '.(($validated['search'] ?? '') !== '' ? $validated['search'] : 'Tidak ada')
            .' | Tanggal: '.$this->dateRangeLabel($validated);

        $xlsx = $this->buildBookingsXlsx($headers, $rows, $exportedAt, $filterText);
        $filename = 'JASPEST-Data-Booking-'.now('Asia/Jakarta')->format('Y-m-d').'.xlsx';

        return response()->streamDownload(
            static function () use ($xlsx): void { echo $xlsx; },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /** Filter pengajuan berdasarkan tanggal masuk (hari menurut WIB, batas awal dan akhir inklusif). */
    private function applyDateRange($query, array $validated): void
    {
        if (filled($validated['date_from'] ?? null)) {
            $query->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $validated['date_from'], 'Asia/Jakarta')->startOfDay()->utc());
        }
        if (filled($validated['date_to'] ?? null)) {
            $query->where('created_at', '<=', Carbon::createFromFormat('Y-m-d', $validated['date_to'], 'Asia/Jakarta')->endOfDay()->utc());
        }
    }

    private function dateRangeLabel(array $validated): string
    {
        $from = $validated['date_from'] ?? null;
        $to = $validated['date_to'] ?? null;
        if (! filled($from) && ! filled($to)) {
            return 'Semua';
        }
        $format = static fn (string $date): string => Carbon::createFromFormat('Y-m-d', $date)->format('d/m/Y');

        return (filled($from) ? $format($from) : 'Awal').' s/d '.(filled($to) ? $format($to) : 'Sekarang');
    }

    private function bookingStatusLabel(?string $status): string
    {
        return match ($status) {
            'new' => 'Baru',
            'contacted' => 'Follow-up',
            'followup' => 'Follow-up',
            'converted' => 'Deal Order',
            'lost' => 'Tidak Deal',
            default => ucfirst((string) $status),
        };
    }

    /** Build a small OOXML workbook without requiring PhpSpreadsheet or ext-zip. */
    private function buildBookingsXlsx(array $headers, array $rows, string $exportedAt, string $filterText): string
    {
        if (! function_exists('gzdeflate')) {
            abort(500, 'Ekstensi PHP zlib belum aktif. Aktifkan zlib pada PHP Laragon untuk membuat file Excel.');
        }

        $columnCount = count($headers);
        $lastColumn = $this->excelColumnName($columnCount);
        $lastRow = count($rows) + 4;
        $xml = static fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $cell = static function (string $reference, string $value, int $style = 4) use ($xml): string {
            return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$xml($value).'</t></is></c>';
        };

        $sheetRows = [];
        $sheetRows[] = '<row r="1" ht="32" customHeight="1">'.$cell('A1', 'JASPEST | DATA BOOKING PELANGGAN', 1).'</row>';
        $sheetRows[] = '<row r="2" ht="22" customHeight="1">'.$cell('A2', 'Laporan pengajuan layanan — diekspor '.$exportedAt.' WIB', 2).'</row>';
        $sheetRows[] = '<row r="3" ht="22" customHeight="1">'.$cell('A3', 'Filter: '.$filterText.' | Total data: '.count($rows), 2).'</row>';

        $headerCells = '';
        foreach ($headers as $index => $header) {
            $headerCells .= $cell($this->excelColumnName($index + 1).'4', $header, 3);
        }
        $sheetRows[] = '<row r="4" ht="30" customHeight="1">'.$headerCells.'</row>';

        foreach ($rows as $rowIndex => $values) {
            $excelRow = $rowIndex + 5;
            $style = $rowIndex % 2 === 0 ? 4 : 5;
            $cells = '';
            foreach ($values as $colIndex => $value) {
                $reference = $this->excelColumnName($colIndex + 1).$excelRow;
                if ($colIndex === 5 && $value !== '' && is_numeric($value)) {
                    $cells .= '<c r="'.$reference.'" s="6"><v>'.(float) $value.'</v></c>';
                } else {
                    $cells .= $cell($reference, (string) $value, $style);
                }
            }
            $sheetRows[] = '<row r="'.$excelRow.'" ht="32" customHeight="1">'.$cells.'</row>';
        }

        $widths = [8, 21, 26, 19, 42, 13, 25, 17, 26, 48, 16, 16, 22, 22];
        $cols = '';
        foreach ($widths as $index => $width) {
            $column = $index + 1;
            $cols .= '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<dimension ref="A1:'.$lastColumn.$lastRow.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/><cols>'.$cols.'</cols><sheetData>'.implode('', $sheetRows).'</sheetData>'
            .'<autoFilter ref="A4:'.$lastColumn.$lastRow.'"/><mergeCells count="3"><mergeCell ref="A1:'.$lastColumn.'1"/><mergeCell ref="A2:'.$lastColumn.'2"/><mergeCell ref="A3:'.$lastColumn.'3"/></mergeCells>'
            .'<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            .'</worksheet>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="4"><font><sz val="10"/><name val="Aptos"/></font><font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Aptos Display"/></font><font><sz val="10"/><color rgb="FF64748B"/><name val="Aptos"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font></fonts>'
            .'<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF102548"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1D4ED8"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFE2E8F0"/></left><right style="thin"><color rgb="FFE2E8F0"/></right><top style="thin"><color rgb="FFE2E8F0"/></top><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="7">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="2" fontId="0" fillId="0" borderId="1" xfId="0"><alignment horizontal="right" vertical="top"/></xf>'
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Data Booking" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => $styles,
            'xl/worksheets/sheet1.xml' => $sheet,
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:creator>JASPEST</dc:creator><dc:title>Data Booking JASPEST</dc:title></cp:coreProperties>',
        ];

        return $this->makeZipArchive($files);
    }

    private function excelColumnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $number = intdiv($number - 1, 26);
        }
        return $name;
    }

    /** Create a ZIP file using zlib so XLSX export does not depend on ext-zip. */
    private function makeZipArchive(array $files): string
    {
        $localData = '';
        $centralDirectory = '';
        $offset = 0;

        foreach ($files as $name => $contents) {
            $name = (string) $name;
            $contents = (string) $contents;
            $compressed = gzdeflate($contents, 6);
            if ($compressed === false) {
                abort(500, 'Gagal mengompres bagian file Excel.');
            }

            $crc = crc32($contents);
            $compressedSize = strlen($compressed);
            $uncompressedSize = strlen($contents);
            $nameLength = strlen($name);

            $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 8, 0, 0, $crc, $compressedSize, $uncompressedSize, $nameLength, 0);
            $localRecord = $localHeader.$name.$compressed;
            $localData .= $localRecord;

            $centralDirectory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 8, 0, 0, $crc, $compressedSize, $uncompressedSize, $nameLength, 0, 0, 0, 0, 0, $offset).$name;
            $offset += strlen($localRecord);
        }

        $entryCount = count($files);
        $endRecord = pack('VvvvvVVv', 0x06054b50, 0, 0, $entryCount, $entryCount, strlen($centralDirectory), strlen($localData), 0);

        return $localData.$centralDirectory.$endRecord;
    }

    public function showBooking(JaspestEnquiry $enquiry): JsonResponse
    {
        $enquiry->load(['photos', 'events', 'whatsappNotification']);
        $resource = $this->bookingResource($enquiry);
        $resource['whatsapp_notification'] = $enquiry->whatsappNotification
            ? $this->whatsappNotificationResource($enquiry->whatsappNotification)
            : null;
        $resource['photos'] = $enquiry->photos->map(fn ($photo): array => [
            'id' => $photo->id,
            'name' => $photo->original_name,
            'url' => route('jaspest.admin.api.bookings.photos.show', [$enquiry->getRouteKey(), $photo->getKey()], false),
            'size' => $photo->size_bytes,
        ])->values();
        $authors = User::whereIn('id', $enquiry->events->pluck('user_id')->filter()->unique()->values())->pluck('name', 'id');
        $resource['events'] = $enquiry->events->map(fn ($event): array => [
            'event' => $event->event,
            'from_status' => $event->from_status,
            'to_status' => $event->to_status,
            'note' => $event->event === 'whatsapp_opened'
                ? 'Pesan tindak lanjut disiapkan dari data pengajuan dan percakapan pelanggan dibuka untuk ditinjau.'
                : $event->note,
            'author' => $event->user_id ? ($authors[$event->user_id] ?? null) : null,
            'created_at' => $event->created_at?->copy()->timezone('Asia/Jakarta')->toIso8601String(),
        ])->values();

        return response()->json($resource);
    }

    public function showPhoto(JaspestEnquiry $enquiry, int $photo): BinaryFileResponse|JsonResponse
    {
        $record = $enquiry->photos()->findOrFail($photo);
        $diskName = (string) config('jaspest_admin.photo_disk', 'local');
        $disk = Storage::disk($diskName);
        $stream = null;
        $resolvedFromLegacyRoot = false;

        if ($disk->exists($record->path)) {
            $stream = $disk->readStream($record->path);
        }

        // Backward compatibility for files saved under the pre-Laravel-13 storage/app root.
        // The fallback remains private and is only served through this authenticated endpoint.
        if (! is_resource($stream) && $diskName === 'local') {
            $relativePath = ltrim(str_replace('\\', '/', (string) $record->path), '/');
            $appRoot = realpath(storage_path('app'));
            $legacyCandidate = realpath(storage_path('app/'.$relativePath));
            if (
                $relativePath !== ''
                && ! str_contains('/'.$relativePath.'/', '/../')
                && $appRoot !== false
                && $legacyCandidate !== false
                && str_starts_with($legacyCandidate, $appRoot.DIRECTORY_SEPARATOR)
                && is_file($legacyCandidate)
            ) {
                $stream = @fopen($legacyCandidate, 'rb');
                $resolvedFromLegacyRoot = is_resource($stream);
            }
        }

        if (! is_resource($stream)) {
            logger()->warning('JASPEST private booking photo is missing or unreadable.', [
                'enquiry_id' => $enquiry->getKey(),
                'photo_id' => $record->getKey(),
                'disk' => $diskName,
                'relative_path' => $record->path,
            ]);

            return response()->json([
                'message' => 'File foto tidak ditemukan atau tidak dapat dibaca pada penyimpanan privat.',
                'code' => 'JASPEST_PHOTO_FILE_MISSING',
            ], 404);
        }

        // Read once (uploads are limited to 5 MB) and identify the real format from file bytes.
        // Never trust the client/database MIME value: a wrong Content-Type makes browsers reject images.
        $bytes = stream_get_contents($stream);
        fclose($stream);
        if (! is_string($bytes) || $bytes === '') {
            logger()->warning('JASPEST private booking photo is empty.', [
                'enquiry_id' => $enquiry->getKey(), 'photo_id' => $record->getKey(),
            ]);
            return response()->json([
                'message' => 'File foto ditemukan tetapi kosong atau tidak dapat dibaca.',
                'code' => 'JASPEST_PHOTO_EMPTY',
            ], 422);
        }

        $imageInfo = @getimagesizefromstring($bytes);
        $actualMime = is_array($imageInfo) ? strtolower((string) ($imageInfo['mime'] ?? '')) : '';
        $supportedMimes = ['image/jpeg', 'image/png', 'image/webp'];

        if (! in_array($actualMime, $supportedMimes, true)) {
            $finfoMime = 'unknown';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo !== false) {
                    $detected = finfo_buffer($finfo, substr($bytes, 0, 65536));
                    if (is_string($detected) && $detected !== '') $finfoMime = $detected;
                    finfo_close($finfo);
                }
            }
            logger()->warning('JASPEST booking photo has unsupported or invalid image bytes.', [
                'enquiry_id' => $enquiry->getKey(),
                'photo_id' => $record->getKey(),
                'recorded_mime' => $record->mime_type,
                'detected_mime' => $finfoMime,
                'path' => $record->path,
                'bytes' => strlen($bytes),
            ]);
            return response()->json([
                'message' => 'File ditemukan, tetapi isi file bukan gambar JPG, PNG, atau WebP yang valid. Kemungkinan ekstensi file tidak sesuai atau file rusak. Unggah ulang foto asli dalam JPG, PNG, atau WebP.',
                'code' => 'JASPEST_PHOTO_INVALID_IMAGE',
                'recorded_mime' => (string) ($record->mime_type ?? ''),
                'detected_mime' => $finfoMime,
            ], 415);
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if ($width < 1 || $height < 1 || ($width * $height) > 40000000) {
            return response()->json([
                'message' => 'Dimensi foto tidak valid atau terlalu besar untuk ditampilkan. Unggah ulang foto dengan ukuran lebih kecil.',
                'code' => 'JASPEST_PHOTO_INVALID_DIMENSIONS',
            ], 422);
        }

        $recordedMime = strtolower((string) ($record->mime_type ?? ''));
        if ($recordedMime === 'image/jpg') $recordedMime = 'image/jpeg';
        if ($recordedMime !== $actualMime) {
            logger()->info('JASPEST photo MIME corrected from file contents.', [
                'enquiry_id' => $enquiry->getKey(),
                'photo_id' => $record->getKey(),
                'recorded_mime' => $recordedMime,
                'actual_mime' => $actualMime,
            ]);
        }

        // Compress the response before the browser receives it. Existing legacy uploads
        // are optimized on-the-fly too, so users do not need to re-upload every old photo.
        // Prefer WebP (quality 82), resize long edges to 1920px, and preserve alpha where
        // applicable. If GD/encoder support is unavailable, keep the verified original.
        if (function_exists('imagecreatefromstring') && function_exists('imagecreatetruecolor')) {
            $decoded = @imagecreatefromstring($bytes);
            if ($decoded !== false) {
                $sourceWidth = imagesx($decoded);
                $sourceHeight = imagesy($decoded);
                $scale = min(1, 1920 / max($sourceWidth, $sourceHeight));
                $targetWidth = max(1, (int) round($sourceWidth * $scale));
                $targetHeight = max(1, (int) round($sourceHeight * $scale));
                $targetMime = function_exists('imagewebp')
                    ? 'image/webp'
                    : ($actualMime === 'image/webp' ? 'image/jpeg' : $actualMime);

                $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
                if ($canvas !== false) {
                    if ($targetMime === 'image/png' || $targetMime === 'image/webp') {
                        imagealphablending($canvas, false);
                        imagesavealpha($canvas, true);
                        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                        imagefill($canvas, 0, 0, $transparent);
                    } else {
                        $white = imagecolorallocate($canvas, 255, 255, 255);
                        imagefill($canvas, 0, 0, $white);
                    }
                    imagecopyresampled($canvas, $decoded, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
                    ob_start();
                    $encoded = match ($targetMime) {
                        'image/webp' => @imagewebp($canvas, null, 82),
                        'image/jpeg' => @imagejpeg($canvas, null, 82),
                        'image/png' => @imagepng($canvas, null, 8),
                        default => false,
                    };
                    $normalizedBytes = ob_get_clean();
                    imagedestroy($canvas);
                    if ($encoded && is_string($normalizedBytes) && $normalizedBytes !== '') {
                        $bytes = $normalizedBytes;
                        $actualMime = $targetMime;
                    }
                }
                imagedestroy($decoded);
            }
        }

        if ($resolvedFromLegacyRoot) {
            logger()->info('JASPEST photo served from legacy private storage root.', [
                'enquiry_id' => $enquiry->getKey(),
                'photo_id' => $record->getKey(),
                'relative_path' => $record->path,
            ]);
        }

        $extension = match ($actualMime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        };
        // Serve the verified bytes as a real file response. BinaryFileResponse
        // calculates Content-Length from the exact temporary file size and streams
        // those same bytes. This avoids the 1-2 byte Content-Length mismatch that
        // occurred on some PHP/Laragon output-buffer configurations with a string
        // response. The temporary file is outside public/ and is deleted after send.
        $temporaryPath = tempnam(sys_get_temp_dir(), 'jaspest-photo-');
        if ($temporaryPath === false) {
            logger()->error('Unable to create temporary file for JASPEST photo response.', [
                'enquiry_id' => $enquiry->getKey(),
                'photo_id' => $record->getKey(),
            ]);

            return response()->json([
                'message' => 'Server tidak dapat menyiapkan foto untuk ditampilkan. Coba lagi.',
                'code' => 'JASPEST_PHOTO_TEMP_FAILED',
            ], 500);
        }

        $written = @file_put_contents($temporaryPath, $bytes, LOCK_EX);
        if ($written === false || $written !== strlen($bytes)) {
            @unlink($temporaryPath);
            logger()->error('Incomplete temporary write while preparing JASPEST photo.', [
                'enquiry_id' => $enquiry->getKey(),
                'photo_id' => $record->getKey(),
                'expected_bytes' => strlen($bytes),
                'written_bytes' => $written,
            ]);

            return response()->json([
                'message' => 'Foto tidak berhasil disiapkan secara utuh oleh server. Coba muat ulang.',
                'code' => 'JASPEST_PHOTO_TEMP_INCOMPLETE',
            ], 500);
        }

        clearstatcache(true, $temporaryPath);
        $temporarySize = @filesize($temporaryPath);
        if ($temporarySize === false || $temporarySize !== strlen($bytes)) {
            @unlink($temporaryPath);
            logger()->error('Temporary JASPEST photo size verification failed.', [
                'enquiry_id' => $enquiry->getKey(),
                'photo_id' => $record->getKey(),
                'expected_bytes' => strlen($bytes),
                'actual_bytes' => $temporarySize,
            ]);

            return response()->json([
                'message' => 'Ukuran foto hasil persiapan server tidak konsisten. Coba muat ulang.',
                'code' => 'JASPEST_PHOTO_TEMP_SIZE_MISMATCH',
            ], 500);
        }

        // A stray newline/BOM before "<?php" in ANY included PHP file is buffered by PHP and
        // silently prepended to the response body. JSON tolerates it, but it corrupts binary
        // images (the browser reads the shifted bytes and cannot decode the picture). Drop
        // whitespace-only buffered output so the image bytes are sent exactly as prepared.
        $strayOutput = ob_get_level() > 0 ? ob_get_contents() : '';
        if (is_string($strayOutput) && $strayOutput !== '' && trim($strayOutput, " \t\n\r\0\x0B\xEF\xBB\xBF") === '') {
            ob_clean();
            logger()->warning('JASPEST discarded stray whitespace output before serving a photo. Check PHP files for characters before <?php.', [
                'enquiry_id' => $enquiry->getKey(),
                'photo_id' => $record->getKey(),
                'stray_bytes' => strlen($strayOutput),
            ]);
        }

        $fileResponse = response()->file($temporaryPath, [
            'Content-Type' => $actualMime,
            'Content-Disposition' => 'inline; filename="jaspest-photo-'.$record->getKey().'.'.$extension.'"',
            'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Jaspest-Image-Mime' => $actualMime,
            'X-Jaspest-Image-Bytes' => (string) $temporarySize,
            'Vary' => 'Cookie',
        ]);
        $fileResponse->deleteFileAfterSend(true);

        return $fileResponse;
    }

    public function logWhatsappOpened(JaspestEnquiry $enquiry): JsonResponse
    {
        abort_unless($enquiry->consent, 422, 'Klien belum menyetujui untuk dihubungi.');

        $updatedEnquiry = DB::transaction(function () use ($enquiry): JaspestEnquiry {
            $locked = JaspestEnquiry::query()->lockForUpdate()->findOrFail($enquiry->id);
            $before = $locked->status;
            // Follow-up is only a wa.me action. Opening the prepared chat does not
            // prove the message was sent; the admin must press Send in WhatsApp.
            // Keep terminal statuses unchanged even if an old link is opened.
            $terminal = in_array($before, ['converted', 'lost'], true);
            $nextStatus = $terminal ? $before : 'followup';
            $timestamp = now();

            $locked->forceFill([
                'wa_opened_at' => $timestamp,
                'status' => $nextStatus,
                'followed_up_at' => $nextStatus === 'followup' ? $timestamp : $locked->followed_up_at,
            ])->save();

            JaspestEnquiryEvent::create([
                'enquiry_id' => $locked->id,
                'user_id' => auth()->id(),
                'event' => 'whatsapp_opened',
                'from_status' => $before,
                'to_status' => $nextStatus,
                'note' => $nextStatus === 'followup'
                    ? 'Admin menyiapkan pesan tindak lanjut berdasarkan data formulir dan membuka percakapan WhatsApp untuk ditinjau sebelum dikirim.'
                    : ($nextStatus === 'converted'
                        ? 'Admin membuka percakapan WhatsApp. Status Deal Order tetap dipertahankan.'
                        : 'Admin membuka percakapan WhatsApp. Status Tidak Deal tetap dipertahankan.'),
            ]);

            return $locked->fresh();
        });

        return response()->json($this->bookingResource($updatedEnquiry->loadCount('photos')));
    }

    /** Catatan internal admin. Disimpan sebagai aktivitas (event) sehingga tampil di riwayat tanpa migrasi baru. */
    public function addBookingNote(Request $request, JaspestEnquiry $enquiry): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        try {
            $event = JaspestEnquiryEvent::create([
                'enquiry_id' => $enquiry->id,
                'user_id' => auth()->id(),
                'event' => 'note_added',
                'from_status' => $enquiry->status,
                'to_status' => $enquiry->status,
                'note' => trim($validated['note']),
            ]);
        } catch (QueryException $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'note' => 'Catatan belum dapat disimpan: kolom "event" di database menolak jenis aktivitas "note_added". Lihat BACA_INI.txt.',
            ]);
        }

        return response()->json([
            'event' => $event->event,
            'from_status' => $event->from_status,
            'to_status' => $event->to_status,
            'note' => $event->note,
            'author' => auth()->user()?->name,
            'created_at' => $event->created_at?->copy()->timezone('Asia/Jakarta')->toIso8601String(),
        ], 201);
    }

    public function storeManualDealOrder(Request $request, JaspestFonnteService $fonnte): JsonResponse
    {
        if (is_string($request->input('whatsapp'))) {
            $request->merge([
                'whatsapp' => preg_replace('/[\s().-]+/', '', trim($request->input('whatsapp'))),
            ]);
        }

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:150'],
            'whatsapp' => ['required', 'string', 'max:24', 'regex:/^\+?[0-9]{8,15}$/'],
            'address' => ['required', 'string', 'min:5', 'max:1000'],
            'property_type' => ['required', Rule::in(['rumah', 'bisnis'])],
            'company_name' => ['required_if:property_type,bisnis', 'nullable', 'string', 'max:200'],
            'need' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== 'konsultasi' && ! JaspestContentItem::query()
                    ->where('type', 'services')->where('status', 'active')->where('slug', $value)->exists()) {
                    $fail('Pilih layanan yang masih tersedia.');
                }
            }],
            'description' => ['nullable', 'string', 'max:3000'],
            'order_price' => ['required', 'numeric', 'min:1', 'max:999999999999.99'],
            'order_area' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'scheduled_date' => ['required', 'date_format:Y-m-d'],
            'assigned_technician_id' => [
                'required', 'integer',
                Rule::exists('jaspest_technicians', 'id')->where('operational_status', 'available'),
            ],
            'consent' => ['accepted'],
        ], [
            'full_name.required' => 'Nama pelanggan wajib diisi.',
            'whatsapp.required' => 'Nomor WhatsApp wajib diisi.',
            'whatsapp.regex' => 'Masukkan nomor WhatsApp yang valid dengan 8–15 digit.',
            'address.required' => 'Alamat pelanggan wajib diisi.',
            'property_type.required' => 'Pilih jenis lokasi pelanggan.',
            'company_name.required_if' => 'Nama perusahaan wajib diisi untuk pelanggan bisnis.',
            'need.required' => 'Pilih layanan yang dipesan.',
            'order_price.required' => 'Harga kesepakatan wajib diisi.',
            'order_price.min' => 'Harga kesepakatan harus lebih dari Rp0.',
            'order_area.required' => 'Luasan pekerjaan wajib diisi.',
            'order_area.gt' => 'Luasan pekerjaan harus lebih dari 0 m².',
            'scheduled_date.required' => 'Tanggal pelaksanaan wajib dipilih.',
            'assigned_technician_id.required' => 'Pilih teknisi yang akan ditugaskan.',
            'assigned_technician_id.exists' => 'Teknisi yang dipilih sudah tidak tersedia. Muat ulang daftar dan pilih teknisi lain.',
            'consent.accepted' => 'Persetujuan pelanggan untuk menerima konfirmasi WhatsApp wajib dicentang.',
        ]);

        $normalizedPhone = $fonnte->normalizeIndonesianMobile((string) $validated['whatsapp']);
        if ($normalizedPhone === null) {
            throw ValidationException::withMessages([
                'whatsapp' => 'Masukkan nomor WhatsApp Indonesia yang valid, misalnya 081234567890.',
            ]);
        }

        $area = round((float) $validated['order_area'], 2);
        $price = round((float) $validated['order_price'], 2);
        $companyName = $validated['property_type'] === 'bisnis'
            ? trim((string) ($validated['company_name'] ?? ''))
            : null;
        $reference = '';
        do {
            $reference = 'JAS-'.now('Asia/Jakarta')->format('ymd').'-'.Str::upper(Str::random(6));
        } while (JaspestEnquiry::query()->where('reference', $reference)->exists());

        $result = DB::transaction(function () use (
            $validated,
            $normalizedPhone,
            $companyName,
            $area,
            $price,
            $reference,
        ): array {
            /** @var JaspestTechnician $technician */
            $technician = JaspestTechnician::query()
                ->whereKey((int) $validated['assigned_technician_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($technician->operational_status !== 'available') {
                throw ValidationException::withMessages([
                    'assigned_technician_id' => 'Teknisi tersebut baru saja ditugaskan. Pilih teknisi lain yang tersedia.',
                ]);
            }

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
                'order_price' => $price,
                'order_area' => $area,
                'scheduled_date' => $validated['scheduled_date'],
                'assigned_technician_id' => $technician->id,
                'assigned_technician_name' => $technician->full_name,
            ]);

            $technician->forceFill(['operational_status' => 'assigned'])->save();

            JaspestEnquiryEvent::create([
                'enquiry_id' => $enquiry->id,
                'user_id' => auth()->id(),
                'event' => 'manual_order_created',
                'from_status' => null,
                'to_status' => 'converted',
                'note' => 'Deal Order dicatat langsung melalui halaman Data Booking.',
                'metadata' => [
                    'source' => 'admin_manual',
                    'order_price' => $price,
                    'order_area' => $area,
                    'scheduled_date' => $validated['scheduled_date'],
                    'assigned_technician_id' => $technician->id,
                    'assigned_technician_name' => $technician->full_name,
                ],
            ]);

            // Profil klien dibuat dan ditautkan dalam transaksi yang sama dengan Deal Order.
            $this->synchronizeDealOrderClient($enquiry);

            $notification = JaspestWhatsappNotification::query()->firstOrCreate(
                [
                    'enquiry_id' => $enquiry->id,
                    'notification_type' => 'deal_order_confirmation',
                ],
                [
                    'recipient' => $normalizedPhone,
                    'message' => '',
                    'status' => 'queued',
                ]
            );

            return [
                'enquiry_id' => $enquiry->id,
                'notification_id' => $notification->id,
            ];
        }, 3);

        $enquiry = JaspestEnquiry::query()->findOrFail($result['enquiry_id']);
        $notification = JaspestWhatsappNotification::query()->find($result['notification_id']);
        if ($notification) {
            try {
                $notification = $fonnte->sendDealOrderConfirmation($enquiry, $notification);
            } catch (\Throwable $exception) {
                report($exception);
                try {
                    $notification->forceFill([
                        'status' => 'failed',
                        'last_error' => 'Order berhasil disimpan, tetapi pesan konfirmasi belum dapat diproses. Periksa log aplikasi sebelum mencoba kembali.',
                        'failed_at' => now(),
                    ])->save();
                    $notification->refresh();
                } catch (\Throwable $loggingException) {
                    report($loggingException);
                }
            }
        }

        $resource = $this->bookingResource($enquiry->fresh()->loadCount('photos'));
        $resource['whatsapp_notification'] = $notification
            ? $this->whatsappNotificationResource($notification)
            : null;
        $resource['message'] = 'Deal Order berhasil ditambahkan.';

        return response()->json($resource, 201);
    }

    public function updateBookingStatus(Request $request, JaspestEnquiry $enquiry, JaspestFonnteService $fonnte): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUS_ACTIONS)],
            'note' => ['nullable', 'string', 'max:1000'],
            'order_price' => ['required_if:status,converted', 'nullable', 'numeric', 'min:1', 'max:999999999999.99'],
            'order_area' => ['required_if:status,converted', 'nullable', 'numeric', 'gt:0', 'max:9999999999.99'],
            'scheduled_date' => ['required_if:status,converted', 'nullable', 'date_format:Y-m-d'],
            'assigned_technician_id' => [
                'required_if:status,converted',
                'nullable',
                'integer',
                Rule::exists('jaspest_technicians', 'id')->where('operational_status', 'available'),
            ],
        ], [
            'order_price.required_if' => 'Harga kesepakatan wajib diisi untuk mengonfirmasi order.',
            'order_price.min' => 'Harga kesepakatan harus lebih dari Rp0.',
            'order_area.required_if' => 'Luasan final wajib diisi untuk mengonfirmasi order.',
            'order_area.gt' => 'Luasan final harus lebih dari 0 m².',
            'scheduled_date.required_if' => 'Tanggal pelaksanaan wajib dipilih.',
            'assigned_technician_id.required_if' => 'Pilih teknisi yang akan ditugaskan.',
            'assigned_technician_id.exists' => 'Teknisi yang dipilih sudah tidak tersedia. Muat ulang daftar dan pilih teknisi lain.',
        ]);

        $status = $validated['status'];

        $whatsappNotificationId = null;

        if ($status === 'converted') {
            DB::transaction(function () use ($enquiry, $validated, &$whatsappNotificationId, $fonnte): void {
                /** @var JaspestEnquiry $lockedEnquiry */
                $lockedEnquiry = JaspestEnquiry::query()->lockForUpdate()->findOrFail($enquiry->id);
                if ($lockedEnquiry->status === 'converted') {
                    abort(422, 'Booking ini sudah berstatus Deal Order. Data order tidak dapat dikonversi ulang.');
                }

                /** @var JaspestTechnician $technician */
                $technician = JaspestTechnician::query()
                    ->lockForUpdate()
                    ->findOrFail((int) $validated['assigned_technician_id']);

                // Re-check availability while holding the row lock to prevent double assignment.
                if ($technician->operational_status !== 'available') {
                    throw ValidationException::withMessages([
                        'assigned_technician_id' => 'Teknisi tersebut baru saja ditugaskan atau sedang tidak tersedia. Pilih teknisi lain.',
                    ]);
                }

                $before = $lockedEnquiry->status;
                $price = round((float) $validated['order_price'], 2);
                $area = round((float) $validated['order_area'], 2);
                $scheduledDate = $validated['scheduled_date'];

                $technician->forceFill(['operational_status' => 'assigned'])->save();
                $lockedEnquiry->forceFill([
                    'status' => 'converted',
                    'converted_at' => now(),
                    'order_price' => $price,
                    'order_area' => $area,
                    'scheduled_date' => $scheduledDate,
                    'assigned_technician_id' => $technician->id,
                    'assigned_technician_name' => $technician->full_name,
                ])->save();

                JaspestEnquiryEvent::create([
                    'enquiry_id' => $lockedEnquiry->id,
                    'user_id' => auth()->id(),
                    'event' => 'order_confirmed',
                    'from_status' => $before,
                    'to_status' => 'converted',
                    'note' => 'Deal Order dikonfirmasi. Harga: Rp'.number_format($price, 0, ',', '.').'; luasan: '.number_format($area, 2, ',', '.').' m²; tanggal pelaksanaan: '.$scheduledDate.'; teknisi: '.$technician->full_name.'.',
                    'metadata' => [
                        'order_price' => $price,
                        'order_area' => $area,
                        'scheduled_date' => $scheduledDate,
                        'assigned_technician_id' => $technician->id,
                        'assigned_technician_name' => $technician->full_name,
                        'technician_code' => $technician->technician_code,
                    ],
                ]);

                // Profil klien dan relasi order dibuat otomatis dalam transaksi yang sama.
                $this->synchronizeDealOrderClient($lockedEnquiry);

                $normalizedRecipient = $fonnte->normalizeIndonesianMobile((string) $lockedEnquiry->whatsapp);
                $notification = JaspestWhatsappNotification::query()->firstOrCreate(
                    [
                        'enquiry_id' => $lockedEnquiry->id,
                        'notification_type' => 'deal_order_confirmation',
                    ],
                    [
                        'recipient' => $normalizedRecipient ?: substr((preg_replace('/\D+/', '', (string) $lockedEnquiry->whatsapp) ?: 'invalid'), 0, 20),
                        'message' => '',
                        'status' => 'queued',
                    ]
                );
                $whatsappNotificationId = $notification->id;
            });

            // The order and its client relation are committed before calling Fonnte.
            // Provider errors never roll back a valid Deal Order.
            $notification = $whatsappNotificationId
                ? JaspestWhatsappNotification::query()->find($whatsappNotificationId)
                : null;
            if ($notification) {
                try {
                    $notification = $fonnte->sendDealOrderConfirmation($enquiry->fresh(), $notification);
                } catch (\Throwable $exception) {
                    // Never make a committed Deal Order appear to have failed
                    // just because a notification bookkeeping step failed.
                    report($exception);
                    try {
                        $notification->forceFill([
                            'status' => 'failed',
                            'last_error' => 'Order berhasil tersimpan, tetapi proses notifikasi mengalami kesalahan internal. Periksa log aplikasi sebelum mencoba ulang.',
                            'failed_at' => now(),
                        ])->save();
                        $notification->refresh();
                    } catch (\Throwable $loggingException) {
                        report($loggingException);
                    }
                }
            }
        } else {
            if ($enquiry->status === 'converted') {
                abort(422, 'Booking berstatus Deal Order tidak dapat dikembalikan melalui dashboard ini.');
            }

            $before = $enquiry->status;
            $timestamps = [
                'followed_up_at' => $status === 'followup' ? now() : $enquiry->followed_up_at,
            ];
            $enquiry->forceFill(array_merge(['status' => $status], $timestamps))->save();

            JaspestEnquiryEvent::create([
                'enquiry_id' => $enquiry->id,
                'user_id' => auth()->id(),
                'event' => 'status_changed',
                'from_status' => $before,
                'to_status' => $status,
                'note' => $validated['note'] ?? null,
            ]);
        }

        $freshEnquiry = $enquiry->fresh()->loadCount('photos');
        $resource = $this->bookingResource($freshEnquiry);
        if ($status === 'converted' && isset($notification)) {
            $resource['whatsapp_notification'] = $this->whatsappNotificationResource($notification);
        }

        return response()->json($resource);
    }

    public function retryDealOrderWhatsapp(
        Request $request,
        JaspestEnquiry $enquiry,
        JaspestFonnteService $fonnte,
    ): JsonResponse {
        abort_unless($enquiry->status === 'converted', 409, 'Notifikasi konfirmasi hanya tersedia untuk booking berstatus Deal Order.');

        /** @var JaspestWhatsappNotification|null $notification */
        $notification = $enquiry->whatsappNotification()->first();
        abort_if(! $notification, 404, 'Riwayat notifikasi Deal Order belum tersedia.');

        if (! in_array($notification->status, ['failed', 'not_configured'], true)) {
            abort(409, 'Notifikasi ini tidak dapat dikirim ulang dari status saat ini.');
        }

        if (! $enquiry->consent) {
            abort(422, 'Pelanggan belum memberikan persetujuan untuk dihubungi.');
        }

        $notification = $fonnte->sendDealOrderConfirmation($enquiry, $notification, true);

        return response()->json([
            'notification' => $this->whatsappNotificationResource($notification),
        ]);
    }

    public function accounts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $protectedIds = array_map('intval', config('jaspest_admin.editor_user_ids', []));
        $baseQuery = User::query()
            ->whereIn('role', self::DIVISION_ROLES)
            ->when($protectedIds !== [], fn ($query) => $query->whereNotIn('id', $protectedIds));

        $query = (clone $baseQuery)->orderBy('name')->orderBy('id');
        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (($validated['status'] ?? 'all') !== 'all') {
            $query->where('is_active', ($validated['status'] ?? 'active') === 'active');
        }

        $paginator = $query->paginate(15)->withQueryString();
        $summary = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('is_active', true)->count(),
            'inactive' => (clone $baseQuery)->where('is_active', false)->count(),
        ];

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (User $account): array => $this->accountResource($account))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'summary' => $summary,
        ]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $data = $this->validatedAccount($request);
        $account = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'phone' => $data['phone'],
            'email' => null,
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => $data['is_active'],
        ]);

        return response()->json($this->accountResource($account), 201);
    }

    public function updateAccount(Request $request, int $account): JsonResponse
    {
        $account = $this->findManagedAccount($account);
        $data = $this->validatedAccount($request, $account);
        $attributes = [
            'name' => $data['name'],
            'username' => $data['username'],
            'phone' => $data['phone'],
            'role' => $data['role'],
            'is_active' => $data['is_active'],
        ];

        if (filled($data['password'] ?? null)) {
            $attributes['password'] = $data['password'];
        }

        $account->update($attributes);

        return response()->json($this->accountResource($account->fresh()));
    }

    public function deleteAccount(int $account): JsonResponse
    {
        $account = $this->findManagedAccount($account);
        $protectedIds = array_map('strval', config('jaspest_admin.editor_user_ids', []));
        if ((string) $account->id === (string) auth()->id() || in_array((string) $account->id, $protectedIds, true)) {
            return response()->json([
                'message' => 'Akun administrator utama atau akun yang sedang digunakan tidak dapat dihapus dari menu ini.',
            ], 409);
        }

        $account->delete();

        return response()->json(['message' => 'Akun berhasil dihapus.']);
    }

    private function findManagedAccount(int $id): User
    {
        $protectedIds = array_map('intval', config('jaspest_admin.editor_user_ids', []));
        return User::query()
            ->whereIn('role', self::DIVISION_ROLES)
            ->when($protectedIds !== [], fn ($query) => $query->whereNotIn('id', $protectedIds))
            ->findOrFail($id);
    }

    private function validatedAccount(Request $request, ?User $account = null): array
    {
        if (is_string($request->input('username'))) {
            $request->merge(['username' => Str::lower(trim($request->input('username')))]);
        }
        if (is_string($request->input('phone'))) {
            $phone = preg_replace('/[\s().-]+/', '', trim($request->input('phone')));
            $request->merge(['phone' => $phone]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'regex:/\A[a-zA-Z0-9._-]+\z/', Rule::unique('users', 'username')->ignore($account?->id)],
            'phone' => ['required', 'string', 'max:24', 'regex:/\A\+?[0-9]{8,15}\z/'],
            'role' => ['required', Rule::in(self::DIVISION_ROLES)],
            'password' => [$account ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['required', 'boolean'],
        ], [
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.',
            'password.required' => 'Password wajib diisi untuk membuat akun baru.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'phone.regex' => 'Nomor telepon harus terdiri dari 8–15 digit dan boleh diawali tanda +.',
        ]);

        $validated['is_active'] = (bool) $validated['is_active'];
        return $validated;
    }

    private function divisionLabel(string $role): string
    {
        return [
            'direksi' => 'Direksi',
            'finance' => 'Finance',
            'logistik' => 'Logistik',
            'marketing' => 'Marketing',
        ][$role] ?? 'Tidak diketahui';
    }

    /**
     * Direktori klien otomatis dari seluruh booking berstatus Deal Order.
     * Satu nomor WhatsApp dinormalisasi menjadi satu profil klien dan dapat
     * memiliki banyak booking/order yang tertaut.
     */
    public function clients(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        // Rekonsiliasi idempotent untuk Deal Order lama atau order yang dibuat
        // melalui jalur integrasi lain. Order baru juga disinkronkan saat konversi.
        $syncIssues = $this->synchronizeUnlinkedDealOrders();

        $search = trim((string) ($validated['search'] ?? ''));
        $clientQuery = JaspestClient::query()
            ->withCount('enquiries')
            ->withSum('enquiries as lifetime_value', 'order_price')
            ->orderBy('full_name')->orderBy('id');

        if ($search !== '') {
            $clientQuery->where(function ($builder) use ($search): void {
                $builder->where('client_code', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('whatsapp', 'like', "%{$search}%");
            });
        }
        if (($validated['status'] ?? 'all') !== 'all') {
            $clientQuery->where('status', $validated['status']);
        }

        $clientPage = $clientQuery->paginate(15, ['*'], 'page')->withQueryString();
        $summary = [
            'total' => JaspestClient::count(),
            'active' => JaspestClient::where('status', 'active')->count(),
            'inactive' => JaspestClient::where('status', 'inactive')->count(),
            'linked_orders' => JaspestClientEnquiryLink::count(),
        ];

        return response()->json([
            'data' => $clientPage->getCollection()
                ->map(fn (JaspestClient $client): array => $this->clientResource($client))
                ->values(),
            'meta' => [
                'current_page' => $clientPage->currentPage(),
                'last_page' => $clientPage->lastPage(),
                'total' => $clientPage->total(),
            ],
            'summary' => $summary,
            'sync_issues' => $syncIssues,
        ])->header('Cache-Control', 'no-store, private');
    }

    /** Sinkronkan semua Deal Order yang belum memiliki relasi klien. Aman dipanggil berulang. */
    private function synchronizeUnlinkedDealOrders(): int
    {
        $issues = 0;

        JaspestEnquiry::query()
            ->where('status', 'converted')
            ->whereDoesntHave('clientLink')
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$issues): void {
                foreach ($orders as $order) {
                    try {
                        $this->synchronizeDealOrderClient($order);
                    } catch (ValidationException|QueryException $exception) {
                        // Data yang tidak memenuhi syarat tetap terlihat sebagai
                        // booking Deal Order dan dapat diperbaiki tanpa membuat
                        // profil klien yang identitasnya tidak valid.
                        $issues++;
                        if ($exception instanceof QueryException) {
                            report($exception);
                        }
                    }
                }
            });

        return $issues;
    }

    /**
     * Buat/temukan profil berdasarkan nomor WhatsApp dan tautkan Deal Order.
     * Dipanggil di dalam transaksi konversi maupun rekonsiliasi direktori.
     */
    private function synchronizeDealOrderClient(JaspestEnquiry $enquiry): ?JaspestClient
    {
        return DB::transaction(function () use ($enquiry): ?JaspestClient {
            $lockedEnquiry = JaspestEnquiry::query()->lockForUpdate()->findOrFail($enquiry->id);
            if ($lockedEnquiry->status !== 'converted') {
                return null;
            }

            $existingLink = JaspestClientEnquiryLink::query()
                ->where('enquiry_id', $lockedEnquiry->id)
                ->lockForUpdate()
                ->first();
            if ($existingLink) {
                return JaspestClient::query()->findOrFail($existingLink->client_id);
            }

            $normalized = $this->normalizeClientWhatsapp((string) $lockedEnquiry->whatsapp);
            if (! preg_match('/^628[1-9][0-9]{7,11}$/', $normalized)) {
                throw ValidationException::withMessages([
                    'whatsapp' => 'Deal Order '.$lockedEnquiry->reference.' belum dapat disinkronkan: nomor WhatsApp tidak valid.',
                ]);
            }

            // Unique index pada whatsapp_normalized melindungi dua order dari
            // nomor yang sama agar tidak menghasilkan dua profil klien.
            $client = JaspestClient::query()->firstOrCreate(
                ['whatsapp_normalized' => $normalized],
                [
                    'client_code' => 'KLI-'.Str::upper(Str::random(10)),
                    'full_name' => $lockedEnquiry->full_name,
                    'company_name' => $lockedEnquiry->company_name,
                    'whatsapp' => $lockedEnquiry->whatsapp,
                    'email' => null,
                    'address' => $lockedEnquiry->address,
                    'status' => 'active',
                ]
            );

            // Unique index enquiry_id menjamin satu booking hanya tertaut ke
            // satu klien meskipun sinkronisasi dipanggil hampir bersamaan.
            $link = JaspestClientEnquiryLink::query()->firstOrCreate(
                ['enquiry_id' => $lockedEnquiry->id],
                [
                    'client_id' => $client->id,
                    'linked_by' => auth()->id(),
                ]
            );

            if ((int) $link->client_id !== (int) $client->id) {
                $client = JaspestClient::query()->findOrFail($link->client_id);
            }

            if ($link->wasRecentlyCreated) {
                JaspestEnquiryEvent::create([
                    'enquiry_id' => $lockedEnquiry->id,
                    'user_id' => auth()->id(),
                    'event' => 'client_linked',
                    'from_status' => 'converted',
                    'to_status' => 'converted',
                    'note' => 'Data klien otomatis disinkronkan dari Deal Order: '.$client->client_code.' — '.$client->full_name.'.',
                    'metadata' => [
                        'client_id' => $client->id,
                        'client_code' => $client->client_code,
                        'automatic' => true,
                    ],
                ]);
            }

            return $client;
        }, 3);
    }

    public function updateClient(Request $request, JaspestClient $client): JsonResponse
    {
        if (is_string($request->input('whatsapp'))) {
            $phone = preg_replace('/[\\s().-]+/', '', trim($request->input('whatsapp')));
            $request->merge(['whatsapp' => $phone]);
        }

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:200'],
            'whatsapp' => ['required', 'string', 'max:24', 'regex:/^\\+?[0-9]{8,15}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:3000'],
        ], [
            'whatsapp.regex' => 'Nomor WhatsApp harus terdiri dari 8–15 digit dan boleh diawali tanda +.',
        ]);

        $validated['whatsapp_normalized'] = $this->normalizeClientWhatsapp($validated['whatsapp']);
        $duplicate = JaspestClient::query()
            ->where('whatsapp_normalized', $validated['whatsapp_normalized'])
            ->where('id', '<>', $client->id)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'whatsapp' => 'Nomor WhatsApp tersebut sudah digunakan oleh klien lain. Periksa Data Klien sebelum menyimpan.',
            ]);
        }

        $client->update($validated);

        return response()->json([
            'message' => 'Data klien berhasil diperbarui.',
            'data' => $this->clientResource($client->fresh()->loadCount('enquiries')->loadSum('enquiries as lifetime_value', 'order_price')),
        ]);
    }

    private function normalizeClientWhatsapp(string $phone): string
    {
        $digits = preg_replace('/\\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }
        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }
        return $digits;
    }

    public function technicians(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'available', 'assigned', 'off_duty'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = JaspestTechnician::query()->orderBy('full_name');
        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('full_name', 'like', "%{$search}%")
                    ->orWhere('technician_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('specialization', 'like', "%{$search}%")
                    ->orWhere('service_area', 'like', "%{$search}%");
            });
        }
        if (($validated['status'] ?? 'all') !== 'all') {
            $query->where('operational_status', $validated['status']);
        }

        $paginator = $query->paginate(15)->withQueryString();
        $summary = [
            'total' => JaspestTechnician::count(),
            'available' => JaspestTechnician::where('operational_status', 'available')->count(),
            'assigned' => JaspestTechnician::where('operational_status', 'assigned')->count(),
            'off_duty' => JaspestTechnician::where('operational_status', 'off_duty')->count(),
        ];

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (JaspestTechnician $technician): array => $this->technicianResource($technician))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'summary' => $summary,
        ]);
    }

    /** Return existing technicians with an explicit availability flag for order assignment. */
    public function bookingTechnicians(): JsonResponse
    {
        $items = JaspestTechnician::query()->orderBy('full_name')->get()->map(fn (JaspestTechnician $technician): array => [
            'id' => $technician->id,
            'technician_code' => $technician->technician_code,
            'full_name' => $technician->full_name,
            'specialization' => $technician->specialization,
            'operational_status' => $technician->operational_status,
            'is_available' => $technician->operational_status === 'available',
        ])->values();

        return response()->json(['data' => $items])->header('Cache-Control', 'no-store, private');
    }

    public function storeTechnician(Request $request): JsonResponse
    {
        $data = $this->validatedTechnician($request);
        $data['technician_code'] = filled($data['technician_code'] ?? null)
            ? $data['technician_code']
            : 'TKN-'.Str::upper(Str::random(8));
        $technician = JaspestTechnician::create($data);

        return response()->json($this->technicianResource($technician), 201);
    }

    public function updateTechnician(Request $request, JaspestTechnician $technician): JsonResponse
    {
        $data = $this->validatedTechnician($request, $technician);
        if (blank($data['technician_code'] ?? null)) {
            unset($data['technician_code']);
        }
        $updatedTechnician = DB::transaction(function () use ($data, $technician): JaspestTechnician {
            $lockedTechnician = JaspestTechnician::query()->whereKey($technician->id)->lockForUpdate()->firstOrFail();
            if ($data['operational_status'] === 'off_duty'
                && $lockedTechnician->operational_status !== 'off_duty'
                && JaspestInventoryTechnicianStock::query()->where('technician_id', $lockedTechnician->id)->where('quantity', '>', 0)->exists()) {
                throw ValidationException::withMessages([
                    'operational_status' => 'Saldo inventaris teknisi masih ada. Catat pengembalian barang ke gudang sebelum mengubah status menjadi di luar tugas.',
                ]);
            }
            $lockedTechnician->update($data);
            return $lockedTechnician->fresh();
        }, 3);

        return response()->json($this->technicianResource($updatedTechnician));
    }

    public function deleteTechnician(JaspestTechnician $technician): JsonResponse
    {
        $deleted = DB::transaction(function () use ($technician): bool {
            $lockedTechnician = JaspestTechnician::query()->whereKey($technician->id)->lockForUpdate()->firstOrFail();
            $hasInventoryHistory = JaspestInventoryMovement::query()->where('technician_id', $lockedTechnician->id)->exists()
                || JaspestInventoryMaintenance::query()->where('technician_id', $lockedTechnician->id)->exists()
                || JaspestInventoryTechnicianStock::query()->where('technician_id', $lockedTechnician->id)->where('quantity', '>', 0)->exists();
            if ($hasInventoryHistory) return false;
            $lockedTechnician->delete();
            return true;
        }, 3);

        if (! $deleted) {
            return response()->json([
                'message' => 'Teknisi memiliki riwayat inventaris atau jadwal pemeliharaan. Ubah status menjadi Di luar tugas agar histori dan saldo tetap aman.',
            ], 409);
        }
        return response()->json(['message' => 'Data teknisi berhasil dihapus.']);
    }

    private function validatedTechnician(Request $request, ?JaspestTechnician $technician = null): array
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }

        return $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:2000'],
            'technician_code' => ['nullable', 'string', 'max:30', Rule::unique('jaspest_technicians', 'technician_code')->ignore($technician?->id)],
            'specialization' => ['required', 'string', 'min:2', 'max:120'],
            'service_area' => ['nullable', 'string', 'max:180'],
            'certification' => ['nullable', 'string', 'max:180'],
            'operational_status' => ['required', Rule::in(['available', 'assigned', 'off_duty'])],
            'notes' => ['nullable', 'string', 'max:3000'],
        ], [
            'email.required' => 'Email teknisi wajib diisi.',
            'email.email' => 'Masukkan alamat email teknisi yang valid.',
            'email.max' => 'Email teknisi maksimal 150 karakter.',
        ]);
    }

    /** Inventory dashboard data, including low-stock and expiry alerts. */
    public function inventorySummary(): JsonResponse
    {
        $today = now('Asia/Jakarta')->toDateString();
        $expiryLimit = now('Asia/Jakarta')->addDays(30)->toDateString();
        $items = JaspestInventoryItem::query()->where('is_active', true);
        $lowStock = (clone $items)->where('minimum_stock', '>', 0)
            ->whereColumn('stock_on_hand', '<=', 'minimum_stock')
            ->orderByRaw('CASE WHEN stock_on_hand = 0 THEN 0 ELSE 1 END')
            ->orderBy('stock_on_hand')->limit(8)->get();

        $expiryQuery = fn () => JaspestInventoryBatch::query()
            ->whereNotNull('expires_at')
            ->where(function ($query): void {
                $query->where('quantity_remaining', '>', 0)
                    ->orWhereHas('technicianStocks', fn ($stocks) => $stocks->where('quantity', '>', 0));
            });
        $expiring = $expiryQuery()->with('item:id,sku,name,unit')
            ->withSum('technicianStocks as technician_quantity', 'quantity')
            ->whereDate('expires_at', '<=', $expiryLimit)
            ->orderBy('expires_at')->limit(8)->get();

        $stockValue = (float) JaspestInventoryBatch::query()
            ->selectRaw('COALESCE(SUM(quantity_remaining * unit_cost), 0) as total_value')
            ->value('total_value');

        return response()->json([
            'summary' => [
                'items' => (clone $items)->count(),
                // Do not sum unlike units (liter, pcs, kg, unit) into one misleading total.
                'stocked_items' => (clone $items)->where('stock_on_hand', '>', 0)->count(),
                'low_stock' => (clone $items)->where('minimum_stock', '>', 0)
                    ->whereColumn('stock_on_hand', '<=', 'minimum_stock')->count(),
                'expiring_batches' => (clone $expiryQuery())->whereDate('expires_at', '<=', $expiryLimit)->count(),
                'expired_batches' => (clone $expiryQuery())->whereDate('expires_at', '<', $today)->count(),
                'stock_value' => $stockValue,
                'technician_stock_units' => (float) JaspestInventoryTechnicianStock::query()->sum('quantity'),
                'maintenance_due' => JaspestInventoryMaintenance::query()->where('status', 'planned')
                    ->whereDate('scheduled_date', '<=', $expiryLimit)->count(),
            ],
            'low_stock_items' => $lowStock->map(fn (JaspestInventoryItem $item): array => [
                'id' => $item->id, 'sku' => $item->sku, 'name' => $item->name,
                'stock_on_hand' => (float) $item->stock_on_hand, 'minimum_stock' => (float) $item->minimum_stock,
                'unit' => $item->unit,
            ])->values(),
            'expiring_items' => $expiring->map(function (JaspestInventoryBatch $batch): array {
                $warehouseQuantity = (float) $batch->quantity_remaining;
                $technicianQuantity = (float) ($batch->technician_quantity ?? 0);
                return [
                    'id' => $batch->id, 'item_id' => $batch->item_id,
                    'sku' => $batch->item?->sku, 'name' => $batch->item?->name,
                    'batch_number' => $batch->batch_number, 'expires_at' => $batch->expires_at?->format('Y-m-d'),
                    'quantity_remaining' => round($warehouseQuantity + $technicianQuantity, 3),
                    'warehouse_quantity' => $warehouseQuantity,
                    'technician_quantity' => $technicianQuantity,
                    'unit' => $batch->item?->unit,
                ];
            })->values(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function inventoryItems(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['all', 'asset', 'consumable'])],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = JaspestInventoryItem::query()->orderBy('name');
        if (filled($validated['search'] ?? null)) {
            $term = '%'.addcslashes(trim($validated['search']), '%_\\').'%';
            $query->where(fn ($builder) => $builder->where('sku', 'like', $term)
                ->orWhere('name', 'like', $term)->orWhere('category', 'like', $term)
                ->orWhere('brand', 'like', $term)->orWhere('specification', 'like', $term));
        }
        if (($validated['type'] ?? 'all') !== 'all') {
            $query->where('item_type', $validated['type']);
        }
        if (($validated['status'] ?? 'all') !== 'all') {
            $query->where('is_active', $validated['status'] === 'active');
        }

        $paginator = $query->paginate(15)->withQueryString();
        return response()->json([
            'data' => $paginator->getCollection()->map(fn (JaspestInventoryItem $item): array => $this->inventoryItemResource($item))->values(),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function inventoryOptions(): JsonResponse
    {
        return response()->json([
            'items' => JaspestInventoryItem::query()->where('is_active', true)->orderBy('name')->limit(500)
                ->get(['id', 'sku', 'name', 'unit', 'item_type', 'stock_on_hand', 'track_expiry'])
                ->map(fn (JaspestInventoryItem $item): array => [
                    'id' => $item->id, 'sku' => $item->sku, 'name' => $item->name,
                    'unit' => $item->unit, 'item_type' => $item->item_type, 'stock_on_hand' => (float) $item->stock_on_hand,
                    'track_expiry' => (bool) $item->track_expiry,
                ])->values(),
            'assets' => JaspestInventoryItem::query()->where('is_active', true)->where('item_type', 'asset')
                ->orderBy('name')->limit(500)->get(['id', 'sku', 'name', 'unit'])
                ->map(fn (JaspestInventoryItem $item): array => [
                    'id' => $item->id, 'sku' => $item->sku, 'name' => $item->name, 'unit' => $item->unit,
                ])->values(),
            'technicians' => JaspestTechnician::query()->where('operational_status', '!=', 'off_duty')
                ->orderBy('full_name')->get(['id', 'technician_code', 'full_name', 'operational_status'])
                ->map(fn (JaspestTechnician $technician): array => [
                    'id' => $technician->id, 'code' => $technician->technician_code,
                    'name' => $technician->full_name, 'status' => $technician->operational_status,
                ])->values(),
            'orders' => JaspestEnquiry::query()->where('status', 'converted')->latest('converted_at')
                ->limit(300)->get(['id', 'reference', 'full_name', 'need', 'scheduled_date'])
                ->map(fn (JaspestEnquiry $order): array => [
                    'id' => $order->id, 'reference' => $order->reference,
                    'name' => $order->full_name, 'need' => $order->need,
                    'scheduled_date' => $order->scheduled_date?->format('Y-m-d'),
                ])->values(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function storeInventoryItem(Request $request): JsonResponse
    {
        if (filled($request->input('sku'))) $request->merge(['sku' => strtoupper(trim((string) $request->input('sku')))]);
        $data = $request->validate([
            'sku' => ['nullable', 'string', 'max:40', Rule::unique('jaspest_inventory_items', 'sku')],
            'name' => ['required', 'string', 'min:2', 'max:180'],
            'category' => ['required', 'string', 'min:2', 'max:80'],
            'item_type' => ['required', Rule::in(['asset', 'consumable'])],
            'brand' => ['nullable', 'string', 'max:120'],
            'specification' => ['nullable', 'string', 'max:3000'],
            'unit' => ['required', 'string', 'max:24'],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'track_expiry' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);
        $data['sku'] = filled($data['sku'] ?? null) ? strtoupper(trim($data['sku'])) : $this->generateInventorySku();
        $item = JaspestInventoryItem::create($data);
        return response()->json($this->inventoryItemResource($item), 201);
    }

    public function updateInventoryItem(Request $request, JaspestInventoryItem $item): JsonResponse
    {
        if (filled($request->input('sku'))) $request->merge(['sku' => strtoupper(trim((string) $request->input('sku')))]);
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:40', Rule::unique('jaspest_inventory_items', 'sku')->ignore($item->id)],
            'name' => ['required', 'string', 'min:2', 'max:180'],
            'category' => ['required', 'string', 'min:2', 'max:80'],
            'item_type' => ['required', Rule::in(['asset', 'consumable'])],
            'brand' => ['nullable', 'string', 'max:120'],
            'specification' => ['nullable', 'string', 'max:3000'],
            'unit' => ['required', 'string', 'max:24'],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'track_expiry' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);
        $updatedItem = DB::transaction(function () use ($data, $item): JaspestInventoryItem {
            $lockedItem = JaspestInventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $hasMovements = $lockedItem->movements()->exists();
            if ($lockedItem->item_type !== $data['item_type'] && $hasMovements) {
                throw ValidationException::withMessages(['item_type' => 'Jenis barang tidak dapat diubah setelah memiliki riwayat mutasi.']);
            }
            if ((bool) $lockedItem->track_expiry !== (bool) $data['track_expiry'] && $hasMovements) {
                throw ValidationException::withMessages(['track_expiry' => 'Pengaturan pelacakan kedaluwarsa tidak dapat diubah setelah barang memiliki riwayat mutasi.']);
            }
            if ($lockedItem->unit !== $data['unit'] && $hasMovements) {
                throw ValidationException::withMessages(['unit' => 'Satuan barang tidak dapat diubah setelah memiliki riwayat mutasi agar histori dan saldo tetap konsisten.']);
            }
            if ($lockedItem->is_active && ! (bool) $data['is_active']) {
                $hasStock = (float) $lockedItem->stock_on_hand > 0
                    || $lockedItem->technicianStocks()->where('quantity', '>', 0)->exists();
                $hasPlannedMaintenance = $lockedItem->maintenance()->where('status', 'planned')->exists();
                if ($hasStock || $hasPlannedMaintenance) {
                    throw ValidationException::withMessages([
                        'is_active' => 'Barang masih memiliki stok di gudang/teknisi atau jadwal pemeliharaan aktif. Selesaikan mutasi dan jadwal terlebih dahulu sebelum menonaktifkan.',
                    ]);
                }
            }
            $lockedItem->update($data);
            return $lockedItem->fresh();
        }, 3);
        return response()->json($this->inventoryItemResource($updatedItem));
    }

    public function deleteInventoryItem(JaspestInventoryItem $item): JsonResponse
    {
        $deleted = DB::transaction(function () use ($item): bool {
            $lockedItem = JaspestInventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ((float) $lockedItem->stock_on_hand > 0 || $lockedItem->movements()->exists()
                || $lockedItem->technicianStocks()->where('quantity', '>', 0)->exists()
                || $lockedItem->maintenance()->exists()) {
                return false;
            }
            $lockedItem->delete();
            return true;
        }, 3);
        if (! $deleted) {
            return response()->json(['message' => 'Barang memiliki saldo atau riwayat. Nonaktifkan barang agar histori tetap aman.'], 409);
        }
        return response()->json(['message' => 'Barang berhasil dihapus.']);
    }

    public function inventoryMovements(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['all', 'stock_in', 'stock_out', 'distribution', 'usage', 'return', 'adjustment_in', 'adjustment_out', 'write_off'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = JaspestInventoryMovement::query()->with(['item:id,sku,name,unit', 'technician:id,full_name,technician_code', 'enquiry:id,reference,full_name'])
            ->latest();
        if (($validated['type'] ?? 'all') !== 'all') $query->where('movement_type', $validated['type']);
        if (filled($validated['search'] ?? null)) {
            $term = '%'.addcslashes(trim($validated['search']), '%_\\').'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('movement_code', 'like', $term)->orWhere('batch_number', 'like', $term)
                    ->orWhere('supplier_name', 'like', $term)->orWhereHas('item', fn ($item) => $item->where('name', 'like', $term)->orWhere('sku', 'like', $term))
                    ->orWhereHas('technician', fn ($technician) => $technician->where('full_name', 'like', $term))
                    ->orWhereHas('enquiry', fn ($enquiry) => $enquiry->where('reference', 'like', $term)->orWhere('full_name', 'like', $term));
            });
        }
        $paginator = $query->paginate(25)->withQueryString();
        return response()->json([
            'data' => $paginator->getCollection()->map(fn (JaspestInventoryMovement $movement): array => $this->inventoryMovementResource($movement))->values(),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function storeInventoryMovement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'movement_type' => ['required', Rule::in(['stock_in', 'stock_out', 'distribution', 'usage', 'return', 'adjustment_in', 'adjustment_out', 'write_off'])],
            'item_id' => ['required', 'integer', 'exists:jaspest_inventory_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999'],
            'technician_id' => ['nullable', 'integer', 'required_if:movement_type,distribution,usage,return', 'exists:jaspest_technicians,id'],
            'enquiry_id' => ['nullable', 'integer', 'required_if:movement_type,usage', 'exists:jaspest_enquiries,id'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'batch_number' => ['nullable', 'string', 'max:80'],
            'expires_at' => ['nullable', 'date_format:Y-m-d'],
            'supplier_name' => ['nullable', 'string', 'max:180'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000', 'required_if:movement_type,stock_out,adjustment_in,adjustment_out,write_off'],
        ]);

        $movement = DB::transaction(function () use ($data): JaspestInventoryMovement {
            /** @var JaspestInventoryItem $item */
            $item = JaspestInventoryItem::query()->whereKey($data['item_id'])->lockForUpdate()->firstOrFail();
            if (! $item->is_active) {
                throw ValidationException::withMessages(['item_id' => 'Barang ini sudah nonaktif dan tidak dapat dimutasi.']);
            }

            $type = $data['movement_type'];
            if ($type === 'usage' && $item->item_type !== 'consumable') {
                throw ValidationException::withMessages(['item_id' => 'Pemakaian pekerjaan hanya dapat dicatat untuk bahan habis pakai. Aset/peralatan dicatat melalui distribusi dan pengembalian.']);
            }
            if (in_array($type, ['stock_in', 'adjustment_in'], true)) {
                $expiryDate = $data['expires_at'] ?? null;
                if ($item->track_expiry && blank($expiryDate)) {
                    throw ValidationException::withMessages(['expires_at' => 'Tanggal kedaluwarsa wajib diisi untuk barang yang melacak kedaluwarsa.']);
                }
                if (filled($expiryDate) && $expiryDate < now('Asia/Jakarta')->toDateString()) {
                    throw ValidationException::withMessages(['expires_at' => 'Tanggal kedaluwarsa tidak boleh sudah lewat saat stok diterima.']);
                }
            }

            $quantity = round((float) $data['quantity'], 3);
            if ($quantity <= 0) {
                throw ValidationException::withMessages(['quantity' => 'Jumlah minimal adalah 0,001.']);
            }
            $before = round((float) $item->stock_on_hand, 3);
            $after = $before;
            $batchAllocations = [];
            $movementBatchNumber = filled($data['batch_number'] ?? null) ? trim($data['batch_number']) : null;
            $technician = null;
            $enquiry = null;
            $unitCost = round((float) ($data['unit_cost'] ?? 0), 2);

            if (in_array($type, ['distribution', 'usage', 'return'], true)) {
                $technician = JaspestTechnician::query()->whereKey($data['technician_id'])
                    ->lockForUpdate()->firstOrFail();
                if ($technician->operational_status === 'off_duty' && $type !== 'return') {
                    throw ValidationException::withMessages(['technician_id' => 'Teknisi sedang di luar tugas dan tidak dapat menerima distribusi atau pemakaian barang.']);
                }
            }
            if ($type === 'usage') {
                $enquiry = JaspestEnquiry::query()->whereKey($data['enquiry_id'])
                    ->where('status', 'converted')->lockForUpdate()->first();
                if (! $enquiry) {
                    throw ValidationException::withMessages(['enquiry_id' => 'Pemakaian barang harus dikaitkan dengan booking berstatus Deal Order.']);
                }
            }

            $makeBatch = function (string $prefix = 'BATCH') use ($item, $data, $quantity, $unitCost): JaspestInventoryBatch {
                return JaspestInventoryBatch::create([
                    'item_id' => $item->id,
                    'batch_number' => filled($data['batch_number'] ?? null) ? trim($data['batch_number']) : $prefix.'-'.now('Asia/Jakarta')->format('ymd').'-'.Str::upper(Str::random(5)),
                    'expires_at' => $data['expires_at'] ?? null,
                    'quantity_received' => $quantity,
                    'quantity_remaining' => $quantity,
                    'unit_cost' => $unitCost,
                    'supplier_name' => $data['supplier_name'] ?? null,
                    'invoice_number' => $data['invoice_number'] ?? null,
                    'received_at' => now('Asia/Jakarta')->toDateString(),
                ]);
            };

            $consumeWarehouse = function (bool $includeExpired = false) use ($item, $quantity): array {
                $remaining = $quantity;
                $allocations = [];
                $batchesQuery = JaspestInventoryBatch::query()->where('item_id', $item->id)->where('quantity_remaining', '>', 0);
                if (! $includeExpired) {
                    $batchesQuery->where(function ($query): void {
                        $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', now('Asia/Jakarta')->toDateString());
                    });
                }
                $batches = $batchesQuery->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('expires_at')->orderBy('id')->lockForUpdate()->get();
                foreach ($batches as $batch) {
                    if ($remaining <= 0.0005) break;
                    $available = round((float) $batch->quantity_remaining, 3);
                    $taken = min($available, $remaining);
                    if ($taken <= 0) continue;
                    $batch->quantity_remaining = round($available - $taken, 3);
                    $batch->save();
                    $allocations[] = [
                        'batch_id' => $batch->id, 'batch_number' => $batch->batch_number,
                        'quantity' => round($taken, 3), 'expires_at' => $batch->expires_at?->format('Y-m-d'),
                        'unit_cost' => (float) $batch->unit_cost,
                    ];
                    $remaining = round($remaining - $taken, 3);
                }
                if ($remaining > 0.0005) {
                    throw ValidationException::withMessages(['quantity' => 'Stok layak pakai di gudang tidak mencukupi. Periksa stok tersedia dan batch kedaluwarsa.']);
                }
                return $allocations;
            };

            $consumeTechnicianBatches = function (bool $includeExpired = false) use ($item, $technician, $quantity): array {
                $today = now('Asia/Jakarta')->toDateString();
                $balancesQuery = JaspestInventoryTechnicianBatch::query()
                    ->join('jaspest_inventory_batches as inventory_batches', 'inventory_batches.id', '=', 'jaspest_inventory_technician_batches.batch_id')
                    ->where('jaspest_inventory_technician_batches.item_id', $item->id)
                    ->where('jaspest_inventory_technician_batches.technician_id', $technician->id)
                    ->where('jaspest_inventory_technician_batches.quantity', '>', 0);
                if (! $includeExpired) {
                    $balancesQuery->where(function ($query) use ($today): void {
                        $query->whereNull('inventory_batches.expires_at')->orWhereDate('inventory_batches.expires_at', '>=', $today);
                    });
                }
                $balances = $balancesQuery
                    ->orderByRaw('CASE WHEN inventory_batches.expires_at IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('inventory_batches.expires_at')->orderBy('inventory_batches.id')
                    ->select('jaspest_inventory_technician_batches.*')->lockForUpdate()->get();
                $remaining = $quantity;
                $allocations = [];
                foreach ($balances as $balance) {
                    if ($remaining <= 0.0005) break;
                    $batch = JaspestInventoryBatch::query()->whereKey($balance->batch_id)->lockForUpdate()->firstOrFail();
                    $available = round((float) $balance->quantity, 3);
                    $taken = min($available, $remaining);
                    if ($taken <= 0) continue;
                    $balance->quantity = round($available - $taken, 3);
                    $balance->save();
                    $allocations[] = [
                        'batch_id' => $batch->id, 'batch_number' => $batch->batch_number,
                        'quantity' => round($taken, 3), 'expires_at' => $batch->expires_at?->format('Y-m-d'),
                        'unit_cost' => (float) $batch->unit_cost,
                    ];
                    $remaining = round($remaining - $taken, 3);
                }
                if ($remaining > 0.0005) {
                    $message = $includeExpired
                        ? 'Saldo barang pada teknisi tidak mencukupi untuk pengembalian.'
                        : 'Saldo barang layak pakai pada teknisi tidak mencukupi. Batch kedaluwarsa tidak dapat digunakan.';
                    throw ValidationException::withMessages(['quantity' => $message]);
                }
                return $allocations;
            };

            $weightedUnitCost = function (array $allocations) use ($quantity): float {
                if ($allocations === []) return 0.0;
                $total = array_reduce($allocations, fn (float $sum, array $allocation): float => $sum + ((float) $allocation['quantity'] * (float) $allocation['unit_cost']), 0.0);
                return round($total / $quantity, 2);
            };

            if (in_array($type, ['stock_in', 'adjustment_in'], true)) {
                $receivedBatch = $makeBatch($type === 'stock_in' ? 'BATCH' : 'ADJ');
                $movementBatchNumber = $receivedBatch->batch_number;
                $after = round($before + $quantity, 3);
            } elseif (in_array($type, ['stock_out', 'adjustment_out', 'distribution', 'write_off'], true)) {
                if ($before + 0.0005 < $quantity) {
                    throw ValidationException::withMessages(['quantity' => 'Jumlah melebihi stok gudang yang tersedia.']);
                }
                $batchAllocations = $consumeWarehouse($type === 'write_off');
                $unitCost = $weightedUnitCost($batchAllocations);
                $after = round($before - $quantity, 3);
                if ($type === 'distribution') {
                    foreach ($batchAllocations as $allocation) {
                        $techBatch = JaspestInventoryTechnicianBatch::query()->firstOrCreate(
                            ['item_id' => $item->id, 'technician_id' => $technician->id, 'batch_id' => $allocation['batch_id']],
                            ['quantity' => 0]
                        );
                        $techBatch = JaspestInventoryTechnicianBatch::query()->whereKey($techBatch->id)->lockForUpdate()->firstOrFail();
                        $techBatch->quantity = round((float) $techBatch->quantity + (float) $allocation['quantity'], 3);
                        $techBatch->save();
                    }
                    $aggregate = JaspestInventoryTechnicianStock::query()->firstOrCreate(
                        ['item_id' => $item->id, 'technician_id' => $technician->id], ['quantity' => 0]
                    );
                    $aggregate = JaspestInventoryTechnicianStock::query()->whereKey($aggregate->id)->lockForUpdate()->firstOrFail();
                    $aggregate->quantity = round((float) $aggregate->quantity + $quantity, 3);
                    $aggregate->save();
                }
            } elseif (in_array($type, ['usage', 'return'], true)) {
                $aggregate = JaspestInventoryTechnicianStock::query()->where('item_id', $item->id)
                    ->where('technician_id', $technician->id)->lockForUpdate()->first();
                $allocatedQuantity = (float) JaspestInventoryTechnicianBatch::query()
                    ->where('item_id', $item->id)->where('technician_id', $technician->id)->sum('quantity');
                if (! $aggregate || abs($allocatedQuantity - (float) $aggregate->quantity) > 0.0005) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Saldo batch teknisi tidak sinkron dengan saldo total. Hubungi administrator untuk rekonsiliasi sebelum melanjutkan.',
                    ]);
                }
                if ((float) $aggregate->quantity + 0.0005 < $quantity) {
                    throw ValidationException::withMessages(['quantity' => 'Jumlah melebihi saldo barang teknisi.']);
                }
                $batchAllocations = $consumeTechnicianBatches($type === 'return');
                $unitCost = $weightedUnitCost($batchAllocations);
                $aggregate->quantity = round((float) $aggregate->quantity - $quantity, 3);
                $aggregate->save();

                if ($type === 'return') {
                    foreach ($batchAllocations as $allocation) {
                        $batch = JaspestInventoryBatch::query()->whereKey($allocation['batch_id'])->lockForUpdate()->firstOrFail();
                        $batch->quantity_remaining = round((float) $batch->quantity_remaining + (float) $allocation['quantity'], 3);
                        $batch->save();
                    }
                    $after = round($before + $quantity, 3);
                }
            }

            $item->stock_on_hand = $after;
            $item->save();

            $movementCode = 'INV-'.now('Asia/Jakarta')->format('ymd').'-'.Str::upper(Str::random(8));
            return JaspestInventoryMovement::create([
                'movement_code' => $movementCode,
                'item_id' => $item->id,
                'movement_type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'warehouse_before' => $before,
                'warehouse_after' => $after,
                'technician_id' => $technician?->id,
                'enquiry_id' => $enquiry?->id,
                'supplier_name' => $data['supplier_name'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'batch_number' => $movementBatchNumber,
                'expires_at' => $data['expires_at'] ?? null,
                'batch_allocations' => $batchAllocations ?: null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
        }, 3);

        return response()->json($this->inventoryMovementResource($movement->load(['item', 'technician', 'enquiry'])), 201);
    }

    public function inventoryTechnicianStocks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = JaspestInventoryTechnicianStock::query()->with([
            'item:id,sku,name,unit', 'technician:id,full_name,technician_code,operational_status',
        ])->where('quantity', '>', 0)->latest('updated_at');
        if (filled($validated['search'] ?? null)) {
            $term = '%'.addcslashes(trim($validated['search']), '%_\\').'%';
            $query->where(fn ($builder) => $builder
                ->whereHas('item', fn ($item) => $item->where('name', 'like', $term)->orWhere('sku', 'like', $term))
                ->orWhereHas('technician', fn ($technician) => $technician->where('full_name', 'like', $term)->orWhere('technician_code', 'like', $term)));
        }
        $paginator = $query->paginate(20)->withQueryString();
        $stockRows = $paginator->getCollection();
        $batchBalances = collect();
        if ($stockRows->isNotEmpty()) {
            $batchBalances = JaspestInventoryTechnicianBatch::query()
                ->with('batch:id,batch_number,expires_at')
                ->where('quantity', '>', 0)
                ->where(function ($builder) use ($stockRows): void {
                    foreach ($stockRows as $stockRow) {
                        $builder->orWhere(fn ($pair) => $pair->where('item_id', $stockRow->item_id)
                            ->where('technician_id', $stockRow->technician_id));
                    }
                })
                ->orderBy('batch_id')->get()
                ->groupBy(fn (JaspestInventoryTechnicianBatch $balance): string => $balance->item_id.':'.$balance->technician_id);
        }
        return response()->json([
            'data' => $stockRows->map(function (JaspestInventoryTechnicianStock $stock) use ($batchBalances): array {
                $key = $stock->item_id.':'.$stock->technician_id;
                $batches = ($batchBalances->get($key) ?? collect())->map(fn (JaspestInventoryTechnicianBatch $balance): array => [
                    'batch_number' => $balance->batch?->batch_number,
                    'expires_at' => $balance->batch?->expires_at?->format('Y-m-d'),
                    'quantity' => (float) $balance->quantity,
                ])->values();
                return [
                    'id' => $stock->id, 'item_id' => $stock->item_id, 'item_name' => $stock->item?->name,
                    'sku' => $stock->item?->sku, 'unit' => $stock->item?->unit,
                    'technician_id' => $stock->technician_id, 'technician_name' => $stock->technician?->full_name,
                    'technician_code' => $stock->technician?->technician_code,
                    'technician_status' => $stock->technician?->operational_status,
                    'quantity' => (float) $stock->quantity, 'batches' => $batches,
                    'updated_at' => $stock->updated_at?->copy()->timezone('Asia/Jakarta')->toIso8601String(),
                ];
            })->values(),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function inventoryMaintenance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'planned', 'completed', 'cancelled'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = JaspestInventoryMaintenance::query()->with(['item:id,sku,name,unit', 'technician:id,full_name,technician_code'])->orderByDesc('scheduled_date')->latest('id');
        if (($validated['status'] ?? 'all') !== 'all') $query->where('status', $validated['status']);
        $paginator = $query->paginate(20)->withQueryString();
        return response()->json([
            'data' => $paginator->getCollection()->map(fn (JaspestInventoryMaintenance $item): array => $this->inventoryMaintenanceResource($item))->values(),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function storeInventoryMaintenance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:jaspest_inventory_items,id'],
            'technician_id' => ['nullable', 'integer', 'exists:jaspest_technicians,id'],
            'maintenance_type' => ['required', Rule::in(['inspection', 'cleaning', 'repair', 'calibration', 'servicing'])],
            'scheduled_date' => ['required', 'date_format:Y-m-d'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $maintenance = DB::transaction(function () use ($data): JaspestInventoryMaintenance {
            $item = JaspestInventoryItem::query()->whereKey($data['item_id'])->lockForUpdate()->firstOrFail();
            if ($item->item_type !== 'asset' || ! $item->is_active) {
                throw ValidationException::withMessages(['item_id' => 'Pemeliharaan hanya tersedia untuk aset aktif.']);
            }
            if (! empty($data['technician_id'])) {
                $technician = JaspestTechnician::query()->whereKey($data['technician_id'])->lockForUpdate()->firstOrFail();
                if ($technician->operational_status === 'off_duty') {
                    throw ValidationException::withMessages(['technician_id' => 'Teknisi sedang di luar tugas.']);
                }
            }
            return JaspestInventoryMaintenance::create([
                ...$data,
                'cost' => $data['cost'] ?? 0,
                'status' => 'planned',
                'created_by' => auth()->id(),
            ])->load(['item', 'technician']);
        }, 3);
        return response()->json($this->inventoryMaintenanceResource($maintenance), 201);
    }

    public function updateInventoryMaintenanceStatus(Request $request, JaspestInventoryMaintenance $maintenance): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['planned', 'completed', 'cancelled'])]]);
        if ($maintenance->status === 'completed' && $data['status'] === 'planned') {
            throw ValidationException::withMessages(['status' => 'Pemeliharaan yang sudah selesai tidak dapat dibuka kembali dari riwayat.']);
        }
        $maintenance->status = $data['status'];
        $maintenance->completed_at = $data['status'] === 'completed' ? now() : null;
        $maintenance->save();
        return response()->json($this->inventoryMaintenanceResource($maintenance->fresh(['item', 'technician'])));
    }

    private function generateInventorySku(): string
    {
        do {
            $sku = 'JAS-'.Str::upper(Str::random(8));
        } while (JaspestInventoryItem::query()->where('sku', $sku)->exists());
        return $sku;
    }

    private function inventoryItemResource(JaspestInventoryItem $item): array
    {
        $technicianStock = (float) JaspestInventoryTechnicianStock::query()->where('item_id', $item->id)->sum('quantity');
        return [
            'id' => $item->id, 'sku' => $item->sku, 'name' => $item->name,
            'category' => $item->category, 'item_type' => $item->item_type,
            'brand' => $item->brand, 'specification' => $item->specification,
            'unit' => $item->unit, 'minimum_stock' => (float) $item->minimum_stock,
            'stock_on_hand' => (float) $item->stock_on_hand,
            'technician_stock' => $technicianStock,
            'track_expiry' => (bool) $item->track_expiry,
            'is_active' => (bool) $item->is_active,
            'stock_state' => (float) $item->minimum_stock > 0 && (float) $item->stock_on_hand <= (float) $item->minimum_stock ? 'low' : 'normal',
            'created_at' => $item->created_at?->toIso8601String(),
        ];
    }

    private function inventoryMovementResource(JaspestInventoryMovement $movement): array
    {
        $labels = [
            'stock_in' => 'Stok masuk', 'stock_out' => 'Stok keluar', 'distribution' => 'Distribusi teknisi',
            'usage' => 'Pemakaian pekerjaan', 'return' => 'Pengembalian',
            'adjustment_in' => 'Penyesuaian masuk', 'adjustment_out' => 'Penyesuaian keluar', 'write_off' => 'Pemusnahan / barang kedaluwarsa',
        ];
        return [
            'id' => $movement->id, 'movement_code' => $movement->movement_code,
            'movement_type' => $movement->movement_type, 'movement_label' => $labels[$movement->movement_type] ?? $movement->movement_type,
            'item_id' => $movement->item_id, 'item_name' => $movement->item?->name,
            'sku' => $movement->item?->sku, 'unit' => $movement->item?->unit,
            'quantity' => (float) $movement->quantity, 'unit_cost' => (float) $movement->unit_cost,
            'warehouse_before' => (float) $movement->warehouse_before, 'warehouse_after' => (float) $movement->warehouse_after,
            'technician_id' => $movement->technician_id, 'technician_name' => $movement->technician?->full_name,
            'order_reference' => $movement->enquiry?->reference, 'client_name' => $movement->enquiry?->full_name,
            'supplier_name' => $movement->supplier_name, 'invoice_number' => $movement->invoice_number,
            'batch_number' => $movement->batch_number, 'expires_at' => $movement->expires_at?->format('Y-m-d'),
            'batch_allocations' => $movement->batch_allocations, 'notes' => $movement->notes,
            'created_at' => $movement->created_at?->copy()->timezone('Asia/Jakarta')->toIso8601String(),
        ];
    }

    private function inventoryMaintenanceResource(JaspestInventoryMaintenance $maintenance): array
    {
        $labels = [
            'inspection' => 'Inspeksi', 'cleaning' => 'Pembersihan', 'repair' => 'Perbaikan',
            'calibration' => 'Kalibrasi', 'servicing' => 'Servis berkala',
        ];
        return [
            'id' => $maintenance->id, 'item_id' => $maintenance->item_id,
            'item_name' => $maintenance->item?->name, 'sku' => $maintenance->item?->sku,
            'technician_id' => $maintenance->technician_id, 'technician_name' => $maintenance->technician?->full_name,
            'maintenance_type' => $maintenance->maintenance_type,
            'maintenance_label' => $labels[$maintenance->maintenance_type] ?? $maintenance->maintenance_type,
            'scheduled_date' => $maintenance->scheduled_date?->format('Y-m-d'),
            'completed_at' => $maintenance->completed_at?->copy()->timezone('Asia/Jakarta')->toIso8601String(),
            'status' => $maintenance->status, 'cost' => (float) $maintenance->cost,
            'notes' => $maintenance->notes,
        ];
    }

    public function contentIndex(string $type): JsonResponse
    {
        $this->assertType($type);

        if ($type === 'articles') {
            $items = BlogPost::query()->latest('updated_at')->get()->map(fn (BlogPost $post): array => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'category' => $post->category,
                'description' => $post->excerpt,
                'body' => $post->body,
                'image_url' => $post->coverUrl(),
                'status' => $post->status === 'published' ? 'Aktif' : 'Draft',
                'updated_at' => $post->updated_at?->toIso8601String(),
            ]);
        } else {
            $items = JaspestContentItem::where('type', $type)->orderBy('sort_order')->orderByDesc('updated_at')->get()
                ->map(fn (JaspestContentItem $item): array => $this->contentResource($item));
        }

        return response()->json(['data' => $items->values()]);
    }

    public function storeContent(Request $request, string $type): JsonResponse
    {
        $this->assertType($type);
        if ($type === 'pages') {
            throw ValidationException::withMessages(['title' => 'Slot halaman bawaan tidak dapat ditambah. Edit salah satu slot yang tersedia.']);
        }
        $data = $this->validatedContent($request, $type);

        if ($type === 'articles') {
            $post = BlogPost::create($this->articlePayload($data, $request));
            return response()->json($this->articleResource($post), 201);
        }

        $item = JaspestContentItem::create($this->contentPayload($data, $request, $type));
        return response()->json($this->contentResource($item), 201);
    }

    public function updateContent(Request $request, string $type, int $id): JsonResponse
    {
        $this->assertType($type);
        $data = $this->validatedContent($request, $type);

        if ($type === 'articles') {
            $post = BlogPost::findOrFail($id);
            $oldPath = $post->cover_path;
            $payload = $this->articlePayload($data, $request, $post);
            $post->update($payload);
            if (! empty($payload['cover_path']) && $oldPath && $oldPath !== $payload['cover_path']) {
                Storage::disk('public')->delete(str_starts_with($oldPath, 'storage/') ? substr($oldPath, 8) : $oldPath);
            }
            if ($request->boolean('remove_image') && ! $request->hasFile('image') && $oldPath) {
                Storage::disk('public')->delete(str_starts_with($oldPath, 'storage/') ? substr($oldPath, 8) : $oldPath);
                $post->update(['cover_path' => null]);
            }
            return response()->json($this->articleResource($post->fresh()));
        }

        $item = JaspestContentItem::where('type', $type)->findOrFail($id);
        $oldPath = $item->image_path;
        $payload = $this->contentPayload($data, $request, $type, $item);
        $item->update($payload);
        if (! empty($payload['image_path']) && $oldPath && $oldPath !== $payload['image_path']) {
            Storage::disk(config('jaspest_admin.content_disk', 'public'))->delete($oldPath);
        }
        if ($request->boolean('remove_image') && ! $request->hasFile('image') && $oldPath) {
            Storage::disk(config('jaspest_admin.content_disk', 'public'))->delete($oldPath);
            $item->update(['image_path' => null]);
        }
        return response()->json($this->contentResource($item->fresh()));
    }

    public function deleteContent(string $type, int $id): JsonResponse
    {
        $this->assertType($type);
        if ($type === 'pages') {
            throw ValidationException::withMessages(['title' => 'Slot halaman bawaan tidak dapat dihapus.']);
        }
        if ($type === 'articles') {
            $post = BlogPost::findOrFail($id);
            if ($post->cover_path) {
                Storage::disk('public')->delete(str_starts_with($post->cover_path, 'storage/') ? substr($post->cover_path, 8) : $post->cover_path);
            }
            $post->delete();
        } else {
            $item = JaspestContentItem::where('type', $type)->findOrFail($id);
            if ($item->image_path) {
                Storage::disk(config('jaspest_admin.content_disk', 'public'))->delete($item->image_path);
            }
            $item->delete();
        }

        return response()->json(['message' => 'Konten berhasil dihapus.']);
    }

    private function validatedContent(Request $request, string $type): array
    {
        $article = $type === 'articles';

        return $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:'.($article ? 160 : 180)],
            'slug' => ['nullable', 'string', 'max:'.($article ? 180 : 200), 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'category' => ['nullable', 'string', 'max:'.($article ? 60 : 120)],
            'description' => ['nullable', 'string', 'max:'.($article ? 300 : 500)],
            'body' => ['nullable', 'string', 'max:100000'],
            'status' => ['required', Rule::in(['Aktif', 'Draft', 'active', 'draft'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'image_alt' => ['nullable', 'string', 'max:180'],
            'remove_image' => ['nullable', 'boolean'],
            'icon' => ['nullable', 'string', 'max:40'],
            'keywords' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function contentPayload(array $data, Request $request, string $type, ?JaspestContentItem $existing = null): array
    {
        $slug = ($type === 'pages' && $existing) ? $existing->slug : ($data['slug'] ?: Str::slug($data['title']));
        $base = $slug;
        $suffix = 2;
        while (JaspestContentItem::where('type', $type)->where('slug', $slug)
            ->when($existing, fn ($query) => $query->where('id', '<>', $existing->getKey()))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        $extra = $existing?->data ?? [];
        if (filled($data['icon'] ?? null)) {
            $extra['icon'] = $data['icon'];
        }
        if ($type === 'faqs' && array_key_exists('keywords', $data)) {
            $extra['keywords'] = array_values(array_filter(array_map('trim', explode(',', (string) $data['keywords']))));
        }
        $payload = [
            'type' => $type,
            'title' => $data['title'],
            'slug' => $slug,
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'body' => $data['body'] ?? null,
            'status' => in_array($data['status'], ['Aktif', 'active'], true) ? 'active' : 'draft',
            'sort_order' => $data['sort_order'] ?? 0,
            'image_alt' => $data['image_alt'] ?? null,
            'data' => $extra,
        ];
        if ($type === 'settings' && $slug === 'nama-perusahaan') {
            $companyName = trim((string) ($payload['description'] ?? ''));
            $companyName = preg_replace('/^(?:PT|CV)\.?\s*/iu', '', $companyName) ?: 'Jaya Abadi Solusi';
            $payload['description'] = 'CV '.(trim($companyName) ?: 'Jaya Abadi Solusi');
        }
        if ($request->hasFile('image')) {
            $payload['image_path'] = $request->file('image')->store('jaspest-content', config('jaspest_admin.content_disk', 'public'));
        }
        return $payload;
    }

    private function articlePayload(array $data, Request $request, ?BlogPost $existing = null): array
    {
        $slug = $data['slug'] ?: Str::slug($data['title']);
        $base = $slug;
        $suffix = 2;
        while (BlogPost::where('slug', $slug)->when($existing, fn ($query) => $query->where('id', '<>', $existing->getKey()))->exists()) {
            $slug = $base.'-'.$suffix++;
        }
        $published = in_array($data['status'], ['Aktif', 'active'], true);
        $payload = [
            'title' => $data['title'],
            'slug' => $slug,
            'category' => Str::limit($data['category'] ?: 'Informasi', 60, ''),
            'excerpt' => Str::limit($data['description'] ?: strip_tags($data['body'] ?? ''), 300),
            'body' => $data['body'] ?: ($data['description'] ?? ''),
            'author_name' => $existing?->author_name ?: 'Tim JASPEST',
            'cover_alt' => $data['image_alt'] ?? null,
            'meta_title' => $existing?->meta_title,
            'meta_description' => $existing?->meta_description,
            'status' => $published ? 'published' : 'draft',
            'published_at' => $published ? ($existing?->published_at ?: now()) : null,
        ];
        if ($request->hasFile('image')) {
            $newPath = $request->file('image')->store('jaspest-blog', 'public');
            $payload['cover_path'] = 'storage/'.$newPath;
        }
        return $payload;
    }

    private function contentResource(JaspestContentItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'slug' => $item->slug,
            'category' => $item->category ?? '',
            'description' => $item->description ?? '',
            'body' => $item->body ?? '',
            'image_url' => \App\Support\JaspestMedia::url($item->image_path),
            // Gambar bawaan (dari seeder) dipakai sebagai pratinjau bila belum ada foto unggahan, sama seperti di website.
            'default_image_url' => ! empty($item->data['default_image']) ? asset('jaspest/'.$item->data['default_image']) : \App\Support\JaspestMedia::placeholder((string) $item->type, $item->slug),
            'image_alt' => $item->image_alt ?? '',
            'icon' => $item->data['icon'] ?? '',
            'keywords' => implode(', ', $item->data['keywords'] ?? []),
            'status' => $item->status === 'active' ? 'Aktif' : 'Draft',
            'sort_order' => $item->sort_order,
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }

    private function articleResource(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'category' => $post->category,
            'description' => $post->excerpt,
            'body' => $post->body,
            'image_url' => $post->coverUrl(),
            'image_alt' => $post->cover_alt ?? '',
            'keywords' => '',
            'status' => $post->status === 'published' ? 'Aktif' : 'Draft',
            'sort_order' => 0,
            'updated_at' => $post->updated_at?->toIso8601String(),
        ];
    }

    private function accountResource(User $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'username' => $account->username,
            'phone' => $account->phone,
            'role' => $account->role,
            'role_label' => $this->divisionLabel((string) $account->role),
            'is_active' => (bool) $account->is_active,
            'created_at' => $account->created_at?->toIso8601String(),
        ];
    }

    private function clientResource(JaspestClient $client): array
    {
        return [
            'id' => $client->id,
            'client_code' => $client->client_code,
            'full_name' => $client->full_name,
            'company_name' => $client->company_name,
            'whatsapp' => $client->whatsapp,
            'email' => $client->email,
            'address' => $client->address,
            'status' => $client->status,
            'notes' => $client->notes,
            'order_count' => (int) ($client->enquiries_count ?? $client->enquiries()->count()),
            'lifetime_value' => (string) ($client->lifetime_value ?? $client->enquiries()->sum('order_price') ?? 0),
            'created_at' => $client->created_at?->toIso8601String(),
            'updated_at' => $client->updated_at?->toIso8601String(),
        ];
    }

    private function technicianResource(JaspestTechnician $technician): array
    {
        return [
            'id' => $technician->id,
            'technician_code' => $technician->technician_code,
            'full_name' => $technician->full_name,
            'email' => $technician->email,
            'address' => $technician->address,
            'specialization' => $technician->specialization,
            'service_area' => $technician->service_area,
            'certification' => $technician->certification,
            'operational_status' => $technician->operational_status,
            'notes' => $technician->notes,
            'created_at' => $technician->created_at?->toIso8601String(),
        ];
    }

    private function whatsappNotificationResource(JaspestWhatsappNotification $notification): array
    {
        return [
            'status' => $notification->status,
            'recipient' => $notification->recipient,
            'last_error' => match ($notification->status) {
                'not_configured' => 'Pesan konfirmasi belum dapat dikirim. Hubungi administrator sistem.',
                'failed' => 'Pesan konfirmasi belum berhasil dikirim. Periksa nomor pelanggan dan koneksi, lalu coba kirim ulang.',
                'unknown' => 'Status pengiriman belum dapat dipastikan. Periksa riwayat pesan sebelum mencoba kembali.',
                'skipped_consent' => 'Persetujuan pelanggan untuk menerima pesan belum tercatat.',
                'skipped_invalid_number' => 'Nomor WhatsApp pelanggan perlu diperiksa.',
                default => null,
            },
            'accepted_at' => $notification->accepted_at?->copy()->timezone('Asia/Jakarta')->toIso8601String(),
            'sent_at' => $notification->sent_at?->copy()->timezone('Asia/Jakarta')->toIso8601String(),
            'failed_at' => $notification->failed_at?->copy()->timezone('Asia/Jakarta')->toIso8601String(),
            'retryable' => in_array($notification->status, ['failed', 'not_configured'], true),
        ];
    }

    private function bookingResource(JaspestEnquiry $item): array
    {
        $service = JaspestContentItem::where('type', 'services')->where('slug', $item->need)->value('title');
        $need = $service ?: (str_contains($item->need, '-') ? Str::of($item->need)->replace('-', ' ')->title()->toString() : $item->need);
        $createdAt = $item->created_at?->copy()->timezone('Asia/Jakarta');
        $waOpenedAt = $item->wa_opened_at?->copy()->timezone('Asia/Jakarta');
        $contactedAt = $item->contacted_at?->copy()->timezone('Asia/Jakarta');

        return [
            'id' => $item->reference,
            'database_id' => $item->id,
            'reference' => $item->reference,
            'name' => $item->full_name,
            'whatsapp' => $item->whatsapp,
            'address' => $item->address,
            'area' => (string) $item->estimated_area,
            'need' => $need,
            'need_code' => $item->need,
            'property' => $item->property_type,
            'company' => $item->company_name,
            'description' => $item->description,
            'date' => $createdAt?->locale('id')->translatedFormat('d M Y, H:i').' WIB',
            'created_at' => $createdAt?->toIso8601String(),
            'status' => $item->status === 'contacted' ? 'followup' : $item->status,
            'consent' => (bool) $item->consent,
            'photo_count' => $item->photos_count ?? $item->photos()->count(),
            'wa_opened_at' => $waOpenedAt?->toIso8601String(),
            'contacted_at' => $contactedAt?->toIso8601String(),
            'order_price' => $item->order_price !== null ? (string) $item->order_price : null,
            'order_area' => $item->order_area !== null ? (string) $item->order_area : null,
            'scheduled_date' => $item->scheduled_date?->format('Y-m-d'),
            'assigned_technician_id' => $item->assigned_technician_id,
            'assigned_technician_name' => $item->assigned_technician_name,
        ];
    }

    private function assertType(string $type): void
    {
        abort_unless(in_array($type, self::CONTENT_TYPES, true), 404);
    }
}
