import api from './api';
import { SignatureItem, SignatureRequest, CreateSignaturePayload, RequestSignaturePayload } from '../types/signature';

export const fetchSignatures = async (documentId: string): Promise<SignatureItem[]> => {
  const response = await api.get(`/signatures/${documentId}`);
  return response.data;
};

export const requestSignature = async (
  documentId: string,
  payload: RequestSignaturePayload,
): Promise<SignatureRequest> => {
  const response = await api.post(`/signatures/${documentId}/request`, payload);
  return response.data;
};

export const signDocument = async (
  documentId: string,
  payload: CreateSignaturePayload,
): Promise<SignatureItem> => {
  const response = await api.post(`/signatures/${documentId}/sign`, payload);
  return response.data;
};

export const verifySignature = async (documentId: string, signatureId: string): Promise<SignatureItem> => {
  const response = await api.post(`/signatures/${documentId}/verify/${signatureId}`);
  return response.data;
};

export const getPendingSignatures = async (userId: string): Promise<SignatureRequest[]> => {
  const response = await api.get(`/signatures/pending/${userId}`);
  return response.data;
};

export const respondToSignatureRequest = async (
  requestId: string,
  accepted: boolean,
): Promise<SignatureRequest> => {
  const response = await api.post(`/signatures/request/${requestId}/respond`, { accepted });
  return response.data;
};
