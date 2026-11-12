'use client';

import { useState, useEffect, useMemo } from 'react';
import { useRouter } from 'next/navigation';
import { motion, AnimatePresence, Variants } from 'framer-motion';
import {
  Scissors, ArrowRight, ArrowLeft, Upload, CheckCircle, Sparkles,
  MapPin, Building, Globe, Check
} from 'lucide-react';
import { useSpecialistAuth } from '@/contexts/SpecialistAuthContext';
import { apiClient } from '@/lib/api-client';

// ─── Types ────────────────────────────────────────────────────────────────────

interface CraftTaxonomy {
  id: string;
  name: string;
  slug: string;
  parent_id: string | null;
  children?: CraftTaxonomy[];
}

interface Profession {
  id: string;
  name: string;
  slug: string;
  icon: string;
  craft_taxonomy: CraftTaxonomy[];
}

// ─── Animations ───────────────────────────────────────────────────────────────

const slideVariants: Variants = {
  initial: { opacity: 0, y: 18 },
  animate: { opacity: 1, y: 0, transition: { duration: 0.4, ease: [0.22, 1, 0.36, 1] } },
  exit:    { opacity: 0, y: -18, transition: { duration: 0.28, ease: [0.22, 1, 0.36, 1] } },
};

// ─── Capsule Component ────────────────────────────────────────────────────────

function Capsule({
  label,
  selected,
  onClick,
  size = 'md',
}: {
  label: string;
  selected: boolean;
  onClick: () => void;
  size?: 'sm' | 'md' | 'lg';
}) {
  const sizeClasses = {
    sm: 'px-3.5 py-1.5 text-sm',
    md: 'px-5 py-2.5 text-sm font-medium',
    lg: 'px-6 py-3 text-base font-semibold',
  };

  return (
    <motion.button
      layout
      onClick={onClick}
      whileTap={{ scale: 0.95 }}
      whileHover={{ scale: 1.03 }}
      className={`relative rounded-full border transition-all duration-200 ${sizeClasses[size]} ${
        selected
          ? 'bg-gold border-gold text-black shadow-[0_0_16px_rgba(212,175,55,0.35)]'
          : 'bg-surface border-border-light text-text-secondary hover:border-gold/40 hover:text-white'
      }`}
    >
      {selected && (
        <motion.span
          layoutId={`check-${label}`}
          className="absolute -top-1 -right-1 w-4 h-4 bg-white rounded-full flex items-center justify-center shadow"
          initial={{ scale: 0 }}
          animate={{ scale: 1 }}
          exit={{ scale: 0 }}
        >
          <Check className="w-2.5 h-2.5 text-black" strokeWidth={3} />
        </motion.span>
      )}
      {label}
    </motion.button>
  );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function SpecialistOnboardingPage() {
  const router = useRouter();
  const { specialistAccount, logout } = useSpecialistAuth();

  const [currentStep, setCurrentStep] = useState<number>(0);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');

  // Database-driven professions
  const [professions, setProfessions] = useState<Profession[]>([]);
  const [loadingProfessions, setLoadingProfessions] = useState(false);

  // Step Data
  const [selectedProfessionIds, setSelectedProfessionIds] = useState<string[]>([]);
  const [selectedExpertiseIds, setSelectedExpertiseIds] = useState<string[]>([]);
  const [craftData, setCraftData] = useState({ bio: '', years_experience: 0 });
  const [identityData, setIdentityData] = useState({ name: '', handle: '', headline: '' });
  const [availabilityData, setAvailabilityData] = useState([
    { day: 'Monday',    active: true,  start: '09:00', end: '17:00' },
    { day: 'Tuesday',   active: true,  start: '09:00', end: '17:00' },
    { day: 'Wednesday', active: true,  start: '09:00', end: '17:00' },
    { day: 'Thursday',  active: true,  start: '09:00', end: '17:00' },
    { day: 'Friday',    active: true,  start: '09:00', end: '17:00' },
    { day: 'Saturday',  active: false, start: '10:00', end: '15:00' },
    { day: 'Sunday',    active: false, start: '10:00', end: '15:00' },
  ]);

  // ── On mount: check onboarding status + fetch professions ──
  useEffect(() => {
    Promise.all([
      apiClient.get('/v1/specialist-portal/onboarding/status'),
      apiClient.get('/v1/professions'),
    ])
      .then(([statusData, profData]) => {
        if (statusData.completed) {
          router.push('/specialist-portal/workspace');
          return;
        }
        if (Array.isArray(profData)) {
          setProfessions(profData);
        }
        const steps = statusData.steps || {};
        let stepIdx = 0;
        if (steps.identity) stepIdx = 1;
        if (steps.craft) stepIdx = 2;
        if (steps.verification) stepIdx = 3;
        if (steps.availability) stepIdx = 4;
        setCurrentStep(stepIdx);
      })
      .catch(console.error)
      .finally(() => setIsLoading(false));
  }, [router]);

  // ── Derive craft nodes available for selected professions ──
  const availableCrafts = useMemo(() => {
    if (selectedProfessionIds.length === 0) return [];
    const seen = new Set<string>();
    const crafts: CraftTaxonomy[] = [];
    professions
      .filter((p) => selectedProfessionIds.includes(p.id))
      .forEach((p) => {
        p.craft_taxonomy.forEach((craft) => {
          if (!seen.has(craft.id)) {
            seen.add(craft.id);
            crafts.push(craft);
          }
        });
      });
    console.log('[Onboarding] Selected profession IDs:', selectedProfessionIds);
    console.log('[Onboarding] Available crafts:', crafts);
    return crafts;
  }, [selectedProfessionIds, professions]);

  // ── Compute the "identity headline" from selected professions ──
  const professionLabel = useMemo(() => {
    const names = professions
      .filter((p) => selectedProfessionIds.includes(p.id))
      .map((p) => p.name);
    if (names.length === 0) return 'Professional';
    if (names.length <= 2) return names.join(' & ');
    return `${names.slice(0, 2).join(', ')} & more`;
  }, [selectedProfessionIds, professions]);

  // ── Navigation ──
  const nextStep = () => { setError(''); setCurrentStep((s) => s + 1); };
  const prevStep = () => { setError(''); setCurrentStep((s) => Math.max(0, s - 1)); };

  // ── Profession toggle ──
  const toggleProfession = (id: string) => {
    setSelectedProfessionIds((prev) =>
      prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id]
    );
    // Clear expertises that no longer belong to any selected profession
    setSelectedExpertiseIds([]);
  };

  // ── Expertise toggle ──
  const toggleExpertise = (id: string) => {
    setSelectedExpertiseIds((prev) =>
      prev.includes(id) ? prev.filter((e) => e !== id) : [...prev, id]
    );
  };

  // ── Submit Handlers ──
  const handleProfessionSubmit = () => {
    if (selectedProfessionIds.length === 0) {
      return setError('Please select at least one profession.');
    }
    nextStep();
  };

  const handleExpertiseSubmit = async () => {
    if (!craftData.bio) return setError('Please add your bio.');
    setIsSubmitting(true);
    try {
      await apiClient.put('/v1/specialist-portal/onboarding/craft', {
        bio: craftData.bio,
        years_experience: craftData.years_experience,
        profession_ids: selectedProfessionIds,
        expertise_ids: selectedExpertiseIds,
      });
      nextStep();
    } catch (e: any) {
      setError(e.message);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleIdentitySubmit = async () => {
    if (!identityData.name || !identityData.handle)
      return setError('Please fill in your display name and handle.');
    setIsSubmitting(true);
    try {
      await apiClient.put('/v1/specialist-portal/onboarding/identity', identityData);
      nextStep();
    } catch (e: any) {
      setError(e.message);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleVerificationSubmit = async () => {
    setIsSubmitting(true);
    try {
      await apiClient.post('/v1/specialist-portal/onboarding/verification', { verification_data: {} });
      nextStep();
    } catch (e: any) {
      setError(e.message);
    } finally {
      setIsSubmitting(false);
    }
  };


  const handleAvailabilitySubmit = async () => {
    setIsSubmitting(true);
    try {
      await apiClient.put('/v1/specialist-portal/onboarding/availability', { availability: availabilityData });
      nextStep();
    } catch (e: any) {
      setError(e.message);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleComplete = async () => {
    setIsSubmitting(true);
    try {
      await apiClient.post('/v1/specialist-portal/onboarding/complete');
      router.push('/specialist-portal/workspace');
    } catch (e: any) {
      setError(e.message);
      setIsSubmitting(false);
    }
  };

  const TOTAL_STEPS = 6; // 0-welcome, 1-professions, 2-expertise, 3-identity, 4-verification, 5-availability, 6-launch

  if (isLoading) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <div className="w-10 h-10 border-4 border-gold border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background text-text-primary overflow-hidden relative">
      {/* Ambient glow */}
      <div className="fixed top-0 left-1/2 -translate-x-1/2 w-[600px] h-[400px] bg-gold/8 blur-[120px] rounded-full pointer-events-none" />
      <div className="fixed bottom-0 right-0 w-[400px] h-[400px] bg-amber-900/10 blur-[100px] rounded-full pointer-events-none" />

      {/* Header */}
      <header className="fixed top-0 w-full px-6 py-5 flex justify-between items-center z-20 bg-background/60 backdrop-blur-xl border-b border-border-light/30">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center shadow-lg">
            <Scissors className="w-4 h-4 text-black" />
          </div>
          <span className="font-bold text-lg tracking-tight">yo.salon</span>
        </div>
        <button
          onClick={logout}
          className="text-sm text-text-secondary hover:text-white transition-colors"
        >
          Save &amp; Exit
        </button>
      </header>

      {/* Main */}
      <main className="max-w-2xl mx-auto pt-32 pb-24 px-6 min-h-screen flex flex-col justify-center">

        {/* Progress bar — hidden on welcome and launch */}
        {currentStep > 0 && currentStep < TOTAL_STEPS && (
          <div className="mb-10">
            <div className="flex justify-between text-xs text-text-secondary mb-2 uppercase tracking-widest font-semibold">
              <span>Step {currentStep} of {TOTAL_STEPS - 1}</span>
              <span>{Math.round((currentStep / (TOTAL_STEPS - 1)) * 100)}%</span>
            </div>
            <div className="h-1 w-full bg-surface rounded-full overflow-hidden">
              <motion.div
                className="h-full bg-gradient-to-r from-gold to-amber-400"
                initial={{ width: 0 }}
                animate={{ width: `${(currentStep / (TOTAL_STEPS - 1)) * 100}%` }}
                transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
              />
            </div>
          </div>
        )}

        <AnimatePresence mode="wait">

          {/* ───────── STEP 0: WELCOME ───────── */}
          {currentStep === 0 && (
            <motion.div key="step-0" variants={slideVariants} initial="initial" animate="animate" exit="exit" className="space-y-8">
              <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gold/10 border border-gold/25 text-xs font-semibold text-gold tracking-wide uppercase">
                <Sparkles className="w-3.5 h-3.5" />
                Specialist Network
              </div>
              <div>
                <h1 className="text-4xl md:text-5xl font-bold leading-[1.12] tracking-tight mb-5">
                  The future of your<br className="hidden md:block" /> beauty career starts here.
                </h1>
                <p className="text-lg text-text-secondary leading-relaxed max-w-xl">
                  We're building an elite network of beauty professionals. By joining, you commit to craft, punctuality, and client respect.
                </p>
              </div>
              <button
                onClick={nextStep}
                className="bg-white text-black hover:bg-gray-100 px-8 py-4 rounded-xl font-semibold flex items-center gap-3 transition-transform hover:scale-[1.02] active:scale-[0.98] shadow-lg"
              >
                Accept &amp; Begin
                <ArrowRight className="w-5 h-5" />
              </button>
            </motion.div>
          )}

          {/* ───────── STEP 1: PROFESSIONS ───────── */}
          {currentStep === 1 && (
            <motion.div key="step-1" variants={slideVariants} initial="initial" animate="animate" exit="exit" className="space-y-8">
              <div>
                <h2 className="text-3xl font-bold mb-2.5">What do you do?</h2>
                <p className="text-text-secondary text-base leading-relaxed">
                  Choose the professional roles that describe your work. Select everything that applies — you can be more than one.
                </p>
              </div>

              {error && <div className="p-4 rounded-xl bg-red-500/10 text-red-400 border border-red-500/20 text-sm">{error}</div>}

              {professions.length === 0 ? (
                <div className="flex items-center justify-center py-12">
                  <div className="w-8 h-8 border-4 border-gold border-t-transparent rounded-full animate-spin" />
                </div>
              ) : (
                <div className="flex flex-wrap gap-3 justify-center pt-2">
                  {professions.map((profession) => (
                    <Capsule
                      key={profession.id}
                      label={profession.name}
                      selected={selectedProfessionIds.includes(profession.id)}
                      onClick={() => toggleProfession(profession.id)}
                      size="lg"
                    />
                  ))}
                </div>
              )}

              {selectedProfessionIds.length > 0 && (
                <motion.p
                  initial={{ opacity: 0, y: 6 }}
                  animate={{ opacity: 1, y: 0 }}
                  className="text-center text-sm text-gold font-medium"
                >
                  {selectedProfessionIds.length} role{selectedProfessionIds.length > 1 ? 's' : ''} selected
                </motion.p>
              )}

              <div className="flex gap-4 pt-4">
                <button onClick={prevStep} className="px-6 py-3.5 rounded-xl border border-border-light hover:bg-surface transition-colors">
                  <ArrowLeft className="w-4 h-4" />
                </button>
                <button
                  onClick={handleProfessionSubmit}
                  className="flex-1 bg-gold hover:bg-amber-500 text-black px-6 py-3.5 rounded-xl font-semibold transition-all hover:scale-[1.01] active:scale-[0.99]"
                >
                  Continue
                </button>
              </div>
            </motion.div>
          )}

          {/* ───────── STEP 2: EXPERTISE + BIO ───────── */}
          {currentStep === 2 && (
            <motion.div key="step-2" variants={slideVariants} initial="initial" animate="animate" exit="exit" className="space-y-8">
              <div>
                <h2 className="text-3xl font-bold mb-2.5">What are you known for?</h2>
                <p className="text-text-secondary text-base leading-relaxed">
                  Choose the crafts that define your work as a <span className="text-white font-medium">{professionLabel}</span>.
                </p>
              </div>

              {error && <div className="p-4 rounded-xl bg-red-500/10 text-red-400 border border-red-500/20 text-sm">{error}</div>}

              {/* Expertise capsules grouped loosely */}
              {availableCrafts.length > 0 ? (
                <div className="space-y-4">
                  <div className="flex flex-wrap gap-2.5 justify-center">
                    {availableCrafts.map((craft) => (
                      <Capsule
                        key={craft.id}
                        label={craft.name}
                        selected={selectedExpertiseIds.includes(craft.id)}
                        onClick={() => toggleExpertise(craft.id)}
                        size="md"
                      />
                    ))}
                  </div>
                  {selectedExpertiseIds.length > 0 && (
                    <motion.p
                      initial={{ opacity: 0, y: 6 }}
                      animate={{ opacity: 1, y: 0 }}
                      className="text-center text-sm text-gold/80 font-medium"
                    >
                      {selectedExpertiseIds.length} craft area{selectedExpertiseIds.length > 1 ? 's' : ''} selected
                    </motion.p>
                  )}
                </div>
              ) : (
                <p className="text-center text-text-secondary text-sm py-4">
                  No expertise areas found for your selected professions. You can add them later.
                </p>
              )}

              {/* Years of experience */}
              <div className="space-y-2">
                <label className="text-sm font-medium text-text-secondary">Years of Experience</label>
                <input
                  type="number"
                  min="0"
                  max="50"
                  value={craftData.years_experience}
                  onChange={(e) => setCraftData({ ...craftData, years_experience: parseInt(e.target.value) || 0 })}
                  className="w-full bg-surface border border-border-light rounded-xl px-4 py-3.5 text-white focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-all"
                />
              </div>

              {/* Bio */}
              <div className="space-y-2">
                <label className="text-sm font-medium text-text-secondary">Your Story</label>
                <textarea
                  value={craftData.bio}
                  onChange={(e) => setCraftData({ ...craftData, bio: e.target.value })}
                  placeholder="What makes your approach unique? What drives your craft?"
                  rows={4}
                  className="w-full bg-surface border border-border-light rounded-xl px-4 py-3.5 text-white focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-all resize-none"
                />
              </div>

              <div className="flex gap-4 pt-2">
                <button onClick={prevStep} className="px-6 py-3.5 rounded-xl border border-border-light hover:bg-surface transition-colors">
                  <ArrowLeft className="w-4 h-4" />
                </button>
                <button
                  onClick={handleExpertiseSubmit}
                  disabled={isSubmitting}
                  className="flex-1 bg-gold hover:bg-amber-500 text-black px-6 py-3.5 rounded-xl font-semibold transition-all disabled:opacity-50 hover:scale-[1.01] active:scale-[0.99]"
                >
                  {isSubmitting ? 'Saving...' : 'Continue'}
                </button>
              </div>
            </motion.div>
          )}

          {/* ───────── STEP 3: IDENTITY ───────── */}
          {currentStep === 3 && (
            <motion.div key="step-3" variants={slideVariants} initial="initial" animate="animate" exit="exit" className="space-y-8">
              <div>
                <h2 className="text-3xl font-bold mb-2.5">
                  Build your {professionLabel} profile.
                </h2>
                <p className="text-text-secondary text-base">How clients will find and know you on the platform.</p>
              </div>

              {error && <div className="p-4 rounded-xl bg-red-500/10 text-red-400 border border-red-500/20 text-sm">{error}</div>}

              <div className="space-y-5">
                <div className="space-y-2">
                  <label className="text-sm font-medium text-text-secondary">Display Name</label>
                  <input
                    type="text"
                    value={identityData.name}
                    onChange={(e) => setIdentityData({ ...identityData, name: e.target.value })}
                    placeholder="Jane Doe"
                    className="w-full bg-surface border border-border-light rounded-xl px-4 py-3.5 text-white focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-all"
                  />
                </div>

                <div className="space-y-2">
                  <label className="text-sm font-medium text-text-secondary">Unique Handle</label>
                  <div className="relative">
                    <span className="absolute left-4 top-3.5 text-text-secondary select-none">@</span>
                    <input
                      type="text"
                      value={identityData.handle}
                      onChange={(e) =>
                        setIdentityData({ ...identityData, handle: e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '') })
                      }
                      placeholder="janestyles"
                      className="w-full bg-surface border border-border-light rounded-xl pl-9 pr-4 py-3.5 text-white focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-all"
                    />
                  </div>
                  <p className="text-xs text-text-secondary">
                    Your booking link: yo.salon/@{identityData.handle || 'yourhandle'}
                  </p>
                </div>

                <div className="space-y-2">
                  <label className="text-sm font-medium text-text-secondary">Professional Headline</label>
                  <input
                    type="text"
                    value={identityData.headline}
                    onChange={(e) => setIdentityData({ ...identityData, headline: e.target.value })}
                    placeholder={`e.g. ${professionLabel} · 8 Years`}
                    className="w-full bg-surface border border-border-light rounded-xl px-4 py-3.5 text-white focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-all"
                  />
                  <p className="text-xs text-text-secondary">A short professional statement clients see on your profile card.</p>
                </div>
              </div>

              <div className="flex gap-4 pt-2">
                <button onClick={prevStep} className="px-6 py-3.5 rounded-xl border border-border-light hover:bg-surface transition-colors">
                  <ArrowLeft className="w-4 h-4" />
                </button>
                <button
                  onClick={handleIdentitySubmit}
                  disabled={isSubmitting}
                  className="flex-1 bg-gold hover:bg-amber-500 text-black px-6 py-3.5 rounded-xl font-semibold transition-all disabled:opacity-50"
                >
                  {isSubmitting ? 'Saving...' : 'Continue'}
                </button>
              </div>
            </motion.div>
          )}

          {/* ───────── STEP 4: VERIFICATION ───────── */}
          {currentStep === 4 && (
            <motion.div key="step-4" variants={slideVariants} initial="initial" animate="animate" exit="exit" className="space-y-8">
              <div>
                <h2 className="text-3xl font-bold mb-2.5">Trust &amp; Verification</h2>
                <p className="text-text-secondary">To protect our community, we verify all specialists.</p>
              </div>

              {error && <div className="p-4 rounded-xl bg-red-500/10 text-red-400 border border-red-500/20 text-sm">{error}</div>}

              <div className="p-8 border-2 border-dashed border-border-light rounded-2xl bg-surface/50 text-center hover:bg-surface hover:border-gold/40 transition-all cursor-pointer group">
                <div className="w-16 h-16 bg-background rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform">
                  <Upload className="w-6 h-6 text-gold" />
                </div>
                <h3 className="font-semibold text-lg mb-1">Upload ID or License</h3>
                <p className="text-sm text-text-secondary mb-4">PNG, JPG, or PDF up to 5MB</p>
                <span className="text-sm font-medium text-gold bg-gold/10 px-4 py-2 rounded-lg">Browse Files</span>
              </div>

              <div className="bg-blue-500/10 border border-blue-500/20 rounded-xl p-4 flex gap-3">
                <CheckCircle className="w-5 h-5 text-blue-400 shrink-0 mt-0.5" />
                <p className="text-sm text-blue-100">
                  You can skip this step for now, but your profile won't be visible in the marketplace until our moderation team verifies you.
                </p>
              </div>

              <div className="flex gap-4">
                <button onClick={prevStep} className="px-6 py-3.5 rounded-xl border border-border-light hover:bg-surface transition-colors">
                  <ArrowLeft className="w-4 h-4" />
                </button>
                <button
                  onClick={handleVerificationSubmit}
                  disabled={isSubmitting}
                  className="flex-1 bg-gold hover:bg-amber-500 text-black px-6 py-3.5 rounded-xl font-semibold transition-all disabled:opacity-50"
                >
                  {isSubmitting ? 'Submitting...' : 'Submit Verification'}
                </button>
              </div>
            </motion.div>
          )}


          {/* ───────── STEP 5: AVAILABILITY ───────── */}
          {currentStep === 5 && (
            <motion.div key="step-5" variants={slideVariants} initial="initial" animate="animate" exit="exit" className="space-y-8">
              <div>
                <h2 className="text-3xl font-bold mb-2.5">Set your standard hours</h2>
                <p className="text-text-secondary">You can adjust this anytime in your calendar settings.</p>
              </div>

              {error && <div className="p-4 rounded-xl bg-red-500/10 text-red-400 border border-red-500/20 text-sm">{error}</div>}

              <div className="bg-surface border border-border-light rounded-2xl divide-y divide-border-light overflow-hidden">
                {availabilityData.map((dayObj, i) => (
                  <div key={dayObj.day} className="flex items-center gap-4 px-5 py-4">
                    <div className="w-28 flex items-center gap-3">
                      <input
                        type="checkbox"
                        checked={dayObj.active}
                        onChange={(e) => {
                          const arr = [...availabilityData];
                          arr[i] = { ...arr[i], active: e.target.checked };
                          setAvailabilityData(arr);
                        }}
                        className="w-4 h-4 accent-gold rounded"
                      />
                      <span className={`text-sm font-medium ${dayObj.active ? 'text-white' : 'text-text-secondary'}`}>
                        {dayObj.day}
                      </span>
                    </div>

                    {dayObj.active ? (
                      <div className="flex items-center gap-2 flex-1 text-sm">
                        <input
                          type="time"
                          value={dayObj.start}
                          onChange={(e) => {
                            const arr = [...availabilityData];
                            arr[i] = { ...arr[i], start: e.target.value };
                            setAvailabilityData(arr);
                          }}
                          className="bg-background border border-border-light rounded-lg px-3 py-1.5 focus:outline-none focus:border-gold text-sm"
                        />
                        <span className="text-text-secondary">–</span>
                        <input
                          type="time"
                          value={dayObj.end}
                          onChange={(e) => {
                            const arr = [...availabilityData];
                            arr[i] = { ...arr[i], end: e.target.value };
                            setAvailabilityData(arr);
                          }}
                          className="bg-background border border-border-light rounded-lg px-3 py-1.5 focus:outline-none focus:border-gold text-sm"
                        />
                      </div>
                    ) : (
                      <span className="text-sm text-text-secondary flex-1">Closed</span>
                    )}
                  </div>
                ))}
              </div>

              <div className="flex gap-4">
                <button onClick={prevStep} className="px-6 py-3.5 rounded-xl border border-border-light hover:bg-surface transition-colors">
                  <ArrowLeft className="w-4 h-4" />
                </button>
                <button
                  onClick={handleAvailabilitySubmit}
                  disabled={isSubmitting}
                  className="flex-1 bg-gold hover:bg-amber-500 text-black px-6 py-3.5 rounded-xl font-semibold transition-all disabled:opacity-50"
                >
                  {isSubmitting ? 'Saving...' : 'Complete Setup'}
                </button>
              </div>
            </motion.div>
          )}

          {/* ───────── STEP 6: LAUNCH ───────── */}
          {currentStep === 6 && (
            <motion.div
              key="step-6"
              variants={slideVariants}
              initial="initial"
              animate="animate"
              exit="exit"
              className="text-center space-y-8 flex flex-col items-center justify-center py-16"
            >
              <div className="relative">
                <motion.div
                  initial={{ scale: 0, rotate: -20 }}
                  animate={{ scale: 1, rotate: 0 }}
                  transition={{ type: 'spring', stiffness: 220, damping: 20, delay: 0.1 }}
                  className="w-24 h-24 rounded-full bg-gradient-to-br from-gold to-amber-500 flex items-center justify-center shadow-[0_0_60px_rgba(212,175,55,0.5)] z-10 relative"
                >
                  <CheckCircle className="w-11 h-11 text-black" />
                </motion.div>
                <motion.div
                  className="absolute inset-0 rounded-full bg-gold/30"
                  animate={{ scale: [1, 1.6, 1], opacity: [0.6, 0, 0.6] }}
                  transition={{ duration: 2.5, repeat: Infinity, ease: 'easeInOut' }}
                />
              </div>

              <div className="space-y-4">
                <h1 className="text-5xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gold via-amber-300 to-white">
                  You're in.
                </h1>
                <p className="text-xl text-text-secondary max-w-sm mx-auto leading-relaxed">
                  Your {professionLabel} profile is ready. It's time to make art.
                </p>
              </div>

              <button
                onClick={handleComplete}
                disabled={isSubmitting}
                className="w-full max-w-xs bg-white text-black hover:bg-gray-100 px-8 py-4 rounded-xl font-bold flex items-center justify-center gap-3 transition-transform hover:scale-[1.02] active:scale-[0.98] shadow-xl"
              >
                {isSubmitting ? 'Launching...' : 'Enter Workspace'}
                <ArrowRight className="w-5 h-5" />
              </button>
            </motion.div>
          )}

        </AnimatePresence>
      </main>
    </div>
  );
}
