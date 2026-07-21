'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Mail, Lock, Eye, EyeOff, Scissors, ArrowRight } from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { motion } from 'framer-motion';

export default function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const router = useRouter();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const data = await apiClient.login(email, password);
      // Follow the backend's instruction — it knows where this user belongs
      router.push(data.next_route || '/dashboard');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Login failed. Please check your credentials.');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex overflow-hidden bg-[#070707]">

      {/* ── Left: Cinematic Image Panel ─────────────────────────────────── */}
      <div className="hidden lg:flex lg:w-[55%] relative overflow-hidden">
        {/* Background image */}
        <div
          className="absolute inset-0 bg-cover bg-center bg-no-repeat"
          style={{ backgroundImage: `url('/images/salon-auth.jpg')` }}
        />

        {/* Multi-layer gradient overlay for luxury depth */}
        <div className="absolute inset-0 bg-gradient-to-r from-[#070707]/30 via-[#070707]/10 to-[#070707]/70" />
        <div className="absolute inset-0 bg-gradient-to-t from-[#070707]/80 via-transparent to-[#070707]/40" />

        {/* Content on image */}
        <div className="relative z-10 flex flex-col justify-between p-10 w-full">
          {/* Brand mark */}
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center shadow-xl shadow-[#FFD700]/20">
              <Scissors className="w-5 h-5 text-black" />
            </div>
            <span className="text-text-primary text-xl font-bold tracking-tight">Yo Salon</span>
          </div>

          {/* Hero tagline at bottom */}
          <div>
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.3, duration: 0.8 }}
            >
              <p className="text-gold text-xs font-semibold tracking-[0.2em] uppercase mb-4">Premium Salon Management</p>
              <h2 className="text-5xl font-bold text-text-primary leading-tight mb-4">
                Elevate Your<br />
                <span className="text-transparent bg-clip-text bg-gradient-to-r from-[#FFD700] to-[#C9A227]">
                  Salon Experience
                </span>
              </h2>
              <p className="text-text-primary/60 text-lg leading-relaxed max-w-sm">
                Manage bookings, staff, and growth with a platform built for luxury salons.
              </p>
            </motion.div>

            {/* Social proof badges */}
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ delay: 0.6, duration: 0.8 }}
              className="flex items-center gap-6 mt-10"
            >
              {[
                { value: '10k+', label: 'Bookings/mo' },
                { value: '98%', label: 'Satisfaction' },
                { value: '24/7', label: 'Support' },
              ].map((badge) => (
                <div key={badge.label} className="text-center">
                  <p className="text-text-primary text-xl font-bold">{badge.value}</p>
                  <p className="text-text-primary/50 text-xs mt-0.5">{badge.label}</p>
                </div>
              ))}
            </motion.div>
          </div>
        </div>
      </div>

      {/* ── Right: Login Form Panel ──────────────────────────────────────── */}
      <div className="flex-1 flex items-center justify-center p-6 lg:p-12 relative">

        {/* Subtle background texture */}
        <div
          className="absolute inset-0 opacity-[0.03] bg-cover bg-center"
          style={{ backgroundImage: `url('/images/1.jpg')` }}
        />

        {/* Noise texture overlay */}
        <div className="absolute inset-0 bg-[#070707]/95" />

        {/* Mobile logo */}
        <div className="lg:hidden absolute top-6 left-6 flex items-center gap-3 z-10">
          <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center">
            <Scissors className="w-4 h-4 text-black" />
          </div>
          <span className="text-text-primary font-bold tracking-tight">Yo Salon</span>
        </div>

        <motion.div
          initial={{ opacity: 0, x: 20 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
          className="w-full max-w-md relative z-10"
        >
          {/* Header */}
          <div className="mb-10">
            <h1 className="text-4xl font-bold text-text-primary tracking-tight mb-2">
              Welcome back
            </h1>
            <p className="text-text-primary/40 text-base">
              Sign in to your salon dashboard
            </p>
          </div>

          {/* Error */}
          {error && (
            <motion.div
              initial={{ opacity: 0, y: -8 }}
              animate={{ opacity: 1, y: 0 }}
              className="bg-red-500/10 border border-red-500/20 rounded-2xl p-4 mb-6"
            >
              <p className="text-red-400 text-sm">{error}</p>
            </motion.div>
          )}

          <form onSubmit={handleSubmit} className="space-y-5">
            {/* Email */}
            <div className="space-y-2">
              <label className="text-xs font-semibold tracking-widest uppercase text-text-primary/40">
                Email Address
              </label>
              <div className="relative group">
                <Mail className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-text-primary/30 group-focus-within:text-gold transition-colors duration-300" />
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="your@email.com"
                  required
                  className="w-full pl-11 pr-4 py-4 rounded-2xl text-text-primary placeholder-white/20 bg-card border border-border-light focus:outline-none focus:border-[#FFD700]/50 focus:bg-white/8 transition-all duration-300 text-sm"
                />
              </div>
            </div>

            {/* Password */}
            <div className="space-y-2">
              <label className="text-xs font-semibold tracking-widest uppercase text-text-primary/40">
                Password
              </label>
              <div className="relative group">
                <Lock className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-text-primary/30 group-focus-within:text-gold transition-colors duration-300" />
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                  required
                  className="w-full pl-11 pr-14 py-4 rounded-2xl text-text-primary placeholder-white/20 bg-card border border-border-light focus:outline-none focus:border-[#FFD700]/50 focus:bg-white/8 transition-all duration-300 text-sm"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-4 top-1/2 -translate-y-1/2 text-text-primary/30 hover:text-gold transition-colors duration-300"
                >
                  {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            {/* Forgot password */}
            <div className="flex justify-end">
              <a
                href="/forgot-password"
                className="text-sm text-gold/70 hover:text-gold transition-colors duration-300"
              >
                Forgot password?
              </a>
            </div>

            {/* Submit */}
            <button
              type="submit"
              disabled={loading}
              className="w-full py-4 rounded-2xl font-semibold text-black bg-gradient-to-r from-[#FFD700] to-[#C9A227] hover:brightness-110 active:scale-[0.98] transition-all duration-300 shadow-2xl shadow-[#FFD700]/20 flex items-center justify-center gap-2 mt-2"
            >
              {loading ? (
                <span className="flex items-center gap-2">
                  <svg className="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                  </svg>
                  Signing in...
                </span>
              ) : (
                <>
                  Sign In <ArrowRight className="w-4 h-4" />
                </>
              )}
            </button>
          </form>

          {/* Register link */}
          <p className="mt-8 text-center text-text-primary/40 text-sm">
            Don&apos;t have an account?{' '}
            <a
              href="/register"
              className="text-gold hover:text-[#FFE55C] font-semibold transition-colors duration-300"
            >
              Create one
            </a>
          </p>
        </motion.div>
      </div>
    </div>
  );
}
