import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { specialistAppointmentsApi } from '@/services/specialist/api';

type AppointmentTab = 'upcoming' | 'completed' | 'cancelled' | 'no-show';

export function useAppointments(activeTab: AppointmentTab) {
  const queryClient = useQueryClient();

  const appointments = useQuery({
    queryKey: ['specialist-appointments', activeTab],
    queryFn: () => {
      const scope = activeTab === 'upcoming' ? 'upcoming' : 'all';
      return specialistAppointmentsApi.getAppointments(scope, activeTab);
    },
  });

  const updateStatus = useMutation({
    mutationFn: ({ bookingId, status }: { bookingId: string; status: string }) =>
      specialistAppointmentsApi.updateBookingStatus(bookingId, status),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-appointments'] });
    },
  });

  return { appointments, updateStatus };
}

export function useTodayBookings() {
  return useQuery({
    queryKey: ['specialist-today-bookings'],
    queryFn: specialistAppointmentsApi.getTodayBookings,
  });
}
