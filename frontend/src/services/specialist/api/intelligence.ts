import { apiClient } from '@/lib/api-client';

export const specialistIntelligenceApi = {
  getInsights: async () => {
    // TODO: Replace with real API call to intelligence endpoint
    // return await apiClient.get('/v1/specialist-portal/intelligence');
    
    // DEMO DATA - Remove when real intelligence API is implemented
    return {
      followUps: [
        { id: 1, customer: 'Sarah Johnson', lastBooking: '2026-07-15', daysOverdue: 7, suggestedService: 'Beard Sculpt' },
        { id: 2, customer: 'Michael Smith', lastBooking: '2026-07-10', daysOverdue: 12, suggestedService: 'Executive Haircut' },
      ],
      trends: [
        { id: 1, trend: 'Customers with dry hair increase during July', recommendation: 'Recommend Treatment Y', impact: 'high' },
        { id: 2, trend: 'Beard services trending up', recommendation: 'Promote Beard Sculpt', impact: 'medium' },
      ],
      opportunities: [
        { id: 1, customer: 'James Wilson', likelihood: 85, product: 'Premium Pomade', reason: 'Frequently buys styling products' },
        { id: 2, customer: 'Alex Brown', likelihood: 72, product: 'Beard Oil', reason: 'Beard profile changed recently' },
      ],
      strengths: [
        { id: 1, service: 'Skin Fade', rating: 95, bookings: 45 },
        { id: 2, service: 'Beard Sculpt', rating: 88, bookings: 32 },
      ],
      atRisk: [
        { id: 1, customer: 'David Kim', lastBooking: '2026-06-20', risk: 'high', reason: 'Haven\'t returned in 45 days' },
        { id: 2, customer: 'Emma Davis', lastBooking: '2026-06-25', risk: 'medium', reason: 'Haven\'t returned in 40 days' },
      ],
    };
  },
};
