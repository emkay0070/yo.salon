import axios, { AxiosInstance, AxiosError, InternalAxiosRequestConfig } from 'axios';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL 
  ? `${process.env.NEXT_PUBLIC_API_URL}/v1` 
  : '/backend/api/v1';

const SALON_STORAGE_KEY = 'portal_active_salon_id';

const UUID_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const INVALID_ID_SET = new Set(['null', 'undefined', '0', 'nan', '']);

function sanitizePortalId(value: string | null): string | null {
  if (!value) return null;
  const trimmed = value.trim();
  if (INVALID_ID_SET.has(trimmed.toLowerCase())) return null;
  if (!UUID_REGEX.test(trimmed)) return null;
  return trimmed;
}

class PortalApiClient {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({
      baseURL: API_BASE_URL,
      headers: {
        'Content-Type': 'application/json',
      },
    });

    this.client.interceptors.request.use(
      (config: InternalAxiosRequestConfig) => {
        const token = localStorage.getItem('portal_auth_token');
        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
        }

        const rawSalonId = localStorage.getItem(SALON_STORAGE_KEY);
        const salonId = sanitizePortalId(rawSalonId);
        if (salonId) {
          config.headers['X-Salon-Id'] = salonId;
        } else if (rawSalonId) {
          localStorage.removeItem(SALON_STORAGE_KEY);
          console.warn('[PortalApiClient] Discarded invalid stored salon_id:', JSON.stringify(rawSalonId));
        }

        return config;
      },
      (error) => Promise.reject(error)
    );

    // Add response interceptor for error handling
    this.client.interceptors.response.use(
      (response) => response,
      (error: AxiosError) => {
        // Don't auto-redirect for follow/favorite endpoints - let UI handle auth errors
        const isFollowOrFavorite = error.config?.url?.includes('/specialists/') &&
          (error.config.url?.includes('/follow') || error.config.url?.includes('/favorite'));

        if (error.response?.status === 401 && !isFollowOrFavorite) {
          // Only hard-redirect on protected routes — not on public pages like /login
          // where the login page is handling auth fallback itself.
          const publicPaths = ['/login', '/portal/login', '/register', '/book', '/welcome', '/salons', '/forgot-password'];
          const isPublicPage = publicPaths.some(p => window.location.pathname.startsWith(p));
          if (!isPublicPage) {
            localStorage.removeItem('portal_auth_token');
            localStorage.removeItem(SALON_STORAGE_KEY);
            window.location.href = '/portal/login';
          }
        }

        // Log 500 errors for debugging
        if (error.response?.status === 500) {
          console.error('Server Error (500):', {
            url: error.config?.url,
            message: error.message,
            response: error.response?.data
          });
        }

        return Promise.reject(error);
      }
    );
  }

  setActiveSalonId(salonId: string) {
    const clean = sanitizePortalId(salonId);
    if (clean) {
      localStorage.setItem(SALON_STORAGE_KEY, clean);
    } else {
      localStorage.removeItem(SALON_STORAGE_KEY);
      if (salonId) {
        console.warn('[PortalApiClient] Rejected invalid salon_id in setActiveSalonId:', JSON.stringify(salonId));
      }
    }
  }

  getActiveSalonId(): string | null {
    return sanitizePortalId(localStorage.getItem(SALON_STORAGE_KEY));
  }

  clearActiveSalonId() {
    localStorage.removeItem(SALON_STORAGE_KEY);
  }

  // Auth endpoints
  async register(data: {
    email: string;
    password: string;
    phone: string;
    salon_id: string;
    name?: string;
  }) {
    const response = await this.client.post('/portal/create', data);
    const { portal_account, customer, token } = response.data;
    if (token) {
      localStorage.setItem('portal_auth_token', token);
    }
    return { portal_account, customer, is_new_customer: response.data.is_new_customer };
  }

  async login(email: string, password: string) {
    const response = await this.client.post('/portal/login', { email, password });
    const { token, context } = response.data;
    localStorage.setItem('portal_auth_token', token);

    // Store the active salon from context
    if (context?.active_salon?.id) {
      this.setActiveSalonId(context.active_salon.id);
    }

    return { context, token };
  }

  async logout() {
    try {
      await this.client.post('/portal/logout');
    } finally {
      localStorage.removeItem('portal_auth_token');
      this.clearActiveSalonId();
    }
  }

  async getMe() {
    const response = await this.client.get('/portal/me');
    return response.data;
  }

  // Generic methods for flexibility
  async get(url: string, params?: Record<string, any>) {
    const response = await this.client.get(url, { params: params || {} });
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

  async patch(url: string, data?: any) {
    const response = await this.client.patch(url, data);
    return response.data;
  }

  async delete(url: string) {
    const response = await this.client.delete(url);
    return response.data;
  }

  // Booking — creates a portal booking using the portal booking endpoint
  async createPortalBooking(data: {
    service_id: string;
    salon_id: string;
    specialist_id?: string;
    date: string;
    time: string;
    notes?: string;
    idempotency_key?: string;
  }) {
    const payload = {
      ...data,
      idempotency_key: data.idempotency_key ?? crypto.randomUUID(),
    };
    // Use the portal booking endpoint which has proper auth middleware
    const response = await this.client.post('/portal/bookings', payload);
    return response.data;
  }

  // Booking Context / Payments
  async getPaymentInstructions(salonId: string, serviceId: string) {
    const response = await this.client.get('/booking-context/payment', {
      params: { salon_id: salonId, service_id: serviceId }
    });
    return response.data;
  }



  // Booking Rules methods
  async validateBookingRules(data: {
    service_id: string;
    salon_id: string;
    date: string;
    time?: string;
    customer_id?: string;
    specialist_id?: string;
  }) {
    const response = await this.client.post('/booking-rules/validate', data);
    return response.data;
  }



  async getCancellationPolicy(params: {
    service_id: string;
    salon_id: string;
  }) {
    const response = await this.client.get('/booking-rules/cancellation-policy', { params });
    return response.data;
  }

  async checkCancellation(data: {
    service_id: string;
    salon_id: string;
    booking_time: string;
    deposit_amount: number;
  }) {
    const response = await this.client.post('/booking-rules/check-cancellation', data);
    return response.data;
  }

  async getBookingRequirements(params: {
    service_id: string;
    salon_id: string;
  }) {
    const response = await this.client.get('/booking-rules/requirements', { params });
    return response.data;
  }
}

export const portalApiClient = new PortalApiClient();

