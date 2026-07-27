'use client';

import { useState, useEffect, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { motion } from 'framer-motion';
import { Crown, CheckCircle2, User, Mail, Lock, Phone, ArrowRight, Loader2, ArrowLeft, Star } from 'lucide-react';
import { portalApiClient } from '@/lib/portal-api-client';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { GlassCard } from '@/components/ui/glass-card';

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
        await portalApiClient.post('/v1/portal/accept-invitation', {
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
    <div className="min-h-screen w-full bg-[#070707] flex flex-col md:grid md:grid-cols-12 overflow-x-hidden relative font-poppins text-white">
      
      {/* LEFT COLUMN: REGISTRATION FORM PANEL */}
      <div className="col-span-12 md:col-span-5 lg:col-span-4 flex flex-col justify-between p-6 sm:p-10 z-10 bg-[#070707] border-r border-white/5 min-h-screen">
        
        {/* Top Header Section */}
        <div className="flex items-center justify-between">
          <button 
            onClick={() => router.push('/portal/login')}
            className="flex items-center gap-2 text-xs text-white/40 hover:text-gold transition-colors duration-300 group"
          >
            <ArrowLeft className="w-4 h-4 group-hover:-translate-x-1 transition-transform" />
            <span>Back to sign in</span>
          </button>
          
          <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-gold to-[#C9A227] flex items-center justify-center shadow-lg md:hidden">
            <Crown className="w-4 h-4 text-black" />
          </div>
        </div>

        {/* Center Registration Form Container */}
        <div className="w-full max-w-sm mx-auto my-auto py-10">
          
          {/* Logo / Header */}
          <div className="flex items-center gap-3 mb-6">
            <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-gold to-[#C9A227] flex items-center justify-center shadow-xl shadow-gold/10">
              <Crown className="w-5 h-5 text-black" />
            </div>
            <span className="font-sora font-bold text-base tracking-wider text-white">Yo.Salon</span>
          </div>

          {/* STEP 1: Form Details */}
          {step === 'details' && (
            <motion.div
              initial={{ opacity: 0, x: -10 }}
              animate={{ opacity: 1, x: 0 }}
              className="w-full"
            >
              <h1 className="text-2xl font-bold font-sora text-white mb-2 tracking-tight">
                {invitationToken ? 'Accept Invitation' : 'Create Account'}
              </h1>
              <p className="text-white/40 text-xs sm:text-sm mb-6">
                {invitationToken
                  ? 'Set up your portal credentials to manage your salon duties.'
                  : 'Join your salon workspace and manage appointments online.'
                }
              </p>

              <form onSubmit={handleSubmit} className="space-y-4">
                {!invitationToken && (
                  <div>
                    <label className="block text-xs font-semibold text-white/60 mb-1.5 uppercase tracking-wider">Full Name</label>
                    <div className="relative">
                      <User className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-white/30" />
                      <input
                        type="text"
                        name="name"
                        value={formData.name}
                        onChange={handleChange}
                        className="w-full pl-11 pr-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-white placeholder:text-white/20 focus:outline-none focus:border-gold hover:border-white/20 transition-colors"
                        placeholder="Sophia Lin"
                        required
                      />
                    </div>
                  </div>
                )}

                <div>
                  <label className="block text-xs font-semibold text-white/60 mb-1.5 uppercase tracking-wider">Email Address</label>
                  <div className="relative">
                    <Mail className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-white/30" />
                    <input
                      type="email"
                      name="email"
                      value={formData.email}
                      onChange={handleChange}
                      className="w-full pl-11 pr-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-white placeholder:text-white/20 focus:outline-none focus:border-gold hover:border-white/20 transition-colors"
                      placeholder="sophia@yoursalon.com"
                      required
                    />
                  </div>
                </div>

                {!invitationToken && (
                  <div>
                    <label className="block text-xs font-semibold text-white/60 mb-1.5 uppercase tracking-wider">Phone Number</label>
                    <div className="relative">
                      <Phone className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-white/30" />
                      <input
                        type="tel"
                        name="phone"
                        value={formData.phone}
                        onChange={handleChange}
                        className="w-full pl-11 pr-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-white placeholder:text-white/20 focus:outline-none focus:border-gold hover:border-white/20 transition-colors"
                        placeholder="+1 (555) 019-2834"
                        required
                      />
                    </div>
                  </div>
                )}

                <div>
                  <label className="block text-xs font-semibold text-white/60 mb-1.5 uppercase tracking-wider">Password</label>
                  <div className="relative">
                    <Lock className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-white/30" />
                    <input
                      type="password"
                      name="password"
                      value={formData.password}
                      onChange={handleChange}
                      className="w-full pl-11 pr-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-white placeholder:text-white/20 focus:outline-none focus:border-gold hover:border-white/20 transition-colors"
                      placeholder="At least 8 characters"
                      required
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-white/60 mb-1.5 uppercase tracking-wider">Confirm Password</label>
                  <div className="relative">
                    <Lock className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-white/30" />
                    <input
                      type="password"
                      name="confirmPassword"
                      value={formData.confirmPassword}
                      onChange={handleChange}
                      className="w-full pl-11 pr-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-white placeholder:text-white/20 focus:outline-none focus:border-gold hover:border-white/20 transition-colors"
                      placeholder="Confirm your password"
                      required
                    />
                  </div>
                </div>

                {error && (
                  <div className="p-3 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-xs font-medium">
                    {error}
                  </div>
                )}

                <button
                  type="submit"
                  disabled={isLoading}
                  className="w-full py-3 bg-gradient-to-r from-gold to-[#C9A227] text-black rounded-xl font-semibold hover:brightness-110 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-gold/15"
                >
                  {isLoading ? (
                    <>
                      <Loader2 className="w-4 h-4 animate-spin text-black" />
                      <span>Creating account...</span>
                    </>
                  ) : (
                    <>
                      <span>{invitationToken ? 'Accept Invitation' : 'Register Account'}</span>
                      <ArrowRight className="w-4 h-4 text-black" />
                    </>
                  )}
                </button>
              </form>
            </motion.div>
          )}

          {/* STEP 2: Verify Existing Profile Profile */}
          {step === 'verify' && existingCustomer && (
            <motion.div
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              className="w-full"
            >
              <div className="text-center mb-6">
                <div className="w-14 h-14 rounded-full bg-gold/10 flex items-center justify-center mx-auto mb-4 border border-gold/20">
                  <User className="w-7 h-7 text-gold" />
                </div>
                <h2 className="text-xl font-bold font-sora text-white mb-2">Profile Found!</h2>
                <p className="text-white/40 text-xs">
                  We matched your details with a client profile at {existingCustomer.salon?.name || 'your salon'}.
                </p>
              </div>

              <div className="bg-white/5 border border-white/10 rounded-xl p-4 mb-6">
                <div className="flex items-center gap-3 mb-3">
                  <CheckCircle2 className="w-4.5 h-4.5 text-gold" />
                  <span className="text-white font-medium text-sm">{existingCustomer.name}</span>
                </div>
                <div className="text-xs text-white/50 space-y-1 font-mono">
                  <p>{existingCustomer.phone}</p>
                  {existingCustomer.email && <p>{existingCustomer.email}</p>}
                  <p>{existingCustomer.visits} visits recorded</p>
                </div>
              </div>

              <p className="text-white/45 text-xs leading-relaxed mb-6">
                Click link below to sync your previous appointment history and service preferences with your login credentials.
              </p>

              <button
                onClick={handleLinkAccount}
                disabled={isLoading}
                className="w-full py-3 bg-gradient-to-r from-gold to-[#C9A227] text-black rounded-xl font-semibold hover:brightness-110 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-gold/15"
              >
                {isLoading ? (
                  <>
                    <Loader2 className="w-4 h-4 animate-spin text-black" />
                    <span>Syncing profile...</span>
                  </>
                ) : (
                  <>
                    <span>Link Profile & Log In</span>
                    <ArrowRight className="w-4 h-4 text-black" />
                  </>
                )}
              </button>
            </motion.div>
          )}

          {/* STEP 3: Successful creation */}
          {step === 'success' && (
            <motion.div
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              className="text-center w-full"
            >
              <div className="w-16 h-16 rounded-full bg-gradient-to-br from-gold to-[#C9A227] flex items-center justify-center mx-auto mb-6 shadow-xl shadow-gold/15">
                <CheckCircle2 className="w-8 h-8 text-black" />
              </div>
              <h2 className="text-2xl font-bold font-sora text-white mb-2">Account Ready!</h2>
              <p className="text-white/40 text-xs sm:text-sm mb-8 leading-relaxed">
                Your portal account has been successfully initialized. You can now manage appointments and client settings.
              </p>
              
              <button
                onClick={() => router.push('/portal/home')}
                className="w-full py-3 bg-gradient-to-r from-gold to-[#C9A227] text-black rounded-xl font-semibold hover:brightness-110 transition-all duration-300 flex items-center justify-center gap-2 cursor-pointer"
              >
                <span>Go to dashboard</span>
                <ArrowRight className="w-4 h-4 text-black" />
              </button>
            </motion.div>
          )}

        </div>

        {/* Bottom Footer Section */}
        <div className="text-left">
          <p className="text-[10px] text-white/20">
            Powered by Yo.Salon & EmKay Studios. Secure SSL Encrypted.
          </p>
        </div>
      </div>

      {/* RIGHT COLUMN: HIGH-END SALON IMAGE BANNER */}
      <div className="hidden md:block md:col-span-7 lg:col-span-8 relative h-screen overflow-hidden">
        {/* Spotlighting */}
        <div className="absolute inset-0 bg-[#070707] z-0" />
        
        {/* Salon Interior image */}
        <img 
          src="/images/salon-barber.jpg" 
          alt="Premium Barbershop Station" 
          className="absolute inset-0 w-full h-full object-cover opacity-60 mix-blend-luminosity hover:mix-blend-normal transition-all duration-1000"
        />

        {/* Gradient dark overlays */}
        <div className="absolute inset-0 bg-gradient-to-t from-black via-black/30 to-transparent z-10 pointer-events-none" />
        <div className="absolute inset-0 bg-gradient-to-r from-black/80 via-transparent to-transparent z-10 pointer-events-none" />

        {/* Slogan details overlay */}
        <div className="absolute bottom-12 left-12 right-12 z-20 max-w-lg">
          <GlassCard className="p-8 border border-white/10 backdrop-blur-xl bg-black/45" elevation={3}>
            <div className="flex gap-1 mb-4">
              {[...Array(5)].map((_, i) => (
                <Star key={i} className="w-4 h-4 text-gold fill-gold" />
              ))}
            </div>
            
            <p className="text-white/80 text-sm leading-relaxed mb-6 font-medium font-sora italic">
              "Setting up our salon workspace took less than five minutes. Our clients are completely amazed by the styling of their scheduling screens, and payments process seamlessly."
            </p>
            
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-full bg-gradient-to-tr from-gold to-orange-500 flex items-center justify-center text-xs font-bold text-black font-sora">
                SL
              </div>
              <div className="flex flex-col">
                <span className="text-xs font-bold text-white">Sophia Lin</span>
                <span className="text-[10px] text-white/40">Colorist & Owner, Luminary Lab</span>
              </div>
            </div>
          </GlassCard>
        </div>
      </div>
      
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
