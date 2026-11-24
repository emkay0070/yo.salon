'use client';

import { useEffect, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { Loader2, AlertCircle, CheckCircle, Clock, MessageCircle } from 'lucide-react';
import { apiClient } from '@/lib/api-client';

/**
 * FlutterwaveCallbackPage
 *
 * Flutterwave redirects the customer here after payment completion.
 * Verifies the payment server-side via the public
 * /payments/customer/status endpoint, then redirects to the appropriate
 * success/failure/pending page.
 *
 * Query params:
 *   booking_id - Booking UUID
 *   (Flutterwave may also append status, transaction_id, tx_ref, etc.)
 */
export default function FlutterwaveCallbackPage() {
  const router = useRouter();
  const searchParams = useSearchParams();

  const bookingId = searchParams.get('booking_id') || '';
  const flwStatus = searchParams.get('status') || ''; // Flutterwave's client-side status (untrusted)

  const [isVerifying, setIsVerifying] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function verifyPayment() {
      if (!bookingId) {
        setError('Missing booking reference.');
        setIsVerifying(false);
        return;
      }

      try {
        const response = await apiClient.get('/payments/customer/status', {
          params: { booking_id: bookingId },
        });

        if (cancelled) return;

        const data = response.data;
        const paymentStatus = data?.payment_status || data?.payment_request?.status || 'unknown';

        // Map server-side status to outcome page
        switch (paymentStatus) {
          case 'paid':
          case 'completed':
            router.push(`/payment/success?booking_id=${encodeURIComponent(bookingId)}`);
            break;
          case 'failed':
          case 'cancelled':
            router.push(`/payment/failure?booking_id=${encodeURIComponent(bookingId)}`);
            break;
          case 'pending':
          case 'sent':
            router.push(`/payment/pending?booking_id=${encodeURIComponent(bookingId)}`);
            break;
          default:
            router.push(`/payment/pending?booking_id=${encodeURIComponent(bookingId)}`);
            break;
        }
      } catch (err: any) {
        if (cancelled) return;
        console.error('Payment verification failed:', err);
        setError(err?.response?.data?.message || 'Failed to verify payment status.');
        setIsVerifying(false);
      }
    }

    verifyPayment();

    return () => {
      cancelled = true;
    };
  }, [bookingId, flwStatus, router]);

  if (error) {
    return (
      <div className="min-h-screen bg-[#070707] text-white flex items-center justify-center">
        <div className="text-center max-w-md px-6">
          <div className="w-16 h-16 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-6">
            <AlertCircle className="w-8 h-8 text-red-400" />
          </div>
          <h2 className="text-2xl font-bold text-white mb-4">Verification Failed</h2>
          <p className="text-text-secondary text-sm mb-6">{error}</p>
          <button
            onClick={() => router.push('/')}
            className="px-8 py-3 bg-gradient-to-r from-gold to-[#C9A227] hover:brightness-110 transition-all text-black font-semibold rounded-xl text-sm shadow-lg shadow-gold/10 flex items-center justify-center gap-2 cursor-pointer"
          >
            Return Home
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-[#070707] text-white flex items-center justify-center">
      <div className="text-center max-w-md px-6">
        <div className="w-16 h-16 bg-[#6C5CE7]/10 rounded-full flex items-center justify-center mx-auto mb-6">
          <Loader2 className="w-8 h-8 text-[#A29BFE] animate-spin" />
        </div>
        <h2 className="text-2xl font-bold text-white mb-4">Verifying Payment...</h2>
        <p className="text-text-secondary text-sm mb-8">
          We are confirming your payment with Flutterwave. Please wait.
        </p>
        <div className="bg-white/[0.02] border border-white/5 rounded-2xl p-6 text-left">
          <div className="flex items-center gap-3 mb-4">
            <div className="w-8 h-8 rounded-xl bg-[#6C5CE7]/20 flex items-center justify-center">
              <Clock className="w-4 h-4 text-[#A29BFE]" />
            </div>
            <div>
              <p className="text-white text-sm font-medium">Server-side verification</p>
              <p className="text-text-secondary text-xs">Your payment status is being verified on our servers before redirecting you.</p>
            </div>
          </div>
          {flwStatus && (
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-xl bg-[#6C5CE7]/20 flex items-center justify-center">
                <MessageCircle className="w-4 h-4 text-[#A29BFE]" />
              </div>
              <div>
                <p className="text-white text-sm font-medium">Provider response</p>
                <p className="text-text-secondary text-xs">Flutterwave reported: {flwStatus}</p>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
