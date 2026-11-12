'use client';

import { useState } from 'react';
import { apiClient } from '@/lib/api-client';
import { useRouter } from 'next/navigation';
import { User, Mail, Lock, Eye, EyeOff, Building2, UserCircle, Briefcase, ArrowRight, Check } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';

type Journey = 'customer' | 'professional' | 'business';
type BackendJourney = 'customer' | 'specialist' | 'salon';

const journeyConfig = {
  customer: {
    icon: UserCircle,
    label: 'Customer',
    subtitle: 'Discover & book',
    selectedTitle: 'Create your account.',
    selectedSubtitle: 'Let\'s get started.',
    backendValue: 'customer' as BackendJourney,
  },
  professional: {
    icon: Briefcase,
    label: 'Professional',
    subtitle: 'Offer my expertise',
    selectedTitle: 'Create your account.',
    selectedSubtitle: 'Let\'s get started.',
    backendValue: 'specialist' as BackendJourney,
  },
  business: {
    icon: Building2,
    label: 'Business',
    subtitle: 'Build my space',
    selectedTitle: 'Create your account.',
    selectedSubtitle: 'Let\'s get started.',
    backendValue: 'salon' as BackendJourney,
  },
};

export default function RegisterPage() {
  const router = useRouter();
  const [journey, setJourney] = useState<Journey | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (password !== confirmPassword) {
      setError('Passwords do not match');
      return;
    }

    if (password.length < 8) {
      setError('Password must be at least 8 characters');
      return;
    }

    setLoading(true);

    try {
      const backendJourney = journey ? journeyConfig[journey].backendValue : undefined;
      const response = await apiClient.register({ email, password, name, journey: backendJourney });
      const nextRoute = response.next_route || '/onboarding';
      router.push(nextRoute);
    } catch (err: any) {
      console.error('Registration error:', err);
      const errorMessage = err.response?.data?.message || err.response?.data?.error || err.message || 'Registration failed';
      setError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  const handleJourneySelect = (selectedJourney: Journey) => {
    setJourney(selectedJourney);
    setTimeout(() => setShowForm(true), 400);
  };

  return (
    <div className="min-h-screen flex flex-col bg-[#0A0A0A] relative overflow-hidden">
      {/* Subtle background */}
      <div className="absolute inset-0 opacity-[0.02]" style={{ backgroundImage: `url("data:image/svg+xml,%3Csvg viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E")` }} />

      {/* Header */}
      <div className="relative z-10 flex items-center justify-between p-8 md:p-12">
        <motion.div
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6 }}
        >
          <h1 className="text-xl md:text-2xl font-bold text-white tracking-[0.3em]">
            YO.<span className="text-[#FFD700]">SALON</span>
          </h1>
        </motion.div>
        <motion.a
          href="/login"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ duration: 0.6, delay: 0.2 }}
          className="text-white/40 text-sm font-light hover:text-[#FFD700] transition-colors duration-300"
        >
          Sign in →
        </motion.a>
      </div>

      {/* Main content */}
      <div className="relative z-10 flex-1 flex items-center justify-center px-8 md:px-12 pb-12">
        <AnimatePresence mode="wait">
          {!showForm ? (
            // Journey Selection Screen
            <motion.div
              key="journey-selection"
              className="w-full max-w-4xl"
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -20 }}
              transition={{ duration: 0.5 }}
            >
              <div className="text-center mb-12">
                <motion.h1
                  initial={{ opacity: 0, y: -10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.6, delay: 0.1 }}
                  className="text-3xl md:text-4xl font-semibold text-white mb-4 tracking-tight"
                >
                  What brings you to Yo.Salon?
                </motion.h1>
                <motion.p
                  initial={{ opacity: 0, y: -10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.6, delay: 0.2 }}
                  className="text-white/50 text-sm font-light"
                >
                  I want to...
                </motion.p>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {(Object.keys(journeyConfig) as Journey[]).map((key, index) => {
                  const config = journeyConfig[key];
                  const Icon = config.icon;
                  return (
                    <motion.button
                      key={key}
                      type="button"
                      onClick={() => handleJourneySelect(key)}
                      className="p-6 rounded-xl border transition-all duration-300 text-left group"
                      style={{
                        backgroundColor: 'rgba(255,255,255,0.03)',
                        borderColor: 'rgba(255,255,255,0.08)',
                      }}
                      initial={{ opacity: 0, y: 20 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ duration: 0.5, delay: 0.3 + index * 0.1 }}
                      whileHover={{
                        scale: 1.02,
                        backgroundColor: 'rgba(255,215,0,0.08)',
                        borderColor: 'rgba(255,215,0,0.2)',
                      }}
                      whileTap={{ scale: 0.98 }}
                    >
                      <div className="flex flex-col items-center text-center gap-4">
                        <div className="flex-shrink-0">
                          <Icon className="w-8 h-8 text-white/40 group-hover:text-[#FFD700] transition-colors duration-300" />
                        </div>
                        <div className="flex-1">
                          <p className="text-white font-medium mb-1">{config.label}</p>
                          <p className="text-white/50 text-sm font-light">{config.subtitle}</p>
                        </div>
                      </div>
                    </motion.button>
                  );
                })}
              </div>
            </motion.div>
          ) : (
            // Form Screen
            <motion.div
              key="form"
              className="w-full max-w-[400px]"
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -20 }}
              transition={{ duration: 0.5 }}
            >
              {journey && (
                <>
                  <div className="mb-8">
                    <motion.div
                      initial={{ opacity: 0, y: -10 }}
                      animate={{ opacity: 1, y: 0 }}
                      className="flex items-center gap-3 mb-6"
                    >
                      <div className="flex items-center gap-2 px-3 py-1.5 rounded-full bg-[rgba(255,215,0,0.1)] border border-[rgba(255,215,0,0.2)]">
                        <Check className="w-4 h-4 text-[#FFD700]" />
                        <span className="text-[#FFD700] text-sm font-medium">{journeyConfig[journey].label}</span>
                      </div>
                      <div className="flex-1 h-px bg-white/10" />
                    </motion.div>

                    <motion.h1
                      initial={{ opacity: 0, y: 10 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: 0.1 }}
                      className="text-2xl font-semibold text-white mb-3 tracking-tight"
                    >
                      {journeyConfig[journey].selectedTitle}
                    </motion.h1>
                    <motion.p
                      initial={{ opacity: 0, y: 10 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: 0.2 }}
                      className="text-white/50 text-sm font-light"
                    >
                      {journeyConfig[journey].selectedSubtitle}
                    </motion.p>
                  </div>

                  {error && (
                    <motion.div
                      initial={{ opacity: 0, y: -10 }}
                      animate={{ opacity: 1, y: 0 }}
                      className="bg-[rgba(255,215,0,0.08)] border border-[rgba(255,215,0,0.15)] rounded-lg p-3 mb-8"
                    >
                      <p className="text-[#FFD700] text-xs font-light">{error}</p>
                    </motion.div>
                  )}

                  <form onSubmit={handleSubmit} className="space-y-5">
                    <motion.div
                      initial={{ opacity: 0, y: 10 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: 0.3 }}
                    >
                      <label className="block mb-2 text-xs tracking-widest uppercase text-white/40 font-light">
                        Full name
                      </label>
                      <div className="relative">
                        <input
                          type="text"
                          value={name}
                          onChange={(e) => setName(e.target.value)}
                          className="w-full px-4 py-3 rounded-lg text-white placeholder-white/20 focus:outline-none transition-all duration-300"
                          placeholder="John Doe"
                          required
                          style={{
                            backgroundColor: 'rgba(255,255,255,0.03)',
                            border: '1px solid rgba(255,255,255,0.08)',
                          }}
                          onFocus={(e) => {
                            e.target.style.borderColor = 'rgba(255,215,0,0.3)';
                            e.target.style.boxShadow = '0 0 30px rgba(255,215,0,0.08)';
                          }}
                          onBlur={(e) => {
                            e.target.style.borderColor = 'rgba(255,255,255,0.08)';
                            e.target.style.boxShadow = 'none';
                          }}
                        />
                      </div>
                    </motion.div>

                    <motion.div
                      initial={{ opacity: 0, y: 10 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: 0.35 }}
                    >
                      <label className="block mb-2 text-xs tracking-widest uppercase text-white/40 font-light">
                        Email
                      </label>
                      <div className="relative">
                        <input
                          type="email"
                          value={email}
                          onChange={(e) => setEmail(e.target.value)}
                          className="w-full px-4 py-3 rounded-lg text-white placeholder-white/20 focus:outline-none transition-all duration-300"
                          placeholder="you@example.com"
                          required
                          style={{
                            backgroundColor: 'rgba(255,255,255,0.03)',
                            border: '1px solid rgba(255,255,255,0.08)',
                          }}
                          onFocus={(e) => {
                            e.target.style.borderColor = 'rgba(255,215,0,0.3)';
                            e.target.style.boxShadow = '0 0 30px rgba(255,215,0,0.08)';
                          }}
                          onBlur={(e) => {
                            e.target.style.borderColor = 'rgba(255,255,255,0.08)';
                            e.target.style.boxShadow = 'none';
                          }}
                        />
                      </div>
                    </motion.div>

                    <motion.div
                      initial={{ opacity: 0, y: 10 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: 0.4 }}
                    >
                      <label className="block mb-2 text-xs tracking-widest uppercase text-white/40 font-light">
                        Password
                      </label>
                      <div className="relative">
                        <input
                          type={showPassword ? 'text' : 'password'}
                          value={password}
                          onChange={(e) => setPassword(e.target.value)}
                          className="w-full px-4 py-3 rounded-lg text-white placeholder-white/20 focus:outline-none transition-all duration-300"
                          placeholder="••••••••"
                          required
                          style={{
                            backgroundColor: 'rgba(255,255,255,0.03)',
                            border: '1px solid rgba(255,255,255,0.08)',
                          }}
                          onFocus={(e) => {
                            e.target.style.borderColor = 'rgba(255,215,0,0.3)';
                            e.target.style.boxShadow = '0 0 30px rgba(255,215,0,0.08)';
                          }}
                          onBlur={(e) => {
                            e.target.style.borderColor = 'rgba(255,255,255,0.08)';
                            e.target.style.boxShadow = 'none';
                          }}
                        />
                        <button
                          type="button"
                          onClick={() => setShowPassword(!showPassword)}
                          className="absolute right-4 top-1/2 -translate-y-1/2 text-white/30 hover:text-[#FFD700] transition-colors duration-300"
                        >
                          {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                        </button>
                      </div>
                    </motion.div>

                    <motion.div
                      initial={{ opacity: 0, y: 10 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: 0.45 }}
                    >
                      <label className="block mb-2 text-xs tracking-widest uppercase text-white/40 font-light">
                        Confirm password
                      </label>
                      <div className="relative">
                        <input
                          type={showConfirmPassword ? 'text' : 'password'}
                          value={confirmPassword}
                          onChange={(e) => setConfirmPassword(e.target.value)}
                          className="w-full px-4 py-3 rounded-lg text-white placeholder-white/20 focus:outline-none transition-all duration-300"
                          placeholder="••••••••"
                          required
                          style={{
                            backgroundColor: 'rgba(255,255,255,0.03)',
                            border: '1px solid rgba(255,255,255,0.08)',
                          }}
                          onFocus={(e) => {
                            e.target.style.borderColor = 'rgba(255,215,0,0.3)';
                            e.target.style.boxShadow = '0 0 30px rgba(255,215,0,0.08)';
                          }}
                          onBlur={(e) => {
                            e.target.style.borderColor = 'rgba(255,255,255,0.08)';
                            e.target.style.boxShadow = 'none';
                          }}
                        />
                        <button
                          type="button"
                          onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                          className="absolute right-4 top-1/2 -translate-y-1/2 text-white/30 hover:text-[#FFD700] transition-colors duration-300"
                        >
                          {showConfirmPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                        </button>
                      </div>
                    </motion.div>

                    <motion.div
                      initial={{ opacity: 0, y: 10 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: 0.5 }}
                      className="pt-2"
                    >
                      <motion.button
                        type="submit"
                        disabled={loading}
                        className="w-full py-3 rounded-lg font-medium text-[#0A0A0A] transition-all duration-300"
                        style={{
                          background: 'linear-gradient(135deg, #FFD700 0%, #C9A227 100%)'
                        }}
                        whileHover={{ scale: 1.02 }}
                        whileTap={{ scale: 0.98 }}
                      >
                        {loading ? 'Creating...' : 'Begin →'}
                      </motion.button>
                    </motion.div>
                  </form>

                  <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ delay: 0.6 }}
                    className="mt-8 pt-8 border-t border-white/5"
                  >
                    <button
                      type="button"
                      onClick={() => {
                        setShowForm(false);
                        setJourney(null);
                      }}
                      className="text-white/40 text-sm font-light hover:text-white transition-colors duration-300"
                    >
                      ← Back to journey selection
                    </button>
                  </motion.div>
                </>
              )}
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </div>
  );
}
