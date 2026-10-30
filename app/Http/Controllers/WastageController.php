<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\Wastage;
use App\Services\StockExpiryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WastageController extends Controller
{
    public function index()
    {
        $wastages = Wastage::with(['ingredient', 'user'])->latest()->paginate(15);
        $ingredients = Ingredient::orderBy('name')->get();

        return view('inventory.wastage', compact('wastages', 'ingredients'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'note' => 'nullable|string',
        ]);

        // One entry is at most one opened package (evaluators, Oct 28), and never more than the stock.
        $ingredient = Ingredient::find($validated['ingredient_id']);
        $stock = (float) $ingredient->current_stock;
        $pack = $ingredient->packaging_unit ? (float) $ingredient->capacity_per_pack : INF;
        $amount = fn (float $n) => rtrim(rtrim(number_format($n, 2), '0'), '.');
        if ($validated['quantity'] > min($stock, $pack)) {
            return back()->withInput()->withErrors(['quantity' => $pack < $stock
                ? "One opened {$ingredient->packaging_unit} holds {$amount($pack)} {$ingredient->unit}. Log each package separately."
                : "Only {$amount($stock)} {$ingredient->unit} in stock."]);
        }

        $this->record($ingredient, (float) $validated['quantity'], $validated['reason'], $validated['note'] ?? null);

        return redirect()->route('inventory.wastage.index')->with('success', 'Wastage logged and stock adjusted.');
    }

    public function destroy(Wastage $wastage)
    {
        DB::transaction(function () use ($wastage) {
            // Deleting a wastage entry means "this waste record was wrong" —
            // reverse the stock deduction it made at store() time so the
            // correction doesn't silently leave the ingredient permanently
            // short with no paper trail.
            $ingredient = $wastage->ingredient;
            $ingredient->current_stock += $wastage->quantity;
            $ingredient->save();

            InventoryLog::create([
                'type' => 'wastage',
                'ingredient_id' => $ingredient->id,
                'change_amount' => $wastage->quantity,
                'after_amount' => $ingredient->current_stock,
                'reason' => 'Wastage record deleted: '.$wastage->reason,
                'user_id' => auth()->id(),
            ]);

            $wastage->delete();
        });

        return redirect()->route('inventory.wastage.index')->with('success', 'Wastage record removed and stock restored.');
    }

    /** Writes off what's left of an expired delivery or restock (the Expiring soon list). */
    public function writeOffExpired(InventoryLog $inventoryLog, StockExpiryService $expiry)
    {
        $left = $expiry->leftOf($inventoryLog);
        if ($left <= 0) {
            return back()->with('error', 'Nothing from that delivery is left in stock.');
        }

        $this->record($inventoryLog->ingredient, $left, 'Expired', 'Expired '.$inventoryLog->expires_at?->format('M j, Y').'; received '.$inventoryLog->created_at->format('M j'));

        return back()->with('success', 'Wrote off '.rtrim(rtrim(number_format($left, 2), '0'), '.')." {$inventoryLog->ingredient->unit} of {$inventoryLog->ingredient->name} as expired.");
    }

    private function record(Ingredient $ingredient, float $quantity, string $reason, ?string $note): void
    {
        DB::transaction(function () use ($ingredient, $quantity, $reason, $note) {
            Wastage::create(['ingredient_id' => $ingredient->id, 'quantity' => $quantity, 'reason' => $reason, 'note' => $note, 'user_id' => auth()->id()]);

            $ingredient->current_stock -= $quantity;
            $ingredient->save();

            InventoryLog::create([
                'type' => 'wastage',
                'ingredient_id' => $ingredient->id,
                'change_amount' => -$quantity,
                'after_amount' => $ingredient->current_stock,
                'reason' => 'Wastage: '.$reason,
                'user_id' => auth()->id(),
            ]);
        });
    }
}
