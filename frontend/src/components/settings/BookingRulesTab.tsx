'use client';

import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { Loader2, Save, ShieldAlert } from 'lucide-react';
import { useRole } from '@/contexts/RoleContext';

export default function BookingRulesTab() {
  const { salonId, isLoading: roleLoading } = useRole();
  const queryClient = useQueryClient();
  const [config, setConfig] = useState<any>({
    booking_policy_override: {
      minimum_lead_time_hours: 2,
      maximum_advance_booking_days: 60,
    },
    cancellation_policy_override: {
      free_cancellation_hours: 24,
      late_cancellation_fee_percentage: 50,
      no_show_fee_percentage: 100,
    },
    payment_policy_override: {
      deposit_required: false,
      deposit_percentage: 20,
    }
  });

  const validSalonId = salonId && salonId !== 'null' && salonId !== 'undefined' ? salonId : null;
  const canSave = !!validSalonId && !roleLoading;

  const { data: configData, isLoading } = useQuery({
    queryKey: ['booking-config', validSalonId],
    queryFn: () => apiClient.getBookingConfig(validSalonId!),
    enabled: !!validSalonId && !roleLoading,
  });

  useEffect(() => {
    if (configData?.configuration) {
      setConfig((prev: any) => ({
        ...prev,
        ...configData.configuration
      }));
    }
  }, [configData]);

  const updateMutation = useMutation({
    mutationFn: (newConfig: any) => apiClient.updateBookingConfig(validSalonId!, newConfig),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['booking-config', validSalonId] });
    }
  });

  const handleChange = (category: string, field: string, value: any) => {
    setConfig((prev: any) => ({
      ...prev,
      [category]: {
        ...(prev[category] || {}),
        [field]: value
      }
    }));
  };

  const handleSave = () => {
    if (canSave) updateMutation.mutate(config);
  };

  if (roleLoading || isLoading) {
    return (
      <div className="flex justify-center items-center py-20">
        <Loader2 className="w-8 h-8 text-[#FFD700] animate-spin" />
      </div>
    );
  }

  if (!validSalonId) {
    return (
      <div className="flex flex-col justify-center items-center py-20 text-center space-y-3">
        <ShieldAlert className="w-10 h-10 text-amber-400" />
        <h3 className="text-lg font-semibold text-text-primary">No salon selected</h3>
        <p className="text-sm text-text-secondary max-w-md">
          Salon context is not available. Please sign out and back in, or complete onboarding.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-8">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-xl font-semibold text-text-primary mb-1 flex items-center gap-2">
            <ShieldAlert className="w-5 h-5 text-[#FFD700]" />
            Booking Rules & Policies
          </h2>
          <p className="text-sm text-text-secondary">Configure your rules for booking, cancellations, and deposits.</p>
        </div>
        <button
          onClick={handleSave}
          disabled={!canSave || updateMutation.isPending}
          className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black font-semibold rounded-lg hover:opacity-90 disabled:opacity-50 transition-opacity"
        >
          {updateMutation.isPending ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
          Save Changes
        </button>
      </div>

      {/* Booking Constraints */}
      <div className="bg-white/[0.02] border border-white/5 rounded-xl p-6">
        <h3 className="text-lg font-medium text-text-primary mb-4">Booking Constraints</h3>
        <div className="space-y-5">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">Minimum Lead Time</label>
              <p className="text-xs text-text-secondary">How many hours in advance must clients book?</p>
            </div>
            <div className="flex items-center gap-2">
              <input 
                type="number"
                min="0"
                value={config.booking_policy_override?.minimum_lead_time_hours || 0}
                onChange={(e) => handleChange('booking_policy_override', 'minimum_lead_time_hours', parseInt(e.target.value))}
                className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary w-24 text-center focus:outline-none focus:border-[#FFD700]"
              />
              <span className="text-sm text-text-secondary">hours</span>
            </div>
          </div>
          
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">Maximum Advance Booking</label>
              <p className="text-xs text-text-secondary">How far into the future can clients book?</p>
            </div>
            <div className="flex items-center gap-2">
              <input 
                type="number"
                min="1"
                value={config.booking_policy_override?.maximum_advance_booking_days || 30}
                onChange={(e) => handleChange('booking_policy_override', 'maximum_advance_booking_days', parseInt(e.target.value))}
                className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary w-24 text-center focus:outline-none focus:border-[#FFD700]"
              />
              <span className="text-sm text-text-secondary">days</span>
            </div>
          </div>
        </div>
      </div>

      {/* Cancellation Policy */}
      <div className="bg-white/[0.02] border border-white/5 rounded-xl p-6">
        <h3 className="text-lg font-medium text-text-primary mb-4">Cancellation Policy</h3>
        <div className="space-y-5">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">Free Cancellation Window</label>
              <p className="text-xs text-text-secondary">Hours before appointment clients can cancel for free.</p>
            </div>
            <div className="flex items-center gap-2">
              <input 
                type="number"
                min="0"
                value={config.cancellation_policy_override?.free_cancellation_hours || 24}
                onChange={(e) => handleChange('cancellation_policy_override', 'free_cancellation_hours', parseInt(e.target.value))}
                className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary w-24 text-center focus:outline-none focus:border-[#FFD700]"
              />
              <span className="text-sm text-text-secondary">hours</span>
            </div>
          </div>
          
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">Late Cancellation Fee</label>
              <p className="text-xs text-text-secondary">Percentage of service price charged if cancelled late.</p>
            </div>
            <div className="flex items-center gap-2">
              <input 
                type="number"
                min="0"
                max="100"
                value={config.cancellation_policy_override?.late_cancellation_fee_percentage || 50}
                onChange={(e) => handleChange('cancellation_policy_override', 'late_cancellation_fee_percentage', parseInt(e.target.value))}
                className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary w-24 text-center focus:outline-none focus:border-[#FFD700]"
              />
              <span className="text-sm text-text-secondary">%</span>
            </div>
          </div>
          
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">No-Show Fee</label>
              <p className="text-xs text-text-secondary">Percentage of service price charged for no-shows.</p>
            </div>
            <div className="flex items-center gap-2">
              <input 
                type="number"
                min="0"
                max="100"
                value={config.cancellation_policy_override?.no_show_fee_percentage || 100}
                onChange={(e) => handleChange('cancellation_policy_override', 'no_show_fee_percentage', parseInt(e.target.value))}
                className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary w-24 text-center focus:outline-none focus:border-[#FFD700]"
              />
              <span className="text-sm text-text-secondary">%</span>
            </div>
          </div>
        </div>
      </div>

      {/* Payment & Deposit */}
      <div className="bg-white/[0.02] border border-white/5 rounded-xl p-6">
        <h3 className="text-lg font-medium text-text-primary mb-4">Payment & Deposit</h3>
        <div className="space-y-5">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">Require Deposit</label>
              <p className="text-xs text-text-secondary">Require customers to pay a deposit at booking.</p>
            </div>
            <button
              onClick={() => handleChange('payment_policy_override', 'deposit_required', !config.payment_policy_override?.deposit_required)}
              className={`w-12 h-6 rounded-full transition-colors flex items-center p-0.5 ${
                config.payment_policy_override?.deposit_required ? 'bg-[#FFD700]' : 'bg-[#1a1a1a] border border-white/10'
              }`}
            >
              <div
                className={`w-5 h-5 rounded-full bg-[#0A0A0A] transition-transform ${
                  config.payment_policy_override?.deposit_required ? 'translate-x-6' : 'translate-x-0.5'
                }`}
              />
            </button>
          </div>
          
          {config.payment_policy_override?.deposit_required && (
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mt-4 pt-4 border-t border-white/5">
              <div>
                <label className="block text-sm font-medium text-text-primary mb-1">Deposit Percentage</label>
                <p className="text-xs text-text-secondary">Percentage of the total to charge as deposit.</p>
              </div>
              <div className="flex items-center gap-2">
                <input 
                  type="number"
                  min="1"
                  max="100"
                  value={config.payment_policy_override?.deposit_percentage || 20}
                  onChange={(e) => handleChange('payment_policy_override', 'deposit_percentage', parseInt(e.target.value))}
                  className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary w-24 text-center focus:outline-none focus:border-[#FFD700]"
                />
                <span className="text-sm text-text-secondary">%</span>
              </div>
            </div>
          )}
        </div>
      </div>
      
      {updateMutation.isSuccess && (
        <div className="text-green-400 text-sm text-right mt-2">
          Booking rules successfully updated!
        </div>
      )}
      {updateMutation.isError && (
        <div className="text-red-400 text-sm text-right mt-2">
          Failed to update booking rules. Please try again.
        </div>
      )}
    </div>
  );
}
