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

const readStorage = (key: string) => {
  const localValue = localStorage.getItem(key);
  if (localValue) return localValue;

  const sessionValue = sessionStorage.getItem(key);
  if (sessionValue) {
    localStorage.setItem(key, sessionValue);
  }

  return sessionValue;
};

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
    localStorage.setItem(TOKEN_KEY, data.token);
    localStorage.setItem(USER_KEY, JSON.stringify(data.user));
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
    localStorage.setItem(TOKEN_KEY, data.token);
    localStorage.setItem(USER_KEY, JSON.stringify(data.user));
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
    localStorage.setItem(USER_KEY, JSON.stringify(data.user));
    return data.user;
  },

  async updateProfile(payload: {
    name: string;
    phone?: string | null;
    current_password?: string;
    new_password?: string;
    new_password_confirmation?: string;
  }): Promise<{ message: string; user: ApiUser }> {
    const { data } = await apiClient.put<{ message: string; user: ApiUser }>('/auth/profile', payload);
    localStorage.setItem(USER_KEY, JSON.stringify(data.user));
    return data;
  },

  getStoredUser(): ApiUser | null {
    const raw = readStorage(USER_KEY);
    if (!raw) return null;
    try {
      return JSON.parse(raw) as ApiUser;
    } catch {
      this.clearAuth();
      return null;
    }
  },

  isAuthenticated(): boolean {
    return !!readStorage(TOKEN_KEY);
  },

  getToken(): string | null {
    return readStorage(TOKEN_KEY);
  },
};
