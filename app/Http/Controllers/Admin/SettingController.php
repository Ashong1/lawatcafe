<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\StaticIpAssignment;
use App\Services\Agent\PermissionResolver;
use App\Services\Agent\ToolRegistry;
use App\Services\AIService;
use App\Services\OpnSenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    /**
     * Display Store Preferences.
     */
    public function store()
    {
        $settings = [
            'low_stock_threshold' => Setting::get('low_stock_threshold', '500'),
            'store_open_time' => Setting::get('store_open_time', '08:00'),
            'store_close_time' => Setting::get('store_close_time', '22:00'),
            'receipt_header' => Setting::get('receipt_header', 'Thank you for visiting Lawa\'t Kape!'),
        ];

        return view('admin.settings.store', compact('settings'));
    }

    /**
     * Display AI provider API key configuration and availability status.
     * Merges what used to be the separate "API Integrations" page (which
     * only ever held a decorative, unused model dropdown and one API key
     * field mislabeled as generic but actually OpenRouter-only) into a
     * single accurate page alongside real per-provider/per-model status.
     */
    public function aiProviders(AIService $ai)
    {
        $providers = $ai->getProviderStatuses();
        $settings = [
            'gemini_api_key' => Setting::get('gemini_api_key', ''),
            'groq_api_key' => Setting::get('groq_api_key', ''),
            'openrouter_api_key' => Setting::get('openrouter_api_key', ''),
        ];

        return view('admin.settings.ai-providers', compact('providers', 'settings'));
    }

    /**
     * Save AI provider API keys (fallback used only when the matching env
     * var isn't set — see AIService's constructor).
     */
    public function updateAiProviders(Request $request)
    {
        $validated = $request->validate([
            'gemini_api_key' => 'nullable|string',
            'groq_api_key' => 'nullable|string',
            'openrouter_api_key' => 'nullable|string',
        ]);

        $this->applySettings($validated);

        return redirect()->back()->with('success', 'API keys updated successfully.');
    }

    /**
     * Ping every model under a provider right now and record fresh status.
     */
    public function testAiProvider(string $provider, AIService $ai)
    {
        if (! in_array($provider, ['gemini', 'groq', 'openrouter'], true)) {
            abort(404);
        }

        $result = $ai->testProvider($provider);

        return redirect()->back()->with('success', "Tested {$provider}: {$result['ok']} model(s) healthy, {$result['failed']} failed.");
    }

    /**
     * Swap a model in a provider's active list for a different model ID,
     * verifying the replacement immediately.
     */
    public function replaceProviderModel(Request $request, string $provider, AIService $ai)
    {
        if (! in_array($provider, ['gemini', 'groq', 'openrouter'], true)) {
            abort(404);
        }

        $validated = $request->validate([
            'old_model' => 'required|string',
            'new_model' => 'required|string|max:255',
        ]);

        $result = $ai->replaceModel($provider, $validated['old_model'], $validated['new_model']);

        if (! $result['replaced']) {
            return redirect()->back()->with('error', "Couldn't find {$validated['old_model']} in the {$provider} model list.");
        }

        return redirect()->back()->with(
            $result['new_model_ok'] ? 'success' : 'error',
            "Replaced {$validated['old_model']} with {$validated['new_model']} — ".($result['new_model_ok'] ? 'it tested healthy.' : 'but it failed too. Try a different model ID.')
        );
    }

    /**
     * Clear a provider's model-list override, reverting to the hardcoded defaults.
     */
    public function resetProviderModels(string $provider, AIService $ai)
    {
        if (! in_array($provider, ['gemini', 'groq', 'openrouter'], true)) {
            abort(404);
        }

        $ai->resetModels($provider);

        return redirect()->back()->with('success', ucfirst($provider)."'s model list reset to defaults.");
    }

    /**
     * Display Network Configuration.
     */
    public function network(OpnSenseService $opnsense)
    {
        $settings = [
            'opnsense_zone' => Setting::get('opnsense_zone', '0'),
            'network_ignored_ips' => Setting::get('network_ignored_ips', '192.168.2.251,192.168.2.100,192.168.2.5,192.168.2.4'),
            'network_infrastructure_ips' => Setting::get('network_infrastructure_ips', Setting::DEFAULT_INFRASTRUCTURE_IPS),
        ];

        $staticIps = StaticIpAssignment::latest()->get();
        $allowedAddresses = $opnsense->getAllowedAddresses();

        // Shown next to both IP lists so an admin can see at a glance which
        // addresses are dynamic and therefore unusable as fixed identities.
        $dhcpPools = array_column($opnsense->getDhcpPools(), 'label');

        return view('admin.settings.network', compact('settings', 'staticIps', 'allowedAddresses', 'dhcpPools'));
    }

    /**
     * Display AI Agent tool permission tiers.
     */
    public function agent(ToolRegistry $registry, PermissionResolver $permissions)
    {
        $overrides = $permissions->currentOverrides();

        $tools = array_map(function (string $class) use ($overrides, $permissions) {
            $tool = app($class);
            $name = $tool->name();

            $tier = $tool->permissionTier();
            foreach (['auto_approved' => 'auto', 'confirm_required' => 'confirm', 'admin_only' => 'admin_only'] as $bucket => $bucketTier) {
                if (in_array($name, $overrides[$bucket], true)) {
                    $tier = $bucketTier;
                }
            }

            return [
                'name' => $name,
                'description' => $tool->description(),
                'default_tier' => $tool->permissionTier(),
                'configurable' => $permissions->isConfigurable($tool),
                'tier' => $permissions->isConfigurable($tool) ? $tier : $tool->permissionTier(),
            ];
        }, $registry->allToolClasses());

        return view('admin.settings.agent', compact('tools'));
    }

    /**
     * Update AI Agent tool permission tiers.
     */
    public function updateAgentPermissions(Request $request, ToolRegistry $registry, PermissionResolver $permissions)
    {
        $validTiers = [PermissionResolver::TIER_AUTO, PermissionResolver::TIER_CONFIRM, PermissionResolver::TIER_ADMIN_ONLY];
        $knownTools = array_map(fn ($class) => app($class)->name(), $registry->allToolClasses());

        $validated = $request->validate([
            'tiers' => 'required|array',
            'tiers.*' => 'required|string|in:'.implode(',', $validTiers),
        ]);

        $tierByToolName = array_intersect_key($validated['tiers'], array_flip($knownTools));
        $permissions->saveOverrides($tierByToolName);

        return redirect()->back()->with('success', 'AI agent permissions updated.');
    }

    /**
     * Update Store Preferences — business-facing settings, reachable by admin-or-above.
     */
    public function updateStore(Request $request)
    {
        $validated = $request->validate([
            'voucher_durations' => 'nullable|json',
            'low_stock_threshold' => 'nullable|numeric',
            'free_wifi_min_amount' => 'nullable|numeric|min:0',
            'free_wifi_duration' => 'nullable|numeric|min:1',
            'store_open_time' => 'nullable|string',
            'store_close_time' => 'nullable|string',
            'receipt_header' => 'nullable|string|max:255',
        ]);

        $this->applySettings($validated);

        Cache::forget('dashboard_stats_today');

        return redirect()->back()->with('success', 'Configuration updated successfully.');
    }

    /**
     * Update Network Configuration — super_admin only, enforced at the route level.
     */
    public function updateNetwork(Request $request, OpnSenseService $opnsense)
    {
        $validated = $request->validate([
            'network_ignored_ips' => 'nullable|string',
            'network_infrastructure_ips' => 'nullable|string',
            'opnsense_zone' => 'nullable|string',
        ]);

        // Both lists are permanent exemptions: an address in either one stops
        // being treated as a customer. A DHCP-pool address can't carry that
        // meaning — it belongs to a different phone every few hours — so
        // accepting one guarantees a real guest silently disappears from the
        // sessions page later. (Skipped when OPNsense is unreachable and the
        // pool list comes back empty, rather than blocking the save.)
        //
        // Only newly-introduced addresses are checked. network_ignored_ips is
        // posted as a hidden field on this form, so rejecting a value that is
        // already stored would lock the admin out of saving anything at all
        // through a field this page never lets them edit.
        foreach (['network_ignored_ips', 'network_infrastructure_ips'] as $field) {
            $submitted = array_filter(array_map('trim', explode(',', (string) ($validated[$field] ?? ''))));
            $stored = array_filter(array_map('trim', explode(',', (string) Setting::get($field, ''))));

            foreach (array_diff($submitted, $stored) as $ip) {
                if ($pool = $opnsense->dhcpPoolContaining($ip)) {
                    return back()->withInput()->withErrors([
                        $field => "{$ip} is inside the DHCP pool {$pool['label']}, so it belongs to whichever guest currently holds that lease. Exempting it would hide a real customer. Give the device a static DHCP reservation outside the pool first.",
                    ]);
                }
            }
        }

        $this->applySettings($validated);

        return redirect()->back()->with('success', 'Configuration updated successfully.');
    }

    private function applySettings(array $validated, array $exclude = []): void
    {
        foreach ($validated as $key => $value) {
            if (! in_array($key, $exclude, true)) {
                Setting::set($key, $value);
            }
        }
    }
}
