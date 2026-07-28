'use client';

import { useState } from 'react';
import { motion } from 'framer-motion';
import DashboardLayout from '@/components/DashboardLayout';
import { useSalonBranding } from '@/hooks/useSalonBranding';
import { apiClient } from '@/lib/api-client';
import {
  Palette,
  Type,
  Image as ImageIcon,
  Globe,
  Sparkles,
  Save,
  RefreshCw,
  Upload
} from 'lucide-react';

const EXPERIENCE_FAMILIES = [
  { id: 'luxury_noir', name: 'Luxury Noir', description: 'Sophisticated, premium dark theme with gold accents' },
  { id: 'modern_glass', name: 'Modern Glass', description: 'Clean, contemporary with cool blue tones' },
  { id: 'urban_pulse', name: 'Urban Pulse', description: 'Edgy, energetic with bold colors and dynamic motion' },
  { id: 'minimal_zen', name: 'Minimal Zen', description: 'Calm, peaceful with natural tones and soft motion' },
  { id: 'organic', name: 'Organic', description: 'Natural, earthy with rounded edges and nature textures' },
  { id: 'classic_barber', name: 'Classic Barber', description: 'Traditional, masculine with navy and copper tones' },
  { id: 'neon_future', name: 'Neon Future', description: 'Futuristic, cyberpunk with neon colors and glow effects' },
  { id: 'executive', name: 'Executive', description: 'Professional, corporate with navy and silver tones' },
];

const FONT_OPTIONS = [
  { id: 'sora', name: 'Sora', style: 'Modern, geometric sans-serif' },
  { id: 'playfair', name: 'Playfair Display', style: 'Elegant, classic serif' },
  { id: 'inter', name: 'Inter', style: 'Clean, versatile sans-serif' },
  { id: 'poppins', name: 'Poppins', style: 'Friendly, rounded sans-serif' },
];

export default function BrandingSettingsPage() {
  const { brand, colors, experience, isLoading, refetch } = useSalonBranding();
  const [isSaving, setIsSaving] = useState(false);
  const [saveSuccess, setSaveSuccess] = useState(false);
  
  const [formData, setFormData] = useState({
    experience_family: brand?.brand?.experience_family || 'luxury_noir',
    primary_color: brand?.brand?.primary_color || '#FF622B',
    secondary_color: brand?.brand?.secondary_color || '#FF8C5A',
    accent_color: brand?.brand?.accent_color || '#FFD700',
    font_heading: brand?.brand?.font_heading || 'sora',
    font_body: brand?.brand?.font_body || 'inter',
    logo: brand?.brand?.logo || '',
    background_image: brand?.brand?.background_image || '',
    custom_domain: brand?.brand?.custom_domain || '',
    white_label_enabled: brand?.brand?.white_label_enabled || false,
  });

  const handleSave = async () => {
    setIsSaving(true);
    setSaveSuccess(false);
    
    try {
      await apiClient.put('/brand-experience', formData);
      setSaveSuccess(true);
      refetch();
      setTimeout(() => setSaveSuccess(false), 3000);
    } catch (error) {
      console.error('Failed to save branding:', error);
    } finally {
      setIsSaving(false);
    }
  };

  const handleReset = () => {
    setFormData({
      experience_family: brand?.brand?.experience_family || 'luxury_noir',
      primary_color: brand?.brand?.primary_color || '#FF622B',
      secondary_color: brand?.brand?.secondary_color || '#FF8C5A',
      accent_color: brand?.brand?.accent_color || '#FFD700',
      font_heading: brand?.brand?.font_heading || 'sora',
      font_body: brand?.brand?.font_body || 'inter',
      logo: brand?.brand?.logo || '',
      background_image: brand?.brand?.background_image || '',
      custom_domain: brand?.brand?.custom_domain || '',
      white_label_enabled: brand?.brand?.white_label_enabled || false,
    });
  };

  if (isLoading) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center h-full">
          <div className="text-text-secondary">Loading branding settings...</div>
        </div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout>
      <div className="max-w-6xl mx-auto p-6">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-text-primary mb-2">Brand Experience</h1>
          <p className="text-text-secondary">
            Customize your salon's visual identity and create a unique experience for your customers.
          </p>
        </div>

        {/* Success Message */}
        {saveSuccess && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            className="mb-6 p-4 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400"
          >
            Branding saved successfully!
          </motion.div>
        )}

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Main Settings */}
          <div className="lg:col-span-2 space-y-6">
            {/* Experience Family */}
            <div className="bg-surface/50 backdrop-blur-xl rounded-2xl p-6 border border-border-light">
              <div className="flex items-center gap-3 mb-4">
                <Sparkles className="w-5 h-5 text-gold" />
                <h2 className="text-xl font-semibold text-text-primary">Experience Family</h2>
              </div>
              <p className="text-text-secondary text-sm mb-4">
                Choose a design language that defines your salon's personality.
              </p>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {EXPERIENCE_FAMILIES.map((family) => (
                  <button
                    key={family.id}
                    onClick={() => setFormData({ ...formData, experience_family: family.id })}
                    className={`p-4 rounded-xl border-2 transition-all text-left ${
                      formData.experience_family === family.id
                        ? 'border-gold bg-gold/10'
                        : 'border-border-light hover:border-border-medium'
                    }`}
                  >
                    <div className="font-medium text-text-primary mb-1">{family.name}</div>
                    <div className="text-sm text-text-secondary">{family.description}</div>
                  </button>
                ))}
              </div>
            </div>

            {/* Colors */}
            <div className="bg-surface/50 backdrop-blur-xl rounded-2xl p-6 border border-border-light">
              <div className="flex items-center gap-3 mb-4">
                <Palette className="w-5 h-5 text-gold" />
                <h2 className="text-xl font-semibold text-text-primary">Brand Colors</h2>
              </div>
              <p className="text-text-secondary text-sm mb-4">
                Define your salon's color palette. These colors will be used throughout your dashboard and customer-facing pages.
              </p>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-text-primary mb-2">Primary Color</label>
                  <div className="flex gap-3">
                    <input
                      type="color"
                      value={formData.primary_color}
                      onChange={(e) => setFormData({ ...formData, primary_color: e.target.value })}
                      className="w-16 h-12 rounded-lg cursor-pointer border-0"
                    />
                    <input
                      type="text"
                      value={formData.primary_color}
                      onChange={(e) => setFormData({ ...formData, primary_color: e.target.value })}
                      className="flex-1 px-4 py-2 rounded-xl bg-background border border-border-light text-text-primary focus:border-gold focus:outline-none"
                      placeholder="#FF622B"
                    />
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium text-text-primary mb-2">Secondary Color</label>
                  <div className="flex gap-3">
                    <input
                      type="color"
                      value={formData.secondary_color}
                      onChange={(e) => setFormData({ ...formData, secondary_color: e.target.value })}
                      className="w-16 h-12 rounded-lg cursor-pointer border-0"
                    />
                    <input
                      type="text"
                      value={formData.secondary_color}
                      onChange={(e) => setFormData({ ...formData, secondary_color: e.target.value })}
                      className="flex-1 px-4 py-2 rounded-xl bg-background border border-border-light text-text-primary focus:border-gold focus:outline-none"
                      placeholder="#FF8C5A"
                    />
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium text-text-primary mb-2">Accent Color</label>
                  <div className="flex gap-3">
                    <input
                      type="color"
                      value={formData.accent_color}
                      onChange={(e) => setFormData({ ...formData, accent_color: e.target.value })}
                      className="w-16 h-12 rounded-lg cursor-pointer border-0"
                    />
                    <input
                      type="text"
                      value={formData.accent_color}
                      onChange={(e) => setFormData({ ...formData, accent_color: e.target.value })}
                      className="flex-1 px-4 py-2 rounded-xl bg-background border border-border-light text-text-primary focus:border-gold focus:outline-none"
                      placeholder="#FFD700"
                    />
                  </div>
                </div>
              </div>
            </div>

            {/* Typography */}
            <div className="bg-surface/50 backdrop-blur-xl rounded-2xl p-6 border border-border-light">
              <div className="flex items-center gap-3 mb-4">
                <Type className="w-5 h-5 text-gold" />
                <h2 className="text-xl font-semibold text-text-primary">Typography</h2>
              </div>
              <p className="text-text-secondary text-sm mb-4">
                Choose fonts that reflect your salon's style.
              </p>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-text-primary mb-2">Heading Font</label>
                  <select
                    value={formData.font_heading}
                    onChange={(e) => setFormData({ ...formData, font_heading: e.target.value })}
                    className="w-full px-4 py-3 rounded-xl bg-background border border-border-light text-text-primary focus:border-gold focus:outline-none"
                  >
                    {FONT_OPTIONS.map((font) => (
                      <option key={font.id} value={font.id}>
                        {font.name} - {font.style}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-text-primary mb-2">Body Font</label>
                  <select
                    value={formData.font_body}
                    onChange={(e) => setFormData({ ...formData, font_body: e.target.value })}
                    className="w-full px-4 py-3 rounded-xl bg-background border border-border-light text-text-primary focus:border-gold focus:outline-none"
                  >
                    {FONT_OPTIONS.map((font) => (
                      <option key={font.id} value={font.id}>
                        {font.name} - {font.style}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
            </div>

            {/* Images */}
            <div className="bg-surface/50 backdrop-blur-xl rounded-2xl p-6 border border-border-light">
              <div className="flex items-center gap-3 mb-4">
                <ImageIcon className="w-5 h-5 text-gold" />
                <h2 className="text-xl font-semibold text-text-primary">Images</h2>
              </div>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-text-primary mb-2">Logo URL</label>
                  <input
                    type="text"
                    value={formData.logo}
                    onChange={(e) => setFormData({ ...formData, logo: e.target.value })}
                    className="w-full px-4 py-3 rounded-xl bg-background border border-border-light text-text-primary focus:border-gold focus:outline-none"
                    placeholder="https://your-salon.com/logo.png"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-text-primary mb-2">Background Image URL</label>
                  <input
                    type="text"
                    value={formData.background_image}
                    onChange={(e) => setFormData({ ...formData, background_image: e.target.value })}
                    className="w-full px-4 py-3 rounded-xl bg-background border border-border-light text-text-primary focus:border-gold focus:outline-none"
                    placeholder="https://your-salon.com/background.jpg"
                  />
                </div>
              </div>
            </div>

            {/* White Label */}
            <div className="bg-surface/50 backdrop-blur-xl rounded-2xl p-6 border border-border-light">
              <div className="flex items-center gap-3 mb-4">
                <Globe className="w-5 h-5 text-gold" />
                <h2 className="text-xl font-semibold text-text-primary">White Label</h2>
              </div>
              <p className="text-text-secondary text-sm mb-4">
                Enable white-label mode to make the platform appear as your own branded software.
              </p>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-text-primary mb-2">Custom Domain</label>
                  <input
                    type="text"
                    value={formData.custom_domain}
                    onChange={(e) => setFormData({ ...formData, custom_domain: e.target.value })}
                    className="w-full px-4 py-3 rounded-xl bg-background border border-border-light text-text-primary focus:border-gold focus:outline-none"
                    placeholder="salon.yourdomain.com"
                  />
                </div>
                <div className="flex items-center gap-3">
                  <input
                    type="checkbox"
                    id="white_label"
                    checked={formData.white_label_enabled}
                    onChange={(e) => setFormData({ ...formData, white_label_enabled: e.target.checked })}
                    className="w-5 h-5 rounded border-border-light"
                  />
                  <label htmlFor="white_label" className="text-sm text-text-primary">
                    Enable white-label mode
                  </label>
                </div>
              </div>
            </div>
          </div>

          {/* Preview Sidebar */}
          <div className="lg:col-span-1">
            <div className="sticky top-6 bg-surface/50 backdrop-blur-xl rounded-2xl p-6 border border-border-light">
              <h3 className="text-lg font-semibold text-text-primary mb-4">Preview</h3>
              
              {/* Color Preview */}
              <div className="space-y-3 mb-6">
                <div className="flex items-center gap-3">
                  <div 
                    className="w-12 h-12 rounded-lg shadow-lg"
                    style={{ backgroundColor: formData.primary_color }}
                  />
                  <div>
                    <div className="text-sm font-medium text-text-primary">Primary</div>
                    <div className="text-xs text-text-secondary">{formData.primary_color}</div>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <div 
                    className="w-12 h-12 rounded-lg shadow-lg"
                    style={{ backgroundColor: formData.secondary_color }}
                  />
                  <div>
                    <div className="text-sm font-medium text-text-primary">Secondary</div>
                    <div className="text-xs text-text-secondary">{formData.secondary_color}</div>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <div 
                    className="w-12 h-12 rounded-lg shadow-lg"
                    style={{ backgroundColor: formData.accent_color }}
                  />
                  <div>
                    <div className="text-sm font-medium text-text-primary">Accent</div>
                    <div className="text-xs text-text-secondary">{formData.accent_color}</div>
                  </div>
                </div>
              </div>

              {/* Experience Preview */}
              <div className="mb-6">
                <div className="text-sm font-medium text-text-primary mb-2">Experience</div>
                <div className="text-sm text-text-secondary">
                  {EXPERIENCE_FAMILIES.find(f => f.id === formData.experience_family)?.name}
                </div>
              </div>

              {/* Font Preview */}
              <div className="mb-6">
                <div className="text-sm font-medium text-text-primary mb-2">Typography</div>
                <div className="text-sm text-text-secondary">
                  Heading: {FONT_OPTIONS.find(f => f.id === formData.font_heading)?.name}
                </div>
                <div className="text-sm text-text-secondary">
                  Body: {FONT_OPTIONS.find(f => f.id === formData.font_body)?.name}
                </div>
              </div>

              {/* Actions */}
              <div className="space-y-3">
                <button
                  onClick={handleSave}
                  disabled={isSaving}
                  className="w-full px-4 py-3 rounded-xl font-medium text-white transition-all flex items-center justify-center gap-2"
                  style={{ 
                    backgroundColor: formData.primary_color,
                    opacity: isSaving ? 0.7 : 1,
                  }}
                >
                  {isSaving ? (
                    <>
                      <RefreshCw className="w-4 h-4 animate-spin" />
                      Saving...
                    </>
                  ) : (
                    <>
                      <Save className="w-4 h-4" />
                      Save Changes
                    </>
                  )}
                </button>
                <button
                  onClick={handleReset}
                  className="w-full px-4 py-3 rounded-xl font-medium text-text-primary border border-border-light hover:border-border-medium transition-all"
                >
                  Reset to Current
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}
