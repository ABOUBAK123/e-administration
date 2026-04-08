import React, { useEffect, useState } from 'react'
import { PlusCircle, PlayCircle, Trash2, CheckCircle2, FileText, PenTool, Upload, Eye, Copy, Settings } from 'lucide-react'
import { fetchDocuments, uploadDocumentFile } from '../services/documents'
import { fetchAppSetting } from '../services/administration'
import { fetchSignataires, AppUserRecord } from '../services/users'
import { useAuthStore } from '../store/authStore'
import {
  fetchWorkflows,
  fetchWorkflowTemplates,
  createWorkflow,
  createWorkflowTemplate,
  advanceWorkflow,
  rejectWorkflow,
  deleteWorkflow,
} from '../services/workflows'
import { WorkflowItem, WorkflowExecution, WorkflowTemplateItem } from '../types/workflow'
import { DocumentItem } from '../types/document'

type SignatureZone = {
  id: string
  x: number
  y: number
  width: number
  height: number
}

function Workflows() {
  const [workflows, setWorkflows] = useState<WorkflowItem[]>([])
  const [documents, setDocuments] = useState<DocumentItem[]>([])
  const [selectedWorkflowId, setSelectedWorkflowId] = useState('')
  const [selectedDocumentId, setSelectedDocumentId] = useState('')
  const [executions, setExecutions] = useState<WorkflowExecution[]>([])
  const [showNewWorkflowModal, setShowNewWorkflowModal] = useState(false)
  const [viewingWorkflow, setViewingWorkflow] = useState<WorkflowItem | null>(null)
  const [creationMode, setCreationMode] = useState<'workflow' | 'template'>('workflow')
  const [newWorkflowStep, setNewWorkflowStep] = useState(1)
  const [workflowTemplates, setWorkflowTemplates] = useState<WorkflowTemplateItem[]>([])
  const [newWorkflowForm, setNewWorkflowForm] = useState({
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
  const [feedback, setFeedback] = useState<string | null>(null)
  const [signataires, setSignataires] = useState<AppUserRecord[]>([])
  const [uploadSuccessPopup, setUploadSuccessPopup] = useState<string | null>(null)
  const [uploadErrorPopup, setUploadErrorPopup] = useState<string | null>(null)
  const [deleteConfirmation, setDeleteConfirmation] = useState<{ show: boolean; id?: string }>({ show: false })
  const [positioningTargetKey, setPositioningTargetKey] = useState<string | null>(null)
  const [positioningTargetName, setPositioningTargetName] = useState('')
  const [positioningIsObjectUrl, setPositioningIsObjectUrl] = useState(false)
  const [positioningFileUrl, setPositioningFileUrl] = useState<string | null>(null)
  const [zonesByFileKey, setZonesByFileKey] = useState<Record<string, SignatureZone[]>>({})
  const [savedZoneByKey, setSavedZoneByKey] = useState<Record<string, boolean>>({})
  const [showTileSettings, setShowTileSettings] = useState(false)
  const [workflowSearch, setWorkflowSearch] = useState('')
  const [onlyofficeBaseUrl, setOnlyofficeBaseUrl] = useState('')
  const [docViewer, setDocViewer] = useState<'onlyoffice' | 'native'>('onlyoffice')
  const [forceNativeViewer, setForceNativeViewer] = useState(false)
  const [dragAction, setDragAction] = useState<{ zoneId: string; mode: 'move' | 'resize'; startX: number; startY: number; origZone: SignatureZone } | null>(null)
  const currentUser = useAuthStore((state) => state.user)
  const [visibleTiles, setVisibleTiles] = useState({
    toValidate: true,
    drafts: true,
    started: true,
    finished: true,
    stopped: true,
    archived: true,
  })

  const getFileKey = (file: File) => `${file.name}-${file.size}-${file.lastModified}`

  const normalizeStatus = (status: string) => status.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
  const currentPositioningZones = positioningTargetKey ? zonesByFileKey[positioningTargetKey] || [] : []
  const maxCreationStep = creationMode === 'template' ? 4 : 5
  const notificationsStep = creationMode === 'template' ? 3 : 4
  const summaryStep = maxCreationStep
  const isViewMode = creationMode === 'workflow' && viewingWorkflow !== null
  const modalNotificationsStep = isViewMode ? 0 : notificationsStep
  const modalSummaryStep = isViewMode ? 3 : summaryStep
  const modalMaxStep = isViewMode ? 3 : maxCreationStep
  const onlyofficeViewerUrl = positioningFileUrl && onlyofficeBaseUrl
    ? `${onlyofficeBaseUrl}/web-apps/apps/documenteditor/main/index.html?fileUrl=${encodeURIComponent(positioningFileUrl)}`
    : null
  const shouldUseOnlyoffice = docViewer === 'onlyoffice' && !forceNativeViewer && Boolean(onlyofficeViewerUrl)

  const tileStats = {
    toValidate: executions.filter((execution) => {
      const status = normalizeStatus(execution.status)
      return status.includes('pending') || status.includes('en_attente') || status.includes('a_signer') || status.includes('a_valider')
    }).length,
    drafts: workflows.length,
    started: executions.filter((execution) => {
      const status = normalizeStatus(execution.status)
      return status.includes('in_progress') || status.includes('started') || status.includes('demarre')
    }).length,
    finished: executions.filter((execution) => {
      const status = normalizeStatus(execution.status)
      return status.includes('complete') || status.includes('approved') || status.includes('termine') || status.includes('valide')
    }).length,
    stopped: executions.filter((execution) => {
      const status = normalizeStatus(execution.status)
      return status.includes('reject') || status.includes('arrete') || status.includes('stopped')
    }).length,
  }
  const archivedCount = 0
  const allTilesVisible = Object.values(visibleTiles).every(Boolean)
  const normalizedWorkflowSearch = workflowSearch.trim().toLowerCase()

  const getOwnerLabel = (workflow: WorkflowItem) => {
    if (workflow.creator) {
      return workflow.creator.fullName || workflow.creator.username || workflow.creator.email || 'Inconnu'
    }

    if (!workflow.createdBy?.trim()) {
      return 'Inconnu'
    }

    if (currentUser && workflow.createdBy === currentUser.id) {
      return currentUser.fullName || currentUser.username || currentUser.email
    }

    return 'Inconnu'
  }

  const getWorkflowDisplayStatus = (workflowId: string): 'draft' | 'started' | 'finished' => {
    const workflowExecutions = executions.filter((execution) => execution.workflowId === workflowId)

    if (workflowExecutions.length === 0) {
      return 'draft'
    }

    const hasInProgress = workflowExecutions.some((execution) => {
      const status = normalizeStatus(execution.status)
      return status.includes('in_progress') || status.includes('started') || status.includes('demarre')
    })

    if (hasInProgress) {
      return 'started'
    }

    const isFinished = workflowExecutions.every((execution) => {
      const status = normalizeStatus(execution.status)
      return status.includes('complete') || status.includes('approved') || status.includes('termine') || status.includes('valide')
    })

    return isFinished ? 'finished' : 'started'
  }

  const getUserDisplayName = (userId: string) => {
    const user = signataires.find((item) => item.id === userId)
    if (!user) return userId
    return user.fullName || user.username || user.email || userId
  }

  const mapWorkflowStepsToForm = (workflow: WorkflowItem) => {
    const validationSteps = workflow.steps
      .filter((step) => !step.requiresSignature && !step.name.toLowerCase().includes('signature'))
      .map((step, index) => ({ id: index + 1, approverId: step.approverId || '' }))

    const signatureSteps = workflow.steps
      .filter((step) => step.requiresSignature || step.name.toLowerCase().includes('signature'))
      .map((step, index) => ({ id: index + 1, signerId: step.approverId || '' }))

    return {
      validationSteps: validationSteps.length > 0 ? validationSteps : [{ id: 1, approverId: '' }],
      signatureSteps: signatureSteps.length > 0 ? signatureSteps : [{ id: 1, signerId: '' }],
    }
  }

  const getWorkflowModalProgress = (workflow: WorkflowItem) => {
    const totalSteps = workflow.steps.length || 1
    const status = getWorkflowDisplayStatus(workflow.id)
    const workflowExecutions = executions.filter((execution) => execution.workflowId === workflow.id)

    const activeExecution = workflowExecutions.find((execution) => {
      const normalized = normalizeStatus(execution.status)
      return normalized.includes('in_progress') || normalized.includes('started') || normalized.includes('demarre') || normalized.includes('pending')
    }) || workflowExecutions[0]

    if (status === 'draft') {
      return { statusLabel: 'Brouillon', progressPercent: 0, stepLabel: `0/${totalSteps}` }
    }

    if (status === 'finished') {
      return { statusLabel: 'Terminé', progressPercent: 100, stepLabel: `${totalSteps}/${totalSteps}` }
    }

    const rawStep = activeExecution?.currentStep || 1
    const currentStep = Math.max(1, Math.min(rawStep, totalSteps))
    const progressPercent = Math.max(0, Math.min(Math.round((currentStep / totalSteps) * 100), 99))
    return { statusLabel: 'Démarré', progressPercent, stepLabel: `${currentStep}/${totalSteps}` }
  }

  const openViewWorkflowModal = (workflow: WorkflowItem) => {
    const mapped = mapWorkflowStepsToForm(workflow)
    const sanitizedDocsToSign = (workflow.docsToSign || []).filter((id): id is string => typeof id === 'string' && id.trim().length > 0)
    const sanitizedAttachedDocs = (workflow.attachedDocs || []).filter((id): id is string => typeof id === 'string' && id.trim().length > 0)
    setCreationMode('workflow')
    setViewingWorkflow(workflow)
    setNewWorkflowStep(1)
    setNewWorkflowForm((prev) => ({
      ...prev,
      templateId: '',
      name: workflow.name || '',
      description: workflow.description || '',
      validationSteps: mapped.validationSteps,
      signatureSteps: mapped.signatureSteps,
      docsToSign: sanitizedDocsToSign,
      attachedDocs: sanitizedAttachedDocs,
      docsToSignUploaded: [],
      attachedDocsUploaded: [],
      docsToSignSource: 'documents',
      attachedDocsSource: 'documents',
    }))
    setShowNewWorkflowModal(true)
  }

  const filteredWorkflows = workflows.filter((workflow) => {
    if (!normalizedWorkflowSearch) {
      return true
    }

    const owner = getOwnerLabel(workflow)
    const displayStatus = getWorkflowDisplayStatus(workflow.id)
    const status = displayStatus === 'draft' ? 'brouillon' : displayStatus === 'started' ? 'démarré' : 'terminé'
    return [workflow.name, owner, status].join(' ').toLowerCase().includes(normalizedWorkflowSearch)
  })

  const tileOptions = [
    { key: 'toValidate', label: 'À signer / valider' },
    { key: 'drafts', label: 'Brouillons' },
    { key: 'started', label: 'Démarrés' },
    { key: 'finished', label: 'Terminés' },
    { key: 'stopped', label: 'Arrêtés' },
    { key: 'archived', label: 'Archivés' },
  ] as const

  useEffect(() => {
    const savedTileVisibility = localStorage.getItem('workflow_tile_visibility')
    if (savedTileVisibility) {
      try {
        const parsed = JSON.parse(savedTileVisibility)
        setVisibleTiles((prev) => ({ ...prev, ...parsed }))
      } catch {
        // Ignore invalid local storage payloads.
      }
    }

    const load = async () => {
      try {
        const templates = await fetchWorkflowTemplates()
        setWorkflowTemplates(templates)
      } catch {
        setWorkflowTemplates([])
      }

      try {
        const wf = await fetchWorkflows()
        setWorkflows(wf)
        if (wf.length > 0 && !selectedWorkflowId) {
          setSelectedWorkflowId(wf[0].id)
        }
      } catch (error) {
        setFeedback('Impossible de charger les workflows')
      }

      try {
        const docs = await fetchDocuments()
        setDocuments(docs)
        if (docs.length > 0 && !selectedDocumentId) {
          setSelectedDocumentId(docs[0].id)
        }
      } catch (error) {
        setFeedback('Impossible de charger les documents')
      }
    }
    load()
  }, [])

  useEffect(() => {
    fetchSignataires()
      .then(setSignataires)
      .catch(() => setSignataires([]))
  }, [])

  useEffect(() => {
    const loadOnlyOfficeUrl = async () => {
      try {
        const [urlSetting, viewerSetting] = await Promise.all([
          fetchAppSetting('oo_url'),
          fetchAppSetting('doc_viewer'),
        ])
        const fromSettings = (urlSetting?.value || '').trim().replace(/\/$/, '')
        setDocViewer(viewerSetting?.value === 'native' ? 'native' : 'onlyoffice')
        if (fromSettings) {
          setOnlyofficeBaseUrl(fromSettings)
          return
        }
      } catch {
        // Fallback below.
      }

      const fallback = typeof window !== 'undefined' ? (localStorage.getItem('oo_url') || '').trim().replace(/\/$/, '') : ''
      setOnlyofficeBaseUrl(fallback)
    }

    void loadOnlyOfficeUrl()
  }, [])

  useEffect(() => {
    localStorage.setItem('workflow_tile_visibility', JSON.stringify(visibleTiles))
  }, [visibleTiles])

  useEffect(() => {
    if (!positioningTargetKey || !shouldUseOnlyoffice) return
    const timer = window.setTimeout(() => {
      setForceNativeViewer(true)
      setFeedback('OnlyOffice indisponible, bascule automatique vers le lecteur PDF natif.')
    }, 2500)

    return () => {
      window.clearTimeout(timer)
    }
  }, [positioningTargetKey, shouldUseOnlyoffice])

  const closeNewWorkflowModal = () => {
    setShowNewWorkflowModal(false)
    setViewingWorkflow(null)
    setCreationMode('workflow')
    setNewWorkflowStep(1)
    closePositioning()
  }

  const toggleTile = (key: keyof typeof visibleTiles) => {
    setVisibleTiles((prev) => ({ ...prev, [key]: !prev[key] }))
  }

  const toggleAllTiles = (checked: boolean) => {
    setVisibleTiles({
      toValidate: checked,
      drafts: checked,
      started: checked,
      finished: checked,
      stopped: checked,
      archived: checked,
    })
  }

  const showUploadSuccessPopup = (message: string) => {
    setUploadSuccessPopup(message)
    window.setTimeout(() => {
      setUploadSuccessPopup(null)
    }, 2200)
  }

  const showUploadErrorPopup = (message: string) => {
    setUploadErrorPopup(message)
    window.setTimeout(() => {
      setUploadErrorPopup(null)
    }, 3000)
  }

  const openPositioning = (file: File) => {
    if (!file.type.includes('pdf') && !file.name.toLowerCase().endsWith('.pdf')) {
      setFeedback('Le positionnement est disponible uniquement pour les fichiers PDF')
      return
    }
    if (positioningIsObjectUrl && positioningFileUrl) {
      URL.revokeObjectURL(positioningFileUrl)
    }
    const localUrl = URL.createObjectURL(file)
    setForceNativeViewer(false)
    setPositioningTargetKey(getFileKey(file))
    setPositioningTargetName(file.name)
    setPositioningFileUrl(localUrl)
    setPositioningIsObjectUrl(true)
    setSavedZoneByKey((prev) => ({ ...prev, [getFileKey(file)]: Boolean((zonesByFileKey[getFileKey(file)] || []).length) }))
  }

  const closePositioning = () => {
    if (positioningIsObjectUrl && positioningFileUrl) {
      URL.revokeObjectURL(positioningFileUrl)
    }
    setPositioningTargetKey(null)
    setPositioningTargetName('')
    setPositioningFileUrl(null)
    setPositioningIsObjectUrl(false)
    setForceNativeViewer(false)
  }

  const addZone = (x = 10, y = 15) => {
    if (!positioningTargetKey) return
    setZonesByFileKey((prev) => {
      const currentZones = prev[positioningTargetKey] || []
      const nextZone: SignatureZone = {
        id: `zone-${Date.now()}-${currentZones.length + 1}`,
        x,
        y,
        width: 28,
        height: 12,
      }
      return {
        ...prev,
        [positioningTargetKey]: [...currentZones, nextZone],
      }
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

  const handleZonePointerMove = (event: React.PointerEvent<HTMLDivElement>) => {
    if (!dragAction || !positioningTargetKey) return
    const rect = event.currentTarget.getBoundingClientRect()
    const pctX = ((event.clientX - rect.left) / rect.width) * 100
    const pctY = ((event.clientY - rect.top) / rect.height) * 100
    const dx = pctX - dragAction.startX
    const dy = pctY - dragAction.startY
    const original = dragAction.origZone

    setZonesByFileKey((prev) => ({
      ...prev,
      [positioningTargetKey]: (prev[positioningTargetKey] || []).map((zone) => {
        if (zone.id !== dragAction.zoneId) return zone
        if (dragAction.mode === 'move') {
          return {
            ...zone,
            x: Math.max(0, Math.min(100 - zone.width, original.x + dx)),
            y: Math.max(0, Math.min(100 - zone.height, original.y + dy)),
          }
        }
        const newWidth = Math.max(8, Math.min(100 - original.x, original.width + dx))
        const newHeight = Math.max(4, Math.min(100 - original.y, original.height + dy))
        return { ...zone, width: newWidth, height: newHeight }
      }),
    }))
    setSavedZoneByKey((prev) => ({ ...prev, [positioningTargetKey]: false }))
  }

  const stopZoneDrag = () => {
    setDragAction(null)
  }

  const savePositioningAndClose = () => {
    if (!positioningTargetKey) return
    if (currentPositioningZones.length === 0) {
      setFeedback('Veuillez placer au moins une zone de signature avant d\'enregistrer.')
      return
    }
    setSavedZoneByKey((prev) => ({ ...prev, [positioningTargetKey]: true }))
    setFeedback('Position de la zone enregistrée.')
    closePositioning()
  }

  const handleCreate = async (event: React.FormEvent) => {
    event.preventDefault()
    if (!newWorkflowForm.name) {
      setFeedback('Nom du workflow requis')
      return
    }
    const validationStepsFiltered = newWorkflowForm.validationSteps.filter(s => s.approverId.trim())
    if (validationStepsFiltered.length === 0) {
      setFeedback('Au moins un validateur est requis')
      return
    }
    try {
      if (creationMode === 'template') {
        const createdTemplate = await createWorkflowTemplate({
          name: newWorkflowForm.name.trim(),
          description: newWorkflowForm.description.trim(),
          validationSteps: validationStepsFiltered.map((step, index) => ({
            id: index + 1,
            approverId: step.approverId,
          })),
          signatureSteps: newWorkflowForm.signatureSteps
            .filter((step) => step.signerId.trim())
            .map((step, index) => ({ id: index + 1, signerId: step.signerId })),
          notificationConfig: {
            notifyEmail: newWorkflowForm.notifyEmail,
            emails: newWorkflowForm.notifyEmails,
            cc: newWorkflowForm.notifyCc,
            stages: newWorkflowForm.notifyStages,
            sendDownloadLink: newWorkflowForm.sendDownloadLink,
          },
        })

        setWorkflowTemplates((prev) => [createdTemplate, ...prev])
        resetWorkflowForm()
        closeNewWorkflowModal()
        setFeedback('Modèle de workflow créé')
        return
      }

      const uploadFilesWithErrorHandling = async (files: File[], sectionLabel: string) => {
        const uploaded: DocumentItem[] = []
        for (const file of files) {
          try {
            const uploadedDocument = await uploadDocumentFile(file)
            uploaded.push(uploadedDocument)
          } catch {
            showUploadErrorPopup(`Échec du téléversement (${sectionLabel}) : ${file.name}`)
            throw new Error(`UPLOAD_FAILED:${file.name}`)
          }
        }
        return uploaded
      }

      const docsToSignFromDocuments = newWorkflowForm.docsToSign.filter((id) => typeof id === 'string' && id.trim().length > 0)
      const attachedDocsFromDocuments = newWorkflowForm.attachedDocs.filter((id) => typeof id === 'string' && id.trim().length > 0)
      const docsToSignToUpload = newWorkflowForm.docsToSignUploaded
      const attachedDocsToUpload = newWorkflowForm.attachedDocsUploaded

      if (docsToSignFromDocuments.length + docsToSignToUpload.length === 0) {
        setFeedback('Ajoutez au moins un document à signer')
        return
      }

      const [uploadedDocsToSign, uploadedAttachedDocs] = await Promise.all([
        uploadFilesWithErrorHandling(docsToSignToUpload, 'Documents à signer'),
        uploadFilesWithErrorHandling(attachedDocsToUpload, 'Pièces jointes'),
      ])
      const totalUploadedFiles = uploadedDocsToSign.length + uploadedAttachedDocs.length

      const docsToSignIds = [
        ...docsToSignFromDocuments,
        ...uploadedDocsToSign.map((document) => document.id),
      ]
      const attachedDocsIds = [
        ...attachedDocsFromDocuments,
        ...uploadedAttachedDocs.map((document) => document.id),
      ]

      const steps = [
        ...validationStepsFiltered.map((s, i) => ({ name: `Validation ${i + 1}`, approverId: s.approverId, order: i + 1 })),
        ...newWorkflowForm.signatureSteps.filter(s => s.signerId.trim()).map((s, i) => ({ name: `Signature ${i + 1}`, approverId: s.signerId, order: validationStepsFiltered.length + i + 1 })),
      ]
      const uploadedSignatureFiles = docsToSignToUpload.map((file) => {
        const fileKey = getFileKey(file)
        return {
          fileName: file.name,
          fileSize: file.size,
          fileType: file.type || 'application/octet-stream',
          zones: (zonesByFileKey[fileKey] || []).map((zone) => ({
            x: zone.x,
            y: zone.y,
            width: zone.width,
            height: zone.height,
          })),
        }
      })
      const allSignatureFiles = [...uploadedSignatureFiles]
      const created = await createWorkflow({
        name: newWorkflowForm.name,
        description: newWorkflowForm.description,
        steps,
        docsToSign: docsToSignIds,
        attachedDocs: attachedDocsIds,
        uploadedSignatureFiles: allSignatureFiles,
      })
      setWorkflows((prev) => [
        ...prev,
        {
          ...created,
          createdBy: created.createdBy || currentUser?.id || currentUser?.fullName || currentUser?.username || '',
        },
      ])
      if (uploadedDocsToSign.length > 0 || uploadedAttachedDocs.length > 0) {
        setDocuments((prev) => [...uploadedDocsToSign, ...uploadedAttachedDocs, ...prev])
      }
      resetWorkflowForm()
      setZonesByFileKey({})
      closeNewWorkflowModal()
      setSelectedWorkflowId(created.id)
      setFeedback('Workflow créé')
      if (totalUploadedFiles > 0) {
        showUploadSuccessPopup(`Téléversement confirmé: ${totalUploadedFiles} fichier(s) enregistré(s)`)
      }
    } catch (error) {
      if (error instanceof Error && error.message.startsWith('UPLOAD_FAILED:')) {
        return
      }
      setFeedback('Échec de la création du workflow')
    }
  }

  useEffect(() => {
    return () => {
      if (positioningIsObjectUrl && positioningFileUrl) {
        URL.revokeObjectURL(positioningFileUrl)
      }
    }
  }, [positioningFileUrl, positioningIsObjectUrl])

  useEffect(() => {
    if (!newWorkflowForm.templateId) return
    const template = workflowTemplates.find((item) => item.id === newWorkflowForm.templateId)
    if (!template) return

    setNewWorkflowForm((prev) => ({
      ...prev,
      name: template.name,
      description: template.description || '',
      validationSteps:
        template.validationSteps.length > 0
          ? template.validationSteps.map((step, index) => ({ id: index + 1, approverId: step.approverId || '' }))
          : [{ id: 1, approverId: '' }],
      signatureSteps:
        template.signatureSteps.length > 0
          ? template.signatureSteps.map((step, index) => ({ id: index + 1, signerId: step.signerId || '' }))
          : [{ id: 1, signerId: '' }],
      notifyEmail: template.notificationConfig?.notifyEmail ?? true,
      notifyEmails: template.notificationConfig?.emails || '',
      notifyCc: template.notificationConfig?.cc || '',
      notifyStages: {
        onValidationStep: template.notificationConfig?.stages?.onValidationStep ?? true,
        onSignatureStep: template.notificationConfig?.stages?.onSignatureStep ?? true,
        onApproved: template.notificationConfig?.stages?.onApproved ?? true,
        onRejected: template.notificationConfig?.stages?.onRejected ?? false as boolean,
        onCompleted: template.notificationConfig?.stages?.onCompleted ?? true,
      },
      sendDownloadLink: template.notificationConfig?.sendDownloadLink ?? true,
    }))
  }, [newWorkflowForm.templateId, workflowTemplates])

  const addValidationStep = () => {
    const newId = Math.max(...newWorkflowForm.validationSteps.map(s => s.id), 0) + 1
    setNewWorkflowForm((prev) => ({
      ...prev,
      validationSteps: [...prev.validationSteps, { id: newId, approverId: '' }],
    }))
  }

  const removeValidationStep = (id: number) => {
    setNewWorkflowForm((prev) => ({
      ...prev,
      validationSteps: prev.validationSteps.filter(s => s.id !== id),
    }))
  }

  const addSignatureStep = () => {
    const newId = Math.max(...newWorkflowForm.signatureSteps.map(s => s.id), 0) + 1
    setNewWorkflowForm((prev) => ({
      ...prev,
      signatureSteps: [...prev.signatureSteps, { id: newId, signerId: '' }],
    }))
  }

  const removeSignatureStep = (id: number) => {
    setNewWorkflowForm((prev) => ({
      ...prev,
      signatureSteps: prev.signatureSteps.filter(s => s.id !== id),
    }))
  }

  const handleNextStep = () => {
    if (isViewMode) {
      setNewWorkflowStep((prev) => Math.min(prev + 1, modalMaxStep))
      return
    }

    if (newWorkflowStep === 1 && !newWorkflowForm.name.trim()) {
      setFeedback('Le nom du parapheur est obligatoire')
      return
    }
    if (newWorkflowStep === 2) {
      const validatorsCount = newWorkflowForm.validationSteps.filter(s => s.approverId.trim()).length
      if (validatorsCount === 0) {
        setFeedback('Au moins un validateur est requis')
        return
      }
    }
    setFeedback(null)
    setNewWorkflowStep((prev) => Math.min(prev + 1, modalMaxStep))
  }

  const handlePreviousStep = () => {
    setNewWorkflowStep((prev) => Math.max(prev - 1, 1))
  }

  const handleAdvance = async (executionId: string) => {
    try {
      const updated = await advanceWorkflow(executionId, 1)
      setExecutions((prev) => prev.map((item) => (item.id === updated.id ? updated : item)))
      setFeedback('Étape avancée')
    } catch {
      setFeedback('Impossible d\'avancer l\'étape')
    }
  }

  const handleReject = async (executionId: string) => {
    try {
      const updated = await rejectWorkflow(executionId, 'Non conforme')
      setExecutions((prev) => prev.map((item) => (item.id === updated.id ? updated : item)))
      setFeedback('Workflow rejeté')
    } catch {
      setFeedback('Échec du rejet')
    }
  }

  const handleDuplicateFromModal = async () => {
    setFeedback(null)
    setFeedback('Formulaire dupliqué - modifiez et créez une nouvelle version')
    // Form stays populated, user can modify
  }

  const handleDeleteFromModal = () => {
    setDeleteConfirmation({ show: true, id: 'pending-workflow' })
  }

  const confirmDeleteFromModal = () => {
    if (deleteConfirmation.id && deleteConfirmation.id !== 'pending-workflow') {
      // Delete existing workflow
      handleDelete(deleteConfirmation.id)
    }
    setDeleteConfirmation({ show: false })
    resetWorkflowForm()
    closeNewWorkflowModal()
    setFeedback('Workflow annulé')
  }

  const handleDelete = async (workflowId: string) => {
    try {
      await deleteWorkflow(workflowId)
      setWorkflows((prev) => prev.filter((wf) => wf.id !== workflowId))
      setFeedback('Workflow supprimé')
    } catch {
      setFeedback('Échec de la suppression')
    }
  }

  const resetWorkflowForm = () => {
    setNewWorkflowForm({
      templateId: '',
      name: '',
      description: '',
      validationSteps: [{ id: 1, approverId: '' }],
      signatureSteps: [{ id: 1, signerId: '' }],
      docsToSign: [],
      attachedDocs: [],
      docsToSignUploaded: [],
      attachedDocsUploaded: [],
      docsToSignSource: 'documents',
      attachedDocsSource: 'documents',
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
  }

  const openCreateWorkflowModal = () => {
    setCreationMode('workflow')
    setViewingWorkflow(null)
    setNewWorkflowStep(1)
    resetWorkflowForm()
    setShowNewWorkflowModal(true)
  }

  const openCreateTemplateModal = () => {
    setCreationMode('template')
    setViewingWorkflow(null)
    setNewWorkflowStep(1)
    resetWorkflowForm()
    setShowNewWorkflowModal(true)
  }

  return (
    <div className="space-y-6">
      {feedback && <div className="p-3 bg-blue-100 text-blue-800 rounded-xl">{feedback}</div>}
      {uploadSuccessPopup && (
        <div className="fixed top-4 right-4 z-[80] px-4 py-3 rounded-xl border border-green-200 bg-green-50 text-green-800 shadow-lg text-sm font-semibold">
          âœ… {uploadSuccessPopup}
        </div>
      )}
      {uploadErrorPopup && (
        <div className="fixed top-20 right-4 z-[80] px-4 py-3 rounded-xl border border-red-200 bg-red-50 text-red-800 shadow-lg text-sm font-semibold">
          âš ï¸ {uploadErrorPopup}
        </div>
      )}

      <div className="flex justify-end gap-2">
        <button
          onClick={openCreateTemplateModal}
          className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition"
        >
          + Nouveau Modèle
        </button>
        <button
          onClick={openCreateWorkflowModal}
          className="px-4 py-2 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold rounded-lg transition"
        >
          + Nouveau workflow
        </button>
      </div>

      <div className="flex justify-end">
        <div className="relative">
          <button
            onClick={() => setShowTileSettings((prev) => !prev)}
            className="h-10 w-10 rounded-lg border border-gray-200 bg-white text-gray-600 hover:text-[#2453d6] hover:border-[#2453d6] flex items-center justify-center transition"
            title="Paramétrage des vignettes"
            aria-label="Paramétrage des vignettes"
          >
            <Settings size={19} />
          </button>

          {showTileSettings && (
            <div className="absolute right-0 mt-2 w-72 bg-white border border-gray-200 rounded-xl shadow-xl z-30 overflow-hidden">
              <div className="px-4 py-3 border-b border-gray-200 font-semibold text-gray-800">Parapheurs</div>

              {tileOptions.map((tile) => (
                <label key={tile.key} className="flex items-center gap-3 px-4 py-3 border-b border-gray-100 text-gray-800 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={visibleTiles[tile.key]}
                    onChange={() => toggleTile(tile.key)}
                    className="h-4 w-4 rounded border-gray-300"
                  />
                  <span>{tile.label}</span>
                </label>
              ))}

              <label className="flex items-center gap-3 px-4 py-3 text-gray-800 cursor-pointer">
                <input
                  type="checkbox"
                  checked={allTilesVisible}
                  onChange={(event) => toggleAllTiles(event.target.checked)}
                  className="h-4 w-4 rounded border-gray-300"
                />
                <span>Tous les parapheurs</span>
              </label>
            </div>
          )}
        </div>
      </div>

      <section className="space-y-3">
        <h2 className="text-3xl font-semibold text-gray-800">Parapheurs</h2>
        <div className="h-px bg-gray-200" />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 pt-1">
          {visibleTiles.toValidate && (
          <div className="rounded-xl overflow-hidden border border-gray-200 bg-[#f5f5f5]">
            <div className="bg-[#e04934] text-white text-lg font-semibold px-3 py-1.5">À signer / valider</div>
            <div className="px-4 py-5 flex items-end justify-between">
              <span className="text-5xl text-gray-500 font-light">{tileStats.toValidate}</span>
              <FileText size={72} className="text-gray-300" strokeWidth={1.2} />
            </div>
          </div>
          )}

          {visibleTiles.drafts && (
          <div className="rounded-xl overflow-hidden border border-gray-200 bg-[#f5f5f5]">
            <div className="bg-[#9f9fa3] text-white text-lg font-semibold px-3 py-1.5">Brouillons</div>
            <div className="px-4 py-5 flex items-end justify-between">
              <span className="text-5xl text-gray-500 font-light">{tileStats.drafts}</span>
              <FileText size={72} className="text-gray-300" strokeWidth={1.2} />
            </div>
          </div>
          )}

          {visibleTiles.started && (
          <div className="rounded-xl overflow-hidden border border-gray-200 bg-[#f5f5f5]">
            <div className="bg-[#dec10a] text-white text-lg font-semibold px-3 py-1.5">Démarrés</div>
            <div className="px-4 py-5 flex items-end justify-between">
              <span className="text-5xl text-gray-500 font-light">{tileStats.started}</span>
              <FileText size={72} className="text-gray-300" strokeWidth={1.2} />
            </div>
          </div>
          )}

          {visibleTiles.finished && (
          <div className="rounded-xl overflow-hidden border border-gray-200 bg-[#f5f5f5]">
            <div className="bg-[#95bc3d] text-white text-lg font-semibold px-3 py-1.5">Terminés</div>
            <div className="px-4 py-5 flex items-end justify-between">
              <span className="text-5xl text-gray-500 font-light">{tileStats.finished}</span>
              <FileText size={72} className="text-gray-300" strokeWidth={1.2} />
            </div>
          </div>
          )}

          {visibleTiles.stopped && (
          <div className="rounded-xl overflow-hidden border border-gray-200 bg-[#f5f5f5]">
            <div className="bg-[#e38200] text-white text-lg font-semibold px-3 py-1.5">Arrêtés</div>
            <div className="px-4 py-5 flex items-end justify-between">
              <span className="text-5xl text-gray-500 font-light">{tileStats.stopped}</span>
              <FileText size={72} className="text-gray-300" strokeWidth={1.2} />
            </div>
          </div>
          )}

          {visibleTiles.archived && (
          <div className="rounded-xl overflow-hidden border border-gray-200 bg-[#f5f5f5] lg:col-span-1">
            <div className="bg-[#5c5c5e] text-white text-lg font-semibold px-3 py-1.5">Archivés</div>
            <div className="px-4 py-5 flex items-end justify-between">
              <span className="text-5xl text-gray-500 font-light">{archivedCount}</span>
              <FileText size={72} className="text-gray-300" strokeWidth={1.2} />
            </div>
          </div>
          )}
        </div>
      </section>

      {/* Workflows Table Section */}
      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mt-6">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
          <h2 className="text-2xl font-bold text-gray-800">Workflows créés</h2>
          <input
            type="text"
            value={workflowSearch}
            onChange={(event) => setWorkflowSearch(event.target.value)}
            placeholder="Rechercher un workflow ou un propriétaire..."
            className="w-full md:w-80 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6]/30 focus:border-[#2453d6]"
          />
        </div>

        {workflows.length === 0 ? (
          <p className="text-gray-500 text-center py-6">Aucun workflow créé</p>
        ) : filteredWorkflows.length === 0 ? (
          <p className="text-gray-500 text-center py-6">Aucun résultat pour cette recherche</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="bg-gray-50 border-b border-gray-200">
                  <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">NOM</th>
                  <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">PROPRIÉTAIRE</th>
                  <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">DERNIÈRE MODIFICATION</th>
                  <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">STATUT</th>
                  <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">PROGRESSION</th>
                  <th className="px-4 py-3 text-center text-sm font-semibold text-gray-700">ACTIONS</th>
                </tr>
              </thead>
              <tbody>
                {filteredWorkflows.map((wf, idx) => {
                  const workflowExecutions = executions.filter(ex => ex.workflowId === wf.id)
                  const totalExecutions = workflowExecutions.length
                  const completedExecutions = workflowExecutions.filter(ex => ex.status === 'completed').length
                  const progressPercentage = totalExecutions > 0 ? Math.round((completedExecutions / totalExecutions) * 100) : 0
                  const lastModified = wf.updatedAt ? new Date(wf.updatedAt).toLocaleDateString('fr-FR') : 'N/A'
                  const displayStatus = getWorkflowDisplayStatus(wf.id)
                  
                  return (
                    <tr
                      key={wf.id}
                      className={`border-b border-gray-100 ${idx % 2 === 0 ? 'bg-gray-50' : 'bg-white'} hover:bg-gray-100 transition-colors`}
                    >
                      <td className="px-4 py-3 text-sm text-gray-900 font-medium">{wf.name}</td>
                      <td className="px-4 py-3 text-sm text-gray-600">{getOwnerLabel(wf)}</td>
                      <td className="px-4 py-3 text-sm text-gray-600">{lastModified}</td>
                      <td className="px-4 py-3 text-sm">
                        <span className={`inline-block px-3 py-1 rounded-full text-xs font-medium ${
                          displayStatus === 'finished' ? 'bg-green-100 text-green-800' :
                          displayStatus === 'started' ? 'bg-amber-100 text-amber-800' :
                          'bg-gray-100 text-gray-800'
                        }`}>
                          {displayStatus === 'draft' ? 'Brouillon' : displayStatus === 'started' ? 'Démarré' : 'Terminé'}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-sm">
                        <div className="flex items-center gap-2">
                          <div className="w-24 bg-gray-200 rounded-full h-2">
                            <div
                              className="bg-blue-500 h-2 rounded-full transition-all duration-300"
                              style={{ width: `${progressPercentage}%` }}
                            ></div>
                          </div>
                          <span className="text-xs text-gray-600 font-medium">{progressPercentage}%</span>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-center">
                        <div className="flex items-center justify-center gap-2">
                          <button
                            onClick={() => {
                              setSelectedWorkflowId(wf.id)
                              openViewWorkflowModal(wf)
                            }}
                            className="text-blue-500 hover:text-blue-700 transition-colors"
                            title="Voir détails"
                          >
                            <Eye size={18} />
                          </button>
                          <button
                            onClick={() => handleDuplicateFromModal()}
                            className="text-green-500 hover:text-green-700 transition-colors"
                            title="Dupliquer"
                          >
                            <Copy size={18} />
                          </button>
                          <button
                            onClick={() => {
                              setNewWorkflowForm(wf as any)
                              setDeleteConfirmation({ show: true, id: wf.id })
                            }}
                            className="text-red-500 hover:text-red-700 transition-colors"
                            title="Supprimer"
                          >
                            <Trash2 size={18} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Delete Confirmation Modal */}
      {deleteConfirmation.show && (
        <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
            <h3 className="text-lg font-bold text-gray-900 mb-2">Confirmer la suppression</h3>
            <p className="text-gray-600 mb-6">
              Êtes-vous sûr de vouloir supprimer ce workflow ? Cette action ne peut pas être annulée.
            </p>
            <div className="flex gap-3 justify-end">
              <button
                onClick={() => setDeleteConfirmation({ show: false })}
                className="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors font-medium"
              >
                Annuler
              </button>
              <button
                onClick={() => confirmDeleteFromModal()}
                className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium"
              >
                Supprimer
              </button>
            </div>
          </div>
        </div>
      )}

      {showNewWorkflowModal && (
        <div className="fixed inset-0 z-50 bg-black/30 flex items-start justify-center p-4 md:p-8 overflow-y-auto">
          {/* File inputs always in DOM so label/htmlFor works reliably */}
          <input
            id="docs-to-sign-file-input"
            type="file"
            multiple
            style={{ position: 'fixed', top: '-9999px', left: '-9999px', opacity: 0, width: 0, height: 0 }}
            onChange={(e) => {
              if (e.target.files && e.target.files.length > 0) {
                const files = Array.from(e.target.files)
                setNewWorkflowForm((prev) => ({ ...prev, docsToSignUploaded: [...prev.docsToSignUploaded, ...files] }))
                showUploadSuccessPopup(`${files.length} fichier(s) ajouté(s) dans Documents à signer`)
                e.target.value = ''
              }
            }}
          />
          <input
            id="attached-docs-file-input"
            type="file"
            multiple
            style={{ position: 'fixed', top: '-9999px', left: '-9999px', opacity: 0, width: 0, height: 0 }}
            onChange={(e) => {
              if (e.target.files && e.target.files.length > 0) {
                const files = Array.from(e.target.files)
                setNewWorkflowForm((prev) => ({ ...prev, attachedDocsUploaded: [...prev.attachedDocsUploaded, ...files] }))
                showUploadSuccessPopup(`${files.length} fichier(s) ajouté(s) dans Pièces jointes`)
                e.target.value = ''
              }
            }}
          />
          <div className="w-full max-w-2xl bg-white rounded-2xl shadow-xl border border-gray-100">
            <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
              <h2 className="text-lg font-semibold text-gray-900">
                {isViewMode ? 'Détails du workflow' : creationMode === 'template' ? 'Nouveau Modèle de Workflow' : 'Nouveau Parapheur'}
              </h2>
              <button onClick={closeNewWorkflowModal} className="text-gray-400 hover:text-gray-600 text-2xl leading-none">×</button>
            </div>

            <div className="px-6 py-5 space-y-4">
              <div className="flex flex-wrap gap-4 text-xs font-semibold text-gray-400">
                {(isViewMode
                  ? [
                    { key: 1, label: 'Général' },
                    { key: 2, label: 'Attribution' },
                    { key: 3, label: 'Opération' },
                  ]
                  : [
                    { key: 1, label: 'Général' },
                    { key: 2, label: 'Attribution' },
                    ...(creationMode === 'workflow' ? [{ key: 3, label: 'Documents' }] : []),
                    { key: notificationsStep, label: 'Notifications' },
                    { key: summaryStep, label: 'Opération' },
                  ]).map((step) => (
                  <div key={step.key} className={`flex items-center gap-2 ${newWorkflowStep === step.key ? 'text-[#2453d6]' : 'text-gray-400'}`}>
                    <span className={`h-8 w-8 rounded-full grid place-items-center text-xs ${newWorkflowStep === step.key ? 'bg-[#2453d6] text-white' : 'bg-gray-100 text-gray-500'}`}>
                      {step.key}
                    </span>
                    <span className="text-sm">{step.label}</span>
                  </div>
                ))}
              </div>

              {isViewMode && viewingWorkflow && (
                <div className="rounded-xl border border-blue-100 bg-blue-50 p-4 space-y-3">
                  <div className="flex items-center justify-between gap-3">
                    <p className="text-sm font-semibold text-gray-800">Progression actuelle</p>
                    <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ${
                      getWorkflowModalProgress(viewingWorkflow).statusLabel === 'Terminé'
                        ? 'bg-green-100 text-green-800'
                        : getWorkflowModalProgress(viewingWorkflow).statusLabel === 'Démarré'
                          ? 'bg-amber-100 text-amber-800'
                          : 'bg-gray-100 text-gray-700'
                    }`}>
                      {getWorkflowModalProgress(viewingWorkflow).statusLabel}
                    </span>
                  </div>
                  <div className="w-full bg-white rounded-full h-2.5 border border-blue-100 overflow-hidden">
                    <div
                      className="h-2.5 bg-[#2453d6] transition-all"
                      style={{ width: `${getWorkflowModalProgress(viewingWorkflow).progressPercent}%` }}
                    />
                  </div>
                  <p className="text-xs text-gray-600">
                    Étape actuelle: {getWorkflowModalProgress(viewingWorkflow).stepLabel}
                  </p>
                </div>
              )}

              <form onSubmit={(event) => {
                if (isViewMode) {
                  event.preventDefault()
                  return
                }
                handleCreate(event)
              }} className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
                {newWorkflowStep === 1 && (
                  <>
                    {creationMode === 'workflow' && !isViewMode && (
                      <div className="space-y-2">
                        <label className="block text-sm font-semibold text-gray-700">Choisir un modèle</label>
                        <select
                          value={newWorkflowForm.templateId}
                          onChange={(e) => setNewWorkflowForm((prev) => ({ ...prev, templateId: e.target.value }))}
                          className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#2453d6]"
                        >
                          <option value="">Sélectionner un modèle</option>
                          {workflowTemplates.map((workflowTemplate) => (
                            <option key={workflowTemplate.id} value={workflowTemplate.id}>{workflowTemplate.name}</option>
                          ))}
                        </select>
                      </div>
                    )}

                    <div className="space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">{creationMode === 'template' ? 'Nom du modèle *' : 'Nom du parapheur *'}</label>
                      <input
                        type="text"
                        value={newWorkflowForm.name}
                        onChange={(e) => setNewWorkflowForm((prev) => ({ ...prev, name: e.target.value }))}
                        disabled={isViewMode}
                        className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#2453d6]"
                      />
                    </div>

                    <div className="space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">Description</label>
                      <textarea
                        value={newWorkflowForm.description}
                        onChange={(e) => setNewWorkflowForm((prev) => ({ ...prev, description: e.target.value }))}
                        disabled={isViewMode}
                        rows={3}
                        className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#2453d6]"
                      />
                    </div>
                  </>
                )}

                {newWorkflowStep === 2 && (
                  <div className="space-y-6">
                    {/* Étapes de validation */}
                    <div className="bg-green-50 border-l-4 border-green-500 rounded-lg p-5 space-y-4">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                          <CheckCircle2 size={24} className="text-green-600" />
                          <h3 className="text-sm font-semibold text-gray-800">Étapes de validation</h3>
                        </div>
                        <button
                          type="button"
                          onClick={addValidationStep}
                          disabled={isViewMode}
                          className="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition"
                        >
                          Ajouter
                        </button>
                      </div>
                      <div className="space-y-3">
                        {newWorkflowForm.validationSteps.map((step, idx) => (
                          <div key={step.id} className="flex items-center gap-3 bg-white p-4 rounded-lg border border-gray-200">
                            <span className="h-10 w-10 rounded-full bg-green-600 text-white text-sm font-bold flex items-center justify-center flex-shrink-0">
                              {idx + 1}
                            </span>
                            <select
                              value={step.approverId}
                              onChange={(e) => {
                                const updatedSteps = [...newWorkflowForm.validationSteps]
                                updatedSteps[idx].approverId = e.target.value
                                setNewWorkflowForm((prev) => ({ ...prev, validationSteps: updatedSteps }))
                              }}
                              disabled={isViewMode}
                              className="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                            >
                              <option value="">Choisir un validateur</option>
                              {signataires.map((u) => (
                                <option key={u.id} value={u.id}>{u.fullName} ({u.email})</option>
                              ))}
                            </select>
                            <button
                              type="button"
                              onClick={() => removeValidationStep(step.id)}
                              disabled={isViewMode}
                              className="p-2.5 text-red-500 hover:bg-red-50 rounded-lg transition"
                            >
                              <Trash2 size={20} />
                            </button>
                          </div>
                        ))}
                      </div>
                    </div>

                    {/* Étapes de signature */}
                    <div className="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-5 space-y-4">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                          <PenTool size={24} className="text-blue-600" />
                          <h3 className="text-sm font-semibold text-gray-800">Étapes de signature</h3>
                        </div>
                        <button
                          type="button"
                          onClick={addSignatureStep}
                          disabled={isViewMode}
                          className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition"
                        >
                          Ajouter
                        </button>
                      </div>
                      <div className="space-y-3">
                        {newWorkflowForm.signatureSteps.map((step, idx) => (
                          <div key={step.id} className="flex items-center gap-3 bg-white p-4 rounded-lg border border-gray-200">
                            <span className="h-10 w-10 rounded-full bg-blue-600 text-white text-sm font-bold flex items-center justify-center flex-shrink-0">
                              {idx + 1}
                            </span>
                            <select
                              value={step.signerId}
                              onChange={(e) => {
                                const updatedSteps = [...newWorkflowForm.signatureSteps]
                                updatedSteps[idx].signerId = e.target.value
                                setNewWorkflowForm((prev) => ({ ...prev, signatureSteps: updatedSteps }))
                              }}
                              disabled={isViewMode}
                              className="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                              <option value="">Choisir un signataire</option>
                              {signataires.map((u) => (
                                <option key={u.id} value={u.id}>{u.fullName} ({u.email})</option>
                              ))}
                            </select>
                            <button
                              type="button"
                              onClick={() => removeSignatureStep(step.id)}
                              disabled={isViewMode}
                              className="p-2.5 text-red-500 hover:bg-red-50 rounded-lg transition"
                            >
                              <Trash2 size={20} />
                            </button>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                )}

                {creationMode === 'workflow' && !isViewMode && newWorkflowStep === 3 && (
                  <div className="space-y-4">
                    <div className="bg-red-50 border border-red-100 rounded-lg p-4 space-y-3">
                      <div className="flex items-center gap-2">
                        <FileText size={20} className="text-red-600" />
                        <h3 className="text-sm font-semibold text-gray-800">Documents à signer</h3>
                      </div>
                      <div className="space-y-2">
                        <label className="block text-xs font-medium text-gray-600">Source</label>
                        <select
                          value={newWorkflowForm.docsToSignSource}
                          onChange={(e) => setNewWorkflowForm((prev) => ({ ...prev, docsToSignSource: e.target.value as 'documents' | 'upload' }))}
                          className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                        >
                          <option value="documents">Mes Documents</option>
                          <option value="upload">Téléverser depuis l'ordinateur</option>
                        </select>
                      </div>
                      {newWorkflowForm.docsToSignSource === 'documents' ? (
                        <div className="space-y-3">
                          <select
                            className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                            onChange={(e) => {
                              if (e.target.value && !newWorkflowForm.docsToSign.includes(e.target.value)) {
                                setNewWorkflowForm((prev) => ({ ...prev, docsToSign: [...prev.docsToSign, e.target.value] }))
                                e.target.value = ''
                              }
                            }}
                          >
                            <option value="">Sélectionner fichiers</option>
                            {documents.map((document) => (
                              <option key={document.id} value={document.id}>{document.title}</option>
                            ))}
                          </select>
                          {newWorkflowForm.docsToSign.length > 0 && (
                            <div className="space-y-2">
                              {newWorkflowForm.docsToSign
                                .filter((documentId) => typeof documentId === 'string' && documentId.trim().length > 0)
                                .map((documentId, index) => {
                                const document = documents.find((item) => item.id === documentId)
                                return (
                                  <div key={`${documentId}-${index}`} className="flex items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2">
                                    <div className="min-w-0">
                                      <p className="truncate text-sm font-medium text-gray-800">{document?.title || `Document ${documentId.slice(0, 8)}`}</p>
                                      <p className="text-xs text-gray-500">Joint directement au workflow</p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                      <button
                                        type="button"
                                        onClick={() => setNewWorkflowForm((prev) => ({
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
                                  const isSelected = newWorkflowForm.docsToSign.includes(document.id)
                                  return (
                                    <div key={document.id} className="flex items-center justify-between gap-2 px-3 py-2">
                                      <p className="truncate text-xs text-gray-700">{document.title}</p>
                                      <div className="flex items-center gap-2 shrink-0">
                                        <button
                                          type="button"
                                          onClick={() => { if (!isSelected) setNewWorkflowForm((prev) => ({ ...prev, docsToSign: [...prev.docsToSign, document.id] })) }}
                                          className="px-2 py-1 rounded-md bg-gray-100 hover:bg-gray-200 text-[11px] font-semibold text-gray-700 disabled:opacity-50"
                                          disabled={isSelected}
                                        >
                                          {isSelected ? 'Ajouté' : 'Ajouter'}
                                        </button>
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
                          <label
                            htmlFor="docs-to-sign-file-input"
                            className="w-full flex items-center justify-center gap-2 border-2 border-dashed border-red-300 rounded-lg px-3 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors font-medium cursor-pointer"
                          >
                            <Upload size={16} />
                            Sélect. fichiers
                          </label>
                          {newWorkflowForm.docsToSignUploaded.length > 0 && (
                            <div className="space-y-2">
                              {newWorkflowForm.docsToSignUploaded.map((file, idx) => {
                                const fileKey = getFileKey(file)
                                const zonesCount = zonesByFileKey[fileKey]?.length || 0
                                const isSaved = Boolean(savedZoneByKey[fileKey])
                                return (
                                  <div key={fileKey} className="flex items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2">
                                    <div className="min-w-0">
                                      <p className="truncate text-sm font-medium text-gray-800">{file.name}</p>
                                      <p className="text-xs text-gray-500">{(file.size / 1024).toFixed(2)} KB â€¢ {zonesCount} zone(s)</p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                      {isSaved
                                        ? <span className="px-3 py-1.5 rounded-lg bg-green-100 text-green-700 text-xs font-semibold cursor-pointer" onClick={() => openPositioning(file)}>✓ Positionné ({zonesCount})</span>
                                        : <button
                                          type="button"
                                          onClick={() => openPositioning(file)}
                                          className="px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold"
                                        >
                                          Positionner
                                        </button>}
                                      <button
                                        type="button"
                                        onClick={() => setNewWorkflowForm((prev) => ({
                                          ...prev,
                                          docsToSignUploaded: prev.docsToSignUploaded.filter((_, i) => i !== idx),
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
                        </div>
                      )}
                      <div className="text-xs text-gray-500">
                        {newWorkflowForm.docsToSign.length + newWorkflowForm.docsToSignUploaded.length > 0
                          ? `${newWorkflowForm.docsToSign.length + newWorkflowForm.docsToSignUploaded.length} fichier(s) sélectionné(s)`
                          : 'Aucun fichier choisi'}
                      </div>
                    </div>

                    <div className="bg-green-50 border border-green-100 rounded-lg p-4 space-y-3">
                      <div className="flex items-center gap-2">
                        <FileText size={20} className="text-green-600" />
                        <h3 className="text-sm font-semibold text-gray-800">Pièces jointes</h3>
                      </div>
                      <div className="space-y-2">
                        <label className="block text-xs font-medium text-gray-600">Source</label>
                        <select
                          value={newWorkflowForm.attachedDocsSource}
                          onChange={(e) => setNewWorkflowForm((prev) => ({ ...prev, attachedDocsSource: e.target.value as 'documents' | 'upload' }))}
                          className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                        >
                          <option value="documents">Mes Documents</option>
                          <option value="upload">Téléverser depuis l'ordinateur</option>
                        </select>
                      </div>
                      {newWorkflowForm.attachedDocsSource === 'documents' ? (
                        <select
                          className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                          onChange={(e) => {
                            if (e.target.value && !newWorkflowForm.attachedDocs.includes(e.target.value)) {
                              setNewWorkflowForm((prev) => ({ ...prev, attachedDocs: [...prev.attachedDocs, e.target.value] }))
                              e.target.value = ''
                            }
                          }}
                        >
                          <option value="">Sélectionner fichiers</option>
                          {documents.map((document) => (
                            <option key={document.id} value={document.id}>{document.title}</option>
                          ))}
                        </select>
                      ) : (
                        <div className="space-y-3">
                          <label
                            htmlFor="attached-docs-file-input"
                            className="w-full flex items-center justify-center gap-2 border-2 border-dashed border-green-300 rounded-lg px-3 py-3 text-sm text-green-600 hover:bg-green-50 transition-colors font-medium cursor-pointer"
                          >
                            <Upload size={16} />
                            Sélect. fichiers
                          </label>
                          {newWorkflowForm.attachedDocsUploaded.length > 0 && (
                            <div className="space-y-2">
                              {newWorkflowForm.attachedDocsUploaded.map((file, idx) => (
                                <div key={`${file.name}-${idx}`} className="flex items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2">
                                  <div className="min-w-0">
                                    <p className="truncate text-sm font-medium text-gray-800">{file.name}</p>
                                    <p className="text-xs text-gray-500">{(file.size / 1024).toFixed(2)} KB</p>
                                  </div>
                                  <button
                                    type="button"
                                    onClick={() => setNewWorkflowForm((prev) => ({
                                      ...prev,
                                      attachedDocsUploaded: prev.attachedDocsUploaded.filter((_, i) => i !== idx),
                                    }))}
                                    className="px-2 py-1.5 rounded-lg text-red-600 hover:bg-red-50 text-xs font-semibold"
                                  >
                                    Supprimer
                                  </button>
                                </div>
                              ))}
                            </div>
                          )}
                        </div>
                      )}
                      <div className="text-xs text-gray-500">
                        {newWorkflowForm.attachedDocs.length + newWorkflowForm.attachedDocsUploaded.length > 0
                          ? `${newWorkflowForm.attachedDocs.length + newWorkflowForm.attachedDocsUploaded.length} fichier(s) sélectionné(s)`
                          : 'Aucun fichier choisi'}
                      </div>
                    </div>
                  </div>
                )}

                {newWorkflowStep === modalNotificationsStep && !isViewMode && (
                    <div className="space-y-4">
                      {/* Master toggle */}
                      <label className="flex items-center gap-3 cursor-pointer select-none">
                        <div
                          onClick={() => setNewWorkflowForm((prev) => ({ ...prev, notifyEmail: !prev.notifyEmail }))}
                          className={`relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors cursor-pointer ${newWorkflowForm.notifyEmail ? 'bg-[#2453d6]' : 'bg-gray-300'}`}
                        >
                          <span className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform ${newWorkflowForm.notifyEmail ? 'translate-x-6' : 'translate-x-1'}`} />
                        </div>
                        <span className="text-sm font-semibold text-gray-800">Activer les notifications par e-mail</span>
                      </label>

                      {newWorkflowForm.notifyEmail && (
                        <div className="rounded-xl border border-amber-200 bg-amber-50 p-5 space-y-5">
                          <div className="flex items-center gap-2 text-amber-700 font-semibold text-sm">
                            <span>ðŸ“§</span>
                            <span>Notifications par email</span>
                          </div>

                          {/* Recipients */}
                          <div className="space-y-1.5">
                            <label className="block text-sm font-semibold text-gray-700">Emails de notification</label>
                            <textarea
                              rows={2}
                              value={newWorkflowForm.notifyEmails}
                              onChange={(e) => setNewWorkflowForm((prev) => ({ ...prev, notifyEmails: e.target.value }))}
                              placeholder="Séparer les emails par des virgules (ex: user1@gov.ma, user2@gov.ma)"
                              className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white"
                            />
                          </div>

                          {/* CC */}
                          <div className="space-y-1.5">
                            <label className="block text-sm font-semibold text-gray-700">Copie cachée (Cc)</label>
                            <textarea
                              rows={2}
                              value={newWorkflowForm.notifyCc}
                              onChange={(e) => setNewWorkflowForm((prev) => ({ ...prev, notifyCc: e.target.value }))}
                              placeholder="Emails en copie cachée (optionnel)"
                              className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white"
                            />
                          </div>

                          {/* Stage triggers */}
                          <div className="space-y-2">
                            <p className="text-sm font-semibold text-gray-700">Étapes déclenchant une notification</p>
                            <div className="space-y-2">
                              {([
                                { key: 'onValidationStep', label: 'À chaque étape de validation' },
                                { key: 'onSignatureStep',  label: 'À chaque étape de signature' },
                                { key: 'onApproved',       label: 'Workflow approuvé (toutes les étapes réussies)' },
                                { key: 'onRejected',       label: 'Workflow rejeté' },
                                { key: 'onCompleted',      label: 'Document signé disponible (fin du workflow)' },
                              ] as { key: keyof typeof newWorkflowForm.notifyStages; label: string }[]).map(({ key, label }) => (
                                <label key={key} className="flex items-center gap-2.5 text-sm text-gray-700 cursor-pointer select-none">
                                  <input
                                    type="checkbox"
                                    checked={newWorkflowForm.notifyStages[key]}
                                    onChange={(e) => setNewWorkflowForm((prev) => ({
                                      ...prev,
                                      notifyStages: { ...prev.notifyStages, [key]: e.target.checked },
                                    }))}
                                    className="h-4 w-4 rounded border-gray-300 accent-[#2453d6]"
                                  />
                                  {label}
                                </label>
                              ))}
                            </div>
                          </div>

                          {/* Download link */}
                          <div className="pt-3 border-t border-amber-200 space-y-1">
                            <label className="flex items-center gap-2.5 text-sm font-semibold text-gray-700 cursor-pointer select-none">
                              <input
                                type="checkbox"
                                checked={newWorkflowForm.sendDownloadLink}
                                onChange={(e) => setNewWorkflowForm((prev) => ({ ...prev, sendDownloadLink: e.target.checked }))}
                                className="h-4 w-4 rounded border-gray-300 accent-[#2453d6]"
                              />
                              Envoyer un lien de téléchargement du document signé à la fin du workflow
                            </label>
                            <p className="ml-7 text-xs text-gray-500">Un lien sécurisé vers le document signé et les Pièces jointes sera inclus dans le dernier e-mail.</p>
                          </div>
                        </div>
                      )}
                    </div>
                )}

                {newWorkflowStep === modalSummaryStep && (
                  <div className="space-y-5">
                    {/* Recap section */}
                    <div className="space-y-3 text-sm text-gray-700">
                      <div>
                        <p className="font-semibold text-gray-800">Nom du parapheur</p>
                        <p>{newWorkflowForm.name || 'â€”'}</p>
                      </div>
                      <div>
                        <p className="font-semibold text-gray-800">Description</p>
                        <p>{newWorkflowForm.description || 'â€”'}</p>
                      </div>
                      <div className="bg-blue-50 border border-blue-100 rounded-lg p-4 space-y-3">
                        <div>
                          <p className="font-semibold text-gray-800 flex items-center gap-2"><CheckCircle2 size={18} className="text-green-600" /> Étapes de validation ({newWorkflowForm.validationSteps.filter(s => s.approverId).length})</p>
                          <ul className="list-disc list-inside text-gray-600 ml-6">
                            {newWorkflowForm.validationSteps.filter(s => s.approverId).map((s, i) => (
                              <li key={s.id}>Validateur {i + 1}: {getUserDisplayName(s.approverId)}</li>
                            ))}
                          </ul>
                        </div>
                        <div>
                          <p className="font-semibold text-gray-800 flex items-center gap-2"><PenTool size={18} className="text-blue-600" /> Étapes de signature ({newWorkflowForm.signatureSteps.filter(s => s.signerId).length})</p>
                          <ul className="list-disc list-inside text-gray-600 ml-6">
                            {newWorkflowForm.signatureSteps.filter(s => s.signerId).map((s, i) => (
                              <li key={s.id}>Signataire {i + 1}: {getUserDisplayName(s.signerId)}</li>
                            ))}
                          </ul>
                        </div>
                      </div>
                      <div>
                        <p className="font-semibold text-gray-800">Notifications</p>
                        {newWorkflowForm.notifyEmail ? (
                          <ul className="list-disc list-inside text-gray-600 space-y-0.5">
                            <li>Activées â€” {newWorkflowForm.notifyEmails || 'aucun destinataire saisi'}</li>
                            {newWorkflowForm.notifyCc && <li>Cc: {newWorkflowForm.notifyCc}</li>}
                            {newWorkflowForm.sendDownloadLink && <li>Lien de téléchargement du document signé inclus</li>}
                          </ul>
                        ) : (
                          <p className="text-gray-500">Désactivées</p>
                        )}
                      </div>
                    </div>

                    {/* Actions section */}
                    {!isViewMode && (
                    <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
                      <p className="text-sm font-semibold text-gray-800 mb-4">Actions disponibles</p>
                      <div className="flex flex-wrap gap-3">
                        <button
                          type="button"
                          onClick={handleDuplicateFromModal}
                          className="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition flex items-center gap-2"
                        >
                          <PlusCircle size={18} /> Dupliquer
                        </button>
                        <button
                          type="button"
                          onClick={handleDeleteFromModal}
                          className="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition flex items-center gap-2"
                        >
                          <Trash2 size={18} /> Supprimer
                        </button>
                        <button
                          type="submit"
                          className="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition flex items-center gap-2"
                        >
                          <PlayCircle size={18} /> Démarrer
                        </button>
                      </div>
                    </div>
                    )}
                  </div>
                )}

                <div className="flex items-center justify-between pt-3">
                  <button
                    type="button"
                    onClick={handlePreviousStep}
                    disabled={newWorkflowStep === 1}
                    className="px-4 py-2 rounded-lg bg-gray-200 disabled:opacity-50 text-gray-700 text-sm font-semibold"
                  >
                    Précédent
                  </button>

                  {isViewMode ? (
                    <button
                      type="button"
                      onClick={closeNewWorkflowModal}
                      className="px-4 py-2 rounded-lg bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold"
                    >
                      Fermer
                    </button>
                  ) : newWorkflowStep < modalMaxStep ? (
                    <button
                      type="button"
                      onClick={handleNextStep}
                      className="px-4 py-2 rounded-lg bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold"
                    >
                      Suivant
                    </button>
                  ) : newWorkflowStep !== modalSummaryStep ? (
                    <button
                      type="submit"
                      className="px-4 py-2 rounded-lg bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold"
                    >
                      {creationMode === 'template' ? 'Créer le modèle' : 'Créer le workflow'}
                    </button>
                  ) : null}
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {positioningTargetKey && positioningFileUrl && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-lg border border-gray-200 w-full max-w-6xl h-[86vh] overflow-hidden flex flex-col">
            <div className="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
              <div>
                <p className="text-lg font-semibold text-gray-800">Document PDF - Zones de Signature</p>
                <p className="text-sm text-gray-600">{positioningTargetName}</p>
                <p className="text-sm text-gray-500">{currentPositioningZones.length} zone de signature à positionner</p>
              </div>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => addZone()}
                  className="px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold"
                >
                  Ajouter zone
                </button>
                <button
                  type="button"
                  onClick={clearZones}
                  className="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold"
                >
                  Effacer zones
                </button>
                <button
                  type="button"
                  onClick={savePositioningAndClose}
                  className="px-3 py-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-xs font-semibold"
                >
                  Enregistrer
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
              onPointerMove={handleZonePointerMove}
              onPointerUp={stopZoneDrag}
              onPointerLeave={stopZoneDrag}
            >
              {/* PDF displayed behind the interaction overlay */}
              {shouldUseOnlyoffice ? (
                <iframe
                  title="OnlyOffice PDF Viewer"
                  src={onlyofficeViewerUrl}
                  className="absolute inset-0 w-full h-full border-0"
                  onError={() => {
                    setForceNativeViewer(true)
                    setFeedback('OnlyOffice indisponible pour ce document. Passage au lecteur PDF natif.')
                  }}
                />
              ) : (
                <iframe
                  title="PDF Viewer"
                  src={positioningFileUrl!}
                  className="absolute inset-0 w-full h-full border-0"
                />
              )}

              {/* Non-blocking overlay keeps PDF scroll available; zone blocks remain interactive. */}
              <div
                className={`absolute inset-0 pointer-events-none ${dragAction ? 'cursor-grabbing' : ''}`}
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
                    title="Glissez pour déplacer"
                    onPointerDown={(event) => {
                      event.stopPropagation()
                      event.preventDefault()
                      const rect = event.currentTarget.parentElement!.getBoundingClientRect()
                      setDragAction({
                        zoneId: zone.id,
                        mode: 'move',
                        startX: ((event.clientX - rect.left) / rect.width) * 100,
                        startY: ((event.clientY - rect.top) / rect.height) * 100,
                        origZone: { ...zone },
                      })
                    }}
                  >
                    <button
                      type="button"
                      className="absolute -top-2 -right-2 h-5 w-5 rounded-full bg-red-600 text-white text-[10px] leading-none opacity-0 group-hover:opacity-100 transition-opacity z-10"
                      title="Supprimer"
                      onClick={(event) => {
                        event.stopPropagation()
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
                      onPointerDown={(event) => {
                        event.stopPropagation()
                        event.preventDefault()
                        const rect = event.currentTarget.parentElement!.parentElement!.getBoundingClientRect()
                        setDragAction({
                          zoneId: zone.id,
                          mode: 'resize',
                          startX: ((event.clientX - rect.left) / rect.width) * 100,
                          startY: ((event.clientY - rect.top) / rect.height) * 100,
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

      <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 className="text-2xl font-bold text-gray-800 mb-4">Workflows existants</h2>
        {workflows.length === 0 ? (
          <p className="text-gray-500">Aucun workflow</p>
        ) : (
          <div className="space-y-3">
            {workflows.map((wf) => (
              <div key={wf.id} className="border border-gray-200 p-4 rounded-xl flex justify-between items-center bg-gray-50">
                <div>
                  <p className="font-semibold text-lg text-gray-800">{wf.name}</p>
                  <p className="text-sm text-gray-600">{wf.description}</p>
                </div>
                <button
                  onClick={() => handleDelete(wf.id)}
                  className="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 flex items-center gap-2"
                >
                  <Trash2 size={15} /> Supprimer
                </button>
              </div>
            ))}
          </div>
        )}
      </section>

      <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 className="text-2xl font-bold text-gray-800 mb-4">Exécutions</h2>
        {executions.length === 0 ? (
          <p className="text-gray-500">Aucune exécution</p>
        ) : (
          <div className="space-y-3">
            {executions.map((exec) => (
              <div key={exec.id} className="border border-gray-200 p-4 rounded-xl bg-gray-50">
                <p className="text-sm text-gray-600">Workflow ID: {exec.workflowId}</p>
                <p className="text-sm text-gray-600">Document ID: {exec.documentId}</p>
                <p className="text-sm text-gray-600">Étape actuelle: {exec.currentStep}</p>
                <p className="text-sm text-gray-600">Statut: {exec.status}</p>
                <div className="mt-2 flex gap-2">
                  <button
                    onClick={() => handleAdvance(exec.id)}
                    className="px-3 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
                  >
                    Avancer
                  </button>
                  <button
                    onClick={() => handleReject(exec.id)}
                    className="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600"
                  >
                    Rejeter
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  )
}

export default Workflows


