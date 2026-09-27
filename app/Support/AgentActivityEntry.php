<?php

namespace App\Support;

use App\Models\AiActionAudit;
use Illuminate\Support\Str;

/**
 * One Agent Activity row, in words a cafe owner reads without a glossary —
 * no tool names, warning codes or capitalised statuses. Presentation only:
 * the audit record itself is untouched, so the trail stays exact.
 */
class AgentActivityEntry
{
    /**
     * tool => [what it did, what it wants to do]. A null second entry marks a
     * routine look-up: it only reads data, never changes anything, and is
     * hidden by default so real actions aren't buried (they were ~20 of ~80).
     */
    private const TOOLS = [
        'blockSites' => ['Blocked websites for guests', 'Wants to block websites for guests', 'globe'],
        'unblockSites' => ['Unblocked websites', 'Wants to unblock websites', 'globe'],
        'blockDevice' => ['Blocked a device from the Wi-Fi', 'Wants to block a device from the Wi-Fi', 'ban'],
        'unblockDevice' => ['Let a blocked device back on', 'Wants to let a blocked device back on', 'ban'],
        'setSessionBandwidthTier' => ["Changed a guest's Wi-Fi speed", "Wants to change a guest's Wi-Fi speed", 'gauge'],
        'adjustFairUseCeiling' => ['Adjusted the Wi-Fi speed limit', 'Wants to adjust the Wi-Fi speed limit', 'gauge'],
        'generateVoucherBatch' => ['Created Wi-Fi vouchers', 'Wants to create Wi-Fi vouchers', 'ticket'],
        'restockIngredient' => ['Restocked an ingredient', 'Wants to restock an ingredient', 'package'],
        'voidSale' => ['Voided a sale', 'Wants to void a sale', 'receipt'],
        'draftSupplierPo' => ['Drafted a supplier order', 'Wants to draft a supplier order', 'truck'],
        'sendSupplierPo' => ['Sent a supplier order', 'Wants to send a supplier order', 'truck'],
        'suggestCategoryContent' => ['Wrote menu category text', 'Wants to update menu category text', 'pencil'],

        'getAnomalySignals' => ['Checked for unusual activity', null, 'radar'],
        'getActiveSessions' => ["Checked who's on the Wi-Fi", null, 'users'],
        'getTrafficStats' => ['Checked Wi-Fi speed and traffic', null, 'activity'],
        'checkStockLevels' => ['Checked stock levels', null, 'package'],
        'getSalesSummary' => ['Checked sales', null, 'banknote'],
        'shiftHandoffSummary' => ['Summarised the shift', null, 'clipboard-list'],
        'listSupplierPoDrafts' => ['Checked supplier order drafts', null, 'truck'],
        'listBlockedSites' => ['Checked which websites are blocked', null, 'globe'],
        'lookupVoucher' => ['Looked up a voucher', null, 'ticket'],
        'checkMySession' => ["Checked a guest's Wi-Fi time", null, 'clock'],
        'getPortalPosture' => ['Checked the Wi-Fi login page settings', null, 'shield'],
        'getSystemHealth' => ["Checked the server's health", null, 'server'],
        'getScheduledJobHealth' => ['Checked the background tasks', null, 'timer'],
        'getAiStackStatus' => ['Checked the AI service', null, 'bot'],
        'getRecentSystemErrors' => ['Checked recent system errors', null, 'alert-triangle'],
        'listUserAccounts' => ['Checked staff accounts', null, 'users'],
    ];

    /** What each automatic warning means, without the internal code name. */
    private const SIGNALS = [
        'voucher_revenue_divergence' => 'more Wi-Fi codes used than sales would explain',
        'repeat_mac_abuse' => 'one device used several vouchers',
        'banned_device_reentry' => 'a blocked device got back on the Wi-Fi',
        'low_stock_high_demand' => "a popular item's ingredient is running low",
        'network_internet' => 'the internet link is down or unstable',
        'network_firewall' => "the firewall can't be reached or a gateway is down",
        'network_dns' => 'DNS or site blocking is not working',
        'network_dhcp' => 'the Wi-Fi is running out of addresses',
        'network_portal' => 'the Wi-Fi login page is not loading',
        'network_infrastructure' => 'network equipment is not responding',
        'network_unknown_devices' => 'a blocked device is back on the network',
    ];

    /** Friendlier names for the inputs an owner sees on a proposal. */
    private const PARAMS = [
        'ip_address' => 'Device IP',
        'mac_address' => 'Device ID',
        'session_id' => 'Session',
        'tier' => 'Speed tier',
        'duration_minutes' => 'Minutes each',
        'quantity' => 'How many',
        'domains' => 'Websites',
        'reason' => 'Reason',
        'voucher_code' => 'Voucher',
        'ingredient' => 'Ingredient',
        'ingredient_name' => 'Ingredient',
        'amount' => 'Amount',
        'sale_id' => 'Sale',
        'transaction_number' => 'Order',
        'mbps' => 'Speed (Mbps)',
    ];

    /** A warning's plain meaning, e.g. for Findings History ("low_stock_high_demand" -> "a popular item's ingredient is running low"). */
    public static function signalLabel(string $type): string
    {
        return Str::ucfirst(self::SIGNALS[$type] ?? str_replace('_', ' ', $type));
    }

    /** @return string[] tool names that only read data */
    public static function routineTools(): array
    {
        return array_keys(array_filter(self::TOOLS, fn ($t) => $t[1] === null));
    }

    /**
     * The same friendly label anywhere a tool name would otherwise reach a
     * person: the chat's confirmation card and the header's pending list
     * showed "blockSites" in capitals.
     */
    public static function labelFor(string $tool, bool $proposed): string
    {
        $entry = self::TOOLS[$tool] ?? null;

        if (! $entry) {
            return Str::headline($tool);
        }

        return $proposed && $entry[1] ? $entry[1] : $entry[0];
    }

    /**
     * The action as a command, for settings pages ("Block websites for
     * guests"), where past tense ("Blocked…") would read as a log entry.
     */
    public static function actionName(string $tool): string
    {
        $entry = self::TOOLS[$tool] ?? null;
        if (! $entry) {
            return Str::headline($tool);
        }
        if ($entry[1]) {
            return Str::ucfirst(Str::after($entry[1], 'Wants to '));
        }

        return preg_replace(['/^Checked /', '/^Looked up /', '/^Summarised /'], ['Check ', 'Look up ', 'Summarise '], $entry[0]);
    }

    public function __construct(public readonly AiActionAudit $audit) {}

    public function isRoutine(): bool
    {
        // Unknown names (the log holds a model's hallucinated "None") are
        // routine too: nothing ran, nothing to act on.
        return ! isset(self::TOOLS[$this->audit->tool_name]) || self::TOOLS[$this->audit->tool_name][1] === null;
    }

    public function isPending(): bool
    {
        return $this->audit->status === 'proposed';
    }

    public function title(): string
    {
        $tool = self::TOOLS[$this->audit->tool_name] ?? null;

        if (! $tool) {
            return 'Tried an action that doesn’t exist';
        }

        // Proposed and declined are both "wanted to"; the status says which.
        return in_array($this->audit->status, ['proposed', 'rejected'], true) && $tool[1] ? $tool[1] : $tool[0];
    }

    public function icon(): string
    {
        return 'lucide-'.(self::TOOLS[$this->audit->tool_name][2] ?? 'bot');
    }

    /** One plain sentence of detail, or null when the title says it all. */
    public function detail(): ?string
    {
        $result = $this->audit->result ?? [];
        $params = $this->audit->input_params ?? [];

        if ($this->audit->tool_name === 'getAnomalySignals' && isset($result['data']['signals'])) {
            $found = collect($result['data']['signals'])
                ->map(fn ($s) => self::SIGNALS[$s['type'] ?? ''] ?? null)
                ->filter()->unique()->values();

            return $found->isEmpty() ? 'Nothing unusual found.' : 'Found: '.$found->implode('; ').'.';
        }

        // Old failures from when the tool couldn't look up bare site names.
        if ($this->audit->tool_name === 'blockSites' && str_starts_with((string) ($result['message'] ?? ''), 'No valid domain names to block')) {
            return 'It couldn’t tell which websites these were: '.Str::after($result['message'], ': ').' Ask again — it now looks site names up by itself.';
        }

        if (! empty($result['message'])) {
            return $this->plain($result['message']);
        }

        if ($params === []) {
            return null;
        }

        return collect($params)
            ->map(fn ($value, $key) => (self::PARAMS[$key] ?? Str::ucfirst(str_replace('_', ' ', Str::snake($key)))).': '
                .$this->plain(is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value))
            ->implode(' · ');
    }

    /**
     * Strip code-speak out of text written by tools and the model: warning
     * codes become their meaning ("repeat_mac_abuse" -> "one device used
     * several vouchers"), any other snake_case word becomes plain words
     * ("voucher_code" -> "voucher code").
     */
    private function plain(string $text): string
    {
        $text = strtr($text, self::SIGNALS);

        return preg_replace_callback('/\b[a-z]+(?:_[a-z]+)+\b/', fn ($m) => str_replace('_', ' ', $m[0]), $text);
    }

    /** Who started it, in words. */
    public function requestedBy(): string
    {
        return $this->audit->actor
            ? $this->audit->actor->name.', in chat'
            : 'Barista AI, on its own';
    }

    /** @return array{label: string, classes: string, icon: string} */
    public function status(): array
    {
        return match ($this->audit->status) {
            'executed' => ['label' => 'Done', 'classes' => 'bg-green-50 text-green-800 border-green-200', 'icon' => 'lucide-check'],
            'proposed' => ['label' => 'Waiting for your OK', 'classes' => 'bg-amber-50 text-amber-900 border-amber-300', 'icon' => 'lucide-clock'],
            'rejected' => ['label' => $this->audit->approvedBy ? 'Declined by '.$this->audit->approvedBy->name : 'Declined', 'classes' => 'bg-gray-100 text-gray-700 border-gray-200', 'icon' => 'lucide-x'],
            default => ['label' => 'Didn’t work', 'classes' => 'bg-red-50 text-red-800 border-red-200', 'icon' => 'lucide-alert-circle'],
        };
    }

    /** "OK'd by …" for an approved action, else null. */
    public function approvedBy(): ?string
    {
        return $this->audit->status !== 'rejected' && $this->audit->approvedBy
            ? 'OK’d by '.$this->audit->approvedBy->name
            : null;
    }
}
