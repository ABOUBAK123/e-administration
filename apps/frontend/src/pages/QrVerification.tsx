import { FormEvent, useEffect, useMemo, useRef, useState } from 'react'
import { CheckCircle2, ShieldCheck, Search, Download, AlertTriangle } from 'lucide-react'
import { verifyDocumentNumber, PublicVerificationResult } from '../services/qrcode'

function QrVerification() {
  const [documentNumber, setDocumentNumber] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [result, setResult] = useState<PublicVerificationResult | null>(null)
  const autoOpenDoneRef = useRef(false)

  const runVerification = async (rawDocumentNumber: string) => {
    const trimmed = rawDocumentNumber.trim()
    if (!trimmed) {
      setError('Veuillez saisir un numero de document')
      return
    }

    setLoading(true)
    setError(null)
    setResult(null)

    try {
      const data = await verifyDocumentNumber(trimmed)
      setResult(data)
    } catch (err: any) {
      const message = err?.response?.data?.message || 'Verification impossible avec ce numero'
      setError(Array.isArray(message) ? message.join(', ') : message)
    } finally {
      setLoading(false)
    }
  }

  const signedAtLabel = useMemo(() => {
    if (!result?.signedAt) return 'Non renseigne'
    const parsed = new Date(result.signedAt)
    if (Number.isNaN(parsed.getTime())) return 'Non renseigne'
    return parsed.toLocaleString('fr-FR')
  }, [result])

  const onSubmit = async (e: FormEvent) => {
    e.preventDefault()
    await runVerification(documentNumber)
  }

  useEffect(() => {
    const params = new URLSearchParams(window.location.search)
    const fromQrScanNumber = String(params.get('documentNumber') || '').trim()
    if (!fromQrScanNumber) return

    setDocumentNumber(fromQrScanNumber)
    autoOpenDoneRef.current = false
    void runVerification(fromQrScanNumber)
  }, [])

  useEffect(() => {
    if (!result?.pdfUrl) return
    const params = new URLSearchParams(window.location.search)
    const shouldAutoOpen = params.get('autoOpen') === '1'
    if (!shouldAutoOpen) return
    if (autoOpenDoneRef.current) return

    autoOpenDoneRef.current = true
    window.open(result.pdfUrl, '_blank', 'noopener,noreferrer')
  }, [result])

  return (
    <div className="space-y-6">
      <section className="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <h3 className="text-xl font-bold text-gray-800">Verification d'authenticite</h3>
        <p className="text-gray-500 mt-1">
          Saisissez le numero codifie du document pour verifier la signature et telecharger le PDF signe.
        </p>

        <form className="mt-5 flex flex-col gap-3 md:flex-row" onSubmit={onSubmit}>
          <div className="flex-1">
            <label htmlFor="documentNumber" className="block text-sm text-gray-600 mb-1">
              Numero document
            </label>
            <input
              id="documentNumber"
              value={documentNumber}
              onChange={(e) => setDocumentNumber(e.target.value.toUpperCase())}
              placeholder="DGI-CONTROLE-0000001-2026"
              className="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#2453d6]"
            />
          </div>

          <button
            type="submit"
            disabled={loading}
            className="h-[46px] mt-auto px-5 rounded-xl bg-[#2453d6] hover:bg-[#1f47bb] disabled:opacity-60 text-white font-semibold inline-flex items-center justify-center gap-2"
          >
            <Search size={16} />
            {loading ? 'Verification...' : 'Verifier'}
          </button>
        </form>

        {error && (
          <div className="mt-4 rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 inline-flex items-center gap-2">
            <AlertTriangle size={16} /> {error}
          </div>
        )}
      </section>

      {result && (
        <section className="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center gap-2 text-green-700">
              <ShieldCheck size={20} />
              <span className="font-semibold">
                {result.authentic ? 'Document authentique verifie' : 'Document non valide'}
              </span>
            </div>
            <span className="text-sm bg-gray-100 text-gray-700 px-3 py-1 rounded-full">
              Statut: {result.status}
            </span>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
              <p className="text-gray-500">Numero</p>
              <p className="font-semibold text-gray-800">{result.documentNumber}</p>
            </div>
            <div>
              <p className="text-gray-500">Date de signature</p>
              <p className="font-semibold text-gray-800">{signedAtLabel}</p>
            </div>
            <div>
              <p className="text-gray-500">Administration emettrice</p>
              <p className="font-semibold text-gray-800">
                {result.issuingAdministration
                  ? `${result.issuingAdministration.name} (${result.issuingAdministration.code})`
                  : 'Non renseignee'}
              </p>
            </div>
            <div>
              <p className="text-gray-500">Entite sous tutelle</p>
              <p className="font-semibold text-gray-800">{result.subEntityCode || 'Non renseignee'}</p>
            </div>
          </div>

          <div className="rounded-xl border border-gray-200 p-4">
            <p className="text-gray-500 text-sm">Titre du document</p>
            <p className="font-semibold text-gray-900">{result.title}</p>
            {result.description && <p className="text-gray-600 mt-1">{result.description}</p>}
          </div>

          <div>
            <p className="text-sm font-semibold text-gray-700 mb-2">Signatures</p>
            <div className="space-y-2">
              {result.signatures.length === 0 && (
                <p className="text-sm text-gray-500">Aucune signature enregistree.</p>
              )}
              {result.signatures.map((signature) => (
                <div
                  key={signature.id}
                  className="rounded-lg border border-gray-200 px-3 py-2 text-sm flex items-center justify-between gap-2"
                >
                  <div>
                    <p className="font-medium text-gray-800">{signature.signerName}</p>
                    <p className="text-gray-500">{new Date(signature.timestamp).toLocaleString('fr-FR')}</p>
                  </div>
                  <span className="inline-flex items-center gap-1 text-green-700 bg-green-50 px-2 py-1 rounded-full">
                    <CheckCircle2 size={14} /> {signature.status}
                  </span>
                </div>
              ))}
            </div>
          </div>

          <a
            href={result.pdfUrl}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-[#173b9f] text-white font-semibold hover:bg-[#0f2b75]"
          >
            <Download size={16} /> Telecharger le PDF signe
          </a>
        </section>
      )}
    </div>
  )
}

export default QrVerification
