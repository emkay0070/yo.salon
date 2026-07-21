import axios, { AxiosInstance, AxiosError } from 'axios';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || (typeof window !== 'undefined' ? `${window.location.protocol}//${window.location.hostname}:8000/api` : 'http://localhost:8000/api');

class ApiClient {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({
      baseURL: API_BASE_URL,
      headers: {
        'Content-Type': 'application/json',
      },
    });

    // Add request interceptor to include auth token
    this.client.interceptors.request.use(
      (config) => {
        const token = localStorage.getItem('auth_token');
        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
      },
      (error) => Promise.reject(error)
    );

    // Add response interceptor for error handling
    this.client.interceptors.response.use(
      (response) => response,
      (error: AxiosError) => {
        if (error.response?.status === 401) {
          // Handle unauthorized - clear token and redirect to login
          localStorage.removeItem('auth_token');
          window.location.href = '/login';
        }
        return Promise.reject(error);
      }
    );
  }

  // Auth endpoints
  async login(email: string, password: string) {
    const response = await this.client.post('/v1/auth/login', { email, password });
    const { token } = response.data;
    localStorage.setItem('auth_token', token);
    // Return full payload so callers can use next_route, status, etc.
    return response.data;
  }

  async register(data: {
    email: string;
    password: string;
    name: string;
  }) {
    const response = await this.client.post('/v1/auth/register', data);
    const { token } = response.data;
    localStorage.setItem('auth_token', token);
    // Return full payload so callers can use next_route, status, etc.
    return response.data;
  }

  async logout() {
    try {
      await this.client.post('/v1/auth/logout');
    } finally {
      localStorage.removeItem('auth_token');
    }
  }

  async forgotPassword(email: string) {
    const response = await this.client.post('/v1/auth/forgot-password', { email });
    return response.data;
  }

  // Returns { user, status, current_step, next_route }
  async getCurrentUser() {
    const response = await this.client.get('/v1/auth/user');
    return response.data;
  }

  // Salon endpoints
  async getSalons() {
    const response = await this.client.get('/v1/salons');
    return response.data;
  }

  async getSalon(id: string) {
    const response = await this.client.get(`/v1/salons/${id}`);
    return response.data;
  }

  async getSalonBySlug(slug: string) {
    const response = await this.client.get(`/v1/salons/${slug}`);
    return response.data;
  }

  async checkSlug(slug: string) {
    // Returns { available: boolean, slug: string, suggestions?: string[] }
    try {
      const response = await this.client.get('/v1/salons/check-slug', { params: { slug } });
      return response.data;
    } catch (e: any) {
      // Swallow 404/405 during development (route not yet available)
      if (e.response?.status === 404 || e.response?.status === 405) {
        return { available: true, slug };
      }
      throw e;
    }
  }

  async createSalon(data: any) {
    const response = await this.client.post('/v1/salons', data);
    return response.data;
  }

  async updateSalon(id: string, data: any) {
    const response = await this.client.put(`/v1/salons/${id}`, data);
    return response.data;
  }

  async deleteSalon(id: string) {
    await this.client.delete(`/v1/salons/${id}`);
  }

  async getSalonServices(slug: string) {
    const response = await this.client.get(`/v1/salons/${slug}/services`);
    return response.data;
  }

  async getSalonStaff(slug: string) {
    const response = await this.client.get(`/v1/salons/${slug}/staff`);
    return response.data;
  }

  // Customer endpoints
  async getCustomers(params?: Record<string, any>) {
    const response = await this.client.get('/v1/customers', { params });
    return response.data;
  }

  async getCustomer(id: string) {
    const response = await this.client.get(`/v1/customers/${id}`);
    return response.data;
  }

  async createCustomer(data: any | FormData) {
    const config = data instanceof FormData 
      ? { headers: { 'Content-Type': 'multipart/form-data' } } 
      : undefined;
    const response = await this.client.post('/v1/customers', data, config);
    return response.data;
  }

  async updateCustomer(id: string, data: any | FormData) {
    const config = data instanceof FormData 
      ? { headers: { 'Content-Type': 'multipart/form-data' } } 
      : undefined;
    
    if (data instanceof FormData) {
      data.append('_method', 'PUT');
      const response = await this.client.post(`/v1/customers/${id}`, data, config);
      return response.data;
    }

    const response = await this.client.put(`/v1/customers/${id}`, data, config);
    return response.data;
  }

  async deleteCustomer(id: string) {
    await this.client.delete(`/v1/customers/${id}`);
  }

  // Booking endpoints
  async getBookings(params?: Record<string, any>) {
    const response = await this.client.get('/v1/bookings', { params });
    return response.data;
  }

  async getBooking(id: string) {
    const response = await this.client.get(`/v1/bookings/${id}`);
    return response.data;
  }

  async createBooking(data: any) {
    const response = await this.client.post('/v1/bookings', data);
    return response.data;
  }

  async createBookingWithAccount(data: any) {
    const response = await this.client.post('/v1/bookings/with-account', data);
    return response.data;
  }

  async updateBooking(id: string, data: any) {
    const response = await this.client.put(`/v1/bookings/${id}`, data);
    return response.data;
  }

  async deleteBooking(id: string) {
    await this.client.delete(`/v1/bookings/${id}`);
  }

  async getSalonBookings(salonId: string) {
    const response = await this.client.get(`/v1/salons/${salonId}/bookings`);
    return response.data;
  }

  async getCustomerBookings(customerId: string) {
    const response = await this.client.get(`/v1/customers/${customerId}/bookings`);
    return response.data;
  }

  // Service endpoints
  async getServices(params?: Record<string, any>) {
    const response = await this.client.get('/v1/services', { params });
    return response.data;
  }

  async getService(id: string) {
    const response = await this.client.get(`/v1/services/${id}`);
    return response.data;
  }

  async createService(data: any | FormData) {
    const config = data instanceof FormData 
      ? { headers: { 'Content-Type': 'multipart/form-data' } } 
      : undefined;
    const response = await this.client.post('/v1/services', data, config);
    return response.data;
  }

  async updateService(id: string, data: any | FormData) {
    const config = data instanceof FormData 
      ? { headers: { 'Content-Type': 'multipart/form-data' } } 
      : undefined;
    
    // Note: Laravel can be tricky with PUT and multipart/form-data. 
    // It is often safer to send a POST request with a _method=PUT field when uploading files,
    // but we will try standard PUT first, or explicitly send as POST if FormData.
    if (data instanceof FormData) {
      data.append('_method', 'PUT');
      const response = await this.client.post(`/v1/services/${id}`, data, config);
      return response.data;
    }

    const response = await this.client.put(`/v1/services/${id}`, data, config);
    return response.data;
  }

  async deleteService(id: string) {
    await this.client.delete(`/v1/services/${id}`);
  }

  async getSalonServicesById(salonId: string) {
    const response = await this.client.get(`/v1/salons/${salonId}/services`);
    return response.data;
  }

  // Staff endpoints
  async getStaff(params?: Record<string, any>) {
    const response = await this.client.get('/v1/staff', { params });
    return response.data;
  }

  async getStaffMember(id: string) {
    const response = await this.client.get(`/v1/staff/${id}`);
    return response.data;
  }

  async createStaffMember(data: any | FormData) {
    const config = data instanceof FormData 
      ? { headers: { 'Content-Type': 'multipart/form-data' } } 
      : undefined;
    const response = await this.client.post('/v1/staff', data, config);
    return response.data;
  }

  async updateStaffMember(id: string, data: any | FormData) {
    const config = data instanceof FormData 
      ? { headers: { 'Content-Type': 'multipart/form-data' } } 
      : undefined;
      
    if (data instanceof FormData) {
      data.append('_method', 'PUT');
      const response = await this.client.post(`/v1/staff/${id}`, data, config);
      return response.data;
    }

    const response = await this.client.put(`/v1/staff/${id}`, data, config);
    return response.data;
  }

  async deleteStaffMember(id: string) {
    await this.client.delete(`/v1/staff/${id}`);
  }

  async getSalonStaffById(salonId: string) {
    const response = await this.client.get(`/v1/salons/${salonId}/staff`);
    return response.data;
  }

  // Profile endpoints
  async getProfiles() {
    const response = await this.client.get('/v1/profiles');
    return response.data;
  }

  async getProfile(id: string) {
    const response = await this.client.get(`/v1/profiles/${id}`);
    return response.data;
  }

  async createProfile(data: any) {
    const response = await this.client.post('/v1/profiles', data);
    return response.data;
  }

  async updateProfile(id: string, data: any) {
    const response = await this.client.put(`/v1/profiles/${id}`, data);
    return response.data;
  }

  async deleteProfile(id: string) {
    await this.client.delete(`/v1/profiles/${id}`);
  }

  async getSalonProfiles(salonId: string) {
    const response = await this.client.get(`/v1/salons/${salonId}/profiles`);
    return response.data;
  }

  // Payments endpoints
  async getPaymentMethods(params?: Record<string, any>) {
    const response = await this.client.get('/v1/payment-methods', { params });
    return response.data;
  }

  async addPaymentMethod(data: any) {
    const response = await this.client.post('/v1/payment-methods', data);
    return response.data;
  }

  async getTransactions(params?: Record<string, any>) {
    const response = await this.client.get('/v1/transactions', { params });
    return response.data;
  }

  async getTransactionSummary(params?: Record<string, any>) {
    const response = await this.client.get('/v1/transactions/summary', { params });
    return response.data;
  }

  async getSettlements(params?: Record<string, any>) {
    const response = await this.client.get('/v1/settlements', { params });
    return response.data;
  }

  async requestPayment(data: any) {
    const response = await this.client.post('/v1/payment-requests', data);
    return response.data;
  }

  async recordManualTransaction(data: any) {
    const response = await this.client.post('/v1/transactions', data);
    return response.data;
  }

  // Analytics endpoints
  async getAnalytics(params?: Record<string, any>) {
    const response = await this.client.get('/v1/analytics', { params });
    return response.data;
  }

  // Platform Admin endpoints
  async getPlatformStats() {
    const response = await this.client.get('/v1/admin/platform-stats');
    return response.data;
  }

  async getHealthMetrics() {
    const response = await this.client.get('/v1/admin/health-metrics');
    return response.data;
  }

  async getRevenueData() {
    const response = await this.client.get('/v1/admin/revenue-data');
    return response.data;
  }

  async getSupportTickets() {
    const response = await this.client.get('/v1/admin/support-tickets');
    return response.data;
  }

  async getRecentActivity() {
    const response = await this.client.get('/v1/admin/recent-activity');
    return response.data;
  }

  async getSystemAlerts() {
    const response = await this.client.get('/v1/admin/system-alerts');
    return response.data;
  }

  // Membership endpoints
  async getMembership() {
    const response = await this.client.get('/v1/membership');
    return response.data;
  }

  async getMembershipPlans() {
    const response = await this.client.get('/v1/membership/plans');
    return response.data;
  }

  async getMembershipPlan(id: string) {
    const response = await this.client.get(`/v1/membership/plans/${id}`);
    return response.data;
  }

  async getMembershipUsage() {
    const response = await this.client.get('/v1/membership/usage');
    return response.data;
  }

  async getMembershipInvoices() {
    const response = await this.client.get('/v1/membership/invoices');
    return response.data;
  }

  async getMembershipInvoice(id: string) {
    const response = await this.client.get(`/v1/membership/invoices/${id}`);
    return response.data;
  }

  async getMembershipTimeline() {
    const response = await this.client.get('/v1/membership/timeline');
    return response.data;
  }

  async changeMembershipPlan(planId: string) {
    const response = await this.client.post('/v1/membership/change-plan', { plan_id: planId });
    return response.data;
  }

  async cancelMembership(reason?: string) {
    const response = await this.client.post('/v1/membership/cancel', { reason });
    return response.data;
  }

  async resumeMembership() {
    const response = await this.client.post('/v1/membership/resume');
    return response.data;
  }

  async startMembershipTrial(planId: string) {
    const response = await this.client.post('/v1/membership/start-trial', { plan_id: planId });
    return response.data;
  }

  // Portal account endpoints
  async createPortalAccount(data: { email: string; password: string; phone: string; salon_id: string; name?: string }) {
    const response = await this.client.post('/v1/portal/create', data);
    return response.data;
  }

  async portalLogin(data: { email: string; password: string }) {
    const response = await this.client.post('/v1/portal/login', data);
    return response.data;
  }

  async sendPortalInvitation(customerId: string) {
    const response = await this.client.post('/v1/portal/send-invitation', { customer_id: customerId });
    return response.data;
  }

  async acceptPortalInvitation(data: { invitation_token: string; email: string; password: string }) {
    const response = await this.client.post('/v1/portal/accept-invitation', data);
    return response.data;
  }

  // Onboarding endpoints
  async getOnboardingSession() {
    const response = await this.client.get('/v1/onboarding');
    return response.data;
  }

  async saveOnboardingDraft(step: string, data: any) {
    const response = await this.client.post('/v1/onboarding/draft', { step, data });
    return response.data;
  }

  async completeOnboarding() {
    const response = await this.client.post('/v1/onboarding/complete');
    return response.data;
  }

  // Generic GET/POST methods for flexibility
  async get(url: string, params?: Record<string, any>) {
    const response = await this.client.get(url, { params });
    return response.data;
  }

  async post(url: string, data?: any) {
    const response = await this.client.post(url, data);
    return response.data;
  }

  async put(url: string, data?: any) {
    const response = await this.client.put(url, data);
    return response.data;
  }

  async delete(url: string) {
    const response = await this.client.delete(url);
    return response.data;
  }
}

export const apiClient = new ApiClient();
