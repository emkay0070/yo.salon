'use client';

import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, Plus, Search, User, Package, Scissors, CreditCard, Smartphone, Banknote, CheckCircle, Loader2 } from 'lucide-react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';

type Step = 'customer' | 'items' | 'specialist' | 'payment' | 'success';

interface WalkInSaleProps {
  isOpen: boolean;
  onClose: () => void;
  providerId: string;
}

interface SaleItem {
  id: string;
  type: 'service' | 'product';
  name: string;
  price: number;
  quantity: number;
}

export default function WalkInSale({ isOpen, onClose, providerId }: WalkInSaleProps) {
  const [step, setStep] = useState<Step>('customer');
  const [customerSearch, setCustomerSearch] = useState('');
  const [selectedCustomer, setSelectedCustomer] = useState<any>(null);
  const [items, setItems] = useState<SaleItem[]>([]);
  const [selectedSpecialist, setSelectedSpecialist] = useState<any>(null);
  const [paymentMethod, setPaymentMethod] = useState<'cash' | 'mtn' | 'airtel' | 'visa'>('cash');
  const [isProcessing, setIsProcessing] = useState(false);
  const queryClient = useQueryClient();

  // Fetch customers for search
  const { data: customers = [], isLoading: isSearchingCustomers } = useQuery({
    queryKey: ['customers-search', customerSearch],
    queryFn: () => apiClient.getCustomers({ search: customerSearch }),
    enabled: step === 'customer' && customerSearch.length >= 2,
  });

  // Fetch services
  const { data: services = [] } = useQuery({
    queryKey: ['services', providerId],
    queryFn: () => apiClient.getServices({ provider_id: providerId }),
    enabled: isOpen,
  });

  // Fetch staff/specialists
  const { data: staff = [] } = useQuery({
    queryKey: ['staff', providerId],
    queryFn: () => apiClient.getStaff({ provider_id: providerId }),
    enabled: isOpen,
  });

  // Create transaction mutation
  const createTransaction = useMutation({
    mutationFn: (data: any) => apiClient.recordManualTransaction(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['transactions'] });
      queryClient.invalidateQueries({ queryKey: ['transaction-summary'] });
      setStep('success');
      setIsProcessing(false);
    },
    onError: () => {
      setIsProcessing(false);
      alert('Failed to complete sale. Please try again.');
    },
  });

  const totalAmount = items.reduce((sum, item) => sum + (item.price * item.quantity), 0);

  const handleSelectCustomer = (customer: any) => {
    setSelectedCustomer(customer);
    setStep('items');
  };

  const handleAddItem = (item: any, type: 'service' | 'product') => {
    const existingIndex = items.findIndex(i => i.id === item.id);
    if (existingIndex >= 0) {
      const updated = [...items];
      updated[existingIndex].quantity += 1;
      setItems(updated);
    } else {
      setItems([...items, {
        id: item.id,
        type,
        name: item.name,
        price: item.price,
        quantity: 1,
      }]);
    }
  };

  const handleRemoveItem = (index: number) => {
    const updated = [...items];
    updated.splice(index, 1);
    setItems(updated);
  };

  const handleCompleteSale = () => {
    if (items.length === 0) {
      alert('Please add at least one item.');
      return;
    }

    setIsProcessing(true);

    const payload = {
      booking_id: null,
      customer_id: selectedCustomer?.id || null,
      type: 'payment',
      amount: totalAmount,
      payment_method: paymentMethod,
      notes: `Walk-in sale: ${items.map(i => `${i.quantity}x ${i.name}`).join(', ')}`,
      provider_reference: selectedSpecialist?.id ? `staff:${selectedSpecialist.id}` : null,
    };

    createTransaction.mutate(payload);
  };

  const handleReset = () => {
    setStep('customer');
    setCustomerSearch('');
    setSelectedCustomer(null);
    setItems([]);
    setSelectedSpecialist(null);
    setPaymentMethod('cash');
  };

  const handleClose = () => {
    handleReset();
    onClose();
  };

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={handleClose}
            className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
          />
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.95 }}
            className="fixed inset-0 z-50 flex items-center justify-center p-4 pointer-events-none"
          >
            <div className="bg-[#111111] border border-border-light rounded-3xl w-full max-w-lg max-h-[90vh] overflow-y-auto pointer-events-auto">
              {/* Header */}
              <div className="p-6 border-b border-border-light flex items-center justify-between">
                <h2 className="text-xl font-bold text-text-primary">New Walk-in Sale</h2>
                <button onClick={handleClose} className="w-8 h-8 rounded-full bg-card hover:bg-white/10 flex items-center justify-center text-text-secondary hover:text-text-primary transition-colors">
                  <X className="w-4 h-4" />
                </button>
              </div>

              {/* Body */}
              <div className="p-6">
                {/* Step 1: Customer */}
                {step === 'customer' && (
                  <motion.div initial={{ opacity: 0, x: -20 }} animate={{ opacity: 1, x: 0 }}>
                    <h3 className="text-lg font-semibold text-text-primary mb-4">Who is this?</h3>
                    
                    <div className="relative mb-4">
                      <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-text-secondary" />
                      <input
                        type="text"
                        placeholder="Search customer by name or phone..."
                        value={customerSearch}
                        onChange={e => setCustomerSearch(e.target.value)}
                        className="w-full pl-11 pr-4 py-3 bg-card border border-border-light rounded-2xl text-text-primary placeholder-[#A0A0A0] focus:outline-none focus:border-[rgba(108,92,231,0.5)] transition-colors"
                      />
                    </div>

                    {isSearchingCustomers && (
                      <div className="flex items-center justify-center py-8">
                        <Loader2 className="w-5 h-5 animate-spin text-text-secondary" />
                      </div>
                    )}

                    {!isSearchingCustomers && customerSearch.length >= 2 && customers.length === 0 && (
                      <div className="text-center py-8 text-text-secondary text-sm mb-4">
                        No customers found matching "{customerSearch}"
                      </div>
                    )}

                    <div className="space-y-2 max-h-[200px] overflow-y-auto mb-4">
                      {customers.map((customer: any) => (
                        <button
                          key={customer.id}
                          onClick={() => handleSelectCustomer(customer)}
                          className="w-full p-3 bg-card border border-border-light rounded-xl hover:bg-white/10 transition-colors text-left"
                        >
                          <div className="font-medium text-text-primary">{customer.name}</div>
                          {customer.phone && <div className="text-sm text-text-secondary">{customer.phone}</div>}
                        </button>
                      ))}
                    </div>

                    <button
                      onClick={() => setStep('items')}
                      className="w-full py-3 bg-card border border-border-light rounded-2xl text-text-primary hover:bg-white/10 transition-colors font-medium"
                    >
                      Skip (Anonymous Customer)
                    </button>
                  </motion.div>
                )}

                {/* Step 2: Items */}
                {step === 'items' && (
                  <motion.div initial={{ opacity: 0, x: -20 }} animate={{ opacity: 1, x: 0 }}>
                    <h3 className="text-lg font-semibold text-text-primary mb-4">What are they getting?</h3>
                    
                    <div className="space-y-4 mb-6">
                      <div>
                        <h4 className="text-sm font-medium text-text-secondary mb-2">Services</h4>
                        <div className="space-y-2">
                          {services.map((service: any) => (
                            <button
                              key={service.id}
                              onClick={() => handleAddItem(service, 'service')}
                              className="w-full p-3 bg-card border border-border-light rounded-xl hover:bg-white/10 transition-colors text-left flex items-center justify-between"
                            >
                              <div className="flex items-center gap-3">
                                <Scissors className="w-4 h-4 text-text-secondary" />
                                <span className="text-text-primary">{service.name}</span>
                              </div>
                              <span className="text-text-primary font-medium">{service.price.toLocaleString()} UGX</span>
                            </button>
                          ))}
                        </div>
                      </div>
                    </div>

                    {items.length > 0 && (
                      <div className="mb-6 p-4 bg-card border border-border-light rounded-xl">
                        <h4 className="text-sm font-medium text-text-secondary mb-3">Added Items</h4>
                        {items.map((item, index) => (
                          <div key={index} className="flex items-center justify-between py-2">
                            <span className="text-text-primary">{item.quantity}x {item.name}</span>
                            <div className="flex items-center gap-3">
                              <span className="text-text-primary font-medium">{(item.price * item.quantity).toLocaleString()} UGX</span>
                              <button onClick={() => handleRemoveItem(index)} className="text-red-400 hover:text-red-300">
                                <X className="w-4 h-4" />
                              </button>
                            </div>
                          </div>
                        ))}
                        <div className="border-t border-border-light mt-3 pt-3 flex justify-between font-bold">
                          <span className="text-text-primary">Total</span>
                          <span className="text-text-primary">{totalAmount.toLocaleString()} UGX</span>
                        </div>
                      </div>
                    )}

                    <div className="flex gap-3">
                      <button onClick={() => setStep('customer')} className="flex-1 py-3 bg-card border border-border-light rounded-2xl text-text-primary hover:bg-white/10 transition-colors">
                        Back
                      </button>
                      <button
                        onClick={() => setStep('specialist')}
                        disabled={items.length === 0}
                        className="flex-1 py-3 bg-[#6C5CE7] text-white rounded-2xl font-medium hover:bg-[#5a4bc7] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        Continue
                      </button>
                    </div>
                  </motion.div>
                )}

                {/* Step 3: Specialist */}
                {step === 'specialist' && (
                  <motion.div initial={{ opacity: 0, x: -20 }} animate={{ opacity: 1, x: 0 }}>
                    <h3 className="text-lg font-semibold text-text-primary mb-4">Who performed this service?</h3>
                    
                    <div className="space-y-2 mb-6 max-h-[200px] overflow-y-auto">
                      <button
                        onClick={() => setSelectedSpecialist(null)}
                        className={`w-full p-3 border rounded-xl text-left transition-colors ${!selectedSpecialist ? 'bg-[#6C5CE7]/10 border-[#6C5CE7]/30' : 'bg-card border-border-light hover:bg-white/10'}`}
                      >
                        <span className="text-text-primary">No specialist needed</span>
                      </button>
                      {staff.map((person: any) => (
                        <button
                          key={person.id}
                          onClick={() => setSelectedSpecialist(person)}
                          className={`w-full p-3 border rounded-xl text-left transition-colors ${selectedSpecialist?.id === person.id ? 'bg-[#6C5CE7]/10 border-[#6C5CE7]/30' : 'bg-card border-border-light hover:bg-white/10'}`}
                        >
                          <div className="font-medium text-text-primary">{person.name}</div>
                          <div className="text-sm text-text-secondary">{person.role || 'Staff'}</div>
                        </button>
                      ))}
                    </div>

                    <div className="flex gap-3">
                      <button onClick={() => setStep('items')} className="flex-1 py-3 bg-card border border-border-light rounded-2xl text-text-primary hover:bg-white/10 transition-colors">
                        Back
                      </button>
                      <button onClick={() => setStep('payment')} className="flex-1 py-3 bg-[#6C5CE7] text-white rounded-2xl font-medium hover:bg-[#5a4bc7] transition-colors">
                        Continue
                      </button>
                    </div>
                  </motion.div>
                )}

                {/* Step 4: Payment */}
                {step === 'payment' && (
                  <motion.div initial={{ opacity: 0, x: -20 }} animate={{ opacity: 1, x: 0 }}>
                    <h3 className="text-lg font-semibold text-text-primary mb-4">Complete Payment</h3>
                    
                    <div className="p-4 bg-card border border-border-light rounded-xl mb-6">
                      <div className="flex justify-between items-center mb-2">
                        <span className="text-text-secondary">Amount due</span>
                        <span className="text-2xl font-bold text-text-primary">{totalAmount.toLocaleString()} UGX</span>
                      </div>
                      {selectedCustomer && (
                        <div className="text-sm text-text-secondary">
                          Customer: {selectedCustomer.name}
                        </div>
                      )}
                      {selectedSpecialist && (
                        <div className="text-sm text-text-secondary">
                          Performed by: {selectedSpecialist.name}
                        </div>
                      )}
                    </div>

                    <h4 className="text-sm font-medium text-text-secondary mb-3">Payment method</h4>
                    <div className="grid grid-cols-2 gap-3 mb-6">
                      {[
                        { id: 'cash', icon: Banknote, label: 'Cash' },
                        { id: 'mtn', icon: Smartphone, label: 'MTN' },
                        { id: 'airtel', icon: Smartphone, label: 'Airtel' },
                        { id: 'visa', icon: CreditCard, label: 'Card' },
                      ].map((method) => (
                        <button
                          key={method.id}
                          onClick={() => setPaymentMethod(method.id as any)}
                          className={`p-4 border rounded-xl flex flex-col items-center gap-2 transition-colors ${
                            paymentMethod === method.id
                              ? 'bg-[#6C5CE7]/10 border-[#6C5CE7]/30'
                              : 'bg-card border-border-light hover:bg-white/10'
                          }`}
                        >
                          <method.icon className="w-6 h-6 text-text-primary" />
                          <span className="text-sm text-text-primary">{method.label}</span>
                        </button>
                      ))}
                    </div>

                    <div className="flex gap-3">
                      <button onClick={() => setStep('specialist')} className="flex-1 py-3 bg-card border border-border-light rounded-2xl text-text-primary hover:bg-white/10 transition-colors">
                        Back
                      </button>
                      <button
                        onClick={handleCompleteSale}
                        disabled={isProcessing}
                        className="flex-1 py-3 bg-[#6C5CE7] text-white rounded-2xl font-medium hover:bg-[#5a4bc7] transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                      >
                        {isProcessing ? (
                          <>
                            <Loader2 className="w-4 h-4 animate-spin" />
                            Processing...
                          </>
                        ) : (
                          'Complete Sale'
                        )}
                      </button>
                    </div>
                  </motion.div>
                )}

                {/* Step 5: Success */}
                {step === 'success' && (
                  <motion.div initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} className="py-10 text-center">
                    <div className="w-20 h-20 bg-emerald-500/10 rounded-full flex items-center justify-center mb-6 mx-auto">
                      <CheckCircle className="w-10 h-10 text-emerald-500" />
                    </div>
                    <h3 className="text-2xl font-bold text-text-primary mb-2">Sale Complete</h3>
                    <p className="text-text-secondary mb-6">
                      {totalAmount.toLocaleString()} UGX
                    </p>
                    {selectedCustomer && (
                      <p className="text-text-secondary mb-6">
                        {selectedCustomer.name}
                      </p>
                    )}
                    <div className="flex gap-3">
                      <button onClick={handleReset} className="flex-1 py-3 bg-card border border-border-light rounded-2xl text-text-primary hover:bg-white/10 transition-colors">
                        New Walk-in
                      </button>
                      <button onClick={handleClose} className="flex-1 py-3 bg-[#6C5CE7] text-white rounded-2xl font-medium hover:bg-[#5a4bc7] transition-colors">
                        Close
                      </button>
                    </div>
                  </motion.div>
                )}
              </div>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}
