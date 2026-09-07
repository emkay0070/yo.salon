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
import { Avatar } from '@/components/ui/Avatar';
import ThemeSwitcher from '@/components/ThemeSwitcher';
import { salonRoutes } from '@/lib/routes';

export default function SalonSettingsPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = use(params);
  const router = useRouter();
  const { user, salonId, activeSalon, salonSlug } = useRole();
  const routes = salonRoutes(salonSlug);
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
    phone: '',
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
  });

  // File upload states
  const [profilePhotoFile, setProfilePhotoFile] = useState<File | null>(null);
  const [salonLogoFile, setSalonLogoFile] = useState<File | null>(null);

  // Verify user has access to this salon
  useEffect(() => {
    if (!user) {
      router.push(`/login?returnTo=/${slug}/settings`);
      return;
    }

    if (!salonId) {
      console.warn('[SalonSettingsPage] User has no salon assignment');
      return;
    }

    // Verify the salon slug matches the user's active salon
    // Use activeSalon from RoleContext instead of API call to avoid network errors
    if (activeSalon && activeSalon.slug !== slug) {
      console.error('[SalonSettingsPage] Access denied: user salon does not match URL salon');
      router.push(routes.dashboard);
    }
  }, [salonId, user, router, slug, activeSalon]);

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
        phone: user.phone || '',
      });
    }
  }, [user]);

  // Populate salon form with salon data
  useEffect(() => {
    if (activeSalon) {
      setSalonForm({
        name: activeSalon.name || '',
        description: activeSalon.description || '',
        phone: activeSalon.phone || '',
        email: activeSalon.email || '',
        website: activeSalon.website || '',
        address: activeSalon.address || '',
        city: activeSalon.city || '',
        country: activeSalon.country || '',
      });
    }
  }, [activeSalon]);

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
        phone: updatedUser.phone || '',
      });
    } catch (error) {
      console.error('Failed to update profile:', error);
    }
  };

  const handleSaveSalon = async () => {
    try {
      await apiClient.updateSalon(salonId || '', salonForm);
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
        phone: updatedUser.phone || '',
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
        phone: updatedUser.phone || '',
      });
      setSaveSuccess(true);
      setTimeout(() => setSaveSuccess(false), 2000);
    } catch (error) {
      console.error('Failed to remove profile photo:', error);
    }
  };

  const handleSalonLogoUpload = async () => {
    if (!salonLogoFile || !salonId) return;
    try {
      const updatedSalon = await apiClient.uploadSalonLogo(salonId, salonLogoFile);
      setSalonForm({
        name: updatedSalon.name || '',
        description: updatedSalon.description || '',
        phone: updatedSalon.phone || '',
        email: updatedSalon.email || '',
        website: updatedSalon.website || '',
        address: updatedSalon.address || '',
        city: updatedSalon.city || '',
        country: updatedSalon.country || '',
      });
      setSaveSuccess(true);
      setTimeout(() => setSaveSuccess(false), 2000);
      setSalonLogoFile(null);
    } catch (error) {
      console.error('Failed to upload salon logo:', error);
    }
  };

  const handleSalonLogoRemove = async () => {
    if (!salonId) return;
    try {
      const updatedSalon = await apiClient.removeSalonLogo(salonId);
      setSalonForm({
        name: updatedSalon.name || '',
        description: updatedSalon.description || '',
        phone: updatedSalon.phone || '',
        email: updatedSalon.email || '',
        website: updatedSalon.website || '',
        address: updatedSalon.address || '',
        city: updatedSalon.city || '',
        country: updatedSalon.country || '',
      });
      setSaveSuccess(true);
      setTimeout(() => setSaveSuccess(false), 2000);
    } catch (error) {
      console.error('Failed to remove salon logo:', error);
    }
  };
  
  const { theme: nextTheme, setTheme: setNextTheme } = useNextTheme();

  const tabs = [
    { id: 'my-profile', label: 'My Profile', icon: User },
    { id: 'salon-profile', label: 'Salon Profile', icon: Building2 },
    { id: 'notifications', label: 'Notifications', icon: Bell },
    { id: 'security', label: 'Security', icon: Lock },
    { id: 'appearance', label: 'Appearance', icon: Palette },
    { id: 'branding', label: 'Branding', icon: Globe },
    { id: 'membership', label: 'Membership', icon: Crown },
    { id: 'operating-hours', label: 'Operating Hours', icon: Clock },
    { id: 'holidays-closures', label: 'Holidays & Closures', icon: CalendarDays },
    { id: 'booking-rules', label: 'Booking Rules', icon: ShieldAlert },
    { id: 'payment-methods', label: 'Payment Methods', icon: CreditCard },
    { id: 'staff-roster', label: 'Staff Roster', icon: Users },
    { id: 'add-ons', label: 'Add-ons & Credits', icon: Sparkles },
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
        <div className="mb-10">
          <h1 className="text-3xl font-semibold text-text-primary tracking-tight">Settings</h1>
          <p className="text-text-secondary mt-2">{slug}</p>
        </div>

        <div className="flex flex-col md:flex-row gap-10 items-start">
          {/* Sidebar */}
          <div className="w-full md:w-[220px] flex-shrink-0">
            <div className="flex flex-col gap-1">
              {tabs.map((tab) => (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                  className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-sm ${
                    activeTab === tab.id
                      ? 'bg-white/[0.06] text-text-primary font-medium'
                      : 'text-text-secondary hover:bg-white/[0.02] hover:text-text-primary'
                  }`}
                >
                  <tab.icon className={`w-4 h-4 shrink-0 ${activeTab === tab.id ? 'text-[#FFD700]' : 'text-text-secondary/70'}`} />
                  <span>{tab.label}</span>
                </button>
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

                  <div className="flex items-center gap-6 py-6 border-b border-white/[0.04]">
                    <Avatar
                      name={profileForm.name || 'User'}
                      size="2xl"
                      shape="circle"
                    />
                    <div>
                      <div className="flex gap-3 mt-1">
                        <input
                          type="file"
                          accept="image/*"
                          onChange={(e) => setProfilePhotoFile(e.target.files?.[0] || null)}
                          className="hidden"
                          id="profile-photo-upload"
                        />
                        <label htmlFor="profile-photo-upload" className="px-4 py-2 bg-white/[0.06] hover:bg-white/10 border border-white/10 rounded-lg text-sm font-medium text-text-primary transition-colors cursor-pointer">
                          Upload new picture
                        </label>
                        {profilePhotoFile && (
                          <button
                            onClick={handleProfilePhotoUpload}
                            className="px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity"
                          >
                            Save Photo
                          </button>
                        )}
                        <button
                          onClick={handleProfilePhotoRemove}
                          className="px-4 py-2 text-text-secondary hover:text-red-400 text-sm font-medium transition-colors"
                        >
                          Remove
                        </button>
                      </div>
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
                        <p className="text-text-primary">{profileForm.email || 'Not provided'}</p>
                      )}
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-text-secondary mb-2">Phone Number</label>
                      {isEditingProfile ? (
                        <input
                          type="tel"
                          value={profileForm.phone}
                          onChange={(e) => setProfileForm({ ...profileForm, phone: e.target.value })}
                          className="w-full bg-card border border-border-light text-text-primary rounded-xl px-4 py-3 focus:outline-none focus:border-[#FFD700]/50 transition-all text-sm"
                        />
                      ) : (
                        <p className="text-text-primary">{profileForm.phone || 'Add phone number'}</p>
                      )}
                    </div>

                    {isEditingProfile && (
                      <div className="flex gap-3 pt-4">
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
                              phone: user?.phone || '',
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
                    <h2 className="text-lg font-medium text-text-primary">Salon Profile</h2>
                    {!isEditingSalon && (
                      <button
                        onClick={() => setIsEditingSalon(true)}
                        className="px-3 py-1.5 bg-white/[0.06] hover:bg-white/10 border border-white/10 rounded-lg text-sm font-medium text-text-primary transition-colors"
                      >
                        Edit
                      </button>
                    )}
                  </div>
                  <p className="text-sm text-text-secondary mb-6">Manage your business information and contact details.</p>

                  {/* Salon Logo */}
                  <div className="flex items-center gap-6 py-6 border-b border-white/[0.04]">
                    <div className="w-20 h-20 rounded-xl bg-card border border-border-light flex items-center justify-center overflow-hidden">
                      {activeSalon?.logo_url ? (
                        <img src={activeSalon?.logo_url} alt="Salon Logo" className="w-full h-full object-cover" />
                      ) : (
                        <Building2 className="w-8 h-8 text-text-secondary/50" />
                      )}
                    </div>
                    <div>
                      <div className="flex gap-3 mt-1">
                        <input
                          type="file"
                          accept="image/*"
                          onChange={(e) => setSalonLogoFile(e.target.files?.[0] || null)}
                          className="hidden"
                          id="salon-logo-upload"
                        />
                        <label htmlFor="salon-logo-upload" className="px-4 py-2 bg-white/[0.06] hover:bg-white/10 border border-white/10 rounded-lg text-sm font-medium text-text-primary transition-colors cursor-pointer">
                          Upload logo
                        </label>
                        {salonLogoFile && (
                          <button
                            onClick={handleSalonLogoUpload}
                            className="px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity"
                          >
                            Save Logo
                          </button>
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

                  {/* Basic Info */}
                  <div className="mt-6 space-y-4">
                    <h3 className="text-sm font-medium text-text-primary mb-4">Basic Information</h3>
                    <div>
                      <label className="block text-sm font-medium text-text-secondary mb-2">Salon Name</label>
                      {isEditingSalon ? (
                        <input
                          type="text"
                          value={salonForm.name}
                          onChange={(e) => setSalonForm({ ...salonForm, name: e.target.value })}
                          className="w-full bg-card border border-border-light text-text-primary rounded-xl px-4 py-3 focus:outline-none focus:border-[#FFD700]/50 transition-all text-sm"
                        />
                      ) : (
                        <p className="text-text-primary">{activeSalon?.name || 'Not set'}</p>
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
                        <p className="text-text-primary">{activeSalon?.description || 'Add a description'}</p>
                      )}
                    </div>
                  </div>

                  {/* Contact Info */}
                  <div className="mt-6 space-y-4">
                    <h3 className="text-sm font-medium text-text-primary mb-4">Contact Information</h3>
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
                        <p className="text-text-primary">{activeSalon?.phone || 'Not set'}</p>
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
                        <p className="text-text-primary">{activeSalon?.email || 'Not set'}</p>
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
                        <p className="text-text-primary">{activeSalon?.website || 'Add website'}</p>
                      )}
                    </div>
                  </div>

                  {/* Location */}
                  <div className="mt-6 space-y-4">
                    <h3 className="text-sm font-medium text-text-primary mb-4">Location</h3>
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
                        <p className="text-text-primary">{activeSalon?.address || 'Add address'}</p>
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
                        <p className="text-text-primary">{activeSalon?.city || 'Not set'}</p>
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
                        <p className="text-text-primary">{activeSalon?.country || 'Not set'}</p>
                      )}
                    </div>
                  </div>

                  {/* Operating Hours */}
                  <div className="mt-6">
                    <h3 className="text-sm font-medium text-text-primary mb-4">Operating Hours</h3>
                    <div className="bg-card border border-border-light rounded-xl p-4">
                      <OperatingHoursTab />
                    </div>
                  </div>

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
                            name: activeSalon?.name || '',
                            description: activeSalon?.description || '',
                            phone: activeSalon?.phone || '',
                            email: activeSalon?.email || '',
                            website: activeSalon?.website || '',
                            address: activeSalon?.address || '',
                            city: activeSalon?.city || '',
                            country: activeSalon?.country || '',
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

                <div className="bg-gradient-to-br from-[#FF622B]/10 via-[#FF622B]/5 to-transparent border border-[#FF622B]/25 rounded-xl p-6">
                  <div className="flex items-start gap-4">
                    <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-[#FF622B] to-[#FF8C5A] flex items-center justify-center flex-shrink-0">
                      <Globe className="w-6 h-6 text-white" />
                    </div>
                    <div className="flex-1">
                      <h3 className="text-text-primary font-semibold mb-1">Brand Experience</h3>
                      <p className="text-text-secondary text-sm mb-4">Customize your salon's visual identity with colors, fonts, and experience families.</p>
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

                <div className="space-y-4">
                  <div className="flex items-center justify-between py-3 border-b border-border-light">
                    <div>
                      <p className="text-text-primary font-medium">Experience Family</p>
                      <p className="text-text-secondary text-xs">Choose your design language</p>
                    </div>
                    <Link href={routes.settings + '/branding'} className="text-[#FF622B] text-sm font-medium hover:underline">
                      Customize →
                    </Link>
                  </div>

                  <div className="flex items-center justify-between py-3 border-b border-border-light">
                    <div>
                      <p className="text-text-primary font-medium">Brand Colors</p>
                      <p className="text-text-secondary text-xs">Primary, secondary, and accent colors</p>
                    </div>
                    <Link href={routes.settings + '/branding'} className="text-[#FF622B] text-sm font-medium hover:underline">
                      Customize →
                    </Link>
                  </div>

                  <div className="flex items-center justify-between py-3 border-b border-border-light">
                    <div>
                      <p className="text-text-primary font-medium">Typography</p>
                      <p className="text-text-secondary text-xs">Heading and body fonts</p>
                    </div>
                    <Link href={routes.settings + '/branding'} className="text-[#FF622B] text-sm font-medium hover:underline">
                      Customize →
                    </Link>
                  </div>

                  <div className="flex items-center justify-between py-3 border-b border-border-light">
                    <div>
                      <p className="text-text-primary font-medium">Logo & Images</p>
                      <p className="text-text-secondary text-xs">Upload your salon logo and background</p>
                    </div>
                    <Link href={routes.settings + '/branding'} className="text-[#FF622B] text-sm font-medium hover:underline">
                      Customize →
                    </Link>
                  </div>

                  <div className="flex items-center justify-between py-3">
                    <div>
                      <p className="text-text-primary font-medium">White Label</p>
                      <p className="text-text-secondary text-xs">Custom domain and white-label mode</p>
                    </div>
                    <Link href={routes.settings + '/branding'} className="text-[#FF622B] text-sm font-medium hover:underline">
                      Customize →
                    </Link>
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
