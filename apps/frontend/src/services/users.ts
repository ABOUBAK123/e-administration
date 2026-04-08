import api from './api'

export interface AppUserRecord {
  id: string
  username: string
  email: string
  fullName: string
  avatar?: string | null
  role: string
  status: 'active' | 'inactive' | 'suspended'
  quota?: string
  administrationId?: string | null
  directionLabel?: string | null
  directionScopeType?: 'emitter' | 'recipient' | null
  directionScopeId?: string | null
  subEntityCode?: string | null
  createdAt: string
  updatedAt: string
}

interface CreateAppUserPayload {
  username: string
  email: string
  password: string
  fullName: string
  role: string
  status?: 'active' | 'inactive' | 'suspended'
  quota?: string
  administrationId?: string
  directionLabel?: string
  directionScopeType?: 'emitter' | 'recipient'
  directionScopeId?: string
  subEntityCode?: string
}

interface UpdateAppUserPayload {
  username?: string
  email?: string
  fullName?: string
  role?: string
  password?: string
  quota?: string
  administrationId?: string
  directionLabel?: string
  directionScopeType?: 'emitter' | 'recipient'
  directionScopeId?: string
  subEntityCode?: string
}

export const fetchAppUsers = async (): Promise<AppUserRecord[]> => {
  const response = await api.get('/users')
  return response.data?.data || []
}

export const fetchSignataires = async (): Promise<AppUserRecord[]> => {
  const response = await api.get('/users/signataires')
  return Array.isArray(response.data) ? response.data : []
}

export const createAppUser = async (payload: CreateAppUserPayload): Promise<AppUserRecord> => {
  const response = await api.post('/users', payload)
  return response.data
}

export const updateAppUserStatus = async (
  userId: string,
  status: 'active' | 'inactive' | 'suspended',
): Promise<AppUserRecord> => {
  const response = await api.put(`/users/${userId}/status`, { status })
  return response.data
}

export const updateAppUser = async (userId: string, payload: UpdateAppUserPayload): Promise<AppUserRecord> => {
  const response = await api.put(`/users/${userId}`, payload)
  return response.data
}

export const deleteAppUser = async (userId: string): Promise<void> => {
  await api.delete(`/users/${userId}`)
}

export const uploadAppUserAvatar = async (userId: string, file: File): Promise<AppUserRecord> => {
  const formData = new FormData()
  formData.append('file', file)
  const response = await api.put(`/users/${userId}/avatar`, formData)
  return response.data
}