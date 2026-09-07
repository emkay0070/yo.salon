import React, { useState } from 'react';
import { X } from 'lucide-react';
import { apiClient } from '@/lib/api-client';

interface PolicyEditorModalProps {
  salonId: string;
  staff: any;
  currentPolicy: any;
  onClose: () => void;
  onSaved: () => void;
}

export default function PolicyEditorModal({ salonId, staff, currentPolicy, onClose, onSaved }: PolicyEditorModalProps) {
  const [type, setType] = useState(currentPolicy?.type || 'commission');
  const [rate, setRate] = useState(currentPolicy?.rules?.rate || 50);
  const [amount, setAmount] = useState(currentPolicy?.rules?.amount || 0);
  const [frequency, setFrequency] = useState(currentPolicy?.settlement_frequency || 'weekly');
  const [effectiveFrom, setEffectiveFrom] = useState(
    currentPolicy?.effective_from ? new Date(currentPolicy.effective_from).toISOString().split('T')[0] : new Date().toISOString().split('T')[0]
  );
  
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    const rules: any = {};
    if (type === 'commission') {
      rules.rate = Number(rate);
    } else if (type === 'salary') {
      rules.amount = Number(amount);
    }

    try {
      const isStaff = staff.type === 'staff';
      await apiClient.createCompensationPolicy(salonId, {
        compensatable_type: isStaff ? 'App\\Models\\Staff' : 'App\\Models\\Specialist',
        compensatable_id: staff.id,
        type,
        rules,
        settlement_frequency: frequency,
        effective_from: effectiveFrom
      });
      onSaved();
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to save policy');
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
              <h3 className="text-base font-semibold leading-6 text-gray-900">
                Update Compensation Terms
              </h3>
              <p className="text-sm text-gray-500 mb-4">
                Changes will automatically supersede the current active policy starting on the effective date.
              </p>
              
              {error && (
                <div className="mb-4 rounded-md bg-red-50 p-4">
                  <p className="text-sm text-red-700">{error}</p>
                </div>
              )}

              <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                  <label className="block text-sm font-medium leading-6 text-gray-900">Compensation Type</label>
                  <select
                    value={type}
                    onChange={(e) => setType(e.target.value)}
                    className="mt-2 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
                  >
                    <option value="commission">Commission</option>
                    <option value="salary">Fixed Salary</option>
                  </select>
                </div>

                {type === 'commission' && (
                  <div>
                    <label className="block text-sm font-medium leading-6 text-gray-900">Commission Rate (%)</label>
                    <input
                      type="number"
                      min="0"
                      max="100"
                      value={rate}
                      onChange={(e) => setRate(e.target.value)}
                      className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                    />
                  </div>
                )}

                {type === 'salary' && (
                  <div>
                    <label className="block text-sm font-medium leading-6 text-gray-900">Salary Amount (UGX)</label>
                    <input
                      type="number"
                      min="0"
                      value={amount}
                      onChange={(e) => setAmount(e.target.value)}
                      className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                    />
                  </div>
                )}

                <div>
                  <label className="block text-sm font-medium leading-6 text-gray-900">Settlement Frequency</label>
                  <select
                    value={frequency}
                    onChange={(e) => setFrequency(e.target.value)}
                    className="mt-2 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
                  >
                    <option value="weekly">Weekly</option>
                    <option value="biweekly">Bi-weekly</option>
                    <option value="monthly">Monthly</option>
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium leading-6 text-gray-900">Effective Date</label>
                  <input
                    type="date"
                    value={effectiveFrom}
                    onChange={(e) => setEffectiveFrom(e.target.value)}
                    className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                  />
                  <p className="mt-1 text-xs text-gray-500">
                    If this date is in the future, it will automatically activate on that day.
                  </p>
                </div>

                <div className="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                  <button
                    type="submit"
                    disabled={loading}
                    className="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto disabled:opacity-50"
                  >
                    {loading ? 'Saving...' : 'Save Terms'}
                  </button>
                  <button
                    type="button"
                    className="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto"
                    onClick={onClose}
                  >
                    Cancel
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
