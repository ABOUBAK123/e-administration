export default () => {
  const databaseType = process.env.DB_TYPE ?? 'postgres';

  return {
    app: {
      env: process.env.NODE_ENV || 'development',
      port: process.env.API_PORT || 3000,
      url: process.env.API_URL || 'http://localhost:3000',
      frontendUrl: process.env.FRONTEND_URL || 'http://localhost:5173',
    },
    database: {
      type: databaseType,
      host: process.env.DB_HOST ?? 'localhost',
      port: parseInt(process.env.DB_PORT ?? (databaseType === 'mysql' ? '3306' : '5432'), 10),
      username: process.env.DB_USER ?? 'epAdmin',
      password: process.env.DB_PASSWORD,
      database: process.env.DB_NAME ?? 'e_parapheur',
      ssl: process.env.DB_SSL === 'true',
      synchronize: process.env.DB_SYNCHRONIZE === 'true',
      logging: process.env.DB_LOGGING === 'true',
    },
    jwt: {
      secret: process.env.JWT_SECRET,
      expirationTime: process.env.JWT_EXPIRATION || '3600s',
    },
    onlyoffice: {
      url: process.env.ONLYOFFICE_URL || 'http://localhost/onlyoffice',
    },
    redis: {
      host: process.env.REDIS_HOST || 'localhost',
      port: parseInt(process.env.REDIS_PORT || '6379', 10),
      password: process.env.REDIS_PASSWORD,
    },
    mail: {
      host: process.env.MAIL_HOST || '',
      port: parseInt(process.env.MAIL_PORT || '587', 10),
      secure: process.env.MAIL_SECURE === 'true',
      user: process.env.MAIL_USER || '',
      password: process.env.MAIL_PASSWORD || '',
      from: process.env.MAIL_FROM || 'noreply@e-parapheur.local',
    },
    storage: {
      path: process.env.STORAGE_PATH || './storage',
      maxFileSize: parseInt(process.env.MAX_FILE_SIZE || '104857600', 10),
    },
    signature: {
      algorithm: process.env.SIGNATURE_ALGORITHM || 'SHA256',
      keyPath: process.env.SIGNATURE_KEY_PATH || './certs/signature.key',
    },
    qrcode: {
      size: parseInt(process.env.QR_CODE_SIZE || '200', 10),
      errorCorrection: process.env.QR_CODE_ERROR_CORRECTION || 'H',
    },
  };
};
