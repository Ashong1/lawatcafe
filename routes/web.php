<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\CaptivePortalController; // <-- Added Portal Controller
use App\Http\Middleware\RoleMiddleware; 

// ==========================================
// PUBLIC CAPTIVE PORTAL ROUTES
// ==========================================
// Initial Redirect - Send guests to the login page by default
Route::get('/', function () {
    return redirect()->route('login');
});

Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [CaptivePortalController::class, 'index'])->name('index');
    Route::post('/authenticate', [CaptivePortalController::class, 'authenticate'])->name('authenticate');
    Route::post('/verify-payment', [CaptivePortalController::class, 'verifyPayment'])->name('verify-payment');
    Route::post('/upload', [CaptivePortalController::class, 'uploadReceipt'])->name('upload');
    Route::get('/success', [CaptivePortalController::class, 'success'])->name('success');
});

// ==========================================
// SHARED ROUTES (Admins & Staff)
// ==========================================
Route::middleware(['auth', 'verified'])->group(function () {
    
    // Shared POS Access
    Route::get('/pos', [PosController::class, 'index'])->name('pos');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');

    // Shared Profile Management
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'edit')->name('profile.edit');
        Route::patch('/profile', 'update')->name('profile.update');
        Route::delete('/profile', 'destroy')->name('profile.destroy');
    });

    // ==========================================
    // ADMIN ONLY ROUTES
    // ==========================================
    Route::middleware([RoleMiddleware::class . ':admin'])->group(function () {
        
        // Admin Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Inventory Management
        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/logs', function() {
                return view('inventory.logs', ['logs' => \App\Models\InventoryLog::with(['ingredient', 'user'])->latest()->get()]);
            })->name('logs');
            Route::resource('categories', \App\Http\Controllers\CategoryController::class)->except(['create', 'show', 'edit']);
            Route::resource('products', ProductController::class)->except(['create', 'show', 'edit']);
            Route::resource('ingredients', IngredientController::class)->except(['create', 'show', 'edit']);
        });

        // Kitchen Display System
        Route::get('/kds', [\App\Http\Controllers\KdsController::class, 'index'])->name('kds.index');
        Route::post('/kds/{sale}/status', [\App\Http\Controllers\KdsController::class, 'updateStatus'])->name('kds.update');

        // Network & Voucher Management
        Route::prefix('network')->name('network.')->group(function () {
            Route::get('/sessions', [VoucherController::class, 'sessions'])->name('sessions');
            Route::post('/sessions/kick', [VoucherController::class, 'kick'])->name('sessions.kick');
            
            Route::get('/vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
            Route::post('/vouchers/generate', [VoucherController::class, 'generateBatch'])->name('vouchers.generate');
            Route::post('/vouchers/bulk-delete', [VoucherController::class, 'bulkDestroy'])->name('vouchers.bulk-delete');
            Route::post('/vouchers/purge', [VoucherController::class, 'purge'])->name('vouchers.purge');
            Route::get('/vouchers/{voucher}/print', [VoucherController::class, 'print'])->name('vouchers.print');
            Route::delete('/vouchers/{voucher}', [VoucherController::class, 'destroy'])->name('vouchers.destroy');
        });

        // Finance / Sales Reports
        Route::get('/sales/export', [SalesController::class, 'export'])->name('sales.export'); 
        Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');

        // System Accounts
        Route::resource('accounts', AccountController::class)->except(['create', 'show', 'edit']);

        // Payment & IMAP Settings
        Route::get('/settings/payment', [\App\Http\Controllers\Admin\SettingController::class, 'index'])->name('admin.settings.payment');
        Route::post('/settings/payment', [\App\Http\Controllers\Admin\SettingController::class, 'update'])->name('admin.settings.payment.update');
    });

    // ==========================================
    // STAFF ONLY ROUTES
    // ==========================================
    Route::middleware([RoleMiddleware::class . ':staff'])->group(function () {
        
        // Staff Hub
        Route::get('/staff-dashboard', [StaffController::class, 'index'])->name('staff.dashboard');

    });
});

require __DIR__.'/auth.php';