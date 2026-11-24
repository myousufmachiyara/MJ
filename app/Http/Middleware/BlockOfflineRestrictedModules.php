<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * OFFLINE MODE ONLY (config('app.offline_mode') — see config/app.php).
 *
 * Purchase Invoices and Purchase Returns create new barcoded stock. Doing
 * that offline risks two separate, unreconcilable numbering sequences for
 * the same physical item if the client also receives stock on the live
 * system before syncing — so these modules are simply unavailable in an
 * offline build. (Inbound Consignments carry the same risk and are blocked
 * separately, inside ConsignmentController::validateConsignment(), since
 * their create/edit form is shared with the allowed outbound direction and
 * can't be blocked by route name alone.)
 *
 * This is a route-name check, not a controller edit, so it never touches
 * PurchaseInvoiceController/PurchaseReturnController or routes/web.php.
 * Registered globally in bootstrap/app.php.
 */
class BlockOfflineRestrictedModules
{
    /**
     * Route name prefixes that are off-limits in an offline build.
     */
    private const BLOCKED_PREFIXES = [
        'purchase_invoices.',
        'purchase_invoice_items.',
        'purchase_return.',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (!config('app.offline_mode')) {
            return $next($request);
        }

        $routeName = $request->route()?->getName() ?? '';

        foreach (self::BLOCKED_PREFIXES as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return redirect()
                    ->route('dashboard')
                    ->with('error', 'Purchasing is only available when connected to the live system. Please use it once you\'re back online.');
            }
        }

        return $next($request);
    }
}
