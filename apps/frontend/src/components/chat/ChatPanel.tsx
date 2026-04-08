import { useEffect, useRef, useState, useCallback } from 'react'
import {
  MessageCircle, X, Send, Users, ChevronDown, Hash, ArrowLeft, AtSign, Lock,
} from 'lucide-react'
import { chatService, ChatMessage, TypingUpdate, RoomUser } from '../../services/chat'
import { useAuthStore } from '../../store/authStore'

const ROOMS = [
  { id: 'general',    label: 'Général' },
  { id: 'documents',  label: 'Documents' },
  { id: 'workflows',  label: 'Workflows' },
  { id: 'signatures', label: 'Signatures' },
]

type ChatMode = 'channels' | 'dm-list' | 'dm'

function formatTime(iso: string): string {
  const d = new Date(iso)
  return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

function getInitials(name: string): string {
  return name.split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2)
}

function avatarBg(initials: string): string {
  const colors = ['bg-blue-500', 'bg-emerald-500', 'bg-violet-500', 'bg-rose-500', 'bg-amber-500', 'bg-cyan-500']
  const idx = (initials.charCodeAt(0) + (initials.charCodeAt(1) || 0)) % colors.length
  return colors[idx]
}

function dmRoomId(a: string, b: string) {
  return `dm:${[a, b].sort().join('|')}`
}

function dedupeMessagesById(messages: ChatMessage[]): ChatMessage[] {
  const seen = new Set<string>()
  const unique: ChatMessage[] = []

  for (const message of messages) {
    const id = String(message.id || '')
    if (!id || seen.has(id)) continue
    seen.add(id)
    unique.push(message)
  }

  return unique
}

function getChatScope(): 'same-administration' | 'all' {
  return (localStorage.getItem('chat_scope') as 'same-administration' | 'all') || 'all'
}

// ─── Messages area ──────────────────────────────────────────────────────────
interface MessagesAreaProps {
  messages: ChatMessage[]
  typingUsers: Map<string, string>
  userId: string
  emptyLabel: string
  messagesEndRef: React.RefObject<HTMLDivElement>
}
function MessagesArea({ messages, typingUsers, userId, emptyLabel, messagesEndRef }: MessagesAreaProps) {
  const renderedMessages = dedupeMessagesById(messages)

  return (
    <div className="flex-1 overflow-y-auto px-4 py-3 space-y-3 bg-gray-50">
      {renderedMessages.length === 0 && (
        <div className="flex flex-col items-center justify-center h-full text-center text-gray-400 gap-2">
          <MessageCircle size={32} strokeWidth={1} className="text-gray-300" />
          <p className="text-sm font-medium text-gray-500">{emptyLabel}</p>
          <p className="text-xs">Soyez le premier à écrire !</p>
        </div>
      )}
      {renderedMessages.map((msg, index) => {
        const isOwn = msg.senderId === userId
        return (
          <div key={`${msg.id}-${msg.timestamp}-${index}`} className={`flex gap-2 ${isOwn ? 'flex-row-reverse' : 'flex-row'}`}>
            {!isOwn && (
              <div className={`h-7 w-7 rounded-full ${avatarBg(msg.senderInitials)} text-white text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5`}>
                {msg.senderInitials}
              </div>
            )}
            <div className={`max-w-[75%] flex flex-col gap-0.5 ${isOwn ? 'items-end' : 'items-start'}`}>
              {!isOwn && <span className="text-[11px] text-gray-500 font-medium px-1">{msg.senderName}</span>}
              <div className={`rounded-2xl px-3 py-2 text-sm leading-relaxed break-words ${isOwn ? 'bg-[#173b9f] text-white rounded-tr-sm' : 'bg-white text-gray-800 shadow-sm border border-gray-100 rounded-tl-sm'}`}>
                {msg.text}
              </div>
              <span className={`text-[10px] text-gray-400 px-1 ${isOwn ? 'text-right' : ''}`}>{formatTime(msg.timestamp)}</span>
            </div>
          </div>
        )
      })}
      {typingUsers.size > 0 && (
        <div className="flex gap-2 items-center">
          <div className="bg-white rounded-2xl rounded-tl-sm px-3 py-2 shadow-sm border border-gray-100 flex items-center gap-1.5">
            <span className="text-xs text-gray-500 italic">
              {Array.from(typingUsers.values()).join(', ')} {typingUsers.size > 1 ? 'écrivent' : 'écrit'}...
            </span>
            <span className="flex gap-0.5">
              {[0, 1, 2].map((i) => (
                <span key={i} className="h-1.5 w-1.5 rounded-full bg-gray-400 block animate-bounce" style={{ animationDelay: `${i * 0.15}s` }} />
              ))}
            </span>
          </div>
        </div>
      )}
      <div ref={messagesEndRef} />
    </div>
  )
}

// ─── Input bar ──────────────────────────────────────────────────────────────
interface InputBarProps {
  inputText: string
  onChange: (e: React.ChangeEvent<HTMLInputElement>) => void
  onKeyDown: (e: React.KeyboardEvent) => void
  onSend: () => void
  userInitials: string
  placeholder: string
  inputRef: React.RefObject<HTMLInputElement>
}
function InputBar({ inputText, onChange, onKeyDown, onSend, userInitials, placeholder, inputRef }: InputBarProps) {
  return (
    <div className="shrink-0 border-t border-gray-100 bg-white px-3 py-2.5 flex items-center gap-2">
      <div className={`h-7 w-7 rounded-full ${avatarBg(userInitials)} text-white text-[10px] font-bold flex items-center justify-center shrink-0`}>
        {userInitials}
      </div>
      <input
        ref={inputRef}
        type="text"
        value={inputText}
        onChange={onChange}
        onKeyDown={onKeyDown}
        placeholder={placeholder}
        className="flex-1 text-sm bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#173b9f]/30 focus:border-[#173b9f] transition"
      />
      <button
        onClick={onSend}
        disabled={!inputText.trim()}
        className="h-9 w-9 rounded-xl bg-[#173b9f] text-white flex items-center justify-center hover:bg-[#1f47bb] disabled:opacity-40 disabled:cursor-not-allowed transition shrink-0"
      >
        <Send size={15} />
      </button>
    </div>
  )
}

// ─── Main component ─────────────────────────────────────────────────────────
export default function ChatPanel() {
  const user = useAuthStore((s) => s.user)
  const [open, setOpen] = useState(false)

  // Mode: channels | dm-list | dm
  const [chatMode, setChatMode] = useState<ChatMode>('channels')

  // Channels
  const [currentRoom, setCurrentRoom] = useState('general')
  const [channelMessages, setChannelMessages] = useState<ChatMessage[]>([])
  const [showRooms, setShowRooms] = useState(false)

  // DM
  const [dmWith, setDmWith] = useState<RoomUser | null>(null)
  const [dmMessages, setDmMessages] = useState<ChatMessage[]>([])
  const [dmSearch, setDmSearch] = useState('')

  // Shared
  const [connectedUsers, setConnectedUsers] = useState<RoomUser[]>([])
  const [typingUsers, setTypingUsers] = useState<Map<string, string>>(new Map())
  const [inputText, setInputText] = useState('')
  const [showUsers, setShowUsers] = useState(false)
  const [unreadCount, setUnreadCount] = useState(0)

  const messagesEndRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLInputElement>(null)
  const typingTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  const userId = user?.id || 'unknown'
  const userName = user?.fullName || user?.username || 'Utilisateur'
  const userInitials = getInitials(userName)
  const chatScope = getChatScope()
  const isDmBlocked = chatScope === 'same-administration'

  const scrollToBottom = () => messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })

  const activeRoom = chatMode === 'dm' && dmWith ? dmRoomId(userId, dmWith.userId) : currentRoom

  // Connexion socket + abonnements
  useEffect(() => {
    if (!user) return
    const socket = chatService.connect()

    socket.on('room:history', (history: ChatMessage[]) => {
      const normalizedHistory = dedupeMessagesById(history)
      if (chatMode === 'dm') setDmMessages(normalizedHistory)
      else setChannelMessages(normalizedHistory)
      setTimeout(scrollToBottom, 50)
    })

    socket.on('message:received', (msg: ChatMessage) => {
      if (chatMode === 'dm' && dmWith) {
        if (msg.room === dmRoomId(userId, dmWith.userId)) {
          setDmMessages((prev) => dedupeMessagesById([...prev, msg]))
          if (!open) setUnreadCount((c) => c + 1)
          setTimeout(scrollToBottom, 50)
        }
      } else if (msg.room === currentRoom) {
        setChannelMessages((prev) => dedupeMessagesById([...prev, msg]))
        if (!open) setUnreadCount((c) => c + 1)
        setTimeout(scrollToBottom, 50)
      }
    })

    socket.on('user:joined', (data: { connectedUsers: RoomUser[] }) => setConnectedUsers(data.connectedUsers))
    socket.on('user:left',   (data: { connectedUsers: RoomUser[] }) => setConnectedUsers(data.connectedUsers))

    socket.on('typing:update', (data: TypingUpdate) => {
      setTypingUsers((prev) => {
        const next = new Map(prev)
        data.isTyping ? next.set(data.userId, data.userName) : next.delete(data.userId)
        return next
      })
    })

    chatService.joinRoom(activeRoom, userId, userName, userInitials)

    return () => {
      socket.off('room:history'); socket.off('message:received')
      socket.off('user:joined');  socket.off('user:left'); socket.off('typing:update')
    }
  }, [user, currentRoom, chatMode, dmWith]) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (open) {
      setUnreadCount(0)
      setTimeout(() => inputRef.current?.focus(), 100)
      setTimeout(scrollToBottom, 100)
    }
  }, [open])

  const switchRoom = (roomId: string) => {
    setCurrentRoom(roomId)
    setChannelMessages([])
    setTypingUsers(new Map())
    chatService.joinRoom(roomId, userId, userName, userInitials)
    setShowRooms(false)
  }

  const openDm = (target: RoomUser) => {
    setDmWith(target)
    setDmMessages([])
    setTypingUsers(new Map())
    setChatMode('dm')
    setShowUsers(false)
    chatService.joinRoom(dmRoomId(userId, target.userId), userId, userName, userInitials)
    setInputText('')
    setTimeout(() => inputRef.current?.focus(), 100)
  }

  const backToChannels = () => {
    setChatMode('channels')
    setDmWith(null)
    setDmMessages([])
    setTypingUsers(new Map())
    chatService.joinRoom(currentRoom, userId, userName, userInitials)
    setInputText('')
  }

  const handleSend = () => {
    const text = inputText.trim()
    if (!text) return
    chatService.sendMessage(text, activeRoom)
    chatService.stopTyping(activeRoom)
    setInputText('')
    if (typingTimerRef.current) clearTimeout(typingTimerRef.current)
  }

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend() }
  }

  const handleInputChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      setInputText(e.target.value)
      chatService.startTyping(activeRoom)
      if (typingTimerRef.current) clearTimeout(typingTimerRef.current)
      typingTimerRef.current = setTimeout(() => chatService.stopTyping(activeRoom), 2000)
    },
    [activeRoom],
  )

  const currentRoomLabel = ROOMS.find((r) => r.id === currentRoom)?.label ?? currentRoom
  const otherUsers = connectedUsers.filter((u) => u.userId !== userId)
  const filteredUsers = otherUsers.filter((u) => u.userName.toLowerCase().includes(dmSearch.toLowerCase()))

  if (!user) return null

  return (
    <>
      {/* Bubble flottante */}
      <button
        onClick={() => setOpen((v) => !v)}
        className={`fixed bottom-6 right-6 z-50 h-14 w-14 rounded-full text-white shadow-2xl flex items-center justify-center transition-all duration-200 active:scale-95 ${unreadCount > 0 && !open ? 'bg-orange-500 hover:bg-orange-600 animate-pulse ring-4 ring-orange-300/60' : 'bg-[#173b9f] hover:bg-[#1f47bb]'}`}
        aria-label="Chat"
      >
        {open ? <X size={22} /> : (
          <>
            <MessageCircle size={22} />
            {unreadCount > 0 && (
              <span className="absolute -top-1 -right-1 h-5 w-5 rounded-full bg-orange-600 text-[10px] font-bold flex items-center justify-center ring-2 ring-white animate-pulse">
                {unreadCount > 9 ? '9+' : unreadCount}
              </span>
            )}
          </>
        )}
      </button>

      {/* Fenêtre chat */}
      {open && (
        <div className="fixed bottom-20 right-4 sm:right-6 z-50 w-[calc(100vw-2rem)] sm:w-[360px] md:w-[380px] h-[500px] max-h-[calc(100vh-6rem)] bg-white rounded-2xl shadow-2xl border border-gray-200 flex flex-col overflow-hidden">

          {/* ── Header ────────────────────────────────────────────────── */}
          <div className="bg-[#173b9f] text-white px-4 py-3 flex items-center justify-between shrink-0">
            <div className="flex items-center gap-2 min-w-0">
              {(chatMode === 'dm' || chatMode === 'dm-list') && (
                <button onClick={backToChannels} className="hover:text-white/70 transition shrink-0">
                  <ArrowLeft size={16} />
                </button>
              )}
              {chatMode === 'dm' && dmWith ? (
                <>
                  <div className={`h-6 w-6 rounded-full ${avatarBg(dmWith.userInitials)} text-[9px] font-bold flex items-center justify-center shrink-0`}>
                    {dmWith.userInitials}
                  </div>
                  <div className="min-w-0">
                    <p className="font-semibold text-sm truncate leading-tight">{dmWith.userName}</p>
                    <p className="text-[10px] text-blue-200 leading-tight">Message direct</p>
                  </div>
                </>
              ) : chatMode === 'dm-list' ? (
                <span className="font-semibold text-sm">Choisir un utilisateur</span>
              ) : (
                <><MessageCircle size={18} /><span className="font-semibold text-sm">Chat en direct</span></>
              )}
            </div>

            <div className="flex items-center gap-2 shrink-0">
              {chatMode === 'channels' && (
                <>
                  <div className="relative">
                    <button
                      onClick={() => { setShowRooms((v) => !v); setShowUsers(false) }}
                      className="flex items-center gap-1 text-xs bg-white/20 hover:bg-white/30 rounded-lg px-2 py-1 transition"
                    >
                      <Hash size={12} />
                      <span className="max-w-[70px] truncate">{currentRoomLabel}</span>
                      <ChevronDown size={12} className={`transition-transform ${showRooms ? 'rotate-180' : ''}`} />
                    </button>
                    {showRooms && (
                      <div className="absolute top-full right-0 mt-1 bg-white rounded-xl shadow-xl border border-gray-100 py-1 w-44 z-10">
                        {ROOMS.map((r) => (
                          <button
                            key={r.id}
                            onClick={() => switchRoom(r.id)}
                            className={`w-full flex items-center gap-2 px-3 py-2 text-sm text-left hover:bg-gray-50 transition ${r.id === currentRoom ? 'text-[#173b9f] font-medium' : 'text-gray-700'}`}
                          >
                            <Hash size={13} className="text-gray-400" /> {r.label}
                          </button>
                        ))}
                      </div>
                    )}
                  </div>
                  <button
                    onClick={() => { setShowUsers((v) => !v); setShowRooms(false) }}
                    className={`flex items-center gap-1 text-xs rounded-lg px-2 py-1 transition ${showUsers ? 'bg-white/30' : 'bg-white/20 hover:bg-white/30'}`}
                  >
                    <Users size={14} /><span>{connectedUsers.length}</span>
                  </button>
                </>
              )}
              <button onClick={() => setOpen(false)} className="hover:text-white/70 transition">
                <X size={17} />
              </button>
            </div>
          </div>

          {/* ── Onglets Salons / Messages directs ──────────────────────── */}
          {(chatMode === 'channels' || chatMode === 'dm-list') && (
            <div className="shrink-0 flex bg-[#122d80]">
              <button
                onClick={backToChannels}
                className={`flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-semibold transition ${chatMode === 'channels' ? 'text-white bg-white/15 border-b-2 border-white' : 'text-white/60 hover:text-white hover:bg-white/10'}`}
              >
                <Hash size={12} /> Salons
              </button>
              <button
                onClick={() => setChatMode('dm-list')}
                className={`flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-semibold transition ${chatMode === 'dm-list' ? 'text-white bg-white/15 border-b-2 border-white' : 'text-white/60 hover:text-white hover:bg-white/10'}`}
              >
                <AtSign size={12} /> Messages directs
              </button>
            </div>
          )}

          {/* ── Utilisateurs connectés (mode channels) ─────────────────── */}
          {chatMode === 'channels' && showUsers && (
            <div className="bg-blue-50 border-b border-blue-100 px-4 py-2 shrink-0">
              <p className="text-xs font-semibold text-blue-700 mb-1.5">
                {connectedUsers.length} connecté{connectedUsers.length > 1 ? 's' : ''}
              </p>
              <div className="flex flex-wrap gap-1.5">
                {connectedUsers.map((u) => (
                  <button
                    key={u.socketId}
                    onClick={() => u.userId !== userId && !isDmBlocked && openDm(u)}
                    disabled={u.userId === userId || isDmBlocked}
                    className="flex items-center gap-1.5 bg-white rounded-full px-2 py-0.5 shadow-sm border border-blue-100 hover:border-blue-300 hover:bg-blue-50 transition disabled:cursor-default disabled:opacity-70"
                    title={u.userId === userId ? 'Vous' : (isDmBlocked ? 'Chat limité à la même administration' : `Message direct à ${u.userName}`)}
                  >
                    <span className={`h-5 w-5 rounded-full ${avatarBg(u.userInitials)} text-white text-[9px] font-bold flex items-center justify-center`}>{u.userInitials}</span>
                    <span className="text-xs text-gray-700 max-w-[80px] truncate">{u.userName}</span>
                    {u.userId === userId && <span className="text-[9px] text-blue-500">(vous)</span>}
                  </button>
                ))}
              </div>
              {isDmBlocked && otherUsers.length > 0 && (
                <p className="text-[10px] text-amber-600 mt-1.5 flex items-center gap-1"><Lock size={10} /> Messages directs limités à la même administration</p>
              )}
            </div>
          )}

          {/* ── Vue liste DM ───────────────────────────────────────────── */}
          {chatMode === 'dm-list' && (
            <div className="flex-1 flex flex-col overflow-hidden">
              {isDmBlocked && (
                <div className="mx-4 mt-3 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2 flex gap-2 items-start shrink-0">
                  <Lock size={13} className="text-amber-500 mt-0.5 shrink-0" />
                  <p className="text-xs text-amber-700">
                    Les messages directs sont limités aux utilisateurs de <strong>la même administration</strong> selon la configuration.
                  </p>
                </div>
              )}
              <div className="px-4 py-2 shrink-0">
                <input
                  type="text"
                  value={dmSearch}
                  onChange={(e) => setDmSearch(e.target.value)}
                  placeholder="Rechercher un utilisateur..."
                  className="w-full text-sm bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#173b9f]/30 focus:border-[#173b9f] transition"
                />
              </div>
              <div className="flex-1 overflow-y-auto px-4 space-y-1 pb-3">
                {filteredUsers.length === 0 && (
                  <div className="flex flex-col items-center justify-center py-12 text-gray-400 gap-2">
                    <Users size={28} strokeWidth={1} className="text-gray-300" />
                    <p className="text-sm">{otherUsers.length === 0 ? 'Aucun autre utilisateur connecté' : 'Aucun résultat'}</p>
                  </div>
                )}
                {filteredUsers.map((u) => (
                  <button
                    key={u.socketId}
                    onClick={() => !isDmBlocked && openDm(u)}
                    disabled={isDmBlocked}
                    className="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-blue-50 transition text-left border border-transparent hover:border-blue-100 disabled:opacity-60 disabled:cursor-not-allowed"
                  >
                    <div className={`h-9 w-9 rounded-full ${avatarBg(u.userInitials)} text-white text-xs font-bold flex items-center justify-center shrink-0`}>
                      {u.userInitials}
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="text-sm font-medium text-gray-800 truncate">{u.userName}</p>
                      <p className="text-xs text-emerald-500 flex items-center gap-1">
                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 inline-block" /> En ligne
                      </p>
                    </div>
                    {!isDmBlocked
                      ? <span className="text-xs text-[#173b9f] font-medium shrink-0">Écrire →</span>
                      : <Lock size={13} className="text-amber-400 shrink-0" />
                    }
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* ── Vue conversation DM ────────────────────────────────────── */}
          {chatMode === 'dm' && (
            <>
              <MessagesArea
                messages={dmMessages}
                typingUsers={typingUsers}
                userId={userId}
                emptyLabel={dmWith ? `Aucun message avec ${dmWith.userName}` : 'Message direct'}
                messagesEndRef={messagesEndRef}
              />
              <InputBar
                inputText={inputText}
                onChange={handleInputChange}
                onKeyDown={handleKeyDown}
                onSend={handleSend}
                userInitials={userInitials}
                placeholder={`Message à ${dmWith?.userName ?? ''}…`}
                inputRef={inputRef}
              />
            </>
          )}

          {/* ── Vue salons ─────────────────────────────────────────────── */}
          {chatMode === 'channels' && (
            <>
              <MessagesArea
                messages={channelMessages}
                typingUsers={typingUsers}
                userId={userId}
                emptyLabel={`Aucun message dans #${currentRoomLabel}`}
                messagesEndRef={messagesEndRef}
              />
              <InputBar
                inputText={inputText}
                onChange={handleInputChange}
                onKeyDown={handleKeyDown}
                onSend={handleSend}
                userInitials={userInitials}
                placeholder={`Message #${currentRoomLabel}…`}
                inputRef={inputRef}
              />
            </>
          )}
        </div>
      )}
    </>
  )
}