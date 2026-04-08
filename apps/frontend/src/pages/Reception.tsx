import { useEffect, useMemo, useState } from 'react'
import { Inbox, Search, Download, UserPlus } from 'lucide-react'
import JSZip from 'jszip'
import { fetchReceptionDocuments, markReceptionZipDownloaded, shareDocument } from '../services/documents'
import { fetchRecipientAdministrations } from '../services/administration'
import { fetchAppUsers } from '../services/users'
import { tokenStore } from '../services/tokenStore'
import { DocumentItem } from '../types/document'
import { RecipientAdministration } from '../types/administration'

type ShareMeta = {
  sharedAt?: string
  applicantFullName?: string
  applicantMatricule?: string
  applicantEmail?: string
  zipDownloadedAt?: string
}

function parseShareMeta(description?: string): ShareMeta {
  const marker = 'RECIPIENT_SHARE_META::'
  const raw = String(description || '')
  const line = raw.split('\n').find((item) => item.startsWith(marker))
  if (!line) return {}
  const jsonPart = line.slice(marker.length).trim()
  if (!jsonPart) return {}
  try {
    return JSON.parse(jsonPart) as ShareMeta
  } catch {
    return {}
  }
}

function parseActRequestReceivedFiles(description?: string): Array<{ originalName: string; storedPath: string }> {
  const marker = 'ACT_REQUEST_META::'
  const raw = String(description || '')
  const markerIndex = raw.indexOf(marker)
  if (markerIndex < 0) return []
  try {
    const parsed = JSON.parse(raw.slice(markerIndex + marker.length).trim()) as Record<string, any>
    if (!Array.isArray(parsed?.receivedDocuments)) return []
    return parsed.receivedDocuments
      .map((item: any) => ({
        originalName: String(item?.originalName || '').trim(),
        storedPath: String(item?.storedPath || '').trim(),
      }))
      .filter((item: { originalName: string; storedPath: string }) => item.originalName || item.storedPath)
  } catch {
    return []
  }
}

function resolveUploadUrl(storedPath: string): string {
  const normalizedPath = String(storedPath || '').trim()
  if (!normalizedPath) return ''
  if (/^https?:\/\//i.test(normalizedPath)) return normalizedPath
  const origin = window.location.origin
  return new URL(normalizedPath.startsWith('/') ? normalizedPath : `/${normalizedPath}`, origin).toString()
}

function safeFileName(value: string, fallback = 'piece_jointe'): string {
  const trimmed = String(value || '').trim()
  if (!trimmed) return fallback
  return trimmed.replace(/[\\/:*?"<>|]+/g, '_')
}

function Reception() {
  const [documents, setDocuments] = useState<DocumentItem[]>([])
  const [recipients, setRecipients] = useState<RecipientAdministration[]>([])
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [downloadedById, setDownloadedById] = useState<Record<string, boolean>>({})
  const [zipLoadingById, setZipLoadingById] = useState<Record<string, boolean>>({})
  const [users, setUsers] = useState<Array<{ id: string; fullName: string; email: string }>>([])
  const [shareModalDocId, setShareModalDocId] = useState<string | null>(null)
  const [shareTargetUserId, setShareTargetUserId] = useState('')
  const [shareStatus, setShareStatus] = useState<string | null>(null)

  const loadData = async () => {
    setLoading(true)
    setError(null)
    try {
      const [docs, recipientAdministrations] = await Promise.all([
        fetchReceptionDocuments(1, 100, search),
        fetchRecipientAdministrations(),
      ])

      setDocuments(Array.isArray(docs) ? docs : [])
      setRecipients((recipientAdministrations || []).filter((item) => item.channel === 'api' || item.channel === 'application'))
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Impossible de charger les documents de réception.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void loadData()
  }, [])

  useEffect(() => {
    const loadUsers = async () => {
      try {
        const appUsers = await fetchAppUsers()
        setUsers((Array.isArray(appUsers) ? appUsers : []).map((item) => ({
          id: item.id,
          fullName: item.fullName,
          email: item.email,
        })))
      } catch {
        setUsers([])
      }
    }
    void loadUsers()
  }, [])

  const recipientNameById = useMemo(() => {
    const map = new Map<string, string>()
    recipients.forEach((item) => map.set(item.id, item.name))
    return map
  }, [recipients])

  const filteredDocuments = useMemo(() => {
    const term = search.trim().toLowerCase()
    if (!term) return documents
    return documents.filter((doc) => {
      const recipientName = recipientNameById.get(doc.recipientAdministrationId || '') || ''
      return (
        (doc.title || '').toLowerCase().includes(term)
        || (doc.description || '').toLowerCase().includes(term)
        || recipientName.toLowerCase().includes(term)
      )
    })
  }, [documents, recipientNameById, search])

  const rows = useMemo(() => {
    return filteredDocuments.map((doc) => {
      const meta = parseShareMeta(doc.description)
      const zipDownloadedAt = String(meta.zipDownloadedAt || '').trim() || null
      return {
        doc,
        sharedAt: meta.sharedAt ? new Date(meta.sharedAt).toLocaleString('fr-FR') : new Date(doc.updatedAt || doc.createdAt).toLocaleString('fr-FR'),
        matricule: String(meta.applicantMatricule || '-').trim() || '-',
        applicantName: String(meta.applicantFullName || '-').trim() || '-',
        email: String(meta.applicantEmail || '-').trim() || '-',
        zipDownloadedAt,
        firstZipDownloadedAtLabel: zipDownloadedAt ? new Date(zipDownloadedAt).toLocaleString('fr-FR') : '-',
      }
    })
  }, [filteredDocuments])

  const handleZipDownload = async (doc: DocumentItem) => {
    setError(null)
    setZipLoadingById((prev) => ({ ...prev, [doc.id]: true }))
    try {
      const filesFromMeta = parseActRequestReceivedFiles(doc.description)
      const files = filesFromMeta.length > 0
        ? filesFromMeta
        : [{ originalName: doc.title || 'document', storedPath: String(doc.filePath || '').trim() }]

      const validFiles = files.filter((item) => item.storedPath)
      if (validFiles.length === 0) {
        throw new Error('Aucun fichier disponible pour téléchargement.')
      }

      const token = tokenStore.getAccessToken()
      const zip = new JSZip()
      for (const [index, file] of validFiles.entries()) {
        const url = resolveUploadUrl(file.storedPath)
        const response = await fetch(url, {
          headers: token ? { Authorization: `Bearer ${token}` } : undefined,
        })
        if (!response.ok) {
          throw new Error(`Téléchargement impossible pour ${file.originalName || file.storedPath}`)
        }
        const blob = await response.blob()
        const fallback = `piece_jointe_${index + 1}`
        zip.file(safeFileName(file.originalName || file.storedPath, fallback), blob)
      }

      const zipBlob = await zip.generateAsync({ type: 'blob' })
      const objectUrl = URL.createObjectURL(zipBlob)
      const link = document.createElement('a')
      link.href = objectUrl
      link.download = `${safeFileName(doc.title || 'document', 'document')}-${doc.id.slice(0, 8)}.zip`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(objectUrl)

      await markReceptionZipDownloaded(doc.id)
      setDownloadedById((prev) => ({ ...prev, [doc.id]: true }))
    } catch (err: any) {
      setError(err?.message || 'Impossible de télécharger le ZIP.')
    } finally {
      setZipLoadingById((prev) => ({ ...prev, [doc.id]: false }))
    }
  }

  const submitInternalShare = async () => {
    if (!shareModalDocId || !shareTargetUserId) {
      setShareStatus('Veuillez sélectionner un utilisateur.')
      return
    }
    const doc = documents.find((item) => item.id === shareModalDocId)
    const user = users.find((item) => item.id === shareTargetUserId)
    if (!doc || !user) {
      setShareStatus('Document ou utilisateur introuvable.')
      return
    }

    try {
      await shareDocument(doc.id, {
        mode: 'internal',
        recipientName: user.fullName,
        recipientEmail: user.email,
        permission: 'lecture',
      })
      setShareStatus(`Document partagé avec ${user.fullName}.`)
      setShareTargetUserId('')
    } catch (err: any) {
      setShareStatus(err?.response?.data?.message || 'Partage interne impossible.')
    }
  }

  return (
    <div className="space-y-5">
      <div className="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 rounded-xl bg-blue-50 flex items-center justify-center">
              <Inbox size={18} className="text-[#2453d6]" />
            </div>
            <div>
              <h2 className="text-lg font-bold text-gray-800">Réception des administrations destinataires</h2>
              <p className="text-xs text-gray-500">Documents reçus pour les administrations réceptrices configurées en mode application.</p>
            </div>
          </div>

          <div className="relative w-full md:w-80">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  void loadData()
                }
              }}
              placeholder="Rechercher un document..."
              className="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
            />
          </div>
        </div>
      </div>

      <div className="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        {error && <div className="p-4 text-sm text-red-700 bg-red-50 border-b border-red-100">{error}</div>}

        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-gray-600 text-xs uppercase">
            <tr>
              <th className="px-4 py-3 text-left">Date</th>
              <th className="px-4 py-3 text-left">Matricule</th>
              <th className="px-4 py-3 text-left">Nom et prénoms</th>
              <th className="px-4 py-3 text-left">Email</th>
              <th className="px-4 py-3 text-left">Document zipper</th>
              <th className="px-4 py-3 text-left">Date 1er ZIP téléchargé</th>
              <th className="px-4 py-3 text-left">Partager</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr>
                <td className="px-4 py-6 text-gray-500" colSpan={7}>Chargement...</td>
              </tr>
            ) : filteredDocuments.length === 0 ? (
              <tr>
                <td className="px-4 py-6 text-gray-500" colSpan={7}>Aucun document reçu via application.</td>
              </tr>
            ) : (
              rows.map(({ doc, sharedAt, matricule, applicantName, email, zipDownloadedAt, firstZipDownloadedAtLabel }) => {
                const isDownloaded = Boolean(downloadedById[doc.id]) || Boolean(zipDownloadedAt)
                return (
                <tr key={doc.id} className="border-t border-gray-100 hover:bg-gray-50">
                  <td className="px-4 py-3 text-gray-600">{sharedAt}</td>
                  <td className="px-4 py-3 text-gray-700">{matricule}</td>
                  <td className="px-4 py-3 text-gray-700">{applicantName}</td>
                  <td className="px-4 py-3 text-gray-700">{email}</td>
                  <td className="px-4 py-3">
                    <button
                      type="button"
                      onClick={() => void handleZipDownload(doc)}
                      disabled={Boolean(zipLoadingById[doc.id])}
                      className={`inline-flex items-center gap-1 px-2 py-1 rounded text-xs border disabled:opacity-60 ${isDownloaded ? 'border-emerald-200 text-emerald-700 bg-emerald-50 hover:bg-emerald-100' : 'border-orange-200 text-orange-700 hover:bg-orange-50'}`}
                    >
                      <Download size={12} />
                      {zipLoadingById[doc.id] ? 'Téléchargement...' : (isDownloaded ? 'ZIP téléchargé' : 'Télécharger ZIP')}
                    </button>
                  </td>
                  <td className="px-4 py-3 text-gray-600">{firstZipDownloadedAtLabel}</td>
                  <td className="px-4 py-3">
                    <button
                      type="button"
                      onClick={() => { setShareModalDocId(doc.id); setShareStatus(null) }}
                      className="h-8 w-8 rounded-lg text-gray-500 hover:text-blue-600 hover:bg-blue-50 grid place-items-center"
                      title="Partager à un utilisateur de la même administration"
                    >
                      <UserPlus size={16} />
                    </button>
                  </td>
                </tr>
              )})
            )}
          </tbody>
        </table>
      </div>

      {shareModalDocId && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
          <div className="bg-white w-full max-w-md rounded-2xl shadow-2xl border border-gray-100">
            <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
              <h3 className="text-sm font-semibold text-gray-800">Partager en interne</h3>
              <button
                type="button"
                onClick={() => { setShareModalDocId(null); setShareTargetUserId(''); setShareStatus(null) }}
                className="h-8 w-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100"
              >
                ×
              </button>
            </div>
            <div className="p-5 space-y-3">
              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">Utilisateur de la même administration</label>
                <select
                  value={shareTargetUserId}
                  onChange={(e) => setShareTargetUserId(e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                >
                  <option value="">Sélectionner un utilisateur</option>
                  {users.map((item) => (
                    <option key={item.id} value={item.id}>{item.fullName} ({item.email})</option>
                  ))}
                </select>
              </div>
              {shareStatus && <p className="text-xs text-gray-600">{shareStatus}</p>}
            </div>
            <div className="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => { setShareModalDocId(null); setShareTargetUserId(''); setShareStatus(null) }}
                className="px-3 py-2 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"
              >
                Annuler
              </button>
              <button
                type="button"
                onClick={() => void submitInternalShare()}
                className="px-3 py-2 text-xs font-semibold rounded-lg bg-[#2453d6] text-white hover:bg-[#1f47bb]"
              >
                Partager
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export default Reception
