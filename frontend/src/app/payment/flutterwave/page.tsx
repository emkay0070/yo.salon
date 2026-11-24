'use client';

import { useEffect, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { Loader2, AlertCircle, CheckCircle, Clock, MessageCircle } from 'lucide-react';
import { apiClient } from '@/lib/api-client';

/**
 * FlutterwaveCheckoutPage
 *
 * Customer-facing Flutterwave checkout page.
 *
 * Receives booking details via query parameters, initializes Flutterwave
 * payment via the public /payments/customer/initialize endpoint, then
 * redirects the customer to the Flutterwave payment link.
 *
 * Query params:
 *   booking_id      - Booking UUID
 *   payment_method_id - Payment method UUID
 *   email           - Customer email
 *   customer_name   - Customer name
 *   customer_phone  - Customer phone (optional)
 *   redirect_url    - Flutterwave callback URL (set by the booking page)
 */
export default function FlutterwaveCheckoutPage() {
  const router = useRouter();
  const searchParams = useSearchParams();

  const bookingId = searchParams.get('booking_id') || '';
  const paymentMethodId = searchParams.get('payment_method_id') || '';
  const email = searchParams.get('email') || '';
  const customerName = searchParams.get('customer_name') || '';
  const customerPhone = searchParams.get('customer_phone') || '';
  const redirectUrl = searchParams.get('redirect_url') || '';

  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [redirecting, setRedirecting] = useState(false);

  useEffect(() => {
    let cancelled = false;

    async function initializeFlutterwavePayment() {
      if (!bookingId || !paymentMethodId || !email || !customerName) {
        setError('Missing required payment details.');
        setIsLoading(false);
        return;
      }

      try {
        const response = await apiClient.post('/payments/customer/initialize', {
          booking_id: bookingId,
          payment_method_id: paymentMethodId,
          email,
          customer_name: customerName,
          customer_phone: customerPhone || undefined,
          redirect_url: redirectUrl || undefined,
        });

        if (cancelled) return;

        const paymentLink = response.data?.payment_link;

        if (!paymentLink) {
          setError('No payment link returned from payment provider.');
          setIsLoading(false);
          return;
        }

        // Redirect customer to Flutterwave checkout
        setRedirecting(true);
        window.location.href = paymentLink;
      } catch (err: any) {
        if (cancelled) return;
        console.error('Flutterwave initialization failed:', err);
        setError(err?.response?.data?.message || 'Failed to initialize Flutterwave payment. Please try again.');
        setIsLoading(false);
      }
    }

    initializeFlutterwavePayment();

    return () => {
      cancelled = true;
    };
  }, [bookingId, paymentMethodId, email, customerName, customerPhone, redirectUrl]);

  if (error) {
    return (
      <div className="min-h-screen bg-[#070707] text-white flex items-center justify-center">
        <div className="text-center max-w-md px-6">
          <div className="w-16 h-16 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-6">
            <AlertCircle className="w-8 h-8 text-red-400" />
          </div>
          <h2 className="text-2xl font-bold text-white mb-4">Payment Initialization Failed</h2>
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
        <h2 className="text-2xl font-bold text-white mb-4">
          {redirecting ? 'Redirecting to Flutterwave...' : 'Initializing Payment...'}
        </h2>
        <p className="text-text-secondary text-sm mb-8">
          You will be redirected to Flutterwave to complete your payment securely.
        </p>
        <div className="bg-white/[0.02] border border-white/5 rounded-2xl p-6 text-left">
          <div className="flex items-center gap-3 mb-4">
            <div className="w-8 h-8 rounded-xl bg-[#6C5CE7]/20 flex items-center justify-center">
              <MessageCircle className="w-4 h-4 text-[#A29BFE]" />
            </div>
            <div>
              <p className="text-white text-sm font-medium">What happens next?</p>
              <p className="text-text-secondary text-xs">You'll be redirected to Flutterwave's secure checkout to complete your payment.</p>
            </div>
          </div>
          <div className="flex items-center gap-3 mb-4">
            <div className="w-8 h-8 rounded-xl bg-[#6C5CE7]/20 flex items-center justify-center">
              <CheckCircle className="w-4 h-4 text-[#A29BFE]" />
            </div>
            <div>
              <p className="text-white text-sm font-medium">Return here after payment</p>
              <p className="text-text-secondary text-xs">After completing payment, you'll return to this page to confirm your booking.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
