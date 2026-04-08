import api from './api'

export type PublicVerificationResult = {
  authentic: boolean
  documentNumber: string
  documentId: string
  title: string
  description?: string
  status: string
  signedAt?: string | null
  subEntityCode?: string | null
  issuingAdministration?: {
    id: string
    name: string
    code: string
  } | null
  signatures: Array<{
    id: string
    signerName: string
    signerEmail?: string | null
    timestamp: string
    isValid: boolean
    status: string
    reason?: string | null
    location?: string | null
  }>
  pdfUrl: string
  qrcodeVerificationCode?: string | null
}

export async function verifyDocumentNumber(documentNumber: string): Promise<PublicVerificationResult> {
  const response = await api.get(`/qrcode/public/verify/${encodeURIComponent(documentNumber)}`)
  return response.data
}
