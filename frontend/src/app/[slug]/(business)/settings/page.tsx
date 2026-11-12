'use client';

import { useState, useEffect, use } from 'react';
import { useRouter } from 'next/navigation';
import { useQuery, useMutation } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { Settings, User, Bell, Lock, Globe, Palette, Save, CheckCircle, Crown, ChevronRight, Sparkles, Clock, ShieldAlert, CreditCard, Users, CalendarDays, Building2, MapPin, Phone as PhoneIcon, Mail, Link as LinkIcon, Image } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';
import DashboardLayout from '@/components/DashboardLayout';
import AddOnsTab from '@/components/settings/AddOnsTab';
import OperatingHoursTab from '@/components/settings/OperatingHoursTab';
import BookingRulesTab from '@/components/settings/BookingRulesTab';
import PaymentMethodsTab from '@/components/settings/PaymentMethodsTab';
import StaffRosterTab from '@/components/settings/StaffRosterTab';
import ScheduleExceptionsTab from '@/components/settings/ScheduleExceptionsTab';
import { useTheme as useNextTheme } from 'next-themes';
import Link from 'next/link';
import { useRole } from '@/contexts/RoleContext';
import { useCategory } from '@/contexts/CategoryContext';
import { Avatar } from '@/components/ui/Avatar';
import ThemeSwitcher from '@/components/ThemeSwitcher';
import { providerRoutes } from '@/lib/routes';
import { LocationPicker } from '@/components/settings/LocationPicker';
import OnboardingChecklist from '@/components/dashboard/OnboardingChecklist';
import ScheduleMigrationBanner from '@/components/settings/ScheduleMigrationBanner';

export default function ProviderSettingsPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = use(params);
  const router = useRouter();
  const { user, providerId, activeProvider, providerSlug, setActiveProvider } = useRole();
  const { terminology } = useCategory();
  const routes = providerRoutes(providerSlug);
  const [activeTab, setActiveTab] = useState('my-profile');
  const [saveSuccess, setSaveSuccess] = useState(false);
  const [accentColor, setAccentColor] = useState('#FFD700');
  const [notificationSettings, setNotificationSettings] = useState({
    emailBookings: true,
    smsReminders: true,
    pushUpdates: false,
    weeklyReports: true,
  });

  // My Profile editing state
  const [isEditingProfile, setIsEditingProfile] = useState(false);
  const [profileForm, setProfileForm] = useState({
    name: '',
    email: '',
  });

  // Salon Profile editing state
  const [isEditingSalon, setIsEditingSalon] = useState(false);
  const [salonForm, setSalonForm] = useState({
    name: '',
    description: '',
    phone: '',
    email: '',
    website: '',
    address: '',
    city: '',
    country: '',
    lat: '',
    lng: '',
  });

  // File upload states
  const [profilePhotoFile, setProfilePhotoFile] = useState<File | null>(null);
  const [profilePhotoPreview, setProfilePhotoPreview] = useState<string | null>(null);
  const [salonLogoFile, setSalonLogoFile] = useState<File | null>(null);
  const [salonLogoPreview, setSalonLogoPreview] = useState<string | null>(null);
  const [coverImageFile, setCoverImageFile] = useState<File | null>(null);
  const [coverImagePreview, setCoverImagePreview] = useState<string | null>(null);
  const [backgroundImageFile, setBackgroundImageFile] = useState<File | null>(null);
  const [backgroundImagePreview, setBackgroundImagePreview] = useState<string | null>(null);
  const [brandExperience, setBrandExperience] = useState<any>(null);
  const [gallery, setGallery] = useState<any[]>([]);
  const [galleryFile, setGalleryFile] = useState<File | null>(null);
  const [showLocationPicker, setShowLocationPicker] = useState(false);

  // Verify user has access to this salon
  useEffect(() => {
    if (!user) {
      router.push(`/login?returnTo=/${slug}/settings`);
      return;
    }

    if (!providerId) {
      console.warn('[SalonSettingsPage] User has no provider assignment');
      return;
    }

    // Verify the provider slug matches the user's active provider
    // Use activeProvider from RoleContext instead of API call to avoid network errors
    if (activeProvider && activeProvider.slug !== slug) {
      console.error('[SalonSettingsPage] Access denied: user provider does not match URL provider');
      router.push(routes.dashboard);
    }
  }, [providerId, user, router, slug, activeProvider]);

  const { data: prefData } = useQuery({
    queryKey: ['notification-preferences'],
    queryFn: () => apiClient.getNotificationPreferences(),
  });

  useEffect(() => {
    if (prefData?.preferences) {
      setNotificationSettings(prefData.preferences);
    }
  }, [prefData]);

  // Populate profile form with user data
  useEffect(() => {
    if (user) {
      setProfileForm({
        name: user.name || '',
        email: user.email || '',
      });
    }
  }, [user]);

  // Populate salon form with salon data
  useEffect(() => {
    if (activeProvider) {
      console.log('[Settings] activeProvider:', activeProvider);
      console.log('[Settings] activeProvider.provider:', activeProvider.provider);
      console.log('[Settings] activeProvider.provider.cover_image_url:', activeProvider.provider?.cover_image_url);
      console.log('[Settings] activeProvider.logo_url:', activeProvider.logo_url);
      setSalonForm({
        name: activeProvider.name || '',
        description: activeProvider.description || '',
        phone: activeProvider.phone || '',
        email: activeProvider.email || '',
        website: activeProvider.website || '',
        address: activeProvider.address || '',
        city: activeProvider.city || '',
        country: activeProvider.country || '',
        lat: activeProvider.lat?.toString() || '',
        lng: activeProvider.lng?.toString() || '',
      });
    }
  }, [activeProvider]);

  const updatePrefsMutation = useMutation({
    mutationFn: (newPrefs: any) => apiClient.updateNotificationPreferences(newPrefs),
  });

  const handlePrefChange = (key: string, value: boolean) => {
    const newPrefs = { ...notificationSettings, [key]: value };
    setNotificationSettings(newPrefs);
    updatePrefsMutation.mutate(newPrefs);
  };

  const handleSaveProfile = async () => {
    try {
      const updatedUser = await apiClient.updateMe(profileForm);
      setSaveSuccess(true);
      setTimeout(() => setSaveSuccess(false), 2000);
      setIsEditingProfile(false);
      // Update the local form state with the returned data
      setProfileForm({
        name: updatedUser.name || '',
        email: updatedUser.email || '',
      });
    } catch (error) {
      console.error('Failed to update profile:', error);
    }
  };

  const handleSaveSalon = async () => {
    try {
      await apiClient.updateProvider(providerId || '', salonForm);
      setSaveSuccess(true);
      setTimeout(() => setSaveSuccess(false), 2000);
      setIsEditingSalon(false);
    } catch (error) {
      console.error('Failed to update salon:', error);
    }
  };

  const handleProfilePhotoUpload = async () => {
    if (!profilePhotoFile) return;
    try {
      const updatedUser = await apiClient.uploadProfilePhoto(profilePhotoFile);
      setProfileForm({
        name: updatedUser.name || '',
        email: updatedUser.email || '',
      });
      setSaveSuccess(true);
      setTimeout(() => setSaveSuccess(false), 2000);
      setProfilePhotoFile(null);
    } catch (error) {
      console.error('Failed to upload profile photo:', error);
    }
  };

  const handleProfilePhotoRemove = async () => {
    try {
      const updatedUser = await apiClient.removeProfilePhoto();
      setProfileForm({
        name: updatedUser.name || '',
        email: updatedUser.email || '',
      });
      setSaveSuccess(true);
      setTimeout(() => setSaveSuccess(false), 2000);
    } catch (error) {
      console.error('Failed to remove profile photo:', error);
    }
  };

  const handleSalonLogoUpload = async () => {
    if (!salonLogoFile || !providerId) return;
    try {
      const updatedSalon = await apiClient.uploadProviderLogo(providerId, salonLogoFile);
      setSalonForm({
        name: updatedSalon.name || '',
        description: updatedSalon.description || '',
        phone: updatedSalon.phone || '',
        email: updatedSalon.email || '',
        website: updatedSalon.website || '',
        address: updatedSalon.address || '',
        city: updatedSalon.city || '',
        country: updatedSalon.country || '',
        lat: updatedSalon.lat || '',
        lng: updatedSalon.lng || '',
      });
      // Update active provider in context to refresh sidebar immediately
      setActiveProvider({ ...activeProvider, logo_url: updatedSalon.logo_url });
      setSaveSuccess(true);
      setTimeout(() => setSaveSuccess(false), 2000);
      setSalonLogoFile(null);
      setSalonLogoPreview(null);
    } catch (error) {
      console.error('Failed to upload salon logo:', error);
    }
  };

  const handleSalonLogoRemove = async () => {
    if (!providerId) return;
    try {
      const updatedSalon = await apiClient.removeProviderLogo(providerId);
      setSalonForm({
        name: updatedSalon.name || '',
        description: updatedSalon.description || '',
        phone: updatedSalon.phone || '',
        email: updatedSalon.email || '',
        website: updatedSalon.website || '',
        address: updatedSalon.address || '',
        city: updatedSalon.city || '',
        country: updatedSalon.country || '',
        lat: updatedSalon.lat || '',
        lng: updatedSalon.lng || '',
      });
      setSaveSuccess(true);
      setTimeout(() => setSaveSuccess(false), 2000);
    } catch (error) {
      console.error('Failed to remove salon logo:', error);
    }
  };

  const handleCoverImageUpload = async () => {
    console.log('[handleCoverImageUpload] Starting upload', {
      hasFile: !!coverImageFile,
      fileName: coverImageFile?.name,
      fileSize: coverImageFile?.size,
      providerId: activeProvider?.provider_id,
    });
    
    if (!coverImageFile || !activeProvider?.provider_id) {
      console.error('[handleCoverImageUpload] Missing file or provider_id');
      return;
    }
    
    try {
      const updatedProvider = await apiClient.uploadProviderCoverImage(activeProvider.provider_id, coverImageFile);
      console.log('[handleCoverImageUpload] Upload successful', updatedProvider);
      setSaveSuccess(true);
      setTimeout(() => setSaveSuccess(false), 2000);
      setCoverImageFile(null);
      setCoverImagePreview(null);
    } catch (error) {
      console.error('Failed to upload cover image:', error);
    }
  };

  const handleBackgroundImageUpload = async () => {
    if (!backgroundImageFile) return;
    try {
      const updatedBrand = await apiClient.uploadBrandBackgroundImage(backgroundImageFile);
      setBrandExperience(updatedBrand);
      setSaveSuccess(true);
      setTimeout(() => setSaveSuccess(false), 2000);
      setBackgroundImageFile(null);
      setBackgroundImagePreview(null);
    } catch (error) {
      console.error('Failed to upload background image:', error);
    }
  };

  // Load brand experience data
  useEffect(() => {
    if (providerId) {
      apiClient.get(`/v1/brand-experience`).then((data) => {
        setBrandExperience(data.brand || data);
      }).catch((error) => {
        console.error('Failed to load brand experience:', error);
      });
    }
  }, [providerId]);

  // Load gallery data
  useEffect(() => {
    if (providerId) {
      apiClient.getGallery().then((data) => {
        setGallery(data.data || []);
      }).catch((error) => {
        console.error('Failed to load gallery:', error);
      });
    }
  }, [providerId]);

  const handleGalleryUpload = async () => {
    if (!galleryFile) return;
    try {
      const newImage = await apiClient.uploadGalleryImage(galleryFile, 'Gallery image');
      setGallery([newImage, ...gallery]);
      setSaveSuccess(true);
      setTimeout(() => setSaveSuccess(false), 2000);
      setGalleryFile(null);
    } catch (error) {
      console.error('Failed to upload gallery image:', error);
    }
  };

  const handleGalleryDelete = async (imageId: string) => {
    try {
      await apiClient.deleteGalleryImage(imageId);
      setGallery(gallery.filter(img => img.id !== imageId));
      setSaveSuccess(true);
      setTimeout(() => setSaveSuccess(false), 2000);
    } catch (error) {
      console.error('Failed to delete gallery image:', error);
    }
  };
  
  const { theme: nextTheme, setTheme: setNextTheme } = useNextTheme();

  const settingsGroups = [
    {
      id: 'account',
      label: 'ACCOUNT',
      items: [
        { id: 'my-profile', label: 'Profile', icon: User },
        { id: 'notifications', label: 'Notifications', icon: Bell },
        { id: 'security', label: 'Security', icon: Lock },
        { id: 'appearance', label: 'Appearance', icon: Palette },
      ],
    },
    {
      id: 'business-group',
      label: terminology.business.toUpperCase(),
      items: [
        { id: 'salon-profile', label: 'Profile', icon: Building2 },
        { id: 'branding', label: 'Branding', icon: Globe },
        { id: 'operating-hours', label: 'Operating Hours', icon: Clock },
        { id: 'holidays-closures', label: 'Holidays & Closures', icon: CalendarDays },
      ],
    },
    {
      id: 'booking',
      label: 'BOOKING',
      items: [
        { id: 'booking-rules', label: 'Booking Rules', icon: ShieldAlert },
      ],
    },
    {
      id: 'business',
      label: 'BUSINESS',
      items: [
        { id: 'payment-methods', label: 'Payment Methods', icon: CreditCard },
        { id: 'staff-roster', label: `${terminology.specialistPlural} Roster`, icon: Users },
      ],
    },
    {
      id: 'plan',
      label: 'PLAN',
      items: [
        { id: 'membership', label: 'Membership', icon: Crown },
        { id: 'add-ons', label: 'Add-ons & Credits', icon: Sparkles },
      ],
    },
  ];

  const SettingRow = ({ label, value, onClick }: { label: string, value?: string, onClick?: () => void }) => (
    <div 
      onClick={onClick}
      className={`flex items-center justify-between py-4 border-b border-white/[0.04] last:border-0 group ${onClick ? 'cursor-pointer hover:bg-white/[0.02] -mx-4 px-4 transition-colors' : ''}`}
    >
      <span className="text-sm font-medium text-text-primary/90">{label}</span>
      <div className="flex items-center gap-3">
        {value && <span className="text-sm text-text-secondary">{value}</span>}
        {onClick && <ChevronRight className="w-4 h-4 text-text-secondary/50 group-hover:text-text-primary/70 transition-colors" />}
      </div>
    </div>
  );

  return (
    <DashboardLayout>
      <div className="max-w-[1000px] mx-auto overflow-x-hidden font-sans">
        <div className="mb-8">
          <h1 className="text-3xl font-semibold text-text-primary tracking-tight">Settings</h1>
          <p className="text-text-secondary mt-2 mb-6">Configure your {terminology.business.toLowerCase()}, brand, bookings and workspace.</p>
          <ScheduleMigrationBanner />
          <OnboardingChecklist variant="full" />
        </div>

        <div className="flex flex-col md:flex-row gap-10 items-start">
          {/* Sidebar */}
          <div className="w-full md:w-[240px] flex-shrink-0">
            <div className="flex flex-col gap-6">
              {settingsGroups.map((group) => (
                <div key={group.id}>
                  <div className="text-xs font-semibold text-text-secondary/60 tracking-widest uppercase mb-2 px-3">
                    {group.label}
                  </div>
                  <div className="flex flex-col gap-1">
                    {group.items.map((item) => (
                      <button
                        key={item.id}
                        onClick={() => setActiveTab(item.id)}
                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-sm ${
                          activeTab === item.id
                            ? 'bg-white/[0.06] text-text-primary font-medium'
                            : 'text-text-secondary hover:bg-white/[0.02] hover:text-text-primary'
                        }`}
                      >
                        <item.icon className={`w-4 h-4 shrink-0 ${activeTab === item.id ? 'text-[#FFD700]' : 'text-text-secondary/70'}`} />
                        <span>{item.label}</span>
                      </button>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Content */}
          <div className="flex-1 min-w-0 w-full">
            {activeTab === 'my-profile' && (
              <motion.div
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                className="space-y-8"
              >
                <div>
                  <div className="flex items-center justify-between mb-1">
                    <h2 className="text-lg font-medium text-text-primary">My Profile</h2>
                    {!isEditingProfile && (
                      <button
                        onClick={() => setIsEditingProfile(true)}
                        className="px-3 py-1.5 bg-white/[0.06] hover:bg-white/10 border border-white/10 rounded-lg text-sm font-medium text-text-primary transition-colors"
                      >
                        Edit
                      </button>
                    )}
                  </div>
                  <p className="text-sm text-text-secondary mb-6">Manage your personal information and identity.</p>

                  {/* Profile Photo */}
                  <div className="py-6 border-b border-white/[0.04]">
                    <div className="text-sm font-medium text-text-primary mb-1">Profile Photo</div>
                    <div className="text-xs text-text-secondary mb-3">Your personal identity across Yo.Salon</div>
                    
                    <div className="relative w-32 h-32 rounded-full overflow-hidden bg-black/20 border-2 border-white/10">
                      {profilePhotoPreview ? (
                        <img 
                          src={profilePhotoPreview} 
                          alt="Profile Preview" 
                          className="w-full h-full object-cover"
                        />
                      ) : user?.profile_photo?.url ? (
                        <img 
                          src={user.profile_photo.url} 
                          alt="Profile Photo" 
                          className="w-full h-full object-cover"
                          onError={(e) => console.error('Profile photo failed to load:', user.profile_photo.url)}
                        />
                      ) : (
                        <div className="w-full h-full flex items-center justify-center bg-gradient-to-br from-[#FF622B] to-[#FF8C5A]">
                          <span className="text-2xl font-semibold text-white">
                            {profileForm.name?.charAt(0) || 'U'}
                          </span>
                        </div>
                      )}
                    </div>
                    
                    <div className="flex gap-3 mt-4">
                      <input
                        type="file"
                        accept="image/*"
                        onChange={(e) => {
                          const file = e.target.files?.[0] || null;
                          setProfilePhotoFile(file);
                          if (file) {
                            const reader = new FileReader();
                            reader.onloadend = () => {
                              setProfilePhotoPreview(reader.result as string);
                            };
                            reader.readAsDataURL(file);
                          }
                        }}
                        className="hidden"
                        id="profile-photo-upload"
                      />
                      <label htmlFor="profile-photo-upload" className="px-4 py-2 bg-white/[0.06] hover:bg-white/10 border border-white/10 rounded-lg text-sm font-medium text-text-primary transition-colors cursor-pointer">
                        Change photo
                      </label>
                      {profilePhotoFile && (
                        <>
                          <button
                            onClick={handleProfilePhotoUpload}
                            className="px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity"
                          >
                            Save Photo
                          </button>
                          <button
                            onClick={() => {
                              setProfilePhotoFile(null);
                              setProfilePhotoPreview(null);
                            }}
                            className="px-4 py-2 text-text-secondary hover:text-red-400 text-sm font-medium transition-colors"
                          >
                            Cancel
                          </button>
                        </>
                      )}
                      <button
                        onClick={handleProfilePhotoRemove}
                        className="px-4 py-2 text-text-secondary hover:text-red-400 text-sm font-medium transition-colors"
                      >
                        Remove
                      </button>
                    </div>
                  </div>

                  <div className="mt-6 space-y-4">
                    <div>
                      <label className="block text-sm font-medium text-text-secondary mb-2">Full Name</label>
                      {isEditingProfile ? (
                        <input
                          type="text"
                          value={profileForm.name}
                          onChange={(e) => setProfileForm({ ...profileForm, name: e.target.value })}
                          className="w-full bg-card border border-border-light text-text-primary rounded-xl px-4 py-3 focus:outline-none focus:border-[#FFD700]/50 transition-all text-sm"
                        />
                      ) : (
                        <p className="text-text-primary">{profileForm.name || 'Not provided'}</p>
                      )}
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-text-secondary mb-2">Email Address</label>
                      {isEditingProfile ? (
                        <input
                          type="email"
                          value={profileForm.email}
                          onChange={(e) => setProfileForm({ ...profileForm, email: e.target.value })}
                          className="w-full bg-card border border-border-light text-text-primary rounded-xl px-4 py-3 focus:outline-none focus:border-[#FFD700]/50 transition-all text-sm"
                        />
                      ) : (
                        <p className="text-text-primary">{profileForm.email}</p>
                      )}
                    </div>
                    {isEditingProfile && (
                      <div className="flex gap-3 mt-4">
                        <button
                          onClick={handleSaveProfile}
                          className="px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-xl text-sm font-semibold hover:opacity-90 transition-opacity"
                        >
                          Save Changes
                        </button>
                        <button
                          onClick={() => {
                            setIsEditingProfile(false);
                            setProfileForm({
                              name: user?.name || '',
                              email: user?.email || '',
                            });
                          }}
                          className="px-4 py-2 bg-card border border-border-light text-text-primary rounded-xl text-sm font-medium hover:bg-white/10 transition-colors"
                        >
                          Cancel
                        </button>
                      </div>
                    )}

                    {saveSuccess && (
                      <div className="flex items-center gap-2 text-emerald-400 text-sm">
                        <CheckCircle className="w-4 h-4" />
                        <span>Changes saved successfully</span>
                      </div>
                    )}
                  </div>
                </div>
              </motion.div>
            )}

            {activeTab === 'salon-profile' && (
              <motion.div
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                className="space-y-8"
              >
                <div>
                  <div className="flex items-center justify-between mb-1">
                    <h2 className="text-lg font-medium text-text-primary">{terminology.business} Profile</h2>
                    {!isEditingSalon && (
                      <button
                        onClick={() => setIsEditingSalon(true)}
                        className="px-3 py-1.5 bg-white/[0.06] hover:bg-white/10 border border-white/10 rounded-lg text-sm font-medium text-text-primary transition-colors"
                      >
                        Edit
                      </button>
                    )}
                  </div>
                  <p className="text-sm text-text-secondary mb-6">Manage how your {terminology.business.toLowerCase()} is represented on Yo.Salon.</p>

                  {/* Profile Images */}
                  <div className="mb-8">
                    <h3 className="text-xs font-semibold text-text-secondary/60 tracking-widest uppercase mb-4">PROFILE IMAGES</h3>

                    {/* Salon Logo */}
                  <div className="flex items-center gap-6 py-6 border-b border-white/[0.04]">
                    <div className="w-20 h-20 rounded-xl bg-card border border-border-light flex items-center justify-center overflow-hidden">
                      {salonLogoPreview ? (
                        <img src={salonLogoPreview} alt="Logo Preview" className="w-full h-full object-cover" />
                      ) : activeProvider?.logo_url ? (
                        <img 
                          src={activeProvider?.logo_url} 
                          alt="Salon Logo" 
                          className="w-full h-full object-cover"
                          onError={(e) => console.error('Logo failed to load:', activeProvider?.logo_url)}
                        />
                      ) : (
                        <Building2 className="w-8 h-8 text-text-secondary/50" />
                      )}
                    </div>
                    <div>
                      <div className="text-sm font-medium text-text-primary mb-1">{terminology.business} Logo</div>
                      <div className="text-xs text-text-secondary mb-3">Represents your business across Yo.Salon</div>
                      <div className="flex gap-3 mt-1">
                        <input
                          type="file"
                          accept="image/*"
                          onChange={(e) => {
                            const file = e.target.files?.[0];
                            if (file) {
                              setSalonLogoFile(file);
                              const reader = new FileReader();
                              reader.onloadend = () => {
                                setSalonLogoPreview(reader.result as string);
                              };
                              reader.readAsDataURL(file);
                            }
                          }}
                          className="hidden"
                          id="salon-logo-upload"
                        />
                        <label htmlFor="salon-logo-upload" className="px-4 py-2 bg-white/[0.06] hover:bg-white/10 border border-white/10 rounded-lg text-sm font-medium text-text-primary transition-colors cursor-pointer">
                          Upload logo
                        </label>
                        {salonLogoFile && (
                          <>
                            <button
                              onClick={handleSalonLogoUpload}
                              className="px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity"
                            >
                              Save Logo
                            </button>
                            <button
                              onClick={() => {
                                setSalonLogoFile(null);
                                setSalonLogoPreview(null);
                              }}
                              className="px-4 py-2 text-text-secondary hover:text-red-400 text-sm font-medium transition-colors"
                            >
                              Cancel
                            </button>
                          </>
                        )}
                        <button
                          onClick={handleSalonLogoRemove}
                          className="px-4 py-2 text-text-secondary hover:text-red-400 text-sm font-medium transition-colors"
                        >
                          Remove
                        </button>
                      </div>
                    </div>
                  </div>

                  {/* Cover Image */}
                  <div className="py-6 border-b border-white/[0.04]">
                    <div className="text-sm font-medium text-text-primary mb-1">Cover Photo</div>
                    <div className="text-xs text-text-secondary mb-3">The main image customers see when discovering your {terminology.business.toLowerCase()}</div>
                    
                    {coverImagePreview ? (
                      <div className="mb-3 rounded-lg overflow-hidden bg-black/20">
                        <img 
                          src={coverImagePreview} 
                          alt="Cover Preview" 
                          className="w-full h-48 object-cover"
                        />
                      </div>
                    ) : activeProvider?.provider?.cover_image_url ? (
                      <div className="mb-3 rounded-lg overflow-hidden bg-black/20">
                        <img 
                          src={activeProvider.provider.cover_image_url} 
                          alt="Cover Image" 
                          className="w-full h-48 object-cover"
                          onError={(e) => console.error('Cover image failed to load:', activeProvider.provider.cover_image_url)}
                        />
                      </div>
                    ) : (
                      <div className="mb-3 rounded-lg overflow-hidden bg-black/20 h-48 flex items-center justify-center text-text-secondary">
                        No cover image
                      </div>
                    )}
                    
                    <div className="flex gap-3">
                      <input
                        type="file"
                        accept="image/*"
                        onChange={(e) => {
                          const file = e.target.files?.[0] || null;
                          setCoverImageFile(file);
                          if (file) {
                            const reader = new FileReader();
                            reader.onloadend = () => {
                              setCoverImagePreview(reader.result as string);
                            };
                            reader.readAsDataURL(file);
                          }
                        }}
                        className="hidden"
                        id="cover-image-upload"
                      />
                      <label htmlFor="cover-image-upload" className="px-4 py-2 bg-white/[0.06] hover:bg-white/10 border border-white/10 rounded-lg text-sm font-medium text-text-primary transition-colors cursor-pointer">
                        Change cover
                      </label>
                      {coverImageFile && (
                        <>
                          <button
                            onClick={handleCoverImageUpload}
                            className="px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity"
                          >
                            Save Cover
                          </button>
                          <button
                            onClick={() => {
                              setCoverImageFile(null);
                              setCoverImagePreview(null);
                            }}
                            className="px-4 py-2 text-text-secondary hover:text-red-400 text-sm font-medium transition-colors"
                          >
                            Cancel
                          </button>
                        </>
                      )}
                    </div>
                  </div>

                  {/* Gallery */}
                  <div className="py-6 border-b border-white/[0.04]">
                    <div className="text-sm font-medium text-text-primary mb-1">Gallery</div>
                    <div className="text-xs text-text-secondary mb-3">Showcase your work, space, team, or style</div>
                    
                    <div className="grid grid-cols-4 gap-3 mb-4">
                      {gallery.slice(0, 8).map((image) => (
                        <div key={image.id} className="relative group aspect-square rounded-lg overflow-hidden bg-black/20">
                          <img 
                            src={image.url} 
                            alt={image.alt_text || 'Gallery image'} 
                            className="w-full h-full object-cover"
                          />
                          <button
                            onClick={() => handleGalleryDelete(image.id)}
                            className="absolute top-2 right-2 w-6 h-6 bg-black/60 hover:bg-red-500 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                          >
                            <svg className="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                          </button>
                        </div>
                      ))}
                      <div className="aspect-square rounded-lg border-2 border-dashed border-white/20 flex items-center justify-center hover:border-[#FFD700]/50 transition-colors">
                        <input
                          type="file"
                          accept="image/*"
                          onChange={(e) => setGalleryFile(e.target.files?.[0] || null)}
                          className="hidden"
                          id="gallery-upload"
                        />
                        <label htmlFor="gallery-upload" className="cursor-pointer flex flex-col items-center gap-1">
                          <svg className="w-6 h-6 text-text-secondary/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                          </svg>
                          <span className="text-xs text-text-secondary/50">Add</span>
                        </label>
                      </div>
                    </div>
                    
                    {galleryFile && (
                      <div className="flex gap-3">
                        <button
                          onClick={handleGalleryUpload}
                          className="px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity"
                        >
                          Upload Image
                        </button>
                        <button
                          onClick={() => setGalleryFile(null)}
                          className="px-4 py-2 text-text-secondary hover:text-red-400 text-sm font-medium transition-colors"
                        >
                          Cancel
                        </button>
                      </div>
                    )}
                  </div>
                  </div>

                  {/* Basic Information */}
                  <div className="mb-8">
                    <h3 className="text-xs font-semibold text-text-secondary/60 tracking-widest uppercase mb-4">BASIC INFORMATION</h3>
                    <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium text-text-secondary mb-2">{terminology.business} Name</label>
                      {isEditingSalon ? (
                        <input
                          type="text"
                          value={salonForm.name}
                          onChange={(e) => setSalonForm({ ...salonForm, name: e.target.value })}
                          className="w-full bg-card border border-border-light text-text-primary rounded-xl px-4 py-3 focus:outline-none focus:border-[#FFD700]/50 transition-all text-sm"
                        />
                      ) : (
                        <p className="text-text-primary">{activeProvider?.name || 'Not set'}</p>
                      )}
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-text-secondary mb-2">Description</label>
                      {isEditingSalon ? (
                        <textarea
                          value={salonForm.description}
                          onChange={(e) => setSalonForm({ ...salonForm, description: e.target.value })}
                          rows={3}
                          className="w-full bg-card border border-border-light text-text-primary rounded-xl px-4 py-3 focus:outline-none focus:border-[#FFD700]/50 transition-all text-sm resize-none"
                        />
                      ) : (
                        <p className="text-text-primary">{activeProvider?.description || 'Add a description'}</p>
                      )}
                    </div>
                  </div>
                  </div>

                  {/* Contact Information */}
                  <div className="mb-8">
                    <h3 className="text-xs font-semibold text-text-secondary/60 tracking-widest uppercase mb-4">CONTACT INFORMATION</h3>
                    <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium text-text-secondary mb-2">Phone</label>
                      {isEditingSalon ? (
                        <input
                          type="tel"
                          value={salonForm.phone}
                          onChange={(e) => setSalonForm({ ...salonForm, phone: e.target.value })}
                          className="w-full bg-card border border-border-light text-text-primary rounded-xl px-4 py-3 focus:outline-none focus:border-[#FFD700]/50 transition-all text-sm"
                        />
                      ) : (
                        <p className="text-text-primary">{activeProvider?.phone || 'Not set'}</p>
                      )}
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-text-secondary mb-2">Email</label>
                      {isEditingSalon ? (
                        <input
                          type="email"
                          value={salonForm.email}
                          onChange={(e) => setSalonForm({ ...salonForm, email: e.target.value })}
                          className="w-full bg-card border border-border-light text-text-primary rounded-xl px-4 py-3 focus:outline-none focus:border-[#FFD700]/50 transition-all text-sm"
                        />
                      ) : (
                        <p className="text-text-primary">{activeProvider?.email || 'Not set'}</p>
                      )}
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-text-secondary mb-2">Website</label>
                      {isEditingSalon ? (
                        <input
                          type="url"
                          value={salonForm.website}
                          onChange={(e) => setSalonForm({ ...salonForm, website: e.target.value })}
                          className="w-full bg-card border border-border-light text-text-primary rounded-xl px-4 py-3 focus:outline-none focus:border-[#FFD700]/50 transition-all text-sm"
                        />
                      ) : (
                        <p className="text-text-primary">{activeProvider?.website || 'Add website'}</p>
                      )}
                    </div>
                  </div>
                  </div>

                  {/* Location */}
                  <div className="mb-8">
                    <h3 className="text-xs font-semibold text-text-secondary/60 tracking-widest uppercase mb-4">LOCATION</h3>
                    <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium text-text-secondary mb-2">Address</label>
                      {isEditingSalon ? (
                        <input
                          type="text"
                          value={salonForm.address}
                          onChange={(e) => setSalonForm({ ...salonForm, address: e.target.value })}
                          className="w-full bg-card border border-border-light text-text-primary rounded-xl px-4 py-3 focus:outline-none focus:border-[#FFD700]/50 transition-all text-sm"
                        />
                      ) : (
                        <p className="text-text-primary">{activeProvider?.address || 'Add address'}</p>
                      )}
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-text-secondary mb-2">City</label>
                      {isEditingSalon ? (
                        <input
                          type="text"
                          value={salonForm.city}
                          onChange={(e) => setSalonForm({ ...salonForm, city: e.target.value })}
                          className="w-full bg-card border border-border-light text-text-primary rounded-xl px-4 py-3 focus:outline-none focus:border-[#FFD700]/50 transition-all text-sm"
                        />
                      ) : (
                        <p className="text-text-primary">{activeProvider?.city || 'Not set'}</p>
                      )}
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-text-secondary mb-2">Country</label>
                      {isEditingSalon ? (
                        <input
                          type="text"
                          value={salonForm.country}
                          onChange={(e) => setSalonForm({ ...salonForm, country: e.target.value })}
                          className="w-full bg-card border border-border-light text-text-primary rounded-xl px-4 py-3 focus:outline-none focus:border-[#FFD700]/50 transition-all text-sm"
                        />
                      ) : (
                        <p className="text-text-primary">{activeProvider?.country || 'Not set'}</p>
                      )}
                    </div>
                    {isEditingSalon && (
                      <button
                        onClick={() => setShowLocationPicker(true)}
                        className="w-full px-4 py-3 bg-white/[0.06] hover:bg-white/10 border border-white/10 rounded-xl text-sm font-medium text-text-primary transition-colors flex items-center justify-center gap-2"
                      >
                        <MapPin className="w-4 h-4" />
                        Pick location on map
                      </button>
                    )}
                  </div>
                  </div>

                  {showLocationPicker && (
                    <div className="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4 md:p-8">
                      <div className="w-full max-w-3xl max-h-[90vh] overflow-y-auto">
                        <LocationPicker
                          initialLocation={{
                            address: salonForm.address,
                            city: salonForm.city,
                            country: salonForm.country,
                            lat: activeProvider?.lat ? parseFloat(activeProvider.lat) : undefined,
                            lng: activeProvider?.lng ? parseFloat(activeProvider.lng) : undefined,
                          }}
                          brandColors={{
                            primary: brandExperience?.primary_color,
                            secondary: brandExperience?.secondary_color,
                            accent: brandExperience?.accent_color,
                          }}
                          onLocationSelect={(location) => {
                            setSalonForm({
                              ...salonForm,
                              address: location.address,
                              city: location.city,
                              country: location.country,
                              lat: location.lat.toString(),
                              lng: location.lng.toString(),
                            });
                            setShowLocationPicker(false);
                          }}
                          onCancel={() => setShowLocationPicker(false)}
                        />
                      </div>
                    </div>
                  )}

                  {isEditingSalon && (
                    <div className="flex gap-3 pt-4">
                      <button
                        onClick={handleSaveSalon}
                        className="px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-xl text-sm font-semibold hover:opacity-90 transition-opacity"
                      >
                        Save Changes
                      </button>
                      <button
                        onClick={() => {
                          setIsEditingSalon(false);
                          setSalonForm({
                            name: activeProvider?.name || '',
                            description: activeProvider?.description || '',
                            phone: activeProvider?.phone || '',
                            email: activeProvider?.email || '',
                            website: activeProvider?.website || '',
                            address: activeProvider?.address || '',
                            city: activeProvider?.city || '',
                            country: activeProvider?.country || '',
                            lat: activeProvider?.lat || '',
                            lng: activeProvider?.lng || '',
                          });
                        }}
                        className="px-4 py-2 bg-card border border-border-light text-text-primary rounded-xl text-sm font-medium hover:bg-white/10 transition-colors"
                      >
                        Cancel
                      </button>
                    </div>
                  )}

                  {saveSuccess && (
                    <div className="flex items-center gap-2 text-emerald-400 text-sm">
                      <CheckCircle className="w-4 h-4" />
                      <span>Changes saved successfully</span>
                    </div>
                  )}
                </div>
              </motion.div>
            )}

            {activeTab === 'notifications' && (
              <motion.div
                initial={{ opacity: 0, x: 20 }}
                animate={{ opacity: 1, x: 0 }}
                className="space-y-6"
              >
                <h2 className="text-xl font-semibold text-text-primary mb-6">Notification Settings</h2>
                
                {[
                  { label: 'Email notifications for new bookings', key: 'emailBookings' },
                  { label: 'SMS notifications for reminders', key: 'smsReminders' },
                  { label: 'Push notifications for updates', key: 'pushUpdates' },
                  { label: 'Weekly summary reports', key: 'weeklyReports' },
                ].map((item) => (
                  <div key={item.key} className="flex items-center justify-between py-3 border-b border-border-light">
                    <span className="text-text-primary">{item.label}</span>
                    <button
                      onClick={() => handlePrefChange(item.key, !notificationSettings[item.key as keyof typeof notificationSettings])}
                      className={`w-12 h-6 rounded-full transition-colors flex items-center p-0.5 ${
                        notificationSettings[item.key as keyof typeof notificationSettings] ? 'bg-[#FFD700]' : 'bg-[#1a1a1a] border border-white/10'
                      }`}
                    >
                        <div
                          className={`w-5 h-5 rounded-full bg-[#0A0A0A] transition-transform ${
                            notificationSettings[item.key as keyof typeof notificationSettings] ? 'translate-x-6' : 'translate-x-0.5'
                          }`}
                        />
                    </button>
                  </div>
                ))}
              </motion.div>
            )}

            {activeTab === 'staff-roster' && (
              <motion.div
                initial={{ opacity: 0, x: 20 }}
                animate={{ opacity: 1, x: 0 }}
              >
                <StaffRosterTab />
              </motion.div>
            )}

            {activeTab === 'operating-hours' && (
              <motion.div
                initial={{ opacity: 0, x: 20 }}
                animate={{ opacity: 1, x: 0 }}
              >
                <OperatingHoursTab />
              </motion.div>
            )}

            {activeTab === 'holidays-closures' && (
              <motion.div
                initial={{ opacity: 0, x: 20 }}
                animate={{ opacity: 1, x: 0 }}
              >
                <ScheduleExceptionsTab />
              </motion.div>
            )}

            {activeTab === 'booking-rules' && (
              <motion.div
                initial={{ opacity: 0, x: 20 }}
                animate={{ opacity: 1, x: 0 }}
              >
                <BookingRulesTab />
              </motion.div>
            )}

            {activeTab === 'payment-methods' && (
              <motion.div
                initial={{ opacity: 0, x: 20 }}
                animate={{ opacity: 1, x: 0 }}
              >
                <PaymentMethodsTab />
              </motion.div>
            )}

            {activeTab === 'add-ons' && (
              <motion.div
                initial={{ opacity: 0, x: 20 }}
                animate={{ opacity: 1, x: 0 }}
              >
                <AddOnsTab />
              </motion.div>
            )}

            {activeTab === 'appearance' && (
              <motion.div
                initial={{ opacity: 0, x: 20 }}
                animate={{ opacity: 1, x: 0 }}
                className="space-y-6"
              >
                <h2 className="text-xl font-semibold text-text-primary mb-6">Appearance Settings</h2>

                <div>
                  <label className="block text-text-secondary font-medium mb-3">Theme Preset</label>
                  <ThemeSwitcher />
                </div>

                <div>
                  <label className="block text-text-primary/70 font-medium mb-3">Accent Color</label>
                  <div className="flex gap-4">
                    {['#FFD700', '#FF622B', '#2F7A5C', '#6366F1'].map((color) => (
                      <button
                        key={color}
                        onClick={() => setAccentColor(color)}
                        className="w-10 h-10 rounded-full border-2 transition-all hover:scale-110"
                        style={{
                          backgroundColor: color,
                          borderColor: accentColor === color ? 'white' : 'transparent',
                        }}
                      />
                    ))}
                  </div>
                </div>
              </motion.div>
            )}

            {activeTab === 'branding' && (
              <motion.div
                initial={{ opacity: 0, x: 20 }}
                animate={{ opacity: 1, x: 0 }}
                className="space-y-6"
              >
                <h2 className="text-xl font-semibold text-text-primary mb-6">Salon Branding</h2>

                {/* Experience Background */}
                <div className="bg-card border border-border-light rounded-xl p-6">
                  <h3 className="text-text-primary font-semibold mb-2">Experience Background</h3>
                  <p className="text-text-secondary text-sm mb-4">This image shapes the visual world of your {terminology.business.toLowerCase()}'s digital experience.</p>
                  
                  {brandExperience?.background_image && (
                    <div className="mb-4 rounded-lg overflow-hidden bg-black/20">
                      <img 
                        src={brandExperience.background_image} 
                        alt="Experience Background" 
                        className="w-full h-48 object-cover"
                      />
                    </div>
                  )}
                  
                  <div className="flex gap-3">
                    <input
                      type="file"
                      accept="image/*"
                      onChange={(e) => {
                        const file = e.target.files?.[0] || null;
                        setBackgroundImageFile(file);
                        if (file) {
                          const reader = new FileReader();
                          reader.onloadend = () => {
                            setBackgroundImagePreview(reader.result as string);
                          };
                          reader.readAsDataURL(file);
                        }
                      }}
                      className="hidden"
                      id="background-image-upload"
                    />
                    <label htmlFor="background-image-upload" className="px-4 py-2 bg-white/[0.06] hover:bg-white/10 border border-white/10 rounded-lg text-sm font-medium text-text-primary transition-colors cursor-pointer">
                      Change background
                    </label>
                    {backgroundImageFile && (
                      <>
                        <button
                          onClick={handleBackgroundImageUpload}
                          className="px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity"
                        >
                          Save Background
                        </button>
                        <button
                          onClick={() => {
                            setBackgroundImageFile(null);
                            setBackgroundImagePreview(null);
                          }}
                          className="px-4 py-2 text-text-secondary hover:text-red-400 text-sm font-medium transition-colors"
                        >
                          Cancel
                        </button>
                      </>
                    )}
                  </div>
                  
                  {backgroundImagePreview && (
                    <div className="mt-4 rounded-lg overflow-hidden bg-black/20">
                      <img 
                        src={backgroundImagePreview} 
                        alt="Background Preview" 
                        className="w-full h-48 object-cover"
                      />
                    </div>
                  )}
                </div>

                <div className="bg-gradient-to-br from-[#FF622B]/10 via-[#FF622B]/5 to-transparent border border-[#FF622B]/25 rounded-xl p-6">
                  <div className="flex items-start gap-4">
                    <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-[#FF622B] to-[#FF8C5A] flex items-center justify-center flex-shrink-0">
                      <Globe className="w-6 h-6 text-white" />
                    </div>
                    <div className="flex-1">
                      <h3 className="text-text-primary font-semibold mb-1">Advanced Branding</h3>
                      <p className="text-text-secondary text-sm mb-4">Customize experience family, colors, fonts, and more.</p>
                      <Link
                        href={routes.settings + '/branding'}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-[#FF622B] to-[#FF8C5A] text-white rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity"
                      >
                        Customize Branding
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                        </svg>
                      </Link>
                    </div>
                  </div>
                </div>
              </motion.div>
            )}

            {activeTab === 'membership' && (
              <motion.div
                initial={{ opacity: 0, x: 20 }}
                animate={{ opacity: 1, x: 0 }}
                className="space-y-6"
              >
                <h2 className="text-xl font-semibold text-text-primary mb-6">Membership & Billing</h2>

                <div className="bg-gradient-to-br from-[#FFD700]/10 via-[#FFD700]/5 to-transparent border border-[#FFD700]/25 rounded-xl p-6">
                  <div className="flex items-start gap-4">
                    <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center flex-shrink-0">
                      <Crown className="w-6 h-6 text-black" />
                    </div>
                    <div className="flex-1">
                      <h3 className="text-text-primary font-semibold mb-1">Your Workspace</h3>
                      <p className="text-text-secondary text-sm mb-4">Manage your subscription, view usage, and upgrade your plan.</p>
                      <Link
                        href={routes.settings + '/membership'}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity"
                      >
                        Manage Membership
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                        </svg>
                      </Link>
                    </div>
                  </div>
                </div>

                <div className="space-y-4">
                  <div className="flex items-center justify-between py-3 border-b border-border-light">
                    <div>
                      <p className="text-text-primary font-medium">Current Plan</p>
                      <p className="text-text-secondary text-xs">View your current subscription details</p>
                    </div>
                    <Link href={routes.settings + '/membership'} className="text-[#FFD700] text-sm font-medium hover:underline">
                      View Details →
                    </Link>
                  </div>

                  <div className="flex items-center justify-between py-3 border-b border-border-light">
                    <div>
                      <p className="text-text-primary font-medium">Usage & Limits</p>
                      <p className="text-text-secondary text-xs">Check your current usage against plan limits</p>
                    </div>
                    <Link href={routes.settings + '/membership'} className="text-[#FFD700] text-sm font-medium hover:underline">
                      View Usage →
                    </Link>
                  </div>

                  <div className="flex items-center justify-between py-3">
                    <div>
                      <p className="text-text-primary font-medium">Upgrade Plan</p>
                      <p className="text-text-secondary text-xs">Upgrade to access more features</p>
                    </div>
                    <Link href={routes.settings + '/membership'} className="text-[#FFD700] text-sm font-medium hover:underline">
                      Upgrade →
                    </Link>
                  </div>
                </div>
              </motion.div>
            )}
          </div>
        </div>
      </div>

      {/* Success Toast */}
      <AnimatePresence>
        {saveSuccess && (
          <motion.div
            initial={{ opacity: 0, y: 50 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: 50 }}
            className="fixed bottom-8 right-8 bg-green-500/20 border border-green-500/30 rounded-xl p-4 flex items-center gap-3"
          >
            <CheckCircle className="w-5 h-5 text-green-400" />
            <span className="text-green-400 font-medium">Settings saved successfully!</span>
          </motion.div>
        )}
      </AnimatePresence>
    </DashboardLayout>
  );
}
