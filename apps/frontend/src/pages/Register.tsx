import React, { useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useAuthStore } from '../store/authStore';
import { registerInvited } from '../services/auth';

function Register() {
  const [email, setEmail] = useState('');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [searchParams] = useSearchParams();
  const inviteMode = searchParams.get('invite') === '1';
  const [fullName, setFullName] = useState(() => {
    const prenoms = searchParams.get('prenoms') || '';
    const nom = searchParams.get('nom') || '';
    return `${prenoms} ${nom}`.trim();
  });
  const invitedRole = useMemo(() => searchParams.get('role') || 'user', [searchParams]);
  const invitedEmail = useMemo(() => searchParams.get('email') || '', [searchParams]);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const register = useAuthStore((state) => state.register);
  const navigate = useNavigate();

  React.useEffect(() => {
    if (invitedEmail) {
      setEmail(invitedEmail);
    }
  }, [invitedEmail]);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setError(null);
    setSuccess(null);
    setLoading(true);
    try {
      if (inviteMode) {
        await registerInvited({
          email,
          username,
          password,
          fullName,
          role: invitedRole,
        });
        setSuccess('Votre formulaire a été envoyé. Votre compte reste désactivé jusqu’à activation par l’administrateur.');
        window.setTimeout(() => navigate('/login'), 2500);
      } else {
        await register(email, username, password, fullName);
        navigate('/documents');
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Impossible de créer un compte.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-md mx-auto mt-20 p-8 bg-white shadow-lg rounded-lg">
      <h2 className="text-2xl font-semibold mb-6">{inviteMode ? 'Formulaire invité' : 'Inscription'}</h2>
      {inviteMode && (
        <div className="mb-4 p-3 bg-blue-100 text-blue-700 rounded text-sm">
          Vous avez été invité. Votre compte sera créé en statut désactivé et devra être activé par l’administrateur.
        </div>
      )}
      {error && <div className="mb-4 p-3 bg-red-100 text-red-700 rounded">{error}</div>}
      {success && <div className="mb-4 p-3 bg-green-100 text-green-700 rounded">{success}</div>}
      <form onSubmit={handleSubmit}>
        <input
          type="email"
          className="w-full mb-3 px-3 py-2 border rounded"
          placeholder="Email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          readOnly={Boolean(invitedEmail)}
          required
        />
        <input
          type="text"
          className="w-full mb-3 px-3 py-2 border rounded"
          placeholder="Nom d'utilisateur"
          value={username}
          onChange={(e) => setUsername(e.target.value)}
          required
        />
        <input
          type="text"
          className="w-full mb-3 px-3 py-2 border rounded"
          placeholder="Nom complet"
          value={fullName}
          onChange={(e) => setFullName(e.target.value)}
          required
        />
        <input
          type="password"
          className="w-full mb-3 px-3 py-2 border rounded"
          placeholder="Mot de passe"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          required
        />
        <button
          type="submit"
          className="w-full py-2 bg-green-600 text-white rounded hover:bg-green-700 transition"
          disabled={loading}
        >
          {loading ? 'Envoi...' : inviteMode ? 'Envoyer le formulaire' : "S'inscrire"}
        </button>
      </form>
      {!inviteMode && (
        <p className="mt-4 text-sm text-gray-500">
          Déjà membre ? <a href="/login" className="text-blue-600">Se connecter</a>
        </p>
      )}
    </div>
  );
}

export default Register;
