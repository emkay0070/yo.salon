'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { motion } from 'framer-motion';
import { Search, Calendar, Clock, MapPin, Star, ArrowRight, Loader2 } from 'lucide-react';
import { apiClient } from '@/lib/api-client';

interface Salon {
  id: string;
  name: string;
  slug: string;
  location?: string;
  description?: string;
  rating?: number;
}

export default function BookPage() {
  const router = useRouter();
  const [searchQuery, setSearchQuery] = useState('');
  const [salons, setSalons] = useState<Salon[]>([]);
  const [loading, setLoading] = useState(false);
  const [selectedSalon, setSelectedSalon] = useState<Salon | null>(null);

  useEffect(() => {
    if (searchQuery.length >= 2) {
      searchSalons();
    }
  }, [searchQuery]);

  const searchSalons = async () => {
    setLoading(true);
    try {
      const data = await apiClient.getSalons();
      setSalons(data.filter((salon: Salon) => 
        salon.name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
        salon.slug?.toLowerCase().includes(searchQuery.toLowerCase())
      ));
    } catch (error) {
      console.error('Failed to search salons:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleSalonSelect = (salon: Salon) => {
    router.push(`/salons/${salon.slug}/book`);
  };

  return (
    <div className="min-h-screen bg-[#070707] text-white">
      {/* Header */}
      <div className="border-b border-white/10 bg-black/40 backdrop-blur-xl">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex items-center justify-between">
            <h1 className="text-2xl font-bold">Book Appointment</h1>
            <button
              onClick={() => router.push('/welcome')}
              className="text-white/60 hover:text-white transition-colors"
            >
              Back to Home
            </button>
          </div>
        </div>
      </div>

      {/* Search Section */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="max-w-2xl mx-auto mb-12">
          <div className="relative">
            <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-white/40" />
            <input
              type="text"
              placeholder="Search for salons by name..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full pl-12 pr-4 py-4 bg-white/5 border border-white/10 rounded-2xl text-white placeholder-white/40 focus:outline-none focus:border-white/20 transition-colors"
            />
          </div>
        </div>

        {/* Salons Grid */}
        {loading ? (
          <div className="flex justify-center py-12">
            <Loader2 className="w-8 h-8 animate-spin text-white/40" />
          </div>
        ) : salons.length > 0 ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {salons.map((salon) => (
              <motion.div
                key={salon.id}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                className="bg-white/5 border border-white/10 rounded-2xl p-6 hover:border-white/20 transition-colors cursor-pointer"
                onClick={() => handleSalonSelect(salon)}
              >
                <div className="flex items-start justify-between mb-4">
                  <div>
                    <h3 className="text-xl font-bold mb-1">{salon.name}</h3>
                    <div className="flex items-center gap-1 text-white/40 text-sm">
                      <MapPin className="w-4 h-4" />
                      {salon.location || 'Location not specified'}
                    </div>
                  </div>
                  {salon.rating && (
                    <div className="flex items-center gap-1 bg-yellow-500/10 px-2 py-1 rounded-lg">
                      <Star className="w-4 h-4 text-yellow-400 fill-yellow-400" />
                      <span className="text-sm font-medium">{salon.rating}</span>
                    </div>
                  )}
                </div>

                {salon.description && (
                  <p className="text-white/60 text-sm mb-4 line-clamp-2">
                    {salon.description}
                  </p>
                )}

                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2 text-white/40 text-sm">
                    <Clock className="w-4 h-4" />
                    <span>Open Now</span>
                  </div>
                  <div className="flex items-center gap-2 text-gold text-sm font-medium">
                    Book Now <ArrowRight className="w-4 h-4" />
                  </div>
                </div>
              </motion.div>
            ))}
          </div>
        ) : searchQuery.length >= 2 ? (
          <div className="text-center py-12 text-white/40">
            No salons found matching "{searchQuery}"
          </div>
        ) : (
          <div className="text-center py-12 text-white/40">
            Start typing to search for salons...
          </div>
        )}
      </div>
    </div>
  );
}
