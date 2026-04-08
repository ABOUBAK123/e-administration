import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { fetchDocuments } from '../services/documents'
import { getPendingSignatures } from '../services/signatures'
import { fetchWorkflowDetails, fetchWorkflows } from '../services/workflows'
import { useAuthStore } from '../store/authStore'
import { DocumentItem } from '../types/document'
import { WorkflowExecution, WorkflowItem } from '../types/workflow'

type WorkflowProgressItem = {
  id: string
  name: string
  progress: number
  statusLabel: string
}

const normalizeStatus = (status?: string) => (status || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')

const formatRelativeDate = (value?: string) => {
  if (!value) return 'Date inconnue'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Date inconnue'

  const diffMs = Date.now() - date.getTime()
  const diffMinutes = Math.floor(diffMs / 60000)
  if (diffMinutes < 1) return 'à l\'instant'
  if (diffMinutes < 60) return `il y a ${diffMinutes} min`

  const diffHours = Math.floor(diffMinutes / 60)
  if (diffHours < 24) return `il y a ${diffHours} h`

  const diffDays = Math.floor(diffHours / 24)
  if (diffDays < 7) return `il y a ${diffDays} j`

  return date.toLocaleDateString('fr-FR')
}

const documentStatusView = (raw?: string) => {
  const status = normalizeStatus(raw)
  if (status.includes('pending') || status.includes('in_progress') || status.includes('validation')) {
    return { label: 'En validation', badgeClass: 'bg-amber-100 text-amber-700' }
  }
  if (status.includes('signed') || status.includes('complete') || status.includes('valide')) {
    return { label: 'Signé', badgeClass: 'bg-green-100 text-green-700' }
  }
  if (status.includes('archive')) {
    return { label: 'Archivé', badgeClass: 'bg-gray-200 text-gray-700' }
  }
  return { label: 'Brouillon', badgeClass: 'bg-blue-100 text-blue-700' }
}

const workflowProgressFromExecutions = (workflow: WorkflowItem, executions: WorkflowExecution[] | undefined): WorkflowProgressItem => {
  const totalSteps = Math.max(workflow.steps?.length || 0, 1)
  const list = executions || []

  if (list.length === 0) {
    return { id: workflow.id, name: workflow.name, progress: 0, statusLabel: 'Brouillon' }
  }

  const allCompleted = list.every((item) => normalizeStatus(item.status).includes('completed'))
  if (allCompleted) {
    return { id: workflow.id, name: workflow.name, progress: 100, statusLabel: 'Terminé' }
  }

  const active = list.find((item) => {
    const status = normalizeStatus(item.status)
    return status.includes('in_progress') || status.includes('pending') || status.includes('started')
  }) || list[0]

  const step = Math.max(1, Math.min(active.currentStep || 1, totalSteps))
  const progress = Math.max(5, Math.min(Math.round((step / totalSteps) * 100), 99))
  return { id: workflow.id, name: workflow.name, progress, statusLabel: 'Démarré' }
}

function Dashboard() {
  const user = useAuthStore((state) => state.user)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [documents, setDocuments] = useState<DocumentItem[]>([])
  const [pendingSignaturesCount, setPendingSignaturesCount] = useState(0)
  const [workflowProgress, setWorkflowProgress] = useState<WorkflowProgressItem[]>([])

  useEffect(() => {
    const loadDashboardData = async () => {
      setLoading(true)
      setError(null)

      try {
        const [docs, wfList, pendingSignatures] = await Promise.all([
          fetchDocuments(1, 100),
          fetchWorkflows(),
          user?.id ? getPendingSignatures(user.id) : Promise.resolve([]),
        ])

        setDocuments(Array.isArray(docs) ? docs : [])
        setPendingSignaturesCount(Array.isArray(pendingSignatures) ? pendingSignatures.length : 0)

        const details = await Promise.all(
          (Array.isArray(wfList) ? wfList : []).slice(0, 6).map(async (workflow) => {
            try {
              return await fetchWorkflowDetails(workflow.id)
            } catch {
              return null
            }
          }),
        )

        const progressItems = (Array.isArray(wfList) ? wfList : []).slice(0, 6).map((workflow) => {
          const detail = details.find((item) => item?.id === workflow.id)
          return workflowProgressFromExecutions(workflow, detail?.executions)
        })

        setWorkflowProgress(progressItems)
      } catch (err) {
        setError('Impossible de charger les données réelles du tableau de bord.')
      } finally {
        setLoading(false)
      }
    }

    loadDashboardData()
  }, [user?.id])

  const recentDocuments = useMemo(() => {
    return [...documents]
      .sort((a, b) => new Date(b.updatedAt || b.createdAt).getTime() - new Date(a.updatedAt || a.createdAt).getTime())
      .slice(0, 5)
  }, [documents])

  const documentsInValidation = useMemo(() => {
    return documents.filter((document) => {
      const status = normalizeStatus(document.status)
      return status.includes('pending') || status.includes('in_progress') || status.includes('validation')
    }).length
  }, [documents])

  const signedDocuments = useMemo(() => {
    return documents.filter((document) => {
      const status = normalizeStatus(document.status)
      return status.includes('signed') || status.includes('complete') || status.includes('valide')
    }).length
  }, [documents])

  return (
    <div className="space-y-6">
      {error && <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}

      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <div className="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
          <p className="text-sm text-gray-500 mb-2">Documents totaux</p>
          <p className="text-3xl font-bold text-gray-900">{loading ? '...' : documents.length}</p>
          <p className="text-sm text-gray-500 font-semibold mt-2">Données réelles</p>
        </div>
        <div className="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
          <p className="text-sm text-gray-500 mb-2">En validation</p>
          <p className="text-3xl font-bold text-gray-900">{loading ? '...' : documentsInValidation}</p>
          <p className="text-sm text-amber-500 font-semibold mt-2">Statuts en cours</p>
        </div>
        <div className="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
          <p className="text-sm text-gray-500 mb-2">Documents signés</p>
          <p className="text-3xl font-bold text-gray-900">{loading ? '...' : signedDocuments}</p>
          <p className="text-sm text-green-500 font-semibold mt-2">Statuts finalisés</p>
        </div>
        <div className="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
          <p className="text-sm text-gray-500 mb-2">Signatures en attente</p>
          <p className="text-3xl font-bold text-gray-900">{loading ? '...' : pendingSignaturesCount}</p>
          <p className="text-sm text-[#2453d6] font-semibold mt-2">Utilisateur connecté</p>
        </div>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <section className="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
          <div className="flex justify-between items-center mb-5">
            <h3 className="text-2xl font-bold text-gray-800">Documents récents</h3>
            <Link to="/documents" className="text-[#2453d6] font-medium">Voir tout</Link>
          </div>

          {loading ? (
            <p className="text-sm text-gray-500">Chargement des documents...</p>
          ) : recentDocuments.length === 0 ? (
            <p className="text-sm text-gray-500">Aucun document trouvé.</p>
          ) : (
            <div className="space-y-3">
              {recentDocuments.map((document) => {
                const status = documentStatusView(document.status)
                return (
                  <div key={document.id} className="flex items-center justify-between bg-gray-50 rounded-xl p-4">
                    <div>
                      <p className="font-semibold text-gray-800">{document.title}</p>
                      <p className="text-sm text-gray-500">Modifié {formatRelativeDate(document.updatedAt || document.createdAt)}</p>
                    </div>
                    <span className={`px-3 py-1 rounded-full text-sm ${status.badgeClass}`}>{status.label}</span>
                  </div>
                )
              })}
            </div>
          )}
        </section>

        <section className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
          <h3 className="text-2xl font-bold text-gray-800 mb-5">Progression des workflows</h3>

          {loading ? (
            <p className="text-sm text-gray-500">Chargement des workflows...</p>
          ) : workflowProgress.length === 0 ? (
            <p className="text-sm text-gray-500">Aucun workflow disponible.</p>
          ) : (
            <div className="space-y-4">
              {workflowProgress.map((item) => (
                <div key={item.id} className="rounded-xl bg-gray-50 p-4 border border-gray-100">
                  <div className="flex items-center justify-between gap-3">
                    <p className="font-semibold text-gray-800 truncate">{item.name}</p>
                    <span className="text-xs font-semibold text-gray-500">{item.statusLabel}</span>
                  </div>
                  <div className="h-2 bg-gray-200 rounded-full mt-3 overflow-hidden">
                    <div className="h-2 bg-[#2453d6] rounded-full" style={{ width: `${item.progress}%` }} />
                  </div>
                  <p className="mt-2 text-xs text-gray-500">{item.progress}%</p>
                </div>
              ))}
            </div>
          )}
        </section>
      </div>
    </div>
  )
}

export default Dashboard
