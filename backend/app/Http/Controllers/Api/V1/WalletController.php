<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Get wallet details
     */
    public function index(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        $customerId = $request->attributes->get('customer_id');

        try {
            $wallet = $this->walletService->getWallet($customerId, $salonId);
            $transactions = $this->walletService->getTransactions($customerId, $salonId, 5);

            return response()->json([
                'wallet' => $wallet,
                'recent_transactions' => $transactions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get wallet',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add funds to wallet
     */
    public function addFunds(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string',
        ]);

        $salonId = $request->attributes->get('salon_id');
        $customerId = $request->attributes->get('customer_id');

        try {
            $wallet = $this->walletService->addFunds(
                $customerId,
                $salonId,
                $validated['amount'],
                $validated['description']
            );

            return response()->json([
                'message' => 'Funds added successfully',
                'wallet' => $wallet,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add funds',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        $customerId = $request->attributes->get('customer_id');
        $limit = $request->query('limit', 20);

        try {
            $transactions = $this->walletService->getTransactions($customerId, $salonId, $limit);

            return response()->json([
                'transactions' => $transactions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get transactions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
