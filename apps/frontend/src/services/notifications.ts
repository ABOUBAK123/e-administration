import api from './api'

export interface AppNotification {
  id: string
  recipientId: string
  title: string
  message: string
  type: 'info' | 'validation' | 'signature' | 'workflow' | 'system'
  workflowId?: string | null
  executionId?: string | null
  actionUrl?: string | null
  isRead: boolean
  createdAt: string
}

export const fetchNotifications = async (): Promise<AppNotification[]> => {
  const response = await api.get('/notifications')
  return Array.isArray(response.data) ? response.data : []
}

export const fetchUnreadCount = async (): Promise<number> => {
  const response = await api.get('/notifications/unread-count')
  return response.data?.count ?? 0
}

export const markNotificationAsRead = async (notificationId: string): Promise<void> => {
  await api.put(`/notifications/${notificationId}/read`)
}

export const markAllNotificationsAsRead = async (): Promise<void> => {
  await api.put('/notifications/read-all')
}
