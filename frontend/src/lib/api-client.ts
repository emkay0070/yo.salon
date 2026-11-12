import axios, { AxiosInstance, AxiosError, InternalAxiosRequestConfig } from 'axios';

// Extend Axios config to include custom location context option
interface CustomAxiosRequestConfig extends InternalAxiosRequestConfig {
  includeLocationContext?: boolean;
}

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || '/backend/api';

const UUID_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const INVALID_ID_SEGMENTS = new Set(['null', 'undefined', '0', 'NaN', '']);

function isValidIdSegment(segment: string): boolean {
  if (!segment) return false;
  if (INVALID_ID_SEGMENTS.has(segment.toLowerCase())) return false;
  // If it's exactly 36 chars, check if it's a UUID
  if (segment.length === 36 && UUID_REGEX.test(segment)) return true;
  // Otherwise allow digits (IDs), UUIDs, and long alphanumeric tokens (invitations)
  return /^\d+$/.test(segment) || UUID_REGEX.test(segment) || /^[A-Za-z0-9\-_]+$/.test(segment);
}

function validateUrlPathSegments(url: string | undefined): { ok: true } | { ok: false; reason: string } {
  if (!url) return { ok: true };
  const pathOnly = url.split('?')[0];
  const segments = pathOnly.split('/').filter(Boolean);
  for (let i = 0; i < segments.length - 1; i++) {
    const current = segments[i];
    const next = segments[i + 1];
    const pluralResource = /^(salons|staff|customers|bookings|services|profiles|specialists|invitations|plans|invoices|schedules|schedule-exceptions|add-ons|methods|website-configurations|media)$/i;
    const singularOrSpecific = /^(schedules|health|booking-config|availability|specialists|staff|services|bookings|profiles|check-slug|create|upload|upload-base64)$/i;
    const looksLikeIdPosition =
      (pluralResource.test(current) && !singularOrSpecific.test(next)) ||
      (current === 'v1' && pluralResource.test(segments[i + 1] ?? '') && segments[i + 2] && !singularOrSpecific.test(segments[i + 2]));
    if (pluralResource.test(current) && next && !singularOrSpecific.test(next)) {
      if (!isValidIdSegment(next)) {
        return { ok: false, reason: `Invalid resource ID '${next}' in URL path '${url}'` };
      }
    }
  }
  return { ok: true };
}

class ApiClient {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({
      baseURL: API_BASE_URL,
    });

    this.client.interceptors.request.use(
      (config: CustomAxiosRequestConfig) => {
        let token = localStorage.getItem('auth_token');
        const specialistToken = localStorage.getItem('specialist_auth_token');
        
        // If the request explicitly targets the specialist portal, use the specialist token instead
        if (config.url?.includes('/specialist-portal/')) {
          token = specialistToken || token;
        } else if (!token && specialistToken) {
          // Fallback to specialist token if no main auth token exists (e.g., for media uploads during onboarding)
          token = specialistToken;
        }

        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
        }

        // Add location context header only when explicitly requested
        // X-Location-Id is a context signal, not an authorization mechanism
        // Authorization is determined by the user's role and backend logic
        // Location persistence is UI convenience, not automatic request context
        if (config.includeLocationContext) {
          const locationId = localStorage.getItem('location_id');
          if (locationId) {
            config.headers['X-Location-Id'] = locationId;
            console.log('[ApiClient] Adding X-Location-Id header (explicit opt-in):', locationId);
          } else {
            console.log('[ApiClient] includeLocationContext=true but no location_id in localStorage');
          }
        }
        
        // Don't set Content-Type for FormData - let axios set it with boundary
        if (config.data instanceof FormData) {
          delete config.headers['Content-Type'];
          console.log('[ApiClient] FormData detected for:', config.url);
          console.log('[ApiClient] FormData entries:', Array.from((config.data as FormData).entries()));
        }
        
        const check = validateUrlPathSegments(config.url);
        if (!check.ok) {
          const err = new Error(check.reason);
          (err as any).code = 'ERR_INVALID_RESOURCE_ID';
          console.error('[ApiClient] Blocked request:', check.reason);
          return Promise.reject(err);
        }
        return config;
      },
      (error) => Promise.reject(error)
    );

    this.client.interceptors.response.use(
      (response) => response,
      (error: AxiosError) => {
        if (error.response?.status === 401) {
          if (typeof window !== 'undefined') {
            // Only redirect if the failed request was explicitly for the specialist portal
            if (error.config?.url?.includes('/specialist-portal/')) {
              localStorage.removeItem('specialist_auth_token');
              if (!window.location.pathname.startsWith('/specialist-portal/login')) {
                window.location.href = '/specialist-portal/login';
              }
            } else {
              // Otherwise, it was a staff/admin request that failed
              localStorage.removeItem('auth_token');
              // Don't interrupt if they are browsing the customer portal or specialist portal
              if (!window.location.pathname.startsWith('/login') && !window.location.pathname.startsWith('/portal') && !window.location.pathname.startsWith('/specialist-portal')) {
                window.location.href = '/login';
              }
            }
          }
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
    journey?: 'customer' | 'specialist' | 'salon';
  }) {
    const response = await this.client.post('/v1/auth/register', data);
    const { token } = response.data;

    if (data.journey === 'specialist') {
      localStorage.setItem('specialist_auth_token', token);
    } else if (data.journey === 'customer') {
      localStorage.setItem('portal_auth_token', token);
    } else {
      localStorage.setItem('auth_token', token);
    }

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

  // Business categories (public - for onboarding)
  async getBusinessCategories(providerType?: string) {
    const params = providerType ? { provider_type: providerType } : {};
    const response = await this.client.get('/v1/business-categories', { params });
    return response.data;
  }

  // Returns { user, status, current_step, next_route }
  async getCurrentUser() {
    const response = await this.client.get('/v1/auth/user');
    return response.data;
  }

  // Provider endpoints
  async getProviders() {
    const response = await this.client.get('/v1/providers');
    return response.data;
  }

  async getProvider(id: string) {
    const response = await this.client.get(`/v1/providers/${id}`);
    return response.data;
  }

  async getProviderBySlug(slug: string) {
    const response = await this.client.get(`/v1/providers/${slug}`);
    return response.data;
  }

  async checkSlug(slug: string) {
    // Returns { available: boolean, slug: string, suggestions?: string[] }
    try {
      const response = await this.client.get('/v1/slug-availability', { params: { slug } });
      return response.data;
    } catch (e: any) {
      // Swallow 404/405 during development (route not yet available)
      if (e.response?.status === 404 || e.response?.status === 405) {
        return { available: true, slug };
      }
      throw e;
    }
  }

  async createProvider(data: any) {
    const response = await this.client.post('/v1/providers', data);
    return response.data;
  }

  async updateProvider(id: string, data: any) {
    const response = await this.client.put(`/v1/providers/${id}`, data);
    return response.data;
  }

  async deleteProvider(id: string) {
    await this.client.delete(`/v1/providers/${id}`);
  }

  async getProviderServices(slug: string) {
    const response = await this.client.get(`/v1/providers/${slug}/services`);
    // The show endpoint returns the full provider object with services nested
    return response.data?.services || response.data || [];
  }

  async getProviderStaff(slug: string) {
    const response = await this.client.get(`/v1/providers/${slug}/staff`);
    // The show endpoint returns the full provider object with specialists nested
    return response.data?.specialists || response.data || [];
  }

  // Customer endpoints
  async getCustomers(params?: Record<string, any>) {
    // Customers endpoint uses explicit query parameters for filtering
    // No X-Location-Id header needed - controller validates location_id from query params
    const config: CustomAxiosRequestConfig = { includeLocationContext: false } as any;
    const response = await this.client.get('/v1/customers', { params, ...config });
    // Response structure: { customers: [], metadata: {} }
    // For backward compatibility, return customers array directly if metadata not present
    return response.data?.customers || response.data;
  }

  async getCustomer(id: string) {
    const config: CustomAxiosRequestConfig = { includeLocationContext: true } as any;
    const response = await this.client.get(`/v1/customers/${id}`, config);
    return response.data;
  }

  async createCustomer(data: any) {
    const config: CustomAxiosRequestConfig = { includeLocationContext: true } as any;
    const response = await this.client.post('/v1/customers', data, config);
    return response.data;
  }

  async updateCustomer(id: string, data: any) {
    const config: CustomAxiosRequestConfig = { includeLocationContext: true } as any;
    const response = await this.client.put(`/v1/customers/${id}`, data, config);
    return response.data;
  }

  async deleteCustomer(id: string) {
    const config: CustomAxiosRequestConfig = { includeLocationContext: true } as any;
    await this.client.delete(`/v1/customers/${id}`, config);
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

  async resolveBooking(id: string, data: any) {
    const response = await this.client.post(`/v1/bookings/${id}/resolve`, data);
    return response.data;
  }

  async getBookingsNeedingResolution(params?: Record<string, any>) {
    const response = await this.client.get('/v1/bookings/needs-resolution', { params });
    return response.data;
  }

  async getProviderBookings(providerId: string) {
    const response = await this.client.get(`/v1/providers/${providerId}/bookings`);
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

  async getProviderServicesById(providerId: string) {
    const response = await this.client.get(`/v1/providers/${providerId}/services`);
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

  async createStaffMember(data: any) {
    const response = await this.client.post('/v1/staff', data);
    return response.data;
  }

  async updateStaffMember(id: string, data: any) {
    const response = await this.client.put(`/v1/staff/${id}`, data);
    return response.data;
  }

  async deleteStaffMember(id: string) {
    await this.client.delete(`/v1/staff/${id}`);
  }

  async getProviderStaffById(providerId: string) {
    const response = await this.client.get(`/v1/staff/by-provider/${providerId}`);
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

  async updateMe(data: any) {
    const response = await this.client.patch('/v1/me', data);
    return response.data;
  }

  async getMe() {
    const response = await this.client.get('/v1/me');
    return response.data;
  }

  async uploadProfilePhoto(file: File) {
    const formData = new FormData();
    formData.append('file', file);
    const response = await this.client.post('/v1/me/profile-photo', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  }

  async removeProfilePhoto() {
    const response = await this.client.delete('/v1/me/profile-photo');
    return response.data;
  }

  async uploadProviderLogo(providerId: string, file: File) {
    const formData = new FormData();
    formData.append('file', file);
    const response = await this.client.post(`/v1/providers/${providerId}/logo`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  }

  async removeProviderLogo(providerId: string) {
    const response = await this.client.delete(`/v1/providers/${providerId}/logo`);
    return response.data;
  }

  async uploadProviderCoverImage(providerId: string, file: File) {
    const formData = new FormData();
    formData.append('cover_image', file);
    const response = await this.client.post(`/v1/providers/${providerId}/update`, formData);
    return response.data;
  }

  async uploadBrandBackgroundImage(file: File) {
    const formData = new FormData();
    formData.append('background_image', file);
    const response = await this.client.put('/v1/brand-experience', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  }

  async getGallery() {
    const response = await this.client.get('/v1/gallery');
    return response.data;
  }

  async uploadGalleryImage(file: File, altText?: string, caption?: string) {
    const formData = new FormData();
    formData.append('file', file);
    if (altText) formData.append('alt_text', altText);
    if (caption) formData.append('caption', caption);
    const response = await this.client.post('/v1/gallery', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  }

  async deleteGalleryImage(id: string) {
    const response = await this.client.delete(`/v1/gallery/${id}`);
    return response.data;
  }

  async deleteProfile(id: string) {
    await this.client.delete(`/v1/profiles/${id}`);
  }

  async getProviderProfiles(providerId: string) {
    const response = await this.client.get(`/v1/providers/${providerId}/profiles`);
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

  async verifySalonPayment(reference: string) {
    const response = await this.client.post('/v1/payments/salon/verify', { reference });
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

  async getIntelligence() {
    const response = await this.client.get('/v1/analytics/intelligence');
    return response.data;
  }

  async getLocationHealth(locationId: string) {
    const response = await this.client.get(`/v1/locations/${locationId}/health`);
    return response.data;
  }

  async copilotChat(message: string, context?: any) {
    const response = await this.client.post('/v1/analytics/copilot-chat', { message, context });
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

  async getMembershipPlans(providerType: string = 'provider') {
    const response = await this.client.get('/v1/membership/plans', {
      params: { provider_type: providerType }
    });
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
  async createPortalAccount(data: { email: string; password: string; phone: string; provider_id: string; name?: string }) {
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

  // Notification preferences endpoints
  async getNotificationPreferences() {
    const response = await this.client.get('/v1/notification-preferences');
    return response.data;
  }

  async updateNotificationPreferences(preferences: any) {
    const response = await this.client.put('/v1/notification-preferences', preferences);
    return response.data;
  }

  // Add-ons endpoints
  async getAddOns() {
    const response = await this.client.get('/v1/add-ons');
    return response.data;
  }

  async purchaseAddOn(data: { product_code: string, payment_method_id: string }) {
    const response = await this.client.post('/v1/add-ons/purchase', data);
    return response.data;
  }

  // Booking config endpoints
  async getBookingConfig(providerId: string) {
    const response = await this.client.get(`/v1/providers/${providerId}/booking-config`);
    return response.data;
  }

  async updateBookingConfig(providerId: string, config: any) {
    const response = await this.client.put(`/v1/providers/${providerId}/booking-config`, config);
    return response.data;
  }

  // Provider schedules endpoints
  async getProviderSchedules(providerId: string) {
    const response = await this.client.get(`/v1/providers/${providerId}/schedules`);
    return response.data;
  }

  async updateProviderSchedules(providerId: string, scheduleMode: string, schedules?: any) {
    const response = await this.client.put(`/v1/providers/${providerId}/schedules`, { schedule_mode: scheduleMode, schedules });
    return response.data;
  }

  async publishProviderSchedules(providerId: string) {
    const response = await this.client.post(`/v1/providers/${providerId}/schedules/publish`);
    return response.data;
  }

  // Schedule exceptions endpoints (location-scoped)
  async getScheduleExceptions(locationId: string, scope?: string, assignmentId?: string) {
    const params: any = {};
    if (scope) params.scope = scope;
    if (assignmentId) params.assignment_id = assignmentId;
    const response = await this.client.get(`/v1/locations/${locationId}/schedule-exceptions`, { params });
    return response.data;
  }

  async createScheduleException(locationId: string, payload: any) {
    const response = await this.client.post(`/v1/locations/${locationId}/schedule-exceptions`, payload);
    return response.data;
  }

  async updateScheduleException(locationId: string, id: string, data: any) {
    const response = await this.client.put(`/v1/locations/${locationId}/schedule-exceptions/${id}`, data);
    return response.data;
  }

  async deleteScheduleException(locationId: string, id: string) {
    await this.client.delete(`/v1/locations/${locationId}/schedule-exceptions/${id}`);
  }

  // Assignment schedule endpoints (location-scoped)
  async getAssignmentSchedule(locationId: string, specialistId: string) {
    const response = await this.client.get(`/v1/locations/${locationId}/specialists/${specialistId}/schedule`);
    return response.data;
  }

  async updateAssignmentSchedule(locationId: string, specialistId: string, scheduleMode: string, schedules?: any) {
    const response = await this.client.put(`/v1/locations/${locationId}/specialists/${specialistId}/schedule`, { schedule_mode: scheduleMode, schedules });
    return response.data;
  }

  // Provider specialists endpoints
  async getProviderById(providerId: string) {
    const response = await this.client.get(`/v1/providers/id/${providerId}`);
    return response.data;
  }

  async getProviderSpecialists(providerId: string): Promise<any> {
    // Use the authenticated route for providerId (UUID) - requires auth:sanctum
    try {
      const response = await this.client.get(`/v1/providers/${providerId}/specialists`);
      // The show endpoint returns the full provider object with specialists nested
      return response.data?.specialists || response.data || [];
    } catch (error: any) {
      console.warn('[getProviderSpecialists] UUID endpoint failed, this may be expected if providerId is invalid or endpoint requires provider context');
      throw error;
    }
  }

  async getProviderSpecialistsBySlug(slug: string): Promise<any> {
    // Use the public route for slug - no auth required
    const response = await this.client.get(`/v1/providers/${slug}/specialists`);
    // The show endpoint returns the full provider object with specialists nested
    return response.data?.specialists || response.data || [];
  }

  async getAssignableSpecialists(providerIdOrSlug: string, locationId?: string): Promise<any> {
    // Get operationally assignable specialists (requires active SpecialistAssignment)
    // This endpoint filters out dangling specialists without active assignments
    const params: any = {};
    if (locationId) {
      params.location_id = locationId;
    }
    const response = await this.client.get(`/v1/providers/${providerIdOrSlug}/assignable-specialists`, { params });
    return response.data || [];
  }

  async getProviderSpecialistsWithFallback(providerId?: string, slug?: string | null | undefined): Promise<any> {
    // Convert null to undefined for consistency
    const slugValue = slug === null ? undefined : slug;
    
    // Try UUID endpoint first if providerId is available (authenticated, more data)
    if (providerId) {
      try {
        return await this.getProviderSpecialists(providerId);
      } catch (error: any) {
        console.warn('[getProviderSpecialistsWithFallback] UUID endpoint failed, falling back to slug:', error.message);
        // Continue to slug fallback
      }
    }
    
    // Fallback to slug endpoint if available
    if (slugValue) {
      try {
        return await this.getProviderSpecialistsBySlug(slugValue);
      } catch (error: any) {
        console.error('[getProviderSpecialistsWithFallback] Slug endpoint also failed:', error.message);
        throw error;
      }
    }
    
    throw new Error('Either providerId or slug must be provided');
  }

  async updateProviderSpecialist(providerId: string, id: string, data: any) {
    const response = await this.client.put(`/v1/providers/${providerId}/specialists/${id}`, data);
    return response.data;
  }

  async removeProviderSpecialist(providerId: string, id: string) {
    await this.client.delete(`/v1/providers/${providerId}/specialists/${id}`);
  }

  // Invitation endpoints
  async createInvitation(data: { role: 'customer' | 'staff' | 'specialist' | 'manager' | 'receptionist', email?: string, target_id?: string }) {
    const response = await this.client.post('/v1/invitations', data);
    return response.data;
  }

  async getInvitation(token: string) {
    const response = await this.client.get(`/v1/invitations/${token}`);
    return response.data;
  }

  async acceptInvitation(token: string, data: { name: string; email: string; password: string }) {
    const response = await this.client.post(`/v1/invitations/${token}/accept`, data);
    return response.data;
  }

  // Customer lookup
  async lookupCustomer(phone: string, providerId?: string) {
    const params: any = { phone };
    if (providerId) params.provider_id = providerId;
    const response = await this.client.get('/v1/customers/lookup', { params });
    return response.data;
  }

  // Find customer details for booking prefill (booking-safe data only)
  async findCustomerDetails(phone: string, providerId?: string, locationId?: string) {
    const params: any = { phone };
    if (providerId) params.provider_id = providerId;
    if (locationId) params.location_id = locationId;
    const response = await this.client.post('/v1/customers/find-details', params);
    return response.data;
  }

  // Platform payments
  async getPendingInvoices() {
    const response = await this.client.get('/v1/payments/pending-invoices');
    return response.data;
  }

  async updatePlatformPaymentMethod(id: string, data: any) {
    const response = await this.client.put(`/v1/payments/methods/${id}`, data);
    return response.data;
  }

  // Pulse data
  async getPulseData(params?: Record<string, any>) {
    const response = await this.client.get('/v1/pulse', { params });
    return response.data;
  }

  // ── Floor operational endpoints ─────────────────────────────────────────────
  // These map to FloorController which delegates to BookingService / SessionService.

  /** GET /floor/snapshot — confirmed + arrived + waiting + in_progress for today */
  async getFloorSnapshot() {
    const config: CustomAxiosRequestConfig = { includeLocationContext: true } as any;
    const response = await this.client.get('/v1/floor/snapshot', config);
    return response.data;
  }

  /** POST /bookings/{id}/arrive — confirmed → arrived */
  async bookingArrive(bookingId: string) {
    const config: CustomAxiosRequestConfig = { includeLocationContext: true } as any;
    const response = await this.client.post(`/v1/bookings/${bookingId}/arrive`, {}, config);
    return response.data;
  }

  /** POST /bookings/{id}/wait — arrived → waiting */
  async bookingWait(bookingId: string) {
    const config: CustomAxiosRequestConfig = { includeLocationContext: true } as any;
    const response = await this.client.post(`/v1/bookings/${bookingId}/wait`, {}, config);
    return response.data;
  }

  /** POST /bookings/{id}/services/{bookingServiceId}/start — start service session */
  async floorStartSession(bookingId: string, bookingServiceId: string) {
    const config: CustomAxiosRequestConfig = { includeLocationContext: true } as any;
    const response = await this.client.post(
      `/v1/bookings/${bookingId}/services/${bookingServiceId}/start`,
      {},
      config
    );
    return response.data;
  }

  /** POST /bookings/{id}/services/{bookingServiceId}/complete — complete service session */
  async floorCompleteSession(bookingId: string, bookingServiceId: string) {
    const config: CustomAxiosRequestConfig = { includeLocationContext: true } as any;
    const response = await this.client.post(
      `/v1/bookings/${bookingId}/services/${bookingServiceId}/complete`,
      {},
      config
    );
    return response.data;
  }

  // Provider availability
  async getProviderAvailability(providerId: string, params?: Record<string, any>) {
    const response = await this.client.get(`/providers/${providerId}/availability`, { params });
    return response.data;
  }

  // Payment instructions
  async getPaymentInstructions(bookingId: string) {
    const response = await this.client.get(`/v1/bookings/${bookingId}/payment-instructions`);
    return response.data;
  }

  async getProviderServicePaymentInstructions(providerId: string, serviceId: string) {
    const response = await this.client.get('/v1/booking-context/payment', {
      params: { provider_id: providerId, service_id: serviceId }
    });
    return response.data;
  }

  // Platform payment methods
  async getPlatformPaymentMethods() {
    const response = await this.client.get('/v1/payments/platform-methods');
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

  async put(url: string, data?: any, params?: Record<string, any>) {
    const response = await this.client.put(url, data, { params });
    return response.data;
  }

  async delete(url: string) {
    const response = await this.client.delete(url);
    return response.data;
  }

  // Media endpoints
  async uploadMedia(file: File, options?: { directory?: string; alt_text?: string }) {
    const formData = new FormData();
    formData.append('file', file);
    if (options?.directory) formData.append('directory', options.directory);
    if (options?.alt_text) formData.append('alt_text', options.alt_text);
    
    const config = { headers: { 'Content-Type': 'multipart/form-data' } };
    const response = await this.client.post('/v1/media/upload', formData, config);
    return response.data;
  }

  async uploadMediaBase64(base64Data: string, options?: { directory?: string; alt_text?: string }) {
    const response = await this.client.post('/v1/media/upload-base64', {
      data: base64Data,
      ...options,
    });
    return response.data;
  }

  async getMedia(mediaId: string) {
    const response = await this.client.get(`/v1/media/${mediaId}`);
    return response.data;
  }

  async deleteMedia(mediaId: string) {
    await this.client.delete(`/v1/media/${mediaId}`);
  }

  // Capability endpoints
  async getCapabilities(providerId: string) {
    const response = await this.client.get(`/v1/providers/${providerId}/capabilities`);
    return response.data;
  }

  async checkFeature(providerId: string, featureCode: string) {
    const response = await this.client.get(`/v1/providers/${providerId}/capabilities/feature/${featureCode}`);
    return response.data;
  }

  async getQuota(providerId: string, resourceCode: string) {
    const response = await this.client.get(`/v1/providers/${providerId}/capabilities/quota/${resourceCode}`);
    return response.data;
  }

  async getCredits(providerId: string, creditCode: string) {
    const response = await this.client.get(`/v1/providers/${providerId}/capabilities/credits/${creditCode}`);
    return response.data;
  }

  // Specialist capabilities endpoint
  async getSpecialistCapabilities() {
    const response = await this.client.get('/v1/specialist-portal/capabilities');
    return response.data;
  }

  // Compensation Management Endpoints
  async getCompensationPolicies(providerId: string) {
    const response = await this.client.get(`/v1/providers/${providerId}/compensation/policies`);
    return response.data;
  }

  async createCompensationPolicy(providerId: string, data: any) {
    const response = await this.client.post(`/v1/providers/${providerId}/compensation/policies`, data);
    return response.data;
  }

  async getCompensationEarnings(providerId: string, params?: any) {
    const response = await this.client.get(`/v1/providers/${providerId}/compensation/earnings`, { params });
    return response.data;
  }

  async getCompensationPeriods(providerId: string, params?: any) {
    const response = await this.client.get(`/v1/providers/${providerId}/compensation/periods`, { params });
    return response.data;
  }

  async getCompensationPeriod(providerId: string, periodId: string) {
    const response = await this.client.get(`/v1/providers/${providerId}/compensation/periods/${periodId}`);
    return response.data;
  }

  async closeCompensationPeriod(providerId: string, periodId: string) {
    const response = await this.client.post(`/v1/providers/${providerId}/compensation/periods/${periodId}/close`);
    return response.data;
  }

  async addCompensationAdjustment(providerId: string, periodId: string, data: any) {
    const response = await this.client.post(`/v1/providers/${providerId}/compensation/periods/${periodId}/adjustments`, data);
    return response.data;
  }

  async getCompensationPayables(providerId: string) {
    const response = await this.client.get(`/v1/providers/${providerId}/compensation/payables`);
    return response.data;
  }

  async initiateCompensationPayout(providerId: string, settlementId: string, data: any) {
    const response = await this.client.post(`/v1/providers/${providerId}/compensation/settlements/${settlementId}/payout`, data);
    return response.data;
  }

  // Payment Profiles (Specialist Portal)
  async getSpecialistPaymentProfiles() {
    const response = await this.client.get('/v1/specialist-portal/payment-profiles');
    return response.data;
  }

  async createSpecialistPaymentProfile(data: any) {
    const response = await this.client.post('/v1/specialist-portal/payment-profiles', data);
    return response.data;
  }

  async updateSpecialistPaymentProfile(id: string, data: any) {
    const response = await this.client.put(`/v1/specialist-portal/payment-profiles/${id}`, data);
    return response.data;
  }

  async deleteSpecialistPaymentProfile(id: string) {
    await this.client.delete(`/v1/specialist-portal/payment-profiles/${id}`);
  }

  // Payment Profiles & Payout Readiness (Manager)
  async getTeamMemberPaymentProfile(providerId: string, memberId: string) {
    const response = await this.client.get(`/v1/providers/${providerId}/team/${memberId}/payment-profile`);
    return response.data;
  }

  async getPayoutReadiness(providerId: string, settlementId: string) {
    const response = await this.client.get(`/v1/providers/${providerId}/compensation/settlements/${settlementId}/payout-readiness`);
    return response.data;
  }

  // ── Ledger ─────────────────────────────────────────────────────────────────
  // Financial truth: double-entry journal, LedgerEntry records.
  // NOT the same as getTransactions() — transactions are payment-rail events.
  async getProviderLedger(providerId: string, params?: Record<string, any>) {
    const response = await this.client.get(`/v1/providers/${providerId}/ledger`, { params });
    return response.data;
  }

  // ── Team ───────────────────────────────────────────────────────────────────
  // Unified identity: Staff + employed specialists + independent specialists
  // Each member includes their active compensation policy and outstanding payable
  // sourced from the Settlement table — never from booking prices.
  async getProviderTeam(providerId: string) {
    const response = await this.client.get(`/v1/providers/${providerId}/team`);
    return response.data;
  }

  // Schedule Migration Endpoints
  async checkScheduleMigration() {
    const response = await this.client.get('/v1/schedule-migration/status');
    return response.data;
  }

  async migrateSchedule() {
    const response = await this.client.post('/v1/schedule-migration/migrate');
    return response.data;
  }
  // ── Floor Operations (Break Management) ─────────────────────────────────────
  async startSpecialistBreak(specialistId: string, durationMinutes: number, locationId?: string) {
    const response = await this.client.post(`/v1/specialists/${specialistId}/breaks`, {
      duration_minutes: durationMinutes,
      location_id: locationId,
    });
    return response.data;
  }

  async endSpecialistBreak(specialistId: string, locationId?: string) {
    const response = await this.client.post(`/v1/specialists/${specialistId}/breaks/end`, {
      location_id: locationId,
    });
    return response.data;
  }
}

export const apiClient = new ApiClient();
