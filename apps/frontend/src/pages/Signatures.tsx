import React, { useEffect, useRef, useState } from 'react'
import { PenLine, Send, ShieldCheck, CheckCircle2, XCircle, Upload, FileUp, Workflow, MapPin, PlusCircle, Trash2, PlayCircle, PenTool, FileText, Eye } from 'lucide-react'
import { fetchDocumentById, fetchDocuments, uploadDocumentFile } from '../services/documents'
import { fetchAppSetting, fetchSignatureProviderConfig } from '../services/administration'
import {
  fetchWorkflows,
  fetchWorkflowDetails,
  fetchWorkflowTemplates,
  createWorkflow,
  executeWorkflow,
  performWorkflowStepAction,
} from '../services/workflows'
import { fetchSignataires, AppUserRecord } from '../services/users'
import {
  fetchSignatures,
  requestSignature,
  signDocument,
  getPendingSignatures,
  respondToSignatureRequest,
  verifySignature,
} from '../services/signatures'
import { useAuthStore } from '../store/authStore'
import { useSignatureFilesStore, SignatureZone } from '../store/signatureFilesStore'
import { DocumentItem } from '../types/document'
import { SignatureItem, SignatureRequest } from '../types/signature'
import { WorkflowExecution, WorkflowItem, WorkflowTemplateItem } from '../types/workflow'

type SignatureWorkflowRow = {
  executionId: string
  executionIds: string[]
  actionableExecutionIds: string[]
  workflowId: string
  workflowName: string
  creatorLabel: string
  documentId: string
  documentTitle: string
  documentCount: number
  status: string
  statusLabel: string
  progressPercent: number
  nextActorLabel: string
  actionType: 'signature' | 'validation'
  isMyTurn: boolean
}

type PendingSelfDoc = {
  doc: DocumentItem
  uploadedAt: string
}

type SignedSelfDoc = {
  doc: DocumentItem
  signedAt: string
}

const isSignatureStepType = (
  description?: string | null,
  requiresSignature?: boolean,
): boolean => {
  const normalized = String(description || '').toLowerCase()
  if (normalized.includes('signature')) return true
  if (normalized.includes('validation')) return false
  return Boolean(requiresSignature)
}

function Signatures() {
  const user = useAuthStore((state) => state.user)
  const [documents, setDocuments] = useState<DocumentItem[]>([])
  const [selectedDocumentId, setSelectedDocumentId] = useState('')
  const [signatures, setSignatures] = useState<SignatureItem[]>([])
  const [pendingRequests, setPendingRequests] = useState<SignatureRequest[]>([])
  const [requestTo, setRequestTo] = useState('')
  const [requestMessage, setRequestMessage] = useState('')
  const [signatureHash, setSignatureHash] = useState('')
  const [workflowsRows, setWorkflowsRows] = useState<SignatureWorkflowRow[]>([])
  const [pendingSelfDocs, setPendingSelfDocs] = useState<PendingSelfDoc[]>([])
  const [, setSelectedPendingIds] = useState<string[]>([])
  const [, setSignedSelfDocs] = useState<SignedSelfDoc[]>([])
  const [workflowsLoading, setWorkflowsLoading] = useState(false)
  const [, setSignatureProviderReady] = useState<boolean | null>(null)
  const [feedback, setFeedback] = useState<string | null>(null)
  const [onlyofficeBaseUrl, setOnlyofficeBaseUrl] = useState('')
  const [docViewer, setDocViewer] = useState<'onlyoffice' | 'native'>('onlyoffice')
  const [forceNativeViewer, setForceNativeViewer] = useState(false)
  const [wfForceNativeViewer, setWfForceNativeViewer] = useState(false)
  const [positioningTargetKey, setPositioningTargetKey] = useState<string | null>(null)
  const [positioningTargetName, setPositioningTargetName] = useState('')
  const [, setPositioningDocumentId] = useState<string | null>(null)
  const [positioningFileUrl, setPositioningFileUrl] = useState<string | null>(null)
  const [positioningIsObjectUrl, setPositioningIsObjectUrl] = useState(false)
  const zonesByFileKey = useSignatureFilesStore((s) => s.zonesByFileKey)
  const setZonesByFileKey = useSignatureFilesStore((s) => s.setZonesByFileKey)
  const savedZoneByKey = useSignatureFilesStore((s) => s.savedZoneByKey)
  const setSavedZoneByKey = useSignatureFilesStore((s) => s.setSavedZoneByKey)
  const [dragAction, setDragAction] = useState<{ zoneId: string; mode: 'move' | 'resize'; startX: number; startY: number; origZone: SignatureZone } | null>(null)
  const wfDocsToSignRef = useRef<HTMLInputElement>(null)
  const wfAttachedDocsRef = useRef<HTMLInputElement>(null)

  const currentPositioningZones = positioningTargetKey ? zonesByFileKey[positioningTargetKey] || [] : []
  const positioningOnlyofficeViewerUrl = positioningFileUrl && onlyofficeBaseUrl
    ? `${onlyofficeBaseUrl}/web-apps/apps/documenteditor/main/index.html?fileUrl=${encodeURIComponent(positioningFileUrl)}`
    : null
  const shouldUseOnlyofficePositioning = docViewer === 'onlyoffice' && !forceNativeViewer && Boolean(positioningOnlyofficeViewerUrl)

  // ── Workflow creation modal state ──────────────────────────────────────
  const [showCreateWfModal, setShowCreateWfModal] = useState(false)
  const [wfStep, setWfStep] = useState(1)
  const [wfTemplates, setWfTemplates] = useState<WorkflowTemplateItem[]>([])
  const [wfSignataires, setWfSignataires] = useState<AppUserRecord[]>([])
  const [myWorkflows, setMyWorkflows] = useState<WorkflowItem[]>([])
  const [wfForm, setWfForm] = useState({
    templateId: '',
    name: '',
    description: '',
    validationSteps: [{ id: 1, approverId: '' }],
    signatureSteps: [{ id: 1, signerId: '' }],
    docsToSign: [] as string[],
    attachedDocs: [] as string[],
    docsToSignUploaded: [] as File[],
    attachedDocsUploaded: [] as File[],
    docsToSignSource: 'documents' as 'documents' | 'upload',
    attachedDocsSource: 'documents' as 'documents' | 'upload',
    notifyEmail: true,
    notifyEmails: '',
    notifyCc: '',
    notifyStages: {
      onValidationStep: true,
      onSignatureStep: true,
      onApproved: true,
      onRejected: false as boolean,
      onCompleted: true,
    },
    sendDownloadLink: true,
  })
  const [wfPositioningFile, setWfPositioningFile] = useState<File | null>(null)
  const [wfPositioningFileUrl, setWfPositioningFileUrl] = useState<string | null>(null)
  const [wfZonesByFileKey, setWfZonesByFileKey] = useState<Record<string, SignatureZone[]>>({})
  const [wfDragAction, setWfDragAction] = useState<{ zoneId: string; mode: 'move' | 'resize'; startX: number; startY: number; origZone: SignatureZone } | null>(null)
  const [wfSubmitting, setWfSubmitting] = useState(false)
  const [wfError, setWfError] = useState<string | null>(null)
  const WF_MAX_STEP = 5

  const getApiErrorMessage = (error: any, fallback: string) => {
    const responseMessage = error?.response?.data?.message
    if (Array.isArray(responseMessage)) {
      return responseMessage.join(' | ') || fallback
    }
    if (typeof responseMessage === 'string' && responseMessage.trim()) {
      return responseMessage
    }
    if (typeof error?.message === 'string' && error.message.trim()) {
      return error.message
    }
    return fallback
  }

  const getFileKey = (file: File) => `${file.name}-${file.size}-${file.lastModified}`
  const wfCurrentZones = wfPositioningFile ? wfZonesByFileKey[getFileKey(wfPositioningFile)] || [] : []
  const getDocumentZoneKey = (documentId: string) => `doc-${documentId}`
  const getUserDisplayLabel = (userId?: string | null) => {
    if (!userId) return 'Non assigné'
    if (user?.id === userId) {
      return user.fullName || user.username || user.email || 'Vous'
    }
    const matched = wfSignataires.find((item) => item.id === userId)
    if (!matched) return 'Utilisateur inconnu'
    return matched.fullName || matched.username || matched.email || 'Utilisateur inconnu'
  }

  const getExecutionStatusLabel = (
    status: string,
    currentStep: number,
    totalSteps: number,
  ) => {
    const normalized = String(status || '').toLowerCase()
    if (normalized === 'completed') return 'Terminé'
    if (normalized === 'rejected') return 'Rejeté'
    if (normalized === 'in_progress') {
      if (currentStep <= 1) return 'Démarré'
      if (currentStep <= totalSteps) return 'En cours'
    }
    if (normalized === 'pending') return 'Démarré'
    return status || 'Inconnu'
  }

  const getExecutionProgressPercent = (
    status: string,
    currentStep: number,
    totalSteps: number,
  ) => {
    if (totalSteps <= 0) return 0
    const normalized = String(status || '').toLowerCase()
    if (normalized === 'completed') return 100
    const completedSteps = Math.max(0, Math.min(currentStep - 1, totalSteps))
    return Math.round((completedSteps / totalSteps) * 100)
  }
  const formatDate = (value?: string | null) => {
    if (!value) return '-'
    const parsed = new Date(value)
    return Number.isNaN(parsed.getTime()) ? '-' : parsed.toLocaleString('fr-FR')
  }

  const buildWorkflowInboxRows = async (): Promise<SignatureWorkflowRow[]> => {
    const [wfList, docs] = await Promise.all([fetchWorkflows(), fetchDocuments()])
    const docsMap = new Map(docs.map((doc) => [doc.id, doc.title]))

    const detailsList = await Promise.all(
      wfList.map(async (wf) => {
        try {
          return await fetchWorkflowDetails(wf.id)
        } catch {
          return null
        }
      }),
    )

    type ExecutionItem = {
      executionId: string
      documentId: string
      documentTitle: string
      status: string
      progressPercent: number
      nextActorLabel: string
      isMyTurn: boolean
    }

    const grouped = new Map<
      string,
      {
        workflowId: string
        workflowName: string
        creatorLabel: string
        actionType: 'signature' | 'validation'
        currentStep: number
        items: ExecutionItem[]
      }
    >()

    for (const details of detailsList) {
      if (!details) continue
      const steps = (details.steps || []) as any[]
      const executions = (details.executions || []) as WorkflowExecution[]

      for (const execution of executions) {
        const currentStep = Number(execution.currentStep || 1)
        const currentStepData = steps.find((step) => Number(step.order) === currentStep)
        const totalSteps = Math.max(steps.length, 1)
        const nextStepData = steps.find((step) => Number(step.order) === currentStep + 1)
        const assigneeId = currentStepData?.assigneeId || currentStepData?.approverId || ''
        const actionType: 'signature' | 'validation' = isSignatureStepType(
          currentStepData?.description || currentStepData?.name,
          currentStepData?.requiresSignature,
        )
          ? 'signature'
          : 'validation'
        const progressPercent = getExecutionProgressPercent(execution.status, currentStep, totalSteps)
        const nextActorId = nextStepData?.assigneeId || nextStepData?.approverId || ''
        const nextActorFromRelation =
          nextStepData?.assignee?.fullName ||
          nextStepData?.assignee?.username ||
          nextStepData?.assignee?.email
        const nextActorLabel =
          execution.status === 'completed'
            ? 'Workflow terminé'
            : execution.status === 'rejected'
              ? 'Workflow rejeté'
              : nextActorFromRelation || (nextActorId ? getUserDisplayLabel(nextActorId) : 'Fin de workflow')

        const key = `${details.id}::${actionType}::${currentStep}`
        const creatorLabel =
          details.creator?.fullName ||
          details.creator?.username ||
          details.creator?.email ||
          'Créateur inconnu'
        const group = grouped.get(key) || {
          workflowId: details.id,
          workflowName: details.name,
          creatorLabel,
          actionType,
          currentStep,
          items: [],
        }

        group.items.push({
          executionId: execution.id,
          documentId: execution.documentId,
          documentTitle: docsMap.get(execution.documentId) || `Document ${execution.documentId.slice(0, 8)}`,
          status: execution.status,
          progressPercent,
          nextActorLabel,
          isMyTurn: !assigneeId || assigneeId === user?.id,
        })

        grouped.set(key, group)
      }
    }

    const rows: SignatureWorkflowRow[] = []

    for (const group of grouped.values()) {
      const statuses = group.items.map((item) => item.status)
      const status = statuses.includes('in_progress')
        ? 'in_progress'
        : statuses.includes('pending')
          ? 'pending'
          : statuses.includes('rejected')
            ? 'rejected'
            : 'completed'

      const statusLabel =
        status === 'completed'
          ? 'Terminé'
          : status === 'rejected'
            ? 'Rejeté'
            : status === 'in_progress'
              ? 'En cours'
              : 'Démarré'

      const progressPercent =
        group.items.length > 0
          ? Math.round(
              group.items.reduce((sum, item) => sum + item.progressPercent, 0) / group.items.length,
            )
          : 0

      const inProgressItem = group.items.find((item) => item.status === 'in_progress')
      const representative = inProgressItem || group.items[0]
      const actionableExecutionIds = group.items
        .filter((item) => item.status === 'in_progress' && item.isMyTurn)
        .map((item) => item.executionId)

      rows.push({
        executionId: representative?.executionId || `${group.workflowId}-${group.currentStep}`,
        executionIds: group.items.map((item) => item.executionId),
        actionableExecutionIds,
        workflowId: group.workflowId,
        workflowName: group.workflowName,
        creatorLabel: group.creatorLabel,
        documentId: representative?.documentId || '',
        documentTitle:
          group.items.length === 1
            ? representative?.documentTitle || 'Document'
            : `${group.items.length} documents`,
        documentCount: group.items.length,
        status,
        statusLabel,
        progressPercent,
        nextActorLabel:
          status === 'completed'
            ? 'Workflow terminé'
            : status === 'rejected'
              ? 'Workflow rejeté'
              : inProgressItem?.nextActorLabel || representative?.nextActorLabel || 'Fin de workflow',
        actionType: group.actionType,
        isMyTurn: actionableExecutionIds.length > 0,
      })
    }

    rows.sort((a, b) => {
      if (a.isMyTurn !== b.isMyTurn) return a.isMyTurn ? -1 : 1
      if (a.status !== b.status) {
        const aPending = a.status === 'in_progress'
        const bPending = b.status === 'in_progress'
        if (aPending !== bPending) return aPending ? -1 : 1
      }
      return a.workflowName.localeCompare(b.workflowName)
    })

    return rows
  }

  const reloadSelfSignatureLists = async () => {
    const docs = await fetchDocuments(1, 200)
    setDocuments(docs)

    setSelectedDocumentId((previous) => {
      if (previous && docs.some((doc) => doc.id === previous)) return previous
      return docs[0]?.id || ''
    })

    const pending = docs
      .filter((doc) => doc.status !== 'signed' && doc.status !== 'archived')
      .filter((doc) => {
        const path = String(doc.filePath || '')
        return Boolean(path) && !/\/undefined$/i.test(path)
      })
      .map((doc) => ({ doc, uploadedAt: formatDate(doc.createdAt) }))
    const signed = docs
      .filter((doc) => doc.status === 'signed')
      .map((doc) => ({ doc, signedAt: formatDate(doc.signedAt || doc.updatedAt) }))

    setPendingSelfDocs(pending)
    setSignedSelfDocs(signed)
  }

  useEffect(() => {
    setSelectedPendingIds((prev) => prev.filter((id) => pendingSelfDocs.some((entry) => entry.doc.id === id)))
  }, [pendingSelfDocs])

  useEffect(() => {
    const loadDocuments = async () => {
      if (!user?.id) return
      try {
        await reloadSelfSignatureLists()
      } catch (error) {
        setFeedback('Impossible de charger les documents')
      }
    }

    loadDocuments()
  }, [user?.id])

  useEffect(() => {
    const loadOnlyOfficeUrl = async () => {
      try {
        const [setting, viewerSetting] = await Promise.all([
          fetchAppSetting('oo_url'),
          fetchAppSetting('doc_viewer'),
        ])
        setOnlyofficeBaseUrl((setting?.value || '').trim().replace(/\/$/, ''))
        setDocViewer(viewerSetting?.value === 'native' ? 'native' : 'onlyoffice')
      } catch {
        setOnlyofficeBaseUrl('')
        setDocViewer('onlyoffice')
      }
    }

    loadOnlyOfficeUrl()
  }, [])

  useEffect(() => {
    if (!positioningTargetKey || !shouldUseOnlyofficePositioning) return
    const timer = window.setTimeout(() => {
      setForceNativeViewer(true)
      setFeedback('OnlyOffice indisponible, bascule automatique vers le lecteur PDF natif.')
    }, 2500)

    return () => {
      window.clearTimeout(timer)
    }
  }, [positioningTargetKey, shouldUseOnlyofficePositioning])

  useEffect(() => {
    const shouldUseOnlyofficeForWorkflow = docViewer === 'onlyoffice'
      && !wfForceNativeViewer
      && Boolean(wfPositioningFile)
      && Boolean(wfPositioningFileUrl)
      && Boolean(onlyofficeBaseUrl)

    if (!shouldUseOnlyofficeForWorkflow) return

    const timer = window.setTimeout(() => {
      setWfForceNativeViewer(true)
      setFeedback('OnlyOffice indisponible, bascule automatique vers le lecteur PDF natif.')
    }, 2500)

    return () => {
      window.clearTimeout(timer)
    }
  }, [docViewer, wfForceNativeViewer, wfPositioningFile, wfPositioningFileUrl, onlyofficeBaseUrl])

  useEffect(() => {
    const loadPending = async () => {
      if (!user?.id) return
      try {
        const requests = await getPendingSignatures(user.id)
        setPendingRequests(requests)
      } catch (error) {
        console.error(error)
      }
    }

    loadPending()
  }, [user])

  useEffect(() => {
    const loadSignatures = async () => {
      if (!selectedDocumentId) return
      try {
        const sigs = await fetchSignatures(selectedDocumentId)
        setSignatures(sigs)
      } catch (error) {
        setFeedback('Impossible de charger les signatures')
      }
    }

    loadSignatures()
  }, [selectedDocumentId])

  useEffect(() => {
    const loadWorkflowRows = async () => {
      if (!user?.id) {
        setWorkflowsRows([])
        setWorkflowsLoading(false)
        return
      }
      setWorkflowsLoading(true)
      try {
        const rows = await buildWorkflowInboxRows()
        setWorkflowsRows(rows)
      } catch {
        setFeedback('Impossible de charger les workflows de signature/validation')
      } finally {
        setWorkflowsLoading(false)
      }
    }

    loadWorkflowRows()

    const timer = window.setInterval(() => {
      loadWorkflowRows()
    }, 10000)

    return () => {
      window.clearInterval(timer)
    }
  }, [user?.id])

  useEffect(() => {
    const loadSignatureProvider = async () => {
      try {
        const cfg = await fetchSignatureProviderConfig()
        const ready = Boolean(
          cfg?.isActive &&
          (cfg?.endpoint || '').trim() &&
          (cfg?.apiKey || '').trim() &&
          (cfg?.consentPageId || '').trim() &&
          (cfg?.signatureProfileId || '').trim(),
        )
        setSignatureProviderReady(ready)
      } catch {
        setSignatureProviderReady(false)
      }
    }

    loadSignatureProvider()
  }, [])

  const handleRequest = async (event: React.FormEvent) => {
    event.preventDefault()
    if (!selectedDocumentId || !requestTo) {
      setFeedback('Document et destinataire requis')
      return
    }

    try {
      await requestSignature(selectedDocumentId, { recipientEmail: requestTo, message: requestMessage })
      setFeedback('Demande de signature envoyée')
      setRequestMessage('')
      setRequestTo('')
      if (user?.id) {
        const requests = await getPendingSignatures(user.id)
        setPendingRequests(requests)
      }
    } catch (error) {
      setFeedback('Erreur lors de la demande de signature')
    }
  }

  const handleSign = async (event: React.FormEvent) => {
    event.preventDefault()
    if (!selectedDocumentId || !signatureHash) {
      setFeedback('Document et hash de signature requis')
      return
    }

    try {
      await signDocument(selectedDocumentId, { signatureHash })
      setFeedback('Document signé avec succès')
      setSignatureHash('')
      if (selectedDocumentId) {
        const sigs = await fetchSignatures(selectedDocumentId)
        setSignatures(sigs)
      }
      await reloadSelfSignatureLists()
    } catch (error: any) {
      setFeedback(getApiErrorMessage(error, 'Erreur lors de la signature du document'))
    }
  }

  const handleRespond = async (requestId: string, accepted: boolean) => {
    try {
      await respondToSignatureRequest(requestId, accepted)
      setFeedback(accepted ? 'Demande acceptée' : 'Demande refusée')
      if (user?.id) {
        const requests = await getPendingSignatures(user.id)
        setPendingRequests(requests)
      }
    } catch (error) {
      setFeedback('Erreur lors de la réponse à la demande de signature')
    }
  }

  const handleVerify = async (signatureId: string) => {
    if (!selectedDocumentId) {
      setFeedback('Document non sélectionné')
      return
    }
    try {
      await verifySignature(selectedDocumentId, signatureId)
      setFeedback('Signature vérifiée')
    } catch (error) {
      setFeedback('Erreur de vérification')
    }
  }

  const refreshWorkflowsRows = async () => {
    try {
      const rows = await buildWorkflowInboxRows()
      setWorkflowsRows(rows)
    } catch {
      // best effort refresh
    }
  }

  const handleWorkflowSignatureAction = async (row: SignatureWorkflowRow) => {
    if (row.status !== 'in_progress') {
      setFeedback('Cette exécution n’est plus en cours')
      return
    }

    if (row.actionType !== 'signature') {
      setFeedback('Cette étape est une validation, pas une signature')
      return
    }

    if (row.actionableExecutionIds.length === 0) {
      setFeedback('Cette étape n’est pas assignée à votre utilisateur')
      return
    }

    try {
      const results = await Promise.allSettled(
        row.actionableExecutionIds.map((executionId) =>
          performWorkflowStepAction(executionId, 'signature'),
        ),
      )
      const successCount = results.filter((result) => result.status === 'fulfilled').length
      const failedCount = results.length - successCount
      if (successCount === 0) {
        const firstRejected = results.find((result) => result.status === 'rejected') as
          | PromiseRejectedResult
          | undefined
        throw firstRejected?.reason
      }

      setFeedback(
        failedCount > 0
          ? `Signature par lot partielle: ${successCount} document(s) signé(s), ${failedCount} en échec.`
          : `Signature par lot effectuée sur ${successCount} document(s).`,
      )

      if (row.documentId) {
        setSelectedDocumentId(row.documentId)
        const sigs = await fetchSignatures(row.documentId)
        setSignatures(sigs)
      }
      await refreshWorkflowsRows()
      await reloadSelfSignatureLists()
    } catch (error: any) {
      setFeedback(getApiErrorMessage(error, 'Erreur lors de la signature depuis le workflow'))
    }
  }

  const handleWorkflowValidationAction = async (row: SignatureWorkflowRow) => {
    if (row.status !== 'in_progress') {
      setFeedback('Cette exécution n’est plus en cours')
      return
    }

    if (row.actionType !== 'validation') {
      setFeedback('Cette étape est une signature, pas une validation')
      return
    }

    if (row.actionableExecutionIds.length === 0) {
      setFeedback('Cette étape n’est pas assignée à votre utilisateur')
      return
    }

    try {
      const results = await Promise.allSettled(
        row.actionableExecutionIds.map((executionId) =>
          performWorkflowStepAction(executionId, 'validation'),
        ),
      )
      const successCount = results.filter((result) => result.status === 'fulfilled').length
      const failedCount = results.length - successCount
      if (successCount === 0) {
        const firstRejected = results.find((result) => result.status === 'rejected') as
          | PromiseRejectedResult
          | undefined
        throw firstRejected?.reason
      }

      setFeedback(
        failedCount > 0
          ? `Validation par lot partielle: ${successCount} document(s) validé(s), ${failedCount} en échec.`
          : `Validation par lot effectuée sur ${successCount} document(s).`,
      )
      await refreshWorkflowsRows()
    } catch (error: any) {
      setFeedback(getApiErrorMessage(error, 'Erreur lors de la validation depuis le workflow'))
    }
  }

  const openPositioningForDocument = async (documentId: string) => {
    let doc: DocumentItem | null = documents.find((item) => item.id === documentId) || null
    try {
      doc = await fetchDocumentById(documentId)
    } catch {
      // Best effort: fallback to list item if detail fetch fails.
    }

    const mimeType = String(doc?.mimeType || '').toLowerCase()
    if (mimeType && !mimeType.includes('pdf')) {
      setFeedback('Le positionnement est disponible uniquement pour les fichiers PDF')
      return
    }

    const apiBaseUrl = (import.meta.env.VITE_API_URL || 'http://localhost:3000/api/v1').replace(/\/?api\/?v1\/?$/i, '')
    const absoluteFileUrl = `${apiBaseUrl}/api/v1/documents/public/${encodeURIComponent(documentId)}/digital-version`

    if (positioningIsObjectUrl && positioningFileUrl) {
      URL.revokeObjectURL(positioningFileUrl)
    }

    const resolvedDocId = doc?.id || documentId
    setPositioningTargetKey(getDocumentZoneKey(resolvedDocId))
    setPositioningTargetName(doc?.title || `Document ${String(documentId).slice(0, 8)}`)
    setPositioningDocumentId(resolvedDocId)
    setPositioningFileUrl(absoluteFileUrl)
    setPositioningIsObjectUrl(false)
    setForceNativeViewer(false)
    setSavedZoneByKey((prev) => ({
      ...prev,
      [getDocumentZoneKey(resolvedDocId)]: Boolean((zonesByFileKey[getDocumentZoneKey(resolvedDocId)] || []).length),
    }))
  }

  const closePositioning = () => {
    if (positioningIsObjectUrl && positioningFileUrl) {
      URL.revokeObjectURL(positioningFileUrl)
    }
    setPositioningTargetKey(null)
    setPositioningTargetName('')
    setPositioningDocumentId(null)
    setPositioningFileUrl(null)
    setPositioningIsObjectUrl(false)
    setForceNativeViewer(false)
    setDragAction(null)
  }

  const addZone = (x = 10, y = 15) => {
    if (!positioningTargetKey) return
    setZonesByFileKey((prev) => {
      const currentZones = prev[positioningTargetKey] || []
      const nextZone: SignatureZone = {
        id: currentZones[0]?.id || `zone-${Date.now()}-1`,
        x,
        y,
        width: 28,
        height: 12,
      }
      // Keep a single signature zone per document.
      return { ...prev, [positioningTargetKey]: [nextZone] }
    })
    setSavedZoneByKey((prev) => ({ ...prev, [positioningTargetKey]: false }))
  }

  const clearZones = () => {
    if (!positioningTargetKey) return
    setZonesByFileKey((prev) => ({ ...prev, [positioningTargetKey]: [] }))
    setSavedZoneByKey((prev) => ({ ...prev, [positioningTargetKey]: false }))
  }

  const deleteZone = (zoneId: string) => {
    if (!positioningTargetKey) return
    setZonesByFileKey((prev) => ({
      ...prev,
      [positioningTargetKey]: (prev[positioningTargetKey] || []).filter((z) => z.id !== zoneId),
    }))
    setSavedZoneByKey((prev) => ({ ...prev, [positioningTargetKey]: false }))
  }

  const handleOverlayPointerMove = (event: React.PointerEvent<HTMLDivElement>) => {
    if (!positioningTargetKey || !dragAction) return
    const rect = event.currentTarget.getBoundingClientRect()
    const pctX = ((event.clientX - rect.left) / rect.width) * 100
    const pctY = ((event.clientY - rect.top) / rect.height) * 100
    const dx = pctX - dragAction.startX
    const dy = pctY - dragAction.startY
    const original = dragAction.origZone

    setZonesByFileKey((prev) => {
      const currentZones = prev[positioningTargetKey] || []
      return {
        ...prev,
        [positioningTargetKey]: currentZones.map((zone) => (
          zone.id === dragAction.zoneId
            ? dragAction.mode === 'move'
              ? {
                ...zone,
                x: Math.max(0, Math.min(100 - zone.width, original.x + dx)),
                y: Math.max(0, Math.min(100 - zone.height, original.y + dy)),
              }
              : {
                ...zone,
                width: Math.max(8, Math.min(100 - original.x, original.width + dx)),
                height: Math.max(4, Math.min(100 - original.y, original.height + dy)),
              }
            : zone
        )),
      }
    })
    setSavedZoneByKey((prev) => ({ ...prev, [positioningTargetKey]: false }))
  }

  const handleSaveZonePlacement = () => {
    if (!positioningTargetKey) return
    if (currentPositioningZones.length === 0) {
      setFeedback('Veuillez placer la zone de signature avant d\'enregistrer')
      return
    }
    setSavedZoneByKey((prev) => ({ ...prev, [positioningTargetKey]: true }))
    setFeedback('Zone de signature enregistrée')
    closePositioning()
  }

  // ── Workflow creation modal helpers ────────────────────────────────────

  const resetWfForm = () => {
    setWfForm({
      templateId: '', name: '', description: '',
      validationSteps: [{ id: 1, approverId: '' }],
      signatureSteps: [{ id: 1, signerId: '' }],
      docsToSign: [], attachedDocs: [],
      docsToSignUploaded: [], attachedDocsUploaded: [],
      docsToSignSource: 'documents', attachedDocsSource: 'documents',
      notifyEmail: true, notifyEmails: '', notifyCc: '',
      notifyStages: { onValidationStep: true, onSignatureStep: true, onApproved: true, onRejected: false as boolean, onCompleted: true },
      sendDownloadLink: true,
    })
  }

  const openCreateWfModal = () => {
    setWfStep(1)
    resetWfForm()
    setShowCreateWfModal(true)
  }

  const closeWfPositioning = () => {
    if (wfPositioningFileUrl) URL.revokeObjectURL(wfPositioningFileUrl)
    setWfPositioningFile(null)
    setWfPositioningFileUrl(null)
    setWfForceNativeViewer(false)
    setWfDragAction(null)
  }

  const closeCreateWfModal = () => {
    setShowCreateWfModal(false)
    setWfStep(1)
    closeWfPositioning()
  }

  const wfAddValidationStep = () => {
    const newId = Math.max(...wfForm.validationSteps.map(s => s.id), 0) + 1
    setWfForm(prev => ({ ...prev, validationSteps: [...prev.validationSteps, { id: newId, approverId: '' }] }))
  }
  const wfRemoveValidationStep = (id: number) => {
    setWfForm(prev => ({ ...prev, validationSteps: prev.validationSteps.filter(s => s.id !== id) }))
  }
  const wfAddSignatureStep = () => {
    const newId = Math.max(...wfForm.signatureSteps.map(s => s.id), 0) + 1
    setWfForm(prev => ({ ...prev, signatureSteps: [...prev.signatureSteps, { id: newId, signerId: '' }] }))
  }
  const wfRemoveSignatureStep = (id: number) => {
    setWfForm(prev => ({ ...prev, signatureSteps: prev.signatureSteps.filter(s => s.id !== id) }))
  }

  const wfNextStep = () => {
    if (wfStep === 1 && !wfForm.name.trim()) { setFeedback('Le nom du parapheur est obligatoire'); return }
    if (wfStep === 2) {
      const validationCount = wfForm.validationSteps.filter(s => s.approverId.trim()).length
      const signatureCount = wfForm.signatureSteps.filter(s => s.signerId.trim()).length
      if (validationCount + signatureCount === 0) {
        setFeedback('Ajoutez au moins une étape: validation ou signature')
        return
      }
    }
    setFeedback(null)
    setWfStep(prev => Math.min(prev + 1, WF_MAX_STEP))
  }
  const wfPrevStep = () => setWfStep(prev => Math.max(prev - 1, 1))

  const openWfPositioning = (file: File) => {
    if (!file.type.includes('pdf') && !file.name.toLowerCase().endsWith('.pdf')) {
      setFeedback('Le positionnement est disponible uniquement pour les fichiers PDF')
      return
    }
    if (wfPositioningFileUrl) URL.revokeObjectURL(wfPositioningFileUrl)
    setWfPositioningFile(file)
    setWfPositioningFileUrl(URL.createObjectURL(file))
    setWfForceNativeViewer(false)
  }

  const wfAddZone = (x = 10, y = 15) => {
    if (!wfPositioningFile) return
    const fileKey = getFileKey(wfPositioningFile)
    setWfZonesByFileKey(prev => {
      const cur = prev[fileKey] || []
      return { ...prev, [fileKey]: [...cur, { id: `zone-${Date.now()}-${cur.length + 1}`, x, y, width: 28, height: 12 }] }
    })
  }
  const wfClearZones = () => {
    if (!wfPositioningFile) return
    setWfZonesByFileKey(prev => ({ ...prev, [getFileKey(wfPositioningFile!)]: [] }))
  }
  const wfDeleteZone = (zoneId: string) => {
    if (!wfPositioningFile) return
    const fk = getFileKey(wfPositioningFile)
    setWfZonesByFileKey(prev => ({ ...prev, [fk]: (prev[fk] || []).filter(z => z.id !== zoneId) }))
  }
  const wfHandlePointerMove = (e: React.PointerEvent<HTMLDivElement>) => {
    if (!wfDragAction || !wfPositioningFile) return
    const rect = e.currentTarget.getBoundingClientRect()
    const pctX = ((e.clientX - rect.left) / rect.width) * 100
    const pctY = ((e.clientY - rect.top) / rect.height) * 100
    const dx = pctX - wfDragAction.startX
    const dy = pctY - wfDragAction.startY
    const fk = getFileKey(wfPositioningFile)
    const orig = wfDragAction.origZone

    setWfZonesByFileKey(prev => ({
      ...prev,
      [fk]: (prev[fk] || []).map(z => {
        if (z.id !== wfDragAction.zoneId) return z
        if (wfDragAction.mode === 'move') {
          return { ...z, x: Math.max(0, Math.min(100 - z.width, orig.x + dx)), y: Math.max(0, Math.min(100 - z.height, orig.y + dy)) }
        }
        // resize
        const newW = Math.max(8, Math.min(100 - orig.x, orig.width + dx))
        const newH = Math.max(4, Math.min(100 - orig.y, orig.height + dy))
        return { ...z, width: newW, height: newH }
      })
    }))
  }
  const wfStopDrag = () => setWfDragAction(null)

  const handleCreateWf = async (event: React.FormEvent) => {
    event.preventDefault()
    setWfError(null)
    if (!wfForm.name) { setWfError('Nom du workflow requis'); return }
    const validationStepsFiltered = wfForm.validationSteps.filter(s => s.approverId.trim())
    const signatureStepsFiltered = wfForm.signatureSteps.filter(s => s.signerId.trim())
    if (validationStepsFiltered.length + signatureStepsFiltered.length === 0) {
      setWfError('Ajoutez au moins une étape: validation ou signature')
      return
    }
    setWfSubmitting(true)
    try {
      const uploadFilesWithHandling = async (files: File[]) => {
        const uploaded: DocumentItem[] = []
        for (const file of files) {
          const doc = await uploadDocumentFile(file)
          uploaded.push(doc)
        }
        return uploaded
      }
      const [uploadedDocsToSign, uploadedAttachedDocs] = await Promise.all([
        uploadFilesWithHandling(wfForm.docsToSignUploaded),
        uploadFilesWithHandling(wfForm.attachedDocsUploaded),
      ])
      const docsToSignIds = [...wfForm.docsToSign, ...uploadedDocsToSign.map(d => d.id)]
      const attachedDocsIds = [...wfForm.attachedDocs, ...uploadedAttachedDocs.map(d => d.id)]
      const steps = [
        ...validationStepsFiltered.map((s, i) => ({ name: `Validation ${i + 1}`, approverId: s.approverId, order: i + 1 })),
        ...signatureStepsFiltered.map((s, i) => ({ name: `Signature ${i + 1}`, approverId: s.signerId, order: validationStepsFiltered.length + i + 1 })),
      ]
      const uploadedSignatureFiles = wfForm.docsToSignUploaded.map(file => {
        const fileKey = getFileKey(file)
        return {
          fileName: file.name, fileSize: file.size, fileType: file.type || 'application/octet-stream',
          zones: (wfZonesByFileKey[fileKey] || []).map(z => ({ x: z.x, y: z.y, width: z.width, height: z.height })),
        }
      })
      const created = await createWorkflow({ name: wfForm.name, description: wfForm.description, steps, docsToSign: docsToSignIds, attachedDocs: attachedDocsIds, uploadedSignatureFiles })
      // Démarrer le workflow : exécuter pour chaque document à signer
      if (docsToSignIds.length > 0) {
        for (const docId of docsToSignIds) {
          try {
            await executeWorkflow(created.id, docId)
          } catch { /* execution errors non-blocking */ }
        }
      }
      // Recharger les workflows pour avoir les executions à jour
      const refreshed = await fetchWorkflows().catch(() => [] as WorkflowItem[])
      setMyWorkflows(refreshed)
      if (uploadedDocsToSign.length > 0 || uploadedAttachedDocs.length > 0) {
        setDocuments(prev => [...uploadedDocsToSign, ...uploadedAttachedDocs, ...prev])
      }
      resetWfForm()
      setWfZonesByFileKey({})
      closeCreateWfModal()
      setFeedback('Workflow créé et démarré avec succès')
    } catch (err: any) {
      setWfError(getApiErrorMessage(err, 'Échec de la création du workflow'))
    } finally {
      setWfSubmitting(false)
    }
  }

  // Load signataires + templates + workflows for the modal / table
  useEffect(() => {
    fetchSignataires().then(setWfSignataires).catch(() => setWfSignataires([]))
    fetchWorkflowTemplates().then(setWfTemplates).catch(() => setWfTemplates([]))
    fetchWorkflows().then(setMyWorkflows).catch(() => setMyWorkflows([]))
  }, [])

  // Apply template when selected
  useEffect(() => {
    if (!wfForm.templateId) return
    const tpl = wfTemplates.find(t => t.id === wfForm.templateId)
    if (!tpl) return
    setWfForm(prev => ({
      ...prev,
      name: tpl.name,
      description: tpl.description || '',
      validationSteps: tpl.validationSteps.length > 0 ? tpl.validationSteps.map((s, i) => ({ id: i + 1, approverId: s.approverId || '' })) : [{ id: 1, approverId: '' }],
      signatureSteps: tpl.signatureSteps.length > 0 ? tpl.signatureSteps.map((s, i) => ({ id: i + 1, signerId: s.signerId || '' })) : [{ id: 1, signerId: '' }],
      notifyEmail: tpl.notificationConfig?.notifyEmail ?? true,
      notifyEmails: tpl.notificationConfig?.emails || '',
      notifyCc: tpl.notificationConfig?.cc || '',
      notifyStages: {
        onValidationStep: tpl.notificationConfig?.stages?.onValidationStep ?? true,
        onSignatureStep: tpl.notificationConfig?.stages?.onSignatureStep ?? true,
        onApproved: tpl.notificationConfig?.stages?.onApproved ?? true,
        onRejected: tpl.notificationConfig?.stages?.onRejected ?? false as boolean,
        onCompleted: tpl.notificationConfig?.stages?.onCompleted ?? true,
      },
      sendDownloadLink: tpl.notificationConfig?.sendDownloadLink ?? true,
    }))
  }, [wfForm.templateId, wfTemplates])

  // Cleanup wf positioning URL on unmount
  useEffect(() => () => { if (wfPositioningFileUrl) URL.revokeObjectURL(wfPositioningFileUrl) }, [wfPositioningFileUrl])

  return (
    <div className="space-y-6">
      {feedback && <div className="p-3 bg-blue-100 text-blue-800 rounded-xl">{feedback}</div>}

      {/* Hidden file inputs for workflow modal – always in DOM */}
      <input ref={wfDocsToSignRef} type="file" multiple
        style={{ position: 'absolute', width: 1, height: 1, opacity: 0, overflow: 'hidden' }}
        onChange={(e) => { const files = Array.from(e.target.files || []); e.target.value = ''; if (files.length > 0) setWfForm(prev => ({ ...prev, docsToSignUploaded: [...prev.docsToSignUploaded, ...files] })) }} />
      <input ref={wfAttachedDocsRef} type="file" multiple
        style={{ position: 'absolute', width: 1, height: 1, opacity: 0, overflow: 'hidden' }}
        onChange={(e) => { const files = Array.from(e.target.files || []); e.target.value = ''; if (files.length > 0) setWfForm(prev => ({ ...prev, attachedDocsUploaded: [...prev.attachedDocsUploaded, ...files] })) }} />

      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-base font-semibold text-gray-800">
          <button className="flex items-center gap-2 hover:text-[#2453d6]"><PenLine size={18} /> Signer</button>
          <button className="flex items-center gap-2 hover:text-[#2453d6]"><Send size={18} /> Demander</button>
          <button className="flex items-center gap-2 hover:text-[#2453d6]"><ShieldCheck size={18} /> Vérifier</button>
          <button className="flex items-center gap-2 hover:text-[#2453d6]"><CheckCircle2 size={18} /> Valider</button>
        </div>
      </div>

      <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <Workflow size={20} className="text-[#2453d6]" /> Boîte de réception des actions (signature / validation)
          </h2>
          <span className="text-xs text-gray-500">{workflowsRows.length} ligne(s)</span>
        </div>

        {workflowsLoading ? (
          <p className="text-sm text-gray-500">Chargement des workflows...</p>
        ) : workflowsRows.length === 0 ? (
          <p className="text-sm text-gray-500">Aucun workflow en exécution pour signature/validation.</p>
        ) : (
          <div className="overflow-auto rounded-xl border border-gray-200">
            <table className="w-full min-w-[760px] text-left text-sm">
              <thead className="bg-gray-100 text-gray-600 text-[11px] uppercase tracking-wide">
                <tr>
                  <th className="px-3 py-2 font-semibold">Workflow</th>
                  <th className="px-3 py-2 font-semibold">Créateur</th>
                  <th className="px-3 py-2 font-semibold">Progression</th>
                  <th className="px-3 py-2 font-semibold">Statut</th>
                  <th className="px-3 py-2 font-semibold">Prochain intervenant</th>
                  <th className="px-3 py-2 font-semibold">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 text-xs">
                {workflowsRows.map((row) => (
                  <tr key={row.executionId} className={!row.isMyTurn ? 'bg-gray-50' : 'bg-white'}>
                    <td className="px-3 py-2 font-medium text-gray-800">{row.workflowName}</td>
                    <td className="px-3 py-2 text-gray-700">{row.creatorLabel}</td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-2">
                        <div className="w-20 h-2 rounded-full bg-gray-200 overflow-hidden">
                          <div
                            className="h-2 rounded-full bg-[#2453d6] transition-all duration-300"
                            style={{ width: `${row.progressPercent}%` }}
                          />
                        </div>
                        <span className="text-gray-600 font-semibold text-xs">{row.progressPercent}%</span>
                      </div>
                    </td>
                    <td className="px-3 py-2">
                      <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold ${row.statusLabel === 'En cours' ? 'bg-amber-100 text-amber-800' : row.statusLabel === 'Terminé' ? 'bg-green-100 text-green-800' : row.statusLabel === 'Rejeté' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'}`}>
                        {row.statusLabel}
                      </span>
                    </td>
                    <td className="px-3 py-2 text-gray-600">{row.nextActorLabel}</td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-2">
                        {row.status !== 'completed' && row.actionType === 'signature' && (
                          <button
                            type="button"
                            title="Signer"
                            onClick={() => handleWorkflowSignatureAction(row)}
                            className={`h-8 w-8 rounded-lg border inline-flex items-center justify-center ${
                              row.status === 'in_progress' && row.isMyTurn
                                ? 'border-blue-200 text-blue-600 hover:bg-blue-50'
                                : 'border-gray-200 text-gray-400 hover:bg-gray-50'
                            }`}
                          >
                            <PenTool size={14} />
                          </button>
                        )}
                        {row.status !== 'completed' && row.actionType === 'validation' && (
                          <button
                            type="button"
                            title="Valider"
                            onClick={() => handleWorkflowValidationAction(row)}
                            className={`h-8 w-8 rounded-lg border inline-flex items-center justify-center ${
                              row.status === 'in_progress' && row.isMyTurn
                                ? 'border-emerald-200 text-emerald-600 hover:bg-emerald-50'
                                : 'border-gray-200 text-gray-400 hover:bg-gray-50'
                            }`}
                          >
                            <CheckCircle2 size={14} />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <div className="flex items-center justify-between">
          <h2 className="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <FileUp size={20} className="text-[#2453d6]" /> Signataire: téléverser et signer son propre document
          </h2>
          <button
            type="button"
            onClick={openCreateWfModal}
            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold shadow-sm transition-colors"
          >
            <PlusCircle size={18} />
            Créer un workflow de signature
          </button>
        </div>
        <p className="mt-3 text-sm text-gray-500">
          Pour téléverser et signer vos documents, créez un nouveau workflow via le formulaire dédié.
        </p>
      </section>

      {/* ── Tableau Workflows de signature / validation ──────────────── */}
      <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 className="text-2xl font-bold text-gray-800 mb-4">Suivi global des workflows</h2>
        {myWorkflows.length === 0 ? (
          <p className="text-gray-500 text-center py-6">Aucun workflow créé</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-gray-200">
                  <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nom</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Propriétaire</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Dernière modification</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Statut</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Progression</th>
                  <th className="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider"></th>
                </tr>
              </thead>
              <tbody>
                {myWorkflows.map((wf) => {
                  const totalSteps = wf.steps?.length || 1
                  const latestExec = wf.executions?.length ? wf.executions.reduce((a, b) => (a.currentStep > b.currentStep ? a : b)) : null
                  const completedSteps = latestExec ? Math.min(latestExec.currentStep - 1, totalSteps) : 0
                  const progress = Math.round((completedSteps / totalSteps) * 100)
                  const currentStepObj = latestExec && wf.steps ? wf.steps.find(s => s.order === latestExec.currentStep) : wf.steps?.[0]
                  const isSignatureStep = currentStepObj?.description?.toLowerCase().includes('signature') || currentStepObj?.name?.toLowerCase().includes('signature')
                  const statusLabel = !latestExec
                    ? 'BROUILLON'
                    : latestExec.status === 'completed'
                      ? 'TERMINÉ'
                      : latestExec.status === 'rejected'
                        ? 'REJETÉ'
                        : 'DÉMARRÉ'
                  const statusColor = !latestExec
                    ? 'bg-gray-200 text-gray-700'
                    : latestExec.status === 'completed'
                      ? 'bg-green-500 text-white'
                      : latestExec.status === 'rejected'
                        ? 'bg-red-500 text-white'
                        : 'bg-[#e8a230] text-white'
                  const progressColor = latestExec?.status === 'completed' ? 'bg-green-500' : 'bg-[#e8a230]'

                  return (
                    <tr key={wf.id} className="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                      <td className="px-4 py-4 text-sm text-gray-900 font-medium">{wf.name}</td>
                      <td className="px-4 py-4">
                        <div className="flex items-center gap-3">
                          {wf.creator?.avatar ? (
                            <img src={wf.creator.avatar} alt="" className="w-9 h-9 rounded-full object-cover" />
                          ) : (
                            <div className="w-9 h-9 rounded-full bg-gray-300 flex items-center justify-center text-white text-sm font-bold">{(wf.creator?.fullName || wf.creator?.username || '?')[0].toUpperCase()}</div>
                          )}
                          <div className="min-w-0">
                            <p className="text-sm font-medium text-gray-900 truncate">{wf.creator?.fullName || wf.creator?.username || '—'}</p>
                            <p className="text-xs text-gray-500 truncate">{wf.creator?.email || ''}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-4 text-sm text-gray-600">{wf.updatedAt ? new Date(wf.updatedAt).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—'}</td>
                      <td className="px-4 py-4">
                        <span className={`inline-block px-3 py-1 rounded-full text-xs font-bold uppercase ${statusColor}`}>{statusLabel}</span>
                      </td>
                      <td className="px-4 py-4">
                        <div className="flex items-center gap-2">
                          <div className="w-28 h-7 rounded border border-gray-300 bg-white overflow-hidden relative">
                            <div className={`absolute inset-y-0 left-0 ${progressColor} transition-all`} style={{ width: `${progress}%` }} />
                            <span className="absolute inset-0 flex items-center justify-center text-xs font-bold text-gray-700">{progress} %</span>
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-4 text-right">
                        <div className="flex items-center justify-end gap-2">
                          {isSignatureStep ? (
                            <button title="Signature" className="p-1.5 rounded hover:bg-blue-50 text-gray-500 hover:text-blue-600 transition-colors"><PenTool size={18} /></button>
                          ) : (
                            <button title="Validation" className="p-1.5 rounded hover:bg-green-50 text-gray-500 hover:text-green-600 transition-colors"><CheckCircle2 size={18} /></button>
                          )}
                          <button title="Voir les détails" className="p-1.5 rounded hover:bg-gray-100 text-gray-500 hover:text-gray-800 transition-colors"><Eye size={18} /></button>
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </section>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <section className="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <h2 className="text-2xl font-bold text-gray-800 mb-4">Signature de document</h2>
          <select
            value={selectedDocumentId}
            onChange={(e) => setSelectedDocumentId(e.target.value)}
            className="w-full p-2 border rounded mb-4"
          >
            {documents.map((doc) => (
              <option key={doc.id} value={doc.id}>
                {doc.title}
              </option>
            ))}
          </select>

          <form onSubmit={handleRequest} className="mt-2">
            <h3 className="font-semibold mb-2">Demander une signature</h3>
            <input
              type="text"
              className="w-full mb-2 p-2 border rounded"
              placeholder="ID du destinataire"
              value={requestTo}
              onChange={(e) => setRequestTo(e.target.value)}
              required
            />
            <input
              type="text"
              className="w-full mb-2 p-2 border rounded"
              placeholder="Message (optionnel)"
              value={requestMessage}
              onChange={(e) => setRequestMessage(e.target.value)}
            />
            <button type="submit" className="px-3 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
              Envoyer la demande
            </button>
          </form>

          <form onSubmit={handleSign} className="mt-6">
            <h3 className="font-semibold mb-2">Signer le document</h3>
            <input
              type="text"
              className="w-full mb-2 p-2 border rounded"
              placeholder="Hash de signature (ex: 0x123...)"
              value={signatureHash}
              onChange={(e) => setSignatureHash(e.target.value)}
              required
            />
            <button type="submit" className="px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
              Signer
            </button>
          </form>
        </section>

        <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <h2 className="text-2xl font-bold text-gray-800 mb-4">Signatures</h2>
          {signatures.length === 0 ? (
            <p className="text-gray-500">Aucune signature trouvée</p>
          ) : (
            <div className="space-y-2">
              {signatures.map((sig) => (
                <div key={sig.id} className="border border-gray-200 p-3 rounded-xl bg-gray-50">
                  <p className="text-xs text-gray-500">ID: {sig.id}</p>
                  <p className="text-sm text-gray-700">Utilisateur: {sig.userId}</p>
                  <p className="text-sm text-gray-700">Vérifié: {sig.verified ? 'Oui' : 'Non'}</p>
                  <button
                    onClick={() => handleVerify(sig.id)}
                    className="px-3 py-2 mt-2 bg-gray-200 rounded-lg hover:bg-gray-300"
                  >
                    Vérifier
                  </button>
                </div>
              ))}
            </div>
          )}
        </section>
      </div>

      <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 className="text-2xl font-bold text-gray-800 mb-4">Demandes en attente</h2>
        {pendingRequests.length === 0 ? (
          <p className="text-gray-500">Aucune demande en attente</p>
        ) : (
          <div className="space-y-2">
            {pendingRequests.map((request) => (
              <div key={request.id} className="border border-gray-200 p-3 rounded-xl bg-gray-50">
                <p className="text-sm text-gray-700">Document ID: {request.documentId}</p>
                <p className="text-sm text-gray-700">Demandé par: {request.requestedBy}</p>
                <p className="text-sm text-gray-700">Statut: {request.status}</p>
                <div className="mt-2 flex gap-2">
                  <button
                    onClick={() => handleRespond(request.id, true)}
                    className="px-3 py-2 bg-green-500 text-white rounded-lg flex items-center gap-1"
                  >
                    <CheckCircle2 size={14} /> Accepter
                  </button>
                  <button
                    onClick={() => handleRespond(request.id, false)}
                    className="px-3 py-2 bg-red-500 text-white rounded-lg flex items-center gap-1"
                  >
                    <XCircle size={14} /> Rejeter
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </section>

      {/* Positioning modal – place signature zones in OnlyOffice or native PDF viewer */}
      {positioningTargetKey && positioningFileUrl && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-lg border border-gray-200 w-full max-w-6xl h-[88vh] overflow-hidden flex flex-col">
            <div className="px-4 py-3 border-b border-gray-200 flex items-center justify-between shrink-0">
              <div>
                <p className="text-lg font-semibold text-gray-800 flex items-center gap-2">
                  <MapPin size={18} className="text-[#2453d6]" />
                  Zone de signature — {positioningTargetName}
                </p>
                <p className="text-sm text-gray-500">
                  1 zone max · Cliquez dans le lecteur PDF puis glissez la zone pour l'ajuster
                </p>
              </div>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => {
                    if (currentPositioningZones.length > 0) {
                      setFeedback('Une seule zone est autorisée. Déplacez la zone existante.')
                      return
                    }
                    addZone()
                  }}
                  className="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold"
                >
                  Placer zone
                </button>
                <button
                  type="button"
                  onClick={handleSaveZonePlacement}
                  className="px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold"
                >
                  Enregistrer
                </button>
                <button
                  type="button"
                  onClick={clearZones}
                  className="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold"
                >
                  Effacer zone
                </button>
                <button
                  type="button"
                  onClick={closePositioning}
                  className="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold"
                >
                  Fermer
                </button>
              </div>
            </div>

            <div
              className="relative flex-1 bg-gray-100 overflow-auto"
              onPointerMove={handleOverlayPointerMove}
              onPointerUp={() => {
                setDragAction(null)
              }}
              onPointerLeave={() => setDragAction(null)}
            >
              {shouldUseOnlyofficePositioning ? (
                <iframe
                  title={`Lecteur OnlyOffice ${positioningTargetName}`}
                  src={positioningOnlyofficeViewerUrl || undefined}
                  className="absolute inset-0 w-full h-full border-0"
                  onError={() => {
                    setForceNativeViewer(true)
                    setFeedback('OnlyOffice indisponible, bascule automatique vers le lecteur PDF natif.')
                  }}
                />
              ) : (
                <object
                  data={positioningFileUrl}
                  type="application/pdf"
                  className="absolute inset-0 w-full h-full"
                >
                  <div className="absolute inset-0 grid place-items-center bg-white p-4 text-center">
                    <div>
                      <p className="text-sm text-gray-700 mb-2">Le lecteur PDF intégré n'est pas disponible.</p>
                      <a
                        href={positioningFileUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="text-sm font-semibold text-[#2453d6] underline"
                      >
                        Ouvrir le PDF dans un nouvel onglet
                      </a>
                    </div>
                  </div>
                </object>
              )}

              {/* Non-blocking overlay: allows scrolling in the PDF viewer */}
              <div className={`absolute inset-0 pointer-events-none ${dragAction ? 'cursor-grabbing' : ''}`}
                style={dragAction ? { pointerEvents: 'auto' } : undefined}
              >
                {currentPositioningZones.map((zone, index) => (
                  <div
                    key={zone.id}
                    className="absolute pointer-events-auto border-2 border-blue-600 bg-blue-100/75 hover:border-blue-700 text-blue-900 text-xs font-semibold flex flex-col items-center justify-center cursor-move group transition-colors select-none"
                    style={{
                      left: `${zone.x}%`,
                      top: `${zone.y}%`,
                      width: `${zone.width}%`,
                      height: `${zone.height}%`,
                    }}
                    title="Glissez-déposez pour déplacer la zone"
                    onPointerDown={(e) => {
                      e.stopPropagation()
                      e.preventDefault()
                      const rect = e.currentTarget.parentElement!.getBoundingClientRect()
                      setDragAction({
                        zoneId: zone.id,
                        mode: 'move',
                        startX: ((e.clientX - rect.left) / rect.width) * 100,
                        startY: ((e.clientY - rect.top) / rect.height) * 100,
                        origZone: { ...zone },
                      })
                    }}
                  >
                    <button
                      type="button"
                      className="absolute top-0 right-0 h-5 w-5 rounded-full bg-red-600 text-white text-[10px] leading-none opacity-0 group-hover:opacity-100 transition-opacity"
                      title="Supprimer cette zone"
                      onClick={(e) => {
                        e.stopPropagation()
                        deleteZone(zone.id)
                      }}
                    >
                      ✕
                    </button>
                    <span>✎ SIGNATURE {index + 1}</span>
                    <span className="text-[10px] font-medium opacity-80">Glisser pour déplacer</span>
                    <div
                      className="absolute bottom-0 right-0 w-4 h-4 cursor-nwse-resize bg-blue-600 hover:bg-blue-800 rounded-tl-sm opacity-60 hover:opacity-100 transition-opacity"
                      title="Glissez pour redimensionner"
                      onPointerDown={(e) => {
                        e.stopPropagation()
                        e.preventDefault()
                        const rect = e.currentTarget.parentElement!.parentElement!.getBoundingClientRect()
                        setDragAction({
                          zoneId: zone.id,
                          mode: 'resize',
                          startX: ((e.clientX - rect.left) / rect.width) * 100,
                          startY: ((e.clientY - rect.top) / rect.height) * 100,
                          origZone: { ...zone },
                        })
                      }}
                    />
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ── Workflow Creation Modal ────────────────────────────────────── */}
      {showCreateWfModal && (
        <div className="fixed inset-0 z-50 bg-black/30 flex items-start justify-center p-4 md:p-8 overflow-y-auto">

          <div className="w-full max-w-2xl bg-white rounded-2xl shadow-xl border border-gray-100">
            <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
              <h2 className="text-lg font-semibold text-gray-900">Nouveau Parapheur</h2>
              <button onClick={closeCreateWfModal} className="text-gray-400 hover:text-gray-600 text-2xl leading-none">×</button>
            </div>

            <div className="px-6 py-5 space-y-4">
              {/* Step indicators */}
              <div className="flex flex-wrap gap-4 text-xs font-semibold text-gray-400">
                {[{ key: 1, label: 'Général' }, { key: 2, label: 'Attribution' }, { key: 3, label: 'Documents' }, { key: 4, label: 'Notifications' }, { key: 5, label: 'Opération' }].map(step => (
                  <div key={step.key} className={`flex items-center gap-2 ${wfStep === step.key ? 'text-[#2453d6]' : 'text-gray-400'}`}>
                    <span className={`h-8 w-8 rounded-full grid place-items-center text-xs ${wfStep === step.key ? 'bg-[#2453d6] text-white' : 'bg-gray-100 text-gray-500'}`}>{step.key}</span>
                    <span className="text-sm">{step.label}</span>
                  </div>
                ))}
              </div>

              <form onSubmit={handleCreateWf} className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
                {/* Step 1: General */}
                {wfStep === 1 && (
                  <>
                    <div className="space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">Choisir un modèle</label>
                      <select value={wfForm.templateId} onChange={(e) => setWfForm(prev => ({ ...prev, templateId: e.target.value }))} className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#2453d6]">
                        <option value="">Sélectionner un modèle</option>
                        {wfTemplates.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                      </select>
                    </div>
                    <div className="space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">Nom du parapheur *</label>
                      <input type="text" value={wfForm.name} onChange={(e) => setWfForm(prev => ({ ...prev, name: e.target.value }))} className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#2453d6]" />
                    </div>
                    <div className="space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">Description</label>
                      <textarea value={wfForm.description} onChange={(e) => setWfForm(prev => ({ ...prev, description: e.target.value }))} rows={3} className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#2453d6]" />
                    </div>
                  </>
                )}

                {/* Step 2: Attribution */}
                {wfStep === 2 && (
                  <div className="space-y-6">
                    <div className="bg-green-50 border-l-4 border-green-500 rounded-lg p-5 space-y-4">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3"><CheckCircle2 size={24} className="text-green-600" /><h3 className="text-sm font-semibold text-gray-800">Étapes de validation</h3></div>
                        <button type="button" onClick={wfAddValidationStep} className="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition">Ajouter</button>
                      </div>
                      <div className="space-y-3">
                        {wfForm.validationSteps.map((step, idx) => (
                          <div key={step.id} className="flex items-center gap-3 bg-white p-4 rounded-lg border border-gray-200">
                            <span className="h-10 w-10 rounded-full bg-green-600 text-white text-sm font-bold flex items-center justify-center flex-shrink-0">{idx + 1}</span>
                            <select value={step.approverId} onChange={(e) => { const u = [...wfForm.validationSteps]; u[idx].approverId = e.target.value; setWfForm(prev => ({ ...prev, validationSteps: u })) }} className="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                              <option value="">Choisir un validateur</option>
                              {wfSignataires.map(u => <option key={u.id} value={u.id}>{u.fullName} ({u.email})</option>)}
                            </select>
                            <button type="button" onClick={() => wfRemoveValidationStep(step.id)} className="p-2.5 text-red-500 hover:bg-red-50 rounded-lg transition"><Trash2 size={20} /></button>
                          </div>
                        ))}
                      </div>
                    </div>
                    <div className="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-5 space-y-4">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3"><PenTool size={24} className="text-blue-600" /><h3 className="text-sm font-semibold text-gray-800">Étapes de signature</h3></div>
                        <button type="button" onClick={wfAddSignatureStep} className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition">Ajouter</button>
                      </div>
                      <div className="space-y-3">
                        {wfForm.signatureSteps.map((step, idx) => (
                          <div key={step.id} className="flex items-center gap-3 bg-white p-4 rounded-lg border border-gray-200">
                            <span className="h-10 w-10 rounded-full bg-blue-600 text-white text-sm font-bold flex items-center justify-center flex-shrink-0">{idx + 1}</span>
                            <select value={step.signerId} onChange={(e) => { const u = [...wfForm.signatureSteps]; u[idx].signerId = e.target.value; setWfForm(prev => ({ ...prev, signatureSteps: u })) }} className="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                              <option value="">Choisir un signataire</option>
                              {wfSignataires.map(u => <option key={u.id} value={u.id}>{u.fullName} ({u.email})</option>)}
                            </select>
                            <button type="button" onClick={() => wfRemoveSignatureStep(step.id)} className="p-2.5 text-red-500 hover:bg-red-50 rounded-lg transition"><Trash2 size={20} /></button>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                )}

                {/* Step 3: Documents */}
                {wfStep === 3 && (
                  <div className="space-y-4">
                    <div className="bg-red-50 border border-red-100 rounded-lg p-4 space-y-3">
                      <div className="flex items-center gap-2"><FileText size={20} className="text-red-600" /><h3 className="text-sm font-semibold text-gray-800">Documents à signer</h3></div>
                      <div className="space-y-2">
                        <label className="block text-xs font-medium text-gray-600">Source</label>
                        <select value={wfForm.docsToSignSource} onChange={(e) => setWfForm(prev => ({ ...prev, docsToSignSource: e.target.value as 'documents' | 'upload' }))} className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                          <option value="documents">Mes Documents</option>
                          <option value="upload">Téléverser depuis l'ordinateur</option>
                        </select>
                      </div>
                      {wfForm.docsToSignSource === 'documents' ? (
                        <div className="space-y-3">
                          <select className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700" onChange={(e) => { if (e.target.value && !wfForm.docsToSign.includes(e.target.value)) { setWfForm(prev => ({ ...prev, docsToSign: [...prev.docsToSign, e.target.value] })); e.target.value = '' } }}>
                            <option value="">Sélectionner fichiers</option>
                            {documents.map(d => <option key={d.id} value={d.id}>{d.title}</option>)}
                          </select>
                          {wfForm.docsToSign.length > 0 && (
                            <div className="space-y-2">
                              {wfForm.docsToSign
                                .filter((documentId) => typeof documentId === 'string' && documentId.trim().length > 0)
                                .map((documentId, idx) => {
                                  const document = documents.find((item) => item.id === documentId)
                                  const docKey = getDocumentZoneKey(documentId)
                                  const zoneCount = zonesByFileKey[docKey]?.length || 0
                                  const isSaved = Boolean(savedZoneByKey[docKey])
                                  return (
                                    <div key={`${documentId}-${idx}`} className="flex items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2">
                                      <div className="min-w-0">
                                        <p className="truncate text-sm font-medium text-gray-800">{document?.title || `Document ${documentId.slice(0, 8)}`}</p>
                                        <p className="text-xs text-gray-500">{zoneCount} zone(s)</p>
                                      </div>
                                      <div className="flex items-center gap-2">
                                        {isSaved
                                          ? <span className="px-3 py-1.5 rounded-lg bg-green-100 text-green-700 text-xs font-semibold cursor-pointer" onClick={() => { void openPositioningForDocument(documentId) }}>✓ Positionné ({zoneCount})</span>
                                          : <button
                                            type="button"
                                            onClick={() => { void openPositioningForDocument(documentId) }}
                                            className="px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold"
                                          >
                                            Positionner
                                          </button>}
                                        <button
                                          type="button"
                                          onClick={() => setWfForm(prev => ({
                                            ...prev,
                                            docsToSign: prev.docsToSign.filter((id) => id !== documentId),
                                          }))}
                                          className="px-2 py-1.5 rounded-lg text-red-600 hover:bg-red-50 text-xs font-semibold"
                                        >
                                          Supprimer
                                        </button>
                                      </div>
                                    </div>
                                  )
                                })}
                            </div>
                          )}
                          {documents.length > 0 && (
                            <div className="pt-2 space-y-2">
                              <p className="text-xs font-medium text-gray-600">Mes documents disponibles</p>
                              <div className="max-h-44 overflow-auto rounded-lg border border-gray-200 bg-white divide-y divide-gray-100">
                                {documents.map((document) => {
                                  const isSelected = wfForm.docsToSign.includes(document.id)
                                  const docKey = getDocumentZoneKey(document.id)
                                  const zoneCount = zonesByFileKey[docKey]?.length || 0
                                  const isSaved = Boolean(savedZoneByKey[docKey])
                                  return (
                                    <div key={document.id} className="flex items-center justify-between gap-2 px-3 py-2">
                                      <p className="truncate text-xs text-gray-700">{document.title}</p>
                                      <div className="flex items-center gap-2 shrink-0">
                                        <button
                                          type="button"
                                          onClick={() => { if (!isSelected) setWfForm(prev => ({ ...prev, docsToSign: [...prev.docsToSign, document.id] })) }}
                                          className="px-2 py-1 rounded-md bg-gray-100 hover:bg-gray-200 text-[11px] font-semibold text-gray-700 disabled:opacity-50"
                                          disabled={isSelected}
                                        >
                                          {isSelected ? 'Ajouté' : 'Ajouter'}
                                        </button>
                                        {isSaved
                                          ? <span className="px-2 py-1 rounded-md bg-green-100 text-green-700 text-[11px] font-semibold cursor-pointer" onClick={() => { void openPositioningForDocument(document.id) }}>✓ Positionné ({zoneCount})</span>
                                          : <button
                                            type="button"
                                            onClick={() => { void openPositioningForDocument(document.id) }}
                                            className="px-2 py-1 rounded-md bg-violet-600 hover:bg-violet-700 text-[11px] font-semibold text-white"
                                          >
                                            Positionner
                                          </button>}
                                      </div>
                                    </div>
                                  )
                                })}
                              </div>
                            </div>
                          )}
                        </div>
                      ) : (
                        <div className="space-y-3">
                          <button type="button" onClick={() => wfDocsToSignRef.current?.click()} className="w-full flex items-center justify-center gap-2 border-2 border-dashed border-red-300 rounded-lg px-3 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors font-medium cursor-pointer"><Upload size={16} />Sélect. fichiers</button>
                          {wfForm.docsToSignUploaded.length > 0 && (
                            <div className="space-y-2">
                              {wfForm.docsToSignUploaded.map((file, idx) => {
                                const fk = getFileKey(file)
                                const zc = wfZonesByFileKey[fk]?.length || 0
                                return (
                                  <div key={fk} className="flex items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2">
                                    <div className="min-w-0"><p className="truncate text-sm font-medium text-gray-800">{file.name}</p><p className="text-xs text-gray-500">{(file.size / 1024).toFixed(2)} KB · {zc} zone(s)</p></div>
                                    <div className="flex items-center gap-2">
                                      {zc > 0
                                        ? <span className="px-3 py-1.5 rounded-lg bg-green-100 text-green-700 text-xs font-semibold cursor-pointer" onClick={() => openWfPositioning(file)}>✓ Positionné ({zc})</span>
                                        : <button type="button" onClick={() => openWfPositioning(file)} className="px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold">Positionner</button>}
                                      <button type="button" onClick={() => setWfForm(prev => ({ ...prev, docsToSignUploaded: prev.docsToSignUploaded.filter((_, i) => i !== idx) }))} className="px-2 py-1.5 rounded-lg text-red-600 hover:bg-red-50 text-xs font-semibold">Supprimer</button>
                                    </div>
                                  </div>
                                )
                              })}
                            </div>
                          )}
                        </div>
                      )}
                      <div className="text-xs text-gray-500">{wfForm.docsToSign.length + wfForm.docsToSignUploaded.length > 0 ? `${wfForm.docsToSign.length + wfForm.docsToSignUploaded.length} fichier(s) sélectionné(s)` : 'Aucun fichier choisi'}</div>
                    </div>
                    <div className="bg-green-50 border border-green-100 rounded-lg p-4 space-y-3">
                      <div className="flex items-center gap-2"><FileText size={20} className="text-green-600" /><h3 className="text-sm font-semibold text-gray-800">Pièces jointes</h3></div>
                      <div className="space-y-2">
                        <label className="block text-xs font-medium text-gray-600">Source</label>
                        <select value={wfForm.attachedDocsSource} onChange={(e) => setWfForm(prev => ({ ...prev, attachedDocsSource: e.target.value as 'documents' | 'upload' }))} className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                          <option value="documents">Mes Documents</option>
                          <option value="upload">Téléverser depuis l'ordinateur</option>
                        </select>
                      </div>
                      {wfForm.attachedDocsSource === 'documents' ? (
                        <select className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700" onChange={(e) => { if (e.target.value && !wfForm.attachedDocs.includes(e.target.value)) { setWfForm(prev => ({ ...prev, attachedDocs: [...prev.attachedDocs, e.target.value] })); e.target.value = '' } }}>
                          <option value="">Sélectionner fichiers</option>
                          {documents.map(d => <option key={d.id} value={d.id}>{d.title}</option>)}
                        </select>
                      ) : (
                        <div className="space-y-3">
                          <button type="button" onClick={() => wfAttachedDocsRef.current?.click()} className="w-full flex items-center justify-center gap-2 border-2 border-dashed border-green-300 rounded-lg px-3 py-3 text-sm text-green-600 hover:bg-green-50 transition-colors font-medium cursor-pointer"><Upload size={16} />Sélect. fichiers</button>
                          {wfForm.attachedDocsUploaded.length > 0 && (
                            <div className="space-y-2">
                              {wfForm.attachedDocsUploaded.map((file, idx) => (
                                <div key={`${file.name}-${idx}`} className="flex items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2">
                                  <div className="min-w-0"><p className="truncate text-sm font-medium text-gray-800">{file.name}</p><p className="text-xs text-gray-500">{(file.size / 1024).toFixed(2)} KB</p></div>
                                  <button type="button" onClick={() => setWfForm(prev => ({ ...prev, attachedDocsUploaded: prev.attachedDocsUploaded.filter((_, i) => i !== idx) }))} className="px-2 py-1.5 rounded-lg text-red-600 hover:bg-red-50 text-xs font-semibold">Supprimer</button>
                                </div>
                              ))}
                            </div>
                          )}
                        </div>
                      )}
                      <div className="text-xs text-gray-500">{wfForm.attachedDocs.length + wfForm.attachedDocsUploaded.length > 0 ? `${wfForm.attachedDocs.length + wfForm.attachedDocsUploaded.length} fichier(s) sélectionné(s)` : 'Aucun fichier choisi'}</div>
                    </div>
                  </div>
                )}

                {/* Step 4: Notifications */}
                {wfStep === 4 && (
                  <div className="space-y-4">
                    <label className="flex items-center gap-3 cursor-pointer select-none">
                      <div onClick={() => setWfForm(prev => ({ ...prev, notifyEmail: !prev.notifyEmail }))} className={`relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors cursor-pointer ${wfForm.notifyEmail ? 'bg-[#2453d6]' : 'bg-gray-300'}`}>
                        <span className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform ${wfForm.notifyEmail ? 'translate-x-6' : 'translate-x-1'}`} />
                      </div>
                      <span className="text-sm font-semibold text-gray-800">Activer les notifications par e-mail</span>
                    </label>
                    {wfForm.notifyEmail && (
                      <div className="rounded-xl border border-amber-200 bg-amber-50 p-5 space-y-5">
                        <div className="space-y-1.5">
                          <label className="block text-sm font-semibold text-gray-700">Emails de notification</label>
                          <textarea rows={2} value={wfForm.notifyEmails} onChange={(e) => setWfForm(prev => ({ ...prev, notifyEmails: e.target.value }))} placeholder="Séparer les emails par des virgules" className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white" />
                        </div>
                        <div className="space-y-1.5">
                          <label className="block text-sm font-semibold text-gray-700">Copie cachée (Cc)</label>
                          <textarea rows={2} value={wfForm.notifyCc} onChange={(e) => setWfForm(prev => ({ ...prev, notifyCc: e.target.value }))} placeholder="Emails en copie cachée (optionnel)" className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white" />
                        </div>
                        <div className="space-y-2">
                          <p className="text-sm font-semibold text-gray-700">Étapes déclenchant une notification</p>
                          {([
                            { key: 'onValidationStep', label: 'À chaque étape de validation' },
                            { key: 'onSignatureStep', label: 'À chaque étape de signature' },
                            { key: 'onApproved', label: 'Workflow approuvé' },
                            { key: 'onRejected', label: 'Workflow rejeté' },
                            { key: 'onCompleted', label: 'Document signé disponible' },
                          ] as { key: keyof typeof wfForm.notifyStages; label: string }[]).map(({ key, label }) => (
                            <label key={key} className="flex items-center gap-2.5 text-sm text-gray-700 cursor-pointer select-none">
                              <input type="checkbox" checked={wfForm.notifyStages[key]} onChange={(e) => setWfForm(prev => ({ ...prev, notifyStages: { ...prev.notifyStages, [key]: e.target.checked } }))} className="h-4 w-4 rounded border-gray-300 accent-[#2453d6]" />
                              {label}
                            </label>
                          ))}
                        </div>
                        <div className="pt-3 border-t border-amber-200">
                          <label className="flex items-center gap-2.5 text-sm font-semibold text-gray-700 cursor-pointer select-none">
                            <input type="checkbox" checked={wfForm.sendDownloadLink} onChange={(e) => setWfForm(prev => ({ ...prev, sendDownloadLink: e.target.checked }))} className="h-4 w-4 rounded border-gray-300 accent-[#2453d6]" />
                            Envoyer un lien de téléchargement du document signé
                          </label>
                        </div>
                      </div>
                    )}
                  </div>
                )}

                {/* Step 5: Summary */}
                {wfStep === 5 && (
                  <div className="space-y-5">
                    <div className="space-y-3 text-sm text-gray-700">
                      <div><p className="font-semibold text-gray-800">Nom du parapheur</p><p>{wfForm.name || '—'}</p></div>
                      <div><p className="font-semibold text-gray-800">Description</p><p>{wfForm.description || '—'}</p></div>
                      <div className="bg-blue-50 border border-blue-100 rounded-lg p-4 space-y-3">
                        <div>
                          <p className="font-semibold text-gray-800 flex items-center gap-2"><CheckCircle2 size={18} className="text-green-600" /> Étapes de validation ({wfForm.validationSteps.filter(s => s.approverId).length})</p>
                          <ul className="list-disc list-inside text-gray-600 ml-6">{wfForm.validationSteps.filter(s => s.approverId).map((s, i) => <li key={s.id}>Validateur {i + 1}: {wfSignataires.find(u => u.id === s.approverId)?.fullName || s.approverId}</li>)}</ul>
                        </div>
                        <div>
                          <p className="font-semibold text-gray-800 flex items-center gap-2"><PenTool size={18} className="text-blue-600" /> Étapes de signature ({wfForm.signatureSteps.filter(s => s.signerId).length})</p>
                          <ul className="list-disc list-inside text-gray-600 ml-6">{wfForm.signatureSteps.filter(s => s.signerId).map((s, i) => <li key={s.id}>Signataire {i + 1}: {wfSignataires.find(u => u.id === s.signerId)?.fullName || s.signerId}</li>)}</ul>
                        </div>
                      </div>
                    </div>
                    {wfError && <div className="p-3 bg-red-100 text-red-800 rounded-lg text-sm font-medium">{wfError}</div>}
                    <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
                      <p className="text-sm font-semibold text-gray-800 mb-4">Actions disponibles</p>
                      <div className="flex flex-wrap gap-3">
                        <button type="submit" disabled={wfSubmitting} className="px-6 py-2.5 bg-green-600 hover:bg-green-700 disabled:opacity-60 disabled:cursor-wait text-white text-sm font-semibold rounded-lg transition flex items-center gap-2">{wfSubmitting ? <><span className="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full" /> Démarrage…</> : <><PlayCircle size={18} /> Démarrer</>}</button>
                      </div>
                    </div>
                  </div>
                )}

                <div className="flex items-center justify-between pt-3">
                  <button type="button" onClick={wfPrevStep} disabled={wfStep === 1} className="px-4 py-2 rounded-lg bg-gray-200 disabled:opacity-50 text-gray-700 text-sm font-semibold">Précédent</button>
                  {wfStep < WF_MAX_STEP ? (
                    <button type="button" onClick={wfNextStep} className="px-4 py-2 rounded-lg bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold">Suivant</button>
                  ) : null}
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* ── Workflow Positioning Modal ─────────────────────────────────── */}
      {wfPositioningFile && wfPositioningFileUrl && (
        <div className="fixed inset-0 bg-black/40 z-[60] flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-lg border border-gray-200 w-full max-w-6xl h-[88vh] overflow-hidden flex flex-col">
            <div className="px-4 py-3 border-b border-gray-200 flex items-center justify-between shrink-0">
              <div>
                <p className="text-lg font-semibold text-gray-800 flex items-center gap-2">
                  <MapPin size={18} className="text-violet-600" />
                  Zones de signature — {wfPositioningFile.name}
                </p>
                <p className="text-sm text-gray-500">{wfCurrentZones.length} zone(s) · Cliquez pour ajouter, glissez pour déplacer, coin ↘ pour redimensionner</p>
              </div>
              <div className="flex items-center gap-2">
                <button type="button" onClick={() => wfAddZone()} className="px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold">+ Zone</button>
                <button type="button" onClick={wfClearZones} className="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold">Effacer</button>
                <button type="button" disabled={wfCurrentZones.length === 0} onClick={closeWfPositioning} className="px-3 py-1.5 rounded-lg bg-green-600 hover:bg-green-700 disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs font-semibold">Enregistrer</button>
                <button type="button" onClick={() => { wfClearZones(); closeWfPositioning(); }} className="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold">Fermer</button>
              </div>
            </div>
            <div
              className="relative flex-1 bg-gray-100 overflow-auto"
              onPointerMove={wfHandlePointerMove}
              onPointerUp={wfStopDrag}
              onPointerLeave={wfStopDrag}
            >
              {(docViewer === 'onlyoffice' && !wfForceNativeViewer && Boolean(wfPositioningFileUrl) && Boolean(onlyofficeBaseUrl)) ? (
                <iframe
                  title={`Lecteur OnlyOffice ${wfPositioningFile.name}`}
                  src={wfPositioningFileUrl && onlyofficeBaseUrl
                    ? `${onlyofficeBaseUrl}/web-apps/apps/documenteditor/main/index.html?fileUrl=${encodeURIComponent(wfPositioningFileUrl)}`
                    : undefined}
                  className="absolute inset-0 w-full h-full border-0"
                  onError={() => {
                    setWfForceNativeViewer(true)
                    setFeedback('OnlyOffice indisponible, bascule automatique vers le lecteur PDF natif.')
                  }}
                />
              ) : (
                <object data={wfPositioningFileUrl} type="application/pdf" className="absolute inset-0 w-full h-full">
                  <div className="absolute inset-0 grid place-items-center bg-white p-4 text-center">
                    <div>
                      <p className="text-sm text-gray-700 mb-2">Le lecteur PDF intégré n'est pas disponible.</p>
                      <a href={wfPositioningFileUrl} target="_blank" rel="noreferrer" className="text-sm font-semibold text-violet-600 underline">Ouvrir le PDF dans un nouvel onglet</a>
                    </div>
                  </div>
                </object>
              )}

              {/* Non-blocking overlay: pointer-events-none lets scroll pass through to the PDF */}
              <div className={`absolute inset-0 pointer-events-none ${wfDragAction ? 'cursor-grabbing' : ''}`}
                style={wfDragAction ? { pointerEvents: 'auto' } : undefined}
                onPointerMove={wfDragAction ? wfHandlePointerMove : undefined}
                onPointerUp={wfDragAction ? wfStopDrag : undefined}
              >
                {wfCurrentZones.map((zone, index) => (
                  <div
                    key={zone.id}
                    className="absolute pointer-events-auto border-2 border-blue-600 bg-blue-100/75 hover:border-blue-700 text-blue-900 text-xs font-semibold flex flex-col items-center justify-center cursor-move group transition-colors select-none"
                    style={{ left: `${zone.x}%`, top: `${zone.y}%`, width: `${zone.width}%`, height: `${zone.height}%` }}
                    title="Glissez pour déplacer"
                    onPointerDown={(ev) => { ev.stopPropagation(); ev.preventDefault(); const rect = ev.currentTarget.parentElement!.getBoundingClientRect(); setWfDragAction({ zoneId: zone.id, mode: 'move', startX: ((ev.clientX - rect.left) / rect.width) * 100, startY: ((ev.clientY - rect.top) / rect.height) * 100, origZone: { ...zone } }) }}
                  >
                    {/* Delete button */}
                    <button type="button" className="absolute -top-2 -right-2 h-5 w-5 rounded-full bg-red-600 text-white text-[10px] leading-none opacity-0 group-hover:opacity-100 transition-opacity z-10" title="Supprimer" onClick={(ev) => { ev.stopPropagation(); wfDeleteZone(zone.id) }}>✕</button>
                    <span>✎ SIGNATURE {index + 1}</span>
                    <span className="text-[10px] font-medium opacity-80">Glisser pour déplacer</span>
                    {/* Resize handle bottom-right */}
                    <div
                      className="absolute bottom-0 right-0 w-4 h-4 cursor-nwse-resize bg-blue-600 hover:bg-blue-800 rounded-tl-sm opacity-60 hover:opacity-100 transition-opacity"
                      title="Glissez pour redimensionner"
                      onPointerDown={(ev) => { ev.stopPropagation(); ev.preventDefault(); const rect = ev.currentTarget.parentElement!.parentElement!.getBoundingClientRect(); setWfDragAction({ zoneId: zone.id, mode: 'resize', startX: ((ev.clientX - rect.left) / rect.width) * 100, startY: ((ev.clientY - rect.top) / rect.height) * 100, origZone: { ...zone } }) }}
                    />
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export default Signatures
