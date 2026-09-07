import { useQuery } from '@tanstack/react-query';
import { specialistCalendarApi } from '@/services/specialist/api';

export function useCalendar(weekStart: string) {
  return useQuery({
    queryKey: ['specialist-calendar', weekStart],
    queryFn: () => specialistCalendarApi.getWeekCalendar(weekStart),
  });
}
