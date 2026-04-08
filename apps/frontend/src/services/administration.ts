import api from './api';
import {
  AdministrationProfile,
  AdministrationUser,
  AppSetting,
  UpsertAppSettingPayload,
  CreateAdministrationProfilePayload,
  CreateAdministrationUserPayload,
  CreateIssuingAdministrationPayload,
  CreateRecipientAdministrationPayload,
  CreateRoutingRulePayload,
  CreateTemplatePayload,
  GenerateTemplateDocumentPayload,
  GeneratedTemplateDocument,
  CreateTemplateVariablePayload,
  DocumentTemplate,
  IssuingAdministration,
  RecipientAdministration,
  RoutingRule,
  TemplateVariable,
  UpdateAdministrationProfilePayload,
  UpdateAdministrationUserPayload,
  UpdateIssuingAdministrationPayload,
  UpdateRecipientAdministrationPayload,
  UpdateRoutingRulePayload,
  UpdateTemplatePayload,
  UpdateTemplateVariablePayload,
  SignatureProviderConfig,
  NotificationConfig,
  UpdateNotificationConfigPayload,
  UpdateSignatureProviderConfigPayload,
  CreateDirectionTypePayload,
  UpdateDirectionTypePayload,
  DirectionType,
  RequestedAct,
  CreateRequestedActPayload,
} from '../types/administration';

export const fetchAppSettings = async (): Promise<AppSetting[]> => {
  const response = await api.get('/administration/app-settings');
  return response.data || [];
};

export const fetchAppSetting = async (key: string): Promise<AppSetting | null> => {
  try {
    const response = await api.get(`/administration/app-settings/${key}`);
    return response.data || null;
  } catch {
    return null;
  }
};

export const upsertAppSetting = async (key: string, payload: UpsertAppSettingPayload): Promise<AppSetting> => {
  const response = await api.put(`/administration/app-settings/${key}`, payload);
  return response.data;
};

export const upsertAppSettings = async (
  entries: Array<{ key: string; value: string | null; description?: string }>,
): Promise<AppSetting[]> => {
  const response = await api.put('/administration/app-settings', { entries });
  return response.data || [];
};

export const uploadThemeBackgroundImage = async (file: File): Promise<{ imagePath: string }> => {
  const formData = new FormData();
  formData.append('file', file);
  const response = await api.post('/administration/app-settings/theme-background', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return response.data;
};

export const fetchDirectionTypes = async (): Promise<DirectionType[]> => {
  const response = await api.get('/administration/direction-types');
  return response.data || [];
};

export const createDirectionType = async (
  payload: CreateDirectionTypePayload,
): Promise<DirectionType> => {
  const response = await api.post('/administration/direction-types', payload);
  return response.data;
};

export const updateDirectionType = async (
  id: string,
  payload: UpdateDirectionTypePayload,
): Promise<DirectionType> => {
  const response = await api.put(`/administration/direction-types/${id}`, payload);
  return response.data;
};

export const deleteDirectionType = async (id: string): Promise<void> => {
  await api.delete(`/administration/direction-types/${id}`);
};

export const fetchTemplates = async (): Promise<DocumentTemplate[]> => {
  const response = await api.get('/administration/templates');
  return response.data || [];
};

export const createTemplate = async (payload: CreateTemplatePayload): Promise<DocumentTemplate> => {
  const response = await api.post('/administration/templates', payload);
  return response.data;
};

export const updateTemplate = async (id: string, payload: UpdateTemplatePayload): Promise<DocumentTemplate> => {
  const response = await api.put(`/administration/templates/${id}`, payload);
  return response.data;
};

export const deleteTemplate = async (id: string): Promise<void> => {
  await api.delete(`/administration/templates/${id}`);
};

export const generateTemplateDocument = async (
  id: string,
  payload: GenerateTemplateDocumentPayload,
): Promise<GeneratedTemplateDocument> => {
  const response = await api.post(`/administration/templates/${id}/generate`, payload);
  return response.data;
};

export const fetchTemplateVariables = async (templateId: string): Promise<TemplateVariable[]> => {
  const response = await api.get(`/administration/templates/${templateId}/variables`);
  return response.data || [];
};

export const createTemplateVariable = async (
  templateId: string,
  payload: CreateTemplateVariablePayload,
): Promise<TemplateVariable> => {
  const response = await api.post(`/administration/templates/${templateId}/variables`, payload);
  return response.data;
};

export const updateTemplateVariable = async (
  templateId: string,
  variableId: string,
  payload: UpdateTemplateVariablePayload,
): Promise<TemplateVariable> => {
  const response = await api.put(`/administration/templates/${templateId}/variables/${variableId}`, payload);
  return response.data;
};

export const deleteTemplateVariable = async (templateId: string, variableId: string): Promise<void> => {
  await api.delete(`/administration/templates/${templateId}/variables/${variableId}`);
};

export const fetchIssuingAdministrations = async (): Promise<IssuingAdministration[]> => {
  const response = await api.get('/administration/emitters');
  return response.data || [];
};

export const createIssuingAdministration = async (
  payload: CreateIssuingAdministrationPayload,
): Promise<IssuingAdministration> => {
  const response = await api.post('/administration/emitters', payload);
  return response.data;
};

export const updateIssuingAdministration = async (
  id: string,
  payload: UpdateIssuingAdministrationPayload,
): Promise<IssuingAdministration> => {
  const response = await api.put(`/administration/emitters/${id}`, payload);
  return response.data;
};

export const deleteIssuingAdministration = async (id: string): Promise<void> => {
  await api.delete(`/administration/emitters/${id}`);
};

export const uploadAdministrationLogo = async (
  id: string,
  file: File,
): Promise<IssuingAdministration> => {
  const formData = new FormData();
  formData.append('file', file);
  const response = await api.post(`/administration/emitters/${id}/logo`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return response.data;
};

export const createAdministrationProfile = async (
  administrationId: string,
  payload: CreateAdministrationProfilePayload,
): Promise<AdministrationProfile> => {
  const response = await api.post(`/administration/emitters/${administrationId}/profiles`, payload);
  return response.data;
};

export const updateAdministrationProfile = async (
  administrationId: string,
  profileId: string,
  payload: UpdateAdministrationProfilePayload,
): Promise<AdministrationProfile> => {
  const response = await api.put(`/administration/emitters/${administrationId}/profiles/${profileId}`, payload);
  return response.data;
};

export const deleteAdministrationProfile = async (administrationId: string, profileId: string): Promise<void> => {
  await api.delete(`/administration/emitters/${administrationId}/profiles/${profileId}`);
};

export const createAdministrationUser = async (
  administrationId: string,
  payload: CreateAdministrationUserPayload,
): Promise<AdministrationUser> => {
  const response = await api.post(`/administration/emitters/${administrationId}/users`, payload);
  return response.data;
};

export const updateAdministrationUser = async (
  administrationId: string,
  userId: string,
  payload: UpdateAdministrationUserPayload,
): Promise<AdministrationUser> => {
  const response = await api.put(`/administration/emitters/${administrationId}/users/${userId}`, payload);
  return response.data;
};

export const deleteAdministrationUser = async (administrationId: string, userId: string): Promise<void> => {
  await api.delete(`/administration/emitters/${administrationId}/users/${userId}`);
};

export const fetchRecipientAdministrations = async (): Promise<RecipientAdministration[]> => {
  const response = await api.get('/administration/recipients');
  return response.data || [];
};

export const createRecipientAdministration = async (
  payload: CreateRecipientAdministrationPayload,
): Promise<RecipientAdministration> => {
  const response = await api.post('/administration/recipients', payload);
  return response.data;
};

export const updateRecipientAdministration = async (
  id: string,
  payload: UpdateRecipientAdministrationPayload,
): Promise<RecipientAdministration> => {
  const response = await api.put(`/administration/recipients/${id}`, payload);
  return response.data;
};

export const deleteRecipientAdministration = async (id: string): Promise<void> => {
  await api.delete(`/administration/recipients/${id}`);
};

export const uploadRecipientAdministrationLogo = async (
  id: string,
  file: File,
): Promise<RecipientAdministration> => {
  const formData = new FormData();
  formData.append('file', file);
  const response = await api.post(`/administration/recipients/${id}/logo`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return response.data;
};

export const fetchRoutingRules = async (): Promise<RoutingRule[]> => {
  const response = await api.get('/administration/routing-rules');
  return response.data || [];
};

export const createRoutingRule = async (payload: CreateRoutingRulePayload): Promise<RoutingRule> => {
  const response = await api.post('/administration/routing-rules', payload);
  return response.data;
};

export const updateRoutingRule = async (
  id: string,
  payload: UpdateRoutingRulePayload,
): Promise<RoutingRule> => {
  const response = await api.put(`/administration/routing-rules/${id}`, payload);
  return response.data;
};

export const deleteRoutingRule = async (id: string): Promise<void> => {
  await api.delete(`/administration/routing-rules/${id}`);
};

export const fetchSignatureProviderConfig = async (
  administrationId?: string,
): Promise<SignatureProviderConfig> => {
  const response = await api.get('/administration/signature-provider-config', {
    params: administrationId ? { administrationId } : undefined,
  });
  return response.data;
};

export const updateSignatureProviderConfig = async (
  payload: UpdateSignatureProviderConfigPayload,
): Promise<SignatureProviderConfig> => {
  const response = await api.put('/administration/signature-provider-config', payload);
  return response.data;
};

export const fetchNotificationConfigByAdministration = async (
  administrationId: string,
): Promise<NotificationConfig> => {
  const response = await api.get(`/administration/emitters/${administrationId}/notification-config`);
  return response.data;
};

export const updateNotificationConfigByAdministration = async (
  administrationId: string,
  payload: UpdateNotificationConfigPayload,
): Promise<NotificationConfig> => {
  const response = await api.put(
    `/administration/emitters/${administrationId}/notification-config`,
    payload,
  );
  return response.data;
};

export const fetchRequestedActs = async (): Promise<RequestedAct[]> => {
  const response = await api.get('/administration/requested-acts');
  return response.data || [];
};

export const createRequestedAct = async (
  payload: CreateRequestedActPayload,
): Promise<RequestedAct> => {
  const response = await api.post('/administration/requested-acts', payload);
  return response.data;
};

export const updateRequestedAct = async (
  id: string,
  payload: CreateRequestedActPayload,
): Promise<RequestedAct> => {
  const response = await api.put(`/administration/requested-acts/${id}`, payload);
  return response.data;
};

export const deleteRequestedAct = async (id: string): Promise<void> => {
  await api.delete(`/administration/requested-acts/${id}`);
};
