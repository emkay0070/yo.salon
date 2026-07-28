import axios, { AxiosInstance, AxiosError } from 'axios';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || '/backend/api';

const SALON_STORAGE_KEY = 'portal_active_salon_id';

class PortalApiClient {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({
      baseURL: API_BASE_URL,
      headers: {
        'Content-Type': 'application/json',
      },
    });

    // Add request interceptor to include portal auth token + salon context
    this.client.interceptors.request.use(
      (config) => {
        const token = localStorage.getItem('portal_auth_token');
        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
        }

        // Attach active salon context
        const salonId = localStorage.getItem(SALON_STORAGE_KEY);
        if (salonId) {
          config.headers['X-Salon-Id'] = salonId;
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
          localStorage.removeItem('portal_auth_token');
          localStorage.removeItem(SALON_STORAGE_KEY);
          window.location.href = '/portal/login';
        }
        return Promise.reject(error);
      }
    );
  }

  // Salon context management
  setActiveSalonId(salonId: string) {
    localStorage.setItem(SALON_STORAGE_KEY, salonId);
  }

  getActiveSalonId(): string | null {
    return localStorage.getItem(SALON_STORAGE_KEY);
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

  async patch(url: string, data?: any) {
    const response = await this.client.patch(url, data);
    return response.data;
  }

  async delete(url: string) {
    const response = await this.client.delete(url);
    return response.data;
  }
}

export const portalApiClient = new PortalApiClient();

