import api from './api';
import { tokenStore } from './tokenStore';

interface LoginPayload {
  email: string;
  password: string;
}

interface RegisterPayload {
  email: string;
  username: string;
  password: string;
  fullName: string;
  role?: string;
  status?: string;
}

interface RegisterInvitedResponse {
  message: string;
  user: {
    id: string;
    email: string;
    username: string;
    fullName: string;
    role: string;
    status: string;
  };
}

interface ForgotPasswordPayload {
  email: string;
}

interface ResetPasswordPayload {
  token: string;
  newPassword: string;
}

export interface AuthResponse {
  accessToken: string;
  refreshToken: string;
  user: {
    id: string;
    email: string;
    username: string;
    fullName: string;
    role: string;
    avatar?: string;
    administrationId?: string | null;
    directionLabel?: string | null;
    directionScopeType?: 'emitter' | 'recipient' | null;
    directionScopeId?: string | null;
    subEntityCode?: string | null;
  };
}

export interface UpdateCurrentUserPayload {
  email?: string;
  fullName?: string;
  currentPassword?: string;
  password?: string;
}

export interface CurrentUserPermissionsResponse {
  isElevated: boolean;
  permissions: string[];
  source: 'elevated_role' | 'administration_user_profile' | 'role_profile' | 'role_default' | 'none';
  debug?: {
    userRole?: string | null;
    normalizedUserRole?: string | null;
    adminRole?: string | null;
    normalizedAdminRole?: string | null;
    administrationProfileName?: string | null;
    administrationProfileId?: string | null;
    roleProfileName?: string | null;
    roleProfileId?: string | null;
  };
}

export interface CurrentUserThemeResponse {
  scopeType: 'emitter' | 'recipient' | null;
  scopeId: string | null;
  menuColor: string;
  loginBackgroundImage: string | null;
  administrationLogo?: string | null;
}

export const login = async (payload: LoginPayload): Promise<AuthResponse> => {
  const response = await api.post('/auth/login', payload);
  return response.data;
};

export const register = async (payload: RegisterPayload): Promise<AuthResponse> => {
  const response = await api.post('/auth/register', payload);
  return response.data;
};

export const registerInvited = async (payload: RegisterPayload): Promise<RegisterInvitedResponse> => {
  const response = await api.post('/auth/register-invited', payload);
  return response.data;
};

export const refreshToken = async (refreshToken?: string): Promise<AuthResponse> => {
  const response = await api.post('/auth/refresh', refreshToken ? { refreshToken } : {});
  return response.data;
};

export const getCurrentUser = async (): Promise<AuthResponse['user']> => {
  const response = await api.get('/users/profile');
  return response.data;
};

export const getCurrentUserPermissions = async (): Promise<CurrentUserPermissionsResponse> => {
  const response = await api.get('/users/profile/permissions');
  return response.data;
};

export const getCurrentUserTheme = async (): Promise<CurrentUserThemeResponse> => {
  const response = await api.get('/users/profile/theme');
  return response.data;
};

export const updateCurrentUserProfile = async (
  payload: UpdateCurrentUserPayload,
): Promise<AuthResponse['user']> => {
  const response = await api.put('/users/profile', payload);
  return response.data;
};

export const uploadCurrentUserAvatar = async (file: File): Promise<AuthResponse['user']> => {
  const endpoint = `${import.meta.env.VITE_API_URL || 'http://localhost:3000/api/v1'}/users/profile/avatar`;

  const sendUpload = async (fieldName: 'file' | 'avatar', accessTokenOverride?: string | null) => {
    const formData = new FormData();
    formData.append(fieldName, file);

    const accessToken = accessTokenOverride ?? tokenStore.getAccessToken();
    const headers: HeadersInit = {};
    if (accessToken) {
      headers.Authorization = `Bearer ${accessToken}`;
    }

    const response = await fetch(endpoint, {
      method: 'PUT',
      headers,
      body: formData,
      credentials: 'include',
    });

    const payload = await response.json().catch(() => ({} as any));
    if (!response.ok) {
      const error: any = new Error(payload?.message || 'Avatar upload failed');
      error.response = {
        status: response.status,
        data: payload,
      };
      throw error;
    }

    return payload as AuthResponse['user'];
  };

  let refreshedAccessToken: string | null = null;
  let refreshedOnce = false;
  let lastError: any = null;

  for (const fieldName of ['file', 'avatar'] as const) {
    try {
      return await sendUpload(fieldName, refreshedAccessToken);
    } catch (error: any) {
      lastError = error;
      const status = Number(error?.response?.status || 0);

      if (status === 401 && !refreshedOnce) {
        const refreshed = await refreshToken(tokenStore.getRefreshToken() || undefined);
        tokenStore.setTokens({ accessToken: refreshed.accessToken, refreshToken: refreshed.refreshToken });
        refreshedAccessToken = refreshed.accessToken;
        refreshedOnce = true;

        try {
          return await sendUpload(fieldName, refreshedAccessToken);
        } catch (retryError: any) {
          lastError = retryError;
          const retryStatus = Number(retryError?.response?.status || 0);
          const canTryOtherField = retryStatus === 400 || retryStatus === 415 || retryStatus === 422;
          if (!canTryOtherField) {
            throw retryError;
          }
          continue;
        }
      }

      const canTryOtherField = status === 400 || status === 415 || status === 422;
      if (!canTryOtherField) {
        throw error;
      }
    }
  }

  throw lastError;
};

export const forgotPassword = async (payload: ForgotPasswordPayload): Promise<{ message: string }> => {
  const response = await api.post('/auth/forgot-password', payload);
  return response.data;
};

export const resetPassword = async (payload: ResetPasswordPayload): Promise<{ message: string }> => {
  const response = await api.post('/auth/reset-password', payload);
  return response.data;
};
