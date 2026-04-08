import React, { useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuthStore } from '../store/authStore';

const API_ROOT = (import.meta.env.VITE_API_URL || 'http://localhost:3000/api/v1').replace(/\/api(?:\/v\d+)?\/?$/, '');

const resolvePublicThemeUrl = (value?: string | null): string | null => {
  if (!value) return null;
  if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('data:image/')) return value;
  return `${API_ROOT}${value.startsWith('/') ? '' : '/'}${value}`;
};

function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
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
  const login = useAuthStore((state) => state.login);
  const navigate = useNavigate();
  const pageBgStyle: React.CSSProperties = loginBackgroundImage
    ? { backgroundColor: menuColor, transition: 'background-color 0.5s ease' }
    : { background: `linear-gradient(135deg, ${menuColor} 0%, #0f172a 100%)`, transition: 'background 0.5s ease' };

  // Fetch global theme from public endpoint so new browsers also show the correct theme
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
          // Preload the image before showing it to avoid a half-rendered background
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
        .catch(() => {/* silently ignore — falls back to default colors */});
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
    setError(null);
    setLoading(true);
    try {
      await login(email.trim().toLowerCase(), password);
      navigatingRef.current = true; // stop any pending theme updates from flashing on this page
      navigate('/documents');
    } catch (err: any) {
      if (err?.response?.status === 401) {
        setError('Email ou mot de passe incorrect.');
      } else if (err?.response?.status) {
        setError(err.response?.data?.message || 'Erreur serveur.');
      } else {
        setError('Impossible de joindre le serveur. Vérifiez que le backend tourne sur le port 3000.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="relative min-h-screen flex items-center justify-center p-4" style={pageBgStyle}>
      {/* Full-screen background image via <img> — far more reliable cross-browser than CSS backgroundImage */}
      {loginBackgroundImage && (
        <img
          src={loginBackgroundImage}
          alt=""
          aria-hidden="true"
          className="fixed inset-0 w-screen h-screen object-cover object-center"
          style={{ zIndex: 0 }}
        />
      )}
      {/* Dark overlay when image is present */}
      {loginBackgroundImage && (
        <div
          className="absolute inset-0"
          style={{ backgroundColor: 'rgba(0,0,0,0.40)', zIndex: 1 }}
        />
      )}
      <div className="relative w-full max-w-md p-8 bg-white/95 shadow-lg rounded-lg backdrop-blur-sm" style={{ zIndex: 2 }}>
        <h2 className="text-2xl font-semibold mb-6" style={{ color: menuColor }}>Connexion</h2>
        {error && <div className="mb-4 p-3 bg-red-100 text-red-700 rounded">{error}</div>}
        <form onSubmit={handleSubmit} autoComplete="on">
          <input
            type="email"
            name="email"
            autoComplete="username"
            className="w-full mb-3 px-3 py-2 border rounded"
            placeholder="Email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
          <input
            type="password"
            name="password"
            autoComplete="current-password"
            className="w-full mb-3 px-3 py-2 border rounded"
            placeholder="Mot de passe"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
          <button
            type="submit"
            className="w-full py-2 text-white rounded transition"
            style={{ backgroundColor: menuColor }}
            disabled={loading}
          >
            {loading ? 'Connexion...' : 'Se connecter'}
          </button>
        </form>
        <div className="mt-3 text-right">
          <Link to="/forgot-password" className="text-sm hover:underline" style={{ color: menuColor }}>
            Mot de passe oublie ?
          </Link>
        </div>
      </div>
    </div>
  );
}

export default Login;
