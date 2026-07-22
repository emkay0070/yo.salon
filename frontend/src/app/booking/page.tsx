import { Suspense } from 'react';
import BookingContent from './BookingContent';

export default function BookingPage() {
  return (
    <Suspense fallback={<div className="p-8 text-center">Loading booking...</div>}>
      <BookingContent />
    </Suspense>
  );
}
