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
                Route::get('/booking-technicians', [JaspestAdminApiController::class, 'bookingTechnicians'])->name('bookings.technicians');
                Route::get('/bookings/{enquiry}/photos/{photo}', [JaspestAdminApiController::class, 'showPhoto'])
                    ->whereNumber('photo')->name('bookings.photos.show');
                Route::post('/bookings/{enquiry}/whatsapp-opened', [JaspestAdminApiController::class, 'logWhatsappOpened'])
                    ->middleware('throttle:30,1')->name('bookings.whatsapp-opened');
                Route::post('/bookings/{enquiry}/notes', [JaspestAdminApiController::class, 'addBookingNote'])
                    ->middleware('throttle:30,1')->name('bookings.notes');
                Route::patch('/bookings/{enquiry}/status', [JaspestAdminApiController::class, 'updateBookingStatus'])
                    ->middleware('throttle:30,1')->name('bookings.status');

                Route::get('/accounts', [JaspestAdminApiController::class, 'accounts'])->middleware('can:manage-jaspest-accounts')->name('accounts.index');
                Route::post('/accounts', [JaspestAdminApiController::class, 'storeAccount'])
                    ->middleware(['throttle:30,1', 'can:manage-jaspest-accounts'])->name('accounts.store');
                Route::put('/accounts/{account}', [JaspestAdminApiController::class, 'updateAccount'])
                    ->whereNumber('account')->middleware(['throttle:30,1', 'can:manage-jaspest-accounts'])->name('accounts.update');
                Route::delete('/accounts/{account}', [JaspestAdminApiController::class, 'deleteAccount'])
                    ->whereNumber('account')->middleware(['throttle:30,1', 'can:manage-jaspest-accounts'])->name('accounts.delete');

                Route::get('/technicians', [JaspestAdminApiController::class, 'technicians'])->name('technicians.index');
                Route::post('/technicians', [JaspestAdminApiController::class, 'storeTechnician'])
                    ->middleware('throttle:30,1')->name('technicians.store');
                Route::put('/technicians/{technician}', [JaspestAdminApiController::class, 'updateTechnician'])
                    ->whereNumber('technician')->middleware('throttle:30,1')->name('technicians.update');
                Route::delete('/technicians/{technician}', [JaspestAdminApiController::class, 'deleteTechnician'])
                    ->whereNumber('technician')->middleware('throttle:30,1')->name('technicians.delete');

                Route::prefix('inventory')->name('inventory.')->group(function (): void {
                    Route::get('/summary', [JaspestAdminApiController::class, 'inventorySummary'])->name('summary');
                    Route::get('/options', [JaspestAdminApiController::class, 'inventoryOptions'])->name('options');
                    Route::get('/items', [JaspestAdminApiController::class, 'inventoryItems'])->name('items.index');
                    Route::post('/items', [JaspestAdminApiController::class, 'storeInventoryItem'])
                        ->middleware('throttle:30,1')->name('items.store');
                    Route::put('/items/{item}', [JaspestAdminApiController::class, 'updateInventoryItem'])
                        ->whereNumber('item')->middleware('throttle:30,1')->name('items.update');
                    Route::delete('/items/{item}', [JaspestAdminApiController::class, 'deleteInventoryItem'])
                        ->whereNumber('item')->middleware('throttle:30,1')->name('items.delete');
                    Route::get('/movements', [JaspestAdminApiController::class, 'inventoryMovements'])->name('movements.index');
                    Route::post('/movements', [JaspestAdminApiController::class, 'storeInventoryMovement'])
                        ->middleware('throttle:30,1')->name('movements.store');
                    Route::get('/technician-stocks', [JaspestAdminApiController::class, 'inventoryTechnicianStocks'])->name('technician-stocks.index');
                    Route::get('/maintenance', [JaspestAdminApiController::class, 'inventoryMaintenance'])->name('maintenance.index');
                    Route::post('/maintenance', [JaspestAdminApiController::class, 'storeInventoryMaintenance'])
                        ->middleware('throttle:30,1')->name('maintenance.store');
                    Route::patch('/maintenance/{maintenance}/status', [JaspestAdminApiController::class, 'updateInventoryMaintenanceStatus'])
                        ->whereNumber('maintenance')->middleware('throttle:30,1')->name('maintenance.status');
                });

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
