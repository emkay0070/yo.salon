'use client';

import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, Clock, Calendar, User, CheckCircle, XCircle, RotateCcw, AlertCircle } from 'lucide-react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';

interface ResolutionModalProps {
  isOpen: boolean;
  onClose: () => void;
  booking: {
    id: string;
    customerName: string;
    service: string;
    staffName: string;
    time: string;
    date?: string;
    operational_state?: string;
    grace_period_ends_at?: string | null;
  };
  onSuccess?: () => void;
}

type ResolutionStep = 'initial' | 'reason' | 'follow-up' | 'confirm';

export default function ResolutionModal({ isOpen, onClose, booking, onSuccess }: ResolutionModalProps) {
  const queryClient = useQueryClient();
  const [step, setStep] = useState<ResolutionStep>('initial');
  const [selectedOutcome, setSelectedOutcome] = useState<string | null>(null);
  const [selectedReason, setSelectedReason] = useState<string | null>(null);
  const [notes, setNotes] = useState('');
  const [followUpRequired, setFollowUpRequired] = useState(false);
  const [followUpDate, setFollowUpDate] = useState('');
  const [followUpNotes, setFollowUpNotes] = useState('');

  // Calculate overdue minutes
  const getOverdueMinutes = () => {
    if (!booking.grace_period_ends_at) return 0;
    const now = new Date();
    const endsAt = new Date(booking.grace_period_ends_at);
    return Math.floor((now.getTime() - endsAt.getTime()) / 60000);
  };

  const overdueMinutes = getOverdueMinutes();

  // Outcome options with user-friendly labels
  const outcomes = [
    { 
      id: 'CUSTOMER_ARRIVED', 
      label: 'Customer arrived', 
      icon: CheckCircle, 
      color: 'from-emerald-500 to-emerald-600',
      description: 'Customer arrived after grace period'
    },
    { 
      id: 'NO_SHOW', 
      label: "Customer didn't arrive", 
      icon: XCircle, 
      color: 'from-orange-500 to-orange-600',
      description: 'Customer did not show up'
    },
    { 
      id: 'CANCELLED', 
      label: 'Customer cancelled', 
      icon: XCircle, 
      color: 'from-red-500 to-red-600',
      description: 'Customer cancelled the appointment'
    },
    { 
      id: 'RESCHEDULED', 
      label: 'Reschedule requested', 
      icon: RotateCcw, 
      color: 'from-cyan-500 to-cyan-600',
      description: 'Customer wants to reschedule'
    },
    { 
      id: 'OTHER', 
      label: 'Something else', 
      icon: AlertCircle, 
      color: 'from-gray-500 to-gray-600',
      description: 'Other situation'
    },
  ];

  // Reason codes based on selected outcome
  const getReasons = (outcome: string) => {
    switch (outcome) {
      case 'NO_SHOW':
        return [
          { id: 'CUSTOMER_DID_NOT_ARRIVE', label: 'Customer did not arrive' },
          { id: 'CUSTOMER_COULD_NOT_BE_REACHED', label: 'Could not reach customer' },
          { id: 'OTHER', label: 'Other reason' },
        ];
      case 'CANCELLED':
        return [
          { id: 'CUSTOMER_CANCELLED', label: 'Customer cancelled' },
          { id: 'PROVIDER_UNAVAILABLE', label: 'Provider unavailable' },
          { id: 'SPECIALIST_UNAVAILABLE', label: 'Specialist unavailable' },
          { id: 'LOCATION_CLOSED', label: 'Location closed' },
          { id: 'OTHER', label: 'Other reason' },
        ];
      case 'RESCHEDULED':
        return [
          { id: 'CUSTOMER_REQUESTED_RESCHEDULE', label: 'Customer requested reschedule' },
          { id: 'PROVIDER_INITIATED_RESCHEDULE', label: 'Provider initiated reschedule' },
          { id: 'OTHER', label: 'Other reason' },
        ];
      case 'CUSTOMER_ARRIVED':
        return [
          { id: 'CUSTOMER_ARRIVED_LATE', label: 'Customer arrived late' },
          { id: 'OTHER', label: 'Other reason' },
        ];
      default:
        return [{ id: 'OTHER', label: 'Other reason' }];
    }
  };

  const resolveMutation = useMutation({
    mutationFn: async (data: any) => {
      return await apiClient.resolveBooking(booking.id, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings'] });
      resetForm();
      onClose();
      onSuccess?.();
    },
    onError: (error) => {
      console.error('Failed to resolve booking:', error);
      alert('Failed to resolve booking. Please try again.');
    },
  });

  const resetForm = () => {
    setStep('initial');
    setSelectedOutcome(null);
    setSelectedReason(null);
    setNotes('');
    setFollowUpRequired(false);
    setFollowUpDate('');
    setFollowUpNotes('');
  };

  const handleOutcomeSelect = (outcome: string) => {
    setSelectedOutcome(outcome);
    if (outcome === 'CUSTOMER_ARRIVED') {
      // Skip reason selection for late arrival
      setStep('follow-up');
    } else {
      setStep('reason');
    }
  };

  const handleReasonSelect = (reason: string) => {
    setSelectedReason(reason);
    setStep('follow-up');
  };

  const handleConfirm = () => {
    const payload: any = {
      outcome: selectedOutcome === 'CUSTOMER_ARRIVED' ? 'CUSTOMER_ARRIVED' :
              selectedOutcome === 'NO_SHOW' ? 'NO_SHOW' :
              selectedOutcome === 'CANCELLED' ? 'CANCELLED' :
              selectedOutcome === 'RESCHEDULED' ? 'RESCHEDULED' :
              selectedOutcome === 'OTHER' ? 'COMPLETED_LATE' : 'COMPLETED_LATE',
      reason_code: selectedReason || 'OTHER',
      notes: notes || undefined,
      follow_up_required: followUpRequired,
      follow_up_at: followUpRequired && followUpDate ? followUpDate : undefined,
      follow_up_notes: followUpRequired && followUpNotes ? followUpNotes : undefined,
    };

    // Only include new_booking_data if outcome is RESCHEDULED
    if (payload.outcome === 'RESCHEDULED') {
      // This would require a rescheduling flow - for now, prevent this
      alert('Rescheduling requires selecting a new date and time. Please use the Reschedule option instead.');
      return;
    }

    resolveMutation.mutate(payload);
  };

  const handleClose = () => {
    resetForm();
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
            className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50"
          />
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.95 }}
            className="fixed inset-0 flex items-center justify-center z-50 p-4"
          >
            <div className="bg-card border border-border-light rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
              {/* Header */}
              <div className="p-6 border-b border-border-light">
                <div className="flex items-center justify-between mb-4">
                  <h2 className="text-xl font-bold text-text-primary">
                    {booking.customerName}'s appointment needs your attention
                  </h2>
                  <button
                    onClick={handleClose}
                    className="p-2 rounded-lg hover:bg-white/10 transition-colors"
                  >
                    <X className="w-5 h-5 text-text-secondary" />
                  </button>
                </div>

                {/* Booking summary */}
                <div className="bg-surface/50 border border-border-light rounded-xl p-4">
                  <div className="flex items-center gap-3 mb-2">
                    <Clock className="w-4 h-4 text-gold" />
                    <span className="text-text-primary font-medium">{booking.time}</span>
                    <span className="text-text-secondary">·</span>
                    <span className="text-text-secondary">{booking.service}</span>
                    <span className="text-text-secondary">·</span>
                    <span className="text-text-secondary">{booking.staffName}</span>
                  </div>
                  <div className="flex items-center gap-2 text-red-400">
                    <AlertCircle className="w-4 h-4" />
                    <span className="text-sm font-medium">{overdueMinutes} minutes overdue</span>
                  </div>
                </div>
              </div>

              {/* Body */}
              <div className="p-6">
                {step === 'initial' && (
                  <div className="space-y-4">
                    <p className="text-text-primary font-medium">What happened?</p>
                    <div className="space-y-2">
                      {outcomes.map((outcome) => {
                        const Icon = outcome.icon;
                        return (
                          <button
                            key={outcome.id}
                            onClick={() => handleOutcomeSelect(outcome.id)}
                            className="w-full flex items-center gap-3 p-4 rounded-xl border border-border-light hover:border-[rgba(255,215,0,0.4)] hover:bg-white/5 transition-all text-left"
                          >
                            <div className={`p-2 rounded-lg bg-gradient-to-br ${outcome.color}`}>
                              <Icon className="w-5 h-5 text-text-primary" />
                            </div>
                            <div className="flex-1">
                              <p className="text-text-primary font-semibold">{outcome.label}</p>
                              <p className="text-text-secondary text-sm">{outcome.description}</p>
                            </div>
                          </button>
                        );
                      })}
                    </div>
                  </div>
                )}

                {step === 'reason' && (
                  <div className="space-y-4">
                    <button
                      onClick={() => setStep('initial')}
                      className="text-text-secondary text-sm hover:text-text-primary flex items-center gap-1"
                    >
                      <X className="w-4 h-4" /> Back
                    </button>
                    <p className="text-text-primary font-medium">Why?</p>
                    <div className="space-y-2">
                      {getReasons(selectedOutcome!).map((reason) => (
                        <button
                          key={reason.id}
                          onClick={() => handleReasonSelect(reason.id)}
                          className="w-full p-3 rounded-xl border border-border-light hover:border-[rgba(255,215,0,0.4)] hover:bg-white/5 transition-all text-left text-text-primary"
                        >
                          {reason.label}
                        </button>
                      ))}
                    </div>
                  </div>
                )}

                {step === 'follow-up' && (
                  <div className="space-y-4">
                    <button
                      onClick={() => setStep('reason')}
                      className="text-text-secondary text-sm hover:text-text-primary flex items-center gap-1"
                    >
                      <X className="w-4 h-4" /> Back
                    </button>

                    {/* Notes */}
                    <div>
                      <label className="text-text-primary font-medium block mb-2">Notes (optional)</label>
                      <textarea
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                        placeholder="Add any additional context..."
                        className="w-full p-3 rounded-xl border border-border-light bg-surface text-text-primary placeholder-text-muted resize-none h-24"
                      />
                    </div>

                    {/* Follow-up */}
                    <div className="bg-surface/50 border border-border-light rounded-xl p-4">
                      <label className="flex items-center gap-3 cursor-pointer">
                        <input
                          type="checkbox"
                          checked={followUpRequired}
                          onChange={(e) => setFollowUpRequired(e.target.checked)}
                          className="w-5 h-5 rounded border-border-light"
                        />
                        <span className="text-text-primary font-medium">Follow up with customer</span>
                      </label>

                      {followUpRequired && (
                        <div className="mt-4 space-y-3">
                          <div>
                            <label className="text-text-secondary text-sm block mb-1">Follow-up date</label>
                            <input
                              type="date"
                              value={followUpDate}
                              onChange={(e) => setFollowUpDate(e.target.value)}
                              className="w-full p-2 rounded-lg border border-border-light bg-card text-text-primary"
                            />
                          </div>
                          <div>
                            <label className="text-text-secondary text-sm block mb-1">Follow-up notes</label>
                            <textarea
                              value={followUpNotes}
                              onChange={(e) => setFollowUpNotes(e.target.value)}
                              placeholder="What to follow up about..."
                              className="w-full p-2 rounded-lg border border-border-light bg-card text-text-primary placeholder-text-muted resize-none h-20"
                            />
                          </div>
                        </div>
                      )}
                    </div>

                    <button
                      onClick={handleConfirm}
                      disabled={resolveMutation.isPending}
                      className="w-full py-3 rounded-xl font-medium bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-obsidian hover:opacity-90 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {resolveMutation.isPending ? 'Saving...' : 'Save decision'}
                    </button>
                  </div>
                )}
              </div>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}
