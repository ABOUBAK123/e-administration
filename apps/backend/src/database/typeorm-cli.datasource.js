const { DataSource } = require('typeorm');
const { join } = require('path');

const dbType = process.env.DB_TYPE || 'mysql';

const options = {
  type: dbType,
  host: process.env.DB_HOST || 'localhost',
  port: Number(process.env.DB_PORT || (dbType === 'mysql' ? 3306 : 5432)),
  username: process.env.DB_USER || (dbType === 'mysql' ? 'epadmin_app' : 'epAdmin'),
  password: process.env.DB_PASSWORD || (dbType === 'mysql' ? 'epAdminDev2024!' : 'epPassword'),
  database: process.env.DB_NAME || 'e_parapheur',
  entities: [join(process.cwd(), 'dist/**/*.entity.js')],
  migrations: [join(process.cwd(), 'dist/database/migrations/*.js')],
  synchronize: false,
  logging: false,
};

if (dbType === 'postgres') {
  options.ssl = process.env.DB_SSL === 'true';
}

module.exports = new DataSource(options);
