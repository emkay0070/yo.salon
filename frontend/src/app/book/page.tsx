'use client';

import { useState, useEffect, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { motion } from 'framer-motion';
import { Crown, Calendar, Clock, User, ArrowRight, CheckCircle2, Loader2 } from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { portalApiClient } from '@/lib/portal-api-client';
import { usePortalAuth } from '@/contexts/PortalAuthContext';

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
  time: string;
  available: boolean;
}

function BookPageContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { login } = usePortalAuth();
  const salonSlug = searchParams.get('salon') || '';

  const [step, setStep] = useState<'service' | 'staff' | 'time' | 'details' | 'confirm' | 'success'>('service');
  const [selectedService, setSelectedService] = useState<Service | null>(null);
  const [selectedStaff, setSelectedStaff] = useState<StaffMember | null>(null);
  const [selectedTime, setSelectedTime] = useState<string | null>(null);
  const [selectedDate, setSelectedDate] = useState<string>(new Date().toISOString().split('T')[0]);
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
  const [bookingResult, setBookingResult] = useState<any>(null);

  const [services, setServices] = useState<Service[]>([]);
  const [staff, setStaff] = useState<StaffMember[]>([]);
  const [timeSlots, setTimeSlots] = useState<TimeSlot[]>([]);
  const [salonId, setSalonId] = useState<string>('');

  // Fetch salon data from slug
  useEffect(() => {
    if (salonSlug) {
      apiClient.getSalonBySlug(salonSlug).then((salon) => {
        setSalonId(salon.id);
        // Fetch services and staff for this salon
        apiClient.getSalonServices(salonSlug).then(setServices);
        apiClient.getSalonStaff(salonSlug).then(setStaff);
      });
    }
  }, [salonSlug]);

  // Mock time slots - in production, fetch from API based on selected date
  const mockTimeSlots: TimeSlot[] = [
    { time: '09:00', available: true },
    { time: '09:30', available: true },
    { time: '10:00', available: false },
    { time: '10:30', available: true },
    { time: '11:00', available: true },
    { time: '11:30', available: false },
    { time: '14:00', available: true },
    { time: '14:30', available: true },
    { time: '15:00', available: true },
    { time: '15:30', available: false },
  ];

  useState(() => {
    setTimeSlots(mockTimeSlots);
  });

  const handleServiceSelect = (service: Service) => {
    setSelectedService(service);
    setStep('staff');
  };

  const handleStaffSelect = (staffMember: StaffMember) => {
    setSelectedStaff(staffMember);
    setStep('time');
  };

  const handleTimeSelect = (time: string) => {
    setSelectedTime(time);
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

  const handleConfirmBooking = async () => {
    setIsLoading(true);
    setError('');

    try {
      if (createAccount) {
        // Journey 4: Booking + Create Account
        // Validate account details
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

        // Call BookingService with account creation
        const result = await apiClient.createBookingWithAccount({
          salon_id: salonId,
          customer_name: customerDetails.name,
          customer_phone: customerDetails.phone,
          customer_email: customerDetails.email,
          service_id: selectedService?.id,
          staff_id: selectedStaff?.id,
          date: selectedDate,
          time: selectedTime,
          create_account: true,
          account_email: accountDetails.email || customerDetails.email,
          account_password: accountDetails.password,
        });
        
        setBookingResult(result);
      } else {
        // Journey 3: Guest booking
        const customerResult = await apiClient.createCustomer({
          salon_id: salonId,
          name: customerDetails.name,
          phone: customerDetails.phone,
          email: customerDetails.email,
        });

        const customerId = customerResult.id || customerResult.customer?.id;

        await apiClient.createBooking({
          salon_id: salonId,
          customer_id: customerId,
          service_id: selectedService?.id,
          staff_id: selectedStaff?.id,
          date: selectedDate,
          time: selectedTime,
          duration: selectedService?.duration,
          price: selectedService?.price,
        });
      }

      setStep('success');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to create booking');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#070707]">
      {/* Header */}
      <div className="border-b border-border-medium">
        <div className="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center">
              <Crown className="w-5 h-5 text-black" />
            </div>
            <span className="text-text-primary font-semibold">Book Appointment</span>
          </div>
          <button
            onClick={() => router.push('/portal/login')}
            className="text-text-secondary hover:text-text-primary text-sm"
          >
            Sign In
          </button>
        </div>
      </div>

      {/* Progress Steps */}
      <div className="max-w-4xl mx-auto px-4 py-6">
        <div className="flex items-center justify-between mb-8">
          {['service', 'staff', 'time', 'details'].map((s, index) => (
            <div key={s} className="flex items-center flex-1">
              <div
                className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium ${
                  step === s
                    ? 'bg-[#FFD700] text-black'
                    : ['service', 'staff', 'time', 'details'].indexOf(step) > index
                    ? 'bg-[#FFD700]/20 text-[#FFD700]'
                    : 'bg-surface text-text-secondary'
                }`}
              >
                {['service', 'staff', 'time', 'details'].indexOf(step) > index ? (
                  <CheckCircle2 className="w-4 h-4" />
                ) : (
                  index + 1
                )}
              </div>
              {index < 3 && (
                <div
                  className={`flex-1 h-0.5 mx-2 ${
                    ['service', 'staff', 'time', 'details'].indexOf(step) > index
                      ? 'bg-[#FFD700]'
                      : 'bg-border-medium'
                  }`}
                />
              )}
            </div>
          ))}
        </div>

        {/* Step 1: Select Service */}
        {step === 'service' && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
          >
            <h2 className="text-2xl font-bold text-text-primary mb-2">Choose a Service</h2>
            <p className="text-text-secondary mb-6">Select the service you'd like to book</p>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {services.map((service) => (
                <motion.button
                  key={service.id}
                  whileHover={{ scale: 1.02 }}
                  whileTap={{ scale: 0.98 }}
                  onClick={() => handleServiceSelect(service)}
                  className={`p-6 rounded-2xl border-2 text-left transition-all ${
                    selectedService?.id === service.id
                      ? 'border-[#FFD700] bg-[#FFD700]/10'
                      : 'border-border-medium bg-card hover:border-border-light'
                  }`}
                >
                  <div className="flex items-start justify-between mb-3">
                    <h3 className="text-text-primary font-semibold">{service.name}</h3>
                    <span className="text-[#FFD700] font-bold">
                      {service.price.toLocaleString()} UGX
                    </span>
                  </div>
                  <div className="flex items-center gap-2 text-text-secondary text-sm">
                    <Clock className="w-4 h-4" />
                    <span>{service.duration} min</span>
                    <span>•</span>
                    <span>{service.category}</span>
                  </div>
                </motion.button>
              ))}
            </div>
          </motion.div>
        )}

        {/* Step 2: Select Staff */}
        {step === 'staff' && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
          >
            <h2 className="text-2xl font-bold text-text-primary mb-2">Choose a Stylist</h2>
            <p className="text-text-secondary mb-6">Select your preferred stylist (optional)</p>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
              {staff.map((staffMember) => (
                <motion.button
                  key={staffMember.id}
                  whileHover={{ scale: 1.02 }}
                  whileTap={{ scale: 0.98 }}
                  onClick={() => handleStaffSelect(staffMember)}
                  className={`p-6 rounded-2xl border-2 text-center transition-all ${
                    selectedStaff?.id === staffMember.id
                      ? 'border-[#FFD700] bg-[#FFD700]/10'
                      : 'border-border-medium bg-card hover:border-border-light'
                  }`}
                >
                  <div className="w-16 h-16 rounded-full bg-surface mx-auto mb-3 flex items-center justify-center">
                    <User className="w-8 h-8 text-text-secondary" />
                  </div>
                  <h3 className="text-text-primary font-semibold mb-1">{staffMember.name}</h3>
                  <p className="text-text-secondary text-sm">{staffMember.role}</p>
                </motion.button>
              ))}
            </div>

            <button
              onClick={() => {
                setSelectedStaff(null);
                setStep('time');
              }}
              className="text-text-secondary hover:text-text-primary text-sm"
            >
              Skip stylist selection →
            </button>
          </motion.div>
        )}

        {/* Step 3: Select Time */}
        {step === 'time' && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
          >
            <h2 className="text-2xl font-bold text-text-primary mb-2">Choose a Time</h2>
            <p className="text-text-secondary mb-6">Select your preferred date and time</p>

            <input
              type="date"
              value={selectedDate}
              onChange={(e) => setSelectedDate(e.target.value)}
              className="w-full md:w-auto px-4 py-3 bg-surface border border-border-medium rounded-xl text-text-primary mb-6"
              min={new Date().toISOString().split('T')[0]}
            />

            <div className="grid grid-cols-3 md:grid-cols-5 gap-3">
              {timeSlots.map((slot) => (
                <motion.button
                  key={slot.time}
                  whileHover={slot.available ? { scale: 1.05 } : {}}
                  whileTap={slot.available ? { scale: 0.95 } : {}}
                  onClick={() => slot.available && handleTimeSelect(slot.time)}
                  disabled={!slot.available}
                  className={`p-4 rounded-xl border-2 text-center transition-all ${
                    !slot.available
                      ? 'border-border-medium bg-surface/30 text-text-secondary/50 cursor-not-allowed'
                      : selectedTime === slot.time
                      ? 'border-[#FFD700] bg-[#FFD700]/10 text-[#FFD700]'
                      : 'border-border-medium bg-card hover:border-border-light text-text-primary'
                  }`}
                >
                  {slot.time}
                </motion.button>
              ))}
            </div>
          </motion.div>
        )}

        {/* Step 4: Customer Details */}
        {step === 'details' && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
          >
            <h2 className="text-2xl font-bold text-text-primary mb-2">Your Details</h2>
            <p className="text-text-secondary mb-6">We'll create your profile automatically</p>

            <form onSubmit={handleDetailsSubmit} className="space-y-4">
              <div>
                <label className="block text-sm text-text-secondary mb-2">Full Name *</label>
                <input
                  type="text"
                  value={customerDetails.name}
                  onChange={(e) => setCustomerDetails(prev => ({ ...prev, name: e.target.value }))}
                  className="w-full px-4 py-3 bg-surface border border-border-medium rounded-xl text-text-primary placeholder:text-text-secondary focus:outline-none focus:border-[#FFD700] transition-colors"
                  placeholder="Enter your name"
                  required
                />
              </div>

              <div>
                <label className="block text-sm text-text-secondary mb-2">Phone Number *</label>
                <input
                  type="tel"
                  value={customerDetails.phone}
                  onChange={(e) => setCustomerDetails(prev => ({ ...prev, phone: e.target.value }))}
                  className="w-full px-4 py-3 bg-surface border border-border-medium rounded-xl text-text-primary placeholder:text-text-secondary focus:outline-none focus:border-[#FFD700] transition-colors"
                  placeholder="Enter your phone number"
                  required
                />
              </div>

              <div>
                <label className="block text-sm text-text-secondary mb-2">Email (optional)</label>
                <input
                  type="email"
                  value={customerDetails.email}
                  onChange={(e) => setCustomerDetails(prev => ({ ...prev, email: e.target.value }))}
                  className="w-full px-4 py-3 bg-surface border border-border-medium rounded-xl text-text-primary placeholder:text-text-secondary focus:outline-none focus:border-[#FFD700] transition-colors"
                  placeholder="Enter your email"
                />
              </div>

              {/* Create Account Option */}
              <div className="bg-surface border border-border-medium rounded-xl p-4">
                <label className="flex items-center gap-3 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={createAccount}
                    onChange={(e) => setCreateAccount(e.target.checked)}
                    className="w-5 h-5 rounded border-border-medium text-[#FFD700] focus:ring-[#FFD700]"
                  />
                  <div>
                    <span className="text-text-primary font-medium">Create a Client Portal Account</span>
                    <p className="text-text-secondary text-sm">Manage appointments, view history, and more</p>
                  </div>
                </label>
              </div>

              {/* Account Details (shown when createAccount is checked) */}
              {createAccount && (
                <div className="space-y-4 pt-4 border-t border-border-medium">
                  <div>
                    <label className="block text-sm text-text-secondary mb-2">Account Email *</label>
                    <input
                      type="email"
                      value={accountDetails.email}
                      onChange={(e) => setAccountDetails(prev => ({ ...prev, email: e.target.value }))}
                      className="w-full px-4 py-3 bg-surface border border-border-medium rounded-xl text-text-primary placeholder:text-text-secondary focus:outline-none focus:border-[#FFD700] transition-colors"
                      placeholder="Enter your account email"
                      required={createAccount}
                    />
                  </div>
                  <div>
                    <label className="block text-sm text-text-secondary mb-2">Password *</label>
                    <input
                      type="password"
                      value={accountDetails.password}
                      onChange={(e) => setAccountDetails(prev => ({ ...prev, password: e.target.value }))}
                      className="w-full px-4 py-3 bg-surface border border-border-medium rounded-xl text-text-primary placeholder:text-text-secondary focus:outline-none focus:border-[#FFD700] transition-colors"
                      placeholder="Create a password (min 8 characters)"
                      required={createAccount}
                    />
                  </div>
                  <div>
                    <label className="block text-sm text-text-secondary mb-2">Confirm Password *</label>
                    <input
                      type="password"
                      value={accountDetails.confirmPassword}
                      onChange={(e) => setAccountDetails(prev => ({ ...prev, confirmPassword: e.target.value }))}
                      className="w-full px-4 py-3 bg-surface border border-border-medium rounded-xl text-text-primary placeholder:text-text-secondary focus:outline-none focus:border-[#FFD700] transition-colors"
                      placeholder="Confirm your password"
                      required={createAccount}
                    />
                  </div>
                </div>
              )}

              {error && (
                <div className="p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm">
                  {error}
                </div>
              )}

              <button
                type="submit"
                className="w-full py-3 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-xl font-semibold hover:brightness-110 transition-all duration-300 flex items-center justify-center gap-2"
              >
                Continue
                <ArrowRight className="w-5 h-5" />
              </button>
            </form>
          </motion.div>
        )}

        {/* Step 5: Confirm */}
        {step === 'confirm' && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
          >
            <h2 className="text-2xl font-bold text-text-primary mb-2">Confirm Booking</h2>
            <p className="text-text-secondary mb-6">Please review your appointment details</p>

            <div className="bg-card border border-border-medium rounded-2xl p-6 space-y-4 mb-6">
              <div className="flex items-center justify-between py-3 border-b border-border-medium">
                <span className="text-text-secondary">Service</span>
                <span className="text-text-primary font-medium">{selectedService?.name}</span>
              </div>
              {selectedStaff && (
                <div className="flex items-center justify-between py-3 border-b border-border-medium">
                  <span className="text-text-secondary">Stylist</span>
                  <span className="text-text-primary font-medium">{selectedStaff.name}</span>
                </div>
              )}
              <div className="flex items-center justify-between py-3 border-b border-border-medium">
                <span className="text-text-secondary">Date</span>
                <span className="text-text-primary font-medium">{selectedDate}</span>
              </div>
              <div className="flex items-center justify-between py-3 border-b border-border-medium">
                <span className="text-text-secondary">Time</span>
                <span className="text-text-primary font-medium">{selectedTime}</span>
              </div>
              <div className="flex items-center justify-between py-3 border-b border-border-medium">
                <span className="text-text-secondary">Duration</span>
                <span className="text-text-primary font-medium">{selectedService?.duration} min</span>
              </div>
              <div className="flex items-center justify-between py-3">
                <span className="text-text-secondary">Total</span>
                <span className="text-[#FFD700] font-bold text-xl">
                  {selectedService?.price.toLocaleString()} UGX
                </span>
              </div>
            </div>

            {error && (
              <div className="p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm mb-4">
                {error}
              </div>
            )}

            <button
              onClick={handleConfirmBooking}
              disabled={isLoading}
              className="w-full py-3 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-xl font-semibold hover:brightness-110 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
              {isLoading ? (
                <>
                  <Loader2 className="w-5 h-5 animate-spin" />
                  Confirming...
                </>
              ) : (
                <>
                  Confirm Booking
                  <ArrowRight className="w-5 h-5" />
                </>
              )}
            </button>
          </motion.div>
        )}

        /* Step 6: Success */
        {step === 'success' && (
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            className="text-center py-12"
          >
            <div className="w-20 h-20 rounded-full bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center mx-auto mb-6">
              <CheckCircle2 className="w-10 h-10 text-black" />
            </div>
            <h2 className="text-3xl font-bold text-text-primary mb-2">Booking Confirmed!</h2>
            <p className="text-text-secondary mb-6">
              Your appointment has been scheduled. We'll send you a confirmation message.
            </p>
            <div className="bg-card border border-border-medium rounded-2xl p-6 mb-6 text-left max-w-md mx-auto">
              <div className="flex items-center justify-between py-2 border-b border-border-medium">
                <span className="text-text-secondary">Service</span>
                <span className="text-text-primary font-medium">{selectedService?.name}</span>
              </div>
              <div className="flex items-center justify-between py-2 border-b border-border-medium">
                <span className="text-text-secondary">Date</span>
                <span className="text-text-primary font-medium">{selectedDate}</span>
              </div>
              <div className="flex items-center justify-between py-2">
                <span className="text-text-secondary">Time</span>
                <span className="text-text-primary font-medium">{selectedTime}</span>
              </div>
            </div>
            
            {bookingResult?.portal_account ? (
              <div className="space-y-4">
                <p className="text-text-secondary text-sm">
                  Your portal account has been created! Redirecting to your portal...
                </p>
                <button
                  onClick={async () => {
                    try {
                      await login(accountDetails.email || customerDetails.email, accountDetails.password);
                      router.push('/portal/today');
                    } catch (err) {
                      setError('Failed to auto-login. Please login manually.');
                    }
                  }}
                  className="w-full py-3 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-xl font-semibold hover:brightness-110 transition-all duration-300 flex items-center justify-center gap-2"
                >
                  Go to Portal
                  <ArrowRight className="w-5 h-5" />
                </button>
              </div>
            ) : (
              <div className="space-y-4">
                <p className="text-text-secondary text-sm mb-4">
                  Want to manage your appointments online?
                </p>
                <button
                  onClick={() => router.push('/portal/create-account')}
                  className="text-[#FFD700] hover:underline font-medium"
                >
                  Create a free account →
                </button>
              </div>
            )}
          </motion.div>
        )}
      </div>
    </div>
  );
}

export default function BookPage() {
  return (
    <Suspense fallback={<div className="min-h-screen flex items-center justify-center bg-[#070707]"><Loader2 className="w-8 h-8 text-[#FFD700] animate-spin" /></div>}>
      <BookPageContent />
    </Suspense>
  );
}
