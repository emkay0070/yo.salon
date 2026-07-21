'use client';

import { motion } from 'framer-motion';
import { Sparkles, Scissors, Clock, DollarSign, Search } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery } from '@tanstack/react-query';
import ClientLayout from '@/components/ClientLayout';
import { useState } from 'react';

export default function DiscoverPage() {
  const { customer, salon } = usePortalAuth();
  const [searchQuery, setSearchQuery] = useState('');

  const { data: services, isLoading } = useQuery({
    queryKey: ['portal-services'],
    queryFn: () => portalApiClient.get('/v1/portal/services'),
    enabled: !!customer,
  });

  const filteredServices = services?.filter((service: any) =>
    service.name.toLowerCase().includes(searchQuery.toLowerCase())
  ) || [];

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  return (
    <ClientLayout>
      <div className="space-y-6">
        {/* Header */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <h1 className="text-3xl font-bold text-text-primary mb-2">Discover Services</h1>
          <p className="text-text-secondary">Explore our offerings</p>
        </motion.div>

        {/* Search Bar */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="relative"
        >
          <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-text-secondary" />
          <input
            type="text"
            placeholder="Search services..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full pl-12 pr-4 py-3 bg-surface border border-border-light rounded-xl focus:outline-none focus:border-gold/50 text-text-primary placeholder:text-text-secondary"
          />
        </motion.div>

        {/* Services Grid */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
          className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
        >
          {filteredServices.length > 0 ? (
            filteredServices.map((service: any) => (
              <ServiceCard key={service.id} service={service} />
            ))
          ) : (
            <div className="col-span-full bg-surface border border-border-light rounded-2xl p-12 text-center">
              <Sparkles className="w-16 h-16 text-text-secondary mx-auto mb-4" />
              <h3 className="text-xl font-semibold text-text-primary mb-2">No services found</h3>
              <p className="text-text-secondary">Try a different search term</p>
            </div>
          )}
        </motion.div>
      </div>
    </ClientLayout>
  );
}

function ServiceCard({ service }: { service: any }) {
  return (
    <div className="bg-surface border border-border-light rounded-2xl p-6 hover:border-gold/30 transition-all cursor-pointer group">
      <div className="w-full h-32 bg-gradient-to-br from-gold/10 to-dark-gold/10 rounded-xl mb-4 flex items-center justify-center group-hover:scale-105 transition-transform">
        <Scissors className="w-12 h-12 text-gold" />
      </div>
      <h3 className="font-semibold text-text-primary mb-2">{service.name}</h3>
      <p className="text-sm text-text-secondary mb-4 line-clamp-2">{service.description || 'Professional service'}</p>
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 text-sm text-text-secondary">
          <Clock className="w-4 h-4" />
          <span>{service.duration} min</span>
        </div>
        <div className="flex items-center gap-1 text-lg font-semibold text-text-primary">
          <DollarSign className="w-4 h-4" />
          <span>{service.price}</span>
        </div>
      </div>
    </div>
  );
}
