# E-Parapheur Connect & Sign - Complete API Overview

## Authentication Endpoints

### Register New User
```
POST /api/auth/register
Content-Type: application/json

{
  "email": "user@example.com",
  "username": "username",
  "password": "password123",
  "fullName": "Full Name"
}

Response: 200 OK
{
  "accessToken": "eyJhbGc...",
  "refreshToken": "eyJhbGc...",
  "user": {
    "id": "uuid",
    "email": "user@example.com",
    "username": "username",
    "fullName": "Full Name",
    "role": "user",
    "avatar": null
  }
}
```

### Login User
```
POST /api/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123"
}

Response: 200 OK
{
  "accessToken": "eyJhbGc...",
  "refreshToken": "eyJhbGc...",
  "user": {...}
}
```

### Refresh Token
```
POST /api/auth/refresh
Content-Type: application/json
Authorization: Bearer <refreshToken>

{
  "refreshToken": "eyJhbGc..."
}

Response: 200 OK
{
  "accessToken": "new_token",
  "refreshToken": "new_refresh_token",
  "user": {...}
}
```

### Get Current User
```
GET /api/auth/me
Authorization: Bearer <accessToken>

Response: 200 OK
{
  "id": "uuid",
  "email": "user@example.com",
  "username": "username",
  "fullName": "Full Name",
  "role": "user",
  "avatar": null
}
```

---

## Document Management Endpoints

### Get All Documents
```
GET /api/documents?page=1&limit=10&search=query
Authorization: Bearer <accessToken>

Response: 200 OK
[
  {
    "id": "uuid",
    "title": "Document Title",
    "description": "Description",
    "ownerId": "uuid",
    "status": "active",
    "createdAt": "2024-01-01T00:00:00Z",
    "updatedAt": "2024-01-01T00:00:00Z"
  }
]
```

### Get Own Documents
```
GET /api/documents/my-documents
Authorization: Bearer <accessToken>

Response: 200 OK
[{...}]
```

### Search Documents
```
GET /api/documents/search?query=test
Authorization: Bearer <accessToken>

Response: 200 OK
[{...}]
```

### Get Document by ID
```
GET /api/documents/:id
Authorization: Bearer <accessToken>

Response: 200 OK
{
  "id": "uuid",
  "title": "Document Title",
  "description": "Description",
  "ownerId": "uuid",
  "status": "active",
  "versions": [],
  "signatures": [],
  "qrcodes": [],
  "createdAt": "2024-01-01T00:00:00Z"
}
```

### Get Document Versions
```
GET /api/documents/:id/versions
Authorization: Bearer <accessToken>

Response: 200 OK
[
  {
    "id": "uuid",
    "version": 1,
    "filePath": "/path/to/file",
    "createdBy": "uuid",
    "createdAt": "2024-01-01T00:00:00Z"
  }
]
```

### Create New Document
```
POST /api/documents
Authorization: Bearer <accessToken>
Content-Type: application/json

{
  "title": "Document Title",
  "description": "Description",
  "type": "pdf"
}

Response: 201 Created
{
  "id": "uuid",
  "title": "Document Title",
  "description": "Description",
  "ownerId": "uuid",
  "status": "draft",
  "createdAt": "2024-01-01T00:00:00Z"
}
```

### Upload Document File
```
POST /api/documents/:id/upload
Authorization: Bearer <accessToken>
Content-Type: multipart/form-data

file: <binary>

Response: 200 OK
{
  "id": "uuid",
  "title": "Document Title",
  "filePath": "/path/to/file",
  "fileSize": 1024,
  "updatedAt": "2024-01-01T00:00:00Z"
}
```

### Create New Document Version
```
POST /api/documents/:id/version
Authorization: Bearer <accessToken>
Content-Type: multipart/form-data

file: <binary>

Response: 201 Created
{
  "id": "uuid",
  "version": 2,
  "filePath": "/path/to/file",
  "createdBy": "uuid",
  "createdAt": "2024-01-01T00:00:00Z"
}
```

### Update Document
```
PUT /api/documents/:id
Authorization: Bearer <accessToken>
Content-Type: application/json

{
  "title": "Updated Title",
  "description": "Updated Description",
  "status": "active"
}

Response: 200 OK
{
  "id": "uuid",
  "title": "Updated Title",
  "description": "Updated Description",
  "status": "active",
  "updatedAt": "2024-01-01T00:00:00Z"
}
```

### Delete Document
```
DELETE /api/documents/:id
Authorization: Bearer <accessToken>

Response: 200 OK
{
  "message": "Document deleted successfully"
}
```

---

## Digital Signature Endpoints

### Sign Document
```
POST /api/signatures/:documentId/sign
Authorization: Bearer <accessToken>
Content-Type: application/json

{
  "certificateData": "cert_data",
  "algorithmUsed": "SHA256"
}

Response: 201 Created
{
  "id": "uuid",
  "documentId": "uuid",
  "userId": "uuid",
  "certificateData": "cert_data",
  "timestamp": "2024-01-01T00:00:00Z",
  "isValid": true
}
```

### Request Signature
```
POST /api/signatures/:documentId/request
Authorization: Bearer <accessToken>
Content-Type: application/json

{
  "recipientId": "uuid",
  "reason": "Approval",
  "expiryDate": "2024-02-01T00:00:00Z"
}

Response: 201 Created
{
  "id": "uuid",
  "documentId": "uuid",
  "requesterId": "uuid",
  "recipientId": "uuid",
  "reason": "Approval",
  "status": "pending",
  "expiryDate": "2024-02-01T00:00:00Z",
  "createdAt": "2024-01-01T00:00:00Z"
}
```

### Get Document Signatures
```
GET /api/signatures/:documentId
Authorization: Bearer <accessToken>

Response: 200 OK
[
  {
    "id": "uuid",
    "documentId": "uuid",
    "userId": "uuid",
    "timestamp": "2024-01-01T00:00:00Z",
    "isValid": true
  }
]
```

### Verify Signature
```
POST /api/signatures/:documentId/verify/:signatureId
Authorization: Bearer <accessToken>

Response: 200 OK
{
  "isValid": true,
  "signatureId": "uuid",
  "verifiedAt": "2024-01-01T00:00:00Z"
}
```

### Get Pending Signatures
```
GET /api/signatures/pending/:userId
Authorization: Bearer <accessToken>

Response: 200 OK
[
  {
    "id": "uuid",
    "documentId": "uuid",
    "requesterId": "uuid",
    "reason": "Approval",
    "status": "pending"
  }
]
```

### Respond to Signature Request
```
POST /api/signatures/request/:requestId/respond
Authorization: Bearer <accessToken>
Content-Type: application/json

{
  "accepted": true
}

Response: 200 OK
{
  "id": "uuid",
  "status": "signed",
  "respondedAt": "2024-01-01T00:00:00Z"
}
```

### Delete Signature
```
DELETE /api/signatures/:signatureId
Authorization: Bearer <accessToken>

Response: 200 OK
{
  "message": "Signature deleted successfully"
}
```

---

## Workflow Management Endpoints

### Get All Workflows
```
GET /api/workflows
Authorization: Bearer <accessToken>

Response: 200 OK
[
  {
    "id": "uuid",
    "name": "Workflow Name",
    "description": "Description",
    "steps": [],
    "status": "active",
    "createdAt": "2024-01-01T00:00:00Z"
  }
]
```

### Get Workflow by ID
```
GET /api/workflows/:id
Authorization: Bearer <accessToken>

Response: 200 OK
{
  "id": "uuid",
  "name": "Workflow Name",
  "description": "Description",
  "steps": [
    {
      "id": "uuid",
      "type": "review",
      "assigneeId": "uuid",
      "order": 1
    }
  ],
  "status": "active",
  "createdAt": "2024-01-01T00:00:00Z"
}
```

### Get Workflow Steps
```
GET /api/workflows/:id/steps
Authorization: Bearer <accessToken>

Response: 200 OK
[
  {
    "id": "uuid",
    "type": "review",
    "assigneeId": "uuid",
    "order": 1
  }
]
```

### Get Workflow Execution
```
GET /api/workflows/:id/executions/:executionId
Authorization: Bearer <accessToken>

Response: 200 OK
{
  "id": "uuid",
  "workflowId": "uuid",
  "documentId": "uuid",
  "status": "in_progress",
  "currentStepIndex": 0,
  "createdAt": "2024-01-01T00:00:00Z"
}
```

### Create Workflow
```
POST /api/workflows
Authorization: Bearer <accessToken>
Content-Type: application/json

{
  "name": "Workflow Name",
  "description": "Description",
  "steps": [
    {
      "type": "review",
      "assigneeId": "uuid",
      "order": 1
    },
    {
      "type": "sign",
      "assigneeId": "uuid",
      "order": 2
    }
  ]
}

Response: 201 Created
{
  "id": "uuid",
  "name": "Workflow Name",
  "description": "Description",
  "steps": [],
  "status": "active",
  "createdAt": "2024-01-01T00:00:00Z"
}
```

### Execute Workflow
```
POST /api/workflows/:id/execute
Authorization: Bearer <accessToken>
Content-Type: application/json

{
  "documentId": "uuid"
}

Response: 201 Created
{
  "id": "uuid",
  "workflowId": "uuid",
  "documentId": "uuid",
  "status": "in_progress",
  "currentStepIndex": 0,
  "createdAt": "2024-01-01T00:00:00Z"
}
```

### Advance Workflow Step
```
POST /api/workflows/execution/:executionId/advance
Authorization: Bearer <accessToken>
Content-Type: application/json

{
  "stepIndex": 0,
  "decision": "approved"
}

Response: 200 OK
{
  "id": "uuid",
  "status": "in_progress",
  "currentStepIndex": 1
}
```

### Reject Workflow
```
POST /api/workflows/execution/:executionId/reject
Authorization: Bearer <accessToken>
Content-Type: application/json

{
  "reason": "Needs revision"
}

Response: 200 OK
{
  "id": "uuid",
  "status": "rejected",
  "rejectionReason": "Needs revision",
  "rejectedAt": "2024-01-01T00:00:00Z"
}
```

### Update Workflow
```
PUT /api/workflows/:id
Authorization: Bearer <accessToken>
Content-Type: application/json

{
  "name": "Updated Name",
  "description": "Updated Description"
}

Response: 200 OK
{
  "id": "uuid",
  "name": "Updated Name",
  "description": "Updated Description",
  "updatedAt": "2024-01-01T00:00:00Z"
}
```

### Delete Workflow
```
DELETE /api/workflows/:id
Authorization: Bearer <accessToken>

Response: 200 OK
{
  "message": "Workflow deleted successfully"
}
```

---

## QR Code Verification Endpoints

### Generate QR Code
```
POST /api/qrcode/generate
Authorization: Bearer <accessToken>
Content-Type: application/json

{
  "documentId": "uuid",
  "expiryDays": 30,
  "metadata": {
    "key": "value"
  }
}

Response: 201 Created
{
  "id": "uuid",
  "documentId": "uuid",
  "qrcodeData": "data:image/png;base64,...",
  "verificationCode": "ABC123XYZ",
  "expiryDate": "2024-02-01T00:00:00Z",
  "createdAt": "2024-01-01T00:00:00Z"
}
```

### Get QR Codes for Document
```
GET /api/qrcode/document/:documentId
Authorization: Bearer <accessToken>

Response: 200 OK
[
  {
    "id": "uuid",
    "documentId": "uuid",
    "qrcodeData": "data:image/png;base64,...",
    "verificationCode": "ABC123XYZ",
    "expiryDate": "2024-02-01T00:00:00Z",
    "isActive": true
  }
]
```

### Verify QR Code
```
POST /api/qrcode/verify
Authorization: Bearer <accessToken>
Content-Type: application/json

{
  "verificationCode": "ABC123XYZ",
  "qrcodeData": "scanned_qr_data"
}

Response: 200 OK
{
  "isValid": true,
  "qrcodeId": "uuid",
  "documentId": "uuid",
  "verifiedAt": "2024-01-01T00:00:00Z"
}
```

### Revoke QR Code
```
DELETE /api/qrcode/:qrcodeId
Authorization: Bearer <accessToken>

Response: 200 OK
{
  "message": "QR code revoked successfully"
}
```

### Cleanup Expired QR Codes
```
POST /api/qrcode/cleanup
Authorization: Bearer <accessToken>

Response: 200 OK
{
  "deletedCount": 5,
  "message": "Cleanup completed"
}
```

---

## User Management Endpoints

### Get All Users
```
GET /api/users?page=1&limit=10
Authorization: Bearer <accessToken>

Response: 200 OK
[
  {
    "id": "uuid",
    "email": "user@example.com",
    "username": "username",
    "fullName": "Full Name",
    "role": "user",
    "status": "active",
    "avatar": null,
    "createdAt": "2024-01-01T00:00:00Z"
  }
]
```

### Search Users
```
GET /api/users/search?query=john
Authorization: Bearer <accessToken>

Response: 200 OK
[{...}]
```

### Get User Profile
```
GET /api/users/profile
Authorization: Bearer <accessToken>

Response: 200 OK
{
  "id": "uuid",
  "email": "user@example.com",
  "username": "username",
  "fullName": "Full Name",
  "role": "user",
  "avatar": null
}
```

### Get User by ID
```
GET /api/users/:id
Authorization: Bearer <accessToken>

Response: 200 OK
{
  "id": "uuid",
  "email": "user@example.com",
  "username": "username",
  "fullName": "Full Name",
  "role": "user",
  "status": "active",
  "avatar": null
}
```

### Create User
```
POST /api/users
Authorization: Bearer <accessToken>
Content-Type: application/json

{
  "email": "user@example.com",
  "username": "username",
  "password": "password123",
  "fullName": "Full Name",
  "role": "user"
}

Response: 201 Created
{
  "id": "uuid",
  "email": "user@example.com",
  "username": "username",
  "fullName": "Full Name",
  "role": "user",
  "status": "active"
}
```

### Update User
```
PUT /api/users/:id
Authorization: Bearer <accessToken>
Content-Type: application/json

{
  "fullName": "Updated Name",
  "avatar": "url/to/avatar"
}

Response: 200 OK
{
  "id": "uuid",
  "fullName": "Updated Name",
  "avatar": "url/to/avatar",
  "updatedAt": "2024-01-01T00:00:00Z"
}
```

### Update User Role
```
PUT /api/users/:id/role
Authorization: Bearer <accessToken>
Content-Type: application/json

{
  "role": "admin"
}

Response: 200 OK
{
  "id": "uuid",
  "role": "admin",
  "updatedAt": "2024-01-01T00:00:00Z"
}
```

### Update User Status
```
PUT /api/users/:id/status
Authorization: Bearer <accessToken>
Content-Type: application/json

{
  "status": "inactive"
}

Response: 200 OK
{
  "id": "uuid",
  "status": "inactive",
  "updatedAt": "2024-01-01T00:00:00Z"
}
```

### Delete User
```
DELETE /api/users/:id
Authorization: Bearer <accessToken>

Response: 200 OK
{
  "message": "User deleted successfully"
}
```

---

## Error Responses

All endpoints return standard error responses on failure:

```
400 - Bad Request
{
  "statusCode": 400,
  "message": "Error details",
  "error": "Bad Request"
}

401 - Unauthorized
{
  "statusCode": 401,
  "message": "Unauthorized access",
  "error": "Unauthorized"
}

403 - Forbidden
{
  "statusCode": 403,
  "message": "You do not have permission",
  "error": "Forbidden"
}

404 - Not Found
{
  "statusCode": 404,
  "message": "Resource not found",
  "error": "Not Found"
}

500 - Internal Server Error
{
  "statusCode": 500,
  "message": "Internal server error",
  "error": "Internal Server Error"
}
```

---

## Authentication

All protected endpoints require a Bearer token in the Authorization header:

```
Authorization: Bearer <accessToken>
```

Tokens expire after 15 minutes. Use the refresh endpoint to get a new token:

```
POST /api/auth/refresh
{
  "refreshToken": "<refreshToken>"
}
```

---

## API Documentation

Full interactive API documentation is available at `/api/docs` after starting the application.
