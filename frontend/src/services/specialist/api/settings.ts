import { apiClient } from '@/lib/api-client';

export const specialistSettingsApi = {
  getSettings: async () => {
    // TODO: Implement when backend settings endpoint is available
    // return await apiClient.get('/v1/specialist-portal/settings');
    return {
      notifications_enabled: true,
      email_notifications: true,
      sms_notifications: false,
      appearance: 'dark',
    };
  },

  updateSettings: async (settings: any) => {
    // TODO: Implement when backend settings endpoint is available
    // return await apiClient.put('/v1/specialist-portal/settings', settings);
    return settings;
  },
};
