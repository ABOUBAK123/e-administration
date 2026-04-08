import 'reflect-metadata';
import { DataSource, DataSourceOptions } from 'typeorm';
import { join } from 'path';

const dbType = (process.env.DB_TYPE || 'mysql') as 'mysql' | 'postgres';

const baseOptions: DataSourceOptions = {
  type: dbType,
  host: process.env.DB_HOST || 'localhost',
  port: Number(process.env.DB_PORT || (dbType === 'mysql' ? 3306 : 5432)),
  username: process.env.DB_USER || (dbType === 'mysql' ? 'epadmin_app' : 'epAdmin'),
  password: process.env.DB_PASSWORD || (dbType === 'mysql' ? 'epAdminDev2024!' : 'epPassword'),
  database: process.env.DB_NAME || 'e_parapheur',
  entities: [join(process.cwd(), 'src/**/*.entity.ts')],
  migrations: [join(process.cwd(), 'src/database/migrations/*.ts')],
  synchronize: false,
  logging: false,
};

if (dbType === 'postgres') {
  (baseOptions as DataSourceOptions & { ssl?: boolean }).ssl = process.env.DB_SSL === 'true';
}

export default new DataSource(baseOptions);
