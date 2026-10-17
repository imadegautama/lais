<?php

namespace App\Http\Controllers;

use App\Enums\ItemKind;
use App\Enums\StockReason;
use App\Http\Requests\ItemRequest;
use App\Models\Account;
use App\Models\Business;
use App\Models\Item;
use App\Models\StockMovement;
use App\Services\Inventory;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    /**
     * Product and stock list (UI-PAGES C5), searched, filtered, sorted, and paginated on the server.
     */
    public function index(Request $request): Response
    {
        $business = $request->user()->currentBusiness();

        return Inertia::render('items/index', [
            'items' => TableQuery::paginate(
                $request,
                $business->items()->getQuery()->with('recipeLines.material'),
                map: fn (Item $item) => $this->row($item),
                sorts: [
                    'nama' => 'name',
                    'harga' => fn (Builder $query, string $direction) => $query->orderByRaw('items.price is null')->orderBy('items.price', $direction),
                    'biaya' => fn (Builder $query, string $direction) => $query->orderByRaw(Item::UNIT_COST_SQL." {$direction}"),
                    'margin' => fn (Builder $query, string $direction) => $query->orderByRaw('coalesce(items.price, 0) = 0')->orderByRaw(Item::MARGIN_SQL." {$direction}"),
                    'stok' => fn (Builder $query, string $direction) => $query->orderByRaw('items.kind = ?', [ItemKind::Komposisi->value])->orderBy('items.stock', $direction),
                ],
                defaultSort: 'nama',
                filters: ['jenis' => ['kind', ItemKind::values()]],
                search: ['name', 'cast(items.aliases as char)'],
            ),
            // A closure, so a table reload with only: ['items'] skips these queries.
            'summary' => fn () => $this->summary($business),
        ]);
    }

    /**
     * Create form (C6). `?jenis=` preselects the kind.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('items/form', [
            'item' => null,
            'initialKind' => ItemKind::tryFrom((string) $request->query('jenis')) ?? ItemKind::Produk,
            'materials' => $this->materials($request->user()->currentBusiness()),
            'units' => $this->units($request->user()->currentBusiness()),
            'movements' => [],
        ]);
    }

    public function store(ItemRequest $request, Inventory $inventory): RedirectResponse
    {
        $business = $request->user()->currentBusiness();
        $data = $request->validated();

        $item = DB::transaction(function () use ($business, $data, $inventory) {
            $kind = ItemKind::from($data['kind']);
            $item = $business->items()->create(['kind' => $kind] + $this->attributes($data, $kind));

            if ($kind === ItemKind::Komposisi) {
                $item->recipeLines()->createMany($data['recipe']);
            }

            if ((float) ($data['opening_stock'] ?? 0) > 0) {
                $inventory->receive($item, (float) $data['opening_stock'], (int) $data['opening_cost'], StockReason::StokAwal, Account::OWNER_CAPITAL);
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
            'units' => $this->units($item->business),
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

            if ($item->kind === ItemKind::Komposisi) {
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
     * Totals over every item, not just the current page.
     *
     * @return array<string, mixed>
     */
    private function summary(Business $business): array
    {
        $stocked = fn () => $business->items()->stocked();
        $low = fn () => $business->items()->lowStock();
        $negative = fn () => $business->items()->negativeStock();

        return [
            'itemCount' => $business->items()->count(),
            'stockedCount' => $stocked()->count(),
            'inventoryValue' => (int) $stocked()->sum('items.stock_value'),
            'lowStockCount' => $low()->count(),
            'lowStockNames' => $low()->orderBy('items.name')->limit(2)->pluck('items.name'),
            'negativeStockCount' => $negative()->count(),
            'negativeStockNames' => $negative()->orderBy('items.name')->limit(2)->pluck('items.name'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, ItemKind $kind): array
    {
        return [
            'name' => $data['name'],
            'aliases' => $data['aliases'] ?? [],
            'unit' => $data['unit'],
            'price' => $kind->isSold() ? $data['price'] : null,
            'min_stock' => $kind->isStocked() ? ($data['min_stock'] ?? null) : null,
            'show_in_store' => $kind->isSold() && ($data['show_in_store'] ?? true),
            // PRD US-08: stocked items are sold out by their stock; only recipe products have a switch.
            'available' => $kind->isStocked() || ($data['available'] ?? true),
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
            'lowStock' => $item->isLowStock(),
            'negativeStock' => $item->isNegativeStock(),
        ];
    }

    /**
     * Units this business already uses, offered next to the common ones.
     *
     * @return list<string>
     */
    private function units(Business $business): array
    {
        return array_values($business->items()->distinct()->orderBy('items.unit')->pluck('items.unit')->all());
    }

    /**
     * Items that can be used as recipe ingredients.
     *
     * @return list<array<string, mixed>>
     */
    private function materials(Business $business, ?Item $except = null): array
    {
        return array_values($business->items()
            ->stocked()
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->orderBy('items.name')
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'kind' => $item->kind,
                'name' => $item->name,
                'unit' => $item->unit,
                'averageCost' => round($item->averageCost(), 4),
            ])
            ->all());
    }
}
