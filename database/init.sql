-- =============================================================
-- E-Parapheur Connect & Sign — Initialisation Complète
-- PostgreSQL 14+  |  Encodage : UTF-8
--
-- Usage (superutilisateur postgres) :
--   psql -U postgres -f database/init.sql
--
-- Ce script est idempotent : relançable sans risque.
-- Il crée le rôle, la base, le schéma complet et les données
-- initiales (admin + config signature vide).
--
-- Pour réinitialiser entièrement :
--   psql -U postgres -c "DROP DATABASE IF EXISTS e_parapheur"
--   puis relancer ce fichier.
-- =============================================================

\echo ''
\echo '======================================================'
\echo ' E-Parapheur — Installation de la base de données'
\echo '======================================================'
\echo ''

-- -------------------------------------------------------------
-- Rôle applicatif epAdmin
-- -------------------------------------------------------------
DO $$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'epAdmin') THEN
    CREATE ROLE "epAdmin"
      WITH LOGIN PASSWORD 'epPasswordDev2024'
      NOSUPERUSER NOCREATEDB NOCREATEROLE INHERIT NOREPLICATION;
    RAISE NOTICE '[OK] Rôle epAdmin créé.';
  ELSE
    RAISE NOTICE '[--] Rôle epAdmin existant.';
  END IF;
END $$;

-- -------------------------------------------------------------
-- Base de données  (la commande est ignorée si elle existe déjà)
-- -------------------------------------------------------------
SELECT 'CREATE DATABASE e_parapheur OWNER "epAdmin" ENCODING ''UTF8'' TEMPLATE template0'
WHERE NOT EXISTS (
    SELECT 1 FROM pg_database WHERE datname = 'e_parapheur'
) \gexec

GRANT ALL PRIVILEGES ON DATABASE e_parapheur TO "epAdmin";

-- Connexion à la base cible
\connect e_parapheur

-- Droits sur le schéma public
GRANT ALL ON SCHEMA public TO "epAdmin";
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES    TO "epAdmin";
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO "epAdmin";
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON FUNCTIONS TO "epAdmin";

-- Extension UUID (nécessite superutilisateur)
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

\echo '[OK] Extensions et droits configurés.'

-- =============================================================
-- TABLES — Ordre respectant les dépendances FK
-- =============================================================

-- -----------------------------------------------------------
-- T01 · users
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id             UUID         PRIMARY KEY DEFAULT uuid_generate_v4(),
    username       VARCHAR(255) NOT NULL UNIQUE,
    email          VARCHAR(255) NOT NULL UNIQUE,
    "passwordHash" VARCHAR(255) NOT NULL,
    "fullName"     VARCHAR(255) NOT NULL,
    avatar         VARCHAR(500),
    role           VARCHAR(50)  NOT NULL DEFAULT 'user',
    status         VARCHAR(50)  NOT NULL DEFAULT 'active',
    quota          VARCHAR(50)  NOT NULL DEFAULT '5 Go',
    bio            TEXT,
    "createdAt"    TIMESTAMP    NOT NULL DEFAULT now(),
    "updatedAt"    TIMESTAMP    NOT NULL DEFAULT now(),
    "deletedAt"    TIMESTAMP
);
CREATE INDEX IF NOT EXISTS "IDX_users_username" ON users (username);
CREATE INDEX IF NOT EXISTS "IDX_users_email"    ON users (email);
CREATE INDEX IF NOT EXISTS "IDX_users_status"   ON users (status);

-- -----------------------------------------------------------
-- T02 · issuing_administrations
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS issuing_administrations (
    id                       UUID         PRIMARY KEY DEFAULT uuid_generate_v4(),
    name                     VARCHAR(255) NOT NULL UNIQUE,
    code                     VARCHAR(100) NOT NULL UNIQUE,
    "isActive"               BOOLEAN      NOT NULL DEFAULT true,
    "documentNumberPrefix"   VARCHAR(50)  NOT NULL DEFAULT 'DOC',
    "documentNumberPadding"  INTEGER      NOT NULL DEFAULT 6,
    "documentNumberSequence" INTEGER      NOT NULL DEFAULT 0,
    logo                     VARCHAR(500),
    metadata                 JSON,
    "createdAt"              TIMESTAMP    NOT NULL DEFAULT now(),
    "updatedAt"              TIMESTAMP    NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS "IDX_issuing_admin_name" ON issuing_administrations (name);
CREATE INDEX IF NOT EXISTS "IDX_issuing_admin_code" ON issuing_administrations (code);

-- -----------------------------------------------------------
-- T03 · recipient_administrations
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS recipient_administrations (
    id             UUID         PRIMARY KEY DEFAULT uuid_generate_v4(),
    name           VARCHAR(255) NOT NULL UNIQUE,
    channel        VARCHAR(20)  NOT NULL,
    "apiEndpoint"  VARCHAR(1000),
    "emailAddress" VARCHAR(255),
    metadata       JSON,
    "isActive"     BOOLEAN      NOT NULL DEFAULT true,
    "createdAt"    TIMESTAMP    NOT NULL DEFAULT now(),
    "updatedAt"    TIMESTAMP    NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS "IDX_recipient_admin_name"    ON recipient_administrations (name);
CREATE INDEX IF NOT EXISTS "IDX_recipient_admin_channel" ON recipient_administrations (channel);

-- -----------------------------------------------------------
-- T04 · administration_profiles
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS administration_profiles (
    id                 UUID         PRIMARY KEY DEFAULT uuid_generate_v4(),
    "administrationId" UUID         NOT NULL,
    name               VARCHAR(150) NOT NULL,
    permissions        JSON,
    "createdAt"        TIMESTAMP    NOT NULL DEFAULT now(),
    "updatedAt"        TIMESTAMP    NOT NULL DEFAULT now(),

    CONSTRAINT "FK_admin_profiles_administration"
        FOREIGN KEY ("administrationId")
        REFERENCES issuing_administrations (id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS "IDX_admin_profiles_administrationId" ON administration_profiles ("administrationId");
CREATE INDEX IF NOT EXISTS "IDX_admin_profiles_name"             ON administration_profiles (name);

-- -----------------------------------------------------------
-- T05 · administration_users
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS administration_users (
    id                 UUID         PRIMARY KEY DEFAULT uuid_generate_v4(),
    "administrationId" UUID         NOT NULL,
    "profileId"        UUID,
    "fullName"         VARCHAR(255) NOT NULL,
    email              VARCHAR(255) NOT NULL UNIQUE,
    username           VARCHAR(150) NOT NULL UNIQUE,
    "adminRole"       VARCHAR(50)  NOT NULL DEFAULT 'user',
    status             VARCHAR(50)  NOT NULL DEFAULT 'active',
    "createdAt"        TIMESTAMP    NOT NULL DEFAULT now(),
    "updatedAt"        TIMESTAMP    NOT NULL DEFAULT now(),

    CONSTRAINT "FK_admin_users_administration"
        FOREIGN KEY ("administrationId")
        REFERENCES issuing_administrations (id) ON DELETE CASCADE,

    CONSTRAINT "FK_admin_users_profile"
        FOREIGN KEY ("profileId")
        REFERENCES administration_profiles (id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS "IDX_admin_users_administrationId" ON administration_users ("administrationId");
CREATE INDEX IF NOT EXISTS "IDX_admin_users_email"            ON administration_users (email);
CREATE INDEX IF NOT EXISTS "IDX_admin_users_username"         ON administration_users (username);

-- -----------------------------------------------------------
-- T06 · document_templates
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS document_templates (
    id                 UUID          PRIMARY KEY DEFAULT uuid_generate_v4(),
    name               VARCHAR(255)  NOT NULL,
    "fileName"         VARCHAR(255)  NOT NULL,
    "fileType"         VARCHAR(20)   NOT NULL,
    "storagePath"      VARCHAR(1000),
    content            TEXT,
    "administrationId" UUID,
    "createdBy"        UUID,
    "createdAt"        TIMESTAMP     NOT NULL DEFAULT now(),
    "updatedAt"        TIMESTAMP     NOT NULL DEFAULT now(),

    CONSTRAINT "FK_doc_templates_administration"
        FOREIGN KEY ("administrationId")
        REFERENCES issuing_administrations (id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS "IDX_doc_templates_name"             ON document_templates (name);
CREATE INDEX IF NOT EXISTS "IDX_doc_templates_fileType"         ON document_templates ("fileType");
CREATE INDEX IF NOT EXISTS "IDX_doc_templates_administrationId" ON document_templates ("administrationId");

-- -----------------------------------------------------------
-- T07 · template_variables
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS template_variables (
    id             UUID         PRIMARY KEY DEFAULT uuid_generate_v4(),
    "templateId"   UUID         NOT NULL,
    key            VARCHAR(150) NOT NULL,
    label          VARCHAR(255) NOT NULL,
    "fieldType"    VARCHAR(50)  NOT NULL DEFAULT 'text',
    required       BOOLEAN      NOT NULL DEFAULT false,
    placeholder    VARCHAR(500),
    "defaultValue" VARCHAR(500),
    options        JSON,
    "createdAt"    TIMESTAMP    NOT NULL DEFAULT now(),
    "updatedAt"    TIMESTAMP    NOT NULL DEFAULT now(),

    CONSTRAINT "FK_template_vars_template"
        FOREIGN KEY ("templateId")
        REFERENCES document_templates (id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS "IDX_template_vars_templateId" ON template_variables ("templateId");
CREATE INDEX IF NOT EXISTS "IDX_template_vars_key"        ON template_variables (key);

-- -----------------------------------------------------------
-- T08 · routing_rules
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS routing_rules (
    id                          UUID         PRIMARY KEY DEFAULT uuid_generate_v4(),
    name                        VARCHAR(255) NOT NULL,
    "documentType"              VARCHAR(100) NOT NULL,
    "templateId"                UUID,
    "recipientAdministrationId" UUID         NOT NULL,
    conditions                  JSON,
    priority                    INTEGER      NOT NULL DEFAULT 1,
    "isActive"                  BOOLEAN      NOT NULL DEFAULT true,
    "createdAt"                 TIMESTAMP    NOT NULL DEFAULT now(),
    "updatedAt"                 TIMESTAMP    NOT NULL DEFAULT now(),

    CONSTRAINT "FK_routing_rules_recipient"
        FOREIGN KEY ("recipientAdministrationId")
        REFERENCES recipient_administrations (id) ON DELETE CASCADE,

    CONSTRAINT "FK_routing_rules_template"
        FOREIGN KEY ("templateId")
        REFERENCES document_templates (id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS "IDX_routing_rules_documentType"              ON routing_rules ("documentType");
CREATE INDEX IF NOT EXISTS "IDX_routing_rules_recipientAdministrationId" ON routing_rules ("recipientAdministrationId");
CREATE INDEX IF NOT EXISTS "IDX_routing_rules_priority"                  ON routing_rules (priority);

-- -----------------------------------------------------------
-- T09 · workflows
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS workflows (
    id                       UUID         PRIMARY KEY DEFAULT uuid_generate_v4(),
    name                     VARCHAR(500) NOT NULL,
    description              TEXT,
    status                   VARCHAR(50)  NOT NULL DEFAULT 'active',
    "docsToSign"             JSONB        DEFAULT '[]'::jsonb,
    "attachedDocs"           JSONB        DEFAULT '[]'::jsonb,
    "uploadedSignatureFiles" JSONB        DEFAULT '[]'::jsonb,
    "createdBy"              UUID         NOT NULL,
    "creatorId"              UUID,
    "createdAt"              TIMESTAMP    NOT NULL DEFAULT now(),
    "updatedAt"              TIMESTAMP    NOT NULL DEFAULT now(),

    CONSTRAINT "FK_workflows_creator"
        FOREIGN KEY ("creatorId")
        REFERENCES users (id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS "IDX_workflows_createdBy" ON workflows ("createdBy");
CREATE INDEX IF NOT EXISTS "IDX_workflows_status"    ON workflows (status);

-- -----------------------------------------------------------
-- T10 · workflow_steps
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS workflow_steps (
    id                  UUID         PRIMARY KEY DEFAULT uuid_generate_v4(),
    "workflowId"        UUID         NOT NULL,
    "order"             INTEGER      NOT NULL,
    type                VARCHAR(100) NOT NULL,
    "assigneeId"        UUID,
    description         TEXT,
    "requiresSignature" BOOLEAN      NOT NULL DEFAULT false,
    "createdAt"         TIMESTAMP    NOT NULL DEFAULT now(),

    CONSTRAINT "UQ_workflow_steps_order" UNIQUE ("workflowId", "order"),

    CONSTRAINT "FK_workflow_steps_workflow"
        FOREIGN KEY ("workflowId")
        REFERENCES workflows (id) ON DELETE CASCADE,

    CONSTRAINT "FK_workflow_steps_assignee"
        FOREIGN KEY ("assigneeId")
        REFERENCES users (id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS "IDX_workflow_steps_workflowId" ON workflow_steps ("workflowId");
CREATE INDEX IF NOT EXISTS "IDX_workflow_steps_order"      ON workflow_steps ("order");

-- -----------------------------------------------------------
-- T10B · workflow_templates
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS workflow_templates (
    id                 UUID         PRIMARY KEY DEFAULT uuid_generate_v4(),
    "administrationId" UUID         NOT NULL,
    name               VARCHAR(500) NOT NULL,
    description        TEXT,
    "validationSteps" JSONB        DEFAULT '[]'::jsonb,
    "signatureSteps"  JSONB        DEFAULT '[]'::jsonb,
    "notificationConfig" JSONB     DEFAULT NULL,
    status             VARCHAR(50)  NOT NULL DEFAULT 'active',
    "createdBy"       UUID         NOT NULL,
    "createdAt"       TIMESTAMP    NOT NULL DEFAULT now(),
    "updatedAt"       TIMESTAMP    NOT NULL DEFAULT now(),

    CONSTRAINT "FK_workflow_templates_administration"
        FOREIGN KEY ("administrationId")
        REFERENCES issuing_administrations (id) ON DELETE CASCADE,

    CONSTRAINT "FK_workflow_templates_createdBy"
        FOREIGN KEY ("createdBy")
        REFERENCES users (id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS "IDX_workflow_templates_administrationId" ON workflow_templates ("administrationId");
CREATE INDEX IF NOT EXISTS "IDX_workflow_templates_createdBy" ON workflow_templates ("createdBy");
CREATE INDEX IF NOT EXISTS "IDX_workflow_templates_status" ON workflow_templates (status);

-- -----------------------------------------------------------
-- T11 · documents
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS documents (
    id                        UUID          PRIMARY KEY DEFAULT uuid_generate_v4(),
    title                     VARCHAR(500)  NOT NULL,
    description               TEXT,
    "filePath"                VARCHAR(1000) NOT NULL,
    "fileSize"                BIGINT        NOT NULL,
    "mimeType"                VARCHAR(100)  NOT NULL,
    status                    VARCHAR(50)   NOT NULL DEFAULT 'draft',
    "createdBy"               UUID          NOT NULL,
    "ownerId"                 UUID          NOT NULL,
    "issuingAdministrationId" UUID,
    "recipientAdministrationId" UUID,
    "documentNumber"          VARCHAR(120),
    "signedAt"                TIMESTAMP,
    "createdAt"               TIMESTAMP     NOT NULL DEFAULT now(),
    "updatedAt"               TIMESTAMP     NOT NULL DEFAULT now(),
    "deletedAt"               TIMESTAMP,

    CONSTRAINT "UQ_documents_issuing_number"
        UNIQUE ("issuingAdministrationId", "documentNumber"),

    CONSTRAINT "FK_documents_owner"
        FOREIGN KEY ("ownerId")
        REFERENCES users (id) ON DELETE RESTRICT,

    CONSTRAINT "FK_documents_recipient_administration"
        FOREIGN KEY ("recipientAdministrationId")
        REFERENCES recipient_administrations (id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS "IDX_documents_ownerId"   ON documents ("ownerId");
CREATE INDEX IF NOT EXISTS "IDX_documents_status"    ON documents (status);
CREATE INDEX IF NOT EXISTS "IDX_documents_createdAt" ON documents ("createdAt");
CREATE INDEX IF NOT EXISTS "IDX_documents_recipientAdministrationId" ON documents ("recipientAdministrationId");

-- -----------------------------------------------------------
-- T12 · document_versions
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS document_versions (
    id           UUID          PRIMARY KEY DEFAULT uuid_generate_v4(),
    "documentId" UUID          NOT NULL,
    version      INTEGER       NOT NULL,
    "filePath"   VARCHAR(1000) NOT NULL,
    "creatorId"  UUID          NOT NULL,
    "changeLog"  TEXT,
    "createdAt"  TIMESTAMP     NOT NULL DEFAULT now(),

    CONSTRAINT "FK_doc_versions_document"
        FOREIGN KEY ("documentId")
        REFERENCES documents (id) ON DELETE CASCADE,

    CONSTRAINT "FK_doc_versions_creator"
        FOREIGN KEY ("creatorId")
        REFERENCES users (id) ON DELETE RESTRICT
);
CREATE INDEX IF NOT EXISTS "IDX_doc_versions_documentId" ON document_versions ("documentId");
CREATE INDEX IF NOT EXISTS "IDX_doc_versions_version"    ON document_versions (version);

-- -----------------------------------------------------------
-- T13 · signatures
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS signatures (
    id                   UUID         PRIMARY KEY DEFAULT uuid_generate_v4(),
    "documentId"         UUID         NOT NULL,
    "signerId"           UUID         NOT NULL,
    signature            BYTEA        NOT NULL,
    certificate          TEXT,
    "timestamp"          TIMESTAMP    NOT NULL,
    reason               VARCHAR(500),
    location             VARCHAR(500),
    "isValid"            BOOLEAN      NOT NULL DEFAULT true,
    status               VARCHAR(50)  NOT NULL DEFAULT 'valid',
    "signatureAlgorithm" VARCHAR(100),
    "createdAt"          TIMESTAMP    NOT NULL DEFAULT now(),

    CONSTRAINT "FK_signatures_document"
        FOREIGN KEY ("documentId")
        REFERENCES documents (id) ON DELETE CASCADE,

    CONSTRAINT "FK_signatures_signer"
        FOREIGN KEY ("signerId")
        REFERENCES users (id) ON DELETE RESTRICT
);
CREATE INDEX IF NOT EXISTS "IDX_signatures_documentId" ON signatures ("documentId");
CREATE INDEX IF NOT EXISTS "IDX_signatures_signerId"   ON signatures ("signerId");
CREATE INDEX IF NOT EXISTS "IDX_signatures_status"     ON signatures (status);

-- -----------------------------------------------------------
-- T14 · signature_requests
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS signature_requests (
    id            UUID        PRIMARY KEY DEFAULT uuid_generate_v4(),
    "documentId"  UUID        NOT NULL,
    "requestedBy" UUID        NOT NULL,
    "requestedTo" UUID        NOT NULL,
    message       TEXT,
    status        VARCHAR(50) NOT NULL DEFAULT 'pending',
    "expiryDate"  TIMESTAMP   NOT NULL,
    "respondedAt" TIMESTAMP,
    -- Colonnes FK générées automatiquement par TypeORM via @ManyToOne
    "requesterId" UUID,
    "recipientId" UUID,
    "createdAt"   TIMESTAMP   NOT NULL DEFAULT now(),

    CONSTRAINT "FK_sig_requests_document"
        FOREIGN KEY ("documentId")
        REFERENCES documents (id) ON DELETE CASCADE,

    CONSTRAINT "FK_sig_requests_requester"
        FOREIGN KEY ("requesterId")
        REFERENCES users (id) ON DELETE SET NULL,

    CONSTRAINT "FK_sig_requests_recipient"
        FOREIGN KEY ("recipientId")
        REFERENCES users (id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS "IDX_sig_requests_documentId" ON signature_requests ("documentId");
CREATE INDEX IF NOT EXISTS "IDX_sig_requests_status"     ON signature_requests (status);
CREATE INDEX IF NOT EXISTS "IDX_sig_requests_expiryDate" ON signature_requests ("expiryDate");

-- -----------------------------------------------------------
-- T15 · qr_codes
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS qr_codes (
    id                 UUID         PRIMARY KEY DEFAULT uuid_generate_v4(),
    "documentId"       UUID         NOT NULL,
    data               TEXT         NOT NULL,
    metadata           JSON,
    "verificationCode" VARCHAR(255) NOT NULL UNIQUE,
    status             VARCHAR(50)  NOT NULL DEFAULT 'active',
    "scanCount"        INTEGER      NOT NULL DEFAULT 0,
    "createdBy"        UUID         NOT NULL,
    -- Colonne FK générée automatiquement par TypeORM via @ManyToOne creator
    "creatorId"        UUID,
    "expiresAt"        TIMESTAMP    NOT NULL,
    "createdAt"        TIMESTAMP    NOT NULL DEFAULT now(),

    CONSTRAINT "FK_qr_codes_document"
        FOREIGN KEY ("documentId")
        REFERENCES documents (id) ON DELETE CASCADE,

    CONSTRAINT "FK_qr_codes_creator"
        FOREIGN KEY ("creatorId")
        REFERENCES users (id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS "IDX_qr_codes_documentId"       ON qr_codes ("documentId");
CREATE INDEX IF NOT EXISTS "IDX_qr_codes_status"           ON qr_codes (status);
CREATE INDEX IF NOT EXISTS "IDX_qr_codes_verificationCode" ON qr_codes ("verificationCode");

-- -----------------------------------------------------------
-- T16 · workflow_executions
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS workflow_executions (
    id            UUID        PRIMARY KEY DEFAULT uuid_generate_v4(),
    "workflowId"  UUID        NOT NULL,
    "documentId"  UUID        NOT NULL,
    "currentStep" INTEGER     NOT NULL DEFAULT 1,
    status        VARCHAR(50) NOT NULL DEFAULT 'in_progress',
    "stepData"    JSON,
    "startedAt"   TIMESTAMP   NOT NULL DEFAULT now(),
    "completedAt" TIMESTAMP,

    CONSTRAINT "FK_wf_executions_workflow"
        FOREIGN KEY ("workflowId")
        REFERENCES workflows (id) ON DELETE RESTRICT,

    CONSTRAINT "FK_wf_executions_document"
        FOREIGN KEY ("documentId")
        REFERENCES documents (id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS "IDX_wf_executions_workflowId" ON workflow_executions ("workflowId");
CREATE INDEX IF NOT EXISTS "IDX_wf_executions_documentId" ON workflow_executions ("documentId");
CREATE INDEX IF NOT EXISTS "IDX_wf_executions_status"     ON workflow_executions (status);

-- -----------------------------------------------------------
-- T17 · signature_provider_configs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS signature_provider_configs (
    id                    UUID        PRIMARY KEY DEFAULT uuid_generate_v4(),
    "administrationId"     UUID,
    "isActive"            BOOLEAN     NOT NULL DEFAULT false,
    endpoint              VARCHAR(500),
    "signPath"            VARCHAR(500),
    "apiKey"              VARCHAR(500),
    "consentPageId"       VARCHAR(255),
    "signatureProfileId"  VARCHAR(255),
    "providerOwnerUserId" VARCHAR(255),
    "verifySsl"           BOOLEAN     NOT NULL DEFAULT true,
    "timeoutMs"           INTEGER     NOT NULL DEFAULT 30000,
    metadata              JSONB,
    "createdAt"           TIMESTAMP   NOT NULL DEFAULT now(),
    "updatedAt"           TIMESTAMP   NOT NULL DEFAULT now(),

    CONSTRAINT "FK_sig_provider_administration"
        FOREIGN KEY ("administrationId")
        REFERENCES issuing_administrations (id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS "IDX_sig_provider_isActive" ON signature_provider_configs ("isActive");
CREATE INDEX IF NOT EXISTS "IDX_sig_provider_administrationId" ON signature_provider_configs ("administrationId");

-- -----------------------------------------------------------
-- T18 · notification_configs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS notification_configs (
    id                 UUID         PRIMARY KEY DEFAULT uuid_generate_v4(),
    "administrationId" UUID         NOT NULL,
    "isActive"         BOOLEAN      NOT NULL DEFAULT true,
    "smtpHost"         VARCHAR(255),
    "smtpPort"         INTEGER      NOT NULL DEFAULT 587,
    "smtpSecure"       BOOLEAN      NOT NULL DEFAULT false,
    "smtpUser"         VARCHAR(255),
    "smtpPassword"     VARCHAR(500),
    "smtpFrom"         VARCHAR(255),
    triggers            JSONB,
    "createdAt"        TIMESTAMP    NOT NULL DEFAULT now(),
    "updatedAt"        TIMESTAMP    NOT NULL DEFAULT now(),

    CONSTRAINT "UQ_notification_configs_administrationId" UNIQUE ("administrationId"),
    CONSTRAINT "FK_notification_configs_administration"
        FOREIGN KEY ("administrationId")
        REFERENCES issuing_administrations (id) ON DELETE CASCADE
);

-- -----------------------------------------------------------
-- T19 · direction_types
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS direction_types (
    id           UUID         PRIMARY KEY DEFAULT uuid_generate_v4(),
    name         VARCHAR(150) NOT NULL,
    description  TEXT,
    "createdAt" TIMESTAMP    NOT NULL DEFAULT now(),
    "updatedAt" TIMESTAMP    NOT NULL DEFAULT now(),

    CONSTRAINT "UQ_direction_types_name" UNIQUE (name)
);

-- -----------------------------------------------------------
-- T20 · app_settings
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS app_settings (
    id          UUID         PRIMARY KEY DEFAULT uuid_generate_v4(),
    key         VARCHAR(100) NOT NULL,
    value       TEXT,
    description VARCHAR(255),
    "updatedAt" TIMESTAMP    NOT NULL DEFAULT now(),
    CONSTRAINT "UQ_app_settings_key" UNIQUE (key)
);

-- -----------------------------------------------------------
-- T21 · audit_logs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id           UUID         PRIMARY KEY DEFAULT uuid_generate_v4(),
    "userId"     UUID,
    action       VARCHAR(255) NOT NULL,
    "entityType" VARCHAR(100) NOT NULL,
    "entityId"   UUID         NOT NULL,
    changes      JSON,
    "ipAddress"  VARCHAR(50),
    "userAgent"  VARCHAR(500),
    description  TEXT,
    "createdAt"  TIMESTAMP    NOT NULL DEFAULT now(),

    CONSTRAINT "FK_audit_logs_user"
        FOREIGN KEY ("userId")
        REFERENCES users (id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS "IDX_audit_logs_userId"    ON audit_logs ("userId");
CREATE INDEX IF NOT EXISTS "IDX_audit_logs_action"    ON audit_logs (action);
CREATE INDEX IF NOT EXISTS "IDX_audit_logs_entity"    ON audit_logs ("entityType", "entityId");
CREATE INDEX IF NOT EXISTS "IDX_audit_logs_createdAt" ON audit_logs ("createdAt");

-- Accorder toutes les tables créées à epAdmin
GRANT ALL ON ALL TABLES    IN SCHEMA public TO "epAdmin";
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO "epAdmin";

\echo '[OK] 21 tables créées.'

-- =============================================================
-- DONNÉES INITIALES (SEED)
-- =============================================================

-- Administrateur système par défaut
-- Identifiants : admin@e-parapheur.ci  /  Admin@123456
-- Hash bcrypt (saltRounds=10)
INSERT INTO users (
    id, username, email, "passwordHash", "fullName", role, status
)
VALUES (
    uuid_generate_v4(),
    'admin',
    'admin@e-parapheur.ci',
    '$2a$10$o2DhWU1bP4nTxLCL7JneUuxjEd9uo9YRW1dh4nPjO8J9VLJmtgYQi',
    'Administrateur Système',
    'admin',
    'active'
)
ON CONFLICT (username) DO NOTHING;

-- Configuration fournisseur de signature (désactivée par défaut)
INSERT INTO signature_provider_configs ("isActive")
SELECT false
WHERE NOT EXISTS (SELECT 1 FROM signature_provider_configs);

INSERT INTO direction_types (name, description)
SELECT seed.name, NULL
FROM (
    VALUES
        ('Direction Générale'),
        ('Direction Centrale'),
        ('Direction Régionale'),
        ('Service'),
        ('Division'),
        ('Bureau')
) AS seed(name)
WHERE NOT EXISTS (
    SELECT 1 FROM direction_types existing WHERE existing.name = seed.name
);

-- =============================================================
\echo ''
\echo '======================================================'
\echo '[OK] Base de données initialisée avec succès !'
\echo ''
\echo '  Connexion : psql -U epAdmin -d e_parapheur'
\echo '  Admin web  : admin@e-parapheur.ci'
\echo '  Mot de passe : Admin@123456'
\echo ''
\echo '  IMPORTANT : Changez ce mot de passe après la'
\echo '  première connexion !'
\echo '======================================================'
