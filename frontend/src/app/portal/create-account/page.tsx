'use client';

import { useState, useEffect, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { motion } from 'framer-motion';
import { Crown, CheckCircle2, User, Mail, Lock, Phone, ArrowRight, Loader2 } from 'lucide-react';
import { portalApiClient } from '@/lib/portal-api-client';
import { usePortalAuth } from '@/contexts/PortalAuthContext';

function CreateAccountContent() {
  const router = useRouter();
  const { login } = usePortalAuth();
  const searchParams = useSearchParams();
  const salonSlug = searchParams.get('salon') || '';
  const invitationToken = searchParams.get('invitation_token') || '';
  const [salonId, setSalonId] = useState<string>('');

  const [step, setStep] = useState<'details' | 'verify' | 'success'>('details');
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    password: '',
    confirmPassword: '',
  });
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const [existingCustomer, setExistingCustomer] = useState<any>(null);

  // Fetch salon data from slug to get salon_id
  useEffect(() => {
    if (salonSlug && !invitationToken) {
      portalApiClient.get(`/v1/salons/${salonSlug}`).then((salon) => {
        setSalonId(salon.id);
      }).catch(() => {
        setError('Salon not found');
      });
    }
  }, [salonSlug, invitationToken]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData(prev => ({ ...prev, [e.target.name]: e.target.value }));
    setError('');
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (formData.password !== formData.confirmPassword) {
      setError('Passwords do not match');
      return;
    }

    if (formData.password.length < 8) {
      setError('Password must be at least 8 characters');
      return;
    }

    setIsLoading(true);

    try {
      if (invitationToken) {
        // Accept invitation flow
        const result = await portalApiClient.post('/v1/portal/accept-invitation', {
          invitation_token: invitationToken,
          email: formData.email,
          password: formData.password,
        });
        setStep('success');
      } else {
        // Regular account creation flow - requires salon_id
        if (!salonId) {
          setError('Salon information is required. Please access this page from a salon link.');
          setIsLoading(false);
          return;
        }

        const result = await portalApiClient.register({
          email: formData.email,
          password: formData.password,
          phone: formData.phone,
          salon_id: salonId,
          name: formData.name,
        });

        if (result.is_new_customer === false) {
          setExistingCustomer(result.customer);
          setStep('verify');
        } else {
          setStep('success');
        }
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to create account. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  const handleLinkAccount = async () => {
    setIsLoading(true);
    try {
      // Auto-login after account creation
      await login(formData.email, formData.password);
      router.push('/portal/home');
    } catch (err) {
      setError('Failed to link account');
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center p-4 bg-[#070707]">
      {/* Background gradient */}
      <div className="absolute inset-0 bg-gradient-to-br from-[#FFD700]/10 via-[#C9A227]/5 to-transparent" />

      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        className="relative z-10 w-full max-w-md"
      >
        {/* Logo */}
        <motion.div
          initial={{ opacity: 0, scale: 0.8 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ delay: 0.2, duration: 0.5 }}
          className="flex items-center justify-center gap-3 mb-8"
        >
          <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center shadow-2xl shadow-[#FFD700]/20">
            <Crown className="w-8 h-8 text-black" />
          </div>
        </motion.div>

        {/* Step 1: Account Details */}
        {step === 'details' && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
          >
            <div className="bg-card border border-border-medium rounded-2xl p-8">
              <h1 className="text-2xl font-bold text-text-primary mb-2">
                {invitationToken ? 'Accept Invitation' : 'Create Your Account'}
              </h1>
              <p className="text-text-secondary mb-6">
                {invitationToken
                  ? 'Set up your portal account to manage your appointments'
                  : 'Join your salon and manage appointments online'
                }
              </p>

              <form onSubmit={handleSubmit} className="space-y-4">
                {!invitationToken && (
                  <div>
                    <label className="block text-sm text-text-secondary mb-2">Full Name</label>
                    <div className="relative">
                      <User className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-text-secondary" />
                      <input
                        type="text"
                        name="name"
                        value={formData.name}
                        onChange={handleChange}
                        className="w-full pl-10 pr-4 py-3 bg-surface border border-border-medium rounded-xl text-text-primary placeholder:text-text-secondary focus:outline-none focus:border-[#FFD700] transition-colors"
                        placeholder="Enter your name"
                        required
                      />
                    </div>
                  </div>
                )}

                <div>
                  <label className="block text-sm text-text-secondary mb-2">Email</label>
                  <div className="relative">
                    <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-text-secondary" />
                    <input
                      type="email"
                      name="email"
                      value={formData.email}
                      onChange={handleChange}
                      className="w-full pl-10 pr-4 py-3 bg-surface border border-border-medium rounded-xl text-text-primary placeholder:text-text-secondary focus:outline-none focus:border-[#FFD700] transition-colors"
                      placeholder="Enter your email"
                      required
                    />
                  </div>
                </div>

                {!invitationToken && (
                  <div>
                    <label className="block text-sm text-text-secondary mb-2">Phone</label>
                    <div className="relative">
                      <Phone className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-text-secondary" />
                      <input
                        type="tel"
                        name="phone"
                        value={formData.phone}
                        onChange={handleChange}
                        className="w-full pl-10 pr-4 py-3 bg-surface border border-border-medium rounded-xl text-text-primary placeholder:text-text-secondary focus:outline-none focus:border-[#FFD700] transition-colors"
                        placeholder="Enter your phone number"
                        required
                      />
                    </div>
                  </div>
                )}

                <div>
                  <label className="block text-sm text-text-secondary mb-2">Password</label>
                  <div className="relative">
                    <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-text-secondary" />
                    <input
                      type="password"
                      name="password"
                      value={formData.password}
                      onChange={handleChange}
                      className="w-full pl-10 pr-4 py-3 bg-surface border border-border-medium rounded-xl text-text-primary placeholder:text-text-secondary focus:outline-none focus:border-[#FFD700] transition-colors"
                      placeholder="Create a password"
                      required
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-sm text-text-secondary mb-2">Confirm Password</label>
                  <div className="relative">
                    <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-text-secondary" />
                    <input
                      type="password"
                      name="confirmPassword"
                      value={formData.confirmPassword}
                      onChange={handleChange}
                      className="w-full pl-10 pr-4 py-3 bg-surface border border-border-medium rounded-xl text-text-primary placeholder:text-text-secondary focus:outline-none focus:border-[#FFD700] transition-colors"
                      placeholder="Confirm your password"
                      required
                    />
                  </div>
                </div>

                {error && (
                  <div className="p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm">
                    {error}
                  </div>
                )}

                <button
                  type="submit"
                  disabled={isLoading}
                  className="w-full py-3 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-xl font-semibold hover:brightness-110 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                >
                  {isLoading ? (
                    <>
                      <Loader2 className="w-5 h-5 animate-spin" />
                      Creating account...
                    </>
                  ) : (
                    <>
                      {invitationToken ? 'Accept Invitation' : 'Create Account'}
                      <ArrowRight className="w-5 h-5" />
                    </>
                  )}
                </button>
              </form>

              <div className="mt-6 text-center">
                <p className="text-text-secondary text-sm">
                  Already have an account?{' '}
                  <button
                    onClick={() => router.push('/portal/login')}
                    className="text-[#FFD700] hover:underline"
                  >
                    Sign in
                  </button>
                </p>
              </div>
            </div>
          </motion.div>
        )}

        {/* Step 2: Verify Existing Customer */}
        {step === 'verify' && existingCustomer && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="bg-card border border-border-medium rounded-2xl p-8"
          >
            <div className="text-center mb-6">
              <div className="w-16 h-16 rounded-full bg-[#FFD700]/20 flex items-center justify-center mx-auto mb-4">
                <User className="w-8 h-8 text-[#FFD700]" />
              </div>
              <h2 className="text-xl font-bold text-text-primary mb-2">Welcome Back!</h2>
              <p className="text-text-secondary">
                We found your profile at {existingCustomer.salon?.name || 'your salon'}
              </p>
            </div>

            <div className="bg-surface border border-border-medium rounded-xl p-4 mb-6">
              <div className="flex items-center gap-3 mb-3">
                <CheckCircle2 className="w-5 h-5 text-[#FFD700]" />
                <span className="text-text-primary font-medium">{existingCustomer.name}</span>
              </div>
              <div className="text-sm text-text-secondary space-y-1">
                <p>{existingCustomer.phone}</p>
                {existingCustomer.email && <p>{existingCustomer.email}</p>}
                <p>{existingCustomer.visits} visits</p>
              </div>
            </div>

            <p className="text-text-secondary text-sm mb-6">
              Link this account to access your appointment history and preferences.
            </p>

            <button
              onClick={handleLinkAccount}
              disabled={isLoading}
              className="w-full py-3 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-xl font-semibold hover:brightness-110 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
              {isLoading ? (
                <>
                  <Loader2 className="w-5 h-5 animate-spin" />
                  Linking account...
                </>
              ) : (
                <>
                  Link Account
                  <ArrowRight className="w-5 h-5" />
                </>
              )}
            </button>
          </motion.div>
        )}

        {/* Step 3: Success */}
        {step === 'success' && (
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            className="bg-card border border-border-medium rounded-2xl p-8 text-center"
          >
            <div className="w-20 h-20 rounded-full bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center mx-auto mb-6">
              <CheckCircle2 className="w-10 h-10 text-black" />
            </div>
            <h2 className="text-2xl font-bold text-text-primary mb-2">Account Created!</h2>
            <p className="text-text-secondary mb-6">
              Your portal account is ready. You can now manage your appointments online.
            </p>
            <button
              onClick={() => router.push('/portal/home')}
              className="w-full py-3 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-xl font-semibold hover:brightness-110 transition-all duration-300 flex items-center justify-center gap-2"
            >
              Go to Home
              <ArrowRight className="w-5 h-5" />
            </button>
          </motion.div>
        )}
      </motion.div>
    </div>
  );
}

export default function CreateAccountPage() {
  return (
    <Suspense fallback={<div className="min-h-screen flex items-center justify-center bg-[#070707]"><Loader2 className="w-8 h-8 text-[#FFD700] animate-spin" /></div>}>
      <CreateAccountContent />
    </Suspense>
  );
}
