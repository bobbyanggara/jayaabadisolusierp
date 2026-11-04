<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\JaspestContentItem;
use App\Models\JaspestClient;
use App\Models\JaspestClientEnquiryLink;
use App\Models\JaspestEnquiry;
use App\Models\JaspestEnquiryEvent;
use App\Models\JaspestInventoryItem;
use App\Models\JaspestInventoryMaintenance;
use App\Models\JaspestInventoryMovement;
use App\Models\JaspestTechnician;
use App\Models\JaspestWhatsappNotification;
use App\Services\JaspestFonnteService;
use App\Support\JaspestTikTok;
use App\Support\JaspestMap;
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
    private const CONTENT_TYPES = ['pages', 'services', 'pests', 'methods', 'articles', 'gallery', 'faqs', 'certifications', 'settings'];

    /**
     * Bagian halaman utama website yang dapat diubah dari "Konten Halaman".
     * Urutan sama dengan urutan tampil di website (atas ke bawah). Setiap bagian hanya
     * memuat kolom yang benar-benar dipakai website, sehingga admin dan website selalu sama.
     */
    private const PAGE_SLOTS = [
        'beranda-hero' => [
            'section' => 'Beranda',
            'label' => 'Bagian utama',
            'where' => 'Tampil di bagian paling atas halaman utama website.',
            'icon' => 'house',
            'default_image' => 'assets/hero-pest-control.webp',
            'defaults' => ['title' => 'Rumah nyaman. Bisnis tetap jalan.', 'description' => 'Jaga ruang yang berarti bagi Anda. Konsultasikan kebutuhan pengendalian hama rumah dan bisnis bersama JASPEST.', 'body' => null, 'status' => 'active', 'sort_order' => 1],
            'fields' => [
                'title' => ['label' => 'Judul besar', 'max' => 100, 'required' => true],
                'description' => ['label' => 'Paragraf di bawah judul', 'max' => 300, 'rows' => 3, 'required' => true],
                'image' => ['label' => 'Foto utama (sisi kanan)', 'remove_label' => 'Hapus foto unggahan dan pakai foto bawaan website'],
                'image_alt' => ['label' => 'Deskripsi foto', 'max' => 180, 'hint' => 'Dibaca oleh pembaca layar dan tampil bila foto gagal dimuat.'],
            ],
        ],
        'tentang-jaspest' => [
            'section' => 'Tentang Kami',
            'label' => 'Judul, isi, foto, label foto, dan tombol',
            'where' => 'Tampil pada bagian Tentang Kami: judul dan paragraf di sisi kanan, foto di sisi kiri beserta label biru di bagian bawah foto, dan tombol di bawah paragraf.',
            'icon' => 'file',
            'default_image' => 'assets/residential-inspection.webp',
            'defaults' => ['title' => 'CV. JAYA ABADI SOLUSI', 'category' => 'JASPEST', 'description' => 'Sejak 2008 di Sidoarjo', 'body' => "JASPEST adalah perusahaan yang sudah berpengalaman lebih dari 15 tahun di bidang Jasa Pest Control (pengendalian hama).\n\nJASPEST berdiri di Sidoarjo sejak tahun 2008. Kami hadir sebagai mitra terpercaya bagi sektor residensial (rumah tinggal) maupun komersial (bisnis/industri).\n\nKami menerapkan Sistem IPM (Integrated Pest Management) dalam pengendalian hama yang ramah lingkungan, aman, dan efektif. Bagi kami, mewujudkan kepuasan pelanggan adalah prioritas utama dalam setiap layanan yang kami berikan.", 'status' => 'active', 'sort_order' => 2, 'data' => ['cta' => 'Mari bicarakan kebutuhan Anda']],
            'fields' => [
                'title' => ['label' => 'Judul', 'max' => 80, 'required' => true],
                'body' => ['label' => 'Isi Tentang Kami', 'max' => 3000, 'rows' => 9, 'required' => true, 'hint' => 'Pisahkan antar paragraf dengan satu baris kosong. Paragraf pertama tampil lebih tebal.'],
                'image' => ['label' => 'Foto (sisi kiri)', 'remove_label' => 'Hapus foto unggahan dan pakai foto bawaan website'],
                'image_alt' => ['label' => 'Deskripsi foto', 'max' => 180, 'hint' => 'Dibaca oleh pembaca layar dan tampil bila foto gagal dimuat.'],
                'category' => ['label' => 'Label pada foto: baris atas', 'max' => 30, 'required' => true, 'hint' => 'Label biru di bagian bawah foto.'],
                'description' => ['label' => 'Label pada foto: baris tebal di bawahnya', 'max' => 60, 'required' => true],
                'cta' => ['label' => 'Teks tombol', 'max' => 40, 'required' => true, 'hint' => 'Tombol di bawah paragraf; mengarah ke formulir konsultasi.'],
            ],
        ],
        'video-tiktok' => [
            'section' => 'Video',
            'label' => 'Pemutar TikTok',
            'where' => 'Pemutar video pada bagian "Lebih dekat dengan kegiatan kami". Video tampil bila status Aktif dan link valid.',
            'icon' => 'camera',
            'defaults' => ['title' => 'Video kegiatan JASPEST', 'description' => null, 'body' => null, 'status' => 'draft', 'sort_order' => 3],
            'fields' => [
                'description' => ['label' => 'Link video TikTok', 'max' => 500, 'rows' => 2, 'kind' => 'url', 'placeholder' => 'https://www.tiktok.com/@akun/video/1234567890123456789', 'hint' => 'Tempel link video lengkap. Link pendek vm.tiktok.com juga dapat dicoba.'],
                'title' => ['label' => 'Judul pada sampul video', 'max' => 80, 'required' => true],
                'category' => ['label' => 'Nama akun sumber (opsional)', 'max' => 120, 'placeholder' => '@jaspest', 'hint' => 'Kosongkan bila ingin diambil otomatis dari link.'],
                'status' => ['label' => 'Status video', 'required' => true, 'hint' => 'Aktif: video tampil di website. Draft: video disembunyikan.'],
            ],
        ],
        'kontak-konsultasi' => [
            'section' => 'Formulir Konsultasi',
            'label' => 'Judul dan pengantar',
            'where' => 'Teks di samping formulir pengajuan pada bagian paling bawah halaman utama.',
            'icon' => 'chat',
            'defaults' => ['title' => 'Mulai dari kebutuhan Anda', 'description' => 'Isi pengajuan agar tim JASPEST dapat memahami kebutuhan dan menghubungi Anda.', 'body' => null, 'status' => 'active', 'sort_order' => 4],
            'fields' => [
                'title' => ['label' => 'Judul', 'max' => 100, 'required' => true],
                'description' => ['label' => 'Kalimat pengantar', 'max' => 300, 'rows' => 3, 'required' => true],
            ],
        ],
    ];
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
            'No.', 'Nomor Referensi', 'Nama Klien', 'Nomor WhatsApp', 'Alamat',
            'Perkiraan Luas (m²)', 'Layanan', 'Tipe Lokasi', 'Nama Perusahaan',
            'Keterangan', 'Tanggal Diterima', 'Waktu Diterima (WIB)', 'Status', 'Persetujuan Kontak',
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
        $filename = 'JASPEST-Manajemen-Order-'.now('Asia/Jakarta')->format('Y-m-d').'.xlsx';

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
        $sheetRows[] = '<row r="1" ht="32" customHeight="1">'.$cell('A1', 'JASPEST | MANAJEMEN ORDER', 1).'</row>';
        $sheetRows[] = '<row r="2" ht="22" customHeight="1">'.$cell('A2', 'Laporan order layanan — diekspor '.$exportedAt.' WIB', 2).'</row>';
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
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Manajemen Order" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => $styles,
            'xl/worksheets/sheet1.xml' => $sheet,
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:creator>JASPEST</dc:creator><dc:title>Manajemen Order JASPEST</dc:title></cp:coreProperties>',
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
                ? 'Pesan tindak lanjut disiapkan dari informasi permintaan layanan dan percakapan pelanggan dibuka untuk ditinjau.'
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
                'message' => 'Foto belum dapat ditampilkan. Silakan coba kembali.',
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
                'message' => 'Foto belum dapat dimuat dengan lengkap. Silakan muat ulang halaman.',
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
                'message' => 'Foto belum dapat diproses. Silakan muat ulang halaman.',
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
                'note' => 'Catatan belum dapat disimpan. Silakan coba kembali atau hubungi administrator.',
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
                if (! JaspestContentItem::query()
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
                'note' => 'Order manual dicatat melalui Manajemen Order.',
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
        $resource['message'] = 'Order Manual berhasil dibuat.';

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
            'order_area.required_if' => 'Luas pekerjaan wajib diisi untuk mengonfirmasi order.',
            'order_area.gt' => 'Luas pekerjaan harus lebih dari 0 m².',
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
                    abort(422, 'Order ini sudah berstatus Deal Order. Data tidak dapat dikonfirmasi ulang.');
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
                    'note' => 'Order dikonfirmasi. Harga: Rp'.number_format($price, 0, ',', '.').'; luasan: '.number_format($area, 2, ',', '.').' m²; tanggal pelaksanaan: '.$scheduledDate.'; teknisi: '.$technician->full_name.'.',
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
                abort(422, 'Order berstatus Deal Order tidak dapat dikembalikan melalui halaman ini.');
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
        abort_unless($enquiry->status === 'converted', 409, 'Notifikasi konfirmasi hanya tersedia untuk order berstatus Deal Order.');

        /** @var JaspestWhatsappNotification|null $notification */
        $notification = $enquiry->whatsappNotification()->first();
        abort_if(! $notification, 404, 'Riwayat konfirmasi order belum tersedia.');

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
                    'whatsapp' => 'Order '.$lockedEnquiry->reference.' belum dapat disinkronkan: nomor WhatsApp tidak valid.',
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
                    'note' => 'Data klien otomatis disinkronkan dari order terkonfirmasi: '.$client->client_code.' — '.$client->full_name.'.',
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
            'data' => $this->clientResource($client->fresh()->loadCount('enquiries')),
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
        // ID teknisi selalu dibuat otomatis oleh sistem dan tidak dapat diinput manual.
        do {
            $code = 'TKN-'.Str::upper(Str::random(8));
        } while (JaspestTechnician::query()->where('technician_code', $code)->exists());
        $data['technician_code'] = $code;
        $technician = JaspestTechnician::create($data);

        return response()->json($this->technicianResource($technician), 201);
    }

    public function updateTechnician(Request $request, JaspestTechnician $technician): JsonResponse
    {
        $data = $this->validatedTechnician($request, $technician);
        unset($data['technician_code']); // ID teknisi tidak dapat diubah dari form.
        $updatedTechnician = DB::transaction(function () use ($data, $technician): JaspestTechnician {
            $lockedTechnician = JaspestTechnician::query()->whereKey($technician->id)->lockForUpdate()->firstOrFail();
            $lockedTechnician->update($data);
            return $lockedTechnician->fresh();
        }, 3);

        return response()->json($this->technicianResource($updatedTechnician));
    }

    public function deleteTechnician(JaspestTechnician $technician): JsonResponse
    {
        $deleted = DB::transaction(function () use ($technician): bool {
            $lockedTechnician = JaspestTechnician::query()->whereKey($technician->id)->lockForUpdate()->firstOrFail();
            // Keep technicians referenced by past orders or maintenance records so operational history remains intact.
            $hasOperationalHistory = JaspestInventoryMaintenance::query()->where('technician_id', $lockedTechnician->id)->exists()
                || JaspestEnquiry::query()->where('assigned_technician_id', $lockedTechnician->id)->exists();
            if ($hasOperationalHistory) return false;
            $lockedTechnician->delete();
            return true;
        }, 3);

        if (! $deleted) {
            return response()->json([
                'message' => 'Teknisi masih tercatat pada riwayat order atau pemeliharaan. Ubah status operasionalnya menjadi Di luar tugas agar histori tetap terjaga.',
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

    /** Ringkasan inventaris berdasarkan kondisi barang dan satuan masing-masing item. */
    public function inventorySummary(): JsonResponse
    {
        $items = JaspestInventoryItem::query();
        $damaged = (clone $items)->where('damaged_quantity', '>', 0);
        $repair = (clone $items)->where('condition_status', 'needs_repair');
        $normal = (clone $items)->where('condition_status', 'normal');
        $maintenanceLimit = now('Asia/Jakarta')->addDays(30)->toDateString();

        $mapConditionItem = static fn (JaspestInventoryItem $item): array => [
            'id' => $item->id,
            'name' => $item->name,
            'unit' => $item->unit,
            'stock_on_hand' => (float) $item->stock_on_hand,
            'damaged_quantity' => (float) $item->damaged_quantity,
            'condition_status' => $item->condition_status,
        ];

        return response()->json([
            'summary' => [
                'items' => (clone $items)->count(),
                // Count item records with stock; quantities of different units are never added together.
                'stocked_items' => (clone $items)->where('stock_on_hand', '>', 0)->count(),
                'damaged_items' => (clone $damaged)->count(),
                'repair_items' => (clone $repair)->count(),
                'normal_items' => (clone $normal)->count(),
                'maintenance_due' => JaspestInventoryMaintenance::query()->where('status', 'planned')
                    ->whereDate('scheduled_date', '<=', $maintenanceLimit)->count(),
            ],
            'needs_repair_items' => (clone $repair)->orderBy('name')->limit(8)->get()->map($mapConditionItem)->values(),
            'damaged_item_rows' => (clone $damaged)->orderByDesc('damaged_quantity')->orderBy('name')->limit(8)->get()->map($mapConditionItem)->values(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function inventoryItems(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'condition' => ['nullable', Rule::in(['all', 'normal', 'needs_repair', 'damaged'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = JaspestInventoryItem::query()->orderBy('name');
        if (filled($validated['search'] ?? null)) {
            $term = '%'.addcslashes(trim($validated['search']), '%_\\').'%';
            $query->where(fn ($builder) => $builder->where('name', 'like', $term)
                ->orWhere('category', 'like', $term)->orWhere('brand', 'like', $term)
                ->orWhere('specification', 'like', $term));
        }
        if (($validated['condition'] ?? 'all') !== 'all') {
            $condition = $validated['condition'];
            if ($condition === 'damaged') {
                $query->where('condition_status', 'damaged')->where('damaged_quantity', '>', 0);
            } else {
                $query->where('condition_status', $condition);
            }
        }

        $paginator = $query->paginate(15)->withQueryString();
        return response()->json([
            'data' => $paginator->getCollection()->map(fn (JaspestInventoryItem $item): array => $this->inventoryItemResource($item))->values(),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function inventoryOptions(): JsonResponse
    {
        $items = JaspestInventoryItem::query()->orderBy('name')->limit(500)->get(['id', 'name', 'unit', 'stock_on_hand']);
        return response()->json([
            'items' => $items->map(fn (JaspestInventoryItem $item): array => [
                'id' => $item->id, 'name' => $item->name, 'unit' => $item->unit,
                'stock_on_hand' => (float) $item->stock_on_hand,
            ])->values(),
            // Any registered inventory item can have a maintenance record; no separate item type is required.
            'assets' => $items->map(fn (JaspestInventoryItem $item): array => [
                'id' => $item->id, 'name' => $item->name, 'unit' => $item->unit,
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

    public const INVENTORY_UNITS = ['Pcs', 'Unit', 'Set', 'Pack', 'Box', 'Botol', 'Sak', 'Roll', 'Meter', 'Liter', 'Mililiter', 'Kg', 'Gram'];

    public function storeInventoryItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:180'],
            'category' => ['required', 'string', 'min:2', 'max:80'],
            'brand' => ['nullable', 'string', 'max:120'],
            'specification' => ['nullable', 'string', 'max:3000'],
            'unit' => ['required', 'string', Rule::in(self::INVENTORY_UNITS)],
            'stock_on_hand' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'damaged_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'condition_status' => ['required', Rule::in(['normal', 'needs_repair', 'damaged'])],
        ]);
        $data['damaged_quantity'] = (float) ($data['damaged_quantity'] ?? 0);
        $data['stock_on_hand'] = round((float) $data['stock_on_hand'], 3);
        $data['damaged_quantity'] = round($data['damaged_quantity'], 3);
        $this->validateInventoryCondition($data['condition_status'], $data['damaged_quantity'], $data['stock_on_hand']);

        $item = DB::transaction(function () use ($data): JaspestInventoryItem {
            $item = JaspestInventoryItem::create($data);
            if ($item->stock_on_hand > 0) {
                $this->createInventoryMovement($item, 'stock_in', (float) $item->stock_on_hand, 0, 0,
                    (float) $item->stock_on_hand, 'Stok awal saat barang didaftarkan.');
            }
            return $item->fresh();
        }, 3);

        return response()->json($this->inventoryItemResource($item), 201);
    }

    public function updateInventoryItem(Request $request, JaspestInventoryItem $item): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:180'],
            'category' => ['required', 'string', 'min:2', 'max:80'],
            'brand' => ['nullable', 'string', 'max:120'],
            'specification' => ['nullable', 'string', 'max:3000'],
            'unit' => ['required', 'string', 'max:24', Rule::in(array_merge(self::INVENTORY_UNITS, [(string) $item->unit]))],
            'stock_on_hand' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'damaged_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'condition_status' => ['required', Rule::in(['normal', 'needs_repair', 'damaged'])],
        ]);
        $data['stock_on_hand'] = round((float) $data['stock_on_hand'], 3);
        $data['damaged_quantity'] = round((float) ($data['damaged_quantity'] ?? 0), 3);
        $this->validateInventoryCondition($data['condition_status'], $data['damaged_quantity'], $data['stock_on_hand']);

        $updatedItem = DB::transaction(function () use ($data, $item): JaspestInventoryItem {
            $locked = JaspestInventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($locked->unit !== $data['unit'] && $locked->movements()->exists()) {
                throw ValidationException::withMessages(['unit' => 'Satuan tidak dapat diubah karena barang sudah memiliki riwayat transaksi.']);
            }
            $before = round((float) $locked->stock_on_hand, 3);
            $after = $data['stock_on_hand'];
            $locked->update($data);
            $difference = round($after - $before, 3);
            if (abs($difference) >= 0.001) {
                $type = $difference > 0 ? 'adjustment_in' : 'adjustment_out';
                $this->createInventoryMovement($locked, $type, abs($difference), 0, $before, $after,
                    'Penyesuaian stok melalui pembaruan data barang.');
            }
            return $locked->fresh();
        }, 3);

        return response()->json($this->inventoryItemResource($updatedItem));
    }

    private function validateInventoryCondition(string $condition, float $damagedQuantity, float $stock): void
    {
        if ($damagedQuantity > $stock + 0.0005) {
            throw ValidationException::withMessages(['damaged_quantity' => 'Jumlah barang rusak tidak boleh melebihi stok.']);
        }
        if ($condition === 'damaged' && $damagedQuantity <= 0) {
            throw ValidationException::withMessages(['damaged_quantity' => 'Isi jumlah barang rusak untuk kondisi Rusak.']);
        }
        if ($condition === 'normal' && $damagedQuantity > 0) {
            throw ValidationException::withMessages(['condition_status' => 'Pilih Perlu Perbaikan atau Rusak jika masih ada barang rusak.']);
        }
    }

    private function createInventoryMovement(
        JaspestInventoryItem $item,
        string $type,
        float $quantity,
        float $unitCost,
        float $before,
        float $after,
        string $notes,
        ?int $enquiryId = null,
        ?string $supplierName = null,
        ?string $invoiceNumber = null,
    ): JaspestInventoryMovement {
        return JaspestInventoryMovement::create([
            'movement_code' => 'INV-'.now('Asia/Jakarta')->format('ymd').'-'.Str::upper(Str::random(8)),
            'item_id' => $item->id,
            'movement_type' => $type,
            'quantity' => round($quantity, 3),
            'unit_cost' => round($unitCost, 2),
            'warehouse_before' => round($before, 3),
            'warehouse_after' => round($after, 3),
            'enquiry_id' => $enquiryId,
            'supplier_name' => $supplierName,
            'invoice_number' => $invoiceNumber,
            'notes' => $notes,
            'created_by' => auth()->id(),
        ]);
    }

    public function deleteInventoryItem(JaspestInventoryItem $item): JsonResponse
    {
        $deleted = DB::transaction(function () use ($item): bool {
            $locked = JaspestInventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ((float) $locked->stock_on_hand > 0 || $locked->movements()->exists() || $locked->maintenance()->exists()) {
                return false;
            }
            $locked->delete();
            return true;
        }, 3);
        if (! $deleted) {
            return response()->json(['message' => 'Barang memiliki stok atau riwayat. Data tidak dapat dihapus agar histori tetap aman.'], 409);
        }
        return response()->json(['message' => 'Barang berhasil dihapus.']);
    }

    public function inventoryMovements(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['all', 'stock_in', 'stock_out', 'usage', 'adjustment_in', 'adjustment_out', 'write_off'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = JaspestInventoryMovement::query()->with(['item:id,name,unit', 'enquiry:id,reference,full_name'])->latest();
        if (($validated['type'] ?? 'all') !== 'all') $query->where('movement_type', $validated['type']);
        if (filled($validated['search'] ?? null)) {
            $term = '%'.addcslashes(trim($validated['search']), '%_\\').'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('movement_code', 'like', $term)
                    ->orWhere('supplier_name', 'like', $term)
                    ->orWhereHas('item', fn ($item) => $item->where('name', 'like', $term))
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
            'movement_type' => ['required', Rule::in(['stock_in', 'stock_out', 'usage', 'adjustment_in', 'adjustment_out', 'write_off'])],
            'item_id' => ['required', 'integer', 'exists:jaspest_inventory_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999'],
            'enquiry_id' => ['nullable', 'integer', 'required_if:movement_type,usage', 'exists:jaspest_enquiries,id'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'supplier_name' => ['nullable', 'string', 'max:180'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000', 'required_if:movement_type,stock_out,adjustment_out,write_off'],
        ]);

        $movement = DB::transaction(function () use ($data): JaspestInventoryMovement {
            $item = JaspestInventoryItem::query()->whereKey($data['item_id'])->lockForUpdate()->firstOrFail();
            $type = $data['movement_type'];
            $quantity = round((float) $data['quantity'], 3);
            $before = round((float) $item->stock_on_hand, 3);
            $damaged = round((float) $item->damaged_quantity, 3);
            $unitCost = round((float) ($data['unit_cost'] ?? 0), 2);
            $after = $before;

            if (in_array($type, ['stock_in', 'adjustment_in'], true)) {
                $after = round($before + $quantity, 3);
            } elseif ($type === 'write_off') {
                if ($quantity > $damaged + 0.0005) {
                    throw ValidationException::withMessages(['quantity' => 'Jumlah penghapusan melebihi jumlah barang rusak yang tercatat.']);
                }
                $after = round($before - $quantity, 3);
                $item->damaged_quantity = round($damaged - $quantity, 3);
                if ((float) $item->damaged_quantity <= 0.0005) {
                    $item->damaged_quantity = 0;
                    if ($item->condition_status === 'damaged') {
                        $item->condition_status = 'normal';
                    }
                }
            } else {
                $usableStock = round($before - $damaged, 3);
                if ($quantity > $usableStock + 0.0005) {
                    throw ValidationException::withMessages(['quantity' => 'Jumlah melebihi stok yang tidak rusak. Gunakan penghapusan khusus untuk barang rusak.']);
                }
                if ($type === 'usage') {
                    $order = JaspestEnquiry::query()->whereKey($data['enquiry_id'])->lockForUpdate()->firstOrFail();
                    if ($order->status !== 'converted') {
                        throw ValidationException::withMessages(['enquiry_id' => 'Pemakaian hanya dapat dicatat untuk order berstatus Deal Order.']);
                    }
                }
                $after = round($before - $quantity, 3);
            }

            $item->stock_on_hand = $after;
            $item->save();
            $labels = [
                'stock_in' => 'Stok masuk', 'stock_out' => 'Stok keluar', 'usage' => 'Pemakaian pekerjaan',
                'adjustment_in' => 'Penyesuaian masuk', 'adjustment_out' => 'Penyesuaian keluar', 'write_off' => 'Penghapusan barang rusak',
            ];
            $notes = trim((string) ($data['notes'] ?? ''));
            if ($type === 'write_off' && $notes === '') $notes = 'Penghapusan jumlah barang rusak.';
            return $this->createInventoryMovement(
                $item, $type, $quantity, $unitCost, $before, $after,
                $notes !== '' ? $notes : ($labels[$type] ?? 'Transaksi stok'),
                isset($data['enquiry_id']) ? (int) $data['enquiry_id'] : null,
                $data['supplier_name'] ?? null,
                $data['invoice_number'] ?? null,
            );
        }, 3);

        return response()->json($this->inventoryMovementResource($movement->load(['item', 'enquiry'])), 201);
    }

    public function inventoryMaintenance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'planned', 'completed', 'cancelled'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = JaspestInventoryMaintenance::query()->with(['item:id,name,unit', 'technician:id,full_name,technician_code'])->orderByDesc('scheduled_date')->latest('id');
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
            JaspestInventoryItem::query()->whereKey($data['item_id'])->lockForUpdate()->firstOrFail();
            if (! empty($data['technician_id'])) {
                $technician = JaspestTechnician::query()->whereKey($data['technician_id'])->lockForUpdate()->firstOrFail();
                if ($technician->operational_status === 'off_duty') {
                    throw ValidationException::withMessages(['technician_id' => 'Teknisi sedang di luar tugas.']);
                }
            }
            return JaspestInventoryMaintenance::create([
                ...$data, 'cost' => $data['cost'] ?? 0, 'status' => 'planned', 'created_by' => auth()->id(),
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

    private function inventoryItemResource(JaspestInventoryItem $item): array
    {
        $conditionLabels = ['normal' => 'Tidak Ada Kendala', 'needs_repair' => 'Perlu Perbaikan', 'damaged' => 'Rusak'];
        return [
            'id' => $item->id, 'name' => $item->name, 'category' => $item->category,
            'brand' => $item->brand, 'specification' => $item->specification, 'unit' => $item->unit,
            'stock_on_hand' => (float) $item->stock_on_hand,
            'damaged_quantity' => (float) $item->damaged_quantity,
            'condition_status' => $item->condition_status,
            'condition_label' => $conditionLabels[$item->condition_status] ?? 'Tidak Ada Kendala',
            'created_at' => $item->created_at?->toIso8601String(),
        ];
    }

    private function inventoryMovementResource(JaspestInventoryMovement $movement): array
    {
        $labels = [
            'stock_in' => 'Stok masuk', 'stock_out' => 'Stok keluar', 'usage' => 'Pemakaian pekerjaan',
            'adjustment_in' => 'Penyesuaian masuk', 'adjustment_out' => 'Penyesuaian keluar',
            'write_off' => 'Penghapusan barang rusak', 'distribution' => 'Distribusi (riwayat lama)',
            'return' => 'Pengembalian (riwayat lama)',
        ];
        return [
            'id' => $movement->id, 'movement_code' => $movement->movement_code,
            'movement_type' => $movement->movement_type, 'movement_label' => $labels[$movement->movement_type] ?? $movement->movement_type,
            'item_id' => $movement->item_id, 'item_name' => $movement->item?->name,
            'unit' => $movement->item?->unit,
            'quantity' => (float) $movement->quantity, 'unit_cost' => (float) $movement->unit_cost,
            'warehouse_before' => (float) $movement->warehouse_before, 'warehouse_after' => (float) $movement->warehouse_after,
            'order_reference' => $movement->enquiry?->reference, 'client_name' => $movement->enquiry?->full_name,
            'supplier_name' => $movement->supplier_name, 'invoice_number' => $movement->invoice_number,
            'notes' => $movement->notes,
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
            'item_name' => $maintenance->item?->name,
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
        } elseif ($type === 'pages') {
            $this->ensurePageSlots();
            $order = array_keys(self::PAGE_SLOTS);
            $items = JaspestContentItem::where('type', 'pages')->whereIn('slug', $order)->get()
                ->sortBy(fn (JaspestContentItem $item): int => (int) array_search($item->slug, $order, true))
                ->map(fn (JaspestContentItem $item): array => $this->pageResource($item));
        } else {
            $items = JaspestContentItem::where('type', $type)
                ->orderBy('sort_order')->orderByDesc('updated_at')->get()
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
        if ($type === 'certifications' && ! $request->hasFile('image')) {
            throw ValidationException::withMessages(['image' => 'Logo wajib diunggah untuk sertifikasi atau keanggotaan baru.']);
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

        if ($type === 'pages') {
            return $this->updatePage($request, JaspestContentItem::where('type', 'pages')->findOrFail($id));
        }

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

    /**
     * Pastikan setiap bagian halaman utama punya satu baris data. Isi awalnya sama dengan
     * teks yang tampil di website, jadi bagian yang hilang dapat diedit kembali di admin.
     */
    private function ensurePageSlots(): void
    {
        foreach (self::PAGE_SLOTS as $slug => $slot) {
            JaspestContentItem::firstOrCreate(
                ['type' => 'pages', 'slug' => $slug],
                $slot['defaults'] + ['type' => 'pages', 'slug' => $slug]
            );
        }
    }

    private function pageResource(JaspestContentItem $item): array
    {
        $slot = self::PAGE_SLOTS[$item->slug];
        $resource = $this->contentResource($item);

        if (isset($slot['fields']['cta'])) {
            $resource['cta'] = (string) ($item->data['cta'] ?? $slot['defaults']['data']['cta'] ?? '');
        }

        // Hanya bagian video yang punya status; bagian lain selalu tampil di website.
        if (! isset($slot['fields']['status'])) {
            $resource['status'] = 'Aktif';
        }

        $resource['hint'] = '';
        $resource['default_image_url'] = ! empty($slot['default_image']) ? asset('jaspest/'.$slot['default_image']) : null;
        $resource['slot'] = [
            'section' => $slot['section'],
            'label' => $slot['label'],
            'where' => $slot['where'],
            'icon' => $slot['icon'],
            'fields' => $slot['fields'],
        ];

        return $resource;
    }

    /**
     * Simpan satu bagian halaman utama. Hanya kolom milik bagian tersebut yang dibaca dan
     * diubah; kolom lain tidak tersentuh, sehingga data yang tidak dipakai website tidak ikut berubah.
     */
    private function updatePage(Request $request, JaspestContentItem $item): JsonResponse
    {
        $slot = self::PAGE_SLOTS[$item->slug] ?? null;
        if ($slot === null) {
            throw ValidationException::withMessages(['title' => 'Bagian ini tidak ada pada halaman utama website.']);
        }

        $fields = $slot['fields'];
        $rules = [];
        $attributes = [];
        foreach ($fields as $key => $field) {
            $attributes[$key] = $field['label'];
            $presence = ! empty($field['required']) ? 'required' : 'nullable';
            $rules[$key] = match ($key) {
                'title', 'cta' => [$presence, 'string', 'min:2', 'max:'.$field['max']],
                'description', 'body', 'category', 'image_alt' => [$presence, 'string', 'max:'.$field['max']],
                'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
                'status' => ['required', Rule::in(['Aktif', 'Draft', 'active', 'draft'])],
            };
        }
        if (isset($fields['image'])) {
            $rules['remove_image'] = ['nullable', 'boolean'];
        }

        $data = $request->validate($rules, [], $attributes);

        if ($item->slug === 'video-tiktok') {
            $data = $this->normalizeVideoSlot($data);
        }

        $changes = [];
        foreach (['title', 'description', 'body', 'category', 'image_alt'] as $column) {
            if (isset($fields[$column])) {
                $changes[$column] = $data[$column] ?? null;
            }
        }

        if ($item->slug === 'tentang-jaspest') {
            $text = preg_replace('/\R/u', "\n", (string) $data['body']);
            $text = preg_replace("/\n[ \t]*\n(?:[ \t]*\n)*/", "\n\n", trim($text));
            $changes['body'] = $text;
        }

        if (isset($fields['cta'])) {
            $extra = is_array($item->data) ? $item->data : [];
            $extra['cta'] = trim((string) ($data['cta'] ?? ''));
            $changes['data'] = $extra;
        }

        $changes['status'] = isset($fields['status'])
            ? (in_array($data['status'], ['Aktif', 'active'], true) ? 'active' : 'draft')
            : 'active';

        $disk = config('jaspest_admin.content_disk', 'public');
        $oldPath = $item->image_path;
        if (isset($fields['image']) && $request->hasFile('image')) {
            $changes['image_path'] = $request->file('image')->store('jaspest-content', $disk);
        }

        $item->update($changes);

        if (! empty($changes['image_path']) && $oldPath && $oldPath !== $changes['image_path']) {
            Storage::disk($disk)->delete($oldPath);
        }
        if (isset($fields['image']) && $request->boolean('remove_image') && ! $request->hasFile('image') && $oldPath) {
            Storage::disk($disk)->delete($oldPath);
            $item->update(['image_path' => null]);
        }

        return response()->json($this->pageResource($item->fresh()));
    }

    private function validatedContent(Request $request, string $type): array
    {
        $article = $type === 'articles';

        $isMapLink = $type === 'settings' && $request->input('slug') === 'link-google-maps';
        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:'.($article ? 160 : 180)],
            'slug' => ['nullable', 'string', 'max:'.($article ? 180 : 200), 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'category' => ['nullable', 'string', 'max:'.($article ? 60 : 120)],
            'description' => ['nullable', 'string', 'max:'.($isMapLink ? 3000 : ($article ? 300 : 500))],
            'body' => ['nullable', 'string', 'max:100000'],
            'status' => ['required', Rule::in(['Aktif', 'Draft', 'active', 'draft'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'image_alt' => ['nullable', 'string', 'max:180'],
            'remove_image' => ['nullable', 'boolean'],
            'icon' => ['nullable', 'string', 'max:40'],
            'keywords' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($type === 'settings' && $request->input('slug') === 'email-kantor' && filled($data['description'] ?? null)
            && ! filter_var(trim((string) $data['description']), FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'description' => 'Format email tidak valid. Contoh: nama@perusahaan.com',
            ]);
        }

        if ($isMapLink && filled($data['description'] ?? null)
            && ! JaspestMap::isGoogleMapsUrl((string) $data['description'])) {
            throw ValidationException::withMessages([
                'description' => 'Masukkan link HTTPS resmi dari Google Maps, misalnya link yang disalin melalui tombol Bagikan di Google Maps.',
            ]);
        }

        return $data;
    }

    /**
     * Slot "Video TikTok": kolom Deskripsi berisi link video. Link divalidasi dan
     * disimpan dalam bentuk baku agar pemutar di website selalu bisa memakainya.
     */
    private function normalizeVideoSlot(array $data): array
    {
        $link = trim((string) ($data['description'] ?? ''));
        $active = in_array($data['status'], ['Aktif', 'active'], true);

        if ($link === '') {
            if ($active) {
                throw ValidationException::withMessages([
                    'description' => 'Isi link video TikTok, atau ubah status menjadi Draft untuk menyembunyikan video.',
                ]);
            }

            $data['description'] = null;

            return $data;
        }

        $canonical = JaspestTikTok::resolve($link);

        if ($canonical === null) {
            throw ValidationException::withMessages([
                'description' => 'Link TikTok tidak dikenali. Gunakan link video lengkap, contoh: https://www.tiktok.com/@akun/video/1234567890123456789. Jika memakai link pendek gagal, buka videonya di browser lalu salin link dari address bar.',
            ]);
        }

        $data['description'] = $canonical;

        return $data;
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
            'hint' => (string) ($item->data['hint'] ?? ''),
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
            'address' => $client->address,
            'status' => $client->status,
            'notes' => $client->notes,
            'order_count' => (int) ($client->enquiries_count ?? $client->enquiries()->count()),
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
