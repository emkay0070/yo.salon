'use client';

import { use, useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { Scissors, ArrowRight, Sparkles, XCircle, CheckCircle, Briefcase, MapPin } from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { motion } from 'framer-motion';

interface InvitationData {
  salon_name: string;
  role: string;
  location?: string;
  email: string;
  invited_by?: string;
}

export default function SpecialistInvitePage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = use(params);
  const [invitation, setInvitation] = useState<InvitationData | null>(null);
  const [email, setEmail] = useState('');
  const [name, setName] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [showRegistration, setShowRegistration] = useState(false);
  const [showSuccess, setShowSuccess] = useState(false);
  const router = useRouter();

  useEffect(() => {
    const fetchInvite = async () => {
      try {
        const data = await apiClient.getInvitation(token);
        setInvitation(data);
        if (data.email) setEmail(data.email);
        setLoading(false);
      } catch (err) {
        setError('Invalid or expired invitation link.');
        setLoading(false);
      }
    };
    fetchInvite();
  }, [token]);

  const handleAccept = () => {
    setShowRegistration(true);
  };

  const handleDecline = () => {
    router.push('/');
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setSubmitting(true);

    try {
      await apiClient.acceptInvitation(token, { name, email, password });
      setShowSuccess(true);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to create account');
      setSubmitting(false);
    }
  };

  const handleContinue = () => {
    router.push('/specialist-portal/login');
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#070707]">
        <div className="animate-pulse w-10 h-10 rounded-full bg-gold/20 border border-gold/30" />
      </div>
    );
  }

  if (error && !invitation) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#070707]">
        <div className="bg-card border border-border-light rounded-2xl p-8 max-w-md w-full text-center">
          <XCircle className="w-12 h-12 text-rose-400 mx-auto mb-4" />
          <p className="text-rose-400 font-medium mb-2">This invitation is no longer available</p>
          <p className="text-text-secondary text-sm mb-6">It may have expired, been accepted, or been cancelled.</p>
          <button
            onClick={() => router.push('/')}
            className="text-gold hover:text-gold/80 transition-colors text-sm font-medium"
          >
            Go to Yo.Salon
          </button>
        </div>
      </div>
    );
  }

  // Success Screen
  if (showSuccess) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#070707]">
        <motion.div
          initial={{ opacity: 0, scale: 0.95 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 0.3 }}
          className="bg-card border border-border-light rounded-2xl p-8 max-w-md w-full text-center"
        >
          <CheckCircle className="w-16 h-16 text-gold mx-auto mb-6" />
          <h2 className="text-2xl font-bold text-text-primary mb-2">You're in.</h2>
          <p className="text-text-secondary text-sm mb-4">
            Welcome to <span className="text-gold font-medium">{invitation?.salon_name}</span>.
          </p>
          <p className="text-text-secondary text-sm mb-6">
            Your specialist account is ready and you've joined the team.
          </p>
          <button
            onClick={handleContinue}
            className="w-full bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black font-semibold rounded-xl px-4 py-3.5 hover:opacity-90 transition-all shadow-lg shadow-[#FFD700]/20"
          >
            Continue to Specialist Portal
          </button>
        </motion.div>
      </div>
    );
  }

  // Registration Form
  if (showRegistration) {
    return (
      <div className="min-h-screen flex overflow-hidden bg-[#070707]">
        <div className="hidden lg:flex lg:w-[50%] relative overflow-hidden">
          <div
            className="absolute inset-0 bg-cover bg-center bg-no-repeat"
            style={{ backgroundImage: `url('/images/salon-styling.jpg')` }}
          />
          <div className="absolute inset-0 bg-gradient-to-r from-[#070707]/20 via-[#070707]/10 to-[#070707]/75" />
          <div className="absolute inset-0 bg-gradient-to-t from-[#070707]/80 via-transparent to-[#070707]/40" />

          <div className="relative z-10 flex flex-col justify-between p-10 w-full">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center shadow-xl shadow-[#FFD700]/20">
                <Scissors className="w-5 h-5 text-black" />
              </div>
              <span className="text-text-primary text-xl font-bold tracking-tight">{invitation?.salon_name}</span>
            </div>

            <div>
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.3, duration: 0.8 }}
              >
                <p className="text-gold text-xs font-semibold tracking-[0.2em] uppercase mb-4">
                  Welcome to Yo.Salon
                </p>
                <h2 className="text-4xl font-bold text-text-primary leading-tight mb-6">
                  Let's get your<br />
                  <span className="text-transparent bg-clip-text bg-gradient-to-r from-[#FFD700] to-[#C9A227]">
                    profile ready
                  </span>
                </h2>
              </motion.div>
            </div>
          </div>
        </div>

        <div className="w-full lg:w-[50%] flex flex-col justify-center relative z-10">
          <div className="max-w-[440px] w-full mx-auto px-6 sm:px-10 py-12">
            <div className="mb-10 text-center lg:text-left">
              <div className="flex items-center justify-center lg:justify-start gap-2 mb-3">
                <Sparkles className="w-5 h-5 text-gold" />
                <h1 className="text-2xl sm:text-3xl font-bold text-text-primary tracking-tight">Create Your Profile</h1>
              </div>
              <p className="text-text-secondary text-sm">
                Complete your specialist profile to access your dedicated workspace and manage your appointments.
              </p>
            </div>

            {error && (
              <div className="mb-6 p-4 bg-rose-500/10 border border-rose-500/20 rounded-xl text-rose-400 text-sm">
                {error}
              </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-5">
              <div>
                <label className="block text-xs font-medium text-text-secondary mb-2">Email</label>
                <div className="relative">
                  <input
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="jane@example.com"
                    required
                    className="w-full bg-[#111111] border border-[#222222] text-text-primary rounded-xl px-4 py-3 focus:outline-none focus:border-[#FFD700]/50 focus:ring-1 focus:ring-[#FFD700]/50 transition-all text-sm"
                  />
                  {email && (
                    <div className="absolute right-3 top-1/2 -translate-y-1/2 text-emerald-400 text-xs flex items-center gap-1">
                      <CheckCircle className="w-3 h-3" />
                      <span>This invitation was sent to this email</span>
                    </div>
                  )}
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-text-secondary mb-2">Your name</label>
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
                <label className="block text-xs font-medium text-text-secondary mb-2">Create a password</label>
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
                {submitting ? 'Creating profile...' : 'Create profile & accept invitation'}
                {!submitting && <ArrowRight className="w-4 h-4" />}
              </button>
            </form>
          </div>
        </div>
      </div>
    );
  }

  // Invitation Preview Screen (Specialist-specific)
  return (
    <div className="min-h-screen flex overflow-hidden bg-[#070707]">
      <div className="hidden lg:flex lg:w-1/2 relative overflow-hidden">
        <div
          className="absolute inset-0 bg-cover bg-center bg-no-repeat"
          style={{ backgroundImage: `url('/images/salon-styling.jpg')` }}
        />
        <div className="absolute inset-0 bg-gradient-to-r from-[#070707]/30 via-[#070707]/20 to-[#070707]/90" />
        <div className="absolute inset-0 bg-gradient-to-t from-[#070707]/90 via-transparent to-[#070707]/50" />

        <div className="relative z-10 flex flex-col justify-between p-12 w-full">
          <div className="flex items-center gap-4">
            <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center shadow-2xl shadow-[#FFD700]/30">
              <Scissors className="w-7 h-7 text-black" />
            </div>
            <span className="text-text-primary text-2xl font-bold tracking-tight">{invitation?.salon_name}</span>
          </div>

          <div>
            <motion.div
              initial={{ opacity: 0, y: 30 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.3, duration: 0.8 }}
            >
              <p className="text-gold text-sm font-semibold tracking-[0.3em] uppercase mb-6">
                Specialist Invitation
              </p>
              <h2 className="text-5xl font-bold text-text-primary leading-tight mb-6">
                <span className="text-gold">{invitation?.salon_name}</span> wants you on their team
              </h2>
              <p className="text-text-secondary text-lg max-w-md">
                Build your professional identity and grow your career with {invitation?.salon_name}.
              </p>
            </motion.div>
          </div>
        </div>
      </div>

      <div className="w-full lg:w-1/2 flex flex-col justify-center relative z-10 p-8 lg:p-16">
        <motion.div
          initial={{ opacity: 0, x: 30 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ duration: 0.6 }}
          className="max-w-xl mx-auto w-full"
        >
          <div className="lg:hidden text-center mb-10">
            <div className="w-20 h-20 rounded-2xl bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center shadow-xl shadow-[#FFD700]/20 mx-auto mb-6">
              <Briefcase className="w-10 h-10 text-black" />
            </div>
            <h1 className="text-4xl font-bold text-text-primary mb-3"><span className="text-gold">{invitation?.salon_name}</span> wants you on their team</h1>
            <p className="text-text-secondary text-lg">Specialist invitation</p>
          </div>

          <div className="bg-[#111111] border border-[#222211] rounded-3xl p-8 mb-8">
            <div className="grid md:grid-cols-2 gap-6 mb-8">
              <div className="flex items-start gap-4">
                <div className="w-16 h-16 rounded-2xl bg-gold/10 border border-gold/20 flex items-center justify-center flex-shrink-0">
                  <Briefcase className="w-8 h-8 text-gold" />
                </div>
                <div className="flex-1">
                  <p className="text-text-primary font-medium mb-2 text-sm">Your role</p>
                  <p className="text-gold font-bold text-2xl capitalize">Specialist</p>
                </div>
              </div>

              {invitation?.location && (
                <div className="flex items-start gap-4">
                  <div className="w-16 h-16 rounded-2xl bg-gold/10 border border-gold/20 flex items-center justify-center flex-shrink-0">
                    <MapPin className="w-8 h-8 text-gold" />
                  </div>
                  <div className="flex-1">
                    <p className="text-text-primary font-medium mb-2 text-sm">Location</p>
                    <p className="text-text-secondary text-xl font-medium">{invitation.location}</p>
                  </div>
                </div>
              )}
            </div>

            <div className="border-t border-[#222211] pt-8">
              <p className="text-text-secondary text-sm mb-6 uppercase tracking-wider">Build your professional identity</p>
              <ul className="grid sm:grid-cols-2 gap-4 text-base text-text-secondary">
                <li className="flex items-center gap-3">
                  <CheckCircle className="w-6 h-6 text-emerald-400 flex-shrink-0" />
                  Manage your craft
                </li>
                <li className="flex items-center gap-3">
                  <CheckCircle className="w-6 h-6 text-emerald-400 flex-shrink-0" />
                  Grow your career
                </li>
                <li className="flex items-center gap-3">
                  <CheckCircle className="w-6 h-6 text-emerald-400 flex-shrink-0" />
                  Access your dedicated workspace
                </li>
                <li className="flex items-center gap-3">
                  <CheckCircle className="w-6 h-6 text-emerald-400 flex-shrink-0" />
                  Manage your appointments
                </li>
              </ul>
            </div>
          </div>

          <div className="flex gap-4">
            <button
              onClick={handleAccept}
              className="flex-1 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black font-bold rounded-2xl px-6 py-5 hover:opacity-90 transition-all shadow-xl shadow-[#FFD700]/30 flex justify-center items-center gap-3 text-lg"
            >
              Accept & join
              <ArrowRight className="w-6 h-6" />
            </button>
            <button
              onClick={handleDecline}
              className="flex-1 bg-transparent border-2 border-border-light text-text-secondary font-semibold rounded-2xl px-6 py-5 hover:bg-[#111111] hover:border-gold/30 transition-all text-lg"
            >
              Decline
            </button>
          </div>

          <div className="mt-10 text-center">
            <p className="text-text-secondary text-sm">This invitation was sent to <span className="text-gold font-medium">{invitation?.email}</span></p>
          </div>
        </motion.div>
      </div>
    </div>
  );
}
