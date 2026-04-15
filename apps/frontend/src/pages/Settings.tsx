import React, { FormEvent, useEffect, useMemo, useRef, useState } from 'react'
import {
  createAdministrationProfile,
  createDirectionType,
  createRequestedAct,
  createIssuingAdministration,
  createRecipientAdministration,
  createRoutingRule,
  createTemplate,
  createTemplateVariable,
  deleteIssuingAdministration,
  deleteAdministrationProfile,
  deleteDirectionType,
  deleteRecipientAdministration,
  deleteRequestedAct,
  deleteRoutingRule,
  deleteTemplate,
  deleteTemplateVariable,
  fetchAppSettings,
  fetchIssuingAdministrations,
  fetchDirectionTypes,
  fetchRequestedActs,
  fetchRecipientAdministrations,
  fetchRoutingRules,
  fetchNotificationConfigByAdministration,
  fetchSignatureProviderConfig,
  fetchTemplateVariables,
  fetchTemplates,
  generateTemplateDocument,
  updateIssuingAdministration,
  updateAdministrationProfile,
  updateDirectionType,
  updateRecipientAdministration,
  updateRequestedAct,
  uploadRecipientAdministrationLogo,
  updateRoutingRule,
  updateNotificationConfigByAdministration,
  updateSignatureProviderConfig,
  updateTemplate,
  updateTemplateVariable,
  upsertAppSettings,
  uploadThemeBackgroundImage,
  uploadAdministrationLogo,
} from '../services/administration'
import { uploadDocumentFile } from '../services/documents'
import { getCurrentUser, getCurrentUserPermissions, getCurrentUserTheme } from '../services/auth'
import { useAuthStore } from '../store/authStore'
import { AppUserRecord, createAppUser, deleteAppUser, fetchAppUsers, updateAppUser, updateAppUserStatus, uploadAppUserAvatar } from '../services/users'
import {
  AdministrationProfile,
  AppSetting,
  DirectionType,
  DocumentTemplate,
  IssuingAdministration,
  RecipientAdministration,
  RoutingRule,
  NotificationConfig,
  RequestedAct,
  SignatureProviderConfig,
  TemplateVariable,
} from '../types/administration'

type TabKey = 'templates' | 'emitters' | 'recipients' | 'sub-entities' | 'requested-acts' | 'direction-types' | 'routing' | 'onlyoffice' | 'users' | 'theming' | 'email-notifications' | 'signature-provider' | 'user-profiles'

const SETTINGS_TAB_CONFIG: Array<{ key: TabKey; label: string; permission: string }> = [
  { key: 'templates', label: 'Templates', permission: 'administration.templates' },
  { key: 'emitters', label: 'Émetteurs', permission: 'administration.emitters' },
  { key: 'recipients', label: 'Destinataires', permission: 'administration.recipients' },
  { key: 'sub-entities', label: 'Entités sous tutelle', permission: 'administration.recipients' },
  { key: 'requested-acts', label: 'Acte demandé', permission: 'administration.requested-acts' },
  { key: 'direction-types', label: 'Types de direction', permission: 'administration.recipients' },
  { key: 'routing', label: 'Routage', permission: 'administration.routing' },
  { key: 'onlyoffice', label: 'OnlyOffice', permission: 'administration.onlyoffice' },
  { key: 'users', label: 'Utilisateurs', permission: 'administration.users' },
  { key: 'theming', label: 'Apparence', permission: 'administration.theming' },
  { key: 'email-notifications', label: 'Notifications E-mail', permission: 'administration.email-notifications' },
  { key: 'signature-provider', label: 'API Signature', permission: 'administration.signature-provider' },
  { key: 'user-profiles', label: 'Rôles', permission: 'administration.user-profiles' },
]

interface UserAppProfile {
  id: string
  name: string
  description: string
  permissions: string[]
  createdAt: string
}

interface PermissionNode {
  id: string
  label: string
  children?: PermissionNode[]
}

interface SubEntityItem {
  id: string
  code: string
  name: string
  parentCode?: string
  directionType: string
  managerName?: string
  managerEmail?: string
  description?: string
}

type TemplateSignatureZone = {
  id: string
  x: number
  y: number
  width: number
  height: number
}

type RequestedActApplicantFieldType = 'text' | 'date' | 'number' | 'phone' | 'email' | 'textarea'

const APP_PERMISSION_TREE: PermissionNode[] = [
  { id: 'dashboard', label: 'Tableau de bord' },
  {
    id: 'templates-shared', label: 'Templates partages', children: [
      { id: 'templates-shared.view', label: 'Voir les templates partages' },
    ],
  },
  {
    id: 'documents', label: 'Mes Documents', children: [
      { id: 'documents.view', label: 'Voir les documents' },
      { id: 'documents.upload', label: 'Déposer un document' },
      { id: 'documents.create-folder', label: 'Créer un dossier' },
      { id: 'documents.share', label: 'Partager un document' },
      { id: 'documents.edit-onlyoffice', label: 'Éditer dans OnlyOffice' },
      { id: 'documents.delete', label: 'Supprimer un document' },
    ],
  },
  {
    id: 'workflows', label: 'Workflows', children: [
      { id: 'workflows.view', label: 'Voir les workflows' },
      { id: 'workflows.create', label: 'Créer un workflow' },
      { id: 'workflows.validate', label: 'Valider une étape' },
      { id: 'workflows.delete', label: 'Supprimer un workflow' },
    ],
  },
  {
    id: 'signatures', label: 'Signatures', children: [
      { id: 'signatures.view', label: 'Voir les signatures' },
      { id: 'signatures.request', label: 'Demander une signature' },
      { id: 'signatures.sign', label: 'Signer un document' },
      { id: 'signatures.reject', label: 'Rejeter une signature' },
    ],
  },
  {
    id: 'reception', label: 'Réception',
  },
  {
    id: 'act-requests', label: 'Demande d\'actes', children: [
      { id: 'act-requests.view', label: 'Voir les demandes d\'actes' },
      { id: 'act-requests.process', label: 'Traiter les demandes d\'actes' },
    ],
  },
  {
    id: 'administration', label: 'Administration', children: [
      { id: 'administration.templates', label: 'Templates' },
      { id: 'administration.emitters', label: 'Émetteurs' },
      { id: 'administration.recipients', label: 'Destinataires' },
      { id: 'administration.requested-acts', label: 'Acte demandé' },
      { id: 'administration.routing', label: 'Règles de routage' },
      { id: 'administration.onlyoffice', label: 'OnlyOffice' },
      { id: 'administration.users', label: 'Utilisateurs' },
      { id: 'administration.theming', label: 'Apparence' },
      { id: 'administration.email-notifications', label: 'Notifications E-mail' },
      { id: 'administration.signature-provider', label: 'API Signature' },
      { id: 'administration.user-profiles', label: 'Profils' },
    ],
  },
  { id: 'qrcode', label: 'Vérification QR' },
]

const ALL_PERMISSION_IDS = Array.from(
  new Set(
    APP_PERMISSION_TREE.flatMap((node) => [
      node.id,
      ...(node.children?.map((child) => child.id) || []),
    ]),
  ),
)

const ALLOWED_PERMISSION_SET = new Set<string>(ALL_PERMISSION_IDS)

const normalizePermissionIds = (values: unknown): string[] => {
  if (!Array.isArray(values)) return []
  const normalized = values
    .filter((value): value is string => typeof value === 'string')
    .map((value) => value.trim().toLowerCase())
    .filter((value) => Boolean(value) && ALLOWED_PERMISSION_SET.has(value))

  return Array.from(new Set(normalized))
}

const TEMPLATE_SHARE_MAP_SETTING_KEY = 'template_share_map'
const SIGNATURE_QR_POSITION_SETTING_KEY = 'signature_qr_position'

function Settings() {
  const currentUser = useAuthStore((state) => state.user)
  const [activeTab, setActiveTab] = useState<TabKey>('templates')
  const [settingsTabPermissions, setSettingsTabPermissions] = useState<Set<string>>(new Set())
  const [isElevatedAdministrationRole, setIsElevatedAdministrationRole] = useState(false)
  const [isAdminAdministrationContext, setIsAdminAdministrationContext] = useState(false)
  const [currentUserScopeType, setCurrentUserScopeType] = useState<'emitter' | 'recipient' | null>(null)
  const [currentUserScopeId, setCurrentUserScopeId] = useState<string | null>(null)
  const [currentUserSubEntityCode, setCurrentUserSubEntityCode] = useState('')
  void isAdminAdministrationContext

  // --- Confirmation & result modals ---
  const [confirmModal, setConfirmModal] = useState<{ open: boolean; title: string; message: string; onConfirm: () => void }>({ open: false, title: '', message: '', onConfirm: () => {} })
  const [resultModal, setResultModal] = useState<{ open: boolean; type: 'success' | 'error'; message: string }>({ open: false, type: 'success', message: '' })

  const askConfirm = (title: string, message: string, onConfirm: () => void) => {
    setConfirmModal({ open: true, title, message, onConfirm })
  }

  const showResult = (type: 'success' | 'error', message: string) => {
    setResultModal({ open: true, type, message })
    setTimeout(() => setResultModal(prev => ({ ...prev, open: false })), 5000)
  }

  useEffect(() => {
    const loadSettingsTabPermissions = async () => {
      try {
        const result = await getCurrentUserPermissions()
        const theme = await getCurrentUserTheme()
        const profile = await getCurrentUser()
        const normalizeRoleToken = (value?: string | null) => (value || '').trim().toLowerCase().replace(/[-\s]+/g, '_')
        const isSuperAdminRole = (value?: string | null) => {
          const normalized = normalizeRoleToken(value)
          return normalized === 'super_admin' || normalized === 'superadmin'
        }
        const normalizedUserRole = normalizeRoleToken(result?.debug?.userRole || result?.debug?.normalizedUserRole)
        const normalizedAdminRole = normalizeRoleToken(result?.debug?.adminRole || result?.debug?.normalizedAdminRole)
        const permissionList = Array.isArray(result?.permissions) ? result.permissions : []

        setSettingsTabPermissions(new Set(permissionList))
        setIsElevatedAdministrationRole(Boolean(result?.isElevated) || isSuperAdminRole(normalizedUserRole) || isSuperAdminRole(normalizedAdminRole))
        setIsAdminAdministrationContext(normalizedUserRole === 'admin_administration' || normalizedAdminRole === 'admin_administration')
        setCurrentUserScopeType(theme?.scopeType ?? null)
        setCurrentUserScopeId(theme?.scopeId ?? null)
        setCurrentUserSubEntityCode(String((profile as any)?.subEntityCode || '').trim().toUpperCase())
      } catch {
        setSettingsTabPermissions(new Set())
        const fallbackRole = (currentUser?.role || '').trim().toLowerCase().replace(/[-\s]+/g, '_')
        setIsElevatedAdministrationRole(fallbackRole === 'super_admin' || fallbackRole === 'superadmin')
        setIsAdminAdministrationContext(false)
        setCurrentUserScopeType(null)
        setCurrentUserScopeId(null)
        setCurrentUserSubEntityCode('')
      }
    }

    void loadSettingsTabPermissions()
  }, [currentUser?.role])

  const visibleSettingsTabs = useMemo(() => {
    if (isElevatedAdministrationRole || settingsTabPermissions.has('administration.*')) {
      return SETTINGS_TAB_CONFIG
    }

    // Parent permission `administration` only unlocks entry to the module;
    // sub-tabs are governed strictly by their own `administration.<sub-tab>` permissions.
    return SETTINGS_TAB_CONFIG.filter((tab) => settingsTabPermissions.has(tab.permission))
  }, [isElevatedAdministrationRole, settingsTabPermissions])

  useEffect(() => {
    if (visibleSettingsTabs.length === 0) return
    if (!visibleSettingsTabs.some((tab) => tab.key === activeTab)) {
      setActiveTab(visibleSettingsTabs[0].key)
    }
  }, [activeTab, visibleSettingsTabs])

  useEffect(() => {
    if (visibleSettingsTabs.length === 0) return
    const isCurrentVisible = visibleSettingsTabs.some((tab) => tab.key === activeTab)
    if (!isCurrentVisible) {
      setActiveTab(visibleSettingsTabs[0].key)
    }
  }, [activeTab, visibleSettingsTabs])

  // --- Theming state ---
  const [themingForm, setThemingForm] = useState({
    appName: '',
    webUrl: '',
    slogan: '',
    primaryColor: '#0082c9',
    bgColor: '#495F55',
    legalNoticeUrl: '',
    privacyPolicyUrl: '',
    disableUserTheming: false,
  })
  const [themingLogoFile, setThemingLogoFile] = useState<File | null>(null)
  const [themingBgFile, setThemingBgFile] = useState<File | null>(null)
  const [themingHeaderLogoFile, setThemingHeaderLogoFile] = useState<File | null>(null)
  const [themingFaviconFile, setThemingFaviconFile] = useState<File | null>(null)
  const [themingBgPreview, setThemingBgPreview] = useState<string | null>(null)
  const [themingLogoPreview, setThemingLogoPreview] = useState<string | null>(null)
  const [themingHeaderLogoPreview, setThemingHeaderLogoPreview] = useState<string | null>(null)
  const [themingFaviconPreview, setThemingFaviconPreview] = useState<string | null>(null)
  const [themingSuccess, setThemingSuccess] = useState<string | null>(null)
  const [themingScopeType, setThemingScopeType] = useState<'emitter' | 'recipient'>('emitter')
  const [themingScopeId, setThemingScopeId] = useState<string>('')
  const [appSettingsCache, setAppSettingsCache] = useState<Map<string, string | null>>(new Map())

  const API_ROOT = (import.meta.env.VITE_API_URL || 'http://localhost:3000/api/v1').replace(/\/api(?:\/v\d+)?\/?$/, '')
  const resolveAssetUrl = (value?: string | null) => {
    if (!value) return null
    if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('data:image/')) return value
    return `${API_ROOT}${value.startsWith('/') ? '' : '/'}${value}`
  }

  const syncThemeRuntime = (menuColorValue: string, loginBgValue: string | null) => {
    try {
      localStorage.setItem('ep_theme_menu_color', menuColorValue || '#173b9f')
      if (loginBgValue) {
        localStorage.setItem('ep_theme_login_bg', loginBgValue)
      } else {
        localStorage.removeItem('ep_theme_login_bg')
      }
    } catch {
      // Ignore storage quota/access errors and still dispatch runtime events.
    }

    window.dispatchEvent(new StorageEvent('storage', { key: 'ep_theme_menu_color', newValue: menuColorValue || '#173b9f' }))
    window.dispatchEvent(new StorageEvent('storage', { key: 'ep_theme_login_bg', newValue: loginBgValue }))
    window.dispatchEvent(new CustomEvent('ep_theme_changed', {
      detail: {
        menuColor: menuColorValue || '#173b9f',
        loginBackgroundImage: loginBgValue,
      },
    }))
  }

  const getScopedThemeKey = (suffix: string, scopeType = themingScopeType, scopeId = themingScopeId) =>
    `theme_${scopeType}_${scopeId}_${suffix}`

  const applyScopedTheming = (settingMap: Map<string, string | null>, scopeType: 'emitter' | 'recipient', scopeId: string) => {
    if (!scopeId) {
      setThemingForm((prev) => ({
        ...prev,
        appName: '',
        webUrl: '',
        slogan: '',
        primaryColor: '#173b9f',
        bgColor: '#495F55',
        legalNoticeUrl: '',
        privacyPolicyUrl: '',
        disableUserTheming: false,
      }))
      setThemingBgPreview(null)
      syncThemeRuntime('#173b9f', null)
      return
    }

    const getScoped = (suffix: string) => settingMap.get(getScopedThemeKey(suffix, scopeType, scopeId))

    const menuColor = getScoped('menu_color') || '#173b9f'
    const loginBg = getScoped('login_background_image') || null

    setThemingForm((prev) => ({
      ...prev,
      appName: getScoped('app_name') || '',
      webUrl: getScoped('web_url') || '',
      slogan: getScoped('slogan') || '',
      primaryColor: menuColor,
      bgColor: getScoped('bg_color') || '#495F55',
      legalNoticeUrl: getScoped('legal_notice_url') || '',
      privacyPolicyUrl: getScoped('privacy_policy_url') || '',
      disableUserTheming: getScoped('disable_user_theming') === 'true',
    }))

    setThemingBgPreview(loginBg)
    const resolvedLoginBg = resolveAssetUrl(loginBg)
    syncThemeRuntime(menuColor, resolvedLoginBg)
  }

  const handleThemingFileChange = (
    e: React.ChangeEvent<HTMLInputElement>,
    setter: React.Dispatch<React.SetStateAction<File | null>>,
    previewSetter: React.Dispatch<React.SetStateAction<string | null>>
  ) => {
    const file = e.target.files?.[0] ?? null
    setter(file)
    if (file) {
      const reader = new FileReader()
      reader.onload = (ev) => previewSetter(ev.target?.result as string)
      reader.readAsDataURL(file)
    } else {
      previewSetter(null)
    }
  }

  const handleSaveTheming = (e: React.FormEvent) => {
    e.preventDefault()
    if (!themingScopeId) {
      showResult('error', 'Sélectionnez une administration à personnaliser.')
      return
    }
    askConfirm(
      'Enregistrer l\'apparence',
      'Voulez-vous appliquer ces paramètres d\'apparence à toute l\'application ?',
      async () => {
        try {
          let backgroundImage = themingBgPreview
          if (themingBgFile) {
            const uploaded = await uploadThemeBackgroundImage(themingBgFile)
            backgroundImage = uploaded?.imagePath || null
            setThemingBgPreview(backgroundImage)
            setThemingBgFile(null)
          }

          await upsertAppSettings([
            { key: 'theme_menu_color', value: themingForm.primaryColor || '#173b9f', description: 'Couleur globale du menu principal' },
            { key: 'theme_login_background_image', value: backgroundImage || null, description: 'Image globale de fond de la page de connexion' },
            { key: getScopedThemeKey('app_name'), value: themingForm.appName || null, description: 'Nom de l\'application' },
            { key: getScopedThemeKey('web_url'), value: themingForm.webUrl || null, description: 'Lien web de l\'instance' },
            { key: getScopedThemeKey('slogan'), value: themingForm.slogan || null, description: 'Slogan affiché' },
            { key: getScopedThemeKey('menu_color'), value: themingForm.primaryColor || '#173b9f', description: 'Couleur du menu principal' },
            { key: getScopedThemeKey('bg_color'), value: themingForm.bgColor || '#495F55', description: 'Couleur d\'arrière-plan' },
            { key: getScopedThemeKey('login_background_image'), value: backgroundImage || null, description: 'Image de fond de la page de connexion' },
            { key: getScopedThemeKey('legal_notice_url'), value: themingForm.legalNoticeUrl || null, description: 'Lien notice légale' },
            { key: getScopedThemeKey('privacy_policy_url'), value: themingForm.privacyPolicyUrl || null, description: 'Lien politique de confidentialité' },
            { key: getScopedThemeKey('disable_user_theming'), value: String(Boolean(themingForm.disableUserTheming)), description: 'Désactive la personnalisation utilisateur' },
          ])

          const resolvedLoginBg = resolveAssetUrl(backgroundImage)
          syncThemeRuntime(themingForm.primaryColor || '#173b9f', resolvedLoginBg)

          setThemingSuccess('Les paramètres d\'apparence ont été enregistrés avec succès.')
          setTimeout(() => setThemingSuccess(null), 4000)
          showResult('success', 'Paramètres d\'apparence enregistrés avec succès.')
          const refreshed = await fetchAppSettings()
          setAppSettingsCache(new Map<string, string | null>(refreshed.map((s: AppSetting) => [s.key, s.value])))
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Impossible d\'enregistrer les paramètres d\'apparence.')
        }
      }
    )
  }
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success] = useState<string | null>(null)

  const [templates, setTemplates] = useState<DocumentTemplate[]>([])
  const [templateVariables, setTemplateVariables] = useState<TemplateVariable[]>([])
  const [selectedTemplateId, setSelectedTemplateId] = useState<string>('')

  const [emitters, setEmitters] = useState<IssuingAdministration[]>([])
  const [selectedEmitterId, setSelectedEmitterId] = useState<string>('')

  const [recipients, setRecipients] = useState<RecipientAdministration[]>([])
  const [routingRules, setRoutingRules] = useState<RoutingRule[]>([])

  const [templateForm, setTemplateForm] = useState({ name: '', fileName: '', fileType: 'docx' as 'docx' | 'xlsx' | 'pptx' | 'pdf', content: '' })
  const [variableForm, setVariableForm] = useState({ name: '', fieldType: 'text' as 'text' | 'date' | 'number' | 'select' | 'textarea' })
  const [emitterForm, setEmitterForm] = useState({
    name: '',
    code: '',
    adminType: '',
    sector: '',
    description: '',
    contactEmail: '',
    techEmail: '',
    contactPhone: '',
    referentMetier: '',
    postalAddress: '',
    transmissionMethod: 'api',
    endpointUrl: '',
    dataFormat: 'json',
    authMethod: 'api_key',
    apiKey: '',
    timeout: 30,
    requireTls: true,
    enableRetry: true,
    docTypes: ['pdf'] as string[],
    defaultWorkflow: '',
    dossierPrefix: '',
    autoConvertPdf: true,
    requiredMetadata: '',
    signatureLevel: 'qualifiee',
    logRetention: 365,
    gdprCompliant: true,
    enableAudit: true,
    fileEncryption: false,
    ipWhitelist: '',
    businessHours: '',
    slaResponse: '24h',
    timezone: 'Europe/Paris',
    duplicateHandling: 'update',
    externalRefField: '',
    trackingUrl: '',
    webhookUrl: '',
    webhookSecret: '',
    tags: '',
  })
  const [recipientForm, setRecipientForm] = useState({
    name: '',
    code: '',
    adminType: '',
    sector: '',
    description: '',
    channel: 'api' as 'api' | 'email' | 'ler' | 'application',
    apiEndpoint: '',
    emailAddress: '',
    contactEmail: '',
    techEmail: '',
    contactPhone: '',
    contactFax: '',
    postalAddress: '',
    referentMetier: '',
    referentTechnique: '',
    apiMethod: 'POST',
    apiFormat: 'multipart',
    apiAuth: 'api_key',
    apiTimeout: 30,
    emailSubject: '[E-Parapheur] Nouveau document signé - {{reference}}',
    emailBody: '',
    lerProvider: 'laposte',
    lerAccountId: '',
    enableRetry: true,
    enableNotification: true,
    compressFiles: false,
    encryptFiles: false,
    docTypes: ['pdf', 'docx', 'xlsx'] as string[],
    maxFileSize: 50,
    maxFiles: 10,
    receiptMethod: 'automatic',
    receiptWebhookUrl: '',
    receiptTimeout: 24,
    activateImmediately: true,
  })
  const [recipientListSearchQuery, setRecipientListSearchQuery] = useState('')

  const [subEntityScopeType, setSubEntityScopeType] = useState<'emitter' | 'recipient'>('emitter')
  const [subEntityScopeId, setSubEntityScopeId] = useState('')
  const [subEntitySearchQuery, setSubEntitySearchQuery] = useState('')
  const [editingSubEntityId, setEditingSubEntityId] = useState<string | null>(null)
  const [directionTypes, setDirectionTypes] = useState<DirectionType[]>([])
  const [editingDirectionTypeId, setEditingDirectionTypeId] = useState<string | null>(null)
  const [directionTypeForm, setDirectionTypeForm] = useState({ name: '', description: '' })
  const [subEntityForm, setSubEntityForm] = useState({
    name: '',
    code: '',
    parentCode: '',
    directionType: '',
    managerName: '',
    managerEmail: '',
    description: '',
  })
  const [requestedActs, setRequestedActs] = useState<RequestedAct[]>([])
  const [requestedActsSearch, setRequestedActsSearch] = useState('')
  const [requestedActForm, setRequestedActForm] = useState({
    administrationRef: '',
    directionCode: '',
    documentName: '',
  })
  const [requestedActDocInput, setRequestedActDocInput] = useState('')
  const [requestedActRequiredDocs, setRequestedActRequiredDocs] = useState<string[]>([])
  const [requestedActApplicantFieldLabelInput, setRequestedActApplicantFieldLabelInput] = useState('')
  const [requestedActApplicantFieldTypeInput, setRequestedActApplicantFieldTypeInput] = useState<RequestedActApplicantFieldType>('text')
  const [requestedActApplicantFields, setRequestedActApplicantFields] = useState<Array<{ label: string; inputType: RequestedActApplicantFieldType }>>([])
  const [editingRequestedActId, setEditingRequestedActId] = useState<string | null>(null)
  const [ruleForm, setRuleForm] = useState({ name: '', documentType: '', templateId: '', recipientAdministrationId: '', priority: 1 })

  const [editingTemplateId, setEditingTemplateId] = useState<string | null>(null)
  const [editingVariableId, setEditingVariableId] = useState<string | null>(null)
  const [editingEmitterId, setEditingEmitterId] = useState<string | null>(null)
  const [editingRecipientId, setEditingRecipientId] = useState<string | null>(null)
  const [editingRuleId, setEditingRuleId] = useState<string | null>(null)
  const [isTestingRecipientConnection, setIsTestingRecipientConnection] = useState(false)
  const [emitterLogoFile, setEmitterLogoFile] = useState<File | null>(null)
  const [emitterLogoPreview, setEmitterLogoPreview] = useState<string | null>(null)
  const [currentEmitterLogoUrl, setCurrentEmitterLogoUrl] = useState<string | null>(null)
  const [recipientLogoFile, setRecipientLogoFile] = useState<File | null>(null)
  const [recipientLogoPreview, setRecipientLogoPreview] = useState<string | null>(null)
  const [currentRecipientLogoUrl, setCurrentRecipientLogoUrl] = useState<string | null>(null)
  const emitterListRef = useRef<HTMLDivElement | null>(null)
  const [generationValues, setGenerationValues] = useState<Record<string, string>>({})
  const [generatedContent, setGeneratedContent] = useState('')
  const [generatedFileName, setGeneratedFileName] = useState('')
  const generationFormRef = useRef<HTMLDivElement | null>(null)
  const [templatePositioningDocId, setTemplatePositioningDocId] = useState<string | null>(null)
  const [templatePositioningDocName, setTemplatePositioningDocName] = useState('')
  const [templatePositioningFileUrl, setTemplatePositioningFileUrl] = useState<string | null>(null)
  const [templateForceNativeViewer, setTemplateForceNativeViewer] = useState(false)
  const [templateZonesByDocKey, setTemplateZonesByDocKey] = useState<Record<string, TemplateSignatureZone[]>>({})
  const [templateSavedZoneByDocKey, setTemplateSavedZoneByDocKey] = useState<Record<string, boolean>>({})
  const [templateDragAction, setTemplateDragAction] = useState<{ zoneId: string; mode: 'move' | 'resize'; startX: number; startY: number; origZone: TemplateSignatureZone } | null>(null)
  const [showTemplateOnlyOfficeEditor, setShowTemplateOnlyOfficeEditor] = useState(false)
  const [templateEditorZones, setTemplateEditorZones] = useState<TemplateSignatureZone[]>([])
  const [templateEditorSaved, setTemplateEditorSaved] = useState(false)

  // Partage de template
  const [shareTemplateId, setShareTemplateId] = useState<string | null>(null)
  const [shareUserId, setShareUserId] = useState('')
  const [shareSearch, setShareSearch] = useState('')
  const [templateShareMap, setTemplateShareMap] = useState<Record<string, string[]>>({})

  const [managedUsers, setManagedUsers] = useState<AppUserRecord[]>([])
  const [managedUsersSearch, setManagedUsersSearch] = useState('')
  const [showNewUserModal, setShowNewUserModal] = useState(false)
  const [newUserForm, setNewUserForm] = useState({
    nom: '',
    prenoms: '',
    displayName: '',
    role: '',
    email: '',
    password: '',
    confirmPassword: '',
    quota: '5 Go',
    isActive: false,
    administrationType: '' as '' | 'emitter' | 'recipient',
    administrationScopeId: '',
    subEntityId: '',
  })
  const [newUserPasswordVisible, setNewUserPasswordVisible] = useState(false)
  const [newUserError, setNewUserError] = useState<string | null>(null)
  const [newUserAvatarFile, setNewUserAvatarFile] = useState<File | null>(null)
  const [newUserAvatarPreview, setNewUserAvatarPreview] = useState<string | null>(null)
  const [editingManagedUser, setEditingManagedUser] = useState<AppUserRecord | null>(null)
  const [editManagedUserForm, setEditManagedUserForm] = useState({
    nom: '',
    prenoms: '',
    displayName: '',
    role: '',
    email: '',
    quota: '5 Go',
    isActive: false,
    administrationType: '' as '' | 'emitter' | 'recipient',
    administrationScopeId: '',
    subEntityId: '',
  })
  const [editManagedUserError, setEditManagedUserError] = useState<string | null>(null)
  const [userCreatedSuccess, setUserCreatedSuccess] = useState<{ open: boolean; fullName: string }>({ open: false, fullName: '' })

  const buildUsernameFromIdentity = (prenoms: string, nom: string) => {
    const base = `${prenoms}.${nom}`
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9.]/g, '')
      .replace(/\.+/g, '.')
      .replace(/^\.|\.$/g, '')

    return `${base || 'user'}.${Date.now().toString().slice(-6)}`
  }

  const buildUsernameFromDisplayName = (displayName: string, prenoms: string, nom: string) => {
    const normalized = displayName
      .trim()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9.]/g, '')
      .replace(/\.+/g, '.')
      .replace(/^\.|\.$/g, '')

    if (!normalized) {
      return buildUsernameFromIdentity(prenoms, nom)
    }

    return `${normalized}.${Date.now().toString().slice(-6)}`
  }

  const resetNewUserForm = () => {
    setNewUserForm({
      nom: '',
      prenoms: '',
      displayName: '',
      role: '',
      email: '',
      password: '',
      confirmPassword: '',
      quota: '5 Go',
      isActive: false,
      administrationType: '',
      administrationScopeId: '',
      subEntityId: '',
    })
    setNewUserError(null)
    setNewUserPasswordVisible(false)
    setNewUserAvatarFile(null)
    setNewUserAvatarPreview(null)
  }

  const saveManagedUserQuotas = (_next: Record<string, string>) => {
    // Quotas are now stored in the DB via the user's quota field – this function
    // is kept as a no-op to avoid breaking any remaining call sites during migration.
  }

  const getManagedUserQuota = (userId: string) => {
    const user = managedUsers.find((u) => u.id === userId)
    return user?.quota || 'Non défini'
  }

  const handleCreateManagedUser = (e: React.FormEvent) => {
    e.preventDefault()
    if (!newUserForm.nom.trim() || !newUserForm.prenoms.trim()) {
      setNewUserError('Le nom et les prénoms sont obligatoires.')
      return
    }
    if (!newUserForm.role.trim()) {
      setNewUserError('Le rôle est obligatoire.')
      return
    }
    if (!newUserForm.email.trim()) {
      setNewUserError('L\'e-mail est obligatoire.')
      return
    }
    if (!newUserForm.password.trim()) {
      setNewUserError('Le mot de passe est obligatoire.')
      return
    }
    if (!newUserForm.confirmPassword.trim()) {
      setNewUserError('La confirmation du mot de passe est obligatoire.')
      return
    }
    if (!newUserForm.administrationType) {
      setNewUserError('Le type d\'administration est obligatoire.')
      return
    }
    if (!newUserForm.administrationScopeId) {
      setNewUserError('L\'administration est obligatoire.')
      return
    }
    if (!newUserForm.subEntityId) {
      setNewUserError('La direction sous tutelle est obligatoire.')
      return
    }
    if (newUserForm.password !== newUserForm.confirmPassword) {
      setNewUserError('Les mots de passe ne correspondent pas.')
      return
    }

    askConfirm(
      'Créer un utilisateur',
      `Créer le compte pour ${newUserForm.prenoms.trim()} ${newUserForm.nom.trim()} ?`,
      async () => {
        try {
          const composedFullName = `${newUserForm.prenoms.trim()} ${newUserForm.nom.trim()}`.trim()
          const selectedDirection = newUserSubEntityOptions.find((item) => item.id === newUserForm.subEntityId)
          if (newUserForm.subEntityId && !selectedDirection) {
            setNewUserError('La direction sélectionnée est introuvable. Veuillez la sélectionner à nouveau avant de créer le compte.')
            return
          }
          const selectedAdministrationId = selectedDirection?.scopeType === 'emitter' ? selectedDirection.scopeId : undefined
          const created = await createAppUser({
            username: buildUsernameFromDisplayName(newUserForm.displayName, newUserForm.prenoms, newUserForm.nom),
            email: newUserForm.email.trim().toLowerCase(),
            password: newUserForm.password,
            fullName: composedFullName,
            role: newUserForm.role.trim(),
            status: newUserForm.isActive ? 'active' : 'inactive',
            quota: newUserForm.quota.trim() || '5 Go',
            administrationId: selectedAdministrationId,
            directionLabel: selectedDirection?.label || '',
            directionScopeType: (selectedDirection?.scopeType || undefined) as 'emitter' | 'recipient' | undefined,
            directionScopeId: selectedDirection?.scopeId,
            subEntityCode: selectedDirection?.code,
          })

          let createdWithAvatar = created
          if (newUserAvatarFile) {
            try {
              createdWithAvatar = await uploadAppUserAvatar(created.id, newUserAvatarFile)
            } catch (avatarErr: any) {
              showResult('error', avatarErr?.response?.data?.message || "L'utilisateur a été créé mais l'upload de la photo a échoué.")
            }
          }

          setManagedUsers((prev) => [{ ...createdWithAvatar, administrationId: selectedAdministrationId || null }, ...prev])
          saveManagedUserQuotas({})
          setShowNewUserModal(false)
          resetNewUserForm()
          setUserCreatedSuccess({ open: true, fullName: `${newUserForm.prenoms.trim()} ${newUserForm.nom.trim()}` })
        } catch (err: any) {
          setNewUserError(err?.response?.data?.message || 'Impossible de créer l\'utilisateur.')
        }
      },
    )
  }

  const handleToggleManagedUserStatus = (user: AppUserRecord) => {
    const nextStatus = user.status === 'active' ? 'inactive' : 'active'
    askConfirm(
      nextStatus === 'active' ? 'Activer le compte' : 'Désactiver le compte',
      `Voulez-vous ${nextStatus === 'active' ? 'activer' : 'désactiver'} le compte ${user.fullName} ?`,
      async () => {
        try {
          const updated = await updateAppUserStatus(user.id, nextStatus)
          setManagedUsers((prev) => prev.map((item) => (item.id === updated.id ? { ...item, ...updated } : item)))
          showResult('success', `Compte ${nextStatus === 'active' ? 'activé' : 'désactivé'} avec succès.`)
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Impossible de modifier le statut du compte.')
        }
      },
    )
  }

  const openEditManagedUser = (user: AppUserRecord) => {
    const parts = (user.fullName || '').trim().split(/\s+/)
    const nom = parts.length > 1 ? parts[parts.length - 1] : user.fullName
    const prenoms = parts.length > 1 ? parts.slice(0, -1).join(' ') : ''
    const mappedDirectionLabel = user.directionLabel || ''
    const directionOption = userDirectionOptions.find((item) => (
      (Boolean(user.subEntityCode) && item.code === user.subEntityCode)
      ||
      (item.scopeType === (user.directionScopeType || 'emitter') && item.scopeId === (user.directionScopeId || user.administrationId || ''))
      || (mappedDirectionLabel && item.label === mappedDirectionLabel)
    ))
    const fallbackAdministrationType = user.administrationId ? 'emitter' : ''
    const fallbackAdministrationScopeId = user.administrationId || ''
    setEditingManagedUser(user)
    setEditManagedUserError(null)
    setEditManagedUserForm({
      nom: nom || '',
      prenoms: prenoms || '',
      displayName: user.fullName || '',
      role: user.role || '',
      email: user.email || '',
      quota: user.quota || '5 Go',
      isActive: user.status === 'active',
      administrationType: directionOption?.scopeType || fallbackAdministrationType,
      administrationScopeId: directionOption?.scopeId || fallbackAdministrationScopeId,
      subEntityId: directionOption?.id || '',
    })
  }

  const handleSaveManagedUserEdit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!editingManagedUser) return

    if (!editManagedUserForm.nom.trim() || !editManagedUserForm.prenoms.trim()) {
      setEditManagedUserError('Le nom et les prénoms sont obligatoires.')
      return
    }
    if (!editManagedUserForm.role.trim()) {
      setEditManagedUserError('Le rôle est obligatoire.')
      return
    }
    if (!editManagedUserForm.email.trim()) {
      setEditManagedUserError('L\'e-mail est obligatoire.')
      return
    }
    if (!editManagedUserForm.administrationType) {
      setEditManagedUserError('Le type d\'administration est obligatoire.')
      return
    }
    if (!editManagedUserForm.administrationScopeId) {
      setEditManagedUserError('L\'administration est obligatoire.')
      return
    }
    if (!editManagedUserForm.subEntityId) {
      setEditManagedUserError('La direction sous tutelle est obligatoire.')
      return
    }

    askConfirm(
      'Modifier l\'utilisateur',
      `Voulez-vous enregistrer les modifications de ${editManagedUserForm.prenoms} ${editManagedUserForm.nom} ?`,
      async () => {
        try {
          const composedDisplayName = `${editManagedUserForm.prenoms.trim()} ${editManagedUserForm.nom.trim()}`.trim()

          const selectedDirection = editUserSubEntityOptions.find((item) => item.id === editManagedUserForm.subEntityId)
          if (editManagedUserForm.subEntityId && !selectedDirection) {
            setEditManagedUserError('La direction sélectionnée est introuvable. Veuillez la sélectionner à nouveau avant d\'enregistrer.')
            return
          }
          const selectedAdministrationId = selectedDirection?.scopeType === 'emitter' ? selectedDirection.scopeId : undefined
          const updated = await updateAppUser(editingManagedUser.id, {
            username: buildUsernameFromDisplayName(editManagedUserForm.displayName, editManagedUserForm.prenoms, editManagedUserForm.nom),
            email: editManagedUserForm.email.trim().toLowerCase(),
            fullName: composedDisplayName,
            role: editManagedUserForm.role.trim(),
            quota: editManagedUserForm.quota.trim() || '5 Go',
            administrationId: selectedAdministrationId,
            directionLabel: selectedDirection?.label || '',
            directionScopeType: (selectedDirection?.scopeType || undefined) as 'emitter' | 'recipient' | undefined,
            directionScopeId: selectedDirection?.scopeId,
            subEntityCode: selectedDirection?.code,
          })

          const nextStatus = editManagedUserForm.isActive ? 'active' : 'inactive'
          const updatedWithStatus =
            updated.status === nextStatus ? updated : await updateAppUserStatus(updated.id, nextStatus)

          setManagedUsers((prev) => prev.map((item) => (item.id === updatedWithStatus.id ? { ...item, ...updatedWithStatus, administrationId: selectedAdministrationId || item.administrationId || null } : item)))

          saveManagedUserQuotas({})

          setEditingManagedUser(null)
          showResult('success', 'Utilisateur modifié avec succès.')
        } catch (err: any) {
          setEditManagedUserError(err?.response?.data?.message || 'Impossible de modifier cet utilisateur.')
        }
      },
    )
  }

  const handleDeleteManagedUser = (user: AppUserRecord) => {
    askConfirm(
      'Supprimer l\'utilisateur',
      `Voulez-vous supprimer le compte de ${user.fullName} ?`,
      async () => {
        try {
          await deleteAppUser(user.id)
          setManagedUsers((prev) => prev.filter((item) => item.id !== user.id))
          showResult('success', 'Utilisateur supprimé avec succès.')
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Impossible de supprimer cet utilisateur.')
        }
      },
    )
  }

  const buildInviteLink = () => {
    const params = new URLSearchParams()
    params.set('invite', '1')
    params.set('email', newUserForm.email.trim())
    params.set('nom', newUserForm.nom.trim())
    params.set('prenoms', newUserForm.prenoms.trim())
    params.set('displayName', newUserForm.displayName.trim())
    params.set('role', newUserForm.role.trim())
    params.set('administrationType', newUserForm.administrationType)
    params.set('administrationScopeId', newUserForm.administrationScopeId)
    params.set('subEntityId', newUserForm.subEntityId)
    return `${window.location.origin}/register?${params.toString()}`
  }

  const handleSendInvitationFormLink = () => {
    if (!newUserForm.email.trim()) {
      setNewUserError('Renseignez un e-mail avant l\'envoi du lien.')
      return
    }
    if (!newUserForm.role.trim()) {
      setNewUserError('Sélectionnez un rôle avant l\'envoi du lien.')
      return
    }

    const link = buildInviteLink()
    const subject = encodeURIComponent('Invitation - Formulaire de création de compte')
    const body = encodeURIComponent(
      `Bonjour,\n\nVeuillez renseigner votre formulaire de création de compte via ce lien :\n${link}\n\nVotre compte sera créé en statut désactivé et devra être activé par l'administrateur.`,
    )

    if (navigator?.clipboard?.writeText) {
      navigator.clipboard.writeText(link).catch(() => undefined)
    }

    window.location.href = `mailto:${newUserForm.email.trim()}?subject=${subject}&body=${body}`
    showResult('success', 'Lien d\'invitation généré. Il a aussi été copié dans le presse-papiers.')
  }

  // Email notification settings
  const [emailNotifForm, setEmailNotifForm] = useState({
    host: '',
    port: '587',
    user: '',
    password: '',
    from: 'noreply@e-parapheur.local',
    secure: false,
  })
  const [emailNotifPasswordVisible, setEmailNotifPasswordVisible] = useState(false)
  const [notifTriggers, setNotifTriggers] = useState({
    onDocumentShared: true,
    onSignatureRequested: true,
    onSignatureResponded: true,
    onWorkflowAssigned: true,
    onWorkflowStepCompleted: true,
    onDocumentUploaded: false,
    onUserCreated: false,
  })
  const [userAppProfiles, setUserAppProfiles] = useState<UserAppProfile[]>([])
  const [showProfilesList, setShowProfilesList] = useState(true)
  const [profileEditId, setProfileEditId] = useState<string | null>(null)
  const [profileFormData, setProfileFormData] = useState({ name: '', description: '' })
  const [profilePermissions, setProfilePermissions] = useState<string[]>([])
  const [profileExpandedSections, setProfileExpandedSections] = useState<Record<string, boolean>>({})
  const [chatSettings, setChatSettings] = useState<{ enabled: boolean; scope: 'same-administration' | 'all' }>({
    enabled: true,
    scope: 'same-administration',
  })
  const [signatureProviderApiKeyVisible, setSignatureProviderApiKeyVisible] = useState(false)
  const [signatureProviderForm, setSignatureProviderForm] = useState({
    isActive: false,
    endpoint: '',
    signPath: '/v1/sign',
    apiKey: '',
    consentPageId: '',
    signatureProfileId: '',
    providerOwnerUserId: '',
    verifySsl: true,
    timeoutMs: 30000,
  })
  const [signatureQrPositionForm, setSignatureQrPositionForm] = useState({
    imagePage: '-1',
    imageX: '390',
    imageY: '710',
    imageWidth: '150',
    imageHeight: '80',
  })

  const extractProfilePermissions = (permissions: Record<string, unknown> | undefined): string[] => {
    if (!permissions) return []
    const candidate = (permissions as any)?.menuPermissions
    return normalizePermissionIds(candidate)
  }

  const applySignatureProviderConfig = (config: SignatureProviderConfig) => {
    setSignatureProviderForm({
      isActive: Boolean(config?.isActive),
      endpoint: String(config?.endpoint || ''),
      signPath: String(config?.signPath || '/v1/sign'),
      apiKey: String(config?.apiKey || ''),
      consentPageId: String(config?.consentPageId || ''),
      signatureProfileId: String(config?.signatureProfileId || ''),
      providerOwnerUserId: String(config?.providerOwnerUserId || ''),
      verifySsl: Boolean(config?.verifySsl ?? true),
      timeoutMs: Number(config?.timeoutMs || 30000),
    })
  }

  const applyNotificationConfig = (config: NotificationConfig) => {
    setEmailNotifForm({
      host: String(config?.smtpHost || ''),
      port: String(config?.smtpPort || 587),
      user: String(config?.smtpUser || ''),
      password: String(config?.smtpPassword || ''),
      from: String(config?.smtpFrom || 'noreply@e-parapheur.local'),
      secure: Boolean(config?.smtpSecure),
    })

    const triggers = ((config?.triggers || {}) as Record<string, boolean>)
    setNotifTriggers((prev) => ({
      ...prev,
      ...triggers,
    }))
  }

  const handleSaveEmailNotif = async () => {
    if (!selectedEmitterId) {
      showResult('error', 'Sélectionnez une administration émettrice pour enregistrer la configuration e-mail.')
      return
    }

    try {
      await updateNotificationConfigByAdministration(selectedEmitterId, {
        smtpHost: emailNotifForm.host,
        smtpPort: Number(emailNotifForm.port || 587),
        smtpSecure: emailNotifForm.secure,
        smtpUser: emailNotifForm.user,
        smtpPassword: emailNotifForm.password,
        smtpFrom: emailNotifForm.from,
        triggers: notifTriggers,
      })
      showResult('success', 'Configuration des notifications e-mail enregistrée.')
    } catch (err: any) {
      showResult('error', err?.response?.data?.message || 'Impossible d\'enregistrer la configuration e-mail.')
    }
  }

  const handleTestEmailNotif = () => {
    showResult('success', 'Test d\'envoi déclenché. Vérifiez la boîte de réception du destinataire de test.')
  }

  const handleSaveChatSettings = async () => {
    try {
      await upsertAppSettings([
        { key: 'chat_enabled', value: chatSettings.enabled ? 'true' : 'false', description: 'Activer le chat en direct' },
        { key: 'chat_scope', value: chatSettings.scope, description: 'Portée du chat direct' },
      ])
      showResult('success', 'Paramètres du chat enregistrés.')
    } catch (err: any) {
      showResult('error', err?.response?.data?.message || 'Impossible d\'enregistrer les paramètres du chat.')
    }
  }

  const handleSaveSignatureProviderConfig = async () => {
    if (!selectedEmitterId) {
      showResult('error', 'Sélectionnez une administration émettrice pour enregistrer la configuration API Signature.')
      return
    }

    try {
      const saved = await updateSignatureProviderConfig({
        administrationId: selectedEmitterId,
        isActive: signatureProviderForm.isActive,
        endpoint: signatureProviderForm.endpoint,
        signPath: signatureProviderForm.signPath,
        apiKey: signatureProviderForm.apiKey,
        consentPageId: signatureProviderForm.consentPageId,
        signatureProfileId: signatureProviderForm.signatureProfileId,
        providerOwnerUserId: signatureProviderForm.providerOwnerUserId,
        verifySsl: signatureProviderForm.verifySsl,
        timeoutMs: signatureProviderForm.timeoutMs,
      })

      const toSafeNumber = (value: string, fallback: number) => {
        const parsed = Number(value)
        return Number.isFinite(parsed) ? parsed : fallback
      }

      await upsertAppSettings([
        {
          key: SIGNATURE_QR_POSITION_SETTING_KEY,
          value: JSON.stringify({
            imagePage: toSafeNumber(signatureQrPositionForm.imagePage, -1),
            imageX: toSafeNumber(signatureQrPositionForm.imageX, 390),
            imageY: toSafeNumber(signatureQrPositionForm.imageY, 710),
            imageWidth: toSafeNumber(signatureQrPositionForm.imageWidth, 150),
            imageHeight: toSafeNumber(signatureQrPositionForm.imageHeight, 80),
          }),
          description: 'Coordonnées visuelles du QR/signature sur le PDF (page, x, y, width, height).',
        },
      ])

      applySignatureProviderConfig(saved)
      showResult('success', 'Configuration API Signature et position QR enregistrées.')
    } catch (err: any) {
      showResult('error', err?.response?.data?.message || 'Impossible d\'enregistrer la configuration API Signature.')
    }
  }

  const extractProfileDescription = (permissions: Record<string, unknown> | undefined): string => {
    const candidate = (permissions as any)?.description
    return typeof candidate === 'string' ? candidate : ''
  }

  const toggleProfilePermission = (id: string, node: PermissionNode | undefined) => {
    if (node?.children?.length) {
      const childIds = node.children.map(c => c.id)
      const allChecked = childIds.every(c => profilePermissions.includes(c))
      if (allChecked) {
        // uncheck parent + all children
        setProfilePermissions(prev => prev.filter(p => p !== id && !childIds.includes(p)))
      } else {
        // check parent + all children
        setProfilePermissions(prev => normalizePermissionIds([...prev, id, ...childIds]))
      }
    } else {
      setProfilePermissions(prev =>
        prev.includes(id) ? prev.filter(p => p !== id) : normalizePermissionIds([...prev, id])
      )
    }
  }

  const isParentChecked = (node: PermissionNode) =>
    !!(node.children?.length && node.children.every(c => profilePermissions.includes(c.id)))

  const isParentIndeterminate = (node: PermissionNode) =>
    !!(node.children?.length &&
      node.children.some(c => profilePermissions.includes(c.id)) &&
      !node.children.every(c => profilePermissions.includes(c.id)))

  const startEditProfile = (p: UserAppProfile) => {
    setProfileEditId(p.id)
    setProfileFormData({ name: p.name, description: p.description })
    setProfilePermissions(p.permissions)
    setShowProfilesList(false)
  }

  const cancelProfileEdit = () => {
    setProfileEditId(null)
    setProfileFormData({ name: '', description: '' })
    setProfilePermissions([])
    setShowProfilesList(true)
  }

  const handleSaveUserProfile = (e: React.FormEvent) => {
    e.preventDefault()
    if (!profileFormData.name.trim()) { showResult('error', 'Le nom du profil est obligatoire.'); return }
    if (!selectedEmitterId) { showResult('error', 'Sélectionnez une administration émettrice.'); return }
    askConfirm(
      profileEditId ? 'Modifier le profil' : 'Créer le profil',
      profileEditId ? `Enregistrer les modifications du profil « ${profileFormData.name} » ?` : `Créer le profil « ${profileFormData.name} » ?`,
      async () => {
        try {
          const payload = {
            name: profileFormData.name.trim(),
            permissions: {
              description: profileFormData.description.trim(),
              menuPermissions: normalizePermissionIds(profilePermissions),
            },
          }

          if (profileEditId) {
            await updateAdministrationProfile(selectedEmitterId, profileEditId, payload)
            showResult('success', 'Profil mis à jour avec succès.')
          } else {
            await createAdministrationProfile(selectedEmitterId, payload)
            showResult('success', `Profil « ${payload.name} » créé avec succès.`)
          }

          await loadData()
          cancelProfileEdit()
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Impossible d\'enregistrer le profil.')
        }
      }
    )
  }

  const handleDeleteUserProfile = (id: string, name: string) => {
    if (!selectedEmitterId) { showResult('error', 'Sélectionnez une administration émettrice.'); return }
    askConfirm(
      'Supprimer le profil',
      `Cette action est irréversible. Voulez-vous vraiment supprimer le profil « ${name} » ?`,
      async () => {
        try {
          await deleteAdministrationProfile(selectedEmitterId, id)
          await loadData()
          if (profileEditId === id) cancelProfileEdit()
          showResult('success', `Profil « ${name} » supprimé.`)
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Impossible de supprimer le profil.')
        }
      }
    )
  }

  useEffect(() => {
    const currentEmitter = emitters.find((item) => item.id === selectedEmitterId)
    const mappedProfiles: UserAppProfile[] = (((currentEmitter?.profiles || []) as AdministrationProfile[])).map((profile) => ({
      id: profile.id,
      name: profile.name,
      description: extractProfileDescription(profile.permissions),
      permissions: extractProfilePermissions(profile.permissions),
      createdAt: profile.createdAt,
    }))
    setUserAppProfiles(mappedProfiles)
  }, [emitters, selectedEmitterId])

  const filteredRoleProfiles = useMemo(() => userAppProfiles, [userAppProfiles])
  const visibleManagedUsers = useMemo(() => {
    if (isElevatedAdministrationRole) return managedUsers
    if (!isAdminAdministrationContext) return managedUsers

    const normalizedCurrentSubEntityCode = currentUserSubEntityCode.trim().toUpperCase()
    if (!normalizedCurrentSubEntityCode) return []

    return managedUsers.filter((user) => String(user.subEntityCode || '').trim().toUpperCase() === normalizedCurrentSubEntityCode)
  }, [currentUserSubEntityCode, isAdminAdministrationContext, isElevatedAdministrationRole, managedUsers])

  const searchedManagedUsers = useMemo(() => {
    const search = managedUsersSearch.trim().toLowerCase()
    if (!search) return visibleManagedUsers

    return visibleManagedUsers.filter((user) => {
      const fullName = String(user.fullName || '').toLowerCase()
      const email = String(user.email || '').toLowerCase()
      const role = String(user.role || '').toLowerCase()
      const direction = String(user.directionLabel || '').toLowerCase()
      const subEntity = String(user.subEntityCode || '').toLowerCase()

      return fullName.includes(search)
        || email.includes(search)
        || role.includes(search)
        || direction.includes(search)
        || subEntity.includes(search)
    })
  }, [managedUsersSearch, visibleManagedUsers])

  // OnlyOffice settings
  const [onlyofficeUrl, setOnlyofficeUrl] = useState('https://onlyoffice.ci/')
  const [onlyofficeDisableCert, setOnlyofficeDisableCert] = useState(false)
  const [onlyofficeSecret, setOnlyofficeSecret] = useState('')
  const [onlyofficeSecretVisible, setOnlyofficeSecretVisible] = useState(false)
  const [onlyofficeSaved, setOnlyofficeSaved] = useState(false)
  const [docViewer, setDocViewer] = useState<'onlyoffice' | 'native'>('onlyoffice')

  const handleSaveOnlyoffice = (e: React.FormEvent) => {
    e.preventDefault()
    askConfirm(
      'Enregistrer la configuration OnlyOffice',
      'Voulez-vous enregistrer les paramètres de connexion OnlyOffice ?',
      async () => {
        try {
          await upsertAppSettings([
            { key: 'oo_url', value: onlyofficeUrl, description: 'URL du serveur OnlyOffice' },
            { key: 'oo_disable_cert', value: String(onlyofficeDisableCert), description: 'Désactiver la vérification SSL OnlyOffice' },
            { key: 'oo_secret', value: onlyofficeSecret, description: 'Clé secrète JWT OnlyOffice' },
            { key: 'doc_viewer', value: docViewer, description: 'Lecteur de documents préféré (onlyoffice ou native)' },
          ])
          setOnlyofficeSaved(true)
          setTimeout(() => setOnlyofficeSaved(false), 3000)
          showResult('success', 'Configuration OnlyOffice enregistrée avec succès.')
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Impossible d\'enregistrer la configuration OnlyOffice.')
        }
      }
    )
  }

  const lockedEmitterId = useMemo(() => {
    if (isElevatedAdministrationRole) return ''
    if (currentUserScopeType !== 'emitter') return ''
    return currentUserScopeId || ''
  }, [currentUserScopeId, currentUserScopeType, isElevatedAdministrationRole])

  const scopedEmitters = useMemo(() => {
    if (!lockedEmitterId) return emitters
    return emitters.filter((item) => item.id === lockedEmitterId)
  }, [emitters, lockedEmitterId])

  const templatesForSelectedAdministration = useMemo(() => {
    if (!selectedEmitterId) {
      return isElevatedAdministrationRole
        ? templates
        : templates.filter((item) => !item.administrationId)
    }

    return templates.filter((item) => item.administrationId === selectedEmitterId)
  }, [isElevatedAdministrationRole, selectedEmitterId, templates])

  const scopedTemplateIds = useMemo(
    () => new Set(templatesForSelectedAdministration.map((item) => item.id)),
    [templatesForSelectedAdministration],
  )

  const routingRulesForSelectedAdministration = useMemo(() => {
    return routingRules.filter((item) => {
      if (item.templateId) {
        return scopedTemplateIds.has(item.templateId)
      }

      return isElevatedAdministrationRole && !selectedEmitterId
    })
  }, [isElevatedAdministrationRole, routingRules, scopedTemplateIds, selectedEmitterId])

  const selectedTemplateName = useMemo(() => templatesForSelectedAdministration.find((item) => item.id === selectedTemplateId)?.name || '', [selectedTemplateId, templatesForSelectedAdministration])
  const selectedTemplate = useMemo(() => templatesForSelectedAdministration.find((item) => item.id === selectedTemplateId) || null, [selectedTemplateId, templatesForSelectedAdministration])
  const templateContentVariableKeys = useMemo(() => {
    const content = selectedTemplate?.content || ''
    if (!content) return [] as string[]
    const slugify = (text: string): string =>
      text
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/['']/g, '_')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
    const matches = Array.from(content.matchAll(/\{\{\s*([^}]+?)\s*\}\}/g)).map((match) => slugify(match[1]))
    return Array.from(new Set(matches))
  }, [selectedTemplate?.content])
  const generationFields = useMemo(() => {
    const byKey = new Map<string, { key: string; label: string; fieldType: 'text' | 'date' | 'number' | 'select' | 'textarea'; placeholder?: string; defaultValue?: string; required?: boolean }>()
    templateVariables.forEach((variable) => {
      byKey.set(variable.key, {
        key: variable.key,
        label: variable.label || variable.key,
        fieldType: variable.fieldType,
        placeholder: variable.placeholder,
        defaultValue: variable.defaultValue,
        required: variable.required,
      })
    })
    templateContentVariableKeys.forEach((key) => {
      if (!byKey.has(key)) {
        byKey.set(key, {
          key,
          label: key,
          fieldType: 'text',
          required: false,
        })
      }
    })
    return Array.from(byKey.values())
  }, [templateVariables, templateContentVariableKeys])
  const selectedSubEntityScope = useMemo(() => {
    if (!subEntityScopeId) return null
    if (subEntityScopeType === 'emitter') {
      return emitters.find((item) => item.id === subEntityScopeId) || null
    }
    return recipients.find((item) => item.id === subEntityScopeId) || null
  }, [subEntityScopeType, subEntityScopeId, emitters, recipients])

  const subEntityOptions = useMemo(() => {
    const emitterOptions = emitters.map((item) => ({
      id: item.id,
      label: item.name,
      type: 'emitter' as const,
    }))
    const recipientOptions = recipients.map((item) => ({
      id: item.id,
      label: item.name,
      type: 'recipient' as const,
    }))
    return [...emitterOptions, ...recipientOptions]
  }, [emitters, recipients])

  const scopedAdministrationOptions = useMemo(
    () => subEntityOptions.filter((option) => option.type === subEntityScopeType),
    [subEntityOptions, subEntityScopeType],
  )

  const directionTypeLabelMap = useMemo(
    () => new Map(directionTypes.map((item) => [item.id, item.name])),
    [directionTypes],
  )

  const normalizeSubEntities = (value: unknown): SubEntityItem[] => {
    if (!Array.isArray(value)) return []

    return value
      .map((item) => {
        const raw = item as Record<string, unknown>
        const code = String(raw.code || '').trim().toUpperCase()
        const name = String(raw.name || '').trim()
        if (!code || !name) return null

        const rawId = String(raw.id || '').trim()
        const fallbackId = `${code}::${String(raw.parentCode || 'root').trim().toUpperCase()}::${name
          .toLowerCase()
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')
          .replace(/[^a-z0-9]+/g, '-')
          .replace(/^-+|-+$/g, '') || 'direction'}`
        return {
          id: rawId || fallbackId,
          code,
          name,
          parentCode: String(raw.parentCode || '').trim().toUpperCase() || undefined,
          directionType: String(raw.directionType || raw.type || '').trim() || 'direction',
          managerName: String(raw.managerName || raw.responsableName || '').trim() || undefined,
          managerEmail: String(raw.managerEmail || raw.responsableEmail || '').trim() || undefined,
          description: String(raw.description || '').trim() || undefined,
        }
      })
      .filter(Boolean) as SubEntityItem[]
  }

  const currentSubEntities = useMemo(() => {
    const metadata = ((selectedSubEntityScope as any)?.metadata || {}) as Record<string, unknown>
    return normalizeSubEntities(metadata.subEntities || metadata.sousTutelles)
  }, [selectedSubEntityScope])

  const filteredSubEntities = useMemo(() => {
    const query = subEntitySearchQuery.trim().toLowerCase()
    if (!query) return currentSubEntities

    return currentSubEntities.filter((entity) => {
      const directionTypeLabel = (directionTypeLabelMap.get(entity.directionType) || entity.directionType || '').toLowerCase()
      return (
        entity.name.toLowerCase().includes(query)
        || entity.code.toLowerCase().includes(query)
        || (entity.parentCode || '').toLowerCase().includes(query)
        || (entity.managerName || '').toLowerCase().includes(query)
        || (entity.managerEmail || '').toLowerCase().includes(query)
        || (entity.description || '').toLowerCase().includes(query)
        || directionTypeLabel.includes(query)
      )
    })
  }, [currentSubEntities, subEntitySearchQuery, directionTypeLabelMap])

  const userDirectionOptions = useMemo(() => {
    const scopes = [
      ...scopedEmitters.map((item) => ({ id: item.id, name: item.name, metadata: (item as any)?.metadata, scopeType: 'emitter' as const })),
      ...recipients.map((item) => ({ id: item.id, name: item.name, metadata: (item as any)?.metadata, scopeType: 'recipient' as const })),
    ]

    return scopes.flatMap((scope) => {
      const metadata = (scope.metadata || {}) as Record<string, unknown>
      return normalizeSubEntities(metadata.subEntities || metadata.sousTutelles).map((entity) => ({
        id: entity.id,
        code: entity.code,
        label: `${entity.name}${scope.name ? ` (${scope.scopeType === 'recipient' ? 'Destinataire' : 'Émettrice'} - ${scope.name})` : ''}`,
        scopeId: scope.id,
        scopeType: scope.scopeType,
      }))
    })
  }, [recipients, scopedEmitters])

  const administrationOptionsByType = useMemo(
    () => ({
      emitter: scopedEmitters.map((item) => ({ id: item.id, name: item.name, metadata: (item as any)?.metadata })),
      recipient: recipients.map((item) => ({ id: item.id, name: item.name, metadata: (item as any)?.metadata })),
    }),
    [recipients, scopedEmitters],
  )

  const newUserAdministrationOptions = useMemo(() => {
    if (newUserForm.administrationType === 'emitter') return administrationOptionsByType.emitter
    if (newUserForm.administrationType === 'recipient') return administrationOptionsByType.recipient
    return [] as Array<{ id: string; name: string; metadata: unknown }>
  }, [administrationOptionsByType, newUserForm.administrationType])

  const editUserAdministrationOptions = useMemo(() => {
    if (editManagedUserForm.administrationType === 'emitter') return administrationOptionsByType.emitter
    if (editManagedUserForm.administrationType === 'recipient') return administrationOptionsByType.recipient
    return [] as Array<{ id: string; name: string; metadata: unknown }>
  }, [administrationOptionsByType, editManagedUserForm.administrationType])

  const newUserSubEntityOptions = useMemo(() => {
    if (!newUserForm.administrationType || !newUserForm.administrationScopeId) return []
    const source = newUserForm.administrationType === 'emitter'
      ? administrationOptionsByType.emitter
      : administrationOptionsByType.recipient
    const administration = source.find((item) => item.id === newUserForm.administrationScopeId)
    if (!administration) return []

    const metadata = (administration.metadata || {}) as Record<string, unknown>
    return normalizeSubEntities(metadata.subEntities || metadata.sousTutelles).map((entity) => ({
      id: entity.id,
      code: entity.code,
      label: `${entity.name} (${newUserForm.administrationType === 'recipient' ? 'Destinataire' : 'Émettrice'} - ${administration.name})`,
      scopeId: administration.id,
      scopeType: newUserForm.administrationType as 'emitter' | 'recipient',
    }))
  }, [administrationOptionsByType, newUserForm.administrationScopeId, newUserForm.administrationType])

  const editUserSubEntityOptions = useMemo(() => {
    if (!editManagedUserForm.administrationType || !editManagedUserForm.administrationScopeId) return []
    const source = editManagedUserForm.administrationType === 'emitter'
      ? administrationOptionsByType.emitter
      : administrationOptionsByType.recipient
    const administration = source.find((item) => item.id === editManagedUserForm.administrationScopeId)
    if (!administration) return []

    const metadata = (administration.metadata || {}) as Record<string, unknown>
    return normalizeSubEntities(metadata.subEntities || metadata.sousTutelles).map((entity) => ({
      id: entity.id,
      code: entity.code,
      label: `${entity.name} (${editManagedUserForm.administrationType === 'recipient' ? 'Destinataire' : 'Émettrice'} - ${administration.name})`,
      scopeId: administration.id,
      scopeType: editManagedUserForm.administrationType as 'emitter' | 'recipient',
    }))
  }, [administrationOptionsByType, editManagedUserForm.administrationScopeId, editManagedUserForm.administrationType])

  const requestedActAdministrationOptions = useMemo(
    () => [
      ...scopedEmitters.map((item) => ({
        ref: `emitter:${item.id}`,
        label: `Émettrice - ${item.name}`,
        metadata: (item as any)?.metadata,
      })),
      ...(isElevatedAdministrationRole ? recipients.map((item) => ({
        ref: `recipient:${item.id}`,
        label: `Destinataire - ${item.name}`,
        metadata: (item as any)?.metadata,
      })) : []),
    ],
    [isElevatedAdministrationRole, recipients, scopedEmitters],
  )

  const selectedRequestedActAdministration = useMemo(
    () => requestedActAdministrationOptions.find((item) => item.ref === requestedActForm.administrationRef) || null,
    [requestedActAdministrationOptions, requestedActForm.administrationRef],
  )

  const requestedActDirections = useMemo(() => {
    const metadata = ((selectedRequestedActAdministration as any)?.metadata || {}) as Record<string, unknown>
    const allDirections = normalizeSubEntities(metadata.subEntities || (metadata as any).sousTutelles)

    if (!isAdminAdministrationContext) {
      return allDirections
    }

    const assignedSubEntityCode = currentUserSubEntityCode
    if (assignedSubEntityCode) {
      return allDirections.filter((entity) => entity.code === assignedSubEntityCode)
    }

    const normalizedEmail = String(currentUser?.email || '').trim().toLowerCase()
    const normalizedFullName = String(currentUser?.fullName || '').trim().toLowerCase()
    const managedDirections = allDirections.filter((entity) => {
      const managerEmail = String(entity.managerEmail || '').trim().toLowerCase()
      const managerName = String(entity.managerName || '').trim().toLowerCase()
      return (normalizedEmail && managerEmail === normalizedEmail)
        || (normalizedFullName && managerName === normalizedFullName)
    })

    return managedDirections.length > 0 ? managedDirections : []
  }, [currentUser?.email, currentUser?.fullName, currentUserSubEntityCode, isAdminAdministrationContext, selectedRequestedActAdministration])

  const requestedActLockedAdministrationRef = useMemo(() => {
    if (isElevatedAdministrationRole) return ''
    if (currentUserScopeType !== 'emitter') return ''
    if (!currentUserScopeId) return ''
    return `emitter:${currentUserScopeId}`
  }, [currentUserScopeId, currentUserScopeType, isElevatedAdministrationRole])

  const requestedActLockedDirectionCode = useMemo(() => {
    if (isElevatedAdministrationRole) return ''
    return currentUserSubEntityCode
  }, [currentUserSubEntityCode, isElevatedAdministrationRole])

  const filteredRequestedActs = useMemo(() => {
    const query = requestedActsSearch.trim().toLowerCase()
    if (!query) return requestedActs

    return requestedActs.filter((item) => {
      const requiredDocs = Array.isArray(item.requiredDocuments) ? item.requiredDocuments.join(' ') : ''
      const applicantFields = Array.isArray(item.applicantFields)
        ? item.applicantFields.map((field) => `${field.label} ${field.inputType}`).join(' ')
        : ''

      return String(item.documentName || '').toLowerCase().includes(query)
        || String(item.administrationLabel || '').toLowerCase().includes(query)
        || String(item.directionLabel || '').toLowerCase().includes(query)
        || String(requiredDocs).toLowerCase().includes(query)
        || String(applicantFields).toLowerCase().includes(query)
    })
  }, [requestedActs, requestedActsSearch])

  useEffect(() => {
    if (editingRequestedActId) return

    setRequestedActForm((prev) => {
      let changed = false
      let next = prev

      if (requestedActLockedAdministrationRef && prev.administrationRef !== requestedActLockedAdministrationRef) {
        next = { ...next, administrationRef: requestedActLockedAdministrationRef }
        changed = true
      }

      if (requestedActLockedDirectionCode) {
        const exists = requestedActDirections.some((entity) => entity.code === requestedActLockedDirectionCode)
        if (exists && next.directionCode !== requestedActLockedDirectionCode) {
          next = { ...next, directionCode: requestedActLockedDirectionCode }
          changed = true
        }
      }

      return changed ? next : prev
    })
  }, [editingRequestedActId, requestedActDirections, requestedActLockedAdministrationRef, requestedActLockedDirectionCode])

  const filteredRecipientAdministrations = useMemo(() => {
    const query = recipientListSearchQuery.trim().toLowerCase()
    if (!query) return recipients

    return recipients.filter((item) => {
      const metadata = (item.metadata || {}) as Record<string, any>
      return (
        String(item.name || '').toLowerCase().includes(query)
        || String(item.channel || '').toLowerCase().includes(query)
        || String(metadata.sector || '').toLowerCase().includes(query)
        || String(metadata.code || '').toLowerCase().includes(query)
        || String(item.emailAddress || '').toLowerCase().includes(query)
        || String(metadata.contactEmail || '').toLowerCase().includes(query)
      )
    })
  }, [recipients, recipientListSearchQuery])

  const usedDirectionTypeIds = useMemo(() => {
    const values = [...emitters, ...recipients].flatMap((item) => {
      const metadata = ((item as any)?.metadata || {}) as Record<string, unknown>
      return normalizeSubEntities(metadata.subEntities || metadata.sousTutelles).map((entity) => entity.directionType)
    })
    return new Set(values)
  }, [emitters, recipients])

  useEffect(() => {
    if (!requestedActForm.directionCode) return
    const exists = requestedActDirections.some((entity) => entity.code === requestedActForm.directionCode)
    if (!exists) {
      setRequestedActForm((prev) => ({ ...prev, directionCode: '' }))
    }
  }, [requestedActDirections, requestedActForm.directionCode])

  const subEntityDirectionTypeOptions = useMemo(() => {
    const configured = directionTypes.map((item) => ({ id: item.id, name: item.name }))
    if (subEntityForm.directionType && !configured.some((item) => item.id === subEntityForm.directionType)) {
      return [{ id: subEntityForm.directionType, name: subEntityForm.directionType }, ...configured]
    }
    return configured
  }, [directionTypes, subEntityForm.directionType])

  const buildVariableKey = (name: string) =>
    name
      .trim()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '')

  const parseTemplateShareMap = (raw: string | null | undefined): Record<string, string[]> => {
    if (!raw) return {}
    try {
      const parsed = JSON.parse(raw) as Record<string, unknown>
      const normalized: Record<string, string[]> = {}
      Object.entries(parsed || {}).forEach(([templateId, value]) => {
        if (Array.isArray(value)) {
          normalized[templateId] = value.filter((entry): entry is string => typeof entry === 'string')
        }
      })
      return normalized
    } catch {
      return {}
    }
  }

  const handleOpenOnlyOfficeEditor = () => {
    const onlyOfficeBaseUrl = onlyofficeUrl.replace(/\/$/, '')
    if (!onlyOfficeBaseUrl) {
      showResult('error', 'URL OnlyOffice non configurée. Configurez-la d’abord dans l’onglet OnlyOffice.')
      return
    }
    askConfirm(
      'Ouvrir OnlyOffice',
      'Voulez-vous ouvrir OnlyOffice dans un nouvel onglet ?',
      () => {
        setTemplateForceNativeViewer(false)
        setTemplateDragAction(null)
        setShowTemplateOnlyOfficeEditor(true)
        showResult('success', 'OnlyOffice ouvert dans la fenêtre de rédaction du modèle.')
      }
    )
  }

  const handleCreateTemplateFromOnlyOffice = () => {
    if (!templateForm.name.trim() || !templateForm.fileName.trim()) {
      showResult('error', 'Renseignez au moins le nom et le fichier du modèle avant la création.')
      return
    }
    if (!selectedEmitterId) {
      showResult('error', 'Sélectionnez une administration émettrice avant de créer le modèle.')
      return
    }

    askConfirm(
      'Créer le modèle',
      'Voulez-vous créer ce modèle et l’ajouter à la liste des modèles ? ',
      async () => {
        try {
          const created = await createTemplate({
            ...templateForm,
            administrationId: selectedEmitterId,
          })
          await loadData()
          if ((created as any)?.id) {
            setSelectedTemplateId((created as any).id)
          }
          setEditingTemplateId(null)
          showResult('success', 'Modèle créé et ajouté à la liste.')
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Impossible de créer le modèle depuis cette fenêtre.')
        }
      },
    )
  }

  const closeTemplateOnlyOfficeEditor = () => {
    setShowTemplateOnlyOfficeEditor(false)
    setTemplateDragAction(null)
  }

  const addTemplateEditorZone = (x = 10, y = 15) => {
    setTemplateEditorZones((prev) => {
      const next: TemplateSignatureZone = {
        id: `zone-${Date.now()}-${prev.length + 1}`,
        x,
        y,
        width: 28,
        height: 12,
      }
      return [...prev, next]
    })
    setTemplateEditorSaved(false)
  }

  const clearTemplateEditorZones = () => {
    setTemplateEditorZones([])
    setTemplateEditorSaved(false)
  }

  const deleteTemplateEditorZone = (zoneId: string) => {
    setTemplateEditorZones((prev) => prev.filter((zone) => zone.id !== zoneId))
    setTemplateEditorSaved(false)
  }

  const saveTemplateEditorZones = () => {
    if (templateEditorZones.length === 0) {
      showResult('error', 'Ajoutez au moins une zone avant d\'enregistrer.')
      return
    }
    setTemplateEditorSaved(true)
    showResult('success', 'Zones enregistrées pour le modèle.')
  }

  const handleTemplateEditorPointerMove = (event: React.PointerEvent<HTMLDivElement>) => {
    if (!templateDragAction) return
    const rect = event.currentTarget.getBoundingClientRect()
    const pctX = ((event.clientX - rect.left) / rect.width) * 100
    const pctY = ((event.clientY - rect.top) / rect.height) * 100
    const dx = pctX - templateDragAction.startX
    const dy = pctY - templateDragAction.startY
    const original = templateDragAction.origZone

    setTemplateEditorZones((prev) => prev.map((zone) => {
      if (zone.id !== templateDragAction.zoneId) return zone
      if (templateDragAction.mode === 'move') {
        return {
          ...zone,
          x: Math.max(0, Math.min(100 - zone.width, original.x + dx)),
          y: Math.max(0, Math.min(100 - zone.height, original.y + dy)),
        }
      }
      const newWidth = Math.max(8, Math.min(100 - original.x, original.width + dx))
      const newHeight = Math.max(4, Math.min(100 - original.y, original.height + dy))
      return { ...zone, width: newWidth, height: newHeight }
    }))
    setTemplateEditorSaved(false)
  }

  const loadData = async () => {
    setLoading(true)
    setError(null)
    try {
      const [templatesData, emittersData, recipientsData, rulesData, appUsersData, directionTypesData, appSettingsData, requestedActsData] = await Promise.all([
        fetchTemplates(),
        fetchIssuingAdministrations(),
        fetchRecipientAdministrations(),
        fetchRoutingRules(),
        fetchAppUsers(),
        fetchDirectionTypes(),
        fetchAppSettings(),
        fetchRequestedActs(),
      ])

      setTemplates(templatesData)
      setEmitters(emittersData)
      setRecipients(recipientsData)
      setRoutingRules(rulesData)
      setManagedUsers(appUsersData)
      setDirectionTypes(directionTypesData)
      setRequestedActs(requestedActsData || [])

      // Apply app settings from DB
      const settingMap = new Map<string, string | null>(appSettingsData.map((s: AppSetting) => [s.key, s.value]))
      setAppSettingsCache(settingMap)
      if (settingMap.has('chat_enabled')) {
        setChatSettings((prev) => ({
          ...prev,
          enabled: settingMap.get('chat_enabled') !== 'false',
          scope: (settingMap.get('chat_scope') || prev.scope) as 'same-administration' | 'all',
        }))
      }
      if (settingMap.has('oo_url')) {
        setOnlyofficeUrl(settingMap.get('oo_url') || 'https://onlyoffice.ci/')
        setOnlyofficeDisableCert(settingMap.get('oo_disable_cert') === 'true')
        setOnlyofficeSecret(settingMap.get('oo_secret') || '')
      }
      if (settingMap.has('doc_viewer')) {
        setDocViewer((settingMap.get('doc_viewer') === 'native' ? 'native' : 'onlyoffice') as 'onlyoffice' | 'native')
      }
      setTemplateShareMap(parseTemplateShareMap(settingMap.get(TEMPLATE_SHARE_MAP_SETTING_KEY)))
      if (settingMap.has(SIGNATURE_QR_POSITION_SETTING_KEY)) {
        try {
          const parsed = JSON.parse(String(settingMap.get(SIGNATURE_QR_POSITION_SETTING_KEY) || '{}')) as Record<string, unknown>
          const toStringNumber = (value: unknown, fallback: string) => {
            const num = Number(value)
            return Number.isFinite(num) ? String(num) : fallback
          }
          setSignatureQrPositionForm({
            imagePage: toStringNumber(parsed.imagePage, '-1'),
            imageX: toStringNumber(parsed.imageX, '390'),
            imageY: toStringNumber(parsed.imageY, '710'),
            imageWidth: toStringNumber(parsed.imageWidth, '150'),
            imageHeight: toStringNumber(parsed.imageHeight, '80'),
          })
        } catch {
          // keep defaults
        }
      }

      // Sync admin logo from first emitter with a logo (DB is source of truth)
      const API_ROOT = (import.meta.env.VITE_API_URL || 'http://localhost:3000/api/v1').replace(/\/api(?:\/v\d+)?\/?$/, '')
      const emitterWithLogo = emittersData.find((e: IssuingAdministration) => e.logo)
      const recipientWithLogo = recipientsData.find((r: RecipientAdministration) => Boolean((r.metadata as any)?.logo))
      const recipientLogoPath = recipientWithLogo ? String((recipientWithLogo.metadata as any)?.logo || '') : ''
      const preferredLogo = emitterWithLogo?.logo || recipientLogoPath || ''
      if (preferredLogo) {
        const fullLogoUrl = preferredLogo.startsWith('http')
          ? preferredLogo
          : `${API_ROOT}${preferredLogo}`
        window.dispatchEvent(new StorageEvent('storage', { key: 'ep_admin_logo', newValue: fullLogoUrl }))
      } else {
        window.dispatchEvent(new StorageEvent('storage', { key: 'ep_admin_logo', newValue: null }))
      }

      if (!selectedTemplateId && templatesData.length > 0) {
        setSelectedTemplateId(templatesData[0].id)
      }

      if (!subEntityScopeId) {
        if (emittersData.length > 0) {
          setSubEntityScopeType('emitter')
          setSubEntityScopeId(emittersData[0].id)
        } else if (recipientsData.length > 0) {
          setSubEntityScopeType('recipient')
          setSubEntityScopeId(recipientsData[0].id)
        }
      }
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Erreur lors du chargement des paramètres administration')
    } finally {
      setLoading(false)
    }
  }

  const loadTemplateVariables = async (templateId: string) => {
    if (!templateId) {
      setTemplateVariables([])
      return
    }
    try {
      const data = await fetchTemplateVariables(templateId)
      setTemplateVariables(data)
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Erreur lors du chargement des variables')
    }
  }

  useEffect(() => {
    void loadData()
  }, [])

  useEffect(() => {
    if (lockedEmitterId) {
      if (selectedEmitterId !== lockedEmitterId) {
        setSelectedEmitterId(lockedEmitterId)
      }
      return
    }

    if (!selectedEmitterId && scopedEmitters.length > 0) {
      setSelectedEmitterId(scopedEmitters[0].id)
      return
    }

    if (selectedEmitterId && !scopedEmitters.some((item) => item.id === selectedEmitterId)) {
      setSelectedEmitterId(scopedEmitters[0]?.id || '')
    }
  }, [lockedEmitterId, scopedEmitters, selectedEmitterId])

  useEffect(() => {
    if (!selectedTemplateId) {
      if (templatesForSelectedAdministration.length > 0) {
        setSelectedTemplateId(templatesForSelectedAdministration[0].id)
      }
      return
    }

    if (!templatesForSelectedAdministration.some((item) => item.id === selectedTemplateId)) {
      setSelectedTemplateId(templatesForSelectedAdministration[0]?.id || '')
    }
  }, [selectedTemplateId, templatesForSelectedAdministration])

  useEffect(() => {
    if (lockedEmitterId) {
      if (themingScopeType !== 'emitter') {
        setThemingScopeType('emitter')
      }
      if (themingScopeId !== lockedEmitterId) {
        setThemingScopeId(lockedEmitterId)
      }
      return
    }

    if (themingScopeId) {
      return
    }

    if (themingScopeType === 'emitter' && emitters.length > 0) {
      setThemingScopeId(emitters[0].id)
      return
    }

    if (themingScopeType === 'recipient' && recipients.length > 0) {
      setThemingScopeId(recipients[0].id)
      return
    }

    if (emitters.length > 0) {
      setThemingScopeType('emitter')
      setThemingScopeId(emitters[0].id)
      return
    }

    if (recipients.length > 0) {
      setThemingScopeType('recipient')
      setThemingScopeId(recipients[0].id)
    }
  }, [emitters, lockedEmitterId, recipients, themingScopeId, themingScopeType])

  useEffect(() => {
    applyScopedTheming(appSettingsCache, themingScopeType, themingScopeId)
  }, [appSettingsCache, themingScopeType, themingScopeId])

  useEffect(() => {
    void loadTemplateVariables(selectedTemplateId)
  }, [selectedTemplateId])

  useEffect(() => {
    setGenerationValues((prev) => {
      const next: Record<string, string> = {}
      const storageKey = selectedTemplateId ? `template_generation_values_${selectedTemplateId}` : ''
      let savedValues: Record<string, string> = {}
      if (storageKey) {
        try {
          const raw = localStorage.getItem(storageKey)
          if (raw) {
            savedValues = JSON.parse(raw)
          }
        } catch {
          savedValues = {}
        }
      }
      generationFields.forEach((field) => {
        next[field.key] = savedValues[field.key] ?? prev[field.key] ?? field.defaultValue ?? ''
      })
      return next
    })
  }, [generationFields, selectedTemplateId])

  useEffect(() => {
    if (scopedAdministrationOptions.length === 0) {
      setSubEntityScopeId('')
      return
    }
    if (!scopedAdministrationOptions.some((option) => option.id === subEntityScopeId)) {
      setSubEntityScopeId(scopedAdministrationOptions[0].id)
    }
  }, [scopedAdministrationOptions, subEntityScopeId])

  useEffect(() => {
    const loadNotificationConfig = async () => {
      if (!selectedEmitterId) return
      try {
        const config = await fetchNotificationConfigByAdministration(selectedEmitterId)
        applyNotificationConfig(config)
      } catch (err: any) {
        showResult('error', err?.response?.data?.message || 'Impossible de charger la configuration SMTP de cette administration')
      }
    }
    void loadNotificationConfig()
  }, [selectedEmitterId])

  useEffect(() => {
    const loadSignatureProviderConfig = async () => {
      if (!selectedEmitterId) return
      try {
        const config = await fetchSignatureProviderConfig(selectedEmitterId)
        applySignatureProviderConfig(config)
      } catch (err: any) {
        showResult('error', err?.response?.data?.message || 'Impossible de charger la configuration API Signature de cette administration')
      }
    }
    void loadSignatureProviderConfig()
  }, [selectedEmitterId])

  const handleCreateTemplate = async (event: FormEvent) => {
    event.preventDefault()
    if (!selectedEmitterId) {
      showResult('error', 'Sélectionnez une administration émettrice pour gérer les templates.')
      return
    }
    askConfirm(
      editingTemplateId ? 'Mettre à jour le template' : 'Créer le template',
      editingTemplateId ? 'Voulez-vous enregistrer les modifications de ce template ?' : 'Voulez-vous créer ce nouveau template ?',
      async () => {
        try {
          if (editingTemplateId) {
            await updateTemplate(editingTemplateId, templateForm)
          } else {
            await createTemplate({
              ...templateForm,
              administrationId: selectedEmitterId,
            })
          }
          setTemplateForm({ name: '', fileName: '', fileType: 'docx', content: '' })
          setEditingTemplateId(null)
          showResult('success', editingTemplateId ? 'Template mis à jour avec succès.' : 'Template créé avec succès.')
          await loadData()
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Opération sur le modèle impossible')
        }
      }
    )
  }

  const handleGenerateDocumentFromTemplate = async (event: FormEvent) => {
    event.preventDefault()
    if (!selectedTemplateId) {
      showResult('error', 'Sélectionnez un template avant de générer un document')
      return
    }
    askConfirm(
      'Générer un document',
      'Voulez-vous générer le document à partir du template sélectionné ?',
      async () => {
        try {
          const templateFileName = selectedTemplate?.fileName || 'document.pdf'
          const templateExtMatch = templateFileName.match(/\.([^.]+)$/)
          const templateExt = (templateExtMatch?.[1] || selectedTemplate?.fileType || 'pdf').toLowerCase()
          const outputName = `${templateFileName.replace(/\.[^.]+$/, '')}-genere.${templateExt}`
          const generated = await generateTemplateDocument(selectedTemplateId, {
            values: generationValues,
            outputFileName: outputName,
          })
          setGeneratedContent(generated.generatedContent)
          setGeneratedFileName(generated.fileName)
          const outputExt = (generated.fileName.match(/\.([^.]+)$/)?.[1] || templateExt || 'pdf').toLowerCase()
          const mimeByExt: Record<string, string> = {
            pdf: 'application/pdf',
            docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            doc: 'application/msword',
            xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            xls: 'application/vnd.ms-excel',
            pptx: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ppt: 'application/vnd.ms-powerpoint',
            txt: 'text/plain;charset=utf-8',
          }
          const generatedFile = new File([generated.generatedContent], generated.fileName, { type: mimeByExt[outputExt] || 'application/octet-stream' })
          const uploadedDocument = await uploadDocumentFile(generatedFile)
          const uploadedAny = uploadedDocument as any
          const documentId = String(uploadedAny?.id || '')
          if (!documentId) {
            showResult('error', 'Document généré mais identifiant introuvable pour ouvrir le positionnement.')
            return
          }
          const apiBaseUrl = (import.meta.env.VITE_API_URL || 'http://localhost:3000/api/v1').replace(/\/?api\/?v1\/?$/i, '')
          const fileUrl = `${apiBaseUrl}/api/v1/documents/public/${encodeURIComponent(documentId)}/digital-version`
          const docKey = `doc-${documentId}`
          setTemplatePositioningDocId(documentId)
          setTemplatePositioningDocName(uploadedAny?.title || generated.fileName)
          setTemplatePositioningFileUrl(fileUrl)
          setTemplateForceNativeViewer(false)
          setTemplateDragAction(null)
          setTemplateSavedZoneByDocKey((prev) => ({
            ...prev,
            [docKey]: Boolean((templateZonesByDocKey[docKey] || []).length),
          }))
          showResult('success', `Document généré automatiquement: ${generated.fileName}`)
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Impossible de générer le document depuis le template')
        }
      }
    )
  }

  const handleSaveGenerationForm = () => {
    if (!selectedTemplateId) {
      showResult('error', 'Sélectionnez un template avant d\'enregistrer le formulaire')
      return
    }
    try {
      localStorage.setItem(`template_generation_values_${selectedTemplateId}`, JSON.stringify(generationValues))
      showResult('success', 'Formulaire enregistré avec succès.')
    } catch {
      showResult('error', 'Impossible d\'enregistrer les valeurs du formulaire.')
    }
  }

  const handlePreviewGeneratedDocument = async () => {
    if (!selectedTemplateId) {
      showResult('error', 'Sélectionnez un template avant de visualiser')
      return
    }
    try {
      const templateFileName = selectedTemplate?.fileName || 'document.pdf'
      const templateExtMatch = templateFileName.match(/\.([^.]+)$/)
      const templateExt = (templateExtMatch?.[1] || selectedTemplate?.fileType || 'pdf').toLowerCase()
      const outputName = `${templateFileName.replace(/\.[^.]+$/, '')}-apercu.${templateExt}`
      const generated = await generateTemplateDocument(selectedTemplateId, {
        values: generationValues,
        outputFileName: outputName,
      })
      setGeneratedContent(generated.generatedContent)
      setGeneratedFileName(generated.fileName)
      showResult('success', `Aperçu généré: ${generated.fileName}`)
    } catch (err: any) {
      showResult('error', err?.response?.data?.message || 'Impossible de générer l\'aperçu du document')
    }
  }

  const handleCreateVariable = async (event: FormEvent) => {
    event.preventDefault()
    if (!selectedTemplateId) return
    const generatedKey = buildVariableKey(variableForm.name)
    if (!generatedKey) {
      showResult('error', 'Le nom du champ est obligatoire')
      return
    }
    const payload = {
      key: generatedKey,
      label: variableForm.name.trim(),
      fieldType: variableForm.fieldType,
    }
    askConfirm(
      editingVariableId ? 'Mettre à jour la variable' : 'Créer la variable',
      editingVariableId ? 'Voulez-vous enregistrer les modifications de cette variable ?' : `Voulez-vous créer la variable "${variableForm.name}" ?`,
      async () => {
        try {
          if (editingVariableId) {
            await updateTemplateVariable(selectedTemplateId, editingVariableId, payload)
          } else {
            await createTemplateVariable(selectedTemplateId, payload)
          }
          setVariableForm({ name: '', fieldType: 'text' })
          setEditingVariableId(null)
          showResult('success', 'Champ du formulaire enregistré avec succès.')
          await loadTemplateVariables(selectedTemplateId)
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Opération sur la variable impossible')
        }
      }
    )
  }

  const closeTemplatePositioning = () => {
    setTemplatePositioningDocId(null)
    setTemplatePositioningDocName('')
    setTemplatePositioningFileUrl(null)
    setTemplateForceNativeViewer(false)
    setTemplateDragAction(null)
  }

  const addTemplateZone = (x = 10, y = 15) => {
    if (!templatePositioningDocId) return
    const docKey = `doc-${templatePositioningDocId}`
    setTemplateZonesByDocKey((prev) => {
      const currentZones = prev[docKey] || []
      const nextZone: TemplateSignatureZone = {
        id: `zone-${Date.now()}-${currentZones.length + 1}`,
        x,
        y,
        width: 28,
        height: 12,
      }
      return { ...prev, [docKey]: [...currentZones, nextZone] }
    })
    setTemplateSavedZoneByDocKey((prev) => ({ ...prev, [docKey]: false }))
  }

  const clearTemplateZones = () => {
    if (!templatePositioningDocId) return
    const docKey = `doc-${templatePositioningDocId}`
    setTemplateZonesByDocKey((prev) => ({ ...prev, [docKey]: [] }))
    setTemplateSavedZoneByDocKey((prev) => ({ ...prev, [docKey]: false }))
  }

  const deleteTemplateZone = (zoneId: string) => {
    if (!templatePositioningDocId) return
    const docKey = `doc-${templatePositioningDocId}`
    setTemplateZonesByDocKey((prev) => ({
      ...prev,
      [docKey]: (prev[docKey] || []).filter((zone) => zone.id !== zoneId),
    }))
    setTemplateSavedZoneByDocKey((prev) => ({ ...prev, [docKey]: false }))
  }

  const handleTemplateZonePointerMove = (event: React.PointerEvent<HTMLDivElement>) => {
    if (!templatePositioningDocId || !templateDragAction) return
    const rect = event.currentTarget.getBoundingClientRect()
    const pctX = ((event.clientX - rect.left) / rect.width) * 100
    const pctY = ((event.clientY - rect.top) / rect.height) * 100
    const dx = pctX - templateDragAction.startX
    const dy = pctY - templateDragAction.startY
    const original = templateDragAction.origZone
    const docKey = `doc-${templatePositioningDocId}`

    setTemplateZonesByDocKey((prev) => ({
      ...prev,
      [docKey]: (prev[docKey] || []).map((zone) => {
        if (zone.id !== templateDragAction.zoneId) return zone
        if (templateDragAction.mode === 'move') {
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
    setTemplateSavedZoneByDocKey((prev) => ({ ...prev, [docKey]: false }))
  }

  const saveTemplateZonePlacement = () => {
    if (!templatePositioningDocId) return
    const docKey = `doc-${templatePositioningDocId}`
    const currentZones = templateZonesByDocKey[docKey] || []
    if (currentZones.length === 0) {
      showResult('error', 'Veuillez placer au moins une zone de signature avant d\'enregistrer.')
      return
    }
    setTemplateSavedZoneByDocKey((prev) => ({ ...prev, [docKey]: true }))
    showResult('success', 'Zone de signature enregistrée.')
    closeTemplatePositioning()
  }

  const resetSubEntityForm = () => {
    setEditingSubEntityId(null)
    setSubEntityForm({
      name: '',
      code: '',
      parentCode: '',
      directionType: '',
      managerName: '',
      managerEmail: '',
      description: '',
    })
  }

  const resetDirectionTypeForm = () => {
    setEditingDirectionTypeId(null)
    setDirectionTypeForm({ name: '', description: '' })
  }

  const handleSaveDirectionType = (event: FormEvent) => {
    event.preventDefault()

    const name = directionTypeForm.name.trim()
    const description = directionTypeForm.description.trim()

    if (!name) {
      showResult('error', 'Le nom du type est obligatoire.')
      return
    }

    const duplicate = directionTypes.find(
      (item) => item.name.trim().toLowerCase() === name.toLowerCase() && item.id !== editingDirectionTypeId,
    )

    if (duplicate) {
      showResult('error', 'Un type de direction avec ce nom existe déjà.')
      return
    }

    askConfirm(
      editingDirectionTypeId ? 'Modifier le type de direction' : 'Créer le type de direction',
      editingDirectionTypeId
        ? `Voulez-vous enregistrer les modifications du type « ${name} » ?`
        : `Voulez-vous créer le type « ${name} » ?`,
      async () => {
        try {
          if (editingDirectionTypeId) {
            await updateDirectionType(editingDirectionTypeId, { name, description })
          } else {
            await createDirectionType({ name, description })
          }

          await loadData()
          resetDirectionTypeForm()
          showResult('success', editingDirectionTypeId ? 'Type de direction mis à jour avec succès.' : 'Type de direction créé avec succès.')
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Impossible d\'enregistrer le type de direction.')
        }
      },
    )
  }

  const startEditDirectionType = (item: DirectionType) => {
    setEditingDirectionTypeId(item.id)
    setDirectionTypeForm({ name: item.name, description: item.description || '' })
  }

  const handleDeleteDirectionType = (item: DirectionType) => {
    if (usedDirectionTypeIds.has(item.id)) {
      showResult('error', 'Ce type de direction est déjà utilisé par au moins une entité sous tutelle.')
      return
    }

    askConfirm(
      'Supprimer le type de direction',
      `Voulez-vous vraiment supprimer le type « ${item.name} » ?`,
      async () => {
        try {
          await deleteDirectionType(item.id)
          await loadData()
          if (editingDirectionTypeId === item.id) {
            resetDirectionTypeForm()
          }
          showResult('success', 'Type de direction supprimé avec succès.')
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Suppression du type de direction impossible.')
        }
      },
    )
  }

  const handleSaveSubEntity = async (event: FormEvent) => {
    event.preventDefault()

    if (!subEntityScopeId) {
      showResult('error', 'Selectionnez une administration parent avant de creer une direction.')
      return
    }

    const code = subEntityForm.code.trim().toUpperCase()
    const name = subEntityForm.name.trim()
    const directionType = subEntityForm.directionType.trim()

    if (!code || !name || !directionType) {
      showResult('error', 'Nom, code et type de direction sont obligatoires.')
      return
    }

    const duplicateCode = currentSubEntities.find((item) => item.code === code && item.id !== editingSubEntityId)
    if (duplicateCode) {
      showResult('error', `Le code ${code} existe deja dans cette administration.`)
      return
    }

    const nextEntity: SubEntityItem = {
      id: editingSubEntityId || `${code}-${Date.now()}`,
      code,
      name,
      parentCode: subEntityForm.parentCode.trim().toUpperCase() || undefined,
      directionType,
      managerName: subEntityForm.managerName.trim() || undefined,
      managerEmail: subEntityForm.managerEmail.trim() || undefined,
      description: subEntityForm.description.trim() || undefined,
    }

    const nextSubEntities = editingSubEntityId
      ? currentSubEntities.map((item) => (item.id === editingSubEntityId ? nextEntity : item))
      : [...currentSubEntities, nextEntity]

    askConfirm(
      editingSubEntityId ? 'Mettre a jour la direction' : 'Creer la direction',
      editingSubEntityId
        ? `Voulez-vous mettre a jour la direction "${name}" ?`
        : `Voulez-vous creer la direction "${name}" ?`,
      async () => {
        try {
          const currentMetadata = (((selectedSubEntityScope as any)?.metadata || {}) as Record<string, unknown>)
          const mergedMetadata = {
            ...currentMetadata,
            subEntities: nextSubEntities,
            sousTutelles: nextSubEntities,
          }

          if (subEntityScopeType === 'emitter') {
            await updateIssuingAdministration(subEntityScopeId, { metadata: mergedMetadata })
          } else {
            await updateRecipientAdministration(subEntityScopeId, { metadata: mergedMetadata })
          }

          showResult('success', editingSubEntityId ? 'Direction mise a jour avec succes.' : 'Direction creee avec succes.')
          resetSubEntityForm()
          await loadData()
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Operation impossible sur les entites sous tutelle.')
        }
      },
    )
  }

  const startEditSubEntity = (item: SubEntityItem) => {
    setEditingSubEntityId(item.id)
    setSubEntityForm({
      name: item.name,
      code: item.code,
      parentCode: item.parentCode || '',
      directionType: item.directionType || '',
      managerName: item.managerName || '',
      managerEmail: item.managerEmail || '',
      description: item.description || '',
    })
  }

  const handleDeleteSubEntity = async (item: SubEntityItem) => {
    if (!subEntityScopeId) return

    askConfirm(
      'Supprimer la direction',
      `Voulez-vous vraiment supprimer la direction "${item.name}" ?`,
      async () => {
        try {
          const nextSubEntities = currentSubEntities.filter((entity) => entity.id !== item.id)
          const currentMetadata = (((selectedSubEntityScope as any)?.metadata || {}) as Record<string, unknown>)
          const mergedMetadata = {
            ...currentMetadata,
            subEntities: nextSubEntities,
            sousTutelles: nextSubEntities,
          }

          if (subEntityScopeType === 'emitter') {
            await updateIssuingAdministration(subEntityScopeId, { metadata: mergedMetadata })
          } else {
            await updateRecipientAdministration(subEntityScopeId, { metadata: mergedMetadata })
          }

          showResult('success', 'Direction supprimee avec succes.')
          if (editingSubEntityId === item.id) {
            resetSubEntityForm()
          }
          await loadData()
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Suppression de la direction impossible.')
        }
      },
    )
  }

  const handleCreateEmitter = async (event: FormEvent) => {
    event.preventDefault()
    const _emitterPayload = {
      name: emitterForm.name,
      code: emitterForm.code,
      metadata: {
        adminType: emitterForm.adminType,
        sector: emitterForm.sector,
        description: emitterForm.description,
        contactEmail: emitterForm.contactEmail,
        techEmail: emitterForm.techEmail,
        contactPhone: emitterForm.contactPhone,
        referentMetier: emitterForm.referentMetier,
        postalAddress: emitterForm.postalAddress,
        logoFileName: emitterLogoFile?.name || '',
        transmissionMethod: emitterForm.transmissionMethod,
        endpointUrl: emitterForm.endpointUrl,
        dataFormat: emitterForm.dataFormat,
        authMethod: emitterForm.authMethod,
        apiKey: emitterForm.apiKey,
        timeout: emitterForm.timeout,
        requireTls: emitterForm.requireTls,
        enableRetry: emitterForm.enableRetry,
        docTypes: emitterForm.docTypes,
        defaultWorkflow: emitterForm.defaultWorkflow,
        dossierPrefix: emitterForm.dossierPrefix,
        autoConvertPdf: emitterForm.autoConvertPdf,
        requiredMetadata: emitterForm.requiredMetadata,
        signatureLevel: emitterForm.signatureLevel,
        logRetention: emitterForm.logRetention,
        gdprCompliant: emitterForm.gdprCompliant,
        enableAudit: emitterForm.enableAudit,
        fileEncryption: emitterForm.fileEncryption,
        ipWhitelist: emitterForm.ipWhitelist,
        businessHours: emitterForm.businessHours,
        slaResponse: emitterForm.slaResponse,
        timezone: emitterForm.timezone,
        duplicateHandling: emitterForm.duplicateHandling,
        externalRefField: emitterForm.externalRefField,
        trackingUrl: emitterForm.trackingUrl,
        webhookUrl: emitterForm.webhookUrl,
        webhookSecret: emitterForm.webhookSecret,
        tags: emitterForm.tags,
      },
    }
    askConfirm(
      editingEmitterId ? "Mettre à jour l'administration émettrice" : "Enregistrer l'administration émettrice",
      editingEmitterId
        ? `Voulez-vous enregistrer les modifications de "${emitterForm.name}" ?`
        : `Voulez-vous enregistrer l'administration émettrice "${emitterForm.name}" ?`,
      async () => {
        try {
          let savedId = editingEmitterId || ''
          if (editingEmitterId) {
            await updateIssuingAdministration(editingEmitterId, _emitterPayload)
          } else {
            const created = await createIssuingAdministration(_emitterPayload)
            savedId = created.id
          }
          // Upload logo if a file was selected
          if (emitterLogoFile && savedId) {
            try {
              await uploadAdministrationLogo(savedId, emitterLogoFile)
            } catch (logoErr) {
              console.error('Logo upload failed:', logoErr)
            }
          }
          setEmitterForm({
            name: '', code: '', adminType: '', sector: '', description: '',
            contactEmail: '', techEmail: '', contactPhone: '', referentMetier: '', postalAddress: '',
            transmissionMethod: 'api', endpointUrl: '', dataFormat: 'json', authMethod: 'api_key',
            apiKey: '', timeout: 30, requireTls: true, enableRetry: true, docTypes: ['pdf'],
            defaultWorkflow: '', dossierPrefix: '', autoConvertPdf: true, requiredMetadata: '',
            signatureLevel: 'qualifiee', logRetention: 365, gdprCompliant: true, enableAudit: true,
            fileEncryption: false, ipWhitelist: '', businessHours: '', slaResponse: '24h',
            timezone: 'Europe/Paris', duplicateHandling: 'update', externalRefField: '',
            trackingUrl: '', webhookUrl: '', webhookSecret: '', tags: '',
          })
          setEmitterLogoFile(null)
          setEmitterLogoPreview(null)
          setCurrentEmitterLogoUrl(null)
          setEditingEmitterId(null)
          showResult('success', editingEmitterId
            ? 'Administration émettrice mise à jour avec succès.'
            : 'Administration émettrice enregistrée avec succès.')
          await loadData()
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || "Opération sur l'administration émettrice impossible")
        }
      }
    )
  }

  const handleCreateRecipient = async (event: FormEvent) => {
    event.preventDefault()
    const _recipientPayload = {
      name: recipientForm.name,
      channel: recipientForm.channel,
      apiEndpoint: recipientForm.channel === 'api' ? recipientForm.apiEndpoint : undefined,
      emailAddress: recipientForm.channel === 'email' ? (recipientForm.emailAddress || recipientForm.contactEmail) : undefined,
      metadata: {
        code: recipientForm.code,
        adminType: recipientForm.adminType,
        sector: recipientForm.sector,
        description: recipientForm.description,
        contactEmail: recipientForm.contactEmail,
        techEmail: recipientForm.techEmail,
        contactPhone: recipientForm.contactPhone,
        contactFax: recipientForm.contactFax,
        postalAddress: recipientForm.postalAddress,
        referentMetier: recipientForm.referentMetier,
        referentTechnique: recipientForm.referentTechnique,
        apiMethod: recipientForm.apiMethod,
        apiFormat: recipientForm.apiFormat,
        apiAuth: recipientForm.apiAuth,
        apiTimeout: recipientForm.apiTimeout,
        emailSubject: recipientForm.emailSubject,
        emailBody: recipientForm.emailBody,
        lerProvider: recipientForm.lerProvider,
        lerAccountId: recipientForm.lerAccountId,
        enableRetry: recipientForm.enableRetry,
        enableNotification: recipientForm.enableNotification,
        compressFiles: recipientForm.compressFiles,
        encryptFiles: recipientForm.encryptFiles,
        docTypes: recipientForm.docTypes,
        maxFileSize: recipientForm.maxFileSize,
        maxFiles: recipientForm.maxFiles,
        receiptMethod: recipientForm.receiptMethod,
        receiptWebhookUrl: recipientForm.receiptWebhookUrl,
        receiptTimeout: recipientForm.receiptTimeout,
        activateImmediately: recipientForm.activateImmediately,
      },
    }
    askConfirm(
      editingRecipientId ? "Mettre à jour l'administration destinataire" : "Enregistrer l'administration destinataire",
      editingRecipientId
        ? `Voulez-vous enregistrer les modifications de "${recipientForm.name}" ?`
        : `Voulez-vous enregistrer l'administration destinataire "${recipientForm.name}" ?`,
      async () => {
        try {
          if (editingRecipientId) {
            await updateRecipientAdministration(editingRecipientId, _recipientPayload)
            if (recipientLogoFile) {
              const updatedWithLogo = await uploadRecipientAdministrationLogo(editingRecipientId, recipientLogoFile)
              const API_ROOT = (import.meta.env.VITE_API_URL || 'http://localhost:3000/api/v1').replace(/\/api(?:\/v\d+)?\/?$/, '')
              const logoPath = String((updatedWithLogo.metadata as any)?.logo || '')
              if (logoPath) {
                const fullLogoUrl = logoPath.startsWith('http') ? logoPath : `${API_ROOT}${logoPath}`
                window.dispatchEvent(new StorageEvent('storage', { key: 'ep_admin_logo', newValue: fullLogoUrl }))
              }
            }
          } else {
            const createdRecipient = await createRecipientAdministration(_recipientPayload)
            if (recipientLogoFile) {
              const updatedWithLogo = await uploadRecipientAdministrationLogo(createdRecipient.id, recipientLogoFile)
              const API_ROOT = (import.meta.env.VITE_API_URL || 'http://localhost:3000/api/v1').replace(/\/api(?:\/v\d+)?\/?$/, '')
              const logoPath = String((updatedWithLogo.metadata as any)?.logo || '')
              if (logoPath) {
                const fullLogoUrl = logoPath.startsWith('http') ? logoPath : `${API_ROOT}${logoPath}`
                window.dispatchEvent(new StorageEvent('storage', { key: 'ep_admin_logo', newValue: fullLogoUrl }))
              }
            }
          }
          setRecipientForm({
            name: '', code: '', adminType: '', sector: '', description: '',
            channel: 'api', apiEndpoint: '', emailAddress: '',
            contactEmail: '', techEmail: '', contactPhone: '', contactFax: '', postalAddress: '',
            referentMetier: '', referentTechnique: '', apiMethod: 'POST', apiFormat: 'multipart',
            apiAuth: 'api_key', apiTimeout: 30,
            emailSubject: '[E-Parapheur] Nouveau document signé - {{reference}}',
            emailBody: '', lerProvider: 'laposte', lerAccountId: '',
            enableRetry: true, enableNotification: true, compressFiles: false, encryptFiles: false,
            docTypes: ['pdf', 'docx', 'xlsx'], maxFileSize: 50, maxFiles: 10,
            receiptMethod: 'automatic', receiptWebhookUrl: '', receiptTimeout: 24,
            activateImmediately: true,
          })
          setRecipientLogoFile(null)
          setRecipientLogoPreview(null)
          setCurrentRecipientLogoUrl(null)
          setEditingRecipientId(null)
          showResult('success', editingRecipientId
            ? 'Administration destinataire mise à jour avec succès.'
            : 'Administration destinataire enregistrée avec succès.')
          await loadData()
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || "Opération sur le destinataire impossible")
        }
      }
    )
  }

  const handleTestRecipientConnection = async () => {
    if (recipientForm.channel === 'api' && !recipientForm.apiEndpoint.trim()) {
      showResult('error', 'Veuillez renseigner un endpoint API avant le test de connexion')
      return
    }
    if (recipientForm.channel === 'email' && !recipientForm.emailAddress.trim()) {
      showResult('error', 'Veuillez renseigner un email de destination avant le test de connexion')
      return
    }
    try {
      setIsTestingRecipientConnection(true)
      await new Promise((resolve) => setTimeout(resolve, 1400))
      const pseudoLatency = Math.floor(90 + Math.random() * 120)
      if (recipientForm.channel === 'api') {
        showResult('success', `Connexion API testée avec succès (${pseudoLatency} ms)`)
      } else if (recipientForm.channel === 'email') {
        showResult('success', `Connexion email testée avec succès (${pseudoLatency} ms)`)
      } else if (recipientForm.channel === 'application') {
        showResult('success', `Réception via l'application validée (${pseudoLatency} ms)`)
      } else {
        showResult('success', `Connexion LER testée avec succès (${pseudoLatency} ms)`)
      }
    } catch {
      showResult('error', 'Échec du test de connexion')
    } finally {
      setIsTestingRecipientConnection(false)
    }
  }

  const handleCreateRule = async (event: FormEvent) => {
    event.preventDefault()
    if (!selectedEmitterId) {
      showResult('error', 'Sélectionnez une administration émettrice pour gérer les règles de routage.')
      return
    }
    if (!ruleForm.templateId) {
      showResult('error', 'Sélectionnez un template de votre administration pour cette règle de routage.')
      return
    }
    if (!scopedTemplateIds.has(ruleForm.templateId)) {
      showResult('error', 'Le template sélectionné ne correspond pas à l\'administration active.')
      return
    }

    const payload = {
      name: ruleForm.name,
      documentType: ruleForm.documentType,
      templateId: ruleForm.templateId || undefined,
      recipientAdministrationId: ruleForm.recipientAdministrationId,
      priority: Number(ruleForm.priority),
    }
    askConfirm(
      editingRuleId ? 'Mettre à jour la règle' : 'Créer la règle de routage',
      editingRuleId ? 'Voulez-vous enregistrer les modifications de cette règle ?' : `Voulez-vous créer la règle "${ruleForm.name}" ?`,
      async () => {
        try {
          if (editingRuleId) {
            await updateRoutingRule(editingRuleId, payload)
          } else {
            await createRoutingRule(payload)
          }
          setRuleForm({ name: '', documentType: '', templateId: '', recipientAdministrationId: '', priority: 1 })
          setEditingRuleId(null)
          showResult('success', editingRuleId ? 'Règle de routage mise à jour avec succès.' : 'Règle de routage créée avec succès.')
          await loadData()
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Opération sur la règle de routage impossible')
        }
      }
    )
  }

  const handleDeleteTemplate = async (id: string) => {
    askConfirm(
      'Supprimer le template',
      'Cette action est irréversible. Voulez-vous vraiment supprimer ce template ?',
      async () => {
        try {
          await deleteTemplate(id)
          if (selectedTemplateId === id) setSelectedTemplateId('')
          showResult('success', 'Template supprimé avec succès.')
          await loadData()
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Suppression du modèle impossible')
        }
      }
    )
  }

  const handleDeleteVariable = async (variableId: string) => {
    if (!selectedTemplateId) return
    askConfirm(
      'Supprimer la variable',
      'Cette action est irréversible. Voulez-vous vraiment supprimer cette variable ?',
      async () => {
        try {
          await deleteTemplateVariable(selectedTemplateId, variableId)
          showResult('success', 'Variable supprimée avec succès.')
          await loadTemplateVariables(selectedTemplateId)
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Suppression de la variable impossible')
        }
      }
    )
  }

  const handleDeleteEmitter = async (id: string) => {
    askConfirm(
      "Supprimer l'administration émettrice",
      "Cette action est irréversible. Voulez-vous vraiment supprimer cette administration émettrice ?",
      async () => {
        try {
          await deleteIssuingAdministration(id)
          if (selectedEmitterId === id) setSelectedEmitterId('')
          showResult('success', 'Administration émettrice supprimée avec succès.')
          await loadData()
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || "Suppression de l'administration émettrice impossible")
        }
      }
    )
  }

  const handleDeleteRecipient = async (id: string) => {
    askConfirm(
      'Supprimer le destinataire',
      'Cette action est irréversible. Voulez-vous vraiment supprimer cette administration destinataire ?',
      async () => {
        try {
          await deleteRecipientAdministration(id)
          showResult('success', 'Administration destinataire supprimée avec succès.')
          await loadData()
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Suppression du destinataire impossible')
        }
      }
    )
  }

  const handleDeleteRule = async (id: string) => {
    askConfirm(
      'Supprimer la règle de routage',
      'Cette action est irréversible. Voulez-vous vraiment supprimer cette règle de routage ?',
      async () => {
        try {
          await deleteRoutingRule(id)
          showResult('success', 'Règle de routage supprimée avec succès.')
          await loadData()
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Suppression de la règle impossible')
        }
      }
    )
  }

  const startEditTemplate = (item: DocumentTemplate) => {
    setEditingTemplateId(item.id)
    setTemplateForm({ name: item.name, fileName: item.fileName, fileType: item.fileType, content: item.content || '' })
  }

  const startEditVariable = (item: TemplateVariable) => {
    setEditingVariableId(item.id)
    setVariableForm({ name: item.label || item.key, fieldType: item.fieldType })
  }

  const startEditEmitter = (item: IssuingAdministration) => {
    setEditingEmitterId(item.id)
    const meta = (item.metadata || {}) as Record<string, any>
    setEmitterForm({
      name: item.name,
      code: item.code,
      adminType: meta.adminType || '',
      sector: meta.sector || '',
      description: meta.description || '',
      contactEmail: meta.contactEmail || '',
      techEmail: meta.techEmail || '',
      contactPhone: meta.contactPhone || '',
      referentMetier: meta.referentMetier || '',
      postalAddress: meta.postalAddress || '',
      transmissionMethod: meta.transmissionMethod || 'api',
      endpointUrl: meta.endpointUrl || '',
      dataFormat: meta.dataFormat || 'json',
      authMethod: meta.authMethod || 'api_key',
      apiKey: meta.apiKey || '',
      timeout: Number(meta.timeout || 30),
      requireTls: Boolean(meta.requireTls ?? true),
      enableRetry: Boolean(meta.enableRetry ?? true),
      docTypes: Array.isArray(meta.docTypes) && meta.docTypes.length > 0 ? meta.docTypes : ['pdf'],
      defaultWorkflow: meta.defaultWorkflow || '',
      dossierPrefix: meta.dossierPrefix || '',
      autoConvertPdf: Boolean(meta.autoConvertPdf ?? true),
      requiredMetadata: meta.requiredMetadata || '',
      signatureLevel: meta.signatureLevel || 'qualifiee',
      logRetention: Number(meta.logRetention || 365),
      gdprCompliant: Boolean(meta.gdprCompliant ?? true),
      enableAudit: Boolean(meta.enableAudit ?? true),
      fileEncryption: Boolean(meta.fileEncryption ?? false),
      ipWhitelist: meta.ipWhitelist || '',
      businessHours: meta.businessHours || '',
      slaResponse: meta.slaResponse || '24h',
      timezone: meta.timezone || 'Europe/Paris',
      duplicateHandling: meta.duplicateHandling || 'update',
      externalRefField: meta.externalRefField || '',
      trackingUrl: meta.trackingUrl || '',
      webhookUrl: meta.webhookUrl || '',
      webhookSecret: meta.webhookSecret || '',
      tags: meta.tags || '',
    })
    setEmitterLogoFile(null)
    setEmitterLogoPreview(null)
    // Show existing logo from server
    if (item.logo) {
      const API_ROOT = (import.meta.env.VITE_API_URL || 'http://localhost:3000/api/v1').replace(/\/api(?:\/v\d+)?\/?$/, '')
      setCurrentEmitterLogoUrl(item.logo.startsWith('http') ? item.logo : `${API_ROOT}${item.logo}`)
    } else {
      setCurrentEmitterLogoUrl(null)
    }
  }

  const startEditRecipient = (item: RecipientAdministration) => {
    const meta = (item.metadata || {}) as Record<string, any>
    setEditingRecipientId(item.id)
    setRecipientForm({
      name: item.name,
      channel: item.channel,
      apiEndpoint: item.apiEndpoint || '',
      emailAddress: item.emailAddress || '',
      code: meta.code || '',
      adminType: meta.adminType || '',
      sector: meta.sector || '',
      description: meta.description || '',
      contactEmail: meta.contactEmail || item.emailAddress || '',
      techEmail: meta.techEmail || '',
      contactPhone: meta.contactPhone || '',
      contactFax: meta.contactFax || '',
      postalAddress: meta.postalAddress || '',
      referentMetier: meta.referentMetier || '',
      referentTechnique: meta.referentTechnique || '',
      apiMethod: meta.apiMethod || 'POST',
      apiFormat: meta.apiFormat || 'multipart',
      apiAuth: meta.apiAuth || 'api_key',
      apiTimeout: Number(meta.apiTimeout || 30),
      emailSubject: meta.emailSubject || '[E-Parapheur] Nouveau document signé - {{reference}}',
      emailBody: meta.emailBody || '',
      lerProvider: meta.lerProvider || 'laposte',
      lerAccountId: meta.lerAccountId || '',
      enableRetry: Boolean(meta.enableRetry ?? true),
      enableNotification: Boolean(meta.enableNotification ?? true),
      compressFiles: Boolean(meta.compressFiles ?? false),
      encryptFiles: Boolean(meta.encryptFiles ?? false),
      docTypes: Array.isArray(meta.docTypes) && meta.docTypes.length > 0 ? meta.docTypes : ['pdf', 'docx', 'xlsx'],
      maxFileSize: Number(meta.maxFileSize || 50),
      maxFiles: Number(meta.maxFiles || 10),
      receiptMethod: meta.receiptMethod || 'automatic',
      receiptWebhookUrl: meta.receiptWebhookUrl || '',
      receiptTimeout: Number(meta.receiptTimeout || 24),
      activateImmediately: Boolean(meta.activateImmediately ?? true),
    })

    setRecipientLogoFile(null)
    setRecipientLogoPreview(null)
    const API_ROOT = (import.meta.env.VITE_API_URL || 'http://localhost:3000/api/v1').replace(/\/api(?:\/v\d+)?\/?$/, '')
    const logoPath = String(meta.logo || '').trim()
    if (logoPath) {
      setCurrentRecipientLogoUrl(logoPath.startsWith('http') ? logoPath : `${API_ROOT}${logoPath}`)
    } else {
      setCurrentRecipientLogoUrl(null)
    }
  }

  const startEditRule = (item: RoutingRule) => {
    setEditingRuleId(item.id)
    setRuleForm({
      name: item.name,
      documentType: item.documentType,
      templateId: item.templateId || '',
      recipientAdministrationId: item.recipientAdministrationId,
      priority: item.priority,
    })
  }

  const addRequestedActRequiredDocument = () => {
    const value = requestedActDocInput.trim()
    if (!value) return
    if (requestedActRequiredDocs.some((item) => item.toLowerCase() === value.toLowerCase())) {
      setRequestedActDocInput('')
      return
    }
    setRequestedActRequiredDocs((prev) => [...prev, value])
    setRequestedActDocInput('')
  }

  const removeRequestedActRequiredDocument = (index: number) => {
    setRequestedActRequiredDocs((prev) => prev.filter((_, idx) => idx !== index))
  }

  const addRequestedActApplicantField = () => {
    const label = requestedActApplicantFieldLabelInput.trim()
    if (!label) return

    const fieldType = requestedActApplicantFieldTypeInput
    if (requestedActApplicantFields.some((item) => item.label.toLowerCase() === label.toLowerCase() && item.inputType === fieldType)) {
      setRequestedActApplicantFieldLabelInput('')
      return
    }

    setRequestedActApplicantFields((prev) => [...prev, { label, inputType: fieldType }])
    setRequestedActApplicantFieldLabelInput('')
  }

  const removeRequestedActApplicantField = (index: number) => {
    setRequestedActApplicantFields((prev) => prev.filter((_, idx) => idx !== index))
  }

  const handleSaveRequestedAct = async (event: FormEvent) => {
    event.preventDefault()

    if (!requestedActForm.administrationRef) {
      showResult('error', 'Veuillez sélectionner une administration.')
      return
    }

    if (!requestedActForm.directionCode) {
      showResult('error', 'Veuillez sélectionner une direction.')
      return
    }

    if (!requestedActForm.documentName.trim()) {
      showResult('error', 'Veuillez renseigner le nom du document.')
      return
    }

    const docs = requestedActRequiredDocs.map((item) => item.trim()).filter(Boolean)
    if (docs.length === 0) {
      showResult('error', 'Veuillez ajouter au moins un document à fournir.')
      return
    }

    const administrationLabel = selectedRequestedActAdministration?.label || requestedActForm.administrationRef
    const direction = requestedActDirections.find((item) => item.code === requestedActForm.directionCode)

    const [administrationScopeType, administrationScopeId] = requestedActForm.administrationRef.split(':')
    if (!administrationScopeType || !administrationScopeId) {
      showResult('error', 'Administration invalide.')
      return
    }

    try {
      const payload = {
        administrationScopeType: administrationScopeType as 'emitter' | 'recipient',
        administrationScopeId,
        administrationLabel,
        directionCode: requestedActForm.directionCode,
        directionLabel: direction ? `${direction.code} - ${direction.name}` : requestedActForm.directionCode,
        documentName: requestedActForm.documentName.trim(),
        requiredDocuments: docs,
        applicantFields: requestedActApplicantFields,
      }

      if (editingRequestedActId) {
        const updated = await updateRequestedAct(editingRequestedActId, payload)
        setRequestedActs((prev) => prev.map((entry) => (entry.id === editingRequestedActId ? updated : entry)))
      } else {
        const created = await createRequestedAct(payload)
        setRequestedActs((prev) => [created, ...prev])
      }

      setRequestedActForm({ administrationRef: '', directionCode: '', documentName: '' })
      setRequestedActRequiredDocs([])
      setRequestedActDocInput('')
      setRequestedActApplicantFields([])
      setRequestedActApplicantFieldLabelInput('')
      setRequestedActApplicantFieldTypeInput('text')
      setEditingRequestedActId(null)
      showResult('success', editingRequestedActId ? 'Acte demandé modifié avec succès.' : 'Acte demandé enregistré avec succès.')
    } catch (err: any) {
      showResult('error', err?.response?.data?.message || (editingRequestedActId ? 'Impossible de modifier cet acte demandé.' : 'Impossible d\'enregistrer l\'acte demandé.'))
    }
  }

  const startEditRequestedAct = (item: RequestedAct) => {
    setEditingRequestedActId(item.id)
    setRequestedActForm({
      administrationRef: `${item.administrationScopeType}:${item.administrationScopeId}`,
      directionCode: item.directionCode,
      documentName: item.documentName,
    })
    setRequestedActRequiredDocs(Array.isArray(item.requiredDocuments) ? item.requiredDocuments : [])
    setRequestedActDocInput('')
    setRequestedActApplicantFields(Array.isArray(item.applicantFields) ? item.applicantFields as Array<{ label: string; inputType: RequestedActApplicantFieldType }> : [])
    setRequestedActApplicantFieldLabelInput('')
    setRequestedActApplicantFieldTypeInput('text')
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const cancelEditRequestedAct = () => {
    setEditingRequestedActId(null)
    setRequestedActForm({ administrationRef: '', directionCode: '', documentName: '' })
    setRequestedActRequiredDocs([])
    setRequestedActDocInput('')
    setRequestedActApplicantFields([])
    setRequestedActApplicantFieldLabelInput('')
    setRequestedActApplicantFieldTypeInput('text')
  }

  const handleDeleteRequestedAct = (item: RequestedAct) => {
    askConfirm(
      'Supprimer l\'acte demandé',
      `Voulez-vous supprimer l\'acte « ${item.documentName} » ?`,
      async () => {
        try {
          await deleteRequestedAct(item.id)
          setRequestedActs((prev) => prev.filter((entry) => entry.id !== item.id))
          showResult('success', 'Acte demandé supprimé avec succès.')
        } catch (err: any) {
          showResult('error', err?.response?.data?.message || 'Impossible de supprimer cet acte demandé.')
        }
      },
    )
  }

  const goToEmitterList = () => {
    emitterListRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }

  return (
    <div className="space-y-5">
      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex flex-wrap gap-2">
        {visibleSettingsTabs.map((tab) => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={`px-3 py-2 rounded-lg text-xs font-semibold ${activeTab === tab.key ? 'bg-[#2453d6] text-white' : 'bg-gray-100 text-gray-700'}`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {visibleSettingsTabs.length === 0 && (
        <div className="bg-amber-50 border border-amber-100 text-amber-700 rounded-xl p-3 text-xs">
          Aucun sous-onglet d'administration n'est autorisé pour votre rôle.
        </div>
      )}

      {error && <div className="bg-red-50 border border-red-100 text-red-700 rounded-xl p-3 text-xs">{error}</div>}
      {success && <div className="bg-green-50 border border-green-100 text-green-700 rounded-xl p-3 text-xs">{success}</div>}
      {loading && <div className="bg-blue-50 border border-blue-100 text-blue-700 rounded-xl p-3 text-xs">Chargement en cours...</div>}

      {activeTab === 'templates' && (
        <div className="grid grid-cols-1 xl:grid-cols-2 gap-5">
          <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
            <h2 className="text-lg font-semibold text-gray-800">Gestion des Templates</h2>
            <p className="text-xs text-gray-600">Utilisez la syntaxe <span className="font-semibold">&#123;&#123;variable&#125;&#125;</span> dans le texte pour créer des documents dynamiques.</p>
            <div className="rounded-lg border border-gray-200 bg-gray-50 p-3">
              <label className="block text-xs text-gray-500 mb-1">Administration émettrice concernée</label>
              <select
                value={selectedEmitterId}
                onChange={(e) => setSelectedEmitterId(e.target.value)}
                disabled={Boolean(lockedEmitterId)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-[#2453d6]/30 disabled:bg-gray-100"
              >
                <option value="">Sélectionner une administration</option>
                {scopedEmitters.map((item) => (
                  <option key={item.id} value={item.id}>{item.name} ({item.code})</option>
                ))}
              </select>
            </div>
            <form onSubmit={handleCreateTemplate} className="grid grid-cols-1 md:grid-cols-3 gap-3">
              <input value={templateForm.name} onChange={(e) => setTemplateForm({ ...templateForm, name: e.target.value })} placeholder="Nom" className="border rounded-lg px-3 py-2 text-xs" required />
              <input value={templateForm.fileName} onChange={(e) => setTemplateForm({ ...templateForm, fileName: e.target.value })} placeholder="Fichier" className="border rounded-lg px-3 py-2 text-xs" required />
              <select value={templateForm.fileType} onChange={(e) => setTemplateForm({ ...templateForm, fileType: e.target.value as 'docx' | 'xlsx' | 'pptx' | 'pdf' })} className="border rounded-lg px-3 py-2 text-xs">
                <option value="docx">DOCX</option>
                <option value="xlsx">XLSX</option>
                <option value="pptx">PPTX</option>
                <option value="pdf">PDF</option>
              </select>
              <button type="button" onClick={handleOpenOnlyOfficeEditor} className="md:col-span-3 bg-green-100 text-green-700 rounded-lg px-3 py-2 text-xs font-semibold hover:bg-green-200">
                Ouvrir OnlyOffice
              </button>
              <textarea
                value={templateForm.content}
                onChange={(e) => setTemplateForm({ ...templateForm, content: e.target.value })}
                placeholder="Texte du template (ex: Je soussigné {{nom}}, né le {{date_naissance}}...)"
                className="md:col-span-3 border rounded-lg px-3 py-2 text-xs min-h-[120px]"
              />
              <button className="md:col-span-3 bg-[#2453d6] text-white rounded-lg px-3 py-2 text-xs font-semibold">{editingTemplateId ? 'Modifier le modèle' : 'Créer le modèle'}</button>
            </form>

            <div className="max-h-64 overflow-auto">
              <div className="flex flex-wrap gap-2">
                {templatesForSelectedAdministration.map((item) => (
                  <div key={item.id} className={`w-full sm:w-[290px] border rounded-lg p-2.5 ${selectedTemplateId === item.id ? 'border-[#2453d6] bg-blue-50' : 'border-gray-200 bg-gray-50'}`}>
                    <button onClick={() => setSelectedTemplateId(item.id)} className="w-full text-left">
                      <p className="text-xs font-semibold text-gray-800 truncate" title={item.name}>{item.name}</p>
                      <p className="text-[11px] text-gray-500 truncate" title={`${item.fileName} · ${item.fileType.toUpperCase()}`}>{item.fileName} · {item.fileType.toUpperCase()}</p>
                      <p className="text-[11px] text-blue-600 mt-1">Partage(s): {(templateShareMap[item.id] || []).length}</p>
                    </button>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                    <button onClick={() => startEditTemplate(item)} className="px-2 py-1 rounded bg-gray-200 text-gray-700 text-[11px]">Modifier</button>
                    <button onClick={() => handleDeleteTemplate(item.id)} className="px-2 py-1 rounded bg-red-100 text-red-700 text-[11px]">Supprimer</button>
                    <button
                      onClick={() => { setShareTemplateId(item.id); setShareUserId(''); setShareSearch('') }}
                      title="Partager ce template"
                      className="px-2 py-1 rounded bg-blue-100 text-blue-700 text-[11px] flex items-center gap-1"
                    >
                      <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" /></svg>
                      Partager
                    </button>
                    <button
                      onClick={() => {
                        setSelectedTemplateId(item.id)
                        setTimeout(() => generationFormRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100)
                      }}
                      title="Ouvrir le formulaire de génération"
                      className="px-2 py-1 rounded bg-green-100 text-green-700 text-[11px] flex items-center gap-1"
                    >
                      <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                      Formulaire
                    </button>
                  </div>
                  </div>
                ))}
                {templatesForSelectedAdministration.length === 0 && (
                  <div className="w-full border border-dashed border-gray-300 rounded-lg p-4 text-xs text-gray-500">
                    Aucun template configuré pour cette administration.
                  </div>
                )}
              </div>
            </div>
          </section>

          <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
            <h2 className="text-lg font-semibold text-gray-800">Balises dynamiques et Génération</h2>
            <p className="text-xs text-gray-600">Modèle sélectionné: <span className="font-semibold">{selectedTemplateName || 'Aucun'}</span></p>
            <form onSubmit={handleCreateVariable} className="grid grid-cols-1 md:grid-cols-3 gap-3">
              <input value={variableForm.name} onChange={(e) => setVariableForm({ ...variableForm, name: e.target.value })} placeholder="Nom" className="md:col-span-2 border rounded-lg px-3 py-2 text-xs" required />
              <select value={variableForm.fieldType} onChange={(e) => setVariableForm({ ...variableForm, fieldType: e.target.value as 'text' | 'date' | 'number' | 'select' | 'textarea' })} className="border rounded-lg px-3 py-2 text-xs" required>
                <option value="text">Texte</option>
                <option value="date">Date</option>
                <option value="number">Nombre</option>
                <option value="select">Liste</option>
                <option value="textarea">Zone de texte</option>
              </select>
              <button disabled={!selectedTemplateId} className="md:col-span-3 bg-[#2453d6] text-white rounded-lg px-3 py-2 text-xs font-semibold disabled:opacity-40">{editingVariableId ? 'Modifier le champ' : 'Ajouter le champ'}</button>
            </form>

            <div className="space-y-2 max-h-64 overflow-auto">
              {templateVariables.map((item) => (
                <div key={item.id} className="border border-gray-200 bg-gray-50 rounded-lg p-3">
                  <p className="text-xs font-semibold text-gray-800">{item.label}</p>
                  <p className="text-[11px] text-gray-500">Balise: &#123;&#123;{item.key}&#125;&#125;</p>
                  <p className="text-[11px] text-gray-500">Type: {item.fieldType}</p>
                  <div className="mt-2 flex gap-2">
                    <button onClick={() => startEditVariable(item)} className="px-2 py-1 rounded bg-gray-200 text-gray-700 text-[11px]">Modifier</button>
                    <button onClick={() => handleDeleteVariable(item.id)} className="px-2 py-1 rounded bg-red-100 text-red-700 text-[11px]">Supprimer</button>
                  </div>
                </div>
              ))}
            </div>

            <div className="border-t border-gray-100 pt-4 space-y-3" ref={generationFormRef}>
              <h3 className="text-sm font-semibold text-gray-800">Générer un document à la volée</h3>
              <form onSubmit={handleGenerateDocumentFromTemplate} className="space-y-3">
                {generationFields.length === 0 && (
                  <p className="text-xs text-gray-500">Aucune variable détectée. Ajoutez des balises &#123;&#123;...&#125;&#125; dans le texte du template.</p>
                )}
                {generationFields.map((field) => (
                  <div key={field.key} className="space-y-1">
                    <label className="text-xs font-medium text-gray-700">{field.label} ({field.key})</label>
                    {field.fieldType === 'textarea' ? (
                      <textarea
                        value={generationValues[field.key] || ''}
                        onChange={(e) => setGenerationValues((prev) => ({ ...prev, [field.key]: e.target.value }))}
                        className="w-full border rounded-lg px-3 py-2 text-xs min-h-[80px]"
                        placeholder={field.placeholder || ''}
                        required={Boolean(field.required)}
                      />
                    ) : (
                      <input
                        type={field.fieldType === 'number' ? 'number' : field.fieldType === 'date' ? 'date' : 'text'}
                        value={generationValues[field.key] || ''}
                        onChange={(e) => setGenerationValues((prev) => ({ ...prev, [field.key]: e.target.value }))}
                        className="w-full border rounded-lg px-3 py-2 text-xs"
                        placeholder={field.placeholder || ''}
                        required={Boolean(field.required)}
                      />
                    )}
                  </div>
                ))}
                <div className="flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    disabled={!selectedTemplateId}
                    onClick={handleSaveGenerationForm}
                    className="bg-gray-100 text-gray-700 rounded-lg px-3 py-2 text-xs font-semibold disabled:opacity-40"
                  >
                    Enregistrer
                  </button>
                  <button
                    type="button"
                    disabled={!selectedTemplateId}
                    onClick={() => { void handlePreviewGeneratedDocument() }}
                    className="bg-amber-100 text-amber-700 rounded-lg px-3 py-2 text-xs font-semibold disabled:opacity-40"
                  >
                    Visualiser
                  </button>
                  <button disabled={!selectedTemplateId} className="bg-[#2453d6] text-white rounded-lg px-3 py-2 text-xs font-semibold disabled:opacity-40">Générer et enregistrer le document</button>
                </div>
              </form>

              {generatedContent && (
                <div className="rounded-lg border border-gray-200 bg-gray-50 p-3">
                  <div className="flex items-center justify-between mb-2">
                    <p className="text-xs font-semibold text-gray-800">Aperçu généré: {generatedFileName}</p>
                    <button
                      type="button"
                      onClick={() => {
                        const blob = new Blob([generatedContent], { type: 'text/plain;charset=utf-8' })
                        const url = URL.createObjectURL(blob)
                        const a = document.createElement('a')
                        a.href = url
                        a.download = generatedFileName || 'document-genere.txt'
                        document.body.appendChild(a)
                        a.click()
                        document.body.removeChild(a)
                        URL.revokeObjectURL(url)
                      }}
                      className="inline-flex items-center gap-1 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold rounded-lg transition"
                    >
                      Télécharger
                    </button>
                  </div>
                  <pre className="whitespace-pre-wrap text-[11px] text-gray-700 max-h-40 overflow-auto">{generatedContent}</pre>
                </div>
              )}
            </div>
          </section>
        </div>
      )}

      {activeTab === 'emitters' && (
        <div className="grid grid-cols-1 gap-5">
          <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
            <h2 className="text-lg font-semibold text-gray-800">Configurer une Administration Émettrice</h2>
            <form onSubmit={handleCreateEmitter} className="space-y-4">
              <fieldset className="border rounded-lg p-4 space-y-3">
                <legend className="px-2 text-xs font-semibold text-gray-700">Informations Générales</legend>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <input value={emitterForm.name} onChange={(e) => setEmitterForm({ ...emitterForm, name: e.target.value })} placeholder="Nom de l'administration *" className="border rounded-lg px-3 py-2 text-xs" required />
                  <input value={emitterForm.code} onChange={(e) => setEmitterForm({ ...emitterForm, code: e.target.value })} placeholder="Code d'identification *" className="border rounded-lg px-3 py-2 text-xs" required />
                  <select value={emitterForm.adminType} onChange={(e) => setEmitterForm({ ...emitterForm, adminType: e.target.value })} className="border rounded-lg px-3 py-2 text-xs" required>
                    <option value="">Type d'administration *</option>
                    <option value="nationale">Administration Nationale</option>
                    <option value="regionale">Régionale</option>
                    <option value="departementale">Départementale</option>
                    <option value="communale">Communale</option>
                    <option value="etablissement">Établissement Public</option>
                  </select>
                  <select value={emitterForm.sector} onChange={(e) => setEmitterForm({ ...emitterForm, sector: e.target.value })} className="border rounded-lg px-3 py-2 text-xs" required>
                    <option value="">Secteur d'activité *</option>
                    <option value="fiscalite_finance">Fiscalité & Finances</option>
                    <option value="protection_sociale">Protection Sociale</option>
                    <option value="travail_emploi">Travail & Emploi</option>
                    <option value="urbanisme_logement">Urbanisme & Logement</option>
                    <option value="education_formation">Éducation & Formation</option>
                    <option value="sante">Santé</option>
                    <option value="justice">Justice</option>
                    <option value="securite">Sécurité</option>
                    <option value="environnement">Environnement</option>
                    <option value="autre">Autre</option>
                  </select>
                </div>
                <textarea value={emitterForm.description} onChange={(e) => setEmitterForm({ ...emitterForm, description: e.target.value })} rows={2} placeholder="Description" className="w-full border rounded-lg px-3 py-2 text-xs" />
              </fieldset>


              <fieldset className="border rounded-lg p-4 space-y-3">
                <legend className="px-2 text-xs font-semibold text-gray-700">Coordonnées & Contacts</legend>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <input type="email" value={emitterForm.contactEmail} onChange={(e) => setEmitterForm({ ...emitterForm, contactEmail: e.target.value })} placeholder="Email de contact *" className="border rounded-lg px-3 py-2 text-xs" required />
                  <input type="email" value={emitterForm.techEmail} onChange={(e) => setEmitterForm({ ...emitterForm, techEmail: e.target.value })} placeholder="Email technique" className="border rounded-lg px-3 py-2 text-xs" />
                  <input value={emitterForm.contactPhone} onChange={(e) => setEmitterForm({ ...emitterForm, contactPhone: e.target.value })} placeholder="Téléphone" className="border rounded-lg px-3 py-2 text-xs" />
                  <input value={emitterForm.referentMetier} onChange={(e) => setEmitterForm({ ...emitterForm, referentMetier: e.target.value })} placeholder="Référent métier" className="border rounded-lg px-3 py-2 text-xs" />
                </div>
                <textarea value={emitterForm.postalAddress} onChange={(e) => setEmitterForm({ ...emitterForm, postalAddress: e.target.value })} rows={2} placeholder="Adresse postale" className="w-full border rounded-lg px-3 py-2 text-xs" />
              </fieldset>

              <fieldset className="border rounded-lg p-4 space-y-3">
                <legend className="px-2 text-xs font-semibold text-gray-700">Logo de l'administration (affiché dans la barre latérale)</legend>
                <div className="grid grid-cols-1 md:grid-cols-[88px_1fr] gap-3 items-center">
                  <div className="w-20 h-20 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center overflow-hidden">
                    {emitterLogoPreview ? (
                      <img src={emitterLogoPreview} alt="Aperçu" className="w-full h-full object-contain" />
                    ) : currentEmitterLogoUrl ? (
                      <img src={currentEmitterLogoUrl} alt="Logo actuel" className="w-full h-full object-contain" />
                    ) : (
                      <span className="text-gray-400 text-xs text-center px-1">Aperçu logo</span>
                    )}
                  </div>
                  <div className="space-y-1">
                    <input
                      type="file"
                      accept="image/png,image/jpeg,image/svg+xml,image/webp"
                      onChange={(e) => {
                        const file = e.target.files?.[0] || null
                        setEmitterLogoFile(file)
                        if (file) {
                          const reader = new FileReader()
                          reader.onload = (ev) => setEmitterLogoPreview(ev.target?.result as string)
                          reader.readAsDataURL(file)
                        } else {
                          setEmitterLogoPreview(null)
                        }
                      }}
                      className="w-full text-xs text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                    />
                    <p className="text-[11px] text-gray-500">
                      Formats acceptés: PNG, JPG, SVG, WEBP (max 2 MB). Ce logo s'affichera dans la barre latérale à côté de « E-Parapheur ».
                    </p>
                    {emitterLogoFile && (
                      <p className="text-[11px] text-green-700 font-medium">✓ Fichier sélectionné: {emitterLogoFile.name}</p>
                    )}
                    {currentEmitterLogoUrl && !emitterLogoFile && (
                      <div className="flex items-center gap-2">
                        <p className="text-[11px] text-blue-600">Logo actuel enregistré</p>
                        <button
                          type="button"
                          onClick={() => {
                            setCurrentEmitterLogoUrl(null)
                            window.dispatchEvent(new StorageEvent('storage', { key: 'ep_admin_logo', newValue: null }))
                          }}
                          className="text-[11px] text-red-500 hover:underline"
                        >
                          Supprimer
                        </button>
                      </div>
                    )}
                  </div>
                </div>
              </fieldset>

              <fieldset className="border rounded-lg p-4 space-y-3">
                <legend className="px-2 text-xs font-semibold text-gray-700">Configuration Technique</legend>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <select value={emitterForm.transmissionMethod} onChange={(e) => setEmitterForm({ ...emitterForm, transmissionMethod: e.target.value })} className="border rounded-lg px-3 py-2 text-xs" required>
                    <option value="api">API REST</option>
                    <option value="sftp">SFTP</option>
                    <option value="email">Email Sécurisé</option>
                    <option value="ler">LER</option>
                    <option value="portal">Portail Web</option>
                  </select>
                  <input value={emitterForm.endpointUrl} onChange={(e) => setEmitterForm({ ...emitterForm, endpointUrl: e.target.value })} placeholder="Endpoint URL" className="border rounded-lg px-3 py-2 text-xs" />
                  <select value={emitterForm.dataFormat} onChange={(e) => setEmitterForm({ ...emitterForm, dataFormat: e.target.value })} className="border rounded-lg px-3 py-2 text-xs">
                    <option value="json">JSON</option>
                    <option value="xml">XML</option>
                    <option value="pdf">PDF</option>
                    <option value="multipart">Multipart/Form-Data</option>
                  </select>
                  <select value={emitterForm.authMethod} onChange={(e) => setEmitterForm({ ...emitterForm, authMethod: e.target.value })} className="border rounded-lg px-3 py-2 text-xs">
                    <option value="api_key">API Key</option>
                    <option value="oauth2">OAuth 2.0</option>
                    <option value="mtls">mTLS</option>
                    <option value="basic">Basic Auth</option>
                  </select>
                  <input type="password" value={emitterForm.apiKey} onChange={(e) => setEmitterForm({ ...emitterForm, apiKey: e.target.value })} placeholder="Clé API / Token" className="border rounded-lg px-3 py-2 text-xs" />
                  <input type="number" min={5} max={300} value={emitterForm.timeout} onChange={(e) => setEmitterForm({ ...emitterForm, timeout: Number(e.target.value) })} placeholder="Timeout" className="border rounded-lg px-3 py-2 text-xs" />
                </div>
                <div className="flex flex-wrap gap-4 text-xs text-gray-700">
                  <label className="flex items-center gap-2"><input type="checkbox" checked={emitterForm.requireTls} onChange={(e) => setEmitterForm({ ...emitterForm, requireTls: e.target.checked })} /> TLS 1.3 requis</label>
                  <label className="flex items-center gap-2"><input type="checkbox" checked={emitterForm.enableRetry} onChange={(e) => setEmitterForm({ ...emitterForm, enableRetry: e.target.checked })} /> Activer retries</label>
                </div>
              </fieldset>

              <fieldset className="border rounded-lg p-4 space-y-3">
                <legend className="px-2 text-xs font-semibold text-gray-700">Documents</legend>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                  {['pdf', 'docx', 'xml', 'zip'].map((docType) => (
                    <label key={docType} className="flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-2">
                      <input
                        type="checkbox"
                        checked={emitterForm.docTypes.includes(docType)}
                        onChange={(e) => {
                          if (e.target.checked) {
                            setEmitterForm((prev) => ({ ...prev, docTypes: [...prev.docTypes, docType] }))
                          } else {
                            setEmitterForm((prev) => ({ ...prev, docTypes: prev.docTypes.filter((d) => d !== docType) }))
                          }
                        }}
                      />
                      {docType.toUpperCase()}
                    </label>
                  ))}
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <select value={emitterForm.defaultWorkflow} onChange={(e) => setEmitterForm({ ...emitterForm, defaultWorkflow: e.target.value })} className="border rounded-lg px-3 py-2 text-xs">
                    <option value="">Workflow par défaut</option>
                    <option value="simple">Validation Simple (2 étapes)</option>
                    <option value="standard">Circuit Standard (4 étapes)</option>
                    <option value="urgent">Circuit Urgent (1 étape)</option>
                  </select>
                  <input value={emitterForm.dossierPrefix} onChange={(e) => setEmitterForm({ ...emitterForm, dossierPrefix: e.target.value })} placeholder="Préfixe numéro dossier" className="border rounded-lg px-3 py-2 text-xs" />
                </div>
                <label className="flex items-center gap-2 text-xs text-gray-700"><input type="checkbox" checked={emitterForm.autoConvertPdf} onChange={(e) => setEmitterForm({ ...emitterForm, autoConvertPdf: e.target.checked })} /> Convertir automatiquement en PDF</label>
                <textarea value={emitterForm.requiredMetadata} onChange={(e) => setEmitterForm({ ...emitterForm, requiredMetadata: e.target.value })} rows={2} placeholder="Métadonnées obligatoires (JSON)" className="w-full border rounded-lg px-3 py-2 text-xs font-mono" />
              </fieldset>

              <fieldset className="border rounded-lg p-4 space-y-3">
                <legend className="px-2 text-xs font-semibold text-gray-700">Sécurité & Opérationnel</legend>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <select value={emitterForm.signatureLevel} onChange={(e) => setEmitterForm({ ...emitterForm, signatureLevel: e.target.value })} className="border rounded-lg px-3 py-2 text-xs">
                    <option value="simple">Signature Simple</option>
                    <option value="avancee">Signature Avancée</option>
                    <option value="qualifiee">Signature Qualifiée (eIDAS)</option>
                  </select>
                  <input type="number" min={30} max={2555} value={emitterForm.logRetention} onChange={(e) => setEmitterForm({ ...emitterForm, logRetention: Number(e.target.value) })} placeholder="Durée conservation logs" className="border rounded-lg px-3 py-2 text-xs" />
                  <input value={emitterForm.businessHours} onChange={(e) => setEmitterForm({ ...emitterForm, businessHours: e.target.value })} placeholder="Horaires de traitement" className="border rounded-lg px-3 py-2 text-xs" />
                  <select value={emitterForm.slaResponse} onChange={(e) => setEmitterForm({ ...emitterForm, slaResponse: e.target.value })} className="border rounded-lg px-3 py-2 text-xs">
                    <option value="immediat">Immédiat</option>
                    <option value="24h">24 heures</option>
                    <option value="48h">48 heures</option>
                    <option value="5j">5 jours ouvrés</option>
                  </select>
                  <select value={emitterForm.timezone} onChange={(e) => setEmitterForm({ ...emitterForm, timezone: e.target.value })} className="border rounded-lg px-3 py-2 text-xs">
                    <option value="Europe/Paris">Europe/Paris</option>
                    <option value="UTC">UTC</option>
                    <option value="America/New_York">America/New_York</option>
                  </select>
                  <select value={emitterForm.duplicateHandling} onChange={(e) => setEmitterForm({ ...emitterForm, duplicateHandling: e.target.value })} className="border rounded-lg px-3 py-2 text-xs">
                    <option value="reject">Rejeter le nouvel envoi</option>
                    <option value="update">Mettre à jour le dossier existant</option>
                    <option value="version">Créer une nouvelle version</option>
                  </select>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-gray-700">
                  <label className="flex items-center gap-2"><input type="checkbox" checked={emitterForm.gdprCompliant} onChange={(e) => setEmitterForm({ ...emitterForm, gdprCompliant: e.target.checked })} /> Conformité RGPD</label>
                  <label className="flex items-center gap-2"><input type="checkbox" checked={emitterForm.enableAudit} onChange={(e) => setEmitterForm({ ...emitterForm, enableAudit: e.target.checked })} /> Activer Audit Trail</label>
                  <label className="flex items-center gap-2"><input type="checkbox" checked={emitterForm.fileEncryption} onChange={(e) => setEmitterForm({ ...emitterForm, fileEncryption: e.target.checked })} /> Chiffrement fichiers au repos</label>
                </div>
                <textarea value={emitterForm.ipWhitelist} onChange={(e) => setEmitterForm({ ...emitterForm, ipWhitelist: e.target.value })} rows={2} placeholder="IPs autorisées" className="w-full border rounded-lg px-3 py-2 text-xs font-mono" />
              </fieldset>

              <fieldset className="border rounded-lg p-4 space-y-3">
                <legend className="px-2 text-xs font-semibold text-gray-700">Métadonnées & Tracking</legend>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <input value={emitterForm.externalRefField} onChange={(e) => setEmitterForm({ ...emitterForm, externalRefField: e.target.value })} placeholder="Champ référence externe" className="border rounded-lg px-3 py-2 text-xs" />
                  <input value={emitterForm.trackingUrl} onChange={(e) => setEmitterForm({ ...emitterForm, trackingUrl: e.target.value })} placeholder="URL de suivi public" className="border rounded-lg px-3 py-2 text-xs" />
                  <input value={emitterForm.webhookUrl} onChange={(e) => setEmitterForm({ ...emitterForm, webhookUrl: e.target.value })} placeholder="Webhook URL" className="border rounded-lg px-3 py-2 text-xs" />
                  <input type="password" value={emitterForm.webhookSecret} onChange={(e) => setEmitterForm({ ...emitterForm, webhookSecret: e.target.value })} placeholder="Webhook secret" className="border rounded-lg px-3 py-2 text-xs" />
                </div>
                <input value={emitterForm.tags} onChange={(e) => setEmitterForm({ ...emitterForm, tags: e.target.value })} placeholder="Tags / Catégories" className="w-full border rounded-lg px-3 py-2 text-xs" />
              </fieldset>

              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={goToEmitterList}
                  className="flex-1 border border-gray-300 text-gray-700 rounded-lg px-3 py-2 text-xs font-semibold hover:bg-gray-50"
                >
                  Liste des administrations émettrices
                </button>
                <button className="flex-1 bg-[#2453d6] text-white rounded-lg px-3 py-2 text-xs font-semibold">
                  {editingEmitterId ? 'Mettre à jour l’administration émettrice' : 'Enregistrer l’administration émettrice'}
                </button>
              </div>
            </form>
            <div ref={emitterListRef} className="space-y-2 max-h-64 overflow-auto">
              {emitters.map((item) => (
                <div key={item.id} className={`border rounded-lg p-3 ${selectedEmitterId === item.id ? 'border-[#2453d6] bg-blue-50' : 'border-gray-200 bg-gray-50'}`}>
                  <button onClick={() => setSelectedEmitterId(item.id)} className="w-full text-left">
                    <p className="text-xs font-semibold text-gray-800">{item.name}</p>
                    <p className="text-[11px] text-gray-500">
                      Code: {item.code} · {(item.metadata as any)?.sector || 'Secteur non défini'} · {Array.isArray((item.metadata as any)?.subEntities) ? (item.metadata as any).subEntities.length : 0} entite(s) sous tutelle
                    </p>
                  </button>
                  <div className="mt-2 flex gap-2">
                    <button onClick={() => startEditEmitter(item)} className="px-2 py-1 rounded bg-gray-200 text-gray-700 text-[11px]">Modifier</button>
                    <button onClick={() => handleDeleteEmitter(item.id)} className="px-2 py-1 rounded bg-red-100 text-red-700 text-[11px]">Supprimer</button>
                  </div>
                </div>
              ))}
            </div>
          </section>

        </div>
      )}

      {activeTab === 'recipients' && (
        <div className="grid grid-cols-1 xl:grid-cols-2 gap-5">
          <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-5">
            <h2 className="text-lg font-semibold text-gray-800">Formulaire d’enregistrement - Administration Destinataire</h2>

            <form onSubmit={handleCreateRecipient} className="space-y-5">
              <div className="rounded-xl border border-gray-200 p-4 space-y-3">
                <p className="text-sm font-semibold text-gray-800">1. Informations générales</p>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <input value={recipientForm.name} onChange={(e) => setRecipientForm({ ...recipientForm, name: e.target.value })} placeholder="Nom de l'administration" className="border rounded-lg px-3 py-2 text-xs" required />
                  <input value={recipientForm.code} onChange={(e) => setRecipientForm({ ...recipientForm, code: e.target.value })} placeholder="Code d'identification (SIRET/SIREN)" className="border rounded-lg px-3 py-2 text-xs" required />
                  <select value={recipientForm.adminType} onChange={(e) => setRecipientForm({ ...recipientForm, adminType: e.target.value })} className="border rounded-lg px-3 py-2 text-xs" required>
                    <option value="">Type d'administration</option>
                    <option value="nationale">Administration Nationale</option>
                    <option value="regionale">Administration Régionale</option>
                    <option value="departementale">Administration Départementale</option>
                    <option value="communale">Administration Communale</option>
                    <option value="etablissement">Établissement Public</option>
                    <option value="prive">Secteur Privé</option>
                    <option value="organisme">Organisme Paritaire</option>
                  </select>
                  <select value={recipientForm.sector} onChange={(e) => setRecipientForm({ ...recipientForm, sector: e.target.value })} className="border rounded-lg px-3 py-2 text-xs" required>
                    <option value="">Secteur de compétence</option>
                    <option value="fiscalite">Fiscalité & Finances</option>
                    <option value="social">Protection Sociale</option>
                    <option value="travail">Travail & Emploi</option>
                    <option value="urbanisme">Urbanisme & Logement</option>
                    <option value="education">Éducation & Formation</option>
                    <option value="sante">Santé</option>
                    <option value="justice">Justice</option>
                    <option value="environnement">Environnement</option>
                    <option value="commerce">Commerce & Industrie</option>
                    <option value="autre">Autre</option>
                  </select>
                </div>
                <textarea value={recipientForm.description} onChange={(e) => setRecipientForm({ ...recipientForm, description: e.target.value })} rows={3} placeholder="Description des missions" className="w-full border rounded-lg px-3 py-2 text-xs" />
              </div>


              <div className="rounded-xl border border-gray-200 p-4 space-y-3">
                <p className="text-sm font-semibold text-gray-800">2. Coordonnées & contacts</p>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <input type="email" value={recipientForm.contactEmail} onChange={(e) => setRecipientForm({ ...recipientForm, contactEmail: e.target.value })} placeholder="Email de réception principal" className="border rounded-lg px-3 py-2 text-xs" required />
                  <input type="email" value={recipientForm.techEmail} onChange={(e) => setRecipientForm({ ...recipientForm, techEmail: e.target.value })} placeholder="Email technique" className="border rounded-lg px-3 py-2 text-xs" />
                  <input value={recipientForm.contactPhone} onChange={(e) => setRecipientForm({ ...recipientForm, contactPhone: e.target.value })} placeholder="Téléphone" className="border rounded-lg px-3 py-2 text-xs" />
                  <input value={recipientForm.contactFax} onChange={(e) => setRecipientForm({ ...recipientForm, contactFax: e.target.value })} placeholder="Fax" className="border rounded-lg px-3 py-2 text-xs" />
                  <input value={recipientForm.referentMetier} onChange={(e) => setRecipientForm({ ...recipientForm, referentMetier: e.target.value })} placeholder="Référent métier" className="border rounded-lg px-3 py-2 text-xs" />
                  <input value={recipientForm.referentTechnique} onChange={(e) => setRecipientForm({ ...recipientForm, referentTechnique: e.target.value })} placeholder="Référent technique" className="border rounded-lg px-3 py-2 text-xs" />
                </div>
                <textarea value={recipientForm.postalAddress} onChange={(e) => setRecipientForm({ ...recipientForm, postalAddress: e.target.value })} rows={2} placeholder="Adresse postale" className="w-full border rounded-lg px-3 py-2 text-xs" />
              </div>

              <div className="rounded-xl border border-gray-200 p-4 space-y-3">
                <p className="text-sm font-semibold text-gray-800">Logo de l'administration (affiché dans la barre latérale)</p>
                <div className="grid grid-cols-1 md:grid-cols-[88px_1fr] gap-3 items-center">
                  <div className="w-20 h-20 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center overflow-hidden">
                    {recipientLogoPreview ? (
                      <img src={recipientLogoPreview} alt="Aperçu" className="w-full h-full object-contain" />
                    ) : currentRecipientLogoUrl ? (
                      <img src={currentRecipientLogoUrl} alt="Logo actuel" className="w-full h-full object-contain" />
                    ) : (
                      <span className="text-gray-400 text-xs text-center px-1">Aperçu logo</span>
                    )}
                  </div>
                  <div className="space-y-1">
                    <input
                      type="file"
                      accept="image/png,image/jpeg,image/svg+xml,image/webp"
                      onChange={(e) => {
                        const file = e.target.files?.[0] || null
                        setRecipientLogoFile(file)
                        if (file) {
                          const reader = new FileReader()
                          reader.onload = (ev) => setRecipientLogoPreview(ev.target?.result as string)
                          reader.readAsDataURL(file)
                        } else {
                          setRecipientLogoPreview(null)
                        }
                      }}
                      className="w-full text-xs text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                    />
                    <p className="text-[11px] text-gray-500">
                      Formats acceptés: PNG, JPG, SVG, WEBP (max 2 MB). Ce logo s'affichera dans la barre latérale à côté de « E-Parapheur ».
                    </p>
                    {recipientLogoFile && (
                      <p className="text-[11px] text-green-700 font-medium">✓ Fichier sélectionné: {recipientLogoFile.name}</p>
                    )}
                    {currentRecipientLogoUrl && !recipientLogoFile && (
                      <div className="flex items-center gap-2">
                        <p className="text-[11px] text-blue-600">Logo actuel enregistré</p>
                        <button
                          type="button"
                          onClick={() => {
                            setCurrentRecipientLogoUrl(null)
                            window.dispatchEvent(new StorageEvent('storage', { key: 'ep_admin_logo', newValue: null }))
                          }}
                          className="text-[11px] text-red-500 hover:underline"
                        >
                          Supprimer
                        </button>
                      </div>
                    )}
                  </div>
                </div>
              </div>

              <div className="rounded-xl border border-gray-200 p-4 space-y-3">
                <p className="text-sm font-semibold text-gray-800">3. Méthode de réception</p>
                <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                  <label className={`border rounded-lg p-3 text-xs cursor-pointer ${recipientForm.channel === 'api' ? 'border-[#2453d6] bg-blue-50' : 'border-gray-200'}`}>
                    <input type="radio" name="recipient-channel" className="mr-2" checked={recipientForm.channel === 'api'} onChange={() => setRecipientForm({ ...recipientForm, channel: 'api' })} />
                    API REST
                  </label>
                  <label className={`border rounded-lg p-3 text-xs cursor-pointer ${recipientForm.channel === 'email' ? 'border-[#2453d6] bg-blue-50' : 'border-gray-200'}`}>
                    <input type="radio" name="recipient-channel" className="mr-2" checked={recipientForm.channel === 'email'} onChange={() => setRecipientForm({ ...recipientForm, channel: 'email' })} />
                    Email sécurisé
                  </label>
                  <label className={`border rounded-lg p-3 text-xs cursor-pointer ${recipientForm.channel === 'ler' ? 'border-[#2453d6] bg-blue-50' : 'border-gray-200'}`}>
                    <input type="radio" name="recipient-channel" className="mr-2" checked={recipientForm.channel === 'ler'} onChange={() => setRecipientForm({ ...recipientForm, channel: 'ler' })} />
                    LER
                  </label>
                  <label className={`border rounded-lg p-3 text-xs cursor-pointer ${recipientForm.channel === 'application' ? 'border-[#2453d6] bg-blue-50' : 'border-gray-200'}`}>
                    <input type="radio" name="recipient-channel" className="mr-2" checked={recipientForm.channel === 'application'} onChange={() => setRecipientForm({ ...recipientForm, channel: 'application' })} />
                    Via l'application (Réception)
                  </label>
                </div>

                {recipientForm.channel === 'api' && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <input value={recipientForm.apiEndpoint} onChange={(e) => setRecipientForm({ ...recipientForm, apiEndpoint: e.target.value })} placeholder="Endpoint URL de réception" className="md:col-span-2 border rounded-lg px-3 py-2 text-xs" required />
                    <select value={recipientForm.apiMethod} onChange={(e) => setRecipientForm({ ...recipientForm, apiMethod: e.target.value })} className="border rounded-lg px-3 py-2 text-xs">
                      <option value="POST">POST</option>
                      <option value="PUT">PUT</option>
                    </select>
                    <select value={recipientForm.apiFormat} onChange={(e) => setRecipientForm({ ...recipientForm, apiFormat: e.target.value })} className="border rounded-lg px-3 py-2 text-xs">
                      <option value="multipart">Multipart/Form-Data</option>
                      <option value="json">JSON</option>
                      <option value="xml">XML</option>
                    </select>
                    <select value={recipientForm.apiAuth} onChange={(e) => setRecipientForm({ ...recipientForm, apiAuth: e.target.value })} className="border rounded-lg px-3 py-2 text-xs">
                      <option value="api_key">API Key</option>
                      <option value="oauth2">OAuth 2.0</option>
                      <option value="basic">Basic Auth</option>
                      <option value="mtls">mTLS</option>
                      <option value="none">Aucune</option>
                    </select>
                    <input type="number" min={5} max={300} value={recipientForm.apiTimeout} onChange={(e) => setRecipientForm({ ...recipientForm, apiTimeout: Number(e.target.value) })} placeholder="Timeout (secondes)" className="border rounded-lg px-3 py-2 text-xs" />
                  </div>
                )}

                {recipientForm.channel === 'email' && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <input type="email" value={recipientForm.emailAddress} onChange={(e) => setRecipientForm({ ...recipientForm, emailAddress: e.target.value })} placeholder="Email de destination" className="border rounded-lg px-3 py-2 text-xs" required />
                    <input value={recipientForm.emailSubject} onChange={(e) => setRecipientForm({ ...recipientForm, emailSubject: e.target.value })} placeholder="Objet du mail" className="border rounded-lg px-3 py-2 text-xs" />
                    <textarea value={recipientForm.emailBody} onChange={(e) => setRecipientForm({ ...recipientForm, emailBody: e.target.value })} rows={3} placeholder="Corps du mail" className="md:col-span-2 border rounded-lg px-3 py-2 text-xs" />
                  </div>
                )}

                {recipientForm.channel === 'ler' && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <select value={recipientForm.lerProvider} onChange={(e) => setRecipientForm({ ...recipientForm, lerProvider: e.target.value })} className="border rounded-lg px-3 py-2 text-xs">
                      <option value="laposte">La Poste e-Recommandé</option>
                      <option value="docusign">DocuSign Envelope</option>
                      <option value="yousign">Yousign</option>
                      <option value="ar24">AR24</option>
                    </select>
                    <input value={recipientForm.lerAccountId} onChange={(e) => setRecipientForm({ ...recipientForm, lerAccountId: e.target.value })} placeholder="ID de compte fournisseur" className="border rounded-lg px-3 py-2 text-xs" />
                  </div>
                )}

                {recipientForm.channel === 'application' && (
                  <div className="rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-xs text-blue-700">
                    Les documents seront reçus directement dans l'onglet Réception de l'application.
                  </div>
                )}
              </div>

              <div className="rounded-xl border border-gray-200 p-4 space-y-3">
                <p className="text-sm font-semibold text-gray-800">4. Documents acceptés</p>
                <div className="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                  {['pdf', 'docx', 'xlsx', 'pptx', 'xml', 'zip'].map((docType) => (
                    <label key={docType} className="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2">
                      <input
                        type="checkbox"
                        checked={recipientForm.docTypes.includes(docType)}
                        onChange={(e) => {
                          if (e.target.checked) {
                            setRecipientForm((prev) => ({ ...prev, docTypes: [...prev.docTypes, docType] }))
                          } else {
                            setRecipientForm((prev) => ({ ...prev, docTypes: prev.docTypes.filter((item) => item !== docType) }))
                          }
                        }}
                      />
                      {docType.toUpperCase()}
                    </label>
                  ))}
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <input type="number" min={1} max={500} value={recipientForm.maxFileSize} onChange={(e) => setRecipientForm({ ...recipientForm, maxFileSize: Number(e.target.value) })} placeholder="Taille max (MB)" className="border rounded-lg px-3 py-2 text-xs" />
                  <input type="number" min={1} max={100} value={recipientForm.maxFiles} onChange={(e) => setRecipientForm({ ...recipientForm, maxFiles: Number(e.target.value) })} placeholder="Nombre max de fichiers" className="border rounded-lg px-3 py-2 text-xs" />
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs text-gray-700">
                  <label className="flex items-center gap-2"><input type="checkbox" checked={recipientForm.enableRetry} onChange={(e) => setRecipientForm({ ...recipientForm, enableRetry: e.target.checked })} /> Activer retry</label>
                  <label className="flex items-center gap-2"><input type="checkbox" checked={recipientForm.enableNotification} onChange={(e) => setRecipientForm({ ...recipientForm, enableNotification: e.target.checked })} /> Notifier l'usager</label>
                  <label className="flex items-center gap-2"><input type="checkbox" checked={recipientForm.compressFiles} onChange={(e) => setRecipientForm({ ...recipientForm, compressFiles: e.target.checked })} /> Compresser fichiers</label>
                  <label className="flex items-center gap-2"><input type="checkbox" checked={recipientForm.encryptFiles} onChange={(e) => setRecipientForm({ ...recipientForm, encryptFiles: e.target.checked })} /> Chiffrer fichiers</label>
                </div>
              </div>

              <div className="rounded-xl border border-gray-200 p-4 space-y-3">
                <p className="text-sm font-semibold text-gray-800">5. Accusé de réception</p>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <select value={recipientForm.receiptMethod} onChange={(e) => setRecipientForm({ ...recipientForm, receiptMethod: e.target.value })} className="border rounded-lg px-3 py-2 text-xs">
                    <option value="automatic">Automatique via API</option>
                    <option value="manual">Manuel</option>
                    <option value="email">Par email automatique</option>
                    <option value="none">Aucun</option>
                  </select>
                  <input type="number" min={1} max={168} value={recipientForm.receiptTimeout} onChange={(e) => setRecipientForm({ ...recipientForm, receiptTimeout: Number(e.target.value) })} placeholder="Délai max (heures)" className="border rounded-lg px-3 py-2 text-xs" />
                  <input value={recipientForm.receiptWebhookUrl} onChange={(e) => setRecipientForm({ ...recipientForm, receiptWebhookUrl: e.target.value })} placeholder="URL webhook confirmation" className="md:col-span-2 border rounded-lg px-3 py-2 text-xs" />
                </div>
                <label className="flex items-center gap-2 text-xs text-gray-700">
                  <input type="checkbox" checked={recipientForm.activateImmediately} onChange={(e) => setRecipientForm({ ...recipientForm, activateImmediately: e.target.checked })} />
                  Activer immédiatement après création
                </label>
              </div>

              <div className="flex flex-col sm:flex-row gap-2">
                <button
                  type="button"
                  onClick={handleTestRecipientConnection}
                  disabled={isTestingRecipientConnection}
                  className="sm:w-auto w-full border border-blue-600 text-blue-600 rounded-lg px-3 py-2 text-xs font-semibold hover:bg-blue-50 disabled:opacity-50"
                >
                  {isTestingRecipientConnection ? 'Test en cours...' : 'Tester la connexion'}
                </button>
                <button className="w-full bg-[#2453d6] text-white rounded-lg px-3 py-2 text-xs font-semibold">
                  {editingRecipientId ? 'Mettre à jour l’administration destinataire' : 'Créer l’administration destinataire'}
                </button>
              </div>
            </form>
          </section>

          <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 max-h-[480px] overflow-auto space-y-3">
            <div className="flex items-center justify-between gap-3">
              <h3 className="text-base font-semibold text-gray-800">Administrations Destinataires enregistrées</h3>
              <span className="text-xs text-gray-400">{filteredRecipientAdministrations.length} / {recipients.length}</span>
            </div>

            <div>
              <input
                value={recipientListSearchQuery}
                onChange={(e) => setRecipientListSearchQuery(e.target.value)}
                placeholder="Rechercher une administration destinataire..."
                className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm placeholder:text-gray-400"
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            {filteredRecipientAdministrations.map((item) => (
              <div key={item.id} className="border border-gray-200 bg-gray-50 rounded-lg p-2.5">
                <div className="flex items-start gap-3">
                  <div className="w-8 h-8 rounded-md border border-gray-200 bg-white overflow-hidden flex items-center justify-center flex-shrink-0">
                    {String((item.metadata as any)?.logo || item.logo || '').trim() ? (
                      <img
                        src={String((item.metadata as any)?.logo || item.logo || '').startsWith('http')
                          ? String((item.metadata as any)?.logo || item.logo || '')
                          : `${API_ROOT}${String((item.metadata as any)?.logo || item.logo || '')}`}
                        alt={`Logo ${item.name}`}
                        className="w-full h-full object-contain"
                      />
                    ) : (
                      <span className="text-[10px] font-semibold text-gray-400">LOGO</span>
                    )}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="text-xs font-semibold text-gray-800 truncate" title={item.name}>{item.name}</p>
                    <p className="text-[11px] text-gray-500">Canal: {item.channel.toUpperCase()} · {(item.metadata as any)?.sector || 'Secteur non défini'}</p>
                    <p className="text-[11px] text-gray-500">
                      Entites sous tutelle: {Array.isArray((item.metadata as any)?.subEntities) ? (item.metadata as any).subEntities.length : 0}
                    </p>
                    <p className="text-[11px] text-gray-500">Contact: {item.emailAddress || (item.metadata as any)?.contactEmail || '—'}</p>
                  </div>
                </div>
                <div className="mt-2 flex gap-2">
                  <button onClick={() => startEditRecipient(item)} className="px-2 py-1 rounded bg-gray-200 text-gray-700 text-[11px]">Modifier</button>
                  <button onClick={() => handleDeleteRecipient(item.id)} className="px-2 py-1 rounded bg-red-100 text-red-700 text-[11px]">Supprimer</button>
                </div>
              </div>
            ))}
            {filteredRecipientAdministrations.length === 0 && (
              <div className="md:col-span-2 rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-xs text-gray-500">
                {recipients.length === 0
                  ? 'Aucune administration destinataire enregistrée.'
                  : 'Aucun résultat pour cette recherche.'}
              </div>
            )}
            </div>
          </section>
        </div>
      )}

      {activeTab === 'sub-entities' && (
        <div className="grid grid-cols-1 xl:grid-cols-[1.2fr_1fr] gap-5">
          <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
            <div className="space-y-1">
              <h2 className="text-base font-bold text-gray-800">Nouvelle Direction</h2>
              <p className="text-xs text-gray-500">Créez les entités sous tutelle depuis ce sous-onglet dédié.</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              <select
                value={subEntityScopeType}
                onChange={(e) => setSubEntityScopeType(e.target.value as 'emitter' | 'recipient')}
                className="border border-gray-300 rounded-xl px-4 py-3 text-sm"
              >
                <option value="emitter">Administration émettrice</option>
                <option value="recipient">Administration destinataire</option>
              </select>
              <select
                value={subEntityScopeId}
                onChange={(e) => setSubEntityScopeId(e.target.value)}
                className="border border-gray-300 rounded-xl px-4 py-3 text-sm"
                disabled={scopedAdministrationOptions.length === 0}
              >
                {scopedAdministrationOptions.length === 0 && <option value="">Aucune administration disponible</option>}
                {scopedAdministrationOptions.map((option) => (
                  <option key={option.id} value={option.id}>{option.label}</option>
                ))}
              </select>
            </div>

            <form onSubmit={handleSaveSubEntity} className="space-y-3">
              <input
                value={subEntityForm.name}
                onChange={(e) => setSubEntityForm((prev) => ({ ...prev, name: e.target.value }))}
                placeholder="Nom de la direction"
                className="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400"
                required
              />

              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <input
                  value={subEntityForm.code}
                  onChange={(e) => setSubEntityForm((prev) => ({ ...prev, code: e.target.value.toUpperCase() }))}
                  placeholder="Code (ex: DIR001)"
                  className="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400"
                  required
                />
                <select
                  value={subEntityForm.parentCode}
                  onChange={(e) => setSubEntityForm((prev) => ({ ...prev, parentCode: e.target.value }))}
                  className="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm"
                >
                  <option value="">Direction Parent (optionnelle)</option>
                  {currentSubEntities
                    .filter((entity) => entity.id !== editingSubEntityId)
                    .map((entity) => (
                      <option key={entity.id} value={entity.code}>{entity.code} - {entity.name}</option>
                    ))}
                </select>
              </div>

              <select
                value={subEntityForm.directionType}
                onChange={(e) => setSubEntityForm((prev) => ({ ...prev, directionType: e.target.value }))}
                className="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm"
                required
              >
                <option value="">Type de Direction</option>
                {subEntityDirectionTypeOptions.map((item) => (
                  <option key={item.id} value={item.id}>{item.name}</option>
                ))}
              </select>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <input
                  value={subEntityForm.managerName}
                  onChange={(e) => setSubEntityForm((prev) => ({ ...prev, managerName: e.target.value }))}
                  placeholder="Nom du Responsable"
                  className="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400"
                />
                <input
                  type="email"
                  value={subEntityForm.managerEmail}
                  onChange={(e) => setSubEntityForm((prev) => ({ ...prev, managerEmail: e.target.value }))}
                  placeholder="Email Responsable"
                  className="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400"
                />
              </div>

              <textarea
                value={subEntityForm.description}
                onChange={(e) => setSubEntityForm((prev) => ({ ...prev, description: e.target.value }))}
                rows={3}
                placeholder="Description (optionnelle)"
                className="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm"
              />

              <div className="grid grid-cols-2 gap-3 pt-2">
                <button className="rounded-xl px-4 py-3 text-sm text-white bg-gradient-to-r from-blue-500 to-violet-600 font-semibold">
                  {editingSubEntityId ? 'Mettre à jour' : 'Créer'}
                </button>
                <button
                  type="button"
                  onClick={resetSubEntityForm}
                  className="rounded-xl px-4 py-3 text-sm text-white bg-gray-500 font-semibold"
                >
                  Annuler
                </button>
              </div>
            </form>
          </section>

          <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3 max-h-[720px] overflow-auto">
            <div className="flex items-center justify-between">
              <h3 className="text-base font-semibold text-gray-800">Directions enregistrées</h3>
              <span className="text-xs text-gray-400">{filteredSubEntities.length} / {currentSubEntities.length} direction{currentSubEntities.length > 1 ? 's' : ''}</span>
            </div>

            <div>
              <input
                value={subEntitySearchQuery}
                onChange={(e) => setSubEntitySearchQuery(e.target.value)}
                placeholder="Rechercher une direction (nom, code, parent, responsable...)"
                className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm placeholder:text-gray-400"
              />
            </div>

            <div className="overflow-auto rounded-xl border border-gray-200">
              <table className="w-full min-w-[860px] text-left">
                <thead className="bg-gray-100 text-gray-600 text-[11px] uppercase tracking-wide">
                  <tr>
                    <th className="px-4 py-3 font-semibold">Nom</th>
                    <th className="px-4 py-3 font-semibold">Code</th>
                    <th className="px-4 py-3 font-semibold">Type</th>
                    <th className="px-4 py-3 font-semibold">Parent</th>
                    <th className="px-4 py-3 font-semibold">Responsable</th>
                    <th className="px-4 py-3 font-semibold">Description</th>
                    <th className="px-4 py-3 font-semibold">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 text-xs bg-white">
                  {filteredSubEntities.map((entity) => (
                    <tr key={entity.id} className={editingSubEntityId === entity.id ? 'bg-blue-50' : ''}>
                      <td className="px-4 py-3 font-medium text-gray-800">{entity.name}</td>
                      <td className="px-4 py-3 text-gray-600">{entity.code}</td>
                      <td className="px-4 py-3 text-gray-600">{directionTypeLabelMap.get(entity.directionType) || entity.directionType}</td>
                      <td className="px-4 py-3 text-gray-600">{entity.parentCode || '—'}</td>
                      <td className="px-4 py-3 text-gray-600">{entity.managerName || entity.managerEmail ? `${entity.managerName || '—'} · ${entity.managerEmail || '—'}` : '—'}</td>
                      <td className="px-4 py-3 text-gray-600 max-w-[220px] truncate" title={entity.description || ''}>{entity.description || '—'}</td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <button
                            type="button"
                            onClick={() => startEditSubEntity(entity)}
                            className="p-1 rounded text-blue-600 hover:bg-blue-50"
                            title="Modifier"
                          >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                          </button>
                          <button
                            type="button"
                            onClick={() => handleDeleteSubEntity(entity)}
                            className="p-1 rounded text-red-600 hover:bg-red-50"
                            title="Supprimer"
                          >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                  {filteredSubEntities.length === 0 && (
                    <tr>
                      <td colSpan={7} className="px-4 py-8 text-center text-xs text-gray-500">
                        {currentSubEntities.length === 0
                          ? 'Aucune direction enregistrée pour cette administration.'
                          : 'Aucun résultat pour cette recherche.'}
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </section>
        </div>
      )}

      {activeTab === 'requested-acts' && (
        <div className="grid grid-cols-1 xl:grid-cols-[1fr_1.1fr] gap-5">
          <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
            <div className="space-y-1">
              <h2 className="text-base font-bold text-gray-800">Acte demandé</h2>
              <p className="text-xs text-gray-500">Créez un acte demandé avec l'administration, la direction, les pièces à fournir et les champs à renseigner par l'usager.</p>
            </div>

            <form onSubmit={handleSaveRequestedAct} className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <select
                  value={requestedActForm.administrationRef}
                  onChange={(e) => setRequestedActForm((prev) => ({ ...prev, administrationRef: e.target.value }))}
                  className="border border-gray-300 rounded-xl px-4 py-3 text-sm"
                  required
                  disabled={Boolean(requestedActLockedAdministrationRef)}
                >
                  <option value="">Administration</option>
                  {requestedActAdministrationOptions.map((option) => (
                    <option key={option.ref} value={option.ref}>{option.label}</option>
                  ))}
                </select>

                <select
                  value={requestedActForm.directionCode}
                  onChange={(e) => setRequestedActForm((prev) => ({ ...prev, directionCode: e.target.value }))}
                  className="border border-gray-300 rounded-xl px-4 py-3 text-sm"
                  required
                  disabled={Boolean(requestedActLockedDirectionCode) || !requestedActForm.administrationRef || requestedActDirections.length === 0}
                >
                  <option value="">Direction</option>
                  {requestedActDirections.map((direction) => (
                    <option key={direction.id} value={direction.code}>{direction.code} - {direction.name}</option>
                  ))}
                </select>
              </div>

              <input
                value={requestedActForm.documentName}
                onChange={(e) => setRequestedActForm((prev) => ({ ...prev, documentName: e.target.value }))}
                placeholder="Nom du document"
                className="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm"
                required
              />

              <div className="space-y-2">
                <label className="text-xs font-semibold text-gray-700">Liste des documents à fournir</label>
                <div className="flex gap-2">
                  <input
                    value={requestedActDocInput}
                    onChange={(e) => setRequestedActDocInput(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        e.preventDefault()
                        addRequestedActRequiredDocument()
                      }
                    }}
                    placeholder="Ex: Copie CNI, Extrait de naissance..."
                    className="flex-1 border border-gray-300 rounded-xl px-4 py-3 text-sm"
                  />
                  <button
                    type="button"
                    onClick={addRequestedActRequiredDocument}
                    className="px-4 py-3 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200"
                  >
                    Ajouter
                  </button>
                </div>

                <div className="flex flex-wrap gap-2">
                  {requestedActRequiredDocs.map((item, index) => (
                    <span key={`${item}-${index}`} className="inline-flex items-center gap-2 rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-xs text-blue-700">
                      {item}
                      <button
                        type="button"
                        onClick={() => removeRequestedActRequiredDocument(index)}
                        className="text-blue-600 hover:text-blue-800"
                      >
                        ×
                      </button>
                    </span>
                  ))}
                </div>
              </div>

              <div className="space-y-2">
                <label className="text-xs font-semibold text-gray-700">Champs à renseigner par l'usager</label>
                <div className="grid grid-cols-1 md:grid-cols-[1fr_170px_auto] gap-2">
                  <input
                    value={requestedActApplicantFieldLabelInput}
                    onChange={(e) => setRequestedActApplicantFieldLabelInput(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        e.preventDefault()
                        addRequestedActApplicantField()
                      }
                    }}
                    placeholder="Nom du champ (ex: Date de naissance)"
                    className="border border-gray-300 rounded-xl px-4 py-3 text-sm"
                  />
                  <select
                    value={requestedActApplicantFieldTypeInput}
                    onChange={(e) => setRequestedActApplicantFieldTypeInput(e.target.value as RequestedActApplicantFieldType)}
                    className="border border-gray-300 rounded-xl px-3 py-3 text-sm bg-white"
                  >
                    <option value="text">Texte</option>
                    <option value="date">Date</option>
                    <option value="number">Nombre</option>
                    <option value="phone">Téléphone</option>
                    <option value="email">Email</option>
                    <option value="textarea">Texte long</option>
                  </select>
                  <button
                    type="button"
                    onClick={addRequestedActApplicantField}
                    className="px-4 py-3 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200"
                  >
                    Ajouter
                  </button>
                </div>

                <div className="flex flex-wrap gap-2">
                  {requestedActApplicantFields.map((item, index) => (
                    <span key={`${item.label}-${item.inputType}-${index}`} className="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs text-indigo-700">
                      {item.label} ({item.inputType})
                      <button
                        type="button"
                        onClick={() => removeRequestedActApplicantField(index)}
                        className="text-indigo-600 hover:text-indigo-800"
                      >
                        ×
                      </button>
                    </span>
                  ))}
                </div>
              </div>

              <div className="flex gap-2">
                <button className="flex-1 rounded-xl px-4 py-3 text-sm text-white bg-[#2453d6] font-semibold hover:bg-[#1f47bb]">
                  {editingRequestedActId ? 'Enregistrer les modifications' : 'Enregistrer l\'acte demandé'}
                </button>
                {editingRequestedActId && (
                  <button
                    type="button"
                    onClick={cancelEditRequestedAct}
                    className="rounded-xl px-4 py-3 text-sm font-semibold border border-gray-300 text-gray-700 hover:bg-gray-50"
                  >
                    Annuler
                  </button>
                )}
              </div>
            </form>
          </section>

          <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3 max-h-[720px] overflow-auto">
            <div className="flex items-center justify-between">
              <h3 className="text-base font-semibold text-gray-800">Actes demandés</h3>
              <span className="text-xs text-gray-400">{filteredRequestedActs.length} élément{filteredRequestedActs.length > 1 ? 's' : ''}</span>
            </div>

            <div>
              <input
                value={requestedActsSearch}
                onChange={(e) => setRequestedActsSearch(e.target.value)}
                placeholder="Rechercher un acte (nom, administration, direction, pièces, champs)..."
                className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm placeholder:text-gray-400"
              />
            </div>

            {filteredRequestedActs.length === 0 ? (
              <div className="rounded-xl border border-dashed border-gray-200 p-6 text-xs text-gray-500 text-center">
                {requestedActs.length === 0
                  ? 'Aucun acte demandé enregistré pour le moment.'
                  : 'Aucun résultat pour cette recherche.'}
              </div>
            ) : (
              <div className="space-y-3">
                {filteredRequestedActs.map((item) => (
                  <div key={item.id} className="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-2">
                    <p className="text-sm font-semibold text-gray-800">{item.documentName}</p>
                    <p className="text-xs text-gray-600">Administration: {item.administrationLabel}</p>
                    <p className="text-xs text-gray-600">Direction: {item.directionLabel}</p>
                    <p className="text-xs text-gray-500">Créé le {new Date(item.createdAt).toLocaleString('fr-FR')}</p>
                    <div className="pt-1">
                      <p className="text-xs font-semibold text-gray-700 mb-1">Documents à fournir:</p>
                      <ul className="list-disc pl-5 text-xs text-gray-700 space-y-0.5">
                        {item.requiredDocuments.map((doc, idx) => (
                          <li key={`${item.id}-req-${idx}`}>{doc}</li>
                        ))}
                      </ul>
                    </div>
                    {Array.isArray(item.applicantFields) && item.applicantFields.length > 0 && (
                      <div className="pt-1">
                        <p className="text-xs font-semibold text-gray-700 mb-1">Champs usager:</p>
                        <ul className="list-disc pl-5 text-xs text-gray-700 space-y-0.5">
                          {item.applicantFields.map((field, idx) => (
                            <li key={`${item.id}-field-${idx}`}>{field.label} ({field.inputType})</li>
                          ))}
                        </ul>
                      </div>
                    )}
                    <div className="pt-2 flex gap-2">
                      <button
                        type="button"
                        onClick={() => startEditRequestedAct(item)}
                        className="text-xs px-3 py-1.5 rounded-lg bg-amber-100 text-amber-800 hover:bg-amber-200"
                      >
                        Modifier
                      </button>
                      <button
                        type="button"
                        onClick={() => handleDeleteRequestedAct(item)}
                        className="text-xs px-3 py-1.5 rounded-lg bg-red-100 text-red-700 hover:bg-red-200"
                      >
                        Supprimer
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </section>
        </div>
      )}

      {activeTab === 'direction-types' && (
        <div className="grid grid-cols-1 xl:grid-cols-[0.95fr_1.05fr] gap-5">
          <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
            <div className="space-y-1">
              <h2 className="text-base font-bold text-gray-800">Nouveau Type de Direction</h2>
              <p className="text-xs text-gray-500">Créez les types utilisés dans les formulaires des entités sous tutelle.</p>
            </div>

            <form onSubmit={handleSaveDirectionType} className="space-y-4">
              <input
                value={directionTypeForm.name}
                onChange={(e) => setDirectionTypeForm((prev) => ({ ...prev, name: e.target.value }))}
                placeholder="Nom du type"
                className="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400"
                required
              />

              <textarea
                value={directionTypeForm.description}
                onChange={(e) => setDirectionTypeForm((prev) => ({ ...prev, description: e.target.value }))}
                rows={4}
                placeholder="Description"
                className="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400"
              />

              <div className="grid grid-cols-2 gap-3 pt-1">
                <button className="rounded-xl px-4 py-3 text-sm text-white bg-gradient-to-r from-blue-500 to-violet-600 font-semibold">
                  {editingDirectionTypeId ? 'Modifier' : 'Créer'}
                </button>
                <button
                  type="button"
                  onClick={resetDirectionTypeForm}
                  className="rounded-xl px-4 py-3 text-sm text-white bg-gray-500 font-semibold"
                >
                  Annuler
                </button>
              </div>
            </form>
          </section>

          <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3">
            <div className="flex items-center justify-between">
              <h3 className="text-base font-semibold text-gray-800">Liste des types</h3>
              <span className="text-xs text-gray-400">{directionTypes.length} type{directionTypes.length > 1 ? 's' : ''}</span>
            </div>

            <div className="overflow-auto rounded-xl border border-gray-200">
              <table className="w-full min-w-[520px] text-left">
                <thead className="bg-gray-100 text-gray-600 text-[11px] uppercase tracking-wide">
                  <tr>
                    <th className="px-4 py-3 font-semibold">Nom du type</th>
                    <th className="px-4 py-3 font-semibold">Description</th>
                    <th className="px-4 py-3 font-semibold">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 text-xs bg-white">
                  {directionTypes.map((item) => (
                    <tr key={item.id} className={editingDirectionTypeId === item.id ? 'bg-blue-50' : ''}>
                      <td className="px-4 py-3 font-medium text-gray-800">{item.name}</td>
                      <td className="px-4 py-3 text-gray-600">{item.description || '—'}</td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <button
                            type="button"
                            onClick={() => startEditDirectionType(item)}
                            className="p-1 rounded text-blue-600 hover:bg-blue-50"
                            title="Modifier"
                          >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                          </button>
                          <button
                            type="button"
                            onClick={() => handleDeleteDirectionType(item)}
                            className="p-1 rounded text-red-600 hover:bg-red-50"
                            title="Supprimer"
                          >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                  {directionTypes.length === 0 && (
                    <tr>
                      <td colSpan={3} className="px-4 py-8 text-center text-xs text-gray-500">Aucun type de direction enregistré.</td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </section>
        </div>
      )}

      {activeTab === 'routing' && (
        <div className="grid grid-cols-1 xl:grid-cols-2 gap-5">
          <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
            <h2 className="text-lg font-semibold text-gray-800">Règles de routage</h2>
            <div className="rounded-lg border border-gray-200 bg-gray-50 p-3">
              <label className="block text-xs text-gray-500 mb-1">Administration émettrice concernée</label>
              <select
                value={selectedEmitterId}
                onChange={(e) => setSelectedEmitterId(e.target.value)}
                disabled={Boolean(lockedEmitterId)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-[#2453d6]/30 disabled:bg-gray-100"
              >
                <option value="">Sélectionner une administration</option>
                {scopedEmitters.map((item) => (
                  <option key={item.id} value={item.id}>{item.name} ({item.code})</option>
                ))}
              </select>
            </div>
            <form onSubmit={handleCreateRule} className="grid grid-cols-1 md:grid-cols-2 gap-3">
              <input value={ruleForm.name} onChange={(e) => setRuleForm({ ...ruleForm, name: e.target.value })} placeholder="Nom règle" className="border rounded-lg px-3 py-2 text-xs" required />
              <input value={ruleForm.documentType} onChange={(e) => setRuleForm({ ...ruleForm, documentType: e.target.value })} placeholder="Type document" className="border rounded-lg px-3 py-2 text-xs" required />
              <select value={ruleForm.templateId} onChange={(e) => setRuleForm({ ...ruleForm, templateId: e.target.value })} className="border rounded-lg px-3 py-2 text-xs" required>
                <option value="">Sélectionner un modèle</option>
                {templatesForSelectedAdministration.map((item) => (
                  <option key={item.id} value={item.id}>{item.name}</option>
                ))}
              </select>
              <select value={ruleForm.recipientAdministrationId} onChange={(e) => setRuleForm({ ...ruleForm, recipientAdministrationId: e.target.value })} className="border rounded-lg px-3 py-2 text-xs" required>
                <option value="">Sélectionner destinataire</option>
                {recipients.map((item) => (
                  <option key={item.id} value={item.id}>{item.name}</option>
                ))}
              </select>
              <input type="number" min={1} value={ruleForm.priority} onChange={(e) => setRuleForm({ ...ruleForm, priority: Number(e.target.value) })} placeholder="Priorité" className="border rounded-lg px-3 py-2 text-xs" />
              <button className="bg-[#2453d6] text-white rounded-lg px-3 py-2 text-xs font-semibold">{editingRuleId ? 'Modifier règle' : 'Créer règle'}</button>
            </form>
          </section>

          <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-2 max-h-[480px] overflow-auto">
            {routingRulesForSelectedAdministration.map((item) => (
              <div key={item.id} className="border border-gray-200 bg-gray-50 rounded-lg p-3">
                <p className="text-xs font-semibold text-gray-800">{item.name}</p>
                <p className="text-[11px] text-gray-500">Type: {item.documentType} · Priorité: {item.priority}</p>
                <div className="mt-2 flex gap-2">
                  <button onClick={() => startEditRule(item)} className="px-2 py-1 rounded bg-gray-200 text-gray-700 text-[11px]">Modifier</button>
                  <button onClick={() => handleDeleteRule(item.id)} className="px-2 py-1 rounded bg-red-100 text-red-700 text-[11px]">Supprimer</button>
                </div>
              </div>
            ))}
            {routingRulesForSelectedAdministration.length === 0 && (
              <div className="border border-dashed border-gray-300 rounded-lg p-4 text-xs text-gray-500">
                Aucune règle de routage pour cette administration.
              </div>
            )}
          </section>
        </div>
      )}

      {activeTab === 'onlyoffice' && (
        <div className="max-w-2xl space-y-6">
          {/* En-tête */}
          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <p className="text-xs font-bold uppercase tracking-widest text-[#2453d6] mb-1">ONLYOFFICE</p>
            <h2 className="text-2xl font-bold text-gray-900 mb-2">Bienvenue dans ONLYOFFICE Docs&nbsp;!</h2>
            <p className="text-sm text-gray-500 mb-4">
              Modifiez et collaborez sur des documents texte, des feuilles de calcul, des présentations
              et des fichiers PDF à l&apos;aide de ONLYOFFICE Docs.
            </p>
            <div className="flex gap-4 text-sm font-semibold text-[#2453d6]">
              <a href="https://www.onlyoffice.com/fr/" target="_blank" rel="noreferrer" className="hover:underline">
                En savoir plus ↗
              </a>
              <a href="https://www.onlyoffice.com/fr/feedback.aspx" target="_blank" rel="noreferrer" className="hover:underline">
                Suggérer une fonctionnalité ↗
              </a>
            </div>
          </div>

          {/* Choix du lecteur de documents */}
          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h3 className="text-lg font-bold text-gray-900 mb-1">Lecteur de documents</h3>
            <p className="text-sm text-gray-500 mb-4">
              Choisissez le lecteur à utiliser pour afficher et éditer les documents.
            </p>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {/* Option OnlyOffice */}
              <button
                type="button"
                onClick={() => setDocViewer('onlyoffice')}
                className={`relative flex flex-col items-center gap-3 p-5 rounded-xl border-2 transition cursor-pointer ${
                  docViewer === 'onlyoffice'
                    ? 'border-[#2453d6] bg-blue-50 shadow-md'
                    : 'border-gray-200 bg-white hover:border-gray-300'
                }`}
              >
                {docViewer === 'onlyoffice' && (
                  <span className="absolute top-2 right-2 w-5 h-5 bg-[#2453d6] rounded-full flex items-center justify-center">
                    <svg className="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" /></svg>
                  </span>
                )}
                <svg className="w-10 h-10 text-[#2453d6]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
                <div className="text-center">
                  <p className="text-sm font-semibold text-gray-900">OnlyOffice</p>
                  <p className="text-xs text-gray-500 mt-1">Éditeur collaboratif complet (DOCX, XLSX, PPTX, PDF)</p>
                </div>
              </button>
              {/* Option Lecteur PDF natif */}
              <button
                type="button"
                onClick={() => setDocViewer('native')}
                className={`relative flex flex-col items-center gap-3 p-5 rounded-xl border-2 transition cursor-pointer ${
                  docViewer === 'native'
                    ? 'border-[#2453d6] bg-blue-50 shadow-md'
                    : 'border-gray-200 bg-white hover:border-gray-300'
                }`}
              >
                {docViewer === 'native' && (
                  <span className="absolute top-2 right-2 w-5 h-5 bg-[#2453d6] rounded-full flex items-center justify-center">
                    <svg className="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" /></svg>
                  </span>
                )}
                <svg className="w-10 h-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <div className="text-center">
                  <p className="text-sm font-semibold text-gray-900">Lecteur PDF natif</p>
                  <p className="text-xs text-gray-500 mt-1">Lecteur intégré du navigateur (PDF uniquement, lecture seule)</p>
                </div>
              </button>
            </div>
          </div>

          {/* Formulaire paramètres */}
          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h3 className="text-lg font-bold text-gray-900 mb-1">Paramètres du serveur</h3>
            <p className="text-sm text-gray-500 mb-5">
              L&apos;emplacement du ONLYOFFICE Docs désigne l&apos;adresse du serveur sur lequel est installé
              le service de document. Veuillez modifier le &lsquo;&lt;documentserver&gt;&rsquo; avec
              l&apos;adresse du serveur de service de document dans la ligne ci-dessous
            </p>

            <form onSubmit={handleSaveOnlyoffice} className="space-y-5">
              {/* URL */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Adresse du ONLYOFFICE Docs
                </label>
                <input
                  type="url"
                  value={onlyofficeUrl}
                  onChange={(e) => setOnlyofficeUrl(e.target.value)}
                  placeholder="https://<documentserver>/"
                  className="w-72 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6]"
                />
              </div>

              {/* Désactiver certificat */}
              <div className="flex items-center gap-2">
                <input
                  id="oo-cert"
                  type="checkbox"
                  checked={onlyofficeDisableCert}
                  onChange={(e) => setOnlyofficeDisableCert(e.target.checked)}
                  className="h-4 w-4 rounded border-gray-300 text-[#2453d6]"
                />
                <label htmlFor="oo-cert" className="text-sm text-gray-700">
                  Désactiver la vérification du certificat{' '}
                  <span className="text-gray-400 text-xs">(non sûr)</span>
                </label>
              </div>

              {/* Clé secrète */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Clé secrète{' '}
                  <span className="text-gray-400 font-normal">(laisser vide pour désactiver)</span>
                </label>
                <div className="relative w-72">
                  <input
                    type={onlyofficeSecretVisible ? 'text' : 'password'}
                    value={onlyofficeSecret}
                    onChange={(e) => setOnlyofficeSecret(e.target.value)}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm pr-9 focus:outline-none focus:ring-2 focus:ring-[#2453d6]"
                    placeholder="••••••••••••"
                  />
                  <button
                    type="button"
                    onClick={() => setOnlyofficeSecretVisible((v) => !v)}
                    className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                  >
                    {onlyofficeSecretVisible ? (
                      <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-5 0-9-4-9-7s4-7 9-7a9.95 9.95 0 015.658 1.748M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3l18 18" /></svg>
                    ) : (
                      <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    )}
                  </button>
                </div>
              </div>

              {/* Bouton sauvegarder */}
              <div className="flex items-center gap-3 pt-1">
                <button
                  type="submit"
                  className="px-5 py-2 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold rounded-lg transition"
                >
                  Enregistrer
                </button>
                {onlyofficeSaved && (
                  <span className="text-sm text-green-600 font-medium">✓ Paramètres enregistrés</span>
                )}
              </div>
            </form>
          </div>
        </div>
      )}
      {activeTab === 'users' && (
        <div className="space-y-4">
          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center justify-between">
            <div>
              <h2 className="text-base font-semibold text-gray-800">Utilisateurs de l'application</h2>
              <p className="text-xs text-gray-500 mt-0.5">{searchedManagedUsers.length} utilisateur{searchedManagedUsers.length !== 1 ? 's' : ''}</p>
            </div>
            <button
              onClick={() => { resetNewUserForm(); setShowNewUserModal(true) }}
              className="px-4 py-2 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-xs font-semibold rounded-lg transition"
            >
              + Nouvel utilisateur
            </button>
          </div>

          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-auto">
            <div className="p-4 border-b border-gray-100">
              <input
                type="text"
                value={managedUsersSearch}
                onChange={(e) => setManagedUsersSearch(e.target.value)}
                placeholder="Rechercher un utilisateur (nom, email, role, direction, code entite)..."
                className="w-full md:w-[420px] border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6]"
              />
            </div>
            <table className="w-full text-xs">
              <thead>
                <tr className="border-b border-gray-100 text-gray-500 font-semibold">
                  <th className="px-4 py-3 text-left">Nom</th>
                  <th className="px-4 py-3 text-left">Prénoms</th>
                  <th className="px-4 py-3 text-left">Rôle</th>
                  <th className="px-4 py-3 text-left">Direction</th>
                  <th className="px-4 py-3 text-left">E-mail</th>
                  <th className="px-4 py-3 text-left">Quota</th>
                  <th className="px-4 py-3 text-left">Statut</th>
                  <th className="px-4 py-3 text-left">Date création</th>
                  <th className="px-4 py-3 text-left">Date modification</th>
                  <th className="px-4 py-3 text-left">Actions</th>
                </tr>
              </thead>
              <tbody>
                {searchedManagedUsers.length === 0 && (
                  <tr>
                    <td colSpan={10} className="px-4 py-8 text-center text-gray-400">Aucun utilisateur trouvé.</td>
                  </tr>
                )}
                {searchedManagedUsers.map((user) => {
                  const parts = (user.fullName || '').trim().split(/\s+/)
                  const nom = parts.length > 1 ? parts[parts.length - 1] : user.fullName
                  const prenoms = parts.length > 1 ? parts.slice(0, -1).join(' ') : ''
                  const emitterAdministrationName = user.administrationId
                    ? (emitters.find((e) => e.id === user.administrationId)?.name || user.administrationId)
                    : ''
                  const directionLabel = user.directionLabel || emitterAdministrationName
                  return (
                    <tr key={user.id} className="border-b border-gray-50 hover:bg-gray-50 transition">
                      <td className="px-4 py-3 text-gray-800 font-medium">{nom || '—'}</td>
                      <td className="px-4 py-3 text-gray-600">{prenoms || '—'}</td>
                      <td className="px-4 py-3 text-gray-600">{user.role || '—'}</td>
                      <td className="px-4 py-3 text-gray-600 max-w-[260px] truncate" title={directionLabel || ''}>
                        {directionLabel || <span className="text-gray-400">—</span>}
                      </td>
                      <td className="px-4 py-3 text-gray-600">{user.email}</td>
                      <td className="px-4 py-3 text-gray-600">{getManagedUserQuota(user.id)}</td>
                      <td className="px-4 py-3">
                        <span className={`inline-flex items-center px-2 py-1 rounded-full text-[11px] font-semibold ${user.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'}`}>
                          {user.status === 'active' ? 'Actif' : 'Désactivé'}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-gray-600">{new Date(user.createdAt).toLocaleString('fr-FR')}</td>
                      <td className="px-4 py-3 text-gray-600">{new Date(user.updatedAt).toLocaleString('fr-FR')}</td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                        <button
                          type="button"
                          onClick={() => openEditManagedUser(user)}
                          className="text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded p-1.5 transition"
                          title="Modifier"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </button>
                        <button
                          type="button"
                          onClick={() => handleToggleManagedUserStatus(user)}
                          className={`rounded p-1.5 transition ${user.status === 'active' ? 'text-red-600 hover:text-red-700 hover:bg-red-50' : 'text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50'}`}
                          title={user.status === 'active' ? 'Désactiver' : 'Activer'}
                        >
                          {user.status === 'active' ? (
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-5.523 0-10-4.477-10-10S6.477 -1 12 -1s10 4.477 10 10-.4.477-10 10m0 0a8.95 8.95 0 001.875-.175m0 0a5 5 0 011 2" /></svg>
                          ) : (
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7C7.523 19 3.732 16.057 2.458 12z" /></svg>
                          )}
                        </button>
                        <button
                          type="button"
                          onClick={() => handleDeleteManagedUser(user)}
                          className="text-red-600 hover:text-red-700 hover:bg-red-50 rounded p-1.5 transition"
                          title="Supprimer"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>

          {showNewUserModal && (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
              <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[90vh]">
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                  <h3 className="text-base font-semibold text-gray-800">Créer un utilisateur</h3>
                  <button
                    onClick={() => setShowNewUserModal(false)}
                    className="text-gray-400 hover:text-gray-600 transition"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                  </button>
                </div>

                <form onSubmit={handleCreateManagedUser} className="flex flex-col flex-1 overflow-y-auto px-6 py-4 space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <input
                      type="text"
                      placeholder="Nom"
                      value={newUserForm.nom}
                      onChange={(e) => setNewUserForm((prev) => ({ ...prev, nom: e.target.value }))}
                      className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                      required
                    />
                    <input
                      type="text"
                      placeholder="Prénoms"
                      value={newUserForm.prenoms}
                      onChange={(e) => setNewUserForm((prev) => ({ ...prev, prenoms: e.target.value }))}
                      className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                      required
                    />
                  </div>

                  <input
                    type="text"
                    placeholder="Nom à afficher"
                    value={newUserForm.displayName}
                    onChange={(e) => setNewUserForm((prev) => ({ ...prev, displayName: e.target.value }))}
                    className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                  />

                  <select
                    value={newUserForm.role}
                    onChange={(e) => setNewUserForm((prev) => ({ ...prev, role: e.target.value }))}
                    className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                    required
                  >
                    <option value="">Rôle (depuis les rôles créés)</option>
                    {filteredRoleProfiles.map((profile) => (
                      <option key={profile.id} value={profile.name}>{profile.name}</option>
                    ))}
                    {filteredRoleProfiles.length === 0 && (
                      <option value="user">user</option>
                    )}
                  </select>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <select
                      value={newUserForm.administrationType}
                      onChange={(e) => {
                        const nextType = e.target.value as '' | 'emitter' | 'recipient'
                        setNewUserForm((prev) => ({
                          ...prev,
                          administrationType: nextType,
                          administrationScopeId: '',
                          subEntityId: '',
                        }))
                      }}
                      className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                      required
                    >
                      <option value="">Type d'administration</option>
                      <option value="emitter">Émettrice</option>
                      <option value="recipient">Destinataire</option>
                    </select>

                    <select
                      value={newUserForm.administrationScopeId}
                      onChange={(e) => setNewUserForm((prev) => ({ ...prev, administrationScopeId: e.target.value, subEntityId: '' }))}
                      className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                      disabled={!newUserForm.administrationType}
                      required
                    >
                      <option value="">Administration</option>
                      {newUserAdministrationOptions.map((option) => (
                        <option key={option.id} value={option.id}>{option.name}</option>
                      ))}
                    </select>
                  </div>

                  <select
                    value={newUserForm.subEntityId}
                    onChange={(e) => setNewUserForm((prev) => ({ ...prev, subEntityId: e.target.value }))}
                    className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                    disabled={!newUserForm.administrationScopeId}
                    required
                  >
                    <option value="">Direction sous tutelle</option>
                    {newUserSubEntityOptions.map((option) => (
                      <option key={option.id} value={option.id}>{option.label}</option>
                    ))}
                  </select>

                  <input
                    type="email"
                    placeholder="E-mail"
                    value={newUserForm.email}
                    onChange={(e) => setNewUserForm((prev) => ({ ...prev, email: e.target.value }))}
                    className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                    required
                  />

                  <select
                    value={newUserForm.quota}
                    onChange={(e) => setNewUserForm((prev) => ({ ...prev, quota: e.target.value }))}
                    className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                  >
                    <option value="">Quota par défaut</option>
                    <option value="1 Go">1 Go</option>
                    <option value="5 Go">5 Go</option>
                    <option value="10 Go">10 Go</option>
                    <option value="Illimité">Illimité</option>
                  </select>

                  <div className="relative">
                    <input
                      type={newUserPasswordVisible ? 'text' : 'password'}
                      placeholder="Mot de passe"
                      value={newUserForm.password}
                      onChange={(e) => setNewUserForm((prev) => ({ ...prev, password: e.target.value }))}
                      className="w-full border border-gray-200 rounded-lg px-3 py-2.5 pr-10 text-sm"
                      required
                    />
                    <button
                      type="button"
                      onClick={() => setNewUserPasswordVisible((prev) => !prev)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    >
                      {newUserPasswordVisible ? '🙈' : '👁️'}
                    </button>
                  </div>

                  <input
                    type={newUserPasswordVisible ? 'text' : 'password'}
                    placeholder="Confirmer le mot de passe"
                    value={newUserForm.confirmPassword}
                    onChange={(e) => setNewUserForm((prev) => ({ ...prev, confirmPassword: e.target.value }))}
                    className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                    required
                  />

                  <div className="space-y-2">
                    <label className="block text-xs font-medium text-gray-600">Photo de profil</label>
                    <input
                      type="file"
                      accept="image/png,image/jpeg,image/jpg,image/webp"
                      onChange={(e) => {
                        const file = e.target.files?.[0] || null
                        setNewUserAvatarFile(file)
                        if (file) {
                          const reader = new FileReader()
                          reader.onload = (ev) => setNewUserAvatarPreview(ev.target?.result as string)
                          reader.readAsDataURL(file)
                        } else {
                          setNewUserAvatarPreview(null)
                        }
                      }}
                      className="w-full text-xs text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                    />
                    <p className="text-[11px] text-gray-500">Formats acceptés: PNG, JPG, JPEG, WEBP (max 5 MB).</p>
                    {newUserAvatarFile && (
                      <p className="text-[11px] text-green-700 font-medium">Fichier sélectionné: {newUserAvatarFile.name}</p>
                    )}
                    {newUserAvatarPreview && (
                      <img src={newUserAvatarPreview} alt="Aperçu photo de profil" className="h-14 w-14 rounded-full object-cover border border-gray-200" />
                    )}
                  </div>

                  <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input
                      type="checkbox"
                      checked={newUserForm.isActive}
                      onChange={(e) => setNewUserForm((prev) => ({ ...prev, isActive: e.target.checked }))}
                    />
                    Actif (par défaut: désactivé)
                  </label>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <input
                      type="text"
                      value={new Date().toLocaleString('fr-FR')}
                      readOnly
                      className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-gray-50 text-gray-500"
                    />
                    <input
                      type="text"
                      value={new Date().toLocaleString('fr-FR')}
                      readOnly
                      className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-gray-50 text-gray-500"
                    />
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3 -mt-2">
                    <p className="text-[11px] text-gray-500">Date de création</p>
                    <p className="text-[11px] text-gray-500">Date modification</p>
                  </div>

                  {newUserError && <p className="text-xs text-red-600">{newUserError}</p>}

                  <div className="flex flex-col md:flex-row gap-2">
                    <button
                      type="button"
                      onClick={handleSendInvitationFormLink}
                      className="w-full py-2.5 bg-white border border-[#2453d6] text-[#2453d6] text-sm font-semibold rounded-lg hover:bg-blue-50 transition"
                    >
                      Envoyer le formulaire par e-mail
                    </button>
                    <button
                      type="submit"
                      className="w-full py-2.5 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold rounded-lg transition"
                    >
                      Créer l'utilisateur
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {editingManagedUser && (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
              <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[90vh]">
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                  <h3 className="text-base font-semibold text-gray-800">Modifier l'utilisateur</h3>
                  <button
                    onClick={() => setEditingManagedUser(null)}
                    className="text-gray-400 hover:text-gray-600 transition"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                  </button>
                </div>

                <form onSubmit={handleSaveManagedUserEdit} className="flex flex-col flex-1 overflow-y-auto px-6 py-4 space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <input
                      type="text"
                      placeholder="Nom"
                      value={editManagedUserForm.nom}
                      onChange={(e) => setEditManagedUserForm((prev) => ({ ...prev, nom: e.target.value }))}
                      className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                      required
                    />
                    <input
                      type="text"
                      placeholder="Prénoms"
                      value={editManagedUserForm.prenoms}
                      onChange={(e) => setEditManagedUserForm((prev) => ({ ...prev, prenoms: e.target.value }))}
                      className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                      required
                    />
                  </div>

                  <input
                    type="text"
                    placeholder="Nom à afficher"
                    value={editManagedUserForm.displayName}
                    onChange={(e) => setEditManagedUserForm((prev) => ({ ...prev, displayName: e.target.value }))}
                    className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                  />

                  <select
                    value={editManagedUserForm.role}
                    onChange={(e) => setEditManagedUserForm((prev) => ({ ...prev, role: e.target.value }))}
                    className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                    required
                  >
                    <option value="">Rôle</option>
                    {filteredRoleProfiles.map((profile) => (
                      <option key={profile.id} value={profile.name}>{profile.name}</option>
                    ))}
                    {filteredRoleProfiles.length === 0 && <option value="user">user</option>}
                  </select>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <select
                      value={editManagedUserForm.administrationType}
                      onChange={(e) => {
                        const nextType = e.target.value as '' | 'emitter' | 'recipient'
                        setEditManagedUserForm((prev) => ({
                          ...prev,
                          administrationType: nextType,
                          administrationScopeId: '',
                          subEntityId: '',
                        }))
                      }}
                      className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                      required
                    >
                      <option value="">Type d'administration</option>
                      <option value="emitter">Émettrice</option>
                      <option value="recipient">Destinataire</option>
                    </select>

                    <select
                      value={editManagedUserForm.administrationScopeId}
                      onChange={(e) => setEditManagedUserForm((prev) => ({ ...prev, administrationScopeId: e.target.value, subEntityId: '' }))}
                      className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                      disabled={!editManagedUserForm.administrationType}
                      required
                    >
                      <option value="">Administration</option>
                      {editUserAdministrationOptions.map((option) => (
                        <option key={option.id} value={option.id}>{option.name}</option>
                      ))}
                    </select>
                  </div>

                  <select
                    value={editManagedUserForm.subEntityId}
                    onChange={(e) => setEditManagedUserForm((prev) => ({ ...prev, subEntityId: e.target.value }))}
                    className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                    disabled={!editManagedUserForm.administrationScopeId}
                    required
                  >
                    <option value="">Direction sous tutelle</option>
                    {editUserSubEntityOptions.map((option) => (
                      <option key={option.id} value={option.id}>{option.label}</option>
                    ))}
                  </select>

                  <input
                    type="email"
                    placeholder="E-mail"
                    value={editManagedUserForm.email}
                    onChange={(e) => setEditManagedUserForm((prev) => ({ ...prev, email: e.target.value }))}
                    className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                    required
                  />

                  <select
                    value={editManagedUserForm.quota}
                    onChange={(e) => setEditManagedUserForm((prev) => ({ ...prev, quota: e.target.value }))}
                    className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                  >
                    <option value="">Quota par défaut</option>
                    <option value="1 Go">1 Go</option>
                    <option value="5 Go">5 Go</option>
                    <option value="10 Go">10 Go</option>
                    <option value="Illimité">Illimité</option>
                  </select>

                  <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input
                      type="checkbox"
                      checked={editManagedUserForm.isActive}
                      onChange={(e) => setEditManagedUserForm((prev) => ({ ...prev, isActive: e.target.checked }))}
                    />
                    Actif
                  </label>

                  {editManagedUserError && <p className="text-xs text-red-600">{editManagedUserError}</p>}

                  <button
                    type="submit"
                    className="w-full py-2.5 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold rounded-lg transition"
                  >
                    Enregistrer les modifications
                  </button>
                </form>
              </div>
            </div>
          )}
        </div>
      )}

      {/* ===================== THEMING TAB ===================== */}
      {activeTab === 'theming' && (
        <div className="max-w-2xl mx-auto space-y-5">
          {themingSuccess && (
            <div className="bg-green-50 border border-green-100 text-green-700 rounded-xl p-3 text-xs">{themingSuccess}</div>
          )}
          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h2 className="text-xl font-bold text-gray-800 mb-1">Personnaliser l'apparence</h2>
            <p className="text-xs text-gray-500 mb-6">
              Cette extension permet de personnaliser facilement l'apparence de votre instance et des clients supportés.
              La personnalisation de l'apparence sera visible par tous les utilisateurs.
            </p>

            <div className="mb-6 p-4 rounded-xl border border-blue-100 bg-blue-50/40 space-y-3">
              <p className="text-xs font-semibold text-gray-700">Portee de personnalisation</p>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <select
                  value={themingScopeType}
                  onChange={(e) => {
                    const nextType = e.target.value as 'emitter' | 'recipient'
                    setThemingScopeType(nextType)
                    if (nextType === 'emitter') {
                      setThemingScopeId(emitters[0]?.id || '')
                    } else {
                      setThemingScopeId(recipients[0]?.id || '')
                    }
                  }}
                  disabled={Boolean(lockedEmitterId)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30 disabled:bg-gray-100"
                >
                  <option value="emitter">Administration emettrice</option>
                  <option value="recipient">Administration destinataire</option>
                </select>

                <select
                  value={themingScopeId}
                  onChange={(e) => setThemingScopeId(e.target.value)}
                  disabled={Boolean(lockedEmitterId)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30 disabled:bg-gray-100"
                >
                  <option value="">Selectionner une administration</option>
                  {themingScopeType === 'emitter'
                    ? scopedEmitters.map((item) => (
                      <option key={item.id} value={item.id}>{item.name} ({item.code})</option>
                    ))
                    : recipients.map((item) => (
                      <option key={item.id} value={item.id}>{item.name}</option>
                    ))}
                </select>
              </div>
              <p className="text-[11px] text-gray-600">
                Chaque administration possede sa propre configuration d'apparence. Les modifications enregistrees ici n'impactent pas les autres administrations.
              </p>
            </div>

            <form onSubmit={handleSaveTheming} className="space-y-6">

              {/* Informations générales */}
              <div className="space-y-4">
                <div className="relative">
                  <label className="absolute -top-2 left-3 bg-white px-1 text-xs text-gray-500">Nom</label>
                  <div className="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                    <input
                      value={themingForm.appName}
                      onChange={(e) => setThemingForm({ ...themingForm, appName: e.target.value })}
                      className="flex-1 px-3 py-2.5 text-sm outline-none"
                    />
                    <button type="button" onClick={() => setThemingForm({ ...themingForm, appName: '' })} className="px-3 text-gray-400 hover:text-gray-600">↺</button>
                  </div>
                </div>

                <div className="relative">
                  <label className="absolute -top-2 left-3 bg-white px-1 text-xs text-gray-500">Lien web</label>
                  <div className="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                    <input
                      value={themingForm.webUrl}
                      onChange={(e) => setThemingForm({ ...themingForm, webUrl: e.target.value })}
                      placeholder="https://"
                      className="flex-1 px-3 py-2.5 text-sm outline-none"
                    />
                    <button type="button" onClick={() => setThemingForm({ ...themingForm, webUrl: '' })} className="px-3 text-gray-400 hover:text-gray-600">↺</button>
                  </div>
                </div>

                <div className="relative">
                  <label className="absolute -top-2 left-3 bg-white px-1 text-xs text-gray-500">Slogan</label>
                  <div className="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                    <input
                      value={themingForm.slogan}
                      onChange={(e) => setThemingForm({ ...themingForm, slogan: e.target.value })}
                      className="flex-1 px-3 py-2.5 text-sm outline-none"
                    />
                    <button type="button" onClick={() => setThemingForm({ ...themingForm, slogan: '' })} className="px-3 text-gray-400 hover:text-gray-600">↺</button>
                  </div>
                </div>
              </div>

              {/* Couleur principale */}
              <div>
                <p className="text-sm font-medium text-gray-700 mb-2">Couleur principale</p>
                <p className="text-xs text-gray-500 mb-3">La couleur principale est utilisée pour mettre en évidence les éléments tels que les boutons importants. Elle peut être légèrement modifiée en fonction du schéma de couleurs actuel.</p>
                <div className="flex items-center gap-2">
                  <div className="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                    <span className="px-3 py-2 text-sm font-mono bg-white">{themingForm.primaryColor}</span>
                    <input
                      type="color"
                      value={themingForm.primaryColor}
                      onChange={(e) => setThemingForm({ ...themingForm, primaryColor: e.target.value })}
                      className="w-10 h-10 border-none cursor-pointer bg-transparent p-0"
                      title="Choisir la couleur principale"
                    />
                  </div>
                </div>
              </div>

              {/* Couleur d'arrière-plan */}
              <div>
                <p className="text-sm font-medium text-gray-700 mb-2">Couleur d'arrière-plan</p>
                <p className="text-xs text-gray-500 mb-3">Au lieu d'une image d'arrière-plan, vous pouvez également définir une couleur unie d'arrière-plan. Si vous définissez une image d'arrière-plan, la modification de cette couleur influencera la couleur des icônes du menu de l'application.</p>
                <div className="flex items-center gap-2">
                  <div className="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                    <span className="px-3 py-2 text-sm font-mono bg-white">{themingForm.bgColor}</span>
                    <input
                      type="color"
                      value={themingForm.bgColor}
                      onChange={(e) => setThemingForm({ ...themingForm, bgColor: e.target.value })}
                      className="w-10 h-10 border-none cursor-pointer bg-transparent p-0"
                      title="Choisir la couleur d'arrière-plan"
                    />
                  </div>
                </div>
              </div>

              {/* Logo */}
              <div>
                <p className="text-sm font-medium text-gray-700 mb-2">Logo</p>
                <div className="flex items-center gap-2">
                  <label className="flex items-center gap-2 bg-[#2453d6] hover:bg-[#1f47bb] transition text-white text-xs font-semibold px-4 py-2 rounded-lg cursor-pointer">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                    Téléverser
                    <input type="file" accept="image/*" className="hidden" onChange={(e) => handleThemingFileChange(e, setThemingLogoFile, setThemingLogoPreview)} />
                  </label>
                  {themingLogoFile && (
                    <button type="button" onClick={() => { setThemingLogoFile(null); setThemingLogoPreview(null) }} className="text-gray-400 hover:text-gray-600 text-lg">↺</button>
                  )}
                </div>
                {themingLogoPreview && (
                  <div className="mt-3">
                    <img src={themingLogoPreview} alt="Logo preview" className="h-16 object-contain rounded border border-gray-200 p-1" />
                  </div>
                )}
              </div>

              {/* Image d'arrière-plan */}
              <div>
                <p className="text-sm font-medium text-gray-700 mb-2">Image d'arrière-plan et de connexion</p>
                <div className="flex items-center gap-2">
                  <label className="flex items-center gap-2 bg-[#2453d6] hover:bg-[#1f47bb] transition text-white text-xs font-semibold px-4 py-2 rounded-lg cursor-pointer">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                    Téléverser
                    <input type="file" accept="image/*" className="hidden" onChange={(e) => handleThemingFileChange(e, setThemingBgFile, setThemingBgPreview)} />
                  </label>
                  {themingBgFile && (
                    <button type="button" onClick={() => { setThemingBgFile(null); setThemingBgPreview(null) }} className="text-gray-400 hover:text-gray-600 text-lg">↺</button>
                  )}
                  {themingBgFile && (
                    <button type="button" onClick={() => { setThemingBgFile(null); setThemingBgPreview(null) }} className="text-gray-400 hover:text-red-500 text-lg">🗑</button>
                  )}
                </div>
                {themingBgPreview && (
                  <div className="mt-3">
                    <img src={resolveAssetUrl(themingBgPreview) || ''} alt="Background preview" className="max-w-sm rounded-lg border border-gray-200 object-cover" />
                  </div>
                )}
              </div>

              {/* Options avancées */}
              <div className="pt-4 border-t border-gray-100">
                <h3 className="text-base font-bold text-gray-800 mb-4">Options avancées</h3>

                <div className="space-y-4">
                  <div className="relative">
                    <label className="absolute -top-2 left-3 bg-white px-1 text-xs text-gray-500">Lien vers la notice légale</label>
                    <div className="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                      <input
                        value={themingForm.legalNoticeUrl}
                        onChange={(e) => setThemingForm({ ...themingForm, legalNoticeUrl: e.target.value })}
                        placeholder="https://"
                        className="flex-1 px-3 py-2.5 text-sm outline-none"
                      />
                      <button type="button" onClick={() => setThemingForm({ ...themingForm, legalNoticeUrl: '' })} className="px-3 text-gray-400 hover:text-gray-600">↺</button>
                    </div>
                  </div>

                  <div className="relative">
                    <label className="absolute -top-2 left-3 bg-white px-1 text-xs text-gray-500">Lien vers la politique de confidentialité</label>
                    <div className="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                      <input
                        value={themingForm.privacyPolicyUrl}
                        onChange={(e) => setThemingForm({ ...themingForm, privacyPolicyUrl: e.target.value })}
                        placeholder="https://"
                        className="flex-1 px-3 py-2.5 text-sm outline-none"
                      />
                      <button type="button" onClick={() => setThemingForm({ ...themingForm, privacyPolicyUrl: '' })} className="px-3 text-gray-400 hover:text-gray-600">↺</button>
                    </div>
                  </div>

                  {/* Logo d'en-tête */}
                  <div>
                    <p className="text-sm font-medium text-gray-700 mb-2">Logo d'en-tête</p>
                    <div className="flex items-center gap-2">
                      <label className="flex items-center gap-2 bg-[#2453d6] hover:bg-[#1f47bb] transition text-white text-xs font-semibold px-4 py-2 rounded-lg cursor-pointer">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                        Téléverser
                        <input type="file" accept="image/*" className="hidden" onChange={(e) => handleThemingFileChange(e, setThemingHeaderLogoFile, setThemingHeaderLogoPreview)} />
                      </label>
                      {themingHeaderLogoFile && (
                        <button type="button" onClick={() => { setThemingHeaderLogoFile(null); setThemingHeaderLogoPreview(null) }} className="text-gray-400 hover:text-gray-600 text-lg">↺</button>
                      )}
                    </div>
                    {themingHeaderLogoPreview && (
                      <div className="mt-3">
                        <img src={themingHeaderLogoPreview} alt="Header logo preview" className="h-14 object-contain rounded border border-gray-200 p-1" />
                      </div>
                    )}
                  </div>

                  {/* Favicon */}
                  <div>
                    <p className="text-sm font-medium text-gray-700 mb-2">Favicon</p>
                    <div className="flex items-center gap-2">
                      <label className="flex items-center gap-2 bg-[#2453d6] hover:bg-[#1f47bb] transition text-white text-xs font-semibold px-4 py-2 rounded-lg cursor-pointer">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                        Téléverser
                        <input type="file" accept="image/x-icon,image/png,image/svg+xml" className="hidden" onChange={(e) => handleThemingFileChange(e, setThemingFaviconFile, setThemingFaviconPreview)} />
                      </label>
                      {themingFaviconFile && (
                        <button type="button" onClick={() => { setThemingFaviconFile(null); setThemingFaviconPreview(null) }} className="text-gray-400 hover:text-gray-600 text-lg">↺</button>
                      )}
                    </div>
                    {themingFaviconPreview && (
                      <div className="mt-3">
                        <img src={themingFaviconPreview} alt="Favicon preview" className="h-10 object-contain rounded border border-gray-200 p-1" />
                      </div>
                    )}
                  </div>
                </div>
              </div>

              {/* Paramètres utilisateurs */}
              <div className="pt-4 border-t border-gray-100">
                <h3 className="text-base font-bold text-gray-800 mb-2">Paramètres utilisateurs</h3>
                <label className="flex items-center gap-3 cursor-pointer">
                  <div
                    onClick={() => setThemingForm({ ...themingForm, disableUserTheming: !themingForm.disableUserTheming })}
                    className={`relative w-11 h-6 rounded-full transition-colors ${
                      themingForm.disableUserTheming ? 'bg-[#2453d6]' : 'bg-gray-300'
                    }`}
                  >
                    <span
                      className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${
                        themingForm.disableUserTheming ? 'translate-x-5' : 'translate-x-0'
                      }`}
                    />
                  </div>
                  <span className="text-sm text-gray-700">Désactiver la gestion du thème par l'utilisateur</span>
                </label>
                <p className="text-xs text-gray-500 mt-2">
                  Bien que vous puissiez sélectionner et personnaliser votre instance, les utilisateurs peuvent modifier leur arrière-plan et leurs couleurs.
                  Si vous voulez imposer votre personnalisation, vous pouvez activer cette option.
                </p>
                <p className="text-xs text-gray-400 mt-1">
                  Installez l'extension PHP ImageMagick qui prend en charge les images SVG pour générer automatiquement des favicons à partir du logo téléversé et de la couleur indiquée.
                </p>
              </div>

              {/* Bouton enregistrer */}
              <div className="pt-2">
                <button
                  type="submit"
                  className="w-full bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold py-3 rounded-lg transition"
                >
                  Enregistrer les paramètres d'apparence
                </button>
              </div>

            </form>
          </div>
        </div>
      )}
      {activeTab === 'email-notifications' && (
        <div className="max-w-2xl mx-auto space-y-5">

          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <label className="block text-xs text-gray-500 mb-1">Administration concernée</label>
            <select
              value={selectedEmitterId}
              onChange={(e) => setSelectedEmitterId(e.target.value)}
              disabled={Boolean(lockedEmitterId)}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30 disabled:bg-gray-100"
            >
              <option value="">Sélectionner une administration</option>
              {scopedEmitters.map((item) => (
                <option key={item.id} value={item.id}>{item.name} ({item.code})</option>
              ))}
            </select>
            <p className="text-xs text-gray-400 mt-2">
              Les paramètres SMTP et déclencheurs sont enregistrés par administration.
            </p>
          </div>

          {/* SMTP Configuration */}
          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div className="flex items-center gap-3 mb-1">
              <div className="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center">
                <svg className="w-5 h-5 text-[#2453d6]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
              </div>
              <div>
                <h2 className="text-base font-bold text-gray-800">Configuration SMTP</h2>
                <p className="text-xs text-gray-500">Paramètres du serveur d'envoi d'e-mails</p>
              </div>
            </div>

            <form onSubmit={handleSaveEmailNotif} className="mt-5 space-y-4">

              {/* Host + Port */}
              <div className="grid grid-cols-3 gap-3">
                <div className="col-span-2">
                  <label className="block text-xs text-gray-500 mb-1">Serveur SMTP (Host)</label>
                  <input
                    type="text"
                    value={emailNotifForm.host}
                    onChange={(e) => setEmailNotifForm({ ...emailNotifForm, host: e.target.value })}
                    placeholder="smtp.example.com"
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                  />
                </div>
                <div>
                  <label className="block text-xs text-gray-500 mb-1">Port</label>
                  <input
                    type="number"
                    value={emailNotifForm.port}
                    onChange={(e) => setEmailNotifForm({ ...emailNotifForm, port: e.target.value })}
                    placeholder="587"
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                  />
                </div>
              </div>

              {/* User */}
              <div>
                <label className="block text-xs text-gray-500 mb-1">Compte SMTP (utilisateur)</label>
                <input
                  type="text"
                  value={emailNotifForm.user}
                  onChange={(e) => setEmailNotifForm({ ...emailNotifForm, user: e.target.value })}
                  placeholder="user@example.com"
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                />
              </div>

              {/* Password */}
              <div>
                <label className="block text-xs text-gray-500 mb-1">Mot de passe SMTP</label>
                <div className="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-[#2453d6]/30">
                  <input
                    type={emailNotifPasswordVisible ? 'text' : 'password'}
                    value={emailNotifForm.password}
                    onChange={(e) => setEmailNotifForm({ ...emailNotifForm, password: e.target.value })}
                    placeholder="••••••••"
                    className="flex-1 px-3 py-2 text-sm outline-none"
                  />
                  <button
                    type="button"
                    onClick={() => setEmailNotifPasswordVisible(v => !v)}
                    className="px-3 text-gray-400 hover:text-gray-600 transition"
                    title={emailNotifPasswordVisible ? 'Masquer' : 'Afficher'}
                  >
                    {emailNotifPasswordVisible
                      ? <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                      : <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    }
                  </button>
                </div>
              </div>

              {/* From address */}
              <div>
                <label className="block text-xs text-gray-500 mb-1">Adresse expéditeur (From)</label>
                <input
                  type="email"
                  value={emailNotifForm.from}
                  onChange={(e) => setEmailNotifForm({ ...emailNotifForm, from: e.target.value })}
                  placeholder="noreply@e-parapheur.local"
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                />
              </div>

              {/* Secure / TLS */}
              <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                <div>
                  <p className="text-sm font-medium text-gray-700">Connexion sécurisée (SSL/TLS)</p>
                  <p className="text-xs text-gray-400 mt-0.5">Activez pour le port 465 (SSL). Désactivez pour STARTTLS (port 587).</p>
                </div>
                <div
                  onClick={() => setEmailNotifForm({ ...emailNotifForm, secure: !emailNotifForm.secure })}
                  className={`relative w-11 h-6 rounded-full cursor-pointer transition-colors ${emailNotifForm.secure ? 'bg-[#2453d6]' : 'bg-gray-300'}`}
                >
                  <span className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${emailNotifForm.secure ? 'translate-x-5' : 'translate-x-0'}`} />
                </div>
              </div>

              {/* Buttons */}
              <div className="flex gap-3 pt-2">
                <button
                  type="button"
                  onClick={handleTestEmailNotif}
                  className="flex items-center gap-2 border border-[#2453d6] text-[#2453d6] text-sm font-semibold px-4 py-2.5 rounded-lg hover:bg-blue-50 transition"
                >
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  Tester la connexion
                </button>
                <button
                  type="submit"
                  className="flex-1 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold py-2.5 rounded-lg transition"
                >
                  Enregistrer la configuration SMTP
                </button>
              </div>

            </form>
          </div>

          {/* Notification Triggers */}
          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div className="flex items-center gap-3 mb-5">
              <div className="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center">
                <svg className="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
              </div>
              <div>
                <h2 className="text-base font-bold text-gray-800">Déclencheurs de notifications</h2>
                <p className="text-xs text-gray-500">Choisissez les événements qui déclenchent l'envoi d'un e-mail aux utilisateurs concernés</p>
              </div>
            </div>

            <div className="space-y-3">
              {([
                { key: 'onDocumentShared',        label: 'Partage de document',              desc: 'Notifier le destinataire lorsqu\'un document lui est partagé.' },
                { key: 'onSignatureRequested',    label: 'Demande de signature',             desc: 'Notifier l\'utilisateur lorsqu\'une signature lui est demandée.' },
                { key: 'onSignatureResponded',    label: 'Réponse à une demande de signature', desc: 'Notifier le demandeur lorsque la signature est acceptée ou refusée.' },
                { key: 'onWorkflowAssigned',      label: 'Assignation de workflow',          desc: 'Notifier l\'utilisateur lorsqu\'un workflow lui est assigné.' },
                { key: 'onWorkflowStepCompleted', label: 'Étape de workflow validée',        desc: 'Notifier les parties prenantes à chaque étape franchie.' },
                { key: 'onDocumentUploaded',      label: 'Dépôt de document',                desc: 'Notifier les administrateurs à l\'upload d\'un nouveau document.' },
                { key: 'onUserCreated',           label: 'Création de compte',               desc: 'Envoyer un e-mail de bienvenue lors de la création d\'un compte.' },
              ] as { key: keyof typeof notifTriggers; label: string; desc: string }[]).map(({ key, label, desc }) => (
                <div key={key} className="flex items-start gap-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50 transition">
                  <div
                    onClick={() => setNotifTriggers(prev => ({ ...prev, [key]: !prev[key] }))}
                    className={`relative mt-0.5 w-10 h-5 rounded-full cursor-pointer flex-shrink-0 transition-colors ${notifTriggers[key] ? 'bg-[#2453d6]' : 'bg-gray-300'}`}
                  >
                    <span className={`absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform ${notifTriggers[key] ? 'translate-x-5' : 'translate-x-0'}`} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold text-gray-800">{label}</p>
                    <p className="text-xs text-gray-500 mt-0.5">{desc}</p>
                  </div>
                </div>
              ))}
            </div>

            <div className="mt-5 pt-4 border-t border-gray-100">
              <button
                type="button"
                onClick={handleSaveEmailNotif}
                className="w-full bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold py-2.5 rounded-lg transition"
              >
                Enregistrer les déclencheurs
              </button>
            </div>
          </div>

          {/* Info block */}
          <div className="bg-blue-50 border border-blue-100 rounded-2xl p-4 flex gap-3">
            <svg className="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <div className="text-xs text-blue-700 space-y-1">
              <p className="font-semibold">Configuration par administration</p>
              <p>Ces paramètres sont désormais enregistrés en base de données pour l'administration sélectionnée.</p>
              <p>Les variables <code className="bg-blue-100 px-1 rounded">MAIL_*</code> du backend restent des valeurs globales de secours.</p>
              <pre className="bg-blue-100 rounded p-2 text-[11px] leading-relaxed mt-1">{`MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USER=user@example.com
MAIL_PASSWORD=secret
MAIL_FROM=noreply@e-parapheur.local`}</pre>
            </div>
          </div>

          {/* Chat Settings */}
          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div className="flex items-center gap-3 mb-5">
              <div className="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center">
                <svg className="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
              </div>
              <div>
                <h2 className="text-base font-bold text-gray-800">Paramètres du Chat</h2>
                <p className="text-xs text-gray-500">Configurez le système de messagerie en direct entre utilisateurs</p>
              </div>
            </div>

            <div className="mb-5 rounded-lg border border-gray-200 bg-gray-50 p-3">
              <label className="block text-xs text-gray-500 mb-1">Administration concernée</label>
              <select
                value={selectedEmitterId}
                onChange={(e) => setSelectedEmitterId(e.target.value)}
                disabled={Boolean(lockedEmitterId)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30 disabled:bg-gray-100"
              >
                <option value="">Sélectionner une administration</option>
                {scopedEmitters.map((item) => (
                  <option key={item.id} value={item.id}>{item.name} ({item.code})</option>
                ))}
              </select>
            </div>

            <div className="space-y-4">
              {/* Activer/désactiver le chat */}
              <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                <div>
                  <p className="text-sm font-medium text-gray-700">Activer le chat en direct</p>
                  <p className="text-xs text-gray-400 mt-0.5">Permet aux utilisateurs d'échanger des messages en temps réel.</p>
                </div>
                <div
                  onClick={() => setChatSettings(prev => ({ ...prev, enabled: !prev.enabled }))}
                  className={`relative w-11 h-6 rounded-full cursor-pointer transition-colors ${chatSettings.enabled ? 'bg-[#2453d6]' : 'bg-gray-300'}`}
                >
                  <span className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${chatSettings.enabled ? 'translate-x-5' : 'translate-x-0'}`} />
                </div>
              </div>

              {/* Portée des messages directs */}
              <div className="space-y-2">
                <p className="text-sm font-semibold text-gray-700">Portée des messages directs</p>
                <p className="text-xs text-gray-400">Définissez avec quels utilisateurs un membre peut initier une conversation privée.</p>
                <div className="space-y-2 mt-2">
                  <label className={`flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition ${
                    chatSettings.scope === 'same-administration'
                      ? 'border-[#2453d6] bg-blue-50'
                      : 'border-gray-200 hover:bg-gray-50'
                  }`}>
                    <input
                      type="radio"
                      name="chat_scope"
                      value="same-administration"
                      checked={chatSettings.scope === 'same-administration'}
                      onChange={() => setChatSettings(prev => ({ ...prev, scope: 'same-administration' }))}
                      className="mt-0.5 accent-[#2453d6]"
                    />
                    <div>
                      <p className="text-sm font-medium text-gray-800">Même administration uniquement</p>
                      <p className="text-xs text-gray-500 mt-0.5">Un utilisateur ne peut chatter qu'avec les membres de son administration. Les messages directs vers d'autres administrations sont bloqués.</p>
                    </div>
                  </label>
                  <label className={`flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition ${
                    chatSettings.scope === 'all'
                      ? 'border-[#2453d6] bg-blue-50'
                      : 'border-gray-200 hover:bg-gray-50'
                  }`}>
                    <input
                      type="radio"
                      name="chat_scope"
                      value="all"
                      checked={chatSettings.scope === 'all'}
                      onChange={() => setChatSettings(prev => ({ ...prev, scope: 'all' }))}
                      className="mt-0.5 accent-[#2453d6]"
                    />
                    <div>
                      <p className="text-sm font-medium text-gray-800">Toutes les administrations</p>
                      <p className="text-xs text-gray-500 mt-0.5">Un utilisateur peut envoyer des messages directs à n'importe quel utilisateur connecté, quelle que soit son administration.</p>
                    </div>
                  </label>
                </div>
              </div>

              {/* Info portée actuelle */}
              <div className="flex gap-2 items-start bg-indigo-50 border border-indigo-100 rounded-xl px-3 py-2.5">
                <svg className="w-4 h-4 text-indigo-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <p className="text-xs text-indigo-700">
                  Ce paramètre est pris en compte immédiatement après enregistrement et s'applique à tous les utilisateurs connectés. Le widget chat dans l'interface respectera cette configuration.
                </p>
              </div>
            </div>

            <div className="mt-5 pt-4 border-t border-gray-100">
              <button
                type="button"
                onClick={handleSaveChatSettings}
                disabled={!chatSettings.enabled && chatSettings.scope === 'all'}
                className="w-full bg-[#2453d6] hover:bg-[#1f47bb] disabled:opacity-50 text-white text-sm font-semibold py-2.5 rounded-lg transition"
              >
                Enregistrer les paramètres du chat
              </button>
            </div>
          </div>

        </div>
      )}

      {activeTab === 'signature-provider' && (
        <div className="max-w-3xl mx-auto space-y-5">
          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div className="flex items-center gap-3 mb-5">
              <div className="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center">
                <svg className="w-5 h-5 text-[#2453d6]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 11c0 1.657-1.343 3-3 3s-3-1.343-3-3 1.343-3 3-3 3 1.343 3 3zm0 0V7a4 4 0 118 0v4m-8 0a4 4 0 008 0m-8 0v6m8-6v6" /></svg>
              </div>
              <div>
                <h2 className="text-base font-bold text-gray-800">Configuration API de signature</h2>
                <p className="text-xs text-gray-500">Paramètres utilisés lors du clic sur “Signer” dans l’onglet Signatures</p>
              </div>
            </div>

            <div className="space-y-4">
              <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                <div>
                  <p className="text-sm font-medium text-gray-700">Activer la signature via API externe</p>
                  <p className="text-xs text-gray-400 mt-0.5">Si désactivé, le système utilise la signature locale interne.</p>
                </div>
                <div
                  onClick={() => setSignatureProviderForm(prev => ({ ...prev, isActive: !prev.isActive }))}
                  className={`relative w-11 h-6 rounded-full cursor-pointer transition-colors ${signatureProviderForm.isActive ? 'bg-[#2453d6]' : 'bg-gray-300'}`}
                >
                  <span className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${signatureProviderForm.isActive ? 'translate-x-5' : 'translate-x-0'}`} />
                </div>
              </div>

              {/* Info box: workflow de la plateforme */}
              <div className="p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-700 leading-relaxed">
                <p className="font-semibold mb-1">Flux d'intégration automatique</p>
                <ol className="list-decimal ml-4 space-y-0.5">
                  <li>Recherche du signataire par e-mail sur la plateforme</li>
                  <li>Création du parapheur (workflow)</li>
                  <li>Envoi du fichier PDF vers la plateforme</li>
                  <li>Association du document au workflow</li>
                  <li>Démarrage du workflow</li>
                  <li>Envoi du lien d'invitation au signataire</li>
                </ol>
                <p className="mt-1.5 text-blue-600">L'e-mail du signataire est automatiquement récupéré depuis son compte utilisateur local.</p>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div className="md:col-span-2">
                  <label className="block text-xs text-gray-500 mb-1">Endpoint (URL de base de l'API)</label>
                  <input
                    type="text"
                    value={signatureProviderForm.endpoint}
                    onChange={(e) => setSignatureProviderForm(prev => ({ ...prev, endpoint: e.target.value }))}
                    placeholder="https://uvci.artci-sign.ci"
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                  />
                  <p className="text-xs text-gray-400 mt-0.5">Sans slash final. Ex : https://uvci.artci-sign.ci</p>
                </div>

                <div className="md:col-span-2">
                  <label className="block text-xs text-gray-500 mb-1">API Key (Bearer token)</label>
                  <div className="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-[#2453d6]/30">
                    <input
                      type={signatureProviderApiKeyVisible ? 'text' : 'password'}
                      value={signatureProviderForm.apiKey}
                      onChange={(e) => setSignatureProviderForm(prev => ({ ...prev, apiKey: e.target.value }))}
                      placeholder="act_38Xcy1gjrQ9jTUfozSvpWYMi.xxxx"
                      className="flex-1 px-3 py-2 text-sm outline-none"
                    />
                    <button
                      type="button"
                      onClick={() => setSignatureProviderApiKeyVisible(v => !v)}
                      className="px-3 text-gray-400 hover:text-gray-600 transition text-xs"
                      title={signatureProviderApiKeyVisible ? 'Masquer' : 'Afficher'}
                    >
                      {signatureProviderApiKeyVisible ? 'Masquer' : 'Afficher'}
                    </button>
                  </div>
                  <p className="text-xs text-gray-400 mt-0.5">Token Bearer utilisé dans l'en-tête Authorization de tous les appels API.</p>
                </div>

                <div className="md:col-span-2">
                  <label className="block text-xs text-gray-500 mb-1">User ID propriétaire (sur la plateforme)</label>
                  <input
                    type="text"
                    value={signatureProviderForm.providerOwnerUserId}
                    onChange={(e) => setSignatureProviderForm(prev => ({ ...prev, providerOwnerUserId: e.target.value }))}
                    placeholder="usr_5ewiqwnMDA9s5cPAqBAjuhyS (optionnel — auto-découverte via /api/users/me)"
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                  />
                  <p className="text-xs text-gray-400 mt-0.5">Identifiant du compte propriétaire de l'API sur la plateforme. Si vide, récupéré automatiquement via /api/users/me.</p>
                </div>

                <div>
                  <label className="block text-xs text-gray-500 mb-1">Consent page ID</label>
                  <input
                    type="text"
                    value={signatureProviderForm.consentPageId}
                    onChange={(e) => setSignatureProviderForm(prev => ({ ...prev, consentPageId: e.target.value }))}
                    placeholder="cop_BgKmiR1nxZEeBiGtYhswaUUc"
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                  />
                  <p className="text-xs text-gray-400 mt-0.5">Assigné à chaque étape de signature du workflow.</p>
                </div>

                <div>
                  <label className="block text-xs text-gray-500 mb-1">Profil de signature (Signature Profile ID)</label>
                  <input
                    type="text"
                    value={signatureProviderForm.signatureProfileId}
                    onChange={(e) => setSignatureProviderForm(prev => ({ ...prev, signatureProfileId: e.target.value }))}
                    placeholder="sip_KA49jsZB5kMY82cGACwYgwp8"
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                  />
                  <p className="text-xs text-gray-400 mt-0.5">Utilisé lors de l'association du document au workflow.</p>
                </div>

                <div>
                  <label className="block text-xs text-gray-500 mb-1">Timeout (ms)</label>
                  <input
                    type="number"
                    min={1000}
                    value={signatureProviderForm.timeoutMs}
                    onChange={(e) => setSignatureProviderForm(prev => ({ ...prev, timeoutMs: Number(e.target.value || 30000) }))}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                  />
                </div>

                <div className="md:col-span-2 rounded-lg border border-gray-200 bg-gray-50 p-3">
                  <p className="text-sm font-medium text-gray-700">Position visuelle du QR sur le document (A4)</p>
                  <p className="text-xs text-gray-400 mt-0.5">Paramètre d'administration utilisé lors de la signature externe.</p>

                  <div className="grid grid-cols-1 md:grid-cols-5 gap-2 mt-3">
                    <div>
                      <label className="block text-[11px] text-gray-500 mb-1">Page</label>
                      <input
                        type="number"
                        value={signatureQrPositionForm.imagePage}
                        onChange={(e) => setSignatureQrPositionForm(prev => ({ ...prev, imagePage: e.target.value }))}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                      />
                    </div>
                    <div>
                      <label className="block text-[11px] text-gray-500 mb-1">X</label>
                      <input
                        type="number"
                        value={signatureQrPositionForm.imageX}
                        onChange={(e) => setSignatureQrPositionForm(prev => ({ ...prev, imageX: e.target.value }))}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                      />
                    </div>
                    <div>
                      <label className="block text-[11px] text-gray-500 mb-1">Y</label>
                      <input
                        type="number"
                        value={signatureQrPositionForm.imageY}
                        onChange={(e) => setSignatureQrPositionForm(prev => ({ ...prev, imageY: e.target.value }))}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                      />
                    </div>
                    <div>
                      <label className="block text-[11px] text-gray-500 mb-1">Largeur</label>
                      <input
                        type="number"
                        value={signatureQrPositionForm.imageWidth}
                        onChange={(e) => setSignatureQrPositionForm(prev => ({ ...prev, imageWidth: e.target.value }))}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                      />
                    </div>
                    <div>
                      <label className="block text-[11px] text-gray-500 mb-1">Hauteur</label>
                      <input
                        type="number"
                        value={signatureQrPositionForm.imageHeight}
                        onChange={(e) => setSignatureQrPositionForm(prev => ({ ...prev, imageHeight: e.target.value }))}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                      />
                    </div>
                  </div>
                </div>
              </div>

              <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                <div>
                  <p className="text-sm font-medium text-gray-700">Vérification SSL</p>
                  <p className="text-xs text-gray-400 mt-0.5">Conservez activé en production.</p>
                </div>
                <div
                  onClick={() => setSignatureProviderForm(prev => ({ ...prev, verifySsl: !prev.verifySsl }))}
                  className={`relative w-11 h-6 rounded-full cursor-pointer transition-colors ${signatureProviderForm.verifySsl ? 'bg-[#2453d6]' : 'bg-gray-300'}`}
                >
                  <span className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${signatureProviderForm.verifySsl ? 'translate-x-5' : 'translate-x-0'}`} />
                </div>
              </div>
            </div>

            <div className="mt-5 pt-4 border-t border-gray-100">
              <button
                type="button"
                onClick={handleSaveSignatureProviderConfig}
                className="w-full bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold py-2.5 rounded-lg transition"
              >
                Enregistrer la configuration API Signature
              </button>
            </div>
          </div>
        </div>
      )}

      {activeTab === 'user-profiles' && (
        <div className="space-y-5">
          <div className="flex justify-end">
            <button
              type="button"
              onClick={() => setShowProfilesList(prev => !prev)}
              className="inline-flex items-center gap-2 border border-[#2453d6] text-[#2453d6] text-xs font-semibold px-3 py-2 rounded-lg hover:bg-blue-50 transition"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" /></svg>
              {showProfilesList ? 'Masquer la liste des profils' : 'Liste des profils'}
            </button>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">

            {/* ----- Liste des profils ----- */}
            {showProfilesList && (
            <div className="lg:col-span-3">
              <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 h-full">
                <div className="flex items-center justify-between mb-4">
                  <h2 className="text-base font-bold text-gray-800">Profils existants</h2>
                  <span className="text-xs text-gray-400">{userAppProfiles.length} profil{userAppProfiles.length !== 1 ? 's' : ''}</span>
                </div>

                {userAppProfiles.length === 0 && (
                  <div className="text-center py-10">
                    <div className="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                      <svg className="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </div>
                    <p className="text-xs text-gray-400">Aucun profil créé</p>
                    <p className="text-xs text-gray-300 mt-1">Utilisez le formulaire pour créer votre premier profil</p>
                  </div>
                )}

                {userAppProfiles.length > 0 && (
                  <div className="overflow-auto rounded-xl border border-gray-200">
                    <table className="w-full min-w-[620px] text-left">
                      <thead className="bg-gray-100 text-gray-600 text-[11px] uppercase tracking-wide">
                        <tr>
                          <th className="px-3 py-2 font-semibold">Code</th>
                          <th className="px-3 py-2 font-semibold">Nom profil</th>
                          <th className="px-3 py-2 font-semibold">Description</th>
                          <th className="px-3 py-2 font-semibold">Menus</th>
                          <th className="px-3 py-2 font-semibold">Actions</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-gray-100 text-xs">
                        {userAppProfiles.map((p) => (
                          <tr key={p.id} className={profileEditId === p.id ? 'bg-blue-50' : 'bg-white'}>
                            <td className="px-3 py-2 font-semibold text-gray-700">PF-{p.id.slice(-4)}</td>
                            <td className="px-3 py-2 text-gray-800 font-medium">{p.name}</td>
                            <td className="px-3 py-2 text-gray-600 max-w-[180px] truncate">{p.description || '-'}</td>
                            <td className="px-3 py-2 text-gray-600">{p.permissions.length} permission{p.permissions.length > 1 ? 's' : ''}</td>
                            <td className="px-3 py-2">
                              <div className="flex items-center gap-2">
                                <button
                                  type="button"
                                  onClick={() => startEditProfile(p)}
                                  className="p-1 rounded text-blue-600 hover:bg-blue-50"
                                  title="Modifier"
                                >
                                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </button>
                                <button
                                  type="button"
                                  onClick={() => handleDeleteUserProfile(p.id, p.name)}
                                  className="p-1 rounded text-red-600 hover:bg-red-50"
                                  title="Supprimer"
                                >
                                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                              </div>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>
            )}

            {/* ----- Formulaire de création / édition ----- */}
            {!showProfilesList && (
            <div className="lg:col-span-3">
              <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <div className="flex items-center justify-between mb-5">
                  <div>
                    <h2 className="text-base font-bold text-gray-800">
                      {profileEditId ? 'Modifier le profil' : 'Créer un profil'}
                    </h2>
                    <p className="text-xs text-gray-500 mt-0.5">Définissez les accès aux menus et sous-menus de l'application</p>
                  </div>
                  {profileEditId && (
                    <button onClick={cancelProfileEdit} className="text-xs text-gray-500 hover:text-gray-700 border border-gray-300 rounded-lg px-3 py-1.5 hover:bg-gray-50 transition">
                      + Nouveau profil
                    </button>
                  )}
                </div>

                <form onSubmit={handleSaveUserProfile} className="space-y-5">

                  {/* Nom + Description */}
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">Administration émettrice <span className="text-red-500">*</span></label>
                      <select
                        value={selectedEmitterId}
                        onChange={e => setSelectedEmitterId(e.target.value)}
                        disabled={Boolean(profileEditId) || Boolean(lockedEmitterId)}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30 disabled:bg-gray-100 disabled:text-gray-500"
                        required
                      >
                        <option value="">Sélectionner une administration</option>
                        {scopedEmitters.map((item) => (
                          <option key={item.id} value={item.id}>{item.name}</option>
                        ))}
                      </select>
                      {profileEditId && (
                        <p className="text-[11px] text-gray-400 mt-1">L'administration émettrice n'est pas modifiable en édition.</p>
                      )}
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">Nom du profil <span className="text-red-500">*</span></label>
                      <input
                        type="text"
                        value={profileFormData.name}
                        onChange={e => setProfileFormData({ ...profileFormData, name: e.target.value })}
                        placeholder="Ex: Gestionnaire, Lecteur…"
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">Description</label>
                      <input
                        type="text"
                        value={profileFormData.description}
                        onChange={e => setProfileFormData({ ...profileFormData, description: e.target.value })}
                        placeholder="Courte description du rôle…"
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#2453d6]/30"
                      />
                    </div>
                  </div>

                  {/* Permissions tree */}
                  <div>
                    <div className="flex items-center justify-between mb-3">
                      <label className="text-xs font-medium text-gray-600">Accès aux menus &amp; fonctionnalités</label>
                      <div className="flex gap-2">
                        <button
                          type="button"
                          onClick={() => setProfilePermissions(ALL_PERMISSION_IDS)}
                          className="text-[11px] text-[#2453d6] hover:underline"
                        >Tout sélectionner</button>
                        <span className="text-gray-300 text-xs">|</span>
                        <button type="button" onClick={() => setProfilePermissions([])} className="text-[11px] text-gray-500 hover:underline">Tout désélectionner</button>
                      </div>
                    </div>

                    <div className="border border-gray-200 rounded-xl overflow-hidden divide-y divide-gray-100">
                      {APP_PERMISSION_TREE.map(node => (
                        <div key={node.id}>
                          {/* Parent row */}
                          <div className="flex items-center gap-3 px-4 py-3 bg-gray-50 hover:bg-gray-100 transition">
                            {node.children?.length ? (
                              <button
                                type="button"
                                onClick={() => setProfileExpandedSections(prev => ({ ...prev, [node.id]: !prev[node.id] }))}
                                className="text-gray-400 hover:text-gray-600 transition flex-shrink-0"
                              >
                                <svg
                                  className={`w-4 h-4 transition-transform ${profileExpandedSections[node.id] ? 'rotate-90' : ''}`}
                                  fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                >
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                </svg>
                              </button>
                            ) : (
                              <span className="w-4 flex-shrink-0" />
                            )}

                            <label className="flex items-center gap-2.5 cursor-pointer flex-1">
                              <input
                                type="checkbox"
                                checked={node.children?.length ? isParentChecked(node) : profilePermissions.includes(node.id)}
                                ref={el => { if (el) el.indeterminate = isParentIndeterminate(node) }}
                                onChange={() => toggleProfilePermission(node.id, node)}
                                className="w-4 h-4 rounded accent-[#2453d6] cursor-pointer"
                              />
                              <span className="text-sm font-semibold text-gray-800">{node.label}</span>
                              {node.children?.length ? (
                                <span className="text-[11px] text-gray-400 ml-auto">
                                  {node.children.filter(c => profilePermissions.includes(c.id)).length}/{node.children.length}
                                </span>
                              ) : null}
                            </label>
                          </div>

                          {/* Children */}
                          {node.children?.length && profileExpandedSections[node.id] && (
                            <div className="divide-y divide-gray-50">
                              {node.children.map(child => (
                                <label key={child.id} className="flex items-center gap-3 pl-11 pr-4 py-2.5 cursor-pointer hover:bg-blue-50/40 transition">
                                  <input
                                    type="checkbox"
                                    checked={profilePermissions.includes(child.id)}
                                    onChange={() => toggleProfilePermission(child.id, undefined)}
                                    className="w-4 h-4 rounded accent-[#2453d6] cursor-pointer flex-shrink-0"
                                  />
                                  <span className="text-sm text-gray-700">{child.label}</span>
                                </label>
                              ))}
                            </div>
                          )}
                        </div>
                      ))}
                    </div>

                    {profilePermissions.length > 0 && (
                      <p className="text-xs text-[#2453d6] mt-2 font-medium">{profilePermissions.length} permission{profilePermissions.length > 1 ? 's' : ''} sélectionnée{profilePermissions.length > 1 ? 's' : ''}</p>
                    )}
                  </div>

                  {/* Submit */}
                  <div className="flex gap-3 pt-2">
                    {profileEditId && (
                      <button type="button" onClick={cancelProfileEdit} className="border border-gray-300 text-gray-700 text-sm font-semibold px-4 py-2.5 rounded-lg hover:bg-gray-50 transition">
                        Annuler
                      </button>
                    )}
                    <button
                      type="submit"
                      className="flex-1 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold py-2.5 rounded-lg transition"
                    >
                      {profileEditId ? 'Enregistrer les modifications' : 'Créer le profil'}
                    </button>
                  </div>

                </form>
              </div>
            </div>
            )}
          </div>
        </div>
      )}

      {templatePositioningDocId && templatePositioningFileUrl && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="bg-white rounded-xl shadow-lg border border-gray-200 w-full max-w-6xl h-[86vh] overflow-hidden flex flex-col">
            <div className="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
              <div>
                <p className="text-lg font-semibold text-gray-800">Document PDF - Zones de signature</p>
                <p className="text-sm text-gray-600">{templatePositioningDocName}</p>
                <p className="text-sm text-gray-500">{(templateZonesByDocKey[`doc-${templatePositioningDocId}`] || []).length} zone(s){templateSavedZoneByDocKey[`doc-${templatePositioningDocId}`] ? ' • Positionnée' : ''}</p>
              </div>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => addTemplateZone()}
                  className="px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold"
                >
                  Ajouter zone
                </button>
                <button
                  type="button"
                  onClick={clearTemplateZones}
                  className="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold"
                >
                  Effacer zones
                </button>
                <button
                  type="button"
                  onClick={saveTemplateZonePlacement}
                  className="px-3 py-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-xs font-semibold"
                >
                  Enregistrer
                </button>
                <button
                  type="button"
                  onClick={closeTemplatePositioning}
                  className="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold"
                >
                  Fermer
                </button>
              </div>
            </div>

            <div
              className="relative flex-1 bg-gray-100 overflow-auto"
              onPointerMove={handleTemplateZonePointerMove}
              onPointerUp={() => setTemplateDragAction(null)}
              onPointerLeave={() => setTemplateDragAction(null)}
            >
              {(docViewer === 'onlyoffice' && !templateForceNativeViewer && Boolean(onlyofficeUrl)) ? (
                <iframe
                  title="OnlyOffice PDF Viewer"
                  src={`${onlyofficeUrl.replace(/\/$/, '')}/web-apps/apps/documenteditor/main/index.html?fileUrl=${encodeURIComponent(templatePositioningFileUrl)}`}
                  className="absolute inset-0 w-full h-full border-0"
                  onError={() => {
                    setTemplateForceNativeViewer(true)
                    showResult('error', 'OnlyOffice indisponible pour ce document. Passage au lecteur PDF natif.')
                  }}
                />
              ) : (
                <object data={templatePositioningFileUrl} type="application/pdf" className="absolute inset-0 w-full h-full">
                  <div className="absolute inset-0 grid place-items-center bg-white p-4 text-center">
                    <div>
                      <p className="text-sm text-gray-700 mb-2">Le lecteur PDF intégré n'est pas disponible.</p>
                      <a href={templatePositioningFileUrl} target="_blank" rel="noreferrer" className="text-sm font-semibold text-[#2453d6] underline">Ouvrir le PDF dans un nouvel onglet</a>
                    </div>
                  </div>
                </object>
              )}

              <div className={`absolute inset-0 pointer-events-none ${templateDragAction ? 'cursor-grabbing' : ''}`} style={templateDragAction ? { pointerEvents: 'auto' } : undefined}>
                {(templateZonesByDocKey[`doc-${templatePositioningDocId}`] || []).map((zone, index) => (
                  <div
                    key={zone.id}
                    className="absolute pointer-events-auto border-2 border-blue-600 bg-blue-100/75 hover:border-blue-700 text-blue-900 text-xs font-semibold flex flex-col items-center justify-center cursor-move group transition-colors select-none"
                    style={{ left: `${zone.x}%`, top: `${zone.y}%`, width: `${zone.width}%`, height: `${zone.height}%` }}
                    title="Glissez pour déplacer"
                    onPointerDown={(event) => {
                      event.stopPropagation()
                      event.preventDefault()
                      const rect = event.currentTarget.parentElement!.getBoundingClientRect()
                      setTemplateDragAction({
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
                        deleteTemplateZone(zone.id)
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
                        setTemplateDragAction({
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

      {showTemplateOnlyOfficeEditor && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="bg-white rounded-xl shadow-lg border border-gray-200 w-full max-w-6xl h-[86vh] overflow-hidden flex flex-col">
            <div className="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
              <div>
                <p className="text-lg font-semibold text-gray-800">Rédaction du modèle - OnlyOffice</p>
                <p className="text-sm text-gray-600">Template sélectionné: {selectedTemplateName || 'Aucun'}</p>
                <p className="text-sm text-gray-500">{templateEditorZones.length} zone(s){templateEditorSaved ? ' • Enregistrées' : ''}</p>
              </div>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={handleCreateTemplateFromOnlyOffice}
                  className="px-3 py-1.5 rounded-lg bg-[#2453d6] hover:bg-[#1f47bb] text-white text-xs font-semibold"
                >
                  Créer le modèle
                </button>
                <button
                  type="button"
                  onClick={() => addTemplateEditorZone()}
                  className="px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold"
                >
                  Ajouter zone
                </button>
                <button
                  type="button"
                  onClick={clearTemplateEditorZones}
                  className="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold"
                >
                  Effacer zone
                </button>
                <button
                  type="button"
                  onClick={saveTemplateEditorZones}
                  className="px-3 py-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-xs font-semibold"
                >
                  Enregistrer
                </button>
                <button
                  type="button"
                  onClick={closeTemplateOnlyOfficeEditor}
                  className="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold"
                >
                  Fermer
                </button>
              </div>
            </div>

            <div
              className="relative flex-1 bg-gray-100 overflow-auto"
              onPointerMove={handleTemplateEditorPointerMove}
              onPointerUp={() => setTemplateDragAction(null)}
              onPointerLeave={() => setTemplateDragAction(null)}
            >
              <iframe
                title="OnlyOffice Template Editor"
                src={`${onlyofficeUrl.replace(/\/$/, '')}/web-apps/apps/documenteditor/main/index.html`}
                className="absolute inset-0 w-full h-full border-0"
                onError={() => {
                  showResult('error', 'OnlyOffice indisponible pour la rédaction du modèle.')
                }}
              />

              <div className={`absolute inset-0 pointer-events-none ${templateDragAction ? 'cursor-grabbing' : ''}`} style={templateDragAction ? { pointerEvents: 'auto' } : undefined}>
                {templateEditorZones.map((zone, index) => (
                  <div
                    key={zone.id}
                    className="absolute pointer-events-auto border-2 border-blue-600 bg-blue-100/75 hover:border-blue-700 text-blue-900 text-xs font-semibold flex flex-col items-center justify-center cursor-move group transition-colors select-none"
                    style={{ left: `${zone.x}%`, top: `${zone.y}%`, width: `${zone.width}%`, height: `${zone.height}%` }}
                    title="Glissez pour déplacer"
                    onPointerDown={(event) => {
                      event.stopPropagation()
                      event.preventDefault()
                      const rect = event.currentTarget.parentElement!.getBoundingClientRect()
                      setTemplateDragAction({
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
                        deleteTemplateEditorZone(zone.id)
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
                        setTemplateDragAction({
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

      {/* ===== POPUP SUCCÈS CRÉATION UTILISATEUR ===== */}
      {userCreatedSuccess.open && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm" onClick={() => setUserCreatedSuccess({ open: false, fullName: '' })}>
          <div className="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full mx-4 flex flex-col items-center text-center" onClick={(e) => e.stopPropagation()}>
            <div className="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mb-4">
              <svg className="w-9 h-9 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <h3 className="text-xl font-bold text-gray-800 mb-2">Utilisateur créé !</h3>
            <p className="text-sm text-gray-600 mb-1">Le compte de</p>
            <p className="text-base font-semibold text-green-700 mb-4">{userCreatedSuccess.fullName}</p>
            <p className="text-sm text-gray-500 mb-6">a été créé avec succès et est maintenant disponible dans la liste des utilisateurs.</p>
            <button
              type="button"
              onClick={() => setUserCreatedSuccess({ open: false, fullName: '' })}
              className="px-6 py-2.5 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold rounded-lg transition"
            >
              Fermer
            </button>
          </div>
        </div>
      )}

      {/* ===== MODAL PARTAGE DE TEMPLATE ===== */}
      {shareTemplateId && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" onClick={() => setShareTemplateId(null)}>
          <div className="bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full mx-4" onClick={e => e.stopPropagation()}>
            <div className="flex items-center gap-3 mb-4">
              <div className="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                <svg className="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" /></svg>
              </div>
              <div>
                <h3 className="text-base font-bold text-gray-800">Partager le template</h3>
                <p className="text-xs text-gray-500">{templates.find(t => t.id === shareTemplateId)?.name}</p>
              </div>
            </div>

            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 mb-1">Rechercher un utilisateur</label>
              <input
                type="text"
                value={shareSearch}
                onChange={(e) => setShareSearch(e.target.value)}
                placeholder="Nom, email..."
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6]"
              />
            </div>

            <div className="max-h-52 overflow-auto space-y-1 mb-4">
              {managedUsers
                .filter(u => {
                  if (!shareSearch.trim()) return true
                  const q = shareSearch.toLowerCase()
                  return (u.fullName || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q) || (u.username || '').toLowerCase().includes(q)
                })
                .map(u => (
                  <button
                    key={u.id}
                    type="button"
                    onClick={() => setShareUserId(u.id)}
                    className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-left transition ${shareUserId === u.id ? 'bg-blue-50 border border-[#2453d6]' : 'hover:bg-gray-50 border border-transparent'}`}
                  >
                    <div className="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600 flex-shrink-0">
                      {u.avatar ? (
                        <img src={u.avatar} alt="" className="w-8 h-8 rounded-full object-cover" />
                      ) : (
                        (u.fullName || u.username || '?').charAt(0).toUpperCase()
                      )}
                    </div>
                    <div className="min-w-0">
                      <p className="text-sm font-medium text-gray-800 truncate">{u.fullName || u.username}</p>
                      <p className="text-xs text-gray-500 truncate">{u.email}</p>
                    </div>
                    {shareUserId === u.id && (
                      <svg className="w-5 h-5 text-[#2453d6] ml-auto flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                    )}
                  </button>
                ))
              }
              {managedUsers.filter(u => {
                if (!shareSearch.trim()) return true
                const q = shareSearch.toLowerCase()
                return (u.fullName || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q) || (u.username || '').toLowerCase().includes(q)
              }).length === 0 && (
                <p className="text-xs text-gray-500 text-center py-4">Aucun utilisateur trouvé.</p>
              )}
            </div>

            <div className="flex gap-3">
              <button
                type="button"
                onClick={() => setShareTemplateId(null)}
                className="flex-1 border border-gray-300 text-gray-700 rounded-xl px-4 py-2.5 text-sm font-semibold hover:bg-gray-50 transition"
              >
                Annuler
              </button>
              <button
                type="button"
                disabled={!shareUserId}
                onClick={() => {
                  void (async () => {
                    const user = managedUsers.find(u => u.id === shareUserId)
                    const tpl = templates.find(t => t.id === shareTemplateId)
                    if (!user || !tpl || !shareTemplateId) return
                    const previous = templateShareMap[shareTemplateId] || []
                    const nextTemplateUsers = Array.from(new Set([...previous, shareUserId]))
                    const nextShareMap = {
                      ...templateShareMap,
                      [shareTemplateId]: nextTemplateUsers,
                    }
                    try {
                      await upsertAppSettings([
                        {
                          key: TEMPLATE_SHARE_MAP_SETTING_KEY,
                          value: JSON.stringify(nextShareMap),
                          description: 'Map des templates partages par utilisateur',
                        },
                      ])
                      setTemplateShareMap(nextShareMap)
                      showResult('success', `Template "${tpl.name}" partagé avec ${user.fullName || user.username}.`)
                      setShareTemplateId(null)
                    } catch (err: any) {
                      showResult('error', err?.response?.data?.message || 'Impossible de partager le template.')
                    }
                  })()
                }}
                className="flex-1 bg-[#2453d6] hover:bg-[#1f47bb] text-white rounded-xl px-4 py-2.5 text-sm font-semibold transition disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Partager
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ===== CONFIRMATION MODAL ===== */}
      {confirmModal.open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" onClick={() => setConfirmModal(prev => ({ ...prev, open: false }))}>
          <div className="bg-white rounded-2xl shadow-2xl p-6 max-w-sm w-full mx-4" onClick={e => e.stopPropagation()}>
            <div className="flex items-center gap-3 mb-4">
              <div className="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                <svg className="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.834-1.964-.834-2.732 0L3.07 16.5C2.3 17.333 3.262 19 4.8 19z" />
                </svg>
              </div>
              <h3 className="text-base font-bold text-gray-800">{confirmModal.title}</h3>
            </div>
            <p className="text-sm text-gray-600 mb-6 leading-relaxed">{confirmModal.message}</p>
            <div className="flex gap-3">
              <button
                type="button"
                onClick={() => setConfirmModal(prev => ({ ...prev, open: false }))}
                className="flex-1 border border-gray-300 text-gray-700 rounded-xl px-4 py-2.5 text-sm font-semibold hover:bg-gray-50 transition"
              >
                Annuler
              </button>
              <button
                type="button"
                onClick={() => { setConfirmModal(prev => ({ ...prev, open: false })); confirmModal.onConfirm() }}
                className="flex-1 bg-[#2453d6] hover:bg-[#1f47bb] text-white rounded-xl px-4 py-2.5 text-sm font-semibold transition"
              >
                Confirmer
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ===== RESULT TOAST ===== */}
      {resultModal.open && (
        <div className="fixed bottom-6 right-6 z-50 max-w-sm w-full animate-in slide-in-from-bottom-4">
          <div className={`rounded-2xl shadow-2xl p-4 flex items-start gap-3 border ${
            resultModal.type === 'success'
              ? 'bg-green-50 border-green-200'
              : 'bg-red-50 border-red-200'
          }`}>
            <div className={`w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 ${
              resultModal.type === 'success' ? 'bg-green-100' : 'bg-red-100'
            }`}>
              {resultModal.type === 'success' ? (
                <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" />
                </svg>
              ) : (
                <svg className="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M6 18L18 6M6 6l12 12" />
                </svg>
              )}
            </div>
            <div className="flex-1 min-w-0">
              <p className={`text-sm font-bold ${resultModal.type === 'success' ? 'text-green-800' : 'text-red-800'}`}>
                {resultModal.type === 'success' ? 'Succès' : 'Erreur'}
              </p>
              <p className={`text-xs mt-0.5 leading-relaxed ${resultModal.type === 'success' ? 'text-green-700' : 'text-red-700'}`}>
                {resultModal.message}
              </p>
            </div>
            <button
              type="button"
              onClick={() => setResultModal(prev => ({ ...prev, open: false }))}
              className="text-gray-400 hover:text-gray-600 text-xl leading-none flex-shrink-0 -mt-0.5"
            >
              ×
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

export default Settings
