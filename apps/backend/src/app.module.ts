import { Module } from '@nestjs/common';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { TypeOrmModule, TypeOrmModuleOptions } from '@nestjs/typeorm';
import { JwtModule } from '@nestjs/jwt';
import { PassportModule } from '@nestjs/passport';
import { join } from 'path';
import configuration from './config/configuration';
import { AppController } from './app.controller';
import { AppService } from './app.service';
import { UsersModule } from './modules/users/users.module';
import { DocumentsModule } from './modules/documents/documents.module';
import { SignaturesModule } from './modules/signatures/signatures.module';
import { WorkflowsModule } from './modules/workflows/workflows.module';
import { QrcodeModule } from './modules/qrcode/qrcode.module';
import { AuthModule } from './modules/auth/auth.module';
import { AdministrationModule } from './modules/administration/administration.module';
import { NotificationsModule } from './modules/notifications/notifications.module';
import { ChatModule } from './modules/chat/chat.module';

@Module({
  imports: [
    ConfigModule.forRoot({
      isGlobal: true,
      load: [configuration],
    }),
    TypeOrmModule.forRootAsync({
      imports: [ConfigModule],
      inject: [ConfigService],
      useFactory: (configService: ConfigService): TypeOrmModuleOptions => {
        const dbType = (configService.get<string>('database.type') || 'postgres') as
          | 'postgres'
          | 'mysql';
        const isDevelopment = configService.get('app.env') === 'development';

        return {
          type: dbType,
          host: configService.get('database.host'),
          port: configService.get('database.port'),
          username: configService.get('database.username'),
          password: configService.get('database.password'),
          database: configService.get('database.database'),
          autoLoadEntities: true,
          entities: [join(__dirname, '**/*.entity{.ts,.js}')],
          migrations: [join(__dirname, 'database/migrations/**/*{.ts,.js}')],
          synchronize: configService.get('database.synchronize') ?? isDevelopment,
          logging: configService.get('database.logging') ?? isDevelopment,
          ...(dbType === 'postgres' ? { ssl: configService.get('database.ssl') } : {}),
        } as TypeOrmModuleOptions;
      },
    }),
    PassportModule.register({ defaultStrategy: 'jwt' }),
    JwtModule.registerAsync({
      imports: [ConfigModule],
      inject: [ConfigService],
      useFactory: (configService: ConfigService) => ({
        secret: configService.get('jwt.secret'),
        signOptions: {
          expiresIn: configService.get('jwt.expirationTime'),
        },
      }),
    }),
    AuthModule,
    UsersModule,
    DocumentsModule,
    SignaturesModule,
    WorkflowsModule,
    QrcodeModule,
    AdministrationModule,
    NotificationsModule,
    ChatModule,
  ],
  controllers: [AppController],
  providers: [AppService],
})
export class AppModule {}
