import axios from 'axios';

const API_BASE = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api';
const TOKEN_KEY = 'auth_token';
const USER_KEY = 'auth_user';

const getStoredToken = () => {
  const localToken = localStorage.getItem(TOKEN_KEY);
  if (localToken) return localToken;

  const sessionToken = sessionStorage.getItem(TOKEN_KEY);
  if (sessionToken) {
    localStorage.setItem(TOKEN_KEY, sessionToken);
  }

  return sessionToken;
};

const clearStoredAuth = () => {
  sessionStorage.removeItem(TOKEN_KEY);
  sessionStorage.removeItem(USER_KEY);
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
};

const apiClient = axios.create({
  baseURL: API_BASE,
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  withCredentials: true,
});

apiClient.interceptors.request.use((config) => {
  const token = getStoredToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      clearStoredAuth();
    }
    return Promise.reject(error);
  }
);

export default apiClient;
