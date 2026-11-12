'use client';

import { useState, useEffect } from 'react';
import { useOnboarding } from '@/contexts/OnboardingContext';
import { motion, AnimatePresence } from 'framer-motion';
import { Scissors, Check, ChevronDown, ChevronUp, Loader2 } from 'lucide-react';
import SceneLayout from './SceneLayout';
import { apiClient } from '@/lib/api-client';

interface ServiceTemplate {
  id: string;
  slug: string;
  name: string;
  description?: string;
  icon?: string;
  is_recommended: boolean;
}

interface ServiceCategory {
  id: string;
  slug: string;
  name: string;
  short_name?: string;
  description?: string;
  icon?: string;
  templates: ServiceTemplate[];
}

export default function ServicesScene() {
  const { services, setServices, providerData } = useOnboarding();
  const [categories, setCategories] = useState<ServiceCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [openCategory, setOpenCategory] = useState<string | null>(null);

  // Fetch service templates based on selected business category
  useEffect(() => {
    const fetchServiceTemplates = async () => {
      if (!providerData.category) {
        setLoading(false);
        return;
      }

      try {
        const data = await apiClient.get('/v1/service-templates', { category_slug: providerData.category });
        
        if (data.data && data.data.length > 0) {
          // Group templates by their category (if available) or just show as a single category
          const categoryData: ServiceCategory = {
            id: data.category.slug,
            slug: data.category.slug,
            name: data.category.name,
            short_name: data.category.short_name,
            description: data.category.description,
            icon: data.category.icon,
            templates: data.data.map((template: any) => ({
              id: template.slug,
              slug: template.slug,
              name: template.name,
              description: template.description,
              icon: template.icon,
              is_recommended: template.is_recommended,
            })),
          };
          
          setCategories([categoryData]);
          setOpenCategory(data.category.slug);
        }
      } catch (error) {
        console.error('Failed to fetch service templates:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchServiceTemplates();
  }, [providerData.category]);

  const isEnabled = (id: string) => services.some((s) => s.id === id);

  const toggle = (cat: ServiceCategory, svc: ServiceTemplate) => {
    if (isEnabled(svc.id)) {
      setServices(services.filter((s) => s.id !== svc.id));
    } else {
      setServices([...services, {
        id: svc.id, 
        name: svc.name, 
        category: cat.name,
        price: '', // No default price - business sets their own
        duration: 0, // Default to 0, business sets their own
        enabled: true,
      }]);
    }
  };

  return (
    <SceneLayout nextLabel={services.length > 0 ? `Continue (${services.length} selected)` : 'Skip for now'}>
      <div className="text-center mb-8">
        <p className="text-[#FFD700]/70 text-xs font-semibold tracking-widest uppercase mb-3">Services</p>
        <h2 className="text-3xl md:text-4xl font-bold text-white mb-3">
          What services do you offer?
        </h2>
        <p className="text-white/40 text-base">
          Tick the ones you offer. You can set exact prices after setup.
        </p>
      </div>

      {loading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="w-8 h-8 text-[#FFD700] animate-spin" />
        </div>
      ) : !providerData.category ? (
        <div className="text-center py-12">
          <p className="text-white/40 text-sm">
            Please select a business category first to see recommended services.
          </p>
        </div>
      ) : categories.length === 0 ? (
        <div className="text-center py-12">
          <p className="text-white/40 text-sm">
            No service templates available for this category yet.
          </p>
        </div>
      ) : (
        <div className="space-y-2">
          {categories.map((cat) => {
            const Icon = Scissors; // Default icon, can be customized based on cat.icon
            const catSelected = cat.templates.filter(s => isEnabled(s.id)).length;
            const isOpen = openCategory === cat.id;

            return (
              <div key={cat.id} className="rounded-2xl border border-white/[0.06] bg-white/[0.02] overflow-hidden">
                {/* Category header */}
                <button
                  onClick={() => setOpenCategory(isOpen ? null : cat.id)}
                  className="w-full flex items-center justify-between px-5 py-4 hover:bg-white/[0.03] transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <Icon className="w-4 h-4 text-white/40" />
                    <span className="text-sm font-semibold text-white/80">{cat.name}</span>
                    {catSelected > 0 && (
                      <span className="text-xs bg-[#FFD700]/20 text-[#FFD700] px-2 py-0.5 rounded-full font-semibold">
                        {catSelected}
                      </span>
                    )}
                  </div>
                  {isOpen ? <ChevronUp className="w-4 h-4 text-white/30" /> : <ChevronDown className="w-4 h-4 text-white/30" />}
                </button>

                {/* Services list */}
                <AnimatePresence>
                  {isOpen && (
                    <motion.div
                      initial={{ height: 0, opacity: 0 }}
                      animate={{ height: 'auto', opacity: 1 }}
                      exit={{ height: 0, opacity: 0 }}
                      transition={{ duration: 0.25 }}
                      className="overflow-hidden border-t border-white/[0.04]"
                    >
                      <div className="px-5 py-3 space-y-2">
                        {cat.templates.map((svc) => {
                          const selected = isEnabled(svc.id);
                          const currentService = services.find((s) => s.id === svc.id);
                          const currentPrice = currentService ? currentService.price : '';

                          return (
                            <div
                              key={svc.id}
                              className={`flex flex-col md:flex-row md:items-center justify-between p-3.5 rounded-xl border transition-all duration-150 gap-3 ${
                                selected ? 'bg-[#FFD700]/[0.08] border-[#FFD700]/30' : 'hover:bg-white/[0.03] border-transparent'
                              }`}
                            >
                              <div className="flex items-center gap-3">
                                <button
                                  type="button"
                                  onClick={() => toggle(cat, svc)}
                                  className={`w-5 h-5 rounded-md border flex items-center justify-center transition-all ${
                                    selected ? 'bg-[#FFD700] border-[#FFD700]' : 'border-white/20'
                                  }`}
                                >
                                  {selected && <Check className="w-3 h-3 text-black" />}
                                </button>
                                <div className="flex flex-col">
                                  <span className={`text-sm font-medium ${selected ? 'text-white' : 'text-white/50'}`}>{svc.name}</span>
                                  {svc.description && (
                                    <span className="text-xs text-white/25">{svc.description}</span>
                                  )}
                                </div>
                              </div>
                              
                              {selected && (
                                <div className="flex items-center gap-2 pl-8 md:pl-0">
                                  <span className="text-xs text-[#FFD700]/70 font-semibold">UGX</span>
                                  <input
                                    type="number"
                                    value={currentPrice}
                                    placeholder="Price"
                                    onChange={(e) => {
                                      const newPrice = e.target.value === '' ? '' : Math.max(0, parseInt(e.target.value) || 0);
                                      setServices(
                                        services.map((s) =>
                                          s.id === svc.id ? { ...s, price: newPrice } : s
                                        )
                                      );
                                    }}
                                    className="w-24 px-2 py-1 rounded bg-white/[0.04] border border-white/[0.1] text-white focus:outline-none focus:border-[#FFD700]/40 text-sm font-semibold text-right"
                                  />
                                </div>
                              )}
                            </div>
                          );
                        })}
                      </div>
                    </motion.div>
                  )}
                </AnimatePresence>
              </div>
            );
          })}
        </div>
      )}
    </SceneLayout>
  );
}
