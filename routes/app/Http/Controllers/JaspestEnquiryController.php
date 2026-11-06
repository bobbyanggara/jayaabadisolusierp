<?php

namespace App\Http\Controllers;

use App\Events\JaspestEnquiryCreated;
use App\Models\JaspestEnquiry;
use App\Models\JaspestEnquiryEvent;
use App\Models\JaspestContentItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JaspestEnquiryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Honeypot: balas seolah berhasil, tetapi jangan simpan kiriman bot.
        if (filled($request->input('website'))) {
            return response()->json(['reference' => 'JAS-'.now()->format('ymd').'-'.Str::upper(Str::random(6))], 201);
        }

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:150'],
            'whatsapp' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'min:5', 'max:1000'],
            'estimated_area' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'need' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== 'konsultasi' && ! JaspestContentItem::where('type', 'services')->where('status', 'active')->where('slug', $value)->exists()) {
                    $fail('Pilihan layanan tidak tersedia. Muat ulang halaman dan pilih layanan yang aktif.');
                }
            }],
            'property_type' => ['required', 'in:rumah,bisnis'],
            'company_name' => ['required_if:property_type,bisnis', 'nullable', 'string', 'max:200'],
            'description' => ['required', 'string', 'min:3', 'max:3000'],
            'consent' => ['accepted'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'consent.accepted' => 'Persetujuan untuk dihubungi wajib dicentang.',
            'photos.max' => 'Maksimal 5 foto dapat diunggah.',
            'photos.*.max' => 'Ukuran setiap foto maksimal 5 MB.',
            'photos.*.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
        ]);

        // Verify the actual image bytes before creating the booking. Do not trust only the
        // original filename or the browser-provided MIME type.
        $verifiedPhotoMimes = [];
        foreach ($request->file('photos', []) as $index => $photo) {
            $imageInfo = @getimagesize($photo->getRealPath());
            $actualMime = is_array($imageInfo) ? strtolower((string) ($imageInfo['mime'] ?? '')) : '';
            if (! in_array($actualMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                throw ValidationException::withMessages([
                    "photos.$index" => 'Foto tidak valid atau format isi file bukan JPG, PNG, atau WebP. Buka foto asli lalu simpan ulang sebagai JPG/PNG/WebP.',
                ]);
            }
            $verifiedPhotoMimes[$index] = $actualMime;
        }

        $phone = preg_replace('/\D+/', '', (string) $validated['whatsapp']);
        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
            $phone = '62'.$phone;
        }
        if (! preg_match('/^628[1-9][0-9]{7,11}$/', $phone)) {
            throw ValidationException::withMessages([
                'whatsapp' => 'Gunakan nomor WhatsApp Indonesia yang benar, misalnya 081234567890.',
            ]);
        }

        $reference = 'JAS-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        $enquiry = JaspestEnquiry::create([
            'reference' => $reference,
            'full_name' => trim($validated['full_name']),
            'whatsapp' => $phone,
            'address' => trim($validated['address']),
            'estimated_area' => $validated['estimated_area'],
            'need' => $validated['need'],
            'property_type' => $validated['property_type'],
            'company_name' => $validated['property_type'] === 'bisnis' ? trim((string) ($validated['company_name'] ?? '')) : null,
            'description' => trim($validated['description']),
            'consent' => true,
            'status' => 'new',
        ]);

        $storedPaths = [];
        try {
            foreach ($request->file('photos', []) as $index => $photo) {
                $diskName = config('jaspest_admin.photo_disk', 'local');
                $sourceMime = $verifiedPhotoMimes[$index] ?? 'application/octet-stream';
                $optimized = $this->optimizeUploadedPhoto($photo, $sourceMime);
                $path = 'jaspest/enquiries/'.$reference.'/'.Str::uuid().'.'.$optimized['extension'];
                $disk = Storage::disk($diskName);
                $saved = $disk->put($path, $optimized['bytes']);
                if (! $saved) {
                    throw new \RuntimeException('Foto gagal disimpan ke penyimpanan privat.');
                }
                $storedPaths[] = $path;
                if (! $disk->exists($path) || (int) $disk->size($path) < 1) {
                    throw new \RuntimeException('Foto tersimpan tetapi file tidak dapat diverifikasi.');
                }
                $enquiry->photos()->create([
                    'path' => $path,
                    'original_name' => mb_substr($photo->getClientOriginalName(), 0, 255),
                    'mime_type' => $optimized['mime'],
                    'size_bytes' => (int) $disk->size($path),
                ]);
            }

            JaspestEnquiryEvent::create([
                'enquiry_id' => $enquiry->id,
                'event' => 'submitted',
                'to_status' => 'new',
                'note' => 'Pengajuan masuk dari website publik.',
            ]);
        } catch (\Throwable $exception) {
            Storage::disk(config('jaspest_admin.photo_disk', 'local'))->delete($storedPaths);
            $enquiry->delete();
            throw $exception;
        }

        // A WebSocket outage must never cancel a valid customer submission.
        try {
            event(new JaspestEnquiryCreated($enquiry->fresh()));
        } catch (\Throwable $broadcastException) {
            report($broadcastException);
        }

        // Follow-up is initiated manually by an admin through a wa.me link.
        // The customer submission never sends an automated WhatsApp message.

        return response()->json(['reference' => $enquiry->reference], 201);
    }
    /**
     * Resize and compress an uploaded image before it is stored privately.
     * Prefer WebP when the server's GD build supports it; otherwise retain PNG/JPEG
     * with lossless PNG compression or JPEG quality 82. If GD is unavailable,
     * keep the validated original so customer submissions are not rejected.
     *
     * @return array{bytes:string,mime:string,extension:string,compressed:bool}
     */
    private function optimizeUploadedPhoto(UploadedFile $photo, string $sourceMime): array
    {
        $originalBytes = file_get_contents($photo->getRealPath());
        if (! is_string($originalBytes) || $originalBytes === '') {
            throw new \RuntimeException('Isi foto yang diunggah kosong atau tidak dapat dibaca.');
        }

        $extensionForMime = static fn (string $mime): string => match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecreatetruecolor')) {
            logger()->warning('GD is unavailable; JASPEST stores the validated original photo without compression.');
            return [
                'bytes' => $originalBytes,
                'mime' => $sourceMime,
                'extension' => $extensionForMime($sourceMime),
                'compressed' => false,
            ];
        }

        $source = @imagecreatefromstring($originalBytes);
        if ($source === false) {
            // Some GD builds do not include a decoder for every image format (commonly WebP).
            // The upload has already passed getimagesize validation, so keep the valid source
            // rather than rejecting the customer's booking.
            logger()->warning('GD cannot decode this supported JASPEST photo format; original retained.', [
                'mime' => $sourceMime,
                'original_name' => $photo->getClientOriginalName(),
            ]);
            return [
                'bytes' => $originalBytes,
                'mime' => $sourceMime,
                'extension' => $extensionForMime($sourceMime),
                'compressed' => false,
            ];
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width < 1 || $height < 1 || ($width * $height) > 40000000) {
            imagedestroy($source);
            throw ValidationException::withMessages([
                'photos' => 'Resolusi foto terlalu besar atau tidak valid. Kecilkan foto sebelum mengunggah.',
            ]);
        }

        $maxDimension = 1920;
        $scale = min(1, $maxDimension / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        // WebP generally gives smaller files. Keep transparency when converting PNG/WebP.
        $targetMime = function_exists('imagewebp')
            ? 'image/webp'
            : ($sourceMime === 'image/webp' ? 'image/jpeg' : $sourceMime);

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($canvas === false) {
            imagedestroy($source);
            throw new \RuntimeException('Memori tidak cukup untuk mengolah foto.');
        }

        if ($targetMime === 'image/png' || $targetMime === 'image/webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);
        } else {
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
        }

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        ob_start();
        $encoded = match ($targetMime) {
            'image/webp' => @imagewebp($canvas, null, 82),
            'image/jpeg' => @imagejpeg($canvas, null, 82),
            'image/png' => @imagepng($canvas, null, 8),
            default => false,
        };
        $optimizedBytes = ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($source);

        if (! $encoded || ! is_string($optimizedBytes) || $optimizedBytes === '') {
            // Keep the valid source rather than losing the customer's submitted photo.
            logger()->warning('JASPEST image encoding failed; validated original photo was retained.', [
                'mime' => $sourceMime,
                'width' => $width,
                'height' => $height,
            ]);
            return [
                'bytes' => $originalBytes,
                'mime' => $sourceMime,
                'extension' => $extensionForMime($sourceMime),
                'compressed' => false,
            ];
        }

        return [
            'bytes' => $optimizedBytes,
            'mime' => $targetMime,
            'extension' => $extensionForMime($targetMime),
            'compressed' => true,
        ];
    }

}
