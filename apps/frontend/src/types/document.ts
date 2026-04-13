export interface DocumentItem {
  id: string;
  title: string;
  description?: string;
  filePath?: string;
  mimeType?: string;
  type?: string;
  ownerId: string;
  issuingAdministrationId?: string | null;
  recipientAdministrationId?: string | null;
  subEntityCode?: string | null;
  documentNumber?: string | null;
  signedAt?: string | null;
  isFavorite?: boolean;
  labelCodes?: string[];
  status: 'draft' | 'active' | 'archived' | 'signed' | 'pending_signature';
  createdAt: string;
  updatedAt: string;
}
