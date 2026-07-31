'use client';

import { motion } from 'framer-motion';
import { Calendar, Clock, User, ArrowRight, ChevronLeft } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import Link from 'next/link';
import { useState } from 'react';
import { useRouter } from 'next/navigation';

export default function NewBookingPage() {
  const { customer, salon } = usePortalAuth();
  const [selectedService, setSelectedService] = useState<any>(null);
  const [selectedDate, setSelectedDate] = useState<string>('');
  const [selectedTime, setSelectedTime] = useState<string>('');
  const [selectedStaff, setSelectedStaff] = useState<any>(null);
  const [step, setStep] = useState(1);

  const queryClient = useQueryClient();
  const router = useRouter();

  const { data: services, isLoading: servicesLoading } = useQuery({
    queryKey: ['portal-services'],
    queryFn: () => portalApiClient.get('/portal/services'),
    enabled: !!customer,
  });

  const createBooking = useMutation({
    mutationFn: (data: any) => portalApiClient.post('/portal/bookings', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-home'] });
      queryClient.invalidateQueries({ queryKey: ['upcoming-bookings'] });
      router.push('/portal/today'); // Go to today to see next appointment
    },
  });

  const { data: availability, isLoading: availabilityLoading } = useQuery({
    queryKey: ['availability', selectedDate, selectedService?.id],
    queryFn: () => portalApiClient.get('/portal/availability', {
      params: {
        service_id: selectedService?.id,
        date: selectedDate,
      },
    }),
    enabled: !!selectedDate && !!selectedService,
  });

  const { data: availableStaff, isLoading: staffLoading } = useQuery({
    queryKey: ['available-staff', selectedService?.id, selectedDate, selectedTime],
    queryFn: () => portalApiClient.get('/portal/availability/staff', {
      params: {
        service_id: selectedService?.id,
        date: selectedDate,
        time: selectedTime,
      },
    }),
    enabled: !!selectedService && !!selectedDate && !!selectedTime,
  });

  if (servicesLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  return (
      <div className="space-y-6">
        {/* Header */}
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
            <h1 className="text-3xl font-bold text-text-primary mb-2">Book Appointment</h1>
            <p className="text-text-secondary">Step {step} of 4</p>
          </div>
        </motion.div>

        {/* Progress Steps */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="flex items-center gap-2"
        >
          {[1, 2, 3, 4].map((s) => (
            <div
              key={s}
              className={`flex-1 h-2 rounded-full transition-all ${
                s <= step ? 'bg-gold' : 'bg-gray-200'
              }`}
            />
          ))}
        </motion.div>

        {/* Step Content */}
        {step === 1 && (
          <ServiceSelection
            services={services}
            selectedService={selectedService}
            onSelect={(service: any) => {
              setSelectedService(service);
              setStep(2);
            }}
          />
        )}

        {step === 2 && (
          <DateTimeSelection
            selectedService={selectedService}
            selectedDate={selectedDate}
            selectedTime={selectedTime}
            availability={availability}
            availabilityLoading={availabilityLoading}
            onDateChange={setSelectedDate}
            onTimeChange={(time: string) => {
              setSelectedTime(time);
              setStep(3);
            }}
            onBack={() => setStep(1)}
          />
        )}

        {step === 3 && (
          <StaffSelection
            availableStaff={availableStaff}
            staffLoading={staffLoading}
            selectedStaff={selectedStaff}
            onSelect={(staff: any) => {
              setSelectedStaff(staff);
              setStep(4);
            }}
            onBack={() => setStep(2)}
          />
        )}

        {step === 4 && (
          <Confirmation
            selectedService={selectedService}
            selectedDate={selectedDate}
            selectedTime={selectedTime}
            selectedStaff={selectedStaff}
            onBack={() => setStep(3)}
            onConfirm={() => {
              createBooking.mutate({
                service_id: selectedService.id,
                staff_id: selectedStaff?.id,
                date: selectedDate,
                time: selectedTime,
                notes: ''
              });
            }}
            isPending={createBooking.isPending}
          />
        )}
      </div>
  );
}

function ServiceSelection({ services, selectedService, onSelect }: any) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay: 0.2 }}
    >
      <h2 className="text-xl font-semibold text-text-primary mb-4">Select a Service</h2>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {services?.map((service: any) => (
          <div
            key={service.id}
            onClick={() => onSelect(service)}
            className={`bg-surface border rounded-2xl p-6 cursor-pointer transition-all hover:border-gold/30 ${
              selectedService?.id === service.id ? 'border-gold' : 'border-border-light'
            }`}
          >
            <div className="flex items-center justify-between mb-4">
              <h3 className="font-semibold text-text-primary">{service.name}</h3>
              <span className="text-gold font-semibold">${service.price}</span>
            </div>
            <p className="text-sm text-text-secondary mb-2">{service.description}</p>
            <div className="flex items-center gap-2 text-sm text-text-secondary">
              <Clock className="w-4 h-4" />
              <span>{service.duration} min</span>
            </div>
          </div>
        ))}
      </div>
    </motion.div>
  );
}

function DateTimeSelection({ selectedService, selectedDate, selectedTime, availability, availabilityLoading, onDateChange, onTimeChange, onBack }: any) {
  const today = new Date();
  const dates = Array.from({ length: 14 }, (_, i) => {
    const date = new Date(today);
    date.setDate(today.getDate() + i);
    return date.toISOString().split('T')[0];
  });

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay: 0.2 }}
    >
      <h2 className="text-xl font-semibold text-text-primary mb-4">Select Date & Time</h2>

      {/* Date Selection */}
      <div className="mb-6">
        <h3 className="text-sm font-medium text-text-primary mb-3">Date</h3>
        <div className="flex gap-2 overflow-x-auto pb-2">
          {dates.map((date) => (
            <button
              key={date}
              onClick={() => onDateChange(date)}
              className={`flex-shrink-0 px-4 py-3 rounded-xl border transition-all ${
                selectedDate === date
                  ? 'bg-gold text-obsidian border-gold'
                  : 'bg-surface border-border-light hover:border-gold/30'
              }`}
            >
              <div className="text-xs text-text-secondary mb-1">
                {new Date(date).toLocaleDateString('en-US', { weekday: 'short' })}
              </div>
              <div className="font-semibold">
                {new Date(date).toLocaleDateString('en-US', { day: 'numeric' })}
              </div>
            </button>
          ))}
        </div>
      </div>

      {/* Time Selection */}
      {selectedDate && (
        <div className="mb-6">
          <h3 className="text-sm font-medium text-text-primary mb-3">Available Times</h3>
          {availabilityLoading ? (
            <div className="text-text-secondary">Loading availability...</div>
          ) : (
            <div className="grid grid-cols-3 md:grid-cols-4 gap-2">
              {availability?.slots?.map((slot: any) => (
                <button
                  key={slot.time}
                  onClick={() => slot.available && onTimeChange(slot.time)}
                  disabled={!slot.available}
                  className={`px-4 py-3 rounded-lg border transition-all ${
                    selectedTime === slot.time
                      ? 'bg-gold text-obsidian border-gold'
                      : slot.available
                      ? 'bg-surface border-border-light hover:border-gold/30'
                      : 'bg-gray-100 border-gray-200 text-gray-400 cursor-not-allowed'
                  }`}
                >
                  {slot.time}
                </button>
              ))}
            </div>
          )}
        </div>
      )}

      <button
        onClick={onBack}
        className="px-6 py-2 bg-surface border border-border-light rounded-full text-text-primary hover:border-gold/30 transition-colors"
      >
        Back
      </button>
    </motion.div>
  );
}

function StaffSelection({ availableStaff, staffLoading, selectedStaff, onSelect, onBack }: any) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay: 0.2 }}
    >
      <h2 className="text-xl font-semibold text-text-primary mb-4">Select Stylist</h2>

      {staffLoading ? (
        <div className="text-text-secondary">Loading available stylists...</div>
      ) : (
        <div className="space-y-3">
          {availableStaff?.available_staff?.map((staff: any) => (
            <div
              key={staff.id}
              onClick={() => onSelect(staff)}
              className={`bg-surface border rounded-xl p-4 cursor-pointer transition-all hover:border-gold/30 flex items-center gap-4 ${
                selectedStaff?.id === staff.id ? 'border-gold' : 'border-border-light'
              }`}
            >
              <div className="w-12 h-12 rounded-full bg-gradient-to-br from-gold to-dark-gold flex items-center justify-center text-white font-semibold">
                {staff.name?.charAt(0)}
              </div>
              <div className="flex-1">
                <h3 className="font-semibold text-text-primary">{staff.name}</h3>
                <p className="text-sm text-text-secondary">Available</p>
              </div>
              <ArrowRight className="w-5 h-5 text-text-secondary" />
            </div>
          ))}
        </div>
      )}

      <button
        onClick={onBack}
        className="mt-6 px-6 py-2 bg-surface border border-border-light rounded-full text-text-primary hover:border-gold/30 transition-colors"
      >
        Back
      </button>
    </motion.div>
  );
}

function Confirmation({ selectedService, selectedDate, selectedTime, selectedStaff, onBack, onConfirm, isPending }: any) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay: 0.2 }}
      className="max-w-2xl"
    >
      <h2 className="text-xl font-semibold text-text-primary mb-6">Confirm Booking</h2>

      <div className="bg-surface border border-border-light rounded-2xl p-6 space-y-4">
        <div className="flex items-center justify-between pb-4 border-b border-border-light">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-gold/10 flex items-center justify-center">
              <User className="w-5 h-5 text-gold" />
            </div>
            <div>
              <p className="text-sm text-text-secondary">Service</p>
              <p className="font-semibold text-text-primary">{selectedService?.name}</p>
            </div>
          </div>
          <span className="text-gold font-semibold">${selectedService?.price}</span>
        </div>

        <div className="flex items-center justify-between pb-4 border-b border-border-light">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center">
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

        <div className="flex items-center justify-between pb-4 border-b border-border-light">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center">
              <User className="w-5 h-5 text-purple-500" />
            </div>
            <div>
              <p className="text-sm text-text-secondary">Stylist</p>
              <p className="font-semibold text-text-primary">{selectedStaff?.name}</p>
            </div>
          </div>
        </div>

        <div className="flex items-center justify-between pt-2">
          <span className="text-lg font-semibold text-text-primary">Total</span>
          <span className="text-2xl font-bold text-gold">${selectedService?.price}</span>
        </div>
      </div>

      <div className="flex gap-3 mt-6">
        <button
          onClick={onBack}
          className="flex-1 px-6 py-3 bg-surface border border-border-light rounded-full text-text-primary hover:border-gold/30 transition-colors"
        >
          Back
        </button>
        <button
          onClick={onConfirm}
          disabled={isPending}
          className="flex-1 px-6 py-3 bg-gold text-obsidian rounded-full font-semibold hover:bg-dark-gold transition-colors disabled:opacity-50"
        >
          {isPending ? 'Confirming...' : 'Confirm Booking'}
        </button>
      </div>
    </motion.div>
  );
}
