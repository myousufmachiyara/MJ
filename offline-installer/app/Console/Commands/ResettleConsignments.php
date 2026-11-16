<?php

namespace App\Console\Commands;

use App\Http\Controllers\ConsignmentController;
use App\Models\SaleInvoice;
use Illuminate\Console\Command;

/**
 * One-off catch-up: re-runs consignment settlement for sale invoices that were
 * saved before settlement worked. Safe to run repeatedly.
 *
 *   php artisan consignments:resettle            (all invoices linked to a consignment)
 *   php artisan consignments:resettle SAL-00882  (one invoice)
 */
class ResettleConsignments extends Command
{
    protected $signature   = 'consignments:resettle {invoice_no? : Only this invoice number}';
    protected $description = 'Mark consignment items as sold for sale invoices linked to a consignment';

    public function handle(): int
    {
        $q = SaleInvoice::with('items');
        $no = $this->argument('invoice_no');
        $no ? $q->where('invoice_no', $no) : $q->whereNotNull('consignment_id');

        $n = 0;
        foreach ($q->get() as $invoice) {
            ConsignmentController::settleItems($invoice);
            $n++;
            $this->line("Processed {$invoice->invoice_no}");
        }
        $this->info("Done — {$n} invoice(s) processed.");
        return self::SUCCESS;
    }
}
