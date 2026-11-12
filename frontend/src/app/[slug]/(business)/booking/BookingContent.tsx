'use client';

import { useState, useEffect, useMemo } from 'react';
import { motion } from 'framer-motion';
import { useRouter, useParams } from 'next/navigation';
import { Calendar } from 'lucide-react';
import DashboardLayout from '@/components/DashboardLayout';
import BookingSteps from '@/components/booking/BookingSteps';
import CalendarPicker from '@/components/booking/CalendarPicker';
import TimePicker from '@/components/booking/TimePicker';
import BookingSummary from '@/components/booking/BookingSummary';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useRole } from '@/contexts/RoleContext';
import { useCategory } from '@/contexts/CategoryContext';
import { apiClient } from '@/lib/api-client';
import { providerRoutes } from '@/lib/routes';
import { formatDateKey } from '@/lib/timezone';

interface Service {
  id: string;
  name: string;
  price: number;
  duration: number;
}

export interface Stylist {
  id: string;
  specialist_id?: string;
  name: string;
  specializations?: string[];
  image?: string;
  available: boolean;
  nextAvailable?: string;
  queuePosition?: number;
}

export default function ProviderBookingContent() {
  const router = useRouter();
  const params = useParams();
  const queryClient = useQueryClient();
  const { providerId: authenticatedProviderId, providerSlug, locationId } = useRole();
  const { terminology } = useCategory();
  const routes = providerRoutes(params.slug as string);

  // Use the provider from the authenticated context
  const providerId = authenticatedProviderId;

  const [currentStep, setCurrentStep] = useState(0);
  const [selectedCustomer, setSelectedCustomer] = useState<{ id: string; name: string; phone: string; email: string } | null>(null);
  const [selectedService, setSelectedService] = useState<Service | undefined>();
  const [selectedStylist, setSelectedStylist] = useState<Stylist | undefined>();
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [selectedTime, setSelectedTime] = useState<string>('');
  const [queuePosition, setQueuePosition] = useState<number | null>(null);
  const [estimatedWaitTime, setEstimatedWaitTime] = useState<string>('');

  const { data: servicesData = [], isLoading: servicesLoading } = useQuery({
    queryKey: ['services', providerId],
    queryFn: () => apiClient.getServices({ provider_id: providerId! }),
    enabled: !!providerId && currentStep >= 1,
  });

  const { data: customersData = [], isLoading: customersLoading } = useQuery({
    queryKey: ['customers', providerId],
    queryFn: () => apiClient.getCustomers({}),
    enabled: !!providerId && currentStep >= 0,
  });

  const { data: staffData = [], isLoading: staffLoading } = useQuery({
    queryKey: ['staff', providerId],
    queryFn: () => apiClient.getAssignableSpecialists(providerId!),
    enabled: !!providerId,
  });

  const services: Service[] = useMemo(() => (servicesData as any[]).map((s: any) => ({
    id: s.id,
    name: s.name,
    price: s.price,
    duration: s.duration,
  })), [servicesData]);

  const stylists: Stylist[] = useMemo(() => (Array.isArray(staffData) ? staffData : []).map((s: any) => ({
    id: s.id,
    specialist_id: s.specialist_id,
    name: s.name,
    specializations: s.specializations || [],
    available: s.active !== false && s.status !== 'off' && s.status !== 'leave',
    nextAvailable: s.status === 'busy' ? 'Soon' : 'Now',
  })), [staffData]);

  const handleStepClick = (step: number) => {
    if (step <= currentStep + 1) {
      setCurrentStep(step);
    }
  };

  const handleContinue = () => {
    if (currentStep < 4) {
      if (currentStep === 2 && selectedStylist && !selectedStylist.available) {
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
            <h3 className="text-base lg:text-lg font-semibold text-text-primary">Select Customer</h3>
            {customersLoading ? (
              <div className="space-y-3">
                {[1,2,3].map(i => (
                  <div key={i} className="w-full h-16 rounded-xl bg-card border border-border-medium animate-pulse" />
                ))}
              </div>
            ) : customersData.length === 0 ? (
              <div className="text-center py-10 text-text-secondary">
                <p className="text-sm">No customers yet. Add customers in the Customers module first.</p>
              </div>
            ) : (
              <div className="space-y-2 max-h-96 overflow-y-auto">
                {(Array.isArray(customersData) ? customersData : []).map((customer: any) => (
                  <button
                    key={customer.id}
                    onClick={() => setSelectedCustomer(customer)}
                    className={`w-full p-3 lg:p-4 rounded-xl border-2 transition-all text-left ${
                      selectedCustomer?.id === customer.id
                        ? 'border-[#FFD700] bg-[#FFD700]/10'
                        : 'border-border-light bg-card hover:bg-white/10'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex-1 min-w-0">
                        <p className="text-text-primary font-semibold text-sm lg:text-base truncate">{customer.name}</p>
                        <p className="text-text-secondary text-xs lg:text-sm mt-1">{customer.phone}</p>
                      </div>
                      {customer.lastVisit && (
                        <span className="text-text-muted text-xs ml-2 flex-shrink-0">
                          Last: {new Date(customer.lastVisit).toLocaleDateString()}
                        </span>
                      )}
                    </div>
                  </button>
                ))}
              </div>
            )}
          </div>
        );

      case 1:
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
                    <span className="text-gold font-bold text-sm lg:text-lg ml-2 flex-shrink-0">UGX {service.price.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}</span>
                  </div>
                </button>
              ))
            )}
          </div>
        );

      case 2:
        return (
          <div className="space-y-3">
            <h3 className="text-base lg:text-lg font-semibold text-text-primary">Select a {terminology.specialist}</h3>
            <div className="bg-blue-500/10 border border-blue-500/30 rounded-lg p-2 lg:p-3">
              <p className="text-blue-400 text-[10px] lg:text-xs">
                Only {terminology.specialistPlural.toLowerCase()} with active beauty practice assignments are bookable
              </p>
            </div>
            {staffLoading ? (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {[1,2].map(i => (
                  <div key={i} className="h-24 rounded-xl bg-card border border-border-medium animate-pulse" />
                ))}
              </div>
            ) : stylists.length === 0 ? (
              <div className="text-center py-10 text-text-secondary">
                <p className="text-sm">No {terminology.specialistPlural.toLowerCase()} yet. Add {terminology.specialistPlural.toLowerCase()} in the Team module first.</p>
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

      case 3:
        return (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
            <CalendarPicker selectedDate={selectedDate} onDateSelect={setSelectedDate} />
            <TimePicker
              selectedTime={selectedTime}
              onTimeSelect={setSelectedTime}
              selectedStylist={selectedStylist}
              selectedDate={selectedDate}
            />
          </div>
        );

      case 4:
        return (
          <div className="space-y-3">
            <h3 className="text-base lg:text-lg font-semibold text-text-primary">Confirm Booking</h3>

            {selectedCustomer && (
              <div className="bg-green-500/10 border border-green-500/30 rounded-xl p-3 lg:p-4">
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 lg:w-10 lg:h-10 rounded-full bg-green-500/20 flex items-center justify-center flex-shrink-0">
                    <span className="text-green-400 text-lg">👋</span>
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-green-400 font-semibold text-sm lg:text-base">Customer: {selectedCustomer.name}</p>
                    <p className="text-text-secondary text-xs lg:text-sm">{selectedCustomer.phone}</p>
                  </div>
                </div>
              </div>
            )}

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
              <p className="text-text-secondary text-xs lg:text-sm">Please review the booking summary and click confirm to complete your booking.</p>
            </div>
          </div>
        );

      default:
        return null;
    }
  };

  const handleReschedule = () => {
    setCurrentStep(3);
  };

  const createBookingMutation = useMutation({
    mutationFn: async (data: any) => {
      return await apiClient.createBooking(data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings', providerId] });
      router.push(routes.bookings);
    },
    onError: (error: any) => {
      console.error('Failed to create booking:', error);
      console.error('Error response:', error.response?.data);
      alert(`Failed to create ${terminology.appointment.toLowerCase()}: ${error.response?.data?.message || error.message || 'Please try again.'}`);
    },
  });

  const handleConfirmBooking = () => {
    if (!selectedCustomer || !selectedService || !selectedStylist || !selectedDate || !selectedTime || !providerId) {
      return;
    }

    createBookingMutation.mutate({
      provider_id: providerId,
      location_id: locationId,
      customer_id: selectedCustomer.id,
      service_id: [selectedService.id],
      specialist_id: selectedStylist.specialist_id,
      date: formatDateKey(selectedDate),
      time: selectedTime,
      status: 'confirmed',
      internal_booking: true,
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
                onClick={currentStep === 4 ? handleConfirmBooking : handleContinue}
                disabled={
                  (currentStep === 0 && !selectedCustomer) ||
                  (currentStep === 1 && !selectedService) ||
                  (currentStep === 2 && !selectedStylist) ||
                  (currentStep === 3 && (!selectedDate || !selectedTime))
                }
                className="flex-1 sm:flex-none px-3 sm:px-4 lg:px-6 py-3 rounded-xl font-medium transition-all disabled:opacity-50 disabled:cursor-not-allowed bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-obsidian hover:opacity-90 text-sm lg:text-base"
              >
                {currentStep === 4 ? 'Confirm Booking' : 'Continue'}
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
