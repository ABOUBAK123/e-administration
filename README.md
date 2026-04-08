# E-Parapheur Connect & Sign

A comprehensive document management platform with electronic signatures, workflow management, and collaborative editing capabilities.

## 🚀 Quick Start

### Prerequisites
- Node.js 18+
- Docker and Docker Compose
- PostgreSQL 14+ (optional, can use Docker)

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd E-administration
   ```

2. **Install dependencies**
   ```bash
   npm install
   ```

3. **Setup environment variables**
   ```bash
   # Backend
   cp apps/backend/.env.example apps/backend/.env
   
   # Frontend
   cp apps/frontend/.env.example apps/frontend/.env
   ```

4. **Start with Docker Compose (Recommended)**
   ```bash
   # Development mode
   npm run docker:up
   
   # Production mode
   npm run docker:up:prod
   ```

   Or run services individually:
   ```bash
   # Start only database
   docker-compose -f docker-compose.dev.yml up postgres redis
   
   # In separate terminals
   npm run backend:dev  # Terminal 1
   npm run frontend:dev # Terminal 2
   ```

### Development

#### Backend Development
```bash
cd apps/backend
npm install
npm run start:dev
```

The API will be available at `http://localhost:3000` with Swagger docs at `http://localhost:3000/api/docs`.

#### Frontend Development
```bash
cd apps/frontend
npm install
npm run dev
```

The frontend will be available at `http://localhost:5173`.

### Database Migrations

```bash
# Create a new migration
npm run migration:create -- --name=CreateUsersTable

# Run migrations
npm run migration:run

# Revert last migration
npm run migration:revert
```

## 📋 Project Structure

```
E-administration/
├── apps/
│   ├── backend/              # NestJS API server
│   │   ├── src/
│   │   │   ├── modules/      # Feature modules
│   │   │   ├── config/       # Configuration
│   │   │   └── database/     # Migrations & schemas
│   │   ├── package.json
│   │   └── tsconfig.json
│   └── frontend/             # React client
│       ├── src/
│       │   ├── components/   # React components
│       │   ├── pages/        # Page components
│       │   ├── services/     # API services
│       │   └── types/        # TypeScript types
│       ├── public/
│       └── vite.config.ts
├── packages/
│   └── shared/               # Shared types & utilities
├── docker/                   # Docker configurations
├── docs/                     # Documentation
├── docker-compose.dev.yml    # Development environment
├── docker-compose.prod.yml   # Production environment
└── package.json             # Root package configuration
```

## 🏗️ Architecture

### Technology Stack

- **Frontend:**
  - React 18+ with TypeScript
  - Vite for build tooling
  - Tailwind CSS for styling
  - Axios for API communication
  - React Router for navigation
  - Zustand for state management

- **Backend:**
  - NestJS framework
  - TypeORM for database ORM
  - JWT for authentication
  - PostgreSQL for data storage
  - Redis for caching

- **Infrastructure:**
  - Docker & Docker Compose
  - OnlyOffice Document Server (collaborative editing)
  - Nginx (production)
  - PostgreSQL 14+

## ✨ Key Features

### 1. Document Management
- Upload and manage documents
- Document versioning
- Full-text search
- Audit trails

### 2. Digital Signatures (Coming Soon)
- Electronic signature support
- Multi-signature workflows
- Signature verification
- Timestamp integration

### 3. QR Code Verification (Coming Soon)
- Generate QR codes for documents
- Verify document authenticity
- QR code management

### 4. Workflow Management (Coming Soon)
- Create custom workflows
- Workflow automation
- Step management
- Status tracking

### 5. Collaborative Editing (Coming Soon)
- Real-time collaboration with OnlyOffice
- Multi-user editing
- Change tracking
- Version history

## 🔐 Security Features

- JWT-based authentication
- Role-based access control (RBAC)
- Encrypted password storage
- CORS protection
- Input validation and sanitization
- Prepared statements against SQL injection
- Secure file upload handling
- HTTPS support in production

## 📚 API Documentation

Once the backend is running, visit `http://localhost:3000/api/docs` for interactive Swagger documentation.

### Available Endpoints

#### Authentication
- `POST /api/v1/auth/login` - User login
- `POST /api/v1/auth/register` - User registration
- `POST /api/v1/auth/refresh` - Refresh token

#### Documents
- `GET /api/v1/documents` - List all documents
- `POST /api/v1/documents` - Create document
- `GET /api/v1/documents/:id` - Get document details
- `PUT /api/v1/documents/:id` - Update document
- `DELETE /api/v1/documents/:id` - Delete document
- `POST /api/v1/documents/upload` - Upload document
- `GET /api/v1/documents/:id/versions` - Get document versions

#### Signatures
- `POST /api/v1/signatures/:documentId/sign` - Sign document
- `GET /api/v1/signatures/:documentId` - Get signatures
- `POST /api/v1/signatures/:documentId/request` - Request signature
- `POST /api/v1/signatures/:documentId/verify/:signatureId` - Verify signature

#### Workflows
- `GET /api/v1/workflows` - List workflows
- `POST /api/v1/workflows` - Create workflow
- `GET /api/v1/workflows/:id` - Get workflow
- `PUT /api/v1/workflows/:id` - Update workflow
- `DELETE /api/v1/workflows/:id` - Delete workflow
- `POST /api/v1/workflows/execute` - Execute workflow

#### QR Codes
- `POST /api/v1/qrcode/:documentId/generate` - Generate QR code
- `GET /api/v1/qrcode/:documentId` - Get QR codes
- `POST /api/v1/qrcode/verify` - Verify QR code
- `DELETE /api/v1/qrcode/:qrcodeId` - Revoke QR code

## 🧪 Testing

```bash
# Backend tests
npm run test

# Frontend tests (to be configured)
npm run frontend:test

# Test coverage
npm run test:cov
```

## 📦 Building for Production

```bash
# Build both frontend and backend
npm run build

# Or individually
npm run backend:build
npm run frontend:build

# Using Docker
npm run docker:build:prod
npm run docker:up:prod
```

## 🔧 Configuration

### Environment Variables

#### Backend (.env)
```
NODE_ENV=development
API_PORT=3000
JWT_SECRET=your-secret-key
DB_HOST=localhost
DB_PORT=5432
DB_USER=username
DB_PASSWORD=password
DB_NAME=database_name
ONLYOFFICE_URL=http://localhost/onlyoffice
```

#### Frontend (.env)
```
VITE_API_URL=http://localhost:3000/api
VITE_ONLYOFFICE_URL=http://localhost/onlyoffice
```

## 🤝 Contributing

1. Create a feature branch (`git checkout -b feature/amazing-feature`)
2. Commit your changes (`git commit -m 'Add amazing feature'`)
3. Push to the branch (`git push origin feature/amazing-feature`)
4. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 📞 Support

For support, email support@e-parapheur.com or create an issue on GitHub.

## 🎯 Roadmap

- [ ] Complete digital signature implementation
- [ ] QR code verification system
- [ ] Advanced workflow engine
- [ ] Multi-language support
- [ ] Mobile app
- [ ] WebRTC for real-time collaboration
- [ ] Advanced analytics dashboard
- [ ] Multi-tenant architecture
- [ ] SSO integration (OAuth 2.0, SAML)
- [ ] Audit logging

## 🤖 Tech Stack Details

### Frontend Dependencies
- `react` - UI library
- `react-router-dom` - Routing
- `axios` - HTTP client
- `tailwindcss` - Styling
- `zustand` - State management
- `react-hook-form` - Form handling
- `zod` - Schema validation
- `qrcode` - QR code generation

### Backend Dependencies
- `@nestjs/core` - NestJS framework
- `@nestjs/typeorm` - ORM integration
- `typeorm` - TypeORM for database
- `pg` - PostgreSQL driver
- `@nestjs/jwt` - JWT authentication
- `passport` - Authentication middleware
- `redis` - Caching
- `qrcode` - QR code generation
- `crypto-js` - Encryption utilities

## 📄 Project Documents

- [Architecture Documentation](./docs/ARCHITECTURE.md) - System design and architecture
- [API Reference](./docs/API.md) - Detailed API documentation
- [Database Schema](./docs/DATABASE.md) - Database design
- [Development Guide](./docs/DEVELOPMENT.md) - Development guidelines

---

**E-Parapheur Connect & Sign** - Document Management Made Easy
