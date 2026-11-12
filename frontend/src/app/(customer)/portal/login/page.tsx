'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { motion } from 'framer-motion';
import {
  Crown, Mail, Lock, ArrowRight, Loader2, ArrowLeft,
  Star, User, Scissors, Building2,
} from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { GlassCard } from '@/components/ui/glass-card';

export default function PortalLoginPage() {
  const router = useRouter();
  const { login: portalLogin } = usePortalAuth();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setIsLoading(true);

    try {
      await portalLogin(email, password);
      router.push('/portal/home');
    } catch (err: any) {
      const msg = err?.response?.data?.message;
      setError(msg || 'Invalid email or password. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen w-full bg-[#070707] flex flex-col md:grid md:grid-cols-12 overflow-x-hidden relative font-poppins text-white">

      {/* ── LEFT PANEL ── */}
      <div className="col-span-12 md:col-span-5 lg:col-span-4 flex flex-col justify-between p-6 sm:p-10 z-10 bg-[#070707] border-r border-white/5 min-h-screen">

        {/* Header */}
        <div className="flex items-center justify-between">
          <button
            onClick={() => router.push('/')}
            className="flex items-center gap-2 text-xs text-white/40 hover:text-white transition-colors duration-300 group"
          >
            <ArrowLeft className="w-4 h-4 group-hover:-translate-x-1 transition-transform" />
            <span>Back to home</span>
          </button>
        </div>

        <div className="w-full max-w-sm mx-auto my-auto py-10">

          {/* Logo */}
          <div className="flex items-center gap-3 mb-8">
            <div className="w-11 h-11 rounded-xl bg-gradient-to-br from-amber-400 to-yellow-500 flex items-center justify-center shadow-xl">
              <Crown className="w-5 h-5 text-black" />
            </div>
            <div>
              <span className="font-sora font-bold text-base tracking-wider text-white block">Yo.Salon</span>
              <span className="text-[10px] text-white/30 uppercase tracking-widest font-bold">Customer Portal</span>
            </div>
          </div>

          <motion.div
            initial={{ opacity: 0, x: -12 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ duration: 0.25 }}
          >
            <h1 className="text-2xl font-bold font-sora text-white mb-1 tracking-tight">Welcome back</h1>
            <p className="text-white/40 text-sm mb-8">Sign in to book and manage your appointments</p>

            <form onSubmit={handleSubmit} className="space-y-4">
              {/* Email */}
              <div>
                <label className="block text-xs font-semibold text-white/60 mb-1.5 uppercase tracking-wider">Email Address</label>
                <div className="relative">
                  <Mail className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" />
                  <input
                    id="login-email"
                    type="email"
                    value={email}
                    onChange={e => { setEmail(e.target.value); setError(''); }}
                    className="w-full pl-10 pr-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-white placeholder:text-white/20 focus:outline-none focus:border-amber-400/60 hover:border-white/20 transition-colors"
                    placeholder="you@example.com"
                    required
                    autoComplete="email"
                  />
                </div>
              </div>

              {/* Password */}
              <div>
                <label className="block text-xs font-semibold text-white/60 mb-1.5 uppercase tracking-wider">Password</label>
                <div className="relative">
                  <Lock className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" />
                  <input
                    id="login-password"
                    type="password"
                    value={password}
                    onChange={e => { setPassword(e.target.value); setError(''); }}
                    className="w-full pl-10 pr-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-white placeholder:text-white/20 focus:outline-none focus:border-amber-400/60 hover:border-white/20 transition-colors"
                    placeholder="••••••••"
                    required
                    autoComplete="current-password"
                  />
                </div>
              </div>

              {/* Error */}
              {error && (
                <motion.div
                  initial={{ opacity: 0, y: -4 }}
                  animate={{ opacity: 1, y: 0 }}
                  className="p-3 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-xs font-medium"
                >
                  {error}
                </motion.div>
              )}

              {/* Submit */}
              <button
                type="submit"
                disabled={isLoading}
                className="w-full py-3 bg-gradient-to-r from-amber-400 to-yellow-500 text-black rounded-xl font-semibold hover:brightness-110 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 cursor-pointer shadow-lg"
              >
                {isLoading ? (
                  <>
                    <Loader2 className="w-4 h-4 animate-spin text-black" />
                    <span>Signing in...</span>
                  </>
                ) : (
                  <>
                    <span>Sign In</span>
                    <ArrowRight className="w-4 h-4 text-black" />
                  </>
                )}
              </button>
            </form>

            <div className="mt-6 pt-5 border-t border-white/5 text-center">
              <p className="text-white/40 text-xs">
                No account yet?{' '}
                <button
                  type="button"
                  onClick={() => router.push('/portal/create-account')}
                  className="text-amber-400 font-semibold hover:underline bg-transparent border-none cursor-pointer"
                >
                  Create one
                </button>
              </p>
            </div>

            {/* Other login links */}
            <div className="mt-8 pt-6 border-t border-white/5 space-y-3">
              <p className="text-white/30 text-xs text-center uppercase tracking-wider">Other portals</p>
              <div className="space-y-2">
                <button
                  type="button"
                  onClick={() => router.push('/specialist-portal/login')}
                  className="w-full flex items-center justify-center gap-2 p-3 rounded-xl bg-white/5 border border-white/10 hover:bg-white/8 hover:border-white/20 transition-all text-white/60 hover:text-white text-xs"
                >
                  <Scissors className="w-4 h-4" />
                  <span>Specialist Portal</span>
                </button>
                <button
                  type="button"
                  onClick={() => router.push('/login')}
                  className="w-full flex items-center justify-center gap-2 p-3 rounded-xl bg-white/5 border border-white/10 hover:bg-white/8 hover:border-white/20 transition-all text-white/60 hover:text-white text-xs"
                >
                  <Building2 className="w-4 h-4" />
                  <span>Salon / Staff Portal</span>
                </button>
              </div>
            </div>
          </motion.div>
        </div>

        {/* Footer */}
        <p className="text-[10px] text-white/20">Powered by Yo.Salon · EmKay Studios · SSL Secured</p>
      </div>

      {/* ── RIGHT PANEL ── */}
      <div className="hidden md:block md:col-span-7 lg:col-span-8 relative h-screen overflow-hidden">
        <div className="absolute inset-0 bg-[#070707] z-0" />
        <img
          src="/images/salon-auth.jpg"
          alt="Premium Salon Interior"
          className="absolute inset-0 w-full h-full object-cover opacity-55 mix-blend-luminosity hover:mix-blend-normal transition-all duration-1000"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-black via-black/30 to-transparent z-10 pointer-events-none" />
        <div className="absolute inset-0 bg-gradient-to-r from-black/80 via-transparent to-transparent z-10 pointer-events-none" />

        <div className="absolute bottom-12 left-12 right-12 z-20 max-w-lg">
          <GlassCard className="p-8 border border-white/10 backdrop-blur-xl bg-black/45" elevation={3}>
            <div className="flex gap-1 mb-4">
              {[...Array(5)].map((_, i) => (
                <Star key={i} className="w-4 h-4 text-amber-400 fill-amber-400" />
              ))}
            </div>
            <p className="text-white/80 text-sm leading-relaxed mb-6 font-medium font-sora italic">
              "Yo.Salon transformed how we interact with our customers. The generated spatial booking
              interface alone increased our reservations by 34% in less than three weeks."
            </p>
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-full bg-gradient-to-tr from-amber-400 to-orange-500 flex items-center justify-center text-xs font-bold text-black font-sora">
                JN
              </div>
              <div className="flex flex-col">
                <span className="text-xs font-bold text-white">Julian Noir</span>
                <span className="text-[10px] text-white/40">Master Stylist, The Atelier Noir</span>
              </div>
            </div>
          </GlassCard>
        </div>
      </div>

    </div>
  );
}
