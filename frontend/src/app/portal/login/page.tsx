'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { motion } from 'framer-motion';
import { Crown, Mail, Lock, ArrowRight, Loader2 } from 'lucide-react';
import { portalApiClient } from '@/lib/portal-api-client';
import { usePortalAuth } from '@/contexts/PortalAuthContext';

export default function PortalLoginPage() {
  const router = useRouter();
  const { login } = usePortalAuth();
  const [formData, setFormData] = useState({
    email: '',
    password: '',
  });
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData(prev => ({ ...prev, [e.target.name]: e.target.value }));
    setError('');
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setIsLoading(true);

    try {
      await login(formData.email, formData.password);
      router.push('/portal/home');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Invalid credentials');
    } finally {
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

        <div className="bg-card border border-border-medium rounded-2xl p-8">
          <h1 className="text-2xl font-bold text-text-primary mb-2">Welcome Back</h1>
          <p className="text-text-secondary mb-6">Sign in to manage your appointments</p>

          <form onSubmit={handleSubmit} className="space-y-4">
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
                  placeholder="Enter your password"
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
                  Signing in...
                </>
              ) : (
                <>
                  Sign In
                  <ArrowRight className="w-5 h-5" />
                </>
              )}
            </button>
          </form>

          <div className="mt-6 text-center">
            <p className="text-text-secondary text-sm">
              Don't have an account?{' '}
              <button
                onClick={() => router.push('/portal/create-account')}
                className="text-[#FFD700] hover:underline"
              >
                Create one
              </button>
            </p>
          </div>
        </div>
      </motion.div>
    </div>
  );
}
