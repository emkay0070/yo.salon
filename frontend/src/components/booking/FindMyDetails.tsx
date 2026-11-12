'use client';

import { useState } from 'react';
import { User, Phone, Check, X } from 'lucide-react';
import { useMutation } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';

interface FindMyDetailsProps {
  providerId?: string;
  locationId?: string;
  onCustomerFound: (customer: { id: string; name: string; phone: string; email: string }) => void;
  onCancel: () => void;
}

export default function FindMyDetails({ providerId, locationId, onCustomerFound, onCancel }: FindMyDetailsProps) {
  const [phone, setPhone] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const findMutation = useMutation({
    mutationFn: async (data: { phone: string; provider_id?: string; location_id?: string }) => {
      return await apiClient.findCustomerDetails(data.phone, data.provider_id, data.location_id);
    },
    onSuccess: (response) => {
      if (response.found && response.customer) {
        onCustomerFound(response.customer);
      } else {
        setError("We couldn't find an account with that number. You can continue as a new customer.");
      }
      setLoading(false);
    },
    onError: (err: any) => {
      setError('Something went wrong. Please try again.');
      setLoading(false);
    },
  });

  const handleFind = () => {
    if (!phone.trim()) {
      setError('Please enter your phone number.');
      return;
    }

    setError('');
    setLoading(true);
    findMutation.mutate({ phone, provider_id: providerId, location_id: locationId });
  };

  const handleContinueAsNew = () => {
    onCancel();
  };

  return (
    <div className="bg-card border border-border-light rounded-2xl p-4 lg:p-6 backdrop-blur-2xl">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-full bg-gold/10 flex items-center justify-center">
            <User className="w-5 h-5 text-gold" />
          </div>
          <div>
            <h3 className="text-lg font-semibold text-text-primary">Find your details</h3>
            <p className="text-text-secondary text-sm">We'll use your existing information to make booking faster.</p>
          </div>
        </div>
        <button
          onClick={onCancel}
          className="p-2 rounded-lg hover:bg-white/10 transition-colors"
        >
          <X className="w-5 h-5 text-text-secondary" />
        </button>
      </div>

      <div className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-text-primary mb-2">Phone number</label>
          <div className="relative">
            <Phone className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-text-secondary" />
            <input
              type="tel"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              placeholder="+256 7XX XXX XXX"
              className="w-full pl-10 pr-4 py-3 rounded-xl bg-white/5 border border-border-light text-text-primary placeholder-text-secondary focus:outline-none focus:border-gold/50 transition-colors"
              disabled={loading}
            />
          </div>
        </div>

        {error && (
          <div className="bg-red-500/10 border border-red-500/30 rounded-xl p-3">
            <p className="text-red-400 text-sm">{error}</p>
          </div>
        )}

        <div className="flex gap-3">
          <button
            onClick={handleFind}
            disabled={loading}
            className="flex-1 py-3 rounded-xl font-medium transition-all disabled:opacity-50 disabled:cursor-not-allowed bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-obsidian hover:opacity-90 text-sm lg:text-base flex items-center justify-center gap-2"
          >
            {loading ? (
              <span>Finding...</span>
            ) : (
              <>
                <Check className="w-4 h-4" />
                Find me
              </>
            )}
          </button>
          <button
            onClick={handleContinueAsNew}
            disabled={loading}
            className="px-4 py-3 rounded-xl font-medium transition-all disabled:opacity-50 disabled:cursor-not-allowed bg-white/10 text-text-primary hover:bg-white/20 text-sm lg:text-base"
          >
            Continue as new
          </button>
        </div>
      </div>
    </div>
  );
}
