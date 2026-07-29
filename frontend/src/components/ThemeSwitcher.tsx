'use client';

import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Palette, Check, ChevronDown } from 'lucide-react';
import { useTheme, ThemePreset } from '@/contexts/ThemeContext';

export default function ThemeSwitcher() {
  const { theme, setTheme, allPresets } = useTheme();
  const [isOpen, setIsOpen] = useState(false);

  const currentTheme = allPresets[theme];

  return (
    <div className="relative">
      <motion.button
        whileHover={{ scale: 1.02 }}
        whileTap={{ scale: 0.98 }}
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/[0.05] border border-white/[0.08] hover:bg-white/[0.08] transition-colors"
      >
        <Palette className="w-4 h-4 text-white/60" />
        <span className="text-sm text-white/90">{currentTheme.name}</span>
        <ChevronDown className={`w-4 h-4 text-white/40 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
      </motion.button>

      <AnimatePresence>
        {isOpen && (
          <>
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setIsOpen(false)}
              className="fixed inset-0 z-40"
            />
            <motion.div
              initial={{ opacity: 0, y: -10, scale: 0.95 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              exit={{ opacity: 0, y: -10, scale: 0.95 }}
              transition={{ duration: 0.2 }}
              className="absolute top-full right-0 mt-2 z-50 w-64 rounded-2xl bg-[#1A1A1A] border border-white/[0.1] shadow-2xl overflow-hidden"
            >
              <div className="p-2">
                {(Object.keys(allPresets) as ThemePreset[]).map((preset) => {
                  const config = allPresets[preset];
                  const isSelected = theme === preset;

                  return (
                    <motion.button
                      key={preset}
                      whileHover={{ scale: 1.01 }}
                      whileTap={{ scale: 0.99 }}
                      onClick={() => {
                        setTheme(preset);
                        setIsOpen(false);
                      }}
                      className={`w-full flex items-center gap-3 p-3 rounded-xl transition-all ${
                        isSelected
                          ? 'bg-white/[0.08] border border-white/[0.15]'
                          : 'hover:bg-white/[0.04] border border-transparent'
                      }`}
                    >
                      <div
                        className="w-8 h-8 rounded-lg flex-shrink-0"
                        style={{
                          background: `linear-gradient(135deg, ${config.colors.accent}20, ${config.colors.surface})`,
                          border: `1px solid ${config.colors.border}`,
                        }}
                      />
                      <div className="flex-1 text-left">
                        <p className="text-sm font-medium text-white">{config.name}</p>
                        <p className="text-xs text-white/50">{config.description}</p>
                      </div>
                      {isSelected && (
                        <Check className="w-4 h-4 text-[#FFD700] flex-shrink-0" />
                      )}
                    </motion.button>
                  );
                })}
              </div>
            </motion.div>
          </>
        )}
      </AnimatePresence>
    </div>
  );
}
