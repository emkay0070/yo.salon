'use client';

import { useState, useEffect, useMemo } from 'react';
import { motion } from 'framer-motion';
import { useRouter, useSearchParams } from 'next/navigation';
import DashboardLayout from '@/components/DashboardLayout';
import BookingSteps from '@/components/booking/BookingSteps';
import CalendarPicker from '@/components/booking/CalendarPicker';
import TimePicker from '@/components/booking/TimePicker';
import BookingSummary from '@/components/booking/BookingSummary';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useRole } from '@/contexts/RoleContext';
import { apiClient } from '@/lib/api-client';

interface Service {
  id: string;
  name: string;
  price: number;
  duration: number;
}

export interface Stylist {
  id: string;
  name: string;
  specializations?: string[];
  image?: string;
  available: boolean;
  nextAvailable?: string;
  queuePosition?: number;
}

export default function BookingPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryClient = useQueryClient();
  const { salonId } = useRole();
  const [currentStep, setCurrentStep] = useState(0);
  const [selectedService, setSelectedService] = useState<Service | undefined>();
  const [selectedStylist, setSelectedStylist] = useState<Stylist | undefined>();
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [selectedTime, setSelectedTime] = useState<string>('');
  const [queuePosition, setQueuePosition] = useState<number | null>(null);
  const [estimatedWaitTime, setEstimatedWaitTime] = useState<string>('');

  const { data: servicesData = [], isLoading: servicesLoading } = useQuery({
    queryKey: ['services', salonId],
    queryFn: () => apiClient.getServices({ salon_id: salonId }),
    enabled: !!salonId,
  });

  const { data: staffData = [], isLoading: staffLoading } = useQuery({
    queryKey: ['staff', salonId],
    queryFn: () => apiClient.getStaff({ salon_id: salonId }),
    enabled: !!salonId,
  });

  const services: Service[] = useMemo(() => (servicesData as any[]).map((s: any) => ({
    id: s.id,
    name: s.name,
    price: s.price,
    duration: s.duration,
  })), [servicesData]);

  const stylists: Stylist[] = useMemo(() => (staffData as any[]).map((s: any) => ({
    id: s.id,
    name: s.name,
    specializations: s.specializations || [],
    available: s.active !== false && s.status !== 'off' && s.status !== 'leave',
    nextAvailable: s.status === 'busy' ? 'Soon' : 'Now',
  })), [staffData]);

  // Preselect stylist if staffId is in search params
  useEffect(() => {
    const staffId = searchParams.get('staffId');
    if (staffId && staffData.length > 0) {
      const stylist = stylists.find((s) => s.id === staffId);
      if (stylist && selectedStylist?.id !== stylist.id) {
        setSelectedStylist(stylist);
        // If we have a stylist preselected, move to step 1 (select stylist, which is already selected)
        if (currentStep < 1) {
          setCurrentStep(1);
        }
      }
    }
  }, [searchParams, staffData, currentStep, stylists, selectedStylist]);

  const handleStepClick = (step: number) => {
    if (step <= currentStep + 1) {
      setCurrentStep(step);
    }
  };

  const handleContinue = () => {
    if (currentStep < 3) {
      if (currentStep === 1 && selectedStylist && !selectedStylist.available) {
        setQueuePosition(selectedStylist.queuePosition || null);
        setEstimatedWaitTime(selectedStylist.nextAvailable || '');
      }
      setCurrentStep(currentStep + 1);
    }
  };

  const renderStepContent = () => {
    switch (currentStep) {
      case 0:
        return (
          <div className="space-y-3">
            <h3 className="text-base lg:text-lg font-semibold text-text-primary">Select a Service</h3>
            {servicesLoading ? (
              <div className="space-y-3">
                {[1,2,3].map(i => (
                  <div key={i} className="w-full h-16 rounded-xl bg-card border border-border-medium animate-pulse" />
                ))}
              </div>
            ) : services.length === 0 ? (
              <div className="text-center py-10 text-text-secondary">
                <p className="text-sm">No services available yet. Add services in the Services module first.</p>
              </div>
            ) : (
              services.map((service) => (
                <button
                  key={service.id}
                  onClick={() => setSelectedService(service)}
                  className={`w-full p-3 lg:p-4 rounded-xl border-2 transition-all ${
                    selectedService?.id === service.id
                      ? 'border-[#FFD700] bg-[#FFD700]/10'
                      : 'border-border-light bg-card hover:bg-white/10'
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <div className="flex-1 min-w-0">
                      <p className="text-text-primary font-semibold text-left text-sm lg:text-base truncate">{service.name}</p>
                      <p className="text-text-secondary text-xs lg:text-sm mt-1 text-left">{service.duration} minutes</p>
                    </div>
                    <span className="text-gold font-bold text-sm lg:text-lg ml-2 flex-shrink-0">UGX {service.price.toLocaleString()}</span>
                  </div>
                </button>
              ))
            )}
          </div>
        );

      case 1:
        return (
          <div className="space-y-3">
            <h3 className="text-base lg:text-lg font-semibold text-text-primary">Select a Stylist</h3>
            {staffLoading ? (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {[1,2].map(i => (
                  <div key={i} className="h-24 rounded-xl bg-card border border-border-medium animate-pulse" />
                ))}
              </div>
            ) : stylists.length === 0 ? (
              <div className="text-center py-10 text-text-secondary">
                <p className="text-sm">No staff members yet. Add staff in the Team module first.</p>
              </div>
            ) : (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {stylists.map((stylist) => (
                  <button
                    key={stylist.id}
                    onClick={() => setSelectedStylist(stylist)}
                    className={`p-3 lg:p-4 rounded-xl border-2 transition-all ${
                      selectedStylist?.id === stylist.id
                        ? 'border-[#FFD700] bg-[#FFD700]/10'
                        : 'border-border-light bg-card hover:bg-white/10'
                    }`}
                  >
                    <div className="flex items-start gap-3">
                      <div className="relative flex-shrink-0">
                        <div className="w-10 h-10 lg:w-12 lg:h-12 rounded-full bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center text-black font-bold text-sm lg:text-lg">
                          {stylist.name.charAt(0)}
                        </div>
                        <div className={`absolute -bottom-1 -right-1 w-3 h-3 lg:w-4 lg:h-4 rounded-full border-2 border-[#1A1A1A] ${
                          stylist.available ? 'bg-green-500' : 'bg-yellow-500'
                        }`} />
                      </div>
                      <div className="flex-1 text-left min-w-0">
                        <p className="text-text-primary font-semibold text-sm lg:text-base truncate">{stylist.name}</p>
                        <p className="text-text-secondary text-xs lg:text-sm mt-1 truncate">
                          {stylist.specializations?.join(', ') || 'All services'}
                        </p>
                        <div className="mt-1 lg:mt-2">
                          {stylist.available ? (
                            <span className="text-green-400 text-xs font-medium">Available Now</span>
                          ) : (
                            <span className="text-yellow-400 text-xs font-medium">
                              Next: {stylist.nextAvailable}
                            </span>
                          )}
                        </div>
                      </div>
                    </div>
                  </button>
                ))}
              </div>
            )}
          </div>
        );

      case 2:
        return (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
            <CalendarPicker selectedDate={selectedDate} onDateSelect={setSelectedDate} />
            <TimePicker
              selectedTime={selectedTime}
              onTimeSelect={setSelectedTime}
              selectedStylist={selectedStylist}
            />
          </div>
        );

      case 3:
        return (
          <div className="space-y-3">
            <h3 className="text-base lg:text-lg font-semibold text-text-primary">Confirm Your Details</h3>

            {queuePosition && (
              <div className="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-3 lg:p-4">
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 lg:w-10 lg:h-10 rounded-full bg-yellow-500/20 flex items-center justify-center flex-shrink-0">
                    <span className="text-yellow-400 font-bold text-sm lg:text-base">{queuePosition}</span>
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-yellow-400 font-semibold text-sm lg:text-base">You're in Queue</p>
                    <p className="text-text-secondary text-xs lg:text-sm">
                      Estimated wait time: {estimatedWaitTime}
                    </p>
                  </div>
                </div>
                <button
                  onClick={handleReschedule}
                  className="mt-3 w-full py-2 lg:py-2.5 rounded-lg bg-yellow-500/20 text-yellow-400 font-medium hover:bg-yellow-500/30 transition-colors text-sm lg:text-base"
                >
                  Reschedule to Earlier Time
                </button>
              </div>
            )}

            <div className="bg-card border border-border-light rounded-xl p-3 lg:p-4">
              <p className="text-text-secondary text-xs lg:text-sm">Your booking is ready to be confirmed.</p>
              <p className="text-text-primary mt-2 text-sm lg:text-base">Please review the summary below and click confirm to complete your booking.</p>
            </div>
          </div>
        );

      default:
        return null;
    }
  };

  const handleReschedule = () => {
    setCurrentStep(2);
  };

  const createBookingMutation = useMutation({
    mutationFn: async (data: any) => {
      return await apiClient.createBooking(data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings', salonId] });
      router.push('/bookings');
    },
    onError: (error) => {
      console.error('Failed to create booking:', error);
      alert('Failed to create booking. Please try again.');
    },
  });

  const handleConfirmBooking = () => {
    if (!selectedService || !selectedStylist || !selectedDate || !selectedTime || !salonId) {
      return;
    }

    // Combine date and time into a proper datetime string
    const bookingDateTime = new Date(selectedDate);
    const [hours, minutes] = selectedTime.split(':').map(Number);
    bookingDateTime.setHours(hours, minutes, 0, 0);

    createBookingMutation.mutate({
      salon_id: salonId,
      service_id: selectedService.id,
      staff_id: selectedStylist.id,
      date: bookingDateTime.toISOString(),
      status: 'confirmed',
      notes: queuePosition ? `Queue position: ${queuePosition}` : undefined,
    });
  };

  return (
    <DashboardLayout>
      <div className="h-full font-sans overflow-x-hidden">
        <div className="mb-4 lg:mb-8">
          <h1 className="font-serif text-2xl lg:text-3xl font-bold text-text-primary tracking-tight">Book Your Appointment</h1>
          <p className="text-text-secondary mt-2 text-sm lg:text-base">Follow the steps to schedule your visit</p>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-2 sm:gap-3 lg:gap-6 max-w-full">
          {/* Left Column */}
          <div className="lg:col-span-2 space-y-3 lg:space-y-6">
            <BookingSteps currentStep={currentStep} onStepClick={handleStepClick} />

            <motion.div
              key={currentStep}
              initial={{ opacity: 0, x: 20 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.3 }}
              className="bg-card border border-border-light rounded-2xl p-3 sm:p-4 lg:p-6 backdrop-blur-2xl"
            >
              {renderStepContent()}
            </motion.div>

            <div className="flex justify-between gap-2 sm:gap-3">
              <button
                onClick={() => setCurrentStep(Math.max(0, currentStep - 1))}
                disabled={currentStep === 0}
                className="flex-1 sm:flex-none px-3 sm:px-4 lg:px-6 py-3 rounded-xl font-medium transition-all disabled:opacity-50 disabled:cursor-not-allowed bg-white/10 text-text-primary hover:bg-white/20 text-sm lg:text-base"
              >
                Back
              </button>
              <button
                onClick={currentStep === 3 ? handleConfirmBooking : handleContinue}
                disabled={
                  (currentStep === 0 && !selectedService) ||
                  (currentStep === 1 && !selectedStylist) ||
                  (currentStep === 2 && (!selectedDate || !selectedTime))
                }
                className="flex-1 sm:flex-none px-3 sm:px-4 lg:px-6 py-3 rounded-xl font-medium transition-all disabled:opacity-50 disabled:cursor-not-allowed bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-obsidian hover:opacity-90 text-sm lg:text-base"
              >
                {currentStep === 3 ? 'Confirm Booking' : 'Continue'}
              </button>
            </div>
          </div>

          {/* Right Column */}
          <div className="space-y-3 lg:space-y-6 order-last lg:order-last">
            <BookingSummary
              service={selectedService}
              stylist={selectedStylist}
              date={selectedDate}
              time={selectedTime}
            />

            <div className="bg-card border border-border-light rounded-2xl p-4 lg:p-6 backdrop-blur-2xl">
              <h3 className="text-lg font-semibold text-text-primary mb-4">Need Help?</h3>
              <p className="text-text-secondary text-sm">
                If you have any questions about our services or need assistance with your booking, please don't hesitate to contact us.
              </p>
              <button
                onClick={() => window.open('mailto:support@yosalon.com', '_blank')}
                className="mt-4 w-full py-3 rounded-xl font-medium bg-white/10 text-text-primary hover:bg-white/20 transition-all text-sm"
              >
                Contact Support
              </button>
            </div>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}
