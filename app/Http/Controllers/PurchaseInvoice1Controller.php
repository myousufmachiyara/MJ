<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice_1;
use App\Models\PurchaseInvoice_1_Item;
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

class PurchaseInvoice1Controller extends Controller
{

    public function index()
    {
        $invoices = PurchaseInvoice_1::with('vendor')->latest()->get();
        return view('purchases-1.index', compact('invoices'));
    }

    public function create()
    {
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();

        return view('purchases-1.create', compact( 'vendors'));
    }

    public function store(Request $request)
    {
        Log::info('PurchaseInvoice_1 store started', [
            'user_id' => auth()->id(),
            'payload' => $request->all(),
        ]);

        $request->validate([
            'invoice_date' => 'required|date',
            'vendor_id'    => 'required|exists:chart_of_accounts,id',
            'remarks'      => 'nullable|string',
            'net_amount'   => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,credit,cheque,material',

            'cheque_no'     => 'required_if:payment_method,cheque|nullable|string|max:50',
            'cheque_date'   => 'required_if:payment_method,cheque|nullable|date',
            'bank_name'     => 'required_if:payment_method,cheque|nullable|string|max:100',
            'cheque_amount' => 'required_if:payment_method,cheque|nullable|numeric|min:0',

            'material_weight' => 'required_if:payment_method,material|nullable|numeric|min:0',
            'material_purity' => 'required_if:payment_method,material|nullable|numeric|min:0',
            'material_value'  => 'required_if:payment_method,material|nullable|numeric|min:0',
            'making_charges'  => 'required_if:payment_method,material|nullable|numeric|min:0',

            'items' => 'required|array|min:1',
            'items.*.item_description'=> 'required|string|max:255',
            'items.*.purity'          => 'nullable|numeric|min:0',
            'items.*.gross_weight'    => 'required|numeric|min:0',
            'items.*.purity_weight'   => 'required|numeric|min:0',
            'items.*.making_rate'    => 'nullable|numeric|min:0',
            'items.*.metal_value'     => 'nullable|numeric|min:0',
            'items.*.taxable_amount'  => 'required|numeric|min:0',
            'items.*.vat_percent'      => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {

            /* ---------- Invoice No ---------- */
            $lastInvoice = PurchaseInvoice_1::withTrashed()->latest('id')->first();
            $nextNumber  = $lastInvoice ? intval($lastInvoice->invoice_no) + 1 : 1;
            $invoiceNo   = str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            Log::info('Invoice number generated', ['invoice_no' => $invoiceNo]);

            /* ---------- Create Invoice ---------- */
            $invoice = PurchaseInvoice_1::create([
                'invoice_no'     => $invoiceNo,
                'vendor_id'      => $request->vendor_id,
                'invoice_date'   => $request->invoice_date,
                'remarks'        => $request->remarks,
                'net_amount'     => $request->net_amount,
                'payment_method' => $request->payment_method,

                'cheque_no'     => $request->cheque_no,
                'cheque_date'   => $request->cheque_date,
                'bank_name'     => $request->bank_name,
                'cheque_amount' => $request->cheque_amount,

                'material_weight' => $request->material_weight,
                'material_purity' => $request->material_purity,
                'material_value'  => $request->material_value,
                'making_charges'  => $request->making_charges,

                'created_by' => auth()->id(),
            ]);

            Log::info('Purchase invoice created', ['invoice_id' => $invoice->id]);

            /* ---------- Items ---------- */
            foreach ($request->items as $index => $item) {

                $invoice->items()->create([
                    'item_description'     => $item['item_description'],
                    'purity'          => $item['purity'] ?? 0,
                    'gross_weight'    => $item['gross_weight'],
                    'purity_weight'   => $item['purity_weight'],
                    'making_value'    => $item['making_value'] ?? 0,
                    'metal_value'     => $item['metal_value'] ?? 0,
                    'taxable_amount'  => $item['taxable_amount'],
                    'vat_amount'      => $item['vat_amount'] ?? 0,
                ]);

                Log::info('Invoice item added', [
                    'invoice_id' => $invoice->id,
                    'row' => $index + 1,
                    'description' => $item['item_description'],
                ]);
            }

            DB::commit();

            Log::info('PurchaseInvoice_1 stored successfully', [
                'invoice_id' => $invoice->id,
                'net_amount' => $invoice->net_amount,
            ]);

            return redirect()
                ->route('purchase_invoices_1.index')
                ->with('success', 'Purchase Invoice created successfully.');

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('PurchaseInvoice_1 store failed', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create purchase invoice. Check logs for details.']);
        }
    }

    public function edit($id)
    {
        $invoice = PurchaseInvoice_1::with(['items'])->findOrFail($id);
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();

        return view('purchases-1.edit', compact('invoice', 'vendors'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'invoice_date' => 'required|date',
            'vendor_id'    => 'required|exists:chart_of_accounts,id',
            'remarks'      => 'nullable|string',
            'net_amount'   => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,credit,cheque,material',

            // Cheque
            'cheque_no'     => 'required_if:payment_method,cheque|nullable|string|max:50',
            'cheque_date'   => 'required_if:payment_method,cheque|nullable|date',
            'bank_name'     => 'required_if:payment_method,cheque|nullable|string|max:100',
            'cheque_amount' => 'required_if:payment_method,cheque|nullable|numeric|min:0',

            // Material
            'material_weight' => 'required_if:payment_method,material|nullable|numeric|min:0',
            'material_purity' => 'required_if:payment_method,material|nullable|numeric|min:0',
            'material_value'  => 'required_if:payment_method,material|nullable|numeric|min:0',
            'making_charges'  => 'required_if:payment_method,material|nullable|numeric|min:0',

            // Items
            'items' => 'required|array|min:1',
            'items.*.description'    => 'required|string|max:255',
            'items.*.purity'         => 'nullable|numeric|min:0',
            'items.*.gross_weight'   => 'required|numeric|min:0',
            'items.*.purity_weight'  => 'required|numeric|min:0',
            'items.*.making_value'   => 'nullable|numeric|min:0',
            'items.*.metal_value'    => 'nullable|numeric|min:0',
            'items.*.taxable_amount' => 'required|numeric|min:0',
            'items.*.vat_amount'     => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $invoice = PurchaseInvoice_1::findOrFail($id);

            // Update invoice main details
            $invoice->update([
                'vendor_id'        => $request->vendor_id,
                'invoice_date'     => $request->invoice_date,
                'remarks'          => $request->remarks,
                'net_amount'       => $request->net_amount,
                'payment_method'   => $request->payment_method,
                'cheque_no'        => $request->cheque_no,
                'cheque_date'      => $request->cheque_date,
                'bank_name'        => $request->bank_name,
                'cheque_amount'    => $request->cheque_amount,
                'material_weight'  => $request->material_weight,
                'material_purity'  => $request->material_purity,
                'material_value'   => $request->material_value,
                'making_charges'   => $request->making_charges,
            ]);

            // Delete old items
            $invoice->items()->delete();

            // Re-insert updated items
            foreach ($request->items as $item) {
                $invoice->items()->create([
                    'description'     => $item['description'],
                    'purity'          => $item['purity'] ?? 0,
                    'gross_weight'    => $item['gross_weight'],
                    'purity_weight'   => $item['purity_weight'],
                    'making_value'    => $item['making_value'] ?? 0,
                    'metal_value'     => $item['metal_value'] ?? 0,
                    'taxable_amount'  => $item['taxable_amount'],
                    'vat_amount'      => $item['vat_amount'] ?? 0,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('purchase_invoices_1.index')
                ->with('success', 'Purchase Invoice updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update invoice.']);
        }
    }

    public function destroy($id)
    {
        $invoice = PurchaseInvoice_1::findOrFail($id);

        // Delete attached files from storage
        foreach ($invoice->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $invoice->delete();

        return redirect()->route('purchase_invoices_1.index')->with('success', 'Purchase Invoice deleted successfully.');
    }

    public function print($id)
    {
        $invoice = PurchaseInvoice_1::with(['vendor', 'items'])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('PUR-' . $invoice->invoice_no);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->setCellPadding(1.5);
        $pdf->SetFont('helvetica', '', 10);

        /* ================= HEADER ================= */
        $logoPath = public_path('assets/img/mj-logo.jpeg');
        $logoHtml = file_exists($logoPath)
            ? '<img src="'.$logoPath.'" width="85">'
            : '';

        $pdf->writeHTML('
            <table width="100%" cellpadding="3">
                <tr>
                    <td width="40%">'.$logoHtml.'</td>
                    <td width="60%" style="text-align:right;font-size:10px;">
                        <strong>MUSFIRA JEWELRY L.L.C</strong><br>
                        Office 202-201-932, Insurance Building, Al Rigga, Dubai – U.A.E<br>
                        TRN No: 10490
                    </td>
                </tr>
            </table>
            <hr>
        ', true, false, false, false);

        /* ================= TITLE ================= */
        $pdf->Ln(2);
        $pdf->SetFont('helvetica','B',12);
        $pdf->Cell(0,6,'TAX INVOICE',0,1,'C');

        /* ================= VENDOR INFO ================= */
        $pdf->SetFont('helvetica','',10);
        $pdf->writeHTML('
            <table cellpadding="3" width="100%">
                <tr>
                    <td width="65%">
                        <b>To,</b><br>
                        M/S. '.($invoice->vendor->name ?? '-').'<br>
                        TRN: '.($invoice->vendor->trn ?? '-').'
                    </td>
                    <td width="35%">
                        <table border="1" cellpadding="3">
                            <tr>
                                <td><b>Date</b></td>
                                <td>'.\Carbon\Carbon::parse($invoice->invoice_date)->format('d.m.Y').'</td>
                            </tr>
                            <tr>
                                <td><b>Invoice No</b></td>
                                <td>'.$invoice->invoice_no.'</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        ', true, false, false, false);

        /* ================= ITEMS TABLE ================= */
        $html = '
        <table border="1" cellpadding="4" width="100%" style="font-size:9px;">
            <thead>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="4%" rowspan="2">#</th>
                    <th width="13%" rowspan="2">Item Description</th>
                    <th width="8%" rowspan="2">Purity</th>
                    <th width="9%" rowspan="2">Gross Wt.</th>
                    <th width="9%" rowspan="2">Purity Wt.</th>
                    <th width="16%" colspan="2">Making</th>
                    <th width="8%" rowspan="2">Metal Val.</th>
                    <th width="8%" rowspan="2">Taxable</th>
                    <th width="6%" rowspan="2">VAT %</th>
                    <th width="9%" rowspan="2">VAT Amt.</th>
                    <th width="10%" rowspan="2">Gross Total</th>
                </tr>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="8%">Rate</th>
                    <th width="8%">Value</th>
                </tr>
            </thead>
            <tbody>
        ';

        $sr = 0;
        $grandTotal = 0;

        foreach ($invoice->items as $item) {
            $sr++;

            $vatPercent = ($item->taxable_amount > 0) ? ($item->vat_amount / $item->taxable_amount) * 100: 0;
            $rowTotal = ($item->taxable_amount ?? 0) + ($item->vat_amount ?? 0);
            $grandTotal += $rowTotal;

            $html .= '
                <tr style="text-align:center;">
                    <td width="4%">'.$sr.'</td>
                    <td width="13%">'.$item->item_description.'</td>
                    <td width="8%">'.number_format($item->purity,2).'</td>
                    <td width="9%">'.number_format($item->gross_weight,2).'</td>
                    <td width="9%">'.number_format($item->purity_weight,2).'</td>

                    <td width="8%">'.number_format($item->making_rate ?? 0,2).'</td>
                    <td width="8%">'.number_format($item->making_value,2).'</td>

                    <td width="8%">'.number_format($item->metal_value,2).'</td>
                    <td width="8%">'.number_format($item->taxable_amount,2).'</td>
                    <td width="6%">'.number_format($vatPercent,2).'</td>
                    <td width="9%">'.number_format($item->vat_amount,2).'</td>
                    <td width="10%">'.number_format($rowTotal,2).'</td>
                </tr>
            ';
        }

        $html .= '
                <tr style="font-weight:bold;">
                    <td colspan="10" align="right">Net Amount</td>
                    <td colspan="2">'.number_format($invoice->net_amount,2).'</td>
                </tr>
            </tbody>
        </table>
        ';

        $pdf->writeHTML($html, true, false, false, false);

        /* ================= PAYMENT + CURRENCY ================= */

        $usdAmount     = $invoice->net_amount;
        $exchangeRate  = 3.6725; // AED rate
        $aedAmount     = $usdAmount * $exchangeRate;

        $paymentHtml = '
        <table width="100%" cellpadding="4" style="font-size:9px;margin-top:8px;">
            <tr>

                <!-- LEFT : PAYMENT TERMS -->
                <td width="60%" valign="top">
                    <table border="1" cellpadding="4" width="100%">
                        <tr style="background-color:#f5f5f5;">
                            <td colspan="2"><b>Payment Terms</b></td>
                        </tr>
                        <tr>
                            <td width="40%"><b>Payment Method</b></td>
                            <td width="60%">'.ucfirst($invoice->payment_method).'</td>
                        </tr>
        ';

        if ($invoice->payment_method === 'cheque') {
            $paymentHtml .= '
                <tr><td><b>Cheque No</b></td><td>'.$invoice->cheque_no.'</td></tr>
                <tr><td><b>Cheque Date</b></td><td>'.\Carbon\Carbon::parse($invoice->cheque_date)->format('d.m.Y').'</td></tr>
                <tr><td><b>Bank Name</b></td><td>'.$invoice->bank_name.'</td></tr>
                <tr><td><b>Cheque Amount</b></td><td>'.number_format($invoice->cheque_amount,2).'</td></tr>
            ';
        }

        if ($invoice->payment_method === 'material') {
            $paymentHtml .= '
                <tr><td><b>Raw Metal Weight</b></td><td>'.number_format($invoice->material_weight,2).'</td></tr>
                <tr><td><b>Raw Metal Purity</b></td><td>'.number_format($invoice->material_purity,2).'</td></tr>
                <tr><td><b>Metal Adjustment</b></td><td>'.number_format($invoice->material_value,2).'</td></tr>
                <tr><td><b>Making Charges</b></td><td>'.number_format($invoice->making_charges,2).'</td></tr>
            ';
        }

        $paymentHtml .= '
                    </table>
                </td>

                <!-- SPACER -->
                <td width="10%"></td>

                <!-- RIGHT : CURRENCY CONVERSION -->
                <td width="30%" valign="top">
                    <table border="1" cellpadding="4" width="100%">
                        <tr style="background-color:#f5f5f5;">
                            <td colspan="2"><b>Currency Conversion</b></td>
                        </tr>
                        <tr>
                            <td><b>Currency</b></td>
                            <td>USD → AED</td>
                        </tr>
                        <tr>
                            <td><b>Exchange Rate</b></td>
                            <td>1 USD = '.number_format($exchangeRate,4).'</td>
                        </tr>
                        <tr>
                            <td><b>Total USD</b></td>
                            <td>'.number_format($usdAmount,2).'</td>
                        </tr>
                        <tr style="font-weight:bold;">
                            <td><b>Total AED</b></td>
                            <td>'.number_format($aedAmount,2).'</td>
                        </tr>
                    </table>
                </td>

            </tr>
        </table>';

        $pdf->Ln(3);
        $pdf->writeHTML($paymentHtml, true, false, false, false);



        /* ================= TERMS ================= */
        $pdf->Ln(5);
        $pdf->SetFont('helvetica','',10);
        $pdf->writeHTML('<p>
            Goods sold on credit, if not paid when due, or in case of law suit arising there from, the purchaser agrees to pay the seller all expense of recovery, collection, etc., including attorney fees, legal expense and/or recovery-agent charges. GOODS ONCE SOLD CANNOT BE RETURNED OR EXCHANGED. Any dispute, difference, controversy or claim arising out of or in connection with this sale, including (but not limited to) any issue regarding its existence, validity, interpretation, performance, discharge and other applicable remedies, shall be subject to the exclusive jurisdiction of Dubai Courts.
        </p>', true, false, false, false);

        /* ================= SIGNATURE ================= */
        $pdf->Ln(20);
        $lineWidth = 60;
        $y = $pdf->GetY();

        $pdf->Line(28, $y, 28 + $lineWidth, $y);
        $pdf->Line(120, $y, 120 + $lineWidth, $y);

        $pdf->SetXY(28, $y);
        $pdf->Cell($lineWidth, 10, "Receiver's Signature", 0, 0, 'C');

        $pdf->SetXY(120, $y);
        $pdf->Cell($lineWidth, 10, "Issuer's Signature", 0, 0, 'C');

        return $pdf->Output('purchase_invoice_'.$invoice->invoice_no.'.pdf', 'I');
    }
}
