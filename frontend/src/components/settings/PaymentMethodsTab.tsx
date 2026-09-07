'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { Loader2, Settings, CreditCard } from 'lucide-react';
import { useRole } from '@/contexts/RoleContext';

export default function PaymentMethodsTab() {
  const { salonId } = useRole();

  const { data: paymentMethods, isLoading } = useQuery({
    queryKey: ['payment-methods'],
    queryFn: () => apiClient.getPaymentMethods(),
  });

  const [configuring, setConfiguring] = useState<string | null>(null);

  if (isLoading) {
    return (
      <div className="flex justify-center items-center py-20">
        <Loader2 className="w-8 h-8 text-[#FFD700] animate-spin" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <h2 className="text-xl font-semibold text-text-primary mb-6 flex items-center gap-2">
        <CreditCard className="w-5 h-5 text-[#FFD700]" />
        Payment Methods
      </h2>

      <div className="bg-gradient-to-br from-[#FFD700]/10 via-[#FFD700]/5 to-transparent border border-[#FFD700]/25 rounded-xl p-6">
        <div className="flex items-start gap-4">
          <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center flex-shrink-0">
            <Settings className="w-6 h-6 text-black" />
          </div>
          <div className="flex-1">
            <h3 className="text-text-primary font-semibold mb-1">Configure Payment Providers</h3>
            <p className="text-text-secondary text-sm mb-4">Set up MTN MoMo, Airtel, and other payment providers for your salon.</p>
          </div>
        </div>
      </div>

      <div className="space-y-4">
        {/* Placeholder for MTN MoMo */}
        <div className="flex items-center justify-between py-3 border-b border-border-light">
          <div>
            <p className="text-text-primary font-medium">MTN MoMo</p>
            <p className="text-text-secondary text-xs">Configure MTN Mobile Money credentials</p>
          </div>
          <button 
            onClick={() => setConfiguring('mtn')}
            className="text-[#FFD700] text-sm font-medium hover:underline"
          >
            Configure →
          </button>
        </div>

        {/* Placeholder for Airtel Money */}
        <div className="flex items-center justify-between py-3 border-b border-border-light">
          <div>
            <p className="text-text-primary font-medium">Airtel Money</p>
            <p className="text-text-secondary text-xs">Configure Airtel Money credentials</p>
          </div>
          <button 
            onClick={() => setConfiguring('airtel')}
            className="text-[#FFD700] text-sm font-medium hover:underline"
          >
            Configure →
          </button>
        </div>

        {/* Manual Payment */}
        <div className="flex items-center justify-between py-3">
          <div>
            <p className="text-text-primary font-medium">Manual Payment</p>
            <p className="text-text-secondary text-xs">Configure instructions for manual payments</p>
          </div>
          <button 
            onClick={() => setConfiguring('manual')}
            className="text-[#FFD700] text-sm font-medium hover:underline"
          >
            Configure →
          </button>
        </div>
      </div>
    </div>
  );
}
