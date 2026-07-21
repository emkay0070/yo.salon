'use client';

import { createContext, useContext, useState, useEffect, ReactNode } from 'react';

interface SuggestedFeature {
  feature: string;
  reason: string;
  confidence: 'high' | 'medium' | 'low';
}

interface CapabilityContextType {
  availableFeatures: string[];
  suggestedFeatures: SuggestedFeature[];
  hasFeature: (feature: string) => boolean;
  getAvailableFeatures: () => string[];
  getSuggestedFeatures: () => SuggestedFeature[];
  setAvailableFeatures: (features: string[]) => void;
  setSuggestedFeatures: (features: SuggestedFeature[]) => void;
}

const CapabilityContext = createContext<CapabilityContextType | undefined>(undefined);

export function CapabilityProvider({ children }: { children: ReactNode }) {
  const [availableFeatures, setAvailableFeatures] = useState<string[]>([]);
  const [suggestedFeatures, setSuggestedFeatures] = useState<SuggestedFeature[]>([]);

  const hasFeature = (feature: string): boolean => {
    return availableFeatures.includes(feature);
  };

  const getAvailableFeatures = (): string[] => {
    return availableFeatures;
  };

  const getSuggestedFeatures = (): SuggestedFeature[] => {
    return suggestedFeatures;
  };

  return (
    <CapabilityContext.Provider
      value={{
        availableFeatures,
        suggestedFeatures,
        hasFeature,
        getAvailableFeatures,
        getSuggestedFeatures,
        setAvailableFeatures,
        setSuggestedFeatures,
      }}
    >
      {children}
    </CapabilityContext.Provider>
  );
}

export function useCapability() {
  const context = useContext(CapabilityContext);
  if (context === undefined) {
    throw new Error('useCapability must be used within a CapabilityProvider');
  }
  return context;
}
