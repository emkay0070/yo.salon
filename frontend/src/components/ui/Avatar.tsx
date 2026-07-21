'use client';

import { useState } from 'react';

// ─── Avatar gradient palettes (deterministic by seed string) ──────────────────
// Each palette is designed to look premium on dark backgrounds.
const GRADIENTS = [
  { from: '#7C3AED', to: '#5B21B6' },   // violet
  { from: '#DB2777', to: '#9D174D' },   // rose
  { from: '#D97706', to: '#B45309' },   // amber
  { from: '#0891B2', to: '#0E7490' },   // cyan
  { from: '#059669', to: '#047857' },   // emerald
  { from: '#7C3AED', to: '#C026D3' },   // violet→fuchsia
  { from: '#DC2626', to: '#9F1239' },   // red→rose
  { from: '#2563EB', to: '#1D4ED8' },   // blue
  { from: '#0D9488', to: '#0F766E' },   // teal
  { from: '#EA580C', to: '#C2410C' },   // orange
];

// ─── Seed hash → palette index ────────────────────────────────────────────────
function seedIndex(seed: string): number {
  let hash = 0;
  for (let i = 0; i < seed.length; i++) {
    hash = (hash * 31 + seed.charCodeAt(i)) >>> 0;
  }
  return hash % GRADIENTS.length;
}

// ─── Get initials from a name ─────────────────────────────────────────────────
function getInitials(name: string): string {
  return name
    .trim()
    .split(/\s+/)
    .map(part => part[0]?.toUpperCase() ?? '')
    .slice(0, 2)
    .join('');
}

// ─── Types ────────────────────────────────────────────────────────────────────
export type AvatarShape = 'circle' | 'rounded';
export type AvatarSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl';

export interface AvatarProps {
  /** Display name — used for initials fallback */
  name: string;
  /** Optional photo URL. Shows gradient initials if undefined / empty / errors */
  src?: string | null;
  /** Determines gradient — defaults to `name` if not supplied */
  seed?: string;
  size?: AvatarSize;
  shape?: AvatarShape;
  /** If provided, a coloured presence dot is rendered */
  statusColor?: string;
  className?: string;
}

// ─── Size maps ────────────────────────────────────────────────────────────────
const SIZE_CLASSES: Record<AvatarSize, string> = {
  xs:  'w-6 h-6 text-[9px]',
  sm:  'w-8 h-8 text-[11px]',
  md:  'w-10 h-10 text-sm',
  lg:  'w-14 h-14 text-base',
  xl:  'w-16 h-16 text-xl',
  '2xl': 'w-20 h-20 text-2xl',
};

const DOT_SIZE: Record<AvatarSize, string> = {
  xs:  'w-2 h-2 border',
  sm:  'w-2.5 h-2.5 border',
  md:  'w-3 h-3 border-[1.5px]',
  lg:  'w-3.5 h-3.5 border-2',
  xl:  'w-4 h-4 border-2',
  '2xl': 'w-5 h-5 border-[3px]',
};

// ─── Component ────────────────────────────────────────────────────────────────
export function Avatar({
  name,
  src,
  seed,
  size = 'md',
  shape = 'circle',
  statusColor,
  className = '',
}: AvatarProps) {
  const [imgError, setImgError] = useState(false);

  const effectiveSeed = seed ?? name;
  const { from, to } = GRADIENTS[seedIndex(effectiveSeed)];
  const initials = getInitials(name);
  const borderRadius = shape === 'circle' ? 'rounded-full' : 'rounded-2xl';
  const sizeClass = SIZE_CLASSES[size];
  const showImage = src && !imgError;

  return (
    <div className={`relative flex-shrink-0 ${className}`}>
      {showImage ? (
        <img
          src={src}
          alt={name}
          onError={() => setImgError(true)}
          className={`${sizeClass} ${borderRadius} object-cover ring-1 ring-white/10`}
        />
      ) : (
        <div
          className={`${sizeClass} ${borderRadius} flex items-center justify-center font-semibold text-white ring-1 ring-white/10 select-none`}
          style={{
            background: `linear-gradient(135deg, ${from}, ${to})`,
          }}
        >
          {initials}
        </div>
      )}

      {/* Presence / status dot */}
      {statusColor && (
        <span
          className={`absolute -bottom-0.5 -right-0.5 ${DOT_SIZE[size]} rounded-full border-[#121212]`}
          style={{ background: statusColor }}
        />
      )}
    </div>
  );
}

// ─── Convenience re-export of gradient getter (so other files can stay consistent)
export { seedIndex, getInitials, GRADIENTS };
