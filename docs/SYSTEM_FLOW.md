# E-Parapheur API - Complete System Flow

## 🔄 Complete User Journey & API Flow

### 1. Authentication Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    AUTHENTICATION FLOW                          │
└─────────────────────────────────────────────────────────────────┘

┌──────────────┐
│   New User   │
└──────┬───────┘
       │
       ▼
   ┌─────────────────────────────────┐
   │ POST /api/auth/register         │
   │ {email, username, password..}  │
   └────────────┬────────────────────┘
                │
                ▼
   ┌─────────────────────────────────┐
   │ AuthService.register()          │
   │ - Validate email                │
   │ - Hash password (bcrypt)        │
   │ - Create user in DB             │
   │ - Generate tokens               │
   └────────────┬────────────────────┘
                │
                ▼
   ┌─────────────────────────────────────────┐
   │ Response: {accessToken, refreshToken}   │
   │ Access Token: 15 minute expiry           │
   │ Refresh Token: 7 day expiry              │
   └────────────┬────────────────────────────┘
                │
                ▼
   ┌──────────────────────────────────┐
   │ All Subsequent Requests          │
   │ Authorization: Bearer <token>    │
   │ JwtStrategy validates token      │
   │ User attached to request object  │
   └──────────────────────────────────┘
```

---

### 2. Document Management Flow

```
┌──────────────────────────────────────────────────────────────┐
│              DOCUMENT MANAGEMENT FLOW                        │
└──────────────────────────────────────────────────────────────┘

┌──────────────┐
│  Create Doc  │
└──────┬───────┘
       │
       ▼
   ┌──────────────────────────────┐
   │ POST /api/documents          │
   │ {title, description, type}   │
   └────────────┬─────────────────┘
                │
                ▼
   ┌──────────────────────────────┐
   │ DocumentsService.create()    │
   │ - Generate UUID              │
   │ - Set owner = currentUser    │
   │ - Status = 'draft'           │
   │ - Save to Document table     │
   └────────────┬─────────────────┘
                │
                ▼
             ┌──┴────────────────────────────────────┐
             │                                       │
             ▼                                       ▼
   ┌──────────────────────────┐        ┌──────────────────────────┐
   │ Upload File              │        │ Create Version           │
   │ POST /api/documents/:id/ │        │ POST /api/documents/:id/ │
   │     upload               │        │     version              │
   │ • Multipart/form-data    │        │ • Creates new version    │
   │ • Store in file system   │        │ • Increments version #   │
   │ • Track filePath in DB   │        │ • Previous versions kept │
   └────────────┬─────────────┘        └────────────┬─────────────┘
                │                                   │
                ▼                                   ▼
   ┌──────────────────────────────┐   ┌──────────────────────────────┐
   │ DocumentVersion created      │   │ Version history tracked      │
   │ - filePath set               │   │ - All versions accessible    │
   │ - Ready for signatures       │   │ - Can revert to old version  │
   └──────────────────────────────┘   └──────────────────────────────┘
                │
                └──────────┬──────────────────────────────────────┐
                           │                                      │
                           ▼                                      ▼
              ┌──────────────────────────┐   ┌──────────────────────────────┐
              │ GET /api/documents/:id   │   │ GET /api/documents/:id/      │
              │ • Retrieve document      │   │     versions                 │
              │ • Load all relations     │   │ • List all versions          │
              │ • With signatures        │   │ • Track changes              │
              │ • With workflows         │   │ • Compare versions           │
              └──────────────────────────┘   └──────────────────────────────┘
```

---

### 3. Digital Signature Flow

```
┌──────────────────────────────────────────────────────────────┐
│             DIGITAL SIGNATURE FLOW                           │
└──────────────────────────────────────────────────────────────┘

SCENARIO A: Self-sign document
───────────────────────────────

┌──────────────────┐
│  Document Owner  │
└────────┬─────────┘
         │
         ▼
     ┌─────────────────────────────────┐
     │ POST /api/signatures/:docId/   │
     │     sign                        │
     │ {certificateData, algorithm}    │
     └────────┬────────────────────────┘
              │
              ▼
     ┌─────────────────────────────────┐
     │ SignaturesService.sign()        │
     │ - Validate certificate          │
     │ - Generate signature (crypto)   │
     │ - timestamp = now()             │
     │ - Algorithm: SHA256             │
     │ - Store in Signature table      │
     └────────┬────────────────────────┘
              │
              ▼
     ┌─────────────────────────────────┐
     │ Signature created & verified    │
     │ Response: {id, timestamp, ...}  │
     └────────┬────────────────────────┘
              │
              ▼
     ┌─────────────────────────────────┐
     │ Can later verify signature      │
     │ POST /api/signatures/:docId/    │
     │     verify/:signatureId         │
     │ Returns isValid: true/false     │
     └─────────────────────────────────┘


SCENARIO B: Request signature from others
──────────────────────────────────────

┌──────────────────┐
│  Document Owner  │
└────────┬─────────┘
         │
         ▼
     ┌──────────────────────────────────┐
     │ POST /api/signatures/:docId/    │
     │     request                     │
     │ {recipientId, reason, expiryDate}
     └────────┬─────────────────────────┘
              │
              ▼
     ┌──────────────────────────────────┐
     │ SignatureRequest created        │
     │ - Status: 'pending'             │
     │ - Recipient notified            │
     │ - Expiry tracked                │
     └────────┬─────────────────────────┘
              │
              ▼
     ┌──────────────────────────┐
     │     Recipient User       │
     └────────┬─────────────────┘
              │
              ▼
     ┌──────────────────────────────────┐
     │ GET /api/signatures/pending/    │
     │     :userId                     │
     │ • View pending requests         │
     │ • See expiry dates              │
     │ • Access documents              │
     └────────┬─────────────────────────┘
              │
              ▼
     ┌──────────────────────────────────┐
     │ POST /api/signatures/request/   │
     │     :requestId/respond          │
     │ {accepted: true}                │
     └────────┬─────────────────────────┘
              │
              ▼
     ┌──────────────────────────────────┐
     │ SignatureRequest updated        │
     │ - Status: 'signed'              │
     │ - Recipient signature created   │
     │ - Timestamp recorded            │
     │ - Owner notified                │
     └──────────────────────────────────┘
```

---

### 4. Workflow Automation Flow

```
┌──────────────────────────────────────────────────────────────┐
│             WORKFLOW AUTOMATION FLOW                         │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────┐
│  Create Workflow Template    │
└────────┬─────────────────────┘
         │
         ▼
     ┌──────────────────────────────────┐
     │ POST /api/workflows              │
     │ {name, description, steps: [     │
     │   {type: 'review', assigneeId},  │
     │   {type: 'sign', assigneeId},    │
     │   {type: 'approve', assigneeId}  │
     │ ]}                               │
     └────────┬─────────────────────────┘
              │
              ▼
     ┌──────────────────────────────────┐
     │ Workflow Template created        │
     │ - Stores workflow steps          │
     │ - Each step has type & assignee  │
     │ - Ready for execution            │
     └────────┬─────────────────────────┘
              │
              ▼
     ┌──────────────────────────────────┐
     │ POST /api/workflows/:id/execute  │
     │ {documentId}                     │
     └────────┬─────────────────────────┘
              │
              ▼
     ┌──────────────────────────────────┐
     │ WorkflowExecution created        │
     │ - Current step index: 0          │
     │ - Status: 'in_progress'          │
     │ - Document linked                │
     │ - Assignee for step 0 notified   │
     └────────┬─────────────────────────┘
              │
              ▼
     ┌──────────────────────────────────┐
     │ Assignee reviews/completes step  │
     │ (Review, Sign, Approve, etc.)    │
     └────────┬─────────────────────────┘
              │
              ▼
     ┌──────────────────────────────────┐
     │ POST /api/workflows/execution/   │
     │     :executionId/advance         │
     │ {stepIndex: 0, decision:...}     │
     └────────┬─────────────────────────┘
              │
              ▼
     ┌──────────────────────────────────┐
     │ WorkflowExecution updated        │
     │ - Step completed                 │
     │ - Current step index: 1          │
     │ - Next assignee notified         │
     │ - Event logged to AuditLog       │
     └────────┬─────────────────────────┘
              │
              └─────────────────────┬────────────────────┐
                                    │                    │
                        ┌───────────▼──────────────┐    │
                        │ Last step completed?   │    │
                        │ if yes → COMPLETE      │    │
                        │ if no → Loop to step 3 │    │
                        └────────────────────────┘    │
                                                      │
                                    ┌─────────────────▼──────────────┐
                                    │ OR: Reject at any point        │
                                    │ POST /api/workflows/execution/ │
                                    │     :executionId/reject        │
                                    │ {reason: '...'}               │
                                    │ Status: 'rejected'            │
                                    │ Document back to owner        │
                                    └───────────────────────────────┘
```

---

### 5. QR Code Verification Flow

```
┌──────────────────────────────────────────────────────────────┐
│          QR CODE VERIFICATION FLOW                           │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────┐
│  Document signed & completed     │
└────────┬─────────────────────────┘
         │
         ▼
     ┌──────────────────────────────────┐
     │ POST /api/qrcode/generate        │
     │ {documentId, expiryDays: 30}    │
     └────────┬─────────────────────────┘
              │
              ▼
     ┌──────────────────────────────────┐
     │ QRCodeService.generate()         │
     │ - Generate unique verification   │
     │   code (e.g., ABC123XYZ789)      │
     │ - Create QR image (base64)       │
     │ - Set expiry = now + 30 days     │
     │ - Store in QrCode table          │
     └────────┬─────────────────────────┘
              │
              ▼
     ┌──────────────────────────────────┐
     │ Response includes:               │
     │ {                                │
     │   id: 'uuid',                    │
     │   qrcodeData: 'data:image/png...',
     │   verificationCode: 'ABC123...',  │
     │   expiryDate: '2024-02-14'       │
     │ }                                │
     └────────┬─────────────────────────┘
              │
              ▼ (Send to recipient via email/message)
     ┌──────────────────────────────────────┐
     │  Recipient scans QR code with        │
     │  phone camera or QR scanner          │
     └────────┬─────────────────────────────┘
              │
              ▼
     ┌──────────────────────────────────────┐
     │ POST /api/qrcode/verify              │
     │ {                                    │
     │   verificationCode: 'ABC123XYZ789',  │
     │   qrcodeData: 'scanned_qr_image'     │
     │ }                                    │
     └────────┬─────────────────────────────┘
              │
              ▼
     ┌──────────────────────────────────────┐
     │ QRCodeService.verify()               │
     │ - Check verification code exists     │
     │ - Check if not expired               │
     │ - Check if not revoked               │
     │ - Validate QR data matches           │
     └────────┬─────────────────────────────┘
              │
              ▼
     ┌──────────────────────────────────────┐
     │ Response:                            │
     │ {                                    │
     │   isValid: true,                     │
     │   documentId: 'uuid',                │
     │   verifiedAt: '2024-01-15...',       │
     │   expiryDate: '2024-02-14...'        │
     │ }                                    │
     └──────────────────────────────────────┘

REVOCATION (Optional):
─────────────────────

     POST /api/qrcode/:qrcodeId/revoke
     → QR code marked as inactive
     → Future verification requests fail
     → Document still valid, just QR disabled
```

---

### 6. Complete User Workflow

```
┌────────────────────────────────────────────────────────────────┐
│         COMPLETE E-PARAPHEUR USER WORKFLOW                     │
└────────────────────────────────────────────────────────────────┘

STEP 1: USER SETUP
──────────────────
   User A: Document Creator & Approver
   User B: Reviewer & Signer
   User C: Final Approver

                        │
                        ▼
STEP 2: AUTHENTICATION (All Users)
──────────────────────────────────
   POST /api/auth/register
   → Create user accounts
   → Receive JWT tokens
   → Token stored on client

                        │
                        ▼
STEP 3: CREATE DOCUMENT (User A)
────────────────────────────────
   POST /api/documents
   → Create: "Q1 Financial Report"
   → Status: draft
   → Own document

                        │
                        ▼
STEP 4: UPLOAD FILE (User A)
────────────────────────────
   POST /api/documents/:id/upload
   → Upload PDF/Document file
   → Store in system
   → Create DocumentVersion 1

                        │
                        ▼
STEP 5: CREATE WORKFLOW (User A)
────────────────────────────────
   POST /api/workflows
   → Define steps:
     1. Review (User B)
     2. Sign (User B)
     3. Approve (User C)

                        │
                        ▼
STEP 6: EXECUTE WORKFLOW (User A)
──────────────────────────────────
   POST /api/workflows/:id/execute
   → Workflow started on document
   → User B notified to review
   → Current step index = 0

                        │
                        ▼
STEP 7: REVIEW & SIGN (User B)
──────────────────────────────
   • Reviews document: GET /api/documents/:id
   • Advances step: POST /api/workflows/execution/:id/advance
   • Signs document: POST /api/signatures/:docId/sign
                        │
                        ▼
STEP 8: APPROVAL (User C)
─────────────────────────
   • Gets notification for approval
   • Advances workflow step
   • Final approval complete
   • Workflow status = 'completed'

                        │
                        ▼
STEP 9: GENERATE QR CODE (User A)
─────────────────────────────────
   POST /api/qrcode/generate
   → Generate QR code for document
   → Set 30-day expiry
   → Share with recipients

                        │
                        ▼
STEP 10: VERIFY & AUDIT (All Users)
───────────────────────────────────
   • Recipients verify QR: POST /api/qrcode/verify
   • Confirm document authenticity
   • Audit log shows all actions
   • Document locked/archived

                        │
                        ▼
         ✅ WORKFLOW COMPLETE
```

---

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        REQUEST FLOW                             │
└─────────────────────────────────────────────────────────────────┘

┌──────────────┐
│ Client (Web) │
└──────┬───────┘
       │
       │ 1. HTTP Request
       │ Authorization: Bearer JWT
       │
       ▼
┌──────────────────────────────────────────┐
│ NGINX (Reverse Proxy)                    │
│ - Route requests                         │
│ - Load balance                           │
│ - SSL termination                        │
└──────┬───────────────────────────────────┘
       │
       │ 2. Forward to Backend
       │
       ▼
┌──────────────────────────────────────────┐
│ NestJS Application (Port 3000)           │
│ - Global Validation Pipe                 │
│ - CORS Middleware                        │
└──────┬───────────────────────────────────┘
       │
       │ 3. Route to Controller
       │
       ▼
┌──────────────────────────────────────────┐
│ JWT Auth Guard                           │
│ - Verify token validity                  │
│ - Extract user info                      │
│ - Check role permissions                 │
└──────┬───────────────────────────────────┘
       │
       │ 4. Request with User Object
       │
       ▼
┌──────────────────────────────────────────┐
│ Controller Method                        │
│ - Parse request body/params              │
│ - Validate with DTOs                     │
│ - Call service method                    │
└──────┬───────────────────────────────────┘
       │
       │ 5. Call Business Logic
       │
       ▼
┌──────────────────────────────────────────┐
│ Service Layer                            │
│ - Execute business logic                 │
│ - Data validation                        │
│ - Call repositories                      │
└──────┬───────────────────────────────────┘
       │
       │ 6. Database Operations
       │ Using TypeORM
       │
       ▼
┌──────────────────────────────────────────┐
│ PostgreSQL Database                      │
│ - Query execution                        │
│ - Transaction handling                   │
│ - Data persistence                       │
│ - Relationship management                │
└──────┬───────────────────────────────────┘
       │
       │ 7. Return Data
       │
       ▼
┌──────────────────────────────────────────┐
│ Cache (Redis)                            │
│ - Store frequently accessed data         │
│ - Session management                     │
│ - Expedite responses                     │
└──────┬───────────────────────────────────┘
       │
       │ 8. Response Built
       │
       ▼
┌──────────────────────────────────────────┐
│ Service Layer Response                   │
│ - Format response object                 │
│ - Include metadata                       │
└──────┬───────────────────────────────────┘
       │
       │ 9. Return to Controller
       │
       ▼
┌──────────────────────────────────────────┐
│ Controller Response                      │
│ - Serialize to JSON                      │
│ - Set HTTP status code                   │
│ - Add response headers                   │
└──────┬───────────────────────────────────┘
       │
       │ 10. HTTP Response
       │ Status: 200 OK
       │ Headers: Content-Type: application/json
       │
       ▼
┌──────────────────────────────────────────┐
│ Reverse Proxy (NGINX)                    │
│ - Final response processing              │
│ - Compression                            │
│ - Caching headers                        │
└──────┬───────────────────────────────────┘
       │
       │ 11. HTTP Response to Client
       │
       ▼
┌──────────────────────────────────────────┐
│ Client (Web)                             │
│ - Parse JSON                             │
│ - Update UI                              │
│ - Store in state management              │
└──────────────────────────────────────────┘
```

---

## Error Handling Flow

```
┌────────────────────────────────────────┐
│       ERROR HANDLING FLOW               │
└────────────────────────────────────────┘

Request arrives
       │
       ▼
Validation fails?
       │
   ├─→ ❌ YES → 400 Bad Request
   │            ValidationError
   │
   └─→ ✅ NO
          │
          ▼
   JWT validation fails?
       │
   ├─→ ❌ YES → 401 Unauthorized
   │            "Invalid token"
   │
   └─→ ✅ NO
          │
          ▼
   User has permission?
       │
   ├─→ ❌ NO → 403 Forbidden
   │           "Access denied"
   │
   └─→ ✅ YES
          │
          ▼
   Resource exists?
       │
   ├─→ ❌ NO → 404 Not Found
   │          "Resource not found"
   │
   └─→ ✅ YES
          │
          ▼
   Business logic executed
       │
   ├─→ ❌ Error → 400-500
   │            Error details
   │
   └─→ ✅ Success → 200/201
               Response data
```

---

## Summary

Your E-Parapheur system provides:

✅ **Complete authentication** - JWT-based secure access
✅ **Document management** - Full lifecycle with versioning
✅ **Digital signatures** - Cryptographic signing and verification
✅ **Workflow automation** - Multi-step approval processes
✅ **QR verification** - Document authenticity confirmation
✅ **Audit logging** - Complete compliance trail

All flows are production-ready and fully implemented!
