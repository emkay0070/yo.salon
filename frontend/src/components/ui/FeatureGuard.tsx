'use client';

import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { ReactNode } from 'react';

interface FeatureGuardProps {
  feature: string;
  children: ReactNode;
  fallback?: ReactNode;
}

export function FeatureGuard({ feature, children, fallback = null }: FeatureGuardProps) {
  const { capabilities } = usePortalAuth();

  const hasFeature = (feature: string): boolean => {
    return !!capabilities?.[feature];
  };

  if (!hasFeature(feature)) {
    return <>{fallback}</>;
  }

  return <>{children}</>;
}

interface ProgressiveSectionProps {
  customerTier: 'new' | 'growing' | 'loyal';
  children: {
    new?: ReactNode;
    growing?: ReactNode;
    loyal?: ReactNode;
  };
}

export function ProgressiveSection({ customerTier, children }: ProgressiveSectionProps) {
  if (customerTier === 'new' && children.new) {
    return <>{children.new}</>;
  }

  if (customerTier === 'growing' && children.growing) {
    return <>{children.growing}</>;
  }

  if (customerTier === 'loyal' && children.loyal) {
    return <>{children.loyal}</>;
  }

  // Default to growing tier if no match
  if (children.growing) {
    return <>{children.growing}</>;
  }

  return null;
}
