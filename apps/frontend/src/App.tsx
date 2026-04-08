import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom'
import { useEffect } from 'react'
import Layout from './components/layout/Layout'
import Dashboard from './pages/Dashboard'
import Documents from './pages/Documents'
import Workflows from './pages/Workflows'
import Signatures from './pages/Signatures'
import Profile from './pages/Profile'
import Settings from './pages/Settings'
import QrVerification from './pages/QrVerification'
import Reception from './pages/Reception'
import ActRequests from './pages/ActRequests'
import SharedTemplates from './pages/SharedTemplates'
import Login from './pages/Login'
import Register from './pages/Register'
import ForgotPassword from './pages/ForgotPassword'
import ResetPassword from './pages/ResetPassword'
import PublicActRequestsApp from './pages/PublicActRequestsApp'
import { useAuthStore } from './store/authStore'

const PrivateRoute = ({ children }: { children: JSX.Element }) => {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated)
  const isAuthResolved = useAuthStore((state) => state.isAuthResolved)
  if (!isAuthResolved) {
    return <div className="min-h-screen grid place-items-center text-sm text-gray-500">Initialisation de la session...</div>
  }
  return isAuthenticated ? children : <Navigate to="/login" />
}

function App() {
  const bootstrapAuth = useAuthStore((state) => state.bootstrapAuth)

  useEffect(() => {
    bootstrapAuth().catch(() => undefined)
  }, [bootstrapAuth])

  return (
    <Router future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/forgot-password" element={<ForgotPassword />} />
        <Route path="/reset-password" element={<ResetPassword />} />
        <Route path="/verify" element={<QrVerification />} />
        <Route path="demande-acte" element={<PublicActRequestsApp />} />
        <Route path="demande-acte/:emitterAdministrationId" element={<PublicActRequestsApp />} />
        <Route path="demande-acte/:emitterAdministrationId/acte/:requestedActId" element={<PublicActRequestsApp />} />
        <Route
          path="/*"
          element={
            <PrivateRoute>
              <Layout />
            </PrivateRoute>
          }
        >
          <Route path="" element={<Dashboard />} />
          <Route path="documents" element={<Documents />} />
          <Route path="workflows" element={<Workflows />} />
          <Route path="signatures" element={<Signatures />} />
          <Route path="reception" element={<Reception />} />
          <Route path="act-requests" element={<ActRequests />} />
          <Route path="templates-shared" element={<SharedTemplates />} />
          <Route path="profile" element={<Profile />} />
          <Route path="settings" element={<Settings />} />
          <Route path="qr-verification" element={<QrVerification />} />
        </Route>
      </Routes>
    </Router>
  )
}

export default App
