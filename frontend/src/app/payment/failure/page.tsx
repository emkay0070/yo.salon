'use client';

import { useRouter, useSearchParams } from 'next/navigation';
import { AlertCircle, ArrowRight, Clock } from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { useEffect, useState } from 'react';

/**
 * PaymentFailurePage
 *
 * Shown when a customer's Flutterwave payment is verified as failed or cancelled.
 * Provides options to retry or contact the salon.
 */
export default function PaymentFailurePage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const bookingId = searchParams.get('booking_id') || '';

  const [retryable, setRetryable] = useState(true);

  useEffect(() => {
    if (bookingId) {
      apiClient
        .get('/payments/customer/status', { params: { booking_id: bookingId } })
        .then((data) => {
          const status = data?.payment_request?.status || data?.payment_status;
          // Expired payments can be retried by creating a new booking
          setRetryable(status === 'expired' || status === 'failed' || status === 'cancelled');
        })
        .catch(() => setRetryable(true));
    }
  }, [bookingId]);

  const handleRetry = () => {
    if (bookingId) {
      router.push(`/book?booking_id=${encodeURIComponent(bookingId)}`);
    } else {
      router.push('/');
    }
  };

  return (
    <div className="min-h-screen bg-[#070707] text-white flex items-center justify-center">
      <div className="text-center max-w-md px-6">
        <div className="w-16 h-16 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg shadow-red-500/20">
          <AlertCircle className="w-8 h-8 text-red-400" />
        </div>
        <h2 className="text-2xl font-bold text-white mb-4">Payment Not Completed</h2>
        <p className="text-text-secondary text-sm mb-8">
          We were unable to verify a successful payment for your booking. This can happen if the payment was cancelled, failed, or is still pending.
        </p>

        <div className="bg-white/[0.02] border border-white/5 rounded-2xl p-6 text-left mb-8">
          <div className="flex justify-between items-center py-2 border-b border-white/5 mb-4">
            <span className="text-text-secondary text-sm">Booking ID</span>
            <span className="text-text-primary font-mono text-sm">{bookingId || '—'}</span>
          </div>
          <div className="flex justify-between items-center py-2">
            <span className="text-text-secondary text-sm">What you can do</span>
            <span className="text-text-primary font-medium text-sm">Retry payment or contact the salon</span>
          </div>
        </div>

        <div className="space-y-3">
          <button
            onClick={handleRetry}
            disabled={!retryable}
            className="w-full py-3.5 bg-gradient-to-r from-gold to-[#C9A227] hover:brightness-110 transition-all text-black font-semibold rounded-xl text-sm shadow-lg shadow-gold/10 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {retryable ? (
              <>
                <ArrowRight className="w-4 h-4" /> Retry Payment
              </>
            ) : (
              <>
                <Clock className="w-4 h-4" /> Payment Expired
              </>
            )}
          </button>
          <button
            onClick={() => router.push('/')}
            className="w-full py-3 bg-white/[0.05] hover:bg-white/[0.1] border border-white/10 transition-all text-text-primary font-medium rounded-xl text-sm flex items-center justify-center gap-2 cursor-pointer"
          >
            Return Home
          </button>
        </div>

        <p className="text-text-secondary text-xs mt-6">
          If you were charged but your booking was not confirmed, please contact the salon with your booking reference.
        </p>
      </div>
    </div>
  );
}
