<?php

namespace App\Services\Agent;

use Illuminate\Support\Facades\Route;

/**
 * The app's own pages, described for the assistant.
 *
 * When ai:resolve-gaps finds the assistant couldn't do something that a page
 * already does, it learns to send the user there. The model may only choose
 * from this list, and only pages the user's role can open — so a learned
 * pointer can never be a made-up URL or a page that 403s.
 */
final class PageCatalog
{
    private const ADMIN = [ToolRegistry::AUDIENCE_ADMIN, ToolRegistry::AUDIENCE_SUPER_ADMIN];

    private const ALL_STAFF = [ToolRegistry::AUDIENCE_STAFF, ToolRegistry::AUDIENCE_ADMIN, ToolRegistry::AUDIENCE_SUPER_ADMIN];

    /** route name => [label, what it's for, audiences] */
    private const PAGES = [
        'pos' => ['Register', 'Ring up orders and sell Wi-Fi codes.', [ToolRegistry::AUDIENCE_STAFF, ToolRegistry::AUDIENCE_ADMIN]],
        'kds.index' => ['Kitchen Display', 'Orders waiting to be made; mark items and orders done.', [ToolRegistry::AUDIENCE_STAFF, ToolRegistry::AUDIENCE_ADMIN]],
        'pos.history' => ['Orders', 'Past sales and orders; request or approve voids.', self::ALL_STAFF],
        'network.sessions' => ["Wi-Fi & Network > Who's Online", 'See who is connected to the guest Wi-Fi, disconnect a device, add time, see data used.', self::ALL_STAFF],
        'network.vouchers.index' => ['Wi-Fi & Network > Wi-Fi Codes', 'Make, print, search and delete Wi-Fi codes.', self::ALL_STAFF],
        'network.health' => ['Wi-Fi & Network > Network Status', 'Whether the internet, router, Wi-Fi sign-in page and shop equipment are working.', self::ALL_STAFF],
        'network.plans' => ['Wi-Fi & Network > Wi-Fi Prices', 'Wi-Fi code prices and durations, and the free Wi-Fi promo.', self::ADMIN],
        'network.traffic' => ['Wi-Fi & Network > Wi-Fi Speed', 'Speed of the Free and Premium plans, and the per-device speed limit.', self::ADMIN],
        'network.trusted-devices' => ['Wi-Fi & Network > Trusted Devices', 'Let a shop phone or laptop use the Wi-Fi without a code.', self::ADMIN],
        'network.blocklist' => ['Wi-Fi & Network > Blocked Devices', 'Block or unblock a device from the Wi-Fi.', self::ADMIN],
        'network.site-blocking' => ['Wi-Fi & Network > Blocked Websites', 'Block or unblock websites for all guests; turn on the adult-sites list.', self::ADMIN],
        'network.portal-report' => ['Wi-Fi & Network > Sign-in Report', 'How guests get online: wrong codes, busy hours, guests cut off early.', self::ADMIN],
        'staff.deliveries.index' => ['Deliveries', 'Receive a supplier delivery against a sent purchase order.', [ToolRegistry::AUDIENCE_STAFF]],
        'inventory.deliveries.index' => ['Inventory > Deliveries', 'Record and review supplier deliveries.', self::ADMIN],
        'inventory.ingredients.index' => ['Inventory > Ingredients', 'Add or edit ingredients, units, stock levels and low-stock alerts.', self::ADMIN],
        'inventory.products.index' => ['Inventory > Products', 'Add, edit, price or hide menu items and their recipes.', self::ADMIN],
        'inventory.categories.index' => ['Inventory > Categories', 'Menu categories and their descriptions.', self::ADMIN],
        'inventory.suppliers.index' => ['Inventory > Suppliers', 'Supplier contacts.', self::ADMIN],
        'inventory.purchase-orders.index' => ['Inventory > Purchase Orders', 'Draft, send and track purchase orders.', self::ADMIN],
        'inventory.wastage.index' => ['Inventory > Wastage', 'Record spoiled or wasted stock.', self::ADMIN],
        'inventory.logs' => ['Inventory > Stock History', 'History of every stock change.', self::ADMIN],
        'admin.finance.z-reads' => ['End of Day', 'Z-reads: each shift\'s cash count against what was expected.', self::ADMIN],
        'admin.analytics' => ['Barista AI > Sales Forecast', 'Sales charts and the 7-day forecast.', self::ADMIN],
        'admin.ai.actions.index' => ['Barista AI > Actions & Approvals', 'What Barista AI has done, and actions waiting for approval.', self::ADMIN],
        'admin.ai.lessons.index' => ['Barista AI > What It Learned', 'Review what the assistant has learned; approve or remove lessons.', self::ADMIN],
        'accounts.index' => ['Settings > Staff Accounts', 'Create, edit or remove staff accounts and reset passwords.', self::ADMIN],
        'admin.settings.store' => ['Settings > Store Settings', 'Opening hours, receipt text, order reminder time.', self::ADMIN],
        'admin.settings.ai-providers' => ['Settings > Barista AI Settings', 'The AI service key and which AI models the assistant uses.', self::ADMIN],
        'admin.settings.network' => ['System Administration > Network', 'Infrastructure address lists, fixed addresses, firewall zone.', [ToolRegistry::AUDIENCE_SUPER_ADMIN]],
    ];

    /** @return array<int, array{route: string, label: string, purpose: string, path: string}> */
    public static function forAudience(string $audience): array
    {
        $pages = [];
        foreach (self::PAGES as $route => [$label, $purpose, $audiences]) {
            if (in_array($audience, $audiences, true) && Route::has($route)) {
                $pages[] = ['route' => $route, 'label' => $label, 'purpose' => $purpose, 'path' => route($route, [], false)];
            }
        }

        return $pages;
    }

    public static function find(string $audience, string $route): ?array
    {
        foreach (self::forAudience($audience) as $page) {
            if ($page['route'] === $route) {
                return $page;
            }
        }

        return null;
    }
}
