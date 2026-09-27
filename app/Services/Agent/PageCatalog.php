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
        'network.sessions' => ['Network > Active Sessions', 'See who is connected to the guest Wi-Fi, disconnect a device, see data used.', self::ALL_STAFF],
        'network.vouchers.index' => ['Network > Vouchers', 'Generate, print, search, delete and purge Wi-Fi voucher codes.', self::ALL_STAFF],
        'network.site-blocking' => ['Network > Site Blocking', 'Block or unblock websites for all guests; turn on the adult-sites category list.', self::ADMIN],
        'network.blocklist' => ['Network > Device Blocklist', 'Ban or unban a device by MAC address.', self::ADMIN],
        'network.traffic' => ['Network > Traffic Shaping', 'Bandwidth caps and the per-device fair-use ceiling.', self::ADMIN],
        'network.plans' => ['Network > Wi-Fi Plans', 'Voucher prices and durations sold to guests.', self::ADMIN],
        'pos.history' => ['Order History', 'Past sales and orders; request or approve voids.', self::ALL_STAFF],
        'staff.deliveries.index' => ['Deliveries', 'Receive a supplier delivery against a sent purchase order.', [ToolRegistry::AUDIENCE_STAFF]],
        'inventory.deliveries.index' => ['Inventory > Deliveries', 'Record and review supplier deliveries.', self::ADMIN],
        'inventory.ingredients.index' => ['Inventory > Ingredients', 'Add or edit ingredients, units, stock levels and reorder points.', self::ADMIN],
        'inventory.products.index' => ['Inventory > Products', 'Add, edit, price or hide menu items and their recipes.', self::ADMIN],
        'inventory.categories.index' => ['Inventory > Categories', 'Menu categories and their descriptions.', self::ADMIN],
        'inventory.suppliers.index' => ['Inventory > Suppliers', 'Supplier contacts.', self::ADMIN],
        'inventory.purchase-orders.index' => ['Inventory > Purchase Orders', 'Draft, send and track purchase orders.', self::ADMIN],
        'inventory.wastage.index' => ['Inventory > Wastage', 'Record spoiled or wasted stock.', self::ADMIN],
        'inventory.logs' => ['Inventory > Logs', 'History of every stock change.', self::ADMIN],
        'admin.analytics' => ['Analytics', 'Sales and network charts over time.', self::ADMIN],
        'admin.finance.z-reads' => ['Z-Reads / Audits', 'End-of-day cash reconciliation and shift audits.', self::ADMIN],
        'accounts.index' => ['Business Settings > Staff Accounts', 'Create, edit or deactivate staff and admin accounts, reset passwords.', self::ADMIN],
        'admin.settings.store' => ['Business Settings > Store Preferences', 'Opening hours, receipt text, free-Wi-Fi rules.', self::ADMIN],
        'admin.settings.network' => ['Settings > Network', 'Infrastructure and VIP IP lists, OPNsense zone.', [ToolRegistry::AUDIENCE_SUPER_ADMIN]],
        'admin.settings.ai-providers' => ['Settings > AI Providers', 'Which AI models the assistant uses and their health.', self::ADMIN],
        'admin.ai.lessons.index' => ['AI Learning', 'Review what the assistant has learned; approve or revoke lessons and skills.', self::ADMIN],
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
