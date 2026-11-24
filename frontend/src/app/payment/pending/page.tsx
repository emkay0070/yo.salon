'use client';

import { useRouter, useSearchParams } from 'next/navigation';
import { Clock, ArrowRight } from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { useEffect, useState } from 'react';

/**
 * PaymentPendingPage
 *
 * Shown when a customer's Flutterwave payment is still pending.
 * Polls the payment status and redirects to success/failure when the status changes.
 */
export default function PaymentPendingPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const bookingId = searchParams.get('booking_id') || '';

  const [isPolling, setIsPolling] = useState(true);
  const [pollCount, setPollCount] = useState(0);

  useEffect(() => {
    if (!bookingId) {
      setIsPolling(false);
      return;
    }

    let cancelled = false;

    async function pollStatus() {
      if (pollCount >= 20) {
        // Stop polling after ~1 minute (20 polls × 3 seconds)
        setIsPolling(false);
        return;
      }

      try {
        const data = await apiClient.get('/payments/customer/status', {
          params: { booking_id: bookingId },
        });

        if (cancelled) return;

        const status = data?.payment_request?.status || data?.payment_status;

        switch (status) {
          case 'paid':
          case 'completed':
            setIsPolling(false);
            router.push(`/payment/success?booking_id=${encodeURIComponent(bookingId)}`);
            break;
          case 'failed':
          case 'cancelled':
            setIsPolling(false);
            router.push(`/payment/failure?booking_id=${encodeURIComponent(bookingId)}`);
            break;
          default:
            // Still pending, continue polling
            setPollCount((c) => c + 1);
            break;
        }
      } catch (err) {
        // Continue polling on error
        setPollCount((c) => c + 1);
      }
    }

    const interval = setInterval(pollStatus, 3000);
    pollStatus(); // Run immediately

    return () => {
      cancelled = true;
      clearInterval(interval);
    };
  }, [bookingId, pollCount, router]);

  return (
    <div className="min-h-screen bg-[#070707] text-white flex items-center justify-center">
      <div className="text-center max-w-md px-6">
        <div className="w-16 h-16 bg-[#6C5CE7]/10 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg shadow-[#6C5CE7]/20">
          <Clock className="w-8 h-8 text-[#A29BFE] animate-spin" />
        </div>
        <h2 className="text-2xl font-bold text-white mb-4">Payment Pending</h2>
        <p className="text-text-secondary text-sm mb-8">
          Your payment is being processed. We are verifying the status and will update you shortly.
        </p>

        <div className="bg-white/[0.02] border border-white/5 rounded-2xl p-6 text-left mb-8">
          <div className="flex justify-between items-center py-2 border-b border-white/5 mb-4">
            <span className="text-text-secondary text-sm">Booking ID</span>
            <span className="text-text-primary font-mono text-sm">{bookingId || '—'}</span>
          </div>
          <div className="flex justify-between items-center py-2">
            <span className="text-text-secondary text-sm">Status</span>
            <span className="text-[#A29BFE] font-semibold text-sm">Pending Verification</span>
          </div>
        </div>

        <p className="text-text-secondary text-sm mb-6">
          Please do not close this page. We will redirect you automatically once the payment is confirmed.
        </p>

        <div className="space-y-3">
          <button
            onClick={() => router.push('/')}
            className="w-full py-3 bg-white/[0.05] hover:bg-white/[0.1] border border-white/10 transition-all text-text-primary font-medium rounded-xl text-sm flex items-center justify-center gap-2 cursor-pointer"
          >
            <ArrowRight className="w-4 h-4" /> Return Home
          </button>
        </div>
      </div>
    </div>
  );
}
