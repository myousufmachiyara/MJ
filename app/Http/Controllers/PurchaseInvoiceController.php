<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseInvoiceAttachment;
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
        $invoices = PurchaseInvoice::with('vendor')->latest()->get();
        return view('purchases.index', compact('invoices'));
    }

    public function create()
    {
        $products = Product::get();
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $units = MeasurementUnit::all();

        return view('purchases.create', compact('products', 'vendors','units'));
    }

    public function store(Request $request)
    {
        $request->validate([

            // Invoice
            'invoice_date' => 'required|date',
            'vendor_id'    => 'required|exists:chart_of_accounts,id',
            'payment_terms' => 'nullable|string',
            'bill_no' => 'nullable|string|max:100',
            'ref_no'  => 'nullable|string|max:100',
            'remarks' => 'nullable|string',

            // Charges
            'convance_charges' => 'nullable|numeric|min:0',
            'labour_charges'   => 'nullable|numeric|min:0',
            'bill_discount'    => 'nullable|numeric|min:0',

            // Attachments
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,zip|max:2048',

            // Items
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'nullable|exists:products,id',
            'items.*.temp_product_name' => 'nullable|string|max:255',

            'items.*.quantity' => 'required_with:items.*.item_id,items.*.temp_product_name|numeric|min:0.01',
            'items.*.unit'     => 'required_with:items.*.item_id,items.*.temp_product_name|exists:measurement_units,id',
            'items.*.price'    => 'required_with:items.*.item_id,items.*.temp_product_name|numeric|min:0',

            // Parts
            'items.*.parts' => 'nullable|array',
            'items.*.parts.*.product_id' => 'required|exists:products,id',
            'items.*.parts.*.qty' => 'required|numeric|min:0.01',
            'items.*.parts.*.rate' => 'required|numeric|min:0',
            'items.*.parts.*.wastage' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {

            $lastInvoice = PurchaseInvoice::withTrashed()->orderBy('id', 'desc')->first();

            $nextNumber = $lastInvoice ? intval($lastInvoice->invoice_no) + 1 : 1;

            $invoiceNo = str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            $invoice = PurchaseInvoice::create([
                'invoice_no'       => $invoiceNo,
                'vendor_id'        => $request->vendor_id,
                'invoice_date'     => $request->invoice_date,
                'payment_terms'    => $request->payment_terms,
                'bill_no'          => $request->bill_no,
                'ref_no'           => $request->ref_no,
                'remarks'          => $request->remarks,
                'convance_charges' => $request->convance_charges ?? 0,
                'labour_charges'   => $request->labour_charges ?? 0,
                'bill_discount'    => $request->bill_discount ?? 0,
                'created_by'       => auth()->id(),
            ]);

            foreach ($request->items as $item) {

                if (empty($item['item_id']) && empty($item['temp_product_name'])) {
                    continue;
                }

                $invoiceItem = $invoice->items()->create([
                    'item_id' => $item['item_id'] ?? null,
                    'temp_product_name' => $item['temp_product_name'] ?? null,
                    'item_type' => !empty($item['parts']) ? 'composite' : 'simple',
                    'variation_id' => $item['variation_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'rate' => $item['price'],
                    'remarks' => $item['item_remarks'] ?? null,
                ]);

                if (!empty($item['parts'])) {
                    foreach ($item['parts'] as $part) {
                        $invoiceItem->parts()->create([
                            'part_product_id' => $part['product_id'],
                            'qty' => $part['qty'],
                            'wastage_qty' => $part['wastage'] ?? 0,
                            'rate' => $part['rate'],
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('purchase_invoices.index')
                ->with('success', 'Purchase Invoice created successfully.');

        } catch (\Exception $e) {

            DB::rollBack();
            Log::error($e);

            return back()->withErrors(['error' => 'Failed to create invoice.']);
        }
    }

    public function edit($id)
    {
        $invoice = PurchaseInvoice::with(['items', 'attachments'])->findOrFail($id);
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $products = Product::select('id', 'name', 'barcode', 'measurement_unit')->get();
        $units = MeasurementUnit::all(); // <-- add this line

        return view('purchases.edit', compact('invoice', 'vendors', 'products', 'units'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'invoice_date' => 'required|date',
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'payment_terms' => 'nullable|string',
            'bill_no' => 'nullable|string|max:100',
            'ref_no' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,zip|max:2048',
            'item_id.*'      => 'required|exists:products,id',
            'variation_id.*' => 'nullable|exists:product_variations,id',
            'quantity.*'     => 'required|numeric|min:0.01',
            'unit.*'         => 'required|exists:measurement_units,id',
            'price.*'        => 'required|numeric|min:0',
            'item_remarks.*' => 'nullable|string',
            'convance_charges' => 'nullable|numeric|min:0',
            'labour_charges'   => 'nullable|numeric|min:0',
            'bill_discount'    => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $invoice = PurchaseInvoice::findOrFail($id);

            // ✅ Update invoice main details
            $invoice->update([
                'vendor_id'        => $request->vendor_id,
                'invoice_date'     => $request->invoice_date,
                'payment_terms'    => $request->payment_terms,
                'bill_no'          => $request->bill_no,
                'ref_no'           => $request->ref_no,
                'remarks'          => $request->remarks,
                'convance_charges' => $request->convance_charges ?? 0,
                'labour_charges'   => $request->labour_charges ?? 0,
                'bill_discount'    => $request->bill_discount ?? 0,
            ]);

            Log::info('Purchase Invoice updated', [
                'invoice_id' => $invoice->id,
                'user_id' => auth()->id(),
            ]);

            // ✅ Delete old items
            $invoice->items()->delete();
            Log::info('Old items deleted for invoice', ['invoice_id' => $invoice->id]);

            // ✅ Re-insert updated items
            $products = Product::pluck('name', 'id');

            foreach ($request->items as $index => $itemData) {
                if (empty($itemData['item_id'])) {
                    continue;
                }

                $invoice->items()->create([
                    'item_id'      => $itemData['item_id'],
                    'variation_id' => $itemData['variation_id'] ?? null,
                    'item_name'    => $products[$itemData['item_id']] ?? null,
                    'quantity'     => $itemData['quantity'] ?? 0,
                    'unit'         => $itemData['unit'] ?? '',
                    'price'        => $itemData['price'] ?? 0,
                    'remarks'      => $itemData['item_remarks'] ?? null,
                ]);
            }

            // ✅ Handle new attachments if any
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('purchase_invoices', 'public');

                    $invoice->attachments()->create([
                        'file_path'     => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'file_type'     => $file->getClientMimeType(),
                    ]);

                    Log::info('Invoice attachment uploaded', [
                        'invoice_id' => $invoice->id,
                        'file' => $file->getClientOriginalName(),
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('purchase_invoices.index')
                            ->with('success', 'Purchase Invoice updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Purchase Invoice update failed', [
                'invoice_id' => $id,
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to update invoice. Please try again.']);
        }
    }

    public function destroy($id)
    {
        $invoice = PurchaseInvoice::findOrFail($id);

        // Delete attached files from storage
        foreach ($invoice->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $invoice->delete();

        return redirect()->route('purchase_invoices.index')->with('success', 'Purchase Invoice deleted successfully.');
    }

    public function getInvoicesByItem($itemId)
    {
        $invoices = PurchaseInvoice::whereHas('items', function ($q) use ($itemId) {
            $q->where('item_id', $itemId);
        })
        ->with('vendor')
        ->get(['id', 'vendor_id']);

        return response()->json(
            $invoices->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'vendor' => $inv->vendor->name ?? '',
                ];
            })
        );
    }

    public function getItemDetails($invoiceId, $itemId)
    {
        $item = PurchaseInvoiceItem::with(['product', 'measurementUnit'])
            ->where('purchase_invoice_id', $invoiceId)
            ->where('item_id', $itemId)
            ->first();

        if (!$item) {
            return response()->json(['error' => 'Item not found in this invoice.'], 404);
        }

        return response()->json([
            'item_id'   => $item->item_id,
            'item_name' => $item->product->name ?? '',
            'quantity'  => $item->quantity,
            'unit_id'   => $item->unit_id,
            'unit_name' => $item->unit->name ?? '',
            'price'     => $item->price,
        ]);
    }

    public function print($id)
    {
        $invoice = PurchaseInvoice::with(['vendor', 'items.parts.product'])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        // --- Company Header with Logo Left, Info Right ---
        $logoPath = public_path('assets/img/mj-logo.jpeg');

        $logoHtml = '';
        if (file_exists($logoPath)) {
            // The Image tag in HTML
            $logoHtml = '<img src="'.$logoPath.'" width="80" style="vertical-align:middle;">';
        }

        $headerHtml = '
        <table width="100%" cellpadding="5">
            <tr>
                <td width="40%" style="text-align:left;">
                    '.$logoHtml.'
                </td>
                <td width="60%" style="text-align:right; font-size:10px;">
                    <strong>MUSFIRA JEWELRY L.L.C</strong><br>
                    Office 202-201-932, Insurance Building, Al Rigga, Dubai – U.A.E<br>
                    TRN No: 10490
                </td>
            </tr>
        </table>
        <hr>
        ';

        $pdf->writeHTML($headerHtml, true, false, false, false, '');

        // --- Invoice Title ---
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 6, 'TAX INVOICE', 0, 1, 'C');

        // --- Vendor + Invoice Info ---
        $pdf->Ln(2);
        $infoHtml = '
        <table cellpadding="3" style="font-size:10px;" width="100%">
            <tr>
                <td width="65%">
                    <b>To,</b><br>
                    M/S. ' . ($invoice->vendor->name ?? '-') . '<br>
                    TRN: ' . ($invoice->vendor->trn ?? '-') . '
                </td>
                <td width="35%">
                    <table border="1" cellpadding="3">
                        <tr><td><b>Date</b></td><td>' . \Carbon\Carbon::parse($invoice->invoice_date)->format('d.m.Y') . '</td></tr>
                        <tr><td><b>Invoice No</b></td><td>' . $invoice->id . '</td></tr>
                    </table>
                </td>
            </tr>
        </table>';
        $pdf->writeHTML($infoHtml, true, false, false, false, '');

        // --- Items Table ---
        $html = '
        <table border="1" cellpadding="4" style="text-align:center;font-size:10px;" width="100%">
            <tr style="font-weight:bold; background-color:#f5f5f5;">
                <th width="5%">#</th>
                <th width="45%">Description</th>
                <th width="15%">Qty</th>
                <th width="20%">Price</th>
                <th width="15%">Amount</th>
            </tr>';

        $count = 0;
        $totalAmount = 0;

        foreach ($invoice->items as $item) {
            $count++;
            $itemTotal = ($item->quantity ?? 0) * ($item->rate ?? 0);
            $totalAmount += $itemTotal;

            // Determine unit name
            if ($item->product) {
                $unitName = $item->product->measurementUnit->name ?? '-';
            } elseif (!empty($item->unit)) {
                // Lookup from MeasurementUnit table
                $unitModel = MeasurementUnit::find($item->unit);
                $unitName = $unitModel->shortcode ?? '-';
            } else {
                $unitName = '-';
            }

            $html .= '
            <tr>
                <td>'.$count.'</td>
                <td style="text-align:left;">'.($item->product ? $item->product->name : ($item->temp_product_name ?? '-')).'</td>
                <td>'.$item->quantity.' '.$unitName.'</td>
                <td>'.number_format($item->rate,2).'</td>
                <td>'.number_format($itemTotal,2).'</td>
            </tr>';

            // --- Include Parts if any ---
            if($item->parts && $item->parts->count() > 0){
                foreach($item->parts as $part){
                    $partTotal = ($part->qty + $part->wastage) * $part->rate;

                    // Part unit
                    if ($part->product) {
                        $partUnit = $part->product->measurementUnit->shortcode ?? '-';
                    } elseif (!empty($part->unit)) {
                        $partUnitModel = MeasurementUnit::find($part->unit);
                        $partUnit = $partUnitModel->shortcode ?? '-';
                    } else {
                        $partUnit = '-';
                    }

                    $html .= '
                    <tr style="font-size:9px;background-color:#efefef">
                        <td></td>
                        <td style="text-align:left;"> - '.$part->product->name.'</td>
                        <td>'.$part->qty.' '.$partUnit.'</td>
                        <td>'.$part->rate.'</td>
                        <td>'.number_format($partTotal,2).'</td>
                    </tr>';
                }
            }
        }

        $html .= '
        <tr>
            <td colspan="4" align="right"><b>Total</b></td>
            <td><b>'.number_format($totalAmount,2).'</b></td>
        </tr>
        </table>';

        $pdf->writeHTML($html, true, false, false, false, '');

       // --- Charges & Net Amount (aligned right, narrower) ---
        $pdf->Ln(3);
        $conv = $invoice->convance_charges ?? 0;
        $labour = $invoice->labour_charges ?? 0;
        $discount = $invoice->bill_discount ?? 0;
        $net = $totalAmount + $conv + $labour - $discount;

        $pdf->SetFont('helvetica','B',10);

        // Define widths
        $labelWidth = 50;  // label column
        $valueWidth = 30;  // value column

        // Get right margin
        $margins = $pdf->getMargins();
        $startX = $pdf->getPageWidth() - $labelWidth - $valueWidth - $margins['right'];
        $pdf->SetX($startX);

        $pdf->Cell($labelWidth, 6, 'Convance Charges', 1, 0, 'L');
        $pdf->Cell($valueWidth, 6, number_format($conv,2), 1, 1, 'R');

        $pdf->SetX($startX);
        $pdf->Cell($labelWidth, 6, 'Labour Charges', 1, 0, 'L');
        $pdf->Cell($valueWidth, 6, number_format($labour,2), 1, 1, 'R');

        $pdf->SetX($startX);
        $pdf->Cell($labelWidth, 6, 'Discount', 1, 0, 'L');
        $pdf->Cell($valueWidth, 6, number_format($discount,2), 1, 1, 'R');

        $pdf->SetX($startX);
        $pdf->Cell($labelWidth, 6, 'Net Amount', 1, 0, 'L');
        $pdf->Cell($valueWidth, 6, number_format($net,2), 1, 1, 'R');

        // --- Terms / Conditions Paragraph ---
        $pdf->SetFont('helvetica','',10);
        $pdf->Ln(5); // small spacing before paragraph
        $terms = '
        <p style="font-size:9px; line-height:12px">
            Goods sold on credit, if not paid when due, or in case of law suit arising there from, the purchaser agrees to pay the seller all expense of recovery, collection, etc., including attorney fees, legal expense and/or recovery-agent charges. GOODS ONCE SOLD CANNOT BE RETURNED OR EXCHANGED. Any dispute, difference, controversy or claim arising out of or in connection with this sale, including (but not limited to) any issue regarding its existence, validity, interpretation, performance, discharge and other applicable remedies, shall be subject to the exclusive jurisdiction of Dubai Courts.
        </p>
        ';
        $pdf->writeHTML($terms, true, false, false, false, '');

        // Footer Signature Lines
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Ln(20);
        $lineWidth = 60;
        $yPosition = $pdf->GetY();

        $pdf->Line(28, $yPosition, 20 + $lineWidth, $yPosition);
        $pdf->Line(130, $yPosition, 120 + $lineWidth, $yPosition);
        $pdf->Ln(5);

        $pdf->SetXY(23, $yPosition);
        $pdf->Cell($lineWidth, 10, "Receiver's Signature", 0, 0, 'C');

        $pdf->SetXY(125, $yPosition);
        $pdf->Cell($lineWidth, 10, "Issuer's Signature", 0, 0, 'C');

        return $pdf->Output('purchase_invoice_'.$invoice->id.'.pdf', 'I');
    }

    public function getProductInvoices($productId)
    {
        try {
            // Fetch invoices for this vendor that include this product
            $invoices = PurchaseInvoice::whereHas('items', function($q) use ($productId) {
                    $q->where('item_id', $productId);
                })
                ->with(['items' => function($q) use ($productId) {
                    $q->where('item_id', $productId);
                }])
                ->get();

            $data = $invoices->map(function($inv) {
                $item = $inv->items->first(); // get the first matching item
                return [
                    'id' => $inv->id,
                    'number' => $inv->invoice_number,
                    'rate' => $item ? $item->price : 0, // safe fallback
                ];
            });

            return response()->json($data);

        } catch (\Exception $e) {
            Log::error('Invoice fetch failed: '.$e->getMessage());
            return response()->json(['error' => 'Failed to load invoices'], 500);
        }
    }
}
