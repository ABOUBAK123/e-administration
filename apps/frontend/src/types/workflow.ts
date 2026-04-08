export interface WorkflowStep {
  id?: string;
  name: string;
  approverId: string;
  order: number;
  assigneeId?: string;
  description?: string;
  requiresSignature?: boolean;
}

export interface WorkflowSignatureZone {
  x: number;
  y: number;
  width: number;
  height: number;
}

export interface WorkflowUploadedFileSignatureConfig {
  fileName: string;
  fileSize: number;
  fileType: string;
  zones: WorkflowSignatureZone[];
}

export interface WorkflowTemplateAssignee {
  id: number;
  approverId?: string;
  signerId?: string;
}

export interface WorkflowNotificationStages {
  onValidationStep: boolean;
  onSignatureStep: boolean;
  onApproved: boolean;
  onRejected: boolean;
  onCompleted: boolean;
}

export interface WorkflowNotificationConfig {
  notifyEmail: boolean;
  emails: string;
  cc: string;
  stages: WorkflowNotificationStages;
  sendDownloadLink: boolean;
}

export interface WorkflowTemplateItem {
  id: string;
  administrationId: string;
  name: string;
  description?: string | null;
  validationSteps: WorkflowTemplateAssignee[];
  signatureSteps: WorkflowTemplateAssignee[];
  notificationConfig: WorkflowNotificationConfig | null;
  status: 'active' | 'archived';
  createdBy: string;
  createdAt: string;
  updatedAt: string;
}

export interface CreateWorkflowTemplatePayload {
  name: string;
  description?: string;
  validationSteps: WorkflowTemplateAssignee[];
  signatureSteps: WorkflowTemplateAssignee[];
  notificationConfig: WorkflowNotificationConfig;
}

export interface WorkflowItem {
  id: string;
  name: string;
  description?: string;
  steps: WorkflowStep[];
  docsToSign?: string[];
  attachedDocs?: string[];
  uploadedSignatureFiles?: WorkflowUploadedFileSignatureConfig[];
  createdBy: string;
  creator?: {
    id: string;
    fullName?: string;
    username?: string;
    email?: string;
    avatar?: string;
  };
  executions?: WorkflowExecution[];
  createdAt: string;
  updatedAt?: string;
  status?: 'active' | 'draft' | 'archived';
}

export interface WorkflowExecution {
  id: string;
  workflowId: string;
  documentId: string;
  currentStep: number;
  status: 'pending' | 'in_progress' | 'completed' | 'rejected';
  createdAt?: string;
  startedAt?: string;
  completedAt?: string;
}

export interface WorkflowDetails extends WorkflowItem {
  executions?: WorkflowExecution[];
}

export interface CreateWorkflowPayload {
  name: string;
  description?: string;
  steps: { name: string; approverId: string; order: number }[];
  docsToSign?: string[];
  attachedDocs?: string[];
  uploadedSignatureFiles?: WorkflowUploadedFileSignatureConfig[];
}

export interface ExecuteWorkflowPayload {
  documentId: string;
}
