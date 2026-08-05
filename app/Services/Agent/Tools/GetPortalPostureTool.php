<?php

namespace App\Services\Agent\Tools;

use App\Models\BannedDevice;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\OpnSenseService;

/**
 * super_admin only. What the captive portal is currently letting through.
 *
 * The figure worth having an assistant watch is awaiting_activation: vouchers
 * redeemed but never let past the firewall. Zero is normal churn; a number that
 * stays above zero means guests are paying and not getting online, which is the
 * failure nobody reports because the guest just leaves.
 */
class GetPortalPostureTool implements AgentTool
{
    public function __construct(protected OpnSenseService $opnsense) {}

    public function name(): string
    {
        return 'getPortalPosture';
    }

    public function description(): string
    {
        return 'Check the captive portal\'s access posture: how many IPs and MAC addresses bypass the portal entirely, how many devices are banned, unused voucher stock, and how many redeemed vouchers are stuck without internet access. Use for "is the portal set up correctly", "who can bypass the portal", or "are guests getting online". Read-only.';
    }

    public function parametersSchema(): array
    {
        return ['type' => 'object', 'properties' => [], 'required' => []];
    }

    public function permissionTier(): string
    {
        return 'auto';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $allowed = $this->opnsense->getAllowedAddresses();

        $stuck = Voucher::where('is_used', true)
            ->whereNull('activated_at')
            ->where('used_at', '>=', now()->subDay())
            ->count();

        $unused = Voucher::where('is_used', false)->count();

        $notes = [];
        if ($stuck > 0) {
            $notes[] = "{$stuck} voucher(s) redeemed in the last day never got online";
        }
        if ($unused < 10) {
            $notes[] = "only {$unused} unused vouchers left to sell";
        }

        $summary = empty($notes)
            ? 'Captive portal posture looks normal.'
            : 'Worth a look: '.implode('; ', $notes).'.';

        return ToolResult::ok($summary, [
            // Counts, not the addresses themselves — an allow-list is a
            // bypass list, and enumerating it into a chat transcript is a
            // needless disclosure. The Network Settings page shows the entries.
            'allow_listed_ips' => count($allowed['ips'] ?? []),
            'allow_listed_macs' => count($allowed['macs'] ?? []),
            'banned_devices' => BannedDevice::count(),
            'unused_vouchers' => $unused,
            'redeemed_but_never_online_today' => $stuck,
        ]);
    }
}
