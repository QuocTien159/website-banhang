import apiClient from './apiClient';

export interface ApiUser {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  role: 'customer' | 'staff' | 'admin';
  status: boolean;
  join_date: string;
}

interface AuthResponse {
  user: ApiUser;
  token: string;
}

const TOKEN_KEY = 'auth_token';
const USER_KEY = 'auth_user';

export const authService = {
  clearAuth(): void {
    sessionStorage.removeItem(TOKEN_KEY);
    sessionStorage.removeItem(USER_KEY);
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
  },

  async login(email: string, mat_khau: string): Promise<AuthResponse> {
    const { data } = await apiClient.post<AuthResponse>('/auth/login', {
      email,
      mat_khau,
    });
    this.clearAuth();
    sessionStorage.setItem(TOKEN_KEY, data.token);
    sessionStorage.setItem(USER_KEY, JSON.stringify(data.user));
    return data;
  },

  async register(payload: {
    ten_kh: string;
    email: string;
    mat_khau: string;
    mat_khau_confirmation: string;
    dien_thoai?: string;
  }): Promise<AuthResponse> {
    const { data } = await apiClient.post<AuthResponse>('/auth/register', payload);
    this.clearAuth();
    sessionStorage.setItem(TOKEN_KEY, data.token);
    sessionStorage.setItem(USER_KEY, JSON.stringify(data.user));
    return data;
  },

  async logout(): Promise<void> {
    try {
      if (this.isAuthenticated()) {
        await apiClient.post('/auth/logout');
      }
    } finally {
      this.clearAuth();
    }
  },

  async me(): Promise<ApiUser> {
    const { data } = await apiClient.get<{ user: ApiUser }>('/auth/me');
    sessionStorage.setItem(USER_KEY, JSON.stringify(data.user));
    return data.user;
  },

  getStoredUser(): ApiUser | null {
    const raw = sessionStorage.getItem(USER_KEY);
    if (!raw) return null;
    try {
      return JSON.parse(raw) as ApiUser;
    } catch {
      this.clearAuth();
      return null;
    }
  },

  isAuthenticated(): boolean {
    return !!sessionStorage.getItem(TOKEN_KEY);
  },

  getToken(): string | null {
    return sessionStorage.getItem(TOKEN_KEY);
  },
};
