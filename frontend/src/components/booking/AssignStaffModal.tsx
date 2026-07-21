'use client';

import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, UserCheck } from 'lucide-react';
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

interface Staff {
  id: string;
  name: string;
  specializations?: string[];
  available?: boolean;
}

export default function AssignStaffModal({ isOpen, onClose, booking, onSuccess }: AssignStaffModalProps) {
  const queryClient = useQueryClient();
  const { salonId } = useRole();
  const [selectedStaff, setSelectedStaff] = useState<Staff | null>(null);

  const { data: staff = [] } = useQuery({
    queryKey: ['staff', salonId],
    queryFn: () => apiClient.getStaff({ salon_id: salonId }),
    enabled: isOpen && !!salonId,
  });

  const updateBookingMutation = useMutation({
    mutationFn: async (data: any) => {
      return await apiClient.updateBooking(booking.id, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings', salonId] });
      setSelectedStaff(null);
      onClose();
      onSuccess?.();
    },
    onError: (error) => {
      console.error('Failed to assign staff:', error);
      alert('Failed to assign staff. Please try again.');
    },
  });

  const handleAssignStaff = () => {
    if (!selectedStaff) {
      alert('Please select a staff member');
      return;
    }

    updateBookingMutation.mutate({
      staff_id: selectedStaff.id,
    });
  };

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50"
          />
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.95 }}
            className="fixed inset-0 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4"
          >
            <div className="bg-card border border-border-light rounded-t-2xl sm:rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
              <div className="p-4 sm:p-6">
                <div className="flex items-center justify-between mb-6">
                  <div>
                    <h2 className="text-xl font-bold text-text-primary">Assign Staff</h2>
                    <p className="text-text-secondary text-sm mt-1">
                      {booking.customerName} - {booking.service}
                    </p>
                  </div>
                  <button
                    onClick={onClose}
                    className="p-2 rounded-lg hover:bg-white/10 transition-colors"
                  >
                    <X className="w-5 h-5 text-text-secondary" />
                  </button>
                </div>

                <div className="bg-card border border-border-light rounded-xl p-4 mb-6">
                  <h3 className="text-text-primary font-semibold mb-2">Currently Assigned</h3>
                  <div className="flex items-center gap-2 text-text-secondary">
                    <UserCheck className="w-4 h-4" />
                    <span>{booking.staffName}</span>
                  </div>
                </div>

                <div className="mb-6">
                  <h3 className="text-text-primary font-semibold mb-3">Select Staff Member</h3>
                  <div className="space-y-2 max-h-48 sm:max-h-60 overflow-y-auto">
                    {staff.map((member: Staff) => (
                      <button
                        key={member.id}
                        type="button"
                        onClick={() => setSelectedStaff(member)}
                        className={`w-full p-4 rounded-xl border-2 transition-all text-left ${
                          selectedStaff?.id === member.id
                            ? 'border-[#FFD700] bg-[#FFD700]/10'
                            : 'border-border-light bg-card hover:bg-white/10'
                        }`}
                      >
                        <div className="flex items-start gap-4">
                          <div className="relative">
                            <div className="w-12 h-12 rounded-full bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center text-black font-bold text-lg">
                              {member.name.charAt(0)}
                            </div>
                            <div className={`absolute -bottom-1 -right-1 w-4 h-4 rounded-full border-2 border-[#1A1A1A] ${
                              member.available ? 'bg-green-500' : 'bg-yellow-500'
                            }`} />
                          </div>
                          <div className="flex-1">
                            <p className="text-text-primary font-semibold">{member.name}</p>
                            <p className="text-text-secondary text-sm mt-1">
                              {member.specializations?.join(', ') || 'All services'}
                            </p>
                          </div>
                        </div>
                      </button>
                    ))}
                  </div>
                </div>

                <div className="flex gap-3">
                  <button
                    onClick={onClose}
                    className="flex-1 py-3 rounded-xl font-medium bg-white/10 text-text-primary hover:bg-white/20 transition-colors"
                  >
                    Cancel
                  </button>
                  <button
                    onClick={handleAssignStaff}
                    disabled={updateBookingMutation.isPending || !selectedStaff}
                    className="flex-1 py-3 rounded-xl font-medium bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-obsidian hover:opacity-90 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {updateBookingMutation.isPending ? 'Assigning...' : 'Assign Staff'}
                  </button>
                </div>
              </div>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}
