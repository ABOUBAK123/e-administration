# API Reference Guide

## Overview

This document provides detailed information about all API endpoints available in E-Parapheur Connect & Sign.

For integration projects in third-party business applications (administrations that receive documents), see `docs/API_INTEGRATION_RECEPTEURS.md`.

## Base URL

- Development: `http://localhost:3000/api/v1`
- Production: `https://api.e-parapheur.com/api/v1`

## Authentication

All endpoints (except `/auth/login` and `/auth/register`) require JWT authentication.

Include the token in the `Authorization` header:
```
Authorization: Bearer <your_access_token>
```

## Response Format

All responses follow a standard format:

### Success Response (2xx)
```json
{
  "statusCode": 200,
  "message": "Success message",
  "data": {}
}
```

### Error Response (4xx, 5xx)
```json
{
  "statusCode": 400,
  "message": "Error message",
  "errors": []
}
```

## Endpoints

### Authentication Module

#### Login
- **POST** `/auth/login`
- **Description**: Authenticate user and get access token
- **Request Body**:
  ```json
  {
    "username": "user@example.com",
    "password": "password123"
  }
  ```
- **Response**: Access token and user info

#### Register
- **POST** `/auth/register`
- **Description**: Create new user account
- **Request Body**:
  ```json
  {
    "username": "user@example.com",
    "email": "user@example.com",
    "password": "password123",
    "fullName": "John Doe"
  }
  ```

#### Refresh Token
- **POST** `/auth/refresh`
- **Description**: Get new access token using refresh token
- **Request Body**:
  ```json
  {
    "refreshToken": "token"
  }
  ```

### Documents Module

#### List Documents
- **GET** `/documents`
- **Query Parameters**:
  - `page` (number): Page number
  - `limit` (number): Items per page
  - `search` (string): Search query
  - `status` (string): Filter by status
- **Response**: Array of documents with pagination info

#### Get Document
- **GET** `/documents/:id`
- **Response**: Document details

#### Create Document
- **POST** `/documents`
- **Request Body**:
  ```json
  {
    "name": "Document Name",
    "description": "Document Description",
    "type": "pdf"
  }
  ```

#### Update Document
- **PUT** `/documents/:id`
- **Request Body**: Same as create

#### Delete Document
- **DELETE** `/documents/:id`
- **Response**: Confirmation message

#### Upload Document
- **POST** `/documents/upload`
- **Content-Type**: multipart/form-data
- **Body**: Form data with `file` field
- **Max File Size**: 100MB

#### Get Document Versions
- **GET** `/documents/:id/versions`
- **Response**: Array of document versions

### Signatures Module

#### Sign Document
- **POST** `/signatures/:documentId/sign`
- **Request Body**:
  ```json
  {
    "certData": "certificate",
    "reason": "Approve",
    "location": "City, Country"
  }
  ```

#### Get Document Signatures
- **GET** `/signatures/:documentId`
- **Response**: Array of signatures for the document

#### Request Signature
- **POST** `/signatures/:documentId/request`
- **Request Body**:
  ```json
  {
    "recipientEmail": "signer@example.com",
    "message": "Please sign this document",
    "expiryDate": "2026-12-31"
  }
  ```

#### Verify Signature
- **POST** `/signatures/:documentId/verify/:signatureId`
- **Response**: Verification status and details

#### Delete Signature
- **DELETE** `/signatures/:signatureId`
- **Response**: Confirmation message

### Workflows Module

#### List Workflows
- **GET** `/workflows`
- **Response**: Array of workflows

#### Get Workflow
- **GET** `/workflows/:id`
- **Response**: Workflow details with steps

#### Create Workflow
- **POST** `/workflows`
- **Request Body**:
  ```json
  {
    "name": "Approval Workflow",
    "description": "Multi-step approval process",
    "steps": [
      {
        "order": 1,
        "type": "review",
        "assignee": "user_id"
      }
    ]
  }
  ```

#### Update Workflow
- **PUT** `/workflows/:id`
- **Request Body**: Same as create

#### Delete Workflow
- **DELETE** `/workflows/:id`

#### Get Workflow Steps
- **GET** `/workflows/:id/steps`
- **Response**: Array of workflow steps

#### Execute Workflow
- **POST** `/workflows/:documentId/execute/:workflowId`
- **Response**: Execution status

### QR Code Module

#### Generate QR Code
- **POST** `/qrcode/:documentId/generate`
- **Request Body**:
  ```json
  {
    "type": "verification",
    "metadata": {}
  }
  ```

#### Get QR Codes
- **GET** `/qrcode/:documentId`
- **Response**: Array of QR codes for document

#### Verify QR Code
- **POST** `/qrcode/verify`
- **Request Body**:
  ```json
  {
    "qrcodeData": "encoded_data"
  }
  ```

#### Revoke QR Code
- **DELETE** `/qrcode/:qrcodeId`

## Error Codes

| Code | Message | Description |
|------|---------|-------------|
| 400 | Bad Request | Invalid request parameters |
| 401 | Unauthorized | Missing or invalid authentication token |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource not found |
| 422 | Unprocessable Entity | Validation failed |
| 500 | Internal Server Error | Server error |

## Rate Limiting

- Rate limit: 100 requests per minute per API key
- Rate limit headers: `X-RateLimit-*`

## Pagination

List endpoints support pagination with the following parameters:
- `page` (default: 1)
- `limit` (default: 20, max: 100)

Response includes:
```json
{
  "data": [],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 100,
    "pages": 5
  }
}
```

## WebSocket Connections

Upcoming feature for real-time updates:
- **URL**: `ws://localhost:3000/ws/documents`
- **Authentication**: JWT token in header or query parameter

## Changelog

See [CHANGELOG.md](./CHANGELOG.md) for API version history.
