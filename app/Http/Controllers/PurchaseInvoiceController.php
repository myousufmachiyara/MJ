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
            Log::info('Starting Purchase Invoice creation', [
                'user_id' => auth()->id(),
                'request' => $request->all()
            ]);

            $invoice = PurchaseInvoice::create([
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

            Log::info('Purchase Invoice created', [
                'invoice_id' => $invoice->id,
            ]);

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

            Log::info('Purchase Invoice transaction committed', [
                'invoice_id' => $invoice->id,
            ]);

            return redirect()->route('purchase_invoices.index')
                ->with('success', 'Purchase Invoice created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Purchase Invoice Store Error', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to create invoice. Please try again.']);
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
        $invoice = PurchaseInvoice::with(['vendor', 'items'])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false); // remove default header (and its line)
        $pdf->setPrintFooter(false); // remove default footer
        $pdf->SetCreator('Your App');
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Purchase Invoice #' . $invoice->id);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->setCellPadding(1.5);

        // --- Logo ---
        $logoPath = public_path('assets/img/Jild-Logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 10, 10, 30);
        }

        // --- Invoice Info Box ---
        $pdf->SetXY(130, 12);
        $invoiceInfo = '
        <table cellpadding="2" style="font-size:10px; line-height:14px;">
            <tr><td><b>Invoice #</b></td><td>' . $invoice->id . '</td></tr>
            <tr><td><b>Date</b></td><td>' . \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') . '</td></tr>
            <tr><td><b>Bill No</b></td><td>' . ($invoice->bill_no ?? '-') . '</td></tr>
            <tr><td><b>Ref.</b></td><td>' . ($invoice->ref_no ?? '-') . '</td></tr>
            <tr><td><b>Vendor</b></td><td>' . ($invoice->vendor->name ?? '-') . '</td></tr>
            <tr><td><b>Payment Terms</b></td><td>' . ($invoice->payment_terms ?? '-') . '</td></tr>
        </table>';
        $pdf->writeHTML($invoiceInfo, false, false, false, false, '');

        $pdf->Line(60, 52.25, 200, 52.25); // Line ends just before the blue box
        
        // --- Title Box (no horizontal line above) ---
        $pdf->SetXY(10, 48);
        $pdf->SetFillColor(23, 54, 93);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(50, 8, 'Purchase Invoice', 0, 1, 'C', 1);
        $pdf->SetTextColor(0, 0, 0);

        // --- Items Table ---
        $pdf->Ln(5);
        $html = '<table border="0.3" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="background-color:#f5f5f5; font-weight:bold;">
                <th width="8%">S.No</th>
                <th width="25%">Item Name</th>
                <th width="25%">Variation</th>
                <th width="20%">Qty</th>
                <th width="10%">Rate</th>
                <th width="12%">Total</th>
            </tr>';

        $count = 0;
        $totalAmount = 0;

        foreach ($invoice->items as $item) {
            $count++;
            $amount = $item->quantity * $item->price;
            $totalAmount += $amount;

            $html .= '
            <tr>
                <td align="center">' . $count . '</td>
                <td>' . ($item->product->name ?? '-') . '</td>
                <td align="center">' . ($item->variation->sku ?? '-') . '</td>
                <td align="center">' . number_format($item->quantity, 2). ' ' .$item->measurementUnit->shortcode.'</td>
                <td align="right">' . number_format($item->price, 2) . '</td>
                <td align="right">' . number_format($item->price * $item->quantity, 2) . '</td>
            </tr>';
        }

        // --- Totals ---
        $html .= '
            <tr>
                <td colspan="5" align="right"><b>Total</b></td>
                <td align="right"><b>' . number_format($totalAmount, 2) . '</b></td>
            </tr>';

        if (!empty($invoice->charges)) {
            $totalAmount += $invoice->charges;
            $html .= '
            <tr>
                <td colspan="5" align="right"><b>Additional Charges</b></td>
                <td align="right">' . number_format($invoice->charges, 2) . '</td>
            </tr>';
        }

        if (!empty($invoice->discount)) {
            $totalAmount -= $invoice->discount;
            $html .= '
            <tr>
                <td colspan="5" align="right"><b>Discount</b></td>
                <td align="right">' . number_format($invoice->discount, 2) . '</td>
            </tr>';
        }

        $html .= '
            <tr style="background-color:#f5f5f5;">
                <td colspan="5" align="right"><b>Net Total</b></td>
                <td align="right"><b>' . number_format($totalAmount, 2) . '</b></td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        // --- Remarks ---
        if (!empty($invoice->remarks)) {
            $remarksHtml = '<b>Remarks:</b><br><span style="font-size:12px;">' . nl2br($invoice->remarks) . '</span>';
            $pdf->writeHTML($remarksHtml, true, false, true, false, '');
        }

        // --- Signatures ---
        $pdf->Ln(20);
        $yPos = $pdf->GetY();
        $lineWidth = 40;

        $pdf->Line(28, $yPos, 28 + $lineWidth, $yPos);
        $pdf->Line(130, $yPos, 130 + $lineWidth, $yPos);

        $pdf->SetXY(28, $yPos + 2);
        $pdf->Cell($lineWidth, 6, 'Received By', 0, 0, 'C');
        $pdf->SetXY(130, $yPos + 2);
        $pdf->Cell($lineWidth, 6, 'Authorized By', 0, 0, 'C');

        return $pdf->Output('purchase_invoice_' . $invoice->id . '.pdf', 'I');
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
