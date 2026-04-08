# Get Started with E-Parapheur Connect & Sign

Welcome to E-Parapheur Connect & Sign! This guide will help you get up and running quickly.

## What is E-Parapheur?

E-Parapheur Connect & Sign is a comprehensive document management platform that enables:
- 📄 Document upload and management
- ✍️ Digital signatures and approval workflows
- 🔄 Collaborative document editing
- 🔐 Document verification and QR codes
- 📋 Workflow automation

## System Requirements

**Minimum:**
- Node.js 18.0.0+
- npm 9.0.0+
- 4GB RAM
- 2GB disk space

**Recommended:**
- Node.js 20.0.0+
- npm 10.0.0+
- 8GB RAM
- 10GB disk space
- Docker & Docker Compose

## Quick Start (5 minutes)

### 1. Prerequisites Check

Verify you have Node.js and npm installed:

```bash
node --version  # Should be v18.0.0 or higher
npm --version   # Should be 9.0.0 or higher
```

### 2. Clone & Install

```bash
cd E-administration
npm install
```

### 3. Configure Environment

```bash
# Copy environment templates
cp apps/backend/.env.example apps/backend/.env
cp apps/frontend/.env.example apps/frontend/.env
```

### 4. Start Services

```bash
# Option 1: Using Docker (Recommended)
npm run docker:up

# Option 2: Run individually
npm run backend:dev    # Terminal 1
npm run frontend:dev   # Terminal 2
```

### 5. Access the Application

- **Frontend:** http://localhost:5173
- **Backend API:** http://localhost:3000
- **API Documentation:** http://localhost:3000/api/docs

## Default Credentials

For development:
- **Username:** `admin@example.com`
- **Password:** `changeme123`

⚠️ **Important:** Change default credentials in production!

## Common Tasks

### Upload a Document

1. Navigate to Documents section
2. Click "Upload Document"
3. Select a PDF, Word, or compatible file
4. Click "Upload"

### Request a Signature

1. Go to Signatures section
2. Click "Request Signature"
3. Select document and recipient
4. Add message and expiry date
5. Submit request

### Create a Workflow

1. Go to Workflows section
2. Click "Create Workflow"
3. Add workflow steps
4. Assign approvers
5. Save workflow

### Generate QR Code

1. Open document details
2. Click "Generate QR Code"
3. Configure QR code options
4. Download or share QR code

## Project Structure

```
E-administration/
├── apps/
│   ├── backend/           # NestJS API
│   └── frontend/          # React App
├── packages/shared/       # Shared utilities
├── docker/               # Docker configs
├── docs/                 # Documentation
└── docker-compose.*.yml  # Compose files
```

## Development Workflow

### Making Changes

1. **Backend changes:**
   ```bash
   cd apps/backend
   npm run start:dev
   ```
   Changes reload automatically!

2. **Frontend changes:**
   ```bash
   cd apps/frontend
   npm run dev
   ```
   Changes reload automatically!

3. **Check for errors:**
   ```bash
   npm run lint
   npm run format
   ```

### Testing Your Changes

```bash
# Backend tests
cd apps/backend
npm test

# Frontend tests
cd apps/frontend
npm test
```

## Troubleshooting

### Port Already in Use

```bash
# Find process using port
lsof -i :3000          # Backend
lsof -i :5173          # Frontend
lsof -i :5432          # Database

# Kill process
kill -9 <PID>
```

### Database Connection Error

1. Check Docker containers: `docker-compose ps`
2. Check `.env` credentials match docker-compose
3. Restart database: `docker-compose down && docker-compose up`

### Module Not Found Error

```bash
# Clear and reinstall dependencies
rm -rf node_modules package-lock.json
npm install
```

### Port 5432 Already in Use

Change the port in `.env`:
```
DB_PORT=5433
```

And update docker-compose:
```yaml
postgres:
  ports:
    - "5433:5432"
```

## Next Steps

1. **Read the full README:** [README.md](../README.md)
2. **Development Guide:** [DEVELOPMENT.md](./DEVELOPMENT.md)
3. **Architecture Overview:** [ARCHITECTURE.md](./ARCHITECTURE.md)
4. **API Documentation:** [API.md](./API.md)
5. **Database Schema:** [DATABASE.md](./DATABASE.md)

## Common Commands

```bash
# Development
npm run docker:up           # Start all services
npm run docker:down         # Stop all services
npm run backend:dev         # Start backend only
npm run frontend:dev        # Start frontend only

# Building
npm run build               # Build all
npm run backend:build       # Build backend
npm run frontend:build      # Build frontend

# Database
npm run migration:run       # Run migrations
npm run migration:create -- --name=YourMigration

# Code Quality
npm run lint                # Check lint errors
npm run lint -- --fix       # Fix lint errors
npm run format              # Format code
```

## Features

### Implemented
✅ Document management  
✅ User authentication  
✅ API documentation  
✅ Docker setup  
✅ Database setup  

### Coming Soon
🚀 Digital signatures  
🚀 Workflow automation  
🚀 QR code verification  
🚀 Collaborative editing  
🚀 Multi-tenant support  

## Support

- **Documentation:** See `/docs` folder
- **Issues:** GitHub Issues
- **Email:** support@e-parapheur.com
- **Forum:** Community discussions

## Tips for Success

1. **Use Docker Compose** - It handles all dependency setup
2. **Check logs** - Use `docker logs <container>` for debugging
3. **Read the docs** - Comprehensive documentation in `/docs`
4. **Follow git workflow** - Create feature branches for changes
5. **Test regularly** - Run tests before committing

## Environment Variables

### Backend Essential
```
DB_HOST=postgres
DB_USER=epAdminDev
DB_PASSWORD=epPasswordDev2024
JWT_SECRET=dev_secret_key
```

### Frontend Essential
```
VITE_API_URL=http://localhost:3000/api
VITE_ONLYOFFICE_URL=http://localhost/onlyoffice
```

## Performance Tips

- Use Redis for caching
- Enable query logging in development
- Monitor database connections
- Check OnlyOffice performance
- Profile API response times

## Security Checklist

- [ ] Change default passwords
- [ ] Update JWT_SECRET to strong value
- [ ] Enable HTTPS in production
- [ ] Configure CORS properly
- [ ] Set up SSL certificates
- [ ] Enable database backups
- [ ] Review audit logs regularly
- [ ] Use strong database passwords

## What's Different from Git Clone

This workspace is pre-configured with:
- ✅ Complete directory structure
- ✅ All configuration files
- ✅ Docker Compose setup
- ✅ Environment templates
- ✅ Documentation
- ✅ Code scaffolding
- ✅ Component templates

You're ready to start coding immediately!

## Need Help?

1. Check [DEVELOPMENT.md](./DEVELOPMENT.md) for detailed setup
2. Read [ARCHITECTURE.md](./ARCHITECTURE.md) for system design
3. Review [API.md](./API.md) for API endpoints
4. Consult [DATABASE.md](./DATABASE.md) for database schema

---

**Happy documenting!** 🎉

For updates and latest information, visit the project documentation.
