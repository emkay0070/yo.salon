<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Journal\JournalEntry;
use App\Domain\Finance\Ledger\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LedgerController extends Controller
{
    /**
     * Get ledger entries representing the economic truth of the salon.
     */
    public function index(Request $request): JsonResponse
    {
        $salon = auth()->user()->currentSalon();
        if (!$salon) {
            return response()->json(['message' => 'No salon associated with your account'], 403);
        }

        // We fetch JournalEntries that belong to this salon's tenant
        // Assuming JournalEntry is linked to tenant/salon context via tenant_id or salon_id.
        // Or we can fetch LedgerEntries that belong to the salon's LedgerAccounts.
        
        $query = LedgerEntry::with(['journalEntry', 'ledgerAccount', 'reference'])
            ->whereHas('ledgerAccount', function ($q) use ($salon) {
                $q->where('owner_type', \App\Models\Salon::class)
                  ->where('owner_id', $salon->id);
            });

        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        return response()->json($query->latest()->get());
    }
}
