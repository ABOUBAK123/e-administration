import { create } from 'zustand'

export type SignatureZone = {
  id: string
  x: number
  y: number
  width: number
  height: number
}

interface SignatureFilesState {
  localFiles: File[]
  selectedLocalKeys: string[]
  zonesByFileKey: Record<string, SignatureZone[]>
  savedZoneByKey: Record<string, boolean>
  uploadedFilesByDocId: Record<string, File>

  setLocalFiles: (updater: File[] | ((prev: File[]) => File[])) => void
  setSelectedLocalKeys: (updater: string[] | ((prev: string[]) => string[])) => void
  setZonesByFileKey: (updater: Record<string, SignatureZone[]> | ((prev: Record<string, SignatureZone[]>) => Record<string, SignatureZone[]>)) => void
  setSavedZoneByKey: (updater: Record<string, boolean> | ((prev: Record<string, boolean>) => Record<string, boolean>)) => void
  setUploadedFilesByDocId: (updater: Record<string, File> | ((prev: Record<string, File>) => Record<string, File>)) => void
}

export const useSignatureFilesStore = create<SignatureFilesState>((set) => ({
  localFiles: [],
  selectedLocalKeys: [],
  zonesByFileKey: {},
  savedZoneByKey: {},
  uploadedFilesByDocId: {},

  setLocalFiles: (updater) =>
    set((state) => ({
      localFiles: typeof updater === 'function' ? updater(state.localFiles) : updater,
    })),

  setSelectedLocalKeys: (updater) =>
    set((state) => ({
      selectedLocalKeys: typeof updater === 'function' ? updater(state.selectedLocalKeys) : updater,
    })),

  setZonesByFileKey: (updater) =>
    set((state) => ({
      zonesByFileKey: typeof updater === 'function' ? updater(state.zonesByFileKey) : updater,
    })),

  setSavedZoneByKey: (updater) =>
    set((state) => ({
      savedZoneByKey: typeof updater === 'function' ? updater(state.savedZoneByKey) : updater,
    })),

  setUploadedFilesByDocId: (updater) =>
    set((state) => ({
      uploadedFilesByDocId: typeof updater === 'function' ? updater(state.uploadedFilesByDocId) : updater,
    })),
}))
