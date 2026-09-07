'use client';

import { motion, AnimatePresence } from 'framer-motion';
import { 
  AlertTriangle, 
  X, 
  Check, 
  ArrowRight,
  Calendar,
  CreditCard,
  Loader2
} from 'lucide-react';
import { useState } from 'react';

interface CancelSubscriptionModalProps {
  isOpen: boolean;
  onClose: () => void;
  renewsAt?: string;
  isTrialing?: boolean;
}

const CANCEL_REASONS = [
  'Too expensive',
  'Not using features enough',
  'Found alternative solution',
  'Temporary break from practice',
  'Technical issues',
  'Other',
];

export default function CancelSubscriptionModal({ 
  isOpen, 
  onClose, 
  renewsAt,
  isTrialing 
}: CancelSubscriptionModalProps) {
  const [step, setStep] = useState<'warning' | 'reason' | 'confirm' | 'processing' | 'success'>('warning');
  const [selectedReason, setSelectedReason] = useState<string>('');
  const [feedback, setFeedback] = useState('');

  if (!isOpen) return null;

  const handleContinue = () => {
    if (step === 'warning') {
      setStep('reason');
    } else if (step === 'reason') {
      setStep('confirm');
    } else if (step === 'confirm') {
      setStep('processing');
      // Simulate API call
      setTimeout(() => {
        setStep('success');
      }, 2000);
    }
  };

  const handleClose = () => {
    setStep('warning');
    setSelectedReason('');
    setFeedback('');
    onClose();
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
            onClick={handleClose}
            className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50"
          />

          {/* Modal */}
          <motion.div
            initial={{ opacity: 0, scale: 0.95, y: 20 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.95, y: 20 }}
            className="fixed inset-0 z-50 flex items-center justify-center p-4"
          >
            <div className="bg-card border border-border-light rounded-2xl max-w-lg w-full">
              {step === 'warning' && (
                <div className="p-6 space-y-6">
                  {/* Header */}
                  <div className="flex items-start justify-between">
                    <div className="flex items-center gap-4">
                      <div className="w-14 h-14 rounded-full bg-red-500/20 flex items-center justify-center">
                        <AlertTriangle className="w-7 h-7 text-red-400" />
                      </div>
                      <div>
                        <h2 className="text-2xl font-bold text-text-primary">
                          Cancel Subscription?
                        </h2>
                        <p className="text-text-secondary">
                          {isTrialing ? 'End your trial early' : 'You will lose access to Pro features'}
                        </p>
                      </div>
                    </div>
                    <button
                      onClick={handleClose}
                      className="p-2 rounded-lg hover:bg-surface text-text-secondary"
                    >
                      <X className="w-5 h-5" />
                    </button>
                  </div>

                  {/* Warning Content */}
                  <div className="bg-red-500/10 border border-red-500/20 rounded-xl p-4 space-y-3">
                    <div className="flex items-start gap-3">
                      <AlertTriangle className="w-5 h-5 text-red-400 shrink-0 mt-0.5" />
                      <div className="space-y-2">
                        <p className="font-medium text-text-primary">
                          {isTrialing 
                            ? 'Your trial will end immediately'
                            : 'Your subscription will end at the current billing period'
                          }
                        </p>
                        {!isTrialing && renewsAt && (
                          <p className="text-sm text-text-secondary">
                            You will continue to have access until {new Date(renewsAt).toLocaleDateString()}
                          </p>
                        )}
                        <p className="text-sm text-text-secondary">
                          After cancellation, you will lose access to:
                        </p>
                        <ul className="text-sm text-text-secondary space-y-1 ml-4">
                          <li>• Career tracking & milestones</li>
                          <li>• Business intelligence & analytics</li>
                          <li>• Revenue tracking & reports</li>
                          <li>• Growth goals & achievements</li>
                        </ul>
                      </div>
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="flex items-center gap-3">
                    <button
                      onClick={handleClose}
                      className="flex-1 py-3 border border-border-light rounded-xl font-semibold text-text-primary hover:bg-surface transition-colors"
                    >
                      Keep Subscription
                    </button>
                    <button
                      onClick={handleContinue}
                      className="flex-1 py-3 bg-red-500 text-white rounded-xl font-semibold hover:bg-red-600 transition-colors"
                    >
                      Continue
                    </button>
                  </div>
                </div>
              )}

              {step === 'reason' && (
                <div className="p-6 space-y-6">
                  <div className="flex items-start justify-between">
                    <div>
                      <h2 className="text-2xl font-bold text-text-primary">
                        Why are you leaving?
                      </h2>
                      <p className="text-text-secondary">
                        Help us improve by sharing your reason
                      </p>
                    </div>
                    <button
                      onClick={handleClose}
                      className="p-2 rounded-lg hover:bg-surface text-text-secondary"
                    >
                      <X className="w-5 h-5" />
                    </button>
                  </div>

                  <div className="space-y-3">
                    {CANCEL_REASONS.map((reason) => (
                      <button
                        key={reason}
                        onClick={() => setSelectedReason(reason)}
                        className={`w-full p-4 rounded-xl text-left transition-colors ${
                          selectedReason === reason
                            ? 'bg-red-500/10 border-2 border-red-500/30'
                            : 'bg-surface border-2 border-transparent hover:border-border-light'
                        }`}
                      >
                        <div className="flex items-center justify-between">
                          <span className="text-text-primary">{reason}</span>
                          {selectedReason === reason && (
                            <Check className="w-5 h-5 text-red-400" />
                          )}
                        </div>
                      </button>
                    ))}
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-text-primary mb-2">
                      Additional feedback (optional)
                    </label>
                    <textarea
                      value={feedback}
                      onChange={(e) => setFeedback(e.target.value)}
                      placeholder="Tell us more about your experience..."
                      rows={3}
                      className="w-full bg-surface border border-border-light rounded-xl px-4 py-3 text-text-primary placeholder-text-muted focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 transition-all"
                    />
                  </div>

                  <div className="flex items-center gap-3">
                    <button
                      onClick={() => setStep('warning')}
                      className="flex-1 py-3 border border-border-light rounded-xl font-semibold text-text-primary hover:bg-surface transition-colors"
                    >
                      Back
                    </button>
                    <button
                      onClick={handleContinue}
                      disabled={!selectedReason}
                      className="flex-1 py-3 bg-red-500 text-white rounded-xl font-semibold hover:bg-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      Continue
                    </button>
                  </div>
                </div>
              )}

              {step === 'confirm' && (
                <div className="p-6 space-y-6">
                  <div className="flex items-start justify-between">
                    <div>
                      <h2 className="text-2xl font-bold text-text-primary">
                        Confirm Cancellation
                      </h2>
                      <p className="text-text-secondary">
                        This action Cannot be undone
                      </p>
                    </div>
                    <button
                      onClick={handleClose}
                      className="p-2 rounded-lg hover:bg-surface text-text-secondary"
                    >
                      <X className="w-5 h-5" />
                    </button>
                  </div>

                  <div className="bg-surface rounded-xl p-4 space-y-3">
                    <div className="flex items-center justify-between">
                      <span className="text-text-secondary">Reason</span>
                      <span className="font-medium text-text-primary">{selectedReason}</span>
                    </div>
                    {feedback && (
                      <div className="flex items-start justify-between">
                        <span className="text-text-secondary">Feedback</span>
                        <span className="font-medium text-text-primary text-right max-w-[200px]">{feedback}</span>
                      </div>
                    )}
                    <div className="flex items-center justify-between">
                      <span className="text-text-secondary">Effective date</span>
                      <span className="font-medium text-text-primary">
                        {isTrialing ? 'Immediately' : renewsAt ? new Date(renewsAt).toLocaleDateString() : 'End of billing period'}
                      </span>
                    </div>
                  </div>

                  <div className="flex items-center gap-3">
                    <button
                      onClick={() => setStep('reason')}
                      className="flex-1 py-3 border border-border-light rounded-xl font-semibold text-text-primary hover:bg-surface transition-colors"
                    >
                      Back
                    </button>
                    <button
                      onClick={handleContinue}
                      className="flex-1 py-3 bg-red-500 text-white rounded-xl font-semibold hover:bg-red-600 transition-colors"
                    >
                      Confirm Cancellation
                    </button>
                  </div>
                </div>
              )}

              {step === 'processing' && (
                <div className="p-12 text-center space-y-4">
                  <Loader2 className="w-12 h-12 text-gold animate-spin mx-auto" />
                  <h2 className="text-xl font-bold text-text-primary">Processing cancellation...</h2>
                  <p className="text-text-secondary">Please wait while we update your subscription</p>
                </div>
              )}

              {step === 'success' && (
                <div className="p-12 text-center space-y-6">
                  <div className="w-16 h-16 rounded-full bg-green-400/20 flex items-center justify-center mx-auto">
                    <Check className="w-8 h-8 text-green-400" />
                  </div>
                  <div>
                    <h2 className="text-2xl font-bold text-text-primary mb-2">Subscription Cancelled</h2>
                    <p className="text-text-secondary">
                      {isTrialing 
                        ? 'Your trial has ended. You can still use Free features.'
                        : `Your subscription will end on ${renewsAt ? new Date(renewsAt).toLocaleDateString() : 'the end of your billing period'}. You can continue using Free features.`
                      }
                    </p>
                  </div>
                  <button
                    onClick={handleClose}
                    className="w-full py-3 bg-gold text-black rounded-xl font-semibold hover:bg-amber-400 transition-colors"
                  >
                    Got it
                  </button>
                </div>
              )}
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}
