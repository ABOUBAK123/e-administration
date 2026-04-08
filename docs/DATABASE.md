# Database Schema Documentation

## Overview

The E-Parapheur Connect & Sign database is built on PostgreSQL 14+ with TypeORM for ORM mapping. This document describes the database structure and relationships.

## Database Diagram

```
┌─────────────────────────┐
│         users           │
├─────────────────────────┤
│ id (PK)                 │
│ username (UNIQUE)       │
│ email (UNIQUE)          │
│ passwordHash            │
│ fullName                │
│ avatar                  │
│ role                    │
│ status                  │
│ createdAt               │
│ updatedAt               │
│ deletedAt               │
└─────────────────────────┘
          │
          ├─────────────────────────┐
          │                         │
          ▼                         ▼
┌─────────────────────────┐  ┌──────────────────────┐
│      documents          │  │ document_versions    │
├─────────────────────────┤  ├──────────────────────┤
│ id (PK)                 │  │ id (PK)              │
│ title                   │  │ documentId (FK)      │
│ description             │  │ version              │
│ filePath                │  │ filePath             │
│ fileSize                │  │ creatorId (FK)       │
│ mimeType                │  │ changeLog            │
│ status                  │  │ createdAt            │
│ createdBy (FK)          │  └──────────────────────┘
│ ownerId (FK)            │
│ createdAt               │
│ updatedAt               │
│ deletedAt               │
└─────────────────────────┘
          │
          ├─────────────────────────┐
          │                         │
          ▼                         ▼
┌─────────────────────────┐  ┌──────────────────────┐
│     signatures          │  │    qr_codes          │
├─────────────────────────┤  ├──────────────────────┤
│ id (PK)                 │  │ id (PK)              │
│ documentId (FK)         │  │ documentId (FK)      │
│ signerId (FK)           │  │ data                 │
│ signature               │  │ metadata             │
│ certificate             │  │ verificationCode     │
│ timestamp               │  │ status               │
│ reason                  │  │ createdAt            │
│ location                │  │ expiresAt            │
│ isValid                 │  │ createdBy (FK)       │
│ createdAt               │  └──────────────────────┘
│ status                  │
└─────────────────────────┘
          │
          │
          ▼
┌─────────────────────────┐
│ signature_requests      │
├─────────────────────────┤
│ id (PK)                 │
│ documentId (FK)         │
│ requestedBy (FK)        │
│ requestedTo (FK)        │
│ message                 │
│ status                  │
│ expiryDate              │
│ createdAt               │
│ respondedAt             │
└─────────────────────────┘


┌─────────────────────────┐
│      workflows          │
├─────────────────────────┤
│ id (PK)                 │
│ name                    │
│ description             │
│ status                  │
│ createdBy (FK)          │
│ createdAt               │
│ updatedAt               │
└─────────────────────────┘
          │
          ├─────────────────────────┐
          │                         │
          ▼                         ▼
┌─────────────────────────┐  ┌──────────────────────┐
│   workflow_steps        │  │ workflow_executions  │
├─────────────────────────┤  ├──────────────────────┤
│ id (PK)                 │  │ id (PK)              │
│ workflowId (FK)         │  │ workflowId (FK)      │
│ order                   │  │ documentId (FK)      │
│ type                    │  │ currentStep          │
│ assigneeId (FK)         │  │ status               │
│ description             │  │ startedAt            │
│ createdAt               │  │ completedAt          │
└─────────────────────────┘  └──────────────────────┘


┌─────────────────────────┐
│      audit_logs         │
├─────────────────────────┤
│ id (PK)                 │
│ userId (FK)             │
│ action                  │
│ entityType              │
│ entityId                │
│ changes                 │
│ ipAddress               │
│ userAgent               │
│ createdAt               │
└─────────────────────────┘
```

## Table Definitions

### Users Table

Stores user account information and credentials.

```sql
CREATE TABLE users (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  username VARCHAR(255) UNIQUE NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  passwordHash VARCHAR(255) NOT NULL,
  fullName VARCHAR(255) NOT NULL,
  avatar VARCHAR(500),
  role VARCHAR(50) DEFAULT 'user', -- 'admin', 'user', 'signer'
  status VARCHAR(50) DEFAULT 'active', -- 'active', 'inactive', 'suspended'
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  deletedAt TIMESTAMP NULL,
  INDEX idx_username (username),
  INDEX idx_email (email),
  INDEX idx_status (status)
);
```

### Documents Table

Stores document metadata and information.

```sql
CREATE TABLE documents (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  title VARCHAR(500) NOT NULL,
  description TEXT,
  filePath VARCHAR(1000) NOT NULL,
  fileSize BIGINT NOT NULL,
  mimeType VARCHAR(100) NOT NULL,
  status VARCHAR(50) DEFAULT 'draft', -- 'draft', 'signed', 'archived'
  createdBy UUID NOT NULL REFERENCES users(id),
  ownerId UUID NOT NULL REFERENCES users(id),
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  deletedAt TIMESTAMP NULL,
  INDEX idx_owner (ownerId),
  INDEX idx_status (status),
  INDEX idx_created (createdAt)
);
```

### Document Versions Table

Maintains version history of documents.

```sql
CREATE TABLE document_versions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  documentId UUID NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
  version INTEGER NOT NULL,
  filePath VARCHAR(1000) NOT NULL,
  creatorId UUID NOT NULL REFERENCES users(id),
  changeLog TEXT,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_document (documentId),
  INDEX idx_version (version),
  UNIQUE (documentId, version)
);
```

### Signatures Table

Stores digital signature information.

```sql
CREATE TABLE signatures (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  documentId UUID NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
  signerId UUID NOT NULL REFERENCES users(id),
  signature BYTEA NOT NULL,
  certificate TEXT,
  timestamp TIMESTAMP NOT NULL,
  reason VARCHAR(500),
  location VARCHAR(500),
  isValid BOOLEAN DEFAULT true,
  status VARCHAR(50) DEFAULT 'valid', -- 'valid', 'revoked', 'expired'
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_document (documentId),
  INDEX idx_signer (signerId),
  INDEX idx_status (status)
);
```

### Signature Requests Table

Manages signature request workflows.

```sql
CREATE TABLE signature_requests (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  documentId UUID NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
  requestedBy UUID NOT NULL REFERENCES users(id),
  requestedTo UUID NOT NULL REFERENCES users(id),
  message TEXT,
  status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'signed', 'declined'
  expiryDate TIMESTAMP NOT NULL,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  respondedAt TIMESTAMP NULL,
  INDEX idx_document (documentId),
  INDEX idx_status (status),
  INDEX idx_expiry (expiryDate)
);
```

### QR Codes Table

Stores QR code data for document verification.

```sql
CREATE TABLE qr_codes (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  documentId UUID NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
  data TEXT NOT NULL,
  metadata JSONB,
  verificationCode VARCHAR(255) UNIQUE NOT NULL,
  status VARCHAR(50) DEFAULT 'active', -- 'active', 'revoked', 'expired'
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expiresAt TIMESTAMP NOT NULL,
  createdBy UUID NOT NULL REFERENCES users(id),
  INDEX idx_document (documentId),
  INDEX idx_status (status),
  INDEX idx_code (verificationCode)
);
```

### Workflows Table

Defines workflow templates.

```sql
CREATE TABLE workflows (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name VARCHAR(500) NOT NULL,
  description TEXT,
  status VARCHAR(50) DEFAULT 'active', -- 'active', 'inactive', 'archived'
  createdBy UUID NOT NULL REFERENCES users(id),
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_creator (createdBy),
  INDEX idx_status (status)
);
```

### Workflow Steps Table

Defines steps within a workflow.

```sql
CREATE TABLE workflow_steps (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  workflowId UUID NOT NULL REFERENCES workflows(id) ON DELETE CASCADE,
  "order" INTEGER NOT NULL,
  type VARCHAR(100) NOT NULL, -- 'review', 'sign', 'approve', 'reject'
  assigneeId UUID REFERENCES users(id),
  description TEXT,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_workflow (workflowId),
  INDEX idx_order ("order"),
  UNIQUE (workflowId, "order")
);
```

### Workflow Executions Table

Tracks workflow execution instances.

```sql
CREATE TABLE workflow_executions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  workflowId UUID NOT NULL REFERENCES workflows(id),
  documentId UUID NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
  currentStep INTEGER NOT NULL,
  status VARCHAR(50) DEFAULT 'in_progress', -- 'in_progress', 'completed', 'rejected'
  startedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completedAt TIMESTAMP NULL,
  INDEX idx_workflow (workflowId),
  INDEX idx_document (documentId),
  INDEX idx_status (status)
);
```

### Audit Logs Table

Logs all user actions for compliance and security.

```sql
CREATE TABLE audit_logs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  userId UUID REFERENCES users(id),
  action VARCHAR(255) NOT NULL,
  entityType VARCHAR(100) NOT NULL, -- 'document', 'signature', 'user', etc.
  entityId UUID NOT NULL,
  changes JSONB,
  ipAddress VARCHAR(50),
  userAgent VARCHAR(500),
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (userId),
  INDEX idx_entity (entityType, entityId),
  INDEX idx_action (action),
  INDEX idx_created (createdAt)
);
```

## Relationships

### One-to-Many Relationships
- Users → Documents (created by)
- Users → Documents (owner)
- Users → Audit Logs
- Documents → Signatures
- Documents → Document Versions
- Documents → QR Codes
- Documents → Signature Requests
- Workflows → Workflow Steps
- Workflows → Workflow Executions

### Many-to-One Relationships
- Signatures → Users (signer)
- Signatures → Documents
- Workflow Executions → Documents
- Workflow Executions → Workflows

## Indexes

Indexes are created on:
- Primary keys (automatic)
- Foreign keys (for joins)
- Status fields (for filtering)
- Date fields (for range queries)
- Unique fields (username, email)
- Document ID (common query)

## Data Types

| Type | Description |
|------|-------------|
| UUID | Unique identifier (primary keys) |
| VARCHAR(n) | Variable length strings |
| TEXT | Long text content |
| BYTEA | Binary data (signatures) |
| JSONB | JSON binary format (metadata) |
| BIGINT | Large integers (file size) |
| TIMESTAMP | Date and time |
| BOOLEAN | True/False values |

## Migration Strategy

Migrations are managed using TypeORM migrations:
- Each change gets its own migration file
- Migrations are timestamped
- Can be run forward and backward
- Stored in `src/database/migrations/`

## Backup & Recovery

- Daily automated backups
- Point-in-time recovery capability
- Backup verification procedures
- Disaster recovery plan

## Performance Optimization

- Indexed columns used in WHERE clauses
- Proper foreign key relationships
- Partitioning for large tables (future)
- Connection pooling configuration
- Query optimization through ORM
