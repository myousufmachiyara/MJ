<?php

namespace App\Console\Commands;

use App\Models\ProductSubcategory;
use App\Models\PurchaseInvoiceItem;
use App\Models\SaleInvoiceItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off data migration: reformats purchase_invoice_items.barcode_number
 * from the old {SubcategoryCode}-{sequence} shape (sequence scoped per
 * subcategory) to the new {CategoryCode}-{sequence}-{SubcategoryCode} shape
 * (sequence scoped per category) — see PurchaseInvoiceController::
 * generateCategoryBarcodeNumber() for the generator that now produces this
 * format for every NEW item going forward. This command only fixes up rows
 * that already exist from before that change.
 *
 * MATCHING STRATEGY: a first version of this command matched candidates via
 * the item's own category_id/subcategory_id columns. That undercounted (or
 * missed entirely) on data where those FK columns aren't reliably populated
 * on older rows — those columns were added in a LATER migration than the
 * subcategory-based barcode format itself, so plenty of items can carry a
 * perfectly good "{SubcategoryCode}-{seq}" barcode with a null
 * subcategory_id/category_id. This version instead decodes the
 * barcode_number TEXT directly: it strips the trailing "-{digits}" and
 * checks whether what's left is a currently-known ProductSubcategory code.
 * This only depends on the subcategory still existing with the same code —
 * not on the item's own FK columns — and is naturally safe to re-run
 * (already-reformatted barcodes end in a subcategory CODE, not digits, so
 * they never match the "ends in digits" check below and are left alone).
 *
 * Also conservative about what it touches:
 *   - Legacy MJ-/MJT-{invoiceNo}-{position} rows and any manually-typed/
 *     custom barcode text that doesn't decode to a known subcategory code
 *     are left completely alone.
 *   - Any Sale Invoice item already recorded against one of the OLD
 *     barcode values (i.e. that purchased item has already been sold) has
 *     its own barcode_number copy updated to match, in the same
 *     transaction, so the purchase<->sale link by barcode_number text
 *     stays intact instead of silently breaking.
 *   - If an item's own category_id/subcategory_id is null, this also fills
 *     it in from the subcategory the barcode decoded to (a null column is
 *     only ever filled in, never overwritten if it already has a value).
 *   - The printed barcode SYMBOL on any label already printed for these
 *     items is UNAFFECTED by this command: it encodes the item's own row
 *     id (PurchaseInvoiceItem::getScanCodeAttribute()), not barcode_number
 *     text, so already-printed labels keep scanning correctly. Only the
 *     human-readable barcode_number text (if a label prints it) will no
 *     longer match what's stored — reprint those specific labels if that
 *     matters for your workflow.
 *
 * Usage:
 *   php artisan purchase-items:reformat-barcodes --dry-run   (preview only, always safe)
 *   php artisan purchase-items:reformat-barcodes             (asks to confirm, then applies)
 */
class ReformatPurchaseItemBarcodes extends Command
{
    protected $signature = 'purchase-items:reformat-barcodes {--dry-run : Preview the changes without writing anything}';

    protected $description = 'Reformat purchase_invoice_items.barcode_number from {SubcategoryCode}-{seq} to {CategoryCode}-{seq}-{SubcategoryCode}, sequence now scoped per category';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Every currently-defined subcategory code (with a resolvable
        // parent category code), used to recognise the OLD
        // "{SubcategoryCode}-{digits}" shape straight from the
        // barcode_number TEXT. Keyed by code for O(1) lookups below.
        $subcategoriesByCode = ProductSubcategory::with('category')
            ->get()
            ->filter(fn ($s) => !empty($s->code) && $s->category && !empty($s->category->code))
            ->keyBy(fn ($s) => $s->code);

        if ($subcategoriesByCode->isEmpty()) {
            $this->error('No product subcategories with both a code and a parent category code were found in this database — nothing to match barcodes against.');
            return self::FAILURE;
        }
        $this->line($subcategoriesByCode->count() . ' subcategory code(s) loaded to match against.');

        $allItems = PurchaseInvoiceItem::query()
            ->whereNotNull('barcode_number')
            ->orderBy('id') // preserve original creation/purchase order within each category
            ->get();

        $this->line($allItems->count() . ' purchase_invoice_items have a barcode_number set — scanning each one\'s TEXT against those codes.');

        // New sequence is scoped per CATEGORY code (shared across every
        // subcategory under it) — running counter starts at 1 and
        // increments in original creation order (oldest item first).
        $nextSeqByCategoryCode = [];
        $updates          = []; // ['item' => ..., 'old' => ..., 'new' => ..., 'subcategory' => ...]
        $alreadyNewFormat = 0;
        $unrecognised     = [];

        foreach ($allItems as $item) {
            $barcode = trim((string) $item->barcode_number);

            // Must end in "-{1 to 6 digits}" to even be a candidate — this
            // is what naturally excludes already-reformatted barcodes
            // (which end in a subcategory CODE, not digits) and most
            // legacy MJ-/MJT- rows (whose "{something}" before the digits
            // won't match a real subcategory code below).
            if (!preg_match('/^(.+)-(\d{1,6})$/', $barcode, $m)) {
                if (preg_match('/-[A-Za-z]/', $barcode)) {
                    $alreadyNewFormat++; // ends in letters — plausibly already new-format, don't flag as unrecognised noise
                } else {
                    $unrecognised[] = $barcode;
                }
                continue;
            }

            $codeGuess   = $m[1];
            $subcategory = $subcategoriesByCode->get($codeGuess);

            if (!$subcategory) {
                $unrecognised[] = $barcode;
                continue;
            }

            $categoryCode = $subcategory->category->code;

            $nextSeqByCategoryCode[$categoryCode] = ($nextSeqByCategoryCode[$categoryCode] ?? 0) + 1;
            $seq = $nextSeqByCategoryCode[$categoryCode];

            $new = $categoryCode . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT) . '-' . $subcategory->code;

            if ($new === $barcode) {
                continue; // already correct — skip
            }

            $updates[] = ['item' => $item, 'old' => $barcode, 'new' => $new, 'subcategory' => $subcategory];
        }

        $this->newLine();
        $this->line('Already new-format (skipped): ' . $alreadyNewFormat);
        $this->line('Not recognised as any known subcategory code (left alone): ' . count($unrecognised));
        if (!empty($unrecognised)) {
            $this->line('  Sample: ' . implode(', ', array_slice(array_unique($unrecognised), 0, 15)));
        }

        if (empty($updates)) {
            $this->info('No purchase_invoice_items need reformatting. Nothing to do.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Item ID', 'Old barcode_number', 'New barcode_number'],
            collect($updates)->map(fn ($u) => [$u['item']->id, $u['old'], $u['new']])->toArray()
        );

        if ($dryRun) {
            $this->warn(count($updates) . ' item(s) WOULD be updated. Re-run without --dry-run to apply.');
            return self::SUCCESS;
        }

        if (!$this->confirm(
            count($updates) . ' item(s) will be updated. Any Sale Invoice item already recorded against an OLD ' .
            'barcode will be updated to the NEW one too, to keep that link intact. A null category_id/subcategory_id ' .
            'on an item will also be filled in from the match (existing values are never overwritten). Continue?'
        )) {
            $this->info('Cancelled — nothing was changed.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($updates) {
            $saleItemsUpdated = 0;
            $fksFilledIn      = 0;

            foreach ($updates as $u) {
                /** @var PurchaseInvoiceItem $item */
                $item        = $u['item'];
                $subcategory = $u['subcategory'];

                $fillData = ['barcode_number' => $u['new']];
                if ($item->subcategory_id === null) {
                    $fillData['subcategory_id'] = $subcategory->id;
                    $fksFilledIn++;
                }
                if ($item->category_id === null) {
                    $fillData['category_id'] = $subcategory->category_id;
                }

                $item->update($fillData);

                $saleItemsUpdated += SaleInvoiceItem::where('barcode_number', $u['old'])
                    ->update(['barcode_number' => $u['new']]);
            }

            $this->info(count($updates) . ' purchase item barcode(s) reformatted.');
            if ($fksFilledIn > 0) {
                $this->info($fksFilledIn . ' item(s) had a null category_id/subcategory_id filled in from the match.');
            }
            if ($saleItemsUpdated > 0) {
                $this->info($saleItemsUpdated . ' matching Sale Invoice item(s) updated to keep the barcode link intact.');
            }
        });

        $this->newLine();
        $this->comment(
            "Note: only the barcode_number TEXT changed. The printed barcode SYMBOL on any label already printed for " .
            "these items encodes the item's own row id, not this text, so already-printed labels still scan correctly. " .
            "Reprint a label only if you need its visible barcode_number text to match the new value."
        );

        return self::SUCCESS;
    }
}