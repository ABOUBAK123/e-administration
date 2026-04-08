import { create } from 'zustand';
import { AuthState, UserProfile } from '../types/auth';
import { tokenStore } from '../services/tokenStore';
import {
  getCurrentUser,
  getCurrentUserTheme,
  login as loginApi,
  refreshToken as refreshApi,
  register as registerApi,
  uploadCurrentUserAvatar,
  updateCurrentUserProfile,
} from '../services/auth';

interface AuthActions {
  setCredentials: (data: { accessToken: string; refreshToken: string; user: UserProfile }) => void;
  setUser: (user: UserProfile) => void;
  logout: () => void;
  login: (email: string, password: string) => Promise<void>;
  register: (email: string, username: string, password: string, fullName: string) => Promise<void>;
  refreshTokens: () => Promise<void>;
  bootstrapAuth: () => Promise<void>;
  syncCurrentUser: () => Promise<void>;
  saveCurrentUserProfile: (payload: { email?: string; fullName?: string; currentPassword?: string; password?: string }) => Promise<void>;
  uploadAvatar: (file: File) => Promise<void>;
}

const API_ROOT = (import.meta.env.VITE_API_URL || 'http://localhost:3000/api/v1').replace(/\/api(?:\/v\d+)?\/?$/, '')

const resolveThemeAssetUrl = (value?: string | null) => {
  if (!value) return null
  if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('data:image/')) return value
  return `${API_ROOT}${value.startsWith('/') ? '' : '/'}${value}`
}

const applyThemeToStorage = (theme: { menuColor?: string | null; loginBackgroundImage?: string | null; administrationLogo?: string | null }) => {
  const menuColor = theme.menuColor || '#173b9f'
  try {
    localStorage.setItem('ep_theme_menu_color', menuColor)
  } catch {
    // Ignore localStorage failures and continue with runtime events.
  }
  window.dispatchEvent(new StorageEvent('storage', { key: 'ep_theme_menu_color', newValue: menuColor }))

  const resolvedLoginBg = resolveThemeAssetUrl(theme.loginBackgroundImage)
  if (resolvedLoginBg) {
    try {
      localStorage.setItem('ep_theme_login_bg', resolvedLoginBg)
    } catch {
      // Ignore localStorage failures and continue with runtime events.
    }
    window.dispatchEvent(new StorageEvent('storage', { key: 'ep_theme_login_bg', newValue: resolvedLoginBg }))
  } else {
    try {
      localStorage.removeItem('ep_theme_login_bg')
    } catch {
      // Ignore localStorage failures and continue with runtime events.
    }
    window.dispatchEvent(new StorageEvent('storage', { key: 'ep_theme_login_bg', newValue: null }))
  }

  const resolvedAdministrationLogo = resolveThemeAssetUrl(theme.administrationLogo)
  if (resolvedAdministrationLogo) {
    try {
      localStorage.setItem('ep_admin_logo', resolvedAdministrationLogo)
    } catch {
      // Ignore localStorage failures and continue with runtime events.
    }
    window.dispatchEvent(new StorageEvent('storage', { key: 'ep_admin_logo', newValue: resolvedAdministrationLogo }))
  } else {
    try {
      localStorage.removeItem('ep_admin_logo')
    } catch {
      // Ignore localStorage failures and continue with runtime events.
    }
    window.dispatchEvent(new StorageEvent('storage', { key: 'ep_admin_logo', newValue: null }))
  }

  window.dispatchEvent(new CustomEvent('ep_theme_changed', {
    detail: {
      menuColor,
      loginBackgroundImage: resolvedLoginBg,
    },
  }))
}

const initialState: AuthState = {
  user: null,
  accessToken: null,
  refreshToken: null,
  isAuthenticated: false,
  isAuthResolved: false,
};

export const useAuthStore = create<AuthState & AuthActions>((set, get) => ({
  ...initialState,
  setCredentials: (data) => {
    tokenStore.setTokens({ accessToken: data.accessToken, refreshToken: data.refreshToken });
    set({
      user: data.user,
      accessToken: data.accessToken,
      refreshToken: data.refreshToken,
      isAuthenticated: true,
      isAuthResolved: true,
    });
  },
  setUser: (user) => {
    set({ user });
  },
  logout: () => {
    tokenStore.clear();
    set({
      user: null,
      accessToken: null,
      refreshToken: null,
      isAuthenticated: false,
      isAuthResolved: true,
    });
  },
  login: async (email, password) => {
    const data = await loginApi({ email, password });
    get().setCredentials({ accessToken: data.accessToken, refreshToken: data.refreshToken, user: data.user });
    // Fire-and-forget: fetch theme after navigation so login page doesn't re-render with the background image
    // just before unmounting (which caused the "halfway image" flash).
    setTimeout(() => {
      getCurrentUserTheme().then(applyThemeToStorage).catch(() => {
        // Silently ignore theme loading errors to avoid blocking authentication.
      });
    }, 0);
  },
  register: async (email, username, password, fullName) => {
    const data = await registerApi({ email, username, password, fullName });
    get().setCredentials({ accessToken: data.accessToken, refreshToken: data.refreshToken, user: data.user });
  },
  refreshTokens: async () => {
    const refresh = tokenStore.getRefreshToken() || undefined;
    const data = await refreshApi(refresh);
    get().setCredentials({ accessToken: data.accessToken, refreshToken: data.refreshToken, user: data.user });
    try {
      const theme = await getCurrentUserTheme();
      applyThemeToStorage(theme);
    } catch {
      // Ignore theme loading errors to avoid breaking token refresh flow.
    }
  },
  bootstrapAuth: async () => {
    try {
      const data = await refreshApi();
      get().setCredentials({ accessToken: data.accessToken, refreshToken: data.refreshToken, user: data.user });
      try {
        const theme = await getCurrentUserTheme();
        applyThemeToStorage(theme);
      } catch {
        // Ignore theme loading errors during bootstrap.
      }
    } catch {
      tokenStore.clear();
      set({
        user: null,
        accessToken: null,
        refreshToken: null,
        isAuthenticated: false,
        isAuthResolved: true,
      });
    }
  },
  syncCurrentUser: async () => {
    const user = await getCurrentUser();
    get().setUser(user);
    try {
      const theme = await getCurrentUserTheme();
      applyThemeToStorage(theme);
    } catch {
      // Ignore theme loading errors in profile synchronization.
    }
  },
  saveCurrentUserProfile: async (payload) => {
    const user = await updateCurrentUserProfile(payload);
    get().setUser(user);
  },
  uploadAvatar: async (file) => {
    const user = await uploadCurrentUserAvatar(file);
    get().setUser(user);
  },
}));
