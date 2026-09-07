'use client';

import { motion, AnimatePresence } from 'framer-motion';
import { Calendar, Clock, User, ArrowRight, ChevronLeft, Sparkles, MessageCircle, CheckCircle } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { usePortalBrand } from '@/contexts/PortalBrandContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import Link from 'next/link';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';

type FlowStep = {
  id: string;
  title: string;
  config: any;
};

export default function NewBookingPage() {
  const { customer, salon, salons } = usePortalAuth();
  const { brand } = usePortalBrand();
  const [selectedService, setSelectedService] = useState<any>(null);
  const [selectedDate, setSelectedDate] = useState<string>('');
  const [selectedTime, setSelectedTime] = useState<string>('');
  const [selectedSpecialist, setSelectedSpecialist] = useState<any>(null);
  const [selectedSalon, setSelectedSalon] = useState<any>(null);
  const [currentStepIndex, setCurrentStepIndex] = useState(0);
  const [bookingSuccess, setBookingSuccess] = useState<any>(null);
  const [bookingError, setBookingError] = useState<string>('');
  const router = useRouter();

  const queryClient = useQueryClient();

  const { data: services, isLoading: servicesLoading } = useQuery({
    queryKey: ['portal-services'],
    queryFn: () => portalApiClient.get('/portal/services'),
    enabled: !!customer,
  });

  const steps = ['specialist', 'datetime', 'confirm'];

  const createBooking = useMutation({
    mutationFn: (data: any) => portalApiClient.createPortalBooking(data),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ['portal-home'] });
      queryClient.invalidateQueries({ queryKey: ['upcoming-bookings'] });
      setBookingSuccess(response.booking);
      setBookingError('');
    },
    onError: (err: any) => {
      setBookingError(err.response?.data?.message || 'Booking failed. Please try again.');
    },
  });

  const { data: availability, isLoading: availabilityLoading } = useQuery({
    queryKey: ['availability', selectedDate, selectedService?.id, selectedSalon?.id || salon?.id],
    queryFn: async () => {
      const params = {
        service_id: selectedService?.id,
        date: selectedDate,
        salon_id: selectedSalon?.id || salon?.id,
      };
      console.log('Fetching portal availability:', params);
      const result = await portalApiClient.get('/portal/availability', params);
      console.log('Portal availability response:', result);
      return result;
    },
    enabled: !!selectedDate && !!selectedService && !!selectedSalon || !!salon,
  });


  const { data: availableSpecialists, isLoading: specialistLoading } = useQuery({
    queryKey: ['available-specialists', selectedService?.id, selectedDate, selectedTime],
    queryFn: () => portalApiClient.get('/portal/availability/staff', {
      service_id: selectedService?.id,
      date: selectedDate,
      time: selectedTime,
      salon_id: selectedSalon?.id || salon?.id,
    }),
    // Fire when service is selected - with date/time for filtering, or without for all specialists
    enabled: !!selectedService && !!(selectedSalon?.id || salon?.id),
  });

  const currentStepId = steps[currentStepIndex];
  const isLastStep = currentStepIndex === steps.length - 1;

  const handleNext = () => {
    if (!isLastStep) {
      setCurrentStepIndex(prev => prev + 1);
    }
  };

  const handleBack = () => {
    if (currentStepIndex > 0) {
      setCurrentStepIndex(prev => prev - 1);
    } else {
      // Go back to service selection if at first step
      setSelectedService(null);
      setCurrentStepIndex(0);
    }
  };

  const handleConfirm = (methodId?: string | null) => {
    if (!selectedTime) {
      alert('Please select a time for your appointment');
      return;
    }

    setBookingError('');
    createBooking.mutate({
      service_id: selectedService.id,
      salon_id: selectedSalon?.id || salon?.id,
      specialist_id: selectedSpecialist?.id,
      date: selectedDate,
      time: selectedTime,
      notes: '',
      payment_method_id: methodId || null
    });
  };

  if (servicesLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  // Show booking success confirmation
  if (bookingSuccess) {
    return (
      <div className="max-w-2xl mx-auto space-y-6">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="text-center py-12"
        >
          <div className="w-20 h-20 rounded-full mx-auto mb-6 flex items-center justify-center"
            style={{ backgroundColor: 'var(--brand-primary, #FFD700)20' }}
          >
            <CheckCircle className="w-10 h-10" style={{ color: 'var(--brand-primary, #FFD700)' }} />
          </div>
          <h1 className="text-3xl font-bold text-text-primary mb-2">Booking Confirmed!</h1>
          <p className="text-text-secondary mb-8">Your appointment has been successfully booked.</p>

          <div className="bg-surface border border-border-light rounded-2xl p-6 text-left"
            style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
          >
            <div className="flex items-center gap-3 mb-4 pb-4 border-b border-border-light">
              <div className="w-10 h-10 rounded-xl flex items-center justify-center"
                style={{ backgroundColor: 'var(--brand-primary, #FFD700)20', borderRadius: 'var(--brand-border-radius, 16px)' }}
              >
                <User className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
              </div>
              <div>
                <p className="text-sm text-text-secondary">Service</p>
                <p className="font-semibold text-text-primary">{selectedService?.name}</p>
              </div>
              <span className="ml-auto font-semibold" style={{ color: 'var(--brand-primary, #FFD700)' }}>${selectedService?.price}</span>
            </div>

            <div className="flex items-center gap-3 mb-4 pb-4 border-b border-border-light">
              <div className="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              >
                <Calendar className="w-5 h-5 text-blue-500" />
              </div>
              <div>
                <p className="text-sm text-text-secondary">Date & Time</p>
                <p className="font-semibold text-text-primary">
                  {new Date(selectedDate).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
                </p>
                <p className="text-sm text-text-secondary">{selectedTime}</p>
              </div>
            </div>

            {selectedSpecialist && (
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center"
                  style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
                >
                  <User className="w-5 h-5 text-purple-500" />
                </div>
                <div>
                  <p className="text-sm text-text-secondary">Specialist</p>
                  <p className="font-semibold text-text-primary">{selectedSpecialist?.name}</p>
                </div>
              </div>
            )}
          </div>

          <button
            onClick={() => router.push('/portal/home')}
            className="mt-8 px-8 py-4 text-white font-semibold rounded-2xl flex items-center justify-center gap-3 group cursor-pointer transition-all mx-auto"
            style={{
              background: `linear-gradient(to right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))`,
              borderRadius: 'var(--brand-border-radius, 16px)',
              boxShadow: '0 4px 12px rgba(0, 0, 0, 0.08)'
            }}
          >
            Back to Home
            <ArrowRight className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
          </button>
        </motion.div>
      </div>
    );
  }

  // Show salon selection if customer has multiple salons and no salon selected
  if (salons.length > 1 && !selectedSalon && !selectedService) {
    return (
      <div className="max-w-2xl mx-auto space-y-6">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="flex items-center gap-4"
        >
          <Link
            href="/portal/bookings"
            className="p-2 rounded-full bg-surface border border-border-light hover:border-gold/30 transition-colors"
          >
            <ChevronLeft className="w-5 h-5 text-text-primary" />
          </Link>
          <div>
            <h1 className="text-3xl font-bold text-text-primary mb-2">Book</h1>
            <p className="text-text-secondary">Select a salon to book at</p>
          </div>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.3 }}
        >
          <div className="bg-surface border border-border-light rounded-2xl p-6 mb-4"
            style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
          >
            <div className="flex items-start gap-4 mb-4">
              <div className="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0"
                style={{ backgroundColor: 'var(--brand-primary, #FFD700)20' }}
              >
                <MessageCircle className="w-6 h-6" style={{ color: 'var(--brand-primary, #FFD700)' }} />
              </div>
              <div className="flex-1">
                <p className="text-text-primary mb-2">
                  Hi {customer?.name?.split(' ')[0] || 'there'}! 👋
                </p>
                <p className="text-text-secondary">
                  Which salon would you like to book at today?
                </p>
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4">
            {salons.map((salonItem: any) => (
              <motion.div
                key={salonItem.id}
                initial={{ opacity: 0, scale: 0.95 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ duration: 0.2 }}
                onClick={() => setSelectedSalon(salonItem)}
                className="bg-surface border border-border-light rounded-2xl p-6 cursor-pointer transition-all hover:border-gold/30 hover:shadow-lg"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              >
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="font-semibold text-text-primary">{salonItem.name}</h3>
                    <p className="text-sm text-text-secondary">{salonItem.visits} visits</p>
                  </div>
                  <ArrowRight className="w-5 h-5 text-text-secondary" />
                </div>
              </motion.div>
            ))}
          </div>
        </motion.div>
      </div>
    );
  }

  // Show service selection if no service selected
  if (!selectedService) {
    return (
      <div className="max-w-2xl mx-auto space-y-6">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="flex items-center gap-4"
        >
          <button
            onClick={() => {
              if (salons.length > 1) {
                setSelectedSalon(null);
              } else {
                router.push('/portal/bookings');
              }
            }}
            className="p-2 rounded-full bg-surface border border-border-light hover:border-gold/30 transition-colors"
          >
            <ChevronLeft className="w-5 h-5 text-text-primary" />
          </button>
          <div>
            <h1 className="text-3xl font-bold text-text-primary mb-2">Book</h1>
            <p className="text-text-secondary">Let's find the perfect time for you</p>
          </div>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.3 }}
        >
          <div className="bg-surface border border-border-light rounded-2xl p-6 mb-4"
            style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
          >
            <div className="flex items-start gap-4 mb-4">
              <div className="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0"
                style={{ backgroundColor: 'var(--brand-primary, #FFD700)20' }}
              >
                <MessageCircle className="w-6 h-6" style={{ color: 'var(--brand-primary, #FFD700)' }} />
              </div>
              <div className="flex-1">
                <p className="text-text-primary mb-2">
                  Hi {customer?.name?.split(' ')[0] || 'there'}! 👋
                </p>
                <p className="text-text-secondary">
                  I'd love to help you book an appointment. What service would you like to book today?
                </p>
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {services?.map((service: any) => (
              <motion.div
                key={service.id}
                initial={{ opacity: 0, scale: 0.95 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ duration: 0.2 }}
                onClick={() => setSelectedService(service)}
                className="bg-surface border border-border-light rounded-2xl p-6 cursor-pointer transition-all hover:border-gold/30 hover:shadow-lg"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              >
                <div className="flex items-center justify-between mb-4">
                  <h3 className="font-semibold text-text-primary">{service.name}</h3>
                  <span className="font-semibold" style={{ color: 'var(--brand-primary, #FFD700)' }}>${service.price}</span>
                </div>
                <p className="text-sm text-text-secondary mb-2">{service.description}</p>
                <div className="flex items-center gap-2 text-sm text-text-secondary">
                  <Clock className="w-4 h-4" />
                  <span>{service.duration} min</span>
                </div>
              </motion.div>
            ))}
          </div>
        </motion.div>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex items-center gap-4"
      >
        <button
          onClick={() => setSelectedService(null)}
          className="p-2 rounded-full bg-surface border border-border-light hover:border-gold/30 transition-colors"
        >
          <ChevronLeft className="w-5 h-5 text-text-primary" />
        </button>
        <div>
          <h1 className="text-3xl font-bold text-text-primary mb-2">Book {selectedService?.name}</h1>
          <p className="text-text-secondary text-sm">
            Step {currentStepIndex + 1} of {steps.length}
          </p>
        </div>
      </motion.div>

      {/* Progress Bar */}
      <div className="h-1 bg-surface rounded-full overflow-hidden">
        <motion.div
          className="h-full"
          style={{ backgroundColor: 'var(--brand-primary, #FFD700)' }}
          initial={{ width: 0 }}
          animate={{ width: `${((currentStepIndex + 1) / steps.length) * 100}%` }}
          transition={{ duration: 0.3 }}
        />
      </div>

      {/* Dynamic Flow Steps */}
      <AnimatePresence mode="wait">
        <motion.div
          key={currentStepId}
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, y: -20 }}
          transition={{ duration: 0.3 }}
        >
          {currentStepId === 'specialist' && (
            <SpecialistStep
              availableSpecialists={availableSpecialists}
              specialistLoading={specialistLoading}
              selectedSpecialist={selectedSpecialist}
              onSelect={setSelectedSpecialist}
              onNext={handleNext}
              onBack={handleBack}
            />
          )}

          {currentStepId === 'datetime' && (
            <DateTimeStep
              selectedService={selectedService}
              selectedDate={selectedDate}
              selectedTime={selectedTime}
              availability={availability}
              availabilityLoading={availabilityLoading}
              onDateChange={setSelectedDate}
              onTimeChange={setSelectedTime}
              onNext={handleNext}
              onBack={handleBack}
            />
          )}

          {/* ConfirmationStep owns its own Back + Confirm buttons — no outer nav on last step */}
          {currentStepId === 'confirm' && (
            <ConfirmationStep
              selectedService={selectedService}
              selectedSalon={selectedSalon || salon}
              selectedDate={selectedDate}
              selectedTime={selectedTime}
              selectedSpecialist={selectedSpecialist}
              onBack={handleBack}
              onConfirm={handleConfirm}
              isPending={createBooking.isPending}
              bookingError={bookingError}
            />
          )}

          {/* Outer navigation — only shown on non-final steps */}
          {!isLastStep && (
            <div className="flex gap-3 mt-6">
              <button
                onClick={handleBack}
                className="flex-1 px-6 py-3 bg-surface border border-border-light rounded-full text-text-primary hover:border-gold/30 transition-colors"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              >
                Back
              </button>
              {currentStepId === 'specialist' && (
                <button
                  onClick={handleNext}
                  className="flex-1 px-6 py-3 text-white rounded-full font-semibold transition-colors"
                  style={{
                    backgroundColor: 'var(--brand-primary, #FFD700)',
                    borderRadius: 'var(--brand-border-radius, 16px)'
                  }}
                >
                  Skip (Any Specialist)
                  <ArrowRight className="w-5 h-5 inline ml-2" />
                </button>
              )}
            </div>
          )}
        </motion.div>
      </AnimatePresence>
    </div>
  );
}

// Dynamic Step Components

function SpecialistStep({ availableSpecialists, specialistLoading, selectedSpecialist, onSelect, onNext }: any) {
  const specialists = availableSpecialists?.available_staff ?? [];

  console.log('SpecialistStep - availableSpecialists:', availableSpecialists);
  console.log('SpecialistStep - specialists:', specialists);
  console.log('SpecialistStep - specialistLoading:', specialistLoading);

  const handleSelect = (specialist: any) => {
    onSelect(specialist);
    onNext();
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3 }}
    >
      <div className="bg-surface border border-border-light rounded-2xl p-6 mb-4"
        style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
      >
        <div className="flex items-start gap-4">
          <div className="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
            style={{ backgroundColor: 'var(--brand-primary, #FFD700)20' }}
          >
            <MessageCircle className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
          </div>
          <div className="flex-1">
            <p className="text-text-primary">Who would you like to style your hair?</p>
            <p className="text-sm text-text-secondary mt-1">Or skip to let us assign the next available specialist.</p>
          </div>
        </div>
      </div>

      <div className="space-y-3">
        {/* Any Available — always first */}
        <motion.div
          initial={{ opacity: 0, x: -20 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ duration: 0.15 }}
          onClick={() => { onSelect(null); onNext(); }}
          className={`bg-surface border rounded-xl p-4 cursor-pointer transition-all hover:border-gold/30 hover:shadow-lg flex items-center gap-4 ${
            selectedSpecialist === null ? 'border-gold' : 'border-border-light'
          }`}
          style={{
            borderRadius: 'var(--brand-border-radius, 16px)',
            ...(selectedSpecialist === null ? { borderColor: 'var(--brand-primary, #FFD700)' } : {})
          }}
        >
          <div className="w-12 h-12 rounded-xl flex items-center justify-center bg-surface border border-border-light flex-shrink-0"
            style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
          >
            <Sparkles className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
          </div>
          <div className="flex-1">
            <h3 className="font-semibold text-text-primary">Any Available Specialist</h3>
            <p className="text-sm text-text-secondary">Let us assign someone for your slot</p>
          </div>
          <ArrowRight className="w-5 h-5 text-text-secondary" />
        </motion.div>

        {/* Specific specialists */}
        {specialistLoading ? (
          <div className="text-center py-6 text-text-secondary text-sm">Loading specialists...</div>
        ) : specialists.length === 0 ? (
          <div className="text-center py-6 text-text-secondary text-sm">
            No specific specialists listed — you can still book with any available.
          </div>
        ) : (
          specialists.map((specialist: any, index: number) => (
            <motion.div
              key={specialist.id}
              initial={{ opacity: 0, x: -20 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.2, delay: index * 0.05 }}
              onClick={() => handleSelect(specialist)}
              className={`bg-surface border rounded-xl p-4 cursor-pointer transition-all hover:border-gold/30 hover:shadow-lg flex items-center gap-4 ${
                selectedSpecialist?.id === specialist.id ? 'border-gold' : 'border-border-light'
              }`}
              style={{
                borderRadius: 'var(--brand-border-radius, 16px)',
                ...(selectedSpecialist?.id === specialist.id ? { borderColor: 'var(--brand-primary, #FFD700)' } : {})
              }}
            >
              <div className="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold flex-shrink-0"
                style={{
                  background: 'linear-gradient(135deg, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))',
                }}
              >
                {specialist.name?.charAt(0)}
              </div>
              <div className="flex-1">
                <h3 className="font-semibold text-text-primary">{specialist.name}</h3>
                {specialist.role && <p className="text-sm text-text-secondary">{specialist.role}</p>}
              </div>
              <ArrowRight className="w-5 h-5 text-text-secondary" />
            </motion.div>
          ))
        )}
      </div>
    </motion.div>
  );
}

/** Format a YYYY-MM-DD string for display without timezone conversion.
 *  new Date('2026-08-04') is parsed as UTC midnight and shifts back 1 day in EAT (+3).
 *  We parse the parts manually to avoid that. */
function parseDateLocal(dateStr: string) {
  const [y, m, d] = dateStr.split('-').map(Number);
  return new Date(y, m - 1, d);
}

function DateTimeStep({ selectedService, selectedDate, selectedTime, availability, availabilityLoading, onDateChange, onTimeChange, onNext }: any) {
  const today = new Date();
  const maxDays = 30;
  const dates = Array.from({ length: maxDays }, (_, i) => {
    const date = new Date(today.getFullYear(), today.getMonth(), today.getDate() + i);
    // Format as YYYY-MM-DD in local time (no UTC shift)
    return [
      date.getFullYear(),
      String(date.getMonth() + 1).padStart(2, '0'),
      String(date.getDate()).padStart(2, '0'),
    ].join('-');
  });

  const handleTimeSelect = (time: string) => {
    onTimeChange(time);
    onNext();
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3 }}
    >
      <div className="bg-surface border border-border-light rounded-2xl p-6 mb-4"
        style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
      >
        <div className="flex items-start gap-4 mb-4">
          <div className="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
            style={{ backgroundColor: 'var(--brand-primary, #FFD700)20' }}
          >
            <MessageCircle className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
          </div>
          <div className="flex-1">
            <p className="text-text-primary">
              When would you like to come in?
            </p>
          </div>
        </div>
      </div>

      {/* Date Selection */}
      <div className="mb-6">
        <h3 className="text-sm font-medium text-text-primary mb-3">Choose a date</h3>
        <div className="flex gap-2 overflow-x-auto pb-2">
          {dates.map((date) => (
            <motion.button
              key={date}
              initial={{ opacity: 0, scale: 0.9 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ duration: 0.2 }}
              onClick={() => onDateChange(date)}
              className={`flex-shrink-0 px-4 py-3 rounded-xl border transition-all ${
                selectedDate === date
                  ? 'text-white'
                  : 'bg-surface border-border-light hover:border-gold/30'
              }`}
              style={{
                ...(selectedDate === date
                  ? { backgroundColor: 'var(--brand-primary, #FFD700)', borderRadius: 'var(--brand-border-radius, 16px)' }
                  : { borderRadius: 'var(--brand-border-radius, 16px)' })
              }}
            >
              <div className="text-xs opacity-80 mb-1">
                {parseDateLocal(date).toLocaleDateString('en-US', { weekday: 'short' })}
              </div>
              <div className="font-semibold">
                {parseDateLocal(date).toLocaleDateString('en-US', { day: 'numeric' })}
              </div>
            </motion.button>
          ))}
        </div>
      </div>

      {/* Time Selection */}
      {selectedDate && (
        <div className="mb-6">
          <h3 className="text-sm font-medium text-text-primary mb-3">Available times</h3>
          {availabilityLoading ? (
            <div className="text-text-secondary">Loading availability...</div>
          ) : (
            <div className="grid grid-cols-3 md:grid-cols-4 gap-2">
              {availability?.slots?.map((slot: any, index: number) => (
                <motion.button
                  key={`${slot.time}-${index}`}
                  initial={{ opacity: 0, scale: 0.9 }}
                  animate={{ opacity: 1, scale: 1 }}
                  transition={{ duration: 0.2 }}
                  onClick={() => slot.available && handleTimeSelect(slot.time)}
                  disabled={!slot.available}
                  className={`px-4 py-3 rounded-lg border transition-all ${
                    selectedTime === slot.time
                      ? 'text-white'
                      : slot.available
                      ? 'bg-surface border-border-light hover:border-gold/30'
                      : 'bg-gray-100 border-gray-200 text-gray-400 cursor-not-allowed'
                  }`}
                  style={{
                    ...(selectedTime === slot.time
                      ? { backgroundColor: 'var(--brand-primary, #FFD700)', borderRadius: 'var(--brand-border-radius, 16px)' }
                      : { borderRadius: 'var(--brand-border-radius, 16px)' })
                  }}
                >
                  {slot.time}
                </motion.button>
              ))}
            </div>
          )}
        </div>
      )}
    </motion.div>
  );
}

function ConfirmationStep({ selectedService, selectedSalon, selectedDate, selectedTime, selectedSpecialist, onBack, onConfirm, isPending, bookingError }: any) {
  const [paymentInstruction, setPaymentInstruction] = useState<any>(null);
  const [selectedMethod, setSelectedMethod] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    async function loadInstructions() {
      try {
        const data = await portalApiClient.getPaymentInstructions(
          selectedSalon?.id,
          selectedService?.id
        );
        setPaymentInstruction(data);
      } catch (err) {
        console.error('Failed to load payment instructions:', err);
      } finally {
        setIsLoading(false);
      }
    }
    loadInstructions();
  }, [selectedSalon?.id, selectedService?.id]);

  if (isLoading) {
    return <div className="text-center py-8 text-text-secondary">Loading booking requirements...</div>;
  }

  const isPaymentRequired = paymentInstruction?.customerAction === 'pay_now';

  const handleConfirmClick = () => {
    if (isPaymentRequired && !selectedMethod) return;
    onConfirm(selectedMethod);
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3 }}
      className="max-w-2xl"
    >
      <div className="bg-surface border border-border-light rounded-2xl p-6 mb-4"
        style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
      >
        <div className="flex items-start gap-4 mb-4">
          <div className="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
            style={{ backgroundColor: 'var(--brand-primary, #FFD700)20' }}
          >
            <MessageCircle className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
          </div>
          <div className="flex-1">
            <p className="text-text-primary">
              Excellent! Here's a summary of your booking:
            </p>
            {paymentInstruction?.message && (
              <p className="text-sm text-text-secondary mt-1">
                {paymentInstruction.message}
              </p>
            )}
          </div>
        </div>
      </div>

      <div className="bg-surface border border-border-light rounded-2xl p-6 space-y-4 mb-6"
        style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
      >
        <div className="flex items-center justify-between pb-4 border-b border-border-light">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl flex items-center justify-center"
              style={{ backgroundColor: 'var(--brand-primary, #FFD700)20', borderRadius: 'var(--brand-border-radius, 16px)' }}
            >
              <User className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
            </div>
            <div>
              <p className="text-sm text-text-secondary">Service</p>
              <p className="font-semibold text-text-primary">{selectedService?.name}</p>
            </div>
          </div>
          <span className="font-semibold" style={{ color: 'var(--brand-primary, #FFD700)' }}>
            {paymentInstruction?.currency || 'UGX'} {selectedService?.price?.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
          </span>
        </div>

        <div className="flex items-center justify-between pb-4 border-b border-border-light">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center"
              style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
            >
              <Calendar className="w-5 h-5 text-blue-500" />
            </div>
            <div>
              <p className="text-sm text-text-secondary">Date & Time</p>
              <p className="font-semibold text-text-primary">
                {new Date(selectedDate).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
              </p>
              <p className="text-sm text-text-secondary">{selectedTime}</p>
            </div>
          </div>
        </div>

        {selectedSpecialist && (
          <div className="flex items-center justify-between pb-4 border-b border-border-light">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              >
                <User className="w-5 h-5 text-purple-500" />
              </div>
              <div>
                <p className="text-sm text-text-secondary">Specialist</p>
                <p className="font-semibold text-text-primary">{selectedSpecialist?.name}</p>
              </div>
            </div>
          </div>
        )}

        {isPaymentRequired && (
          <div className="flex items-center justify-between pb-4 border-b border-border-light">
            <span className="text-text-primary">Amount Due Now</span>
            <span className="font-semibold text-text-secondary">
              {paymentInstruction?.currency || 'UGX'} {paymentInstruction?.amountDueNow?.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
            </span>
          </div>
        )}

        <div className="flex items-center justify-between pt-2">
          <span className="text-lg font-semibold text-text-primary">
            {isPaymentRequired ? 'Total to Pay Now' : 'Total (Pay at Salon)'}
          </span>
          <span className="text-2xl font-bold" style={{ color: 'var(--brand-primary, #FFD700)' }}>
            {paymentInstruction?.currency || 'UGX'} {isPaymentRequired ? paymentInstruction?.amountDueNow?.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) : selectedService?.price?.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
          </span>
        </div>

        {isPaymentRequired && paymentInstruction?.eligibleMethods?.length > 0 && (
          <div className="mt-6 pt-6 border-t border-border-light">
            <span className="text-sm font-semibold text-text-primary block mb-3">Select Payment Method</span>
            <div className="space-y-3">
              {paymentInstruction.eligibleMethods.map((method: any) => (
                <button
                  key={method.id}
                  onClick={() => setSelectedMethod(method.id.toString())}
                  className={`w-full p-4 rounded-xl border flex items-center justify-between transition-all ${
                    selectedMethod === method.id.toString()
                      ? 'border-gold bg-gold/5'
                      : 'border-border-light hover:border-gold/30'
                  }`}
                  style={{
                    borderColor: selectedMethod === method.id.toString() ? 'var(--brand-primary, #FFD700)' : '',
                  }}
                >
                  <span className="font-medium text-text-primary">{method.name}</span>
                  {selectedMethod === method.id.toString() && <CheckCircle className="w-5 h-5 text-gold" style={{ color: 'var(--brand-primary, #FFD700)' }} />}
                </button>
              ))}
            </div>
          </div>
        )}
      </div>

      {bookingError && (
        <div className="px-4 py-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 text-sm mt-4">
          {bookingError}
        </div>
      )}

      <div className="flex gap-3 mt-4">
        <button
          onClick={onBack}
          className="flex-1 px-6 py-3 bg-surface border border-border-light rounded-full text-text-primary hover:border-gold/30 transition-colors"
          style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
        >
          Back
        </button>
        <button
          onClick={handleConfirmClick}
          disabled={isPending || (isPaymentRequired && !selectedMethod)}
          className="flex-1 px-6 py-3 text-white rounded-full font-semibold transition-all disabled:opacity-50"
          style={{
            backgroundColor: 'var(--brand-primary, #FFD700)',
            borderRadius: 'var(--brand-border-radius, 16px)'
          }}
        >
          {isPending ? 'Confirming...' : isPaymentRequired ? 'Pay & Confirm' : 'Confirm Booking'}
        </button>
      </div>
    </motion.div>
  );
}
