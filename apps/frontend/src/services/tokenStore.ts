const ACCESS_TOKEN_KEY = 'ep_access_token'
const REFRESH_TOKEN_KEY = 'ep_refresh_token'

const readFromStorage = (key: string): string | null => {
  try {
    return localStorage.getItem(key)
  } catch {
    return null
  }
}

const writeToStorage = (key: string, value: string | null) => {
  try {
    if (value) {
      localStorage.setItem(key, value)
    } else {
      localStorage.removeItem(key)
    }
  } catch {
    // Ignore storage failures and keep in-memory fallback
  }
}

let accessToken: string | null = readFromStorage(ACCESS_TOKEN_KEY)
let refreshToken: string | null = readFromStorage(REFRESH_TOKEN_KEY)

export const tokenStore = {
  getAccessToken: () => accessToken,
  getRefreshToken: () => refreshToken,
  setTokens: (next: { accessToken?: string | null; refreshToken?: string | null }) => {
    if (typeof next.accessToken !== 'undefined') {
      accessToken = next.accessToken
      writeToStorage(ACCESS_TOKEN_KEY, accessToken)
    }
    if (typeof next.refreshToken !== 'undefined') {
      refreshToken = next.refreshToken
      writeToStorage(REFRESH_TOKEN_KEY, refreshToken)
    }
  },
  clear: () => {
    accessToken = null
    refreshToken = null
    writeToStorage(ACCESS_TOKEN_KEY, null)
    writeToStorage(REFRESH_TOKEN_KEY, null)
  },
}
