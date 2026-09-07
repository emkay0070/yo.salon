'use client';

import { motion } from 'framer-motion';
import { Search, Sparkles, Flame, Scissors, Clock, Star, ArrowRight, DollarSign, Heart, User, MapPin, Map as MapIcon } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { usePortalBrand } from '@/contexts/PortalBrandContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { SalonDiscoveryMap } from '@/components/discovery/SalonDiscoveryMap';
import ServiceDetailsDrawer from '@/components/discovery/ServiceDetailsDrawer';
import SpecialistDetailsDrawer from '@/components/discovery/SpecialistDetailsDrawer';
import SalonDetailsDrawer from '@/components/discovery/SalonDetailsDrawer';

function SpecialistCard({ specialist, onOpen }: { specialist: any; onOpen: (specialist: any) => void }) {
  return (
    <div
      className="bg-surface border border-border-light rounded-2xl p-6 cursor-pointer hover:border-gold/30 transition-all"
      style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
      onClick={() => onOpen(specialist)}
    >
      <div className="flex items-center gap-4 mb-4">
        <div className="w-16 h-16 rounded-full flex items-center justify-center text-white text-xl font-bold flex-shrink-0"
          style={{
            background: 'linear-gradient(to bottom right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))',
            borderRadius: 'var(--brand-border-radius, 16px)'
          }}
        >
          {specialist.name?.charAt(0)}
        </div>
        <div className="flex-1">
          <h3 className="font-semibold text-text-primary">{specialist.name}</h3>
          <p className="text-xs text-text-secondary">{specialist.specialties?.[0] || 'Specialist'}</p>
          {specialist.rating && (
            <div className="flex items-center gap-1">
              <Star className="w-3 h-3" style={{ color: 'var(--brand-primary, #FFD700)' }} fill="currentColor" />
              <span className="text-sm text-text-secondary">{specialist.rating}</span>
              <span className="text-xs text-text-secondary">({specialist.review_count || 0})</span>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

function SalonCard({ salon, onOpen }: { salon: any; onOpen: (salon: any) => void }) {
  return (
    <div
      className="bg-surface border border-border-light rounded-2xl p-6 cursor-pointer hover:border-gold/30 transition-all"
      style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
      onClick={() => onOpen(salon)}
    >
      <div className="flex items-center gap-4 mb-4">
        <div className="w-16 h-16 rounded-full flex items-center justify-center text-white text-xl font-bold flex-shrink-0"
          style={{
            background: 'linear-gradient(to bottom right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))',
            borderRadius: 'var(--brand-border-radius, 16px)'
          }}
        >
          {salon.name?.charAt(0)}
        </div>
        <div className="flex-1">
          <h3 className="font-semibold text-text-primary">{salon.name}</h3>
          <p className="text-xs text-text-secondary">{salon.city || 'Salon'}</p>
          <div className="flex items-center gap-1">
            <Clock className="w-3 h-3 text-text-secondary" />
            <span className="text-xs text-text-secondary">Open Now</span>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function DiscoverPage() {
  const { customer, salon } = usePortalAuth();
  const { brand } = usePortalBrand();
  const router = useRouter();
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<string | null>(null);
  const [favorites, setFavorites] = useState<Set<string>>(new Set());
  const [viewMode, setViewMode] = useState<'list' | 'map'>('list');

  // Drawer state
  const [serviceDrawerOpen, setServiceDrawerOpen] = useState(false);
  const [specialistDrawerOpen, setSpecialistDrawerOpen] = useState(false);
  const [salonDrawerOpen, setSalonDrawerOpen] = useState(false);
  const [selectedService, setSelectedService] = useState<any>(null);
  const [selectedSpecialist, setSelectedSpecialist] = useState<any>(null);
  const [selectedSalon, setSelectedSalon] = useState<any>(null);

  const { data: featuredContent, isLoading: featuredLoading, error: featuredError } = useQuery({
    queryKey: ['discover-featured'],
    queryFn: () => portalApiClient.get('/discover/featured'),
  });

  const { data: trendingContent, isLoading: trendingLoading, error: trendingError } = useQuery({
    queryKey: ['discover-trending'],
    queryFn: () => portalApiClient.get('/discover/trending'),
  });

  const { data: services } = useQuery({
    queryKey: ['portal-services'],
    queryFn: () => portalApiClient.get('/portal/services'),
    enabled: !!customer,
  });

  const { data: specialists, error: specialistsError } = useQuery({
    queryKey: ['portal-specialists'],
    queryFn: () => portalApiClient.get('/specialists'),
  });

  const { data: salons, error: salonsError } = useQuery({
    queryKey: ['portal-salons'],
    queryFn: () => portalApiClient.get('/salons'),
  });

  const { data: searchResults, isLoading: searchLoading } = useQuery({
    queryKey: ['universal-search', searchQuery],
    queryFn: () => portalApiClient.get(`/search?q=${encodeURIComponent(searchQuery)}`),
    enabled: !!customer && searchQuery.length >= 2,
  });

  // Normalize data - handle both direct arrays and paginated responses
  const servicesData = Array.isArray(services) ? services : services?.data || [];
  const specialistsData = Array.isArray(specialists) ? specialists : specialists?.data || [];
  const salonsData = Array.isArray(salons) ? salons : salons?.data || [];

  // Use new featured/trending endpoints
  const featuredSalons = featuredContent?.salons || [];
  const featuredServices = featuredContent?.services || [];
  const featuredSpecialists = featuredContent?.specialists || [];

  const trendingSalons = trendingContent?.salons || [];
  const trendingServices = trendingContent?.services || [];
  const trendingSpecialists = trendingContent?.specialists || [];

  // Filter services by category
  const filteredServices = selectedCategory
    ? servicesData.filter((s: any) => s.category?.toLowerCase() === selectedCategory.toLowerCase()) || []
    : servicesData || [];

  const topRatedServices = [...(filteredServices || [])]
    .sort((a, b) => (b.rating || 0) - (a.rating || 0))
    .slice(0, 6);

  const featuredService = featuredServices?.[0] || filteredServices?.[0];

  const toggleFavorite = (serviceId: string) => {
    setFavorites(prev => {
      const newFavorites = new Set(prev);
      if (newFavorites.has(serviceId)) {
        newFavorites.delete(serviceId);
      } else {
        newFavorites.add(serviceId);
      }
      return newFavorites;
    });
  };

  const handleOpenService = (service: any) => {
    setSelectedService(service);
    setServiceDrawerOpen(true);
  };

  const handleOpenSpecialist = (specialist: any) => {
    setSelectedSpecialist(specialist);
    setSpecialistDrawerOpen(true);
  };

  const handleOpenSalon = (salon: any) => {
    setSelectedSalon(salon);
    setSalonDrawerOpen(true);
  };

  if (featuredLoading || trendingLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  return (
    <div className="space-y-8">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex items-center justify-between"
      >
        <div>
          <h1 className="text-3xl font-bold text-text-primary mb-2">Discover</h1>
          <p className="text-text-secondary">Find salons, specialists, and services near you</p>
        </div>
        <button
          onClick={() => setViewMode(viewMode === 'list' ? 'map' : 'list')}
          className="flex items-center gap-2 px-4 py-2 bg-surface border border-border-light rounded-xl hover:border-gold/30 transition-all"
          style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
        >
          {viewMode === 'list' ? (
            <>
              <MapIcon className="w-4 h-4 text-text-primary" />
              <span className="text-sm text-text-primary">Map View</span>
            </>
          ) : (
            <>
              <Search className="w-4 h-4 text-text-primary" />
              <span className="text-sm text-text-primary">List View</span>
            </>
          )}
        </button>
      </motion.div>

      {/* Map View */}
      {viewMode === 'map' && (
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="rounded-2xl overflow-hidden"
          style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
        >
          <div className="h-[600px]">
            <SalonDiscoveryMap />
          </div>
        </motion.div>
      )}

      {/* List View */}
      {viewMode === 'list' && (
        <>

      {/* Featured Salon/Specialist Hero */}
      {featuredSalons.length > 0 && (
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="relative overflow-hidden rounded-2xl cursor-pointer group"
          style={{
            borderRadius: 'var(--brand-border-radius, 16px)',
            background: `linear-gradient(135deg, var(--brand-primary, #FFD700)20, var(--brand-secondary, #FF8C5A)20)`,
            boxShadow: 'var(--brand-shadow-lg, 0 8px 24px rgba(0,0,0,0.10))'
          }}
          onClick={() => handleOpenSalon(featuredSalons[0])}
        >
          <div className="p-8 sm:p-12">
            <div className="flex items-center gap-2 mb-4">
              <Sparkles className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
              <span className="text-sm font-medium" style={{ color: 'var(--brand-primary, #FFD700)' }}>Featured Salon</span>
            </div>
            <h2 className="text-3xl sm:text-4xl font-bold text-text-primary mb-3">{featuredSalons[0].name}</h2>
            <p className="text-text-secondary mb-6 max-w-2xl">{featuredSalons[0].address || 'Visit our premium salon for an exceptional experience'}</p>
            <div className="flex items-center gap-6 mb-6">
              <div className="flex items-center gap-2">
                <Star className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                <span className="text-text-secondary">{featuredSalons[0].rating || '4.8'}</span>
              </div>
              <div className="flex items-center gap-2">
                <Clock className="w-5 h-5 text-text-secondary" />
                <span className="text-text-secondary">Open Now</span>
              </div>
            </div>
            <button
              className="flex items-center gap-2 px-6 py-3 text-white font-medium rounded-full transition-all group-hover:gap-4"
              style={{
                backgroundColor: 'var(--brand-primary, #FFD700)',
                borderRadius: 'var(--brand-border-radius, 16px)'
              }}
            >
              Book Appointment
              <ArrowRight className="w-5 h-5" />
            </button>
          </div>
          <div className="absolute right-0 top-0 w-1/3 h-full opacity-10">
            <MapPin className="w-full h-full" />
          </div>
        </motion.div>
      )}

      {/* Universal Search Bar */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.2 }}
      >
        <div className="relative">
          <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-text-secondary" />
          <input
            type="text"
            placeholder="Search services, providers, specialists, styles..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full pl-12 pr-4 py-4 bg-surface border border-border-light rounded-2xl text-text-primary placeholder-text-secondary focus:outline-none focus:border-gold/50 transition-colors"
            style={{
              borderRadius: 'var(--brand-border-radius, 16px)',
              boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))'
            }}
          />
        </div>
      </motion.div>

      {/* Search Results */}
      {searchQuery.length >= 2 && (
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.25 }}
        >
          {searchLoading ? (
            <div className="text-text-secondary">Searching...</div>
          ) : searchResults && searchResults.total > 0 ? (
            <div className="space-y-6">
              {/* Providers */}
              {searchResults.providers && searchResults.providers.length > 0 && (
                <div>
                  <div className="flex items-center gap-2 mb-4">
                    <User className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                    <h2 className="text-xl font-bold text-text-primary">Providers</h2>
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    {searchResults.providers.map((provider: any) => (
                      <div
                        key={provider.id}
                        className="bg-surface border border-border-light rounded-2xl p-6 cursor-pointer hover:border-gold/30 transition-all"
                        style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
                        onClick={() => handleOpenSalon(provider)}
                      >
                        <div className="flex items-center gap-4 mb-4">
                          <div className="w-16 h-16 rounded-full flex items-center justify-center text-white text-xl font-bold flex-shrink-0"
                            style={{
                              background: 'linear-gradient(to bottom right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))',
                              borderRadius: 'var(--brand-border-radius, 16px)'
                            }}
                          >
                            {provider.display_name?.charAt(0)}
                          </div>
                          <div className="flex-1">
                            <h3 className="font-semibold text-text-primary">{provider.display_name}</h3>
                            <p className="text-xs text-text-secondary capitalize">{provider.type_label}</p>
                            <div className="flex items-center gap-1">
                              <Star className="w-3 h-3" style={{ color: 'var(--brand-primary, #FFD700)' }} fill="currentColor" />
                              <span className="text-sm text-text-secondary">{provider.rating || '0.0'}</span>
                              <span className="text-xs text-text-secondary">({provider.review_count || 0})</span>
                            </div>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Specialists */}
              {searchResults.specialists && searchResults.specialists.length > 0 && (
                <div>
                  <div className="flex items-center gap-2 mb-4">
                    <User className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                    <h2 className="text-xl font-bold text-text-primary">Specialists</h2>
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    {searchResults.specialists.map((specialist: any) => (
                      <div
                        key={specialist.id}
                        className="bg-surface border border-border-light rounded-2xl p-6 cursor-pointer hover:border-gold/30 transition-all"
                        style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
                        onClick={() => handleOpenSpecialist(specialist)}
                      >
                        <div className="flex items-center gap-4 mb-4">
                          <div className="w-16 h-16 rounded-full flex items-center justify-center text-white text-xl font-bold flex-shrink-0"
                            style={{
                              background: 'linear-gradient(to bottom right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))',
                              borderRadius: 'var(--brand-border-radius, 16px)'
                            }}
                          >
                            {specialist.name?.charAt(0)}
                          </div>
                          <div className="flex-1">
                            <h3 className="font-semibold text-text-primary">{specialist.name}</h3>
                            <p className="text-xs text-text-secondary">{specialist.role}</p>
                            {specialist.provider && (
                              <p className="text-xs text-text-secondary">{specialist.provider.display_name}</p>
                            )}
                            <div className="flex items-center gap-1">
                              <Star className="w-3 h-3" style={{ color: 'var(--brand-primary, #FFD700)' }} fill="currentColor" />
                              <span className="text-sm text-text-secondary">{specialist.rating || '0.0'}</span>
                              <span className="text-xs text-text-secondary">({specialist.review_count || 0})</span>
                            </div>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Services */}
              {searchResults.services && searchResults.services.length > 0 && (
                <div>
                  <div className="flex items-center gap-2 mb-4">
                    <Scissors className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                    <h2 className="text-xl font-bold text-text-primary">Services</h2>
                  </div>
                  <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    {searchResults.services.map((service: any) => (
                      <MagazineServiceCard key={service.id} service={service} featured={false} isFavorited={favorites.has(service.id)} onFavorite={() => toggleFavorite(service.id)} onOpen={() => handleOpenService(service)} />
                    ))}
                  </div>
                </div>
              )}
            </div>
          ) : (
            <div className="bg-surface border border-border-light rounded-2xl p-12 text-center"
              style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
            >
              <Search className="w-16 h-16 text-text-secondary mx-auto mb-4" />
              <h3 className="text-xl font-semibold text-text-primary mb-2">No results found</h3>
              <p className="text-text-secondary">Try a different search term</p>
            </div>
          )}
        </motion.div>
      )}

      {/* Default Feeds (shown when no search) */}
      {searchQuery.length < 2 && (
        <>
          {/* Featured Salons */}
          {featuredSalons.length > 0 && (
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.3 }}
            >
              <div className="flex items-center gap-2 mb-4">
                <Sparkles className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                <h2 className="text-xl font-bold text-text-primary">Featured Salons</h2>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {featuredSalons.slice(0, 3).map((salon: any) => (
                  <SalonCard key={salon.id} salon={salon} onOpen={handleOpenSalon} />
                ))}
              </div>
            </motion.div>
          )}

          {/* Featured Specialists */}
          {featuredSpecialists.length > 0 && (
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.4 }}
            >
              <div className="flex items-center gap-2 mb-4">
                <Sparkles className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                <h2 className="text-xl font-bold text-text-primary">Featured Specialists</h2>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {featuredSpecialists.slice(0, 3).map((specialist: any) => (
                  <SpecialistCard key={specialist.id} specialist={specialist} onOpen={handleOpenSpecialist} />
                ))}
              </div>
            </motion.div>
          )}

          {/* Trending Salons */}
          {trendingSalons.length > 0 && (
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.5 }}
            >
              <div className="flex items-center gap-2 mb-4">
                <Flame className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                <h2 className="text-xl font-bold text-text-primary">Trending Salons</h2>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {trendingSalons.slice(0, 3).map((salon: any) => (
                  <SalonCard key={salon.id} salon={salon} onOpen={handleOpenSalon} />
                ))}
              </div>
            </motion.div>
          )}

          {/* Trending Specialists */}
          {trendingSpecialists.length > 0 && (
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.55 }}
            >
              <div className="flex items-center gap-2 mb-4">
                <Flame className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                <h2 className="text-xl font-bold text-text-primary">Trending Specialists</h2>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {trendingSpecialists.slice(0, 3).map((specialist: any) => (
                  <SpecialistCard key={specialist.id} specialist={specialist} onOpen={handleOpenSpecialist} />
                ))}
              </div>
            </motion.div>
          )}

          {/* Categories Feed */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.6 }}
          >
            <div className="flex items-center gap-2 mb-4">
              <Sparkles className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
              <h2 className="text-xl font-bold text-text-primary">Service Categories</h2>
            </div>
            <div className="flex gap-3 overflow-x-auto pb-2">
              <button
                onClick={() => setSelectedCategory(null)}
                className={`flex-shrink-0 px-6 py-3 border rounded-2xl transition-all ${
                  selectedCategory === null
                    ? 'text-white'
                    : 'bg-surface text-text-secondary hover:border-gold/30'
                }`}
                style={{
                  borderRadius: 'var(--brand-border-radius, 16px)',
                  backgroundColor: selectedCategory === null ? 'var(--brand-primary, #FFD700)' : undefined
                }}
              >
                All
              </button>
              {['Hair', 'Nails', 'Spa', 'Massage', 'Facial', 'Makeup', 'Barber', 'Wellness'].map((category) => (
                <button
                  key={category}
                  onClick={() => setSelectedCategory(category)}
                  className={`flex-shrink-0 px-6 py-3 border rounded-2xl transition-all ${
                    selectedCategory === category
                      ? 'text-white'
                      : 'bg-surface text-text-secondary hover:border-gold/30'
                  }`}
                  style={{
                    borderRadius: 'var(--brand-border-radius, 16px)',
                    backgroundColor: selectedCategory === category ? 'var(--brand-primary, #FFD700)' : undefined
                  }}
                >
                  {category}
                </button>
              ))}
            </div>
          </motion.div>

          {/* Trending Services */}
          {trendingServices.length > 0 && (
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.7 }}
            >
              <div className="flex items-center gap-2 mb-4">
                <Flame className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                <h2 className="text-xl font-bold text-text-primary">Trending Services</h2>
              </div>
              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                {trendingServices.map((service: any) => (
                  <MagazineServiceCard key={service.id} service={service} featured={false} isFavorited={favorites.has(service.id)} onFavorite={() => toggleFavorite(service.id)} onOpen={() => handleOpenService(service)} />
                ))}
              </div>
            </motion.div>
          )}
        </>
      )}
      </>
    )}

    {/* Drawers */}
    {selectedService && (
      <ServiceDetailsDrawer
        isOpen={serviceDrawerOpen}
        onClose={() => setServiceDrawerOpen(false)}
        service={selectedService}
      />
    )}

    {selectedSpecialist && (
      <SpecialistDetailsDrawer
        isOpen={specialistDrawerOpen}
        onClose={() => setSpecialistDrawerOpen(false)}
        specialist={selectedSpecialist}
      />
    )}

    {selectedSalon && (
      <SalonDetailsDrawer
        isOpen={salonDrawerOpen}
        onClose={() => setSalonDrawerOpen(false)}
        salon={selectedSalon}
      />
    )}
    </div>
  );
}

function MagazineServiceCard({ service, featured, isFavorited, onFavorite, onOpen }: { service: any, featured: boolean, isFavorited: boolean, onFavorite: () => void, onOpen: () => void }) {
  return (
    <div
      className="bg-surface border border-border-light overflow-hidden cursor-pointer group hover:border-gold/30 transition-all"
      style={{
        borderRadius: 'var(--brand-border-radius, 16px)',
        boxShadow: featured ? 'var(--brand-shadow-md, 0 4px 12px rgba(0,0,0,0.08))' : 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))'
      }}
      onClick={onOpen}
    >
      <div className="relative h-28 bg-gradient-to-br from-gold/10 to-dark-gold/10 flex items-center justify-center overflow-hidden">
        <Scissors className="w-12 h-12 text-gold group-hover:scale-110 transition-transform duration-300" />
        <div className="absolute top-2 right-2">
          <Heart
            className={`w-4 h-4 transition-colors ${isFavorited ? 'fill-red-500 text-red-500' : 'text-text-secondary hover:text-red-500'}`}
            onClick={(e) => {
              e.stopPropagation();
              onFavorite();
            }}
          />
        </div>
      </div>
      <div className="p-4">
        <div className="flex items-center gap-2 mb-2">
          <span className="text-xs font-medium px-2 py-0.5 rounded-full"
            style={{
              backgroundColor: 'var(--brand-primary, #FFD700)20',
              color: 'var(--brand-primary, #FFD700)'
            }}
          >
            {service.category || 'Service'}
          </span>
          {service.rating && (
            <div className="flex items-center gap-1">
              <Star className="w-3 h-3" style={{ color: 'var(--brand-primary, #FFD700)' }} fill="currentColor" />
              <span className="text-xs text-text-secondary">{service.rating}</span>
            </div>
          )}
        </div>
        <h3 className="font-semibold text-sm text-text-primary mb-1 line-clamp-1">{service.name}</h3>
        <p className="text-xs text-text-secondary mb-3 line-clamp-1">{service.description || 'Professional service'}</p>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-1 text-xs text-text-secondary">
            <Clock className="w-3 h-3" />
            <span>{service.duration} min</span>
          </div>
          <div className="flex items-center gap-1 text-sm font-semibold" style={{ color: 'var(--brand-primary, #FFD700)' }}>
            <DollarSign className="w-3 h-3" />
            <span>{service.price}</span>
          </div>
        </div>
      </div>
    </div>
  );
}
