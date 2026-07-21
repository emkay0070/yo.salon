'use client';

import { useState } from 'react';
import { useOnboarding } from '@/contexts/OnboardingContext';
import { motion, AnimatePresence } from 'framer-motion';
import { Scissors, Sparkles, Check, ChevronDown, ChevronUp } from 'lucide-react';
import SceneLayout from './SceneLayout';

const serviceCategories = [
  {
    id: 'hair', name: 'Hair', icon: Scissors,
    services: [
      { id: 'haircut',        name: 'Haircut',        defaultPrice: 25000, defaultDuration: 30 },
      { id: 'hair-wash',      name: 'Hair Wash',      defaultPrice: 15000, defaultDuration: 20 },
      { id: 'hair-coloring',  name: 'Hair Coloring',  defaultPrice: 80000, defaultDuration: 90 },
      { id: 'blow-dry',       name: 'Blow Dry',       defaultPrice: 20000, defaultDuration: 30 },
      { id: 'hair-treatment', name: 'Hair Treatment', defaultPrice: 50000, defaultDuration: 45 },
    ],
  },
  {
    id: 'beard', name: 'Beard', icon: Scissors,
    services: [
      { id: 'beard-trim',  name: 'Beard Trim',  defaultPrice: 15000, defaultDuration: 20 },
      { id: 'beard-shape', name: 'Beard Shape', defaultPrice: 20000, defaultDuration: 25 },
      { id: 'beard-color', name: 'Beard Color', defaultPrice: 30000, defaultDuration: 30 },
    ],
  },
  {
    id: 'nails', name: 'Nails', icon: Sparkles,
    services: [
      { id: 'manicure', name: 'Manicure',    defaultPrice: 30000, defaultDuration: 45 },
      { id: 'pedicure', name: 'Pedicure',    defaultPrice: 40000, defaultDuration: 60 },
      { id: 'gel-nails', name: 'Gel Nails',  defaultPrice: 50000, defaultDuration: 60 },
      { id: 'acrylic',  name: 'Acrylic',     defaultPrice: 70000, defaultDuration: 90 },
    ],
  },
  {
    id: 'spa', name: 'Spa', icon: Sparkles,
    services: [
      { id: 'facial',    name: 'Facial',     defaultPrice: 60000, defaultDuration: 60 },
      { id: 'massage',   name: 'Massage',    defaultPrice: 80000, defaultDuration: 60 },
      { id: 'body-scrub',name: 'Body Scrub', defaultPrice: 50000, defaultDuration: 45 },
    ],
  },
  {
    id: 'makeup', name: 'Makeup', icon: Sparkles,
    services: [
      { id: 'bridal-makeup',   name: 'Bridal Makeup',   defaultPrice: 200000, defaultDuration: 120 },
      { id: 'party-makeup',    name: 'Party Makeup',    defaultPrice: 80000,  defaultDuration: 60 },
      { id: 'everyday-makeup', name: 'Everyday Makeup', defaultPrice: 40000,  defaultDuration: 30 },
    ],
  },
];

export default function ServicesScene() {
  const { services, setServices } = useOnboarding();
  const [openCategory, setOpenCategory] = useState<string | null>('hair');

  const isEnabled = (id: string) => services.some((s) => s.id === id);

  const toggle = (cat: typeof serviceCategories[0], svc: typeof serviceCategories[0]['services'][0]) => {
    if (isEnabled(svc.id)) {
      setServices(services.filter((s) => s.id !== svc.id));
    } else {
      setServices([...services, {
        id: svc.id, name: svc.name, category: cat.name,
        price: svc.defaultPrice, duration: svc.defaultDuration, enabled: true,
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

      <div className="space-y-2">
        {serviceCategories.map((cat) => {
          const Icon = cat.icon;
          const catSelected = cat.services.filter(s => isEnabled(s.id)).length;
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
                      {cat.services.map((svc) => {
                        const selected = isEnabled(svc.id);
                        return (
                          <button
                            key={svc.id}
                            onClick={() => toggle(cat, svc)}
                            className={`w-full flex items-center justify-between py-2.5 px-3 rounded-xl transition-all duration-150 ${
                              selected ? 'bg-[#FFD700]/[0.08]' : 'hover:bg-white/[0.03]'
                            }`}
                          >
                            <span className={`text-sm ${selected ? 'text-white' : 'text-white/50'}`}>{svc.name}</span>
                            <div className="flex items-center gap-3">
                              <span className="text-xs text-white/25">{svc.defaultDuration} min</span>
                              <div className={`w-5 h-5 rounded-md border flex items-center justify-center transition-all ${
                                selected ? 'bg-[#FFD700] border-[#FFD700]' : 'border-white/20'
                              }`}>
                                {selected && <Check className="w-3 h-3 text-black" />}
                              </div>
                            </div>
                          </button>
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
    </SceneLayout>
  );
}
