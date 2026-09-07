'use client';

import { motion, AnimatePresence } from 'framer-motion';
import { X } from 'lucide-react';
import { useEffect } from 'react';

interface SideDrawerProps {
  isOpen: boolean;
  onClose: () => void;
  children: React.ReactNode;
  title?: string;
  position?: 'left' | 'right' | 'top';
  width?: 'default' | 'wide' | 'full';
}

export default function SideDrawer({ isOpen, onClose, children, title, position = 'right', width = 'default' }: SideDrawerProps) {
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [isOpen]);

  const slideDirectionX = position === 'left' ? '-100%' : (position === 'right' ? '100%' : 0);
  const slideDirectionY = position === 'top' ? '-100%' : 0;

  const getWidthClasses = () => {
    if (width === 'full') return 'w-full max-w-none';
    if (width === 'wide') return 'w-full max-w-[560px]';
    return 'w-full max-w-md';
  };

  const positionClasses =
    position === 'left' ? 'left-0 top-0 h-full border-r md:max-w-md md:border-r' :
    position === 'right' ? 'right-0 top-0 h-full border-l' :
    'top-4 left-0 right-0 w-[calc(100%-2rem)] max-h-[85vh] rounded-3xl border mx-auto max-w-3xl';

  const mobileClasses = position === 'right' ? 'md:right-0 right-0 bottom-0 md:top-0 top-auto md:h-full h-[85vh] md:max-w-md w-full' : '';

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.2 }}
            onClick={onClose}
            className="fixed inset-0 bg-black/60 backdrop-blur-sm z-[60]"
          />

          {/* Drawer */}
          <motion.div
            initial={{ x: slideDirectionX, y: slideDirectionY }}
            animate={{ x: 0, y: 0 }}
            exit={{ x: slideDirectionX, y: slideDirectionY }}
            transition={{ type: 'spring', damping: 32, stiffness: 280 }}
            className={`fixed bg-card border-border-light z-[60] flex flex-col shadow-2xl ${positionClasses} ${getWidthClasses()} ${mobileClasses}`}
          >
            {/* Header */}
            <motion.div
              initial={{ opacity: 0, y: -10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.1, duration: 0.3 }}
              className="flex items-center justify-between p-6 border-b border-border-light shrink-0"
            >
              {title && <h2 className="text-lg font-bold text-text-primary">{title}</h2>}
              <motion.button
                whileHover={{ scale: 1.1 }}
                whileTap={{ scale: 0.95 }}
                onClick={onClose}
                className="text-text-secondary hover:text-text-primary transition-colors"
              >
                <X className="w-5 h-5" />
              </motion.button>
            </motion.div>

            {/* Content */}
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ delay: 0.2, duration: 0.3 }}
              className="flex-1 overflow-y-auto custom-scrollbar pb-20"
            >
              {children}
            </motion.div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}
