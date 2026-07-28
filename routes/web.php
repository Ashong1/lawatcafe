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
use App\Http\Controllers\NotificationController;
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
    Route::post('/authenticate', [CaptivePortalController::class, 'authenticate'])->name('authenticate')->middleware('throttle:voucher-auth');
    Route::post('/verify-payment', [CaptivePortalController::class, 'verifyPayment'])->name('verify-payment')->middleware('throttle:portal-payment');
    Route::post('/upload', [CaptivePortalController::class, 'uploadReceipt'])->name('upload')->middleware('throttle:portal-upload');
    Route::post('/chat', [CaptivePortalController::class, 'chat'])->name('chat')->middleware('throttle:portal-chat');
    Route::get('/menu', [CaptivePortalController::class, 'menu'])->name('menu'); // Added menu route
    Route::post('/disconnect', [CaptivePortalController::class, 'disconnect'])->name('disconnect');
    Route::get('/unlock', [CaptivePortalController::class, 'unlock'])->name('unlock');
    Route::get('/success', [CaptivePortalController::class, 'success'])->name('success');
    });

// ==========================================
// SHARED ROUTES (Admins & Staff)
// ==========================================
Route::middleware(['auth'])->group(function () {
    
    // Shared POS Access
    Route::get('/pos', [PosController::class, 'index'])->name('pos');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::get('/pos/receipt/{sale}', [PosController::class, 'receipt'])->name('pos.receipt');
    Route::post('/pos/suggest-pairing', [PosController::class, 'suggestPairing'])->name('pos.suggest-pairing');

    // Kitchen Display System
    Route::get('/kds', [\App\Http\Controllers\KdsController::class, 'index'])->name('kds.index');
    Route::get('/kds/data', [\App\Http\Controllers\KdsController::class, 'data'])->name('kds.data');
    Route::post('/kds/{sale}/status', [\App\Http\Controllers\KdsController::class, 'updateStatus'])->name('kds.update');
    Route::post('/kds/item/{item}/status', [\App\Http\Controllers\KdsController::class, 'updateItemStatus'])->name('kds.item.update');

    // Order History
    Route::get('/pos/history', [\App\Http\Controllers\OrderHistoryController::class, 'index'])->name('pos.history');
    Route::post('/pos/history/void/{sale}', [\App\Http\Controllers\OrderHistoryController::class, 'void'])->name('pos.history.void');

    // Shift Management
    Route::post('/shift/start', [\App\Http\Controllers\ShiftController::class, 'start'])->name('shift.start');
    Route::get('/shift/closing-report/{shift}', [\App\Http\Controllers\ShiftController::class, 'showClosingReport'])->name('shift.closing-report');
    Route::post('/shift/transaction/{shift}', [\App\Http\Controllers\ShiftController::class, 'recordTransaction'])->name('shift.transaction');
    Route::post('/shift/end/{shift}', [\App\Http\Controllers\ShiftController::class, 'end'])->name('shift.end');

    // Shared Profile Management
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'edit')->name('profile.edit');
        Route::patch('/profile', 'update')->name('profile.update');
        Route::delete('/profile', 'destroy')->name('profile.destroy');
    });

    // Notification System
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'getUnreadCount'])->name('notifications.unread-count');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    // Shared Network Info (Vouchers list visible to staff)
    Route::prefix('network')->name('network.')->group(function () {
        Route::get('/vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
    });

    // Proactive AI analysis history (shared: audience-scoped in the controller,
    // same convention as network.vouchers.index above).
    Route::get('/ai/analysis-history', [\App\Http\Controllers\AiAnalysisController::class, 'index'])->name('ai.analysis.index');

    // AI Agent proposed-action confirm/reject (shared: staff can confirm their own
    // confirm-tier proposals; ToolCallOrchestrator itself blocks staff from
    // confirming admin_only actions regardless of route access). NOTE: the
    // "admin." route-name prefix here is just namespacing, not an access gate —
    // do not "fix" these into an admin-only middleware group, that would break
    // staff's only mechanism for approving their own proposed actions and their
    // pending-action badge below.
    Route::prefix('admin/ai/actions')->name('admin.ai.actions.')->middleware('throttle:ai-actions')->group(function () {
        Route::post('/{audit}/confirm', [\App\Http\Controllers\AiActionController::class, 'confirm'])->name('confirm');
        Route::post('/{audit}/reject', [\App\Http\Controllers\AiActionController::class, 'reject'])->name('reject');
        Route::get('/pending-count', [\App\Http\Controllers\AiActionController::class, 'pendingCount'])->name('pending-count');
        Route::get('/pending-preview', [\App\Http\Controllers\AiActionController::class, 'pendingPreview'])->name('pending-preview');
    });

    // ==========================================
    // ADMIN ONLY ROUTES
    // ==========================================
    Route::middleware([RoleMiddleware::class . ':admin'])->group(function () {

        // Admin Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/admin/live-stats', [DashboardController::class, 'liveStats'])->name('admin.live-stats'); // Added live-stats route
        Route::post('/admin/ai/chat', [DashboardController::class, 'adminChat'])->name('admin.ai.chat')->middleware('throttle:admin-ai-chat');
        Route::get('/admin/ai/insights', [DashboardController::class, 'getAIInsights'])->name('admin.ai.insights');
        Route::get('/admin/analytics', [\App\Http\Controllers\Admin\AnalyticsController::class, 'index'])->name('admin.analytics');
        Route::get('/admin/ai/actions', [\App\Http\Controllers\AiActionController::class, 'index'])->name('admin.ai.actions.index');

        // Inventory Management
        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/logs', [IngredientController::class, 'logs'])->name('logs');
            Route::resource('categories', \App\Http\Controllers\CategoryController::class)->except(['create', 'show', 'edit']);
            Route::resource('products', ProductController::class)->except(['create', 'show', 'edit']);
            Route::patch('products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('products.toggle-status');
            Route::resource('ingredients', IngredientController::class)->except(['create', 'show', 'edit']);
            Route::post('ingredients/{ingredient}/add-stock', [IngredientController::class, 'addStock'])->name('ingredients.add-stock');
            
            // Supplier Deliveries (Receiving)
            Route::resource('deliveries', \App\Http\Controllers\IngredientDeliveryController::class)->only(['index', 'store', 'destroy']);
            
            // Suppliers Database
            Route::resource('suppliers', \App\Http\Controllers\SupplierController::class)->except(['create', 'show', 'edit']);

            // Purchase Order Drafts (AI-drafted, human-managed)
            Route::resource('purchase-orders', \App\Http\Controllers\PurchaseOrderController::class)->only(['index', 'destroy']);
            Route::post('purchase-orders/{draft}/send', [\App\Http\Controllers\PurchaseOrderController::class, 'send'])->name('purchase-orders.send');

            // Wastage & Spoilage
            Route::resource('wastage', \App\Http\Controllers\WastageController::class)->only(['index', 'store', 'destroy']);
        });

        // Network & Voucher Actions (Admin only)
        Route::prefix('network')->name('network.')->group(function () {
            Route::get('/sessions', [VoucherController::class, 'sessions'])->name('sessions');
            Route::post('/sessions/kick', [VoucherController::class, 'kick'])->name('sessions.kick');
            Route::post('/sessions/set-tier', [VoucherController::class, 'setTier'])->name('sessions.set-tier');
            Route::get('/verifications', [\App\Http\Controllers\PaymentController::class, 'logs'])->name('verifications');
            
            Route::post('/vouchers/generate', [VoucherController::class, 'generateBatch'])->name('vouchers.generate');
            Route::post('/vouchers/bulk-delete', [VoucherController::class, 'bulkDestroy'])->name('vouchers.bulk-delete');
            Route::post('/vouchers/purge', [VoucherController::class, 'purge'])->name('vouchers.purge');
            Route::get('/vouchers/batch-print', [VoucherController::class, 'printBatch'])->name('vouchers.batch-print');
            Route::get('/vouchers/{voucher}/print', [VoucherController::class, 'print'])->name('vouchers.print');
            Route::delete('/vouchers/{voucher}', [VoucherController::class, 'destroy'])->name('vouchers.destroy');

            Route::get('/plans', [VoucherController::class, 'plans'])->name('plans');
            Route::get('/traffic', [\App\Http\Controllers\TrafficController::class, 'index'])->name('traffic');
            Route::get('/traffic/stats', [\App\Http\Controllers\TrafficController::class, 'stats'])->name('traffic.stats'); // Added stats route
            Route::post('/traffic', [\App\Http\Controllers\TrafficController::class, 'update'])->name('traffic.update');

            Route::get('/blocklist', [\App\Http\Controllers\BlocklistController::class, 'index'])->name('blocklist');
            Route::post('/blocklist', [\App\Http\Controllers\BlocklistController::class, 'store'])->name('blocklist.store');
            Route::delete('/blocklist/{device}', [\App\Http\Controllers\BlocklistController::class, 'destroy'])->name('blocklist.destroy');
        });

        // Finance / Sales Reports
        Route::get('/sales/export', [SalesController::class, 'export'])->name('sales.export'); 
        Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');

        // Z-Reads / End of Day Audits
        Route::get('/finance/z-reads', [\App\Http\Controllers\EndOfDayController::class, 'index'])->name('admin.finance.z-reads');
        Route::get('/finance/z-reads/{shift}', [\App\Http\Controllers\EndOfDayController::class, 'show'])->name('admin.finance.shift-detail');

        // System Accounts
        Route::resource('accounts', AccountController::class)->except(['create', 'show', 'edit']);

        // System Settings (Consolidated) — Store Preferences is business-facing and
        // stays reachable by admin-or-above; Integrations/Network/Agent are technical
        // settings reserved for super_admin only (see nested group below).
        Route::prefix('settings')->name('admin.settings.')->group(function () {
            Route::get('/store', [\App\Http\Controllers\Admin\SettingController::class, 'store'])->name('store');
            Route::post('/store', [\App\Http\Controllers\Admin\SettingController::class, 'updateStore'])->name('store.update');

            Route::middleware([RoleMiddleware::class . ':super_admin'])->group(function () {
                Route::get('/ai-providers', [\App\Http\Controllers\Admin\SettingController::class, 'aiProviders'])->name('ai-providers');
                Route::post('/ai-providers', [\App\Http\Controllers\Admin\SettingController::class, 'updateAiProviders'])->name('ai-providers.update');
                Route::post('/ai-providers/{provider}/test', [\App\Http\Controllers\Admin\SettingController::class, 'testAiProvider'])
                    ->whereIn('provider', ['gemini', 'groq', 'openrouter'])
                    ->name('ai-providers.test');
                Route::post('/ai-providers/{provider}/models/replace', [\App\Http\Controllers\Admin\SettingController::class, 'replaceProviderModel'])
                    ->whereIn('provider', ['gemini', 'groq', 'openrouter'])
                    ->name('ai-providers.models.replace');
                Route::post('/ai-providers/{provider}/models/reset', [\App\Http\Controllers\Admin\SettingController::class, 'resetProviderModels'])
                    ->whereIn('provider', ['gemini', 'groq', 'openrouter'])
                    ->name('ai-providers.models.reset');
                Route::get('/network', [\App\Http\Controllers\Admin\SettingController::class, 'network'])->name('network');
                Route::post('/network', [\App\Http\Controllers\Admin\SettingController::class, 'updateNetwork'])->name('network.update');
                Route::get('/agent', [\App\Http\Controllers\Admin\SettingController::class, 'agent'])->name('agent');
                Route::post('/agent', [\App\Http\Controllers\Admin\SettingController::class, 'updateAgentPermissions'])->name('agent.update');
            });
        });
    });

    // ==========================================
    // STAFF ONLY ROUTES
    // ==========================================
    Route::middleware([RoleMiddleware::class . ':staff'])->group(function () {
        
        // Staff Hub
        Route::get('/staff-dashboard', [StaffController::class, 'index'])->name('staff.dashboard');
        Route::get('/staff-dashboard/live', [StaffController::class, 'getLiveData'])->name('staff.dashboard.live');
        Route::post('/staff/ai/chat', [StaffController::class, 'staffChat'])->name('staff.ai.chat')->middleware('throttle:staff-ai-chat');

    });
});

require __DIR__.'/auth.php';
