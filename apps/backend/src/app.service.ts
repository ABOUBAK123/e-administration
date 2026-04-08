import { Injectable } from '@nestjs/common';

@Injectable()
export class AppService {
  getHealth() {
    return {
      status: 'OK',
      timestamp: new Date().toISOString(),
    };
  }

  getApiInfo() {
    return {
      name: 'E-Parapheur Connect & Sign API',
      version: '1.0.0',
      description: 'Document management platform with electronic signatures',
      endpoints: {
        docs: '/api/docs',
        health: '/api/health',
        auth: '/api/v1/auth',
        documents: '/api/v1/documents',
        signatures: '/api/v1/signatures',
        workflows: '/api/v1/workflows',
        qrcode: '/api/v1/qrcode',
      },
    };
  }
}
