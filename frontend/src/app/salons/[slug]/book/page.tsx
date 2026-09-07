'use client';

import { useState, useEffect, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { motion, AnimatePresence } from 'framer-motion';
import { 
  Crown, Calendar, Clock, User, ArrowRight, 
  CheckCircle2, Loader2, Star, Check, Shield, Info, Search, Phone, MapPin
} from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { portalApiClient } from '@/lib/portal-api-client';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { CursorGlow } from '@/components/ui/cursor-glow';
import { SalonDiscoveryMap } from '@/components/discovery/SalonDiscoveryMap';
import AvailabilityCalendar from '@/components/booking/AvailabilityCalendar';

interface Service {
  id: string;
  name: string;
  category: string;
  price: number;
  duration: number;
}

interface StaffMember {
  id: string;
  name: string;
  role: string;
}

interface TimeSlot {
  start: string;
  end: string;
  duration: number;
  available_specialists?: Array<{
    id: string;
    name: string;
    skill_level: string;
    price: number;
  }>;
  base_price?: number;
}

function BookPageContent({ slug }: { slug: string }) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { login } = usePortalAuth();
  const salonSlug = slug;

  console.log('=== BookPageContent mounted ===');
  console.log('Slug:', salonSlug);

  const [step, setStep] = useState<'service' | 'staff' | 'time' | 'details' | 'confirm' | 'success'>('service');
  const [selectedServices, setSelectedServices] = useState<Service[]>([]);
  const [selectedStaff, setSelectedStaff] = useState<StaffMember | null>(null);
  const [selectedSlot, setSelectedSlot] = useState<TimeSlot | null>(null);
  const [selectedDate, setSelectedDate] = useState<Date>(new Date());
  const [customerDetails, setCustomerDetails] = useState({
    name: '',
    phone: '',
    email: '',
  });
  const [createAccount, setCreateAccount] = useState(false);
  const [accountDetails, setAccountDetails] = useState({
    email: '',
    password: '',
    confirmPassword: '',
  });
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const [isLookingUp, setIsLookingUp] = useState(false);
  const [lookupMessage, setLookupMessage] = useState('');
  const [bookingResult, setBookingResult] = useState<any>(null);
  const [services, setServices] = useState<Service[]>([]);
  const [staff, setStaff] = useState<StaffMember[]>([]);
  const [salonId, setSalonId] = useState<string>('');
  const [mounted, setMounted] = useState(false);
  const [showNearbySalons, setShowNearbySalons] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  // Fetch salon data from slug
  useEffect(() => {
    if (salonSlug && salonSlug.length > 0) {
      console.log('Fetching salon data for slug:', salonSlug);
      
      apiClient.getSalonBySlug(salonSlug)
        .then((salon) => {
          console.log('Salon fetched:', salon);
          setSalonId(salon.id);
          return Promise.all([
            apiClient.get(`/salons/${salonSlug}/services`),
            apiClient.get(`/salons/${salonSlug}/staff`),
          ]);
        })
        .then(([servicesData, staffData]) => {
          console.log('Services:', servicesData);
          console.log('Staff:', staffData);
          setServices(servicesData);
          setStaff(staffData);
        })
        .catch((err) => {
          console.error('Failed to fetch salon data:', err);
          setServices([]);
          setStaff([]);
        });
    } else {
      console.log('Skipping fetch - slug is empty:', salonSlug);
    }
  }, [salonSlug]);

  const handleServiceSelect = (service: Service) => {
    // Toggle service selection (allow multiple)
    const isSelected = selectedServices.some(s => s.id === service.id);
    if (isSelected) {
      setSelectedServices(selectedServices.filter(s => s.id !== service.id));
    } else {
      setSelectedServices([...selectedServices, service]);
    }
  };

  const handleContinueToStaff = () => {
    if (selectedServices.length > 0) {
      setStep('staff');
    }
  };

  const handleStaffSelect = (staffMember: StaffMember) => {
    setSelectedStaff(staffMember);
    setStep('time');
  };

  const handleSlotSelect = (slot: TimeSlot) => {
    setSelectedSlot(slot);
    setStep('details');
  };

  const handleDetailsSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (!customerDetails.name || !customerDetails.phone) {
      setError('Please fill in all required fields');
      return;
    }

    setStep('confirm');
  };

  const handleLookupCustomer = async () => {
    if (!customerDetails.phone) return;
    setIsLookingUp(true);
    setLookupMessage('');
    try {
      const res = await apiClient.lookupCustomer(customerDetails.phone, salonId);
      if (res.found && res.customer) {
        setCustomerDetails(prev => ({
          ...prev,
          name: res.customer.name || prev.name,
          email: res.customer.email || prev.email,
        }));
        setLookupMessage('Welcome back! We found your details.');
      } else {
        setLookupMessage('New customer? Please fill in your details.');
      }
    } catch (err) {
      // Ignore errors for lookup
    } finally {
      setIsLookingUp(false);
    }
  };

  const handleConfirmBooking = async () => {
    if (!selectedSlot || selectedServices.length === 0) {
      setError('Please select a service and time slot');
      return;
    }

    setIsLoading(true);
    setError('');

    try {
      if (createAccount) {
        if (accountDetails.password !== accountDetails.confirmPassword) {
          setError('Passwords do not match');
          setIsLoading(false);
          return;
        }
        if (accountDetails.password.length < 8) {
          setError('Password must be at least 8 characters');
          setIsLoading(false);
          return;
        }
      }

      const formattedDate = selectedDate.toISOString().split('T')[0];
      
      const result = await apiClient.createBookingWithAccount({
        salon_id: salonId,
        customer_name: customerDetails.name,
        customer_phone: customerDetails.phone,
        customer_email: customerDetails.email || undefined,
        service_id: selectedServices.map(s => s.id),
        staff_id: selectedStaff?.id,
        date: formattedDate,
        time: selectedSlot.start,
        create_account: createAccount,
        account_email: createAccount ? (accountDetails.email || customerDetails.email) : undefined,
        account_password: createAccount ? accountDetails.password : undefined,
      });

      setBookingResult(result);
      setStep('success');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to create booking. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  const stepColors = {
    service: '#FFD700',
    staff: '#a855f7',
    time: '#3b82f6',
    details: '#10b981',
    confirm: '#10b981',
    success: '#10b981',
  };

  return (
    <div className="min-h-screen bg-[#070707] text-white overflow-x-hidden font-poppins relative selection:bg-gold/30 selection:text-white pb-16">
      {/* Dynamic Cursor Glow */}
      {mounted && <CursorGlow color={stepColors[step] || '#FFD700'} intensity={0.35} radius={350} />}

      {/* Floating Ambient Background Blobs */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none z-0">
        <motion.div
          animate={{
            x: [0, 60, -30, 0],
            y: [0, -80, 40, 0],
            scale: [1, 1.15, 0.9, 1],
          }}
          transition={{ duration: 25, repeat: Infinity, ease: 'easeInOut' }}
          className="absolute w-[400px] h-[400px] bg-gold/5 rounded-full blur-[120px] -top-60 -left-20"
        />
        <motion.div
          animate={{
            x: [0, -80, 40, 0],
            y: [0, 60, -60, 0],
            scale: [1, 0.9, 1.1, 1],
          }}
          transition={{ duration: 28, repeat: Infinity, ease: 'easeInOut' }}
          className="absolute w-[500px] h-[500px] bg-purple-500/5 rounded-full blur-[140px] top-1/3 -right-60"
        />
      </div>

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
            onClick={() => setShowNearbySalons(!showNearbySalons)}
            className="flex items-center gap-2 px-3 py-2 bg-white/[0.05] border border-white/10 rounded-xl hover:border-gold/30 transition-all text-sm"
          >
            <MapPin className="w-4 h-4 text-gold" />
            <span className="text-white/80">Nearby Salons</span>
          </button>
        </div>
      </div>

      {/* Nearby Salons Map Modal */}
      {showNearbySalons && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
          onClick={() => setShowNearbySalons(false)}
        >
          <motion.div
            initial={{ scale: 0.95, y: 20 }}
            animate={{ scale: 1, y: 0 }}
            exit={{ scale: 0.95, y: 20 }}
            className="w-full max-w-6xl h-[80vh] bg-[#0c0c0c] rounded-2xl overflow-hidden border border-white/10"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="flex items-center justify-between p-4 border-b border-white/10">
              <div>
                <h2 className="text-xl font-bold text-white">Nearby Salons</h2>
                <p className="text-white/50 text-sm">Find alternative salons in your area</p>
              </div>
              <button
                onClick={() => setShowNearbySalons(false)}
                className="w-8 h-8 rounded-lg bg-white/[0.05] border border-white/10 flex items-center justify-center hover:border-gold/30 transition-all"
              >
                <span className="text-white/60">×</span>
              </button>
            </div>
            <div className="h-[calc(80vh-73px)]">
              <SalonDiscoveryMap />
            </div>
          </motion.div>
        </motion.div>
      )}

      {/* Progress Steps */}
      <div className="max-w-4xl mx-auto px-4 py-8 relative z-10">
        <div className="flex items-center justify-between mb-12 bg-white/[0.02] border border-white/5 rounded-2xl p-4 backdrop-blur-md">
          {['service', 'staff', 'time', 'details'].map((s, index) => {
            const stepIndex = ['service', 'staff', 'time', 'details'].indexOf(step);
            const isCompleted = stepIndex > index || step === 'confirm' || step === 'success';
            const isActive = step === s;
            const stepNames = {
              service: 'Treatment',
              staff: 'Stylist',
              time: 'Reserve Slot',
              details: 'Profile Details',
            };
            const stepSummaries = {
              service: selectedServices.length > 0 ? selectedServices.map(s => s.name).join(', ') : 'Select Treatment',
              staff: selectedStaff ? selectedStaff.name : 'Preferred Stylist',
              time: selectedSlot ? `${selectedDate.toLocaleDateString()} @ ${selectedSlot.start}` : 'Choose Time',
              details: customerDetails.name ? customerDetails.name : 'Your Info',
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
                      index + 1
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
                {index < 3 && (
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

        {/* Dynamic content rendering with step transitions */}
        <AnimatePresence mode="wait">
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

              {services.length === 0 ? (
                <div className="flex flex-col items-center justify-center p-12 bg-white/[0.02] border border-white/5 rounded-2xl">
                  <Loader2 className="w-8 h-8 text-gold animate-spin mb-4" />
                  <p className="text-white/45 text-sm">Fetching premium salon catalog...</p>
                </div>
              ) : (
                <>
                  <div className="flex overflow-x-auto pb-4 snap-x snap-mandatory hide-scrollbar -mx-4 px-4 md:mx-0 md:px-0 md:grid md:grid-cols-2 md:overflow-visible flex-nowrap space-x-4 md:space-x-0 gap-0 md:gap-4">
                    {services.map((service) => {
                      const isSelected = selectedServices.some(s => s.id === service.id);
                      return (
                        <motion.button
                          key={service.id}
                          whileHover={{ scale: 1.01, borderColor: 'rgba(255, 215, 0, 0.4)', backgroundColor: 'rgba(255, 255, 255, 0.04)' }}
                          whileTap={{ scale: 0.99 }}
                          onClick={() => handleServiceSelect(service)}
                          className={`min-w-[85vw] snap-center md:min-w-0 p-6 rounded-2xl border text-left transition-all duration-300 backdrop-blur-md cursor-pointer relative overflow-hidden flex flex-col justify-between h-36 shrink-0 md:shrink ${
                            isSelected
                              ? 'border-gold bg-gold/10 shadow-lg'
                              : 'border-white/5 bg-white/[0.02]'
                          }`}
                        >
                          <div className="absolute top-3 right-3">
                            <div className={`w-5 h-5 rounded-full border-2 flex items-center justify-center ${
                              isSelected ? 'border-gold bg-gold' : 'border-white/30'
                            }`}>
                              {isSelected && (
                                <div className="w-2.5 h-2.5 rounded-full bg-black" />
                              )}
                            </div>
                          </div>
                          <div>
                            <div className="flex justify-between items-start gap-4 mb-2">
                              <h3 className="text-white font-sora font-semibold text-base tracking-tight leading-snug">{service.name}</h3>
                              <span className="text-gold font-sora font-bold text-sm tracking-wide whitespace-nowrap bg-gold/10 px-2.5 py-0.5 rounded-lg border border-gold/20">
                                {service.price.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })} UGX
                              </span>
                            </div>
                            <span className="text-[10px] uppercase font-bold tracking-widest font-mono text-white/30">
                              {service.category}
                            </span>
                          </div>
                          
                          <div className="flex items-center gap-1.5 text-white/50 text-xs mt-4">
                            <Clock className="w-3.5 h-3.5 text-gold" />
                            <span>{service.duration} min duration</span>
                          </div>
                        </motion.button>
                      );
                    })}
                  </div>

                  {selectedServices.length > 0 && (
                    <div className="flex justify-between items-center pt-4">
                      <div className="text-white/50 text-sm">
                        {selectedServices.length} service{selectedServices.length > 1 ? 's' : ''} selected
                      </div>
                      <button
                        onClick={handleContinueToStaff}
                        className="px-8 py-3 bg-gradient-to-r from-gold to-[#C9A227] hover:brightness-110 active:scale-98 transition-all text-black font-semibold rounded-xl text-sm shadow-lg shadow-gold/10 flex items-center justify-center gap-2 cursor-pointer"
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
                <p className="text-white/50 text-sm">Select your preferred stylist or select next to assign automatically</p>
              </div>

              {staff.length === 0 ? (
                <div className="flex flex-col items-center justify-center p-12 bg-white/[0.02] border border-white/5 rounded-2xl">
                  <Loader2 className="w-8 h-8 text-gold animate-spin mb-4" />
                  <p className="text-white/45 text-sm">Loading styling team roster...</p>
                </div>
              ) : (
                <div className="flex overflow-x-auto pb-4 snap-x snap-mandatory hide-scrollbar -mx-4 px-4 md:mx-0 md:px-0 sm:grid sm:grid-cols-2 md:grid-cols-3 sm:overflow-visible flex-nowrap space-x-4 sm:space-x-0 mb-8 gap-0 sm:gap-4">
                  {staff.map((staffMember) => {
                    const stylistThemes: Record<string, { gradient: string, border: string, text: string }> = {
                      julian: { gradient: 'from-blue-500/20 to-cyan-500/10', border: 'border-blue-500/20', text: 'text-blue-300' },
                      sophia: { gradient: 'from-purple-500/20 to-pink-500/10', border: 'border-purple-500/20', text: 'text-purple-300' },
                      emma: { gradient: 'from-emerald-500/20 to-teal-500/10', border: 'border-emerald-500/20', text: 'text-emerald-300' }
                    };
                    const nameKey = staffMember.name.toLowerCase();
                    const stylistTheme = stylistThemes[nameKey] || { gradient: 'from-gold/20 to-amber-500/10', border: 'border-gold/20', text: 'text-gold' };
                    const initial = staffMember.name.split(' ').map(n => n[0]).join('');

                    return (
                      <motion.button
                        key={staffMember.id}
                        whileHover={{ scale: 1.01, borderColor: 'rgba(255, 215, 0, 0.4)', backgroundColor: 'rgba(255, 255, 255, 0.04)' }}
                        whileTap={{ scale: 0.99 }}
                        onClick={() => handleStaffSelect(staffMember)}
                        className={`min-w-[70vw] snap-center sm:min-w-0 shrink-0 sm:shrink p-6 rounded-2xl border text-center transition-all duration-300 backdrop-blur-md cursor-pointer flex flex-col items-center justify-between h-48 ${
                          selectedStaff?.id === staffMember.id
                            ? 'border-gold bg-gold/10 shadow-lg'
                            : 'border-white/5 bg-white/[0.02]'
                        }`}
                      >
                        <div className="flex flex-col items-center">
                          <div className={`w-14 h-14 rounded-full bg-gradient-to-tr ${stylistTheme.gradient} ${stylistTheme.border} ${stylistTheme.text} border flex items-center justify-center text-sm font-bold shadow-inner mb-4`}>
                            {initial}
                          </div>
                          <h3 className="text-white font-sora font-semibold text-base mb-1">{staffMember.name}</h3>
                          <span className="text-white/45 text-xs font-mono uppercase tracking-wider">{staffMember.role}</span>
                        </div>
                        
                        <div className="flex items-center gap-1 mt-3">
                          <Star className="w-3.5 h-3.5 text-gold fill-gold" />
                          <span className="text-[10px] text-[#FFD700] font-semibold">4.95 Rating</span>
                        </div>
                      </motion.button>
                    );
                  })}
                </div>
              )}

              <div className="flex justify-between items-center mt-6">
                <button
                  onClick={() => setStep('service')}
                  className="text-white/50 hover:text-white text-xs font-mono font-medium transition-colors"
                >
                  ← Back to treatments
                </button>
                <button
                  onClick={() => {
                    setSelectedStaff(null);
                    setStep('time');
                  }}
                  className="px-5 py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl text-white text-xs font-mono font-semibold transition-all cursor-pointer"
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

              <AvailabilityCalendar
                salonId={salonId}
                serviceId={selectedServices[0]?.id}
                specialistId={selectedStaff?.id}
                onSlotSelect={handleSlotSelect}
                selectedSlot={selectedSlot}
              />

              <div className="flex justify-between items-center mt-6">
                <button
                  onClick={() => setStep('staff')}
                  className="text-white/50 hover:text-white text-xs font-mono font-medium transition-colors"
                >
                  ← Back to stylists
                </button>
              </div>
            </motion.div>
          )}

          {step === 'details' && (
            <motion.div
              key="details-step"
              initial={{ opacity: 0, y: "100%" }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: "100%" }}
              transition={{ type: 'spring', stiffness: 300, damping: 30 }}
            >
              <div className="fixed inset-x-0 bottom-0 z-50 bg-[#0c0c0c] border-t border-white/10 rounded-t-3xl p-6 pt-8 h-[90vh] overflow-y-auto custom-scrollbar md:static md:bg-transparent md:border-none md:p-0 md:h-auto md:overflow-visible md:rounded-none">
                <div className="w-12 h-1.5 bg-white/20 rounded-full mx-auto mb-6 md:hidden" />
                <div className="text-left mb-8">
                  <h2 className="font-sora text-2xl md:text-3xl font-extrabold tracking-tight text-white mb-2">Your Details</h2>
                  <p className="text-white/50 text-sm">Please input your contact information to finalize the booking</p>
                </div>

              <form onSubmit={handleDetailsSubmit} className="space-y-6 text-left max-w-2xl mx-auto">
                <div className="bg-white/[0.02] border border-white/5 rounded-2xl p-6 space-y-4 backdrop-blur-md">
                  <div>
                    <label className="block text-[10px] font-bold font-mono tracking-wider text-white/40 uppercase mb-2">PHONE NUMBER *</label>
                    <div className="flex gap-2">
                      <input
                        type="tel"
                        value={customerDetails.phone}
                        onChange={(e) => setCustomerDetails(prev => ({ ...prev, phone: e.target.value }))}
                        className="flex-1 px-4 py-3 bg-black/40 border border-white/5 rounded-xl text-white placeholder:text-white/20 focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-colors text-sm"
                        placeholder="Enter phone number"
                        required
                      />
                      <button
                        type="button"
                        onClick={handleLookupCustomer}
                        disabled={!customerDetails.phone || isLookingUp}
                        className="px-4 py-3 bg-white/[0.05] hover:bg-white/[0.1] disabled:opacity-50 border border-white/10 rounded-xl text-white text-xs font-semibold transition-colors flex items-center justify-center min-w-[80px]"
                      >
                        {isLookingUp ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Lookup'}
                      </button>
                    </div>
                    {lookupMessage && <p className="text-gold text-xs mt-2">{lookupMessage}</p>}
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-[10px] font-bold font-mono tracking-wider text-white/40 uppercase mb-2">FULL NAME *</label>
                      <input
                        type="text"
                        value={customerDetails.name}
                        onChange={(e) => setCustomerDetails(prev => ({ ...prev, name: e.target.value }))}
                        className="w-full px-4 py-3 bg-black/40 border border-white/5 rounded-xl text-white placeholder:text-white/20 focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-colors text-sm"
                        placeholder="Enter your name"
                        required
                      />
                    </div>

                    <div>
                      <label className="block text-[10px] font-bold font-mono tracking-wider text-white/40 uppercase mb-2">EMAIL ADDRESS</label>
                      <input
                        type="email"
                        value={customerDetails.email}
                        onChange={(e) => setCustomerDetails(prev => ({ ...prev, email: e.target.value }))}
                        className="w-full px-4 py-3 bg-black/40 border border-white/5 rounded-xl text-white placeholder:text-white/20 focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-colors text-sm"
                        placeholder="Enter your email"
                      />
                    </div>
                  </div>
                </div>

                {/* Create Account Option glass container */}
                <div className="bg-white/[0.02] border border-white/5 rounded-2xl p-5 backdrop-blur-md">
                  <label className="flex items-start gap-4 cursor-pointer select-none">
                    <input
                      type="checkbox"
                      checked={createAccount}
                      onChange={(e) => setCreateAccount(e.target.checked)}
                      className="w-5 h-5 rounded border-white/10 text-gold focus:ring-gold focus:ring-offset-black bg-black/40 mt-1 cursor-pointer"
                    />
                    <div className="flex flex-col gap-0.5">
                      <span className="text-white font-semibold text-sm font-sora">Create a Client Portal Account</span>
                      <p className="text-white/40 text-xs leading-normal">Construct an account workspace to manage booking splits, track payment wallets, and view history.</p>
                    </div>
                  </label>

                  {createAccount && (
                    <motion.div 
                      initial={{ height: 0, opacity: 0 }}
                      animate={{ height: 'auto', opacity: 1 }}
                      transition={{ type: 'spring', stiffness: 200, damping: 20 }}
                      className="space-y-4 pt-5 mt-5 border-t border-white/5"
                    >
                      <div>
                        <label className="block text-[9px] font-bold font-mono tracking-wider text-white/40 uppercase mb-2">ACCOUNT EMAIL *</label>
                        <input
                          type="email"
                          value={accountDetails.email}
                          onChange={(e) => setAccountDetails(prev => ({ ...prev, email: e.target.value }))}
                          className="w-full px-4 py-3 bg-black/40 border border-white/5 rounded-xl text-white placeholder:text-white/20 focus:outline-none focus:border-gold transition-colors text-sm"
                          placeholder="Enter your account email"
                          required={createAccount}
                        />
                      </div>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                          <label className="block text-[9px] font-bold font-mono tracking-wider text-white/40 uppercase mb-2">PASSWORD *</label>
                          <input
                            type="password"
                            value={accountDetails.password}
                            onChange={(e) => setAccountDetails(prev => ({ ...prev, password: e.target.value }))}
                            className="w-full px-4 py-3 bg-black/40 border border-white/5 rounded-xl text-white placeholder:text-white/20 focus:outline-none focus:border-gold transition-colors text-sm"
                            placeholder="Min 8 characters"
                            required={createAccount}
                          />
                        </div>
                        <div>
                          <label className="block text-[9px] font-bold font-mono tracking-wider text-white/40 uppercase mb-2">CONFIRM PASSWORD *</label>
                          <input
                            type="password"
                            value={accountDetails.confirmPassword}
                            onChange={(e) => setAccountDetails(prev => ({ ...prev, confirmPassword: e.target.value }))}
                            className="w-full px-4 py-3 bg-black/40 border border-white/5 rounded-xl text-white placeholder:text-white/20 focus:outline-none focus:border-gold transition-colors text-sm"
                            placeholder="Confirm your password"
                            required={createAccount}
                          />
                        </div>
                      </div>
                    </motion.div>
                  )}
                </div>

                {error && (
                  <div className="p-4 bg-red-500/10 border border-red-500/30 rounded-2xl text-red-400 text-xs font-mono">
                    {error}
                  </div>
                )}

                <div className="flex justify-between items-center pt-4">
                  <button
                    type="button"
                    onClick={() => setStep('time')}
                    className="text-white/50 hover:text-white text-xs font-mono font-medium transition-colors cursor-pointer"
                  >
                    ← Back to scheduling
                  </button>
                  <button
                    type="submit"
                    className="w-full md:w-auto px-8 py-3.5 bg-gradient-to-r from-gold to-[#C9A227] hover:brightness-110 active:scale-98 transition-all text-black font-semibold rounded-xl text-sm shadow-lg shadow-gold/10 flex items-center justify-center gap-2 cursor-pointer"
                  >
                    Continue
                    <ArrowRight className="w-4 h-4" />
                  </button>
                </div>
              </form>
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
              <div className="fixed inset-x-0 bottom-0 z-50 bg-[#0c0c0c] border-t border-white/10 rounded-t-3xl p-6 pt-8 h-[90vh] overflow-y-auto custom-scrollbar md:static md:bg-transparent md:border-none md:p-0 md:h-auto md:overflow-visible md:rounded-none flex flex-col justify-between">
                <div>
                  <div className="w-12 h-1.5 bg-white/20 rounded-full mx-auto mb-6 md:hidden" />
                  <div className="text-left mb-8">
                    <h2 className="font-sora text-2xl md:text-3xl font-extrabold tracking-tight text-white mb-2">Confirm Booking</h2>
                    <p className="text-white/50 text-sm">Please review the invoice breakdown and deposit protection escrow details</p>
                  </div>

              <div className="max-w-2xl mx-auto flex flex-col gap-6">
                <div className="bg-white/[0.02] border border-white/5 rounded-3xl p-6 backdrop-blur-md text-left shadow-xl">
                  <span className="text-[9px] font-bold font-mono tracking-widest text-white/35 uppercase border-b border-white/5 pb-3 block">APPOINTMENT SUMMARY RECEIPT</span>
                  
                  <div className="space-y-4 mt-4 text-sm">
                    {selectedServices.map((service, index) => (
                      <div key={service.id} className="flex justify-between items-center py-2.5 border-b border-white/5">
                        <span className="text-white/50">{index + 1}. {service.name}</span>
                        <span className="text-white font-semibold font-sora">{service.price.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })} UGX</span>
                      </div>
                    ))}
                    {selectedStaff && (
                      <div className="flex justify-between items-center py-2.5 border-b border-white/5">
                        <span className="text-white/50">Stylist Assigned</span>
                        <span className="text-white font-semibold font-sora">{selectedStaff.name}</span>
                      </div>
                    )}
                    <div className="flex justify-between items-center py-2.5 border-b border-white/5">
                      <span className="text-white/50">Scheduled Date</span>
                      <span className="text-white font-mono font-medium">{selectedDate.toLocaleDateString()}</span>
                    </div>
                    <div className="flex justify-between items-center py-2.5 border-b border-white/5">
                      <span className="text-white/50">Reservation Time</span>
                      <span className="text-white font-mono font-medium">{selectedSlot?.start || 'Not selected'}</span>
                    </div>
                    <div className="flex justify-between items-center py-2.5 border-b border-white/5">
                      <span className="text-white/50">Total Duration</span>
                      <span className="text-white font-mono font-medium">{selectedServices.reduce((sum, s) => sum + s.duration, 0)} minutes</span>
                    </div>
                    
                    {/* Invoice Split total */}
                    <div className="flex justify-between items-center pt-4">
                      <span className="text-white/50 font-medium">Grand Total</span>
                      <span className="text-gold font-sora font-extrabold text-2xl bg-gold/10 px-3 py-1 rounded-xl border border-gold/20 shadow-md">
                        {selectedServices.reduce((sum, s) => sum + s.price, 0).toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })} UGX
                      </span>
                    </div>
                  </div>
                </div>

                {error && (
                  <div className="p-4 bg-red-500/10 border border-red-500/30 rounded-2xl text-red-400 text-xs font-mono text-left">
                    {error}
                  </div>
                )}

                <div className="flex justify-between items-center pt-2 sticky bottom-0 bg-[#0c0c0c] pb-4 pt-4 border-t border-white/5 md:border-none md:bg-transparent md:pb-0 md:pt-2">
                  <button
                    onClick={() => setStep('details')}
                    disabled={isLoading}
                    className="text-white/50 hover:text-white text-xs font-mono font-medium transition-colors cursor-pointer hidden md:block"
                  >
                    ← Back to details
                  </button>
                  
                  <button
                    onClick={handleConfirmBooking}
                    disabled={isLoading}
                    className="w-full md:w-auto px-8 py-3.5 bg-gradient-to-r from-gold to-[#C9A227] hover:brightness-110 disabled:opacity-50 disabled:cursor-not-allowed transition-all text-black font-semibold rounded-xl text-sm shadow-lg shadow-gold/20 flex items-center justify-center gap-2 cursor-pointer"
                  >
                    {isLoading ? (
                      <>
                        <Loader2 className="w-4 h-4 animate-spin" />
                        Processing...
                      </>
                    ) : (
                      <>
                        Confirm & Reserve Spot
                        <ArrowRight className="w-4 h-4" />
                      </>
                    )}
                  </button>
                </div>
              </div>
              </div>
              </div>
            </motion.div>
          )}

          {step === 'success' && (
            <motion.div
              key="success-step"
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ type: 'spring', stiffness: 200, damping: 20 }}
              className="text-center py-16 max-w-md mx-auto"
            >
              <div className="w-20 h-20 rounded-full bg-gradient-to-br from-gold to-[#C9A227] flex items-center justify-center mx-auto mb-6 shadow-xl shadow-gold/10 border border-gold/20">
                <CheckCircle2 className="w-10 h-10 text-black" />
              </div>
              <h2 className="font-sora text-3xl font-extrabold text-white mb-2 tracking-tight">Booking Confirmed!</h2>
              <p className="text-white/50 text-sm leading-relaxed mb-8">
                Your luxury treatment slot is reserved. An email confirmation has been dispatched.
              </p>

              <div className="bg-white/[0.02] border border-white/5 rounded-2xl p-6 mb-8 text-left shadow-lg backdrop-blur-md text-sm space-y-3.5">
                <div className="flex justify-between items-center py-2 border-b border-white/5">
                  <span className="text-white/50">Treatment</span>
                  <span className="text-white font-semibold font-sora text-right">{selectedServices.length > 0 ? selectedServices.map(s => s.name).join(', ') : 'Not selected'}</span>
                </div>
                <div className="flex justify-between items-center py-2 border-b border-white/5">
                  <span className="text-white/50">Date</span>
                  <span className="text-white font-mono">{selectedDate.toLocaleDateString()}</span>
                </div>
                <div className="flex justify-between items-center py-2">
                  <span className="text-white/50">Time</span>
                  <span className="text-white font-mono">{selectedSlot?.start || 'Not selected'}</span>
                </div>
              </div>
              
              {bookingResult?.portal_account ? (
                <div className="space-y-4">
                  <p className="text-white/50 text-xs leading-normal">
                    Your Client Portal workspace is ready! Auto-authenticating credentials...
                  </p>
                  <button
                    onClick={async () => {
                      try {
                        await login(accountDetails.email || customerDetails.email, accountDetails.password);
                        router.push('/portal/home');
                      } catch (err) {
                        setError('Failed to auto-login. Please login manually.');
                      }
                    }}
                    className="w-full py-3 bg-gradient-to-r from-gold to-[#C9A227] hover:brightness-110 active:scale-98 transition-all text-black rounded-xl font-semibold shadow-lg shadow-gold/10 flex items-center justify-center gap-2 cursor-pointer"
                  >
                    Go to Portal Dashboard
                    <ArrowRight className="w-5 h-5" />
                  </button>
                </div>
              ) : (
                <div className="bg-gradient-to-br from-gold/10 to-amber-500/5 border border-gold/20 rounded-2xl p-6 text-left shadow-lg mb-8">
                  <div className="flex gap-4 items-start mb-4">
                    <Crown className="w-8 h-8 text-gold shrink-0" />
                    <div>
                      <h3 className="text-white font-sora font-bold text-lg mb-1">Upgrade to Premium Status</h3>
                      <p className="text-white/60 text-xs leading-relaxed">
                        Create a Client Portal workspace to manage booking splits, track payment wallets, and unlock exclusive VIP loyalty rewards.
                      </p>
                    </div>
                  </div>
                  <button 
                    onClick={() => router.push('/login?signup=true')}
                    className="w-full py-3 bg-white/5 hover:bg-white/10 border border-gold/30 hover:border-gold/50 transition-all text-gold rounded-xl font-semibold shadow-inner flex items-center justify-center gap-2 cursor-pointer"
                  >
                    Create Account Now <ArrowRight className="w-4 h-4" />
                  </button>
                  <button
                    onClick={() => router.push('/')}
                    className="w-full py-2 text-white/40 hover:text-white transition-colors text-xs font-mono mt-2 cursor-pointer"
                  >
                    Return to home
                  </button>
                </div>
              )}
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </div>
  );
}

export default function BookPage({ params }: { params: Promise<{ slug: string }> }) {
  const [slug, setSlug] = useState<string>('');

  useEffect(() => {
    params.then(resolved => setSlug(resolved.slug));
  }, [params]);

  if (!slug || slug.length === 0) {
    return <div className="min-h-screen flex items-center justify-center bg-[#070707]"><Loader2 className="w-8 h-8 text-[#FFD700] animate-spin" /></div>;
  }

  return <BookPageContent slug={slug} />;
}
