import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { ChatGateway } from './chat.gateway';
import { NotificationsModule } from '../notifications/notifications.module';
import { ChatMessageEntity } from './chat-message.entity';

@Module({
  imports: [NotificationsModule, TypeOrmModule.forFeature([ChatMessageEntity])],
  providers: [ChatGateway],
})
export class ChatModule {}
