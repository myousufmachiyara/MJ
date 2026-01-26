<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Product;
use App\Models\MeasurementUnit;
use App\Models\ChartOfAccounts; // assuming vendors are COA entries
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\myPDF;
use Carbon\Carbon;

class PurchaseInvoiceController extends Controller
{
    public function index()
    {
        $invoices = PurchaseInvoice::with('vendor','attachments')->get();
        return view('purchase.index', compact('invoices'));
    }

    public function create()
    {
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $banks = ChartOfAccounts::where('account_type', 'bank')->get();
        $products = Product::with('measurementUnit')->get();
        return view('purchase.create', compact('products', 'vendors', 'banks'));
    }

    public function store(Request $request)
    {
        if ($request->payment_method !== 'cheque') {
            $request->merge([
                'bank_name'     => null,
                'cheque_no'     => null,
                'cheque_date'   => null,
                'cheque_amount' => null,
            ]);
        }
        
        $request->validate([
            // Header
            'vendor_id'      => 'required|exists:chart_of_accounts,id',
            'invoice_date'   => 'required|date',
            'currency'       => 'required|in:AED,USD',
            'exchange_rate'  => 'nullable|required_if:currency,USD|numeric|min:0',
            'net_amount'     => 'required|numeric|min:0',
            'payment_method' => 'required|in:credit,cash,cheque,material+making cost',
            'payment_term'   => 'nullable|string',

            // ADDED: Validation for the missing rates
            'gold_rate_aed'  => 'nullable|numeric|min:0',
            'gold_rate_usd'  => 'nullable|numeric|min:0',
            'diamond_rate_aed' => 'nullable|numeric|min:0',
            'diamond_rate_usd' => 'nullable|numeric|min:0',

            // Cheque
            'bank_name'      => 'nullable|required_if:payment_method,cheque|exists:chart_of_accounts,id',
            'cheque_no'      => 'nullable|required_if:payment_method,cheque|string',
            'cheque_date'    => 'nullable|required_if:payment_method,cheque|date',
            'cheque_amount'  => 'nullable|required_if:payment_method,cheque|numeric|min:0',

            // Items
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'nullable|string|required_without:items.*.product_id',
            'items.*.product_id' => 'nullable|exists:products,id|required_without:items.*.item_name',
            'items.*.gross_weight' => 'required|numeric|min:0',
            'items.*.purity' => 'required|numeric|min:0|max:1',
            'items.*.making_rate' => 'required|numeric|min:0',
            'items.*.material_type' => 'required|in:gold,diamond',
            'items.*.vat_percent' => 'required|numeric|min:0',

            // Parts
            'items.*.parts' => 'nullable|array',
            'items.*.parts.*.product_id' => 'required|exists:products,id',
            'items.*.parts.*.qty' => 'required|numeric|min:0',
            'items.*.parts.*.rate' => 'required|numeric|min:0',
            'items.*.parts.*.stone_qty' => 'nullable|numeric|min:0',
            'items.*.parts.*.stone_rate' => 'nullable|numeric|min:0',
            'items.*.parts.*.part_description' => 'nullable|string',

            'material_given_by'  => 'nullable|required_if:payment_method,material+making cost|string',
            'material_received_by'  => 'nullable|required_if:payment_method,material+making cost|string',
        ]);

        try {
            DB::beginTransaction();

            // 1. Generate Invoice Number
            $lastInvoice = PurchaseInvoice::withTrashed()->orderBy('id', 'desc')->first();
            $nextNumber = $lastInvoice ? intval($lastInvoice->invoice_no) + 1 : 1;
            $invoiceNo = str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            $netAmountAed = $request->currency === 'USD' ? round($request->net_amount * $request->exchange_rate, 2) : $request->net_amount;

            // 2. Create Invoice
            $invoice = PurchaseInvoice::create([
                'invoice_no'      => $invoiceNo,
                'vendor_id'       => $request->vendor_id,
                'invoice_date'    => $request->invoice_date,
                'remarks'         => $request->remarks,
                'currency'        => $request->currency,
                'exchange_rate'   => $request->exchange_rate,
                
                // ADDED: These lines ensure the rates are saved to the database
                'gold_rate_aed'   => $request->gold_rate_aed,
                'gold_rate_usd'   => $request->gold_rate_usd,
                'diamond_rate_aed'  => $request->diamond_rate_aed,
                'diamond_rate_usd'  => $request->diamond_rate_usd,

                'net_amount'      => $request->net_amount,
                'net_amount_aed'  => $netAmountAed,
                'payment_method'  => $request->payment_method,
                'payment_term'    => $request->payment_term ?? null,
                'bank_name'       => $request->bank_name,
                'cheque_no'       => $request->cheque_no,
                'cheque_date'     => $request->cheque_date,
                'cheque_amount'   => $request->cheque_amount,
                'material_weight' => $request->material_weight,
                'material_purity' => $request->material_purity,
                'material_value'  => $request->material_value,
                'material_received_by'  => $request->material_received_by,
                'material_given_by'  => $request->material_given_by,
                'making_charges'  => $request->making_charges,
                'created_by'      => auth()->id(),
            ]);

            // 3. Create Items and their Parts
            foreach ($request->items as $itemData) {
                $purityWeight = $itemData['gross_weight'] * $itemData['purity'];
                $col995       = $purityWeight / 0.995;
                $makingValue  = $itemData['gross_weight'] * $itemData['making_rate'];

                $materialValue = $purityWeight * ($itemData['making_rate'] ?? 0);
                $taxable    = $makingValue;
                $vatAmount  = $taxable * ($itemData['vat_percent'] / 100);
                $itemTotal  = $taxable + $vatAmount;

                $invoiceItem = $invoice->items()->create([
                    'item_name'        => $itemData['item_name'] ?? null,
                    'product_id'       => $itemData['product_id'] ?? null,
                    'variation_id'     => $itemData['variation_id'] ?? null,
                    'item_description' => $itemData['item_description'] ?? null,
                    'gross_weight'     => $itemData['gross_weight'],
                    'purity'           => $itemData['purity'],
                    'purity_weight'    => $purityWeight,
                    'col_995'          => $col995,
                    'making_rate'      => $itemData['making_rate'],
                    'making_value'     => $makingValue,
                    'material_type'    => $itemData['material_type'],
                    'material_value'   => $materialValue,
                    'taxable_amount'   => $taxable,
                    'vat_percent'      => $itemData['vat_percent'],
                    'vat_amount'       => $vatAmount,
                    'item_total'       => $itemTotal,
                ]);

                if (!empty($itemData['parts'])) {
                    foreach ($itemData['parts'] as $partData) {
                        $partTotal = ($partData['qty'] * $partData['rate']) + ($partData['stone'] ?? 0);
                        $invoiceItem->parts()->create([
                            'product_id'   => $partData['product_id'],
                            'variation_id' => $partData['variation_id'] ?? null,
                            'qty'          => $partData['qty'],
                            'rate'         => $partData['rate'],
                            'stone_qty'    => $partData['stone_qty'] ?? 0,
                            'stone_rate'   => $partData['stone_rate'] ?? 0,
                            'total'        => $partTotal,
                            'part_description' => $partData['part_description'] ?? null,
                        ]);
                    }
                }
            }

            // 4. Attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('purchase_invoices', 'public');
                    $invoice->attachments()->create(['file_path' => $path]);
                }
            }

            DB::commit();
            return redirect()->route('purchase_invoices.index')->with('success', 'Invoice saved');

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error($e);
            return back()->withInput()->with('error', 'Failed to save invoice: ' . $e->getMessage());
        }
    }

    public function print($id)
    {
        $invoice = PurchaseInvoice::with([
            'vendor',
            'items',
            'items.product.measurementUnit',
            'items.parts.product.measurementUnit',
            'items.parts.variation.attributeValues.attribute'
        ])->findOrFail($id);

        $pdf = new MyPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetTitle('PUR-' . $invoice->invoice_no);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->setCellPadding(1.2);

        /* ================= HEADER ================= */
        $logoPath = public_path('assets/img/mj-logo.jpeg');
        $logoHtml = file_exists($logoPath) ? '<img src="'.$logoPath.'" width="85">' : '';
        $pdf->writeHTML('
            <table width="100%" cellpadding="3">
                <tr>
                    <td width="40%">'.$logoHtml.'</td>
                    <td width="60%" style="text-align:right;font-size:10px;">
                        <strong>MUSFIRA JEWELRY L.L.C</strong><br>
                        Suite #M04, Mezzanine floor, Al Buteen 2 Building, Gold Souq. Gate no.1, Deira, Dubai<br>
                        TRN No: 104902647700003
                    </td>
                </tr>
            </table><hr>', true, false, false, false);

        /* ================= TITLE & VENDOR INFO ================= */
        $pdf->SetFont('helvetica','B',11);
        $pdf->Cell(0,6,'TAX INVOICE (PURCHASE)',0,1,'C');
        $pdf->Ln(2);
        $pdf->SetFont('helvetica','',9);

        $vendorHtml = '
        <table cellpadding="3" width="100%">
            <tr>
                <td width="50%">
                    <b>To:</b><br>
                    '.($invoice->vendor->name ?? '-').'<br>
                    '.($invoice->vendor->address ?? '-').'<br>
                    Contact: '.($invoice->vendor->contact_no ?? '-').'<br>
                    TRN: '.($invoice->vendor->trn ?? '-').'
                </td>
                <td width="50%">
                    <table border="1" cellpadding="3" width="100%">
                        <tr><td width="45%"><b>Date</b></td><td width="55%">'.\Carbon\Carbon::parse($invoice->invoice_date)->format('d.m.Y').'</td></tr>
                        <tr><td><b>Invoice No</b></td><td>'.$invoice->invoice_no.'</td></tr>
                        <tr>
                            <td><b>Gold Rate ('.$invoice->currency.')</b></td>
                            <td>'.number_format($invoice->currency === 'USD' ? $invoice->gold_rate_usd : $invoice->gold_rate_aed, 2).'</td>
                        </tr>
                        <tr>
                            <td><b>Diamond Rate ('.$invoice->currency.')</b></td>
                            <td>'.number_format($invoice->currency === 'USD' ? $invoice->diamond_rate_usd : $invoice->diamond_rate_aed, 2).'</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';
        $pdf->writeHTML($vendorHtml, true, false, false, false);

        /* ================= ITEMS TABLE ================= */
        $html = '
        <table border="1" cellpadding="3" width="100%" style="font-size:8px;">
            <thead>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="3%" rowspan="2">#</th>
                    <th width="10%" rowspan="2">Item Name</th>
                    <th width="10%" rowspan="2">Description</th>
                    <th width="7%" rowspan="2">Gross Wt</th>
                    <th width="6%" rowspan="2">Purity</th>
                    <th width="7%" rowspan="2">Purity Wt</th>
                    <th width="6%" rowspan="2">995</th>
                    <th width="14%" colspan="2">Making</th>
                    <th width="8%" rowspan="2">Material</th>
                    <th width="7%" rowspan="2">Material Val</th>
                    <th width="8%" rowspan="2">Taxable</th>
                    <th width="6%" rowspan="2">VAT %</th>
                    <th width="8%" rowspan="2">Gross Total</th>
                </tr>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="7%">Rate</th>
                    <th width="7%">Value</th>
                </tr>
            </thead>
            <tbody>';

        $runningTaxable = 0; $runningVat = 0;
        foreach ($invoice->items as $index => $item) {
            $taxable = $item->taxable_amount;
            $vat = $item->vat_amount;
            $rowTotal = $taxable + $vat;

            $runningTaxable += $taxable;
            $runningVat += $vat;
            $vatPercent = ($item->taxable_amount > 0) ? ($item->vat_amount / $item->taxable_amount) * 100 : 0;

            // --- PARENT ITEM ROW ---
            $html .= '
                <tr style="text-align:center; background-color: #ffffff;">
                    <td width="3%">'.($index + 1).'</td>
                    <td width="10%">'.$item->item_name.'</td>
                    <td width="10%">'.$item->item_description.'</td>
                    <td width="7%">'.number_format($item->gross_weight, 3).'</td>
                    <td width="6%">'.number_format($item->purity, 3).'</td>
                    <td width="7%">'.number_format($item->purity_weight, 3).'</td>
                    <td width="6%">'.number_format($item->col_995 ?? 0, 3).'</td>
                    <td width="7%">'.number_format($item->making_rate ?? 0, 2).'</td>
                    <td width="7%">'.number_format($item->making_value, 2).'</td>
                    <td width="8%">'.ucfirst($item->material_type).'</td>
                    <td width="7%">'.number_format($item->material_value, 2).'</td>
                    <td width="8%">'.number_format($item->taxable_amount, 2).'</td>
                    <td width="6%">'.round($vatPercent, 0).'%</td>
                    <td width="8%">'.number_format($rowTotal, 2).'</td>
                </tr>';

            // --- PARTS SUB-ROWS ---
            if ($item->parts && $item->parts->count() > 0) {
                // Parts Header for clarity
                $html .= '<tr style="background-color:#f9f9f9; font-style:italic; font-size:7px;">
                            <td width="3%"></td>
                            <td colspan="13" width="97%"><b>Parts Detail:</b></td>
                        </tr>';

                foreach ($item->parts as $part) {
                    // Fetch the unit (e.g., Grams, Pcs, Cts)
                    $partUnit = $part->product->measurementUnit->shortcode ?? ($part->product->measurementUnit->name ?? '-');
                    
                    $variationText = '';
                    if ($part->variation && $part->variation->attributeValues->count()) {
                        $variationText = ' [' .
                            $part->variation->attributeValues
                                ->map(fn($av) => $av->attribute->name.': '.$av->value)
                                ->implode(', ')
                            . ']';
                    }

                    $html .= '
                    <tr style="font-size:7.5px; background-color:#fcfcfc;">
                        <td width="3%"></td>
                        <td width="20%" colspan="2" style="text-align:left;">'.($part->product->name ?? 'Part').$variationText.'</td>
                        <td width="20%" colspan="1" style="text-align:left;">'.$part->part_description.'</td>
                
                        <td width="10%" colspan="2" style="text-align:center;">'.$part->qty.' '.$partUnit.'</td>
                        
                        <td width="10%" colspan="2" style="text-align:center;">Rate: '.number_format($part->rate, 2).'</td>
                        <td width="11%" colspan="1" style="text-align:center;">Stone Qty: '.number_format($part->stone_qty ?? 0, 0).'</td>
                        <td width="12%" colspan="1" style="text-align:center;">Rate: '.number_format($part->stone_rate ?? 0, 2).'</td>
                        <td width="14%" colspan="2" style="text-align:right; padding-right:10px;"><b>Total: '.number_format($part->total, 2).'</b></td>
                    </tr>';
                }
            }
        }

        $html .= '
                <tr style="font-weight:bold; background-color:#f5f5f5;">
                    <td colspan="12" align="right">Net Amount (Incl. VAT)</td>
                    <td colspan="2" align="center">'.number_format($invoice->net_amount, 2).'</td>
                </tr>
            </tbody>
        </table>';

        $pdf->writeHTML($html, true, false, false, false);

        /* ================= SUMMARY SECTION ================= */
        $aedAmount = $invoice->currency === 'USD' ? $invoice->net_amount_aed : $invoice->net_amount;
        $wordsText=$pdf->convertCurrencyToWords($aedAmount);

        $summaryHtml = '
        <table width="100%" cellpadding="0" border="0" style="margin-top:10px;">
            <tr>
                <td width="45%" valign="top">
                    <table border="1" cellpadding="4" width="100%" style="font-size:9px;">
                        <tr style="background-color:#f5f5f5;"><td><b>Payment Details</b></td><td><b>Value</b></td></tr>
                        <tr><td>Method</td><td>'.ucfirst($invoice->payment_method).'</td></tr>';
  
                        if($invoice->payment_method === 'credit'){
                            $summaryHtml .= '
                            <tr><td>Payment Term:</td><td>'.$invoice->payment_term.'</td></tr>';
                        }
                        if($invoice->payment_method === 'cheque'){
                            $summaryHtml .= '
                            <tr><td>Bank Name</td><td>'.$invoice->bank->name.'</td></tr>
                            <tr><td>Cheque No</td><td>'.$invoice->cheque_no.'</td></tr>
                            <tr><td>Cheque Date</td><td>'.$invoice->cheque_date.'</td></tr>';
                        }
                        if(str_contains($invoice->payment_method, 'material')){
                            $summaryHtml .= '
                            <tr><td>Material Wt / Pur</td><td>'.number_format($invoice->material_weight,2).' / '.number_format($invoice->material_purity,3).'</td></tr>
                            <tr><td>Making Charges</td><td>'.number_format($invoice->making_charges,2).' AED</td></tr>
                            <tr><td>Received By</td><td>'.$invoice->material_received_by.'</td></tr>
                            <tr><td>Given By</td><td>'.$invoice->material_given_by.'</td></tr>';
                        }

                        $summaryHtml .= '</table>
                                </td>
                                <td width="10%"></td>
                                <td width="45%" valign="top">
                                    <table border="1" cellpadding="4" width="100%" style="font-size:9px;">
                                        <tr style="background-color:#f5f5f5;"><td colspan="2" align="center"><b>Summary ('.$invoice->currency.')</b></td></tr>
                                        <tr><td width="60%">Total Taxable</td><td width="40%" align="right">'.number_format($runningTaxable, 2).'</td></tr>
                                        <tr><td>Total VAT</td><td align="right">'.number_format($runningVat, 2).'</td></tr>
                                        <tr style="font-weight:bold; background-color:#eeeeee;">
                                            <td>Invoice Total</td>
                                            <td align="right">'.number_format($invoice->net_amount, 2).'</td>
                                        </tr>';
                        
                        if($invoice->currency === 'USD'){
                            $summaryHtml .= '
                                <tr><td>Exchange Rate</td><td align="right">'.number_format($invoice->exchange_rate, 4).'</td></tr>
                                <tr style="font-weight:bold;"><td>Total (AED)</td><td align="right">'.number_format($aedAmount, 2).'</td></tr>';
                        } else {
                            $summaryHtml .= '<tr style="font-weight:bold;"><td>Total (AED)</td><td align="right">'.number_format($aedAmount, 2).'</td></tr>';
                        }
                    $summaryHtml .= '</table>
                </td>
            </tr>
        </table>';

        $pdf->Ln(2);
        $pdf->writeHTML($summaryHtml, true, false, false, false);

        /* ================= AMOUNT IN WORDS ================= */
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 9);

        // Calculate AED and USD strings
        $aedAmount = $invoice->currency === 'USD' ? $invoice->net_amount_aed : $invoice->net_amount;
        $wordsAED = $pdf->convertCurrencyToWords($aedAmount, 'AED');

        if ($invoice->currency === 'USD') {
            $wordsUSD = $pdf->convertCurrencyToWords($invoice->net_amount, 'USD');
            
            // Print USD Words
            $pdf->Cell(0, 5, "Amount in Words (USD): " . $wordsUSD, 0, 1, 'L');
            // Print AED Words below it
            $pdf->Cell(0, 5, "Amount in Words (AED): " . $wordsAED, 0, 1, 'L');
        } else {
            // Just print AED
            $pdf->Cell(0, 5, "Amount in Words (AED): " . $wordsAED, 0, 1, 'L');
        }

        /* ================= TERMS & CONDITIONS ================= */
        $pdf->Ln(2);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY()); 
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 9); 
        $termsHtml = '
            <div style="line-height: 8px; text-align: justify; color: #333;">
                <b>TERMS & CONDITIONS:</b> Goods sold on credit, if not paid when due, or in case of law suit arising there from, 
                the purchaser agrees to pay the seller all expense of recovery, collection, etc., including attorney fees, 
                legal expense and/or recovery-agent charges. <b>GOODS ONCE SOLD CANNOT BE RETURNED OR EXCHANGED.</b> 
                Any dispute, difference, controversy or claim arising out of or in connection with this sale, 
                including (but not limited to) any issue regarding its existence, validity, interpretation, performance, 
                discharge and other applicable remedies, shall be subject to the exclusive jurisdiction of Dubai Courts.
            </div>';

        $pdf->writeHTML($termsHtml, true, false, false, false);

        /* ================= SIGNATURES ================= */
        $pdf->Ln(20);
        $y = $pdf->GetY();
        $pdf->Line(20, $y, 80, $y); $pdf->Line(130, $y, 190, $y);
        $pdf->SetXY(20, $y); $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(60, 5, "Receiver's Signature", 0, 0, 'C');
        $pdf->SetXY(130, $y); $pdf->Cell(60, 5, "Authorized Signature", 0, 0, 'C');

        return $pdf->Output('purchase_invoice_'.$invoice->invoice_no.'.pdf', 'I');
    }
}
