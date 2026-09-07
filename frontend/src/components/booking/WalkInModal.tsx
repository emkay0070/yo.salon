'use client';

import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, User, Phone, Scissors, CheckCircle2 } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useRole } from '@/contexts/RoleContext';
import { apiClient } from '@/lib/api-client';

interface WalkInModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess?: () => void;
}

interface Service {
  id: string;
  name: string;
  price: number;
  duration: number;
}

interface Staff {
  id: string;
  name: string;
}

function getInitials(name: string) {
  return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
}

export default function WalkInModal({ isOpen, onClose, onSuccess }: WalkInModalProps) {
  const queryClient = useQueryClient();
  const { salonId } = useRole();
  const [customerName, setCustomerName] = useState('');
  const [customerPhone, setCustomerPhone] = useState('');
  const [selectedService, setSelectedService] = useState<Service | null>(null);
  const [selectedStaff, setSelectedStaff] = useState<Staff | null>(null);
  const [notes, setNotes] = useState('');

  const { data: services = [] } = useQuery({
    queryKey: ['services', salonId],
    queryFn: () => apiClient.getServices({ salon_id: salonId }),
    enabled: isOpen && !!salonId,
  });

  const { data: staff = [] } = useQuery({
    queryKey: ['staff', salonId],
    queryFn: () => apiClient.getStaff({ salon_id: salonId }),
    enabled: isOpen && !!salonId,
  });

  const createBookingMutation = useMutation({
    mutationFn: async () => {
      let customerId;
      if (customerName && customerPhone) {
        const customer = await apiClient.createCustomer({
          salon_id: salonId,
          name: customerName,
          phone: customerPhone,
        });
        customerId = customer.id;
      }
      return await apiClient.createBooking({
        salon_id: salonId,
        service_id: selectedService?.id,
        staff_id: selectedStaff?.id,
        customer_id: customerId,
        date: new Date().toISOString(),
        status: 'checked_in',
        notes: notes || 'Walk-in booking',
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings', salonId] });
      queryClient.invalidateQueries({ queryKey: ['customers', salonId] });
      setCustomerName('');
      setCustomerPhone('');
      setSelectedService(null);
      setSelectedStaff(null);
      setNotes('');
      onClose();
      onSuccess?.();
    },
    onError: (error) => {
      console.error('Failed to create walk-in booking:', error);
      alert('Failed to create walk-in booking. Please try again.');
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedService) {
      alert('Please select a service');
      return;
    }
    createBookingMutation.mutate();
  };

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="fixed inset-0 bg-black/60 backdrop-blur-xl z-50"
          />

          {/* Modal */}
          <motion.div
            initial={{ opacity: 0, scale: 0.96, y: 12 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.95, y: 10 }}
            transition={{ type: 'spring', damping: 28, stiffness: 280 }}
            className="fixed inset-0 z-50 flex items-center justify-center p-4"
          >
            <div
              onClick={e => e.stopPropagation()}
              className="bg-white/5 border border-white/10 backdrop-blur-xl rounded-3xl w-full max-w-2xl max-h-[90vh] shadow-2xl overflow-hidden flex flex-col sm:flex-row"
            >
              {/* ── Left: Cinematic Photo Panel ──────────────────── */}
              <div className="relative hidden sm:flex flex-col justify-end w-[240px] shrink-0 overflow-hidden">
                <img
                  src="/images/salon-hero.jpg"
                  alt=""
                  className="absolute inset-0 w-full h-full object-cover"
                  style={{ objectPosition: 'center 20%' }}
                />
                {/* Layered scrims */}
                <div className="absolute inset-0 bg-gradient-to-r from-transparent to-black/60" />
                <div className="absolute inset-0 bg-gradient-to-b from-black/10 via-transparent to-black/70" />

                {/* Walk-in badge */}
                <div className="absolute top-5 left-5">
                  <span className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#FFD700]/20 border border-[#FFD700]/30 rounded-full text-[#FFD700] text-xs font-bold backdrop-blur-sm">
                    ✦ Walk-in
                  </span>
                </div>

                {/* Tagline */}
                <div className="relative p-6">
                  <p className="text-[#FFD700] text-xs font-semibold uppercase tracking-widest mb-2">
                    Welcome
                  </p>
                  <p className="text-white text-lg font-semibold leading-snug">
                    Walk-in?{' '}
                    <span className="text-[#FFD700]">We've got you.</span>
                  </p>
                  <p className="text-white/50 text-xs mt-2 leading-relaxed">
                    Every great visit starts right here.
                  </p>
                </div>
              </div>

              {/* ── Right: Form ──────────────────────────────────── */}
              <div className="flex-1 flex flex-col min-h-0">
                {/* Form header */}
                <div className="flex items-center justify-between p-5 pb-0 shrink-0">
                  <div>
                    <h2 className="text-lg font-semibold text-white">New Walk-in</h2>
                    <p className="text-white/70 text-xs mt-0.5">Fill in the details below</p>
                  </div>
                  <motion.button
                    whileHover={{ scale: 1.1 }}
                    whileTap={{ scale: 0.9 }}
                    onClick={onClose}
                    className="p-2 bg-white/10 hover:bg-white/20 border border-white/10 rounded-full transition-colors"
                  >
                    <X className="w-4 h-4 text-white/50 hover:text-white" />
                  </motion.button>
                </div>

                {/* Scrollable form body */}
                <form onSubmit={handleSubmit} className="flex-1 overflow-y-auto p-5 space-y-5">

                  {/* Customer details */}
                  <motion.div 
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.1 }}
                    className="space-y-3"
                  >
                    <label className="block text-white/50 text-xs font-semibold uppercase tracking-wider">
                      Customer <span className="text-white/30 normal-case font-normal">(optional)</span>
                    </label>
                    <motion.div 
                      initial={{ opacity: 0, x: -10 }}
                      animate={{ opacity: 1, x: 0 }}
                      transition={{ delay: 0.15 }}
                      className="relative"
                    >
                      <User className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/50" />
                      <motion.input
                        whileFocus={{ scale: 1.01 }}
                        type="text"
                        value={customerName}
                        onChange={e => setCustomerName(e.target.value)}
                        className="w-full pl-10 pr-4 py-3 bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl text-white placeholder-white/30 focus:outline-none focus:border-[#FFD700]/50 transition-all text-sm"
                        placeholder="Customer name"
                      />
                    </motion.div>
                    <motion.div 
                      initial={{ opacity: 0, x: -10 }}
                      animate={{ opacity: 1, x: 0 }}
                      transition={{ delay: 0.2 }}
                      className="relative"
                    >
                      <Phone className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/50" />
                      <motion.input
                        whileFocus={{ scale: 1.01 }}
                        type="tel"
                        value={customerPhone}
                        onChange={e => setCustomerPhone(e.target.value)}
                        className="w-full pl-10 pr-4 py-3 bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl text-white placeholder-white/30 focus:outline-none focus:border-[#FFD700]/50 transition-all text-sm"
                        placeholder="Phone number"
                      />
                    </motion.div>
                  </motion.div>

                  {/* Service selection */}
                  <motion.div 
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.25 }}
                  >
                    <label className="block text-white/50 text-xs font-semibold uppercase tracking-wider mb-3">
                      Service <span className="text-red-400">*</span>
                    </label>
                    {services.length === 0 ? (
                      <div className="bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl p-5 text-center">
                        <Scissors className="w-5 h-5 text-white/20 mx-auto mb-2" />
                        <p className="text-white/50 text-sm">No services available</p>
                      </div>
                    ) : (
                      <div className="space-y-2 max-h-[160px] overflow-y-auto pr-1">
                        {services.map((service: Service, index: number) => (
                          <motion.button
                            key={service.id}
                            type="button"
                            initial={{ opacity: 0, x: -10 }}
                            animate={{ opacity: 1, x: 0 }}
                            transition={{ delay: 0.3 + index * 0.05 }}
                            whileHover={{ scale: 1.02, x: 2 }}
                            whileTap={{ scale: 0.98 }}
                            onClick={() => setSelectedService(service)}
                            className={`w-full p-3 rounded-xl border-2 transition-all text-left flex items-center justify-between group backdrop-blur-xl ${
                              selectedService?.id === service.id
                                ? 'border-[#FFD700] bg-[#FFD700]/20'
                                : 'border-white/10 bg-white/5 hover:bg-white/10 hover:border-white/20'
                            }`}
                          >
                            <div className="flex items-center gap-2.5">
                              <motion.div 
                                className={`w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0 transition-all ${
                                  selectedService?.id === service.id
                                    ? 'border-[#FFD700] bg-[#FFD700]'
                                    : 'border-white/20'
                                }`}
                                animate={selectedService?.id === service.id ? {
                                  scale: [1, 1.2, 1],
                                } : {}}
                                transition={{ duration: 0.3 }}
                              >
                                {selectedService?.id === service.id && (
                                  <CheckCircle2 className="w-3 h-3 text-black" />
                                )}
                              </motion.div>
                              <div>
                                <p className="text-white font-medium text-sm">{service.name}</p>
                                <p className="text-white/50 text-xs">{service.duration} min</p>
                              </div>
                            </div>
                            <span className={`text-sm font-bold ${selectedService?.id === service.id ? 'text-[#FFD700]' : 'text-white/50'}`}>
                              UGX {service.price.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                            </span>
                          </motion.button>
                        ))}
                      </div>
                    )}
                  </motion.div>

                  {/* Staff selection */}
                  <motion.div 
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.4 }}
                  >
                    <label className="block text-white/50 text-xs font-semibold uppercase tracking-wider mb-3">
                      Assign Staff <span className="text-white/30 normal-case font-normal">(optional)</span>
                    </label>
                    {staff.length === 0 ? (
                      <p className="text-white/50 text-sm">No staff available</p>
                    ) : (
                      <div className="grid grid-cols-2 gap-2">
                        {staff.map((member: Staff, index: number) => (
                          <motion.button
                            key={member.id}
                            type="button"
                            initial={{ opacity: 0, scale: 0.9 }}
                            animate={{ opacity: 1, scale: 1 }}
                            transition={{ delay: 0.45 + index * 0.05 }}
                            whileHover={{ scale: 1.05, y: -2 }}
                            whileTap={{ scale: 0.95 }}
                            onClick={() => setSelectedStaff(selectedStaff?.id === member.id ? null : member)}
                            className={`p-3 rounded-xl border-2 transition-all text-left flex items-center gap-2.5 backdrop-blur-xl ${
                              selectedStaff?.id === member.id
                                ? 'border-[#FFD700] bg-[#FFD700]/20'
                                : 'border-white/10 bg-white/5 hover:bg-white/10 hover:border-white/20'
                            }`}
                          >
                            <motion.div 
                              className={`w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold shrink-0 ${
                                selectedStaff?.id === member.id ? 'bg-[#FFD700] text-black' : 'bg-white/10 text-white'
                              }`}
                              animate={selectedStaff?.id === member.id ? {
                                rotate: [0, 5, -5, 0],
                              } : {}}
                              transition={{ duration: 0.5 }}
                            >
                              {getInitials(member.name)}
                            </motion.div>
                            <p className="text-white font-medium text-xs truncate">{member.name}</p>
                          </motion.button>
                        ))}
                      </div>
                    )}
                  </motion.div>

                  {/* Notes */}
                  <motion.div 
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.55 }}
                  >
                    <label className="block text-white/50 text-xs font-semibold uppercase tracking-wider mb-2">
                      Notes <span className="text-white/30 normal-case font-normal">(optional)</span>
                    </label>
                    <motion.textarea
                      whileFocus={{ scale: 1.01 }}
                      value={notes}
                      onChange={e => setNotes(e.target.value)}
                      className="w-full px-4 py-3 bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl text-white placeholder-white/30 focus:outline-none focus:border-[#FFD700]/50 transition-all resize-none text-sm"
                      rows={2}
                      placeholder="Preferences, allergies, special requests..."
                    />
                  </motion.div>

                  {/* Submit */}
                  <motion.div 
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.6 }}
                    className="flex gap-3 pt-1 pb-2"
                  >
                    <motion.button
                      type="button"
                      whileHover={{ scale: 1.02 }}
                      whileTap={{ scale: 0.98 }}
                      onClick={onClose}
                      className="flex-1 py-3 rounded-xl font-medium bg-white/5 border border-white/10 text-white hover:bg-white/10 transition-colors text-sm backdrop-blur-xl"
                    >
                      Cancel
                    </motion.button>
                    <motion.button
                      type="submit"
                      whileHover={{ scale: 1.02 }}
                      whileTap={{ scale: 0.98 }}
                      disabled={createBookingMutation.isPending || !selectedService}
                      className="flex-1 py-3 rounded-xl font-semibold bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black hover:opacity-90 transition-opacity disabled:opacity-40 disabled:cursor-not-allowed shadow-lg shadow-[#FFD700]/30 text-sm"
                    >
                      {createBookingMutation.isPending ? 'Creating...' : 'Create Walk-in'}
                    </motion.button>
                  </motion.div>
                </form>
              </div>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}
