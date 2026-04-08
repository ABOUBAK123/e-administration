import { io, Socket } from 'socket.io-client'

export interface ChatMessage {
  id: string
  senderId: string
  senderName: string
  senderInitials: string
  text: string
  timestamp: string
  room: string
}

export interface TypingUpdate {
  userId: string
  userName: string
  isTyping: boolean
}

export interface RoomUser {
  socketId: string
  userId: string
  userName: string
  userInitials: string
  room: string
}

const BACKEND_URL = (
  import.meta.env.VITE_API_URL?.replace(/\/api(?:\/v\d+)?\/?$/, '') ||
  'http://localhost:3000'
)

class ChatService {
  private socket: Socket | null = null

  private generateClientMessageId(): string {
    return `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`
  }

  connect(): Socket {
    if (this.socket) {
      if (!this.socket.connected) {
        this.socket.connect()
      }
      return this.socket
    }

    const socket = io(`${BACKEND_URL}/chat`, {
      transports: ['websocket', 'polling'],
      autoConnect: true,
    })

    socket.on('connect', () => console.log('[Chat] Connecté, id:', socket.id))
    socket.on('disconnect', () => console.log('[Chat] Déconnecté'))
    socket.on('connect_error', (err) => console.error('[Chat] Erreur connexion:', err.message))

    this.socket = socket
    return socket
  }

  getSocket(): Socket | null {
    return this.socket
  }

  disconnect() {
    this.socket?.disconnect()
    this.socket = null
  }

  joinRoom(room: string, userId: string, userName: string, userInitials: string) {
    this.socket?.emit('room:join', { room, userId, userName, userInitials })
  }

  sendMessage(text: string, room: string) {
    this.socket?.emit('message:send', {
      text,
      room,
      clientMessageId: this.generateClientMessageId(),
    })
  }

  startTyping(room: string) {
    this.socket?.emit('typing:start', { room })
  }

  stopTyping(room: string) {
    this.socket?.emit('typing:stop', { room })
  }
}

export const chatService = new ChatService()
