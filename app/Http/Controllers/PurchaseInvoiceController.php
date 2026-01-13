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
        $invoices = PurchaseInvoice::with('vendor')->get();
        return view('purchase.index', compact('invoices'));
    }

    public function create()
    {
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $products = Product::get();
        $units = MeasurementUnit::all();
        return view('purchase.create', compact('products', 'vendors','units'));
    }

    public function store(Request $request)
    {
        $request->validate([
            // Invoice header
            'invoice_no' => 'required|unique:purchase_invoices,invoice_no|max:20',
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'invoice_date' => 'required|date',
            'currency' => 'required|in:AED,USD',
            'net_amount' => 'required|numeric|min:0',
            'net_amount_aed' => 'required|numeric|min:0',
            'payment_method' => 'nullable|in:cash,credit,cheque,material+making cost',
            'cheque_no' => 'required_if:payment_method,cheque|max:50',
            'cheque_date' => 'required_if:payment_method,cheque|date',
            'bank_name' => 'required_if:payment_method,cheque|max:100',
            'cheque_amount' => 'required_if:payment_method,cheque|numeric|min:0',
            'material_weight' => 'nullable|numeric|min:0',
            'material_purity' => 'nullable|numeric|min:0|max:100',
            'material_value' => 'nullable|numeric|min:0',
            'making_charges' => 'nullable|numeric|min:0',
            'gold_rate' => 'nullable|numeric|min:0',
            'silver_rate' => 'nullable|numeric|min:0',
            'other_metal_rate' => 'nullable|numeric|min:0',

            // Items
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'nullable|string|max:255|required_without:items.*.product_id',
            'items.*.product_id' => 'nullable|exists:products,id|required_without:items.*.item_name',
            'items.*.variation_id' => 'nullable|exists:product_variations,id',
            'items.*.item_description' => 'nullable|string|max:255',
            'items.*.gross_weight' => 'required|numeric|min:0',
            'items.*.purity' => 'required|numeric|min:0|max:100',
            'items.*.purity_weight' => 'nullable|numeric|min:0',
            'items.*.making_rate' => 'nullable|numeric|min:0',
            'items.*.making_value' => 'nullable|numeric|min:0',
            'items.*.material_value' => 'nullable|numeric|min:0',
            'items.*.metal_value' => 'nullable|numeric|min:0',
            'items.*.taxable_amount' => 'nullable|numeric|min:0',
            'items.*.vat_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.vat_amount' => 'nullable|numeric|min:0',
            'items.*.item_total' => 'nullable|numeric|min:0',
            'items.*.metal_type' => 'nullable|string|in:gold,metal',
            'items.*.gold_rate' => 'nullable|numeric|min:0',
            'items.*.silver_rate' => 'nullable|numeric|min:0',
            'items.*.other_metal_rate' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string|max:255',
            'items.*.attachment' => 'nullable|string|max:255',

            // Parts
            'items.*.parts' => 'nullable|array',
            'items.*.parts.*.product_id' => 'nullable|exists:products,id',
            'items.*.parts.*.variation_id' => 'nullable|exists:product_variations,id',
            'items.*.parts.*.qty' => 'required|numeric|min:0',
            'items.*.parts.*.rate' => 'required|numeric|min:0',
            'items.*.parts.*.wastage' => 'nullable|numeric|min:0',
            'items.*.parts.*.total' => 'required|numeric|min:0',
            'items.*.parts.*.metal_weight' => 'nullable|numeric|min:0',
            'items.*.parts.*.metal_rate' => 'nullable|numeric|min:0',
            'items.*.parts.*.metal_value' => 'nullable|numeric|min:0',
            'items.*.parts.*.part_description' => 'nullable|string|max:255',

            // Attachments
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,zip|max:2048',
        ]);

        try {
            \DB::beginTransaction();

            // Create Invoice
            $invoice = \App\Models\PurchaseInvoice::create($request->only([
                'invoice_no','vendor_id','invoice_date','remarks','currency','exchange_rate',
                'net_amount','net_amount_aed','payment_method','cheque_no','cheque_date','bank_name','cheque_amount',
                'material_weight','material_purity','material_value','making_charges','gold_rate','silver_rate','other_metal_rate'
            ]) + ['created_by' => auth()->id()]);

            // Create Items and Parts
            foreach ($request->items as $itemData) {
                $partsData = $itemData['parts'] ?? [];
                unset($itemData['parts']);

                $item = $invoice->items()->create($itemData);

                foreach ($partsData as $part) {
                    $item->parts()->create($part);
                }
            }

            // Attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('purchase_invoices', 'public');
                    $invoice->attachments()->create(['file_path' => $path]);
                }
            }

            \DB::commit();
            return redirect()->route('purchase_invoices.index')->with('success','Purchase Invoice created successfully.');
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Purchase Invoice store error: '.$e->getMessage());
            return back()->withInput()->with('error','Something went wrong while creating the invoice.');
        }
    }

}
