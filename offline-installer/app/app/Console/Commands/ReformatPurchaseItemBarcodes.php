<?php

namespace App\Console\Commands;

use App\Models\ProductSubcategory;
use App\Models\PurchaseInvoiceItem;
use App\Models\SaleInvoiceItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off data migration: reformats purchase_invoice_items.barcode_number
 * to {CategoryCode}-{sequence}-{SubcategoryCode} (sequence scoped per
 * category) — see PurchaseInvoiceController::generateCategoryBarcodeNumber()
 * for the generator that produces this format for every NEW item going
 * forward. This command only fixes up rows that already exist from before
 * that change, or that were categorized after the fact.
 *
 * MATCHING STRATEGY — two ways an item can be recognised as convertible,
 * tried in order:
 *
 *   (A) TEXT DECODE: the barcode_number itself is the old
 *       "{SubcategoryCode}-{digits}" shape — strip the trailing
 *       "-{digits}" and check whether what's left is a currently-known
 *       ProductSubcategory code. Doesn't depend on the item's own
 *       category_id/subcategory_id columns at all.
 *
 *   (B) FK FALLBACK: the barcode_number DIDN'T decode (most commonly
 *       because it's still in the original legacy "MJ-/MJT-{invoiceNo}-
 *       {position}" format), but the item row itself now has a
 *       subcategory_id set — e.g. someone opened an old Purchase Invoice,
 *       assigned a Category/Subcategory to its items, and saved. Saving
 *       does NOT regenerate barcode_number for an item that already has
 *       one (see PurchaseInvoiceController::createItems() — an existing
 *       barcode is always preserved, never replaced), so the item ends up
 *       correctly categorized but still carrying its old barcode text
 *       forever unless a command like this one fixes it up. This is the
 *       path invoice items go through when categorized well after they
 *       were first purchased.
 *
 * Either way, an item whose barcode_number ALREADY matches the new
 * "{anything}-{5 digits}-{anything}" three-part shape is left alone before
 * either check even runs — that's what makes this command idempotent /
 * safe to run repeatedly without re-numbering already-correct barcodes.
 *
 * Also conservative about what it touches:
 *   - Anything that doesn't decode via (A) and has no subcategory_id for
 *     (B) to use is left completely alone (e.g. genuinely uncategorized
 *     legacy items).
 *   - Any Sale Invoice item already recorded against one of the OLD
 *     barcode values (i.e. that purchased item has already been sold) has
 *     its own barcode_number copy updated to match, in the same
 *     transaction, so the purchase<->sale link by barcode_number text
 *     stays intact instead of silently breaking.
 *   - If an item's own category_id is null but subcategory_id resolves to
 *     a subcategory with a parent category, category_id is filled in too
 *     (a null column is only ever filled in, never overwritten if it
 *     already has a value).
 *   - The printed barcode SYMBOL on any label already printed for these
 *     items is UNAFFECTED by this command: it encodes the item's own row
 *     id (PurchaseInvoiceItem::getScanCodeAttribute()), not barcode_number
 *     text, so already-printed labels keep scanning correctly. Only the
 *     human-readable barcode_number text (if a label prints it) will no
 *     longer match what's stored — reprint those specific labels if that
 *     matters for your workflow.
 *
 * Usage:
 *   php artisan purchase-items:reformat-barcodes --dry-run                    (preview everything, always safe)
 *   php artisan purchase-items:reformat-barcodes --invoice=140 --dry-run      (preview just one invoice's items)
 *   php artisan purchase-items:reformat-barcodes --invoice=140                (apply to just one invoice)
 *   php artisan purchase-items:reformat-barcodes                              (apply to everything, asks to confirm)
 */
class ReformatPurchaseItemBarcodes extends Command
{
    protected $signature = 'purchase-items:reformat-barcodes
        {--dry-run : Preview the changes without writing anything}
        {--invoice= : Only process items belonging to this Purchase Invoice ID}';

    protected $description = 'Reformat purchase_invoice_items.barcode_number to {CategoryCode}-{seq}-{SubcategoryCode} for items that decode from the old format or are now categorized';

    public function handle(): int
    {
        $dryRun     = (bool) $this->option('dry-run');
        $invoiceId  = $this->option('invoice');

        $subcategoriesByCode = ProductSubcategory::with('category')
            ->get()
            ->filter(fn ($s) => !empty($s->code) && $s->category && !empty($s->category->code))
            ->keyBy(fn ($s) => $s->code);

        if ($subcategoriesByCode->isEmpty()) {
            $this->error('No product subcategories with both a code and a parent category code were found in this database — nothing to match barcodes against.');
            return self::FAILURE;
        }
        $this->line($subcategoriesByCode->count() . ' subcategory code(s) loaded to match against.');

        $query = PurchaseInvoiceItem::query()
            ->whereNotNull('barcode_number')
            ->with(['subcategory.category'])
            ->orderBy('id'); // preserve original creation/purchase order within each category

        if ($invoiceId !== null) {
            $query->where('purchase_invoice_id', $invoiceId);
            $this->line('Scoped to purchase_invoice_id = ' . $invoiceId . '.');
        }

        $allItems = $query->get();
        $this->line($allItems->count() . ' purchase_invoice_items have a barcode_number set — checking each one.');

        // New sequence is scoped per CATEGORY code (shared across every
        // subcategory under it) — running counter starts at 1 and
        // increments in original creation order (oldest item first).
        //
        // NOTE: when --invoice is used, this only counts items within that
        // scoped set, so the sequence restarts rather than continuing from
        // that category's true running count across the whole database. Do
        // a full (unscoped) dry-run first if you need the real next number
        // — --invoice is meant for previewing/applying to one invoice's
        // items in isolation, not for partial batches of a larger run.
        $nextSeqByCategoryCode = [];
        $updates          = []; // ['item' => ..., 'old' => ..., 'new' => ..., 'subcategory' => ..., 'via' => 'text'|'fk']
        $alreadyNewFormat = 0;
        $unrecognised     = [];

        foreach ($allItems as $item) {
            $barcode = trim((string) $item->barcode_number);

            // Guard FIRST, before either matching strategy: already
            // "{anything}-{5 digits}-{anything}" (our new 3-part shape) —
            // leave it alone. This is what makes the command safe to
            // re-run without re-numbering already-correct barcodes.
            if (preg_match('/^.+-\d{5}-.+$/', $barcode)) {
                $alreadyNewFormat++;
                continue;
            }

            $subcategory = null;
            $via         = null;

            // (A) TEXT DECODE — old "{SubcategoryCode}-{digits}" shape.
            if (preg_match('/^(.+)-(\d{1,6})$/', $barcode, $m)) {
                $codeGuess = $m[1];
                if ($subcategoriesByCode->has($codeGuess)) {
                    $subcategory = $subcategoriesByCode->get($codeGuess);
                    $via         = 'text';
                }
            }

            // (B) FK FALLBACK — barcode text didn't decode (typically still
            // legacy MJ-/MJT-{invoiceNo}-{position}), but the item itself
            // has since been assigned a subcategory with a resolvable
            // parent category.
            if (!$subcategory && $item->subcategory && $item->subcategory->code
                && $item->subcategory->category && $item->subcategory->category->code) {
                $subcategory = $item->subcategory;
                $via         = 'fk';
            }

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

            $updates[] = ['item' => $item, 'old' => $barcode, 'new' => $new, 'subcategory' => $subcategory, 'via' => $via];
        }

        $this->newLine();
        $this->line('Already new-format (skipped): ' . $alreadyNewFormat);
        $this->line('Matched via barcode text decode: ' . collect($updates)->where('via', 'text')->count());
        $this->line('Matched via item\'s own category/subcategory (barcode text was legacy): ' . collect($updates)->where('via', 'fk')->count());
        $this->line('Not recognised — no decodable text and no subcategory assigned (left alone): ' . count($unrecognised));
        if (!empty($unrecognised)) {
            $this->line('  Sample: ' . implode(', ', array_slice(array_unique($unrecognised), 0, 15)));
        }

        if (empty($updates)) {
            $this->info('No purchase_invoice_items need reformatting. Nothing to do.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Item ID', 'Old barcode_number', 'New barcode_number', 'Matched via'],
            collect($updates)->map(fn ($u) => [$u['item']->id, $u['old'], $u['new'], $u['via']])->toArray()
        );

        if ($dryRun) {
            $this->warn(count($updates) . ' item(s) WOULD be updated. Re-run without --dry-run to apply.');
            return self::SUCCESS;
        }

        if (!$this->confirm(
            count($updates) . ' item(s) will be updated. Any Sale Invoice item already recorded against an OLD ' .
            'barcode will be updated to the NEW one too, to keep that link intact. A null category_id on an item ' .
            'will also be filled in from its subcategory (existing values are never overwritten). Continue?'
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
                }
                if ($item->category_id === null) {
                    $fillData['category_id'] = $subcategory->category_id;
                    $fksFilledIn++;
                }

                $item->update($fillData);

                $saleItemsUpdated += SaleInvoiceItem::where('barcode_number', $u['old'])
                    ->update(['barcode_number' => $u['new']]);
            }

            $this->info(count($updates) . ' purchase item barcode(s) reformatted.');
            if ($fksFilledIn > 0) {
                $this->info($fksFilledIn . ' item(s) had a null category_id filled in from their subcategory.');
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