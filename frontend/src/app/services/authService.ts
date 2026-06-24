import apiClient from './apiClient';

export interface ApiUser {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  role: 'user' | 'admin';
  status: boolean;
  join_date: string;
}

interface AuthResponse {
  user: ApiUser;
  token: string;
}

export const authService = {
  /** Đăng nhập */
  async login(email: string, mat_khau: string): Promise<AuthResponse> {
    const { data } = await apiClient.post<AuthResponse>('/auth/login', {
      email,
      mat_khau,
    });
    localStorage.setItem('auth_token', data.token);
    localStorage.setItem('auth_user', JSON.stringify(data.user));
    return data;
  },

  /** Đăng ký */
  async register(payload: {
    ten_kh: string;
    email: string;
    mat_khau: string;
    mat_khau_confirmation: string;
    dien_thoai?: string;
  }): Promise<AuthResponse> {
    const { data } = await apiClient.post<AuthResponse>('/auth/register', payload);
    localStorage.setItem('auth_token', data.token);
    localStorage.setItem('auth_user', JSON.stringify(data.user));
    return data;
  },

  /** Đăng xuất */
  async logout(): Promise<void> {
    try {
      await apiClient.post('/auth/logout');
    } finally {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('auth_user');
    }
  },

  /** Lấy thông tin user hiện tại từ API */
  async me(): Promise<ApiUser> {
    const { data } = await apiClient.get<{ user: ApiUser }>('/auth/me');
    localStorage.setItem('auth_user', JSON.stringify(data.user));
    return data.user;
  },

  /** Lấy user từ localStorage (không gọi API) */
  getStoredUser(): ApiUser | null {
    const raw = localStorage.getItem('auth_user');
    if (!raw) return null;
    try {
      return JSON.parse(raw) as ApiUser;
    } catch {
      return null;
    }
  },

  /** Kiểm tra đã đăng nhập chưa */
  isAuthenticated(): boolean {
    return !!localStorage.getItem('auth_token');
  },

  /** Lấy token */
  getToken(): string | null {
    return localStorage.getItem('auth_token');
  },
};
