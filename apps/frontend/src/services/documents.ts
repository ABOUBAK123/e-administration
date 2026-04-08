import api from './api';
import { DocumentItem } from '../types/document';

export interface DocumentVersionItem {
  id: string;
  filePath?: string;
  version?: number;
  createdAt?: string;
}

export const fetchDocuments = async (page = 1, limit = 10, search = ''): Promise<DocumentItem[]> => {
  const params: Record<string, string | number> = { page, limit };
  if (search && search.trim()) {
    params.search = search;
  }

  const response = await api.get('/documents', { params });
  if (Array.isArray(response.data)) {
    return response.data;
  }

  if (Array.isArray(response.data?.data)) {
    return response.data.data;
  }

  return [];
};

export const createDocument = async (payload: Partial<DocumentItem>): Promise<DocumentItem> => {
  const response = await api.post('/documents', payload);
  return response.data;
};

export const fetchDocumentById = async (id: string): Promise<DocumentItem> => {
  const response = await api.get(`/documents/${id}`);
  return response.data;
};

export const fetchDocumentVersions = async (id: string): Promise<DocumentVersionItem[]> => {
  const response = await api.get(`/documents/${id}/versions`);
  if (Array.isArray(response.data)) {
    return response.data;
  }
  if (Array.isArray(response.data?.data)) {
    return response.data.data;
  }
  return [];
};

export const fetchReceptionDocuments = async (page = 1, limit = 50, search = ''): Promise<DocumentItem[]> => {
  const params: Record<string, string | number> = { page, limit };
  if (search && search.trim()) {
    params.search = search;
  }

  const response = await api.get('/documents/reception', { params });
  if (Array.isArray(response.data)) {
    return response.data;
  }

  if (Array.isArray(response.data?.data)) {
    return response.data.data;
  }

  return [];
};

export const markReceptionZipDownloaded = async (id: string): Promise<{
  id: string;
  zipDownloadedAt: string | null;
  message: string;
}> => {
  const response = await api.post(`/documents/reception/${id}/zip-downloaded`);
  return response.data;
};

export const fetchActRequests = async (page = 1, limit = 100, search = ''): Promise<DocumentItem[]> => {
  const params: Record<string, string | number> = { page, limit };
  if (search && search.trim()) {
    params.search = search;
  }

  const response = await api.get('/documents/act-requests', { params });
  if (Array.isArray(response.data)) {
    return response.data;
  }

  if (Array.isArray(response.data?.data)) {
    return response.data.data;
  }

  return [];
};

export interface ActRequestDetails {
  id: string;
  title: string;
  status: string;
  createdAt: string;
  subEntityCode?: string | null;
  issuingAdministrationId?: string | null;
  recipientAdministrationId?: string | null;
  applicant: {
    fullName: string;
    email: string;
    phone?: string;
  };
  applicantFieldValues?: Record<string, string>;
  note: string;
  requiredDocuments: Array<{
    name: string;
    received: boolean;
    matchedFiles: string[];
  }>;
  receivedDocuments: Array<{
    originalName: string;
    storedPath: string;
    requiredDocumentLabel?: string;
  }>;
  completeness: {
    requiredTotal: number;
    requiredReceived: number;
    receivedTotal: number;
  };
}

export const fetchActRequestDetails = async (id: string): Promise<ActRequestDetails> => {
  const response = await api.get(`/documents/act-requests/${id}/details`);
  return response.data;
};

export const startActRequestProcessing = async (id: string): Promise<{
  id: string;
  status: string;
  emailSent: boolean;
  applicantEmail: string | null;
  message: string;
}> => {
  const response = await api.post(`/documents/act-requests/${id}/start-processing`);
  return response.data;
};

export const markActRequestAsTreated = async (id: string): Promise<{
  id: string;
  status: string;
  message: string;
}> => {
  const response = await api.post(`/documents/act-requests/${id}/mark-treated`);
  return response.data;
};

export const updateDocument = async (
  id: string,
  payload: Pick<Partial<DocumentItem>, 'title' | 'description'>,
): Promise<DocumentItem> => {
  const response = await api.put(`/documents/${id}`, payload);
  return response.data;
};

export const deleteDocument = async (id: string): Promise<void> => {
  await api.delete(`/documents/${id}`);
};

export interface ShareDocumentPayload {
  mode: 'internal' | 'external' | 'recipient_administration';
  recipientAdministrationId?: string;
  recipientEmail?: string;
  recipientName?: string;
  applicantFullName?: string;
  applicantMatricule?: string;
  applicantEmail?: string;
  permission?: 'lecture' | 'modification';
  hasDelay?: boolean;
  delayValue?: number;
  delayUnit?: 'hours' | 'days';
}

export interface DocumentPreferenceItem {
  documentId: string;
  isFavorite: boolean;
  labelCodes: string[];
  updatedAt?: string;
}

export const shareDocument = async (id: string, payload: ShareDocumentPayload): Promise<{ message: string; expiresAt?: string | null }> => {
  const response = await api.post(`/documents/${id}/share`, payload);
  return response.data;
};

export const fetchMyDocumentPreferences = async (): Promise<DocumentPreferenceItem[]> => {
  const response = await api.get('/documents/preferences');
  if (Array.isArray(response.data)) {
    return response.data;
  }
  if (Array.isArray(response.data?.data)) {
    return response.data.data;
  }
  return [];
};

export const updateDocumentFavoritePreference = async (
  id: string,
  isFavorite: boolean,
): Promise<DocumentPreferenceItem> => {
  const response = await api.put(`/documents/${id}/preferences/favorite`, { isFavorite });
  return response.data;
};

export const updateDocumentLabelCodesPreference = async (
  id: string,
  codes: string[],
): Promise<DocumentPreferenceItem> => {
  const response = await api.put(`/documents/${id}/preferences/labels`, { codes });
  return response.data;
};

export const uploadDocumentFile = async (
  file: File,
  options?: { generatedFromSharedTemplate?: boolean; subEntityCode?: string; title?: string },
): Promise<DocumentItem> => {
  const formData = new FormData();
  formData.append('file', file);
  if (options?.generatedFromSharedTemplate) {
    formData.append('generatedFromSharedTemplate', 'true');
  }
  if (options?.subEntityCode) {
    formData.append('subEntityCode', options.subEntityCode);
  }
  if (options?.title) {
    formData.append('title', options.title);
  }
  const response = await api.post('/documents/new/upload', formData);
  return response.data;
};
