'use client';

import { useState } from 'react';
import { motion } from 'framer-motion';
import { Heart, Share2, Copy, Check, Globe, MessageSquare, MessageCircle } from 'lucide-react';

interface ShareFavoriteActionsProps {
  isFavorited?: boolean;
  onFavorite?: () => void;
  onShare?: () => void;
  shareUrl?: string;
  variant?: 'horizontal' | 'vertical';
}

export function ShareFavoriteActions({
  isFavorited = false,
  onFavorite,
  onShare,
  shareUrl,
  variant = 'horizontal'
}: ShareFavoriteActionsProps) {
  const [showShareMenu, setShowShareMenu] = useState(false);
  const [copied, setCopied] = useState(false);

  const handleCopyLink = () => {
    if (shareUrl) {
      navigator.clipboard.writeText(shareUrl);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  const containerClass = variant === 'horizontal' 
    ? 'flex items-center gap-2' 
    : 'flex flex-col gap-2';

  return (
    <div className={containerClass}>
      {/* Favorite Button */}
      <motion.button
        whileTap={{ scale: 0.95 }}
        onClick={onFavorite}
        className={`p-3 rounded-xl border transition-all ${
          isFavorited 
            ? 'border-red-500/30 bg-red-500/10' 
            : 'border-border-light bg-surface hover:border-gold/30'
        }`}
        style={{
          borderRadius: 'var(--brand-border-radius, 16px)',
          boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))'
        }}
      >
        <Heart
          className={`w-5 h-5 ${isFavorited ? 'fill-red-500 text-red-500' : 'text-text-primary'}`}
        />
      </motion.button>

      {/* Share Button */}
      <div className="relative">
        <motion.button
          whileTap={{ scale: 0.95 }}
          onClick={() => setShowShareMenu(!showShareMenu)}
          className="p-3 rounded-xl border border-border-light bg-surface hover:border-gold/30 transition-all"
          style={{
            borderRadius: 'var(--brand-border-radius, 16px)',
            boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))'
          }}
        >
          <Share2 className="w-5 h-5 text-text-primary" />
        </motion.button>

        {/* Share Menu */}
        {showShareMenu && (
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            className="absolute top-full right-0 mt-2 p-2 bg-surface border border-border-light rounded-xl shadow-lg z-50 min-w-[200px]"
            style={{
              borderRadius: 'var(--brand-border-radius, 16px)',
              boxShadow: 'var(--brand-shadow-lg, 0 8px 24px rgba(0,0,0,0.10))'
            }}
          >
            <div className="space-y-1">
              <button
                onClick={handleCopyLink}
                className="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-surface/50 transition-colors text-left"
              >
                {copied ? (
                  <Check className="w-4 h-4 text-emerald-500" />
                ) : (
                  <Copy className="w-4 h-4 text-text-secondary" />
                )}
                <span className="text-sm text-text-primary">
                  {copied ? 'Copied!' : 'Copy Link'}
                </span>
              </button>

              <button
                onClick={() => {
                  if (shareUrl) window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`, '_blank');
                }}
                className="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-surface/50 transition-colors text-left"
              >
                <Globe className="w-4 h-4 text-blue-600" />
                <span className="text-sm text-text-primary">Facebook</span>
              </button>

              <button
                onClick={() => {
                  if (shareUrl) window.open(`https://twitter.com/intent/tweet?url=${encodeURIComponent(shareUrl)}`, '_blank');
                }}
                className="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-surface/50 transition-colors text-left"
              >
                <MessageSquare className="w-4 h-4 text-blue-400" />
                <span className="text-sm text-text-primary">Twitter</span>
              </button>

              <button
                onClick={() => {
                  if (shareUrl) window.open(`https://wa.me/?text=${encodeURIComponent(shareUrl)}`, '_blank');
                }}
                className="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-surface/50 transition-colors text-left"
              >
                <MessageCircle className="w-4 h-4 text-emerald-500" />
                <span className="text-sm text-text-primary">WhatsApp</span>
              </button>
            </div>
          </motion.div>
        )}
      </div>
    </div>
  );
}
