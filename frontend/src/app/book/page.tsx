'use client';

import { useState, useEffect, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Crown, Calendar, Clock, User, ArrowRight,
  CheckCircle2, Loader2, Star, Check, Info, Search, Phone, MapPin, Map as MapIcon, X, Ban, Lock, LockOpen
} from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { CursorGlow } from '@/components/ui/cursor-glow';
import { ProviderDiscoveryMap } from '@/components/discovery/ProviderDiscoveryMap';
import FindMyDetails from '@/components/booking/FindMyDetails';

interface Service {
  id: string;
  name: string;
  category: string;
  price: number;
  duration: number | null; // Can be null from backend
  min_booking_notice?: number; // Hours notice required for booking
}

interface Specialist {
  id: string;
  name: string;
  role?: string; // Optional since it might not be provided
}

interface Provider {
  id: string;
  name?: string; // Fallback for display_name
  display_name?: string; // Backend returns display_name, not name
  slug: string;
  rating?: number | null; // Can be null from backend
  city?: string; // Optional location field
  type?: string; // Provider type (e.g., 'independent_specialist', 'salon')
  active?: boolean; // Whether the provider is accepting bookings
  status?: string; // 'active' | 'pending' | 'inactive' | 'suspended'
}

interface TimeSlot {
  time: string;
  end: string;
  duration: number;
  available: boolean;
  availableSpecialists?: any[];
}

interface BookingPageContentProps {
  preloadedProvider?: Provider | null;
}

function BookingPageContent({ preloadedProvider }: BookingPageContentProps = {}) {
  const router = useRouter();
  const searchParams = useSearchParams();
  
  // Context from URL params
  const contextProviderSlug = searchParams.get('provider_slug');
  const contextServiceId = searchParams.get('service_id');
  const contextSpecialistId = searchParams.get('specialist_id');
  const contextProviderId = searchParams.get('provider_id');

  // Check if we're on a /[slug]/book route by examining the current path
  const isSlugRoute = typeof window !== 'undefined' && window.location.pathname.match(/^\/[^/]+\/book$/);
  const slugFromPath = isSlugRoute ? window.location.pathname.split('/')[1] : null;

  const [step, setStep] = useState<'provider' | 'service' | 'staff' | 'time' | 'details' | 'payment' | 'confirm' | 'success'>('provider');
  const [selectedProvider, setSelectedProvider] = useState<Provider | null>(null);
  const [selectedServices, setSelectedServices] = useState<Service[]>([]);
  const [selectedSpecialist, setSelectedSpecialist] = useState<Specialist | null>(null);
  const [selectedTime, setSelectedTime] = useState<string | null>(null);
  const [selectedDate, setSelectedDate] = useState<string>(() => {
    const d = new Date();
    return [
      d.getFullYear(),
      String(d.getMonth() + 1).padStart(2, '0'),
      String(d.getDate()).padStart(2, '0'),
    ].join('-');
  });
  const [customerDetails, setCustomerDetails] = useState({
    name: '',
    phone: '',
    email: '',
  });
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const [paymentInstruction, setPaymentInstruction] = useState<any>(null);
  const [selectedPaymentMethod, setSelectedPaymentMethod] = useState<string | null>(null);
  const [services, setServices] = useState<Service[]>([]);
  const [specialists, setSpecialists] = useState<Specialist[]>([]);
  const [providers, setProviders] = useState<Provider[]>([]);
  const [timeSlots, setTimeSlots] = useState<TimeSlot[]>([]);
  const [availabilityStatus, setAvailabilityStatus] = useState<{
    status: 'available' | 'closed' | 'configuration_required' | 'unavailable' | 'error';
    message?: string | null;
    reason?: string | null;
  } | null>(null);
  const [mounted, setMounted] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [viewMode, setViewMode] = useState<'list' | 'map'>('list');
  const [showInfoPopover, setShowInfoPopover] = useState(false);
  const [bookingStatus, setBookingStatus] = useState<string | null>(null);
  const [showFindDetails, setShowFindDetails] = useState(false);
  const [foundCustomer, setFoundCustomer] = useState<{ id: string; name: string; phone: string; email: string } | null>(null);

  useEffect(() => {
    setMounted(true);

    // Determine initial step based on context
    if (contextProviderId || contextProviderSlug) {
      setStep('service');
      if (contextServiceId) {
        // Skip staff step for independent_specialist providers
        const providerType = selectedProvider?.type;
        if (providerType === 'independent_specialist') {
          setStep('time');
        } else {
          setStep('staff');
          if (contextSpecialistId) {
            setStep('time');
          }
        }
      }
    } else if (contextProviderId) {
      setStep('provider');
    } else if (contextServiceId) {
      setStep('provider');
    } else if (contextSpecialistId) {
      setStep('provider');
    }
  }, [contextServiceId, contextSpecialistId, contextProviderId, contextProviderSlug, selectedProvider?.type]);

  // Load data based on context
  useEffect(() => {
    async function loadData() {
      // If on /[slug]/book route, extract slug from path
      if (slugFromPath) {
        try {
          const provider = await apiClient.getProviderBySlug(slugFromPath);
          setSelectedProvider(provider);
          loadProviderData(provider.id);
          return;
        } catch (err) {
          console.error('Failed to load salon from slug:', err);
        }
      }

      // Use preloaded provider if available (from props)
      if (preloadedProvider) {
        setSelectedProvider(preloadedProvider);
        loadProviderData(preloadedProvider.id, preloadedProvider.slug);
        return;
      }

      if (contextProviderSlug) {
        try {
          const provider = await apiClient.getProviderBySlug(contextProviderSlug);
          setSelectedProvider(provider);
          loadProviderData(provider.id, provider.slug);
        } catch (err) {
          console.error('Failed to load salon:', err);
        }
      } else if (contextProviderId) {
        try {
          const provider = await apiClient.get(`/v1/providers/${contextProviderId}`);
          // Load provider's locations
          if (provider.salon) {
            setSelectedProvider(provider.salon);
            loadProviderData(contextProviderId, provider.salon?.slug);
          } else if (provider.locations && provider.locations.length > 0) {
            setProviders(provider.locations);
          } else {
            // Provider has no locations, load all providers
            loadAllProviders();
          }
        } catch (err) {
          console.error('Failed to load provider:', err);
        }
      } else if (contextServiceId) {
        try {
          const service = await apiClient.get(`/v1/services/${contextServiceId}`);
          setSelectedServices([service]);
          loadServiceData(service.id);
        } catch (err) {
          console.error('Failed to load service:', err);
        }
      } else if (contextSpecialistId) {
        try {
          const specialist = await apiClient.get(`/v1/specialists/${contextSpecialistId}`);
          setSelectedSpecialist(specialist);
          loadSpecialistData(specialist.id);
        } catch (err) {
          console.error('Failed to load specialist:', err);
        }
      } else {
        // No context, load all providers
        loadAllProviders();
      }
    }
    
    loadData();
  }, [contextServiceId, contextSpecialistId, contextProviderSlug, contextProviderId, preloadedProvider, slugFromPath]);

  const loadProviderData = async (providerId: string, providerSlug?: string) => {
    if (!providerId) {
      console.warn('loadProviderData called with empty providerId — skipping');
      return;
    }
    try {
      // Use slug-based public endpoints to avoid authentication requirement
      const slug = providerSlug || selectedProvider?.slug;
      const [servicesData, specialistsData] = await Promise.all([
        apiClient.getProviderServices(slug || providerId),
        apiClient.getAssignableSpecialists(slug || providerId), // Use assignableSpecialists to filter out dangling specialists
      ]);
      setServices(servicesData);
      setSpecialists(specialistsData);
    } catch (err) {
      console.error('Failed to load salon data:', err);
    }
  };

  const loadServiceData = async (serviceId: string) => {
    try {
      const service = await apiClient.get(`/services/${serviceId}`);
      if (service.providers) {
        setProviders(service.providers);
      }
    } catch (err) {
      console.error('Failed to load service data:', err);
    }
  };

  const loadSpecialistData = async (specialistId: string) => {
    try {
      const specialist = await apiClient.get(`/specialists/${specialistId}`);
      if (specialist.services) {
        setServices(specialist.services);
      }
      if (specialist.provider) {
        setSelectedProvider(specialist.provider);
      }
    } catch (err) {
      console.error('Failed to load specialist data:', err);
    }
  };

  // Fetch availability when time step is active and date changes
  useEffect(() => {
    async function fetchAvailability() {
      if (step === 'time' && selectedProvider && selectedDate) {
        try {
          setIsLoading(true);
          const serviceId = selectedServices.length > 0 ? selectedServices[0].id : undefined;
          const specialistId = selectedSpecialist ? selectedSpecialist.id : undefined;
          
          console.log('Fetching availability:', {
            providerId: selectedProvider.id,
            providerName: selectedProvider.display_name,
            date: selectedDate,
            serviceId,
            specialistId,
            step
          });
          
          const availability = await apiClient.getProviderAvailability(selectedProvider.id, {
            date: selectedDate,
            service_id: serviceId,
            specialist_id: specialistId
          });
          
          console.log('Availability response:', availability);
          
          if (availability && availability.slots) {
            const normalized: TimeSlot[] = (availability.slots as any[]).map((s) => ({
              time: s.start ?? s.time,
              end: s.end,
              duration: s.duration,
              available: s.available !== false,
              availableSpecialists: s.availableSpecialists ?? s.available_specialists,
            }));
            setTimeSlots(normalized);

            const rawStatus = (availability.status as string) || (availability.is_closed ? 'closed' : 'available');
            const lowerStatus = rawStatus.toLowerCase();

            const validPublicStatuses = ['available', 'closed', 'configuration_required', 'unavailable'] as const;
            const normalizedStatus: (typeof validPublicStatuses)[number]
              = validPublicStatuses.includes(lowerStatus as any)
                ? (lowerStatus as any)
                : 'unavailable';

            const publicStatus: NonNullable<typeof availabilityStatus> = {
              status: normalizedStatus,
              message: availability.message ?? null,
              reason: availability.reason ?? availability.domain_status ?? null,
            };

            setAvailabilityStatus(publicStatus);
          } else {
            console.log('No slots in availability response:', availability);
            setTimeSlots([]);
            setAvailabilityStatus({ status: 'error', message: 'Failed to load availability' });
          }
        } catch (err) {
          console.error('Failed to fetch availability:', err);
          setTimeSlots([]);
          setAvailabilityStatus({ status: 'error', message: 'Failed to connect to the server.' });
        } finally {
          setIsLoading(false);
        }
      }
    }
    
    fetchAvailability();
  }, [step, selectedProvider, selectedDate, selectedServices, selectedSpecialist]);

  const loadAllProviders = async () => {
    try {
      const response = await apiClient.get('/v1/salons');
      // Handle paginated response - extract data array
      setProviders(Array.isArray(response) ? response : (response?.data || []));
    } catch (err) {
      console.error('Failed to load providers:', err);
    }
  };

  const handleServiceSelect = (service: Service) => {
    const isSelected = selectedServices.some(s => s.id === service.id);
    if (isSelected) {
      setSelectedServices(selectedServices.filter(s => s.id !== service.id));
    } else {
      setSelectedServices([...selectedServices, service]);
    }
  };

  const handleServiceContinue = () => {
    // Skip staff step for independent_specialist providers
    if (selectedProvider?.type === 'independent_specialist') {
      setStep('time');
    } else {
      setStep('staff');
    }
  };

  const handleStaffSelect = (specialist: Specialist | null) => {
    setSelectedSpecialist(specialist);
    setStep('time');
  };

  const handleTimeSelect = (time: string) => {
    setSelectedTime(time);
    setStep('details');
  };

  const handleProviderSelect = (provider: Provider) => {
    setSelectedProvider(provider);
    setStep('service');
    loadProviderData(provider.id, provider.slug);
  };

  /** Returns true when the provider's own record flags it as closed for bookings. */
  const isProviderClosed = (provider: Provider | null): boolean => {
    if (!provider) return false;
    if (provider.active === false) return true;
    if (provider.status && ['inactive', 'suspended'].includes(provider.status)) return true;
    return false;
  };

  const getNoAvailabilityMessage = () => {
    const service = selectedServices[0];
    const duration = service?.duration || 0;
    const minNotice = service?.min_booking_notice || 0;

    if (duration > 0 && minNotice > 0) {
      return `This service takes ${duration} minutes and requires ${minNotice} hours' advance notice. There isn't enough time remaining today to fit it.`;
    }
    if (minNotice > 0) {
      return `Appointments require ${minNotice} hours' notice. The earliest available time has passed for today.`;
    }
    if (duration > 0) {
      return `This service takes ${duration} minutes. There isn't enough remaining time today to fit it.`;
    }
    return 'No appointments available on this date.';
  };

  const stepColors = {
    provider: '#FFD700',
    service: '#a855f7',
    staff: '#3b82f6',
    time: '#10b981',
    details: '#f59e0b',
    payment: '#ef4444',
    confirm: '#10b981',
    success: '#10b981',
  };

  if (!mounted) {
    return (
      <div className="min-h-screen bg-[#070707] flex items-center justify-center">
        <Loader2 className="w-10 h-10 text-[#FFD700] animate-spin" />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-[#070707] text-white overflow-x-hidden font-poppins relative selection:bg-gold/30 selection:text-white pb-16">
      {/* Dynamic Cursor Glow */}
      <CursorGlow color={stepColors[step] || '#FFD700'} intensity={0.35} radius={350} />

      {/* Premium Header */}
      <div className="backdrop-blur-xl bg-black/40 border-b border-white/5 sticky top-0 z-50">
        <div className="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
          <div className="flex items-center gap-2.5 cursor-pointer" onClick={() => router.push('/')}>
            <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-gold to-[#C9A227] flex items-center justify-center shadow-md shadow-gold/10">
              <Crown className="w-4.5 h-4.5 text-black" />
            </div>
            <span className="font-sora font-bold text-base tracking-wider bg-gradient-to-r from-white via-white to-gold bg-clip-text text-transparent">
              Yo.Salon
            </span>
          </div>
          <button
            onClick={() => router.push('/portal/login')}
            className="text-white/60 hover:text-gold text-xs transition-colors duration-300 font-medium font-mono"
          >
            Portal Sign In
          </button>
        </div>
      </div>

      {/* Progress Steps */}
      <div className="max-w-4xl mx-auto px-4 py-8 relative z-10">
        <div className="flex items-center justify-between mb-12 bg-white/[0.02] border border-white/5 rounded-2xl p-4 backdrop-blur-md">
          {['provider', 'service', 'staff', 'time', 'details'].filter(s => {
            // Hide provider step if already selected
            if (s === 'provider' && selectedProvider) return false;
            // Hide staff step for independent_specialist providers
            if (s === 'staff' && selectedProvider?.type === 'independent_specialist') return false;
            return true;
          }).map((s, index) => {
            const visibleSteps = ['provider', 'service', 'staff', 'time', 'details'].filter(st => {
              if (st === 'provider' && selectedProvider) return false;
              if (st === 'staff' && selectedProvider?.type === 'independent_specialist') return false;
              return true;
            });
            const stepIndex = visibleSteps.indexOf(step);
            const currentIndex = visibleSteps.indexOf(s);
            const isCompleted = stepIndex > currentIndex || step === 'confirm' || step === 'success';
            const isActive = step === s;
            const stepNames = {
              salon: 'Location',
              service: 'Treatment',
              staff: 'Stylist',
              time: 'Reserve Slot',
              details: 'Profile Details',
              payment: 'Payment',
            };
            const stepSummaries = {
              provider: selectedProvider ? selectedProvider.display_name : 'Select Location',
              service: selectedServices.length > 0 ? selectedServices.map(s => s.name).join(', ') : 'Select Treatment',
              staff: selectedSpecialist ? selectedSpecialist.name : 'Preferred Stylist',
              time: selectedTime ? `${selectedDate} @ ${selectedTime}` : 'Choose Time',
              details: customerDetails.name ? customerDetails.name : 'Your Info',
              payment: paymentInstruction?.customerAction === 'pay_now' ? 'Payment' : '',
            };

            return (
              <div key={s} className="flex items-center flex-1 last:flex-none">
                <div className="flex items-center gap-3">
                  <div
                    className={`w-7 h-7 rounded-lg flex items-center justify-center text-xs font-semibold font-mono transition-all duration-500 border ${
                      isActive
                        ? 'bg-gradient-to-br from-gold to-dark-gold text-black border-gold shadow-md shadow-gold/10'
                        : isCompleted
                        ? 'bg-gold/10 text-gold border-gold/30'
                        : 'bg-white/5 text-white/30 border-white/5'
                    }`}
                  >
                    {isCompleted ? (
                      <Check className="w-3.5 h-3.5" />
                    ) : (
                      currentIndex + 1
                    )}
                  </div>
                  <div className="hidden sm:flex flex-col text-left">
                    <span className={`text-[10px] font-semibold tracking-wider font-mono uppercase ${isActive ? 'text-gold' : 'text-white/40'}`}>
                      {stepNames[s as keyof typeof stepNames]}
                    </span>
                    <span className="text-[11px] text-white/70 max-w-[120px] truncate font-medium">
                      {stepSummaries[s as keyof typeof stepSummaries]}
                    </span>
                  </div>
                </div>
                {currentIndex < visibleSteps.length - 1 && (
                  <div
                    className={`flex-grow h-px mx-4 transition-all duration-700 ${
                      isCompleted
                        ? 'bg-gradient-to-r from-gold/40 to-white/10'
                        : 'bg-white/5'
                    }`}
                  />
                )}
              </div>
            );
          })}
        </div>

        {/* Dynamic content rendering */}
        <AnimatePresence mode="wait">
          {step === 'provider' && (
            <motion.div
              key="salon-step"
              initial={{ opacity: 0, y: 15 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -15 }}
              transition={{ type: 'spring', stiffness: 200, damping: 20 }}
            >
              <div className="text-left mb-8">
                <h2 className="font-sora text-2xl md:text-3xl font-extrabold tracking-tight text-white mb-2">Discover Salons</h2>
                <p className="text-white/50 text-sm">Find the perfect place for your appointment</p>
              </div>

              {/* View mode toggle */}
              <div className="flex items-center gap-2 mb-6">
                <button
                  onClick={() => setViewMode('list')}
                  className={`flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all ${
                    viewMode === 'list'
                      ? 'bg-gold text-black'
                      : 'bg-white/5 text-white/70 hover:bg-white/10'
                  }`}
                >
                  <Search className="w-4 h-4" />
                  List View
                </button>
                <button
                  onClick={() => setViewMode('map')}
                  className={`flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all ${
                    viewMode === 'map'
                      ? 'bg-gold text-black'
                      : 'bg-white/5 text-white/70 hover:bg-white/10'
                  }`}
                >
                  <MapIcon className="w-4 h-4" />
                  Map View
                </button>
              </div>

              {viewMode === 'list' ? (
                <>
                  {/* Search bar */}
                  <div className="relative mb-6">
                    <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-white/30" />
                    <input
                      type="text"
                      placeholder="Search salons by name or location..."
                      value={searchQuery || ''}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      className="w-full pl-12 pr-4 py-4 bg-white/[0.02] border border-white/10 rounded-2xl text-white placeholder:text-white/30 focus:outline-none focus:border-gold/50 transition-all"
                    />
                  </div>

                  <div className="grid md:grid-cols-2 gap-4">
                    {providers
                      .filter(p => !searchQuery || (p.display_name || p.name || '').toLowerCase().includes(searchQuery.toLowerCase()))
                      .map((provider) => {
                        const closed = isProviderClosed(provider);
                        return (
                        <motion.button
                          key={provider.id}
                          whileHover={!closed ? { scale: 1.02, borderColor: 'rgba(255, 215, 0, 0.4)' } : {}}
                          whileTap={!closed ? { scale: 0.98 } : {}}
                          onClick={() => handleProviderSelect(provider)}
                          className={`p-6 rounded-2xl border border-white/5 text-left transition-all cursor-pointer group relative ${
                            closed
                              ? 'bg-white/[0.01] opacity-60'
                              : 'bg-white/[0.02] hover:bg-white/[0.04]'
                          }`}
                        >
                          {closed && (
                            <div className="absolute top-3 right-3 flex items-center gap-1 px-2 py-0.5 bg-red-500/20 border border-red-500/30 rounded-full">
                              <Ban className="w-2.5 h-2.5 text-red-400" />
                              <span className="text-[9px] font-mono font-bold text-red-400 uppercase tracking-wider">Closed</span>
                            </div>
                          )}
                          <div className="flex items-start gap-4">
                            <div className="w-16 h-16 rounded-xl bg-gradient-to-br from-gold/20 to-amber-500/10 border border-gold/20 flex items-center justify-center text-white text-xl font-bold flex-shrink-0 group-hover:from-gold/30 group-hover:to-amber-500/20 transition-all">
                              {(provider.display_name || provider.name || 'P').charAt(0)}
                            </div>
                            <div className="flex-1 min-w-0">
                              <h3 className="text-white font-sora font-semibold text-lg mb-1 truncate">{provider.display_name}</h3>
                              <div className="flex items-center gap-2 text-xs text-white/50 mb-2">
                                <MapPin className="w-3 h-3" />
                                <span className="truncate">{provider.city || 'Kampala'}</span>
                              </div>
                              <div className="flex items-center gap-3">
                                {provider.rating && typeof provider.rating === 'number' && (
                                  <div className="flex items-center gap-1">
                                    <Star className="w-3 h-3 text-gold fill-gold" />
                                    <span className="text-xs text-white/70">{provider.rating.toFixed(1)}</span>
                                  </div>
                                )}
                                <span className="text-xs text-white/40">•</span>
                                <span className={`text-xs ${closed ? 'text-red-400/70' : 'text-white/50'}`}>
                                  {closed ? 'Not accepting bookings' : 'Book now'}
                                </span>
                              </div>
                            </div>
                          </div>
                        </motion.button>
                        );
                      })}

                  </div>

                  {providers.length === 0 && (
                    <div className="text-center py-12">
                      <p className="text-white/50">No salons found</p>
                    </div>
                  )}
                </>
              ) : (
                <div className="h-[500px] rounded-2xl overflow-hidden border border-white/10">
                  <ProviderDiscoveryMap onProviderSelect={handleProviderSelect} />
                </div>
              )}
            </motion.div>
          )}

          {step === 'service' && (
            <motion.div
              key="service-step"
              initial={{ opacity: 0, y: 15 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -15 }}
              transition={{ type: 'spring', stiffness: 200, damping: 20 }}
            >
              <div className="text-left mb-8">
                <h2 className="font-sora text-2xl md:text-3xl font-extrabold tracking-tight text-white mb-2">Choose Treatment</h2>
                <p className="text-white/50 text-sm">Select the experience you'd like to reserve</p>
              </div>

              {/* ── Provider Closed Banner ── */}
              {isProviderClosed(selectedProvider) ? (
                <div className="text-center py-16 px-8">
                  <div className="w-20 h-20 mx-auto mb-6 rounded-full bg-red-500/10 border border-red-500/20 flex items-center justify-center">
                    <Ban className="w-10 h-10 text-red-400" />
                  </div>
                  <h3 className="text-white font-sora font-semibold text-xl mb-3">
                    {selectedProvider?.display_name || 'This provider'} is not accepting bookings
                  </h3>
                  <p className="text-white/50 text-sm max-w-md mx-auto mb-2">
                    This location is currently closed or temporarily unavailable for online booking.
                  </p>
                  <p className="text-white/30 text-xs max-w-sm mx-auto mb-8">
                    Please contact {selectedProvider?.display_name || 'the provider'} directly, or check back later.
                  </p>
                  <button
                    onClick={() => setStep('provider')}
                    className="px-6 py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl text-white text-xs font-mono font-semibold transition-all"
                  >
                    ← Browse Other Salons
                  </button>
                </div>
              ) : services.length === 0 ? (
                <div className="text-center py-16 px-8">
                  <div className="w-20 h-20 mx-auto mb-6 rounded-full bg-white/5 border border-white/10 flex items-center justify-center">
                    <Info className="w-10 h-10 text-gold" />
                  </div>
                  <h3 className="text-white font-sora font-semibold text-xl mb-3">No Services Available</h3>
                  <p className="text-white/50 text-sm max-w-md mx-auto mb-6">
                    {selectedProvider?.display_name || 'This salon'} hasn't added any services yet. Please check back later or contact them directly.
                  </p>
                  <button
                    onClick={() => setStep('provider')}
                    className="px-6 py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl text-white text-xs font-mono font-semibold transition-all"
                  >
                    ← Browse Other Salons
                  </button>
                </div>
              ) : (
                <>
                  <div className="grid md:grid-cols-2 gap-4">
                    {services.map((service) => {
                      const isSelected = selectedServices.some(s => s.id === service.id);
                      return (
                        <motion.button
                          key={service.id}
                          whileHover={{ scale: 1.01 }}
                          whileTap={{ scale: 0.99 }}
                          onClick={() => handleServiceSelect(service)}
                          className={`p-6 rounded-2xl border text-left transition-all cursor-pointer ${
                            isSelected
                              ? 'border-gold bg-gold/10 shadow-lg'
                              : 'border-white/5 bg-white/[0.02] hover:bg-white/[0.04]'
                          }`}
                        >
                          <div className="flex justify-between items-start gap-4 mb-2">
                            <h3 className="text-white font-sora font-semibold text-base">{service.name}</h3>
                            <span className="text-gold font-sora font-bold text-sm">
                              {service.price.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })} UGX
                            </span>
                          </div>
                          <div className="flex items-center gap-1.5 text-white/50 text-xs mt-2">
                            <Clock className="w-3.5 h-3.5 text-gold" />
                            <span>{service.duration || 0} min</span>
                          </div>
                        </motion.button>
                      );
                    })}
                  </div>

                  {selectedServices.length > 0 && (
                    <div className="flex justify-end mt-6">
                      <button
                        onClick={handleServiceContinue}
                        className="px-8 py-3 bg-gradient-to-r from-gold to-[#C9A227] hover:brightness-110 transition-all text-black font-semibold rounded-xl text-sm shadow-lg shadow-gold/10 flex items-center justify-center gap-2"
                      >
                        Continue
                        <ArrowRight className="w-4 h-4" />
                      </button>
                    </div>
                  )}
                </>
              )}
            </motion.div>
          )}

          {step === 'staff' && (
            <motion.div
              key="staff-step"
              initial={{ opacity: 0, y: 15 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -15 }}
              transition={{ type: 'spring', stiffness: 200, damping: 20 }}
            >
              <div className="text-left mb-8">
                <h2 className="font-sora text-2xl md:text-3xl font-extrabold tracking-tight text-white mb-2">Choose Stylist</h2>
                <p className="text-white/50 text-sm">Select your preferred stylist or skip to assign automatically</p>
              </div>

              {/* ── Guard: provider closed → no staff shown ── */}
              {isProviderClosed(selectedProvider) ? (
                <div className="text-center py-12 px-8">
                  <div className="w-16 h-16 mx-auto mb-5 rounded-full bg-red-500/10 border border-red-500/20 flex items-center justify-center">
                    <Ban className="w-8 h-8 text-red-400" />
                  </div>
                  <h3 className="text-white font-sora font-semibold text-lg mb-2">
                    {selectedProvider?.display_name || 'This provider'} is currently closed
                  </h3>
                  <p className="text-white/50 text-sm max-w-sm mx-auto mb-6">
                    This location is not accepting bookings at the moment. Stylists are listed here but cannot be booked.
                  </p>
                  <button
                    onClick={() => setStep('provider')}
                    className="px-6 py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl text-white text-xs font-mono font-semibold transition-all"
                  >
                    ← Browse Other Salons
                  </button>
                </div>
              ) : (
                <>
                  <div className="grid md:grid-cols-3 gap-4">
                    {Array.isArray(specialists) && specialists.map((specialist) => (
                      <motion.button
                        key={specialist.id}
                        whileHover={{ scale: 1.01 }}
                        whileTap={{ scale: 0.99 }}
                        onClick={() => handleStaffSelect(specialist)}
                        className={`p-6 rounded-2xl border text-center transition-all cursor-pointer ${
                          selectedSpecialist?.id === specialist.id
                            ? 'border-gold bg-gold/10 shadow-lg'
                            : 'border-white/5 bg-white/[0.02] hover:bg-white/[0.04]'
                        }`}
                      >
                        <div className="w-14 h-14 mx-auto rounded-full bg-gradient-to-tr from-gold/20 to-amber-500/10 border border-gold/20 flex items-center justify-center text-sm font-bold mb-3">
                          {specialist.name.split(' ').map(n => n[0]).join('')}
                        </div>
                        <h3 className="text-white font-sora font-semibold text-base mb-1">{specialist.name}</h3>
                        <span className="text-white/45 text-xs font-mono uppercase tracking-wider">{specialist.role || 'Specialist'}</span>
                      </motion.button>
                    ))}
                  </div>

                  <div className="flex justify-between items-center mt-6">
                    <button
                      onClick={() => setStep('service')}
                      className="text-white/50 hover:text-white text-xs font-mono font-medium transition-colors"
                    >
                      ← Back to treatments
                    </button>
                    <button
                      onClick={() => handleStaffSelect(null)}
                      className="px-5 py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl text-white text-xs font-mono font-semibold transition-all"
                    >
                      Skip stylist selection →
                    </button>
                  </div>
                </>
              )}
            </motion.div>
          )}

          {step === 'time' && (
            <motion.div
              key="time-step"
              initial={{ opacity: 0, y: 15 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -15 }}
              transition={{ type: 'spring', stiffness: 200, damping: 20 }}
            >
              <div className="text-left mb-8">
                <h2 className="font-sora text-2xl md:text-3xl font-extrabold tracking-tight text-white mb-2">Reserve Slot</h2>
                <p className="text-white/50 text-sm">Select your preferred appointment date and time</p>
              </div>

              <div className="flex flex-col md:flex-row gap-6 mb-8">
                <div className="flex flex-col text-left p-5 bg-white/[0.02] border border-white/5 rounded-2xl backdrop-blur-md w-full md:w-1/3">
                  <span className="text-[9px] font-bold tracking-wider font-mono text-white/30 uppercase mb-3">SELECT DATE</span>
                  <input
                    type="date"
                    value={selectedDate}
                    onChange={(e) => setSelectedDate(e.target.value)}
                    className="w-full px-4 py-3 bg-black/40 border border-white/10 rounded-xl text-white font-mono text-sm focus:outline-none focus:border-gold transition-colors cursor-pointer"
                    min={new Date().toISOString().split('T')[0]}
                  />
                </div>

                <div className="flex-1 text-left p-5 bg-white/[0.02] border border-white/5 rounded-2xl backdrop-blur-md">
                  <div className="flex items-center gap-2 mb-4">
                    <span className="text-[9px] font-bold tracking-wider font-mono text-white/30 uppercase">AVAILABLE TIMESLOTS</span>
                    <button
                      onClick={() => setShowInfoPopover(!showInfoPopover)}
                      className="text-white/30 hover:text-gold transition-colors"
                    >
                      <Info className="w-3.5 h-3.5" />
                    </button>
                  </div>

                  {/* Info Popover */}
                  <AnimatePresence>
                    {showInfoPopover && (
                      <motion.div
                        initial={{ opacity: 0, y: -10 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -10 }}
                        className="mb-4 p-4 bg-white/[0.03] border border-white/10 rounded-xl"
                      >
                        <div className="flex justify-between items-start mb-3">
                          <h4 className="text-white/90 font-semibold text-sm">Booking information</h4>
                          <button
                            onClick={() => setShowInfoPopover(false)}
                            className="text-white/40 hover:text-white transition-colors"
                          >
                            <X className="w-4 h-4" />
                          </button>
                        </div>
                        <ul className="space-y-2 text-xs text-white/60">
                          {selectedServices.length > 0 && selectedServices[0].duration && (
                            <li className="flex items-start gap-2">
                              <span className="text-gold">•</span>
                              <span>This service takes <strong>{selectedServices[0].duration} minutes</strong>.</span>
                            </li>
                          )}
                          {selectedServices.length > 0 && (selectedServices[0].min_booking_notice || 0) > 0 && (
                            <li className="flex items-start gap-2">
                              <span className="text-gold">•</span>
                              <span>Appointments require <strong>{selectedServices[0].min_booking_notice} hours' notice</strong>.</span>
                            </li>
                          )}
                          <li className="flex items-start gap-2">
                            <span className="text-gold">•</span>
                            <span>Available times depend on the specialist's working hours and existing appointments.</span>
                          </li>
                        </ul>
                      </motion.div>
                    )}
                  </AnimatePresence>

                  <div className="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-5 gap-3">
                    {isLoading ? (
                      <div className="col-span-full py-8 flex justify-center">
                        <Loader2 className="w-6 h-6 text-gold animate-spin" />
                      </div>
                    ) : timeSlots.length > 0 ? (
                      timeSlots.map((slot) => (
                        <motion.button
                          key={slot.time}
                          whileHover={{ scale: 1.02 }}
                          whileTap={{ scale: 0.98 }}
                          onClick={() => slot.available && handleTimeSelect(slot.time)}
                          disabled={!slot.available}
                          className={`p-3.5 rounded-xl border font-mono text-xs transition-all flex flex-col items-center justify-center gap-1 ${
                            selectedTime === slot.time
                              ? 'border-gold bg-gold/10 text-gold shadow-md shadow-gold/5 cursor-pointer'
                              : slot.available
                              ? 'border-white/5 bg-black/30 hover:border-white/20 text-white cursor-pointer'
                              : 'border-white/5 bg-white/5 text-white/20 cursor-not-allowed opacity-50'
                          }`}
                        >
                          <span className="font-medium">{slot.time}</span>
                          {slot.available ? (
                            <LockOpen className="w-3 h-3 text-green-400" />
                          ) : (
                            <Lock className="w-3 h-3 text-red-400" />
                          )}
                          <span className="text-[9px] opacity-70">
                            {slot.available ? 'Available' : 'Unavailable'}
                          </span>
                        </motion.button>
                      ))
                    ) : (
                      <div className="col-span-full py-8 text-center flex flex-col items-center justify-center">
                        {availabilityStatus?.status === 'configuration_required' ? (
                          <>
                            {/* Could be PROVIDER_DISABLED or setup missing */}
                            {availabilityStatus.reason === 'provider_disabled' ? (
                              <>
                                <Ban className="w-8 h-8 text-red-400/70 mb-3" />
                                <p className="text-white/80 font-medium text-sm max-w-sm">
                                  <strong>{selectedProvider?.display_name || 'This provider'}</strong> is not currently accepting bookings.
                                </p>
                                <p className="text-white/40 text-xs mt-2">
                                  Please contact them directly or check back later.
                                </p>
                              </>
                            ) : (
                              <>
                                <Info className="w-8 h-8 text-yellow-500/70 mb-3" />
                                <p className="text-white/80 font-medium text-sm max-w-sm">
                                  {availabilityStatus.message || "Online booking isn't currently set up for this provider."}
                                </p>
                                <p className="text-white/40 text-xs mt-2">Please contact {selectedProvider?.display_name || 'the provider'} directly or try again later.</p>
                              </>
                            )}
                          </>
                        ) : availabilityStatus?.status === 'closed' ? (
                          <>
                            <Info className="w-8 h-8 text-white/30 mb-3" />
                            <p className="text-white/80 font-medium text-sm">
                              {selectedProvider?.display_name
                                ? <><strong>{selectedProvider.display_name}</strong> is closed on this day.</>
                                : availabilityStatus.message || 'No appointments available on this date.'}
                            </p>
                            <p className="text-white/40 text-xs mt-2">Please select another date.</p>
                          </>
                        ) : (
                          <>
                            <Info className="w-8 h-8 text-white/30 mb-3" />
                            <p className="text-white/80 font-medium text-sm max-w-sm">
                              {getNoAvailabilityMessage()}
                            </p>
                            <p className="text-white/40 text-xs mt-2">Try another date.</p>
                          </>
                        )}
                      </div>
                    )}

                  </div>
                </div>
              </div>

              <div className="flex justify-between items-center">
                <button
                  onClick={() => selectedProvider?.type === 'independent_specialist' ? setStep('service') : setStep('staff')}
                  className="text-white/50 hover:text-white text-xs font-mono font-medium transition-colors"
                >
                  ← Back to {selectedProvider?.type === 'independent_specialist' ? 'treatments' : 'stylist'}
                </button>
              </div>
            </motion.div>
          )}

          {step === 'details' && (
            <motion.div
              key="details-step"
              initial={{ opacity: 0, y: 15 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -15 }}
              transition={{ type: 'spring', stiffness: 200, damping: 20 }}
            >
              <div className="text-left mb-8">
                <h2 className="font-sora text-2xl md:text-3xl font-extrabold tracking-tight text-white mb-2">Your Details</h2>
                <p className="text-white/50 text-sm">Provide your contact information</p>
              </div>

              {!showFindDetails && !foundCustomer && (
                <div className="bg-white/[0.02] border border-white/5 rounded-2xl p-4 mb-6">
                  <p className="text-white/70 text-sm mb-3">Have you visited us before?</p>
                  <div className="flex gap-3">
                    <button
                      onClick={() => setShowFindDetails(true)}
                      className="flex-1 py-2.5 rounded-lg bg-gold/10 text-gold font-medium hover:bg-gold/20 transition-colors text-sm"
                    >
                      Find my details
                    </button>
                    <button
                      onClick={() => setShowFindDetails(false)}
                      className="flex-1 py-2.5 rounded-lg bg-white/10 text-white/70 font-medium hover:bg-white/20 transition-colors text-sm"
                    >
                      I'm new here
                    </button>
                  </div>
                </div>
              )}

              {showFindDetails && !foundCustomer && (
                <FindMyDetails
                  providerId={selectedProvider?.id}
                  locationId={undefined}
                  onCustomerFound={(customer) => {
                    setFoundCustomer(customer);
                    setCustomerDetails({
                      name: customer.name,
                      phone: customer.phone,
                      email: customer.email || '',
                    });
                  }}
                  onCancel={() => setShowFindDetails(false)}
                />
              )}

              {foundCustomer && (
                <div className="bg-green-500/10 border border-green-500/30 rounded-2xl p-4 mb-6">
                  <div className="flex items-center gap-3 mb-3">
                    <div className="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center flex-shrink-0">
                      <span className="text-green-400 text-lg">👋</span>
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-green-400 font-semibold text-sm">Welcome back, {foundCustomer.name.split(' ')[0]}!</p>
                      <p className="text-white/60 text-xs">
                        We found your details from a previous visit.
                      </p>
                    </div>
                  </div>
                  <button
                    onClick={() => {
                      setFoundCustomer(null);
                      setCustomerDetails({ name: '', phone: '', email: '' });
                    }}
                    className="w-full py-2 rounded-lg bg-white/10 text-white/60 font-medium hover:bg-white/20 transition-colors text-sm"
                  >
                    Not you? Find again
                  </button>
                </div>
              )}

              <div className="space-y-4 max-w-md">
                <div>
                  <label className="block text-sm font-medium text-white/70 mb-2">Full Name *</label>
                  <input
                    type="text"
                    value={customerDetails.name}
                    onChange={(e) => setCustomerDetails({ ...customerDetails, name: e.target.value })}
                    placeholder="Enter your name"
                    className="w-full px-4 py-3 bg-black/40 border border-white/10 rounded-xl text-white focus:outline-none focus:border-gold transition-colors"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-white/70 mb-2">Phone Number *</label>
                  <input
                    type="tel"
                    value={customerDetails.phone}
                    onChange={(e) => setCustomerDetails({ ...customerDetails, phone: e.target.value })}
                    placeholder="Enter your phone"
                    className="w-full px-4 py-3 bg-black/40 border border-white/10 rounded-xl text-white focus:outline-none focus:border-gold transition-colors"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-white/70 mb-2">Email (optional)</label>
                  <input
                    type="email"
                    value={customerDetails.email}
                    onChange={(e) => setCustomerDetails({ ...customerDetails, email: e.target.value })}
                    placeholder="Enter your email"
                    className="w-full px-4 py-3 bg-black/40 border border-white/10 rounded-xl text-white focus:outline-none focus:border-gold transition-colors"
                  />
                </div>
              </div>

              <div className="flex justify-between items-center mt-8">
                <button
                  onClick={() => setStep('time')}
                  className="text-white/50 hover:text-white text-xs font-mono font-medium transition-colors"
                >
                  ← Back to time
                </button>
                <button
                  onClick={async () => {
                    if (!selectedProvider || selectedServices.length === 0) return;
                    setIsLoading(true);
                    setError('');
                    try {
                      const instructions = await apiClient.getProviderServicePaymentInstructions(
                        selectedProvider.id, 
                        selectedServices[0].id
                      );
                      setPaymentInstruction(instructions);
                      if (instructions.customerAction === 'pay_now') {
                        setStep('payment');
                      } else {
                        setStep('confirm');
                      }
                    } catch (err: any) {
                      console.error('Failed to get payment instructions:', err);
                      setError('Failed to fetch payment details. Please try again.');
                    } finally {
                      setIsLoading(false);
                    }
                  }}
                  disabled={isLoading}
                  className="px-8 py-3 bg-gradient-to-r from-gold to-[#C9A227] hover:brightness-110 transition-all text-black font-semibold rounded-xl text-sm shadow-lg shadow-gold/10 flex items-center justify-center gap-2 disabled:opacity-50"
                >
                  {isLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Continue'}
                  {!isLoading && <ArrowRight className="w-4 h-4" />}
                </button>
              </div>
            </motion.div>
          )}

          {step === 'payment' && (
            <motion.div
              key="payment-step"
              initial={{ opacity: 0, y: 15 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -15 }}
              transition={{ type: 'spring', stiffness: 200, damping: 20 }}
            >
              <div className="text-left mb-8">
                <h2 className="font-sora text-2xl md:text-3xl font-extrabold tracking-tight text-white mb-2">Payment</h2>
                <p className="text-white/50 text-sm">Complete payment to secure your booking</p>
              </div>

              {paymentInstruction && (
                <div className="bg-white/[0.02] border border-white/5 rounded-2xl p-6 mb-8">
                  <div className="flex justify-between items-center mb-4">
                    <span className="text-white/70 font-medium">Amount Due Now</span>
                    <span className="text-gold font-bold text-xl">{paymentInstruction.amountDueNow?.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })} {paymentInstruction.currency || 'UGX'}</span>
                  </div>
                  <p className="text-white/60 text-sm mb-6">{paymentInstruction.message}</p>
                  
                  <div className="space-y-3">
                    <span className="text-[10px] font-bold tracking-wider font-mono text-white/40 uppercase block mb-2">Select Payment Method</span>
                    {paymentInstruction.eligibleMethods?.map((method: any) => (
                      <button
                        key={method.id}
                        onClick={() => setSelectedPaymentMethod(method.id.toString())}
                        className={`w-full p-4 rounded-xl border flex items-center justify-between transition-all ${
                          selectedPaymentMethod === method.id.toString()
                            ? 'border-gold bg-gold/10 text-white'
                            : 'border-white/10 bg-black/40 hover:bg-white/5 text-white/70'
                        }`}
                      >
                        <span className="font-medium">{method.name}</span>
                        {selectedPaymentMethod === method.id.toString() && <CheckCircle2 className="w-5 h-5 text-gold" />}
                      </button>
                    ))}
                  </div>
                </div>
              )}

              <div className="flex justify-between items-center mt-8">
                <button
                  onClick={() => setStep('details')}
                  className="text-white/50 hover:text-white text-xs font-mono font-medium transition-colors"
                >
                  ← Back to details
                </button>
                <button
                  onClick={() => setStep('confirm')}
                  disabled={!selectedPaymentMethod}
                  className="px-8 py-3 bg-gradient-to-r from-gold to-[#C9A227] hover:brightness-110 transition-all text-black font-semibold rounded-xl text-sm shadow-lg shadow-gold/10 flex items-center justify-center gap-2 disabled:opacity-50"
                >
                  Continue to Confirm
                  <ArrowRight className="w-4 h-4" />
                </button>
              </div>
            </motion.div>
          )}

          {step === 'confirm' && (
            <motion.div
              key="confirm-step"
              initial={{ opacity: 0, y: 15 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -15 }}
              transition={{ type: 'spring', stiffness: 200, damping: 20 }}
            >
              <div className="text-left mb-8">
                <h2 className="font-sora text-2xl md:text-3xl font-extrabold tracking-tight text-white mb-2">Confirm Booking</h2>
                <p className="text-white/50 text-sm">Review your appointment details</p>
              </div>

              <div className="bg-white/[0.02] border border-white/5 rounded-2xl p-6 space-y-4">
                {selectedProvider && (
                  <div>
                    <span className="text-white/50 text-xs font-mono uppercase">Location</span>
                    <p className="text-white font-semibold">{selectedProvider.display_name}</p>
                  </div>
                )}
                {selectedServices.length > 0 && (
                  <div>
                    <span className="text-white/50 text-xs font-mono uppercase">Services</span>
                    <p className="text-white font-semibold">{selectedServices.map(s => s.name).join(', ')}</p>
                  </div>
                )}
                {selectedSpecialist && (
                  <div>
                    <span className="text-white/50 text-xs font-mono uppercase">Stylist</span>
                    <p className="text-white font-semibold">{selectedSpecialist.name}</p>
                  </div>
                )}
                <div>
                  <span className="text-white/50 text-xs font-mono uppercase">Date & Time</span>
                  <p className="text-white font-semibold">{selectedDate} @ {selectedTime}</p>
                </div>
                <div>
                  <span className="text-white/50 text-xs font-mono uppercase">Contact</span>
                  <p className="text-white font-semibold">{customerDetails.name} - {customerDetails.phone}</p>
                </div>
                {paymentInstruction && (
                  <div className="pt-4 mt-4 border-t border-white/5">
                    <span className="text-white/50 text-xs font-mono uppercase">Payment Summary</span>
                    <div className="flex justify-between mt-2">
                      <span className="text-white/70">Amount Due Now</span>
                      <span className="text-white font-bold">{paymentInstruction.amountDueNow?.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })} {paymentInstruction.currency || 'UGX'}</span>
                    </div>
                    {paymentInstruction.amountRemaining > 0 && (
                      <div className="flex justify-between mt-1">
                        <span className="text-white/70">Pay at Salon</span>
                        <span className="text-white font-medium">{paymentInstruction.amountRemaining?.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })} {paymentInstruction.currency || 'UGX'}</span>
                      </div>
                    )}
                  </div>
                )}
              </div>

              {error && (
                <div className="mt-4 px-4 py-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
                  {error}
                </div>
              )}

              <div className="flex justify-between items-center mt-8">
                <button
                  onClick={() => setStep(paymentInstruction?.customerAction === 'pay_now' ? 'payment' : 'details')}
                  className="text-white/50 hover:text-white text-xs font-mono font-medium transition-colors"
                >
                  ← Back
                </button>
                <button
                  onClick={async () => {
                    setIsLoading(true);
                    setError('');
                    try {
                      const response = await apiClient.createBookingWithAccount({
                        provider_id: selectedProvider?.id,
                        service_id: selectedServices.map(s => s.id),
                        specialist_id: selectedSpecialist?.id || null,
                        date: selectedDate,
                        time: selectedTime,
                        customer_name: customerDetails.name,
                        customer_phone: customerDetails.phone,
                        customer_email: customerDetails.email,
                        create_account: false, // For now, just a guest booking
                        payment_method_id: selectedPaymentMethod ? parseInt(selectedPaymentMethod) : null,
                        idempotency_key: crypto.randomUUID()
                      });
                      // Capture booking status from response
                      setBookingStatus(response.booking?.status || null);
                      setStep('success');
                    } catch (err: any) {
                      console.error('Booking failed:', err);
                      setError(err.response?.data?.message || 'Failed to create booking. Please try again.');
                    } finally {
                      setIsLoading(false);
                    }
                  }}
                  disabled={isLoading}
                  className="px-8 py-3 bg-gradient-to-r from-gold to-[#C9A227] hover:brightness-110 transition-all text-black font-semibold rounded-xl text-sm shadow-lg shadow-gold/10 flex items-center justify-center gap-2 disabled:opacity-50"
                >
                  {isLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Confirm Booking'}
                  {!isLoading && <CheckCircle2 className="w-4 h-4" />}
                </button>
              </div>
            </motion.div>
          )}

          {step === 'success' && (
            <motion.div
              key="success-step"
              initial={{ opacity: 0, y: 15 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -15 }}
              transition={{ type: 'spring', stiffness: 200, damping: 20 }}
              className="text-center py-12"
            >
              <div className={`w-20 h-20 mx-auto rounded-full bg-gradient-to-br ${bookingStatus === 'pending' ? 'from-yellow-500/20 to-amber-500/10 border border-yellow-500/30' : 'from-green-500/20 to-emerald-500/10 border border-green-500/30'} flex items-center justify-center mb-6`}>
                {bookingStatus === 'pending' ? (
                  <Clock className="w-10 h-10 text-yellow-500" />
                ) : (
                  <CheckCircle2 className="w-10 h-10 text-green-500" />
                )}
              </div>
              <h2 className="font-sora text-3xl font-extrabold tracking-tight text-white mb-4">
                {bookingStatus === 'pending' ? 'Booking Submitted!' : 'Booking Confirmed!'}
              </h2>
              <p className="text-white/50 text-sm mb-8">
                {bookingStatus === 'pending' 
                  ? 'Your appointment request has been submitted and is awaiting approval. You will be notified once it is confirmed.'
                  : 'Your appointment has been successfully booked. You will receive a confirmation shortly.'}
              </p>
              <button
                onClick={() => router.push('/')}
                className="px-8 py-3 bg-gradient-to-r from-gold to-[#C9A227] hover:brightness-110 transition-all text-black font-semibold rounded-xl text-sm shadow-lg shadow-gold/10"
              >
                Return Home
              </button>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </div>
  );
}

export default function BookingPage() {
  return (
    <Suspense fallback={<div className="min-h-screen bg-background flex items-center justify-center"><Loader2 className="w-8 h-8 animate-spin text-gold" /></div>}>
      <BookingPageContent />
    </Suspense>
  );
}

export { BookingPageContent };
