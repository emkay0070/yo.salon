import React, { useState, useEffect } from 'react';
import { X, CheckCircle, Smartphone, Landmark, AlertCircle } from 'lucide-react';
import { PaymentProfile } from './PaymentProfileCard';
import { apiClient } from '@/lib/api-client';

interface AddPaymentProfileModalProps {
  existingProfile?: PaymentProfile | null;
  onClose: () => void;
  onSaved: () => void;
}

export default function AddPaymentProfileModal({ existingProfile, onClose, onSaved }: AddPaymentProfileModalProps) {
  const [step, setStep] = useState(existingProfile ? 2 : 1);
  const [method, setMethod] = useState<'mtn' | 'airtel' | 'bank'>(existingProfile?.method || 'mtn');
  const [formData, setFormData] = useState({
    phone_number: existingProfile?.phone_number || '',
    account_number: existingProfile?.account_number || '',
    account_name: existingProfile?.account_name || '',
    bank_name: existingProfile?.bank_name || '',
    label: existingProfile?.label || '',
    is_default: existingProfile ? existingProfile.is_default : true,
  });
  
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleNext = () => {
    setStep(2);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const payload = {
        method,
        ...formData
      };

      if (existingProfile) {
        await apiClient.updateSpecialistPaymentProfile(existingProfile.id, payload);
      } else {
        await apiClient.createSpecialistPaymentProfile(payload);
      }
      
      onSaved();
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to save payment profile');
      setLoading(false);
    }
  };

  const renderStep1 = () => (
    <div className="space-y-4">
      <h3 className="text-lg font-medium text-text-primary">Select Payout Method</h3>
      <p className="text-sm text-text-secondary">How would you like to receive your earnings?</p>
      
      <div className="grid grid-cols-1 gap-3">
        <div
          onClick={() => setMethod('mtn')}
          className={`cursor-pointer rounded-lg border p-4 flex items-center gap-3 transition-colors ${
            method === 'mtn' ? 'border-indigo-600 bg-indigo-50/50' : 'border-border-light hover:border-border-hover'
          }`}
        >
          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-yellow-400 text-black">
            <Smartphone className="h-5 w-5" />
          </div>
          <div>
            <p className="font-medium text-text-primary">MTN Mobile Money</p>
            <p className="text-xs text-text-secondary">Fastest • Instant transfer</p>
          </div>
          {method === 'mtn' && <CheckCircle className="ml-auto h-5 w-5 text-indigo-600" />}
        </div>

        <div
          onClick={() => setMethod('airtel')}
          className={`cursor-pointer rounded-lg border p-4 flex items-center gap-3 transition-colors ${
            method === 'airtel' ? 'border-indigo-600 bg-indigo-50/50' : 'border-border-light hover:border-border-hover'
          }`}
        >
          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-500 text-white">
            <Smartphone className="h-5 w-5" />
          </div>
          <div>
            <p className="font-medium text-text-primary">Airtel Money</p>
            <p className="text-xs text-text-secondary">Fastest • Instant transfer</p>
          </div>
          {method === 'airtel' && <CheckCircle className="ml-auto h-5 w-5 text-indigo-600" />}
        </div>

        <div
          onClick={() => setMethod('bank')}
          className={`cursor-pointer rounded-lg border p-4 flex items-center gap-3 transition-colors ${
            method === 'bank' ? 'border-indigo-600 bg-indigo-50/50' : 'border-border-light hover:border-border-hover'
          }`}
        >
          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-blue-600">
            <Landmark className="h-5 w-5" />
          </div>
          <div>
            <p className="font-medium text-text-primary">Bank Account</p>
            <p className="text-xs text-text-secondary">Takes 1-2 business days</p>
          </div>
          {method === 'bank' && <CheckCircle className="ml-auto h-5 w-5 text-indigo-600" />}
        </div>
      </div>

      <div className="mt-6 flex justify-end gap-3">
        <button
          type="button"
          onClick={onClose}
          className="rounded-lg px-4 py-2 text-sm font-medium text-text-secondary hover:bg-surface"
        >
          Cancel
        </button>
        <button
          type="button"
          onClick={handleNext}
          className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        >
          Continue
        </button>
      </div>
    </div>
  );

  const renderStep2 = () => (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="flex items-center gap-2 mb-2">
        <button 
          type="button"
          onClick={() => !existingProfile && setStep(1)}
          className={`text-sm ${!existingProfile ? 'text-indigo-600 hover:underline' : 'text-text-secondary cursor-default'}`}
        >
          {method === 'mtn' ? 'MTN MoMo' : method === 'airtel' ? 'Airtel Money' : 'Bank Account'}
        </button>
        <span className="text-text-secondary">/</span>
        <span className="text-sm font-medium text-text-primary">Details</span>
      </div>

      {error && (
        <div className="rounded-md bg-red-50 p-3 flex gap-2 items-start">
          <AlertCircle className="h-5 w-5 text-red-600 shrink-0" />
          <p className="text-sm text-red-700">{error}</p>
        </div>
      )}

      {(method === 'mtn' || method === 'airtel') ? (
        <div>
          <label className="block text-sm font-medium text-text-primary mb-1">
            Phone Number
          </label>
          <input
            type="tel"
            required
            placeholder="e.g. 0771234567"
            value={formData.phone_number}
            onChange={(e) => setFormData({ ...formData, phone_number: e.target.value })}
            className="w-full rounded-lg border-border-light bg-surface px-4 py-2 text-text-primary focus:border-indigo-500 focus:ring-indigo-500"
          />
          <p className="text-xs text-text-secondary mt-1">
            This number must be registered for mobile money in your name.
          </p>
        </div>
      ) : (
        <>
          <div>
            <label className="block text-sm font-medium text-text-primary mb-1">
              Bank Name
            </label>
            <input
              type="text"
              required
              placeholder="e.g. Centenary Bank"
              value={formData.bank_name}
              onChange={(e) => setFormData({ ...formData, bank_name: e.target.value })}
              className="w-full rounded-lg border-border-light bg-surface px-4 py-2 text-text-primary focus:border-indigo-500 focus:ring-indigo-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-text-primary mb-1">
              Account Name
            </label>
            <input
              type="text"
              required
              placeholder="Your full name as it appears on the account"
              value={formData.account_name}
              onChange={(e) => setFormData({ ...formData, account_name: e.target.value })}
              className="w-full rounded-lg border-border-light bg-surface px-4 py-2 text-text-primary focus:border-indigo-500 focus:ring-indigo-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-text-primary mb-1">
              Account Number
            </label>
            <input
              type="text"
              required
              value={formData.account_number}
              onChange={(e) => setFormData({ ...formData, account_number: e.target.value })}
              className="w-full rounded-lg border-border-light bg-surface px-4 py-2 text-text-primary focus:border-indigo-500 focus:ring-indigo-500"
            />
          </div>
        </>
      )}

      <div>
        <label className="block text-sm font-medium text-text-primary mb-1">
          Label (Optional)
        </label>
        <input
          type="text"
          placeholder="e.g. Personal Phone, Business Acct"
          value={formData.label}
          onChange={(e) => setFormData({ ...formData, label: e.target.value })}
          className="w-full rounded-lg border-border-light bg-surface px-4 py-2 text-text-primary focus:border-indigo-500 focus:ring-indigo-500"
        />
      </div>

      <div className="flex items-center gap-2 pt-2">
        <input
          type="checkbox"
          id="is_default"
          checked={formData.is_default}
          onChange={(e) => setFormData({ ...formData, is_default: e.target.checked })}
          className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
        />
        <label htmlFor="is_default" className="text-sm text-text-primary">
          Set as default payment method
        </label>
      </div>

      <div className="mt-6 flex justify-end gap-3 pt-4 border-t border-border-light">
        <button
          type="button"
          onClick={onClose}
          className="rounded-lg px-4 py-2 text-sm font-medium text-text-secondary hover:bg-surface"
        >
          Cancel
        </button>
        <button
          type="submit"
          disabled={loading}
          className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-2"
        >
          {loading && <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />}
          {existingProfile ? 'Update Method' : 'Add Method'}
        </button>
      </div>
    </form>
  );

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div className="fixed inset-0 bg-black/60 transition-opacity" onClick={onClose} />
        
        <div className="relative transform overflow-hidden rounded-xl bg-card text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md">
          <div className="flex items-center justify-between border-b border-border-light px-6 py-4">
            <h2 className="text-lg font-semibold text-text-primary">
              {existingProfile ? 'Edit Payment Method' : 'Add Payment Method'}
            </h2>
            <button
              onClick={onClose}
              className="rounded-lg p-2 text-text-secondary hover:bg-surface"
            >
              <X className="h-5 w-5" />
            </button>
          </div>
          
          <div className="p-6">
            {step === 1 ? renderStep1() : renderStep2()}
          </div>
        </div>
      </div>
    </div>
  );
}
