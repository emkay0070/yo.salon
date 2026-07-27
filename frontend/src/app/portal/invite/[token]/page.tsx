'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { Scissors, CheckCircle, ArrowRight } from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { motion } from 'framer-motion';

export default function PortalInvitePage({ params }: { params: { token: string } }) {
  const { token } = params;
  const [salonName, setSalonName] = useState('');
  const [email, setEmail] = useState('');
  const [name, setName] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const router = useRouter();

  useEffect(() => {
    const fetchInvite = async () => {
      try {
        const data = await apiClient.getInvitation(token);
        setSalonName(data.salon_name);
        if (data.email) setEmail(data.email);
        setLoading(false);
      } catch (err) {
        setError('Invalid or expired invitation link.');
        setLoading(false);
      }
    };
    fetchInvite();
  }, [token]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setSubmitting(true);

    try {
      await apiClient.acceptInvitation(token, { name, email, password });
      // On success, redirect to portal login
      router.push('/portal/login?message=Account created successfully');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to create account');
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#070707]">
        <div className="animate-pulse w-10 h-10 rounded-full bg-gold/20 border border-gold/30" />
      </div>
    );
  }

  if (error && !salonName) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#070707]">
        <div className="bg-card border border-border-light rounded-2xl p-8 max-w-md w-full text-center">
          <p className="text-rose-400 font-medium">{error}</p>
        </div>
      </div>
    );
  }

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
            <span className="text-text-primary text-xl font-bold tracking-tight">{salonName}</span>
          </div>

          <div>
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.3, duration: 0.8 }}
            >
              <p className="text-gold text-xs font-semibold tracking-[0.2em] uppercase mb-4">
                You've Been Invited
              </p>
              <h2 className="text-4xl font-bold text-text-primary leading-tight mb-6">
                Join {salonName}'s<br />
                <span className="text-transparent bg-clip-text bg-gradient-to-r from-[#FFD700] to-[#C9A227]">
                  Client Portal
                </span>
              </h2>
            </motion.div>
          </div>
        </div>
      </div>

      {/* ── Right: Form Panel ───────────────────────────────────────────── */}
      <div className="w-full lg:w-[50%] flex flex-col justify-center relative z-10">
        <div className="max-w-[440px] w-full mx-auto px-6 sm:px-10 py-12">
          
          <div className="mb-10 text-center lg:text-left">
            <h1 className="text-2xl sm:text-3xl font-bold text-text-primary tracking-tight mb-2">Create Account</h1>
            <p className="text-text-secondary text-sm">
              Complete your profile to manage bookings and track your visits at {salonName}.
            </p>
          </div>

          {error && (
            <div className="mb-6 p-4 bg-rose-500/10 border border-rose-500/20 rounded-xl text-rose-400 text-sm">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-5">
            <div>
              <label className="block text-xs font-medium text-text-secondary mb-2">Full Name</label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Jane Doe"
                required
                className="w-full bg-[#111111] border border-[#222222] text-text-primary rounded-xl px-4 py-3 focus:outline-none focus:border-[#FFD700]/50 focus:ring-1 focus:ring-[#FFD700]/50 transition-all text-sm"
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-text-secondary mb-2">Email Address</label>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="jane@example.com"
                required
                className="w-full bg-[#111111] border border-[#222222] text-text-primary rounded-xl px-4 py-3 focus:outline-none focus:border-[#FFD700]/50 focus:ring-1 focus:ring-[#FFD700]/50 transition-all text-sm"
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-text-secondary mb-2">Password</label>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                required
                minLength={8}
                className="w-full bg-[#111111] border border-[#222222] text-text-primary rounded-xl px-4 py-3 focus:outline-none focus:border-[#FFD700]/50 focus:ring-1 focus:ring-[#FFD700]/50 transition-all text-sm"
              />
            </div>

            <button
              type="submit"
              disabled={submitting}
              className="w-full bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black font-semibold rounded-xl px-4 py-3.5 hover:opacity-90 transition-all shadow-lg shadow-[#FFD700]/20 flex justify-center items-center gap-2 mt-4 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {submitting ? 'Creating account...' : 'Create Account'}
              {!submitting && <ArrowRight className="w-4 h-4" />}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}
