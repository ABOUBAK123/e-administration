import api from './api';
import {
  WorkflowItem,
  WorkflowExecution,
  CreateWorkflowPayload,
  WorkflowDetails,
  WorkflowTemplateItem,
  CreateWorkflowTemplatePayload,
} from '../types/workflow';

export const fetchWorkflows = async (): Promise<WorkflowItem[]> => {
  const response = await api.get('/workflows');
  return response.data;
};

export const fetchWorkflowDetails = async (workflowId: string): Promise<WorkflowDetails> => {
  const response = await api.get(`/workflows/${workflowId}`);
  return response.data;
};

export const fetchWorkflowTemplates = async (): Promise<WorkflowTemplateItem[]> => {
  const response = await api.get('/workflows/templates');
  return response.data;
};

export const createWorkflowTemplate = async (
  payload: CreateWorkflowTemplatePayload,
): Promise<WorkflowTemplateItem> => {
  const response = await api.post('/workflows/templates', payload);
  return response.data;
};

export const createWorkflow = async (payload: CreateWorkflowPayload): Promise<WorkflowItem> => {
  const response = await api.post('/workflows', payload);
  return response.data;
};

export const executeWorkflow = async (workflowId: string, documentId: string): Promise<WorkflowExecution> => {
  const response = await api.post(`/workflows/${workflowId}/execute`, { documentId });
  return response.data;
};

export const advanceWorkflow = async (
  executionId: string,
  stepIndex: number,
  decision?: string,
): Promise<WorkflowExecution> => {
  const response = await api.post(`/workflows/execution/${executionId}/advance`, { stepIndex, decision });
  return response.data;
};

export const performWorkflowStepAction = async (
  executionId: string,
  action: 'signature' | 'validation',
): Promise<WorkflowExecution> => {
  const response = await api.post(`/workflows/execution/${executionId}/action`, { action });
  return response.data;
};

export const rejectWorkflow = async (executionId: string, reason?: string): Promise<WorkflowExecution> => {
  const response = await api.post(`/workflows/execution/${executionId}/reject`, { reason });
  return response.data;
};

export const deleteWorkflow = async (workflowId: string): Promise<void> => {
  await api.delete(`/workflows/${workflowId}`);
};
