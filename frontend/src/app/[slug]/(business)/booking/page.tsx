import { Suspense } from 'react';
import BookingContent from './BookingContent';

export default function ProviderBookingPage() {
  return (
    <Suspense fallback={<div className="p-8 text-center">Loading booking...</div>}>
      <BookingContent />
    </Suspense>
  );
}
