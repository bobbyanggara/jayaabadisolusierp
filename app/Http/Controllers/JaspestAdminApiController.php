<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\JaspestContentItem;
use App\Models\JaspestEnquiry;
use App\Models\JaspestEnquiryEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
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
    private const STATUSES = ['new', 'contacted', 'followup', 'converted', 'lost'];

    public function dashboard(): JsonResponse
    {
        $counts = [];
        foreach (self::STATUSES as $status) {
            $counts[$status] = JaspestEnquiry::where('status', $status)->count();
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

        $oldestNew = JaspestEnquiry::where('status', 'new')->oldest()->first()?->created_at?->copy()->timezone('Asia/Jakarta')->toIso8601String();

        return response()->json([
            'stats' => [
                'total' => JaspestEnquiry::count(),
                'new' => $counts['new'],
                'contacted' => $counts['contacted'],
                'followup' => $counts['followup'],
                'converted' => $counts['converted'],
                'lost' => $counts['lost'],
            ],
            'series' => $days,
            'recent' => $recent,
            'waiting' => ['oldest_new_at' => $oldestNew],
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
            $query->where('status', $validated['status']);
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
            $query->where('status', $validated['status']);
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
            'new' => 'Belum dihubungi',
            'contacted' => 'Sudah dihubungi',
            'followup' => 'Follow-up',
            'converted' => 'Menjadi order',
            'lost' => 'Tidak deal',
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
        $enquiry->load(['photos', 'events']);
        $resource = $this->bookingResource($enquiry);
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
            'note' => $event->note,
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
        $enquiry->forceFill(['wa_opened_at' => now()])->save();
        JaspestEnquiryEvent::create([
            'enquiry_id' => $enquiry->id,
            'user_id' => auth()->id(),
            'event' => 'whatsapp_opened',
            'from_status' => $enquiry->status,
            'to_status' => $enquiry->status,
            'note' => 'Admin membuka tautan WhatsApp. Pengiriman pesan belum dikonfirmasi.',
        ]);

        return response()->json(['message' => 'Aktivitas WhatsApp dicatat.']);
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

    public function updateBookingStatus(Request $request, JaspestEnquiry $enquiry): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($enquiry->status === 'converted' && $validated['status'] !== 'converted') {
            abort(422, 'Booking yang sudah menjadi order tidak dapat dikembalikan melalui dashboard ini.');
        }

        $before = $enquiry->status;
        $status = $validated['status'];
        $timestamps = [
            'contacted_at' => $status === 'contacted' ? now() : $enquiry->contacted_at,
            'followed_up_at' => $status === 'followup' ? now() : $enquiry->followed_up_at,
            'converted_at' => $status === 'converted' ? now() : $enquiry->converted_at,
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

        return response()->json($this->bookingResource($enquiry->fresh()->loadCount('photos')));
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
            'image_url' => $item->image_path ? Storage::disk(config('jaspest_admin.content_disk', 'public'))->url($item->image_path) : null,
            // Gambar bawaan (dari seeder) dipakai sebagai pratinjau bila belum ada foto unggahan, sama seperti di website.
            'default_image_url' => ! empty($item->data['default_image']) ? asset('jaspest/'.$item->data['default_image']) : null,
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
            'status' => $item->status,
            'consent' => (bool) $item->consent,
            'photo_count' => $item->photos_count ?? $item->photos()->count(),
            'wa_opened_at' => $waOpenedAt?->toIso8601String(),
            'contacted_at' => $contactedAt?->toIso8601String(),
        ];
    }

    private function assertType(string $type): void
    {
        abort_unless(in_array($type, self::CONTENT_TYPES, true), 404);
    }
}
