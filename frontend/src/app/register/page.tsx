'use client';

import { useState } from 'react';
import { apiClient } from '@/lib/api-client';
import { useRouter } from 'next/navigation';
import { User, Mail, Lock, Eye, EyeOff, Scissors, Building2, UserCircle } from 'lucide-react';
import { motion } from 'framer-motion';

type Journey = 'customer' | 'specialist' | 'salon';

export default function RegisterPage() {
  const router = useRouter();
  const [journey, setJourney] = useState<Journey>('salon');
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
      const response = await apiClient.register({ email, password, name, journey });
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

  return (
    <div className="h-screen flex relative overflow-hidden">
      {/* Left side - Editorial brand experience */}
      <div className="hidden lg:flex lg:w-1/2 relative h-full">
        {/* Cinematic image treatment */}
        <div
          className="absolute inset-0 bg-cover bg-center bg-no-repeat"
          style={{ backgroundImage: `url('/images/salon-dark.jpg')` }}
        />
        <div className="absolute inset-0 bg-gradient-to-t from-[#060608]/98 via-[#060608]/70 to-[#060608]/40" />
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_center,transparent_0%,rgba(0,0,0,0.3)_100%)]" />
        <div className="absolute inset-0 opacity-[0.03]" style={{ backgroundImage: `url("data:image/svg+xml,%3Csvg viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E")` }} />

        <motion.div
          className="relative z-10 flex flex-col justify-between p-16 h-full"
          initial={{ opacity: 0, scale: 1.04 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 1.2, ease: [0.25, 0.1, 0.25, 1] }}
        >
          {/* Logo treatment */}
          <div>
            <h1 className="text-2xl font-bold text-white tracking-[0.3em] mb-2">
              YO.<span className="text-[#FFD700]">SALON</span>
            </h1>
            <p className="text-xs tracking-[0.4em] text-white/40 uppercase">
              The peak of grooming
            </p>
          </div>

          {/* Editorial content */}
          <div className="space-y-12">
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.8, delay: 0.3 }}
            >
              <h2 className="text-4xl md:text-5xl font-bold text-white leading-tight mb-6">
                BUILD THE<br />
                <span className="text-[#FFD700]">EXPERIENCE.</span>
              </h2>
              <p className="text-lg text-white/60 leading-relaxed max-w-md font-light">
                Your clients aren't looking for another salon.
                They're looking for somewhere they feel exceptional.
              </p>
            </motion.div>
          </div>

          {/* Subtle testimonial */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.7 }}
            className="pt-8"
          >
            <div className="flex items-center gap-1 mb-3">
              {[...Array(5)].map((_, i) => (
                <span key={i} className="text-[#FFD700] text-sm">★</span>
              ))}
            </div>
            <p className="text-white/50 text-sm italic font-light leading-relaxed max-w-sm">
              "Exceptional experiences begin long before the appointment."
            </p>
            <p className="text-white/30 text-xs mt-2 tracking-wide">
              — Yo.Salon
            </p>
          </motion.div>
        </motion.div>
      </div>

      {/* Right side - Form */}
      <div className="w-full lg:w-1/2 flex items-center justify-center p-8 bg-[#0A0A0A] h-full">
        <motion.div
          className="w-full max-w-[400px]"
          initial={{ opacity: 0, x: 20 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ duration: 0.8, delay: 0.4 }}
        >
          <div className="mb-8">
            <h1 className="text-2xl font-semibold text-white mb-3 tracking-tight">
              Create your space.
            </h1>
            <p className="text-white/50 text-sm font-light">
              Start building a better salon experience.
            </p>
          </div>

          {/* Journey Selector */}
          <div className="mb-8">
            <p className="text-white/40 text-xs font-light mb-4 tracking-wide uppercase">
              What brings you to Yo.Salon?
            </p>
            <div className="grid grid-cols-3 gap-3">
              <motion.button
                type="button"
                onClick={() => setJourney('customer')}
                className={`p-4 rounded-xl border transition-all duration-300 ${
                  journey === 'customer'
                    ? 'bg-[rgba(255,215,0,0.1)] border-[rgba(255,215,0,0.3)]'
                    : 'bg-[rgba(255,255,255,0.03)] border-[rgba(255,255,255,0.06)] hover:border-[rgba(255,255,255,0.12)]'
                }`}
                whileHover={{ scale: 1.02 }}
                whileTap={{ scale: 0.98 }}
              >
                <UserCircle className={`w-6 h-6 mb-2 mx-auto ${journey === 'customer' ? 'text-[#FFD700]' : 'text-white/40'}`} />
                <p className={`text-xs font-medium text-center ${journey === 'customer' ? 'text-white' : 'text-white/50'}`}>
                  Customer
                </p>
              </motion.button>

              <motion.button
                type="button"
                onClick={() => setJourney('specialist')}
                className={`p-4 rounded-xl border transition-all duration-300 ${
                  journey === 'specialist'
                    ? 'bg-[rgba(255,215,0,0.1)] border-[rgba(255,215,0,0.3)]'
                    : 'bg-[rgba(255,255,255,0.03)] border-[rgba(255,255,255,0.06)] hover:border-[rgba(255,255,255,0.12)]'
                }`}
                whileHover={{ scale: 1.02 }}
                whileTap={{ scale: 0.98 }}
              >
                <Scissors className={`w-6 h-6 mb-2 mx-auto ${journey === 'specialist' ? 'text-[#FFD700]' : 'text-white/40'}`} />
                <p className={`text-xs font-medium text-center ${journey === 'specialist' ? 'text-white' : 'text-white/50'}`}>
                  Specialist
                </p>
              </motion.button>

              <motion.button
                type="button"
                onClick={() => setJourney('salon')}
                className={`p-4 rounded-xl border transition-all duration-300 ${
                  journey === 'salon'
                    ? 'bg-[rgba(255,215,0,0.1)] border-[rgba(255,215,0,0.3)]'
                    : 'bg-[rgba(255,255,255,0.03)] border-[rgba(255,255,255,0.06)] hover:border-[rgba(255,255,255,0.12)]'
                }`}
                whileHover={{ scale: 1.02 }}
                whileTap={{ scale: 0.98 }}
              >
                <Building2 className={`w-6 h-6 mb-2 mx-auto ${journey === 'salon' ? 'text-[#FFD700]' : 'text-white/40'}`} />
                <p className={`text-xs font-medium text-center ${journey === 'salon' ? 'text-white' : 'text-white/50'}`}>
                  Salon
                </p>
              </motion.button>
            </div>
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
            <div>
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
            </div>

            <div>
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
            </div>

            <div>
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
            </div>

            <div>
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
            </div>

            <div className="pt-2">
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
            </div>
          </form>

          <div className="mt-8 pt-8 border-t border-white/5">
            <p className="text-white/40 text-sm font-light text-center">
              Already have an account?{' '}
              <a
                href="/login"
                className="text-[#FFD700] hover:text-[#FFE55C] font-medium transition-colors duration-300"
              >
                Sign in →
              </a>
            </p>
          </div>

          {/* Memorable brand detail */}
          <div className="mt-12 text-center">
            <p className="text-white/20 text-xs tracking-[0.3em] uppercase">
              Peak of grooming
            </p>
          </div>
        </motion.div>
      </div>
    </div>
  );
}
