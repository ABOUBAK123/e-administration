# Development Guide

## Prerequisites

- Node.js 18.0.0 or higher
- npm 9.0.0 or higher
- Docker and Docker Compose (recommended)
- PostgreSQL 14+ (if not using Docker)
- Git

## Setup Instructions

### 1. Clone the Repository

```bash
git clone <repository-url> E-administration
cd E-administration
```

### 2. Install Dependencies

```bash
# Install root dependencies
npm install

# Install backend dependencies
npm run backend:install

# Install frontend dependencies
npm run frontend:install
```

### 3. Configure Environment Variables

Copy example environment files and update them:

```bash
# Backend
cp apps/backend/.env.example apps/backend/.env

# Frontend
cp apps/frontend/.env.example apps/frontend/.env
```

Edit the `.env` files with your configuration.

### 4. Start Development Environment

#### Option A: Using Docker Compose (Recommended)

```bash
# Start all services
npm run docker:up

# Services will be available at:
# - Frontend: http://localhost:5173
# - Backend API: http://localhost:3000
# - API Docs: http://localhost:3000/api/docs
# - OnlyOffice: http://localhost/onlyoffice
# - PostgreSQL: localhost:5432
```

#### Option B: Running Services Individually

```bash
# Terminal 1: Start database services
docker-compose -f docker-compose.dev.yml up postgres redis onlyoffice

# Terminal 2: Start backend
npm run backend:dev

# Terminal 3: Start frontend
npm run frontend:dev
```

## Development Workflow

### Backend Development

#### Running the Backend

```bash
cd apps/backend
npm run start:dev
```

The backend server will start on port 3000 with hot-reload enabled.

#### Building the Backend

```bash
npm run backend:build
```

Output will be in `apps/backend/dist/`

#### Backend Testing

```bash
npm run test                # Run tests once
npm run test:watch         # Run tests in watch mode
npm run test:cov           # Generate coverage report
```

#### Creating Database Migrations

```bash
# Generate a new migration file
npm run migration:create -- --name=CreateProductsTable

# Run pending migrations
npm run migration:run

# Revert the last migration
npm run migration:revert
```

#### Code Quality

```bash
npm run lint                # Check for linting errors
npm run lint -- --fix       # Fix linting errors
npm run format              # Format code with Prettier
```

### Frontend Development

#### Running the Frontend

```bash
cd apps/frontend
npm run dev
```

The frontend will be available at `http://localhost:5173` with hot-reload enabled.

#### Building the Frontend

```bash
npm run frontend:build
```

Output will be in `apps/frontend/dist/`

#### Frontend Preview

```bash
npm run frontend:preview
```

#### Frontend Testing

```bash
npm run frontend:test        # Run tests
npm run frontend:test:watch  # Watch mode
npm run frontend:test:cov    # Coverage report
```

#### Code Quality

```bash
npm run lint                # Check for linting errors
npm run lint -- --fix       # Fix linting errors
npm run format              # Format code with Prettier
```

## Project Structure

### Backend Structure

```
apps/backend/src/
├── common/
│   ├── decorators/      # Custom decorators
│   ├── filters/         # Exception filters
│   ├── guards/          # Auth guards
│   ├── interceptors/    # HTTP interceptors
│   └── pipes/           # Validation pipes
├── config/              # Configuration files
├── database/            # Database setup
│   └── migrations/      # Migration files
├── modules/             # Feature modules
│   ├── auth/           # Authentication
│   ├── users/          # User management
│   ├── documents/      # Document management
│   ├── signatures/     # Digital signatures
│   ├── workflows/      # Workflow management
│   └── qrcode/         # QR code functionality
├── app.module.ts        # Root module
├── app.controller.ts    # Root controller
├── app.service.ts       # Service
└── main.ts             # Entry point
```

### Frontend Structure

```
apps/frontend/src/
├── components/
│   ├── documents/      # Document components
│   ├── signatures/     # Signature components
│   ├── workflows/      # Workflow components
│   ├── qrcode/        # QR code components
│   └── layout/        # Layout components
├── pages/             # Page components
├── services/          # API services
├── hooks/             # Custom hooks
├── store/             # Zustand stores
├── types/             # TypeScript types
├── App.tsx           # Root component
├── main.tsx          # Entry point
└── index.css         # Global styles
```

## Database Management

### Setting Up Database

Using Docker:
```bash
docker-compose -f docker-compose.dev.yml up postgres
```

Using local PostgreSQL:
```bash
# Create database
createdb e_parapheur_dev

# Update backend/.env with credentials
```

### Running Migrations

```bash
cd apps/backend
npm run migration:run
```

### Seeding Data (Optional)

Create seed files in `apps/backend/src/database/seeds/` and run them as needed.

## API Documentation

Access Swagger documentation while the backend is running:
- **Local**: http://localhost:3000/api/docs
- **Production**: https://api.e-parapheur.com/api/docs

## Debugging

### Backend Debugging

```bash
# Run in debug mode
npm run start:debug

# Then use Chrome DevTools: chrome://inspect
```

### Frontend Debugging

```bash
# Use React DevTools browser extension
# Debugging also works in VS Code with Debugger for Chrome extension
```

### Docker Container Debugging

```bash
# View logs
docker logs <container_id>

# Interactive bash shell
docker exec -it <container_id> /bin/sh
```

## Common Issues

### Port Already in Use

```bash
# Find process using port 3000
lsof -i :3000

# Kill the process
kill -9 <PID>
```

### Database Connection Error

Check that:
1. PostgreSQL is running
2. `.env` file has correct credentials
3. Database exists

### Node Modules Issues

```bash
# Clear node_modules and reinstall
rm -rf node_modules package-lock.json
npm install
```

## Best Practices

### Code Style

- Follow ESLint rules
- Format code with Prettier before committing
- Use TypeScript strict mode
- Add JSDoc comments for public APIs

### Naming Conventions

- Classes and Constructors: PascalCase
- Constants: UPPER_SNAKE_CASE
- Variables and Functions: camelCase
- Files: kebab-case (components), camelCase (others)

### Git Workflow

1. Create feature branch: `git checkout -b feature/my-feature`
2. Make changes and commit: `git commit -m "Add my feature"`
3. Push to remote: `git push origin feature/my-feature`
4. Create Pull Request on GitHub

### Commit Messages

Follow this format:
```
type(scope): subject

description
```

Types: feat, fix, docs, style, refactor, test, chore

Example:
```
feat(documents): add document search functionality

Implement full-text search for documents
with pagination support
```

## Performance Optimization

### Backend
- Use database indexes
- Implement caching with Redis
- Optimize queries
- Use pagination for large datasets

### Frontend
- Lazy load routes with React.lazy
- Optimize images
- Use code splitting
- Minimize bundle size

## Deployment

See [DEPLOYMENT.md](./DEPLOYMENT.md) for deployment instructions.

## Resources

- [NestJS Documentation](https://docs.nestjs.com/)
- [React Documentation](https://react.dev/)
- [TypeScript Documentation](https://www.typescriptlang.org/docs/)
- [Docker Documentation](https://docs.docker.com/)

## Support

For questions or issues:
1. Check the documentation
2. Search existing GitHub issues
3. Create a new issue with detailed description
4. Contact the development team
