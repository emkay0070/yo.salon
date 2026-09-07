'use client';

import { useSpecialistAuth } from '@/contexts/SpecialistAuthContext';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { 
  User, 
  Camera, 
  Globe, 
  Languages,
  Link as LinkIcon,
  Save,
  Edit,
  Scissors,
  Briefcase,
  ArrowRight,
  Eye,
  EyeOff,
  MapPin
} from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import { apiClient } from '@/lib/api-client';
import Link from 'next/link';

export default function ProfilePage() {
  const { specialist, refresh } = useSpecialistAuth();
  const [isEditing, setIsEditing] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  
  const [formData, setFormData] = useState({
    bio: '',
    languages: [] as string[],
    socialLinks: [] as { platform: string; url: string }[],
    photo_media_id: undefined as string | undefined,
    latitude: undefined as number | undefined,
    longitude: undefined as number | undefined,
    headline: '',
    visibility: 'public',
  });

  const { data: profileData, isLoading } = useQuery({
    queryKey: ['specialist-profile'],
    queryFn: async () => {
      return await apiClient.get('/v1/specialist-portal/profile');
    },
    enabled: !!specialist,
  });

  useEffect(() => {
    if (profileData) {
      setFormData({
        bio: profileData.bio || '',
        languages: profileData.languages || [],
        socialLinks: profileData.social_links || [],
        photo_media_id: undefined,
        latitude: profileData.latitude,
        longitude: profileData.longitude,
        headline: profileData.headline || '',
        visibility: profileData.visibility || 'public',
      });
    }
  }, [profileData]);

  const queryClient = useQueryClient();

  const updateProfileMutation = useMutation({
    mutationFn: async (data: typeof formData) => {
      const payload = {
        bio: data.bio,
        languages: data.languages,
        social_links: data.socialLinks,
        ...(data.photo_media_id ? { photo_media_id: data.photo_media_id } : {}),
        latitude: data.latitude,
        longitude: data.longitude,
        headline: data.headline,
        visibility: data.visibility,
      };
      return await apiClient.put('/v1/specialist-portal/profile', payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-profile'] });
      refresh();
      setIsEditing(false);
    },
  });

  const uploadPhotoMutation = useMutation({
    mutationFn: async (file: File) => {
      return await apiClient.uploadMedia(file, { directory: 'specialists/photos' });
    },
    onSuccess: (data) => {
      const mediaId = data.media?.id || data.id;
      setFormData(prev => ({ ...prev, photo_media_id: mediaId }));
      updateProfileMutation.mutate({ ...formData, photo_media_id: mediaId });
    }
  });

  const handleSave = () => {
    updateProfileMutation.mutate(formData);
  };

  const handlePhotoSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) uploadPhotoMutation.mutate(file);
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <div className="w-8 h-8 border-4 border-gold border-t-transparent rounded-full animate-spin"></div>
      </div>
    );
  }

  const completeness = Math.round(
    ([
      specialist?.name,
      specialist?.photo_url,
      profileData?.bio,
      profileData?.location,
      profileData?.languages?.length > 0,
      formData.headline,
      formData.socialLinks?.length > 0
    ].filter(Boolean).length / 7) * 100
  );

  const cardVariants = {
    initial: { opacity: 0, y: 20 },
    animate: { opacity: 1, y: 0 },
  };

  return (
    <div className="max-w-5xl mx-auto p-4 sm:p-6 lg:p-8 space-y-8 font-sans pb-32 lg:pb-12">

      {/* Page Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-heading font-bold text-text-primary">Identity Studio</h1>
          <p className="text-text-secondary mt-1">Manage how the world sees you</p>
        </div>
        <button
          onClick={() => {
            if (isEditing) handleSave();
            else setIsEditing(true);
          }}
          disabled={updateProfileMutation.isPending}
          className={`flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium transition-all ${
            isEditing 
              ? 'bg-gradient-to-r from-gold to-amber-600 text-obsidian shadow-lg shadow-gold/20 hover:opacity-90' 
              : 'bg-surface border border-border-light text-text-primary hover:bg-surface/80'
          }`}
        >
          {updateProfileMutation.isPending ? (
            <div className="w-4 h-4 border-2 border-obsidian border-t-transparent rounded-full animate-spin" />
          ) : isEditing ? (
            <Save className="w-4 h-4" />
          ) : (
            <Edit className="w-4 h-4" />
          )}
          {isEditing ? 'Save Changes' : 'Edit Identity'}
        </button>
      </div>

      {/* Hero Card */}
      <motion.div
        variants={cardVariants}
        initial="initial"
        animate="animate"
        className="relative rounded-[2rem] overflow-hidden bg-card border border-border-light shadow-2xl"
      >
        {/* Cover */}
        <div className="h-48 bg-gradient-to-br from-obsidian via-surface to-obsidian relative">
          <div className="absolute inset-0 bg-[linear-gradient(to_right,#ffffff08_1px,transparent_1px),linear-gradient(to_bottom,#ffffff08_1px,transparent_1px)] bg-[size:24px_24px]" />
          <div className="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-card to-transparent" />
        </div>

        {/* Content overlapping cover */}
        <div className="px-6 pb-8 lg:px-10 lg:pb-10">
          <div className="relative -mt-20 mb-6 flex justify-between items-end">
            {/* Photo */}
            <div className="relative">
              {profileData?.photo_url || specialist?.photo_url ? (
                <img
                  src={profileData?.photo_url || specialist?.photo_url || ''}
                  alt={specialist?.name || 'Profile'}
                  className={`w-32 h-32 rounded-2xl object-cover border-4 border-card shadow-xl ${uploadPhotoMutation.isPending ? 'opacity-50' : ''}`}
                />
              ) : (
                <div className={`w-32 h-32 rounded-2xl bg-gradient-to-br from-gold to-amber-600 border-4 border-card shadow-xl flex items-center justify-center text-obsidian text-4xl font-heading font-bold ${uploadPhotoMutation.isPending ? 'opacity-50' : ''}`}>
                  {specialist?.name?.charAt(0) || 'S'}
                </div>
              )}
              <input type="file" ref={fileInputRef} className="hidden" accept="image/*" onChange={handlePhotoSelect} />
              <button
                onClick={() => fileInputRef.current?.click()}
                disabled={uploadPhotoMutation.isPending}
                className="absolute -bottom-3 -right-3 w-10 h-10 rounded-xl bg-surface border border-border-light text-text-primary flex items-center justify-center shadow-lg hover:text-gold hover:border-gold transition-all z-10"
              >
                <Camera className="w-5 h-5" />
              </button>
            </div>

            {/* Progress */}
            <div className="hidden sm:flex flex-col items-end pb-2">
              <span className="text-xs text-text-secondary mb-1 uppercase tracking-widest">Profile Completeness</span>
              <div className="flex items-center gap-3">
                <div className="w-32 h-1.5 bg-surface rounded-full overflow-hidden">
                  <div
                    className="h-full bg-gradient-to-r from-gold to-amber-600 rounded-full transition-all duration-1000"
                    style={{ width: `${completeness}%` }}
                  />
                </div>
                <span className="text-gold font-heading font-bold text-lg">{completeness}%</span>
              </div>
            </div>
          </div>

          <div className="max-w-3xl">
            <h2 className="text-4xl font-heading font-bold text-text-primary mb-1 tracking-tight">
              {specialist?.name}
            </h2>
            <div className="flex items-center gap-2 text-base mb-5">
              <span className="text-gold font-medium">
                {specialist?.handle ? `@${specialist.handle}` : `@${specialist?.name?.toLowerCase().replace(/\s+/g, '') || 'specialist'}`}
              </span>
              {formData.headline && (
                <>
                  <span className="w-1 h-1 rounded-full bg-border-medium" />
                  <span className="text-text-secondary">{formData.headline}</span>
                </>
              )}
            </div>

            {profileData?.bio ? (
              <p className="text-lg text-text-secondary font-serif italic mb-6 leading-relaxed">
                &ldquo;{profileData.bio}&rdquo;
              </p>
            ) : (
              <p className="text-text-muted italic mb-6">No bio added yet. Click &quot;Edit Identity&quot; to add one.</p>
            )}

            <div className="flex flex-wrap gap-5 text-sm">
              {profileData?.location && (
                <div className="flex items-center gap-2 text-text-secondary">
                  <MapPin className="w-4 h-4 text-gold" />
                  <span>{profileData.location}</span>
                </div>
              )}
              {profileData?.languages?.length > 0 && (
                <div className="flex items-center gap-2 text-text-secondary">
                  <Languages className="w-4 h-4 text-gold" />
                  <span>{profileData.languages.join(' · ')}</span>
                </div>
              )}
            </div>
          </div>
        </div>
      </motion.div>

      {/* Two-Column Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {/* Left – Editable Sections */}
        <div className="lg:col-span-2 space-y-8">

          {/* Identity */}
          <motion.div variants={cardVariants} initial="initial" animate="animate" transition={{ delay: 0.1 }}
            className="bg-card/60 border border-border-light rounded-[2rem] p-6 lg:p-8 backdrop-blur-sm">
            <div className="flex items-center gap-3 mb-6">
              <div className="w-10 h-10 rounded-xl bg-gold/10 flex items-center justify-center">
                <User className="w-5 h-5 text-gold" />
              </div>
              <div>
                <h3 className="text-xl font-heading font-semibold text-text-primary">Identity</h3>
                <p className="text-sm text-text-secondary">Your personal information</p>
              </div>
            </div>

            <div className="space-y-5">
              <div>
                <label className="block text-xs font-medium text-text-secondary uppercase tracking-widest mb-2">Bio</label>
                {isEditing ? (
                  <textarea
                    value={formData.bio}
                    onChange={(e) => setFormData({ ...formData, bio: e.target.value })}
                    className="w-full p-4 bg-surface border border-border-light rounded-xl text-text-primary placeholder-text-muted focus:outline-none focus:border-gold transition-colors resize-none font-serif text-base leading-relaxed"
                    rows={4}
                    placeholder="Tell clients about yourself in a few memorable sentences..."
                  />
                ) : (
                  <div className="p-4 bg-surface/50 rounded-xl text-text-primary font-serif leading-relaxed min-h-[96px]">
                    {formData.bio || <span className="text-text-muted italic">Not provided</span>}
                  </div>
                )}
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                  <label className="block text-xs font-medium text-text-secondary uppercase tracking-widest mb-2">Languages</label>
                  {isEditing ? (
                    <input
                      type="text"
                      value={formData.languages.join(', ')}
                      onChange={(e) => setFormData({ ...formData, languages: e.target.value.split(',').map(s => s.trim()).filter(s => s) })}
                      className="w-full p-3.5 bg-surface border border-border-light rounded-xl text-text-primary focus:outline-none focus:border-gold transition-colors"
                      placeholder="English, Luganda, ..."
                    />
                  ) : (
                    <div className="p-3.5 bg-surface/50 rounded-xl text-text-primary min-h-[52px]">
                      {formData.languages.length > 0
                        ? <div className="flex flex-wrap gap-2">{formData.languages.map((l, i) => <span key={i} className="px-3 py-1 bg-surface border border-border-light rounded-full text-sm">{l}</span>)}</div>
                        : <span className="text-text-muted italic">Not provided</span>}
                    </div>
                  )}
                </div>
                <div>
                  <label className="block text-xs font-medium text-text-secondary uppercase tracking-widest mb-2">Location Coords</label>
                  {isEditing ? (
                    <div className="flex gap-2">
                      <input type="number" value={formData.latitude || ''} onChange={(e) => setFormData({ ...formData, latitude: parseFloat(e.target.value) || undefined })} className="w-full p-3.5 bg-surface border border-border-light rounded-xl text-text-primary focus:outline-none focus:border-gold transition-colors" placeholder="Lat" />
                      <input type="number" value={formData.longitude || ''} onChange={(e) => setFormData({ ...formData, longitude: parseFloat(e.target.value) || undefined })} className="w-full p-3.5 bg-surface border border-border-light rounded-xl text-text-primary focus:outline-none focus:border-gold transition-colors" placeholder="Lng" />
                    </div>
                  ) : (
                    <div className="p-3.5 bg-surface/50 rounded-xl text-text-primary min-h-[52px]">
                      {formData.latitude && formData.longitude
                        ? <span className="font-mono text-sm">{formData.latitude}, {formData.longitude}</span>
                        : <span className="text-text-muted italic">Derived from assignment</span>}
                    </div>
                  )}
                </div>
              </div>
            </div>
          </motion.div>

          {/* Professional Presence */}
          <motion.div variants={cardVariants} initial="initial" animate="animate" transition={{ delay: 0.2 }}
            className="bg-card/60 border border-border-light rounded-[2rem] p-6 lg:p-8 backdrop-blur-sm">
            <div className="flex items-center gap-3 mb-6">
              <div className="w-10 h-10 rounded-xl bg-gold/10 flex items-center justify-center">
                <Globe className="w-5 h-5 text-gold" />
              </div>
              <div>
                <h3 className="text-xl font-heading font-semibold text-text-primary">Professional Presence</h3>
                <p className="text-sm text-text-secondary">How you appear in search &amp; discovery</p>
              </div>
            </div>

            <div className="space-y-5">
              <div>
                <label className="block text-xs font-medium text-text-secondary uppercase tracking-widest mb-2">Public Headline</label>
                {isEditing ? (
                  <input
                    type="text"
                    value={formData.headline}
                    onChange={(e) => setFormData({ ...formData, headline: e.target.value })}
                    className="w-full p-3.5 bg-surface border border-border-light rounded-xl text-text-primary focus:outline-none focus:border-gold transition-colors"
                    placeholder="e.g. Master Barber · Executive Grooming Specialist"
                  />
                ) : (
                  <div className="p-3.5 bg-surface/50 rounded-xl text-text-primary min-h-[52px]">
                    {formData.headline || <span className="text-text-muted italic">Not provided</span>}
                  </div>
                )}
              </div>

              <div>
                <label className="block text-xs font-medium text-text-secondary uppercase tracking-widest mb-2">Social Links</label>
                {isEditing ? (
                  <div className="space-y-3">
                    {formData.socialLinks.map((link, idx) => (
                      <div key={idx} className="flex gap-3">
                        <input
                          type="text"
                          value={link.platform}
                          onChange={(e) => {
                            const updated = [...formData.socialLinks];
                            updated[idx] = { ...updated[idx], platform: e.target.value };
                            setFormData({ ...formData, socialLinks: updated });
                          }}
                          className="w-32 p-3 bg-surface border border-border-light rounded-xl text-text-primary focus:outline-none focus:border-gold transition-colors text-sm"
                          placeholder="Platform"
                        />
                        <input
                          type="text"
                          value={link.url}
                          onChange={(e) => {
                            const updated = [...formData.socialLinks];
                            updated[idx] = { ...updated[idx], url: e.target.value };
                            setFormData({ ...formData, socialLinks: updated });
                          }}
                          className="flex-1 p-3 bg-surface border border-border-light rounded-xl text-text-primary focus:outline-none focus:border-gold transition-colors text-sm"
                          placeholder="https://..."
                        />
                        <button
                          onClick={() => setFormData({ ...formData, socialLinks: formData.socialLinks.filter((_, i) => i !== idx) })}
                          className="px-3 py-2 bg-terracotta/10 text-terracotta rounded-xl hover:bg-terracotta/20 transition-colors text-sm"
                        >
                          Remove
                        </button>
                      </div>
                    ))}
                    <button
                      onClick={() => setFormData({ ...formData, socialLinks: [...formData.socialLinks, { platform: '', url: '' }] })}
                      className="w-full p-3 border border-dashed border-border-medium rounded-xl text-text-secondary hover:border-gold hover:text-gold transition-colors text-sm"
                    >
                      + Add Link
                    </button>
                  </div>
                ) : (
                  <div className="flex flex-wrap gap-3 min-h-[52px]">
                    {formData.socialLinks?.length > 0 ? formData.socialLinks.map((link, idx) => (
                      <a key={idx} href={link.url} target="_blank" rel="noreferrer"
                        className="flex items-center gap-2 px-4 py-2 bg-surface rounded-xl border border-border-light hover:border-gold transition-colors text-sm text-text-primary">
                        <LinkIcon className="w-4 h-4 text-text-secondary" />
                        {link.platform}
                      </a>
                    )) : (
                      <span className="text-text-muted italic">No social links provided</span>
                    )}
                  </div>
                )}
              </div>

              <div>
                <label className="block text-xs font-medium text-text-secondary uppercase tracking-widest mb-2">Visibility</label>
                {isEditing ? (
                  <select
                    value={formData.visibility}
                    onChange={(e) => setFormData({ ...formData, visibility: e.target.value })}
                    className="w-full p-3.5 bg-surface border border-border-light rounded-xl text-text-primary focus:outline-none focus:border-gold transition-colors"
                  >
                    <option value="public">Public — Visible in directories &amp; search</option>
                    <option value="unlisted">Unlisted — Accessible only via direct link</option>
                    <option value="private">Private — Hidden from public view</option>
                  </select>
                ) : (
                  <div className="flex items-center gap-3 p-3.5 bg-surface/50 rounded-xl text-text-primary">
                    {formData.visibility === 'public'
                      ? <Eye className="w-4 h-4 text-emerald" />
                      : <EyeOff className="w-4 h-4 text-text-muted" />}
                    <span className="capitalize">{formData.visibility}</span>
                  </div>
                )}
              </div>
            </div>
          </motion.div>

          {/* Portfolio */}
          <motion.div variants={cardVariants} initial="initial" animate="animate" transition={{ delay: 0.3 }}
            className="bg-card/60 border border-border-light rounded-[2rem] p-6 lg:p-8 backdrop-blur-sm">
            <div className="flex items-center justify-between mb-6">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-gold/10 flex items-center justify-center">
                  <Camera className="w-5 h-5 text-gold" />
                </div>
                <div>
                  <h3 className="text-xl font-heading font-semibold text-text-primary">Portfolio</h3>
                  <p className="text-sm text-text-secondary">Showcase your best work</p>
                </div>
              </div>
            </div>

            {profileData?.portfolio?.length > 0 ? (
              <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                {profileData.portfolio.map((img: string, idx: number) => (
                  <div key={idx} className="aspect-square rounded-2xl bg-surface border border-border-light overflow-hidden group relative">
                    <img src={img} alt="Portfolio" className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" />
                  </div>
                ))}
              </div>
            ) : (
              <div className="py-16 border-2 border-dashed border-border-light rounded-2xl flex flex-col items-center justify-center text-center">
                <Camera className="w-12 h-12 text-text-muted mb-4" />
                <p className="text-text-primary font-heading font-semibold mb-1">No portfolio items yet</p>
                <p className="text-sm text-text-secondary max-w-xs">Upload images of your best work to showcase your expertise. Coming as part of the Media architecture.</p>
              </div>
            )}
          </motion.div>

          {/* Cancel Button */}
          {isEditing && (
            <motion.button
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              onClick={() => setIsEditing(false)}
              className="w-full py-3 rounded-xl bg-surface border border-border-light text-text-primary font-medium hover:bg-surface/80 transition-colors"
            >
              Cancel
            </motion.button>
          )}
        </div>

        {/* Right Sidebar – Cross-navigation */}
        <div className="space-y-5">
          <motion.div variants={cardVariants} initial="initial" animate="animate" transition={{ delay: 0.15 }}>
            <Link href="/specialist-portal/craft"
              className="flex flex-col p-6 rounded-[2rem] bg-gradient-to-br from-surface to-obsidian border border-border-light hover:border-gold/50 transition-all duration-300 group">
              <div className="w-12 h-12 rounded-xl bg-gold/10 flex items-center justify-center mb-4 group-hover:scale-110 group-hover:bg-gold/20 transition-all">
                <Scissors className="w-6 h-6 text-gold" />
              </div>
              <h4 className="text-base font-heading font-semibold text-text-primary mb-1">Your Craft</h4>
              <p className="text-sm text-text-secondary mb-4 flex-1">Manage your services, skills, and specializations.</p>
              <div className="flex items-center gap-2 text-sm font-medium text-gold">
                View Craft <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
              </div>
            </Link>
          </motion.div>

          <motion.div variants={cardVariants} initial="initial" animate="animate" transition={{ delay: 0.25 }}>
            <Link href="/specialist-portal/career"
              className="flex flex-col p-6 rounded-[2rem] bg-gradient-to-br from-surface to-obsidian border border-border-light hover:border-gold/50 transition-all duration-300 group">
              <div className="w-12 h-12 rounded-xl bg-gold/10 flex items-center justify-center mb-4 group-hover:scale-110 group-hover:bg-gold/20 transition-all">
                <Briefcase className="w-6 h-6 text-gold" />
              </div>
              <h4 className="text-base font-heading font-semibold text-text-primary mb-1">Your Career</h4>
              <p className="text-sm text-text-secondary mb-4 flex-1">Track your history, reviews, qualifications, and achievements.</p>
              <div className="flex items-center gap-2 text-sm font-medium text-gold">
                View Career <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
              </div>
            </Link>
          </motion.div>

          {/* Contact Info read-only card */}
          <motion.div variants={cardVariants} initial="initial" animate="animate" transition={{ delay: 0.35 }}
            className="p-6 rounded-[2rem] bg-card/40 border border-border-light backdrop-blur-sm space-y-4">
            <h4 className="text-sm font-heading font-semibold text-text-secondary uppercase tracking-widest">Contact</h4>
            <div className="space-y-3 text-sm">
              <div className="flex items-center gap-3 text-text-secondary">
                <div className="w-8 h-8 rounded-full bg-surface border border-border-light flex items-center justify-center shrink-0">
                  <Languages className="w-3.5 h-3.5 text-gold" />
                </div>
                <span className="truncate">{specialist?.email}</span>
              </div>
              <div className="flex items-center gap-3 text-text-secondary">
                <div className="w-8 h-8 rounded-full bg-surface border border-border-light flex items-center justify-center shrink-0">
                  <Globe className="w-3.5 h-3.5 text-gold" />
                </div>
                <span>{specialist?.phone || 'No phone number'}</span>
              </div>
            </div>
          </motion.div>
        </div>
      </div>
    </div>
  );
}
