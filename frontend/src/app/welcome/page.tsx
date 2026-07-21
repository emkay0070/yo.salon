'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { motion } from 'framer-motion';
import { Crown, Calendar, User, ArrowRight, Sparkles } from 'lucide-react';

export default function WelcomePage() {
  const router = useRouter();
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  const paths = [
    {
      id: 'book',
      title: 'Book an Appointment',
      description: 'Fast booking. No account required.',
      icon: Calendar,
      color: 'from-emerald-500 to-emerald-600',
      bgColor: 'bg-emerald-500/10',
      borderColor: 'border-emerald-500/30',
      textColor: 'text-emerald-400',
      action: () => router.push('/book'),
    },
    {
      id: 'signin',
      title: 'Sign In',
      description: 'Manage appointments, loyalty, and receipts.',
      icon: User,
      color: 'from-blue-500 to-blue-600',
      bgColor: 'bg-blue-500/10',
      borderColor: 'border-blue-500/30',
      textColor: 'text-blue-400',
      action: () => router.push('/portal/login'),
    },
    {
      id: 'create',
      title: 'Create Account',
      description: 'Create your salon owner account.',
      icon: Sparkles,
      color: 'from-[#FFD700] to-[#C9A227]',
      bgColor: 'bg-[#FFD700]/10',
      borderColor: 'border-[#FFD700]/30',
      textColor: 'text-[#FFD700]',
      action: () => router.push('/register'),
    },
  ];

  return (
    <div className="min-h-screen flex items-center justify-center p-4 bg-[#070707]">
      {/* Background gradient */}
      <div className="absolute inset-0 bg-gradient-to-br from-[#FFD700]/10 via-[#C9A227]/5 to-transparent" />
      
      {/* Animated background elements */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        {mounted && [...Array(6)].map((_, i) => (
          <motion.div
            key={i}
            className="absolute w-64 h-64 bg-[#FFD700]/5 rounded-full blur-3xl"
            initial={{
              x: Math.random() * window.innerWidth,
              y: Math.random() * window.innerHeight,
              scale: 0,
            }}
            animate={{
              x: [null, Math.random() * window.innerWidth],
              y: [null, Math.random() * window.innerHeight],
              scale: [0, 1, 0],
            }}
            transition={{
              duration: 20 + i * 5,
              repeat: Infinity,
              ease: 'easeInOut',
            }}
          />
        ))}
      </div>

      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.6 }}
        className="relative z-10 w-full max-w-4xl"
      >
        {/* Logo */}
        <motion.div
          initial={{ opacity: 0, scale: 0.8 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ delay: 0.2, duration: 0.5 }}
          className="flex items-center justify-center gap-3 mb-12"
        >
          <div className="w-20 h-20 rounded-2xl bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center shadow-2xl shadow-[#FFD700]/20">
            <Crown className="w-10 h-10 text-black" />
          </div>
        </motion.div>

        {/* Headline */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3, duration: 0.5 }}
          className="text-center mb-12"
        >
          <h1 className="text-4xl md:text-5xl font-bold text-text-primary mb-4">
            Welcome to Yo.Salon
          </h1>
          <p className="text-xl text-text-secondary">
            Premium salon management, beautifully simple.
          </p>
        </motion.div>

        {/* Path Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {paths.map((path, index) => {
            const Icon = path.icon;
            return (
              <motion.button
                key={path.id}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.4 + index * 0.1, duration: 0.5 }}
                whileHover={{ y: -8 }}
                whileTap={{ scale: 0.98 }}
                onClick={path.action}
                className={`relative overflow-hidden rounded-2xl border-2 p-8 text-left transition-all duration-300 ${path.borderColor} ${path.bgColor} hover:border-opacity-50 group`}
              >
                {/* Icon */}
                <div className={`w-14 h-14 rounded-xl bg-gradient-to-br ${path.color} flex items-center justify-center mb-6 shadow-lg`}>
                  <Icon className="w-7 h-7 text-white" />
                </div>

                {/* Content */}
                <h2 className="text-2xl font-bold text-text-primary mb-3">
                  {path.title}
                </h2>
                <p className="text-text-secondary mb-6">
                  {path.description}
                </p>

                {/* Arrow */}
                <div className={`flex items-center gap-2 ${path.textColor} font-medium group-hover:gap-3 transition-all`}>
                  <span>Continue</span>
                  <ArrowRight className="w-5 h-5" />
                </div>

                {/* Hover glow effect */}
                <div className={`absolute inset-0 bg-gradient-to-br ${path.color} opacity-0 group-hover:opacity-10 transition-opacity duration-300`} />
              </motion.button>
            );
          })}
        </div>

        {/* Footer */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.8, duration: 0.5 }}
          className="text-center mt-12"
        >
          <p className="text-text-secondary text-sm">
            Powered by Yo.Salon · Premium Salon Management
          </p>
        </motion.div>
      </motion.div>
    </div>
  );
}
