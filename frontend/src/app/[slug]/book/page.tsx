'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { Loader2 } from 'lucide-react';
import { apiClient } from '@/lib/api-client';

// Import the booking page content component as a named export
import { BookingPageContent } from '@/app/book/page';

export default function SalonBookingPage() {
  const params = useParams();
  const slug = params.slug as string;

  const [salon, setSalon] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    async function loadSalon() {
      if (!slug) return;

      try {
        setLoading(true);
        const salonData = await apiClient.getSalonBySlug(slug);
        setSalon(salonData);
      } catch (err: any) {
        console.error('Failed to load salon:', err);
        setError('Salon not found');
      } finally {
        setLoading(false);
      }
    }

    loadSalon();
  }, [slug]);

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#070707]">
        <Loader2 className="w-10 h-10 text-[#FFD700] animate-spin" />
      </div>
    );
  }

  if (error || !salon) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#070707] text-white">
        <p className="text-xl">{error || 'Salon not found'}</p>
      </div>
    );
  }

  // Render the booking component with preloaded salon data
  return <BookingPageContent preloadedSalon={salon} />;
}
