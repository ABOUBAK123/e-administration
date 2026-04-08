import api from './api';

export interface PublicEmitterAdministration {
  id: string;
  name: string;
  code: string;
  logo?: string | null;
}

export interface PublicRequestedAct {
  id: string;
  emitterAdministrationId: string;
  administrationLabel: string;
  directionCode: string;
  directionLabel: string;
  documentName: string;
  requiredDocuments: string[];
  applicantFields?: Array<{
    label: string;
    inputType: 'text' | 'date' | 'number' | 'phone' | 'email' | 'textarea';
  }>;
}

export interface SubmitPublicActRequestPayload {
  emitterAdministrationId: string;
  requestedActId: string;
  applicantFullName: string;
  applicantEmail: string;
  applicantPhone?: string;
  note?: string;
  applicantFieldValues?: Record<string, string>;
  attachments: Array<{
    file: File;
    requiredDocumentLabel?: string;
  }>;
}

export interface SubmitPublicActRequestResponse {
  message: string;
  requestId: string;
  directionCode: string;
  actName: string;
  attachments: Array<{ originalName: string; storedPath: string }>;
}

export const fetchPublicEmitterAdministrations = async (): Promise<PublicEmitterAdministration[]> => {
  const response = await api.get('/documents/public/act-requests/emitters');
  return Array.isArray(response.data) ? response.data : [];
};

export const fetchPublicRequestedActsByEmitter = async (
  emitterAdministrationId: string,
): Promise<PublicRequestedAct[]> => {
  if (!emitterAdministrationId) return [];
  const response = await api.get(`/documents/public/act-requests/emitters/${emitterAdministrationId}`);
  return Array.isArray(response.data) ? response.data : [];
};

export const submitPublicActRequest = async (
  payload: SubmitPublicActRequestPayload,
): Promise<SubmitPublicActRequestResponse> => {
  const formData = new FormData();
  formData.append('emitterAdministrationId', payload.emitterAdministrationId);
  formData.append('requestedActId', payload.requestedActId);
  formData.append('applicantFullName', payload.applicantFullName);
  formData.append('applicantEmail', payload.applicantEmail);
  formData.append('applicantPhone', payload.applicantPhone || '');
  formData.append('note', payload.note || '');
  formData.append('applicantFieldValues', JSON.stringify(payload.applicantFieldValues || {}));

  payload.attachments.forEach((item) => {
    formData.append('files', item.file);
    formData.append('fileLabels', item.requiredDocumentLabel || '');
  });

  const response = await api.post('/documents/public/act-requests/submit', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });

  return response.data;
};
