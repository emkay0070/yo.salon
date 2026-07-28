'use client';

import { createContext, useContext, useState, useCallback, useRef, ReactNode } from 'react';
import { apiClient } from '@/lib/api-client';

interface SalonData {
  name: string;
  logo?: string;
  phone: string;
  email: string;
  address: string;
  lat?: number;
  lng?: number;
  timezone: string;
  currency: string;
  description: string;
  category: string;
  vibe: string;
  businessType: string;
  teamSize: string;
  branches: string;
  draftSlug: string;
  slugAvailable: boolean | null;
}

interface StaffMember {
  id: string;
  name: string;
  role: string;
  phone: string;
  photo?: string;
}

interface Service {
  id: string;
  name: string;
  category: string;
  price: number | string;
  duration: number;
  enabled: boolean;
}

interface WorkspaceData {
  workingDays: string[];
  openingHours: { [key: string]: { open: string; close: string } };
  appointmentDuration: number;
  paymentMethods: string[];
}

// All 10 flat scenes in order
export const SCENES = [
  'welcome',
  'salon-identity',
  'salon-story',
  'salon-contact',
  'services',
  'team',
  'workspace',
  'launch-preview',
  'membership',
  'celebration',
] as const;

export type Scene = typeof SCENES[number];

// Which backend step each scene belongs to (for draft saving)
const SCENE_TO_DRAFT_STEP: Partial<Record<Scene, string>> = {
  'salon-identity': 'salon',
  'salon-story': 'salon',
  'salon-contact': 'salon',
  'services': 'services',
  'team': 'team',
  'workspace': 'wallet',
  'launch-preview': 'salon',
  'membership': 'membership',
};

// Scene metadata for progress header
export const SCENE_META: Record<Scene, { label: string; group: string }> = {
  'welcome':        { label: 'Welcome',      group: 'Start' },
  'salon-identity': { label: 'Your brand',   group: 'Salon' },
  'salon-story':    { label: 'Your story',   group: 'Salon' },
  'salon-contact':  { label: 'Contact',      group: 'Salon' },
  'services':       { label: 'Services',     group: 'Services' },
  'team':           { label: 'Your team',    group: 'Team' },
  'workspace':      { label: 'Workspace',    group: 'Workspace' },
  'launch-preview': { label: 'Preview',      group: 'Launch' },
  'membership':     { label: 'Plan',         group: 'Plan' },
  'celebration':    { label: 'Done!',        group: 'Done' },
};

interface OnboardingContextType {
  scene: Scene;
  sceneIndex: number;
  totalScenes: number;
  progress: number;
  salonData: SalonData;
  staff: StaffMember[];
  services: Service[];
  workspaceData: WorkspaceData;
  selectedPlanId: string | null;
  loading: boolean;
  isSaving: boolean;
  goNext: () => void;
  goPrev: () => void;
  nextStep: () => void;
  prevStep: () => void;
  setSalonData: (data: SalonData) => void;
  setStaff: (staff: StaffMember[]) => void;
  setServices: (services: Service[]) => void;
  setWorkspaceData: (data: WorkspaceData) => void;
  setSelectedPlanId: (planId: string | null) => void;
  autoSave: (step: string, data: any) => void;
  completeOnboarding: () => Promise<any>;
  resetOnboarding: () => void;
}

const OnboardingContext = createContext<OnboardingContextType | undefined>(undefined);

const defaultSalonData: SalonData = {
  name: '',
  phone: '',
  email: '',
  address: '',
  timezone: 'Africa/Kampala',
  currency: 'UGX',
  description: '',
  category: '',
  vibe: '',
  businessType: '',
  teamSize: '',
  branches: 'one',
  draftSlug: '',
  slugAvailable: null,
};

const defaultWorkspaceData: WorkspaceData = {
  workingDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
  openingHours: {
    monday:    { open: '09:00', close: '18:00' },
    tuesday:   { open: '09:00', close: '18:00' },
    wednesday: { open: '09:00', close: '18:00' },
    thursday:  { open: '09:00', close: '18:00' },
    friday:    { open: '09:00', close: '18:00' },
    saturday:  { open: '09:00', close: '18:00' },
    sunday:    { open: '09:00', close: '18:00' },
  },
  appointmentDuration: 30,
  paymentMethods: ['cash'],
};

export function OnboardingProvider({ children }: { children: ReactNode }) {
  const [sceneIndex, setSceneIndex] = useState(0);
  const [salonData, setSalonDataState] = useState<SalonData>(defaultSalonData);
  const [staff, setStaffState] = useState<StaffMember[]>([]);
  const [services, setServicesState] = useState<Service[]>([]);
  const [workspaceData, setWorkspaceDataState] = useState<WorkspaceData>(defaultWorkspaceData);
  const [selectedPlanId, setSelectedPlanId] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [isSaving, setIsSaving] = useState(false);

  // Debounce timer ref for autosave
  const saveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const slugTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const scene = SCENES[sceneIndex];
  const totalScenes = SCENES.length;
  // Exclude welcome and celebration from progress calculation
  const progressScenes = SCENES.filter(s => s !== 'welcome' && s !== 'celebration');
  const progressIndex = progressScenes.indexOf(scene as any);
  const progress = progressIndex < 0 ? 0 : Math.round(((progressIndex + 1) / progressScenes.length) * 100);

  // Debounced autosave — fires 800ms after the last call
  const autoSave = useCallback((step: string, data: any) => {
    if (saveTimer.current) clearTimeout(saveTimer.current);
    saveTimer.current = setTimeout(async () => {
      try {
        setIsSaving(true);
        await apiClient.saveOnboardingDraft(step, data);
      } catch {
        // Silent fail — we'll save again on next interaction
      } finally {
        setIsSaving(false);
      }
    }, 800);
  }, []);

  // Setters with autosave built in
  const setSalonData = useCallback((data: SalonData) => {
    let nextData = { ...data };
    
    // Check if name changed to derive slug
    if (nextData.name !== salonData.name) {
      const derivedSlug = nextData.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)+/g, '');
      nextData.draftSlug = derivedSlug;
      nextData.slugAvailable = null;
      
      if (slugTimer.current) clearTimeout(slugTimer.current);
      if (derivedSlug) {
        slugTimer.current = setTimeout(async () => {
          try {
            const res = await apiClient.checkSlug(derivedSlug);
            setSalonDataState(prev => ({
              ...prev,
              slugAvailable: res.available,
              draftSlug: res.available ? derivedSlug : (res.suggestions?.[0] || derivedSlug + '-1')
            }));
          } catch (e) {
            console.error('Failed to check slug', e);
          }
        }, 600);
      }
    }
    
    setSalonDataState(nextData);
    autoSave('salon', nextData);
  }, [autoSave, salonData.name]);

  const setStaff = useCallback((newStaff: StaffMember[]) => {
    setStaffState(newStaff);
    autoSave('team', newStaff);
  }, [autoSave]);

  const setServices = useCallback((newServices: Service[]) => {
    setServicesState(newServices);
    autoSave('services', newServices);
  }, [autoSave]);

  const setWorkspaceData = useCallback((data: WorkspaceData) => {
    setWorkspaceDataState(data);
    autoSave('wallet', data);
  }, [autoSave]);

  const goNext = useCallback(() => {
    setSceneIndex(prev => Math.min(prev + 1, SCENES.length - 1));
  }, []);

  const goPrev = useCallback(() => {
    setSceneIndex(prev => Math.max(prev - 1, 0));
  }, []);

  const resetOnboarding = useCallback(() => {
    setSceneIndex(0);
    setSalonDataState(defaultSalonData);
    setStaffState([]);
    setServicesState([]);
    setWorkspaceDataState(defaultWorkspaceData);
    setSelectedPlanId(null);
  }, []);

  const completeOnboarding = useCallback(async () => {
    setLoading(true);
    try {
      // Final sync of membership plan
      await apiClient.saveOnboardingDraft('membership', { plan_id: selectedPlanId });
      const result = await apiClient.completeOnboarding();
      return result;
    } finally {
      setLoading(false);
    }
  }, [selectedPlanId]);

  return (
    <OnboardingContext.Provider
      value={{
        scene,
        sceneIndex,
        totalScenes,
        progress,
        salonData,
        staff,
        services,
        workspaceData,
        selectedPlanId,
        loading,
        isSaving,
        goNext,
        goPrev,
        nextStep: goNext,
        prevStep: goPrev,
        setSalonData,
        setStaff,
        setServices,
        setWorkspaceData,
        setSelectedPlanId,
        autoSave,
        completeOnboarding,
        resetOnboarding,
      }}
    >
      {children}
    </OnboardingContext.Provider>
  );
}

export function useOnboarding() {
  const context = useContext(OnboardingContext);
  if (context === undefined) {
    throw new Error('useOnboarding must be used within an OnboardingProvider');
  }
  return context;
}
