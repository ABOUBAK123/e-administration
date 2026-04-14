import { FormEvent, useEffect, useMemo, useState } from 'react'
import { jsPDF } from 'jspdf'
import {
  fetchAppSettings,
  fetchTemplates,
  fetchTemplateVariables,
  generateTemplateDocument,
} from '../services/administration'
import { uploadDocumentFile } from '../services/documents'
import { DocumentTemplate, TemplateVariable } from '../types/administration'
import { useAuthStore } from '../store/authStore'

const TEMPLATE_SHARE_MAP_SETTING_KEY = 'template_share_map'

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

const slugify = (text: string): string =>
  text
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[']/g, '_')
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')

const buildPdfFromText = (content: string): Blob => {
  const doc = new jsPDF({ unit: 'pt', format: 'a4' })
  const pageWidth = doc.internal.pageSize.getWidth()
  const pageHeight = doc.internal.pageSize.getHeight()
  const margin = 40
  const lineHeight = 16
  const maxWidth = pageWidth - margin * 2

  doc.setFont('helvetica', 'normal')
  doc.setFontSize(11)

  const lines = doc.splitTextToSize(content || '', maxWidth) as string[]
  let cursorY = margin

  lines.forEach((line) => {
    if (cursorY > pageHeight - margin) {
      doc.addPage()
      cursorY = margin
    }
    doc.text(line, margin, cursorY)
    cursorY += lineHeight
  })

  return doc.output('blob')
}

function SharedTemplates() {
  const user = useAuthStore((state) => state.user)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null)
  const [templates, setTemplates] = useState<DocumentTemplate[]>([])
  const [sharedTemplateIds, setSharedTemplateIds] = useState<string[]>([])
  const [selectedTemplateId, setSelectedTemplateId] = useState<string | null>(null)
  const [templateVariables, setTemplateVariables] = useState<TemplateVariable[]>([])
  const [generationValues, setGenerationValues] = useState<Record<string, string>>({})
  const [submitting, setSubmitting] = useState(false)
  const [generatedContent, setGeneratedContent] = useState('')
  const [generatedFileName, setGeneratedFileName] = useState('')

  const selectedTemplate = useMemo(
    () => templates.find((item) => item.id === selectedTemplateId) || null,
    [templates, selectedTemplateId],
  )

  const sharedTemplates = useMemo(
    () => templates.filter((item) => sharedTemplateIds.includes(item.id)),
    [templates, sharedTemplateIds],
  )

  const templateContentVariableKeys = useMemo(() => {
    const content = selectedTemplate?.content || ''
    if (!content) return [] as string[]
    const matches = Array.from(content.matchAll(/\{\{\s*([^}]+?)\s*\}\}/g)).map((match) => slugify(match[1]))
    return Array.from(new Set(matches))
  }, [selectedTemplate?.content])

  const generationFields = useMemo(() => {
    const byKey = new Map<string, {
      key: string
      label: string
      fieldType: 'text' | 'date' | 'number' | 'select' | 'textarea'
      placeholder?: string
      defaultValue?: string
      required?: boolean
    }>()

    templateVariables.forEach((variable) => {
      byKey.set(variable.key, {
        key: variable.key,
        label: variable.label || variable.key,
        fieldType: variable.fieldType,
        placeholder: variable.placeholder || undefined,
        defaultValue: variable.defaultValue || undefined,
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

  useEffect(() => {
    const load = async () => {
      setLoading(true)
      setError(null)
      try {
        const [templatesData, appSettingsData] = await Promise.all([
          fetchTemplates(),
          fetchAppSettings(),
        ])

        const settingMap = new Map(appSettingsData.map((entry) => [entry.key, entry.value]))
        const shareMap = parseTemplateShareMap(settingMap.get(TEMPLATE_SHARE_MAP_SETTING_KEY))
        const userId = user?.id || ''
        const ids = Object.entries(shareMap)
          .filter(([, users]) => users.includes(userId))
          .map(([templateId]) => templateId)

        setTemplates(templatesData)
        setSharedTemplateIds(ids)

        if (ids.length > 0) {
          setSelectedTemplateId((prev) => (prev && ids.includes(prev) ? prev : ids[0]))
        } else {
          setSelectedTemplateId(null)
        }
      } catch (err: any) {
        setError(err?.response?.data?.message || 'Impossible de charger les templates partagés.')
      } finally {
        setLoading(false)
      }
    }

    void load()
  }, [user?.id])

  useEffect(() => {
    if (!selectedTemplateId) {
      setTemplateVariables([])
      setGenerationValues({})
      return
    }

    const loadVariables = async () => {
      try {
        const variables = await fetchTemplateVariables(selectedTemplateId)
        setTemplateVariables(variables)
      } catch {
        setTemplateVariables([])
      }
    }

    void loadVariables()
  }, [selectedTemplateId])

  const handleGenerate = async (event: FormEvent) => {
    event.preventDefault()
    if (!selectedTemplateId || !selectedTemplate) {
      setFeedback({ type: 'error', message: 'Sélectionnez un template partagé.' })
      return
    }

    const missingFields = generationFields.filter((field) => !String(generationValues[field.key] || '').trim())
    if (missingFields.length > 0) {
      const missingLabels = missingFields.map((field) => field.label || field.key).join(', ')
      setFeedback({ type: 'error', message: `Veuillez renseigner tous les champs obligatoires: ${missingLabels}.` })
      return
    }

    setSubmitting(true)
    setFeedback(null)
    try {
      const outputName = selectedTemplate.fileName
        ? `${selectedTemplate.fileName.replace(/\.[^.]+$/, '')}-genere.pdf`
        : 'document-genere.pdf'

      const generated = await generateTemplateDocument(selectedTemplateId, {
        values: generationValues,
        outputFileName: outputName,
        requireAllFields: true,
        outputFormat: 'pdf',
      })

      setGeneratedContent(generated.generatedContent)
      setGeneratedFileName(generated.fileName)
      const pdfBlob = buildPdfFromText(generated.generatedContent)
      const generatedFile = new File([pdfBlob], generated.fileName, { type: 'application/pdf' })
      await uploadDocumentFile(generatedFile, {
        generatedFromSharedTemplate: true,
        subEntityCode: user?.subEntityCode || undefined,
        title: generated.fileName.replace(/\.[^.]+$/, ''),
      })

      setFeedback({ type: 'success', message: `Document généré: ${generated.fileName}` })
    } catch (err: any) {
      setFeedback({ type: 'error', message: err?.response?.data?.message || 'Impossible de générer le document.' })
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="space-y-5">
      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h2 className="text-lg font-semibold text-gray-800">Templates partagés</h2>
        <p className="text-xs text-gray-500 mt-1">Les templates mis à votre disposition par l'administrateur.</p>
      </div>

      {loading && <div className="bg-blue-50 border border-blue-100 text-blue-700 rounded-xl p-3 text-xs">Chargement...</div>}
      {error && <div className="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 text-xs">{error}</div>}

      {!loading && !error && (
        <div className="space-y-5">
          <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3">
            <h3 className="text-sm font-semibold text-gray-800">Liste des templates partagés</h3>
            {sharedTemplates.length === 0 ? (
              <div className="text-xs text-gray-500 bg-gray-50 border border-gray-100 rounded-lg p-3">
                Aucun template partagé pour votre compte.
              </div>
            ) : (
              <div className="space-y-3">
                {sharedTemplates.map((item) => (
                  <div
                    key={item.id}
                    className={`border rounded-xl p-4 transition ${
                      selectedTemplateId === item.id ? 'border-[#2453d6] bg-blue-50' : 'border-gray-200 bg-gray-50'
                    }`}
                  >
                    <p className="text-sm font-semibold text-gray-800">{item.name}</p>
                    <p className="text-xs text-gray-500 mt-1">{item.fileName} · {item.fileType.toUpperCase()}</p>
                    <div className="mt-3 flex flex-wrap gap-2">
                      <button
                        type="button"
                        onClick={() => {
                          setSelectedTemplateId(item.id)
                          setFeedback(null)
                        }}
                        className="px-3 py-1.5 rounded-lg bg-green-100 text-green-700 text-xs font-semibold hover:bg-green-200"
                      >
                        Formulaire
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </section>

          <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
            <h3 className="text-sm font-semibold text-gray-800">Formulaire</h3>
            <p className="text-xs text-gray-600">Template sélectionné: <span className="font-semibold">{selectedTemplate?.name || 'Aucun'}</span></p>

            {feedback && (
              <div className={`rounded-lg px-3 py-2 text-xs ${feedback.type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'}`}>
                {feedback.message}
              </div>
            )}

            <form onSubmit={handleGenerate} className="space-y-3">
              {generationFields.length === 0 && (
                <p className="text-xs text-gray-500">Aucune variable détectée pour ce template.</p>
              )}

              {generationFields.map((field) => (
                <div key={field.key} className="space-y-1">
                  <label className="text-xs font-medium text-gray-700">{field.label} ({field.key})</label>
                  {field.fieldType === 'textarea' ? (
                    <textarea
                      value={generationValues[field.key] || ''}
                      onChange={(e) => setGenerationValues((prev) => ({ ...prev, [field.key]: e.target.value }))}
                      className="w-full border rounded-lg px-3 py-2 text-xs min-h-[90px]"
                      placeholder={field.placeholder || ''}
                      required
                    />
                  ) : (
                    <input
                      type={field.fieldType === 'number' ? 'number' : field.fieldType === 'date' ? 'date' : 'text'}
                      value={generationValues[field.key] || ''}
                      onChange={(e) => setGenerationValues((prev) => ({ ...prev, [field.key]: e.target.value }))}
                      className="w-full border rounded-lg px-3 py-2 text-xs"
                      placeholder={field.placeholder || ''}
                      required
                    />
                  )}
                </div>
              ))}

              <button
                type="submit"
                disabled={!selectedTemplateId || submitting}
                className="bg-[#2453d6] text-white rounded-lg px-3 py-2 text-xs font-semibold disabled:opacity-40"
              >
                {submitting ? 'Génération...' : 'Générer et enregistrer le document'}
              </button>
            </form>

            {generatedContent && (
              <div className="border border-gray-200 rounded-lg p-3 bg-gray-50">
                <p className="text-xs font-semibold text-gray-700">Aperçu généré ({generatedFileName || 'document'})</p>
                <pre className="mt-2 text-[11px] text-gray-700 whitespace-pre-wrap max-h-56 overflow-auto">{generatedContent}</pre>
              </div>
            )}
          </section>
        </div>
      )}
    </div>
  )
}

export default SharedTemplates
