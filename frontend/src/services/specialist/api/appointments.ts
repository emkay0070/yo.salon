import { apiClient } from '@/lib/api-client';

export const specialistAppointmentsApi = {
  getAppointments: async (scope: string, status: string) => {
    const json = await apiClient.get(`/v1/specialist-portal/bookings?scope=${scope}&status=${status}`);
    return json.bookings;
  },

  updateBookingStatus: async (bookingId: string, status: string) => {
    return await apiClient.put(`/v1/specialist-portal/bookings/${bookingId}/status`, { status });
  },

  getTodayBookings: async () => {
    const data = await apiClient.get('/v1/specialist-portal/bookings?scope=today');
    return data.bookings
      .filter((b: any) => b.date)
      .map((b: any) => ({
        id: b.id,
        time: b.time,
        customer: b.customer?.name || 'Walk-in',
        service: b.service?.name || 'Service',
        status: b.status === 'pending_payment' ? 'upcoming' : (b.status === 'confirmed' ? 'upcoming' : b.status)
      }));
  },
};
