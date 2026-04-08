import React, { useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { forgotPassword } from '../services/auth';

const API_ROOT = (import.meta.env.VITE_API_URL || 'http://localhost:3000/api/v1').replace(/\/api(?:\/v\d+)?\/?$/, '');

const resolvePublicThemeUrl = (value?: string | null): string | null => {
  if (!value) return null;
  if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('data:image/')) return value;
  return `${API_ROOT}${value.startsWith('/') ? '' : '/'}${value}`;
};

function ForgotPassword() {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [menuColor, setMenuColor] = useState<string>(() => {
    try {
      return localStorage.getItem('ep_theme_menu_color') || '#173b9f';
    } catch {
      return '#173b9f';
    }
  });
  const [loginBackgroundImage, setLoginBackgroundImage] = useState<string | null>(() => {
    try {
      return localStorage.getItem('ep_theme_login_bg') || null;
    } catch {
      return null;
    }
  });
  const navigatingRef = useRef(false);

  const pageBgStyle: React.CSSProperties = loginBackgroundImage
    ? { backgroundColor: menuColor, transition: 'background-color 0.5s ease' }
    : { background: `linear-gradient(135deg, ${menuColor} 0%, #0f172a 100%)`, transition: 'background 0.5s ease' };

  React.useEffect(() => {
    fetch(`${API_ROOT}/api/v1/theme/global`)
      .then((r) => r.json())
      .then((data) => {
        if (navigatingRef.current) return;
        if (data.menuColor) {
          setMenuColor(data.menuColor);
          try {
            localStorage.setItem('ep_theme_menu_color', data.menuColor);
          } catch {
            // ignore
          }
        }
        const bgUrl = resolvePublicThemeUrl(data.loginBackgroundImage);
        if (bgUrl) {
          const img = new Image();
          img.onload = () => {
            if (!navigatingRef.current) {
              setLoginBackgroundImage(bgUrl);
              try {
                localStorage.setItem('ep_theme_login_bg', bgUrl);
              } catch {
                // ignore
              }
            }
          };
          img.src = bgUrl;
        } else {
          setLoginBackgroundImage(null);
          try {
            localStorage.removeItem('ep_theme_login_bg');
          } catch {
            // ignore
          }
        }
      })
      .catch(() => {
        // silently ignore
      });
  }, []);

  React.useEffect(() => {
    const syncTheme = (event: StorageEvent) => {
      if (navigatingRef.current) return;
      if (event.key === 'ep_theme_menu_color') {
        setMenuColor(event.newValue || '#173b9f');
      }
      if (event.key === 'ep_theme_login_bg') {
        setLoginBackgroundImage(event.newValue || null);
      }
    };

    const syncThemeCustom = (event: Event) => {
      if (navigatingRef.current) return;
      const detail = (event as CustomEvent<{ menuColor?: string; loginBackgroundImage?: string | null }>).detail;
      if (detail?.menuColor) {
        setMenuColor(detail.menuColor);
      }
      if (Object.prototype.hasOwnProperty.call(detail || {}, 'loginBackgroundImage')) {
        setLoginBackgroundImage(detail?.loginBackgroundImage || null);
      }
    };

    window.addEventListener('storage', syncTheme);
    window.addEventListener('ep_theme_changed', syncThemeCustom as EventListener);
    return () => {
      window.removeEventListener('storage', syncTheme);
      window.removeEventListener('ep_theme_changed', syncThemeCustom as EventListener);
    };
  }, []);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setLoading(true);
    setError(null);
    setMessage(null);

    try {
      const result = await forgotPassword({ email: email.trim().toLowerCase() });
      setMessage(result.message || 'Si ce compte existe, un email de reinitialisation a ete envoye.');
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Impossible de traiter la demande.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="relative min-h-screen flex items-center justify-center p-4" style={pageBgStyle}>
      {loginBackgroundImage && (
        <img
          src={loginBackgroundImage}
          alt=""
          aria-hidden="true"
          className="fixed inset-0 w-screen h-screen object-cover object-center"
          style={{ zIndex: 0 }}
        />
      )}
      {loginBackgroundImage && (
        <div
          className="absolute inset-0"
          style={{ backgroundColor: 'rgba(0,0,0,0.40)', zIndex: 1 }}
        />
      )}

      <div className="relative w-full max-w-md p-8 bg-white/95 shadow-lg rounded-lg backdrop-blur-sm" style={{ zIndex: 2 }}>
        <h2 className="text-2xl font-semibold mb-6" style={{ color: menuColor }}>Mot de passe oublie</h2>
        <p className="text-sm text-gray-600 mb-4">
          Saisissez votre adresse email pour recevoir un lien de reinitialisation.
        </p>

        {message && <div className="mb-4 p-3 bg-green-100 text-green-700 rounded">{message}</div>}
        {error && <div className="mb-4 p-3 bg-red-100 text-red-700 rounded">{error}</div>}

        <form onSubmit={handleSubmit} autoComplete="on">
          <input
            type="email"
            name="email"
            autoComplete="email"
            className="w-full mb-3 px-3 py-2 border rounded"
            placeholder="Email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />

          <button
            type="submit"
            className="w-full py-2 text-white rounded transition"
            style={{ backgroundColor: menuColor }}
            disabled={loading}
          >
            {loading ? 'Envoi...' : 'Envoyer le lien'}
          </button>
        </form>

        <p className="mt-4 text-sm text-gray-500">
          Retour a la <Link to="/login" className="hover:underline" style={{ color: menuColor }}>connexion</Link>
        </p>
      </div>
    </div>
  );
}

export default ForgotPassword;
