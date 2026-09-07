'use client';

import { useState } from 'react';
import { createPortal } from 'react-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { X, UserCheck, ChevronRight, ChevronLeft } from 'lucide-react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useRole } from '@/contexts/RoleContext';
import { apiClient } from '@/lib/api-client';

interface AssignStaffModalProps {
  isOpen: boolean;
  onClose: () => void;
  booking: {
    id: string;
    customerName: string;
    service: string;
    staffName: string;
  };
  onSuccess?: () => void;
}

interface Specialist {
  id: string;
  name: string;
  specialties?: string[];
  available?: boolean;
  photo?: string;
  role?: string;
}

export default function AssignStaffModal({ isOpen, onClose, booking, onSuccess }: AssignStaffModalProps) {
  const queryClient = useQueryClient();
  const { salonId } = useRole();
  const [selectedSpecialist, setSelectedSpecialist] = useState<Specialist | null>(null);
  const [showSpecialists, setShowSpecialists] = useState(false);

  const { data: specialists = [] } = useQuery({
    queryKey: ['specialists', salonId],
    queryFn: () => salonId ? apiClient.getSalonSpecialists(salonId) : Promise.resolve([]),
    enabled: isOpen && !!salonId,
  });

  const updateBookingMutation = useMutation({
    mutationFn: async (data: any) => {
      console.log('[AssignStaffModal] Updating booking with data:', data);
      console.log('[AssignStaffModal] Booking ID:', booking.id);
      console.log('[AssignStaffModal] Selected specialist:', selectedSpecialist);
      return await apiClient.updateBooking(booking.id, data);
    },
    onSuccess: (response) => {
      console.log('[AssignStaffModal] Update successful, response:', response);
      console.log('[AssignStaffModal] Invalidating queries...');
      queryClient.invalidateQueries({ queryKey: ['bookings'] });
      queryClient.invalidateQueries({ queryKey: ['bookings', salonId] });
      queryClient.invalidateQueries({ queryKey: ['booking', booking.id] });
      queryClient.invalidateQueries({ queryKey: ['specialists', salonId] });
      console.log('[AssignStaffModal] Queries invalidated');
      setSelectedSpecialist(null);
      setShowSpecialists(false);
      onClose();
      onSuccess?.();
    },
    onError: (error) => {
      console.error('[AssignStaffModal] Failed to assign specialist:', error);
      alert('Failed to assign specialist. Please try again.');
    },
  });

  const handleAssignStaff = () => {
    if (!selectedSpecialist) {
      alert('Please select a specialist');
      return;
    }

    updateBookingMutation.mutate({
      specialist_id: selectedSpecialist.id,
    });
  };

  const handleBack = () => {
    setShowSpecialists(false);
  };

  if (typeof window === 'undefined') return null;

  return createPortal(
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="fixed inset-0 bg-black/60 backdrop-blur-xl"
            style={{ zIndex: 99999 }}
          />
          
          {/* Main Container */}
          <div className="fixed inset-0 flex items-center justify-center p-4 pointer-events-none" style={{ zIndex: 100000 }}>
            <div className="pointer-events-auto w-full max-w-6xl">
              <div className="flex flex-col lg:flex-row gap-8 items-center justify-center w-full min-h-0 lg:items-start">
                
                {/* Booking Details Card - Vision Pro Glassmorphism */}
                <AnimatePresence mode="wait">
                  {!showSpecialists && (
                    <motion.div
                      key="booking-card"
                      initial={{ opacity: 0, scale: 0.95 }}
                      animate={{ opacity: 1, scale: 1 }}
                      exit={{ opacity: 0, scale: 0.95, x: -100 }}
                      transition={{ duration: 0.3 }}
                      className="w-full lg:w-80 lg:sticky lg:top-8 bg-white/10 backdrop-blur-2xl border border-white/20 rounded-3xl p-6 shadow-2xl"
                    >
                      <div className="flex items-start justify-between mb-6">
                        <div>
                          <h2 className="text-2xl font-bold text-white mb-2">Assign Staff</h2>
                          <p className="text-white/70 text-sm">
                            {booking.customerName} - {booking.service}
                          </p>
                        </div>
                        <button
                          onClick={onClose}
                          className="p-2 rounded-full bg-white/10 hover:bg-white/20 transition-colors"
                        >
                          <X className="w-5 h-5 text-white" />
                        </button>
                      </div>

                      <div className="bg-white/5 border border-white/10 rounded-2xl p-4 mb-6">
                        <div className="flex items-center gap-3">
                          <div className="w-10 h-10 rounded-full bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center">
                            <UserCheck className="w-5 h-5 text-black" />
                          </div>
                          <div>
                            <p className="text-white/60 text-xs uppercase tracking-wider">Currently Assigned</p>
                            <p className="text-white font-semibold">{booking.staffName}</p>
                          </div>
                        </div>
                      </div>

                      <button
                        onClick={onClose}
                        className="w-full py-3 rounded-xl font-medium bg-white/10 text-white hover:bg-white/20 transition-colors"
                      >
                        Cancel
                      </button>
                    </motion.div>
                  )}
                </AnimatePresence>

                {/* Glassy Select Staff Button - Outside Card */}
                <AnimatePresence mode="wait">
                  {!showSpecialists && (
                    <>
                      <motion.button
                        key="select-staff-btn"
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -20 }}
                        transition={{ duration: 0.3, delay: 0.1 }}
                        onClick={() => setShowSpecialists(true)}
                        className="lg:hidden w-full py-3 px-10 bg-white/10 backdrop-blur-xl border border-white/20 rounded-2xl font-medium bg-gradient-to-r from-[#FFD700]/30 to-[#C9A227]/30 text-white hover:from-[#FFD700]/50 hover:to-[#C9A227]/50 transition-all shadow-2xl shadow-[#FFD700]/30 hover:shadow-[#FFD700]/50 flex items-center justify-center gap-3 text-base"
                      >
                        <span>Select Staff Member</span>
                        <ChevronRight className="w-5 h-5" />
                      </motion.button>
                    </>
                  )}
                </AnimatePresence>

                {/* Floating Staff Cards - Masonry Layout in Open Space */}
                <AnimatePresence mode="wait">
                  {(showSpecialists || window.innerWidth >= 1024) && (
                    <motion.div
                      key="specialists-grid"
                      initial={{ opacity: 0, x: showSpecialists ? 100 : 20 }}
                      animate={{ opacity: 1, x: 0 }}
                      exit={{ opacity: 0, x: 100 }}
                      transition={{ duration: 0.3 }}
                      className="w-full flex flex-col items-center justify-center"
                    >
                      <div className="flex items-center gap-3 mb-6 w-full justify-center">
                        <button
                          onClick={handleBack}
                          className="lg:hidden p-2 rounded-full bg-white/10 hover:bg-white/20 transition-colors"
                        >
                          <ChevronLeft className="w-5 h-5 text-white" />
                        </button>
                        <h3 className="text-white font-semibold text-lg">Select Staff Member</h3>
                      </div>
                      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 gap-3 md:gap-4 max-w-4xl mx-auto justify-items-center">
                        {specialists.map((member: Specialist, index: number) => (
                          <motion.button
                            key={member.id}
                            type="button"
                            onClick={() => setSelectedSpecialist(member)}
                            initial={{ opacity: 0, scale: 0.8, y: 20 }}
                            animate={{ 
                              opacity: 1, 
                              scale: 1, 
                              y: 0,
                            }}
                            whileHover={{ 
                              scale: 1.05,
                              y: -4,
                              transition: { duration: 0.2 }
                            }}
                            whileTap={{ scale: 0.95 }}
                            transition={{ 
                              duration: 0.3, 
                              delay: index * 0.05,
                              type: "spring",
                              stiffness: 200,
                              damping: 15
                            }}
                            className={`relative p-3 md:p-5 rounded-2xl md:rounded-3xl border-2 transition-all text-left ${
                              selectedSpecialist?.id === member.id
                                ? 'border-[#FFD700] bg-[#FFD700]/20 shadow-2xl shadow-[#FFD700]/30'
                                : 'border-white/20 bg-white/10 hover:bg-white/20 hover:border-white/30 shadow-lg'
                            }`}
                          >
                            <div className="flex flex-col items-center text-center">
                              <motion.div 
                                className="relative mb-2 md:mb-4"
                                animate={selectedSpecialist?.id === member.id ? {
                                  scale: [1, 1.1, 1],
                                  rotate: [0, 5, -5, 0]
                                } : {}}
                                transition={{ 
                                  duration: 0.5,
                                  repeat: selectedSpecialist?.id === member.id ? Infinity : 0,
                                  repeatDelay: 2
                                }}
                              >
                                <div className="w-14 h-14 md:w-20 md:h-20 rounded-full bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center text-black font-bold text-lg md:text-2xl shadow-xl">
                                  {member.name.charAt(0)}
                                </div>
                                <motion.div 
                                  className={`absolute -bottom-1 -right-1 w-4 h-4 md:w-5 md:h-5 rounded-full border-2 border-black ${
                                    member.available ? 'bg-green-500' : 'bg-yellow-500'
                                  }`}
                                  animate={{
                                    scale: [1, 1.2, 1],
                                    opacity: [1, 0.7, 1]
                                  }}
                                  transition={{
                                    duration: 2,
                                    repeat: Infinity,
                                    ease: "easeInOut"
                                  }}
                                />
                              </motion.div>
                              <p className="text-white font-semibold text-xs md:text-base mb-1 md:mb-2">{member.name}</p>
                              <p className="text-white/60 text-[10px] md:text-xs leading-relaxed">
                                {Array.isArray(member.specialties) && member.specialties.length > 0 
                                  ? member.specialties.slice(0, 2).join(', ') 
                                  : 'All services'}
                              </p>
                              {selectedSpecialist?.id === member.id && (
                                <motion.div
                                  initial={{ scale: 0 }}
                                  animate={{ scale: 1 }}
                                  className="absolute -top-2 -right-2 w-5 h-5 md:w-6 md:h-6 bg-[#FFD700] rounded-full flex items-center justify-center"
                                >
                                  <svg className="w-3 h-3 md:w-4 md:h-4 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                                  </svg>
                                </motion.div>
                              )}
                            </div>
                          </motion.button>
                        ))}
                      </div>
                      
                      {/* Desktop Assign Button */}
                      {selectedSpecialist && (
                        <motion.div
                          initial={{ opacity: 0, y: 20 }}
                          animate={{ opacity: 1, y: 0 }}
                          className="hidden lg:block mt-6"
                        >
                          <button
                            onClick={handleAssignStaff}
                            disabled={updateBookingMutation.isPending}
                            className="w-full py-3 px-10 bg-white/10 backdrop-blur-xl border border-white/20 rounded-2xl font-medium bg-gradient-to-r from-[#FFD700]/30 to-[#C9A227]/30 text-white hover:from-[#FFD700]/50 hover:to-[#C9A227]/50 transition-all shadow-2xl shadow-[#FFD700]/30 hover:shadow-[#FFD700]/50 text-base disabled:opacity-50 disabled:cursor-not-allowed"
                          >
                            {updateBookingMutation.isPending ? 'Assigning...' : `Assign ${selectedSpecialist.name}`}
                          </button>
                        </motion.div>
                      )}
                      
                      {/* Mobile assign button */}
                      {showSpecialists && selectedSpecialist && (
                        <motion.div
                          initial={{ opacity: 0, y: 20 }}
                          animate={{ opacity: 1, y: 0 }}
                          className="lg:hidden mt-6"
                        >
                          <button
                            onClick={handleAssignStaff}
                            disabled={updateBookingMutation.isPending}
                            className="w-full py-4 px-10 rounded-2xl font-medium bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black hover:opacity-90 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed shadow-lg shadow-[#FFD700]/30 text-lg"
                          >
                            {updateBookingMutation.isPending ? 'Assigning...' : `Assign ${selectedSpecialist.name}`}
                          </button>
                        </motion.div>
                      )}
                    </motion.div>
                  )}
                </AnimatePresence>
              </div>
            </div>
          </div>
        </>
      )}
    </AnimatePresence>,
    document.body
  );
}
