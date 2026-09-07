import { apiClient } from '@/lib/api-client';

export const specialistCalendarApi = {
  getWeekCalendar: async (weekStart: string) => {
    return await apiClient.get(`/v1/specialist-portal/calendar?week_start=${weekStart}`);
  },
};
