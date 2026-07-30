'use client';

import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  X, Banknote, Smartphone, CreditCard, Wifi,
  CheckCircle, ArrowLeft, Loader2, ArrowRight
} from 'lucide-react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';

type ProviderId = 'cash' | 'mtn' | 'airtel' | 'flutterwave' | 'visa';
type WizardStep = 'choose' | 'explain' | 'configure' | 'success';

interface AddMethodWizardProps {
  isOpen: boolean;
  onClose: () => void;
  salonId: string;
}

const PROVIDERS: Record<ProviderId, {
  id: ProviderId;
  name: string;
  type: string;
  Icon: React.ElementType;
  description: string;
  color: string;
  gradient: string;
  fields: { name: string; label: string; placeholder: string; type: string; optional?: boolean }[];
  requiresApiCredentials?: boolean;
}> = {
  mtn: {
    id: 'mtn',
    name: 'MTN Mobile Money',
    type: 'mobile_money',
    Icon: Smartphone,
    description: 'Connect your MTN MoMo Business account. Customers will receive payment prompts directly on their phones. Funds settle into your MTN Business account.',
    color: 'text-[var(--color-gold)]',
    gradient: 'from-[#C9A227]/20 to-[#FFD700]/5',
    requiresApiCredentials: true,
    fields: [
      { name: 'merchant_id', label: 'API User / Merchant ID', placeholder: 'e.g. your_mtn_api_user', type: 'text' },
      { name: 'api_key', label: 'API Key', placeholder: 'e.g. your_mtn_api_key', type: 'text' },
      { name: 'api_secret', label: 'API Secret', placeholder: 'e.g. your_mtn_api_secret', type: 'password' },
      { name: 'api_subscription_key', label: 'Subscription Key', placeholder: 'e.g. your_subscription_key', type: 'text' },
      { name: 'environment', label: 'Environment', placeholder: 'sandbox', type: 'text' }
    ]
  },
  airtel: {
    id: 'airtel',
    name: 'Airtel Money',
    type: 'mobile_money',
    Icon: Smartphone,
    description: 'Connect your Airtel Money Business account. Customers will receive payment prompts directly on their phones. Funds settle into your Airtel Merchant account.',
    color: 'text-red-400',
    gradient: 'from-red-900/40 to-red-900/10',
    requiresApiCredentials: true,
    fields: [
      { name: 'api_key', label: 'Client ID', placeholder: 'e.g. your_airtel_client_id', type: 'text' },
      { name: 'api_secret', label: 'Client Secret', placeholder: 'e.g. your_airtel_client_secret', type: 'password' },
      { name: 'environment', label: 'Environment', placeholder: 'sandbox', type: 'text' }
    ]
  },
  flutterwave: {
    id: 'flutterwave',
    name: 'Flutterwave',
    type: 'gateway',
    Icon: Wifi,
    description: 'Accept global card payments, Apple Pay, and Google Pay via secure payment links sent to your customers.',
    color: 'text-[#A29BFE]',
    gradient: 'from-[#6C5CE7]/30 to-[#A29BFE]/10',
    requiresApiCredentials: true,
    fields: [
      { name: 'api_key', label: 'Public Key', placeholder: 'FLWPUBK-XXXXXXXXX', type: 'text' },
      { name: 'api_secret', label: 'Secret Key', placeholder: 'FLWSECK-XXXXXXXXX', type: 'password' },
      { name: 'environment', label: 'Environment', placeholder: 'sandbox', type: 'text' }
    ]
  },
  cash: {
    id: 'cash',
    name: 'Cash Register',
    type: 'cash',
    Icon: Banknote,
    description: 'Enable your front desk to manually record physical cash payments and generate receipts instantly.',
    color: 'text-emerald-400',
    gradient: 'from-emerald-900/40 to-emerald-900/10',
    fields: [
      { name: 'account_name', label: 'Register Name', placeholder: 'e.g. Main Front Desk', type: 'text' }
    ]
  },
  visa: {
    id: 'visa',
    name: 'Physical Card Terminal',
    type: 'card',
    Icon: CreditCard,
    description: 'Manually record payments processed through your physical POS terminal (PDQ machine).',
    color: 'text-blue-400',
    gradient: 'from-blue-900/50 to-indigo-900/20',
    fields: [
      { name: 'account_name', label: 'Terminal Name', placeholder: 'e.g. Stanbic POS 1', type: 'text' },
      { name: 'account_identifier', label: 'Terminal ID', placeholder: 'e.g. TID-1234', type: 'text', optional: true }
    ]
  }
};

export default function AddMethodWizard({ isOpen, onClose, salonId }: AddMethodWizardProps) {
  const [step, setStep] = useState<WizardStep>('choose');
  const [selectedProvider, setSelectedProvider] = useState<ProviderId | null>(null);
  const [formData, setFormData] = useState<Record<string, string>>({});
  const [error, setError] = useState('');
  const queryClient = useQueryClient();

  useEffect(() => {
    if (isOpen) {
      setStep('choose');
      setSelectedProvider(null);
      setFormData({});
      setError('');
    }
  }, [isOpen]);

  const provider = selectedProvider ? PROVIDERS[selectedProvider] : null;

  const mutation = useMutation({
    mutationFn: (data: any) => apiClient.addPaymentMethod(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['payment-methods'] });
      setStep('success');
    },
    onError: (error: any) => {
      setError(error.response?.data?.message || 'Failed to connect payment method. Please try again.');
    }
  });

  const testConnectionMutation = useMutation({
    mutationFn: (data: any) => apiClient.testPaymentMethodConnection(data),
    onSuccess: () => {
      setError('');
      // Proceed to save after successful test
      mutation.mutate({
        salon_id: salonId,
        provider: selectedProvider,
        type: provider?.type,
        display_name: provider?.name,
        account_name: formData.account_name || undefined,
        account_identifier: formData.account_identifier || undefined,
        merchant_id: formData.merchant_id || undefined,
        api_key: formData.api_key || undefined,
        api_secret: formData.api_secret || undefined,
        api_subscription_key: formData.api_subscription_key || undefined,
        environment: formData.environment || 'sandbox',
        currency: 'UGX',
      });
    },
    onError: (error: any) => {
      setError(error.response?.data?.message || 'Connection test failed. Please check your credentials.');
    }
  });

  const handleNext = () => {
    setError('');
    if (step === 'choose' && selectedProvider) {
      setStep('explain');
    } else if (step === 'explain') {
      setStep('configure');
    } else if (step === 'configure') {
      // Validate
      const missing = provider?.fields.find(f => !f.optional && !formData[f.name]);
      if (missing) {
        setError(`Please provide ${missing.label}`);
        return;
      }
      
      // Test connection first for providers that require API credentials
      if (provider?.requiresApiCredentials) {
        testConnectionMutation.mutate({
          provider: selectedProvider,
          merchant_id: formData.merchant_id,
          api_key: formData.api_key,
          api_secret: formData.api_secret,
          api_subscription_key: formData.api_subscription_key,
          environment: formData.environment || 'sandbox',
        });
      } else {
        // Direct save for non-API providers (cash, card terminal)
        mutation.mutate({
          salon_id: salonId,
          provider: selectedProvider,
          type: provider?.type,
          display_name: provider?.name,
          account_name: formData.account_name || undefined,
          account_identifier: formData.account_identifier || undefined,
          currency: 'UGX',
        });
      }
    }
  };

  const handleClose = () => {
    if (!mutation.isPending) {
      onClose();
    }
  };

  return (
    <AnimatePresence>
      {isOpen && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          onClick={handleClose}
          className="fixed inset-0 bg-[var(--color-overlay)] backdrop-blur-sm z-50 flex items-center justify-center p-4"
        >
          <motion.div
            initial={{ opacity: 0, y: 40, scale: 0.95 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 40, scale: 0.95 }}
            transition={{ type: 'spring', stiffness: 400, damping: 35 }}
            onClick={e => e.stopPropagation()}
            className="w-full max-w-lg bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-3xl overflow-hidden shadow-2xl relative"
          >
            {/* Header */}
            {step !== 'success' && (
              <div className="flex items-center justify-between p-6 border-b border-[var(--color-border-light)] relative z-10">
                <div className="flex items-center gap-4">
                  {step !== 'choose' && (
                    <button 
                      onClick={() => setStep(step === 'configure' ? 'explain' : 'choose')}
                      className="w-8 h-8 rounded-full bg-[var(--color-card)] hover:bg-white/10 flex items-center justify-center transition-colors"
                    >
                      <ArrowLeft className="w-4 h-4 text-[var(--color-text-primary)]/70" />
                    </button>
                  )}
                  <h2 className="text-xl font-bold text-[var(--color-text-primary)] tracking-tight">Connect Channel</h2>
                </div>
                <button
                  onClick={handleClose}
                  className="w-8 h-8 flex items-center justify-center rounded-full bg-[var(--color-card)] hover:bg-white/10 text-[var(--color-text-primary)]/70 hover:text-[var(--color-text-primary)] transition-colors"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>
            )}

            {/* Provider Gradient Background overlay for steps 2 & 3 */}
            <AnimatePresence>
              {provider && (step === 'explain' || step === 'configure') && (
                <motion.div
                  initial={{ opacity: 0 }}
                  animate={{ opacity: 1 }}
                  exit={{ opacity: 0 }}
                  className={`absolute top-0 left-0 right-0 h-64 bg-gradient-to-b ${provider.gradient} to-transparent pointer-events-none opacity-50`}
                />
              )}
            </AnimatePresence>

            <div className="p-8 relative z-10 min-h-[400px] flex flex-col">
              <AnimatePresence mode="wait">
                
                {/* STEP 1: CHOOSE */}
                {step === 'choose' && (
                  <motion.div
                    key="choose"
                    initial={{ opacity: 0, x: 20 }}
                    animate={{ opacity: 1, x: 0 }}
                    exit={{ opacity: 0, x: -20 }}
                    transition={{ duration: 0.2 }}
                    className="flex-1 flex flex-col"
                  >
                    <p className="text-[var(--color-text-secondary)] mb-6">Select a payment provider to add to your wallet.</p>
                    <div className="grid grid-cols-2 gap-4 flex-1">
                      {(Object.keys(PROVIDERS) as ProviderId[]).map((pid) => {
                        const p = PROVIDERS[pid];
                        const isSelected = selectedProvider === pid;
                        return (
                          <button
                            key={pid}
                            onClick={() => setSelectedProvider(pid)}
                            className={`flex flex-col items-center justify-center gap-3 p-6 rounded-2xl border transition-all duration-300 ${
                              isSelected 
                                ? 'bg-white/10 border-white/30 scale-[1.02]' 
                                : 'bg-white/[0.02] border-white/5 hover:bg-white/[0.05] hover:border-[var(--color-border-light)]'
                            }`}
                          >
                            <div className={`w-12 h-12 rounded-full flex items-center justify-center bg-[var(--color-card)] border-2 ${isSelected ? 'border-transparent' : 'border-white/5'}`}>
                              <p.Icon className={`w-6 h-6 ${p.color}`} />
                            </div>
                            <span className="text-[var(--color-text-primary)] font-medium text-sm">{p.name}</span>
                          </button>
                        );
                      })}
                    </div>
                  </motion.div>
                )}

                {/* STEP 2: EXPLAIN */}
                {step === 'explain' && provider && (
                  <motion.div
                    key="explain"
                    initial={{ opacity: 0, x: 20 }}
                    animate={{ opacity: 1, x: 0 }}
                    exit={{ opacity: 0, x: -20 }}
                    transition={{ duration: 0.2 }}
                    className="flex-1 flex flex-col items-center justify-center text-center py-8"
                  >
                    <div className="w-20 h-20 rounded-2xl bg-[var(--color-card)] border border-[var(--color-border-light)] flex items-center justify-center mb-8 shadow-2xl relative">
                      <provider.Icon className={`w-10 h-10 ${provider.color}`} />
                      <div className="absolute inset-0 rounded-2xl ring-1 ring-inset ring-white/10" />
                    </div>
                    <h3 className="text-2xl font-bold text-[var(--color-text-primary)] mb-4 tracking-tight">How it works</h3>
                    <p className="text-[var(--color-text-secondary)] text-lg leading-relaxed max-w-sm mx-auto mb-10">
                      {provider.description}
                    </p>
                  </motion.div>
                )}

                {/* STEP 3: CONFIGURE */}
                {step === 'configure' && provider && (
                  <motion.div
                    key="configure"
                    initial={{ opacity: 0, x: 20 }}
                    animate={{ opacity: 1, x: 0 }}
                    exit={{ opacity: 0, x: -20 }}
                    transition={{ duration: 0.2 }}
                    className="flex-1 flex flex-col"
                  >
                    <div className="flex items-center gap-4 mb-8">
                      <div className="w-12 h-12 rounded-xl bg-[var(--color-card)] border border-[var(--color-border-light)] flex items-center justify-center shadow-lg">
                        <provider.Icon className={`w-6 h-6 ${provider.color}`} />
                      </div>
                      <div>
                        <h3 className="text-xl font-bold text-[var(--color-text-primary)] tracking-tight">{provider.name} Details</h3>
                        <p className="text-[var(--color-text-secondary)] text-sm">Provide your integration credentials.</p>
                      </div>
                    </div>

                    <div className="space-y-5 flex-1">
                      {provider.fields.map(field => (
                        <div key={field.name}>
                          <label className="text-sm font-medium text-[var(--color-text-secondary)] block mb-2 flex justify-between">
                            <span>{field.label}</span>
                            {field.optional && <span className="text-[var(--color-text-primary)]/20">Optional</span>}
                          </label>
                          <input
                            type={field.type}
                            placeholder={field.placeholder}
                            value={formData[field.name] || ''}
                            onChange={e => setFormData(p => ({ ...p, [field.name]: e.target.value }))}
                            className="w-full px-4 py-3.5 bg-surface-base border border-border-subtle rounded-2xl text-[var(--color-text-primary)] placeholder:text-text-muted focus:outline-none focus:border-accent-primary transition-colors"
                          />
                        </div>
                      ))}
                    </div>

                    {error && (
                      <div className="mt-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20">
                        <p className="text-sm text-red-400 text-center">{error}</p>
                      </div>
                    )}
                  </motion.div>
                )}

                {/* STEP 4: SUCCESS */}
                {step === 'success' && provider && (
                  <motion.div
                    key="success"
                    initial={{ opacity: 0, scale: 0.95 }}
                    animate={{ opacity: 1, scale: 1 }}
                    className="flex-1 flex flex-col items-center justify-center text-center py-10"
                  >
                    <motion.div 
                      initial={{ scale: 0 }}
                      animate={{ scale: 1 }}
                      transition={{ type: 'spring', delay: 0.2 }}
                      className="w-24 h-24 bg-emerald-500/10 rounded-full flex items-center justify-center mb-6"
                    >
                      <CheckCircle className="w-12 h-12 text-emerald-500" />
                    </motion.div>
                    <h3 className="text-3xl font-bold text-[var(--color-text-primary)] mb-3 tracking-tight">Connected</h3>
                    <p className="text-[var(--color-text-secondary)] text-lg mb-10 max-w-sm">
                      {provider.name} has been added to your wallet. You're ready to receive payments.
                    </p>
                    <button
                      onClick={handleClose}
                      className="w-full py-4 bg-white hover:bg-gray-100 text-black rounded-2xl font-bold text-lg transition-colors"
                    >
                      Return to Wallet
                    </button>
                  </motion.div>
                )}

              </AnimatePresence>

              {/* Footer Actions (hidden on success) */}
              {step !== 'success' && (
                <div className="mt-8 pt-6 border-t border-white/5">
                  <button
                    onClick={handleNext}
                    disabled={step === 'choose' && !selectedProvider || mutation.isPending || testConnectionMutation.isPending}
                    className="w-full py-4 bg-white hover:bg-gray-100 text-black rounded-2xl font-bold transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                  >
                    {mutation.isPending || testConnectionMutation.isPending ? (
                      <>
                        <Loader2 className="w-5 h-5 animate-spin" />
                        {testConnectionMutation.isPending ? 'Testing Connection...' : 'Connecting...'}
                      </>
                    ) : (
                      <>
                        {step === 'choose' ? 'Continue' : step === 'explain' ? 'Configure Integration' : (provider?.requiresApiCredentials ? 'Test & Connect' : 'Connect Channel')}
                        {step !== 'configure' && <ArrowRight className="w-5 h-5" />}
                      </>
                    )}
                  </button>
                </div>
              )}
            </div>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
