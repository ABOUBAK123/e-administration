import { ChangeEvent, FormEvent, useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  fetchPublicEmitterAdministrations,
  fetchPublicRequestedActsByEmitter,
  PublicEmitterAdministration,
  PublicRequestedAct,
  submitPublicActRequest,
} from '../services/publicActRequests';

function PublicActRequestsApp() {
  const navigate = useNavigate();
  const { emitterAdministrationId = '', requestedActId = '' } = useParams();
  const [emitters, setEmitters] = useState<PublicEmitterAdministration[]>([]);
  const [acts, setActs] = useState<PublicRequestedAct[]>([]);
  const [loadingEmitters, setLoadingEmitters] = useState(false);
  const [loadingActs, setLoadingActs] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [applicantFullName, setApplicantFullName] = useState('');
  const [applicantEmail, setApplicantEmail] = useState('');
  const [applicantPhone, setApplicantPhone] = useState('');
  const [applicantFieldValues, setApplicantFieldValues] = useState<Record<string, string>>({});
  const [note, setNote] = useState('');
  const [attachments, setAttachments] = useState<Array<{ file: File; requiredDocumentLabel?: string }>>([]);
  const [selectedRequiredDocument, setSelectedRequiredDocument] = useState('');
  const [emittersSearchQuery, setEmittersSearchQuery] = useState('');
  const [actsSearchQuery, setActsSearchQuery] = useState('');

  useEffect(() => {
    const loadEmitters = async () => {
      setLoadingEmitters(true);
      setError(null);
      try {
        const data = await fetchPublicEmitterAdministrations();
        setEmitters(data);
      } catch (err: any) {
        setError(err?.response?.data?.message || 'Impossible de charger la liste des administrations.');
      } finally {
        setLoadingEmitters(false);
      }
    };

    void loadEmitters();
  }, []);

  useEffect(() => {
    const loadActs = async () => {
      if (!emitterAdministrationId) {
        setActs([]);
        return;
      }

      setLoadingActs(true);
      setError(null);
      try {
        const data = await fetchPublicRequestedActsByEmitter(emitterAdministrationId);
        setActs(data);
      } catch (err: any) {
        setError(err?.response?.data?.message || 'Impossible de charger les actes disponibles.');
      } finally {
        setLoadingActs(false);
      }
    };

    void loadActs();
  }, [emitterAdministrationId]);

  const selectedEmitter = useMemo(
    () => emitters.find((item) => item.id === emitterAdministrationId) || null,
    [emitters, emitterAdministrationId],
  );

  const selectedAct = useMemo(
    () => acts.find((item) => item.id === requestedActId) || null,
    [acts, requestedActId],
  );

  const filteredEmitters = useMemo(() => {
    const term = emittersSearchQuery.trim().toLowerCase();
    if (!term) return emitters;

    return emitters.filter((item) => {
      const searchable = [item.name, item.code]
        .map((value) => String(value || '').toLowerCase())
        .join(' ');
      return searchable.includes(term);
    });
  }, [emitters, emittersSearchQuery]);

  const filteredActs = useMemo(() => {
    const term = actsSearchQuery.trim().toLowerCase();
    if (!term) return acts;

    return acts.filter((item) => {
      const searchable = [
        item.documentName,
        item.directionLabel,
        item.directionCode,
        ...(Array.isArray(item.requiredDocuments) ? item.requiredDocuments : []),
      ]
        .map((value) => String(value || '').toLowerCase())
        .join(' ');

      return searchable.includes(term);
    });
  }, [acts, actsSearchQuery]);

  const resolveLogoUrl = (logoPath?: string | null) => {
    const raw = String(logoPath || '').trim();
    if (!raw) return '';
    if (raw.startsWith('http://') || raw.startsWith('https://')) return raw;
    const apiRoot = (import.meta.env.VITE_API_URL || 'http://localhost:3000/api/v1').replace(/\/api(?:\/v\d+)?\/?$/i, '');
    return `${apiRoot}${raw.startsWith('/') ? raw : `/${raw}`}`;
  };

  useEffect(() => {
    const requiredDocs = selectedAct?.requiredDocuments || [];
    setSelectedRequiredDocument(requiredDocs[0] || '');

    const configuredFields = Array.isArray(selectedAct?.applicantFields) ? selectedAct?.applicantFields : [];
    const nextValues = configuredFields.reduce((acc, field) => {
      const label = String(field?.label || '').trim();
      if (!label) return acc;
      acc[label] = '';
      return acc;
    }, {} as Record<string, string>);
    setApplicantFieldValues(nextValues);
  }, [selectedAct]);

  const resetForm = () => {
    setApplicantFullName('');
    setApplicantEmail('');
    setApplicantPhone('');
    setApplicantFieldValues((prev) => Object.keys(prev).reduce((acc, key) => {
      acc[key] = '';
      return acc;
    }, {} as Record<string, string>));
    setNote('');
    setAttachments([]);
    setSelectedRequiredDocument(selectedAct?.requiredDocuments?.[0] || '');
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!emitterAdministrationId || !requestedActId) {
      setError('Administration ou acte non valide.');
      return;
    }
    if (!applicantFullName.trim() || !applicantEmail.trim()) {
      setError('Veuillez renseigner votre nom complet et votre email.');
      return;
    }

    const missingCustomField = Object.entries(applicantFieldValues).find(([, value]) => !String(value || '').trim());
    if (missingCustomField) {
      setError(`Veuillez renseigner le champ "${missingCustomField[0]}".`);
      return;
    }

    if (attachments.length === 0) {
      setError('Veuillez ajouter au moins un fichier PDF avec son libelle de piece.');
      return;
    }

    const nonPdfAttachment = attachments.find((item) => {
      const mimeType = String(item.file?.type || '').toLowerCase();
      const name = String(item.file?.name || '').toLowerCase();
      return mimeType !== 'application/pdf' && !name.endsWith('.pdf');
    });
    if (nonPdfAttachment) {
      setError('Seuls les fichiers PDF sont autorises pour les pieces exigees.');
      return;
    }

    setSubmitting(true);
    setError(null);
    setSuccess(null);
    try {
      const response = await submitPublicActRequest({
        emitterAdministrationId,
        requestedActId,
        applicantFullName: applicantFullName.trim(),
        applicantEmail: applicantEmail.trim(),
        applicantPhone: applicantPhone.trim(),
        applicantFieldValues,
        note: note.trim(),
        attachments,
      });
      setSuccess(`${response.message} (Ref: ${response.requestId})`);
      resetForm();
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Impossible d\'envoyer votre demande.');
    } finally {
      setSubmitting(false);
    }
  };

  const isEmitterPage = !emitterAdministrationId;
  const isActsPage = !!emitterAdministrationId && !requestedActId;
  const isSubmitPage = !!emitterAdministrationId && !!requestedActId;
  const hasSelectableRequiredDocument = Boolean(String(selectedRequiredDocument || '').trim());

  const isPdfFile = (file: File) => {
    const mimeType = String(file.type || '').toLowerCase();
    const name = String(file.name || '').toLowerCase();
    return mimeType === 'application/pdf' || name.endsWith('.pdf');
  };

  const handleAddFiles = (event: ChangeEvent<HTMLInputElement>) => {
    const selectedFiles = Array.from(event.target.files || []);
    if (selectedFiles.length === 0) return;

    if (!hasSelectableRequiredDocument) {
      setError('Sélectionnez d\'abord une pièce exigée avant de parcourir vos fichiers.');
      event.target.value = '';
      return;
    }

    const invalidFile = selectedFiles.find((file) => !isPdfFile(file));
    if (invalidFile) {
      setError('Seuls les fichiers PDF sont autorises pour les pieces exigees.');
      event.target.value = '';
      return;
    }

    const normalizedLabel = String(selectedRequiredDocument || '').trim() || undefined;
    setAttachments((prev) => [
      ...prev,
      ...selectedFiles.map((file) => ({ file, requiredDocumentLabel: normalizedLabel })),
    ]);

    event.target.value = '';
  };

  const handleRemoveAttachment = (indexToRemove: number) => {
    setAttachments((prev) => prev.filter((_, index) => index !== indexToRemove));
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-cyan-50 via-sky-50 to-indigo-100 py-8 px-4">
      <div className="max-w-5xl mx-auto space-y-5">
        <section className="rounded-2xl border border-cyan-100 bg-gradient-to-r from-[#0ea5e9] via-[#2563eb] to-[#4f46e5] shadow-lg p-6 text-white">
          <h1 className="text-2xl font-bold">Application Demande d'actes</h1>
          <p className="text-sm text-blue-50 mt-2">
            Parcours en 3 pages: administrations, actes disponibles, puis formulaire de demande.
          </p>
        </section>

        {error && <div className="bg-red-50 border border-red-100 text-red-700 rounded-xl p-3 text-sm">{error}</div>}
        {success && <div className="bg-emerald-50 border border-emerald-100 text-emerald-700 rounded-xl p-3 text-sm">{success}</div>}

        {isEmitterPage && (
          <section className="bg-white/95 backdrop-blur rounded-2xl border border-cyan-100 shadow-sm p-6">
            <h2 className="text-base font-semibold text-gray-800 mb-3">1. Administrations émettrices</h2>
            <div className="mb-3">
              <input
                type="text"
                value={emittersSearchQuery}
                onChange={(event) => setEmittersSearchQuery(event.target.value)}
                placeholder="Rechercher une administration (nom, code, email)..."
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
              />
            </div>
            {loadingEmitters ? (
              <p className="text-sm text-gray-500">Chargement des administrations...</p>
            ) : emitters.length === 0 ? (
              <p className="text-sm text-gray-500">Aucune administration émettrice n'est disponible pour les demandes d'actes.</p>
            ) : filteredEmitters.length === 0 ? (
              <p className="text-sm text-gray-500">Aucune administration ne correspond à votre recherche.</p>
            ) : (
              <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                {filteredEmitters.map((item) => (
                  <button
                    key={item.id}
                    type="button"
                    onClick={() => navigate(`/demande-acte/${item.id}`)}
                    className="aspect-square rounded-xl border border-orange-300 bg-emerald-50 p-3 transition flex flex-col items-center justify-center text-center hover:bg-emerald-100 hover:border-orange-400"
                  >
                    <div className="h-12 w-12 rounded-lg border border-orange-200 bg-white overflow-hidden flex items-center justify-center shrink-0 mb-2">
                      {resolveLogoUrl(item.logo) ? (
                        <img
                          src={resolveLogoUrl(item.logo)}
                          alt={`Logo ${item.name}`}
                          className="h-full w-full object-contain"
                        />
                      ) : (
                        <span className="text-[10px] font-semibold text-orange-600">{String(item.code || '?').slice(0, 3).toUpperCase()}</span>
                      )}
                    </div>
                    <p className="text-xs font-semibold text-gray-800 line-clamp-3">{item.name}</p>
                    <p className="text-[11px] text-orange-700 mt-1">{item.code}</p>
                  </button>
                ))}
              </div>
            )}
          </section>
        )}

        {isActsPage && (
          <section className="bg-white/95 backdrop-blur rounded-2xl border border-indigo-100 shadow-sm p-6">
            <div className="flex items-center justify-between gap-3 mb-3">
              <h2 className="text-base font-semibold text-gray-800">
                2. Actes fournis {selectedEmitter ? `par ${selectedEmitter.name}` : ''}
              </h2>
              <button
                type="button"
                onClick={() => navigate('/demande-acte')}
                className="text-xs rounded-md border border-green-200 bg-green-100 text-green-800 px-3 py-1.5 hover:bg-green-200"
              >
                Retour administrations
              </button>
            </div>
            <div className="mb-3">
              <input
                type="text"
                value={actsSearchQuery}
                onChange={(event) => setActsSearchQuery(event.target.value)}
                placeholder="Rechercher un acte (nom, direction, code, pièce exigée)..."
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
              />
            </div>
            {loadingActs ? (
              <p className="text-sm text-gray-500">Chargement des actes...</p>
            ) : acts.length === 0 ? (
              <p className="text-sm text-gray-500">Aucun acte disponible pour cette administration.</p>
            ) : filteredActs.length === 0 ? (
              <p className="text-sm text-gray-500">Aucun acte ne correspond à votre recherche.</p>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                {filteredActs.map((item) => {
                  const requiredDocuments = item.requiredDocuments || [];
                  const previewDocuments = requiredDocuments.slice(0, 2);
                  const remainingCount = requiredDocuments.length - previewDocuments.length;

                  return (
                    <div key={item.id} className="rounded-lg border border-indigo-100 bg-gradient-to-r from-indigo-50 to-sky-50 p-3 h-full flex flex-col">
                      <p className="text-sm font-semibold text-gray-800 line-clamp-2">{item.documentName}</p>
                      <p className="text-xs text-indigo-700 mt-1 line-clamp-2">
                        Direction: {item.directionLabel} ({item.directionCode})
                      </p>
                      <div className="mt-2 flex-1">
                        <p className="text-xs font-semibold text-gray-700">Pièces exigées:</p>
                        <ul className="mt-1 list-disc pl-5 text-xs text-gray-600 space-y-0.5">
                          {previewDocuments.map((doc) => (
                            <li key={`${item.id}-${doc}`} className="line-clamp-1">{doc}</li>
                          ))}
                          {requiredDocuments.length === 0 && <li>Aucune pièce configurée.</li>}
                        </ul>
                        {remainingCount > 0 && (
                          <p className="text-[11px] text-indigo-700 mt-1">+ {remainingCount} autre(s) pièce(s)</p>
                        )}
                      </div>
                      <div className="mt-3">
                        <button
                          type="button"
                          onClick={() => navigate(`/demande-acte/${emitterAdministrationId}/acte/${item.id}`)}
                          className="text-xs rounded-md bg-orange-500 hover:bg-orange-600 text-white px-3 py-2 w-full"
                        >
                          Continuer vers le formulaire
                        </button>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </section>
        )}

        {isSubmitPage && (
          <section className="bg-white/95 backdrop-blur rounded-2xl border border-emerald-100 shadow-sm p-6">
            <div className="flex items-center justify-between gap-3 mb-3">
              <h2 className="text-base font-semibold text-gray-800">
                3. Formulaire de demande {selectedAct ? `- ${selectedAct.documentName}` : ''}
              </h2>
              <button
                type="button"
                onClick={() => navigate(`/demande-acte/${emitterAdministrationId}`)}
                className="text-xs rounded-md border border-orange-200 bg-orange-100 text-orange-800 px-3 py-1.5 hover:bg-orange-200"
              >
                Retour actes
              </button>
            </div>

            {!selectedAct ? (
              <p className="text-sm text-gray-500">Acte introuvable pour cette administration.</p>
            ) : (
              <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">Nom complet *</label>
                    <input
                      type="text"
                      value={applicantFullName}
                      onChange={(event) => setApplicantFullName(event.target.value)}
                      className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                      placeholder="Votre nom et prénom"
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">Email *</label>
                    <input
                      type="email"
                      value={applicantEmail}
                      onChange={(event) => setApplicantEmail(event.target.value)}
                      className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                      placeholder="votre.email@exemple.com"
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">Téléphone</label>
                    <input
                      type="text"
                      value={applicantPhone}
                      onChange={(event) => setApplicantPhone(event.target.value)}
                      className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                      placeholder="Votre téléphone"
                    />
                  </div>
                </div>

                {Array.isArray(selectedAct.applicantFields) && selectedAct.applicantFields.length > 0 && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    {selectedAct.applicantFields.map((field) => {
                      const label = String(field?.label || '').trim();
                      if (!label) return null;
                      const inputType = String(field?.inputType || 'text').trim().toLowerCase();
                      const value = applicantFieldValues[label] || '';

                      if (inputType === 'textarea') {
                        return (
                          <div key={label} className="md:col-span-2">
                            <label className="block text-xs font-medium text-gray-700 mb-1">{label} *</label>
                            <textarea
                              value={value}
                              onChange={(event) => setApplicantFieldValues((prev) => ({ ...prev, [label]: event.target.value }))}
                              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm min-h-[88px]"
                              required
                            />
                          </div>
                        );
                      }

                      const htmlInputType = inputType === 'phone'
                        ? 'tel'
                        : inputType === 'number'
                          ? 'number'
                          : inputType === 'date'
                            ? 'date'
                            : inputType === 'email'
                              ? 'email'
                              : 'text';

                      return (
                        <div key={label}>
                          <label className="block text-xs font-medium text-gray-700 mb-1">{label} *</label>
                          <input
                            type={htmlInputType}
                            value={value}
                            onChange={(event) => setApplicantFieldValues((prev) => ({ ...prev, [label]: event.target.value }))}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            required
                          />
                        </div>
                      );
                    })}
                  </div>
                )}

                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1">Note</label>
                  <textarea
                    value={note}
                    onChange={(event) => setNote(event.target.value)}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm min-h-[88px]"
                    placeholder="Informations complémentaires"
                  />
                </div>

                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1">Pièces jointes</label>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <select
                      value={selectedRequiredDocument}
                      onChange={(event) => setSelectedRequiredDocument(event.target.value)}
                      className="w-full h-10 rounded-lg border border-gray-300 px-3 text-sm bg-white"
                      disabled={(selectedAct?.requiredDocuments || []).length === 0}
                    >
                      {(selectedAct?.requiredDocuments || []).length === 0 && (
                        <option value="">Aucune pièce exigée configurée</option>
                      )}
                      {(selectedAct?.requiredDocuments || []).map((doc) => (
                        <option key={doc} value={doc}>{doc}</option>
                      ))}
                    </select>
                    <div className="flex flex-col items-start gap-1 w-full">
                      <input
                        id="public-act-request-file-upload"
                        type="file"
                        multiple
                        accept="application/pdf,.pdf"
                        onChange={handleAddFiles}
                        disabled={!hasSelectableRequiredDocument}
                        className="sr-only"
                      />
                      <label
                        htmlFor="public-act-request-file-upload"
                        className={`w-48 h-10 rounded-lg border px-3 text-sm text-center cursor-pointer flex items-center justify-center ${
                          hasSelectableRequiredDocument
                            ? 'border-gray-400 bg-gray-400 text-white hover:bg-gray-500'
                            : 'border-gray-200 bg-gray-200 text-gray-100 cursor-not-allowed pointer-events-none'
                        }`}
                      >
                        Parcourir
                      </label>
                      <span className="text-xs font-medium text-red-600">PDF uniquement</span>
                    </div>
                  </div>
                  {!hasSelectableRequiredDocument && (
                    <p className="mt-1 text-xs text-red-600">
                      Choisissez une pièce dans la liste avant d\'ajouter un fichier.
                    </p>
                  )}
                  {selectedRequiredDocument && (
                    <p className="mt-1 text-xs text-gray-600">Pièce sélectionnée: {selectedRequiredDocument}</p>
                  )}
                  {attachments.length > 0 && (
                    <>
                      <p className="mt-1 text-xs text-gray-600">{attachments.length} fichier(s) associé(s)</p>
                      <div className="mt-2 rounded-lg border border-gray-200 divide-y divide-gray-100 overflow-hidden">
                        {attachments.map((item, index) => (
                          <div key={`${item.file.name}-${item.file.size}-${index}`} className="px-3 py-2 flex items-center justify-between gap-3">
                            <div className="min-w-0">
                              <p className="text-xs font-medium text-gray-800 truncate">{item.file.name}</p>
                              <p className="text-[11px] text-gray-500 mt-0.5">
                                Libellé: {item.requiredDocumentLabel || 'Non renseigné'}
                              </p>
                            </div>
                            <button
                              type="button"
                              onClick={() => handleRemoveAttachment(index)}
                              className="text-[11px] rounded border border-orange-200 text-orange-700 px-2 py-1 hover:bg-orange-50"
                            >
                              Retirer
                            </button>
                          </div>
                        ))}
                      </div>
                    </>
                  )}
                </div>

                <div className="pt-1">
                  <button
                    type="submit"
                    disabled={submitting}
                    className="rounded-lg bg-orange-500 hover:bg-orange-600 disabled:opacity-70 text-white px-4 py-2 text-sm"
                  >
                    {submitting ? 'Envoi en cours...' : 'Envoyer la demande'}
                  </button>
                </div>
              </form>
            )}
          </section>
        )}
      </div>
    </div>
  );
}

export default PublicActRequestsApp;
