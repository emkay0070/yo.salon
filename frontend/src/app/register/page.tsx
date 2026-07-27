'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Mail, Lock, User, Eye, EyeOff, Scissors, ArrowRight, CheckCircle, Palette } from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { motion } from 'framer-motion';

export default function RegisterPage() {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const router = useRouter();

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
      const data = await apiClient.register({ name, email, password });
      // api-client stores the token; we just follow the backend's route instruction
      router.push(data.next_route || '/onboarding');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Registration failed');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const features = [
    'Unlimited bookings & scheduling',
    'Staff & service management',
    'Revenue analytics & reports',
    'Client database & history',
  ];

  return (
    <div className="min-h-screen flex overflow-hidden bg-[#070707]">

      {/* ── Left: Image Panel ───────────────────────────────────────────── */}
      <div className="hidden lg:flex lg:w-[50%] relative overflow-hidden">
        <div
          className="absolute inset-0 bg-cover bg-center bg-no-repeat"
          style={{ backgroundImage: `url('/images/salon-barber.jpg')` }}
        />
        <div className="absolute inset-0 bg-gradient-to-r from-[#070707]/20 via-[#070707]/10 to-[#070707]/75" />
        <div className="absolute inset-0 bg-gradient-to-t from-[#070707]/80 via-transparent to-[#070707]/40" />

        <div className="relative z-10 flex flex-col justify-between p-10 w-full">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center shadow-xl shadow-[#FFD700]/20">
              <Scissors className="w-5 h-5 text-black" />
            </div>
            <span className="text-text-primary text-xl font-bold tracking-tight">Yo Salon</span>
          </div>

          <div>
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.3, duration: 0.8 }}
            >
              <p className="text-gold text-xs font-semibold tracking-[0.2em] uppercase mb-4">
                Everything you need
              </p>
              <h2 className="text-4xl font-bold text-text-primary leading-tight mb-6">
                Grow Your<br />
                <span className="text-transparent bg-clip-text bg-gradient-to-r from-[#FFD700] to-[#C9A227]">
                  Salon Business
                </span>
              </h2>

              <div className="space-y-3">
                {features.map((feature, i) => (
                  <motion.div
                    key={feature}
                    initial={{ opacity: 0, x: -10 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ delay: 0.5 + i * 0.1 }}
                    className="flex items-center gap-3"
                  >
                    <div className="w-5 h-5 rounded-full bg-[#FFD700]/20 border border-[#FFD700]/40 flex items-center justify-center flex-shrink-0">
                      <CheckCircle className="w-3 h-3 text-gold" />
                    </div>
                    <span className="text-text-primary/70 text-sm">{feature}</span>
                  </motion.div>
                ))}
              </div>
            </motion.div>
          </div>
        </div>
      </div>

      {/* ── Right: Register Form ─────────────────────────────────────────── */}
      <div className="flex-1 flex items-center justify-center p-6 lg:p-12 relative overflow-y-auto">
        <div className="absolute inset-0 bg-[#070707]/95" />

        {/* Mobile logo & Theme Switcher */}
        <div className="absolute top-6 left-6 right-6 flex items-center justify-between z-10">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center">
              <Scissors className="w-4 h-4 text-black" />
            </div>
            <span className="text-white font-bold tracking-tight">Yo Salon</span>
          </div>
          <button className="p-2 rounded-xl bg-white/5 border border-white/10 text-white/60 hover:text-white transition-colors">
            <Palette className="w-5 h-5" />
          </button>
        </div>

        <motion.div
          initial={{ opacity: 0, x: 20 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
          className="w-full max-w-md relative z-10 py-16 lg:py-0"
        >
          <div className="mb-8">
            <h1 className="text-4xl font-bold text-white tracking-tight mb-2">
              Create account
            </h1>
            <p className="text-white/50 text-base">
              Start your free salon management journey
            </p>
          </div>

          {error && (
            <motion.div
              initial={{ opacity: 0, y: -8 }}
              animate={{ opacity: 1, y: 0 }}
              className="bg-red-500/10 border border-red-500/20 rounded-2xl p-4 mb-6"
            >
              <p className="text-red-400 text-sm">{error}</p>
            </motion.div>
          )}

          <form onSubmit={handleSubmit} className="space-y-4">
            {/* Full Name */}
            <div className="space-y-2">
              <label className="text-xs font-semibold tracking-widest uppercase text-text-primary/40">Full Name</label>
              <div className="relative group">
                <User className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-text-primary/30 group-focus-within:text-gold transition-colors duration-300" />
                <input
                  type="text"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Jane Doe"
                  required
                  className="w-full pl-11 pr-4 py-4 rounded-2xl text-text-primary placeholder-white/20 bg-card border border-border-light focus:outline-none focus:border-[#FFD700]/50 focus:bg-white/8 transition-all duration-300 text-sm"
                />
              </div>
            </div>

            {/* Email */}
            <div className="space-y-2">
              <label className="text-xs font-semibold tracking-widest uppercase text-text-primary/40">Email Address</label>
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
              <label className="text-xs font-semibold tracking-widest uppercase text-text-primary/40">Password</label>
              <div className="relative group">
                <Lock className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-text-primary/30 group-focus-within:text-gold transition-colors duration-300" />
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Min. 8 characters"
                  required
                  className="w-full pl-11 pr-14 py-4 rounded-2xl text-text-primary placeholder-white/20 bg-card border border-border-light focus:outline-none focus:border-[#FFD700]/50 focus:bg-white/8 transition-all duration-300 text-sm"
                />
                <button type="button" onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-4 top-1/2 -translate-y-1/2 text-text-primary/30 hover:text-gold transition-colors duration-300">
                  {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            {/* Confirm Password */}
            <div className="space-y-2">
              <label className="text-xs font-semibold tracking-widest uppercase text-text-primary/40">Confirm Password</label>
              <div className="relative group">
                <Lock className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-text-primary/30 group-focus-within:text-gold transition-colors duration-300" />
                <input
                  type={showConfirmPassword ? 'text' : 'password'}
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  placeholder="••••••••"
                  required
                  className="w-full pl-11 pr-14 py-4 rounded-2xl text-text-primary placeholder-white/20 bg-card border border-border-light focus:outline-none focus:border-[#FFD700]/50 focus:bg-white/8 transition-all duration-300 text-sm"
                />
                <button type="button" onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                  className="absolute right-4 top-1/2 -translate-y-1/2 text-text-primary/30 hover:text-gold transition-colors duration-300">
                  {showConfirmPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
            </div>

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
                  Creating account...
                </span>
              ) : (
                <> Create Account <ArrowRight className="w-4 h-4" /> </>
              )}
            </button>
          </form>

          <p className="mt-8 text-center text-text-primary/40 text-sm">
            Already have an account?{' '}
            <a href="/login" className="text-gold hover:text-[#FFE55C] font-semibold transition-colors duration-300">
              Sign in
            </a>
          </p>
        </motion.div>
      </div>
    </div>
  );
}
