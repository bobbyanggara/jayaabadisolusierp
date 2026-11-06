<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jaspest_content_items')) {
            return;
        }

        $records = DB::table('jaspest_content_items')
            ->get(['id', 'type', 'slug', 'title', 'description', 'body', 'image_alt']);

        foreach ($records as $record) {
            $updates = [];

            foreach (['title', 'description', 'body', 'image_alt'] as $column) {
                $value = $record->{$column};
                if (! is_string($value) || $value === '') {
                    continue;
                }

                // Replace the former legal prefix and add CV to legacy unprefixed company mentions.
                $normalized = preg_replace('/\bPT\.?\s*Jaya Abadi Solusi/iu', 'CV Jaya Abadi Solusi', $value);
                $normalized = preg_replace('/(?<!CV )(?<!PT )Jaya Abadi Solusi/iu', 'CV Jaya Abadi Solusi', $normalized ?? $value);

                if ($normalized !== $value) {
                    $updates[$column] = $normalized;
                }
            }

            if ($record->type === 'settings' && $record->slug === 'nama-perusahaan') {
                $companyName = trim((string) ($record->description ?? ''));
                $companyName = preg_replace('/^(?:PT|CV)\.?\s*/iu', '', $companyName) ?: 'Jaya Abadi Solusi';
                $updates['description'] = 'CV '.(trim($companyName) ?: 'Jaya Abadi Solusi');
            }

            if ($updates !== []) {
                $updates['updated_at'] = now();
                DB::table('jaspest_content_items')->where('id', $record->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('jaspest_content_items')) {
            return;
        }

        $records = DB::table('jaspest_content_items')
            ->get(['id', 'type', 'slug', 'title', 'description', 'body', 'image_alt']);

        foreach ($records as $record) {
            $updates = [];

            foreach (['title', 'description', 'body', 'image_alt'] as $column) {
                $value = $record->{$column};
                if (! is_string($value) || $value === '') {
                    continue;
                }

                $normalized = preg_replace('/\bCV\s*Jaya Abadi Solusi/iu', 'Jaya Abadi Solusi', $value);
                if ($normalized !== $value) {
                    $updates[$column] = $normalized;
                }
            }

            if ($record->type === 'settings' && $record->slug === 'nama-perusahaan') {
                $companyName = trim((string) ($record->description ?? ''));
                $updates['description'] = preg_replace('/^CV\.?\s*/iu', '', $companyName) ?: 'Jaya Abadi Solusi';
            }

            if ($updates !== []) {
                $updates['updated_at'] = now();
                DB::table('jaspest_content_items')->where('id', $record->id)->update($updates);
            }
        }
    }
};
