'use client';

import { useState } from 'react';
import { apiClient } from '@/lib/api-client';
import { Mail, ArrowLeft, Scissors, CheckCircle, ArrowRight } from 'lucide-react';
import { motion } from 'framer-motion';

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(false);
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      await apiClient.forgotPassword(email);
      setSuccess(true);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to send reset email');
    } finally {
      setLoading(false);
    }
  };

  if (success) {
    return (
      <div className="min-h-screen flex overflow-hidden bg-[#070707] items-center justify-center p-6 relative">
        <div className="absolute inset-0 bg-cover bg-center opacity-[0.03]" style={{ backgroundImage: `url('/images/salon-dark.jpg')` }} />
        <div className="absolute inset-0 bg-[#070707]/95" />

        <motion.div
          initial={{ opacity: 0, scale: 0.95 }}
          animate={{ opacity: 1, scale: 1 }}
          className="bg-card border border-border-light rounded-3xl p-10 shadow-2xl backdrop-blur-2xl w-full max-w-md text-center relative z-10"
        >
          <div className="flex items-center justify-center mb-8">
            <div className="bg-[#FFD700]/10 border border-[#FFD700]/20 rounded-full p-4">
              <CheckCircle className="w-10 h-10 text-gold" />
            </div>
          </div>
          <h1 className="text-3xl font-bold text-text-primary mb-4 tracking-tight">Check Your Email</h1>
          <p className="text-text-primary/60 mb-8 leading-relaxed">
            We've sent a password reset link to <span className="text-text-primary font-semibold">{email}</span>.
            Please check your inbox and follow the instructions.
          </p>
          <a
            href="/login"
            className="inline-flex items-center justify-center w-full py-4 rounded-2xl font-semibold text-black bg-gradient-to-r from-[#FFD700] to-[#C9A227] hover:brightness-110 active:scale-[0.98] transition-all duration-300 shadow-xl shadow-[#FFD700]/20"
          >
            Back to Login
          </a>
        </motion.div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex overflow-hidden bg-[#070707]">

      {/* ── Left: Image Panel ───────────────────────────────────────────── */}
      <div className="hidden lg:flex lg:w-[50%] relative overflow-hidden">
        <div
          className="absolute inset-0 bg-cover bg-center bg-no-repeat"
          style={{ backgroundImage: `url('/images/salon-station.jpg')` }}
        />
        <div className="absolute inset-0 bg-gradient-to-r from-[#070707]/20 via-[#070707]/10 to-[#070707]/75" />
        <div className="absolute inset-0 bg-gradient-to-t from-[#070707]/80 via-transparent to-[#070707]/40" />

        <div className="relative z-10 flex flex-col justify-between p-10 w-full h-full">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center shadow-xl shadow-[#FFD700]/20">
              <Scissors className="w-5 h-5 text-black" />
            </div>
            <span className="text-text-primary text-xl font-bold tracking-tight">Yo Salon</span>
          </div>

          <div className="mb-12">
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.3, duration: 0.8 }}
            >
              <h2 className="text-4xl font-bold text-text-primary leading-tight mb-4">
                Regain Access to<br />
                <span className="text-transparent bg-clip-text bg-gradient-to-r from-[#FFD700] to-[#C9A227]">
                  Your Dashboard
                </span>
              </h2>
              <p className="text-text-primary/60 text-lg max-w-sm">
                Enter your email and we'll help you securely reset your password.
              </p>
            </motion.div>
          </div>
        </div>
      </div>

      {/* ── Right: Form Panel ─────────────────────────────────────────── */}
      <div className="flex-1 flex items-center justify-center p-6 lg:p-12 relative">
        <div className="absolute inset-0 bg-cover bg-center opacity-[0.03]" style={{ backgroundImage: `url('/images/salon-dark.jpg')` }} />
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
          <div className="mb-10">
            <h1 className="text-4xl font-bold text-text-primary tracking-tight mb-2">
              Forgot Password
            </h1>
            <p className="text-text-primary/40 text-base">
              Enter your email to receive a reset link
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

          <form onSubmit={handleSubmit} className="space-y-6">
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
                  className="w-full pl-11 pr-4 py-4 rounded-2xl text-text-primary placeholder-white/20 bg-card border border-border-light focus:outline-none focus:border-[#FFD700]/50 focus:bg-white/8 transition-all duration-300 text-sm"
                  placeholder="your@email.com"
                  required
                />
              </div>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full py-4 rounded-2xl font-semibold text-black bg-gradient-to-r from-[#FFD700] to-[#C9A227] hover:brightness-110 active:scale-[0.98] transition-all duration-300 shadow-2xl shadow-[#FFD700]/20 flex items-center justify-center gap-2"
            >
              {loading ? (
                <span className="flex items-center gap-2">
                  <svg className="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                  </svg>
                  Sending...
                </span>
              ) : (
                <>Send Reset Link <ArrowRight className="w-4 h-4" /></>
              )}
            </button>
          </form>

          <div className="mt-8">
            <a
              href="/login"
              className="inline-flex items-center text-text-primary/40 hover:text-gold text-sm font-medium transition-colors group"
            >
              <ArrowLeft className="w-4 h-4 mr-2 group-hover:-translate-x-1 transition-transform" />
              Back to Login
            </a>
          </div>
        </motion.div>
      </div>
    </div>
  );
}
