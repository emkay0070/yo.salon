'use client';

import { motion } from 'framer-motion';
import { Settings, Bell, Palette, Shield, Lock, Globe, HelpCircle, Info, ChevronRight } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { usePortalBrand } from '@/contexts/PortalBrandContext';

export default function SettingsPage() {
  const { customer, salon } = usePortalAuth();
  const { brand } = usePortalBrand();

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <h1 className="text-3xl font-bold text-text-primary mb-2">Settings</h1>
        <p className="text-text-secondary">App preferences and account settings</p>
      </motion.div>

      {/* Settings Sections */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.1 }}
        className="space-y-4"
      >
        <SettingsSection
          title="Notifications"
          items={[
            { icon: Bell, label: 'Push Notifications', description: 'Manage push notification preferences' },
            { icon: Bell, label: 'Email Notifications', description: 'Manage email notification preferences' },
            { icon: Bell, label: 'SMS Notifications', description: 'Manage SMS notification preferences' },
          ]}
        />
        <SettingsSection
          title="Appearance"
          items={[
            { icon: Palette, label: 'Theme', description: 'Light, Dark, or System' },
            { icon: Palette, label: 'Accent Color', description: 'Choose your preferred accent color' },
          ]}
        />
        <SettingsSection
          title="Security"
          items={[
            { icon: Lock, label: 'Change Password', description: 'Update your password' },
            { icon: Shield, label: 'Two-Factor Authentication', description: 'Add extra security to your account' },
          ]}
        />
        <SettingsSection
          title="Language & Region"
          items={[
            { icon: Globe, label: 'Language', description: 'English' },
            { icon: Globe, label: 'Currency', description: 'UGX - Ugandan Shilling' },
          ]}
        />
        <SettingsSection
          title="Support"
          items={[
            { icon: HelpCircle, label: 'Help Center', description: 'Get help with common issues' },
            { icon: Info, label: 'About', description: 'App version and information' },
          ]}
        />
      </motion.div>
    </div>
  );
}

function SettingsSection({ title, items }: { title: string, items: { icon: any, label: string, description: string }[] }) {
  return (
    <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
      <h3 className="text-lg font-semibold text-text-primary mb-4">{title}</h3>
      <div className="space-y-3">
        {items.map((item, index) => (
          <div key={index} className="flex items-center justify-between p-4 bg-surface border border-border-light rounded-xl hover:border-gold/30 transition-colors cursor-pointer">
            <div className="flex items-center gap-4">
              <div className="w-10 h-10 rounded-xl flex items-center justify-center" style={{ backgroundColor: 'var(--brand-primary, #FFD700)20' }}>
                <item.icon className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
              </div>
              <div>
                <p className="font-medium text-text-primary">{item.label}</p>
                <p className="text-sm text-text-secondary">{item.description}</p>
              </div>
            </div>
            <ChevronRight className="w-5 h-5 text-text-secondary" />
          </div>
        ))}
      </div>
    </div>
  );
}
