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
     * Bulk-saves selling_price (AED) and/or selling_price_usd for many
     * items in one request — one UPDATE per changed row, all in a single
     * transaction, nothing else on the invoice/item touched (no totals
     * recalculation, no accounting, no barcode regeneration — neither
     * price column has any relationship to those, same reasoning as
     * PurchaseInvoiceController::updateSellingPrice()).
     *
     * Also doubles as the single-row save endpoint: the index view's
     * per-row "Save" button POSTs a one-item array here instead of calling
     * PurchaseInvoiceController's older AED-only quick-save endpoint, so
     * this whole screen (both currencies) is self-contained in this one
     * controller and that older endpoint/the Purchase Invoice edit screen
     * it serves are untouched.
     *
     * FIX (bulk Selling Price import, AED + USD): each of the two price
     * fields is independently optional PER ROW — a row can update just one
     * of them (send only `selling_price` or only `selling_price_usd` for
     * that id) without touching the other, so the "only this one currency
     * changed" case from the inline table doesn't blow away whichever
     * price wasn't edited.
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'items'                      => 'required|array|min:1',
            'items.*.id'                 => 'required|integer|exists:purchase_invoice_items,id',
            'items.*.selling_price'      => 'nullable|numeric|min:0',
            'items.*.selling_price_usd'  => 'nullable|numeric|min:0',
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

                $fill = [];
                if (array_key_exists('selling_price', $row)) {
                    $fill['selling_price'] = ($row['selling_price'] !== null && $row['selling_price'] !== '')
                        ? round((float) $row['selling_price'], 2)
                        : null;
                }
                if (array_key_exists('selling_price_usd', $row)) {
                    $fill['selling_price_usd'] = ($row['selling_price_usd'] !== null && $row['selling_price_usd'] !== '')
                        ? round((float) $row['selling_price_usd'], 2)
                        : null;
                }

                if (empty($fill)) {
                    continue;
                }

                PurchaseInvoiceItem::whereKey($row['id'])->update($fill);
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
     * FEATURE (bulk Selling Price import, AED + USD): CSV upload that sets
     * selling_price/selling_price_usd for many items in one go, matched by
     * Barcode Number — the counterpart to export() below, so the round
     * trip is: Export Selected → edit the price column(s) in Excel →
     * Import that same file back.
     *
     * Deliberately server-side CSV parsing (fgetcsv), not a client-side
     * SheetJS import like the Purchase Invoice create/edit screens use —
     * this is a plain file upload handled once on submit, not a live
     * multi-row form the user keeps editing in the browser before saving,
     * so there's no benefit to parsing it in JS first.
     *
     * Header matching is case-insensitive and only requires a Barcode
     * column — every other column (Item Name, Certificate No, Gold Weight,
     * Diamond ct, and either price column) is optional and matched by a
     * few accepted spellings, since a user will often re-upload exactly
     * what export() produced, or a spreadsheet they've renamed columns in.
     *
     * A row's price column is EMPTY (not missing the column entirely, this
     * row specifically has a blank cell) → that row's existing value for
     * that currency is left untouched, not cleared. Only a cell with an
     * actual number overwrites. This matters because the normal use case is
     * a sparsely-filled sheet — e.g. AED filled in for every row but USD
     * known for only some — and blank cells shouldn't wipe out prices that
     * were never meant to be touched by this particular import.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if ($handle === false) {
            return back()->with('error', 'Could not read the uploaded file.');
        }

        $headerRow = fgetcsv($handle);
        if ($headerRow === false) {
            fclose($handle);
            return back()->with('error', 'The uploaded file is empty.');
        }

        $columnIndex = $this->matchImportColumns($headerRow);

        if ($columnIndex['barcode'] === null) {
            fclose($handle);
            return back()->with('error', 'No "Barcode Number" column found in the uploaded file — it must have a column named Barcode, Barcode Number, Item Code, or SKU.');
        }

        $updated       = 0;
        $notFound      = [];
        $alreadySold   = [];
        $noPriceGiven  = [];

        DB::transaction(function () use ($handle, $columnIndex, &$updated, &$notFound, &$alreadySold, &$noPriceGiven) {
            while (($row = fgetcsv($handle)) !== false) {
                $barcode = isset($row[$columnIndex['barcode']]) ? trim((string) $row[$columnIndex['barcode']]) : '';
                if ($barcode === '') {
                    continue; // blank row
                }

                $aedRaw = $columnIndex['aed'] !== null ? trim((string) ($row[$columnIndex['aed']] ?? '')) : '';
                $usdRaw = $columnIndex['usd'] !== null ? trim((string) ($row[$columnIndex['usd']] ?? '')) : '';

                if ($aedRaw === '' && $usdRaw === '') {
                    $noPriceGiven[] = $barcode;
                    continue; // nothing to update for this row
                }

                $item = PurchaseInvoiceItem::where('barcode_number', $barcode)->latest()->first();
                if (!$item) {
                    $notFound[] = $barcode;
                    continue;
                }

                $isSold = DB::table('sale_invoice_items')->where('barcode_number', $item->barcode_number)->exists();
                if ($isSold) {
                    $alreadySold[] = $barcode;
                    continue;
                }

                $fill = [];
                if ($aedRaw !== '' && is_numeric($aedRaw)) {
                    $fill['selling_price'] = round((float) $aedRaw, 2);
                }
                if ($usdRaw !== '' && is_numeric($usdRaw)) {
                    $fill['selling_price_usd'] = round((float) $usdRaw, 2);
                }

                if (!empty($fill)) {
                    $item->update($fill);
                    $updated++;
                }
            }
        });

        fclose($handle);

        $messageParts = [$updated . ' item(s) updated.'];
        if (!empty($notFound)) {
            $messageParts[] = count($notFound) . ' barcode(s) not found: ' . implode(', ', array_slice($notFound, 0, 20)) . (count($notFound) > 20 ? ', ...' : '');
        }
        if (!empty($alreadySold)) {
            $messageParts[] = count($alreadySold) . ' item(s) skipped — already sold: ' . implode(', ', array_slice($alreadySold, 0, 20)) . (count($alreadySold) > 20 ? ', ...' : '');
        }

        return redirect()
            ->route('selling_price.index')
            ->with($updated > 0 ? 'success' : 'error', implode(' ', $messageParts));
    }

    /**
     * Flexible, case-insensitive header matcher for import(). Returns the
     * column index (or null if not present) for the barcode, AED price and
     * USD price columns, matching whichever of these header spellings is
     * present — deliberately accepting export()'s own header text plus a
     * few common alternates, so a re-uploaded export and a hand-built
     * sheet both work.
     */
    private function matchImportColumns(array $headerRow): array
    {
        $normalized = array_map(fn ($h) => strtolower(trim((string) $h)), $headerRow);

        $find = function (array $candidates) use ($normalized) {
            foreach ($candidates as $candidate) {
                $index = array_search($candidate, $normalized, true);
                if ($index !== false) {
                    return $index;
                }
            }
            return null;
        };

        return [
            'barcode' => $find(['barcode number', 'barcode', 'barcode_number', 'item code', 'sku']),
            'aed'     => $find(['selling price (aed)', 'selling price', 'aed', 'price aed', 'selling_price']),
            'usd'     => $find(['selling price (usd)', 'usd', 'price usd', 'selling_price_usd']),
        ];
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
     *   - Selling Price (AED) → selling_price
     *   - Selling Price (USD) → selling_price_usd — FEATURE (bulk Selling
     *     Price import, AED + USD): added so the Export → edit → Import
     *     round trip covers both currencies; column header text matches
     *     what import()'s matchImportColumns() looks for, so a re-uploaded
     *     export is recognized automatically.
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
            ['Barcode Number', 'Item Name', 'Certificate No', 'Gold Weight (gms)', 'Diamond (ct)', 'Selling Price (AED)', 'Selling Price (USD)'],
        ];

        foreach ($items as $item) {
            $rows[] = [
                $item->barcode_number,
                $item->item_name,
                $item->certificate_no,
                number_format((float) $item->gross_weight, 3, '.', ''),
                number_format((float) $item->diamond_total_ct, 3, '.', ''),
                $item->selling_price !== null ? number_format((float) $item->selling_price, 2, '.', '') : '',
                $item->selling_price_usd !== null ? number_format((float) $item->selling_price_usd, 2, '.', '') : '',
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
