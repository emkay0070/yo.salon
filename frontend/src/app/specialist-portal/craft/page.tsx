'use client';

import { useSpecialistAuth, Workplace } from '@/contexts/SpecialistAuthContext';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Scissors,
  Award,
  BookOpen,
  Package,
  TrendingUp,
  Plus,
  X,
  ChevronDown,
  Building2,
  Crown,
  Briefcase,
  Users,
  Image as ImageIcon,
  Upload,
  Lock,
  ChevronRight,
  ChevronLeft,
  Loader2,
  MapPin,
} from 'lucide-react';
import { useState } from 'react';
import { createPortal } from 'react-dom';
import { apiClient } from '@/lib/api-client';
import ServiceDrawer from '@/components/craft/ServiceDrawer';
import ExpertiseDrawer from '@/components/craft/ExpertiseDrawer';

type CraftSection = 'services' | 'expertise' | 'learning' | 'products';

interface ExpertiseItem {
  id: string;
  name: string;
  level: string;
}

// --- Types ---
interface ProviderService {
  id: string;
  name: string;
  description: string;
  price: number;
  base_price: number;
  price_override: number | null;
  duration: number;
  category: string;
  active: boolean;
  skill_level: string;
  is_primary: boolean;
  image_media_id?: string;
  image_url?: string;
  craft_taxonomy_name?: string;
  craft_taxonomy_slug?: string;
}

interface ServicesByProvider {
  provider_id: string;
  provider_name: string;
  provider_type: string;
  role: string;
  employment_type: string;
  can_create_services: boolean;
  can_manage_provider: boolean;
  services: ProviderService[];
}

// --- Role Badge helper ---
function RoleBadge({ role, type, providerType }: { role: string; type: string; providerType?: string }) {
  if (providerType === 'independent_specialist') {
    return (
      <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gold/20 text-gold">
        <Crown className="w-3 h-3" />
        Independent Practice
      </span>
    );
  }

  const map: Record<string, { label: string; icon: any; style: string }> = {
    OWNER:    { label: 'Owner',      icon: Crown,    style: 'bg-gold/20 text-gold' },
    MANAGER:  { label: 'Manager',    icon: Building2, style: 'bg-purple-400/20 text-purple-400' },
    SPECIALIST: {
      label: providerType === 'independent_specialist' ? 'Independent' : type === 'FREELANCER' ? 'Freelancer' : type === 'CONTRACTOR' ? 'Contractor' : 'Employee',
      icon: providerType === 'independent_specialist' ? MapPin : Briefcase,
      style: providerType === 'independent_specialist' ? 'bg-emerald-400/20 text-emerald-400' : 'bg-blue-400/20 text-blue-400'
    },
    TRAINEE:  { label: 'Trainee',    icon: Users,    style: 'bg-green-400/20 text-green-400' },
  };
  const config = map[role] ?? { label: role, icon: Briefcase, style: 'bg-surface text-text-secondary' };
  const Icon = config.icon;
  return (
    <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${config.style}`}>
      <Icon className="w-3 h-3" />
      {config.label}
    </span>
  );
}

// --- Add/Edit Expertise Modal ---
function ExpertiseModal({
  isOpen,
  expertise,
  onClose,
}: {
  isOpen: boolean;
  expertise: ExpertiseItem | null;
  onClose: () => void;
}) {
  const [step, setStep] = useState<0 | 1>(0);
  const [name, setName] = useState(expertise?.name ?? '');
  const [skillLevel, setSkillLevel] = useState(expertise?.level.toLowerCase() ?? 'intermediate');
  const [selectedTaxonomyId, setSelectedTaxonomyId] = useState<string | null>(null);
  const [isCustom, setIsCustom] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const queryClient = useQueryClient();

  const { data: taxonomyData } = useQuery({
    queryKey: ['craft-taxonomy'],
    queryFn: () => apiClient.get('/v1/specialist-portal/craft/taxonomy'),
  });

  const taxonomy = taxonomyData?.taxonomy ?? [];

  const filteredTaxonomy = taxonomy.flatMap((root: any) =>
    root.children.flatMap((category: any) =>
      category.children.map((item: any) => ({
        ...item,
        categoryName: category.name,
        rootName: root.name,
      }))
    )
  ).filter((item: any) =>
    item.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    item.categoryName.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const handleSubmit = async () => {
    if (!name.trim()) return;
    setIsSubmitting(true);
    try {
      const payload: any = {
        name,
        skill_level: skillLevel,
      };
      
      if (selectedTaxonomyId && !isCustom) {
        payload.craft_taxonomy_id = selectedTaxonomyId;
      }

      if (expertise) {
        await apiClient.put(`/v1/specialist-portal/craft/expertise/${expertise.id}`, payload);
      } else {
        await apiClient.post('/v1/specialist-portal/craft/expertise', payload);
      }
      queryClient.invalidateQueries({ queryKey: ['specialist-craft'] });
      onClose();
    } catch (e) {
      console.error('Failed to save expertise', e);
    } finally {
      setIsSubmitting(false);
    }
  };

  const skillLevels = [
    { value: 'beginner', label: 'Beginner' },
    { value: 'intermediate', label: 'Intermediate' },
    { value: 'advanced', label: 'Advanced' },
    { value: 'expert', label: 'Expert' },
  ];

  if (typeof window === 'undefined') return null;

  return createPortal(
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="fixed inset-0 bg-black/60 backdrop-blur-xl"
            style={{ zIndex: 99999 }}
          />
          
          {/* Main Container */}
          <div className="fixed inset-0 flex items-center justify-center p-4 pointer-events-none" style={{ zIndex: 100000 }}>
            <div className="pointer-events-auto w-full max-w-6xl">
              <div className="flex flex-col lg:flex-row gap-8 items-center justify-center w-full min-h-0 lg:items-start">
                
                {/* Expertise Selection - Two Column Layout */}
                <AnimatePresence mode="wait">
                  {!step && (
                    <motion.div
                      key="expertise-selection"
                      initial={{ opacity: 0, scale: 0.95 }}
                      animate={{ opacity: 1, scale: 1 }}
                      exit={{ opacity: 0, scale: 0.95, x: -100 }}
                      transition={{ duration: 0.3 }}
                      className="w-full flex flex-col items-center justify-center"
                    >
                      {/* Centered Header */}
                      <div className="text-center mb-6">
                        <h2 className="text-2xl font-bold text-white mb-2">Add Expertise</h2>
                        <p className="text-white/70 text-sm">Select your skills</p>
                      </div>

                      <button
                        onClick={onClose}
                        className="absolute top-4 right-4 p-2 rounded-full bg-white/10 hover:bg-white/20 transition-colors"
                      >
                        <X className="w-5 h-5 text-white" />
                      </button>

                      {!isCustom ? (
                        <>
                          {/* Two Column Layout */}
                          <div className="grid grid-cols-1 md:grid-cols-2 gap-6 w-full max-w-4xl">
                            {/* Left Column: Search + Popular */}
                            <motion.div 
                              initial={{ opacity: 0, x: -20 }}
                              animate={{ opacity: 1, x: 0 }}
                              transition={{ delay: 0.1 }}
                              className="space-y-4"
                            >
                              <motion.div 
                                initial={{ opacity: 0, y: 10 }}
                                animate={{ opacity: 1, y: 0 }}
                              >
                                <motion.input
                                  whileFocus={{ scale: 1.01 }}
                                  type="text"
                                  value={searchQuery}
                                  onChange={(e) => setSearchQuery(e.target.value)}
                                  placeholder="Search expertise..."
                                  className="w-full bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl px-4 py-3 text-sm text-white placeholder-white/30 focus:outline-none focus:border-[#FFD700]/50 transition-all"
                                />
                              </motion.div>

                              <p className="text-xs font-medium text-white/50 uppercase tracking-wider">Popular</p>
                              <div className="flex flex-wrap gap-2">
                                {taxonomy.slice(0, 2).flatMap((root: any) => 
                                  root.children.slice(0, 2).flatMap((cat: any) => 
                                    cat.children.slice(0, 3).map((item: any, index: any) => ({...item, index}))
                                  )
                                ).slice(0, 6).map((item: any) => (
                                  <motion.button
                                    key={item.id}
                                    initial={{ opacity: 0, scale: 0.9 }}
                                    animate={{ opacity: 1, scale: 1 }}
                                    transition={{ delay: 0.15 + item.index * 0.05 }}
                                    whileHover={{ scale: 1.05, y: -2 }}
                                    whileTap={{ scale: 0.95 }}
                                    onClick={() => {
                                      setName(item.name);
                                      setSelectedTaxonomyId(item.id);
                                    }}
                                    style={{ minWidth: '120px', maxWidth: '180px' }}
                                    className={`text-left px-3 py-2 rounded-lg border transition-all text-sm backdrop-blur-xl flex-grow ${
                                      selectedTaxonomyId === item.id
                                        ? 'bg-[#FFD700]/20 border border-[#FFD700]/40 text-white shadow-lg shadow-[#FFD700]/10'
                                        : 'bg-white/5 border border-white/10 hover:border-[#FFD700]/30 text-white/60'
                                    }`}
                                  >
                                    <p className="font-medium text-sm truncate">{item.name}</p>
                                    <p className="text-xs text-white/40 truncate">{item.categoryName}</p>
                                  </motion.button>
                                ))}
                              </div>
                            </motion.div>

                            {/* Right Column: All Expertise - Flex Wrap with Varying Widths */}
                            <motion.div 
                              initial={{ opacity: 0, x: 20 }}
                              animate={{ opacity: 1, x: 0 }}
                              transition={{ delay: 0.15 }}
                              className="space-y-4"
                            >
                              <p className="text-xs font-medium text-white/50 uppercase tracking-wider">All Expertise</p>
                              <div className="flex flex-wrap gap-2">
                                {filteredTaxonomy.slice(0, 12).map((item: any, index: any) => (
                                  <motion.button
                                    key={item.id}
                                    initial={{ opacity: 0, scale: 0.9 }}
                                    animate={{ opacity: 1, scale: 1 }}
                                    transition={{ delay: 0.2 + index * 0.03 }}
                                    whileHover={{ scale: 1.05, y: -2 }}
                                    whileTap={{ scale: 0.95 }}
                                    onClick={() => {
                                      setName(item.name);
                                      setSelectedTaxonomyId(item.id);
                                    }}
                                    style={{ minWidth: '120px', maxWidth: '180px' }}
                                    className={`text-left px-3 py-2 rounded-lg border transition-all text-sm backdrop-blur-xl flex-grow ${
                                      selectedTaxonomyId === item.id
                                        ? 'bg-[#FFD700]/20 border border-[#FFD700]/40 text-white shadow-lg shadow-[#FFD700]/10'
                                        : 'bg-white/5 border border-white/10 hover:border-[#FFD700]/30 text-white/60'
                                    }`}
                                  >
                                    <p className="font-medium text-sm truncate">{item.name}</p>
                                    <p className="text-xs text-white/40 truncate">{item.categoryName}</p>
                                  </motion.button>
                                ))}
                              </div>
                            </motion.div>
                          </div>

                          {/* Custom Expertise Button */}
                          <motion.div 
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ delay: 0.3 }}
                            className="mt-6"
                          >
                            <motion.button
                              whileHover={{ scale: 1.05 }}
                              onClick={() => setIsCustom(true)}
                              className="px-6 py-2 bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl text-[#FFD700] hover:bg-[#FFD700]/10 hover:border-[#FFD700]/30 font-medium transition-all"
                            >
                              + Custom expertise
                            </motion.button>
                          </motion.div>

                          {/* Next Button - Shows after selection */}
                          <AnimatePresence>
                            {name && (
                              <motion.button
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                exit={{ opacity: 0, y: -20 }}
                                transition={{ duration: 0.3 }}
                                onClick={() => setStep(1)}
                                className="mt-6 w-full py-3 px-10 bg-white/10 backdrop-blur-xl border border-white/20 rounded-2xl font-medium bg-gradient-to-r from-[#FFD700]/30 to-[#C9A227]/30 text-white hover:from-[#FFD700]/50 hover:to-[#C9A227]/50 transition-all shadow-2xl shadow-[#FFD700]/30 hover:shadow-[#FFD700]/50 flex items-center justify-center gap-3 text-base"
                              >
                                <span>Next: Set Skill Level</span>
                                <ChevronRight className="w-5 h-5" />
                              </motion.button>
                            )}
                          </AnimatePresence>

                          <motion.button
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ delay: 0.4 }}
                            onClick={onClose}
                            className="mt-4 px-8 py-3 rounded-xl font-medium bg-white/10 text-white hover:bg-white/20 transition-colors"
                          >
                            Cancel
                          </motion.button>
                        </>
                      ) : (
                        <motion.div
                          initial={{ opacity: 0, y: 10 }}
                          animate={{ opacity: 1, y: 0 }}
                          className="w-full max-w-md"
                        >
                          <label className="block text-sm font-medium text-white/50 mb-2">Custom Name</label>
                          <motion.input
                            whileFocus={{ scale: 1.01 }}
                            type="text"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="e.g. Bohemian Knotless Braiding"
                            className="w-full bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl px-4 py-3 text-sm text-white placeholder-white/30 focus:outline-none focus:border-[#FFD700]/50 transition-all"
                          />
                          <motion.button
                            whileHover={{ scale: 1.05 }}
                            onClick={() => setIsCustom(false)}
                            className="text-xs text-white/40 hover:text-white/60 mt-3 transition-colors"
                          >
                            ← Back to suggestions
                          </motion.button>
                        </motion.div>
                      )}
                    </motion.div>
                  )}
                </AnimatePresence>


                {/* Floating Skill Level Cards - Step 2 Only */}
                <AnimatePresence mode="wait">
                  {step && name && (
                    <motion.div
                      key="skill-level-grid"
                      initial={{ opacity: 0, x: 100 }}
                      animate={{ opacity: 1, x: 0 }}
                      exit={{ opacity: 0, x: 100 }}
                      transition={{ duration: 0.3 }}
                      className="w-full flex flex-col items-center justify-center"
                    >
                      <div className="flex items-center gap-3 mb-6 w-full justify-center">
                        <button
                          onClick={() => setStep(0)}
                          className="p-2 rounded-full bg-white/10 hover:bg-white/20 transition-colors"
                        >
                          <ChevronLeft className="w-5 h-5 text-white" />
                        </button>
                        <h3 className="text-white font-semibold text-lg">Set Skill Level</h3>
                      </div>
                      <div className="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-2 gap-4 md:gap-6 max-w-2xl mx-auto justify-items-center">
                        {skillLevels.map((level, index: number) => (
                          <motion.button
                            key={level.value}
                            type="button"
                            onClick={() => setSkillLevel(level.value)}
                            initial={{ opacity: 0, scale: 0.8, y: 20 }}
                            animate={{ 
                              opacity: 1, 
                              scale: 1, 
                              y: 0,
                            }}
                            whileHover={{ 
                              scale: 1.05,
                              y: -4,
                              transition: { duration: 0.2 }
                            }}
                            whileTap={{ scale: 0.95 }}
                            transition={{ 
                              duration: 0.3, 
                              delay: index * 0.1,
                              type: "spring",
                              stiffness: 200,
                              damping: 15
                            }}
                            className={`relative p-6 md:p-8 rounded-2xl md:rounded-3xl border-2 transition-all text-center ${
                              skillLevel === level.value
                                ? 'border-[#FFD700] bg-[#FFD700]/20 shadow-2xl shadow-[#FFD700]/30'
                                : 'border-white/20 bg-white/10 hover:bg-white/20 hover:border-white/30 shadow-lg'
                            }`}
                          >
                            <div className="flex flex-col items-center">
                              <motion.div 
                                className="relative mb-3 md:mb-4"
                                animate={skillLevel === level.value ? {
                                  scale: [1, 1.1, 1],
                                  rotate: [0, 5, -5, 0]
                                } : {}}
                                transition={{ 
                                  duration: 0.5,
                                  repeat: skillLevel === level.value ? Infinity : 0,
                                  repeatDelay: 2
                                }}
                              >
                                <div className="w-16 h-16 md:w-20 md:h-20 rounded-full bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center text-black font-bold text-xl md:text-2xl shadow-xl">
                                  {level.label.charAt(0)}
                                </div>
                              </motion.div>
                              <p className="font-semibold text-white text-base md:text-lg">{level.label}</p>
                            </div>
                          </motion.button>
                        ))}
                      </div>
                      
                      <motion.button
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.4 }}
                        whileHover={{ scale: 1.02, y: -2 }}
                        whileTap={{ scale: 0.98 }}
                        onClick={handleSubmit}
                        disabled={isSubmitting}
                        className="mt-8 px-12 py-3 rounded-xl bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black font-semibold text-base hover:opacity-90 transition-all disabled:opacity-40 disabled:cursor-not-allowed shadow-lg shadow-[#FFD700]/20"
                      >
                        {isSubmitting ? 'Saving...' : expertise ? 'Update' : 'Add Expertise'}
                      </motion.button>
                    </motion.div>
                  )}
                </AnimatePresence>
              </div>
            </div>
          </div>
        </>
      )}
    </AnimatePresence>,
    document.body
  );
}

// --- Add Service Modal ---
function AddServiceModal({
  workplaces,
  onClose,
}: {
  workplaces: Workplace[];
  onClose: () => void;
}) {
  const [step, setStep] = useState<'select-workplace' | 'select-service' | 'create-service'>('select-workplace');
  const [selectedWorkplace, setSelectedWorkplace] = useState<Workplace | null>(null);
  const [providerServices, setProviderServices] = useState<any[]>([]);
  const [loadingServices, setLoadingServices] = useState(false);
  const [newService, setNewService] = useState({ name: '', price: '', duration: '', category: '', description: '', craft_taxonomy_id: '' });
  const [serviceImage, setServiceImage] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const [uploadingImage, setUploadingImage] = useState(false);
  const [showTaxonomySelector, setShowTaxonomySelector] = useState(false);
  const [taxonomySearch, setTaxonomySearch] = useState('');
  const [loadingTaxonomy, setLoadingTaxonomy] = useState(false);
  const queryClient = useQueryClient();

  const { data: taxonomyData } = useQuery({
    queryKey: ['craft-taxonomy'],
    queryFn: () => apiClient.get('/v1/specialist-portal/craft/taxonomy'),
  });

  const taxonomy = taxonomyData?.taxonomy ?? [];

  const filteredTaxonomy = taxonomy.flatMap((root: any) =>
    root.children.flatMap((category: any) =>
      category.children.map((item: any) => ({
        ...item,
        categoryName: category.name,
        rootName: root.name,
      }))
    )
  ).filter((item: any) =>
    item.name.toLowerCase().includes(taxonomySearch.toLowerCase()) ||
    item.categoryName.toLowerCase().includes(taxonomySearch.toLowerCase())
  );

  const handleSelectWorkplace = async (workplace: Workplace) => {
    setSelectedWorkplace(workplace);
    if (workplace.can_create_services) {
      setStep('create-service');
    } else {
      // Fetch that Provider's services to show a checklist
      setLoadingServices(true);
      try {
        const res = await apiClient.get(`/v1/services?provider_id=${workplace.provider_id}&active=true`);
        setProviderServices(res?.data ?? res ?? []);
      } catch (e) {
        setProviderServices([]);
      } finally {
        setLoadingServices(false);
      }
      setStep('select-service');
    }
  };

  const handleAttachService = async (serviceId: string) => {
    if (!selectedWorkplace) return;
    try {
      await apiClient.post('/v1/specialist-portal/craft/services', {
        service_id: serviceId,
        provider_id: selectedWorkplace.provider_id,
      });
      queryClient.invalidateQueries({ queryKey: ['specialist-craft'] });
      onClose();
    } catch (e) {
      console.error('Failed to attach service', e);
    }
  };

  const handleImageSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      setServiceImage(file);
      const preview = URL.createObjectURL(file);
      setImagePreview(preview);
    }
  };

  const handleCreateService = async () => {
    if (!selectedWorkplace) return;
    try {
      let imageMediaId = null;
      
      // Upload image if selected
      if (serviceImage) {
        setUploadingImage(true);
        try {
          const uploadRes = await apiClient.uploadMedia(serviceImage, { directory: 'services' });
          imageMediaId = uploadRes.id;
        } catch (uploadError) {
          console.error('Failed to upload image', uploadError);
          throw uploadError;
        } finally {
          setUploadingImage(false);
        }
      }

      await apiClient.post('/v1/specialist-portal/craft/services/create', {
        ...newService,
        provider_id: selectedWorkplace.provider_id,
        price: parseFloat(newService.price),
        duration: parseInt(newService.duration),
        craft_taxonomy_id: newService.craft_taxonomy_id || null,
        image_media_id: imageMediaId,
      });
      queryClient.invalidateQueries({ queryKey: ['specialist-craft'] });
      onClose();
    } catch (e) {
      console.error('Failed to create service', e);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
      <motion.div
        initial={{ opacity: 0, scale: 0.95, y: 10 }}
        animate={{ opacity: 1, scale: 1, y: 0 }}
        exit={{ opacity: 0, scale: 0.95, y: 10 }}
        className="bg-card border border-border-light rounded-2xl w-full max-w-md shadow-2xl flex flex-col max-h-[90vh]"
      >
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-border-light shrink-0">
          <div>
            <h2 className="text-lg font-bold text-text-primary">
              {step === 'select-workplace' && 'Add a Service'}
              {step === 'select-service' && `Services at ${selectedWorkplace?.provider_name}`}
              {step === 'create-service' && `New Service for ${selectedWorkplace?.provider_name}`}
            </h2>
            {step !== 'select-workplace' && (
              <button onClick={() => setStep('select-workplace')} className="text-xs text-text-muted hover:text-gold mt-0.5 transition-colors">
                ← Change workplace
              </button>
            )}
          </div>
          <button onClick={onClose} className="text-text-secondary hover:text-text-primary transition-colors">
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Scrollable content */}
        <div className="flex-1 overflow-y-auto p-6">
          {/* Step 1: Choose workplace */}
          {step === 'select-workplace' && (
            <div className="space-y-3">
              <p className="text-sm text-text-secondary mb-4">Where would you like to offer this service?</p>
              {workplaces.map((wp) => (
                <button
                  key={wp.assignment_id}
                  onClick={() => handleSelectWorkplace(wp)}
                  className="w-full flex items-center justify-between p-4 rounded-xl bg-surface border border-border-light hover:border-gold/50 hover:bg-gold/5 transition-all text-left group"
                >
                  <div>
                    <p className="font-medium text-text-primary group-hover:text-gold transition-colors">{wp.provider_name}</p>
                    <div className="mt-1">
                      <RoleBadge role={wp.role} type={wp.employment_type} providerType={wp.provider_type} />
                    </div>
                  </div>
                  <ChevronDown className="w-4 h-4 text-text-secondary rotate-[-90deg] group-hover:text-gold transition-colors" />
                </button>
              ))}
            </div>
          )}

          {/* Step 2a: Select from Provider's services (Employee/Contractor) */}
          {step === 'select-service' && (
            <div className="space-y-3">
              {loadingServices ? (
                <div className="text-center text-text-secondary py-8">Loading services…</div>
              ) : providerServices.length === 0 ? (
                <div className="text-center text-text-secondary py-8">No services found for this Provider.</div>
              ) : (
                providerServices.map((svc: any) => (
                  <button
                    key={svc.id}
                    onClick={() => handleAttachService(svc.id)}
                    className="w-full flex items-center justify-between p-4 rounded-xl bg-surface border border-border-light hover:border-gold/50 hover:bg-gold/5 transition-all text-left group"
                  >
                    <div>
                      <p className="font-medium text-text-primary group-hover:text-gold transition-colors">{svc.name}</p>
                      <p className="text-sm text-text-secondary">{svc.duration} min · UGX {svc.price?.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}</p>
                    </div>
                    <Plus className="w-4 h-4 text-text-secondary group-hover:text-gold transition-colors" />
                  </button>
                ))
              )}
            </div>
          )}

          {/* Step 2b: Create new service (Owner/Manager) */}
          {step === 'create-service' && (
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-text-secondary mb-1">Service Name</label>
                <input
                  type="text"
                  value={newService.name}
                  onChange={(e) => setNewService((p) => ({ ...p, name: e.target.value }))}
                  placeholder="e.g. Signature Balayage"
                  className="w-full bg-surface border border-border-light rounded-xl px-4 py-2.5 text-text-primary placeholder-text-muted focus:outline-none focus:border-gold/60 transition-colors"
                />
                <p className="text-xs text-text-muted mt-1">The name customers will see (your branded offering)</p>
              </div>

              <div>
                <label className="block text-sm font-medium text-text-secondary mb-1">What kind of service is this?</label>
                {!showTaxonomySelector ? (
                  <button
                    onClick={() => setShowTaxonomySelector(true)}
                    className="w-full px-4 py-2.5 bg-surface border border-border-light rounded-xl text-text-primary placeholder-text-muted focus:outline-none focus:border-gold/60 transition-colors text-left hover:border-gold/50"
                  >
                    {newService.craft_taxonomy_id ? 'Category selected' : 'Select category...'}
                  </button>
                ) : (
                  <div className="bg-surface border border-border-light rounded-xl p-3">
                    <input
                      type="text"
                      placeholder="Search categories..."
                      value={taxonomySearch}
                      onChange={(e) => setTaxonomySearch(e.target.value)}
                      className="w-full px-3 py-2 bg-card border border-border-light rounded-lg text-text-primary placeholder-text-secondary focus:outline-none focus:border-gold mb-3 text-sm"
                    />
                    {loadingTaxonomy ? (
                      <div className="flex items-center justify-center py-4">
                        <Loader2 className="w-5 h-5 text-gold animate-spin" />
                      </div>
                    ) : filteredTaxonomy.length === 0 ? (
                      <div className="text-center text-text-secondary py-4 text-sm">No categories found</div>
                    ) : (
                      <div className="space-y-1 max-h-40 overflow-y-auto">
                        {filteredTaxonomy.map((item: any) => (
                          <button
                            key={item.id}
                            onClick={() => {
                              setNewService({ ...newService, craft_taxonomy_id: item.id, category: item.categoryName });
                              setShowTaxonomySelector(false);
                            }}
                            className="w-full text-left px-3 py-2 bg-card rounded-lg hover:bg-gold/10 transition-colors text-text-primary text-sm"
                          >
                            <div className="font-medium">{item.name}</div>
                            <div className="text-xs text-text-secondary">{item.categoryName}</div>
                          </button>
                        ))}
                      </div>
                    )}
                    <button
                      onClick={() => {
                        setShowTaxonomySelector(false);
                        setNewService((p) => ({ ...p, craft_taxonomy_id: '' }));
                      }}
                      className="text-xs text-text-muted hover:text-text-secondary mt-2"
                    >
                      Clear selection
                    </button>
                  </div>
                )}
                <p className="text-xs text-text-muted mt-1">Helps customers find your service (platform classification)</p>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-text-secondary mb-1">Price (UGX)</label>
                  <input
                    type="number"
                    value={newService.price}
                    onChange={(e) => setNewService((p) => ({ ...p, price: e.target.value }))}
                    placeholder="150000"
                    className="w-full bg-surface border border-border-light rounded-xl px-4 py-2.5 text-text-primary placeholder-text-muted focus:outline-none focus:border-gold/60 transition-colors"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-text-secondary mb-1">Duration (mins)</label>
                  <input
                    type="number"
                    value={newService.duration}
                    onChange={(e) => setNewService((p) => ({ ...p, duration: e.target.value }))}
                    placeholder="120"
                    className="w-full bg-surface border border-border-light rounded-xl px-4 py-2.5 text-text-primary placeholder-text-muted focus:outline-none focus:border-gold/60 transition-colors"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-text-secondary mb-1">Description</label>
                <textarea
                  value={newService.description}
                  onChange={(e) => setNewService((p) => ({ ...p, description: e.target.value }))}
                  placeholder="Describe this service..."
                  rows={2}
                  className="w-full bg-surface border border-border-light rounded-xl px-4 py-2.5 text-text-primary placeholder-text-muted focus:outline-none focus:border-gold/60 transition-colors resize-none"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-text-secondary mb-1">Menu Category (Optional)</label>
                <input
                  type="text"
                  value={newService.category}
                  onChange={(e) => setNewService((p) => ({ ...p, category: e.target.value }))}
                  placeholder="e.g. Premium Hair, Signature Services"
                  className="w-full bg-surface border border-border-light rounded-xl px-4 py-2.5 text-text-primary placeholder-text-muted focus:outline-none focus:border-gold/60 transition-colors"
                />
                <p className="text-xs text-text-muted mt-1">For organizing your service menu</p>
              </div>

              <div>
                <label className="block text-sm font-medium text-text-secondary mb-1">Service Image</label>
                <div className="space-y-2">
                  {imagePreview ? (
                    <div className="relative w-full h-40 rounded-xl overflow-hidden border border-border-light">
                      <img src={imagePreview} alt="Preview" className="w-full h-full object-cover" />
                      <button
                        onClick={() => {
                          setServiceImage(null);
                          setImagePreview(null);
                        }}
                        className="absolute top-2 right-2 bg-black/60 text-white p-1.5 rounded-lg hover:bg-black/80 transition-colors"
                      >
                        <X className="w-4 h-4" />
                      </button>
                    </div>
                  ) : (
                    <label className="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed border-border-light rounded-xl cursor-pointer hover:border-gold/50 hover:bg-gold/5 transition-all">
                      <div className="flex flex-col items-center justify-center pt-5 pb-6">
                        <Upload className="w-8 h-8 text-text-secondary mb-2" />
                        <p className="text-sm text-text-secondary">Click to upload image</p>
                        <p className="text-xs text-text-muted mt-1">PNG, JPG, WEBP up to 10MB</p>
                      </div>
                      <input
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        onChange={handleImageSelect}
                        className="hidden"
                      />
                    </label>
                  )}
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Footer button */}
        {step === 'create-service' && (
          <div className="p-6 border-t border-border-light shrink-0">
            <button
              onClick={handleCreateService}
              disabled={!newService.name || !newService.price || !newService.duration || uploadingImage}
              className="w-full py-3 rounded-xl bg-gradient-to-r from-gold to-amber-600 text-black font-semibold text-sm hover:opacity-90 transition-opacity disabled:opacity-40 disabled:cursor-not-allowed"
            >
              {uploadingImage ? 'Uploading Image...' : 'Create Service'}
            </button>
          </div>
        )}
      </motion.div>
    </div>
  );
}

// --- Main Craft Page ---
export default function CraftPage() {
  const { specialist, workplaces } = useSpecialistAuth();
  const [activeSection, setActiveSection] = useState<CraftSection>('services');
  const [isAddModalOpen, setIsAddModalOpen] = useState(false);
  const [expertiseModalOpen, setExpertiseModalOpen] = useState(false);
  const [editingExpertise, setEditingExpertise] = useState<ExpertiseItem | null>(null);
  const [expertiseFilter, setExpertiseFilter] = useState<string>('all');
  const queryClient = useQueryClient();

  const deleteExpertiseMutation = useMutation({
    mutationFn: (expertiseId: string) =>
      apiClient.delete(`/v1/specialist-portal/craft/expertise/${expertiseId}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-craft'] });
    },
  });

  const sections: { id: CraftSection; label: string; icon: any; disabled?: boolean }[] = [
    { id: 'services',  label: 'Services',  icon: Scissors },
    { id: 'expertise', label: 'Expertise', icon: TrendingUp },
    { id: 'learning',  label: 'Learning',  icon: BookOpen, disabled: true },
    { id: 'products',  label: 'Products',  icon: Package, disabled: true },
  ];

  const { data: craftData, isLoading } = useQuery({
    queryKey: ['specialist-craft'],
    queryFn: () => apiClient.get('/v1/specialist-portal/craft'),
    enabled: !!specialist,
  });

  const servicesByProvider: ServicesByProvider[] = craftData?.services_by_provider ?? [];
  const expertise = craftData?.expertise ?? [];
  const serviceReputation = craftData?.service_reputation ?? [];
  const rawSkills = craftData?.raw_skills ?? [];
  const learning = craftData?.learning ?? [];
  const products = craftData?.products ?? [];

  const filteredExpertise = expertise.filter((item: ExpertiseItem) => 
    expertiseFilter === 'all' || item.level.toLowerCase() === expertiseFilter
  );

  const [selectedService, setSelectedService] = useState<any>(null);
  const [isServiceDrawerOpen, setIsServiceDrawerOpen] = useState(false);
  const [selectedExpertise, setSelectedExpertise] = useState<any>(null);
  const [isExpertiseDrawerOpen, setIsExpertiseDrawerOpen] = useState(false);

  if (isLoading) {
    return (
      <div className="p-6 flex items-center justify-center min-h-[50vh]">
        <div className="w-8 h-8 border-2 border-gold/40 border-t-gold rounded-full animate-spin" />
      </div>
    );
  }

  return (
    <>
      <div className="p-4 sm:p-6 lg:p-8 space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-2xl font-bold text-text-primary">Craft</h1>
          <p className="text-text-secondary">Your professional expertise and commercial services</p>
        </div>

        {/* Section Tabs */}
        <div className="flex gap-2 overflow-x-auto pb-2">
          {sections.map((section) => {
            const Icon = section.icon;
            return (
              <button
                key={section.id}
                onClick={() => !section.disabled && setActiveSection(section.id)}
                disabled={section.disabled}
                className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap ${
                  section.disabled
                    ? 'bg-surface/30 text-text-muted cursor-not-allowed opacity-50'
                    : activeSection === section.id
                    ? 'bg-gold text-black'
                    : 'bg-surface text-text-secondary hover:text-text-primary'
                }`}
              >
                <Icon className="w-4 h-4" />
                {section.label}
                {section.disabled && <span className="text-xs opacity-60">· Coming Soon</span>}
              </button>
            );
          })}
        </div>

        {/* === SERVICES SECTION (Multi-Provider) === */}
        {activeSection === 'services' && (
          <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} className="space-y-6">
            <div className="flex items-center justify-between">
              <h3 className="font-semibold text-text-primary">Your Services</h3>
              {workplaces.length > 0 && (
                <button
                  onClick={() => setIsAddModalOpen(true)}
                  className="flex items-center gap-2 px-4 py-2 rounded-lg bg-gradient-to-r from-gold to-amber-600 text-black text-sm font-medium hover:opacity-90 transition-opacity"
                >
                  <Plus className="w-4 h-4" />
                  Add Service
                </button>
              )}
            </div>

            {servicesByProvider.length === 0 ? (
              <div className="bg-card border border-border-light rounded-2xl p-12 text-center">
                <Scissors className="w-12 h-12 text-text-muted mx-auto mb-4" />
                <p className="text-text-secondary font-medium">No services linked yet</p>
                <p className="text-sm text-text-muted mt-1">Click "Add Service" to link yourself to a service you offer.</p>
              </div>
            ) : (
              servicesByProvider.map((group, gi) => (
                <motion.div
                  key={group.provider_id}
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: gi * 0.08 }}
                  className="space-y-4"
                >
                  {/* Provider group header */}
                  <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded-full bg-gold/10 flex items-center justify-center">
                      <Building2 className="w-4 h-4 text-gold" />
                    </div>
                    <div>
                      <p className="font-semibold text-text-primary">{group.provider_name}</p>
                    </div>
                    <RoleBadge role={group.role} type={group.employment_type} providerType={group.provider_type} />
                  </div>

                  {/* Service cards for this Provider */}
                  <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 ml-11">
                    {group.services.map((svc, si) => (
                      <motion.div
                        key={svc.id}
                        initial={{ opacity: 0, y: 6 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: gi * 0.08 + si * 0.05 }}
                        className="bg-card border border-border-light rounded-xl overflow-hidden cursor-pointer hover:border-gold/50 transition-colors"
                        onClick={() => {
                          setSelectedService({
                            ...svc,
                            provider_name: group.provider_name,
                            role: group.role,
                            employment_type: group.employment_type,
                            provider_type: group.provider_type,
                            can_manage_provider: group.can_manage_provider,
                            can_create_services: group.can_create_services,
                            reputation: serviceReputation.find((r: any) => r.service_id === svc.id),
                          });
                          setIsServiceDrawerOpen(true);
                        }}
                      >
                        <div className="flex h-32">
                          {svc.image_url ? (
                            <div className="w-32 h-full bg-surface shrink-0">
                              <img 
                                src={svc.image_url} 
                                alt={svc.name}
                                className="w-full h-full object-cover"
                                onError={(e) => {
                                  (e.target as HTMLImageElement).style.display = 'none';
                                }}
                              />
                            </div>
                          ) : (
                            <div className="w-32 h-full bg-surface shrink-0 flex items-center justify-center">
                              <ImageIcon className="w-8 h-8 text-text-muted" />
                            </div>
                          )}
                          <div className="flex-1 p-4 flex flex-col justify-between">
                            <div>
                              <div className="flex items-start justify-between gap-2 mb-2">
                                <p className="font-medium text-text-primary">{svc.name}</p>
                                <span className={`px-2 py-0.5 rounded-full text-xs font-medium shrink-0 ${
                                  svc.skill_level === 'expert'       ? 'bg-gold/20 text-gold' :
                                  svc.skill_level === 'intermediate' ? 'bg-blue-400/20 text-blue-400' :
                                                                        'bg-green-400/20 text-green-400'
                                }`}>
                                  {svc.skill_level}
                                </span>
                              </div>
                              {svc.craft_taxonomy_name && (
                                <p className="text-xs text-gold mb-1">{svc.craft_taxonomy_name}</p>
                              )}
                              {svc.description && (
                                <p className="text-xs text-text-secondary line-clamp-2 mb-2">{svc.description}</p>
                              )}
                            </div>
                            <div className="flex items-center justify-between text-sm">
                              <span className="text-text-secondary">{svc.duration} min</span>
                              <span className="font-semibold text-text-primary">
                                UGX {svc.price?.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                                {svc.price_override && <span className="text-text-muted line-through ml-1 text-xs">UGX {svc.base_price?.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}</span>}
                              </span>
                            </div>
                          </div>
                        </div>
                      </motion.div>
                    ))}
                  </div>
                </motion.div>
              ))
            )}
          </motion.div>
        )}

        {/* === EXPERTISE SECTION === */}
        {activeSection === 'expertise' && (
          <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} className="space-y-6">
            <div className="flex items-center justify-between">
              <h3 className="font-semibold text-text-primary">Your Expertise</h3>
              <div className="flex items-center gap-3">
                <select
                  value={expertiseFilter}
                  onChange={(e) => setExpertiseFilter(e.target.value)}
                  className="bg-surface border border-border-light rounded-lg px-3 py-2 text-sm text-text-secondary focus:outline-none focus:border-gold/60"
                >
                  <option value="all">All Levels</option>
                  <option value="Expert">Expert</option>
                  <option value="Advanced">Advanced</option>
                  <option value="Intermediate">Intermediate</option>
                  <option value="Beginner">Beginner</option>
                </select>
                <button
                  onClick={() => {
                    setEditingExpertise(null);
                    setExpertiseModalOpen(true);
                  }}
                  className="flex items-center gap-2 px-4 py-2 rounded-lg bg-gradient-to-r from-gold to-amber-600 text-black text-sm font-medium hover:opacity-90 transition-opacity"
                >
                  <Plus className="w-4 h-4" />
                  Add Expertise
                </button>
              </div>
            </div>
            {expertise.length === 0 ? (
              <div className="bg-card border border-border-light rounded-2xl p-12 text-center">
                <TrendingUp className="w-12 h-12 text-text-muted mx-auto mb-4" />
                <p className="text-text-secondary">No expertise added yet</p>
                <p className="text-sm text-text-muted mt-1">Click "Add Expertise" to showcase your skills.</p>
              </div>
            ) : (
              <div className="flex flex-col lg:flex-row gap-6">
                {/* Masonry-style expertise grid */}
                <div className="flex-1">
                  <div className="flex flex-wrap gap-3">
                    {filteredExpertise.map((item: ExpertiseItem, index: number) => (
                      <motion.div
                        key={item.id}
                        initial={{ opacity: 0, scale: 0.95 }}
                        animate={{ opacity: 1, scale: 1 }}
                        transition={{ delay: index * 0.05 }}
                        className={`bg-card border border-border-light rounded-xl px-4 py-3 cursor-pointer hover:border-gold/50 hover:bg-gold/5 transition-all ${
                          item.level === 'Expert' ? 'min-w-[160px] sm:min-w-[200px]' : 'min-w-[140px] sm:min-w-[160px]'
                        }`}
                        onClick={() => {
                          setSelectedExpertise({
                            ...item,
                            reputation: serviceReputation.find((r: any) => r.service_id === item.id),
                          });
                          setIsExpertiseDrawerOpen(true);
                        }}
                      >
                        <div className="flex items-center justify-between gap-3">
                          <span className="font-medium text-text-primary truncate">{item.name}</span>
                          <span className={`px-2 py-0.5 rounded-full text-xs font-medium shrink-0 ${
                            item.level === 'Expert'       ? 'bg-gold/20 text-gold' :
                            item.level === 'Advanced'     ? 'bg-blue-400/20 text-blue-400' :
                            item.level === 'Intermediate' ? 'bg-purple-400/20 text-purple-400' :
                                                             'bg-green-400/20 text-green-400'
                          }`}>
                            {item.level}
                          </span>
                        </div>
                      </motion.div>
                    ))}
                  </div>
                </div>

                {/* Customer Evidence / Reputation - Side panel */}
                <div className="w-full lg:w-80 shrink-0">
                  <h4 className="font-semibold text-text-primary mb-3 text-sm">Customer Evidence</h4>
                  {serviceReputation.length === 0 ? (
                    <div className="bg-card border border-border-light rounded-xl p-6 text-center">
                      <Award className="w-8 h-8 text-text-muted mx-auto mb-2" />
                      <p className="text-xs text-text-secondary">No reviews yet</p>
                    </div>
                  ) : (
                    <div className="space-y-2">
                      {serviceReputation.map((rep: any, index: number) => (
                        <motion.div
                          key={rep.service_id}
                          initial={{ opacity: 0, x: 10 }}
                          animate={{ opacity: 1, x: 0 }}
                          transition={{ delay: index * 0.05 }}
                          className="bg-card border border-border-light rounded-lg p-3"
                        >
                          <div className="flex items-center justify-between">
                            <p className="text-sm font-medium text-text-primary truncate">{rep.service_name}</p>
                            <div className="flex items-center gap-1 shrink-0 ml-2">
                              <span className="text-sm font-bold text-gold">{rep.average_rating}</span>
                              <span className="text-gold text-xs">★</span>
                            </div>
                          </div>
                          <p className="text-xs text-text-muted mt-1">{rep.review_count} review{rep.review_count !== 1 ? 's' : ''}</p>
                        </motion.div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            )}
          </motion.div>
        )}

        {/* === LEARNING SECTION === */}
        {activeSection === 'learning' && (
          <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} className="space-y-6">
            <div className="bg-card/30 border border-border-light/30 rounded-2xl p-12 text-center opacity-50">
              <div className="w-16 h-16 rounded-full bg-surface/30 flex items-center justify-center mx-auto mb-4">
                <Lock className="w-8 h-8 text-text-muted" />
              </div>
              <h3 className="text-xl font-bold text-text-muted mb-2">Learning</h3>
              <p className="text-text-secondary mb-4">This part of your professional Craft profile is coming soon.</p>
              <p className="text-sm text-text-muted">Track courses, certifications, and skill development here.</p>
            </div>
          </motion.div>
        )}

        {/* === PRODUCTS SECTION === */}
        {activeSection === 'products' && (
          <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} className="space-y-6">
            <div className="bg-card/30 border border-border-light/30 rounded-2xl p-12 text-center opacity-50">
              <div className="w-16 h-16 rounded-full bg-surface/30 flex items-center justify-center mx-auto mb-4">
                <Lock className="w-8 h-8 text-text-muted" />
              </div>
              <h3 className="text-xl font-bold text-text-muted mb-2">Products & Tools</h3>
              <p className="text-text-secondary mb-4">This part of your professional Craft profile is coming soon.</p>
              <p className="text-sm text-text-muted">Manage products, tools, and materials you use in your work.</p>
            </div>
          </motion.div>
        )}
      </div>

      {/* Add Service Modal */}
      <AnimatePresence>
        {isAddModalOpen && (
          <AddServiceModal
            workplaces={workplaces}
            onClose={() => setIsAddModalOpen(false)}
          />
        )}
      </AnimatePresence>

      {/* Expertise Modal */}
      <AnimatePresence>
        {expertiseModalOpen && (
          <ExpertiseModal
            isOpen={expertiseModalOpen}
            expertise={editingExpertise}
            onClose={() => {
              setExpertiseModalOpen(false);
              setEditingExpertise(null);
            }}
          />
        )}
      </AnimatePresence>

      {/* Service Drawer */}
      <ServiceDrawer
        isOpen={isServiceDrawerOpen}
        onClose={() => setIsServiceDrawerOpen(false)}
        service={selectedService}
      />

      {/* Expertise Drawer */}
      <ExpertiseDrawer
        isOpen={isExpertiseDrawerOpen}
        onClose={() => setIsExpertiseDrawerOpen(false)}
        expertise={selectedExpertise}
      />
    </>
  );
}
