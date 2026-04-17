<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Models\AccountingEntry;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\myPDF;

class VoucherController extends Controller
{
    // =========================================================================
    // VALID VOUCHER TYPES
    // =========================================================================

    private const TYPES = [
        'purchase' => 'Purchase',
        'sale'     => 'Sale',
        'journal'  => 'Journal',
        'payment'  => 'Payment',
        'receipt'  => 'Receipt',
    ];

    // =========================================================================
    // INDEX
    // Loads both simple and complex vouchers and normalises them for Blade.
    // =========================================================================

    public function index($type)
    {
        $vouchers = Voucher::with([
                'debitAccount',
                'creditAccount',
                'entries.account',
                'reference',
            ])
            ->where('voucher_type', $type)
            ->orderBy('voucher_date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (Voucher $v) {

                // ── Normalise debit / credit for display ──────────────────────
                if ($v->isSimple()) {
                    $v->display_debits  = [['account' => $v->debitAccount->name  ?? 'N/A', 'amount' => $v->amount ?? 0]];
                    $v->display_credits = [['account' => $v->creditAccount->name ?? 'N/A', 'amount' => $v->amount ?? 0]];
                    $v->display_total   = $v->amount ?? 0;
                    $v->is_auto         = false;
                } else {
                    $debitLines  = $v->entries->where('debit',  '>', 0)->values();
                    $creditLines = $v->entries->where('credit', '>', 0)->values();

                    $v->display_debits  = $debitLines->map(fn($e) => [
                        'account' => $e->account->name ?? 'N/A',
                        'amount'  => $e->debit,
                    ])->all();
                    $v->display_credits = $creditLines->map(fn($e) => [
                        'account' => $e->account->name ?? 'N/A',
                        'amount'  => $e->credit,
                    ])->all();
                    $v->display_total = $debitLines->sum('debit');
                    $v->is_auto       = !is_null($v->reference_type);
                }

                // ── Source document label + link ──────────────────────────────
                $v->reference_label = null;
                $v->reference_link  = null;

                if ($v->reference_type && $v->reference_id) {
                    $shortClass = class_basename($v->reference_type);
                    $docNo      = $v->reference?->invoice_no
                               ?? $v->reference?->order_no
                               ?? ('#' . $v->reference_id);

                    $v->reference_label = $shortClass . ' · ' . $docNo;
                    $v->reference_link  = match ($shortClass) {
                        'PurchaseInvoice' => route('purchase_invoices.edit', $v->reference_id),
                        'SaleInvoice'     => route('sale_invoices.edit',     $v->reference_id),
                        default           => null,
                    };
                }

                return $v;
            });

        // All COA accounts — used in add/edit modal dropdowns.
        // Grouped by type so the Blade can render optgroups.
        $accounts   = ChartOfAccounts::orderBy('account_code')->get();
        $validTypes = self::TYPES;

        return view('vouchers.index', compact('vouchers', 'accounts', 'type', 'validTypes'));
    }

    // =========================================================================
    // SHOW  (used by the edit modal JS fetch)
    // =========================================================================

    public function show($type, $id)
    {
        $voucher = Voucher::with(['debitAccount', 'creditAccount', 'entries.account'])
            ->findOrFail($id);

        return response()->json([
            'id'         => $voucher->id,
            'voucher_no' => $voucher->voucher_no,
            'date'       => $voucher->voucher_date?->format('Y-m-d'),
            'ac_dr_sid'  => $voucher->ac_dr_sid,
            'ac_cr_sid'  => $voucher->ac_cr_sid,
            'amount'     => $voucher->amount,
            'remarks'    => $voucher->remarks,
            'is_simple'  => $voucher->isSimple(),
            'is_auto'    => !is_null($voucher->reference_type),
            'entries'    => $voucher->entries->map(fn($e) => [
                'account_id'   => $e->account_id,
                'account_name' => $e->account->name ?? 'N/A',
                'debit'        => $e->debit,
                'credit'       => $e->credit,
                'narration'    => $e->narration,
            ]),
        ]);
    }

    // =========================================================================
    // CREATE  (standalone create page — not used in this app; modal is primary)
    // =========================================================================

    public function create($type)
    {
        $accounts   = ChartOfAccounts::orderBy('account_code')->get();
        $validTypes = self::TYPES;
        return view('vouchers.create', compact('accounts', 'type', 'validTypes'));
    }

    // =========================================================================
    // EDIT  (standalone edit page — not used in this app; modal is primary)
    // =========================================================================

    public function edit($type, $id)
    {
        $voucher    = Voucher::findOrFail($id);
        $accounts   = ChartOfAccounts::orderBy('account_code')->get();
        $validTypes = self::TYPES;
        return view('vouchers.edit', compact('voucher', 'accounts', 'type', 'validTypes'));
    }

    // =========================================================================
    // STORE
    //
    // Accepts manual payment/receipt/journal vouchers.
    // The debit and credit accounts are selected from Chart of Accounts —
    // this covers ALL payment channels:
    //   Cash    → select 101001 Cash in Hand
    //   Bank    → select 102001 Meezan Bank / 102002 HBL Bank etc.
    //   Cheque  → select the bank COA account (same as bank transfer)
    //   Vendor  → select vendor AP account (205001 etc.)
    //   Customer→ select customer AR account (103001 etc.)
    // =========================================================================

    public function store(Request $request, $type)
    {
        try {
            $request->validate([
                'voucher_date' => 'required|date',
                'ac_dr_sid'    => 'required|exists:chart_of_accounts,id',
                'ac_cr_sid'    => 'required|exists:chart_of_accounts,id|different:ac_dr_sid',
                'amount'       => 'required|numeric|min:0.01',
                'remarks'      => 'nullable|string|max:1000',
                'att.*'        => 'nullable|file|max:5120',
            ]);

            $attachments = [];
            if ($request->hasFile('att')) {
                foreach ($request->file('att') as $file) {
                    $attachments[] = $file->store("attachments/{$type}", 'public');
                }
            }

            Voucher::create([
                'voucher_no'   => Voucher::generateVoucherNo($type),
                'voucher_type' => $type,
                'voucher_date' => $request->voucher_date,
                'ac_dr_sid'    => $request->ac_dr_sid,
                'ac_cr_sid'    => $request->ac_cr_sid,
                'amount'       => $request->amount,
                'remarks'      => $request->remarks,
                'attachments'  => $attachments ?: null,
                'created_by'   => auth()->id(),
            ]);

            return back()->with('success', ucfirst($type) . ' voucher added successfully.');

        } catch (\Throwable $e) {
            Log::error("VoucherController::store [{$type}] — " . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function update(Request $request, $type, $id)
    {
        try {
            $voucher = Voucher::findOrFail($id);

            // Auto-generated vouchers cannot be edited here — edit the source invoice
            if (!$voucher->isSimple()) {
                return back()->with('error',
                    'This voucher was auto-generated from ' .
                    class_basename($voucher->reference_type ?? 'an invoice') .
                    '. Edit the source document to change its accounting entries.'
                );
            }

            $request->validate([
                'voucher_date' => 'required|date',
                'ac_dr_sid'    => 'required|exists:chart_of_accounts,id',
                'ac_cr_sid'    => 'required|exists:chart_of_accounts,id|different:ac_dr_sid',
                'amount'       => 'required|numeric|min:0.01',
                'remarks'      => 'nullable|string|max:1000',
                'att.*'        => 'nullable|file|max:5120',
            ]);

            $attachments = $voucher->attachments ?? [];
            if ($request->hasFile('att')) {
                foreach ($request->file('att') as $file) {
                    $attachments[] = $file->store("attachments/{$type}", 'public');
                }
            }

            $voucher->update([
                'voucher_date' => $request->voucher_date,
                'ac_dr_sid'    => $request->ac_dr_sid,
                'ac_cr_sid'    => $request->ac_cr_sid,
                'amount'       => $request->amount,
                'remarks'      => $request->remarks,
                'attachments'  => $attachments ?: null,
            ]);

            return back()->with('success', ucfirst($type) . ' voucher updated successfully.');

        } catch (\Throwable $e) {
            Log::error("VoucherController::update [{$type}] ID {$id} — " . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // DESTROY
    // =========================================================================

    public function destroy($type, $id)
    {
        try {
            $voucher = Voucher::findOrFail($id);

            // Delete any uploaded attachments
            if (!empty($voucher->attachments)) {
                foreach ($voucher->attachments as $file) {
                    if (Storage::disk('public')->exists($file)) {
                        Storage::disk('public')->delete($file);
                    }
                }
            }

            // For complex vouchers also delete the accounting_entries rows
            if (!$voucher->isSimple()) {
                AccountingEntry::where('voucher_id', $voucher->id)->delete();
            }

            $voucher->delete();

            return back()->with('success', ucfirst($type) . ' voucher deleted successfully.');

        } catch (\Throwable $e) {
            Log::error("VoucherController::destroy [{$type}] ID {$id} — " . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // PRINT — PDF voucher, supports both simple and complex vouchers
    // =========================================================================

    public function print($type, $id)
    {
        $voucher = Voucher::with([
            'debitAccount',
            'creditAccount',
            'entries.account',
            'reference',
            'creator',
        ])->findOrFail($id);

        // ── Reference document label ──────────────────────────────────────────
        $docNo     = $voucher->reference?->invoice_no ?? $voucher->reference?->order_no ?? null;
        $refString = $docNo;

        // ── Build unified debit / credit row collections ──────────────────────
        if ($voucher->isSimple()) {
            $debitLines  = collect([[
                'account'   => $voucher->debitAccount->name  ?? '-',
                'amount'    => $voucher->amount ?? 0,
                'narration' => $voucher->remarks ?? '',
            ]]);
            $creditLines = collect([[
                'account'   => $voucher->creditAccount->name ?? '-',
                'amount'    => $voucher->amount ?? 0,
                'narration' => '',
            ]]);
            $printTotal = (float) ($voucher->amount ?? 0);
        } else {
            $debitEntries  = $voucher->entries->where('debit',  '>', 0)->values();
            $creditEntries = $voucher->entries->where('credit', '>', 0)->values();

            $debitLines  = $debitEntries->map(fn($e) => [
                'account'   => $e->account->name ?? '-',
                'amount'    => (float) $e->debit,
                'narration' => $e->narration ?? '',
            ]);
            $creditLines = $creditEntries->map(fn($e) => [
                'account'   => $e->account->name ?? '-',
                'amount'    => (float) $e->credit,
                'narration' => $e->narration ?? '',
            ]);
            $printTotal = (float) $debitEntries->sum('debit');
        }

        // ── PDF setup — same as PurchaseInvoiceController ─────────────────────
        // FIX: was "new MyPDF()" — correct class name is "new myPDF()" (lowercase m)
        $pdf = new myPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('ERP');
        $pdf->SetTitle(strtoupper($type) . ' Voucher - ' . $voucher->voucher_no);
        $pdf->SetMargins(10, 10, 10);
        $pdf->setCellPadding(1.2);
        $pdf->AddPage();

        // ── Company header ────────────────────────────────────────────────────
        $logoPath = public_path('assets/img/mj-logo.jpeg');
        $logoHtml = file_exists($logoPath) ? '<img src="' . $logoPath . '" width="85">' : '';

        $pdf->writeHTML('
            <table width="100%" cellpadding="3">
                <tr>
                    <td width="40%">' . $logoHtml . '</td>
                    <td width="60%" style="text-align:right;font-size:10px;">
                        <strong>MUSFIRA JEWELRY L.L.C</strong><br>
                        Suite #M04, Mezzanine floor, Al Buteen 2 Building, Gold Souq. Gate no.1, Deira, Dubai<br>
                        TRN No: 104902647700003
                    </td>
                </tr>
            </table><hr>', true, false, false, false);

        // ── Voucher title ─────────────────────────────────────────────────────
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, strtoupper($type) . ' VOUCHER', 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 9);

        // ── Meta block ───────────────────────────────────────────────────────
        $metaHtml = '
        <table cellpadding="3" width="100%">
            <tr>
                <td width="50%">
                    ' . ($refString ? '<b>Reference:</b> ' . htmlspecialchars($refString) . '<br>' : '') . '
                    ' . (!empty($voucher->remarks) ? '<b>Remarks:</b> ' . htmlspecialchars($voucher->remarks) : '') . '
                </td>
                <td width="50%">
                    <table border="1" cellpadding="3" width="100%">
                        <tr>
                            <td width="45%"><b>Date</b></td>
                            <td width="55%">' . $voucher->voucher_date?->format('d.m.Y') . '</td>
                        </tr>
                        <tr>
                            <td><b>Voucher No</b></td>
                            <td>' . $voucher->voucher_no . '</td>
                        </tr>
                        <tr>
                            <td><b>Type</b></td>
                            <td>' . ucfirst($type) . '</td>
                        </tr>
                        ' . ($voucher->creator ? '
                        <tr>
                            <td><b>Prepared By</b></td>
                            <td>' . htmlspecialchars($voucher->creator->name) . '</td>
                        </tr>' : '') . '
                    </table>
                </td>
            </tr>
        </table>';
        $pdf->writeHTML($metaHtml, true, false, false, false);
        $pdf->Ln(2);

        // ── Accounting entries table ──────────────────────────────────────────
        $maxRows  = max($debitLines->count(), $creditLines->count());
        $rowsHtml = '';

        for ($i = 0; $i < $maxRows; $i++) {
            $dr  = $debitLines->get($i);
            $cr  = $creditLines->get($i);
            $bg  = ($i % 2 === 0) ? '#ffffff' : '#f9f9f9';

            $rowsHtml .= '
            <tr style="background-color:' . $bg . ';text-align:center;">
                <td width="4%">' . ($i + 1) . '</td>
                <td width="20%" style="text-align:left;">'         . htmlspecialchars($dr['account']   ?? '') . '</td>
                <td width="20%" style="text-align:left;color:#555;">' . htmlspecialchars($cr['account'] ?? '') . '</td>
                <td width="13%" style="text-align:right;">'        . ($dr ? number_format($dr['amount'], 2) : '') . '</td>
                <td width="13%" style="text-align:right;">'        . ($cr ? number_format($cr['amount'], 2) : '') . '</td>
                <td width="30%" style="text-align:left;font-size:8px;color:#666;">'
                    . htmlspecialchars($dr['narration'] ?? $cr['narration'] ?? '') . '</td>
            </tr>';
        }

        $tableHtml = '
        <table border="1" cellpadding="3" width="100%" style="font-size:9px;">
            <thead>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="4%">#</th>
                    <th width="20%">Particulars (Dr)</th>
                    <th width="20%">Particulars (Cr)</th>
                    <th width="13%">Debit (AED)</th>
                    <th width="13%">Credit (AED)</th>
                    <th width="30%">Narration</th>
                </tr>
            </thead>
            <tbody>' . $rowsHtml . '</tbody>
            <tfoot>
                <tr style="font-weight:bold;background-color:#f5f5f5;">
                    <td colspan="3" align="right">TOTAL</td>
                    <td align="right">' . number_format($printTotal, 2) . '</td>
                    <td align="right">' . number_format($printTotal, 2) . '</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>';

        $pdf->writeHTML($tableHtml, true, false, false, false);

        // ── Amount in words ───────────────────────────────────────────────────
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'B', 9);
        $words = $pdf->convertCurrencyToWords($printTotal, 'AED');
        $pdf->Cell(0, 5, 'Amount in Words (AED): ' . $words, 0, 1, 'L');

        $pdf->Ln(2);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(3);

        // ── Signatures ───────────────────────────────────────────────────────
        $pdf->Ln(35);
        $y = $pdf->GetY();
        $pdf->Line(20,  $y, 80,  $y);
        $pdf->Line(130, $y, 190, $y);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY(20,  $y + 1); $pdf->Cell(60, 5, 'Prepared By',          0, 0, 'C');
        $pdf->SetXY(130, $y + 1); $pdf->Cell(60, 5, 'Authorized Signature', 0, 0, 'C');

        return $pdf->Output(strtolower($type) . '_' . $voucher->voucher_no . '.pdf', 'I');
    }
}