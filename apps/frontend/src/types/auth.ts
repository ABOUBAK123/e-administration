export interface UserProfile {
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
}

export interface AuthState {
  user: UserProfile | null;
  accessToken: string | null;
  refreshToken: string | null;
  isAuthenticated: boolean;
  isAuthResolved: boolean;
}
