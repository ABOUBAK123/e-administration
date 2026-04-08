import axios from 'axios';
import { tokenStore } from './tokenStore';

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:3000/api/v1';

const api = axios.create({
  baseURL: API_BASE_URL,
  withCredentials: true,
});

/** Decode JWT payload without verifying signature (client-side only). */
function decodeBase64Url(value: string): string {
  const normalized = value.replace(/-/g, '+').replace(/_/g, '/');
  const padded = normalized + '='.repeat((4 - (normalized.length % 4)) % 4);
  return atob(padded);
}

function getTokenExpiry(token: string): number | null {
  try {
    const payloadPart = token.split('.')[1];
    if (!payloadPart) return null;
    const payload = JSON.parse(decodeBase64Url(payloadPart));
    return typeof payload.exp === 'number' ? payload.exp : null;
  } catch {
    return null;
  }
}

function isTokenExpired(token: string): boolean {
  const exp = getTokenExpiry(token);
  if (exp === null) return false;
  // Consider expired if less than 30 seconds remain
  return Date.now() / 1000 >= exp - 30;
}

let isRefreshing = false;
let failedQueue: Array<{ resolve: (value: any) => void; reject: (reason?: any) => void }> = [];

const processQueue = (error: any, token: string | null = null) => {
  failedQueue.forEach((prom) => {
    if (error) {
      prom.reject(error);
    } else {
      prom.resolve(token);
    }
  });
  failedQueue = [];
};

const clearSessionAndRedirect = () => {
  tokenStore.clear();
  window.location.href = '/login';
};

const setAuthorizationHeader = (headers: any, token: string) => {
  if (!headers || !token) return;
  if (typeof headers.set === 'function') {
    headers.set('Authorization', `Bearer ${token}`);
    return;
  }
  headers.Authorization = `Bearer ${token}`;
};

api.interceptors.request.use(async (config) => {
  let token = tokenStore.getAccessToken();

  // Proactively refresh if the access token is expired or expiring soon
  if (token && isTokenExpired(token) && !isRefreshing) {
    const storedRefresh = tokenStore.getRefreshToken();
    if (storedRefresh) {
      try {
        isRefreshing = true;
        const response = await axios.post(`${API_BASE_URL}/auth/refresh`, {
          refreshToken: storedRefresh,
        }, { withCredentials: true });
        const { accessToken, refreshToken: newRefreshToken, user } = response.data;
        tokenStore.setTokens({ accessToken, refreshToken: newRefreshToken });
        api.defaults.headers.common['Authorization'] = `Bearer ${accessToken}`;
        processQueue(null, accessToken);
        token = accessToken;
      } catch {
        processQueue(new Error('Refresh failed'), null);
        clearSessionAndRedirect();
        return config;
      } finally {
        isRefreshing = false;
      }
    }
  }

  if (token) {
    config.headers = config.headers ?? {};
    setAuthorizationHeader(config.headers, token);
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = (error?.config || {}) as any;
    const isUnauthorized = error?.response?.status === 401;
    const requestUrl = String(originalRequest?.url || '');
    const isRefreshCall = requestUrl.includes('/auth/refresh');

    if (!isUnauthorized || isRefreshCall) {
      return Promise.reject(error);
    }

    if (originalRequest._retry) {
      clearSessionAndRedirect();
      return Promise.reject(error);
    }

    if (isRefreshing) {
      return new Promise((resolve, reject) => {
        failedQueue.push({ resolve, reject });
      })
        .then((token) => {
          originalRequest.headers = originalRequest.headers ?? {};
          setAuthorizationHeader(originalRequest.headers, String(token || ''));
          return api(originalRequest);
        })
        .catch((err) => Promise.reject(err));
    }

    originalRequest._retry = true;
    isRefreshing = true;

    const refreshToken = tokenStore.getRefreshToken();
    if (!refreshToken) {
      isRefreshing = false;
      clearSessionAndRedirect();
      return Promise.reject(error);
    }

    try {
      const response = await axios.post(`${API_BASE_URL}/auth/refresh`, { refreshToken }, { withCredentials: true });
      const { accessToken, refreshToken: newRefreshToken, user } = response.data;

      tokenStore.setTokens({ accessToken, refreshToken: newRefreshToken });

      api.defaults.headers.common['Authorization'] = `Bearer ${accessToken}`;
      originalRequest.headers = originalRequest.headers ?? {};
      setAuthorizationHeader(originalRequest.headers, accessToken);

      processQueue(null, accessToken);
      return api(originalRequest);
    } catch (refreshError) {
      processQueue(refreshError, null);
      clearSessionAndRedirect();
      return Promise.reject(refreshError);
    } finally {
      isRefreshing = false;
    }
  },
);

export default api;
