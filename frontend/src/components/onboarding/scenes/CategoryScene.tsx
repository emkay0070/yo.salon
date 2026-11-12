'use client';

import { useOnboarding } from '@/contexts/OnboardingContext';
import { useCategory } from '@/contexts/CategoryContext';
import { apiClient } from '@/lib/api-client';
import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';

interface BusinessCategory {
  id: string;
  provider_type?: string;
  slug: string;
  name: string;
  short_name?: string;
  description?: string;
  icon?: string;
}

// Custom SVG icons for each category
const CategoryIcons: Record<string, React.ReactNode> = {
  'hair_salon': (
    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M20 24C20 24 22 16 32 16C42 16 44 24 44 24" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
      <path d="M18 24C18 24 16 32 16 40C16 48 20 52 32 52C44 52 48 48 48 40C48 32 46 24 46 24" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
      <path d="M24 28L28 44" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
      <path d="M32 28V44" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
      <path d="M40 28L36 44" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
    </svg>
  ),
  'barbershop': (
    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="24" y="12" width="16" height="40" rx="2" stroke="currentColor" strokeWidth="2"/>
      <path d="M24 20H40" stroke="currentColor" strokeWidth="2"/>
      <path d="M24 28H40" stroke="currentColor" strokeWidth="2"/>
      <path d="M24 36H40" stroke="currentColor" strokeWidth="2"/>
      <path d="M24 44H40" stroke="currentColor" strokeWidth="2"/>
      <circle cx="32" cy="8" r="2" fill="currentColor"/>
    </svg>
  ),
  'nail_studio': (
    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M32 12C32 12 24 20 24 32C24 40 28 44 32 44C36 44 40 40 40 32C40 20 32 12 32 12Z" stroke="currentColor" strokeWidth="2"/>
      <path d="M32 44V52" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
      <path d="M28 48L36 48" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
    </svg>
  ),
  'spa': (
    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M32 8C32 8 20 16 20 28C20 36 24 40 32 40C40 40 44 36 44 28C44 16 32 8 32 8Z" stroke="currentColor" strokeWidth="2"/>
      <path d="M32 40V48" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
      <path d="M24 52L40 52" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
      <path d="M28 56L36 56" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
    </svg>
  ),
  'wellness_center': (
    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="32" cy="24" r="8" stroke="currentColor" strokeWidth="2"/>
      <path d="M32 32V48" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
      <path d="M24 40L40 40" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
      <path d="M20 48L44 48" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
    </svg>
  ),
};

export default function CategoryScene() {
  const { providerData, setProviderData, goNext, goPrev } = useOnboarding();
  const { setCategorySlug } = useCategory();
  const [categories, setCategories] = useState<BusinessCategory[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchCategories = async () => {
      try {
        // Fetch all categories initially (no filter)
        const response = await apiClient.getBusinessCategories();
        if (response.data) {
          setCategories(response.data);
        }
      } catch (error) {
        console.error('Failed to fetch business categories:', error);
        // Fallback to hardcoded categories for development
        setCategories([
          { id: '1', slug: 'hair_salon', name: 'Hair Salon', short_name: 'Salon', description: 'Hair • Styling • Beauty' },
          { id: '2', slug: 'barbershop', name: 'Barbershop', short_name: 'Barber', description: 'Cuts • Grooming • Beard' },
          { id: '3', slug: 'nail_studio', name: 'Nail Studio', short_name: 'Nails', description: 'Nails • Art • Care' },
          { id: '4', slug: 'spa', name: 'Spa', short_name: 'Spa', description: 'Treatments • Relaxation • Wellness' },
          { id: '5', slug: 'wellness_center', name: 'Wellness Center', short_name: 'Wellness', description: 'Care • Movement • Wellbeing' },
        ]);
      } finally {
        setLoading(false);
      }
    };

    fetchCategories();
  }, []);

  const isValid = !!providerData.category;

  const handleCategorySelect = (categorySlug: string) => {
    const selectedCategory = categories.find(c => c.slug === categorySlug);
    const providerType = selectedCategory?.provider_type || 'salon';
    setProviderData({ ...providerData, category: categorySlug, providerType });
    setCategorySlug(categorySlug);
  };

  const getIcon = (slug: string) => {
    return CategoryIcons[slug] || CategoryIcons['hair_salon'];
  };

  if (loading) {
    return (
      <div className="flex-1 flex flex-col items-center justify-center px-4 py-8 bg-black">
        <div className="w-8 h-8 border-2 border-[#FFD700]/30 border-t-[#FFD700] rounded-full animate-spin" />
      </div>
    );
  }

  return (
    <div className="flex-1 flex flex-col items-center justify-center px-4 py-8 lg:py-12 min-h-screen bg-black">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.6 }}
        className="text-center mb-12"
      >
        <p className="text-[#FFD700]/40 text-xs font-semibold tracking-widest uppercase mb-4">Your Universe</p>
        <h2 className="text-2xl md:text-3xl font-bold text-white/90 mb-4">
          What kind of space do you want to create?
        </h2>
        <p className="text-white/25 text-sm max-w-md mx-auto">
          Choose the world your clients will step into.
        </p>
      </motion.div>

      {/* Category Grid */}
      <div className="grid grid-cols-2 md:grid-cols-3 gap-3 max-w-4xl w-full mb-12">
        {categories.map((category, index) => {
          const isSelected = providerData.category === category.slug;

          return (
            <motion.button
              key={category.id}
              type="button"
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.4, delay: index * 0.1 }}
              onClick={() => handleCategorySelect(category.slug)}
              className={`
                relative p-4 rounded-xl border text-left
                transition-all duration-300
                ${isSelected 
                  ? 'bg-gradient-to-br from-[#FFD700]/25 to-[#FFD700]/8 border-[#FFD700]/50 shadow-2xl shadow-[#FFD700]/30' 
                  : 'bg-white/[0.04] border-white/[0.10] hover:bg-white/[0.08] hover:border-white/[0.20]'
                }
              `}
            >
              {/* Icon */}
              <div className={`
                w-10 h-10 mb-3 rounded-lg flex items-center justify-center
                ${isSelected ? 'bg-[#FFD700]/25 text-[#FFD700]' : 'bg-white/[0.06] text-white/50'}
              `}>
                <div className="w-5 h-5">
                  {getIcon(category.slug)}
                </div>
              </div>

              {/* Name */}
              <h3 className={`
                font-semibold mb-0.5 text-sm
                ${isSelected ? 'text-white' : 'text-white/70'}
              `}>
                {category.name}
              </h3>

              {/* Description */}
              {category.description && (
                <p className="text-[10px] text-white/35 leading-relaxed">
                  {category.description}
                </p>
              )}

              {/* Selection indicator */}
              {isSelected && (
                <motion.div
                  initial={{ scale: 0 }}
                  animate={{ scale: 1 }}
                  className="absolute top-3 right-3 w-4 h-4 rounded-full bg-[#FFD700] flex items-center justify-center shadow-lg shadow-[#FFD700]/30"
                >
                  <svg className="w-3 h-3 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" />
                  </svg>
                </motion.div>
              )}
            </motion.button>
          );
        })}
      </div>

      {/* Navigation */}
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ delay: 0.4 }}
        className="flex items-center justify-between w-full max-w-4xl"
      >
        <button
          onClick={goPrev}
          className="flex items-center gap-2 px-3 py-2 rounded-lg text-white/30 hover:text-white/70 hover:bg-white/[0.04] transition-all duration-200 text-xs"
        >
          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
          </svg>
          Back
        </button>

        <button
          onClick={goNext}
          disabled={!isValid}
          className="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black font-semibold text-xs hover:brightness-110 active:scale-[0.97] transition-all duration-200 shadow-xl shadow-[#FFD700]/10 disabled:opacity-40 disabled:cursor-not-allowed disabled:shadow-none"
        >
          Continue
          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </motion.div>
    </div>
  );
}
