'use client';

import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { MessageSquare, Sparkles, Zap, CheckCircle2, ChevronRight, X, CreditCard, Loader2 } from 'lucide-react';
import { useRole } from '@/contexts/RoleContext';

interface AddOnProduct {
  code: string;
  name: string;
  description: string;
  resource_code: string;
  units: number;
  type: string;
  billing_model: string;
  price: {
    amount: number;
    currency: string;
    formatted: string;
  } | null;
}

interface PaymentMethod {
  id: string;
  provider: string;
  type: string;
  identifier: string;
  is_default: boolean;
}

export default function AddOnsTab() {
  const { salonId } = useRole();
  const queryClient = useQueryClient();
  const [selectedProduct, setSelectedProduct] = useState<AddOnProduct | null>(null);
  const [selectedPaymentMethodId, setSelectedPaymentMethodId] = useState<string | null>(null);
  const [purchaseSuccess, setPurchaseSuccess] = useState<{units: number, message: string} | null>(null);

  // Fetch usage for balances
  const { data: usageData } = useQuery({
    queryKey: ['membership-usage', salonId],
    queryFn: () => apiClient.getMembershipUsage(),
    enabled: !!salonId,
  });

  // Fetch available add-ons
  const { data: addOnsResponse, isLoading: isLoadingAddOns } = useQuery({
    queryKey: ['billing-addons'],
    queryFn: () => apiClient.getAddOns(),
  });

  // Prefetch payment methods
  const { data: paymentMethodsResponse } = useQuery({
    queryKey: ['payment-methods', salonId],
    queryFn: () => apiClient.getPaymentMethods(),
    enabled: !!salonId,
  });

  const purchaseMutation = useMutation({
    mutationFn: (data: { product_code: string, payment_method_id: string }) => apiClient.purchaseAddOn(data),
    onSuccess: (res: any) => {
      queryClient.invalidateQueries({ queryKey: ['membership-usage'] });
      setPurchaseSuccess({ units: res.units_added, message: res.message });
      // Keep drawer open but show success state
    },
    onError: (error: any) => {
      alert(error?.response?.data?.message || 'Purchase failed');
    }
  });

  const usage = usageData?.usage || [];
  const products: AddOnProduct[] = addOnsResponse?.data || [];
  const paymentMethods: PaymentMethod[] = paymentMethodsResponse?.data || [];

  // Get current balances
  const smsUsage = usage.find((u: any) => u.metric === 'sms_credits');
  const featuredUsage = usage.find((u: any) => u.metric === 'featured_days');

  // Categorize products
  const communicationProducts = products.filter(p => p.resource_code === 'sms_credits' || p.resource_code === 'whatsapp_credits');
  const marketingProducts = products.filter(p => p.resource_code === 'featured_days' || p.resource_code === 'sponsored_campaign');
  const operationsProducts = products.filter(p => !['sms_credits', 'whatsapp_credits', 'featured_days', 'sponsored_campaign'].includes(p.resource_code));

  const handlePurchase = () => {
    if (!selectedProduct || !selectedPaymentMethodId) return;
    purchaseMutation.mutate({
      product_code: selectedProduct.code,
      payment_method_id: selectedPaymentMethodId,
    });
  };

  const closeDrawer = () => {
    setSelectedProduct(null);
    setPurchaseSuccess(null);
    setSelectedPaymentMethodId(null);
  };

  const getProductFeatures = (code: string) => {
    if (code.includes('sms')) return ['Never expire', 'Instant activation', 'Global reach'];
    if (code.includes('feature')) return ['Top of search results', 'Highlight on homepage', 'Increases bookings by 40%'];
    return ['Instant activation', 'Seamless integration'];
  };

  return (
    <div className="space-y-10 relative">
      {/* Hero Section */}
      <div className="bg-gradient-to-br from-[#FFD700]/10 via-[#FFD700]/5 to-transparent border border-[#FFD700]/25 rounded-2xl p-8 relative overflow-hidden">
        <div className="absolute top-0 right-0 p-8 opacity-20 pointer-events-none">
          <Sparkles className="w-48 h-48 text-[#FFD700]" />
        </div>
        
        <h2 className="text-2xl font-bold text-text-primary mb-2">Expand your salon as you grow.</h2>
        <p className="text-text-secondary mb-8 max-w-xl">Purchase additional credits and features to unlock more capabilities, reach more customers, and optimize your operations.</p>
        
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 relative z-10">
          <div className="bg-card/50 backdrop-blur-sm border border-white/10 rounded-xl p-5">
            <p className="text-text-secondary text-sm mb-1">SMS Credits</p>
            <p className="text-3xl font-bold text-[#FFD700]">{smsUsage ? smsUsage.remaining : '0'}</p>
          </div>
          <div className="bg-card/50 backdrop-blur-sm border border-white/10 rounded-xl p-5">
            <p className="text-text-secondary text-sm mb-1">Featured Days</p>
            <p className="text-3xl font-bold text-[#FFD700]">{featuredUsage ? featuredUsage.remaining : '0'}</p>
          </div>
          <div className="bg-card/50 backdrop-blur-sm border border-white/10 rounded-xl p-5">
            <p className="text-text-secondary text-sm mb-1">WhatsApp Messages</p>
            <p className="text-3xl font-bold text-text-primary">Unlimited</p>
          </div>
        </div>
      </div>

      {isLoadingAddOns ? (
        <div className="flex justify-center py-12">
          <Loader2 className="w-8 h-8 text-[#FFD700] animate-spin" />
        </div>
      ) : (
        <div className="space-y-12">
          {/* Communication */}
          {communicationProducts.length > 0 && (
            <section>
              <div className="mb-6 flex items-center gap-3 border-b border-border-light pb-4">
                <div className="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center">
                  <MessageSquare className="w-5 h-5 text-blue-400" />
                </div>
                <div>
                  <h3 className="text-xl font-bold text-text-primary">Communication</h3>
                  <p className="text-sm text-text-secondary">Keep your customers engaged and reduce no-shows.</p>
                </div>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {communicationProducts.map(product => (
                  <ProductCard key={product.code} product={product} features={getProductFeatures(product.resource_code)} onSelect={() => setSelectedProduct(product)} />
                ))}
              </div>
            </section>
          )}

          {/* Marketing */}
          {marketingProducts.length > 0 && (
            <section>
              <div className="mb-6 flex items-center gap-3 border-b border-border-light pb-4">
                <div className="w-10 h-10 rounded-lg bg-[#FFD700]/10 flex items-center justify-center">
                  <Sparkles className="w-5 h-5 text-[#FFD700]" />
                </div>
                <div>
                  <h3 className="text-xl font-bold text-text-primary">Marketing</h3>
                  <p className="text-sm text-text-secondary">Stand out in the Discover marketplace.</p>
                </div>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {marketingProducts.map(product => (
                  <ProductCard key={product.code} product={product} features={getProductFeatures(product.resource_code)} onSelect={() => setSelectedProduct(product)} />
                ))}
              </div>
            </section>
          )}

          {/* Operations */}
          {operationsProducts.length > 0 && (
            <section>
              <div className="mb-6 flex items-center gap-3 border-b border-border-light pb-4">
                <div className="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                  <Zap className="w-5 h-5 text-emerald-400" />
                </div>
                <div>
                  <h3 className="text-xl font-bold text-text-primary">Operations & Growth</h3>
                  <p className="text-sm text-text-secondary">Expand your staff, storage, and AI capabilities.</p>
                </div>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {operationsProducts.map(product => (
                  <ProductCard key={product.code} product={product} features={getProductFeatures(product.resource_code)} onSelect={() => setSelectedProduct(product)} />
                ))}
              </div>
            </section>
          )}
        </div>
      )}

      {/* Purchase Drawer Overlay */}
      <AnimatePresence>
        {selectedProduct && (
          <>
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={closeDrawer}
              className="fixed inset-0 bg-black/60 backdrop-blur-sm z-40"
            />
            <motion.div
              initial={{ x: '100%' }}
              animate={{ x: 0 }}
              exit={{ x: '100%' }}
              transition={{ type: 'spring', damping: 25, stiffness: 200 }}
              className="fixed inset-y-0 right-0 w-full md:w-[450px] bg-surface border-l border-border-medium z-50 shadow-2xl flex flex-col"
            >
              <div className="p-6 border-b border-border-medium flex items-center justify-between bg-card">
                <h2 className="text-xl font-bold text-text-primary">Purchase</h2>
                <button
                  onClick={closeDrawer}
                  className="p-2 hover:bg-surface rounded-full transition-colors text-text-secondary hover:text-text-primary"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>

              <div className="flex-1 overflow-y-auto p-6">
                {purchaseSuccess ? (
                  <motion.div 
                    initial={{ opacity: 0, scale: 0.95 }}
                    animate={{ opacity: 1, scale: 1 }}
                    className="flex flex-col items-center justify-center h-full text-center space-y-6"
                  >
                    <div className="w-20 h-20 bg-emerald-500/10 rounded-full flex items-center justify-center border border-emerald-500/20">
                      <CheckCircle2 className="w-10 h-10 text-emerald-500" />
                    </div>
                    <div>
                      <h3 className="text-2xl font-bold text-text-primary mb-2">Purchase Successful!</h3>
                      <p className="text-text-secondary">{purchaseSuccess.message}</p>
                    </div>
                    
                    <div className="w-full bg-card border border-border-medium rounded-xl p-6 mt-8">
                      <p className="text-text-secondary text-sm mb-1">New Balance Updated</p>
                      <p className="text-3xl font-bold text-[#FFD700]">+{purchaseSuccess.units}</p>
                    </div>

                    <button 
                      onClick={closeDrawer}
                      className="w-full py-4 bg-surface border border-border-medium rounded-xl text-text-primary font-medium hover:bg-card transition-colors mt-4"
                    >
                      Done
                    </button>
                  </motion.div>
                ) : (
                  <div className="space-y-8">
                    {/* Product Summary */}
                    <div>
                      <h3 className="text-text-secondary text-sm font-medium mb-3 uppercase tracking-wider">Item Summary</h3>
                      <div className="bg-card border border-border-medium rounded-2xl p-5">
                        <h4 className="text-lg font-bold text-text-primary mb-1">{selectedProduct.name}</h4>
                        <p className="text-text-secondary text-sm mb-4">{selectedProduct.description}</p>
                        
                        <div className="flex items-center justify-between pt-4 border-t border-border-light">
                          <span className="text-text-secondary font-medium">Total</span>
                          <span className="text-2xl font-bold text-text-primary">
                            {selectedProduct.price?.formatted || 'Free'}
                          </span>
                        </div>
                      </div>
                    </div>

                    {/* Payment Method */}
                    <div>
                      <h3 className="text-text-secondary text-sm font-medium mb-3 uppercase tracking-wider">Payment Method</h3>
                      <div className="space-y-3">
                        {paymentMethods.length > 0 ? (
                          paymentMethods.map(method => (
                            <button
                              key={method.id}
                              onClick={() => setSelectedPaymentMethodId(method.id)}
                              className={`w-full flex items-center justify-between p-4 rounded-xl border transition-all ${
                                selectedPaymentMethodId === method.id 
                                  ? 'bg-[#FFD700]/10 border-[#FFD700]' 
                                  : 'bg-card border-border-medium hover:border-border-light hover:bg-surface'
                              }`}
                            >
                              <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-lg bg-surface flex items-center justify-center border border-border-light">
                                  <CreditCard className={`w-5 h-5 ${selectedPaymentMethodId === method.id ? 'text-[#FFD700]' : 'text-text-secondary'}`} />
                                </div>
                                <div className="text-left">
                                  <p className="text-text-primary font-medium capitalize">{method.provider.replace('_', ' ')}</p>
                                  <p className="text-text-secondary text-xs">{method.identifier}</p>
                                </div>
                              </div>
                              <div className={`w-5 h-5 rounded-full border-2 flex items-center justify-center ${
                                selectedPaymentMethodId === method.id ? 'border-[#FFD700]' : 'border-border-medium'
                              }`}>
                                {selectedPaymentMethodId === method.id && <div className="w-2.5 h-2.5 rounded-full bg-[#FFD700]" />}
                              </div>
                            </button>
                          ))
                        ) : (
                          <div className="bg-card border border-border-medium border-dashed rounded-xl p-6 text-center">
                            <p className="text-text-secondary text-sm mb-3">No payment methods found.</p>
                            <button className="text-[#FFD700] text-sm font-medium hover:underline">Add Payment Method</button>
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                )}
              </div>

              {/* Footer CTA */}
              {!purchaseSuccess && (
                <div className="p-6 border-t border-border-medium bg-card">
                  <button
                    onClick={handlePurchase}
                    disabled={!selectedPaymentMethodId || purchaseMutation.isPending}
                    className={`w-full py-4 rounded-xl font-bold flex items-center justify-center gap-2 transition-all ${
                      selectedPaymentMethodId && !purchaseMutation.isPending
                        ? 'bg-[#FFD700] text-black hover:bg-[#FFD700]/90 shadow-lg shadow-[#FFD700]/20'
                        : 'bg-surface text-text-secondary cursor-not-allowed border border-border-medium'
                    }`}
                  >
                    {purchaseMutation.isPending ? (
                      <>
                        <Loader2 className="w-5 h-5 animate-spin" />
                        Processing...
                      </>
                    ) : (
                      `Confirm Purchase ${selectedProduct.price?.formatted ? `- ${selectedProduct.price.formatted}` : ''}`
                    )}
                  </button>
                </div>
              )}
            </motion.div>
          </>
        )}
      </AnimatePresence>
    </div>
  );
}

function ProductCard({ product, features, onSelect }: { product: AddOnProduct, features: string[], onSelect: () => void }) {
  return (
    <div className="bg-card border border-border-medium rounded-2xl p-6 flex flex-col h-full hover:border-[#FFD700]/50 transition-colors group relative overflow-hidden">
      <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-[#FFD700] to-[#C9A227] opacity-0 group-hover:opacity-100 transition-opacity" />
      
      <div className="mb-4 flex-1">
        <h4 className="text-lg font-bold text-text-primary mb-2">{product.name}</h4>
        <p className="text-text-secondary text-sm">{product.description}</p>
        
        <div className="mt-6 mb-6">
          <div className="flex items-baseline gap-1">
            <span className="text-2xl font-bold text-text-primary">{product.price?.formatted || 'Free'}</span>
          </div>
        </div>

        <div className="space-y-2">
          {features.map((feature, i) => (
            <div key={i} className="flex items-start gap-2">
              <CheckCircle2 className="w-4 h-4 text-[#FFD700] shrink-0 mt-0.5" />
              <span className="text-sm text-text-primary/90">{feature}</span>
            </div>
          ))}
        </div>
      </div>

      <button
        onClick={onSelect}
        className="w-full py-3 bg-surface border border-border-medium rounded-xl text-text-primary font-medium hover:bg-[#FFD700] hover:text-black hover:border-transparent transition-all mt-4"
      >
        Buy Add-on
      </button>
    </div>
  );
}
