<?php

use App\Http\Controllers\Admin\TableQrCodeController;
use App\Http\Controllers\Cashier\ReceiptController;
use App\Http\Controllers\MidtransNotificationController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Volt::route('t/{diningTable:code}', 'customer.menu')
    ->name('customer.menu');

Volt::route('t/{diningTable:code}/s/{tableSession:token}', 'customer.order-status')
    ->name('customer.session');

Route::post('webhooks/midtrans/notification', MidtransNotificationController::class)
    ->name('webhooks.midtrans.notification');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'role:super_admin,kasir'])->prefix('cashier')->name('cashier.')->group(function (): void {
    Volt::route('orders', 'cashier.orders')->name('orders');
    Volt::route('tables', 'cashier.tables')->name('tables');
    Volt::route('manual-order', 'cashier.manual-order')->name('manual-order');
    Volt::route('transactions', 'cashier.transactions')->name('transactions');

    Route::get('sessions/{tableSession}/receipt', [ReceiptController::class, 'show'])
        ->name('receipts.show');
});

Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Volt::route('categories', 'admin.categories')->name('categories');
    Volt::route('menu-items', 'admin.menu-items')->name('menu-items');
    Volt::route('tables', 'admin.tables')->name('tables');
    Volt::route('users', 'admin.users')->name('users');
    Volt::route('settings', 'admin.settings')->name('settings');

    Route::get('tables/{diningTable}/qr/download', [TableQrCodeController::class, 'download'])
        ->name('tables.qr.download');
    Route::get('tables/qr/print', [TableQrCodeController::class, 'print'])
        ->name('tables.qr.print');
});

require __DIR__.'/auth.php';
