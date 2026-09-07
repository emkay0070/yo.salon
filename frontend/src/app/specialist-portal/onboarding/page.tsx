'use client';

import { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import { motion, AnimatePresence } from 'framer-motion';
import { Scissors, ArrowRight, ArrowLeft, Upload, CheckCircle, Sparkles, MapPin, Building, Globe } from 'lucide-react';
import { useSpecialistAuth } from '@/contexts/SpecialistAuthContext';
import { apiClient } from '@/lib/api-client';

// Animations
const fadeVariants = {
  initial: { opacity: 0, x: 20 },
  animate: { opacity: 1, x: 0 },
  exit: { opacity: 0, x: -20 },
};

export default function SpecialistOnboardingPage() {
  const router = useRouter();
  const { specialistAccount, logout } = useSpecialistAuth();
  
  const [currentStep, setCurrentStep] = useState<number>(0);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');

  // Step Data State
  const [identityData, setIdentityData] = useState({ name: '', handle: '', headline: '' });
  const [craftData, setCraftData] = useState({ bio: '', years_experience: 0, specialties: [] as string[] });
  const [prefData, setPrefData] = useState({ work_preference: 'both' });
  // Availability simple model for UI
  const [availabilityData, setAvailabilityData] = useState([
    { day: 'Monday', active: true, start: '09:00', end: '17:00' },
    { day: 'Tuesday', active: true, start: '09:00', end: '17:00' },
    { day: 'Wednesday', active: true, start: '09:00', end: '17:00' },
    { day: 'Thursday', active: true, start: '09:00', end: '17:00' },
    { day: 'Friday', active: true, start: '09:00', end: '17:00' },
    { day: 'Saturday', active: false, start: '10:00', end: '15:00' },
    { day: 'Sunday', active: false, start: '10:00', end: '15:00' },
  ]);

  useEffect(() => {
    // Fetch initial status
    apiClient.get('/v1/specialist-portal/onboarding/status').then(data => {
      if (data.completed) {
        router.push('/specialist-portal/workspace');
        return;
      }

      // Map missing fields based on completed steps
      const steps = data.steps || {};
      
      let stepIdx = 0;
      if (steps.identity) stepIdx = 1;
      if (steps.craft) stepIdx = 2;
      if (steps.verification) stepIdx = 3;
      if (steps.work_preference) stepIdx = 4;
      if (steps.availability) stepIdx = 5;
      
      setCurrentStep(stepIdx);
      setIsLoading(false);
    }).catch(err => {
      console.error(err);
      setIsLoading(false);
    });
  }, [router]);

  const nextStep = () => {
    setError('');
    setCurrentStep(prev => prev + 1);
  };
  
  const prevStep = () => {
    setError('');
    setCurrentStep(prev => Math.max(0, prev - 1));
  };

  const handleIdentitySubmit = async () => {
    if (!identityData.name || !identityData.handle) return setError('All fields are required');
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

  const handleCraftSubmit = async () => {
    if (!craftData.bio || craftData.specialties.length === 0) return setError('Please fill out bio and select at least one specialty');
    setIsSubmitting(true);
    try {
      await apiClient.put('/v1/specialist-portal/onboarding/craft', craftData);
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

  const handlePrefSubmit = async () => {
    setIsSubmitting(true);
    try {
      await apiClient.put('/v1/specialist-portal/onboarding/work-preferences', prefData);
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
      nextStep(); // Move to launch step
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

  if (isLoading) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <div className="w-10 h-10 border-4 border-gold border-t-transparent rounded-full animate-spin"></div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background text-text-primary overflow-hidden relative">
      {/* Abstract Background Elements */}
      <div className="absolute top-0 left-0 w-full h-[500px] bg-gradient-to-br from-gold/10 to-transparent blur-3xl -z-10 rounded-full mix-blend-screen transform -translate-y-1/2"></div>
      
      {/* Header */}
      <header className="absolute top-0 w-full p-6 flex justify-between items-center z-10">
        <div className="flex items-center gap-2">
          <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center">
            <Scissors className="w-4 h-4 text-black" />
          </div>
          <span className="font-bold text-lg tracking-tight">yo.salon</span>
        </div>
        <button onClick={logout} className="text-sm text-text-secondary hover:text-white transition-colors">
          Save & Exit
        </button>
      </header>

      {/* Main Content */}
      <main className="max-w-3xl mx-auto pt-32 pb-20 px-6 min-h-screen flex flex-col justify-center">
        
        {/* Progress Bar (Hide on Step 6 - Launch) */}
        {currentStep < 6 && (
          <div className="mb-12">
            <div className="flex justify-between text-xs text-text-secondary mb-2 uppercase tracking-wider font-semibold">
              <span>Step {currentStep + 1} of 6</span>
              <span>{Math.round((currentStep / 6) * 100)}% Complete</span>
            </div>
            <div className="h-1.5 w-full bg-surface rounded-full overflow-hidden">
              <motion.div 
                className="h-full bg-gradient-to-r from-gold to-amber-500"
                initial={{ width: 0 }}
                animate={{ width: `${(currentStep / 6) * 100}%` }}
                transition={{ duration: 0.5, ease: "easeInOut" }}
              />
            </div>
          </div>
        )}

        <AnimatePresence mode="wait">
          
          {/* STEP 0: WELCOME */}
          {currentStep === 0 && (
            <motion.div key="step-0" variants={fadeVariants} initial="initial" animate="animate" exit="exit" className="space-y-8">
              <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-surface border border-border-light text-xs font-medium text-gold">
                <Sparkles className="w-3.5 h-3.5" />
                Specialist Network
              </div>
              <h1 className="text-4xl md:text-5xl font-bold leading-tight">
                Welcome to the future <br className="hidden md:block"/> of your beauty business.
              </h1>
              <p className="text-lg text-text-secondary leading-relaxed max-w-2xl">
                We're building a network of elite specialists. By joining yo.salon, you agree to uphold our standards of craft, punctuality, and client respect.
              </p>
              
              <div className="pt-6">
                <button 
                  onClick={nextStep}
                  className="bg-white text-black hover:bg-gray-100 px-8 py-4 rounded-xl font-semibold flex items-center gap-3 transition-transform hover:scale-[1.02] active:scale-[0.98]"
                >
                  Accept & Begin
                  <ArrowRight className="w-5 h-5" />
                </button>
              </div>
            </motion.div>
          )}

          {/* STEP 1: IDENTITY */}
          {currentStep === 1 && (
            <motion.div key="step-1" variants={fadeVariants} initial="initial" animate="animate" exit="exit" className="space-y-8">
              <div>
                <h2 className="text-3xl font-bold mb-3">Define your identity</h2>
                <p className="text-text-secondary">How clients will know you on the platform.</p>
              </div>
              
              {error && <div className="p-4 rounded-lg bg-red-500/10 text-red-400 border border-red-500/20 text-sm">{error}</div>}

              <div className="space-y-6">
                <div className="space-y-2">
                  <label className="text-sm font-medium text-text-secondary">Display Name</label>
                  <input 
                    type="text" 
                    value={identityData.name}
                    onChange={e => setIdentityData({...identityData, name: e.target.value})}
                    placeholder="Jane Doe"
                    className="w-full bg-surface border border-border-light rounded-xl px-4 py-3.5 text-white focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-all"
                  />
                </div>
                
                <div className="space-y-2">
                  <label className="text-sm font-medium text-text-secondary">Unique Handle</label>
                  <div className="relative">
                    <span className="absolute left-4 top-3.5 text-text-secondary">@</span>
                    <input 
                      type="text" 
                      value={identityData.handle}
                      onChange={e => setIdentityData({...identityData, handle: e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '')})}
                      placeholder="janestyles"
                      className="w-full bg-surface border border-border-light rounded-xl pl-9 pr-4 py-3.5 text-white focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-all"
                    />
                  </div>
                  <p className="text-xs text-text-secondary">Used for your booking link: yo.salon/@{identityData.handle || 'janestyles'}</p>
                </div>

                <div className="space-y-2">
                  <label className="text-sm font-medium text-text-secondary">Professional Headline</label>
                  <input 
                    type="text" 
                    value={identityData.headline}
                    onChange={e => setIdentityData({...identityData, headline: e.target.value})}
                    placeholder="e.g. Master Barber · Executive Grooming Specialist"
                    className="w-full bg-surface border border-border-light rounded-xl px-4 py-3.5 text-white focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-all"
                  />
                  <p className="text-xs text-text-secondary">A short professional statement that represents your work</p>
                </div>
              </div>

              <div className="flex gap-4 pt-6">
                <button onClick={prevStep} className="px-6 py-3.5 rounded-xl border border-border-light hover:bg-surface transition-colors">
                  Back
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

          {/* STEP 2: CRAFT */}
          {currentStep === 2 && (
            <motion.div key="step-2" variants={fadeVariants} initial="initial" animate="animate" exit="exit" className="space-y-8">
              <div>
                <h2 className="text-3xl font-bold mb-3">Master of your craft</h2>
                <p className="text-text-secondary">Tell us what you do best and your background.</p>
              </div>

              {error && <div className="p-4 rounded-lg bg-red-500/10 text-red-400 border border-red-500/20 text-sm">{error}</div>}

              <div className="space-y-6">
                <div className="space-y-2">
                  <label className="text-sm font-medium text-text-secondary">Specialties (Select multiple)</label>
                  <div className="flex flex-wrap gap-2">
                    {['Haircut', 'Coloring', 'Braids', 'Makeup', 'Nails', 'Lashes', 'Brows', 'Esthetician', 'Barber'].map(spec => (
                      <button
                        key={spec}
                        onClick={() => {
                          const isSelected = craftData.specialties.includes(spec);
                          if (isSelected) {
                            setCraftData({ ...craftData, specialties: craftData.specialties.filter(s => s !== spec) });
                          } else {
                            setCraftData({ ...craftData, specialties: [...craftData.specialties, spec] });
                          }
                        }}
                        className={`px-4 py-2 rounded-full border text-sm font-medium transition-all duration-200 ${
                          craftData.specialties.includes(spec) 
                            ? 'bg-gold border-gold text-black' 
                            : 'bg-surface border-border-light text-text-secondary hover:border-gold/50 hover:text-white'
                        }`}
                      >
                        {spec}
                      </button>
                    ))}
                  </div>
                </div>

                <div className="space-y-2">
                  <label className="text-sm font-medium text-text-secondary">Years of Experience</label>
                  <input 
                    type="number" 
                    min="0"
                    value={craftData.years_experience}
                    onChange={e => setCraftData({...craftData, years_experience: parseInt(e.target.value) || 0})}
                    className="w-full bg-surface border border-border-light rounded-xl px-4 py-3.5 text-white focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-all"
                  />
                </div>

                <div className="space-y-2">
                  <label className="text-sm font-medium text-text-secondary">Bio</label>
                  <textarea 
                    value={craftData.bio}
                    onChange={e => setCraftData({...craftData, bio: e.target.value})}
                    placeholder="Tell your story. What makes your approach unique?"
                    rows={4}
                    className="w-full bg-surface border border-border-light rounded-xl px-4 py-3.5 text-white focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-all resize-none"
                  />
                </div>
              </div>

              <div className="flex gap-4 pt-6">
                <button onClick={prevStep} className="px-6 py-3.5 rounded-xl border border-border-light hover:bg-surface transition-colors">
                  Back
                </button>
                <button 
                  onClick={handleCraftSubmit}
                  disabled={isSubmitting}
                  className="flex-1 bg-gold hover:bg-amber-500 text-black px-6 py-3.5 rounded-xl font-semibold transition-all disabled:opacity-50"
                >
                  {isSubmitting ? 'Saving...' : 'Continue'}
                </button>
              </div>
            </motion.div>
          )}

          {/* STEP 3: VERIFICATION */}
          {currentStep === 3 && (
            <motion.div key="step-3" variants={fadeVariants} initial="initial" animate="animate" exit="exit" className="space-y-8">
              <div>
                <h2 className="text-3xl font-bold mb-3">Trust & Verification</h2>
                <p className="text-text-secondary">To protect our community, we verify all specialists.</p>
              </div>

              {error && <div className="p-4 rounded-lg bg-red-500/10 text-red-400 border border-red-500/20 text-sm">{error}</div>}

              <div className="p-8 border-2 border-dashed border-border-light rounded-2xl bg-surface/50 text-center hover:bg-surface hover:border-gold/50 transition-all cursor-pointer group">
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
                  You can skip this step for now, but your profile will not be public in the marketplace until verified by our moderation team.
                </p>
              </div>

              <div className="flex gap-4 pt-6">
                <button onClick={prevStep} className="px-6 py-3.5 rounded-xl border border-border-light hover:bg-surface transition-colors">
                  Back
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

          {/* STEP 4: WORK PREFERENCES */}
          {currentStep === 4 && (
            <motion.div key="step-4" variants={fadeVariants} initial="initial" animate="animate" exit="exit" className="space-y-8">
              <div>
                <h2 className="text-3xl font-bold mb-3">How do you work?</h2>
                <p className="text-text-secondary">Configure your operating mode.</p>
              </div>

              {error && <div className="p-4 rounded-lg bg-red-500/10 text-red-400 border border-red-500/20 text-sm">{error}</div>}

              <div className="grid gap-4">
                {[
                  { id: 'salon', icon: Building, title: 'Salon-based', desc: 'I rent a chair or work as an employee in a physical salon.' },
                  { id: 'independent', icon: MapPin, title: 'Independent Mobile', desc: 'I travel to clients or operate my own private suite.' },
                  { id: 'both', icon: Globe, title: 'Hybrid', desc: 'I work at a salon but also take private mobile clients.' },
                ].map(mode => {
                  const Icon = mode.icon;
                  return (
                    <div 
                      key={mode.id}
                      onClick={() => setPrefData({ work_preference: mode.id })}
                      className={`p-5 rounded-xl border-2 flex gap-4 cursor-pointer transition-all ${
                        prefData.work_preference === mode.id 
                          ? 'border-gold bg-gold/5' 
                          : 'border-border-light bg-surface hover:border-gold/30'
                      }`}
                    >
                      <div className={`w-12 h-12 rounded-full flex items-center justify-center shrink-0 ${
                        prefData.work_preference === mode.id ? 'bg-gold text-black' : 'bg-background text-text-secondary'
                      }`}>
                        <Icon className="w-5 h-5" />
                      </div>
                      <div>
                        <h3 className="font-semibold text-white mb-1">{mode.title}</h3>
                        <p className="text-sm text-text-secondary">{mode.desc}</p>
                      </div>
                    </div>
                  );
                })}
              </div>

              <div className="flex gap-4 pt-6">
                <button onClick={prevStep} className="px-6 py-3.5 rounded-xl border border-border-light hover:bg-surface transition-colors">
                  Back
                </button>
                <button 
                  onClick={handlePrefSubmit}
                  disabled={isSubmitting}
                  className="flex-1 bg-gold hover:bg-amber-500 text-black px-6 py-3.5 rounded-xl font-semibold transition-all disabled:opacity-50"
                >
                  {isSubmitting ? 'Saving...' : 'Continue'}
                </button>
              </div>
            </motion.div>
          )}

          {/* STEP 5: AVAILABILITY */}
          {currentStep === 5 && (
            <motion.div key="step-5" variants={fadeVariants} initial="initial" animate="animate" exit="exit" className="space-y-8">
              <div>
                <h2 className="text-3xl font-bold mb-3">Set your standard hours</h2>
                <p className="text-text-secondary">You can always adjust this later in your calendar settings.</p>
              </div>

              {error && <div className="p-4 rounded-lg bg-red-500/10 text-red-400 border border-red-500/20 text-sm">{error}</div>}

              <div className="bg-surface border border-border-light rounded-2xl divide-y divide-border-light overflow-hidden">
                {availabilityData.map((dayObj, i) => (
                  <div key={dayObj.day} className="flex items-center gap-4 p-4">
                    <div className="w-28 flex items-center gap-2">
                      <input 
                        type="checkbox" 
                        checked={dayObj.active}
                        onChange={(e) => {
                          const newArr = [...availabilityData];
                          newArr[i].active = e.target.checked;
                          setAvailabilityData(newArr);
                        }}
                        className="w-4 h-4 accent-gold bg-background border-border-light rounded"
                      />
                      <span className={`font-medium ${dayObj.active ? 'text-white' : 'text-text-secondary'}`}>
                        {dayObj.day}
                      </span>
                    </div>
                    
                    {dayObj.active ? (
                      <div className="flex items-center gap-3 flex-1">
                        <input 
                          type="time" 
                          value={dayObj.start}
                          onChange={(e) => {
                            const newArr = [...availabilityData];
                            newArr[i].start = e.target.value;
                            setAvailabilityData(newArr);
                          }}
                          className="bg-background border border-border-light rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-gold"
                        />
                        <span className="text-text-secondary text-sm">to</span>
                        <input 
                          type="time" 
                          value={dayObj.end}
                          onChange={(e) => {
                            const newArr = [...availabilityData];
                            newArr[i].end = e.target.value;
                            setAvailabilityData(newArr);
                          }}
                          className="bg-background border border-border-light rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-gold"
                        />
                      </div>
                    ) : (
                      <div className="flex-1 text-sm text-text-secondary">Closed</div>
                    )}
                  </div>
                ))}
              </div>

              <div className="flex gap-4 pt-6">
                <button onClick={prevStep} className="px-6 py-3.5 rounded-xl border border-border-light hover:bg-surface transition-colors">
                  Back
                </button>
                <button 
                  onClick={handleAvailabilitySubmit}
                  disabled={isSubmitting}
                  className="flex-1 bg-gold hover:bg-amber-500 text-black px-6 py-3.5 rounded-xl font-semibold transition-all disabled:opacity-50"
                >
                  {isSubmitting ? 'Saving...' : 'Complete Profile'}
                </button>
              </div>
            </motion.div>
          )}

          {/* STEP 6: LAUNCH MOMENT (SUCCESS) */}
          {currentStep === 6 && (
            <motion.div key="step-6" variants={fadeVariants} initial="initial" animate="animate" exit="exit" className="text-center space-y-8 flex flex-col items-center justify-center py-12">
              <div className="relative">
                <motion.div 
                  initial={{ scale: 0 }}
                  animate={{ scale: 1 }}
                  transition={{ type: "spring", stiffness: 200, damping: 20 }}
                  className="w-24 h-24 rounded-full bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center mb-6 z-10 relative shadow-[0_0_40px_rgba(212,175,55,0.5)]"
                >
                  <CheckCircle className="w-12 h-12 text-black" />
                </motion.div>
                {/* Ping animation behind */}
                <div className="absolute inset-0 bg-gold/40 rounded-full animate-ping z-0"></div>
              </div>
              
              <div className="space-y-4">
                <h1 className="text-5xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gold via-amber-400 to-white">
                  You're in.
                </h1>
                <p className="text-xl text-text-secondary max-w-md mx-auto">
                  Your profile is configured. It's time to build your empire, elevate your craft, and make art.
                </p>
              </div>

              <div className="pt-8 w-full max-w-sm">
                <button 
                  onClick={handleComplete}
                  disabled={isSubmitting}
                  className="w-full bg-white text-black hover:bg-gray-100 px-8 py-4 rounded-xl font-bold flex items-center justify-center gap-3 transition-transform hover:scale-[1.02] active:scale-[0.98]"
                >
                  {isSubmitting ? 'Launching...' : 'Enter Workspace'}
                  <ArrowRight className="w-5 h-5" />
                </button>
              </div>
            </motion.div>
          )}

        </AnimatePresence>
      </main>
    </div>
  );
}
