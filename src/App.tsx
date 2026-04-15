import React, { useState, useEffect, useCallback, useRef } from 'react';
import { 
  LogOut, 
  Play, 
  Square, 
  Navigation, 
  MapPin, 
  History, 
  User as UserIcon,
  Shield,
  Zap,
  CheckCircle2,
  AlertCircle,
  Loader2,
  Gauge,
  UserPlus,
  Settings,
  Globe,
  Languages
} from 'lucide-react';
import { motion, AnimatePresence } from 'motion/react';
import { Routes, Route, useNavigate, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import AdminPanel from './components/AdminPanel';

// --- Types ---

interface User {
  id: number;
  username: string;
  displayName: string;
  role: string;
}

interface Shift {
  id: number;
  driverId: number;
  startTime: string;
  endTime?: string;
  startKm: number;
  endKm?: number;
  status: 'active' | 'completed';
}

interface Telemetry {
  id?: number;
  shiftId: number;
  driverId: number;
  timestamp: string;
  latitude: number;
  longitude: number;
  speed: number;
  heading: number;
}

type View = 'driving' | 'history' | 'profile' | 'admin';

// --- API Helpers ---

const API_BASE = '/api';

async function apiFetch(path: string, options: any = {}) {
  const token = localStorage.getItem('token');
  const headers = {
    'Content-Type': 'application/json',
    ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
    ...options.headers,
  };

  console.log(`Fetching ${API_BASE}${path}`, options);
  const response = await fetch(`${API_BASE}${path}`, { ...options, headers });
  console.log(`Response from ${path}:`, response.status, response.ok);
  
  if (!response.ok) {
    const text = await response.text();
    console.error(`Error response body:`, text);
    let error;
    try {
      error = text ? JSON.parse(text) : { message: `API Error: ${response.status}` };
    } catch (e) {
      error = { message: `API Error: ${response.status} ${response.statusText}` };
    }
    throw new Error(error.message || 'API Error');
  }

  const text = await response.text();
  return text ? JSON.parse(text) : null;
}

// --- Components ---

export default function App() {
  const navigate = useNavigate();
  const location = useLocation();
  const { t, i18n } = useTranslation();
  const [user, setUser] = useState<User | null>(null);
  const [activeShift, setActiveShift] = useState<Shift | null>(null);
  const [history, setHistory] = useState<Shift[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [startKm, setStartKm] = useState<string>('');
  const [endKm, setEndKm] = useState<string>('');
  const [currentLocation, setCurrentLocation] = useState<GeolocationCoordinates | null>(null);
  const [isTracking, setIsTracking] = useState(false);
  const [pendingTelemetry, setPendingTelemetry] = useState<Telemetry[]>(() => {
    const saved = localStorage.getItem('pending_telemetry');
    return saved ? JSON.parse(saved) : [];
  });
  const [currentView, setCurrentView] = useState<View>('driving');
  const [adminData, setAdminData] = useState<{ active: any[], history: any[] }>({ active: [], history: [] });
  const [selectedShiftTelemetry, setSelectedShiftTelemetry] = useState<Telemetry[] | null>(null);
  const [showSummary, setShowSummary] = useState<Shift | null>(null);
  const [isRegistering, setIsRegistering] = useState(false);
  const [authData, setAuthData] = useState({ username: '', password: '', displayName: '', email: '' });

  const watchId = useRef<number | null>(null);
  const telemetryInterval = useRef<NodeJS.Timeout | null>(null);

  // --- Persistence ---

  useEffect(() => {
    localStorage.setItem('pending_telemetry', JSON.stringify(pendingTelemetry));
  }, [pendingTelemetry]);

  // --- Auth ---

  useEffect(() => {
    const checkAuth = async () => {
      const token = localStorage.getItem('token');
      if (token) {
        try {
          const userData = await apiFetch('/auth/profile');
          setUser({ id: userData.userId, username: userData.username, role: userData.role, displayName: userData.displayName || 'Driver' });
        } catch (err) {
          localStorage.removeItem('token');
        }
      }
      setLoading(false);
    };
    checkAuth();
  }, []);

  const handleAuth = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    try {
      if (isRegistering) {
        await apiFetch('/auth/register', {
          method: 'POST',
          body: JSON.stringify(authData),
        });
        setIsRegistering(false);
        setError(t('registration_success'));
      } else {
        const data = await apiFetch('/auth/login', {
          method: 'POST',
          body: JSON.stringify({ username: authData.username, password: authData.password }),
        });
        localStorage.setItem('token', data.access_token);
        setUser(data.user);
      }
    } catch (err: any) {
      setError(err.message);
    }
  };

  const handleLogout = () => {
    if (activeShift) {
      setError(t('logout_error_shift_active') || 'Please end your shift first.');
      return;
    }
    localStorage.removeItem('token');
    setUser(null);
  };

  // --- Shift Management ---

  const fetchActiveShift = useCallback(async () => {
    if (!user) return;
    try {
      const shift = await apiFetch('/shifts/active');
      setActiveShift(shift);
      setIsTracking(!!shift);
    } catch (err) {
      console.error('Fetch active shift failed', err);
    }
  }, [user]);

  const fetchHistory = useCallback(async () => {
    if (!user) return;
    try {
      const data = await apiFetch('/shifts/history');
      setHistory(data);
    } catch (err) {
      console.error('Fetch history failed', err);
    }
  }, [user]);

  const fetchAdminData = useCallback(async () => {
    if (!user || user.role !== 'admin') return;
    try {
      const [active, history] = await Promise.all([
        apiFetch('/shifts/admin/active'),
        apiFetch('/shifts/admin/history')
      ]);
      setAdminData({ active, history });
    } catch (err) {
      console.error('Fetch admin data failed', err);
    }
  }, [user]);

  useEffect(() => {
    if (user) {
      fetchActiveShift();
      fetchHistory();
      if (user.role === 'admin') fetchAdminData();
    }
  }, [user, fetchActiveShift, fetchHistory, fetchAdminData]);

  const startShift = async () => {
    if (!user || !startKm) return;
    try {
      const shift = await apiFetch('/shifts/start', {
        method: 'POST',
        body: JSON.stringify({ startKm: parseFloat(startKm) }),
      });
      setActiveShift(shift);
      setIsTracking(true);
      setStartKm('');
    } catch (err: any) {
      setError('Vardiya başlatılamadı: ' + err.message);
    }
  };


  const endShift = async () => {
    console.log('endShift attempt:', { activeShift, endKm });
    if (!activeShift) {
      setError('Aktif vardiya bulunamadı.');
      return;
    }
    if (!endKm) {
      setError('Lütfen bitiş kilometresini girin.');
      return;
    }

    try {
      const endKmNum = parseFloat(endKm);
      if (endKmNum < activeShift.startKm) {
        setError(`Bitiş kilometresi (${endKmNum}), başlangıç kilometresinden (${activeShift.startKm}) küçük olamaz.`);
        return;
      }

      console.log(`Sending end shift request for ID: ${activeShift.id}`);
      const updatedShift = await apiFetch(`/shifts/${activeShift.id}/end`, {
        method: 'POST',
        body: JSON.stringify({ endKm: endKmNum }),
      });
      
      console.log('Shift ended successfully:', updatedShift);
      setShowSummary(updatedShift);
      setEndKm('');
      setActiveShift(null);
      setIsTracking(false);
      fetchHistory();
    } catch (err: any) {
      console.error('End shift error:', err);
      setError('Vardiya sonlandırılamadı: ' + err.message);
    }
  };

  const recordTelemetry = useCallback(async (position: GeolocationPosition) => {
    if (!activeShift || !user) return;

    const data = {
      shiftId: activeShift.id,
      timestamp: new Date().toISOString(),
      latitude: position.coords.latitude,
      longitude: position.coords.longitude,
      speed: position.coords.speed || 0,
      heading: position.coords.heading || 0
    };

    try {
      if (navigator.onLine) {
        await apiFetch('/shifts/telemetry', {
          method: 'POST',
          body: JSON.stringify(data),
        });
      } else {
        setPendingTelemetry(prev => [...prev, data as any]);
      }
    } catch (err) {
      console.error('Telemetry upload failed:', err);
      setPendingTelemetry(prev => [...prev, data as any]);
    }
  }, [activeShift, user]);

  useEffect(() => {
    if (user) {
      // Start watching position immediately when logged in
      watchId.current = navigator.geolocation.watchPosition(
        (pos) => {
          setCurrentLocation(pos.coords);
          setError(null); // Clear any previous GPS errors
        },
        (err) => {
          console.error('GPS Error:', err);
          if (err.code === 1) {
            setError('Konum izni reddedildi. Lütfen tarayıcı ayarlarından konum izni verin.');
          } else if (err.code === 2) {
            setError('Konum bilgisi alınamadı. Lütfen GPS sinyalini kontrol edin.');
          } else if (err.code === 3) {
            setError('Konum isteği zaman aşımına uğradı.');
          }
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
      );
    }

    return () => {
      if (watchId.current !== null) {
        navigator.geolocation.clearWatch(watchId.current);
        watchId.current = null;
      }
    };
  }, [user]);

  useEffect(() => {
    if (isTracking && activeShift) {
      telemetryInterval.current = setInterval(() => {
        navigator.geolocation.getCurrentPosition(
          (pos) => recordTelemetry(pos),
          (err) => console.error('Telemetry GPS Error:', err),
          { enableHighAccuracy: true }
        );
      }, 15000);
    } else {
      if (telemetryInterval.current) {
        clearInterval(telemetryInterval.current);
        telemetryInterval.current = null;
      }
    }

    return () => {
      if (telemetryInterval.current) {
        clearInterval(telemetryInterval.current);
        telemetryInterval.current = null;
      }
    };
  }, [isTracking, activeShift, recordTelemetry]);

  // Sync pending telemetry
  useEffect(() => {
    const handleOnline = async () => {
      if (pendingTelemetry.length > 0) {
        try {
          for (const item of pendingTelemetry) {
            await apiFetch('/shifts/telemetry', {
              method: 'POST',
              body: JSON.stringify(item),
            });
          }
          setPendingTelemetry([]);
        } catch (err) {
          console.error('Sync failed:', err);
        }
      }
    };

    window.addEventListener('online', handleOnline);
    return () => window.removeEventListener('online', handleOnline);
  }, [pendingTelemetry]);

  // --- UI ---

  if (loading) {
    return (
      <div className="min-h-screen bg-neutral-950 flex items-center justify-center">
        <Loader2 className="w-8 h-8 text-orange-500 animate-spin" />
      </div>
    );
  }

  if (!user) {
    return (
      <div className="min-h-screen bg-neutral-950 flex flex-col items-center justify-center p-6 text-white font-sans">
        <motion.div 
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="w-full max-w-md space-y-8"
        >
          <div className="text-center space-y-4">
            <div className="flex justify-center gap-4">
              <div className="w-20 h-20 bg-orange-500 rounded-3xl flex items-center justify-center shadow-2xl shadow-orange-500/20">
                <Navigation className="w-10 h-10 text-white" />
              </div>
            </div>
            <div className="space-y-2">
              <h1 className="text-4xl font-bold tracking-tight">{t('app_name')}</h1>
              <p className="text-neutral-400">{t('auth_subtitle')}</p>
            </div>
            
            {/* Language Switcher */}
            <div className="flex justify-center gap-2 pt-2">
              {['tr', 'en', 'de'].map((lang) => (
                <button
                  key={lang}
                  onClick={() => i18n.changeLanguage(lang)}
                  className={`px-3 py-1 rounded-full text-[10px] font-bold uppercase transition-all ${
                    i18n.language.startsWith(lang) ? 'bg-orange-500 text-white' : 'bg-neutral-900 text-neutral-500 hover:text-white'
                  }`}
                >
                  {lang}
                </button>
              ))}
            </div>
          </div>

          <form onSubmit={handleAuth} className="space-y-4">
            {isRegistering && (
              <>
                <div className="space-y-2">
                  <label className="text-xs text-neutral-500 uppercase font-bold ml-2">{t('display_name')}</label>
                  <input 
                    type="text"
                    required
                    value={authData.displayName}
                    onChange={(e) => setAuthData({ ...authData, displayName: e.target.value })}
                    className="w-full bg-neutral-900 border border-neutral-800 rounded-2xl py-4 px-4 focus:outline-none focus:border-orange-500 transition-colors"
                  />
                </div>
                <div className="space-y-2">
                  <label className="text-xs text-neutral-500 uppercase font-bold ml-2">{t('email')}</label>
                  <input 
                    type="email"
                    required
                    value={authData.email}
                    onChange={(e) => setAuthData({ ...authData, email: e.target.value })}
                    className="w-full bg-neutral-900 border border-neutral-800 rounded-2xl py-4 px-4 focus:outline-none focus:border-orange-500 transition-colors"
                  />
                </div>
              </>
            )}
            <div className="space-y-2">
              <label className="text-xs text-neutral-500 uppercase font-bold ml-2">{t('username')}</label>
              <input 
                type="text"
                required
                value={authData.username}
                onChange={(e) => setAuthData({ ...authData, username: e.target.value })}
                className="w-full bg-neutral-900 border border-neutral-800 rounded-2xl py-4 px-4 focus:outline-none focus:border-orange-500 transition-colors"
              />
            </div>
            <div className="space-y-2">
              <label className="text-xs text-neutral-500 uppercase font-bold ml-2">{t('password')}</label>
              <input 
                type="password"
                required
                value={authData.password}
                onChange={(e) => setAuthData({ ...authData, password: e.target.value })}
                className="w-full bg-neutral-900 border border-neutral-800 rounded-2xl py-4 px-4 focus:outline-none focus:border-orange-500 transition-colors"
              />
            </div>

            {error && <p className="text-red-500 text-sm text-center">{error}</p>}

            <button 
              type="submit"
              className="w-full bg-white text-black font-bold py-4 rounded-2xl flex items-center justify-center gap-3 hover:bg-neutral-200 transition-colors"
            >
              {isRegistering ? <UserPlus className="w-5 h-5" /> : <Shield className="w-5 h-5" />}
              {isRegistering ? t('register') : t('login')}
            </button>
          </form>

          <div className="text-center">
            <button 
              onClick={() => setIsRegistering(!isRegistering)}
              className="text-orange-500 text-sm font-bold"
            >
              {isRegistering ? t('already_have_account') : t('no_account')}
            </button>
          </div>
        </motion.div>
      </div>
    );
  }

  return (
    <Routes>
      <Route path="*" element={
        <div className="min-h-screen bg-neutral-950 text-white font-sans selection:bg-orange-500/30">
      {/* Header */}
      <header className="p-6 flex justify-between items-center border-b border-neutral-900 sticky top-0 bg-neutral-950/80 backdrop-blur-md z-50">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-orange-500 rounded-xl flex items-center justify-center">
            <Navigation className="w-6 h-6 text-white" />
          </div>
          <div>
            <h2 className="font-bold text-sm leading-tight">{user.displayName}</h2>
            <p className="text-[10px] text-neutral-500 uppercase tracking-widest font-bold">
              {activeShift ? t('shift_active') : t('shift_inactive')}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <button 
            onClick={handleLogout}
            className="p-2 text-neutral-500 hover:text-white transition-colors"
          >
            <LogOut className="w-5 h-5" />
          </button>
        </div>
      </header>

      <main className="p-6 max-w-lg mx-auto space-y-8 pb-32">
        {error && (
          <motion.div 
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            className="bg-red-500/10 border border-red-500/20 p-4 rounded-2xl flex items-start gap-3 text-red-400 text-sm"
          >
            <AlertCircle className="w-5 h-5 shrink-0" />
            <div className="flex-1">
              <p>{error}</p>
              <button onClick={() => setError(null)} className="font-bold mt-1 underline">Kapat</button>
            </div>
          </motion.div>
        )}

        {currentView === 'driving' && (
          <div className="space-y-8">
            {/* Status Card */}
            <section className="bg-neutral-900 rounded-3xl p-6 border border-neutral-800 shadow-xl">
              <div className="flex justify-between items-start mb-6">
                <div className="space-y-1">
                  <p className="text-xs text-neutral-500 uppercase tracking-wider font-bold">{t('status')}</p>
                  <h3 className="text-2xl font-bold flex items-center gap-2">
                    {activeShift ? (
                      <>
                        <Zap className="w-6 h-6 text-orange-500 fill-orange-500" />
                        {t('driving')}
                      </>
                    ) : (
                      <>
                        <History className="w-6 h-6 text-neutral-500" />
                        {t('standby')}
                      </>
                    )}
                  </h3>
                </div>
                {activeShift && (
                  <div className="bg-orange-500/10 text-orange-500 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-tighter border border-orange-500/20">
                    GPS {t('online')}
                  </div>
                )}
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="bg-black/40 p-4 rounded-2xl border border-neutral-800/50">
                  <p className="text-[10px] text-neutral-500 uppercase font-bold mb-1">{t('speed')}</p>
                  <p className="text-2xl font-mono font-bold">
                    {currentLocation?.speed ? Math.round(currentLocation.speed * 3.6) : 0}
                    <span className="text-xs text-neutral-600 ml-1">km/h</span>
                  </p>
                </div>
                <div className="bg-black/40 p-4 rounded-2xl border border-neutral-800/50">
                  <p className="text-[10px] text-neutral-500 uppercase font-bold mb-1">{t('heading')}</p>
                  <p className="text-2xl font-mono font-bold">
                    {currentLocation?.heading ? Math.round(currentLocation.heading) : 0}
                    <span className="text-xs text-neutral-600 ml-1">°</span>
                  </p>
                </div>
              </div>
            </section>

            {/* Action Area */}
            <section className="space-y-4">
              {!activeShift ? (
                <div className="space-y-4">
                  <div className="space-y-2">
                    <label className="text-xs text-neutral-500 uppercase font-bold ml-2">{t('start_km')}</label>
                    <div className="relative">
                      <Gauge className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-neutral-500" />
                      <input 
                        type="number"
                        value={startKm}
                        onChange={(e) => setStartKm(e.target.value)}
                        placeholder="Örn: 124500"
                        className="w-full bg-neutral-900 border border-neutral-800 rounded-2xl py-4 pl-12 pr-4 focus:outline-none focus:border-orange-500 transition-colors text-lg font-mono"
                      />
                    </div>
                  </div>
                  <button 
                    onClick={startShift}
                    disabled={!startKm || !currentLocation}
                    className="w-full bg-orange-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-5 rounded-2xl flex items-center justify-center gap-3 shadow-lg shadow-orange-500/20 hover:bg-orange-600 transition-all active:scale-95"
                  >
                    {!currentLocation ? (
                      <Loader2 className="w-6 h-6 animate-spin" />
                    ) : (
                      <Play className="w-6 h-6 fill-white" />
                    )}
                    {!currentLocation ? t('waiting_location') : t('start_shift')}
                  </button>
                </div>
              ) : (
                <div className="space-y-4">
                  <div className="grid grid-cols-2 gap-4 mb-4">
                    <div className="bg-neutral-900/50 p-4 rounded-2xl border border-neutral-800/50">
                      <p className="text-[10px] text-neutral-500 uppercase font-bold mb-1">{t('start_km')}</p>
                      <p className="text-xl font-mono font-bold text-neutral-300">{activeShift.startKm}</p>
                    </div>
                    <div className="bg-neutral-900/50 p-4 rounded-2xl border border-neutral-800/50">
                      <p className="text-[10px] text-neutral-500 uppercase font-bold mb-1">{t('status')}</p>
                      <p className="text-xl font-mono font-bold text-orange-500">{t('driving')}</p>
                    </div>
                  </div>
                  <div className="space-y-2">
                    <label className="text-xs text-neutral-500 uppercase font-bold ml-2">{t('end_km')}</label>
                    <div className="relative">
                      <Gauge className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-neutral-500" />
                      <input 
                        type="number"
                        value={endKm}
                        onChange={(e) => setEndKm(e.target.value)}
                        placeholder="Örn: 124650"
                        className="w-full bg-neutral-900 border border-neutral-800 rounded-2xl py-4 pl-12 pr-4 focus:outline-none focus:border-orange-500 transition-colors text-lg font-mono"
                      />
                    </div>
                  </div>
                  <button 
                    onClick={endShift}
                    className="w-full bg-white text-black font-bold py-5 rounded-2xl flex items-center justify-center gap-3 shadow-lg hover:bg-neutral-200 transition-all active:scale-95"
                  >
                    <Square className="w-6 h-6 fill-black" />
                    {!endKm ? t('enter_end_km') : t('end_shift')}
                  </button>
                </div>
              )}
            </section>

            {/* Location Info */}
            <section className="bg-neutral-900/30 rounded-3xl p-6 border border-neutral-900">
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 bg-neutral-800 rounded-2xl flex items-center justify-center shrink-0">
                  <MapPin className="w-6 h-6 text-neutral-400" />
                </div>
                <div className="min-w-0">
                  <p className="text-[10px] text-neutral-500 uppercase font-bold tracking-wider">{t('current_location')}</p>
                  <p className="text-sm text-neutral-300 truncate font-mono">
                    {currentLocation ? `${currentLocation.latitude.toFixed(4)}, ${currentLocation.longitude.toFixed(4)}` : t('waiting_location')}
                  </p>
                </div>
              </div>
            </section>
          </div>
        )}

        {currentView === 'history' && (
          <div className="space-y-6">
            <h3 className="text-xl font-bold">{t('history')}</h3>
            {history.length === 0 ? (
              <div className="text-center py-12 text-neutral-500">
                <History className="w-12 h-12 mx-auto mb-4 opacity-20" />
                <p>{t('no_history')}</p>
              </div>
            ) : (
              <div className="space-y-4">
                {history.map(shift => (
                  <div key={shift.id} className="bg-neutral-900 p-5 rounded-2xl border border-neutral-800 flex justify-between items-center">
                    <div className="space-y-1">
                      <p className="text-xs text-neutral-500 font-bold">
                        {new Date(shift.startTime).toLocaleDateString(i18n.language === 'tr' ? 'tr-TR' : 'en-US')}
                      </p>
                      <p className="font-bold text-lg">
                        {(shift.endKm || 0) - shift.startKm} <span className="text-xs text-neutral-500">{t('km')}</span>
                      </p>
                    </div>
                    <div className="text-right">
                      <p className="text-[10px] text-neutral-500 uppercase font-bold">{t('km')}</p>
                      <p className="text-xs text-neutral-400 font-mono">{shift.startKm} → {shift.endKm}</p>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {currentView === 'profile' && (
          <div className="space-y-8">
            <div className="flex flex-col items-center text-center space-y-4">
              <div className="w-24 h-24 bg-neutral-900 rounded-full border-4 border-neutral-800 flex items-center justify-center">
                <UserIcon className="w-12 h-12 text-neutral-500" />
              </div>
              <div>
                <h3 className="text-2xl font-bold">{user.displayName}</h3>
                <p className="text-neutral-500">{user.username}</p>
              </div>
            </div>
            
            <div className="bg-neutral-900 rounded-3xl p-6 border border-neutral-800 space-y-4">
              <div className="flex justify-between items-center py-2 border-b border-neutral-800">
                <span className="text-neutral-400">{t('role')}</span>
                <span className="font-bold uppercase text-xs bg-orange-500/10 text-orange-500 px-2 py-1 rounded-md">{user.role}</span>
              </div>
              <div className="flex justify-between items-center py-2 border-b border-neutral-800">
                <span className="text-neutral-400">{t('device_status')}</span>
                <span className="text-green-500 text-xs font-bold">{t('online')}</span>
              </div>
              <div className="flex justify-between items-center py-2 border-b border-neutral-800">
                <span className="text-neutral-400">{t('version')}</span>
                <span className="text-neutral-500 text-xs">v2.1.0 (i18n)</span>
              </div>
              
              {/* Language Switcher in Profile */}
              <div className="space-y-3 pt-2">
                <p className="text-[10px] text-neutral-500 uppercase font-bold tracking-widest flex items-center gap-2">
                  <Globe className="w-3 h-3" />
                  Dil Seçimi / Language / Sprache
                </p>
                <div className="grid grid-cols-3 gap-2">
                  {[
                    { id: 'tr', label: 'Türkçe' },
                    { id: 'en', label: 'English' },
                    { id: 'de', label: 'Deutsch' }
                  ].map((lang) => (
                    <button
                      key={lang.id}
                      onClick={() => i18n.changeLanguage(lang.id)}
                      className={`py-3 rounded-xl text-xs font-bold transition-all border ${
                        i18n.language.startsWith(lang.id) 
                          ? 'bg-orange-500 border-orange-500 text-white' 
                          : 'bg-neutral-950 border-neutral-800 text-neutral-500 hover:text-white'
                      }`}
                    >
                      {lang.label}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Offline Status */}
        {pendingTelemetry.length > 0 && (
          <div className="fixed bottom-24 left-6 right-6 z-40">
            <div className="bg-orange-500 text-white px-4 py-3 rounded-2xl flex items-center justify-between shadow-2xl animate-pulse">
              <div className="flex items-center gap-2">
                <Loader2 className="w-4 h-4 animate-spin" />
                <span className="text-xs font-bold">{t('syncing')} ({pendingTelemetry.length})</span>
              </div>
              <span className="text-[10px] uppercase font-bold opacity-80">{t('offline_mode')}</span>
            </div>
          </div>
        )}
      </main>

      {/* Summary Modal */}
      <AnimatePresence>
        {showSummary && (
          <motion.div 
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-black/90 backdrop-blur-sm z-[100] flex items-center justify-center p-6"
          >
            <motion.div 
              initial={{ scale: 0.9, y: 20 }}
              animate={{ scale: 1, y: 0 }}
              className="bg-neutral-900 w-full max-w-sm rounded-3xl p-8 border border-neutral-800 text-center space-y-6 shadow-2xl"
            >
              <div className="w-20 h-20 bg-green-500/10 rounded-full flex items-center justify-center mx-auto">
                <CheckCircle2 className="w-10 h-10 text-green-500" />
              </div>
              <div className="space-y-2">
                <h3 className="text-2xl font-bold">{t('shift_summary')}</h3>
                <p className="text-neutral-500 text-sm">{new Date(showSummary.startTime).toLocaleString(i18n.language === 'tr' ? 'tr-TR' : 'en-US')}</p>
              </div>
              
              <div className="bg-black/40 rounded-2xl p-6 border border-neutral-800/50">
                <p className="text-xs text-neutral-500 uppercase font-bold mb-2">{t('total_distance')}</p>
                <p className="text-4xl font-mono font-bold text-orange-500">
                  {(showSummary.endKm || 0) - showSummary.startKm}
                  <span className="text-sm text-neutral-600 ml-2">{t('km')}</span>
                </p>
              </div>

              <button 
                onClick={() => setShowSummary(null)}
                className="w-full bg-white text-black font-bold py-4 rounded-2xl hover:bg-neutral-200 transition-colors"
              >
                {t('close')}
              </button>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Bottom Nav */}
      <nav className="fixed bottom-0 left-0 right-0 bg-neutral-950/80 backdrop-blur-lg border-t border-neutral-900 p-4 flex justify-around items-center z-50">
        <button 
          onClick={() => setCurrentView('driving')}
          className={`flex flex-col items-center gap-1 transition-colors ${currentView === 'driving' ? 'text-orange-500' : 'text-neutral-500'}`}
        >
          <Zap className={`w-6 h-6 ${currentView === 'driving' ? 'fill-orange-500' : ''}`} />
          <span className="text-[10px] font-bold uppercase">Sürüş</span>
        </button>
        <button 
          onClick={() => setCurrentView('history')}
          className={`flex flex-col items-center gap-1 transition-colors ${currentView === 'history' ? 'text-orange-500' : 'text-neutral-500'}`}
        >
          <History className="w-6 h-6" />
          <span className="text-[10px] font-bold uppercase">Geçmiş</span>
        </button>
        <button 
          onClick={() => setCurrentView('profile')}
          className={`flex flex-col items-center gap-1 transition-colors ${currentView === 'profile' ? 'text-orange-500' : 'text-neutral-500'}`}
        >
          <UserIcon className="w-6 h-6" />
          <span className="text-[10px] font-bold uppercase">Profil</span>
        </button>
      </nav>
        </div>
      } />
    </Routes>
  );
}
