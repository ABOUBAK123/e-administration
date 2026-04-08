#!/bin/bash

# E-Parapheur Connect & Sign - Quick Start Script
# This script sets up the development environment automatically

set -e

echo "🚀 E-Parapheur Connect & Sign - Setup Script"
echo "=============================================="

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check Node.js
echo -e "\n${YELLOW}Checking prerequisites...${NC}"
if ! command -v node &> /dev/null; then
    echo -e "${RED}❌ Node.js is not installed. Please install Node.js 18+${NC}"
    exit 1
fi

if ! command -v npm &> /dev/null; then
    echo -e "${RED}❌ npm is not installed. Please install npm 9+${NC}"
    exit 1
fi

NODE_VERSION=$(node -v | cut -d'v' -f2 | cut -d'.' -f1)
if [ "$NODE_VERSION" -lt 18 ]; then
    echo -e "${RED}❌ Node.js version must be 18 or higher (your version: $(node -v))${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Node.js $(node -v) found${NC}"
echo -e "${GREEN}✓ npm $(npm -v) found${NC}"

# Check Docker (optional)
if command -v docker &> /dev/null && command -v docker-compose &> /dev/null; then
    echo -e "${GREEN}✓ Docker $(docker --version | cut -d',' -f1) found${NC}"
    echo -e "${GREEN}✓ Docker Compose found${NC}"
    USE_DOCKER=true
else
    echo -e "${YELLOW}⚠ Docker/Docker Compose not found (optional for development)${NC}"
    USE_DOCKER=false
fi

# Install root dependencies
echo -e "\n${YELLOW}Installing dependencies...${NC}"
npm install

# Create environment files
echo -e "\n${YELLOW}Setting up environment files...${NC}"

if [ ! -f "apps/backend/.env" ]; then
    cp apps/backend/.env.example apps/backend/.env
    echo -e "${GREEN}✓ Created apps/backend/.env${NC}"
else
    echo -e "${YELLOW}✓ apps/backend/.env already exists (skipped)${NC}"
fi

if [ ! -f "apps/frontend/.env" ]; then
    cp apps/frontend/.env.example apps/frontend/.env
    echo -e "${GREEN}✓ Created apps/frontend/.env${NC}"
else
    echo -e "${YELLOW}✓ apps/frontend/.env already exists (skipped)${NC}"
fi

# Install package dependencies
echo -e "\n${YELLOW}Installing package dependencies...${NC}"
npm run backend:install
npm run frontend:install

echo -e "\n${GREEN}========================================${NC}"
echo -e "${GREEN}✓ Setup completed successfully!${NC}"
echo -e "${GREEN}========================================${NC}"

echo -e "\n${YELLOW}Next steps:${NC}"
if [ "$USE_DOCKER" = true ]; then
    echo -e "1. Start services with Docker:"
    echo -e "   ${GREEN}npm run docker:up${NC}"
    echo -e ""
    echo -e "2. Access the application:"
    echo -e "   - Frontend: ${GREEN}http://localhost:5173${NC}"
    echo -e "   - Backend: ${GREEN}http://localhost:3000${NC}"
    echo -e "   - API Docs: ${GREEN}http://localhost:3000/api/docs${NC}"
else
    echo -e "1. Start the backend:"
    echo -e "   ${GREEN}npm run backend:dev${NC}"
    echo -e ""
    echo -e "2. In another terminal, start the frontend:"
    echo -e "   ${GREEN}npm run frontend:dev${NC}"
    echo -e ""
    echo -e "3. Access the application:"
    echo -e "   - Frontend: ${GREEN}http://localhost:5173${NC}"
    echo -e "   - Backend: ${GREEN}http://localhost:3000${NC}"
    echo -e "   - API Docs: ${GREEN}http://localhost:3000/api/docs${NC}"
fi

echo -e "\n${YELLOW}Documentation:${NC}"
echo -e "- Getting Started: ${GREEN}docs/GETTING_STARTED.md${NC}"
echo -e "- Development: ${GREEN}docs/DEVELOPMENT.md${NC}"
echo -e "- Architecture: ${GREEN}docs/ARCHITECTURE.md${NC}"
echo -e "- API Reference: ${GREEN}docs/API.md${NC}"
echo -e "- Database: ${GREEN}docs/DATABASE.md${NC}"

echo -e "\n${GREEN}Happy coding! 🎉${NC}"
