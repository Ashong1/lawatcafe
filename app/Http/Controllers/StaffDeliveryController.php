<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\IngredientDelivery;
use App\Models\PurchaseOrderDraft;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Services\DeliveryReceivingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class StaffDeliveryController extends Controller
{
    public function index(Request $request)
    {
        // "Check a delivery": a PO number ("PO-00044" or "44") or a supplier's name
        // brings up that supplier's open orders as a checklist (owner, Oct 30).
        $ref = trim((string) $request->query('ref'));
        $found = null;
        if ($ref !== '') {
            $digits = (int) preg_replace('/\D/', '', $ref);
            $found = PurchaseOrderDraft::with(['ingredient', 'supplier'])->where('status', 'sent')
                ->where(fn ($q) => $q->when($digits && preg_match('/^(po-?)?\d+$/i', $ref), fn ($q) => $q->where('id', $digits))
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%'.$ref.'%')))
                ->orderBy('id')->get();
        }

        $pendingOrders = PurchaseOrderDraft::with(['ingredient', 'supplier'])
            ->where('status', 'sent')
            ->latest()
            ->get();

        $ingredients = Ingredient::orderBy('name')->get();
        $suppliers = \App\Models\Supplier::orderBy('name')->pluck('name');

        $myDeliveries = IngredientDelivery::with('items.ingredient')
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(10);

        return view('staff.deliveries', compact('pendingOrders', 'ingredients', 'myDeliveries', 'suppliers', 'ref', 'found'));
    }

    public function store(Request $request, DeliveryReceivingService $service)
    {
        $validated = $request->validate([
            'supplier_name' => 'required|string|max:255',
            'delivery_date' => 'required|date',
            'reference_number' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.ingredient_id' => 'required|exists:ingredients,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.cost_per_unit' => 'required|numeric|min:0',
            'items.*.purchase_order_draft_id' => 'nullable|integer|exists:purchase_order_drafts,id',
            'items.*.missing' => 'nullable|boolean',
        ]);

        // Missing items aren't received; they and any short ones go in the note for the owner.
        [$missing, $received] = collect($validated['items'])->partition(fn ($item) => ! empty($item['missing']));
        $problems = $this->problems($missing, $received);
        if ($received->isEmpty()) {
            return back()->with('error', 'Nothing was received, so nothing was recorded. The order stays open.');
        }
        $validated['items'] = $received->map(fn ($item) => \Illuminate\Support\Arr::except($item, 'missing'))->values()->all();
        if ($problems) {
            $validated['note'] = trim($problems.' '.($validated['note'] ?? ''));
        }

        $delivery = $service->recordStaffDelivery($validated, auth()->id());

        // Everything that came matched, but something didn't come: stock goes in, the owner still hears about it.
        if ($problems && $delivery->status === 'confirmed') {
            Notification::send(User::active()->whereIn('role', ['admin', 'super_admin'])->get(), new SystemAlert(
                'Delivery came incomplete', "Delivery from {$delivery->supplier_name}: {$problems} What arrived is in stock; the rest stays on order.", 'truck', route('inventory.deliveries.index')));
        }

        $message = $delivery->status === 'confirmed'
            ? 'Delivery recorded — it matched a pending order, so stock was updated automatically.'
            : "Delivery recorded, but its details didn't match a pending order. An admin will need to review it before stock is updated.";

        return redirect()->route('staff.deliveries.index')
            ->with($delivery->status === 'confirmed' ? 'success' : 'status', $message);
    }

    /** "Missing: Sugar (500 g ordered). Short: Milk 800 of 1,000 ml." for items received against a PO. */
    private function problems($missing, $received): string
    {
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
        $drafts = PurchaseOrderDraft::with('ingredient')->whereIn('id', collect([...$missing, ...$received])->pluck('purchase_order_draft_id')->filter())->get()->keyBy('id');

        $lines = $missing->map(fn ($i) => ($d = $drafts[$i['purchase_order_draft_id'] ?? 0] ?? null)
            ? "{$d->ingredient->name} ({$fmt($d->suggested_quantity)} {$d->ingredient->unit} ordered)" : null)->filter();
        $short = $received->map(fn ($i) => ($d = $drafts[$i['purchase_order_draft_id'] ?? 0] ?? null) && (float) $i['quantity'] < (float) $d->suggested_quantity - 0.009
            ? "{$d->ingredient->name} {$fmt($i['quantity'])} of {$fmt($d->suggested_quantity)} {$d->ingredient->unit}" : null)->filter();

        return trim(($lines->isNotEmpty() ? 'Missing: '.$lines->implode(', ').'.' : '').($short->isNotEmpty() ? ' Short: '.$short->implode(', ').'.' : ''));
    }
}
