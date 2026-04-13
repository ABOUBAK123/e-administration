import { useCallback, useEffect, useRef, useState } from 'react'

/** Durée d'inactivité avant affichage de l'avertissement (30 min) */
const INACTIVITY_TIMEOUT_MS = 30 * 60 * 1000

/** Durée du compte à rebours avant déconnexion automatique (2 min) */
const WARNING_COUNTDOWN_S = 2 * 60

const ACTIVITY_EVENTS: (keyof WindowEventMap)[] = [
  'mousemove',
  'mousedown',
  'keydown',
  'scroll',
  'touchstart',
  'click',
]

interface UseInactivityLogoutOptions {
  /** Callback appelé lors de la déconnexion pour inactivité */
  onLogout: () => void
  /** Désactiver la détection (ex : si l'utilisateur n'est pas connecté) */
  enabled?: boolean
}

interface UseInactivityLogoutReturn {
  /** Afficher le modal d'avertissement */
  showWarning: boolean
  /** Secondes restantes avant déconnexion automatique */
  countdown: number
  /** Réinitialiser le timer (bouton "Rester connecté") */
  stayLoggedIn: () => void
}

export function useInactivityLogout({
  onLogout,
  enabled = true,
}: UseInactivityLogoutOptions): UseInactivityLogoutReturn {
  const [showWarning, setShowWarning] = useState(false)
  const [countdown, setCountdown] = useState(WARNING_COUNTDOWN_S)

  const inactivityTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const countdownIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null)

  const clearTimers = useCallback(() => {
    if (inactivityTimerRef.current) {
      clearTimeout(inactivityTimerRef.current)
      inactivityTimerRef.current = null
    }
    if (countdownIntervalRef.current) {
      clearInterval(countdownIntervalRef.current)
      countdownIntervalRef.current = null
    }
  }, [])

  const startCountdown = useCallback(() => {
    setShowWarning(true)
    setCountdown(WARNING_COUNTDOWN_S)

    countdownIntervalRef.current = setInterval(() => {
      setCountdown((prev) => {
        if (prev <= 1) {
          clearTimers()
          setShowWarning(false)
          onLogout()
          return 0
        }
        return prev - 1
      })
    }, 1000)
  }, [clearTimers, onLogout])

  const resetTimer = useCallback(() => {
    if (!enabled) return
    clearTimers()
    setShowWarning(false)
    setCountdown(WARNING_COUNTDOWN_S)

    inactivityTimerRef.current = setTimeout(() => {
      startCountdown()
    }, INACTIVITY_TIMEOUT_MS)
  }, [enabled, clearTimers, startCountdown])

  const stayLoggedIn = useCallback(() => {
    resetTimer()
  }, [resetTimer])

  useEffect(() => {
    if (!enabled) return

    resetTimer()

    const handleActivity = () => {
      // Si le modal n'est pas affiché, on remet le timer à zéro à chaque activité
      if (!showWarning) {
        resetTimer()
      }
    }

    ACTIVITY_EVENTS.forEach((event) => window.addEventListener(event, handleActivity, { passive: true }))

    return () => {
      clearTimers()
      ACTIVITY_EVENTS.forEach((event) => window.removeEventListener(event, handleActivity))
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enabled])

  // Synchroniser handleActivity avec showWarning sans recréer les listeners
  const showWarningRef = useRef(showWarning)
  useEffect(() => {
    showWarningRef.current = showWarning
  }, [showWarning])

  // Réattache les listeners avec la bonne référence à showWarning
  useEffect(() => {
    if (!enabled) return

    const handleActivity = () => {
      if (!showWarningRef.current) {
        resetTimer()
      }
    }

    ACTIVITY_EVENTS.forEach((event) => window.addEventListener(event, handleActivity, { passive: true }))
    return () => {
      ACTIVITY_EVENTS.forEach((event) => window.removeEventListener(event, handleActivity))
    }
  }, [enabled, resetTimer])

  return { showWarning, countdown, stayLoggedIn }
}
