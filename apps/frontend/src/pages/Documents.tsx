import { MouseEvent, useEffect, useMemo, useRef, useState } from 'react';
import {
  Folder, FileText, MoreHorizontal, UserPlus,
  Star, Info, Tag, Pencil, FolderInput,
  Bell, MonitorDown, Download, Trash2,
} from 'lucide-react';
import { DocumentItem } from '../types/document';
import {
  fetchDocuments,
  createDocument,
  deleteDocument,
  updateDocument,
  shareDocument,
  fetchMyDocumentPreferences,
  updateDocumentFavoritePreference,
  updateDocumentLabelCodesPreference,
} from '../services/documents';
import { fetchRecipientAdministrations } from '../services/administration';
import { RecipientAdministration } from '../types/administration';

function Documents() {
  const [documents, setDocuments] = useState<DocumentItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [openActionsId, setOpenActionsId] = useState<string | null>(null);
  const [folderTabs, setFolderTabs] = useState<string[]>([]);
  const [activeFolderTab, setActiveFolderTab] = useState<string | null>(null);
  const [activeSubTab, setActiveSubTab] = useState<'all' | 'favorites' | 'labels'>('all');
  const [favoriteDocumentIds, setFavoriteDocumentIds] = useState<string[]>([]);
  const [documentLabelCodes, setDocumentLabelCodes] = useState<Record<string, string[]>>({});
  const [labelSearchCode, setLabelSearchCode] = useState('');
  const [isShareModalOpen, setIsShareModalOpen] = useState(false);
  const [shareTarget, setShareTarget] = useState<DocumentItem | null>(null);
  const [shareMode, setShareMode] = useState<'internal' | 'external' | 'recipient_administration'>('internal');
  const [shareInternalRecipient, setShareInternalRecipient] = useState('');
  const [shareExternalEmail, setShareExternalEmail] = useState('');
  const [recipientAdministrations, setRecipientAdministrations] = useState<RecipientAdministration[]>([]);
  const [shareRecipientAdministrationId, setShareRecipientAdministrationId] = useState('');
  const [shareApplicantFullName, setShareApplicantFullName] = useState('');
  const [shareApplicantMatricule, setShareApplicantMatricule] = useState('');
  const [shareApplicantEmail, setShareApplicantEmail] = useState('');
  const [sharePermission, setSharePermission] = useState<'lecture' | 'modification'>('lecture');
  const [shareHasDelay, setShareHasDelay] = useState(false);
  const [shareDelayValue, setShareDelayValue] = useState('24');
  const [shareDelayUnit, setShareDelayUnit] = useState<'hours' | 'days'>('hours');
  const [shareStatus, setShareStatus] = useState<{ type: 'success' | 'error'; message: string } | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const folderInputRef = useRef<HTMLInputElement>(null);

  const normalizeLabelCode = (value: string) => value.trim().toUpperCase();

  const isFavoriteDocument = (docId: string) => favoriteDocumentIds.includes(docId);

  const parseLabelCodes = (value: string) => Array.from(new Set(
    value
      .split(',')
      .map((item) => normalizeLabelCode(item))
      .filter((item) => item.length > 0),
  ));

  const isFolder = (doc: DocumentItem) => doc.type === 'folder' || doc.description === '[folder]';

  const getParentFolderName = (doc: DocumentItem): string | null => {
    if (isFolder(doc)) return null;
    if (!doc.description) return null;
    const match = doc.description.match(/^Dossier:\s*(.+)$/i);
    return match?.[1]?.trim() || null;
  };

  const openFileInOnlyOffice = (doc: DocumentItem) => {
    const onlyOfficeBaseUrl = (localStorage.getItem('oo_url') || '').replace(/\/$/, '');
    if (!onlyOfficeBaseUrl) {
      setError('URL OnlyOffice non configurée. Configurez-la dans l’onglet Administration > OnlyOffice.');
      return;
    }

    const readerUrl = `${onlyOfficeBaseUrl}/web-apps/apps/documenteditor/main/index.html?title=${encodeURIComponent(doc.title)}&mode=view`;
    window.open(readerUrl, '_blank', 'noopener,noreferrer');
  };

  const handleDocumentDoubleClick = (doc: DocumentItem, event: MouseEvent<HTMLTableRowElement>) => {
    const target = event.target as HTMLElement | null;
    if (target?.closest('[data-row-actions="true"]')) return;

    if (isFolder(doc)) {
      setFolderTabs((prev) => (prev.includes(doc.title) ? prev : [...prev, doc.title]));
      setActiveFolderTab(doc.title);
      return;
    }

    openFileInOnlyOffice(doc);
  };

  const loadDocuments = async () => {
    setLoading(true);
    setError(null);
    try {
      const [data, preferences] = await Promise.all([
        fetchDocuments(),
        fetchMyDocumentPreferences(),
      ]);
      setDocuments(Array.isArray(data) ? data : []);

      const nextFavoriteIds = preferences
        .filter((item) => Boolean(item?.isFavorite) && String(item?.documentId || '').trim())
        .map((item) => String(item.documentId));
      setFavoriteDocumentIds(Array.from(new Set(nextFavoriteIds)));

      const nextLabelCodes = preferences.reduce((acc, item) => {
        const docId = String(item?.documentId || '').trim();
        if (!docId) return acc;
        const codes = Array.isArray(item?.labelCodes)
          ? item.labelCodes.map((code) => normalizeLabelCode(code)).filter(Boolean)
          : [];
        if (codes.length > 0) {
          acc[docId] = Array.from(new Set(codes));
        }
        return acc;
      }, {} as Record<string, string[]>);
      setDocumentLabelCodes(nextLabelCodes);
    } catch (err) {
      console.error(err);
      setError('Impossible de charger les documents.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadDocuments();
  }, []);

  useEffect(() => {
    const loadRecipients = async () => {
      try {
        const data = await fetchRecipientAdministrations();
        setRecipientAdministrations((Array.isArray(data) ? data : []).filter((item) => item.isActive));
      } catch {
        setRecipientAdministrations([]);
      }
    };
    void loadRecipients();
  }, []);

  const handleDelete = async (id: string) => {
    if (!window.confirm('Supprimer ce document ?')) return;
    try {
      await deleteDocument(id);
      setDocuments((prev) => prev.filter((doc) => doc.id !== id));
      setOpenActionsId(null);
    } catch (err) {
      console.error(err);
      setError('Impossible de supprimer le document.');
    }
  };

  const updateDocumentLocally = (id: string, updater: (doc: DocumentItem) => DocumentItem) => {
    setDocuments((prev) => prev.map((doc) => (doc.id === id ? updater(doc) : doc)));
  };

  const handleRename = async (doc: DocumentItem) => {
    const newTitle = window.prompt('Nouveau nom', doc.title);
    if (!newTitle || !newTitle.trim() || newTitle.trim() === doc.title) return;

    try {
      const updated = await updateDocument(doc.id, { title: newTitle.trim() });
      updateDocumentLocally(doc.id, () => updated);
      if (isFolder(doc) && doc.title !== updated.title) {
        setFolderTabs((prev) => prev.map((tab) => (tab === doc.title ? updated.title : tab)));
        setActiveFolderTab((prev) => (prev === doc.title ? updated.title : prev));
      }
      setOpenActionsId(null);
    } catch (err) {
      console.error(err);
      setError('Impossible de renommer cet élément.');
    }
  };

  const handleMove = async (doc: DocumentItem) => {
    const destination = window.prompt('Déplacer vers (nom du dossier)');
    if (!destination || !destination.trim()) return;

    try {
      const nextDescription = `Dossier: ${destination.trim()}`;
      const updated = await updateDocument(doc.id, { description: nextDescription });
      updateDocumentLocally(doc.id, () => updated);
      setOpenActionsId(null);
    } catch (err) {
      console.error(err);
      setError('Impossible de déplacer cet élément.');
    }
  };

  const openShareModal = (doc: DocumentItem) => {
    setShareTarget(doc);
    setShareMode('internal');
    setShareInternalRecipient('');
    setShareExternalEmail('');
    setShareRecipientAdministrationId('');
    setShareApplicantFullName('');
    setShareApplicantMatricule('');
    setShareApplicantEmail('');
    setSharePermission('lecture');
    setShareHasDelay(false);
    setShareDelayValue('24');
    setShareDelayUnit('hours');
    setShareStatus(null);
    setIsShareModalOpen(true);
    setOpenActionsId(null);
  };

  const closeShareModal = () => {
    setIsShareModalOpen(false);
    setShareStatus(null);
  };

  const buildShareDelayLabel = () => {
    if (!shareHasDelay) return '';

    const parsedValue = Number(shareDelayValue);
    if (!Number.isFinite(parsedValue) || parsedValue <= 0) return '';

    const expiresAt = new Date();
    if (shareDelayUnit === 'hours') {
      expiresAt.setHours(expiresAt.getHours() + parsedValue);
    } else {
      expiresAt.setDate(expiresAt.getDate() + parsedValue);
    }

    return `Valide jusqu'au ${expiresAt.toLocaleString('fr-FR')}`;
  };

  const handleSubmitShare = async () => {
    if (!shareTarget) return;

    const delayLabel = buildShareDelayLabel();
    if (shareHasDelay && !delayLabel) {
      setShareStatus({ type: 'error', message: 'Veuillez renseigner un délai valide (nombre strictement positif).' });
      return;
    }

    const delayValueNum = Number(shareDelayValue);

    if (shareMode === 'internal') {
      if (!shareInternalRecipient.trim()) {
        setShareStatus({ type: 'error', message: 'Veuillez renseigner un utilisateur, un service ou un groupe interne.' });
        return;
      }

      try {
        await shareDocument(shareTarget.id, {
          mode: 'internal',
          recipientName: shareInternalRecipient.trim(),
          recipientEmail: shareInternalRecipient.includes('@') ? shareInternalRecipient.trim() : undefined,
          permission: sharePermission,
          hasDelay: shareHasDelay,
          delayValue: shareHasDelay ? delayValueNum : undefined,
          delayUnit: shareHasDelay ? shareDelayUnit : undefined,
        });
        setShareStatus({
          type: 'success',
          message: `Le fichier « ${shareTarget.title} » a été partagé en interne avec « ${shareInternalRecipient.trim()} » (${sharePermission}).${delayLabel ? ` ${delayLabel}.` : ''}`,
        });
      } catch (err: any) {
        setShareStatus({ type: 'error', message: err?.response?.data?.message || 'Échec du partage interne.' });
      }
      return;
    }

    if (shareMode === 'recipient_administration') {
      const applicantEmail = shareApplicantEmail.trim();
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!shareRecipientAdministrationId) {
        setShareStatus({ type: 'error', message: 'Veuillez sélectionner une administration destinataire.' });
        return;
      }
      if (!shareApplicantFullName.trim() || !shareApplicantMatricule.trim() || !applicantEmail) {
        setShareStatus({ type: 'error', message: 'Nom et prénoms, matricule et email sont obligatoires.' });
        return;
      }
      if (!emailRegex.test(applicantEmail)) {
        setShareStatus({ type: 'error', message: 'Veuillez saisir une adresse email usager valide.' });
        return;
      }

      try {
        const selectedAdministration = recipientAdministrations.find((item) => item.id === shareRecipientAdministrationId);
        await shareDocument(shareTarget.id, {
          mode: 'recipient_administration',
          recipientAdministrationId: shareRecipientAdministrationId,
          applicantFullName: shareApplicantFullName.trim(),
          applicantMatricule: shareApplicantMatricule.trim(),
          applicantEmail,
          recipientName: selectedAdministration?.name,
          permission: sharePermission,
          hasDelay: shareHasDelay,
          delayValue: shareHasDelay ? delayValueNum : undefined,
          delayUnit: shareHasDelay ? shareDelayUnit : undefined,
        });
        setShareStatus({
          type: 'success',
          message: `Le document « ${shareTarget.title} » a été partagé à l'administration destinataire sélectionnée.${delayLabel ? ` ${delayLabel}.` : ''}`,
        });
      } catch (err: any) {
        setShareStatus({ type: 'error', message: err?.response?.data?.message || 'Échec du partage vers administration destinataire.' });
      }
      return;
    }

    const email = shareExternalEmail.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      setShareStatus({ type: 'error', message: 'Veuillez saisir une adresse email valide.' });
      return;
    }

    try {
      await shareDocument(shareTarget.id, {
        mode: 'external',
        recipientEmail: email,
        permission: sharePermission,
        hasDelay: shareHasDelay,
        delayValue: shareHasDelay ? delayValueNum : undefined,
        delayUnit: shareHasDelay ? shareDelayUnit : undefined,
      });
      setShareStatus({
        type: 'success',
        message: `Le lien de partage externe du fichier « ${shareTarget.title} » a été envoyé à ${email}.${delayLabel ? ` ${delayLabel}.` : ''}`,
      });
    } catch (err: any) {
      setShareStatus({ type: 'error', message: err?.response?.data?.message || 'Échec du partage externe.' });
    }
  };

  const handleFavorite = async (doc: DocumentItem) => {
    const shouldFavorite = !isFavoriteDocument(doc.id);
    try {
      const response = await updateDocumentFavoritePreference(doc.id, shouldFavorite);
      setFavoriteDocumentIds((prev) => (
        response.isFavorite
          ? Array.from(new Set([...prev, doc.id]))
          : prev.filter((id) => id !== doc.id)
      ));
    } catch (err) {
      console.error(err);
      setError('Impossible de mettre a jour les favoris.');
    }
    setOpenActionsId(null);
  };

  const handleDetails = (doc: DocumentItem) => {
    window.alert(`Détails : ${doc.title}\nCréé le: ${new Date(doc.createdAt).toLocaleString('fr-FR')}`);
    setOpenActionsId(null);
  };

  const handleLabels = async (doc: DocumentItem) => {
    const currentCodes = documentLabelCodes[doc.id] || [];
    const input = window.prompt(
      `Codes etiquette pour « ${doc.title} » (separes par des virgules)`,
      currentCodes.join(', '),
    );

    if (input === null) {
      setOpenActionsId(null);
      return;
    }

    const nextCodes = parseLabelCodes(input);
    try {
      const response = await updateDocumentLabelCodesPreference(doc.id, nextCodes);
      const persistedCodes = Array.isArray(response.labelCodes)
        ? response.labelCodes.map((code) => normalizeLabelCode(code)).filter(Boolean)
        : [];

      setDocumentLabelCodes((prev) => {
        const next = { ...prev };
        if (persistedCodes.length === 0) {
          delete next[doc.id];
          return next;
        }
        next[doc.id] = Array.from(new Set(persistedCodes));
        return next;
      });
    } catch (err) {
      console.error(err);
      setError('Impossible de mettre a jour les etiquettes.');
    }
    setOpenActionsId(null);
  };

  const handleReminder = (doc: DocumentItem) => {
    window.alert(`Rappel configuré pour « ${doc.title} »`);
    setOpenActionsId(null);
  };

  const handleEditLocally = (doc: DocumentItem) => {
    window.alert(`Ouverture locale de « ${doc.title} »`);
    setOpenActionsId(null);
  };

  const handleDownload = (doc: DocumentItem) => {
    window.alert(`Téléchargement de « ${doc.title} » en cours...`);
    setOpenActionsId(null);
  };

  const addDocumentLocally = (doc: DocumentItem) => {
    setDocuments((prev) => [doc, ...prev]);
  };

  const createBasicDocument = async (title: string, type: string, parentFolderName?: string) => {
    const newDoc = await createDocument({
      title,
      description: type === 'folder' ? '[folder]' : parentFolderName ? `Dossier: ${parentFolderName}` : undefined,
    });
    addDocumentLocally({ ...newDoc, type });
  };

  const handleUploadFiles = async (files: FileList | null) => {
    if (!files || files.length === 0) return;
    try {
      for (const file of Array.from(files)) {
        await createBasicDocument(file.name, 'file', activeFolderTab || undefined);
      }
    } catch (err) {
      console.error(err);
      setError('Impossible de téléverser les fichiers.');
    }
  };

  const handleUploadFolder = async (files: FileList | null) => {
    if (!files || files.length === 0) return;
    try {
      const folderNames = new Set<string>();
      for (const file of Array.from(files)) {
        const relativePath = (file as File & { webkitRelativePath?: string }).webkitRelativePath;
        if (relativePath) {
          const topFolder = relativePath.split('/')[0];
          if (topFolder) folderNames.add(topFolder);
        }
      }

      for (const folderName of folderNames) {
        await createBasicDocument(folderName, 'folder');
      }

      for (const file of Array.from(files)) {
        const relativePath = (file as File & { webkitRelativePath?: string }).webkitRelativePath;
        const topFolder = relativePath?.split('/')[0] || undefined;
        await createBasicDocument(file.name, 'file', topFolder);
      }
    } catch (err) {
      console.error(err);
      setError('Impossible de téléverser le dossier.');
    }
  };

  const handleMenuAction = async (action: string) => {
    try {
      if (action === 'upload-files') {
        fileInputRef.current?.click();
        return;
      }

      if (action === 'upload-folder') {
        folderInputRef.current?.click();
        return;
      }

      if (action === 'new-folder') {
        const folderName = window.prompt('Nom du dossier');
        if (!folderName || !folderName.trim()) return;
        await createBasicDocument(folderName.trim(), 'folder');
        return;
      }

      if (action === 'request-file') {
        await createBasicDocument(`Demande de fichier ${new Date().toLocaleDateString('fr-FR')}`, 'request', activeFolderTab || undefined);
        return;
      }

      if (action === 'new-doc') {
        await createBasicDocument('Nouveau document.docx', 'docx', activeFolderTab || undefined);
        return;
      }

      if (action === 'new-text') {
        await createBasicDocument('Nouveau fichier texte.txt', 'txt', activeFolderTab || undefined);
        return;
      }

      if (action === 'new-whiteboard') {
        await createBasicDocument('Nouveau tableau blanc', 'whiteboard', activeFolderTab || undefined);
        return;
      }

      if (action === 'new-sheet') {
        await createBasicDocument('Nouvelle feuille.xlsx', 'xlsx', activeFolderTab || undefined);
        return;
      }

      if (action === 'new-presentation') {
        await createBasicDocument('Nouvelle présentation.pptx', 'pptx', activeFolderTab || undefined);
        return;
      }

      if (action === 'new-pdf-form') {
        await createBasicDocument('Nouveau formulaire.pdf', 'pdf', activeFolderTab || undefined);
      }
    } catch (err) {
      console.error(err);
      setError('Impossible d\'exécuter cette action.');
    }
  };

  useEffect(() => {
    const listener = (event: Event) => {
      const customEvent = event as CustomEvent<{ action: string }>;
      if (customEvent.detail?.action) {
        void handleMenuAction(customEvent.detail.action);
      }
    };

    window.addEventListener('documents:new-action', listener as EventListener);
    return () => window.removeEventListener('documents:new-action', listener as EventListener);
  }, []);

  useEffect(() => {
    if (folderInputRef.current) {
      folderInputRef.current.setAttribute('webkitdirectory', '');
      folderInputRef.current.setAttribute('directory', '');
    }
  }, []);

  useEffect(() => {
    const handleOutsideClick = (event: globalThis.MouseEvent) => {
      const target = event.target as HTMLElement | null;
      if (target?.closest('[data-row-actions="true"]')) return;
      setOpenActionsId(null);
    };

    document.addEventListener('mousedown', handleOutsideClick);
    return () => document.removeEventListener('mousedown', handleOutsideClick);
  }, []);

  useEffect(() => {
    const handleKeyDown = (event: globalThis.KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpenActionsId(null);
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, []);

  const documentsInCurrentFolder = useMemo(() => {
    if (!activeFolderTab) {
      const foldersAtRoot = documents.filter((doc) => isFolder(doc));
      const filesAtRoot = documents.filter((doc) => !isFolder(doc) && !getParentFolderName(doc));
      return [...foldersAtRoot, ...filesAtRoot];
    }

    const filesInFolder = documents.filter(
      (doc) => !isFolder(doc) && getParentFolderName(doc) === activeFolderTab,
    );
    return filesInFolder;
  }, [documents, activeFolderTab]);

  const displayedDocuments = useMemo(() => {
    if (activeSubTab === 'favorites') {
      return documentsInCurrentFolder.filter((doc) => !isFolder(doc) && isFavoriteDocument(doc.id));
    }

    if (activeSubTab === 'labels') {
      const normalizedSearch = normalizeLabelCode(labelSearchCode);
      const labeledFiles = documentsInCurrentFolder.filter((doc) => !isFolder(doc) && (documentLabelCodes[doc.id] || []).length > 0);
      if (!normalizedSearch) {
        return labeledFiles;
      }
      return labeledFiles.filter((doc) => (documentLabelCodes[doc.id] || []).includes(normalizedSearch));
    }

    return documentsInCurrentFolder;
  }, [
    activeSubTab,
    documentsInCurrentFolder,
    favoriteDocumentIds,
    documentLabelCodes,
    labelSearchCode,
  ]);

  const formatModified = (date: string) => {
    return new Date(date).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    });
  };

  const getFileExtension = (doc: DocumentItem): string => {
    const fileName = String(doc.title || '').trim().toLowerCase();
    const index = fileName.lastIndexOf('.');
    if (index >= 0 && index < fileName.length - 1) {
      return fileName.slice(index + 1);
    }

    const mimeType = String((doc as any).mimeType || '').toLowerCase();
    if (mimeType.includes('wordprocessingml')) return 'docx';
    if (mimeType.includes('spreadsheetml')) return 'xlsx';
    if (mimeType.includes('presentationml')) return 'pptx';
    if (mimeType.includes('pdf')) return 'pdf';
    return '';
  };

  const getFileColorClass = (doc: DocumentItem): string => {
    if (isFolder(doc)) return 'text-blue-600';
    const extension = getFileExtension(doc);
    if (extension === 'docx') return 'text-blue-600';
    if (extension === 'xlsx') return 'text-green-600';
    if (extension === 'pptx') return 'text-red-400';
    if (extension === 'pdf') return 'text-orange-500';
    return 'text-gray-500';
  };

  return (
    <div className="space-y-6">
      <input
        ref={fileInputRef}
        type="file"
        className="hidden"
        multiple
        onChange={(e) => {
          void handleUploadFiles(e.target.files);
          e.currentTarget.value = '';
        }}
      />
      <input
        ref={folderInputRef}
        type="file"
        className="hidden"
        multiple
        onChange={(e) => {
          void handleUploadFolder(e.target.files);
          e.currentTarget.value = '';
        }}
      />

      {error && <div className="p-3 mb-4 text-red-700 bg-red-100 rounded">{error}</div>}

      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-3 flex flex-wrap items-center gap-2">
        <button
          onClick={() => setActiveFolderTab(null)}
          className={`px-3 py-1.5 rounded-lg text-xs font-semibold ${activeFolderTab === null ? 'bg-[#2453d6] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`}
        >
          Racine
        </button>
        {folderTabs.map((folderName) => (
          <button
            key={folderName}
            onClick={() => setActiveFolderTab(folderName)}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold ${activeFolderTab === folderName ? 'bg-[#2453d6] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`}
          >
            {folderName}
          </button>
        ))}
      </div>

      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-3 flex flex-wrap items-center gap-2">
        <button
          onClick={() => setActiveSubTab('all')}
          className={`px-3 py-1.5 rounded-lg text-xs font-semibold ${activeSubTab === 'all' ? 'bg-[#2453d6] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`}
        >
          Tous
        </button>
        <button
          onClick={() => setActiveSubTab('favorites')}
          className={`px-3 py-1.5 rounded-lg text-xs font-semibold ${activeSubTab === 'favorites' ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-700 hover:bg-amber-100'}`}
        >
          Favoris ({favoriteDocumentIds.length})
        </button>
        <button
          onClick={() => setActiveSubTab('labels')}
          className={`px-3 py-1.5 rounded-lg text-xs font-semibold ${activeSubTab === 'labels' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'}`}
        >
          Etiquettes
        </button>

        {activeSubTab === 'labels' && (
          <input
            type="text"
            value={labelSearchCode}
            onChange={(event) => setLabelSearchCode(event.target.value)}
            placeholder="Code etiquette (ex: ETQ-RH-001)"
            className="ml-auto w-full sm:w-72 border border-gray-300 rounded-lg px-3 py-1.5 text-xs"
          />
        )}
      </div>

      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm">
        {loading ? (
          <p className="p-6">Chargement...</p>
        ) : displayedDocuments.length === 0 ? (
          <p className="p-6 text-gray-500">
            {activeSubTab === 'favorites'
              ? 'Aucun fichier favori pour le moment.'
              : activeSubTab === 'labels'
                ? 'Aucun fichier etiquete ne correspond au code saisi.'
                : activeFolderTab
                  ? `Aucun fichier dans le dossier « ${activeFolderTab} ».`
                  : "Aucun dossier ni fichier pour l'instant."}
          </p>
        ) : (
          <table className="w-full text-sm">
            <thead className="border-b bg-gray-50/80">
              <tr>
                <th className="text-left py-3 px-4 font-semibold text-gray-700">Nom</th>
                <th className="text-left py-3 px-4 font-semibold text-gray-700">Type</th>
                <th className="text-left py-3 px-4 font-semibold text-gray-700">Modifié</th>
                <th className="text-left py-3 px-4 font-semibold text-gray-700">Personnes</th>
                <th className="text-left py-3 px-4 font-semibold text-gray-700">Actions</th>
              </tr>
            </thead>
            <tbody>
              {displayedDocuments.map((doc) => (
                <tr
                  key={doc.id}
                  className="border-b hover:bg-gray-50 cursor-pointer"
                  onDoubleClick={(event) => handleDocumentDoubleClick(doc, event)}
                  title={isFolder(doc) ? 'Double-cliquez pour ouvrir ce dossier dans un onglet' : 'Double-cliquez pour ouvrir ce fichier dans OnlyOffice'}
                >
                  <td className="py-3 px-4">
                    <div className="flex items-center gap-3">
                      {isFolder(doc) ? (
                        <Folder size={18} className="text-blue-600" />
                      ) : (
                        <FileText size={18} className={getFileColorClass(doc)} />
                      )}
                      <div className="min-w-0">
                        <span className="font-medium text-gray-800 block truncate">{doc.title}</span>
                        {!isFolder(doc) && (documentLabelCodes[doc.id] || []).length > 0 && (
                          <div className="mt-1 flex flex-wrap gap-1">
                            {(documentLabelCodes[doc.id] || []).slice(0, 3).map((code) => (
                              <span key={`${doc.id}-${code}`} className="inline-flex items-center rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 px-1.5 py-0.5 text-[10px] font-semibold">
                                {code}
                              </span>
                            ))}
                            {(documentLabelCodes[doc.id] || []).length > 3 && (
                              <span className="inline-flex items-center rounded-md bg-gray-50 text-gray-600 border border-gray-200 px-1.5 py-0.5 text-[10px] font-semibold">
                                +{(documentLabelCodes[doc.id] || []).length - 3}
                              </span>
                            )}
                          </div>
                        )}
                      </div>
                    </div>
                  </td>
                  <td className={`py-3 px-4 ${isFolder(doc) ? 'text-gray-600' : getFileColorClass(doc)}`}>
                    {isFolder(doc) ? 'Dossier' : 'Fichier'}
                  </td>
                  <td className="py-3 px-4 text-gray-600">{formatModified(doc.updatedAt || doc.createdAt)}</td>
                  <td className="py-3 px-4 text-gray-400">-</td>
                  <td className="py-3 px-4">
                    <div data-row-actions="true" className="relative flex items-center justify-end gap-1">
                      {/* Bouton partager / ajouter personne */}
                      <button
                        onClick={() => openShareModal(doc)}
                        title="Partager"
                        className="h-8 w-8 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 grid place-items-center transition"
                      >
                        <UserPlus size={16} />
                      </button>

                      {/* Bouton … */}
                      <button
                        onClick={() => setOpenActionsId((prev) => (prev === doc.id ? null : doc.id))}
                        title="Plus d'actions"
                        className="h-8 w-8 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 grid place-items-center transition"
                      >
                        <MoreHorizontal size={16} />
                      </button>

                      {openActionsId === doc.id && (
                        <div className="absolute right-0 top-10 w-56 bg-white border border-gray-200 rounded-2xl shadow-2xl z-50 py-2">
                          <button
                            className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                            onClick={() => void handleFavorite(doc)}
                          >
                            <Star size={15} className={isFavoriteDocument(doc.id) ? 'text-amber-500' : 'text-gray-400'} /> {isFavoriteDocument(doc.id) ? 'Retirer des favoris' : 'Ajouter aux favoris'}
                          </button>
                          <button
                            className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                            onClick={() => handleDetails(doc)}
                          >
                            <Info size={15} className="text-gray-400" /> Ouvrir les détails
                          </button>
                          <button
                            className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                            onClick={() => void handleLabels(doc)}
                          >
                            <Tag size={15} className="text-gray-400" /> Gérer les étiquettes
                          </button>

                          <hr className="my-1 border-gray-100" />

                          <button
                            className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                            onClick={() => void handleRename(doc)}
                          >
                            <Pencil size={15} className="text-gray-400" /> Renommer
                          </button>
                          <button
                            className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                            onClick={() => void handleMove(doc)}
                          >
                            <FolderInput size={15} className="text-gray-400" /> Déplacer ou copier
                          </button>
                          <button
                            className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                            onClick={() => handleReminder(doc)}
                          >
                            <Bell size={15} className="text-gray-400" /> Définir un rappel
                          </button>

                          <hr className="my-1 border-gray-100" />

                          <button
                            className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                            onClick={() => handleEditLocally(doc)}
                          >
                            <MonitorDown size={15} className="text-gray-400" /> Éditer localement
                          </button>
                          <button
                            className="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                            onClick={() => handleDownload(doc)}
                          >
                            <Download size={15} className="text-gray-400" /> Télécharger
                          </button>

                          <hr className="my-1 border-gray-100" />

                          <button
                            className="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition"
                            onClick={() => void handleDelete(doc.id)}
                          >
                            <Trash2 size={15} className="text-red-400" /> Supprimer
                          </button>
                        </div>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {isShareModalOpen && shareTarget && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
          <div className="bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-orange-300">
            <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
              <div>
                <h3 className="text-sm font-semibold text-gray-800">Partager le fichier</h3>
                <p className="text-xs text-gray-500 mt-0.5">{shareTarget.title}</p>
              </div>
              <button
                onClick={closeShareModal}
                className="h-8 w-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100"
                title="Fermer"
              >
                ×
              </button>
            </div>

            <div className="px-5 pt-4">
              <div className="inline-flex rounded-lg bg-white p-1 mb-4 gap-1 border border-gray-100">
                <button
                  onClick={() => { setShareMode('internal'); setShareStatus(null); }}
                  className={`px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors ${shareMode === 'internal' ? 'bg-gray-100 border-gray-300 text-gray-800 shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'}`}
                >
                  Partage interne
                </button>
                <button
                  onClick={() => { setShareMode('external'); setShareStatus(null); }}
                  className={`px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors ${shareMode === 'external' ? 'bg-blue-100 border-blue-300 text-blue-800 shadow-sm' : 'bg-blue-50 border-blue-200 text-blue-700 hover:bg-blue-100'}`}
                >
                  Partage externe (Email)
                </button>
                <button
                  onClick={() => { setShareMode('recipient_administration'); setShareStatus(null); }}
                  className={`px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors ${shareMode === 'recipient_administration' ? 'bg-green-100 border-green-300 text-green-800 shadow-sm' : 'bg-green-50 border-green-200 text-green-700 hover:bg-green-100'}`}
                >
                  Administration destinataire
                </button>
              </div>

              {shareMode === 'internal' ? (
                <div className="space-y-3">
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 mb-1">Utilisateur / Service interne</label>
                    <input
                      value={shareInternalRecipient}
                      onChange={(e) => setShareInternalRecipient(e.target.value)}
                      placeholder="Ex: Direction RH"
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 mb-1">Droits</label>
                    <select
                      value={sharePermission}
                      onChange={(e) => setSharePermission(e.target.value as 'lecture' | 'modification')}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    >
                      <option value="lecture">Lecture</option>
                      <option value="modification">Modification</option>
                    </select>
                  </div>
                </div>
              ) : shareMode === 'external' ? (
                <div className="space-y-3">
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 mb-1">Adresse Email externe</label>
                    <input
                      type="email"
                      value={shareExternalEmail}
                      onChange={(e) => setShareExternalEmail(e.target.value)}
                      placeholder="exemple@domaine.com"
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    />
                  </div>
                  <p className="text-xs text-gray-500">
                    Un lien sécurisé sera envoyé à cette adresse email pour l’accès au fichier.
                  </p>
                </div>
              ) : (
                <div className="space-y-3">
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 mb-1">Administration destinataire</label>
                    <select
                      value={shareRecipientAdministrationId}
                      onChange={(e) => setShareRecipientAdministrationId(e.target.value)}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    >
                      <option value="">Sélectionner une administration</option>
                      {recipientAdministrations.map((item) => (
                        <option key={item.id} value={item.id}>{item.name}</option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 mb-1">Nom et prénoms</label>
                    <input
                      value={shareApplicantFullName}
                      onChange={(e) => setShareApplicantFullName(e.target.value)}
                      placeholder="Ex: KOUADIO Jean Michel"
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 mb-1">Matricule</label>
                    <input
                      value={shareApplicantMatricule}
                      onChange={(e) => setShareApplicantMatricule(e.target.value)}
                      placeholder="Ex: MTR-2026-001"
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 mb-1">Email</label>
                    <input
                      type="email"
                      value={shareApplicantEmail}
                      onChange={(e) => setShareApplicantEmail(e.target.value)}
                      placeholder="exemple@domaine.com"
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    />
                  </div>
                </div>
              )}

              <div className="mt-4 pt-4 border-t border-gray-100 space-y-3">
                <label className="flex items-center gap-2 text-xs font-semibold text-gray-700">
                  <input
                    type="checkbox"
                    checked={shareHasDelay}
                    onChange={(e) => {
                      setShareHasDelay(e.target.checked);
                      setShareStatus(null);
                    }}
                  />
                  Définir un délai de validité du partage
                </label>

                {shareHasDelay && (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <input
                      type="number"
                      min={1}
                      value={shareDelayValue}
                      onChange={(e) => {
                        setShareDelayValue(e.target.value);
                        setShareStatus(null);
                      }}
                      placeholder="Ex: 24"
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    />
                    <select
                      value={shareDelayUnit}
                      onChange={(e) => {
                        setShareDelayUnit(e.target.value as 'hours' | 'days');
                        setShareStatus(null);
                      }}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    >
                      <option value="hours">Heures</option>
                      <option value="days">Jours</option>
                    </select>
                  </div>
                )}
              </div>

              {shareStatus && (
                <div className={`mt-4 rounded-lg px-3 py-2 text-xs ${shareStatus.type === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'}`}>
                  {shareStatus.message}
                </div>
              )}
            </div>

            <div className="px-5 py-4 mt-4 border-t border-gray-100 flex justify-end gap-2">
              <button
                onClick={closeShareModal}
                className="px-3 py-2 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"
              >
                Annuler
              </button>
              <button
                onClick={() => void handleSubmitShare()}
                className="px-3 py-2 text-xs font-semibold rounded-lg bg-[#2453d6] text-white hover:bg-[#1f47bb]"
              >
                Partager
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export default Documents;
