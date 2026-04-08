export interface IssuingAdministration {
  id: string;
  name: string;
  code: string;
  isActive: boolean;
  logo?: string | null;
  metadata?: Record<string, unknown>;
  profiles?: AdministrationProfile[];
  users?: AdministrationUser[];
  createdAt: string;
  updatedAt: string;
}

export interface AdministrationProfile {
  id: string;
  administrationId: string;
  name: string;
  permissions?: Record<string, unknown>;
  createdAt: string;
  updatedAt: string;
}

export interface AdministrationUser {
  id: string;
  administrationId: string;
  profileId?: string;
  profile?: AdministrationProfile;
  adminRole?: 'super_admin' | 'admin' | 'manager' | 'user' | 'signer';
  fullName: string;
  email: string;
  username: string;
  status: 'active' | 'inactive';
  createdAt: string;
  updatedAt: string;
}

export interface DocumentTemplate {
  id: string;
  name: string;
  fileName: string;
  fileType: 'docx' | 'xlsx' | 'pptx' | 'pdf';
  storagePath?: string;
  content?: string;
  administrationId?: string;
  createdBy?: string;
  createdAt: string;
  updatedAt: string;
}

export interface TemplateVariable {
  id: string;
  templateId: string;
  key: string;
  label: string;
  fieldType: 'text' | 'date' | 'number' | 'select' | 'textarea';
  required: boolean;
  placeholder?: string;
  defaultValue?: string;
  options?: string[];
  createdAt: string;
  updatedAt: string;
}

export interface RecipientAdministration {
  id: string;
  name: string;
  channel: 'api' | 'email' | 'ler' | 'application';
  logo?: string | null;
  apiEndpoint?: string;
  emailAddress?: string;
  metadata?: Record<string, unknown>;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface DirectionType {
  id: string;
  name: string;
  description?: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface RoutingRule {
  id: string;
  name: string;
  documentType: string;
  templateId?: string;
  recipientAdministrationId: string;
  conditions?: Record<string, unknown>;
  priority: number;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface CreateTemplatePayload {
  name: string;
  fileName: string;
  fileType: 'docx' | 'xlsx' | 'pptx' | 'pdf';
  storagePath?: string;
  content?: string;
  administrationId?: string;
}

export interface UpdateTemplatePayload {
  name?: string;
  fileName?: string;
  fileType?: 'docx' | 'xlsx' | 'pptx' | 'pdf';
  storagePath?: string;
  content?: string;
}

export interface GenerateTemplateDocumentPayload {
  values?: Record<string, string>;
  outputFileName?: string;
}

export interface GeneratedTemplateDocument {
  templateId: string;
  fileName: string;
  generatedContent: string;
  variablesUsed: string[];
}

export interface CreateTemplateVariablePayload {
  key: string;
  label: string;
  fieldType: 'text' | 'date' | 'number' | 'select' | 'textarea';
  required?: boolean;
  placeholder?: string;
  defaultValue?: string;
  options?: string[];
}

export interface UpdateTemplateVariablePayload {
  key?: string;
  label?: string;
  fieldType?: 'text' | 'date' | 'number' | 'select' | 'textarea';
  required?: boolean;
  placeholder?: string;
  defaultValue?: string;
  options?: string[];
}

export interface CreateIssuingAdministrationPayload {
  name: string;
  code: string;
  metadata?: Record<string, unknown>;
}

export interface UpdateIssuingAdministrationPayload {
  name?: string;
  code?: string;
  isActive?: boolean;
  metadata?: Record<string, unknown>;
}

export interface CreateAdministrationProfilePayload {
  name: string;
  permissions?: Record<string, unknown>;
}

export interface UpdateAdministrationProfilePayload {
  name?: string;
  permissions?: Record<string, unknown>;
}

export interface CreateAdministrationUserPayload {
  fullName: string;
  email: string;
  username: string;
  profileId?: string;
}

export interface UpdateAdministrationUserPayload {
  fullName?: string;
  email?: string;
  username?: string;
  profileId?: string;
  status?: 'active' | 'inactive';
}

export interface CreateRecipientAdministrationPayload {
  name: string;
  channel: 'api' | 'email' | 'ler' | 'application';
  apiEndpoint?: string;
  emailAddress?: string;
  metadata?: Record<string, unknown>;
}

export interface UpdateRecipientAdministrationPayload {
  name?: string;
  channel?: 'api' | 'email' | 'ler' | 'application';
  apiEndpoint?: string;
  emailAddress?: string;
  metadata?: Record<string, unknown>;
  isActive?: boolean;
}

export interface CreateDirectionTypePayload {
  name: string;
  description?: string;
}

export interface UpdateDirectionTypePayload {
  name?: string;
  description?: string;
}

export interface CreateRoutingRulePayload {
  name: string;
  documentType: string;
  templateId?: string;
  recipientAdministrationId: string;
  priority?: number;
}

export interface UpdateRoutingRulePayload {
  name?: string;
  documentType?: string;
  templateId?: string;
  recipientAdministrationId?: string;
  priority?: number;
  isActive?: boolean;
}

export interface SignatureProviderConfig {
  id: string;
  administrationId?: string | null;
  isActive: boolean;
  endpoint?: string | null;
  signPath?: string | null;
  apiKey?: string | null;
  consentPageId?: string | null;
  signatureProfileId?: string | null;
  providerOwnerUserId?: string | null;
  verifySsl: boolean;
  timeoutMs: number;
  createdAt: string;
  updatedAt: string;
}

export interface UpdateSignatureProviderConfigPayload {
  administrationId?: string;
  isActive?: boolean;
  endpoint?: string;
  signPath?: string;
  apiKey?: string;
  consentPageId?: string;
  signatureProfileId?: string;
  providerOwnerUserId?: string;
  verifySsl?: boolean;
  timeoutMs?: number;
}

export interface NotificationConfig {
  id: string;
  administrationId: string;
  isActive: boolean;
  smtpHost?: string | null;
  smtpPort: number;
  smtpSecure: boolean;
  smtpUser?: string | null;
  smtpPassword?: string | null;
  smtpFrom?: string | null;
  triggers?: Record<string, boolean> | null;
  createdAt: string;
  updatedAt: string;
}

export interface UpdateNotificationConfigPayload {
  isActive?: boolean;
  smtpHost?: string;
  smtpPort?: number;
  smtpSecure?: boolean;
  smtpUser?: string;
  smtpPassword?: string;
  smtpFrom?: string;
  triggers?: Record<string, boolean>;
}

export interface AppSetting {
  id: string;
  key: string;
  value: string | null;
  description: string | null;
  updatedAt: string;
}

export interface RequestedAct {
  id: string;
  administrationScopeType: 'emitter' | 'recipient';
  administrationScopeId: string;
  administrationLabel: string;
  directionCode: string;
  directionLabel: string;
  documentName: string;
  requiredDocuments: string[];
  applicantFields?: Array<{
    label: string;
    inputType: 'text' | 'date' | 'number' | 'phone' | 'email' | 'textarea';
  }>;
  createdBy?: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface CreateRequestedActPayload {
  administrationScopeType: 'emitter' | 'recipient';
  administrationScopeId: string;
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

export interface UpsertAppSettingPayload {
  value?: string | null;
  description?: string;
}
