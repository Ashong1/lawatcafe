<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\AiActionController;
use App\Http\Controllers\AiAnalysisController;
use App\Http\Controllers\AiConversationController;
use App\Http\Controllers\AllowedAddressController;
use App\Http\Controllers\BlocklistController;
use App\Http\Controllers\CaptivePortalController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EndOfDayController; // <-- Added Portal Controller
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\IngredientDeliveryController;
use App\Http\Controllers\KdsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderHistoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffDeliveryController;
use App\Http\Controllers\StaticIpController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TrafficController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\WastageController;
use App\Http\Middleware\DenySuperAdmin;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

// ==========================================
// PUBLIC CAPTIVE PORTAL ROUTES
// ==========================================
// Initial Redirect - Send guests to the login page by default.
// Uses Route::redirect() (not a closure) so this route stays compatible with
// `php artisan route:cache` — closure-based route actions can't be cached.
Route::redirect('/', '/login');

Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [CaptivePortalController::class, 'index'])->name('index');
    Route::post('/authenticate', [CaptivePortalController::class, 'authenticate'])->name('authenticate')->middleware('throttle:voucher-auth');
    Route::post('/verify-payment', [CaptivePortalController::class, 'verifyPayment'])->name('verify-payment')->middleware('throttle:portal-payment');
    Route::post('/upload', [CaptivePortalController::class, 'uploadReceipt'])->name('upload')->middleware('throttle:portal-upload');
    Route::post('/chat', [CaptivePortalController::class, 'chat'])->name('chat')->middleware('throttle:portal-chat');
    Route::get('/menu', [CaptivePortalController::class, 'menu'])->name('menu'); // Added menu route
    Route::post('/disconnect', [CaptivePortalController::class, 'disconnect'])->name('disconnect')->middleware('throttle:portal-disconnect');
    Route::get('/unlock', [CaptivePortalController::class, 'unlock'])->name('unlock');
    Route::get('/success', [CaptivePortalController::class, 'success'])->name('success');
    // Opens the firewall for an already-redeemed voucher. Split from
    // authenticate() so the guest decides when their sign-in window closes —
    // see CaptivePortalController::activate(). Shares voucher-auth's limiter:
    // it is the same redemption flow and needs the same brute-force ceiling.
    Route::post('/activate', [CaptivePortalController::class, 'activate'])->name('activate')->middleware('throttle:voucher-auth');
});

// RFC 8908 Captive Portal API, advertised to clients via DHCP option 114
// (RFC 8910). Deliberately top-level rather than under /portal so the URL
// handed out in the DHCP option stays short and stable. Read-only and
// unauthenticated by definition — see CaptivePortalController::captivePortalApi().
Route::get('/captive-portal-api', [CaptivePortalController::class, 'captivePortalApi'])
    ->name('captive-portal-api')
    ->middleware('throttle:captive-portal-api');

// ==========================================
// SHARED ROUTES (Admins & Staff)
// ==========================================
Route::middleware(['auth'])->group(function () {

    // Register + shift: admin and staff only. super_admin is the developer/
    // system account (see User::isSuperAdmin()) and has no cashiering duty —
    // and any sale it rang would land in the real shift and cash
    // reconciliation figures. Order history stays readable by everyone below,
    // since reviewing sales IS a management duty.
    Route::middleware([DenySuperAdmin::class])->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos');
        Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
        Route::get('/pos/receipt/{sale}', [PosController::class, 'receipt'])->name('pos.receipt');
        Route::post('/pos/suggest-pairing', [PosController::class, 'suggestPairing'])->name('pos.suggest-pairing');

        // Running a shift — opening the drawer, recording against it, closing
        // it out. All cashiering actions.
        Route::post('/shift/start', [ShiftController::class, 'start'])->name('shift.start');
        Route::post('/shift/transaction/{shift}', [ShiftController::class, 'recordTransaction'])->name('shift.transaction');
        Route::post('/shift/end/{shift}', [ShiftController::class, 'end'])->name('shift.end');

        // Kitchen Display System — floor work like the register, not a report:
        // it exists to be tapped by whoever is actually making the drinks.
        Route::get('/kds', [KdsController::class, 'index'])->name('kds.index');
        Route::get('/kds/data', [KdsController::class, 'data'])->name('kds.data');
        Route::post('/kds/{sale}/status', [KdsController::class, 'updateStatus'])->name('kds.update');
        Route::post('/kds/item/{item}/status', [KdsController::class, 'updateItemStatus'])->name('kds.item.update');
    });

    // Deliberately outside the block above: the closing report is the Z-read,
    // and reading one is a management duty, not a cashiering one. It is linked
    // straight from admin/finance/z-reads, which super_admin can reach.
    Route::get('/shift/closing-report/{shift}', [ShiftController::class, 'showClosingReport'])->name('shift.closing-report');

    // Order History
    Route::get('/pos/history', [OrderHistoryController::class, 'index'])->name('pos.history');
    Route::post('/pos/history/void/{sale}', [OrderHistoryController::class, 'void'])->name('pos.history.void');

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

    // Shared Network Info (Vouchers list + Active Sessions visible to staff —
    // set-tier stays admin-only, see the ADMIN ONLY network group below).
    Route::prefix('network')->name('network.')->group(function () {
        Route::get('/vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
        Route::get('/sessions', [VoucherController::class, 'sessions'])->name('sessions');
        Route::post('/sessions/kick', [VoucherController::class, 'kick'])->name('sessions.kick');
    });

    // Proactive AI analysis history (shared: audience-scoped in the controller,
    // same convention as network.vouchers.index above).
    Route::get('/ai/analysis-history', [AiAnalysisController::class, 'index'])->name('ai.analysis.index');

    // AI Agent proposed-action confirm/reject (shared: staff can confirm their own
    // confirm-tier proposals; ToolCallOrchestrator itself blocks staff from
    // confirming admin_only actions regardless of route access). NOTE: the
    // "admin." route-name prefix here is just namespacing, not an access gate —
    // do not "fix" these into an admin-only middleware group, that would break
    // staff's only mechanism for approving their own proposed actions and their
    // pending-action badge below.
    Route::prefix('admin/ai/actions')->name('admin.ai.actions.')->middleware('throttle:ai-actions')->group(function () {
        Route::post('/{audit}/confirm', [AiActionController::class, 'confirm'])->name('confirm');
        Route::post('/{audit}/reject', [AiActionController::class, 'reject'])->name('reject');
        Route::get('/pending-count', [AiActionController::class, 'pendingCount'])->name('pending-count');
        Route::get('/pending-preview', [AiActionController::class, 'pendingPreview'])->name('pending-preview');
        Route::get('/statuses', [AiActionController::class, 'statuses'])->name('statuses');
    });

    // Barista AI conversation history (admin/staff widgets only — see
    // agent-chat.blade.php's historyEnabled prop). Scoped to the requesting
    // user inside the controller, so plain 'auth' is enough here.
    Route::prefix('ai/conversations')->name('ai.conversations.')->group(function () {
        Route::get('/', [AiConversationController::class, 'index'])->name('index');
        Route::get('/{conversation}', [AiConversationController::class, 'show'])->name('show');
        Route::delete('/{conversation}', [AiConversationController::class, 'destroy'])->name('destroy');
    });

    // ==========================================
    // ADMIN ONLY ROUTES
    // ==========================================
    Route::middleware([RoleMiddleware::class.':admin'])->group(function () {

        // Admin Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/admin/live-stats', [DashboardController::class, 'liveStats'])->name('admin.live-stats'); // Added live-stats route
        Route::get('/admin/dashboard/live-data', [DashboardController::class, 'liveBusinessData'])->name('admin.dashboard.live-data');
        Route::post('/admin/ai/chat', [DashboardController::class, 'adminChat'])->name('admin.ai.chat')->middleware('throttle:admin-ai-chat');
        Route::get('/admin/ai/insights', [DashboardController::class, 'getAIInsights'])->name('admin.ai.insights');
        Route::get('/admin/analytics', [AnalyticsController::class, 'index'])->name('admin.analytics');
        Route::get('/admin/ai/actions', [AiActionController::class, 'index'])->name('admin.ai.actions.index');

        // Void Requests (staff-submitted, admin-reviewed)
        Route::post('/pos/history/void-requests/{void_request}/approve', [OrderHistoryController::class, 'approveVoidRequest'])->name('pos.history.void-requests.approve');
        Route::post('/pos/history/void-requests/{void_request}/reject', [OrderHistoryController::class, 'rejectVoidRequest'])->name('pos.history.void-requests.reject');

        // Inventory Management
        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/logs', [IngredientController::class, 'logs'])->name('logs');
            Route::resource('categories', CategoryController::class)->except(['create', 'show', 'edit']);
            Route::post('categories/suggest-ai', [CategoryController::class, 'suggestAi'])->name('categories.suggest-ai');
            Route::resource('products', ProductController::class)->except(['create', 'show', 'edit']);
            Route::patch('products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('products.toggle-status');
            // No destroy: inventory_logs.ingredient_id and product_ingredients.ingredient_id
            // both cascade-delete, so hard-deleting an ingredient would silently wipe its
            // entire audit trail and product associations. Ingredients are deactivated via
            // their `status` field through update() instead (same pattern as
            // ProductController::toggleStatus) — IngredientController has no destroy()
            // method, so this route would 500 if ever reached.
            Route::resource('ingredients', IngredientController::class)->except(['create', 'show', 'edit', 'destroy']);
            Route::post('ingredients/{ingredient}/add-stock', [IngredientController::class, 'addStock'])->name('ingredients.add-stock');

            // Supplier Deliveries (Receiving)
            Route::resource('deliveries', IngredientDeliveryController::class)->only(['index', 'store', 'destroy']);
            Route::post('deliveries/{delivery}/confirm', [IngredientDeliveryController::class, 'confirm'])->name('deliveries.confirm');
            Route::post('deliveries/{delivery}/reject', [IngredientDeliveryController::class, 'reject'])->name('deliveries.reject');

            // Suppliers Database
            Route::resource('suppliers', SupplierController::class)->except(['create', 'show', 'edit']);

            // Purchase Order Drafts (AI-drafted, human-managed)
            Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'destroy']);
            Route::post('purchase-orders/{draft}/send', [PurchaseOrderController::class, 'send'])->name('purchase-orders.send');

            // Wastage & Spoilage
            Route::resource('wastage', WastageController::class)->only(['index', 'store', 'destroy']);
        });

        // Network & Voucher Actions (Admin only)
        Route::prefix('network')->name('network.')->group(function () {
            // GET sessions and kick are shared with staff — see the "Shared
            // Network Info" group above. Tier changes stay admin-only.
            Route::post('/sessions/set-tier', [VoucherController::class, 'setTier'])->name('sessions.set-tier');

            Route::post('/vouchers/generate', [VoucherController::class, 'generateBatch'])->name('vouchers.generate');
            Route::post('/vouchers/bulk-delete', [VoucherController::class, 'bulkDestroy'])->name('vouchers.bulk-delete');
            Route::post('/vouchers/purge', [VoucherController::class, 'purge'])->name('vouchers.purge');
            Route::get('/vouchers/batch-print', [VoucherController::class, 'printBatch'])->name('vouchers.batch-print');
            Route::get('/vouchers/{voucher}/print', [VoucherController::class, 'print'])->name('vouchers.print');
            Route::delete('/vouchers/{voucher}', [VoucherController::class, 'destroy'])->name('vouchers.destroy');

            Route::get('/plans', [VoucherController::class, 'plans'])->name('plans');
            Route::get('/traffic', [TrafficController::class, 'index'])->name('traffic');
            Route::get('/traffic/stats', [TrafficController::class, 'stats'])->name('traffic.stats'); // Added stats route
            Route::post('/traffic', [TrafficController::class, 'update'])->name('traffic.update');

            Route::get('/blocklist', [BlocklistController::class, 'index'])->name('blocklist');
            Route::post('/blocklist', [BlocklistController::class, 'store'])->name('blocklist.store');
            Route::delete('/blocklist/{device}', [BlocklistController::class, 'destroy'])->name('blocklist.destroy');

            // Static IP assignments (MAC-bound Kea DHCP reservations, replaces
            // the old app-only "Permanent Kape Devices" IP whitelist).
            Route::post('/static-ips', [StaticIpController::class, 'store'])->name('static-ips.store');
            Route::delete('/static-ips/{assignment}', [StaticIpController::class, 'destroy'])->name('static-ips.destroy');

            // Captive portal allow-list — OPNsense's own "Allowed IP/MAC
            // addresses" passthrough, distinct from static-ips above: these
            // devices skip the portal entirely, no voucher ever required.
            Route::post('/allowed-addresses/ips', [AllowedAddressController::class, 'storeIp'])->name('allowed-addresses.ips.store');
            Route::delete('/allowed-addresses/ips', [AllowedAddressController::class, 'destroyIp'])->name('allowed-addresses.ips.destroy');
            Route::post('/allowed-addresses/macs', [AllowedAddressController::class, 'storeMac'])->name('allowed-addresses.macs.store');
            Route::delete('/allowed-addresses/macs', [AllowedAddressController::class, 'destroyMac'])->name('allowed-addresses.macs.destroy');
        });

        // Finance / Sales Reports
        Route::get('/sales/export', [SalesController::class, 'export'])->name('sales.export');
        Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');

        // Z-Reads / End of Day Audits
        Route::get('/finance/z-reads', [EndOfDayController::class, 'index'])->name('admin.finance.z-reads');
        Route::get('/finance/z-reads/{shift}', [EndOfDayController::class, 'show'])->name('admin.finance.shift-detail');

        // System Accounts
        Route::resource('accounts', AccountController::class)->except(['create', 'show', 'edit']);

        // System Settings (Consolidated) — Store Preferences and the AI Providers
        // API-key form are business-facing and stay reachable by admin-or-above
        // (the shop owner should be able to plug in their own AI provider
        // account); the AI status/testing/model-swap actions and Network/Agent
        // are technical and reserved for super_admin only (see nested group below).
        Route::prefix('settings')->name('admin.settings.')->group(function () {
            Route::get('/store', [SettingController::class, 'store'])->name('store');
            Route::post('/store', [SettingController::class, 'updateStore'])->name('store.update');
            Route::get('/ai-providers', [SettingController::class, 'aiProviders'])->name('ai-providers');
            Route::post('/ai-providers', [SettingController::class, 'updateAiProviders'])->name('ai-providers.update');

            Route::middleware([RoleMiddleware::class.':super_admin'])->group(function () {
                Route::post('/ai-providers/{provider}/test', [SettingController::class, 'testAiProvider'])
                    ->whereIn('provider', ['gemini', 'groq', 'openrouter'])
                    ->name('ai-providers.test');
                Route::post('/ai-providers/{provider}/models/replace', [SettingController::class, 'replaceProviderModel'])
                    ->whereIn('provider', ['gemini', 'groq', 'openrouter'])
                    ->name('ai-providers.models.replace');
                Route::post('/ai-providers/{provider}/models/reset', [SettingController::class, 'resetProviderModels'])
                    ->whereIn('provider', ['gemini', 'groq', 'openrouter'])
                    ->name('ai-providers.models.reset');
                Route::get('/network', [SettingController::class, 'network'])->name('network');
                Route::post('/network', [SettingController::class, 'updateNetwork'])->name('network.update');
                Route::get('/agent', [SettingController::class, 'agent'])->name('agent');
                Route::post('/agent', [SettingController::class, 'updateAgentPermissions'])->name('agent.update');
            });
        });
    });

    // ==========================================
    // STAFF ONLY ROUTES
    // ==========================================
    Route::middleware([RoleMiddleware::class.':staff'])->group(function () {

        // Staff Hub
        Route::get('/staff-dashboard', [StaffController::class, 'index'])->name('staff.dashboard');
        Route::get('/staff-dashboard/live', [StaffController::class, 'getLiveData'])->name('staff.dashboard.live');
        Route::post('/staff/ai/chat', [StaffController::class, 'staffChat'])->name('staff.ai.chat')->middleware('throttle:staff-ai-chat');

        // Delivery Receiving (staff-submitted; auto-confirms on a matching
        // sent purchase order, otherwise held for admin review)
        Route::get('/staff/deliveries', [StaffDeliveryController::class, 'index'])->name('staff.deliveries.index');
        Route::post('/staff/deliveries', [StaffDeliveryController::class, 'store'])->name('staff.deliveries.store');

    });
});

require __DIR__.'/auth.php';
