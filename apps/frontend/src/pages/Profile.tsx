import { ChangeEvent, FormEvent, useEffect, useMemo, useState } from 'react'
import { Camera, Eye, EyeOff, Mail, Save, ShieldCheck, UserRound } from 'lucide-react'
import { useAuthStore } from '../store/authStore'

const API_ROOT = (import.meta.env.VITE_API_URL || 'http://localhost:3000/api/v1').replace(/\/api(?:\/v\d+)?\/?$/, '')

const toAvatarUrl = (avatar?: string) => {
  if (!avatar) return ''
  if (avatar.startsWith('http://') || avatar.startsWith('https://')) return avatar
  return `${API_ROOT}${avatar.startsWith('/') ? '' : '/'}${avatar}`
}

const splitFullName = (fullName: string) => {
  const parts = fullName.trim().split(/\s+/).filter(Boolean)
  if (parts.length <= 1) {
    return {
      firstName: parts[0] || '',
      lastName: '',
    }
  }

  return {
    firstName: parts.slice(0, -1).join(' '),
    lastName: parts.slice(-1).join(' '),
  }
}

const extractApiError = (err: any, fallback: string) => {
  const message = err?.response?.data?.message
  if (Array.isArray(message)) {
    return message.join(' ')
  }
  if (typeof message === 'string' && message.trim()) {
    return message
  }
  return fallback
}

function Profile() {
  const user = useAuthStore((state) => state.user)
  const syncCurrentUser = useAuthStore((state) => state.syncCurrentUser)
  const saveCurrentUserProfile = useAuthStore((state) => state.saveCurrentUserProfile)
  const uploadAvatar = useAuthStore((state) => state.uploadAvatar)

  const initialName = useMemo(() => splitFullName(user?.fullName || ''), [user?.fullName])

  const [email, setEmail] = useState(user?.email || '')
  const [firstName, setFirstName] = useState(initialName.firstName)
  const [lastName, setLastName] = useState(initialName.lastName)
  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')
  const [showCurrentPassword, setShowCurrentPassword] = useState(false)
  const [showNewPassword, setShowNewPassword] = useState(false)
  const [showConfirmPassword, setShowConfirmPassword] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [isSaving, setIsSaving] = useState(false)
  const [isUploadingAvatar, setIsUploadingAvatar] = useState(false)
  const [avatarUploadPopup, setAvatarUploadPopup] = useState<{ type: 'success' | 'error'; message: string } | null>(null)

  useEffect(() => {
    syncCurrentUser().catch(() => undefined)
  }, [syncCurrentUser])

  useEffect(() => {
    const name = splitFullName(user?.fullName || '')
    setEmail(user?.email || '')
    setFirstName(name.firstName)
    setLastName(name.lastName)
  }, [user?.email, user?.fullName])

  useEffect(() => {
    if (!avatarUploadPopup) return
    const timeout = window.setTimeout(() => setAvatarUploadPopup(null), 2500)
    return () => window.clearTimeout(timeout)
  }, [avatarUploadPopup])

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault()
    setError(null)
    setSuccess(null)

    const normalizedEmail = email.trim().toLowerCase()
    const normalizedFirstName = firstName.trim()
    const normalizedLastName = lastName.trim()
    const fullName = `${normalizedFirstName} ${normalizedLastName}`.trim()

    if (!normalizedEmail) {
      setError("L'adresse e-mail est obligatoire.")
      return
    }

    if (!normalizedFirstName && !normalizedLastName) {
      setError('Le nom complet est obligatoire.')
      return
    }

    if (newPassword) {
      if (!currentPassword) {
        setError('Le mot de passe actuel est obligatoire pour définir un nouveau mot de passe.')
        return
      }

      if (newPassword.length < 8) {
        setError('Le nouveau mot de passe doit contenir au moins 8 caractères.')
        return
      }

      if (newPassword !== confirmPassword) {
        setError('La confirmation du nouveau mot de passe ne correspond pas.')
        return
      }
    }

    setIsSaving(true)
    try {
      await saveCurrentUserProfile({
        email: normalizedEmail,
        fullName,
        currentPassword: currentPassword || undefined,
        password: newPassword || undefined,
      })
      await syncCurrentUser()

      setCurrentPassword('')
      setNewPassword('')
      setConfirmPassword('')
      setSuccess('Vos informations personnelles ont été mises à jour.')
    } catch (err: any) {
      setError(extractApiError(err, 'La mise à jour du profil a échoué.'))
    } finally {
      setIsSaving(false)
    }
  }

  const handleAvatarChange = async (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    if (!file) return

    if (!file.type.startsWith('image/')) {
      setError('Veuillez sélectionner une image valide (PNG, JPG, JPEG, WEBP).')
      return
    }

    if (file.size > 5 * 1024 * 1024) {
      setError('La taille maximale autorisée est de 5 Mo.')
      return
    }

    setError(null)
    setSuccess(null)
    setAvatarUploadPopup(null)
    setIsUploadingAvatar(true)
    try {
      await uploadAvatar(file)
      setSuccess('Photo de profil mise à jour avec succès. Rechargez la page si la photo ne se met pas à jour immédiatement.')
      setAvatarUploadPopup({ type: 'success', message: 'Upload réussi: photo de profil mise à jour.' })
    } catch (err: any) {
      const message = extractApiError(err, "Impossible d'uploader la photo de profil.")
      setError(message)
      setAvatarUploadPopup({ type: 'error', message })
    } finally {
      setIsUploadingAvatar(false)
      event.target.value = ''
    }
  }

  return (
    <div className="space-y-6">
      {avatarUploadPopup && (
        <div className="fixed right-5 top-5 z-50">
          <div
            className={`rounded-xl border px-4 py-3 text-sm font-medium shadow-lg ${
              avatarUploadPopup.type === 'success'
                ? 'border-green-200 bg-green-50 text-green-700'
                : 'border-red-200 bg-red-50 text-red-700'
            }`}
          >
            {avatarUploadPopup.message}
          </div>
        </div>
      )}

      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-800">Mon profil</h1>
            <p className="text-sm text-gray-500 mt-1">
              Modifiez votre e-mail, votre nom et votre mot de passe depuis votre espace utilisateur.
            </p>
          </div>
          <div className="inline-flex items-center gap-2 rounded-xl bg-blue-50 px-4 py-2 text-sm font-medium text-[#2453d6]">
            <ShieldCheck size={18} />
            Compte sécurisé
          </div>
        </div>

        <div className="mt-5 flex items-center gap-4">
          <div className="h-20 w-20 rounded-full border border-gray-200 bg-slate-100 overflow-hidden flex items-center justify-center">
            {user?.avatar ? (
              <img
                src={toAvatarUrl(user.avatar)}
                alt="Photo de profil"
                className="h-full w-full object-cover"
              />
            ) : (
              <span className="text-xl font-bold text-slate-600">
                {(user?.fullName || 'U').slice(0, 2).toUpperCase()}
              </span>
            )}
          </div>
          <div>
            <label className="inline-flex items-center gap-2 cursor-pointer rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
              <Camera size={16} />
              {isUploadingAvatar ? 'Upload en cours...' : 'Changer la photo'}
              <input
                type="file"
                accept="image/png,image/jpeg,image/jpg,image/webp"
                className="hidden"
                onChange={handleAvatarChange}
                disabled={isUploadingAvatar}
              />
            </label>
            <p className="mt-1 text-xs text-gray-500">Formats acceptés : PNG, JPG, JPEG, WEBP. Taille max : 5 Mo.</p>
          </div>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div className="xl:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
          <div>
            <h2 className="text-base font-bold text-gray-800">Informations personnelles</h2>
            <p className="text-sm text-gray-500 mt-1">Ces informations seront utilisées dans votre espace et sur les opérations liées à votre compte.</p>
          </div>

          {error && <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
          {success && <div className="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{success}</div>}

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1.5">Prénom</label>
              <div className="relative">
                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><UserRound size={16} /></span>
                <input
                  type="text"
                  value={firstName}
                  onChange={(e) => setFirstName(e.target.value)}
                  autoComplete="given-name"
                  className="w-full border border-gray-300 rounded-lg pl-10 pr-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                  placeholder="Jean"
                  required
                />
              </div>
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1.5">Nom</label>
              <input
                type="text"
                value={lastName}
                onChange={(e) => setLastName(e.target.value)}
                autoComplete="family-name"
                className="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                placeholder="Dupont"
                required
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1.5">Adresse e-mail</label>
            <div className="relative">
              <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><Mail size={16} /></span>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                autoComplete="email"
                className="w-full border border-gray-300 rounded-lg pl-10 pr-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                placeholder="jean.dupont@example.com"
                required
              />
            </div>
          </div>
        </div>

        <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
          <div>
            <h2 className="text-base font-bold text-gray-800">Mot de passe</h2>
            <p className="text-sm text-gray-500 mt-1">Laissez les champs vides si vous ne souhaitez pas le modifier.</p>
          </div>

          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1.5">Mot de passe actuel</label>
            <div className="relative">
              <input
                type={showCurrentPassword ? 'text' : 'password'}
                value={currentPassword}
                onChange={(e) => setCurrentPassword(e.target.value)}
                autoComplete="current-password"
                className="w-full border border-gray-300 rounded-lg px-3 py-2.5 pr-10 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                placeholder="Saisissez votre mot de passe actuel"
              />
              <button type="button" onClick={() => setShowCurrentPassword((value) => !value)} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                {showCurrentPassword ? <EyeOff size={16} /> : <Eye size={16} />}
              </button>
            </div>
          </div>

          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1.5">Nouveau mot de passe</label>
            <div className="relative">
              <input
                type={showNewPassword ? 'text' : 'password'}
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                autoComplete="new-password"
                className="w-full border border-gray-300 rounded-lg px-3 py-2.5 pr-10 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                placeholder="Minimum 8 caractères"
              />
              <button type="button" onClick={() => setShowNewPassword((value) => !value)} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                {showNewPassword ? <EyeOff size={16} /> : <Eye size={16} />}
              </button>
            </div>
          </div>

          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1.5">Confirmer le nouveau mot de passe</label>
            <div className="relative">
              <input
                type={showConfirmPassword ? 'text' : 'password'}
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                autoComplete="new-password"
                className="w-full border border-gray-300 rounded-lg px-3 py-2.5 pr-10 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                placeholder="Répétez le nouveau mot de passe"
              />
              <button type="button" onClick={() => setShowConfirmPassword((value) => !value)} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                {showConfirmPassword ? <EyeOff size={16} /> : <Eye size={16} />}
              </button>
            </div>
          </div>

          <button
            type="submit"
            disabled={isSaving}
            className="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#2453d6] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#1f47bb] disabled:cursor-not-allowed disabled:opacity-60"
          >
            <Save size={16} />
            {isSaving ? 'Enregistrement...' : 'Enregistrer mes modifications'}
          </button>
        </div>
      </form>
    </div>
  )
}

export default Profile
