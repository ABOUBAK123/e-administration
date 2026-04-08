export interface SignatureRequest {
  id: string;
  documentId: string;
  requestedBy: string;
  requestedTo: string;
  status: 'pending' | 'accepted' | 'rejected';
  createdAt: string;
}

export interface SignatureItem {
  id: string;
  documentId: string;
  userId: string;
  signatureHash: string;
  verified: boolean;
  createdAt: string;
}

export interface RequestSignaturePayload {
  recipientEmail: string;
  message?: string;
  expiryDate?: string;
}

export interface CreateSignaturePayload {
  signatureHash: string;
}
