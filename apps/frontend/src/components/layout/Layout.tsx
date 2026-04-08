import { useEffect, useRef, useState } from 'react'
import { Outlet, NavLink, useLocation, useNavigate } from 'react-router-dom'
import {
  Bell, Plus, LayoutDashboard, FileText, GitBranch, PenTool, Shield, QrCode, Inbox,
  Upload, FolderUp, FolderPlus, FilePlus2, FileSpreadsheet, Presentation, Files,
  FileType2, Layout as LayoutIcon, FileQuestion, ChevronDown, UserCircle, LogOut, ClipboardList,
} from 'lucide-react'
import { useAuthStore } from '../../store/authStore'
import { tokenStore } from '../../services/tokenStore'
import { getCurrentUserPermissions, type CurrentUserPermissionsResponse } from '../../services/auth'
import { getCurrentUserTheme } from '../../services/auth'
import { getPendingSignatures } from '../../services/signatures'
import { fetchNotifications, markNotificationAsRead, markAllNotificationsAsRead, type AppNotification } from '../../services/notifications'
import ChatPanel from '../chat/ChatPanel'

const API_ROOT = (import.meta.env.VITE_API_URL || 'http://localhost:3000/api/v1').replace(/\/api(?:\/v\d+)?\/?$/, '')

const toAvatarUrl = (avatar?: string) => {
  if (!avatar) return ''
  if (avatar.startsWith('http://') || avatar.startsWith('https://')) return avatar
  return `${API_ROOT}${avatar.startsWith('/') ? '' : '/'}${avatar}`
}

function Layout() {
  const user = useAuthStore((state) => state.user)
  const logout = useAuthStore((state) => state.logout)
  const navigate = useNavigate()
  const location = useLocation()
  const [dropdownOpen, setDropdownOpen] = useState(false)
  const dropdownRef = useRef<HTMLDivElement>(null)
  const [notificationsOpen, setNotificationsOpen] = useState(false)
  const notificationsRef = useRef<HTMLDivElement>(null)
  const [notificationsLoading, setNotificationsLoading] = useState(false)
  const [notifications, setNotifications] = useState<Array<{
    id: string
    title: string
    description: string
    createdAt: string
    isRead?: boolean
    type?: string
    actionUrl?: string | null
  }>>([])
  const [userMenuOpen, setUserMenuOpen] = useState(false)
  const userMenuRef = useRef<HTMLDivElement>(null)
  const [adminLogo, setAdminLogo] = useState<string | null>(null)
  const [menuColor, setMenuColor] = useState<string>(() => {
    try {
      return localStorage.getItem('ep_theme_menu_color') || '#173b9f'
    } catch {
      return '#173b9f'
    }
  })
  const [rolePermissions, setRolePermissions] = useState<Set<string>>(new Set())
  const [isElevatedRole, setIsElevatedRole] = useState(false)
  const [permissionsLoading, setPermissionsLoading] = useState(true)
  const [permissionsDebug, setPermissionsDebug] = useState<CurrentUserPermissionsResponse | null>(null)
  const showPermissionsDebug = new URLSearchParams(location.search).get('debugPermissions') === '1'

  const canAccessMenu = (menuKey: 'dashboard' | 'templates-shared' | 'documents' | 'workflows' | 'signatures' | 'reception' | 'act-requests' | 'administration' | 'qrcode') => {
    if (isElevatedRole) {
      return true
    }

    if (permissionsLoading) {
      return false
    }

    if (rolePermissions.size === 0) {
      return menuKey === 'dashboard'
    }

    if (rolePermissions.has(menuKey)) {
      return true
    }

    // Backward compatibility: if only reception permission exists, allow Demande d'actes.
    if (menuKey === 'act-requests' && Array.from(rolePermissions).some((permission) => permission === 'reception' || permission.startsWith('reception.'))) {
      return true
    }

    return Array.from(rolePermissions).some((permission) => permission === menuKey || permission.startsWith(`${menuKey}.`))
  }

  useEffect(() => {
    const loadRolePermissions = async () => {
      setPermissionsLoading(true)
      try {
        const result = await getCurrentUserPermissions()
        setIsElevatedRole(Boolean(result?.isElevated))
        setRolePermissions(new Set(Array.isArray(result?.permissions) ? result.permissions : []))
        setPermissionsDebug(result)
      } catch (error: any) {
        if (error?.response?.status === 401) {
          logout()
          navigate('/login')
          return
        }
        // Fallback minimal: keep dashboard visible if permission backend cannot be reached.
        setIsElevatedRole(false)
        setRolePermissions(new Set(['dashboard']))
        setPermissionsDebug(null)
      } finally {
        setPermissionsLoading(false)
      }
    }

    void loadRolePermissions()
  }, [user?.id])

  const emitDocumentAction = (action: string) => {
    window.dispatchEvent(new CustomEvent('documents:new-action', { detail: { action } }))
    setDropdownOpen(false)
  }

  const formatDate = (dateValue: string) => {
    const parsed = new Date(dateValue)
    if (Number.isNaN(parsed.getTime())) return 'Date inconnue'
    return parsed.toLocaleString('fr-FR')
  }

  const loadNotifications = async () => {
    if (!user?.id) {
      setNotifications([])
      return
    }

    if (!tokenStore.getAccessToken()) {
      setNotifications([])
      return
    }

    setNotificationsLoading(true)
    try {
      const [pending, appNotifs] = await Promise.all([
        getPendingSignatures(user.id).catch(() => []),
        fetchNotifications().catch(() => []),
      ])

      const signatureNotifs = pending.map((request: any) => ({
        id: `sig-${request.id}`,
        title: 'Signature en attente',
        description: request.document?.title
          ? `Vous devez signer le document « ${request.document.title} ».`
          : 'Vous avez une demande de signature en attente.',
        createdAt: request.createdAt || '',
        isRead: false,
        type: 'signature' as const,
      }))

      const workflowNotifs = appNotifs.map((n: AppNotification) => ({
        id: n.id,
        title: n.title,
        description: n.message,
        createdAt: n.createdAt || '',
        isRead: n.isRead,
        type: n.type,
        actionUrl: n.actionUrl,
      }))

      const merged = [...workflowNotifs, ...signatureNotifs].sort(
        (a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime(),
      )
      setNotifications(merged)
    } catch (error) {
      if ((error as any)?.response?.status === 401) {
        logout()
        navigate('/login')
        return
      }
      console.error('[Notifications] impossible de charger les notifications', error)
      setNotifications([])
    } finally {
      setNotificationsLoading(false)
    }
  }

  const handleMarkAsRead = async (notifId: string) => {
    if (notifId.startsWith('sig-')) return
    try {
      await markNotificationAsRead(notifId)
      setNotifications((prev) =>
        prev.map((n) => (n.id === notifId ? { ...n, isRead: true } : n)),
      )
    } catch {
      // silent
    }
  }

  const handleMarkAllAsRead = async () => {
    try {
      await markAllNotificationsAsRead()
      setNotifications((prev) => prev.map((n) => ({ ...n, isRead: true })))
    } catch {
      // silent
    }
  }

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setDropdownOpen(false)
      }
      if (notificationsRef.current && !notificationsRef.current.contains(e.target as Node)) {
        setNotificationsOpen(false)
      }
      if (userMenuRef.current && !userMenuRef.current.contains(e.target as Node)) {
        setUserMenuOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setDropdownOpen(false)
        setNotificationsOpen(false)
        setUserMenuOpen(false)
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [])

  const handleLogout = () => {
    logout()
    navigate('/login')
  }

  useEffect(() => {
    loadNotifications()

    if (!user?.id) return
    const interval = window.setInterval(loadNotifications, 30000)
    return () => window.clearInterval(interval)
  }, [user?.id])

  useEffect(() => {
    const loadBranding = async () => {
      try {
        if (!user?.id) {
          setAdminLogo(null)
          return
        }

        const theme = await getCurrentUserTheme().catch(() => null)

        if (theme?.menuColor) {
          setMenuColor(theme.menuColor)
          try {
            localStorage.setItem('ep_theme_menu_color', theme.menuColor)
          } catch {
            // Ignore localStorage failures.
          }
        }

        const selectedLogo = String(theme?.administrationLogo || '').trim()
        if (selectedLogo) {
          const fullLogoUrl = selectedLogo.startsWith('http')
            ? selectedLogo
            : `${API_ROOT}${selectedLogo}`
          setAdminLogo(fullLogoUrl)
          try {
            localStorage.setItem('ep_admin_logo', fullLogoUrl)
          } catch {
            // Ignore localStorage failures and keep runtime state.
          }
        } else {
          setAdminLogo(null)
          try {
            localStorage.removeItem('ep_admin_logo')
          } catch {
            // Ignore localStorage failures and keep runtime state.
          }
        }
      } catch {
        // Keep default branding if the settings endpoint is unavailable.
      }
    }

    void loadBranding()

    const handleStorageLogo = (e: StorageEvent) => {
      if (e.key === 'ep_theme_menu_color') {
        setMenuColor(e.newValue || '#173b9f')
        return
      }

      // For logo stability, always re-resolve from scoped API instead of trusting raw storage values.
      if (e.key === 'ep_admin_logo') {
        void loadBranding()
      }
    }

    const handleThemeChanged = (event: Event) => {
      const detail = (event as CustomEvent<{ menuColor?: string; administrationLogo?: string | null }>).detail
      if (detail?.menuColor) {
        setMenuColor(detail.menuColor)
      }

      // Theme updates may alter scoped assets; re-fetch current scoped logo.
      void loadBranding()
    }

    window.addEventListener('storage', handleStorageLogo)
    window.addEventListener('ep_theme_changed', handleThemeChanged as EventListener)
    return () => {
      window.removeEventListener('storage', handleStorageLogo)
      window.removeEventListener('ep_theme_changed', handleThemeChanged as EventListener)
    }
  }, [user?.id])

  useEffect(() => {
    setDropdownOpen(false)
    setNotificationsOpen(false)
  }, [location.pathname])

  const pageTitle: Record<string, { title: string; subtitle: string }> = {
    '/': { title: 'Tableau de bord', subtitle: 'Gérez vos documents et signatures' },
    '/templates-shared': { title: 'Templates partagés', subtitle: 'Accédez aux templates envoyés par l\'administrateur' },
    '/documents': { title: 'Mes Documents', subtitle: 'Gérez vos documents et dossiers' },
    '/workflows': { title: 'Workflows', subtitle: 'Pilotez vos circuits de validation' },
    '/signatures': { title: 'Signatures', subtitle: 'Suivez les signatures électroniques' },
    '/reception': { title: 'Réception', subtitle: 'Documents reçus via administrations destinataires (application)' },
    '/act-requests': { title: 'Demandes d\'actes', subtitle: 'Demandes recues, orientees par entite sous tutelle' },
    '/profile': { title: 'Mon profil', subtitle: 'Modifiez vos informations personnelles et votre mot de passe' },
    '/settings': { title: 'Administration', subtitle: 'Configurez votre espace de travail' },
    '/qr-verification': { title: 'Verification QR', subtitle: 'Verifiez l\'authenticite et telechargez le PDF signe' },
  }

  const current = pageTitle[location.pathname] || pageTitle['/']

  return (
    <div className="flex h-screen bg-[#f1f2f5]">
      <aside className="w-72 text-white flex flex-col" style={{ backgroundColor: menuColor, transition: 'background-color 0.5s ease' }}>
        <div className="p-7">
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 rounded-lg bg-white/90 overflow-hidden flex items-center justify-center shrink-0">
              {adminLogo ? (
                <img src={adminLogo} alt="Logo" className="h-full w-full object-contain" />
              ) : (
                <span className="font-black text-lg leading-none select-none" style={{ color: menuColor }}>E</span>
              )}
            </div>
            <div>
              <h1 className="text-2xl font-bold leading-7">E-Parapheur</h1>
              <p className="text-blue-100 text-sm">Connect & Sign</p>
            </div>
          </div>
        </div>

        <nav className="px-4 space-y-2 text-base font-medium">
          {canAccessMenu('dashboard') && (
            <NavLink
              to="/"
              className={({ isActive }) => `flex items-center gap-3 px-5 py-3 rounded-xl transition ${isActive ? 'bg-white/15 shadow-inner border border-white/25' : 'hover:bg-white/10'}`}
            >
              <LayoutDashboard size={19} /> Tableau de bord
            </NavLink>
          )}
          {canAccessMenu('documents') && (
            <NavLink
              to="/documents"
              className={({ isActive }) => `flex items-center gap-3 px-5 py-3 rounded-xl transition ${isActive ? 'bg-white/15 shadow-inner border border-white/25' : 'hover:bg-white/10'}`}
            >
              <FileText size={19} /> Mes Documents
            </NavLink>
          )}
          {canAccessMenu('templates-shared') && (
            <NavLink
              to="/templates-shared"
              className={({ isActive }) => `flex items-center gap-3 px-5 py-3 rounded-xl transition ${isActive ? 'bg-white/15 shadow-inner border border-white/25' : 'hover:bg-white/10'}`}
            >
              <Files size={19} /> Templates partagés
            </NavLink>
          )}
          {canAccessMenu('workflows') && (
            <NavLink
              to="/workflows"
              className={({ isActive }) => `flex items-center gap-3 px-5 py-3 rounded-xl transition ${isActive ? 'bg-white/15 shadow-inner border border-white/25' : 'hover:bg-white/10'}`}
            >
              <GitBranch size={19} /> Workflows
            </NavLink>
          )}
          {canAccessMenu('signatures') && (
            <NavLink
              to="/signatures"
              className={({ isActive }) => `flex items-center gap-3 px-5 py-3 rounded-xl transition ${isActive ? 'bg-white/15 shadow-inner border border-white/25' : 'hover:bg-white/10'}`}
            >
              <PenTool size={19} /> Signatures
            </NavLink>
          )}
          {canAccessMenu('reception') && (
            <NavLink
              to="/reception"
              className={({ isActive }) => `flex items-center gap-3 px-5 py-3 rounded-xl transition ${isActive ? 'bg-white/15 shadow-inner border border-white/25' : 'hover:bg-white/10'}`}
            >
              <Inbox size={19} /> Réception
            </NavLink>
          )}
          {canAccessMenu('act-requests') && (
            <NavLink
              to="/act-requests"
              className={({ isActive }) => `flex items-center gap-3 px-5 py-3 rounded-xl transition ${isActive ? 'bg-white/15 shadow-inner border border-white/25' : 'hover:bg-white/10'}`}
            >
              <ClipboardList size={19} /> Demande d'actes
            </NavLink>
          )}
          {canAccessMenu('administration') && (
            <NavLink
              to="/settings"
              className={({ isActive }) => `flex items-center gap-3 px-5 py-3 rounded-xl transition ${isActive ? 'bg-white/15 shadow-inner border border-white/25' : 'hover:bg-white/10'}`}
            >
              <Shield size={19} /> Administration
            </NavLink>
          )}
          {canAccessMenu('qrcode') && (
            <NavLink
              to="/qr-verification"
              className={({ isActive }) => `flex items-center gap-3 px-5 py-3 rounded-xl transition ${isActive ? 'bg-white/15 shadow-inner border border-white/25' : 'hover:bg-white/10'}`}
            >
              <QrCode size={19} /> Vérification QR
            </NavLink>
          )}
        </nav>

        {showPermissionsDebug && (
          <div className="mx-4 mt-4 mb-5 rounded-xl border border-white/30 bg-white/10 p-3 text-xs text-blue-50">
            <p className="font-semibold text-white">Debug Permissions</p>
            <p className="mt-1">source: {permissionsDebug?.source || 'unavailable'}</p>
            <p>isElevated: {String(Boolean(permissionsDebug?.isElevated))}</p>
            <p>userRole: {permissionsDebug?.debug?.userRole || '-'}</p>
            <p>adminRole: {permissionsDebug?.debug?.adminRole || '-'}</p>
            <p>adminProfile: {permissionsDebug?.debug?.administrationProfileName || '-'}</p>
            <p>roleProfile: {permissionsDebug?.debug?.roleProfileName || '-'}</p>
            <p className="mt-2 font-semibold text-white">permissions</p>
            <p className="break-words">{(permissionsDebug?.permissions || []).join(', ') || '(none)'}</p>
          </div>
        )}
      </aside>

      <main className="flex-1 overflow-auto">
        <div className="px-8 py-5 text-sm text-gray-700">développement web</div>
        <header className="px-8 pb-6 flex justify-between items-start">
          <div className="flex items-start gap-4">
            <div>
              <h2 className="text-3xl font-bold text-gray-800 leading-tight">{current.title}</h2>
              <p className="text-base text-gray-500 mt-1">{current.subtitle}</p>
            </div>

            {location.pathname === '/documents' && (
              <div className="relative" ref={dropdownRef}>
                <button
                  onClick={() => setDropdownOpen((v) => !v)}
                  className="px-6 py-3 rounded-xl bg-[#2453d6] hover:bg-[#1f47bb] text-white font-semibold shadow flex items-center gap-2"
                >
                  <Plus size={18} /> Nouveau
                </button>

                {dropdownOpen && (
                  <div className="absolute left-0 top-full mt-2 w-64 bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden py-2">
                    <p className="px-4 pt-1 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wide">
                      Téléverser depuis l'appareil
                    </p>
                    <button className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition" onClick={() => emitDocumentAction('upload-files')}>
                      <Upload size={16} className="text-gray-500" /> Téléverser des fichiers
                    </button>
                    <button className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition" onClick={() => emitDocumentAction('upload-folder')}>
                      <FolderUp size={16} className="text-gray-500" /> Téléverser des dossiers
                    </button>

                    <hr className="my-2 border-gray-100" />

                    <p className="px-4 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wide">
                      Créer un nouveau
                    </p>
                    <button className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition" onClick={() => emitDocumentAction('new-folder')}>
                      <FolderPlus size={16} className="text-gray-500" /> Nouveau dossier
                    </button>
                    <button className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition" onClick={() => emitDocumentAction('request-file')}>
                      <FileQuestion size={16} className="text-gray-500" /> Créer une demande de fichier
                    </button>
                    <button className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition" onClick={() => emitDocumentAction('new-doc')}>
                      <FilePlus2 size={16} className="text-blue-500" /> Nouveau document
                    </button>
                    <button className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition" onClick={() => emitDocumentAction('new-text')}>
                      <FileText size={16} className="text-gray-500" /> Nouveau fichier texte
                    </button>
                    <button className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition" onClick={() => emitDocumentAction('new-whiteboard')}>
                      <LayoutIcon size={16} className="text-purple-500" /> Nouveau tableau blanc
                    </button>
                    <button className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition" onClick={() => emitDocumentAction('new-sheet')}>
                      <FileSpreadsheet size={16} className="text-green-500" /> Nouvelle feuille de calcul
                    </button>
                    <button className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition" onClick={() => emitDocumentAction('new-presentation')}>
                      <Presentation size={16} className="text-orange-500" /> Nouvelle présentation
                    </button>
                    <button className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition" onClick={() => emitDocumentAction('new-pdf-form')}>
                      <FileType2 size={16} className="text-red-500" /> Nouveau formulaire PDF
                    </button>
                  </div>
                )}
              </div>
            )}
          </div>
          <div className="flex items-center gap-4">
            <div className="relative" ref={notificationsRef}>
              <button
                type="button"
                onClick={() => setNotificationsOpen((v) => !v)}
                className="h-12 w-12 bg-white rounded-xl shadow-sm border relative grid place-items-center text-gray-600"
              >
                <Bell size={18} />
                {notifications.filter((n) => !n.isRead).length > 0 && (
                  <span className="absolute -top-1 -right-1 h-5 min-w-[20px] px-1 rounded-full bg-red-500 text-[10px] text-white grid place-items-center">
                    {notifications.filter((n) => !n.isRead).length > 99 ? '99+' : notifications.filter((n) => !n.isRead).length}
                  </span>
                )}
              </button>

              {notificationsOpen && (
                <div className="absolute right-0 top-full mt-2 w-96 max-w-[90vw] bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden">
                  <div className="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <p className="text-sm font-semibold text-gray-800">Notifications</p>
                    <div className="flex items-center gap-3">
                      {notifications.some((n) => !n.isRead) && (
                        <button
                          type="button"
                          onClick={handleMarkAllAsRead}
                          className="text-xs text-green-600 hover:underline"
                        >
                          Tout marquer lu
                        </button>
                      )}
                      <button
                        type="button"
                        onClick={loadNotifications}
                        className="text-xs text-[#2453d6] hover:underline"
                      >
                        Actualiser
                      </button>
                    </div>
                  </div>

                  {notificationsLoading ? (
                    <div className="px-4 py-6 text-sm text-gray-500">Chargement...</div>
                  ) : notifications.length === 0 ? (
                    <div className="px-4 py-6 text-sm text-gray-500">Aucune action en attente pour le moment.</div>
                  ) : (
                    <div className="max-h-80 overflow-auto">
                      {notifications.map((notification) => (
                        <button
                          key={notification.id}
                          type="button"
                          onClick={() => {
                            handleMarkAsRead(notification.id)
                            navigate(notification.actionUrl || '/workflows')
                            setNotificationsOpen(false)
                          }}
                          className={`w-full text-left px-4 py-3 border-b border-gray-100 hover:bg-gray-50 transition ${!notification.isRead ? 'bg-blue-50/50' : ''}`}
                        >
                          <div className="flex items-start gap-2">
                            {!notification.isRead && (
                              <span className="mt-1.5 h-2 w-2 rounded-full bg-blue-500 flex-shrink-0" />
                            )}
                            <div className="flex-1 min-w-0">
                              <p className={`text-sm ${!notification.isRead ? 'font-semibold' : 'font-medium'} text-gray-800`}>{notification.title}</p>
                              <p className="text-xs text-gray-600 mt-1">{notification.description}</p>
                              <p className="text-[11px] text-gray-400 mt-1">{formatDate(notification.createdAt)}</p>
                            </div>
                          </div>
                        </button>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </div>

            <div className="relative" ref={userMenuRef}>
              <button
                type="button"
                onClick={() => setUserMenuOpen((v) => !v)}
                className="h-12 bg-white rounded-xl shadow-sm border px-3 flex items-center gap-2 text-gray-700 hover:bg-gray-50 transition"
              >
                <div className="h-8 w-8 rounded-full bg-[#2453d6]/10 text-[#2453d6] overflow-hidden flex items-center justify-center font-semibold text-xs">
                  {user?.avatar ? (
                    <img src={toAvatarUrl(user.avatar)} alt="Avatar" className="h-full w-full object-cover" />
                  ) : (
                    (user?.fullName?.slice(0, 2).toUpperCase() || 'AD')
                  )}
                </div>
                <ChevronDown size={16} className="text-gray-500" />
              </button>

              {userMenuOpen && (
                <div className="absolute right-0 top-full mt-2 w-64 bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden py-2">
                  <div className="px-4 py-3 border-b border-gray-100">
                    <p className="text-sm font-semibold text-gray-800 truncate">{user?.fullName || 'Utilisateur'}</p>
                    <p className="text-xs text-gray-500 truncate">{user?.email || 'user@example.com'}</p>
                  </div>

                  <button
                    className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                    onClick={() => {
                      navigate('/profile')
                      setUserMenuOpen(false)
                    }}
                  >
                    <UserCircle size={16} className="text-gray-500" /> Mon profil
                  </button>

                  <hr className="my-2 border-gray-100" />

                  <button
                    className="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition"
                    onClick={handleLogout}
                  >
                    <LogOut size={16} /> Déconnexion
                  </button>
                </div>
              )}
            </div>

          </div>
        </header>

        <div className="px-8 pb-8">
          <Outlet />
        </div>
      </main>

      {/* Chat en temps réel */}
      <ChatPanel />
    </div>
  )
}

export default Layout
