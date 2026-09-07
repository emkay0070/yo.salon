'use client';

import { motion } from 'framer-motion';
import { User, Phone, Mail, MapPin, Calendar, Scissors, Edit, LogOut, Award, Heart, Clock, Star, Palette, Shield, Lock, Globe, Cake, AlertCircle, ChevronRight, Building2, Link, Check, X, UserPlus } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { usePortalBrand } from '@/contexts/PortalBrandContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery, useMutation } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';
import { useState, useEffect } from 'react';

export default function ProfilePage() {
  const { customer, salon, logout } = usePortalAuth();
  const { brand } = usePortalBrand();
  const router = useRouter();
  const [activeTab, setActiveTab] = useState<'identity' | 'preferences' | 'profiles' | 'relationships' | 'security'>('identity');

  const { data: profileData, isLoading } = useQuery({
    queryKey: ['portal-profile'],
    queryFn: () => portalApiClient.get('/portal/context'),
    enabled: !!customer,
  });

  const handleLogout = async () => {
    await logout();
    router.push('/portal/login');
  };

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <h1 className="text-3xl font-bold text-text-primary mb-2">Profile</h1>
        <p className="text-text-secondary">Your grooming identity</p>
      </motion.div>

      {/* Profile Header Card */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.1 }}
        className="relative overflow-hidden rounded-2xl p-8"
        style={{
          background: `linear-gradient(135deg, var(--brand-primary, #FFD700)20, var(--brand-secondary, #C9A227)20)`,
          border: `1px solid var(--brand-primary, #FFD700)30`,
          borderRadius: 'var(--brand-border-radius, 16px)',
          boxShadow: 'var(--brand-shadow-lg, 0 8px 24px rgba(0,0,0,0.10))'
        }}
      >
        <div className="flex items-start gap-6 relative z-10">
          <div
            className="w-24 h-24 rounded-2xl flex items-center justify-center flex-shrink-0 cursor-pointer hover:opacity-80 transition-opacity"
            style={{
              background: `linear-gradient(to bottom right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))`,
              borderRadius: 'var(--brand-border-radius, 16px)'
            }}
          >
            <User className="w-12 h-12 text-white" />
          </div>
          <div className="flex-1">
            <h2 className="text-2xl font-bold text-text-primary mb-1">{customer?.name || 'Guest'}</h2>
            <p className="text-text-secondary mb-3">{profileData?.customer?.visits || 0} visits • Member since 2026</p>
            <div className="flex items-center gap-2">
              <Award className="w-4 h-4" style={{ color: 'var(--brand-primary, #FFD700)' }} />
              <span className="text-sm capitalize" style={{ color: 'var(--brand-primary, #FFD700)' }}>
                {profileData?.loyalty_summary?.tier || 'Bronze'} Tier
              </span>
            </div>
          </div>
        </div>
      </motion.div>

      {/* Tabs */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.15 }}
        className="flex gap-2 overflow-x-auto pb-2"
      >
        {[
          { id: 'identity', label: 'Identity', icon: User },
          { id: 'preferences', label: 'Preferences', icon: Palette },
          { id: 'profiles', label: 'Grooming Profiles', icon: Scissors },
          { id: 'relationships', label: 'Relationships', icon: UserPlus },
          { id: 'security', label: 'Security', icon: Shield },
        ].map((tab) => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id as any)}
            className={`flex-shrink-0 flex items-center gap-2 px-5 py-3 rounded-2xl text-sm font-medium transition-all ${
              activeTab === tab.id
                ? 'text-white'
                : 'bg-surface border border-border-light text-text-secondary hover:border-gold/30'
            }`}
            style={{
              ...(activeTab === tab.id
                ? { backgroundColor: 'var(--brand-primary, #FFD700)', borderRadius: 'var(--brand-border-radius, 16px)' }
                : { borderRadius: 'var(--brand-border-radius, 16px)' })
            }}
          >
            <tab.icon className="w-4 h-4" />
            {tab.label}
          </button>
        ))}
      </motion.div>

      {/* Tab Content */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.2 }}
      >
        {activeTab === 'identity' && <IdentityContent customer={customer} profileData={profileData} />}
        {activeTab === 'preferences' && <PreferencesContent profileData={profileData} />}
        {activeTab === 'profiles' && <GroomingProfilesContent profileData={profileData} />}
        {activeTab === 'relationships' && <RelationshipsContent customer={customer} />}
        {activeTab === 'security' && <SecurityContent profileData={profileData} />}
      </motion.div>

      {/* Logout */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.3 }}
      >
        <button
          onClick={handleLogout}
          className="w-full flex items-center justify-center gap-2 px-6 py-3 bg-surface border border-border-light rounded-xl text-text-secondary hover:text-red-500 hover:border-red-500/30 transition-all"
          style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
        >
          <LogOut className="w-4 h-4" />
          Logout
        </button>
      </motion.div>
    </div>
  );
}

function IdentityContent({ customer, profileData }: { customer: any, profileData: any }) {
  const [isEditing, setIsEditing] = useState(false);
  const [editData, setEditData] = useState({
    name: customer?.name || '',
    email: profileData?.portal_account?.email || customer?.email || '',
    phone: customer?.phone || '',
    birthday: customer?.birthday || '',
    address: customer?.address || '',
  });
  const [isSaving, setIsSaving] = useState(false);

  const handleSave = async () => {
    setIsSaving(true);
    try {
      await portalApiClient.put('/portal/profile', editData);
      setIsEditing(false);
      // Refresh context data
      window.location.reload();
    } catch (error) {
      console.error('Failed to update profile:', error);
      alert('Failed to update profile');
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-lg font-semibold text-text-primary">Personal Information</h3>
          {!isEditing && (
            <button
              onClick={() => setIsEditing(true)}
              className="flex items-center gap-2 text-sm font-medium"
              style={{ color: 'var(--brand-primary, #FFD700)' }}
            >
              <Edit className="w-4 h-4" />
              Edit
            </button>
          )}
        </div>

        {isEditing ? (
          <div className="space-y-4">
            <div>
              <label className="block text-sm text-text-secondary mb-2">Name</label>
              <input
                type="text"
                value={editData.name}
                onChange={(e) => setEditData({ ...editData, name: e.target.value })}
                className="w-full px-4 py-3 rounded-xl border border-border-light bg-background text-text-primary focus:outline-none focus:border-gold/50"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              />
            </div>
            <div>
              <label className="block text-sm text-text-secondary mb-2">Email</label>
              <input
                type="email"
                value={editData.email}
                onChange={(e) => setEditData({ ...editData, email: e.target.value })}
                className="w-full px-4 py-3 rounded-xl border border-border-light bg-background text-text-primary focus:outline-none focus:border-gold/50"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              />
            </div>
            <div>
              <label className="block text-sm text-text-secondary mb-2">Phone</label>
              <input
                type="tel"
                value={editData.phone}
                onChange={(e) => setEditData({ ...editData, phone: e.target.value })}
                className="w-full px-4 py-3 rounded-xl border border-border-light bg-background text-text-primary focus:outline-none focus:border-gold/50"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              />
            </div>
            <div>
              <label className="block text-sm text-text-secondary mb-2">Birthday</label>
              <input
                type="date"
                value={editData.birthday}
                onChange={(e) => setEditData({ ...editData, birthday: e.target.value })}
                className="w-full px-4 py-3 rounded-xl border border-border-light bg-background text-text-primary focus:outline-none focus:border-gold/50"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              />
            </div>
            <div>
              <label className="block text-sm text-text-secondary mb-2">Address</label>
              <input
                type="text"
                value={editData.address}
                onChange={(e) => setEditData({ ...editData, address: e.target.value })}
                className="w-full px-4 py-3 rounded-xl border border-border-light bg-background text-text-primary focus:outline-none focus:border-gold/50"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              />
            </div>
            <div className="flex gap-3">
              <button
                onClick={handleSave}
                disabled={isSaving}
                className="flex-1 px-4 py-3 rounded-xl text-white font-medium disabled:opacity-50"
                style={{
                  background: `linear-gradient(to right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))`,
                  borderRadius: 'var(--brand-border-radius, 16px)'
                }}
              >
                {isSaving ? 'Saving...' : 'Save Changes'}
              </button>
              <button
                onClick={() => setIsEditing(false)}
                className="flex-1 px-4 py-3 rounded-xl border border-border-light text-text-primary font-medium"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              >
                Cancel
              </button>
            </div>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-4">
                <div className="w-10 h-10 rounded-xl flex items-center justify-center" style={{ backgroundColor: 'var(--brand-primary, #FFD700)20' }}>
                  <User className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                </div>
                <div>
                  <p className="text-sm text-text-secondary">Name</p>
                  <p className="text-text-primary font-medium">{customer?.name || 'Not provided'}</p>
                </div>
              </div>
              <ChevronRight className="w-5 h-5 text-text-secondary" />
            </div>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-4">
                <div className="w-10 h-10 rounded-xl flex items-center justify-center" style={{ backgroundColor: 'var(--brand-primary, #FFD700)20' }}>
                  <Mail className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                </div>
                <div>
                  <p className="text-sm text-text-secondary">Email</p>
                  <p className="text-text-primary font-medium">{profileData?.portal_account?.email || customer?.email || 'Not provided'}</p>
                </div>
              </div>
              <ChevronRight className="w-5 h-5 text-text-secondary" />
            </div>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-4">
                <div className="w-10 h-10 rounded-xl flex items-center justify-center" style={{ backgroundColor: 'var(--brand-primary, #FFD700)20' }}>
                  <Phone className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                </div>
                <div>
                  <p className="text-sm text-text-secondary">Phone</p>
                  <p className="text-text-primary font-medium">{customer?.phone || 'Not provided'}</p>
                </div>
              </div>
              <ChevronRight className="w-5 h-5 text-text-secondary" />
            </div>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-4">
                <div className="w-10 h-10 rounded-xl flex items-center justify-center" style={{ backgroundColor: 'var(--brand-primary, #FFD700)20' }}>
                  <Cake className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                </div>
                <div>
                  <p className="text-sm text-text-secondary">Birthday</p>
                  <p className="text-text-primary font-medium">{customer?.birthday || 'Not set'}</p>
                </div>
              </div>
              <ChevronRight className="w-5 h-5 text-text-secondary" />
            </div>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-4">
                <div className="w-10 h-10 rounded-xl flex items-center justify-center" style={{ backgroundColor: 'var(--brand-primary, #FFD700)20' }}>
                  <MapPin className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                </div>
                <div>
                  <p className="text-sm text-text-secondary">Address</p>
                  <p className="text-text-primary font-medium">{customer?.address || 'Not provided'}</p>
                </div>
              </div>
              <ChevronRight className="w-5 h-5 text-text-secondary" />
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

function PreferencesContent({ profileData }: { profileData: any }) {
  const loyaltyTier = profileData?.loyalty_summary?.tier || 'Bronze';
  const loyaltyPoints = profileData?.loyalty_summary?.points || 0;

  return (
    <div className="space-y-4">
      <ProfileSection
        title="Loyalty Status"
        items={[
          { icon: Award, label: 'Current Tier', value: loyaltyTier },
          { icon: Star, label: 'Points Balance', value: `${loyaltyPoints} points` },
          { icon: Clock, label: 'Points to Next Tier', value: `${profileData?.loyalty_summary?.points_to_next || 100} points` },
        ]}
      />
      <ProfileSection
        title="Notifications"
        items={[
          { icon: Mail, label: 'Email Notifications', value: 'Enabled' },
          { icon: Phone, label: 'SMS Notifications', value: 'Disabled' },
          { icon: Star, label: 'Promotional Offers', value: 'Enabled' },
        ]}
      />
    </div>
  );
}

function GroomingProfilesContent({ profileData }: { profileData: any }) {
  const [editingSection, setEditingSection] = useState<'hair' | 'beard' | 'skin' | null>(null);
  const [groomingData, setGroomingData] = useState<{
    hair_type: string;
    preferred_hair_style: string;
    hair_concerns: string[];
    beard_style: string;
    beard_products: string;
    skin_type: string;
    allergies: string[];
  }>({
    hair_type: '',
    preferred_hair_style: '',
    hair_concerns: [],
    beard_style: '',
    beard_products: '',
    skin_type: '',
    allergies: [],
  });
  const [isSaving, setIsSaving] = useState(false);

  // Fetch reference data from API
  const { data: referenceData } = useQuery({
    queryKey: ['reference-data'],
    queryFn: () => portalApiClient.post('/reference-data/categories', {
      categories: ['hair_type', 'hair_style', 'hair_concern', 'beard_style', 'beard_product', 'skin_type', 'allergy']
    }),
  });

  const hairTypes = referenceData?.data?.hair_type || [];
  const hairStyles = referenceData?.data?.hair_style || [];
  const hairConcerns = referenceData?.data?.hair_concern || [];
  const beardStyles = referenceData?.data?.beard_style || [];
  const beardProducts = referenceData?.data?.beard_product || [];
  const skinTypes = referenceData?.data?.skin_type || [];
  const commonAllergies = referenceData?.data?.allergy || [];

  const { data: groomingProfile } = useQuery({
    queryKey: ['grooming-profile'],
    queryFn: () => portalApiClient.get('/portal/grooming-profile'),
    enabled: !!profileData?.customer?.id,
  });

  useEffect(() => {
    if (groomingProfile?.grooming_profile) {
      setGroomingData(groomingProfile.grooming_profile);
    }
  }, [groomingProfile]);

  const handleSave = async () => {
    setIsSaving(true);
    try {
      await portalApiClient.put('/portal/grooming-profile', groomingData);
      setEditingSection(null);
    } catch (error) {
      console.error('Failed to update grooming profile:', error);
      alert('Failed to update grooming profile');
    } finally {
      setIsSaving(false);
    }
  };

  const gp = groomingProfile?.grooming_profile;

  return (
    <div className="space-y-4">
      {/* Hair Profile */}
      <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-lg font-semibold text-text-primary">Hair Profile</h3>
          {editingSection !== 'hair' && (
            <button
              onClick={() => setEditingSection('hair')}
              className="flex items-center gap-2 text-sm font-medium"
              style={{ color: 'var(--brand-primary, #FFD700)' }}
            >
              <Edit className="w-4 h-4" />
              Edit
            </button>
          )}
        </div>

        {editingSection === 'hair' ? (
          <div className="space-y-4">
            <div>
              <label className="block text-sm text-text-secondary mb-2">Hair Type</label>
              <select
                value={groomingData.hair_type || ''}
                onChange={(e) => setGroomingData({ ...groomingData, hair_type: e.target.value })}
                className="w-full px-4 py-3 rounded-xl border border-border-light bg-background text-text-primary focus:outline-none focus:border-gold/50"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              >
                <option value="">Select hair type</option>
                {hairTypes.map((type: any) => (
                  <option key={type.key} value={type.value}>{type.label}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm text-text-secondary mb-2">Preferred Hair Style</label>
              <select
                value={groomingData.preferred_hair_style || ''}
                onChange={(e) => setGroomingData({ ...groomingData, preferred_hair_style: e.target.value })}
                className="w-full px-4 py-3 rounded-xl border border-border-light bg-background text-text-primary focus:outline-none focus:border-gold/50"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              >
                <option value="">Select preferred style</option>
                {hairStyles.map((style: any) => (
                  <option key={style.key} value={style.value}>{style.label}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm text-text-secondary mb-2">Hair Concerns (select multiple)</label>
              <div className="grid grid-cols-2 gap-2">
                {hairConcerns.map((concern: any) => (
                  <label key={concern.key} className="flex items-center gap-2 text-sm text-text-secondary cursor-pointer">
                    <input
                      type="checkbox"
                      checked={groomingData.hair_concerns?.includes(concern.value)}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setGroomingData({ ...groomingData, hair_concerns: [...groomingData.hair_concerns, concern.value] as string[] });
                        } else {
                          setGroomingData({ ...groomingData, hair_concerns: groomingData.hair_concerns.filter((c: string) => c !== concern.value) as string[] });
                        }
                      }}
                      className="rounded"
                    />
                    {concern.label}
                  </label>
                ))}
              </div>
            </div>
            <div className="flex gap-3">
              <button
                onClick={handleSave}
                disabled={isSaving}
                className="flex-1 px-4 py-3 rounded-xl text-white font-medium disabled:opacity-50"
                style={{
                  background: `linear-gradient(to right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))`,
                  borderRadius: 'var(--brand-border-radius, 16px)'
                }}
              >
                {isSaving ? 'Saving...' : 'Save'}
              </button>
              <button
                onClick={() => setEditingSection(null)}
                className="flex-1 px-4 py-3 rounded-xl border border-border-light text-text-primary font-medium"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              >
                Cancel
              </button>
            </div>
          </div>
        ) : (
          <ProfileSection
            title=""
            items={[
              { icon: Scissors, label: 'Hair Type', value: gp?.hair_type || 'Not set' },
              { icon: Scissors, label: 'Preferred Style', value: gp?.preferred_hair_style || 'Not set' },
              { icon: AlertCircle, label: 'Hair Concerns', value: Array.isArray(gp?.hair_concerns) && gp.hair_concerns.length > 0 ? gp.hair_concerns.join(', ') : 'None' },
            ]}
          />
        )}
      </div>

      {/* Beard Profile */}
      <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-lg font-semibold text-text-primary">Beard Profile</h3>
          {editingSection !== 'beard' && (
            <button
              onClick={() => setEditingSection('beard')}
              className="flex items-center gap-2 text-sm font-medium"
              style={{ color: 'var(--brand-primary, #FFD700)' }}
            >
              <Edit className="w-4 h-4" />
              Edit
            </button>
          )}
        </div>

        {editingSection === 'beard' ? (
          <div className="space-y-4">
            <div>
              <label className="block text-sm text-text-secondary mb-2">Beard Style</label>
              <select
                value={groomingData.beard_style || ''}
                onChange={(e) => setGroomingData({ ...groomingData, beard_style: e.target.value })}
                className="w-full px-4 py-3 rounded-xl border border-border-light bg-background text-text-primary focus:outline-none focus:border-gold/50"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              >
                <option value="">Select beard style</option>
                {beardStyles.map((style: any) => (
                  <option key={style.key} value={style.value}>{style.label}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm text-text-secondary mb-2">Beard Products</label>
              <select
                value={groomingData.beard_products || ''}
                onChange={(e) => setGroomingData({ ...groomingData, beard_products: e.target.value })}
                className="w-full px-4 py-3 rounded-xl border border-border-light bg-background text-text-primary focus:outline-none focus:border-gold/50"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              >
                <option value="">Select product preference</option>
                {beardProducts.map((product: any) => (
                  <option key={product.key} value={product.value}>{product.label}</option>
                ))}
              </select>
            </div>
            <div className="flex gap-3">
              <button
                onClick={handleSave}
                disabled={isSaving}
                className="flex-1 px-4 py-3 rounded-xl text-white font-medium disabled:opacity-50"
                style={{
                  background: `linear-gradient(to right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))`,
                  borderRadius: 'var(--brand-border-radius, 16px)'
                }}
              >
                {isSaving ? 'Saving...' : 'Save'}
              </button>
              <button
                onClick={() => setEditingSection(null)}
                className="flex-1 px-4 py-3 rounded-xl border border-border-light text-text-primary font-medium"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              >
                Cancel
              </button>
            </div>
          </div>
        ) : (
          <ProfileSection
            title=""
            items={[
              { icon: Scissors, label: 'Beard Style', value: gp?.beard_style || 'Not set' },
              { icon: Scissors, label: 'Beard Products', value: gp?.beard_products || 'Not set' },
            ]}
          />
        )}
      </div>

      {/* Skin Profile */}
      <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-lg font-semibold text-text-primary">Skin Profile</h3>
          {editingSection !== 'skin' && (
            <button
              onClick={() => setEditingSection('skin')}
              className="flex items-center gap-2 text-sm font-medium"
              style={{ color: 'var(--brand-primary, #FFD700)' }}
            >
              <Edit className="w-4 h-4" />
              Edit
            </button>
          )}
        </div>

        {editingSection === 'skin' ? (
          <div className="space-y-4">
            <div>
              <label className="block text-sm text-text-secondary mb-2">Skin Type</label>
              <select
                value={groomingData.skin_type || ''}
                onChange={(e) => setGroomingData({ ...groomingData, skin_type: e.target.value })}
                className="w-full px-4 py-3 rounded-xl border border-border-light bg-background text-text-primary focus:outline-none focus:border-gold/50"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              >
                <option value="">Select skin type</option>
                {skinTypes.map((type: any) => (
                  <option key={type.key} value={type.value}>{type.label}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm text-text-secondary mb-2">Allergies (select multiple)</label>
              <div className="grid grid-cols-2 gap-2">
                {commonAllergies.map((allergy: any) => (
                  <label key={allergy.key} className="flex items-center gap-2 text-sm text-text-secondary cursor-pointer">
                    <input
                      type="checkbox"
                      checked={groomingData.allergies?.includes(allergy.value)}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setGroomingData({ ...groomingData, allergies: [...groomingData.allergies, allergy.value] as string[] });
                        } else {
                          setGroomingData({ ...groomingData, allergies: groomingData.allergies.filter((a: string) => a !== allergy.value) as string[] });
                        }
                      }}
                      className="rounded"
                    />
                    {allergy.label}
                  </label>
                ))}
              </div>
            </div>
            <div className="flex gap-3">
              <button
                onClick={handleSave}
                disabled={isSaving}
                className="flex-1 px-4 py-3 rounded-xl text-white font-medium disabled:opacity-50"
                style={{
                  background: `linear-gradient(to right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))`,
                  borderRadius: 'var(--brand-border-radius, 16px)'
                }}
              >
                {isSaving ? 'Saving...' : 'Save'}
              </button>
              <button
                onClick={() => setEditingSection(null)}
                className="flex-1 px-4 py-3 rounded-xl border border-border-light text-text-primary font-medium"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              >
                Cancel
              </button>
            </div>
          </div>
        ) : (
          <ProfileSection
            title=""
            items={[
              { icon: AlertCircle, label: 'Skin Type', value: gp?.skin_type || 'Not set' },
              { icon: AlertCircle, label: 'Allergies', value: Array.isArray(gp?.allergies) && gp.allergies.length > 0 ? gp.allergies.join(', ') : 'None' },
            ]}
          />
        )}
      </div>
    </div>
  );
}

function RelationshipsContent({ customer }: { customer: any }) {
  const { data: invitations, isLoading: loadingInvitations } = useQuery({
    queryKey: ['customer-invitations'],
    queryFn: () => portalApiClient.get('/v1/specialist-portal/customers/invitations/pending', {
      params: { customer_id: customer?.id }
    }),
    enabled: !!customer?.id,
  });

  const acceptMutation = useMutation({
    mutationFn: async (invitationId: string) => {
      return await portalApiClient.post('/v1/specialist-portal/customers/invitations/accept', {
        invitation_id: invitationId,
      });
    },
  });

  const declineMutation = useMutation({
    mutationFn: async (invitationId: string) => {
      return await portalApiClient.post('/v1/specialist-portal/customers/invitations/decline', {
        invitation_id: invitationId,
      });
    },
  });

  return (
    <div className="space-y-4">
      {/* Pending Invitations */}
      <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
        <h3 className="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
          <UserPlus className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
          Pending Invitations
        </h3>
        
        {loadingInvitations ? (
          <div className="text-center text-text-secondary py-4">Loading invitations...</div>
        ) : invitations?.invitations?.length === 0 ? (
          <div className="text-center text-text-secondary py-4">No pending invitations</div>
        ) : (
          <div className="space-y-3">
            {invitations?.invitations?.map((invitation: any) => (
              <div key={invitation.id} className="bg-background border border-border-light rounded-xl p-4">
                <div className="flex items-center justify-between mb-3">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center text-white font-bold">
                      {invitation.specialist?.name?.charAt(0) || 'S'}
                    </div>
                    <div>
                      <p className="font-medium text-text-primary">{invitation.specialist?.name || 'Specialist'}</p>
                      <p className="text-sm text-text-secondary">{invitation.target_provider?.name || 'Workspace'}</p>
                    </div>
                  </div>
                  <span className="text-xs text-text-secondary">
                    {new Date(invitation.expires_at).toLocaleDateString()}
                  </span>
                </div>
                <div className="flex gap-2">
                  <button
                    onClick={() => acceptMutation.mutate(invitation.id)}
                    disabled={acceptMutation.isPending}
                    className="flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-gradient-to-r from-gold to-amber-600 text-white text-sm font-medium disabled:opacity-50"
                  >
                    <Check className="w-4 h-4" />
                    Accept
                  </button>
                  <button
                    onClick={() => declineMutation.mutate(invitation.id)}
                    disabled={declineMutation.isPending}
                    className="flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg border border-border-light text-text-primary text-sm font-medium hover:bg-surface disabled:opacity-50"
                  >
                    <X className="w-4 h-4" />
                    Decline
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Active Relationships */}
      <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
        <h3 className="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
          <Building2 className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
          Active Relationships
        </h3>
        <div className="text-center text-text-secondary py-4">
          Your active specialist relationships will appear here
        </div>
      </div>
    </div>
  );
}

function SecurityContent({ profileData }: { profileData: any }) {
  const emailVerified = !!profileData?.portal_account?.email_verified_at;
  const phoneVerified = !!profileData?.portal_account?.phone_verified_at;

  return (
    <div className="space-y-4">
      <ProfileSection
        title="Security"
        items={[
          { icon: Lock, label: 'Password', value: 'Last changed recently' },
          { icon: Shield, label: 'Two-Factor Auth', value: 'Disabled' },
        ]}
      />
      <ProfileSection
        title="Verification Status"
        items={[
          { icon: Mail, label: 'Email Verified', value: emailVerified ? 'Verified' : 'Not verified' },
          { icon: Phone, label: 'Phone Verified', value: phoneVerified ? 'Verified' : 'Not verified' },
        ]}
      />
      <ProfileSection
        title="Privacy"
        items={[
          { icon: Shield, label: 'Profile Visibility', value: 'Private' },
          { icon: Shield, label: 'Data Sharing', value: 'Minimal' },
        ]}
      />
      <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
        <h3 className="text-lg font-semibold text-text-primary mb-4">Connected Accounts</h3>
        <div className="space-y-3">
          <ConnectedAccount provider="Google" connected={false} />
          <ConnectedAccount provider="Apple" connected={false} />
        </div>
      </div>
    </div>
  );
}

function ProfileSection({ title, items }: { title: string, items: { icon: any, label: string, value: string }[] }) {
  return (
    <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
      <h3 className="text-lg font-semibold text-text-primary mb-4">{title}</h3>
      <div className="space-y-4">
        {items.map((item, index) => (
          <div key={index} className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <div className="w-10 h-10 rounded-xl flex items-center justify-center" style={{ backgroundColor: 'var(--brand-primary, #FFD700)20' }}>
                <item.icon className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
              </div>
              <div>
                <p className="text-sm text-text-secondary">{item.label}</p>
                <p className="text-text-primary font-medium">{item.value}</p>
              </div>
            </div>
            <ChevronRight className="w-5 h-5 text-text-secondary" />
          </div>
        ))}
      </div>
    </div>
  );
}

function ConnectedAccount({ provider, connected }: { provider: string, connected: boolean }) {
  return (
    <div className="flex items-center justify-between p-4 bg-surface border border-border-light rounded-xl">
      <div className="flex items-center gap-4">
        <div className="w-10 h-10 rounded-xl flex items-center justify-center bg-surface border border-border-light">
          <span className="font-semibold text-text-primary">{provider.charAt(0)}</span>
        </div>
        <div>
          <p className="font-medium text-text-primary">{provider}</p>
          <p className="text-sm text-text-secondary">{connected ? 'Connected' : 'Not connected'}</p>
        </div>
      </div>
      <button className="px-4 py-2 text-sm font-medium rounded-lg" style={{ backgroundColor: connected ? 'rgba(16, 185, 129, 0.1)' : 'var(--brand-primary, #FFD700)20', color: connected ? '#10B981' : 'var(--brand-primary, #FFD700)', borderRadius: 'var(--brand-border-radius, 16px)' }}>
        {connected ? 'Disconnect' : 'Connect'}
      </button>
    </div>
  );
}
