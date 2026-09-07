'use client';

import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { Loader2 } from 'lucide-react';
import { PublicSalonExperienceResolver, EffectiveWebsiteConfiguration } from '@/services/PublicSalonExperienceResolver';
import { motion, AnimatePresence } from 'framer-motion';

// Section components
import HeroSection from '@/components/public-salon/sections/HeroSection';
import ServicesSection from '@/components/public-salon/sections/ServicesSection';
import SpecialistsSection from '@/components/public-salon/sections/SpecialistsSection';
import GallerySection from '@/components/public-salon/sections/GallerySection';
import TestimonialsSection from '@/components/public-salon/sections/TestimonialsSection';
import ContactSection from '@/components/public-salon/sections/ContactSection';

const sectionComponents: Record<string, any> = {
  hero: HeroSection,
  services: ServicesSection,
  team: SpecialistsSection,
  specialists: SpecialistsSection,
  gallery: GallerySection,
  testimonials: TestimonialsSection,
  contact: ContactSection,
  book_now: () => null, // Handled by Hero section
};

export default function PublicSalonPage() {
  const params = useParams();
  const router = useRouter();
  const slug = params.slug as string;

  const [experience, setExperience] = useState<EffectiveWebsiteConfiguration | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Reserved app paths that should never be treated as a salon slug
  const RESERVED_SLUGS = new Set([
    'portal', 'login', 'welcome', 'register', 'onboarding',
    'specialist-portal', 'invite', 'dashboard', 'settings',
    'analytics', 'customers', 'bookings', 'staff', 'services',
    'admin', 'api', 'favicon.ico', '_next',
  ]);

  useEffect(() => {
    async function loadExperience() {
      if (!slug) return;

      // Guard: redirect reserved paths away before hitting the API
      if (RESERVED_SLUGS.has(slug.toLowerCase())) {
        router.replace('/');
        return;
      }

      try {
        setLoading(true);
        const resolved = await PublicSalonExperienceResolver.resolve(slug);
        setExperience(resolved);
      } catch (err: any) {
        console.error('Failed to load salon:', err);
        setError('Salon not found');
      } finally {
        setLoading(false);
      }
    }

    loadExperience();
  }, [slug]);


  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#070707]">
        <Loader2 className="w-10 h-10 text-[#FFD700] animate-spin" />
      </div>
    );
  }

  if (error || !experience) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#070707] text-white">
        <p className="text-xl">{error || 'Salon not found'}</p>
      </div>
    );
  }

  const { salon, theme, sections, navigation, colors, brand } = experience;
  const orderedSections = PublicSalonExperienceResolver.getOrderedSections(sections);
  const cssVariables = PublicSalonExperienceResolver.getCssVariables(experience);

  return (
    <div 
      className="min-h-screen"
      style={cssVariables as React.CSSProperties}
    >
      <AnimatePresence mode="wait">
        {orderedSections.map((sectionId, index) => {
          const SectionComponent = sectionComponents[sectionId];
          if (!SectionComponent) return null;

          return (
            <motion.div
              key={sectionId}
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: index * 0.1, duration: 0.5 }}
            >
              <SectionComponent 
                salon={salon}
                theme={theme}
                brand={brand}
                colors={colors}
                bookingUrl={PublicSalonExperienceResolver.getBookingUrl(slug)}
              />
            </motion.div>
          );
        })}
      </AnimatePresence>
    </div>
  );
}
