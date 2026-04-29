import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
  AlertCircle,
  CheckCircle2,
  ChevronRight,
  Gauge,
  History,
  Languages,
  Loader2,
  LogOut,
  MapPin,
  Navigation,
  Play,
  Settings,
  Shield,
  Square,
  User as UserIcon,
  UserPlus,
  X,
  Zap,
} from 'lucide-react';
import { motion, AnimatePresence } from 'motion/react';
import { Routes, Route } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Capacitor } from '@capacitor/core';

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

const API_BASE = Capacitor.isNativePlatform() ? 'https://driver.setmobile.eu/api' : '/api';
const LANGUAGE_PREFERENCE_KEY = 'setmobile-driver-language';

const LANGUAGE_OPTIONS = [
  { id: 'de', label: 'Deutsch' },
  { id: 'en', label: 'English' },
  { id: 'tr', label: 'Turkce' },
] as const;

function getActivityMeta(coords?: GeolocationCoordinates | null) {
  return {
    latitude: coords?.latitude ?? null,
    longitude: coords?.longitude ?? null,
    speed: coords?.speed ?? null,
    heading: coords?.heading ?? null,
  };
}

function getDateLocale(language: string) {
  if (language.startsWith('de')) {
    return 'de-DE';
  }

  if (language.startsWith('tr')) {
    return 'tr-TR';
  }

  return 'en-US';
}

async function apiFetch(path: string, options: any = {}) {
  const token = localStorage.getItem('token');
  const headers = {
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...options.headers,
  };

  console.log(`Fetching ${API_BASE}${path}`, options);
  const response = await fetch(`${API_BASE}${path}`, { ...options, headers });
  console.log(`Response from ${path}:`, response.status, response.ok);

  if (!response.ok) {
    const text = await response.text();
    console.error('Error response body:', text);
    let error;
    try {
      error = text ? JSON.parse(text) : { message: `API Error: ${response.status}` };
    } catch (parseError) {
      error = { message: `API Error: ${response.status} ${response.statusText}` };
    }
    throw new Error(error.message || 'API Error');
  }

  const text = await response.text();
  return text ? JSON.parse(text) : null;
}

export default function App() {
  const { t, i18n } = useTranslation();
  const [user, setUser] = useState<User | null>(null);
  const [activeShift, setActiveShift] = useState<Shift | null>(null);
  const [history, setHistory] = useState<Shift[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [startKm, setStartKm] = useState('');
  const [endKm, setEndKm] = useState('');
  const [currentLocation, setCurrentLocation] = useState<GeolocationCoordinates | null>(null);
  const [isTracking, setIsTracking] = useState(false);
  const [pendingTelemetry, setPendingTelemetry] = useState<Telemetry[]>(() => {
    const saved = localStorage.getItem('pending_telemetry');
    return saved ? JSON.parse(saved) : [];
  });
  const [currentView, setCurrentView] = useState<View>('driving');
  const [adminData, setAdminData] = useState<{ active: any[]; history: any[] }>({ active: [], history: [] });
  const [showSummary, setShowSummary] = useState<Shift | null>(null);
  const [isRegistering, setIsRegistering] = useState(false);
  const [showSettings, setShowSettings] = useState(false);
  const [showLanguageOptions, setShowLanguageOptions] = useState(false);
  const [authData, setAuthData] = useState({ username: '', password: '', displayName: '', email: '' });

  const watchId = useRef<number | null>(null);
  const telemetryInterval = useRef<NodeJS.Timeout | null>(null);

  const activeLanguage = i18n.resolvedLanguage || i18n.language || 'de';
  const currentLocale = getDateLocale(activeLanguage);
  const currentLanguageLabel =
    LANGUAGE_OPTIONS.find(({ id }) => activeLanguage.startsWith(id))?.label ?? LANGUAGE_OPTIONS[0].label;

  const changeAppLanguage = (language: (typeof LANGUAGE_OPTIONS)[number]['id']) => {
    localStorage.setItem(LANGUAGE_PREFERENCE_KEY, language);
    i18n.changeLanguage(language);
  };

  useEffect(() => {
    localStorage.setItem('pending_telemetry', JSON.stringify(pendingTelemetry));
  }, [pendingTelemetry]);

  useEffect(() => {
    if (!showSettings) {
      setShowLanguageOptions(false);
    }
  }, [showSettings]);

  useEffect(() => {
    const savedPreference = localStorage.getItem(LANGUAGE_PREFERENCE_KEY);

    if (savedPreference && LANGUAGE_OPTIONS.some(({ id }) => id === savedPreference)) {
      if (!activeLanguage.startsWith(savedPreference)) {
        i18n.changeLanguage(savedPreference);
      }
      return;
    }

    if (!activeLanguage.startsWith('de')) {
      i18n.changeLanguage('de');
    }
  }, [activeLanguage, i18n]);

  useEffect(() => {
    const checkAuth = async () => {
      const token = localStorage.getItem('token');
      if (token) {
        try {
          const userData = await apiFetch('/auth/profile');
          setUser({
            id: userData.userId,
            username: userData.username,
            role: userData.role,
            displayName: userData.displayName || 'Driver',
          });
        } catch (authError) {
          localStorage.removeItem('token');
        }
      }
      setLoading(false);
    };

    checkAuth();
  }, []);

  const handleAuth = async (event: React.FormEvent) => {
    event.preventDefault();
    setError(null);

    try {
      if (isRegistering) {
        await apiFetch('/auth/register', {
          method: 'POST',
          body: JSON.stringify(authData),
        });
        setIsRegistering(false);
        setError(t('registration_success'));
        return;
      }

      const data = await apiFetch('/auth/login', {
        method: 'POST',
        body: JSON.stringify({
          username: authData.username,
          password: authData.password,
          ...getActivityMeta(currentLocation),
        }),
      });

      if (!data?.access_token) {
        throw new Error(data?.error || 'Login failed');
      }

      localStorage.setItem('token', data.access_token);
      setUser(data.user);
      setShowSettings(false);
    } catch (authError: any) {
      setError(authError.message);
    }
  };

  const handleLogout = async () => {
    if (activeShift) {
      setError(t('logout_error_shift_active'));
      return;
    }

    try {
      await apiFetch('/auth/logout', {
        method: 'POST',
        body: JSON.stringify(getActivityMeta(currentLocation)),
      });
    } catch (logoutError) {
      console.error('Logout activity log failed', logoutError);
    }

    localStorage.removeItem('token');
    setShowSettings(false);
    setShowLanguageOptions(false);
    setCurrentView('driving');
    setUser(null);
  };

  const fetchActiveShift = useCallback(async () => {
    if (!user) {
      return;
    }

    try {
      const shift = await apiFetch('/shifts/active');
      setActiveShift(shift);
      setIsTracking(Boolean(shift));
    } catch (fetchError) {
      console.error('Fetch active shift failed', fetchError);
    }
  }, [user]);

  const fetchHistory = useCallback(async () => {
    if (!user) {
      return;
    }

    try {
      const data = await apiFetch('/shifts/history');
      setHistory(data);
    } catch (fetchError) {
      console.error('Fetch history failed', fetchError);
    }
  }, [user]);

  const fetchAdminData = useCallback(async () => {
    if (!user || user.role !== 'admin') {
      return;
    }

    try {
      const [active, historyData] = await Promise.all([
        apiFetch('/shifts/admin/active'),
        apiFetch('/shifts/admin/history'),
      ]);
      setAdminData({ active, history: historyData });
    } catch (fetchError) {
      console.error('Fetch admin data failed', fetchError);
    }
  }, [user]);

  useEffect(() => {
    if (user) {
      fetchActiveShift();
      fetchHistory();
      if (user.role === 'admin') {
        fetchAdminData();
      }
    }
  }, [user, fetchActiveShift, fetchHistory, fetchAdminData]);

  const startShift = async () => {
    if (!user || !startKm) {
      return;
    }

    try {
      const shift = await apiFetch('/shifts/start', {
        method: 'POST',
        body: JSON.stringify({
          startKm: parseFloat(startKm),
          ...getActivityMeta(currentLocation),
        }),
      });
      setActiveShift(shift);
      setIsTracking(true);
      setStartKm('');
    } catch (startError: any) {
      setError(`Shift could not be started: ${startError.message}`);
    }
  };

  const endShift = async () => {
    console.log('endShift attempt:', { activeShift, endKm });

    if (!activeShift) {
      setError('No active shift found.');
      return;
    }

    if (!endKm) {
      setError(t('enter_end_km'));
      return;
    }

    try {
      const endKmNum = parseFloat(endKm);
      if (endKmNum < activeShift.startKm) {
        setError(t('km_error'));
        return;
      }

      const updatedShift = await apiFetch(`/shifts/${activeShift.id}/end`, {
        method: 'POST',
        body: JSON.stringify({
          endKm: endKmNum,
          ...getActivityMeta(currentLocation),
        }),
      });

      setShowSummary(updatedShift);
      setEndKm('');
      setActiveShift(null);
      setIsTracking(false);
      fetchHistory();
    } catch (endError: any) {
      console.error('End shift error:', endError);
      setError(`Shift could not be ended: ${endError.message}`);
    }
  };

  const recordTelemetry = useCallback(
    async (position: GeolocationPosition) => {
      if (!activeShift || !user) {
        return;
      }

      const data = {
        shiftId: activeShift.id,
        timestamp: new Date().toISOString(),
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        speed: position.coords.speed || 0,
        heading: position.coords.heading || 0,
      };

      try {
        if (navigator.onLine) {
          await apiFetch('/shifts/telemetry', {
            method: 'POST',
            body: JSON.stringify(data),
          });
        } else {
          setPendingTelemetry((prev) => [...prev, data as any]);
        }
      } catch (telemetryError) {
        console.error('Telemetry upload failed:', telemetryError);
        setPendingTelemetry((prev) => [...prev, data as any]);
      }
    },
    [activeShift, user]
  );

  useEffect(() => {
    if (user) {
      watchId.current = navigator.geolocation.watchPosition(
        (position) => {
          setCurrentLocation(position.coords);
          setError(null);
        },
        (geoError) => {
          console.error('GPS Error:', geoError);
          if (geoError.code === 1) {
            setError(t('error_permission'));
          } else if (geoError.code === 2) {
            setError(t('error_not_found'));
          } else if (geoError.code === 3) {
            setError(t('error_timeout'));
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
  }, [user, t]);

  useEffect(() => {
    if (isTracking && activeShift) {
      telemetryInterval.current = setInterval(() => {
        navigator.geolocation.getCurrentPosition(
          (position) => recordTelemetry(position),
          (geoError) => console.error('Telemetry GPS Error:', geoError),
          { enableHighAccuracy: true }
        );
      }, 15000);
    } else if (telemetryInterval.current) {
      clearInterval(telemetryInterval.current);
      telemetryInterval.current = null;
    }

    return () => {
      if (telemetryInterval.current) {
        clearInterval(telemetryInterval.current);
        telemetryInterval.current = null;
      }
    };
  }, [isTracking, activeShift, recordTelemetry]);

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
        } catch (syncError) {
          console.error('Sync failed:', syncError);
        }
      }
    };

    window.addEventListener('online', handleOnline);
    return () => window.removeEventListener('online', handleOnline);
  }, [pendingTelemetry]);

  const renderSettingsSheet = () => (
    <AnimatePresence>
      {showSettings && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 z-[120] bg-black/70 backdrop-blur-sm p-4 flex items-end sm:items-center sm:justify-center"
          onClick={() => setShowSettings(false)}
        >
          <motion.div
            initial={{ opacity: 0, y: 24, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 24, scale: 0.98 }}
            transition={{ duration: 0.18 }}
            className="w-full sm:max-w-md bg-neutral-950 border border-neutral-800 rounded-[2rem] p-6 shadow-2xl"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="flex items-start justify-between gap-4">
              <div className="space-y-1">
                <p className="text-[10px] uppercase tracking-[0.3em] text-neutral-500 font-bold">{t('settings')}</p>
                <h3 className="text-2xl font-bold text-white">{user ? user.displayName : t('app_name')}</h3>
              </div>
              <button
                onClick={() => setShowSettings(false)}
                className="w-10 h-10 rounded-full bg-neutral-900 border border-neutral-800 text-neutral-400 hover:text-white transition-colors flex items-center justify-center"
                aria-label={t('close')}
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="mt-6 space-y-3">
              <button
                onClick={() => setShowLanguageOptions((prev) => !prev)}
                className="w-full text-left bg-neutral-900/90 border border-neutral-800 rounded-3xl p-4 flex items-center justify-between gap-4 hover:border-neutral-700 transition-colors"
              >
                <div className="flex items-center gap-4 min-w-0">
                  <div className="w-12 h-12 rounded-2xl bg-orange-500/10 text-orange-500 flex items-center justify-center shrink-0">
                    <Languages className="w-6 h-6" />
                  </div>
                  <div className="min-w-0">
                    <p className="text-sm font-bold text-white">{t('language')}</p>
                    <p className="text-xs text-neutral-500 truncate">
                      {t('language_hint')} • {currentLanguageLabel}
                    </p>
                  </div>
                </div>
                <ChevronRight
                  className={`w-5 h-5 text-neutral-500 transition-transform ${showLanguageOptions ? 'rotate-90' : ''}`}
                />
              </button>

              <AnimatePresence initial={false}>
                {showLanguageOptions && (
                  <motion.div
                    initial={{ opacity: 0, height: 0 }}
                    animate={{ opacity: 1, height: 'auto' }}
                    exit={{ opacity: 0, height: 0 }}
                    className="overflow-hidden"
                  >
                    <div className="grid grid-cols-3 gap-2 px-1 pt-1">
                      {LANGUAGE_OPTIONS.map((language) => (
                        <button
                          key={language.id}
                          onClick={() => changeAppLanguage(language.id)}
                          className={`py-3 rounded-2xl text-xs font-bold transition-all border ${
                            activeLanguage.startsWith(language.id)
                              ? 'bg-orange-500 border-orange-500 text-white'
                              : 'bg-neutral-950 border-neutral-800 text-neutral-400 hover:text-white'
                          }`}
                        >
                          {language.label}
                        </button>
                      ))}
                    </div>
                  </motion.div>
                )}
              </AnimatePresence>

              {user && (
                <button
                  onClick={handleLogout}
                  className="w-full text-left bg-neutral-900/90 border border-neutral-800 rounded-3xl p-4 flex items-center justify-between gap-4 hover:border-red-500/40 transition-colors"
                >
                  <div className="flex items-center gap-4 min-w-0">
                    <div className="w-12 h-12 rounded-2xl bg-red-500/10 text-red-400 flex items-center justify-center shrink-0">
                      <LogOut className="w-6 h-6" />
                    </div>
                    <div className="min-w-0">
                      <p className="text-sm font-bold text-white">{t('logout')}</p>
                      <p className="text-xs text-neutral-500 truncate">{t('logout_hint')}</p>
                    </div>
                  </div>
                  <ChevronRight className="w-5 h-5 text-neutral-500" />
                </button>
              )}
            </div>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );

  if (loading) {
    return (
      <div className="min-h-screen bg-neutral-950 flex items-center justify-center">
        <Loader2 className="w-8 h-8 text-orange-500 animate-spin" />
      </div>
    );
  }

  if (!user) {
    return (
      <div className="relative min-h-screen bg-neutral-950 flex flex-col items-center justify-center p-6 text-white font-sans">
        <button
          onClick={() => setShowSettings(true)}
          className="absolute top-6 right-6 w-11 h-11 rounded-full border border-neutral-800 bg-neutral-900/80 text-neutral-400 hover:text-white transition-colors flex items-center justify-center"
          aria-label={t('settings')}
        >
          <Settings className="w-5 h-5" />
        </button>

        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md space-y-8">
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
                    onChange={(event) => setAuthData({ ...authData, displayName: event.target.value })}
                    className="w-full bg-neutral-900 border border-neutral-800 rounded-2xl py-4 px-4 focus:outline-none focus:border-orange-500 transition-colors"
                  />
                </div>
                <div className="space-y-2">
                  <label className="text-xs text-neutral-500 uppercase font-bold ml-2">{t('email')}</label>
                  <input
                    type="email"
                    required
                    value={authData.email}
                    onChange={(event) => setAuthData({ ...authData, email: event.target.value })}
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
                onChange={(event) => setAuthData({ ...authData, username: event.target.value })}
                className="w-full bg-neutral-900 border border-neutral-800 rounded-2xl py-4 px-4 focus:outline-none focus:border-orange-500 transition-colors"
              />
            </div>

            <div className="space-y-2">
              <label className="text-xs text-neutral-500 uppercase font-bold ml-2">{t('password')}</label>
              <input
                type="password"
                required
                value={authData.password}
                onChange={(event) => setAuthData({ ...authData, password: event.target.value })}
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
        </motion.div>

        {renderSettingsSheet()}
      </div>
    );
  }

  return (
    <Routes>
      <Route
        path="*"
        element={
          <div className="min-h-screen bg-neutral-950 text-white font-sans selection:bg-orange-500/30">
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
                  onClick={() => setShowSettings(true)}
                  className="p-2 text-neutral-500 hover:text-white transition-colors"
                  aria-label={t('settings')}
                >
                  <Settings className="w-5 h-5" />
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
                    <button onClick={() => setError(null)} className="font-bold mt-1 underline">
                      {t('close')}
                    </button>
                  </div>
                </motion.div>
              )}

              {currentView === 'driving' && (
                <div className="space-y-8">
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
                          <span className="text-xs text-neutral-600 ml-1">deg</span>
                        </p>
                      </div>
                    </div>
                  </section>

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
                              onChange={(event) => setStartKm(event.target.value)}
                              placeholder="124500"
                              className="w-full bg-neutral-900 border border-neutral-800 rounded-2xl py-4 pl-12 pr-4 focus:outline-none focus:border-orange-500 transition-colors text-lg font-mono"
                            />
                          </div>
                        </div>
                        <button
                          onClick={startShift}
                          disabled={!startKm || !currentLocation}
                          className="w-full bg-orange-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-5 rounded-2xl flex items-center justify-center gap-3 shadow-lg shadow-orange-500/20 hover:bg-orange-600 transition-all active:scale-95"
                        >
                          {!currentLocation ? <Loader2 className="w-6 h-6 animate-spin" /> : <Play className="w-6 h-6 fill-white" />}
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
                              onChange={(event) => setEndKm(event.target.value)}
                              placeholder="124650"
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

                  <section className="bg-neutral-900/30 rounded-3xl p-6 border border-neutral-900">
                    <div className="flex items-center gap-4">
                      <div className="w-12 h-12 bg-neutral-800 rounded-2xl flex items-center justify-center shrink-0">
                        <MapPin className="w-6 h-6 text-neutral-400" />
                      </div>
                      <div className="min-w-0">
                        <p className="text-[10px] text-neutral-500 uppercase font-bold tracking-wider">{t('current_location')}</p>
                        <p className="text-sm text-neutral-300 truncate font-mono">
                          {currentLocation
                            ? `${currentLocation.latitude.toFixed(4)}, ${currentLocation.longitude.toFixed(4)}`
                            : t('waiting_location')}
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
                      {history.map((shift) => (
                        <div key={shift.id} className="bg-neutral-900 p-5 rounded-2xl border border-neutral-800 flex justify-between items-center">
                          <div className="space-y-1">
                            <p className="text-xs text-neutral-500 font-bold">{new Date(shift.startTime).toLocaleDateString(currentLocale)}</p>
                            <p className="font-bold text-lg">
                              {(shift.endKm || 0) - shift.startKm} <span className="text-xs text-neutral-500">{t('km')}</span>
                            </p>
                          </div>
                          <div className="text-right">
                            <p className="text-[10px] text-neutral-500 uppercase font-bold">{t('km')}</p>
                            <p className="text-xs text-neutral-400 font-mono">
                              {shift.startKm} -&gt; {shift.endKm}
                            </p>
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
                  </div>
                </div>
              )}

              {pendingTelemetry.length > 0 && (
                <div className="fixed bottom-24 left-6 right-6 z-40">
                  <div className="bg-orange-500 text-white px-4 py-3 rounded-2xl flex items-center justify-between shadow-2xl animate-pulse">
                    <div className="flex items-center gap-2">
                      <Loader2 className="w-4 h-4 animate-spin" />
                      <span className="text-xs font-bold">
                        {t('syncing')} ({pendingTelemetry.length})
                      </span>
                    </div>
                    <span className="text-[10px] uppercase font-bold opacity-80">{t('offline_mode')}</span>
                  </div>
                </div>
              )}
            </main>

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
                      <p className="text-neutral-500 text-sm">{new Date(showSummary.startTime).toLocaleString(currentLocale)}</p>
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

            {renderSettingsSheet()}

            <nav className="fixed bottom-0 left-0 right-0 bg-neutral-950/80 backdrop-blur-lg border-t border-neutral-900 p-4 flex justify-around items-center z-50">
              <button
                onClick={() => setCurrentView('driving')}
                className={`flex flex-col items-center gap-1 transition-colors ${currentView === 'driving' ? 'text-orange-500' : 'text-neutral-500'}`}
              >
                <Zap className={`w-6 h-6 ${currentView === 'driving' ? 'fill-orange-500' : ''}`} />
                <span className="text-[10px] font-bold uppercase">{t('driving')}</span>
              </button>
              <button
                onClick={() => setCurrentView('history')}
                className={`flex flex-col items-center gap-1 transition-colors ${currentView === 'history' ? 'text-orange-500' : 'text-neutral-500'}`}
              >
                <History className="w-6 h-6" />
                <span className="text-[10px] font-bold uppercase">{t('history')}</span>
              </button>
              <button
                onClick={() => setCurrentView('profile')}
                className={`flex flex-col items-center gap-1 transition-colors ${currentView === 'profile' ? 'text-orange-500' : 'text-neutral-500'}`}
              >
                <UserIcon className="w-6 h-6" />
                <span className="text-[10px] font-bold uppercase">{t('profile')}</span>
              </button>
            </nav>
          </div>
        }
      />
    </Routes>
  );
}
