'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { motion } from 'framer-motion';
import { MapPin, Phone, Mail, Clock, ArrowRight, Scissors, Star, Shield, Loader2 } from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { TenantBrandProvider, useTenantBrand } from '@/contexts/TenantBrandContext';

interface SalonData {
  id: string;
  name: string;
  slug: string;
  description: string;
  address: string;
  phone: string;
  email: string;
  logo: string | null;
}

interface Service {
  id: string;
  name: string;
  category: string;
  price: number;
  duration: number;
}

interface StaffMember {
  id: string;
  name: string;
  role: string;
}

function SalonLandingPageContent({ params }: { params: { slug: string } }) {
  const router = useRouter();
  const { brand } = useTenantBrand();
  const [salon, setSalon] = useState<SalonData | null>(null);
  const [services, setServices] = useState<Service[]>([]);
  const [staff, setStaff] = useState<StaffMember[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    async function loadData() {
      try {
        const salonData = await apiClient.getSalonBySlug(params.slug);
        setSalon(salonData);
        
        const [servicesData, staffData] = await Promise.all([
          apiClient.getSalonServices(params.slug),
          apiClient.getSalonStaff(params.slug)
        ]);
        
        setServices(servicesData);
        setStaff(staffData);
      } catch (err) {
        console.error("Failed to load salon data", err);
      } finally {
        setIsLoading(false);
      }
    }
    
    loadData();
  }, [params.slug]);

  if (isLoading) {
    return (
      <div className="min-h-screen bg-[#070707] flex items-center justify-center">
        <Loader2 className="w-10 h-10 text-[#FFD700] animate-spin" />
      </div>
    );
  }

  if (!salon) {
    return (
      <div className="min-h-screen bg-[#070707] flex flex-col items-center justify-center text-white">
        <h1 className="text-3xl font-sora font-bold mb-4">Salon Not Found</h1>
        <p className="text-white/50">We couldn't find a salon with this URL.</p>
      </div>
    );
  }

  return (
    <div className="min-h-screen text-white selection:bg-gold/30" style={{ backgroundColor: 'var(--brand-background, #050505)' }}>
      {/* ── HERO SECTION ─────────────────────────────────────────────── */}
      <section className="relative pt-32 pb-24 lg:pt-48 lg:pb-32 overflow-hidden border-b border-white/5">
        <div className="absolute inset-0 z-0">
          <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top,#1a1a1a,#050505_70%)]" />
          <div 
            className="absolute top-0 right-0 w-[800px] h-[800px] rounded-full blur-[120px] mix-blend-screen pointer-events-none"
            style={{ 
              backgroundColor: 'var(--brand-secondary, #C9A227)20',
              background: `radial-gradient(ellipse, var(--brand-secondary, #C9A227)20, transparent)`
            }}
          />
        </div>
        
        <div className="container relative z-10 mx-auto px-6 max-w-5xl text-center">
          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-xs font-mono mb-8"
            style={{ 
              backgroundColor: 'var(--brand-primary, #FFD700)20',
              borderColor: 'var(--brand-primary, #FFD700)30',
              color: 'var(--brand-primary, #FFD700)'
            }}
          >
            <Star className="w-3.5 h-3.5 fill-current" />
            Premium Partner
          </motion.div>
          
          <motion.h1 
            initial={{ opacity: 0, y: 30 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.1 }}
            className="font-sora text-5xl md:text-7xl font-extrabold tracking-tight mb-6"
            style={{ fontFamily: 'var(--brand-font-heading, var(--font-sora))' }}
          >
            Welcome to <br className="hidden md:block" />
            <span 
              className="text-transparent bg-clip-text bg-gradient-to-r"
              style={{ 
                backgroundImage: `linear-gradient(to right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227), var(--brand-primary, #FFD700))`
              }}
            >
              {salon.name}
            </span>
          </motion.h1>
          
          <motion.p 
            initial={{ opacity: 0, y: 30 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2 }}
            className="text-lg md:text-xl text-white/60 mb-10 max-w-2xl mx-auto leading-relaxed"
            style={{ fontFamily: 'var(--brand-font-body, var(--font-inter))' }}
          >
            {salon.description || "Experience the pinnacle of beauty and wellness. Our master stylists and therapists are dedicated to elevating your personal style."}
          </motion.p>
          
          <motion.button
            initial={{ opacity: 0, y: 30 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
            onClick={() => router.push('/book')}
            className="px-8 py-4 text-black font-semibold rounded-2xl shadow-xl flex items-center justify-center gap-3 mx-auto group cursor-pointer transition-all"
            style={{ 
              background: `linear-gradient(to right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))`,
              borderRadius: 'var(--brand-border-radius, 16px)',
              boxShadow: '0 12px 32px var(--brand-primary, #FFD700)30'
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.filter = 'brightness(1.1)';
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.filter = 'brightness(1)';
            }}
          >
            Book an Appointment
            <ArrowRight className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
          </motion.button>
        </div>
      </section>

      {/* ── SERVICES SECTION ─────────────────────────────────────────── */}
      <section className="py-24 relative">
        <div className="container mx-auto px-6 max-w-5xl">
          <div className="text-center mb-16">
            <h2 
              className="font-sora text-3xl font-bold mb-4"
              style={{ fontFamily: 'var(--brand-font-heading, var(--font-sora))' }}
            >
              Our Services
            </h2>
            <p className="text-white/50">Curated treatments for your ultimate transformation.</p>
          </div>
          
          <div className="grid md:grid-cols-2 gap-4">
            {services.slice(0, 6).map((service, i) => (
              <motion.div 
                key={service.id}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: i * 0.05 }}
                className="bg-white/[0.02] border border-white/5 p-5 transition-colors flex justify-between items-center"
                style={{ 
                  borderRadius: 'var(--brand-border-radius, 16px)',
                  boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.4))'
                }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.backgroundColor = 'rgba(255,255,255,0.04)';
                  e.currentTarget.style.borderColor = 'var(--brand-primary, #FFD700)';
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.backgroundColor = 'rgba(255,255,255,0.02)';
                  e.currentTarget.style.borderColor = 'rgba(255,255,255,0.05)';
                }}
              >
                <div>
                  <h4 className="font-sora font-semibold text-white mb-1">{service.name}</h4>
                  <p className="text-white/40 text-sm">{service.duration} mins • {service.category}</p>
                </div>
                <div className="font-mono font-medium" style={{ color: 'var(--brand-primary, #FFD700)' }}>
                  UGX {service.price.toLocaleString()}
                </div>
              </motion.div>
            ))}
          </div>
          
          {services.length === 0 && (
            <div className="text-center py-10 border border-dashed border-white/10 rounded-2xl">
              <p className="text-white/40">Services list is being updated.</p>
            </div>
          )}
        </div>
      </section>

      {/* ── TEAM SECTION ─────────────────────────────────────────────── */}
      {staff.length > 0 && (
        <section className="py-24 bg-white/[0.01] border-y border-white/5">
          <div className="container mx-auto px-6 max-w-5xl">
            <div className="text-center mb-16">
              <h2 
                className="font-sora text-3xl font-bold mb-4"
                style={{ fontFamily: 'var(--brand-font-heading, var(--font-sora))' }}
              >
                Meet Our Experts
              </h2>
              <p className="text-white/50">The masters behind the magic.</p>
            </div>
            
            <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
              {staff.slice(0, 4).map((member, i) => (
                <motion.div 
                  key={member.id}
                  initial={{ opacity: 0, scale: 0.95 }}
                  whileInView={{ opacity: 1, scale: 1 }}
                  viewport={{ once: true }}
                  transition={{ delay: i * 0.1 }}
                  className="text-center"
                >
                  <div 
                    className="w-24 h-24 mx-auto border rounded-full mb-4 flex items-center justify-center text-2xl font-bold text-white/50 uppercase"
                    style={{ 
                      background: 'linear-gradient(to bottom right, rgba(255,255,255,0.1), rgba(255,255,255,0.05))',
                      borderColor: 'rgba(255,255,255,0.1)',
                      borderRadius: 'var(--brand-border-radius, 16px)'
                    }}
                  >
                    {member.name.substring(0, 2)}
                  </div>
                  <h4 className="font-sora font-semibold text-white">{member.name}</h4>
                  <p className="text-xs mt-1" style={{ color: 'var(--brand-primary, #FFD700)' }}>{member.role}</p>
                </motion.div>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* ── ABOUT / CONTACT SECTION ──────────────────────────────────── */}
      <section className="py-24 relative overflow-hidden">
        <div 
          className="absolute bottom-0 left-0 w-[600px] h-[600px] rounded-full blur-[100px] mix-blend-screen pointer-events-none"
          style={{ 
            backgroundColor: 'var(--brand-secondary, #C9A227)5',
            background: `radial-gradient(ellipse, var(--brand-secondary, #C9A227)5, transparent)`
          }}
        />
        
        <div className="container relative z-10 mx-auto px-6 max-w-5xl">
          <div 
            className="bg-gradient-to-br from-white/[0.04] to-transparent border border-white/10 p-8 md:p-12"
            style={{ 
              borderRadius: 'var(--brand-border-radius, 24px)',
              boxShadow: 'var(--brand-shadow-lg, 0 12px 32px rgba(0,0,0,0.6))'
            }}
          >
            <div className="grid md:grid-cols-2 gap-12">
              <div>
                <h3 
                  className="font-sora text-2xl font-bold mb-6"
                  style={{ fontFamily: 'var(--brand-font-heading, var(--font-sora))' }}
                >
                  Visit Us
                </h3>
                <p className="text-white/60 mb-8 max-w-sm leading-relaxed">
                  Ready for your next treatment? Reach out to us or drop by our location.
                </p>
                <div className="space-y-4">
                  {salon.address && (
                    <div className="flex items-start gap-4">
                      <div 
                        className="p-2 bg-white/5 rounded-xl shrink-0"
                        style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
                      >
                        <MapPin className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                      </div>
                      <div className="text-white/80 pt-1">{salon.address}</div>
                    </div>
                  )}
                  {salon.phone && (
                    <div className="flex items-center gap-4">
                      <div 
                        className="p-2 bg-white/5 rounded-xl shrink-0"
                        style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
                      >
                        <Phone className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                      </div>
                      <div className="text-white/80">{salon.phone}</div>
                    </div>
                  )}
                  {salon.email && (
                    <div className="flex items-center gap-4">
                      <div 
                        className="p-2 bg-white/5 rounded-xl shrink-0"
                        style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
                      >
                        <Mail className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                      </div>
                      <div className="text-white/80">{salon.email}</div>
                    </div>
                  )}
                </div>
              </div>
              <div 
                className="bg-black/50 p-6 border border-white/5"
                style={{ 
                  borderRadius: 'var(--brand-border-radius, 16px)',
                  boxShadow: 'var(--brand-shadow-md, 0 6px 18px rgba(0,0,0,0.5))'
                }}
              >
                <h4 
                  className="font-sora font-semibold text-white mb-6 flex items-center gap-2"
                  style={{ fontFamily: 'var(--brand-font-heading, var(--font-sora))' }}
                >
                  <Clock className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                  Business Hours
                </h4>
                <div className="space-y-3 text-sm">
                  {['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].map(day => (
                    <div key={day} className="flex justify-between items-center border-b border-white/5 pb-2 last:border-0 last:pb-0">
                      <span className="text-white/50">{day}</span>
                      <span className="text-white">{day === 'Sunday' ? 'Closed' : '09:00 AM - 06:00 PM'}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
      
      <footer className="py-8 border-t border-white/5 text-center text-white/30 text-sm">
        <p>Powered by Yo Salon Platform</p>
      </footer>
    </div>
  );
}

export default function SalonLandingPage({ params }: { params: { slug: string } }) {
  return (
    <TenantBrandProvider slug={params.slug}>
      <SalonLandingPageContent params={params} />
    </TenantBrandProvider>
  );
}
