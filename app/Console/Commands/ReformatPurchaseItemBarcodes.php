<?php

namespace App\Console\Commands;

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
 * Deliberately conservative about what it touches:
 *   - Only rows whose barcode_number exactly matches the OLD
 *     "{SubcategoryCode}-{5 digits}" shape are candidates. Legacy
 *     MJ-/MJT-{invoiceNo}-{position} rows (items with no subcategory) and
 *     any manually-typed/custom barcode text are left completely alone —
 *     this only rewrites what it can confidently recognise as its own
 *     previous output.
 *   - Any Sale Invoice item already recorded against one of the OLD
 *     barcode values (i.e. that purchased item has already been sold) has
 *     its own barcode_number copy updated to match, in the same
 *     transaction, so the purchase<->sale link by barcode_number text
 *     stays intact instead of silently breaking.
 *   - The printed barcode SYMBOL on any label already printed for these
 *     items is UNAFFECTED by this command: it encodes the item's own row
 *     id (PurchaseInvoiceItem::getScanCodeAttribute()), not barcode_number
 *     text, so already-printed labels keep scanning correctly. Only the
 *     human-readable barcode_number text (if a label prints it) will no
 *     longer match what's stored — reprint those specific labels if that
 *     matters for your workflow.
 *
 * Usage:
 *   php artisan purchase-items:reformat-barcodes --dry-run   (preview only)
 *   php artisan purchase-items:reformat-barcodes             (asks to confirm, then applies)
 */
class ReformatPurchaseItemBarcodes extends Command
{
    protected $signature = 'purchase-items:reformat-barcodes {--dry-run : Preview the changes without writing anything}';

    protected $description = 'Reformat purchase_invoice_items.barcode_number from {SubcategoryCode}-{seq} to {CategoryCode}-{seq}-{SubcategoryCode}, sequence now scoped per category';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = PurchaseInvoiceItem::query()
            ->whereNotNull('category_id')
            ->whereNotNull('subcategory_id')
            ->whereNotNull('barcode_number')
            ->with(['category', 'subcategory'])
            ->orderBy('id') // preserve original creation/purchase order within each category
            ->get()
            ->filter(function (PurchaseInvoiceItem $item) {
                if (!$item->category || !$item->category->code) {
                    return false;
                }
                if (!$item->subcategory || !$item->subcategory->code) {
                    return false;
                }

                $oldPattern = '/^' . preg_quote($item->subcategory->code, '/') . '-\d{5}$/';
                return (bool) preg_match($oldPattern, (string) $item->barcode_number);
            });

        if ($candidates->isEmpty()) {
            $this->info('No purchase_invoice_items match the old {SubcategoryCode}-{seq} format. Nothing to do.');
            return self::SUCCESS;
        }

        // New sequence is scoped per CATEGORY (shared across every
        // subcategory under it) — running counter starts at 1 and
        // increments in original creation order (oldest item first).
        $nextSeqByCategory = [];
        $updates = [];

        foreach ($candidates as $item) {
            $categoryId = $item->category_id;
            $nextSeqByCategory[$categoryId] = ($nextSeqByCategory[$categoryId] ?? 0) + 1;
            $seq = $nextSeqByCategory[$categoryId];

            $new = $item->category->code . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT) . '-' . $item->subcategory->code;

            if ($new === $item->barcode_number) {
                continue; // already correct — skip
            }

            $updates[] = ['item' => $item, 'old' => $item->barcode_number, 'new' => $new];
        }

        if (empty($updates)) {
            $this->info('All matching items already use the new format. Nothing to do.');
            return self::SUCCESS;
        }

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
            'barcode will be updated to the NEW one too, to keep that link intact. Continue?'
        )) {
            $this->info('Cancelled — nothing was changed.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($updates) {
            $saleItemsUpdated = 0;

            foreach ($updates as $u) {
                $u['item']->update(['barcode_number' => $u['new']]);

                $saleItemsUpdated += SaleInvoiceItem::where('barcode_number', $u['old'])
                    ->update(['barcode_number' => $u['new']]);
            }

            $this->info(count($updates) . ' purchase item barcode(s) reformatted.');
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
