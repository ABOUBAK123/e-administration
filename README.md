# E-Parapheur Connect & Sign
---

**E-Parapheur Connect & Sign** - Document Management Made Easy
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
=======
# e-administration



## Getting started

To make it easy for you to get started with GitLab, here's a list of recommended next steps.

Already a pro? Just edit this README.md and make it your own. Want to make it easy? [Use the template at the bottom](#editing-this-readme)!

## Add your files

* [Create](https://docs.gitlab.com/user/project/repository/web_editor/#create-a-file) or [upload](https://docs.gitlab.com/user/project/repository/web_editor/#upload-a-file) files
* [Add files using the command line](https://docs.gitlab.com/topics/git/add_files/#add-files-to-a-git-repository) or push an existing Git repository with the following command:

```
cd existing_repo
git remote add origin https://gitlab.com/ABOUBAK123/e-administration.git
git branch -M main
git push -uf origin main
```

## Integrate with your tools

* [Set up project integrations](https://gitlab.com/ABOUBAK123/e-administration/-/settings/integrations)

## Collaborate with your team

* [Invite team members and collaborators](https://docs.gitlab.com/user/project/members/)
* [Create a new merge request](https://docs.gitlab.com/user/project/merge_requests/creating_merge_requests/)
* [Automatically close issues from merge requests](https://docs.gitlab.com/user/project/issues/managing_issues/#closing-issues-automatically)
* [Enable merge request approvals](https://docs.gitlab.com/user/project/merge_requests/approvals/)
* [Set auto-merge](https://docs.gitlab.com/user/project/merge_requests/auto_merge/)

## Test and Deploy

Use the built-in continuous integration in GitLab.

* [Get started with GitLab CI/CD](https://docs.gitlab.com/ci/quick_start/)
* [Analyze your code for known vulnerabilities with Static Application Security Testing (SAST)](https://docs.gitlab.com/user/application_security/sast/)
* [Deploy to Kubernetes, Amazon EC2, or Amazon ECS using Auto Deploy](https://docs.gitlab.com/topics/autodevops/requirements/)
* [Use pull-based deployments for improved Kubernetes management](https://docs.gitlab.com/user/clusters/agent/)
* [Set up protected environments](https://docs.gitlab.com/ci/environments/protected_environments/)

***

# Editing this README

When you're ready to make this README your own, just edit this file and use the handy template below (or feel free to structure it however you want - this is just a starting point!). Thanks to [makeareadme.com](https://www.makeareadme.com/) for this template.

## Suggestions for a good README

Every project is different, so consider which of these sections apply to yours. The sections used in the template are suggestions for most open source projects. Also keep in mind that while a README can be too long and detailed, too long is better than too short. If you think your README is too long, consider utilizing another form of documentation rather than cutting out information.

## Name
Choose a self-explaining name for your project.

## Description
Let people know what your project can do specifically. Provide context and add a link to any reference visitors might be unfamiliar with. A list of Features or a Background subsection can also be added here. If there are alternatives to your project, this is a good place to list differentiating factors.

## Badges
On some READMEs, you may see small images that convey metadata, such as whether or not all the tests are passing for the project. You can use Shields to add some to your README. Many services also have instructions for adding a badge.

## Visuals
Depending on what you are making, it can be a good idea to include screenshots or even a video (you'll frequently see GIFs rather than actual videos). Tools like ttygif can help, but check out Asciinema for a more sophisticated method.

## Installation
Within a particular ecosystem, there may be a common way of installing things, such as using Yarn, NuGet, or Homebrew. However, consider the possibility that whoever is reading your README is a novice and would like more guidance. Listing specific steps helps remove ambiguity and gets people to using your project as quickly as possible. If it only runs in a specific context like a particular programming language version or operating system or has dependencies that have to be installed manually, also add a Requirements subsection.

## Usage
Use examples liberally, and show the expected output if you can. It's helpful to have inline the smallest example of usage that you can demonstrate, while providing links to more sophisticated examples if they are too long to reasonably include in the README.

## Support
Tell people where they can go to for help. It can be any combination of an issue tracker, a chat room, an email address, etc.

## Roadmap
If you have ideas for releases in the future, it is a good idea to list them in the README.

## Contributing
State if you are open to contributions and what your requirements are for accepting them.

For people who want to make changes to your project, it's helpful to have some documentation on how to get started. Perhaps there is a script that they should run or some environment variables that they need to set. Make these steps explicit. These instructions could also be useful to your future self.

You can also document commands to lint the code or run tests. These steps help to ensure high code quality and reduce the likelihood that the changes inadvertently break something. Having instructions for running tests is especially helpful if it requires external setup, such as starting a Selenium server for testing in a browser.

## Authors and acknowledgment
Show your appreciation to those who have contributed to the project.

## License
For open source projects, say how it is licensed.

## Project status
If you have run out of energy or time for your project, put a note at the top of the README saying that development has slowed down or stopped completely. Someone may choose to fork your project or volunteer to step in as a maintainer or owner, allowing your project to keep going. You can also make an explicit request for maintainers.
>>>>>>> origin/main
