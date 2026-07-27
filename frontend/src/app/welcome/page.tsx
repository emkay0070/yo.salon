'use client';

import { useState, useEffect, useRef } from 'react';
import { useRouter } from 'next/navigation';
import { motion, AnimatePresence } from 'framer-motion';
import { 
  Crown, Calendar, User, ArrowRight, Sparkles, 
  Clock, Shield, Settings, Laptop, ChevronDown, 
  TrendingUp, Layers, Check, Menu, X,
  Star, Scissors,
  Award, Mail, Phone, Palette
} from 'lucide-react';
import { GlassCard } from '@/components/ui/glass-card';
import { GlassButton } from '@/components/ui/glass-button';
import { CursorGlow } from '@/components/ui/cursor-glow';
import { useTheme } from 'next-themes';

// -------------------------------------------------------------
// Interactive 3D Mockup Component
// -------------------------------------------------------------
function InteractiveMockup() {
  const cardRef = useRef<HTMLDivElement>(null);
  const [rotate, setRotate] = useState({ x: 0, y: 0 });
  const [glow, setGlow] = useState({ x: 50, y: 50 });
  const [isHovered, setIsHovered] = useState(false);

  const handleMouseMove = (e: React.MouseEvent<HTMLDivElement>) => {
    if (!cardRef.current) return;
    const card = cardRef.current;
    const rect = card.getBoundingClientRect();
    
    // Mouse position relative to the element
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    
    // Convert to percentage
    const xPct = (x / rect.width) * 100;
    const yPct = (y / rect.height) * 100;
    
    // Calculate rotation angle (max 15deg)
    const rotateX = -(yPct - 50) * 0.24; 
    const rotateY = (xPct - 50) * 0.24;  
    
    setRotate({ x: rotateX, y: rotateY });
    setGlow({ x: xPct, y: yPct });
  };

  const handleMouseLeave = () => {
    setIsHovered(false);
    setRotate({ x: 0, y: 0 });
  };

  const handleMouseEnter = () => {
    setIsHovered(true);
  };

  return (
    <div 
      className="w-full flex items-center justify-center p-2"
      style={{ perspective: 1200 }}
    >
      <motion.div
        ref={cardRef}
        onMouseMove={handleMouseMove}
        onMouseEnter={handleMouseEnter}
        onMouseLeave={handleMouseLeave}
        animate={{
          rotateX: rotate.x,
          rotateY: rotate.y,
          scale: isHovered ? 1.02 : 1,
        }}
        transition={{ type: 'spring', stiffness: 200, damping: 25 }}
        className="w-full max-w-2xl aspect-[1.6/1] bg-black/60 border border-white/10 rounded-2xl overflow-hidden shadow-2xl relative"
        style={{
          boxShadow: isHovered 
            ? `0 35px 75px -15px rgba(0,0,0,0.9), -${rotate.y * 3}px ${rotate.x * 3}px 40px -10px rgba(255, 215, 0, 0.12)`
            : '0 25px 50px -12px rgba(0,0,0,0.7)',
        }}
      >
        {/* Shine/Glow overlay inside card */}
        <div 
          className="absolute inset-0 pointer-events-none opacity-45 transition-opacity duration-300"
          style={{
            background: `radial-gradient(circle at ${glow.x}% ${glow.y}%, rgba(255,215,0,0.18) 0%, transparent 60%)`,
          }}
        />
        
        {/* Header Bar */}
        <div className="h-8 border-b border-white/5 bg-black/40 flex items-center px-4 justify-between">
          <div className="flex items-center gap-1.5">
            <div className="w-2.5 h-2.5 rounded-full bg-red-500/40" />
            <div className="w-2.5 h-2.5 rounded-full bg-yellow-500/40" />
            <div className="w-2.5 h-2.5 rounded-full bg-green-500/40" />
          </div>
          <span className="text-[10px] text-white/30 tracking-widest font-mono">YO.SALON INSIGHTS</span>
          <div className="w-12 h-1 bg-white/5 rounded" />
        </div>

        {/* Mockup Dashboard Content */}
        <div className="flex h-[calc(100%-2rem)] text-white">
          {/* Mockup Sidebar */}
          <div className="w-1/4 border-r border-white/5 bg-black/20 p-4 flex flex-col gap-3 justify-between">
            <div className="flex flex-col gap-2">
              <div className="flex items-center gap-2 mb-4">
                <div className="w-6 h-6 rounded-md bg-gradient-to-br from-gold to-dark-gold flex items-center justify-center">
                  <Crown className="w-3.5 h-3.5 text-black" />
                </div>
                <span className="font-sora text-xs font-semibold tracking-wide text-white/95">Yo.Salon</span>
              </div>
              
              <div className="flex flex-col gap-1.5">
                <div className="h-6 rounded-lg bg-white/10 flex items-center px-2 gap-1.5 border border-white/5">
                  <Calendar className="w-3 h-3 text-gold" />
                  <div className="h-1.5 w-10 bg-white/60 rounded" />
                </div>
                <div className="h-6 rounded-lg bg-white/5 flex items-center px-2 gap-1.5">
                  <User className="w-3 h-3 text-white/40" />
                  <div className="h-1.5 w-12 bg-white/30 rounded" />
                </div>
                <div className="h-6 rounded-lg bg-white/5 flex items-center px-2 gap-1.5">
                  <Settings className="w-3 h-3 text-white/40" />
                  <div className="h-1.5 w-8 bg-white/30 rounded" />
                </div>
              </div>
            </div>
            
            <div className="p-2 bg-white/5 rounded-lg border border-white/5 flex flex-col gap-1.5">
              <div className="h-1.5 w-12 bg-white/30 rounded" />
              <div className="h-1.5 w-8 bg-white/15 rounded" />
            </div>
          </div>
          
          {/* Mockup Main Panel */}
          <div className="flex-1 p-5 flex flex-col justify-between overflow-hidden">
            {/* Header info */}
            <div className="flex justify-between items-center mb-3">
              <div className="flex flex-col gap-1">
                <h4 className="text-[11px] uppercase tracking-wider text-white/40 font-semibold font-sora">Current Atmosphere</h4>
                <div className="h-3 w-28 bg-white/20 rounded" />
              </div>
              <div className="px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-[10px] flex items-center gap-1 font-medium">
                <div className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                60 FPS Live
              </div>
            </div>
            
            {/* Interactive Grid */}
            <div className="grid grid-cols-2 gap-3 flex-1 mb-2">
              {/* Upcoming Appointment */}
              <div className="bg-white/5 border border-white/5 rounded-xl p-3 flex flex-col justify-between relative overflow-hidden group">
                <div className="flex justify-between items-start z-10">
                  <span className="text-[9px] text-white/40 font-medium">UPCOMING</span>
                  <span className="text-[9px] text-gold font-medium">10:30 AM</span>
                </div>
                <div className="flex items-center gap-2 my-1.5 z-10">
                  <div className="w-7 h-7 rounded-full bg-gradient-to-tr from-gold/30 to-gold/10 border border-gold/30 flex items-center justify-center text-xs font-semibold text-gold">EV</div>
                  <div className="flex flex-col">
                    <span className="text-[11px] font-medium">Eleanor Vance</span>
                    <span className="text-[9px] text-white/40">Balayage & Styling</span>
                  </div>
                </div>
                <div className="w-full bg-white/10 h-1 rounded-full overflow-hidden z-10">
                  <motion.div 
                    className="h-full bg-gold"
                    initial={{ width: 0 }}
                    animate={{ width: '80%' }}
                    transition={{ duration: 2, delay: 0.5, repeat: Infinity, repeatType: 'reverse' }}
                  />
                </div>
              </div>
              
              {/* Active Stylist Roster */}
              <div className="bg-white/5 border border-white/5 rounded-xl p-3 flex flex-col justify-between relative overflow-hidden">
                <span className="text-[9px] text-white/40 font-medium">STYLISTS ON DUTY</span>
                <div className="flex flex-col gap-1.5 my-1.5">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-1">
                      <div className="w-3.5 h-3.5 rounded-full bg-blue-500/20 flex items-center justify-center text-[7px] text-blue-300 font-semibold border border-blue-500/20">J</div>
                      <span className="text-[9px] text-white/80">Julian</span>
                    </div>
                    <span className="text-[8px] px-1 py-0.2 rounded-full bg-emerald-500/10 text-emerald-400 font-medium border border-emerald-500/10">Available</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-1">
                      <div className="w-3.5 h-3.5 rounded-full bg-purple-500/20 flex items-center justify-center text-[7px] text-purple-300 font-semibold border border-purple-500/20">S</div>
                      <span className="text-[9px] text-white/80">Sophia</span>
                    </div>
                    <span className="text-[8px] px-1 py-0.2 rounded-full bg-amber-500/10 text-amber-400 font-medium border border-amber-500/10">Busy</span>
                  </div>
                </div>
                <div className="flex items-center gap-1 border-t border-white/5 pt-1.5">
                  <Star className="w-2.5 h-2.5 text-gold fill-gold" />
                  <span className="text-[9px] font-semibold text-white/95">4.95 Rating</span>
                </div>
              </div>
            </div>
            
            {/* Mini Footer Stats */}
            <div className="flex justify-between items-center border-t border-white/5 pt-3">
              <div className="flex gap-4">
                <div className="flex flex-col">
                  <span className="text-[8px] text-white/30">DAILY REVENUE</span>
                  <span className="text-xs font-semibold text-emerald-400">$1,420</span>
                </div>
                <div className="flex flex-col">
                  <span className="text-[8px] text-white/30">BOOKING RATE</span>
                  <span className="text-xs font-semibold text-gold">94%</span>
                </div>
              </div>
              <div className="h-5 w-5 rounded bg-white/5 flex items-center justify-center">
                <ArrowRight className="w-3 h-3 text-white/40" />
              </div>
            </div>
          </div>
        </div>
      </motion.div>
    </div>
  );
}

// -------------------------------------------------------------
// Interactive Accordion Item
// -------------------------------------------------------------
interface AccordionItemProps {
  question: string;
  answer: string;
  isOpen: boolean;
  onToggle: () => void;
}

function AccordionItem({ question, answer, isOpen, onToggle }: AccordionItemProps) {
  return (
    <GlassCard className="border border-white/5 overflow-hidden mb-4" elevation={1}>
      <button
        onClick={onToggle}
        className="w-full px-6 py-5 flex items-center justify-between text-left focus:outline-none"
        aria-expanded={isOpen}
      >
        <span className="font-sora font-semibold text-lg text-white group-hover:text-gold transition-colors duration-300">
          {question}
        </span>
        <motion.div
          animate={{ rotate: isOpen ? 180 : 0 }}
          transition={{ type: 'spring', stiffness: 200, damping: 15 }}
          className="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center border border-white/10"
        >
          <ChevronDown className="w-4 h-4 text-white/60" />
        </motion.div>
      </button>
      <AnimatePresence initial={false}>
        {isOpen && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: 'auto', opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            transition={{ type: 'spring', stiffness: 200, damping: 20 }}
          >
            <div className="px-6 pb-6 pt-1 text-white/60 border-t border-white/5 text-sm leading-relaxed">
              {answer}
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </GlassCard>
  );
}

// -------------------------------------------------------------
// Main Welcome & Landing Page Component
// -------------------------------------------------------------
export default function WelcomePage() {
  const router = useRouter();
  const { theme, setTheme } = useTheme();
  const [mounted, setMounted] = useState(false);
  const [activeTab, setActiveTab] = useState<'scheduler' | 'analytics' | 'wallet' | 'booking'>('scheduler');
  const [faqOpen, setFaqOpen] = useState<number | null>(null);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  
  useEffect(() => {
    const timer = setTimeout(() => {
      setMounted(true);
    }, 0);
    return () => clearTimeout(timer);
  }, []);

  const glowColors = {
    scheduler: '#3b82f6',
    analytics: '#a855f7',
    wallet: '#FFD700',
    booking: '#10b981',
  };
  const glowColor = glowColors[activeTab];

  const toggleFaq = (index: number) => {
    setFaqOpen(faqOpen === index ? null : index);
  };

  const osTabs = {
    scheduler: {
      name: 'Command Center',
      subtitle: 'Live Scheduler & Roster',
      description: 'Orchestrate daily appointments with instant slot calculations, stylist assignment, and active status tracking.',
      badge: 'STAFF CONTROL PANEL',
      accentColor: 'from-blue-500 to-cyan-400',
    },
    analytics: {
      name: 'Business Intelligence',
      subtitle: 'Insights & Analytics',
      description: 'Track daily commission splits, customer retention indicators, service rankings, and real-time revenue splits.',
      badge: 'MERCHANT INSIGHTS',
      accentColor: 'from-purple-500 to-pink-500',
    },
    wallet: {
      name: 'Financial Ledger',
      subtitle: 'Wallet & Payout Protection',
      description: 'Enforce booking deposit limits, handle cancellation fees, allocate tips, and track automated Stripe bank payouts.',
      badge: 'PAYMENT GATEWAY',
      accentColor: 'from-[#FFD700] to-amber-500',
    },
    booking: {
      name: 'Client Portal',
      subtitle: 'Immersive Booking Experience',
      description: 'Enable customers to browse portfolios, select treatments, and book appointment instances instantly on any device.',
      badge: 'B2C CLIENT ENGINE',
      accentColor: 'from-emerald-500 to-teal-400',
    }
  };

  const gatewayPaths = [
    {
      id: 'book',
      title: 'Book an Appointment',
      description: 'Search salons, view stylist portfolios, check live schedules, and reserve slot instances instantly.',
      badge: 'CLIENT EXPERIENCE',
      icon: Calendar,
      accent: 'from-emerald-500 to-teal-500',
      action: () => router.push('/book'),
      btnLabel: 'Book Now'
    },
    {
      id: 'signin',
      title: 'Sign In to Your Space',
      description: 'Access your account instantly. Automatically routes salon staff to dashboards and clients to their portal.',
      badge: 'ACCESS GATEWAY',
      icon: User,
      accent: 'from-blue-500 to-indigo-600',
      action: () => router.push('/login'),
      btnLabel: 'Sign In'
    },
    {
      id: 'create',
      title: 'Register Your Salon',
      description: 'Initialize your salon workspace, construct your service list, invite staff, and launch your experience.',
      badge: 'MERCHANT ONBOARDING',
      icon: Sparkles,
      accent: 'from-[#FFD700] to-[#C9A227]',
      action: () => router.push('/register'),
      btnLabel: 'Launch Salon'
    }
  ];

  const features = [
    {
      icon: Clock,
      title: 'AI-Powered Scheduler',
      description: 'Avoid overlapping appointments. Dynamic slot optimization adjusts calendars in real-time, matching stylists and services.'
    },
    {
      icon: Laptop,
      title: 'Branded Experience Engine',
      description: 'Instantly generate an immersive public-facing web experience for your customers using customizable design families.'
    },
    {
      icon: Shield,
      title: 'Deposit Protection',
      description: 'Reduce appointment no-shows. Take partial deposits or full pre-payments seamlessly at checkout with absolute security.'
    },
    {
      icon: TrendingUp,
      title: 'Analytics & Insights',
      description: 'View daily commission splits, payment methods used, client retention indicators, and busy hours dashboards.'
    },
    {
      icon: Layers,
      title: 'Multi-Tenant Isolation',
      description: 'Secure, dedicated database instances per salon. Custom domain forwarding and tenant subdomain hosting ready.'
    },
    {
      icon: Settings,
      title: 'Custom Staff Rosters',
      description: 'Enable staff to set their availability, configure custom service specialties, track customer tips, and manage personal schedules.'
    }
  ];

  const faqs = [
    {
      question: 'What is Yo.Salon?',
      answer: 'Yo.Salon is an Experience-as-a-Service salon management platform. Unlike flat, basic website builders, Yo.Salon generates premium virtual spaces for your B2C clients while hosting a complete, robust B2B backend dashboard to manage schedules, roster commissions, analytics, and checkouts.'
    },
    {
      question: 'How fast can I set up my salon?',
      answer: 'You can go live in under 5 minutes. Simply input your salon details, configure services, invite your staff, and select an Experience Family (like Luxury Noir or Modern Glass). Our generator constructs your public-facing site instantly.'
    },
    {
      question: 'Can I use my own custom domain?',
      answer: 'Yes! Yo.Salon supports custom domain binding (e.g., yoursalon.com). By default, we provide a clean, free subdomain on our network (yoursalon.yosalon.app).'
    },
    {
      question: 'How are client payments and deposits handled?',
      answer: 'We integrate with leading payment settlement controllers. You can toggle deposit options, specify service cancellation policies, accept customer tips, and receive automated payout logs to your designated bank accounts.'
    },
    {
      question: 'What are Experience Families?',
      answer: 'Experience Families are curated design architectures specified in our Design Bible. They determine your salon website\'s aesthetics, typography hierarchy (Sora & Poppins), background effects, glass transparency, hover physics, and ambient particle setups.'
    }
  ];

  const handleLinkClick = (id: string) => {
    const el = document.getElementById(id);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth' });
    }
  };

  return (
    <div className="min-h-screen bg-[#070707] text-white overflow-hidden font-poppins relative selection:bg-gold/30 selection:text-white">
      {/* Dynamic Cursor Glow (follows cursor and changes color based on current view/preset) */}
      {mounted && <CursorGlow color={glowColor} intensity={0.4} radius={350} />}

      {/* Floating Ambient Background Blobs */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none z-0">
        <motion.div
          animate={{
            x: [0, 80, -40, 0],
            y: [0, -120, 60, 0],
            scale: [1, 1.2, 0.9, 1],
          }}
          transition={{ duration: 25, repeat: Infinity, ease: 'easeInOut' }}
          className="absolute w-[500px] h-[500px] bg-gold/5 rounded-full blur-[140px] -top-96 -left-48"
        />
        <motion.div
          animate={{
            x: [0, -100, 50, 0],
            y: [0, 90, -80, 0],
            scale: [1, 0.9, 1.15, 1],
          }}
          transition={{ duration: 30, repeat: Infinity, ease: 'easeInOut' }}
          className="absolute w-[600px] h-[600px] bg-purple-500/5 rounded-full blur-[150px] top-1/3 -right-96"
        />
        <motion.div
          animate={{
            x: [0, 120, -80, 0],
            y: [0, 60, -90, 0],
            scale: [1, 1.1, 0.95, 1],
          }}
          transition={{ duration: 22, repeat: Infinity, ease: 'easeInOut' }}
          className="absolute w-[450px] h-[450px] bg-emerald-500/5 rounded-full blur-[130px] -bottom-48 left-1/4"
        />
      </div>

      {/* -------------------------------------------------------------
          Header / Floating Navigation Bar
          ------------------------------------------------------------- */}
      <nav className="fixed top-4 inset-x-4 h-16 z-50 max-w-7xl mx-auto">
        <div className="w-full h-full backdrop-blur-xl bg-black/40 border border-white/5 rounded-2xl px-6 flex items-center justify-between shadow-xl">
          {/* Logo */}
          <div className="flex items-center gap-2.5 cursor-pointer" onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}>
            <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-gold to-[#C9A227] flex items-center justify-center shadow-lg shadow-gold/10">
              <Crown className="w-5 h-5 text-black" />
            </div>
            <span className="font-sora font-bold text-lg tracking-wider bg-gradient-to-r from-white via-white to-gold bg-clip-text text-transparent">
              Yo.Salon
            </span>
          </div>

          {/* Desktop Nav Links */}
          <div className="hidden md:flex items-center gap-8 text-sm text-white/70">
            <button onClick={() => handleLinkClick('gateway')} className="hover:text-gold transition-colors duration-300 cursor-pointer font-medium">Gateway</button>
            <button onClick={() => handleLinkClick('experiences')} className="hover:text-gold transition-colors duration-300 cursor-pointer font-medium">OS Console</button>
            <button onClick={() => handleLinkClick('features')} className="hover:text-gold transition-colors duration-300 cursor-pointer font-medium">Features</button>
            <button onClick={() => handleLinkClick('faq')} className="hover:text-gold transition-colors duration-300 cursor-pointer font-medium">FAQ</button>
          </div>

          {/* CTAs */}
          <div className="hidden md:flex items-center gap-3">
            {mounted && (
              <button
                onClick={() => setTheme(theme === 'light' ? 'noir' : 'light')}
                className="w-10 h-10 flex items-center justify-center rounded-xl bg-white/5 border border-white/10 text-white/60 hover:text-white hover:bg-white/10 transition-colors"
                title="Toggle Theme"
              >
                <Palette className="w-5 h-5" />
              </button>
            )}
            <GlassButton variant="ghost" size="sm" onClick={() => router.push('/login')}>
              Sign In
            </GlassButton>
            <GlassButton variant="primary" size="sm" onClick={() => router.push('/register')} className="shadow-lg shadow-gold/10">
              Register Salon
            </GlassButton>
          </div>

          {/* Mobile Menu Button */}
          <button 
            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
            className="md:hidden w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center border border-white/10 hover:bg-white/10 transition-colors"
          >
            {mobileMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
          </button>
        </div>

        {/* Mobile Navigation Dropdown */}
        <AnimatePresence>
          {mobileMenuOpen && (
            <motion.div
              initial={{ opacity: 0, y: -10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
              className="absolute top-20 left-0 right-0 p-4 bg-black/90 backdrop-blur-2xl border border-white/10 rounded-2xl flex flex-col gap-4 shadow-2xl md:hidden z-50"
            >
              <button 
                onClick={() => { setMobileMenuOpen(false); handleLinkClick('gateway'); }}
                className="py-2.5 px-4 rounded-xl hover:bg-white/5 text-left text-sm"
              >
                Gateway
              </button>
              <button 
                onClick={() => { setMobileMenuOpen(false); handleLinkClick('experiences'); }}
                className="py-2.5 px-4 rounded-xl hover:bg-white/5 text-left text-sm"
              >
                OS Console
              </button>
              <button 
                onClick={() => { setMobileMenuOpen(false); handleLinkClick('features'); }}
                className="py-2.5 px-4 rounded-xl hover:bg-white/5 text-left text-sm"
              >
                Features
              </button>
              <button 
                onClick={() => { setMobileMenuOpen(false); handleLinkClick('faq'); }}
                className="py-2.5 px-4 rounded-xl hover:bg-white/5 text-left text-sm"
              >
                FAQ
              </button>
              
              <div className="h-px bg-white/5 my-1" />
              
              <div className="flex flex-col gap-2.5">
                <GlassButton variant="secondary" size="md" onClick={() => { setMobileMenuOpen(false); router.push('/login'); }} className="w-full">
                  Sign In
                </GlassButton>
                <GlassButton variant="primary" size="md" onClick={() => { setMobileMenuOpen(false); router.push('/register'); }} className="w-full">
                  Register Salon
                </GlassButton>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </nav>

      {/* -------------------------------------------------------------
          Hero Section
          ------------------------------------------------------------- */}
      <section className="relative z-10 pt-36 pb-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto flex flex-col items-center text-center">
        {/* Animated Pill Badge */}
        <motion.div
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ type: 'spring', stiffness: 300, damping: 25 }}
          className="px-4 py-1.5 rounded-full bg-white/5 border border-white/10 text-xs text-gold/90 font-medium tracking-wide flex items-center gap-2 mb-8 shadow-sm"
        >
          <Sparkles className="w-3.5 h-3.5" />
          <span>V1.0 IM-EXPERIENCE MODULE</span>
        </motion.div>

        {/* Title Headline */}
        <motion.h1
          initial={{ opacity: 0, y: 30 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ type: 'spring', stiffness: 100, damping: 20 }}
          className="font-sora text-4xl sm:text-5xl md:text-6xl font-extrabold tracking-tight max-w-4xl leading-[1.1] mb-6 text-white"
        >
          Transform Your Salon Into an{' '}
          <span className="bg-gradient-to-r from-gold via-yellow-400 to-[#FF8C5A] bg-clip-text text-transparent">
            Immersive Experience
          </span>
        </motion.h1>

        {/* Subtitle */}
        <motion.p
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ type: 'spring', stiffness: 100, damping: 22, delay: 0.1 }}
          className="text-base sm:text-lg md:text-xl text-white/50 max-w-2xl leading-relaxed mb-10"
        >
          Welcome clients into a premium virtual environment before they even step through your doors. Beautiful scheduling, staff management, and custom booking sites generated instantly.
        </motion.p>

        {/* Primary and Secondary Hero Actions */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ type: 'spring', stiffness: 100, damping: 25, delay: 0.2 }}
          className="flex flex-col sm:flex-row gap-4 mb-16 z-10"
        >
          <GlassButton variant="primary" size="lg" onClick={() => handleLinkClick('gateway')} className="px-8 shadow-lg shadow-gold/20 flex gap-2 group">
            Get Started <ArrowRight className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
          </GlassButton>
          <GlassButton variant="secondary" size="lg" onClick={() => handleLinkClick('experiences')} className="px-8 flex gap-2">
            Explore OS Console
          </GlassButton>
        </motion.div>

        {/* Interactive 3D Mockup Container */}
        <motion.div
          initial={{ opacity: 0, y: 40 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ type: 'spring', stiffness: 120, damping: 20, delay: 0.3 }}
          className="w-full max-w-4xl z-10"
        >
          <InteractiveMockup />
        </motion.div>
      </section>

      {/* -------------------------------------------------------------
          Gateway Options Grid (Core Functionality Routes)
          ------------------------------------------------------------- */}
      <section id="gateway" className="relative z-10 py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div className="text-center mb-16">
          <h2 className="font-sora text-3xl md:text-4xl font-bold mb-4 tracking-tight">Choose Your Destination</h2>
          <p className="text-white/40 text-sm md:text-base max-w-xl mx-auto">
            Access client booking forms, staff management accounts, or onboard a new salon to launch your custom platform.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {gatewayPaths.map((path) => {
            const Icon = path.icon;
            return (
              <GlassCard
                key={path.id}
                elevation={2}
                hoverable
                onClick={path.action}
                className="p-8 border border-white/5 flex flex-col justify-between h-96 group relative overflow-hidden text-left"
              >
                {/* Background soft glow gradient */}
                <div className={`absolute -right-24 -top-24 w-48 h-48 bg-gradient-to-br ${path.accent} opacity-0 group-hover:opacity-10 rounded-full blur-2xl transition-opacity duration-500`} />

                <div>
                  {/* Badge */}
                  <div className="text-[10px] uppercase font-bold tracking-wider text-white/30 mb-6 font-mono">
                    {path.badge}
                  </div>

                  {/* Icon */}
                  <div className={`w-14 h-14 rounded-2xl bg-gradient-to-tr ${path.accent} flex items-center justify-center mb-6 shadow-lg shadow-black/40 border border-white/10 group-hover:scale-110 transition-transform duration-300`}>
                    <Icon className="w-7 h-7 text-white" />
                  </div>

                  {/* Title */}
                  <h3 className="font-sora text-2xl font-bold mb-3 tracking-tight group-hover:text-gold transition-colors duration-300">
                    {path.title}
                  </h3>

                  {/* Description */}
                  <p className="text-white/40 text-sm leading-relaxed mb-6 font-normal">
                    {path.description}
                  </p>
                </div>

                {/* Continue button action */}
                <div className="flex items-center gap-2 text-white group-hover:text-gold font-medium text-sm transition-colors duration-300">
                  <span>{path.btnLabel}</span>
                  <ArrowRight className="w-4 h-4 group-hover:translate-x-1.5 transition-transform duration-300" />
                </div>
              </GlassCard>
            );
          })}
        </div>
      </section>

      {/* -------------------------------------------------------------
          Interactive Experience Families Showcase Widget
          ------------------------------------------------------------- */}
      <section id="experiences" className="relative z-10 py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-white/5">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
          {/* Text descriptions */}
          <div className="lg:col-span-5 flex flex-col justify-center text-left">
            <span className="text-gold text-xs uppercase tracking-widest font-bold font-mono mb-4 flex items-center gap-2">
              <Scissors className="w-3.5 h-3.5" /> Platform Workspace
            </span>
            <h2 className="font-sora text-3xl md:text-4xl font-extrabold mb-6 tracking-tight text-white animate-pulse-subtle">
              Salon OS Console
            </h2>
            <p className="text-white/50 text-base leading-relaxed mb-8">
              We present a dynamic Operating System for modern beauty businesses. Switch between critical workspace consoles designed to streamline receptionist operations, merchant analytics, payment settings, and customer booking portals.
            </p>

            {/* Selection bar */}
            <div className="flex flex-col gap-2.5">
              {(Object.keys(osTabs) as Array<keyof typeof osTabs>).map((key) => (
                <button
                  key={key}
                  onClick={() => setActiveTab(key)}
                  className={`w-full text-left p-4 rounded-xl transition-all duration-300 flex flex-col gap-1 border cursor-pointer ${
                    activeTab === key
                      ? 'bg-white/5 border-gold/40 text-white shadow-md'
                      : 'bg-transparent border-transparent text-white/50 hover:bg-white/5 hover:text-white'
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <span className="font-semibold font-sora text-sm">{osTabs[key].name}</span>
                    {activeTab === key && (
                      <motion.div layoutId="activeCheck">
                        <Check className="w-4.5 h-4.5 text-gold" />
                      </motion.div>
                    )}
                  </div>
                  <span className="text-[11px] text-white/45 font-normal leading-normal">{osTabs[key].subtitle}</span>
                </button>
              ))}
            </div>
          </div>

          {/* Interactive Live Switcher Preview Frame */}
          <div className="lg:col-span-7 flex justify-center w-full">
            <div className="w-full max-w-xl min-h-[360px] bg-black/40 border border-white/5 rounded-3xl p-6 flex flex-col justify-between shadow-2xl relative overflow-hidden">
              {/* Dynamic decorative backdrop according to tab settings */}
              <div className="absolute inset-0 z-0 pointer-events-none transition-all duration-500">
                <div 
                  className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-64 h-64 rounded-full blur-[100px] opacity-20 transition-all duration-500"
                  style={{
                    background: activeTab === 'scheduler' ? '#3b82f6' :
                                activeTab === 'analytics' ? '#a855f7' :
                                activeTab === 'wallet' ? '#FFD700' : '#10b981'
                  }}
                />
              </div>

              {/* Top status header */}
              <div className="flex justify-between items-center z-10 pb-2 border-b border-white/5">
                <span className="text-[10px] text-white/35 font-mono tracking-widest">SALON.OS CONTROLLER</span>
                <span className="text-[9px] px-2 py-0.5 rounded-full bg-white/5 border border-white/10 text-white/60">
                  {osTabs[activeTab].badge}
                </span>
              </div>

              {/* The dynamic styled mockup interface card */}
              <div className="z-10 my-6 flex-grow flex items-center justify-center w-full">
                <AnimatePresence mode="wait">
                  <motion.div
                    key={activeTab}
                    initial={{ opacity: 0, scale: 0.95, y: 15 }}
                    animate={{ opacity: 1, scale: 1, y: 0 }}
                    exit={{ opacity: 0, scale: 0.95, y: -15 }}
                    transition={{ type: 'spring', stiffness: 200, damping: 20 }}
                    className="w-full flex items-center justify-center"
                  >
                    {activeTab === 'scheduler' && (
                      <div className="w-full flex flex-col justify-between text-left font-sans text-xs bg-white/[0.02] backdrop-blur-md border border-white/5 rounded-2xl p-5 shadow-xl">
                        {/* Header Info */}
                        <div className="flex justify-between items-center pb-2 border-b border-white/5 mb-3">
                          <div className="flex flex-col">
                            <span className="text-[9px] text-white/45 tracking-wider font-mono">LIVE BOOKINGS TIMELINE</span>
                            <span className="text-[13px] font-semibold font-sora text-white">Thursday, July 23</span>
                          </div>
                          <div className="px-2 py-0.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-[8px] font-medium tracking-wide">
                            Active Today
                          </div>
                        </div>
                        
                        {/* Timeline List */}
                        <div className="flex flex-col gap-2.5 flex-grow">
                          <div className="bg-white/5 border border-white/5 rounded-xl p-3 flex flex-col gap-2 relative overflow-hidden">
                            <div className="flex justify-between items-center text-[9px]">
                              <span className="text-white/45 font-medium">10:00 AM - 11:30 AM</span>
                              <span className="text-blue-400 font-semibold bg-blue-500/10 px-1.5 py-0.2 rounded text-[8px]">In Progress</span>
                            </div>
                            <div className="flex items-center gap-2.5">
                              <div className="w-7 h-7 rounded-full bg-gradient-to-tr from-blue-500 to-cyan-500 flex items-center justify-center text-xs font-semibold text-white">EV</div>
                              <div className="flex flex-col">
                                <span className="text-white font-medium text-[11px]">Eleanor Vance</span>
                                <span className="text-white/40 text-[9px]">Balayage Styling & Cut · Julian</span>
                              </div>
                            </div>
                            <div className="w-full bg-white/10 h-1 rounded-full overflow-hidden mt-1">
                              <motion.div 
                                className="h-full bg-gradient-to-r from-blue-500 to-cyan-400"
                                initial={{ width: '0%' }}
                                animate={{ width: '65%' }}
                                transition={{ duration: 1.5, ease: 'easeOut' }}
                              />
                            </div>
                          </div>

                          <div className="bg-white/5 border border-white/5 rounded-xl p-3 flex flex-col gap-2 opacity-80">
                            <div className="flex justify-between items-center text-[9px]">
                              <span className="text-white/45 font-medium">11:45 AM - 12:15 PM</span>
                              <span className="text-white/50 bg-white/10 px-1.5 py-0.2 rounded font-medium text-[8px]">Confirmed</span>
                            </div>
                            <div className="flex items-center gap-2.5">
                              <div className="w-7 h-7 rounded-full bg-gradient-to-tr from-purple-500 to-pink-500 flex items-center justify-center text-xs font-semibold text-white">MS</div>
                              <div className="flex flex-col">
                                <span className="text-white font-medium text-[11px]">Marcus Sterling</span>
                                <span className="text-white/40 text-[9px]">Classic Beard Trim · Sophia</span>
                              </div>
                            </div>
                          </div>
                        </div>

                        {/* Footer Stats / Quick Actions */}
                        <div className="flex justify-between items-center border-t border-white/5 pt-3 mt-3 text-[9px]">
                          <div className="flex gap-4">
                            <div className="flex flex-col">
                              <span className="text-white/30 text-[7px] uppercase font-mono">Roster Status</span>
                              <span className="text-white/80 font-medium">3 Stylists on Duty</span>
                            </div>
                            <div className="flex flex-col">
                              <span className="text-white/30 text-[7px] uppercase font-mono">Slot Capacity</span>
                              <span className="text-emerald-400 font-medium">92% Utilized</span>
                            </div>
                          </div>
                          <div className="px-2 py-0.5 rounded bg-white/5 hover:bg-white/10 text-white/70 transition-colors border border-white/5 cursor-pointer font-mono text-[8px]">
                            + New Booking
                          </div>
                        </div>
                      </div>
                    )}

                    {activeTab === 'analytics' && (
                      <div className="w-full flex flex-col justify-between text-left font-sans text-xs bg-white/[0.02] backdrop-blur-md border border-white/5 rounded-2xl p-5 shadow-xl">
                        {/* Header Info */}
                        <div className="flex justify-between items-center pb-2 border-b border-white/5 mb-3">
                          <div className="flex flex-col">
                            <span className="text-[9px] text-white/45 tracking-wider font-mono">BUSINESS INTELLIGENCE</span>
                            <span className="text-[13px] font-semibold font-sora text-white">SaaS Revenue Splits</span>
                          </div>
                          <div className="px-2 py-0.5 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-400 text-[8px] font-medium tracking-wide">
                            Live Update
                          </div>
                        </div>

                        {/* Top Statistics Row */}
                        <div className="grid grid-cols-3 gap-2 mb-3">
                          <div className="bg-white/5 border border-white/5 rounded-lg p-2 flex flex-col">
                            <span className="text-white/30 text-[7px] uppercase font-mono">Daily Gross</span>
                            <span className="text-purple-300 font-bold text-xs font-sora mt-0.5">$2,480</span>
                            <span className="text-emerald-400 text-[6px] font-medium mt-0.5">+12.4% vs lw</span>
                          </div>
                          <div className="bg-white/5 border border-white/5 rounded-lg p-2 flex flex-col">
                            <span className="text-white/30 text-[7px] uppercase font-mono">Retention</span>
                            <span className="text-white/90 font-bold text-xs font-sora mt-0.5">88.5%</span>
                            <span className="text-emerald-400 text-[6px] font-medium mt-0.5">+2.1% trend</span>
                          </div>
                          <div className="bg-white/5 border border-white/5 rounded-lg p-2 flex flex-col">
                            <span className="text-white/30 text-[7px] uppercase font-mono">Booking Rate</span>
                            <span className="text-[#FFD700] font-bold text-xs font-sora mt-0.5">96.2%</span>
                            <span className="text-white/40 text-[6px] mt-0.5">Optimal cap</span>
                          </div>
                        </div>

                        {/* Chart Breakdown */}
                        <div className="bg-white/5 border border-white/5 rounded-xl p-3 flex flex-col">
                          <div className="flex justify-between items-center text-[7px] text-white/30 border-b border-white/5 pb-1.5 mb-2">
                            <span>SERVICE REVENUE BREAKDOWN</span>
                            <span>MONTH-TO-DATE</span>
                          </div>
                          <div className="flex flex-col gap-2">
                            <div className="flex flex-col gap-0.5">
                              <div className="flex justify-between text-[8px] text-white/70">
                                <span>Hair Color treatments & Styling</span>
                                <span className="font-semibold text-white/90">$12,850 (48%)</span>
                              </div>
                              <div className="w-full bg-white/10 h-1 rounded-full overflow-hidden">
                                <div className="h-full bg-gradient-to-r from-purple-500 to-indigo-500 rounded-full" style={{ width: '48%' }} />
                              </div>
                            </div>
                            <div className="flex flex-col gap-0.5">
                              <div className="flex justify-between text-[8px] text-white/70">
                                <span>Men's Classic & Sculpt Cuts</span>
                                <span className="font-semibold text-white/90">$8,560 (32%)</span>
                              </div>
                              <div className="w-full bg-white/10 h-1 rounded-full overflow-hidden">
                                <div className="h-full bg-gradient-to-r from-purple-500 to-indigo-500 rounded-full" style={{ width: '32%' }} />
                              </div>
                            </div>
                            <div className="flex flex-col gap-0.5">
                              <div className="flex justify-between text-[8px] text-white/70">
                                <span>Nail Care & Extensions</span>
                                <span className="font-semibold text-white/90">$4,280 (20%)</span>
                              </div>
                              <div className="w-full bg-white/10 h-1 rounded-full overflow-hidden">
                                <div className="h-full bg-gradient-to-r from-purple-500 to-indigo-500 rounded-full" style={{ width: '20%' }} />
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    )}

                    {activeTab === 'wallet' && (
                      <div className="w-full flex flex-col justify-between text-left font-sans text-xs bg-white/[0.02] backdrop-blur-md border border-white/5 rounded-2xl p-5 shadow-xl">
                        {/* Header Info */}
                        <div className="flex justify-between items-center pb-2 border-b border-white/5 mb-3">
                          <div className="flex flex-col">
                            <span className="text-[9px] text-white/45 tracking-wider font-mono">FINANCIAL CONTROLLER</span>
                            <span className="text-[13px] font-semibold font-sora text-white">Wallet & Settlement</span>
                          </div>
                          <div className="px-2 py-0.5 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-[8px] font-medium tracking-wide">
                            Escrow Settled
                          </div>
                        </div>

                        {/* Balance display */}
                        <div className="bg-gradient-to-br from-amber-500/10 to-[#FFD700]/5 border border-[#FFD700]/20 rounded-2xl p-4 mb-3 flex justify-between items-center">
                          <div className="flex flex-col">
                            <span className="text-[8px] text-[#FFD700] uppercase font-mono tracking-wider font-semibold">Available Balance</span>
                            <span className="text-lg font-bold font-sora text-white mt-0.5">$14,250.00</span>
                            <span className="text-[7px] text-white/40 mt-0.5">Payout: Daily 12:00 AM</span>
                          </div>
                          <div className="flex flex-col text-right">
                            <span className="text-[8px] text-white/30 uppercase font-mono">Pending Payout</span>
                            <span className="text-sm font-semibold text-white/80 mt-0.5">$2,840.50</span>
                            <span className="text-[7px] text-emerald-400 mt-0.5">Processing...</span>
                          </div>
                        </div>

                        {/* Split stats */}
                        <div className="bg-white/5 border border-white/5 rounded-xl p-3 flex flex-col justify-between">
                          <div className="flex justify-between items-center text-[8px] text-white/40 border-b border-white/5 pb-2 mb-2">
                            <span>MERCHANT DEPOSIT RULES</span>
                            <span className="text-[#FFD700] font-semibold text-[8px] font-mono">Active</span>
                          </div>
                          
                          <div className="flex flex-col gap-2 text-[9px]">
                            <div className="flex justify-between items-center">
                              <span className="text-white/60">Minimum booking deposit requirement</span>
                              <span className="font-semibold text-white font-mono">30.00%</span>
                            </div>
                            <div className="flex justify-between items-center">
                              <span className="text-white/60">Automated staff commission split</span>
                              <span className="font-semibold text-white font-mono">60.00% / 40.00%</span>
                            </div>
                            <div className="flex justify-between items-center border-t border-white/5 pt-2 mt-1">
                              <span className="text-white/40">Last payout settled to bank account</span>
                              <span className="font-medium text-emerald-400 font-mono">$1,850.50</span>
                            </div>
                          </div>
                        </div>
                      </div>
                    )}

                    {activeTab === 'booking' && (
                      <div className="w-full flex flex-col justify-center items-center py-2 relative overflow-hidden">
                        {/* Mobile Phone Mockup */}
                        <div 
                          className="w-[180px] aspect-[1/2] border-4 border-white/10 rounded-[28px] bg-cover bg-center shadow-2xl relative flex flex-col overflow-hidden text-left"
                          style={{ backgroundImage: `url('/images/salon-luxury.png')` }}
                        >
                          {/* Ambient dark cover */}
                          <div className="absolute inset-0 bg-black/60 z-0 pointer-events-none" />

                          {/* Phone top notch */}
                          <div className="h-4 w-full flex justify-center items-center relative z-10">
                            <div className="w-12 h-1.5 bg-white/20 rounded-full" />
                          </div>

                          {/* App content container */}
                          <div className="flex-1 p-3 flex flex-col justify-between text-white relative z-10 text-[7px] leading-relaxed">
                            {/* App Header */}
                            <div className="flex justify-between items-center border-b border-white/10 pb-1">
                              <div className="flex items-center gap-1">
                                <Crown className="w-2.5 h-2.5 text-[#FFD700]" />
                                <span className="font-semibold text-[8px] font-sora">The Atelier</span>
                              </div>
                              <span className="px-1 py-0.2 rounded-full bg-emerald-500/20 text-emerald-400 font-medium font-mono text-[5px]">Open</span>
                            </div>

                            {/* Stylist Selection List */}
                            <div className="flex flex-col gap-1.5 my-2 flex-grow justify-center">
                              <span className="text-[6px] text-white/50 uppercase font-mono tracking-wider font-semibold">Select Service</span>
                              <div className="bg-white/10 backdrop-blur-md border border-white/10 rounded-lg p-1.5 flex justify-between items-center">
                                <div className="flex flex-col">
                                  <span className="font-medium text-[8px]">Silk Blowout & Press</span>
                                  <span className="text-white/50 text-[6px]">90 min · $85</span>
                                </div>
                                <Check className="w-2 h-2 text-[#FFD700]" />
                              </div>

                              <span className="text-[6px] text-white/50 uppercase font-mono tracking-wider font-semibold">Select Stylist</span>
                              <div className="flex gap-1.5">
                                <div className="bg-white/10 backdrop-blur-md border border-white/10 rounded-lg p-1.5 flex-1 flex flex-col items-center gap-0.5">
                                  <div className="w-5 h-5 rounded-full bg-blue-500/20 border border-blue-500/20 flex items-center justify-center text-[7px] text-blue-300 font-semibold">J</div>
                                  <span className="font-medium text-[6px]">Julian</span>
                                </div>
                                <div className="bg-white/20 backdrop-blur-md border border-[#FFD700]/30 rounded-lg p-1.5 flex-1 flex flex-col items-center gap-0.5 relative">
                                  <div className="w-5 h-5 rounded-full bg-purple-500/20 border border-purple-500/20 flex items-center justify-center text-[7px] text-purple-300 font-semibold">S</div>
                                  <span className="font-medium text-[6px]">Sophia</span>
                                  <div className="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-[#FFD700] flex items-center justify-center text-[4px] text-black font-bold">★</div>
                                </div>
                              </div>
                            </div>

                            {/* Button */}
                            <div className="w-full py-1.5 bg-gradient-to-r from-[#FFD700] to-amber-500 text-black text-center font-bold font-sora rounded-lg shadow-md tracking-wider transform transition-all text-[7px]">
                              Reserve Spot
                            </div>
                          </div>
                        </div>
                      </div>
                    )}
                  </motion.div>
                </AnimatePresence>
              </div>

              {/* Console feature specifications list */}
              <div className="z-10 flex flex-wrap gap-2 justify-center border-t border-white/5 pt-4">
                <span className="text-[9px] px-2.5 py-1 rounded-full bg-white/5 text-white/50 flex items-center gap-1 font-mono border border-white/5">
                  <span className="w-1 h-1 rounded-full bg-gold" />
                  Stripe-Verified Payouts
                </span>
                <span className="text-[9px] px-2.5 py-1 rounded-full bg-white/5 text-white/50 flex items-center gap-1 font-mono border border-white/5">
                  <span className="w-1 h-1 rounded-full bg-gold" />
                  Real-time Database Isolation
                </span>
                <span className="text-[9px] px-2.5 py-1 rounded-full bg-white/5 text-white/50 flex items-center gap-1 font-mono border border-white/5">
                  <span className="w-1 h-1 rounded-full bg-gold" />
                  Subdomain and DNS Bindings
                </span>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* -------------------------------------------------------------
          Platform Features Grid Matrix
          ------------------------------------------------------------- */}
      <section id="features" className="relative z-10 py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-white/5">
        <div className="text-center mb-16">
          <span className="text-gold text-xs uppercase tracking-widest font-bold font-mono mb-4 flex items-center justify-center gap-2">
            <Award className="w-3.5 h-3.5" /> High-End Architecture
          </span>
          <h2 className="font-sora text-3xl md:text-4xl font-bold mb-4 tracking-tight">Engineered for Elegance & Growth</h2>
          <p className="text-white/40 text-sm md:text-base max-w-xl mx-auto">
            A comprehensive, custom experience engine combined with enterprise scheduling, deposit tracking, and multi-tenant isolation.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {features.map((feat, idx) => {
            const Icon = feat.icon;
            return (
              <GlassCard
                key={idx}
                elevation={1}
                hoverable
                className="p-6 border border-white/5 text-left flex flex-col justify-between"
              >
                <div>
                  <div className="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center mb-5 text-gold shadow-md">
                    <Icon className="w-5 h-5" />
                  </div>
                  <h3 className="font-sora font-semibold text-lg text-white mb-2 tracking-tight group-hover:text-gold transition-colors duration-300">
                    {feat.title}
                  </h3>
                  <p className="text-white/40 text-xs md:text-sm leading-relaxed">
                    {feat.description}
                  </p>
                </div>
              </GlassCard>
            );
          })}
        </div>
      </section>

      {/* -------------------------------------------------------------
          Apple-Style Accordion FAQ Section
          ------------------------------------------------------------- */}
      <section id="faq" className="relative z-10 py-20 px-4 sm:px-6 lg:px-8 max-w-3xl mx-auto border-t border-white/5">
        <div className="text-center mb-16">
          <h2 className="font-sora text-3xl md:text-4xl font-bold mb-4 tracking-tight">Frequently Asked Questions</h2>
          <p className="text-white/45 text-sm">
            Everything you need to know about the Yo.Salon experience engine.
          </p>
        </div>

        <div className="w-full">
          {faqs.map((faq, index) => (
            <AccordionItem
              key={index}
              question={faq.question}
              answer={faq.answer}
              isOpen={faqOpen === index}
              onToggle={() => toggleFaq(index)}
            />
          ))}
        </div>
      </section>

      {/* -------------------------------------------------------------
          Interactive Footer Section
          ------------------------------------------------------------- */}
      <footer className="relative z-10 bg-black/60 border-t border-white/5 pt-20 pb-10 px-4 sm:px-6 lg:px-8">
        <div className="max-w-7xl mx-auto">
          {/* Main Footer Directory Grid */}
          <div className="grid grid-cols-1 md:grid-cols-5 gap-10 mb-16 text-left">
            {/* Branding Column */}
            <div className="md:col-span-2 flex flex-col gap-4">
              <div className="flex items-center gap-2.5">
                <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-gold to-[#C9A227] flex items-center justify-center">
                  <Crown className="w-4 h-4 text-black" />
                </div>
                <span className="font-sora font-bold text-lg tracking-wider text-white">
                  Yo.Salon
                </span>
              </div>
              <p className="text-white/40 text-xs md:text-sm leading-relaxed max-w-sm">
                Providing Experience-as-a-Service for progressive beauty and wellness salons. We design beautiful digital environments that look and feel premium.
              </p>
              
              {/* Contact info list */}
              <div className="flex flex-col gap-2 mt-2">
                <div className="flex items-center gap-2 text-white/50 text-xs">
                  <Mail className="w-4 h-4 text-gold" />
                  <span>concierge@yosalon.app</span>
                </div>
                <div className="flex items-center gap-2 text-white/50 text-xs">
                  <Phone className="w-4 h-4 text-gold" />
                  <span>+1 (800) 555-SALON</span>
                </div>
              </div>
            </div>

            {/* Links Columns */}
            <div>
              <h4 className="font-sora font-semibold text-xs text-white/80 uppercase tracking-widest mb-4">PRODUCT</h4>
              <ul className="flex flex-col gap-2 text-xs text-white/40">
                <li><button onClick={() => handleLinkClick('gateway')} className="hover:text-gold transition-colors text-left">Portal Gateway</button></li>
                <li><button onClick={() => handleLinkClick('experiences')} className="hover:text-gold transition-colors text-left font-medium">OS Console</button></li>
                <li><button onClick={() => handleLinkClick('features')} className="hover:text-gold transition-colors text-left">SaaS Features</button></li>
                <li><button onClick={() => router.push('/book')} className="hover:text-gold transition-colors text-left">Customer Booking demo</button></li>
              </ul>
            </div>

            <div>
              <h4 className="font-sora font-semibold text-xs text-white/80 uppercase tracking-widest mb-4">RESOURCES</h4>
              <ul className="flex flex-col gap-2 text-xs text-white/40">
                <li><a href="#" className="hover:text-gold transition-colors">Documentation</a></li>
                <li><a href="#" className="hover:text-gold transition-colors">Style Guide API</a></li>
                <li><a href="#" className="hover:text-gold transition-colors">Design Tokens</a></li>
                <li><a href="#" className="hover:text-gold transition-colors">Developer Portal</a></li>
              </ul>
            </div>

            <div>
              <h4 className="font-sora font-semibold text-xs text-white/80 uppercase tracking-widest mb-4">LEGAL</h4>
              <ul className="flex flex-col gap-2 text-xs text-white/40">
                <li><a href="#" className="hover:text-gold transition-colors">Terms of Service</a></li>
                <li><a href="#" className="hover:text-gold transition-colors">Privacy Policy</a></li>
                <li><a href="#" className="hover:text-gold transition-colors">HIPAA Compliance</a></li>
                <li><a href="#" className="hover:text-gold transition-colors">Tenant SLAs</a></li>
              </ul>
            </div>
          </div>

          {/* Bottom Copyright and Meta tags */}
          <div className="flex flex-col sm:flex-row justify-between items-center border-t border-white/5 pt-8 gap-4 text-center">
            <span className="text-[11px] text-white/30">
              &copy; {new Date().getFullYear()} Yo.Salon & EmKay Studios. All rights reserved.
            </span>
            <div className="flex items-center gap-6 text-[10px] text-white/30">
              <span className="flex items-center gap-1.5">
                <Shield className="w-3.5 h-3.5 text-emerald-400" /> Fully Encrypted Data
              </span>
              <span className="flex items-center gap-1.5">
                <Laptop className="w-3.5 h-3.5 text-purple-400" /> Responsive HTML5
              </span>
            </div>
          </div>
        </div>
      </footer>
    </div>
  );
}
