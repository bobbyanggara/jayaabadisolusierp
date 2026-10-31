<?php

use App\Http\Controllers\JaspestAdminApiController;
use App\Http\Controllers\JaspestAdminAuthController;
use App\Http\Controllers\JaspestAdminController;
use App\Http\Controllers\JaspestEnquiryController;
use App\Http\Middleware\EnsureJaspestAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    $loginPath = '/'.ltrim((string) config('jaspest_admin.login_path', '/admin/login'), '/');
    Route::get($loginPath, [JaspestAdminAuthController::class, 'showLogin'])->name('jaspest.login');
    Route::post($loginPath, [JaspestAdminAuthController::class, 'login'])
        ->middleware('throttle:6,1')->name('jaspest.login.submit');
    Route::post('/admin/logout', [JaspestAdminAuthController::class, 'logout'])->name('jaspest.logout');

    Route::post('/jaspest/enquiries', [JaspestEnquiryController::class, 'store'])
        ->middleware('throttle:10,1')->name('jaspest.enquiries.store');

    Route::prefix(config('jaspest_admin.prefix', 'admin/jaspest'))
        ->name('jaspest.admin.')
        ->middleware(EnsureJaspestAdmin::class)
        ->group(function (): void {
            Route::get('/', [JaspestAdminController::class, 'index'])->name('index');

            Route::prefix('api')->name('api.')->group(function (): void {
                Route::get('/dashboard', [JaspestAdminApiController::class, 'dashboard'])->name('dashboard');
                Route::get('/bookings', [JaspestAdminApiController::class, 'bookings'])->name('bookings.index');
                Route::get('/bookings/export', [JaspestAdminApiController::class, 'exportBookings'])->name('bookings.export');
                Route::get('/bookings/{enquiry}', [JaspestAdminApiController::class, 'showBooking'])->name('bookings.show');
                Route::get('/bookings/{enquiry}/photos/{photo}', [JaspestAdminApiController::class, 'showPhoto'])
                    ->whereNumber('photo')->name('bookings.photos.show');
                Route::post('/bookings/{enquiry}/whatsapp-opened', [JaspestAdminApiController::class, 'logWhatsappOpened'])
                    ->middleware('throttle:30,1')->name('bookings.whatsapp-opened');
                Route::post('/bookings/{enquiry}/notes', [JaspestAdminApiController::class, 'addBookingNote'])
                    ->middleware('throttle:30,1')->name('bookings.notes');
                Route::patch('/bookings/{enquiry}/status', [JaspestAdminApiController::class, 'updateBookingStatus'])
                    ->middleware('throttle:30,1')->name('bookings.status');

                Route::get('/content/{type}', [JaspestAdminApiController::class, 'contentIndex'])
                    ->whereIn('type', ['pages', 'services', 'pests', 'methods', 'articles', 'gallery', 'faqs', 'settings'])
                    ->name('content.index');
                Route::post('/content/{type}', [JaspestAdminApiController::class, 'storeContent'])
                    ->whereIn('type', ['pages', 'services', 'pests', 'methods', 'articles', 'gallery', 'faqs', 'settings'])
                    ->middleware('throttle:30,1')->name('content.store');
                Route::put('/content/{type}/{id}', [JaspestAdminApiController::class, 'updateContent'])
                    ->whereIn('type', ['pages', 'services', 'pests', 'methods', 'articles', 'gallery', 'faqs', 'settings'])
                    ->whereNumber('id')->middleware('throttle:30,1')->name('content.update');
                Route::delete('/content/{type}/{id}', [JaspestAdminApiController::class, 'deleteContent'])
                    ->whereIn('type', ['pages', 'services', 'pests', 'methods', 'articles', 'gallery', 'faqs', 'settings'])
                    ->whereNumber('id')->middleware('throttle:30,1')->name('content.delete');
            });
        });
});
