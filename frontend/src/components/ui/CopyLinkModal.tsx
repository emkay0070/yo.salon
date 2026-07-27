import React, { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, Copy, CheckCircle2, Link as LinkIcon } from 'lucide-react';

interface CopyLinkModalProps {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  description: string;
  link: string;
}

export function CopyLinkModal({ isOpen, onClose, title, description, link }: CopyLinkModalProps) {
  const [copied, setCopied] = useState(false);

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(link);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch (err) {
      console.error('Failed to copy text: ', err);
      // Fallback
      const textArea = document.createElement('textarea');
      textArea.value = link;
      document.body.appendChild(textArea);
      textArea.select();
      document.execCommand('copy');
      document.body.removeChild(textArea);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
          />
          <motion.div
            initial={{ opacity: 0, scale: 0.95, y: 10 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.95, y: 10 }}
            className="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-[var(--color-card)] border border-[var(--color-border-medium)] rounded-2xl shadow-2xl z-50 overflow-hidden"
          >
            {/* Header */}
            <div className="flex items-center justify-between p-5 border-b border-[var(--color-border-light)] bg-white/5">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-[var(--color-gold)]/20 flex items-center justify-center border border-[var(--color-gold)]/30">
                  <LinkIcon className="w-5 h-5 text-[var(--color-gold)]" />
                </div>
                <h3 className="text-lg font-bold text-[var(--color-text-primary)]">{title}</h3>
              </div>
              <button
                onClick={onClose}
                className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/10 text-[var(--color-text-secondary)] hover:text-white transition-colors"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Body */}
            <div className="p-6 space-y-6">
              <p className="text-[var(--color-text-secondary)] text-sm leading-relaxed">
                {description}
              </p>

              <div className="space-y-2">
                <label className="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wider">
                  Invitation Link
                </label>
                <div className="flex items-center gap-2">
                  <div className="flex-1 bg-black/40 border border-[var(--color-border-medium)] rounded-xl px-4 py-3 flex items-center overflow-hidden">
                    <span className="text-[var(--color-text-primary)] text-sm font-mono truncate w-full opacity-80">
                      {link}
                    </span>
                  </div>
                  <button
                    onClick={handleCopy}
                    className="shrink-0 flex items-center justify-center w-12 h-[46px] bg-[var(--color-gold)] hover:bg-[var(--color-dark-gold)] text-black rounded-xl transition-colors"
                  >
                    {copied ? (
                      <CheckCircle2 className="w-5 h-5" />
                    ) : (
                      <Copy className="w-5 h-5" />
                    )}
                  </button>
                </div>
              </div>
            </div>

            {/* Footer */}
            <div className="p-5 border-t border-[var(--color-border-light)] bg-black/20 flex justify-end">
              <button
                onClick={onClose}
                className="px-6 py-2.5 bg-white/10 hover:bg-white/15 text-white rounded-xl text-sm font-medium transition-colors"
              >
                Done
              </button>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}
