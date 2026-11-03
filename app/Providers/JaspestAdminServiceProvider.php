<?php

namespace App\Providers;

use App\Models\BlogPost;
use App\Models\JaspestContentItem;
use App\Support\JaspestMedia;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class JaspestAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/jaspest_admin.php', 'jaspest_admin');
    }

    public function boot(): void
    {
        $isConfiguredAdministrator = static fn (Authenticatable $user): bool => in_array(
            (string) $user->getAuthIdentifier(),
            array_map('strval', config('jaspest_admin.editor_user_ids', [])),
            true
        );

        if (! Gate::has('manage-jaspest-accounts')) {
            // Only the initially configured administrator can create or manage login accounts.
            Gate::define('manage-jaspest-accounts', $isConfiguredAdministrator);
        }

        if (! Gate::has('manage-jaspest')) {
            Gate::define('manage-jaspest', static fn (Authenticatable $user): bool =>
                (bool) ($user->is_active ?? true)
                && ($isConfiguredAdministrator($user) || in_array((string) ($user->role ?? ''), [
                    'direksi', 'finance', 'logistik', 'marketing',
                ], true))
            );
        }

        $this->loadRoutesFrom(__DIR__.'/../../routes/jaspest-admin.php');

        View::composer('jaspest.landing', function (\Illuminate\View\View $view): void {
            $data = $view->getData();
            $config = (array) ($data['jaspestConfig'] ?? []);
            $config['previewMode'] = false;
            $config['enquiryEndpoint'] = url('/jaspest/enquiries');
            $view->with('jaspestConfig', $config);

            $items = JaspestContentItem::query()->where('status', 'active')
                ->orderBy('sort_order')->orderBy('id')->get()->groupBy('type');

            $content = [];
            $content['services'] = ($items['services'] ?? collect())->map(fn (JaspestContentItem $item): array => [
                'id' => $item->slug ?: (string) $item->id,
                'name' => $item->title,
                'icon' => $item->data['icon'] ?? 'bug',
                'label' => $item->category ?? 'Layanan JASPEST',
                'description' => $item->description ?? '',
                'detail' => $item->body ?? $item->description ?? '',
                'image' => JaspestMedia::url($item->image_path),
            ])->values()->all();

            $content['gallery'] = ($items['gallery'] ?? collect())->map(fn (JaspestContentItem $item): array => [
                'id' => $item->slug ?: (string) $item->id,
                'category' => $item->category ?? 'Rumah',
                'title' => $item->title,
                'subtitle' => $item->description ?? '',
                'image' => JaspestMedia::url($item->image_path) ?? asset('jaspest/'.($item->data['default_image'] ?? 'assets/hero-pest-control.webp')),
            ])->values()->all();

            $content['faqs'] = ($items['faqs'] ?? collect())->map(fn (JaspestContentItem $item): array => [
                'id' => $item->slug ?: (string) $item->id,
                'question' => $item->title,
                'answer' => $item->body ?? $item->description ?? '',
                'keywords' => $item->data['keywords'] ?? [],
                'sourceType' => 'form',
                'sourceLabel' => 'Ajukan konsultasi',
            ])->values()->all();

            $content['pests'] = ($items['pests'] ?? collect())->map(fn (JaspestContentItem $item): array => [
                'id' => $item->slug ?: (string) $item->id,
                'name' => $item->title,
                'description' => $item->description ?? '',
                'detail' => $item->body ?? '',
                'image' => JaspestMedia::url($item->image_path),
            ])->values()->all();

            $content['methods'] = ($items['methods'] ?? collect())->map(fn (JaspestContentItem $item): array => [
                'id' => $item->slug ?: (string) $item->id,
                'name' => $item->title,
                'category' => $item->category ?? '',
                'description' => $item->description ?? '',
                'detail' => $item->body ?? '',
                'image' => JaspestMedia::url($item->image_path),
            ])->values()->all();

            $pageItems = ($items['pages'] ?? collect())->keyBy(fn (JaspestContentItem $item) => $item->slug ?: (string) $item->id);
            $settings = ($items['settings'] ?? collect())->keyBy(fn (JaspestContentItem $item) => $item->slug ?: (string) $item->id);
            $content['brand'] = [
                'name' => $settings->get('nama-website')?->description ?: 'JASPEST',
                'company' => $settings->get('nama-perusahaan')?->description ?: 'Jaya Abadi Solusi',
                'tagline' => $settings->get('tagline-website')?->description ?: 'Solusi pengendalian hama untuk rumah dan bisnis.',
            ];
            $config['whatsappNumber'] = $settings->get('nomor-whatsapp')?->description ?: ($config['whatsappNumber'] ?? null);
            $view->with('jaspestConfig', $config);
            $view->with('jaspestPageContent', $pageItems);
            $view->with('jaspestDynamicContent', $content);
            $view->with('jaspestAdminAvailable', true);
        });
    }
}
