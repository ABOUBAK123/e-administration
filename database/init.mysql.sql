-- =============================================================
-- E-Parapheur Connect & Sign - Initialisation MySQL/phpMyAdmin
-- Compatible MySQL 8+ (InnoDB, utf8mb4)
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS e_parapheur
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE e_parapheur;

-- -----------------------------------------------------------
-- T01 - users
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id CHAR(36) NOT NULL,
  username VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  passwordHash VARCHAR(255) NOT NULL,
  fullName VARCHAR(255) NOT NULL,
  avatar VARCHAR(500) DEFAULT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'user',
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  quota VARCHAR(50) NOT NULL DEFAULT '5 Go',
  bio TEXT DEFAULT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deletedAt TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_users_username (username),
  UNIQUE KEY UQ_users_email (email),
  KEY IDX_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T02 - issuing_administrations
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS issuing_administrations (
  id CHAR(36) NOT NULL,
  name VARCHAR(255) NOT NULL,
  code VARCHAR(100) NOT NULL,
  isActive TINYINT(1) NOT NULL DEFAULT 1,
  documentNumberPrefix VARCHAR(50) NOT NULL DEFAULT 'DOC',
  documentNumberPadding INT NOT NULL DEFAULT 6,
  documentNumberSequence INT NOT NULL DEFAULT 0,
  logo VARCHAR(500) DEFAULT NULL,
  metadata JSON DEFAULT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_issuing_admin_name (name),
  UNIQUE KEY UQ_issuing_admin_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T03 - recipient_administrations
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS recipient_administrations (
  id CHAR(36) NOT NULL,
  name VARCHAR(255) NOT NULL,
  channel VARCHAR(20) NOT NULL,
  apiEndpoint VARCHAR(1000) DEFAULT NULL,
  emailAddress VARCHAR(255) DEFAULT NULL,
  metadata JSON DEFAULT NULL,
  isActive TINYINT(1) NOT NULL DEFAULT 1,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_recipient_admin_name (name),
  KEY IDX_recipient_admin_channel (channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T04 - administration_profiles
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS administration_profiles (
  id CHAR(36) NOT NULL,
  administrationId CHAR(36) NOT NULL,
  name VARCHAR(150) NOT NULL,
  permissions JSON DEFAULT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY IDX_admin_profiles_administrationId (administrationId),
  KEY IDX_admin_profiles_name (name),
  CONSTRAINT FK_admin_profiles_administration
    FOREIGN KEY (administrationId)
    REFERENCES issuing_administrations(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T05 - administration_users
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS administration_users (
  id CHAR(36) NOT NULL,
  administrationId CHAR(36) NOT NULL,
  profileId CHAR(36) DEFAULT NULL,
  fullName VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  username VARCHAR(150) NOT NULL,
  adminRole VARCHAR(50) NOT NULL DEFAULT 'user',
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_admin_users_email (email),
  UNIQUE KEY UQ_admin_users_username (username),
  KEY IDX_admin_users_administrationId (administrationId),
  CONSTRAINT FK_admin_users_administration
    FOREIGN KEY (administrationId)
    REFERENCES issuing_administrations(id)
    ON DELETE CASCADE,
  CONSTRAINT FK_admin_users_profile
    FOREIGN KEY (profileId)
    REFERENCES administration_profiles(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T05B - user_direction_assignments
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_direction_assignments (
  id CHAR(36) NOT NULL,
  userId CHAR(36) NOT NULL,
  directionScopeType VARCHAR(20) DEFAULT NULL,
  directionScopeId VARCHAR(120) DEFAULT NULL,
  directionLabel VARCHAR(255) NOT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_user_direction_assignments_userId (userId),
  KEY IDX_user_direction_assignments_scopeType (directionScopeType),
  KEY IDX_user_direction_assignments_scopeId (directionScopeId),
  CONSTRAINT FK_user_direction_assignments_user
    FOREIGN KEY (userId)
    REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T06 - document_templates
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS document_templates (
  id CHAR(36) NOT NULL,
  name VARCHAR(255) NOT NULL,
  fileName VARCHAR(255) NOT NULL,
  fileType VARCHAR(20) NOT NULL,
  storagePath VARCHAR(1000) DEFAULT NULL,
  content LONGTEXT DEFAULT NULL,
  administrationId CHAR(36) DEFAULT NULL,
  createdBy CHAR(36) DEFAULT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY IDX_doc_templates_name (name),
  KEY IDX_doc_templates_fileType (fileType),
  KEY IDX_doc_templates_administrationId (administrationId),
  CONSTRAINT FK_doc_templates_administration
    FOREIGN KEY (administrationId)
    REFERENCES issuing_administrations(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T07 - template_variables
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS template_variables (
  id CHAR(36) NOT NULL,
  templateId CHAR(36) NOT NULL,
  `key` VARCHAR(150) NOT NULL,
  label VARCHAR(255) NOT NULL,
  fieldType VARCHAR(50) NOT NULL DEFAULT 'text',
  required TINYINT(1) NOT NULL DEFAULT 0,
  placeholder VARCHAR(500) DEFAULT NULL,
  defaultValue VARCHAR(500) DEFAULT NULL,
  options JSON DEFAULT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY IDX_template_vars_templateId (templateId),
  KEY IDX_template_vars_key (`key`),
  CONSTRAINT FK_template_vars_template
    FOREIGN KEY (templateId)
    REFERENCES document_templates(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T08 - routing_rules
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS routing_rules (
  id CHAR(36) NOT NULL,
  name VARCHAR(255) NOT NULL,
  documentType VARCHAR(100) NOT NULL,
  templateId CHAR(36) DEFAULT NULL,
  recipientAdministrationId CHAR(36) NOT NULL,
  conditions JSON DEFAULT NULL,
  priority INT NOT NULL DEFAULT 1,
  isActive TINYINT(1) NOT NULL DEFAULT 1,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY IDX_routing_rules_documentType (documentType),
  KEY IDX_routing_rules_recipientAdministrationId (recipientAdministrationId),
  KEY IDX_routing_rules_priority (priority),
  CONSTRAINT FK_routing_rules_recipient
    FOREIGN KEY (recipientAdministrationId)
    REFERENCES recipient_administrations(id)
    ON DELETE CASCADE,
  CONSTRAINT FK_routing_rules_template
    FOREIGN KEY (templateId)
    REFERENCES document_templates(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T09 - workflows
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS workflows (
  id CHAR(36) NOT NULL,
  name VARCHAR(500) NOT NULL,
  description TEXT DEFAULT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  docsToSign JSON DEFAULT NULL,
  attachedDocs JSON DEFAULT NULL,
  uploadedSignatureFiles JSON DEFAULT NULL,
  createdBy CHAR(36) NOT NULL,
  creatorId CHAR(36) DEFAULT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY IDX_workflows_createdBy (createdBy),
  KEY IDX_workflows_status (status),
  CONSTRAINT FK_workflows_creator
    FOREIGN KEY (creatorId)
    REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T10 - workflow_steps
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS workflow_steps (
  id CHAR(36) NOT NULL,
  workflowId CHAR(36) NOT NULL,
  `order` INT NOT NULL,
  type VARCHAR(100) NOT NULL,
  assigneeId CHAR(36) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  requiresSignature TINYINT(1) NOT NULL DEFAULT 0,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_workflow_steps_order (workflowId, `order`),
  KEY IDX_workflow_steps_workflowId (workflowId),
  KEY IDX_workflow_steps_order (`order`),
  CONSTRAINT FK_workflow_steps_workflow
    FOREIGN KEY (workflowId)
    REFERENCES workflows(id)
    ON DELETE CASCADE,
  CONSTRAINT FK_workflow_steps_assignee
    FOREIGN KEY (assigneeId)
    REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T10B - workflow_templates
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS workflow_templates (
  id CHAR(36) NOT NULL,
  administrationId CHAR(36) NOT NULL,
  name VARCHAR(500) NOT NULL,
  description TEXT DEFAULT NULL,
  validationSteps JSON DEFAULT NULL,
  signatureSteps JSON DEFAULT NULL,
  notificationConfig JSON DEFAULT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  createdBy CHAR(36) NOT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY IDX_workflow_templates_administrationId (administrationId),
  KEY IDX_workflow_templates_createdBy (createdBy),
  KEY IDX_workflow_templates_status (status),
  CONSTRAINT FK_workflow_templates_administration
    FOREIGN KEY (administrationId)
    REFERENCES issuing_administrations(id)
    ON DELETE CASCADE,
  CONSTRAINT FK_workflow_templates_createdBy
    FOREIGN KEY (createdBy)
    REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T11 - documents
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS documents (
  id CHAR(36) NOT NULL,
  title VARCHAR(500) NOT NULL,
  description TEXT DEFAULT NULL,
  filePath VARCHAR(1000) NOT NULL,
  fileSize BIGINT NOT NULL,
  mimeType VARCHAR(100) NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'draft',
  createdBy CHAR(36) NOT NULL,
  ownerId CHAR(36) NOT NULL,
  issuingAdministrationId CHAR(36) DEFAULT NULL,
  recipientAdministrationId CHAR(36) DEFAULT NULL,
  documentNumber VARCHAR(120) DEFAULT NULL,
  subEntityCode VARCHAR(100) DEFAULT NULL,
  signedAt TIMESTAMP NULL DEFAULT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deletedAt TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_documents_issuing_number (issuingAdministrationId, documentNumber),
  KEY IDX_documents_ownerId (ownerId),
  KEY IDX_documents_status (status),
  KEY IDX_documents_createdAt (createdAt),
  KEY IDX_documents_recipientAdministrationId (recipientAdministrationId),
  CONSTRAINT FK_documents_owner
    FOREIGN KEY (ownerId)
    REFERENCES users(id)
    ON DELETE RESTRICT,
  CONSTRAINT FK_documents_recipient_administration
    FOREIGN KEY (recipientAdministrationId)
    REFERENCES recipient_administrations(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T12 - document_versions
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS document_versions (
  id CHAR(36) NOT NULL,
  documentId CHAR(36) NOT NULL,
  version INT NOT NULL,
  filePath VARCHAR(1000) NOT NULL,
  creatorId CHAR(36) NOT NULL,
  changeLog TEXT DEFAULT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY IDX_doc_versions_documentId (documentId),
  KEY IDX_doc_versions_version (version),
  CONSTRAINT FK_doc_versions_document
    FOREIGN KEY (documentId)
    REFERENCES documents(id)
    ON DELETE CASCADE,
  CONSTRAINT FK_doc_versions_creator
    FOREIGN KEY (creatorId)
    REFERENCES users(id)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T13 - signatures
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS signatures (
  id CHAR(36) NOT NULL,
  documentId CHAR(36) NOT NULL,
  signerId CHAR(36) NOT NULL,
  signature LONGBLOB NOT NULL,
  certificate TEXT DEFAULT NULL,
  `timestamp` TIMESTAMP NOT NULL,
  reason VARCHAR(500) DEFAULT NULL,
  location VARCHAR(500) DEFAULT NULL,
  isValid TINYINT(1) NOT NULL DEFAULT 1,
  status VARCHAR(50) NOT NULL DEFAULT 'valid',
  signatureAlgorithm VARCHAR(100) DEFAULT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY IDX_signatures_documentId (documentId),
  KEY IDX_signatures_signerId (signerId),
  KEY IDX_signatures_status (status),
  CONSTRAINT FK_signatures_document
    FOREIGN KEY (documentId)
    REFERENCES documents(id)
    ON DELETE CASCADE,
  CONSTRAINT FK_signatures_signer
    FOREIGN KEY (signerId)
    REFERENCES users(id)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T14 - signature_requests
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS signature_requests (
  id CHAR(36) NOT NULL,
  documentId CHAR(36) NOT NULL,
  requestedBy CHAR(36) NOT NULL,
  requestedTo CHAR(36) NOT NULL,
  message TEXT DEFAULT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'pending',
  expiryDate TIMESTAMP NOT NULL,
  respondedAt TIMESTAMP NULL DEFAULT NULL,
  requesterId CHAR(36) DEFAULT NULL,
  recipientId CHAR(36) DEFAULT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY IDX_sig_requests_documentId (documentId),
  KEY IDX_sig_requests_status (status),
  KEY IDX_sig_requests_expiryDate (expiryDate),
  CONSTRAINT FK_sig_requests_document
    FOREIGN KEY (documentId)
    REFERENCES documents(id)
    ON DELETE CASCADE,
  CONSTRAINT FK_sig_requests_requester
    FOREIGN KEY (requesterId)
    REFERENCES users(id)
    ON DELETE SET NULL,
  CONSTRAINT FK_sig_requests_recipient
    FOREIGN KEY (recipientId)
    REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T15 - qr_codes
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS qr_codes (
  id CHAR(36) NOT NULL,
  documentId CHAR(36) NOT NULL,
  data TEXT NOT NULL,
  metadata JSON DEFAULT NULL,
  verificationCode VARCHAR(255) NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  scanCount INT NOT NULL DEFAULT 0,
  createdBy CHAR(36) NOT NULL,
  creatorId CHAR(36) DEFAULT NULL,
  expiresAt TIMESTAMP NOT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_qr_codes_verificationCode (verificationCode),
  KEY IDX_qr_codes_documentId (documentId),
  KEY IDX_qr_codes_status (status),
  CONSTRAINT FK_qr_codes_document
    FOREIGN KEY (documentId)
    REFERENCES documents(id)
    ON DELETE CASCADE,
  CONSTRAINT FK_qr_codes_creator
    FOREIGN KEY (creatorId)
    REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T16 - workflow_executions
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS workflow_executions (
  id CHAR(36) NOT NULL,
  workflowId CHAR(36) NOT NULL,
  documentId CHAR(36) NOT NULL,
  currentStep INT NOT NULL DEFAULT 1,
  status VARCHAR(50) NOT NULL DEFAULT 'in_progress',
  stepData JSON DEFAULT NULL,
  startedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completedAt TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY IDX_wf_executions_workflowId (workflowId),
  KEY IDX_wf_executions_documentId (documentId),
  KEY IDX_wf_executions_status (status),
  CONSTRAINT FK_wf_executions_workflow
    FOREIGN KEY (workflowId)
    REFERENCES workflows(id)
    ON DELETE RESTRICT,
  CONSTRAINT FK_wf_executions_document
    FOREIGN KEY (documentId)
    REFERENCES documents(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T17 - signature_provider_configs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS signature_provider_configs (
  id CHAR(36) NOT NULL,
  administrationId CHAR(36) DEFAULT NULL,
  isActive TINYINT(1) NOT NULL DEFAULT 0,
  endpoint VARCHAR(500) DEFAULT NULL,
  signPath VARCHAR(500) DEFAULT NULL,
  apiKey VARCHAR(500) DEFAULT NULL,
  consentPageId VARCHAR(255) DEFAULT NULL,
  signatureProfileId VARCHAR(255) DEFAULT NULL,
  providerOwnerUserId VARCHAR(255) DEFAULT NULL,
  verifySsl TINYINT(1) NOT NULL DEFAULT 1,
  timeoutMs INT NOT NULL DEFAULT 30000,
  metadata JSON DEFAULT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY IDX_sig_provider_isActive (isActive),
  KEY IDX_sig_provider_administrationId (administrationId),
  CONSTRAINT FK_sig_provider_administration
    FOREIGN KEY (administrationId)
    REFERENCES issuing_administrations(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T18 - notification_configs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS notification_configs (
  id CHAR(36) NOT NULL,
  administrationId CHAR(36) NOT NULL,
  isActive TINYINT(1) NOT NULL DEFAULT 1,
  smtpHost VARCHAR(255) DEFAULT NULL,
  smtpPort INT NOT NULL DEFAULT 587,
  smtpSecure TINYINT(1) NOT NULL DEFAULT 0,
  smtpUser VARCHAR(255) DEFAULT NULL,
  smtpPassword VARCHAR(500) DEFAULT NULL,
  smtpFrom VARCHAR(255) DEFAULT NULL,
  triggers JSON DEFAULT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_notification_configs_administrationId (administrationId),
  CONSTRAINT FK_notification_configs_administration
    FOREIGN KEY (administrationId)
    REFERENCES issuing_administrations(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T19 - direction_types
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS direction_types (
  id CHAR(36) NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT DEFAULT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_direction_types_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T18 - requested_acts
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS requested_acts (
  id CHAR(36) NOT NULL,
  administrationScopeType VARCHAR(20) NOT NULL,
  administrationScopeId CHAR(36) NOT NULL,
  administrationLabel VARCHAR(255) NOT NULL,
  directionCode VARCHAR(120) NOT NULL,
  directionLabel VARCHAR(255) NOT NULL,
  documentName VARCHAR(500) NOT NULL,
  requiredDocuments JSON NOT NULL,
  createdBy CHAR(36) DEFAULT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY IDX_requested_acts_scope (administrationScopeType, administrationScopeId),
  KEY IDX_requested_acts_directionCode (directionCode),
  KEY IDX_requested_acts_createdAt (createdAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T20 - app_settings
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS app_settings (
  id CHAR(36) NOT NULL,
  `key` VARCHAR(100) NOT NULL,
  value TEXT DEFAULT NULL,
  description VARCHAR(255) DEFAULT NULL,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_app_settings_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- T21 - audit_logs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
  id CHAR(36) NOT NULL,
  userId CHAR(36) DEFAULT NULL,
  action VARCHAR(255) NOT NULL,
  entityType VARCHAR(100) NOT NULL,
  entityId CHAR(36) NOT NULL,
  changes JSON DEFAULT NULL,
  ipAddress VARCHAR(50) DEFAULT NULL,
  userAgent VARCHAR(500) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY IDX_audit_logs_userId (userId),
  KEY IDX_audit_logs_action (action),
  KEY IDX_audit_logs_entity (entityType, entityId),
  KEY IDX_audit_logs_createdAt (createdAt),
  CONSTRAINT FK_audit_logs_user
    FOREIGN KEY (userId)
    REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- T22 - notifications (in-app)
-- =============================================================
CREATE TABLE IF NOT EXISTS notifications (
  id CHAR(36) NOT NULL,
  recipientId CHAR(36) NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  type VARCHAR(50) NOT NULL DEFAULT 'info',
  workflowId CHAR(36) DEFAULT NULL,
  executionId CHAR(36) DEFAULT NULL,
  actionUrl VARCHAR(512) DEFAULT NULL,
  isRead TINYINT(1) NOT NULL DEFAULT 0,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY IDX_notifications_recipientId (recipientId),
  KEY IDX_notifications_isRead (isRead)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- DONNEES INITIALES (SEED)
-- =============================================================

-- Signature provider (global default) for batch signing from Signatures tab.
UPDATE signature_provider_configs
SET
  isActive = 1,
  endpoint = 'https://uvci.artci-sign.ci/api/',
  apiKey = 'act_38Xcy1gjrQ9jTUfozSvpWYMi.3aq7VsWt8GS5ySwBX3Zn4yxF4fS1B1ZACDfE2jzcZzFwixrjokeu6TzrDfq6ivJr',
  consentPageId = 'cop_MFPnJ1A1qj9saiPvbA8stjB2',
  signatureProfileId = 'sip_GqGWkYmLrqvSddX6NsxVbEmx',
  verifySsl = 1,
  timeoutMs = 30000,
  updatedAt = CURRENT_TIMESTAMP
WHERE administrationId IS NULL;

INSERT INTO signature_provider_configs (
  id,
  administrationId,
  isActive,
  endpoint,
  signPath,
  apiKey,
  consentPageId,
  signatureProfileId,
  providerOwnerUserId,
  verifySsl,
  timeoutMs,
  metadata,
  createdAt,
  updatedAt
)
SELECT
  UUID(),
  NULL,
  1,
  'https://uvci.artci-sign.ci/api/',
  NULL,
  'act_38Xcy1gjrQ9jTUfozSvpWYMi.3aq7VsWt8GS5ySwBX3Zn4yxF4fS1B1ZACDfE2jzcZzFwixrjokeu6TzrDfq6ivJr',
  'cop_MFPnJ1A1qj9saiPvbA8stjB2',
  'sip_GqGWkYmLrqvSddX6NsxVbEmx',
  NULL,
  1,
  30000,
  NULL,
  CURRENT_TIMESTAMP,
  CURRENT_TIMESTAMP
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1
  FROM signature_provider_configs
  WHERE administrationId IS NULL
);

INSERT INTO users (
  id, username, email, passwordHash, fullName, role, status
)
SELECT
  UUID(),
  'admin',
  'admin@e-parapheur.ci',
  '$2a$10$o2DhWU1bP4nTxLCL7JneUuxjEd9uo9YRW1dh4nPjO8J9VLJmtgYQi',
  'Administrateur Systeme',
  'admin',
  'active'
WHERE NOT EXISTS (
  SELECT 1 FROM users WHERE username = 'admin'
);

INSERT INTO signature_provider_configs (id, isActive)
SELECT UUID(), 0
WHERE NOT EXISTS (
  SELECT 1 FROM signature_provider_configs
);

INSERT INTO direction_types (id, name, description)
SELECT UUID(), seed.name, NULL
FROM (
  SELECT 'Direction Générale' AS name
  UNION ALL SELECT 'Direction Centrale'
  UNION ALL SELECT 'Direction Régionale'
  UNION ALL SELECT 'Service'
  UNION ALL SELECT 'Division'
  UNION ALL SELECT 'Bureau'
) AS seed
WHERE NOT EXISTS (
  SELECT 1 FROM direction_types existing WHERE existing.name = seed.name
);

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- FIN
-- =============================================================
