<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoiceItem;
use App\Models\SaleInvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FEATURE (bulk Selling Price update): a dedicated, invoice-agnostic screen
 * listing every purchased item that hasn't been sold yet, so Selling Price
 * can be set/updated for many items in one place — searchable by barcode /
 * item code / item name / certificate no — instead of having to open each
 * Purchase Invoice individually to reach its items.
 *
 * Deliberately a brand-new, standalone controller rather than adding to
 * PurchaseInvoiceController: nothing here touches invoice master data,
 * items/parts recreation, totals, or accounting — it only ever writes the
 * single `selling_price` column on already-persisted PurchaseInvoiceItem
 * rows, exactly like PurchaseInvoiceController::updateSellingPrice() (the
 * per-row quick-save endpoint this screen's individual "Save" button
 * reuses as-is — see routes/web.php). bulkUpdate() below is the same idea,
 * just accepting many rows in one request instead of one.
 *
 * "Not yet sold" uses the exact same definition SaleInvoiceController::
 * posScan() already uses for its own "already sold" guard: a
 * PurchaseInvoiceItem is sold once a SaleInvoiceItem exists with the same
 * barcode_number (this app's only purchase<->sale link — see
 * ReformatPurchaseItemBarcodes's docblock for background). No new column
 * or stock table is introduced.
 */
class SellingPriceController extends Controller
{
    /**
     * List every unsold purchased item, optionally filtered by a single
     * search box matching Barcode/Item Code, Item Name, or Certificate No.
     * Kept as one plain list (no server-side pagination) — the same
     * approach purchase.index/sale.index already use — with DataTables
     * handling client-side search/sort/pagination, consistent with the
     * rest of the app.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->get('q', ''));

        $items = PurchaseInvoiceItem::query()
            ->whereNotNull('barcode_number')
            // "Not sold yet" — identical definition to SaleInvoiceController::
            // posScan()'s already-sold guard: no SaleInvoiceItem exists with
            // this same barcode_number. whereNotExists (rather than
            // whereNotIn + a full pluck of every sold barcode) keeps this a
            // single indexed correlated subquery regardless of how many
            // items have ever been sold.
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('sale_invoice_items')
                    ->whereColumn('sale_invoice_items.barcode_number', 'purchase_invoice_items.barcode_number');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('barcode_number', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhere('certificate_no', 'like', "%{$search}%")
                        ->orWhere('tray_no', 'like', "%{$search}%");
                });
            })
            ->with([
                'purchaseInvoice:id,invoice_no,invoice_date',
                'category:id,name,code',
                'subcategory:id,name,code',
            ])
            ->orderByDesc('id')
            ->get();

        return view('selling-price.index', compact('items', 'search'));
    }

    /**
     * Bulk-saves selling_price for many items in one request — one UPDATE
     * per changed row, all in a single transaction, nothing else on the
     * invoice/item touched (no totals recalculation, no accounting, no
     * barcode regeneration — selling_price has no relationship to any of
     * those, same as updateSellingPrice()'s reasoning).
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.id'             => 'required|integer|exists:purchase_invoice_items,id',
            'items.*.selling_price'  => 'nullable|numeric|min:0',
        ]);

        // FEATURE (bulk Selling Price update): guard against saving a price
        // onto an item that got sold by someone else between this screen
        // loading and this save (e.g. sold from POS in another tab) — same
        // "already sold" definition as index()/posScan().
        $ids = collect($validated['items'])->pluck('id')->all();
        $alreadySoldBarcodes = PurchaseInvoiceItem::whereIn('id', $ids)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('sale_invoice_items')
                    ->whereColumn('sale_invoice_items.barcode_number', 'purchase_invoice_items.barcode_number');
            })
            ->pluck('barcode_number', 'id');

        $updated = 0;
        $skipped = [];

        DB::transaction(function () use ($validated, $alreadySoldBarcodes, &$updated, &$skipped) {
            foreach ($validated['items'] as $row) {
                if ($alreadySoldBarcodes->has($row['id'])) {
                    $skipped[] = $alreadySoldBarcodes->get($row['id']);
                    continue;
                }

                $sellingPrice = ($row['selling_price'] !== null && $row['selling_price'] !== '')
                    ? round((float) $row['selling_price'], 2)
                    : null;

                PurchaseInvoiceItem::whereKey($row['id'])->update(['selling_price' => $sellingPrice]);
                $updated++;
            }
        });

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'skipped' => $skipped, // barcode_number(s) skipped because already sold
        ]);
    }

    /**
     * FEATURE (export selected items): CSV export of just the checked rows
     * on the Bulk Selling Price screen — Excel opens a CSV natively, so
     * this follows PurchaseInvoiceController::downloadTemplate()'s existing
     * streamDownload()+fputcsv() pattern instead of pulling in a new
     * xlsx-writing dependency for something Excel already reads fine.
     *
     * Exactly the 5 fields asked for, read straight off the purchased item
     * (and its parts, for the diamond ct total) — nothing calculated or
     * guessed:
     *   - Gold Weight  → gross_weight (the same column the Purchase Invoice
     *     create/edit screens themselves label "Gold Gross Wt")
     *   - Diamond (ct) → diamond_total_ct accessor (sum of this item's
     *     parts' qty — see PurchaseInvoiceItem::getDiamondTotalCtAttribute())
     *   - Selling Price → selling_price
     *   - Certificate No → certificate_no
     *   - Barcode Number → barcode_number
     * Barcode Number, Item Name and Certificate No are included as the
     * first three columns purely so each row is identifiable in the sheet;
     * Item Name wasn't explicitly asked for but costs nothing to include
     * and every other export/template in this app leads with an
     * identifying column the same way.
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'item_ids'   => 'required|array|min:1',
            'item_ids.*' => 'integer|exists:purchase_invoice_items,id',
        ]);

        $items = PurchaseInvoiceItem::whereIn('id', $validated['item_ids'])
            ->with('parts') // diamond_total_ct sums over this
            ->orderBy('id')
            ->get();

        $filename = 'selling_price_export_' . now()->format('Y-m-d_His') . '.csv';

        $rows = [
            ['Barcode Number', 'Item Name', 'Certificate No', 'Gold Weight (gms)', 'Diamond (ct)', 'Selling Price'],
        ];

        foreach ($items as $item) {
            $rows[] = [
                $item->barcode_number,
                $item->item_name,
                $item->certificate_no,
                number_format((float) $item->gross_weight, 3, '.', ''),
                number_format((float) $item->diamond_total_ct, 3, '.', ''),
                $item->selling_price !== null ? number_format((float) $item->selling_price, 2, '.', '') : '',
            ];
        }

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
