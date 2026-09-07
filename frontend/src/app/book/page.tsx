'use client';

import { useState, useEffect, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { motion, AnimatePresence } from 'framer-motion';
import { 
  Crown, Calendar, Clock, User, ArrowRight, 
  CheckCircle2, Loader2, Star, Check, Info, Search, Phone
} from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { CursorGlow } from '@/components/ui/cursor-glow';

interface Service {
  id: string;
  name: string;
  category: string;
  price: number;
  duration: number;
}

interface Specialist {
  id: string;
  name: string;
  role?: string; // Optional since it might not be provided
}

interface Salon {
  id: string;
  name: string;
  slug: string;
}

interface TimeSlot {
  time: string;
  end: string;
  duration: number;
  available: boolean;
  availableSpecialists?: any[];
}

interface BookingPageContentProps {
  preloadedSalon?: Salon | null;
}

function BookingPageContent({ preloadedSalon }: BookingPageContentProps = {}) {
  const router = useRouter();
  const searchParams = useSearchParams();
  
  // Context from URL params
  const contextSalonSlug = searchParams.get('salon_slug');
  const contextServiceId = searchParams.get('service_id');
  const contextSpecialistId = searchParams.get('specialist_id');
  const contextSalonId = searchParams.get('salon_id');
  const contextProviderId = searchParams.get('provider_id');

  // Check if we're on a /[slug]/book route by examining the current path
  const isSlugRoute = typeof window !== 'undefined' && window.location.pathname.match(/^\/[^/]+\/book$/);
  const slugFromPath = isSlugRoute ? window.location.pathname.split('/')[1] : null;

  const [step, setStep] = useState<'salon' | 'service' | 'staff' | 'time' | 'details' | 'payment' | 'confirm' | 'success'>('salon');
  const [selectedSalon, setSelectedSalon] = useState<Salon | null>(null);
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
  const [salons, setSalons] = useState<Salon[]>([]);
  const [timeSlots, setTimeSlots] = useState<TimeSlot[]>([]);
  const [availabilityStatus, setAvailabilityStatus] = useState<{
    status: 'available' | 'closed' | 'configuration_required' | 'unavailable' | 'error';
    message?: string | null;
    reason?: string | null;
  } | null>(null);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
    
    // Determine initial step based on context
    if (contextSalonId || contextSalonSlug) {
      setStep('service');
      if (contextServiceId) {
        setStep('staff');
        if (contextSpecialistId) {
          setStep('time');
        }
      }
    } else if (contextProviderId) {
      setStep('salon');
    } else if (contextServiceId) {
      setStep('salon');
    } else if (contextSpecialistId) {
      setStep('salon');
    }
  }, [contextServiceId, contextSpecialistId, contextSalonId, contextSalonSlug, contextProviderId]);

  // Load data based on context
  useEffect(() => {
    async function loadData() {
      // If on /[slug]/book route, extract slug from path
      if (slugFromPath) {
        try {
          const salon = await apiClient.getSalonBySlug(slugFromPath);
          setSelectedSalon(salon);
          loadSalonData(salon.id);
          return;
        } catch (err) {
          console.error('Failed to load salon from slug:', err);
        }
      }

      // Use preloaded salon if available (from props)
      if (preloadedSalon) {
        setSelectedSalon(preloadedSalon);
        loadSalonData(preloadedSalon.id);
        return;
      }

      if (contextSalonSlug) {
        try {
          const salon = await apiClient.getSalonBySlug(contextSalonSlug);
          setSelectedSalon(salon);
          loadSalonData(salon.id);
        } catch (err) {
          console.error('Failed to load salon:', err);
        }
      } else if (contextProviderId) {
        try {
          const provider = await apiClient.get(`/providers/${contextProviderId}`);
          // Load provider's locations (salons)
          if (provider.salon) {
            setSelectedSalon(provider.salon);
            loadSalonData(provider.salon.id);
          } else if (provider.locations && provider.locations.length > 0) {
            setSalons(provider.locations);
          } else {
            // Provider has no locations, load all salons
            loadAllSalons();
          }
        } catch (err) {
          console.error('Failed to load provider:', err);
        }
      } else if (contextServiceId) {
        try {
          const service = await apiClient.get(`/services/${contextServiceId}`);
          setSelectedServices([service]);
          loadServiceData(service.id);
        } catch (err) {
          console.error('Failed to load service:', err);
        }
      } else if (contextSpecialistId) {
        try {
          const specialist = await apiClient.get(`/specialists/${contextSpecialistId}`);
          setSelectedSpecialist(specialist);
          loadSpecialistData(specialist.id);
        } catch (err) {
          console.error('Failed to load specialist:', err);
        }
      } else {
        // No context, load all salons
        loadAllSalons();
      }
    }
    
    loadData();
  }, [contextServiceId, contextSpecialistId, contextSalonSlug, contextProviderId, preloadedSalon, slugFromPath]);

  const loadSalonData = async (salonId: string) => {
    if (!salonId) {
      console.warn('loadSalonData called with empty salonId — skipping');
      return;
    }
    try {
      const [servicesData, specialistsData] = await Promise.all([
        apiClient.getSalonServices(salonId),
        apiClient.getSalonSpecialists(salonId),
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
      if (service.salons) {
        setSalons(service.salons);
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
        setSelectedSalon(specialist.provider);
      }
    } catch (err) {
      console.error('Failed to load specialist data:', err);
    }
  };

  // Fetch availability when time step is active and date changes
  useEffect(() => {
    async function fetchAvailability() {
      if (step === 'time' && selectedSalon && selectedDate) {
        try {
          setIsLoading(true);
          const serviceId = selectedServices.length > 0 ? selectedServices[0].id : undefined;
          const specialistId = selectedSpecialist ? selectedSpecialist.id : undefined;
          
          console.log('Fetching availability:', {
            salonId: selectedSalon.id,
            salonName: selectedSalon.name,
            date: selectedDate,
            serviceId,
            specialistId,
            step
          });
          
          const availability = await apiClient.getSalonAvailability(selectedSalon.id, {
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
  }, [step, selectedSalon, selectedDate, selectedServices, selectedSpecialist]);

  const loadAllSalons = async () => {
    try {
      const data = await apiClient.get('/salons');
      setSalons(data);
    } catch (err) {
      console.error('Failed to load salons:', err);
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

  const handleStaffSelect = (specialist: Specialist | null) => {
    setSelectedSpecialist(specialist);
    setStep('time');
  };

  const handleTimeSelect = (time: string) => {
    setSelectedTime(time);
    setStep('details');
  };

  const handleSalonSelect = (salon: Salon) => {
    setSelectedSalon(salon);
    setStep('service');
    loadSalonData(salon.id);
  };

  const stepColors = {
    salon: '#FFD700',
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
          {['salon', 'service', 'staff', 'time', 'details'].filter(s => {
            // Hide salon step if already selected
            if (s === 'salon' && selectedSalon) return false;
            return true;
          }).map((s, index) => {
            const visibleSteps = ['salon', 'service', 'staff', 'time', 'details'].filter(st => {
              if (st === 'salon' && selectedSalon) return false;
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
              salon: selectedSalon ? selectedSalon.name : 'Select Location',
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
          {step === 'salon' && (
            <motion.div
              key="salon-step"
              initial={{ opacity: 0, y: 15 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -15 }}
              transition={{ type: 'spring', stiffness: 200, damping: 20 }}
            >
              <div className="text-left mb-8">
                <h2 className="font-sora text-2xl md:text-3xl font-extrabold tracking-tight text-white mb-2">Choose Location</h2>
                <p className="text-white/50 text-sm">Select where you'd like to book your appointment</p>
              </div>

              <div className="grid md:grid-cols-2 gap-4">
                {salons.map((salon) => (
                  <motion.button
                    key={salon.id}
                    whileHover={{ scale: 1.01, borderColor: 'rgba(255, 215, 0, 0.4)' }}
                    whileTap={{ scale: 0.99 }}
                    onClick={() => handleSalonSelect(salon)}
                    className="p-6 rounded-2xl border border-white/5 bg-white/[0.02] text-left transition-all cursor-pointer hover:bg-white/[0.04]"
                  >
                    <h3 className="text-white font-sora font-semibold text-lg mb-1">{salon.name}</h3>
                    <p className="text-white/50 text-sm">Book your appointment here</p>
                  </motion.button>
                ))}
              </div>
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
                        <span>{service.duration} min</span>
                      </div>
                    </motion.button>
                  );
                })}
              </div>

              {selectedServices.length > 0 && (
                <div className="flex justify-end mt-6">
                  <button
                    onClick={() => setStep('staff')}
                    className="px-8 py-3 bg-gradient-to-r from-gold to-[#C9A227] hover:brightness-110 transition-all text-black font-semibold rounded-xl text-sm shadow-lg shadow-gold/10 flex items-center justify-center gap-2"
                  >
                    Continue
                    <ArrowRight className="w-4 h-4" />
                  </button>
                </div>
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

              <div className="grid md:grid-cols-3 gap-4">
                {specialists.map((specialist) => (
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
                  <span className="text-[9px] font-bold tracking-wider font-mono text-white/30 uppercase mb-4 block">AVAILABLE TIMESLOTS</span>
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
                          className={`p-3.5 rounded-xl border font-mono text-xs transition-all ${
                            selectedTime === slot.time
                              ? 'border-gold bg-gold/10 text-gold shadow-md shadow-gold/5 cursor-pointer'
                              : slot.available
                              ? 'border-white/5 bg-black/30 hover:border-white/20 text-white cursor-pointer'
                              : 'border-white/5 bg-white/5 text-white/20 cursor-not-allowed opacity-50'
                          }`}
                        >
                          {slot.time}
                        </motion.button>
                      ))
                    ) : (
                      <div className="col-span-full py-8 text-center flex flex-col items-center justify-center">
                        {availabilityStatus?.status === 'configuration_required' || availabilityStatus?.status === 'unavailable' ? (
                          <>
                            <Info className="w-8 h-8 text-yellow-500/70 mb-3" />
                            <p className="text-white/80 font-medium text-sm max-w-sm">
                              {availabilityStatus.message || "Online booking isn't currently available."}
                            </p>
                            <p className="text-white/40 text-xs mt-2">Please contact the salon directly or try again later.</p>
                          </>
                        ) : availabilityStatus?.status === 'closed' ? (
                          <>
                            <Info className="w-8 h-8 text-white/30 mb-3" />
                            <p className="text-white/80 font-medium text-sm">
                              {availabilityStatus.message || "No more appointments are available today."}
                            </p>
                            <p className="text-white/40 text-xs mt-2">Please select another date.</p>
                          </>
                        ) : (
                          <p className="text-white/50 text-sm">No availability on this date.</p>
                        )}
                      </div>
                    )}
                  </div>
                </div>
              </div>

              <div className="flex justify-between items-center">
                <button
                  onClick={() => setStep('staff')}
                  className="text-white/50 hover:text-white text-xs font-mono font-medium transition-colors"
                >
                  ← Back to stylist
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
                    if (!selectedSalon || selectedServices.length === 0) return;
                    setIsLoading(true);
                    setError('');
                    try {
                      const instructions = await apiClient.getSalonServicePaymentInstructions(
                        selectedSalon.id, 
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
                {selectedSalon && (
                  <div>
                    <span className="text-white/50 text-xs font-mono uppercase">Location</span>
                    <p className="text-white font-semibold">{selectedSalon.name}</p>
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
                      await apiClient.createBookingWithAccount({
                        salon_id: selectedSalon?.id,
                        service_id: selectedServices.map(s => s.id),
                        specialist_id: selectedSpecialist?.id || null,
                        date: selectedDate,
                        time: selectedTime,
                        customer_name: customerDetails.name,
                        customer_phone: customerDetails.phone,
                        customer_email: customerDetails.email,
                        create_account: false, // For now, just a guest booking
                        payment_method_id: selectedPaymentMethod,
                        idempotency_key: crypto.randomUUID()
                      });
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
              <div className="w-20 h-20 mx-auto rounded-full bg-gradient-to-br from-green-500/20 to-emerald-500/10 border border-green-500/30 flex items-center justify-center mb-6">
                <CheckCircle2 className="w-10 h-10 text-green-500" />
              </div>
              <h2 className="font-sora text-3xl font-extrabold tracking-tight text-white mb-4">Booking Confirmed!</h2>
              <p className="text-white/50 text-sm mb-8">Your appointment has been successfully booked. You'll receive a confirmation shortly.</p>
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
