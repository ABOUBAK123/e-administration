import { NestFactory } from '@nestjs/core';
import { ValidationPipe, VersioningType } from '@nestjs/common';
import { SwaggerModule, DocumentBuilder } from '@nestjs/swagger';
import { NestExpressApplication } from '@nestjs/platform-express';
import { join } from 'path';
import { json, urlencoded } from 'express';
import helmet from 'helmet';
import { AppModule } from './app.module';

async function bootstrap() {
  const app = await NestFactory.create<NestExpressApplication>(AppModule);
  const isProduction = (process.env.NODE_ENV || '').toLowerCase() === 'production';

  if (isProduction && !process.env.JWT_SECRET) {
    throw new Error('JWT_SECRET is required in production');
  }

  if (isProduction && !process.env.DB_PASSWORD) {
    throw new Error('DB_PASSWORD is required in production');
  }

  app.use(
    helmet({
      crossOriginResourcePolicy: false,
      contentSecurityPolicy: false,
    })
  );

  const corsFromEnv = String(process.env.CORS_ORIGIN || '')
    .split(',')
    .map((origin) => origin.trim())
    .filter(Boolean);

  const allowedOrigins = new Set([
    'http://localhost:5173',
    'http://localhost:5174',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:5174',
    ...corsFromEnv,
  ]);

  // ── CORS headers FIRST — must run before any other middleware ──────────
  app.use((req: any, res: any, next: any) => {
    const origin = req.headers?.origin;
    if (!isProduction) {
      console.log(`[REQ] ${req.method} ${req.url} origin=${origin || 'none'}`);
      res.on('finish', () => {
        if (res.statusCode >= 400) {
          console.log(`[RES] ${req.method} ${req.url} status=${res.statusCode}`);
        }
      });
      if (req.method === 'OPTIONS' && req.url?.includes('/api/v1/users/profile/avatar')) {
        const requestedMethod = req.headers?.['access-control-request-method'] || 'none';
        const requestedHeaders = req.headers?.['access-control-request-headers'] || 'none';
        console.log(
          `[PREFLIGHT] ${req.url} reqMethod=${requestedMethod} reqHeaders=${requestedHeaders}`
        );
      }
    }
    if (origin && allowedOrigins.has(origin)) {
      res.header('Access-Control-Allow-Origin', origin);
      res.header('Vary', 'Origin');
      res.header('Access-Control-Allow-Credentials', 'true');
      res.header(
        'Access-Control-Allow-Headers',
        'Authorization, Content-Type, Accept, X-Requested-With, Origin, Cache-Control, Pragma'
      );
      res.header('Access-Control-Allow-Methods', 'GET, HEAD, PUT, PATCH, POST, DELETE, OPTIONS');
      res.header('Access-Control-Expose-Headers', 'Content-Disposition');
    }

    if (req.method === 'OPTIONS') {
      res.sendStatus(204);
      return;
    }

    next();
  });

  // Allow larger JSON payloads (base64 images saved from theming settings).
  app.use(json({ limit: '15mb' }));
  app.use(urlencoded({ limit: '15mb', extended: true }));

  // Expose local storage folder for user-uploaded assets (avatars, etc.)
  app.useStaticAssets(join(process.cwd(), 'storage'), {
    prefix: '/storage/',
  });

  // Expose uploaded documents for in-app PDF preview/positioning.
  app.useStaticAssets(join(process.cwd(), 'uploads'), {
    prefix: '/uploads/',
  });

  // Enable CORS (NestJS-level — complements the manual middleware above)
  app.enableCors({
    origin: (origin, callback) => {
      if (!origin || allowedOrigins.has(origin)) {
        callback(null, true);
        return;
      }
      callback(null, false);
    },
    methods: ['GET', 'HEAD', 'PUT', 'PATCH', 'POST', 'DELETE', 'OPTIONS'],
    allowedHeaders: [
      'Authorization',
      'Content-Type',
      'Accept',
      'X-Requested-With',
      'Origin',
      'Cache-Control',
      'Pragma',
    ],
    exposedHeaders: ['Content-Disposition'],
    credentials: true,
    optionsSuccessStatus: 204,
    preflightContinue: false,
  });

  // Set global prefix
  app.setGlobalPrefix('api');

  // Enable versioning
  app.enableVersioning({
    type: VersioningType.URI,
    defaultVersion: '1',
  });

  // Global validation pipe
  app.useGlobalPipes(
    new ValidationPipe({
      whitelist: true,
      forbidNonWhitelisted: true,
      transform: true,
      transformOptions: {
        enableImplicitConversion: true,
      },
    })
  );

  // Swagger documentation
  const config = new DocumentBuilder()
    .setTitle('E-Parapheur Connect & Sign API')
    .setDescription('API documentation for E-Parapheur Connect & Sign platform')
    .setVersion('1.0')
    .addBearerAuth()
    .addTag('auth', 'Authentication endpoints')
    .addTag('documents', 'Document management endpoints')
    .addTag('signatures', 'Digital signature endpoints')
    .addTag('workflows', 'Workflow management endpoints')
    .addTag('qrcode', 'QR code verification endpoints')
    .build();

  const document = SwaggerModule.createDocument(app, config);
  SwaggerModule.setup('api/docs', app, document);

  const port = process.env.API_PORT || 3000;
  await app.listen(port);
  console.log(`Application is running on: http://localhost:${port}`);
  console.log(`Swagger documentation: http://localhost:${port}/api/docs`);
}

bootstrap();
