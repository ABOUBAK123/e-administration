# E-Parapheur Connect & Sign - Development Guidelines

## Project Overview
E-Parapheur Connect & Sign is a comprehensive document management platform with electronic signatures, workflow management, and collaborative editing capabilities.

## Architecture
- **Frontend**: React 18+ with Vite, TypeScript, Tailwind CSS
- **Backend**: NestJS with TypeORM
- **Database**: PostgreSQL with migration support
- **Document Server**: OnlyOffice Document Server for collaborative editing
- **Container**: Docker Compose for full stack deployment
- **Security**: Digital signatures, QR code verification, JWT authentication

## Key Features to Implement
- Workflow management system
- Digital signature integration
- QR code verification
- Document versioning and audit trails
- Real-time collaborative editing
- User role-based access control
- Multi-tenant support ready

## Development Stack
- Node.js 18+
- React 18+
- TypeScript
- NestJS
- PostgreSQL 14+
- Docker & Docker Compose
- OnlyOffice Document Server

## Running the Project

### Prerequisites
- Docker and Docker Compose installed
- Node.js 18+ and npm/yarn
- PostgreSQL 14+ (if running without Docker)

### Development Mode
```bash
# From project root
docker-compose -f docker-compose.dev.yml up

# Or run services individually
# Backend: npm run start:dev (in apps/backend)
# Frontend: npm run dev (in apps/frontend)
```

### Production Mode
```bash
docker-compose -f docker-compose.prod.yml up
```

## Environment Setup
Copy `.env.example` to `.env` in respective directories and configure accordingly.

## Database Migrations
```bash
cd apps/backend
npm run typeorm migration:run
```

## Code Organization
- `/apps/backend` - NestJS API server
- `/apps/frontend` - React client application
- `/packages/shared` - Shared types and utilities
- `/docker` - Docker configurations
- `/docs` - Technical documentation
