'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { motion } from 'framer-motion';
import { Crown, Mail, Lock, ArrowRight, Loader2, ArrowLeft, Star } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { GlassButton } from '@/components/ui/glass-button';
import { GlassCard } from '@/components/ui/glass-card';

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
    <div className="min-h-screen w-full bg-[#070707] flex flex-col md:grid md:grid-cols-12 overflow-x-hidden relative font-poppins text-white">
      
      {/* LEFT COLUMN: AUTH FORM PANEL */}
      <div className="col-span-12 md:col-span-5 lg:col-span-4 flex flex-col justify-between p-6 sm:p-10 z-10 bg-[#070707] border-r border-white/5 min-h-screen">
        
        {/* Top Header Section */}
        <div className="flex items-center justify-between">
          <button 
            onClick={() => router.push('/welcome')}
            className="flex items-center gap-2 text-xs text-white/40 hover:text-gold transition-colors duration-300 group"
          >
            <ArrowLeft className="w-4 h-4 group-hover:-translate-x-1 transition-transform" />
            <span>Back to landing</span>
          </button>
          
          <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-gold to-[#C9A227] flex items-center justify-center shadow-lg md:hidden">
            <Crown className="w-4 h-4 text-black" />
          </div>
        </div>

        {/* Center Auth Card Form Container */}
        <div className="w-full max-w-sm mx-auto my-auto py-10">
          {/* Logo / Header */}
          <div className="flex items-center gap-3 mb-8">
            <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-gold to-[#C9A227] flex items-center justify-center shadow-xl shadow-gold/10">
              <Crown className="w-6 h-6 text-black" />
            </div>
            <div className="flex flex-col">
              <span className="font-sora font-bold text-base tracking-wider text-white">Yo.Salon</span>
              <span className="text-[10px] text-white/30 uppercase tracking-widest font-bold">Portal Access</span>
            </div>
          </div>

          <h1 className="text-2xl font-bold font-sora text-white mb-2 tracking-tight">Welcome Back</h1>
          <p className="text-white/40 text-xs sm:text-sm mb-8">Sign in to manage your appointments, team roster, and client settings.</p>

          <form onSubmit={handleSubmit} className="space-y-5">
            <div>
              <label className="block text-xs font-semibold text-white/60 mb-2 uppercase tracking-wider">Email Address</label>
              <div className="relative">
                <Mail className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-white/30" />
                <input
                  type="email"
                  name="email"
                  value={formData.email}
                  onChange={handleChange}
                  className="w-full pl-11 pr-4 py-3 bg-white/5 border border-white/10 rounded-xl text-sm text-white placeholder:text-white/20 focus:outline-none focus:border-gold hover:border-white/20 transition-colors"
                  placeholder="name@yoursalon.com"
                  required
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-white/60 mb-2 uppercase tracking-wider">Password</label>
              <div className="relative">
                <Lock className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-white/30" />
                <input
                  type="password"
                  name="password"
                  value={formData.password}
                  onChange={handleChange}
                  className="w-full pl-11 pr-4 py-3 bg-white/5 border border-white/10 rounded-xl text-sm text-white placeholder:text-white/20 focus:outline-none focus:border-gold hover:border-white/20 transition-colors"
                  placeholder="••••••••"
                  required
                />
              </div>
            </div>

            {error && (
              <motion.div 
                initial={{ opacity: 0, y: -5 }}
                animate={{ opacity: 1, y: 0 }}
                className="p-3 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-xs font-medium"
              >
                {error}
              </motion.div>
            )}

            <button
              type="submit"
              disabled={isLoading}
              className="w-full py-3.5 bg-gradient-to-r from-gold to-[#C9A227] text-black rounded-xl font-semibold hover:brightness-110 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-gold/15"
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

          <div className="mt-8 text-center border-t border-white/5 pt-6">
            <p className="text-white/40 text-xs sm:text-sm">
              Don't have a portal account?{' '}
              <button
                type="button"
                onClick={() => router.push('/portal/create-account')}
                className="text-gold font-semibold hover:underline bg-transparent border-none cursor-pointer"
              >
                Create one
              </button>
            </p>
          </div>
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
        {/* Dynamic ambient background spotlight */}
        <div className="absolute inset-0 bg-[#070707] z-0" />
        
        {/* Salon Interior image */}
        <img 
          src="/images/salon-auth.jpg" 
          alt="Premium Salon Interior" 
          className="absolute inset-0 w-full h-full object-cover opacity-60 mix-blend-luminosity hover:mix-blend-normal transition-all duration-1000"
        />

        {/* Subtle radial dark overlay shadow to isolate the text overlays */}
        <div className="absolute inset-0 bg-gradient-to-t from-black via-black/30 to-transparent z-10 pointer-events-none" />
        <div className="absolute inset-0 bg-gradient-to-r from-black/80 via-transparent to-transparent z-10 pointer-events-none" />

        {/* Floating Quote overlay card */}
        <div className="absolute bottom-12 left-12 right-12 z-20 max-w-lg">
          <GlassCard className="p-8 border border-white/10 backdrop-blur-xl bg-black/45" elevation={3}>
            {/* Stars */}
            <div className="flex gap-1 mb-4">
              {[...Array(5)].map((_, i) => (
                <Star key={i} className="w-4 h-4 text-gold fill-gold" />
              ))}
            </div>
            
            {/* Quote Slogan */}
            <p className="text-white/80 text-sm leading-relaxed mb-6 font-medium font-sora italic">
              "Yo.Salon transformed how we interact with our customers. The generated spatial booking interface alone increased our reservations by 34% in less than three weeks."
            </p>
            
            {/* Author info */}
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-full bg-gradient-to-tr from-gold to-orange-500 flex items-center justify-center text-xs font-bold text-black font-sora">
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
