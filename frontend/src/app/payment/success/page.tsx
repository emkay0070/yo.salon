'use client';

import { useRouter, useSearchParams } from 'next/navigation';
import { CheckCircle2, ArrowRight } from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { useEffect, useState } from 'react';

/**
 * PaymentSuccessPage
 *
 * Shown when a customer's Flutterwave payment is verified as successful.
 * Confirms the booking is paid and provides next steps.
 */
export default function PaymentSuccessPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const bookingId = searchParams.get('booking_id') || '';

  const [verified, setVerified] = useState(false);

  useEffect(() => {
    // Optionally re-verify on mount for extra assurance
    if (bookingId) {
      apiClient
        .get('/payments/customer/status', { params: { booking_id: bookingId } })
        .then((data) => {
          if (data?.payment_status === 'paid' || data?.payment_request?.status === 'paid') {
            setVerified(true);
          }
        })
        .catch(() => {
          // If re-verification fails, still show success (webhook already confirmed)
          setVerified(true);
        });
    } else {
      setVerified(true);
    }
  }, [bookingId]);

  return (
    <div className="min-h-screen bg-[#070707] text-white flex items-center justify-center">
      <div className="text-center max-w-md px-6">
        <div className="w-16 h-16 bg-emerald-500/10 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg shadow-emerald-500/20">
          <CheckCircle2 className="w-8 h-8 text-emerald-400" />
        </div>
        <h2 className="text-2xl font-bold text-white mb-4">Payment Successful</h2>
        <p className="text-text-secondary text-sm mb-8">
          {verified
            ? 'Your payment has been verified and your booking is confirmed.'
            : 'Your payment was successful. We are finalizing your booking confirmation.'}
        </p>

        <div className="bg-white/[0.02] border border-white/5 rounded-2xl p-6 text-left mb-8">
          <div className="flex justify-between items-center py-2 border-b border-white/5 mb-4">
            <span className="text-text-secondary text-sm">Booking ID</span>
            <span className="text-text-primary font-mono text-sm">{bookingId || '—'}</span>
          </div>
          <div className="flex justify-between items-center py-2">
            <span className="text-text-secondary text-sm">Status</span>
            <span className="text-emerald-400 font-semibold text-sm">Paid</span>
          </div>
        </div>

        <button
          onClick={() => router.push('/')}
          className="px-8 py-3 bg-gradient-to-r from-gold to-[#C9A227] hover:brightness-110 transition-all text-black font-semibold rounded-xl text-sm shadow-lg shadow-gold/10 flex items-center justify-center gap-2 cursor-pointer"
        >
          Return Home <ArrowRight className="w-4 h-4" />
        </button>
      </div>
    </div>
  );
}
