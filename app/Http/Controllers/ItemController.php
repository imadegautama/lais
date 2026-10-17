<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemRequest;
use App\Models\Business;
use App\Models\Item;
use App\Models\StockMovement;
use App\Services\Inventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    /**
     * Product and stock list (UI-PAGES C5).
     * shortcut: sends every item and filters in the browser, fine below ~200 items; paginate server-side beyond that.
     */
    public function index(Request $request): Response
    {
        $items = $request->user()->currentBusiness()->items()
            ->with('recipeLines.material')
            ->orderBy('name')
            ->get();

        $rows = $items->map(fn (Item $item) => $this->row($item));

        return Inertia::render('items/index', [
            'items' => $rows->values(),
            'summary' => [
                'inventoryValue' => $items->filter->hasStock()->sum('stock_value'),
                'lowStockCount' => $rows->where('lowStock', true)->count(),
                'negativeStockCount' => $rows->where('negativeStock', true)->count(),
            ],
        ]);
    }

    /**
     * Create form (C6). `?jenis=` preselects the kind.
     */
    public function create(Request $request): Response
    {
        $kind = in_array($request->query('jenis'), Item::KINDS, true) ? $request->query('jenis') : 'produk';

        return Inertia::render('items/form', [
            'item' => null,
            'initialKind' => $kind,
            'materials' => $this->materials($request->user()->currentBusiness()),
            'movements' => [],
        ]);
    }

    public function store(ItemRequest $request, Inventory $inventory): RedirectResponse
    {
        $business = $request->user()->currentBusiness();
        $data = $request->validated();

        $item = DB::transaction(function () use ($business, $data, $inventory) {
            $item = $business->items()->create(['kind' => $data['kind']] + $this->attributes($data, $data['kind']));

            if ($item->kind === 'komposisi') {
                $item->recipeLines()->createMany($data['recipe']);
            }

            if ((float) ($data['opening_stock'] ?? 0) > 0) {
                $inventory->receive($item, (float) $data['opening_stock'], (int) $data['opening_cost'], 'stok_awal', '3101');
            }

            return $item;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$item->name} tersimpan."]);

        return to_route('items.index');
    }

    public function edit(Request $request, Item $item): Response
    {
        Gate::authorize('manage', $item);

        $item->load('recipeLines.material');

        return Inertia::render('items/form', [
            'item' => $this->row($item) + [
                'recipe' => $item->recipeLines->map(fn ($line) => [
                    'material_id' => $line->material_id,
                    'qty' => (float) $line->qty,
                ])->values(),
            ],
            'initialKind' => $item->kind,
            'materials' => $this->materials($item->business, except: $item),
            'movements' => $item->stockMovements()->latest('occurred_at')->latest('id')->limit(20)->get()
                ->map(fn (StockMovement $movement) => [
                    'id' => $movement->id,
                    'occurredAt' => $movement->occurred_at->toIso8601String(),
                    'reason' => $movement->reason,
                    'qty' => (float) $movement->qty,
                    'value' => $movement->value,
                ]),
        ]);
    }

    public function update(ItemRequest $request, Item $item): RedirectResponse
    {
        Gate::authorize('manage', $item);

        $data = $request->validated();

        DB::transaction(function () use ($item, $data) {
            $item->update($this->attributes($data, $item->kind));

            if ($item->kind === 'komposisi') {
                $item->recipeLines()->delete();
                $item->recipeLines()->createMany($data['recipe']);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$item->name} diperbarui."]);

        return to_route('items.index');
    }

    /**
     * Archive (soft delete). An ingredient still used by a recipe stays,
     * otherwise that recipe's HPP would silently drop. Stock on hand must
     * be counted to zero first, so Persediaan never holds value no listed item has.
     */
    public function destroy(Request $request, Item $item): RedirectResponse
    {
        Gate::authorize('manage', $item);

        $usedBy = $item->usedInRecipes()->with('product')->get()->pluck('product.name')->filter();

        if ($usedBy->isNotEmpty()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "{$item->name} masih dipakai di resep {$usedBy->join(', ')}. Hapus dari resep dulu.",
            ]);

            return back();
        }

        if ($item->hasStock() && ((float) $item->stock != 0.0 || $item->stock_value !== 0)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Stok {$item->name} masih ada. Koreksi stoknya ke 0 dulu supaya nilainya tercatat.",
            ]);

            return back();
        }

        $item->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$item->name} diarsipkan."]);

        return to_route('items.index');
    }

    /**
     * Stock count correction (C8, single item).
     */
    public function adjustStock(Request $request, Item $item, Inventory $inventory): RedirectResponse
    {
        Gate::authorize('manage', $item);

        abort_unless($item->hasStock(), 404);

        $validated = $request->validate(
            ['physical_stock' => ['required', 'numeric', 'min:0']],
            [
                'physical_stock.required' => 'Isi jumlah stok hasil hitung.',
                'physical_stock.min' => 'Stok hasil hitung tidak boleh minus.',
            ],
        );

        $movement = $inventory->adjust($item, (float) $validated['physical_stock']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $movement ? "Stok {$item->name} dikoreksi." : "Stok {$item->name} sudah sesuai.",
        ]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, string $kind): array
    {
        return [
            'name' => $data['name'],
            'aliases' => $data['aliases'] ?? [],
            'unit' => $data['unit'],
            'price' => $kind === 'bahan' ? null : $data['price'],
            'min_stock' => $kind === 'komposisi' ? null : ($data['min_stock'] ?? null),
            'show_in_store' => $kind !== 'bahan' && ($data['show_in_store'] ?? true),
            'available' => $data['available'] ?? true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Item $item): array
    {
        $stock = (float) $item->stock;
        $minStock = $item->min_stock !== null ? (float) $item->min_stock : null;

        return [
            'id' => $item->id,
            'kind' => $item->kind,
            'name' => $item->name,
            'aliases' => $item->aliases ?? [],
            'unit' => $item->unit,
            'price' => $item->price,
            'stock' => $stock,
            'stockValue' => $item->stock_value,
            'minStock' => $minStock,
            'unitCost' => $item->unitCost(),
            'marginPercent' => $item->marginPercent(),
            'showInStore' => $item->show_in_store,
            'available' => $item->available,
            'lowStock' => $item->hasStock() && $minStock !== null && $stock >= 0 && $stock < $minStock,
            'negativeStock' => $item->hasStock() && $stock < 0,
        ];
    }

    /**
     * Items that can be used as recipe ingredients.
     *
     * @return list<array<string, mixed>>
     */
    private function materials(Business $business, ?Item $except = null): array
    {
        return $business->items()
            ->whereIn('kind', ['produk', 'bahan'])
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->orderBy('name')
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unit,
                'averageCost' => round($item->averageCost(), 4),
            ])
            ->all();
    }
}
