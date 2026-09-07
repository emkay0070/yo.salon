import React, { useState, useEffect } from 'react';
import { X, CheckCircle, CreditCard, Banknote, Landmark, Smartphone, AlertCircle, Zap } from 'lucide-react';
import { apiClient } from '@/lib/api-client';

interface PayoutReviewModalProps {
  salonId: string;
  staff: any;
  settlement: any;
  onClose: () => void;
  onPaid: () => void;
}

export default function PayoutReviewModal({ salonId, staff, settlement, onClose, onPaid }: PayoutReviewModalProps) {
  const [executionMode, setExecutionMode] = useState<'automated' | 'manual'>('manual');
  const [method, setMethod] = useState('cash');
  const [reference, setReference] = useState('');
  
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [readiness, setReadiness] = useState<any>(null);
  const [loadingReadiness, setLoadingReadiness] = useState(true);

  useEffect(() => {
    apiClient.getPayoutReadiness(salonId, settlement.id)
      .then(res => {
        setReadiness(res);
        if (res.automated_available) {
          setExecutionMode('automated');
        }
      })
      .catch(() => {
        // Fallback to manual only if error
        setReadiness({ automated_available: false, reason: 'Failed to check readiness' });
      })
      .finally(() => {
        setLoadingReadiness(false);
      });
  }, [salonId, settlement.id]);

  const amount = Number(settlement.amount);

  const formatCurrency = (amt: number) => {
    return new Intl.NumberFormat('en-UG', { style: 'currency', currency: 'UGX' }).format(amt);
  };

  const handleConfirm = async () => {
    setLoading(true);
    setError(null);

    try {
      if (executionMode === 'automated') {
        // Dispatch automated payout via backend integration
        await apiClient.initiateCompensationPayout(salonId, settlement.id, {
          amount: amount,
          method: readiness?.method,
          execution_mode: 'automated'
        });
      } else {
        // Record manual payout
        await apiClient.initiateCompensationPayout(salonId, settlement.id, {
          amount: amount,
          method: method,
          execution_mode: 'manual', 
          provider_reference: reference || undefined,
        });
      }
      onPaid();
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to record payout');
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={onClose} />
        
        <div className="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
          <div className="absolute right-0 top-0 hidden pr-4 pt-4 sm:block">
            <button
              type="button"
              className="rounded-md bg-white text-gray-400 hover:text-gray-500"
              onClick={onClose}
            >
              <span className="sr-only">Close</span>
              <X className="h-6 w-6" aria-hidden="true" />
            </button>
          </div>
          
          <div className="sm:flex sm:items-start">
            <div className="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
              <h3 className="text-xl font-semibold leading-6 text-gray-900 mb-6">
                Review Payout
              </h3>
              
              {error && (
                <div className="mb-4 rounded-md bg-red-50 p-4">
                  <p className="text-sm text-red-700">{error}</p>
                </div>
              )}

              <div className="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
                <div className="flex justify-between items-center mb-2">
                  <span className="text-sm text-gray-500">Recipient</span>
                  <span className="text-sm font-medium text-gray-900">{staff.user?.name}</span>
                </div>
                <div className="flex justify-between items-center mb-4">
                  <span className="text-sm text-gray-500">Period Ended</span>
                  <span className="text-sm font-medium text-gray-900">
                    {new Date(settlement.created_at).toLocaleDateString()}
                  </span>
                </div>
                <div className="pt-4 border-t border-gray-200 flex justify-between items-center">
                  <span className="text-base font-semibold text-gray-900">Total Payout</span>
                  <span className="text-2xl font-bold text-indigo-600">{formatCurrency(amount)}</span>
                </div>
              </div>

              {loadingReadiness ? (
                <div className="flex justify-center p-4">
                  <div className="h-6 w-6 animate-spin rounded-full border-2 border-indigo-600 border-t-transparent" />
                </div>
              ) : (
                <>
                  <div className="space-y-4 mb-6">
                    <label className="block text-sm font-medium leading-6 text-gray-900">Execution Method</label>
                    
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                      <div
                        onClick={() => readiness?.automated_available && setExecutionMode('automated')}
                        className={`rounded-lg border p-4 flex flex-col items-center justify-center gap-2 ${
                          executionMode === 'automated' 
                            ? 'border-indigo-600 bg-indigo-50 text-indigo-600 ring-1 ring-indigo-600' 
                            : readiness?.automated_available
                              ? 'border-gray-200 hover:border-gray-300 cursor-pointer text-gray-700'
                              : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed opacity-60'
                        }`}
                      >
                        <Zap className="h-6 w-6" />
                        <span className="text-sm font-medium">Automated Transfer</span>
                        {readiness?.automated_available && (
                          <span className="text-xs font-medium text-green-600">Ready</span>
                        )}
                      </div>

                      <div
                        onClick={() => setExecutionMode('manual')}
                        className={`cursor-pointer rounded-lg border p-4 flex flex-col items-center justify-center gap-2 ${
                          executionMode === 'manual' 
                            ? 'border-indigo-600 bg-indigo-50 text-indigo-600 ring-1 ring-indigo-600' 
                            : 'border-gray-200 hover:border-gray-300 text-gray-700'
                        }`}
                      >
                        <Banknote className="h-6 w-6" />
                        <span className="text-sm font-medium">Record Manual Payout</span>
                      </div>
                    </div>

                    {!readiness?.automated_available && executionMode === 'manual' && readiness?.reason && (
                      <div className="mt-2 flex items-start gap-2 text-sm text-amber-700 bg-amber-50 p-3 rounded-lg">
                        <AlertCircle className="h-5 w-5 shrink-0" />
                        <p>{readiness.reason}</p>
                      </div>
                    )}
                  </div>

                  {executionMode === 'automated' && readiness?.profile && (
                    <div className="mb-6 rounded-lg border border-indigo-100 bg-indigo-50 p-4">
                      <h4 className="text-sm font-medium text-indigo-900 mb-2">Transfer Destination</h4>
                      <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-white text-indigo-600 shadow-sm">
                          {readiness.profile.method === 'bank' ? <Landmark className="h-5 w-5" /> : <Smartphone className="h-5 w-5" />}
                        </div>
                        <div>
                          <p className="font-medium text-indigo-900">
                            {readiness.profile.method === 'mtn' ? 'MTN Mobile Money' : readiness.profile.method === 'airtel' ? 'Airtel Money' : 'Bank Account'}
                            {readiness.profile.label ? ` • ${readiness.profile.label}` : ''}
                          </p>
                          <p className="text-sm text-indigo-700">{readiness.profile.destination}</p>
                        </div>
                      </div>
                    </div>
                  )}

                  {executionMode === 'manual' && (
                    <>
                      <div className="space-y-4 mb-6">
                        <label className="block text-sm font-medium leading-6 text-gray-900">Payment Method (How was this paid?)</label>
                        
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                          <div
                            onClick={() => setMethod('cash')}
                            className={`cursor-pointer rounded-lg border p-3 flex flex-col items-center justify-center gap-2 ${method === 'cash' ? 'border-indigo-600 bg-indigo-50 text-indigo-600' : 'border-gray-200 hover:border-gray-300'}`}
                          >
                            <Banknote className="h-5 w-5" />
                            <span className="text-sm font-medium">Cash</span>
                          </div>
                          
                          <div
                            onClick={() => setMethod('bank_transfer')}
                            className={`cursor-pointer rounded-lg border p-3 flex flex-col items-center justify-center gap-2 ${method === 'bank_transfer' ? 'border-indigo-600 bg-indigo-50 text-indigo-600' : 'border-gray-200 hover:border-gray-300'}`}
                          >
                            <Landmark className="h-5 w-5" />
                            <span className="text-sm font-medium">Bank</span>
                          </div>

                          <div
                            onClick={() => setMethod('mobile_money')}
                            className={`cursor-pointer rounded-lg border p-3 flex flex-col items-center justify-center gap-2 ${method === 'mobile_money' ? 'border-indigo-600 bg-indigo-50 text-indigo-600' : 'border-gray-200 hover:border-gray-300'}`}
                          >
                            <CreditCard className="h-5 w-5" />
                            <span className="text-sm font-medium">Mobile Money</span>
                          </div>
                        </div>
                      </div>

                      <div className="mb-6">
                        <label className="block text-sm font-medium leading-6 text-gray-900">
                          Reference / Receipt Number (Optional)
                        </label>
                        <input
                          type="text"
                          value={reference}
                          onChange={(e) => setReference(e.target.value)}
                          placeholder="e.g. TXN-99482 or Check #123"
                          className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                        />
                      </div>
                    </>
                  )}
                </>
              )}

              <div className="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                <button
                  type="button"
                  disabled={loading}
                  onClick={handleConfirm}
                  className="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto disabled:opacity-50"
                >
                  {loading ? (
                    'Recording...'
                  ) : (
                    <>
                      <CheckCircle className="-ml-0.5 mr-1.5 h-4 w-4" />
                      {executionMode === 'automated' ? 'Execute Payout' : 'Confirm Payment'}
                    </>
                  )}
                </button>
                <button
                  type="button"
                  className="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto"
                  onClick={onClose}
                >
                  Cancel
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
