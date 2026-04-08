# E-Parapheur API - Quick Testing Guide

## Testing the API with cURL or Postman

### Prerequisites
1. Start the application with Docker Compose:
```bash
docker-compose -f docker-compose.dev.yml up
```

2. Wait for all services to start (usually 30-60 seconds)

3. API should be available at: `http://localhost:3000`

4. Swagger documentation at: `http://localhost:3000/api/docs`

---

## 1. Authentication Flow

### Register a New User

**cURL:**
```bash
curl -X POST http://localhost:3000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "username": "user123",
    "password": "SecurePassword123!",
    "fullName": "John Doe"
  }'
```

**Response:**
```json
{
  "accessToken": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "refreshToken": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "email": "user@example.com",
    "username": "user123",
    "fullName": "John Doe",
    "role": "user",
    "avatar": null
  }
}
```

**Save the accessToken for next requests!**

---

### Login

**cURL:**
```bash
curl -X POST http://localhost:3000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "SecurePassword123!"
  }'
```

---

## 2. Document Management

### Create a Document

```bash
export TOKEN="your_access_token_here"

curl -X POST http://localhost:3000/api/documents \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Q1 Report",
    "description": "Quarterly financial report",
    "type": "pdf"
  }'
```

**Response:**
```json
{
  "id": "650e8400-e29b-41d4-a716-446655440001",
  "title": "Q1 Report",
  "description": "Quarterly financial report",
  "ownerId": "550e8400-e29b-41d4-a716-446655440000",
  "status": "draft",
  "createdAt": "2024-01-15T10:30:00Z",
  "updatedAt": "2024-01-15T10:30:00Z"
}
```

**Save the document ID for next requests!**

---

### Upload File to Document

```bash
export DOC_ID="650e8400-e29b-41d4-a716-446655440001"

# First create a test file
echo "This is a test document" > test.txt

curl -X POST http://localhost:3000/api/documents/$DOC_ID/upload \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@test.txt"
```

---

### Get Document by ID

```bash
curl -X GET http://localhost:3000/api/documents/$DOC_ID \
  -H "Authorization: Bearer $TOKEN"
```

---

### Get All Documents

```bash
curl -X GET "http://localhost:3000/api/documents?page=1&limit=10" \
  -H "Authorization: Bearer $TOKEN"
```

---

### Get My Documents

```bash
curl -X GET http://localhost:3000/api/documents/my-documents \
  -H "Authorization: Bearer $TOKEN"
```

---

### Search Documents

```bash
curl -X GET "http://localhost:3000/api/documents/search?query=Report" \
  -H "Authorization: Bearer $TOKEN"
```

---

### Update Document

```bash
curl -X PUT http://localhost:3000/api/documents/$DOC_ID \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Q1 2024 Report",
    "status": "active"
  }'
```

---

### Get Document Versions

```bash
curl -X GET http://localhost:3000/api/documents/$DOC_ID/versions \
  -H "Authorization: Bearer $TOKEN"
```

---

### Create New Document Version

```bash
echo "Updated document content" > test-v2.txt

curl -X POST http://localhost:3000/api/documents/$DOC_ID/version \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@test-v2.txt"
```

---

## 3. Digital Signatures

### Request Signature

First, create another user for signature request:

```bash
curl -X POST http://localhost:3000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "reviewer@example.com",
    "username": "reviewer123",
    "password": "SecurePassword123!",
    "fullName": "Jane Reviewer"
  }'
```

**Save the reviewer's user ID from response**

```bash
export REVIEWER_ID="550e8400-e29b-41d4-a716-446655440002"

curl -X POST http://localhost:3000/api/signatures/$DOC_ID/request \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "recipientId": "'$REVIEWER_ID'",
    "reason": "Please review and approve",
    "expiryDate": "2024-02-15T23:59:59Z"
  }'
```

**Response:**
```json
{
  "id": "750e8400-e29b-41d4-a716-446655440003",
  "documentId": "650e8400-e29b-41d4-a716-446655440001",
  "requesterId": "550e8400-e29b-41d4-a716-446655440000",
  "recipientId": "550e8400-e29b-41d4-a716-446655440002",
  "reason": "Please review and approve",
  "status": "pending",
  "expiryDate": "2024-02-15T23:59:59Z",
  "createdAt": "2024-01-15T10:35:00Z"
}
```

---

### Get Pending Signatures (as Reviewer)

```bash
export REVIEWER_TOKEN="reviewer_access_token"
export REVIEWER_ID="550e8400-e29b-41d4-a716-446655440002"

curl -X GET http://localhost:3000/api/signatures/pending/$REVIEWER_ID \
  -H "Authorization: Bearer $REVIEWER_TOKEN"
```

---

### Sign Document (as Reviewer)

```bash
curl -X POST http://localhost:3000/api/signatures/$DOC_ID/sign \
  -H "Authorization: Bearer $REVIEWER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "certificateData": "MIIBkTCB+wIJAKHHCgVBqr2+MA0GCSqGSIb3DQEBBQUAMBMxETAPBgNVBAMMCHNpZ25lckd...",
    "algorithmUsed": "SHA256"
  }'
```

---

### Respond to Signature Request

```bash
export REQUEST_ID="750e8400-e29b-41d4-a716-446655440003"

curl -X POST http://localhost:3000/api/signatures/request/$REQUEST_ID/respond \
  -H "Authorization: Bearer $REVIEWER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "accepted": true
  }'
```

---

### Get Document Signatures

```bash
curl -X GET http://localhost:3000/api/signatures/$DOC_ID \
  -H "Authorization: Bearer $TOKEN"
```

---

## 4. Workflow Management

### Create Workflow Template

```bash
curl -X POST http://localhost:3000/api/workflows \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Standard Approval Workflow",
    "description": "Review and approval workflow for documents",
    "steps": [
      {
        "type": "review",
        "assigneeId": "'$REVIEWER_ID'",
        "order": 1
      },
      {
        "type": "sign",
        "assigneeId": "'$REVIEWER_ID'",
        "order": 2
      },
      {
        "type": "approve",
        "assigneeId": "'$REVIEWER_ID'",
        "order": 3
      }
    ]
  }'
```

**Save the workflow ID from response**

```bash
export WORKFLOW_ID="850e8400-e29b-41d4-a716-446655440004"
```

---

### Get Workflow

```bash
curl -X GET http://localhost:3000/api/workflows/$WORKFLOW_ID \
  -H "Authorization: Bearer $TOKEN"
```

---

### Get Workflow Steps

```bash
curl -X GET http://localhost:3000/api/workflows/$WORKFLOW_ID/steps \
  -H "Authorization: Bearer $TOKEN"
```

---

### Execute Workflow on Document

```bash
curl -X POST http://localhost:3000/api/workflows/$WORKFLOW_ID/execute \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "documentId": "'$DOC_ID'"
  }'
```

**Response:**
```json
{
  "id": "950e8400-e29b-41d4-a716-446655440005",
  "workflowId": "850e8400-e29b-41d4-a716-446655440004",
  "documentId": "650e8400-e29b-41d4-a716-446655440001",
  "status": "in_progress",
  "currentStepIndex": 0,
  "createdAt": "2024-01-15T10:40:00Z"
}
```

**Save the execution ID**

```bash
export EXECUTION_ID="950e8400-e29b-41d4-a716-446655440005"
```

---

### Advance Workflow Step (as Reviewer)

```bash
curl -X POST http://localhost:3000/api/workflows/execution/$EXECUTION_ID/advance \
  -H "Authorization: Bearer $REVIEWER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "stepIndex": 0,
    "decision": "approved"
  }'
```

---

### Get Workflow Execution

```bash
curl -X GET http://localhost:3000/api/workflows/$WORKFLOW_ID/executions/$EXECUTION_ID \
  -H "Authorization: Bearer $TOKEN"
```

---

### Reject Workflow

```bash
curl -X POST http://localhost:3000/api/workflows/execution/$EXECUTION_ID/reject \
  -H "Authorization: Bearer $REVIEWER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "reason": "Needs more information"
  }'
```

---

## 5. QR Code Verification

### Generate QR Code

```bash
curl -X POST http://localhost:3000/api/qrcode/generate \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "documentId": "'$DOC_ID'",
    "expiryDays": 30,
    "metadata": {
      "department": "Finance",
      "purpose": "Q1 Report Distribution"
    }
  }'
```

**Response:**
```json
{
  "id": "a50e8400-e29b-41d4-a716-446655440006",
  "documentId": "650e8400-e29b-41d4-a716-446655440001",
  "qrcodeData": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAZAAAAGQCAAAAABNVd8...",
  "verificationCode": "ABC123XYZ789",
  "expiryDate": "2024-02-14T10:45:00Z",
  "createdAt": "2024-01-15T10:45:00Z"
}
```

**Save the verification code and QR code ID**

```bash
export QRCODE_ID="a50e8400-e29b-41d4-a716-446655440006"
export VERIFICATION_CODE="ABC123XYZ789"
```

---

### Get QR Codes for Document

```bash
curl -X GET http://localhost:3000/api/qrcode/document/$DOC_ID \
  -H "Authorization: Bearer $TOKEN"
```

---

### Verify QR Code

```bash
curl -X POST http://localhost:3000/api/qrcode/verify \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "verificationCode": "'$VERIFICATION_CODE'",
    "qrcodeData": "iVBORw0KGgoAAAANSUhEUgAAAZAAAAGQCAAAAABNVd8..."
  }'
```

---

### Revoke QR Code

```bash
curl -X DELETE http://localhost:3000/api/qrcode/$QRCODE_ID \
  -H "Authorization: Bearer $TOKEN"
```

---

## 6. User Management

### Get All Users

```bash
curl -X GET "http://localhost:3000/api/users?page=1&limit=10" \
  -H "Authorization: Bearer $TOKEN"
```

---

### Search Users

```bash
curl -X GET "http://localhost:3000/api/users/search?query=john" \
  -H "Authorization: Bearer $TOKEN"
```

---

### Get User Profile

```bash
curl -X GET http://localhost:3000/api/users/profile \
  -H "Authorization: Bearer $TOKEN"
```

---

### Get User by ID

```bash
curl -X GET http://localhost:3000/api/users/$REVIEWER_ID \
  -H "Authorization: Bearer $TOKEN"
```

---

### Update User

```bash
curl -X PUT http://localhost:3000/api/users/$REVIEWER_ID \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "fullName": "Jane Smith Reviewer",
    "avatar": "https://example.com/avatar.jpg"
  }'
```

---

### Update User Role

```bash
curl -X PUT http://localhost:3000/api/users/$REVIEWER_ID/role \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "role": "reviewer"
  }'
```

---

### Update User Status

```bash
curl -X PUT http://localhost:3000/api/users/$REVIEWER_ID/status \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "inactive"
  }'
```

---

## Using Postman

1. **Import Collection**: Create a new Postman collection
2. **Add Environment Variables**:
   - `base_url`: `http://localhost:3000`
   - `token`: (After login, add the access token)
   - `doc_id`: (After creating document)
   - `workflow_id`: (After creating workflow)

3. **Create Requests** using the endpoints above

4. **Test Complete Workflow**:
   - Register → Login → Create Document → Upload File → Request Signature → Create Workflow → Execute Workflow → Generate QR Code

---

## Common Issues & Solutions

### 401 Unauthorized
- Check if your token is valid
- Token may have expired, use refresh endpoint
- Ensure Bearer token is correctly formatted

### 400 Bad Request
- Check JSON syntax
- Verify all required fields are provided
- Check field types match documentation

### 404 Not Found
- Verify IDs are correct (copy-paste from previous responses)
- Check resource exists before deleting

### 500 Internal Server Error
- Check server logs: `docker-compose logs backend`
- Verify database is running
- Check PostgreSQL connection string

---

## Testing Workflow

### Complete Happy Path Test:
1. ✅ Register two users
2. ✅ Login both users
3. ✅ Create document
4. ✅ Upload file
5. ✅ Request signature
6. ✅ Create workflow
7. ✅ Execute workflow
8. ✅ Advance workflow steps
9. ✅ Sign document
10. ✅ Generate QR code
11. ✅ Verify QR code

---

## API Documentation

Access the interactive Swagger documentation at:
```
http://localhost:3000/api/docs
```

This UI allows you to test all endpoints directly with a visual interface!

---

## Performance Tips

- Use pagination for list endpoints
- Browse indexed fields for better search performance
- Cache frequently accessed documents using Redis
- Use document versions instead of creating copies

---

**Happy Testing! 🚀**
