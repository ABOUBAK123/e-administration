import {
  WebSocketGateway,
  WebSocketServer,
  SubscribeMessage,
  MessageBody,
  ConnectedSocket,
  OnGatewayConnection,
  OnGatewayDisconnect,
} from '@nestjs/websockets';
import { Logger, OnModuleInit } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { DataSource, Repository } from 'typeorm';
import { Server, Socket } from 'socket.io';
import { NotificationsService } from '../notifications/notifications.service';
import { ChatMessageEntity } from './chat-message.entity';

interface ChatMessage {
  id: string;
  senderId: string;
  senderName: string;
  senderInitials: string;
  text: string;
  timestamp: string;
  room: string;
}

interface ConnectedUser {
  socketId: string;
  userId: string;
  userName: string;
  userInitials: string;
  room: string;
}

@WebSocketGateway({
  cors: {
    origin: '*',
    credentials: true,
  },
  namespace: '/chat',
})
export class ChatGateway implements OnModuleInit, OnGatewayConnection, OnGatewayDisconnect {
  private readonly logger = new Logger(ChatGateway.name);
  private isChatStorageReady = false;
  private readonly messageIdempotencyWindowMs = 60_000;
  private recentMessageKeys: Map<string, number> = new Map();

  constructor(
    private readonly notificationsService: NotificationsService,
    @InjectRepository(ChatMessageEntity)
    private readonly chatMessageRepository: Repository<ChatMessageEntity>,
    private readonly dataSource: DataSource
  ) {}

  async onModuleInit() {
    await this.checkChatStorageHealth();
  }

  @WebSocketServer()
  server: Server;

  private connectedUsers: Map<string, ConnectedUser> = new Map();

  private cleanupRecentMessageKeys() {
    const now = Date.now();
    for (const [key, timestamp] of this.recentMessageKeys.entries()) {
      if (now - timestamp > this.messageIdempotencyWindowMs) {
        this.recentMessageKeys.delete(key);
      }
    }
  }

  private buildIdempotencyKey(
    senderId: string,
    room: string,
    clientMessageId?: string
  ): string | null {
    const normalized = String(clientMessageId || '').trim();
    if (!normalized) return null;
    return `${senderId}|${room}|${normalized}`;
  }

  handleConnection(client: Socket) {
    console.log(`[Chat] Client connecté : ${client.id}`);
  }

  handleDisconnect(client: Socket) {
    const user = this.connectedUsers.get(client.id);
    if (user) {
      this.server.to(user.room).emit('user:left', {
        userId: user.userId,
        userName: user.userName,
        connectedUsers: this.getRoomUsers(user.room),
      });
      this.connectedUsers.delete(client.id);
    }
    console.log(`[Chat] Client déconnecté : ${client.id}`);
  }

  private async checkChatStorageHealth() {
    const queryRunner = this.dataSource.createQueryRunner();
    try {
      await queryRunner.connect();
      const exists = await queryRunner.hasTable('chat_messages');
      this.isChatStorageReady = exists;

      if (!exists) {
        this.logger.error(
          '[Chat][HealthCheck] Table "chat_messages" introuvable. ' +
            'Exécutez la migration: npm run migration:run. ' +
            "Le chat temps réel reste actif, mais l'historique ne sera pas persisté tant que la table est absente."
        );
      } else {
        this.logger.log(
          '[Chat][HealthCheck] Table "chat_messages" détectée: persistance chat active.'
        );
      }
    } catch (error: any) {
      this.isChatStorageReady = false;
      this.logger.error(
        `[Chat][HealthCheck] Vérification base de données échouée: ${error?.message || error}. ` +
          'Le chat temps réel reste actif sans persistance.'
      );
    } finally {
      await queryRunner.release();
    }
  }

  /** Rejoindre ou créer une room */
  @SubscribeMessage('room:join')
  async handleJoinRoom(
    @MessageBody()
    data: {
      room: string;
      userId: string;
      userName: string;
      userInitials: string;
    },
    @ConnectedSocket() client: Socket
  ) {
    const { room, userId, userName, userInitials } = data;

    // Quitter les rooms précédentes
    const prevUser = this.connectedUsers.get(client.id);
    if (prevUser) {
      client.leave(prevUser.room);
    }

    client.join(room);

    const connectedUser: ConnectedUser = {
      socketId: client.id,
      userId,
      userName,
      userInitials,
      room,
    };
    this.connectedUsers.set(client.id, connectedUser);

    // Charger l'historique persistant (50 derniers) et l'envoyer au client.
    let history: ChatMessage[] = [];
    if (this.isChatStorageReady) {
      const persisted = await this.chatMessageRepository.find({
        where: { room },
        order: { createdAt: 'DESC' },
        take: 50,
      });

      history = persisted.reverse().map((item) => ({
        id: item.id,
        senderId: item.senderId,
        senderName: item.senderName,
        senderInitials: item.senderInitials,
        text: item.text,
        timestamp: item.createdAt.toISOString(),
        room: item.room,
      }));
    }

    client.emit('room:history', history);

    // Notifier les autres
    this.server.to(room).emit('user:joined', {
      userId,
      userName,
      connectedUsers: this.getRoomUsers(room),
    });

    console.log(`[Chat] ${userName} a rejoint la room "${room}"`);
  }

  /** Envoyer un message */
  @SubscribeMessage('message:send')
  async handleMessage(
    @MessageBody()
    data: {
      text: string;
      room: string;
      clientMessageId?: string;
    },
    @ConnectedSocket() client: Socket
  ) {
    const sender = this.connectedUsers.get(client.id);
    if (!sender) return;
    const trimmedText = data.text.trim();
    if (!trimmedText) return;

    this.cleanupRecentMessageKeys();
    const idempotencyKey = this.buildIdempotencyKey(sender.userId, data.room, data.clientMessageId);
    if (idempotencyKey && this.recentMessageKeys.has(idempotencyKey)) {
      this.logger.debug(`[Chat] Duplicate message ignored for key=${idempotencyKey}`);
      return;
    }
    if (idempotencyKey) {
      this.recentMessageKeys.set(idempotencyKey, Date.now());
    }

    const persistedMessage = this.isChatStorageReady
      ? await this.chatMessageRepository.save(
          this.chatMessageRepository.create({
            senderId: sender.userId,
            senderName: sender.userName,
            senderInitials: sender.userInitials,
            text: trimmedText,
            room: data.room,
          })
        )
      : ({
          id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
          createdAt: new Date(),
        } as Pick<ChatMessageEntity, 'id' | 'createdAt'>);

    const message: ChatMessage = {
      id: persistedMessage.id,
      senderId: sender.userId,
      senderName: sender.userName,
      senderInitials: sender.userInitials,
      text: trimmedText,
      timestamp: persistedMessage.createdAt.toISOString(),
      room: data.room,
    };

    // Diffuser à la room
    this.server.to(data.room).emit('message:received', message);

    // Ensure direct messages are delivered even when recipient is not currently in the same room.
    if (this.isDirectRoom(data.room)) {
      const recipientIds = this.parseDirectRoomUserIds(data.room).filter(
        (id) => id !== sender.userId
      );
      for (const recipientId of recipientIds) {
        // Fallback direct emit only to recipient sockets that are not currently in the DM room.
        // This avoids sending the same message twice when the recipient is already in the room.
        const recipientSockets = Array.from(this.connectedUsers.values()).filter(
          (u) => u.userId === recipientId && u.room !== data.room
        );
        recipientSockets.forEach((u) =>
          this.server.to(u.socketId).emit('message:received', message)
        );

        this.notificationsService
          .createNotification({
            recipientId,
            title: 'Nouveau message',
            message: `${sender.userName} vous a envoyé un message.`,
            type: 'info',
            actionUrl: '/documents',
          })
          .catch((err) => {
            this.logger.warn(
              `Failed to create chat notification for ${recipientId}: ${err?.message || err}`
            );
          });
      }
      return;
    }

    // Channel notifications for connected room users (except sender).
    const channelRecipientIds = Array.from(
      new Set(
        this.getRoomUsers(data.room)
          .map((u) => u.userId)
          .filter((id) => id !== sender.userId)
      )
    );

    channelRecipientIds.forEach((recipientId) => {
      this.notificationsService
        .createNotification({
          recipientId,
          title: `Message dans #${data.room}`,
          message: `${sender.userName}: ${trimmedText.slice(0, 120)}`,
          type: 'info',
          actionUrl: '/documents',
        })
        .catch((err) => {
          this.logger.warn(
            `Failed to create channel chat notification for ${recipientId}: ${err?.message || err}`
          );
        });
    });
  }

  /** Indicateur "en train d'écrire" */
  @SubscribeMessage('typing:start')
  handleTypingStart(@MessageBody() data: { room: string }, @ConnectedSocket() client: Socket) {
    const sender = this.connectedUsers.get(client.id);
    if (!sender) return;
    client.to(data.room).emit('typing:update', {
      userId: sender.userId,
      userName: sender.userName,
      isTyping: true,
    });
  }

  @SubscribeMessage('typing:stop')
  handleTypingStop(@MessageBody() data: { room: string }, @ConnectedSocket() client: Socket) {
    const sender = this.connectedUsers.get(client.id);
    if (!sender) return;
    client.to(data.room).emit('typing:update', {
      userId: sender.userId,
      userName: sender.userName,
      isTyping: false,
    });
  }

  private getRoomUsers(room: string): ConnectedUser[] {
    return Array.from(this.connectedUsers.values()).filter((u) => u.room === room);
  }

  private isDirectRoom(room: string): boolean {
    return room.startsWith('dm:');
  }

  private parseDirectRoomUserIds(room: string): string[] {
    if (!this.isDirectRoom(room)) return [];
    const payload = room.slice(3);
    return payload.split('|').filter(Boolean);
  }
}
