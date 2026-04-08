import { useEffect, useMemo, useState } from 'react'
import { CheckCircle2, Download, FileText, Filter, Search } from 'lucide-react'
import JSZip from 'jszip'
import api from '../services/api'
import { ActRequestDetails, fetchActRequestDetails, fetchActRequests, markActRequestAsTreated, startActRequestProcessing } from '../services/documents'
import { tokenStore } from '../services/tokenStore'
import { DocumentItem } from '../types/document'

function normalizeSubEntity(value?: string | null): string {
  return String(value || '').trim().toUpperCase()
}

function isActRequestDocument(doc: DocumentItem): boolean {
  const normalizedType = String(doc.type || '').trim().toLowerCase()
  if (normalizedType === 'request' || normalizedType === 'demande_acte') {
    return true
  }

  const title = String(doc.title || '').toLowerCase()
  const description = String(doc.description || '').toLowerCase()
  return title.includes('demande') || description.includes('demande')
}

type ParsedRequestMeta = {
  applicant: { fullName: string; email: string; phone?: string }
  applicantFieldValues: Record<string, string>
  receivedDocuments: Array<{ originalName: string; storedPath: string; requiredDocumentLabel?: string }>
}

function normalizeKey(value: string): string {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
}

function parseRequestMeta(description?: string | null): ParsedRequestMeta {
  const raw = String(description || '')
  const marker = 'ACT_REQUEST_META::'
  const markerIndex = raw.indexOf(marker)

  if (markerIndex >= 0) {
    const jsonPart = raw.slice(markerIndex + marker.length).trim()
    try {
      const parsed = JSON.parse(jsonPart) as Record<string, any>
      const applicantFieldValues = Object.entries((parsed?.applicantFieldValues || {}) as Record<string, unknown>)
        .reduce((acc, [key, value]) => {
          const label = String(key || '').trim()
          if (!label) return acc
          acc[label] = String(value || '').trim()
          return acc
        }, {} as Record<string, string>)

      const receivedDocuments = Array.isArray(parsed?.receivedDocuments)
        ? parsed.receivedDocuments
          .map((item: any) => ({
            originalName: String(item?.originalName || '').trim(),
            storedPath: String(item?.storedPath || '').trim(),
            requiredDocumentLabel: String(item?.requiredDocumentLabel || '').trim() || undefined,
          }))
          .filter((item: { originalName: string; storedPath: string }) => item.originalName || item.storedPath)
        : []

      return {
        applicant: {
          fullName: String(parsed?.applicant?.fullName || '').trim(),
          email: String(parsed?.applicant?.email || '').trim(),
          phone: String(parsed?.applicant?.phone || '').trim() || undefined,
        },
        applicantFieldValues,
        receivedDocuments,
      }
    } catch {
      // Ignore and fallback to empty values.
    }
  }

  return {
    applicant: { fullName: '', email: '', phone: undefined },
    applicantFieldValues: {},
    receivedDocuments: [],
  }
}

function pickFieldByLabel(values: Record<string, string>, acceptedLabels: string[]): string {
  const normalizedTargets = acceptedLabels.map(normalizeKey)
  for (const [label, value] of Object.entries(values)) {
    if (normalizedTargets.some((target) => normalizeKey(label).includes(target))) {
      return String(value || '').trim()
    }
  }
  return ''
}

function isFieldDisplayedInTable(label: string): boolean {
  const normalized = normalizeKey(label)
  if (!normalized) return false
  return [
    'nom',
    'prenom',
    'email',
    'mail',
    'telephone',
    'phone',
    'mobile',
    'matricule',
  ].some((token) => normalized.includes(token))
}

function mapRequestStatus(status: string): { label: string; className: string } {
  const normalized = String(status || '').trim().toLowerCase()
  if (normalized === 'draft') {
    return { label: 'En attente', className: 'bg-amber-50 text-amber-700 border border-amber-100' }
  }
  if (normalized === 'active' || normalized === 'pending_signature') {
    return { label: 'En cours', className: 'bg-blue-50 text-blue-700 border border-blue-100' }
  }
  if (normalized === 'signed') {
    return { label: 'Signe', className: 'bg-indigo-50 text-indigo-700 border border-indigo-100' }
  }
  if (normalized === 'archived') {
    return { label: 'Traité', className: 'bg-emerald-50 text-emerald-700 border border-emerald-100' }
  }
  return { label: status || '-', className: 'bg-gray-50 text-gray-700 border border-gray-100' }
}

function resolveUploadUrl(storedPath: string): string {
  const normalizedPath = String(storedPath || '').trim()
  if (!normalizedPath) return ''
  if (/^https?:\/\//i.test(normalizedPath)) return normalizedPath
  const origin = new URL(String(api.defaults.baseURL || window.location.origin), window.location.origin).origin
  return new URL(normalizedPath.startsWith('/') ? normalizedPath : `/${normalizedPath}`, origin).toString()
}

function safeFileName(value: string, fallback = 'piece_jointe'): string {
  const trimmed = String(value || '').trim()
  if (!trimmed) return fallback
  return trimmed.replace(/[\\/:*?"<>|]+/g, '_')
}

function ActRequests() {
  const [documents, setDocuments] = useState<DocumentItem[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [search, setSearch] = useState('')
  const [subEntityFilter, setSubEntityFilter] = useState('all')
  const [selectedRequestId, setSelectedRequestId] = useState<string | null>(null)
  const [details, setDetails] = useState<ActRequestDetails | null>(null)
  const [detailsLoading, setDetailsLoading] = useState(false)
  const [detailsError, setDetailsError] = useState<string | null>(null)
  const [zipLoadingById, setZipLoadingById] = useState<Record<string, boolean>>({})
  const [treatLoadingById, setTreatLoadingById] = useState<Record<string, boolean>>({})
  const [processingStartedById, setProcessingStartedById] = useState<Record<string, boolean>>({})
  const [viewMode, setViewMode] = useState<'in-progress' | 'treated'>('in-progress')

  const loadData = async () => {
    setLoading(true)
    setError(null)

    try {
      const data = await fetchActRequests(1, 200)
      setDocuments(Array.isArray(data) ? data : [])
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Impossible de charger les demandes d\'actes.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void loadData()
  }, [])

  // Business filtering is enforced by API; keep a light fallback filter for compatibility.
  const actRequests = useMemo(() => documents.filter(isActRequestDocument), [documents])

  const subEntities = useMemo(() => {
    const unique = Array.from(
      new Set(
        actRequests
          .map((doc) => normalizeSubEntity(doc.subEntityCode))
          .filter((code) => code.length > 0),
      ),
    )
    return unique.sort((a, b) => a.localeCompare(b))
  }, [actRequests])

  const filtered = useMemo(() => {
    const term = search.trim().toLowerCase()

    return actRequests.filter((doc) => {
      const subEntity = normalizeSubEntity(doc.subEntityCode)
      const keepByEntity = subEntityFilter === 'all' || subEntity === subEntityFilter
      if (!keepByEntity) return false

      if (!term) return true

      return [
        doc.title,
        doc.description,
        doc.status,
        doc.type,
        doc.documentNumber,
        subEntity,
      ]
        .map((value) => String(value || '').toLowerCase())
        .join(' ')
        .includes(term)
    })
  }, [actRequests, search, subEntityFilter])

  const tableRows = useMemo(() => {
    return filtered.map((doc) => {
      const parsed = parseRequestMeta(doc.description)
      const matricule = pickFieldByLabel(parsed.applicantFieldValues, ['matricule'])
      const phoneFromFields = pickFieldByLabel(parsed.applicantFieldValues, ['telephone', 'tel', 'phone', 'mobile'])
      const statusView = mapRequestStatus(doc.status)
      return {
        id: doc.id,
        doc,
        actName: doc.title || '-',
        applicantName: parsed.applicant.fullName || '-',
        email: parsed.applicant.email || '-',
        matricule: matricule || '-',
        phone: parsed.applicant.phone || phoneFromFields || '-',
        attachments: parsed.receivedDocuments,
        createdAtLabel: new Date(doc.createdAt).toLocaleString('fr-FR'),
        statusLabel: statusView.label,
        statusClassName: statusView.className,
      }
    })
  }, [filtered])

  const stats = useMemo(() => {
    const done = filtered.filter((doc) => String(doc.status || '').trim().toLowerCase() === 'archived').length
    const waiting = Math.max(0, filtered.length - done)
    return {
      total: filtered.length,
      waiting,
      done,
    }
  }, [filtered])

  const openDetails = async (id: string) => {
    setSelectedRequestId(id)
    setDetails(null)
    setDetailsError(null)
    setDetailsLoading(true)
    try {
      const data = await fetchActRequestDetails(id)
      setDetails(data)
    } catch (err: any) {
      setDetailsError(err?.response?.data?.message || 'Impossible de charger le détail de la demande.')
    } finally {
      setDetailsLoading(false)
    }
  }

  const closeDetailsModal = () => {
    setSelectedRequestId(null)
    setDetails(null)
    setDetailsError(null)
  }

  const downloadAttachmentsZip = async (row: (typeof tableRows)[number]) => {
    const files = row.attachments.filter((item) => String(item.storedPath || '').trim())
    if (files.length === 0) {
      setError('Aucun fichier joint à zipper pour cette demande.')
      return
    }

    setZipLoadingById((prev) => ({ ...prev, [row.id]: true }))
    try {
      const zip = new JSZip()
      const token = tokenStore.getAccessToken()

      for (const [index, file] of files.entries()) {
        const url = resolveUploadUrl(file.storedPath)
        if (!url) continue

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
      link.download = `${safeFileName(row.actName, 'demande_acte')}-${row.id.slice(0, 8)}.zip`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(objectUrl)

      const processingResult = await startActRequestProcessing(row.id)
      setDocuments((prev) => prev.map((item) => (item.id === row.id ? { ...item, status: processingResult.status as any } : item)))
      setProcessingStartedById((prev) => ({ ...prev, [row.id]: true }))
    } catch (err: any) {
      setError(err?.message || 'Impossible de générer le ZIP des pièces jointes.')
    } finally {
      setZipLoadingById((prev) => ({ ...prev, [row.id]: false }))
    }
  }

  const markAsTreated = async (rowId: string) => {
    setError(null)
    setTreatLoadingById((prev) => ({ ...prev, [rowId]: true }))
    try {
      const result = await markActRequestAsTreated(rowId)
      setDocuments((prev) => prev.map((item) => (item.id === rowId ? { ...item, status: result.status as any } : item)))
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Impossible de marquer la demande comme traitee.')
    } finally {
      setTreatLoadingById((prev) => ({ ...prev, [rowId]: false }))
    }
  }

  const rowsByMode = useMemo(() => {
    if (viewMode === 'treated') {
      return tableRows.filter((row) => String(row.doc.status || '').trim().toLowerCase() === 'archived')
    }
    return tableRows.filter((row) => String(row.doc.status || '').trim().toLowerCase() !== 'archived')
  }, [tableRows, viewMode])

  return (
    <div className="space-y-5">
      <div className="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 rounded-xl bg-blue-50 flex items-center justify-center">
              <FileText size={18} className="text-[#2453d6]" />
            </div>
            <div>
              <h2 className="text-lg font-bold text-gray-800">Demandes d'actes</h2>
              <p className="text-xs text-gray-500">
                Les demandes sont recues par administration puis orientees vers l'entite sous tutelle (code entite).
              </p>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2 text-xs">
            <span className="inline-flex px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-100">
              Total: {stats.total}
            </span>
            <button
              type="button"
              onClick={() => setViewMode('in-progress')}
              className={`inline-flex px-2.5 py-1 rounded-full border ${viewMode === 'in-progress' ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-amber-50 text-amber-700 border-amber-100 hover:bg-amber-100'}`}
            >
              A traiter: {stats.waiting}
            </button>
            <button
              type="button"
              onClick={() => setViewMode('treated')}
              className={`inline-flex px-2.5 py-1 rounded-full border ${viewMode === 'treated' ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-emerald-50 text-emerald-700 border-emerald-100 hover:bg-emerald-100'}`}
            >
              Traitees: {stats.done}
            </button>
          </div>
        </div>
      </div>

      <div className="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div className="relative">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Rechercher une demande d'acte..."
              className="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
            />
          </div>

          <div className="relative">
            <Filter size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <select
              value={subEntityFilter}
              onChange={(e) => setSubEntityFilter(e.target.value)}
              className="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30 appearance-none bg-white"
            >
              <option value="all">Toutes les entites sous tutelle</option>
              {subEntities.map((code) => (
                <option key={code} value={code}>{code}</option>
              ))}
            </select>
          </div>
        </div>
      </div>

      <div className="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        {error && <div className="p-4 text-sm text-red-700 bg-red-50 border-b border-red-100">{error}</div>}

        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-gray-600 text-xs uppercase">
            <tr>
              <th className="px-4 py-3 text-left">Nom de l'acte</th>
              <th className="px-4 py-3 text-left">Nom du demandeur</th>
              <th className="px-4 py-3 text-left">Email</th>
              <th className="px-4 py-3 text-left">Matricule</th>
              <th className="px-4 py-3 text-left">Telephone</th>
              <th className="px-4 py-3 text-left">Fichier joint</th>
              <th className="px-4 py-3 text-left">Date</th>
              <th className="px-4 py-3 text-left">Statut</th>
              <th className="px-4 py-3 text-left">Action</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr>
                <td className="px-4 py-6 text-gray-500" colSpan={9}>Chargement...</td>
              </tr>
            ) : rowsByMode.length === 0 ? (
              <tr>
                <td className="px-4 py-6 text-gray-500" colSpan={9}>
                  {viewMode === 'treated' ? 'Aucune demande traitee.' : 'Aucune demande d\'acte en cours.'}
                </td>
              </tr>
            ) : (
              rowsByMode.map((row) => {
                const isProcessing = String(row.doc.status || '').trim().toLowerCase() === 'active' || Boolean(processingStartedById[row.id])
                const isSigned = String(row.doc.status || '').trim().toLowerCase() === 'signed'
                return (
                  <tr key={row.id} className="border-t border-gray-100 hover:bg-gray-50">
                    <td className="px-4 py-3">
                      <p className="font-semibold text-gray-800">{row.actName}</p>
                      <button
                        type="button"
                        onClick={() => openDetails(row.id)}
                        className="text-[11px] text-indigo-700 hover:text-indigo-900 mt-1"
                      >
                        Voir détail
                      </button>
                    </td>
                    <td className="px-4 py-3 text-gray-700">{row.applicantName}</td>
                    <td className="px-4 py-3 text-gray-700">{row.email}</td>
                    <td className="px-4 py-3 text-gray-700">{row.matricule}</td>
                    <td className="px-4 py-3 text-gray-700">{row.phone}</td>
                    <td className="px-4 py-3">
                      <button
                        type="button"
                        onClick={() => downloadAttachmentsZip(row)}
                        disabled={zipLoadingById[row.id] || row.attachments.length === 0}
                        className={`inline-flex items-center gap-1 px-2 py-1 rounded text-xs border disabled:opacity-60 ${
                          isProcessing
                            ? 'border-emerald-200 text-emerald-700 bg-emerald-50 hover:bg-emerald-100'
                            : 'border-orange-200 text-orange-700 hover:bg-orange-50'
                        }`}
                      >
                        <Download size={12} />
                        {zipLoadingById[row.id] ? 'Zippage...' : (isProcessing ? 'ZIP téléchargé' : `ZIP (${row.attachments.length})`)}
                      </button>
                    </td>
                    <td className="px-4 py-3 text-gray-600">{row.createdAtLabel}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex px-2 py-1 rounded-full text-xs ${row.statusClassName}`}>
                        {row.statusLabel}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      {viewMode === 'in-progress' && isSigned ? (
                        <button
                          type="button"
                          onClick={() => markAsTreated(row.id)}
                          disabled={Boolean(treatLoadingById[row.id])}
                          className="inline-flex items-center gap-1 px-2 py-1 rounded text-xs border border-emerald-200 text-emerald-700 hover:bg-emerald-50 disabled:opacity-60"
                        >
                          <CheckCircle2 size={12} />
                          {treatLoadingById[row.id] ? 'Traitement...' : 'Traiter'}
                        </button>
                      ) : (
                        <span className="text-xs text-gray-400">-</span>
                      )}
                    </td>
                  </tr>
                )
              })
            )}
          </tbody>
        </table>
      </div>

      {selectedRequestId && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <button
            type="button"
            aria-label="Fermer la modale"
            onClick={closeDetailsModal}
            className="absolute inset-0 bg-black/45"
          />

          <div className="relative w-full max-w-4xl max-h-[88vh] overflow-y-auto bg-white border border-gray-200 rounded-2xl shadow-2xl p-5 space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-base font-bold text-gray-800">Détail de la demande</h3>
              <button
                type="button"
                onClick={closeDetailsModal}
                className="text-xs text-gray-500 hover:text-gray-700"
              >
                Fermer
              </button>
            </div>

            {detailsLoading && <p className="text-sm text-gray-500">Chargement du détail...</p>}
            {detailsError && <p className="text-sm text-red-700">{detailsError}</p>}

            {details && !detailsLoading && !detailsError && (
              <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                  <div className="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <p className="text-xs text-gray-500">Acte demandé</p>
                    <p className="font-semibold text-gray-800 mt-1">{details.title || '-'}</p>
                    <p className="text-xs text-gray-600 mt-1">Entité: {details.subEntityCode || '-'}</p>
                  </div>
                  <div className="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <p className="text-xs text-gray-500">Complétude du dossier</p>
                    <p className="font-semibold text-gray-800 mt-1">
                      {details.completeness.requiredReceived}/{details.completeness.requiredTotal} pièce(s) exigée(s)
                    </p>
                    <p className="text-xs text-gray-600 mt-1">{details.completeness.receivedTotal} fichier(s) reçu(s)</p>
                  </div>
                </div>

                <div className="rounded-lg border border-gray-200 bg-gray-50 p-3">
                  <p className="text-xs font-semibold text-gray-700">Champs saisis non affichés dans le tableau</p>
                  {Object.entries(details.applicantFieldValues || {})
                    .filter(([label, value]) => !isFieldDisplayedInTable(label) && String(value || '').trim())
                    .length === 0 ? (
                      <p className="text-sm text-gray-500 mt-2">Aucun champ complémentaire renseigné.</p>
                    ) : (
                      <div className="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2">
                        {Object.entries(details.applicantFieldValues || {})
                          .filter(([label, value]) => !isFieldDisplayedInTable(label) && String(value || '').trim())
                          .map(([label, value]) => (
                            <div key={label} className="rounded-md border border-gray-200 bg-white px-3 py-2">
                              <p className="text-[11px] text-gray-500">{label}</p>
                              <p className="text-sm text-gray-800 mt-0.5 whitespace-pre-wrap">{value || '-'}</p>
                            </div>
                          ))}
                      </div>
                    )}
                </div>

                {details.note && (
                  <div className="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <p className="text-xs text-gray-500">Observation usager</p>
                    <p className="text-sm text-gray-700 mt-1 whitespace-pre-wrap">{details.note}</p>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}

export default ActRequests
