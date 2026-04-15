/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { 
  onAuthStateChanged, 
  signInWithPopup, 
  GoogleAuthProvider, 
  signOut,
  User as FirebaseUser
} from 'firebase/auth';
import { 
  collection, 
  addDoc, 
  updateDoc, 
  doc, 
  query, 
  where, 
  onSnapshot, 
  serverTimestamp,
  orderBy,
  limit,
  getDocs,
  setDoc,
  getDoc
} from 'firebase/firestore';
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
  Gauge
} from 'lucide-react';
import { motion, AnimatePresence } from 'motion/react';
import { auth, db } from './firebase';

// --- Types ---

interface Shift {
  id: string;
  driverId: string;
  startTime: any;
  endTime?: any;
  startKm: number;
  endKm?: number;
  status: 'active' | 'completed';
}

interface Telemetry {
  id?: string;
  shiftId: string;
  driverId: string;
  timestamp: any;
  latitude: number;
  longitude: number;
  speed: number;
  heading: number;
}

interface UserProfile {
  uid: string;
  email: string;
  displayName: string;
  role: 'driver' | 'admin';
  pin?: string;
}

type View = 'driving' | 'history' | 'profile';

// --- Components ---

export default function App() {
  const [user, setUser] = useState<FirebaseUser | null>(null);
  const [profile, setProfile] = useState<UserProfile | null>(null);
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
  const [showSummary, setShowSummary] = useState<Shift | null>(null);

  const watchId = useRef<number | null>(null);
  const telemetryInterval = useRef<NodeJS.Timeout | null>(null);

  // --- Persistence ---

  useEffect(() => {
    localStorage.setItem('pending_telemetry', JSON.stringify(pendingTelemetry));
  }, [pendingTelemetry]);

  // --- Auth & Profile ---

  useEffect(() => {
    const unsubscribe = onAuthStateChanged(auth, async (firebaseUser) => {
      setUser(firebaseUser);
      if (firebaseUser) {
        // Fetch or create profile
        const profileRef = doc(db, 'users', firebaseUser.uid);
        const profileSnap = await getDoc(profileRef);
        
        if (profileSnap.exists()) {
          setProfile(profileSnap.data() as UserProfile);
        } else {
          const newProfile: UserProfile = {
            uid: firebaseUser.uid,
            email: firebaseUser.email || '',
            displayName: firebaseUser.displayName || 'Driver',
            role: 'driver'
          };
          await setDoc(profileRef, newProfile);
          setProfile(newProfile);
        }
      } else {
        setProfile(null);
      }
      setLoading(false);
    });

    return () => unsubscribe();
  }, []);

  const handleLogin = async () => {
    try {
      const provider = new GoogleAuthProvider();
      await signInWithPopup(auth, provider);
    } catch (err: any) {
      setError('Giriş başarısız: ' + err.message);
    }
  };

  const handleLogout = async () => {
    try {
      if (activeShift) {
        setError('Lütfen önce vardiyanızı sonlandırın.');
        return;
      }
      await signOut(auth);
    } catch (err: any) {
      setError('Çıkış başarısız: ' + err.message);
    }
  };

  // --- Shift Management ---

  useEffect(() => {
    if (!user) return;

    // Active Shift Listener
    const qActive = query(
      collection(db, 'shifts'),
      where('driverId', '==', user.uid),
      where('status', '==', 'active'),
      limit(1)
    );

    const unsubActive = onSnapshot(qActive, (snapshot) => {
      if (!snapshot.empty) {
        const shiftDoc = snapshot.docs[0];
        setActiveShift({ id: shiftDoc.id, ...shiftDoc.data() } as Shift);
        setIsTracking(true);
      } else {
        setActiveShift(null);
        setIsTracking(false);
      }
    });

    // History Listener
    const qHistory = query(
      collection(db, 'shifts'),
      where('driverId', '==', user.uid),
      where('status', '==', 'completed'),
      orderBy('startTime', 'desc'),
      limit(20)
    );

    const unsubHistory = onSnapshot(qHistory, (snapshot) => {
      const shifts = snapshot.docs.map(doc => ({ id: doc.id, ...doc.data() } as Shift));
      setHistory(shifts);
    });

    return () => {
      unsubActive();
      unsubHistory();
    };
  }, [user]);

  const startShift = async () => {
    if (!user || !startKm) return;
    try {
      const shiftData = {
        driverId: user.uid,
        startTime: serverTimestamp(),
        startKm: parseFloat(startKm),
        status: 'active'
      };
      await addDoc(collection(db, 'shifts'), shiftData);
      setStartKm('');
    } catch (err: any) {
      setError('Vardiya başlatılamadı: ' + err.message);
    }
  };

  const endShift = async () => {
    if (!activeShift || !endKm) return;
    try {
      const endKmNum = parseFloat(endKm);
      if (endKmNum < activeShift.startKm) {
        setError('Bitiş kilometresi başlangıçtan küçük olamaz.');
        return;
      }

      const shiftRef = doc(db, 'shifts', activeShift.id);
      const updatedData = {
        endTime: serverTimestamp(),
        endKm: endKmNum,
        status: 'completed'
      };
      await updateDoc(shiftRef, updatedData);
      
      // Show summary
      setShowSummary({ ...activeShift, ...updatedData });
      
      setEndKm('');
      setActiveShift(null);
    } catch (err: any) {
      setError('Vardiya sonlandırılamadı: ' + err.message);
    }
  };

  // --- GPS Tracking ---

  const recordTelemetry = useCallback(async (position: GeolocationPosition) => {
    if (!activeShift || !user) return;

    const data = {
      shiftId: activeShift.id,
      driverId: user.uid,
      timestamp: serverTimestamp(),
      latitude: position.coords.latitude,
      longitude: position.coords.longitude,
      speed: position.coords.speed || 0,
      heading: position.coords.heading || 0
    };

    try {
      // Try to upload immediately
      if (navigator.onLine) {
        await addDoc(collection(db, 'telemetry'), data);
      } else {
        // Save to local state if offline (in a real app, use IndexedDB)
        setPendingTelemetry(prev => [...prev, data as any]);
      }
    } catch (err) {
      console.error('Telemetry upload failed:', err);
      setPendingTelemetry(prev => [...prev, data as any]);
    }
  }, [activeShift, user]);

  useEffect(() => {
    if (isTracking && activeShift) {
      // Start watching position
      watchId.current = navigator.geolocation.watchPosition(
        (pos) => setCurrentLocation(pos.coords),
        (err) => setError('GPS Hatası: ' + err.message),
        { enableHighAccuracy: true }
      );

      // Periodic recording (every 30 seconds)
      telemetryInterval.current = setInterval(() => {
        navigator.geolocation.getCurrentPosition(recordTelemetry);
      }, 30000);
    } else {
      if (watchId.current) navigator.geolocation.clearWatch(watchId.current);
      if (telemetryInterval.current) clearInterval(telemetryInterval.current);
    }

    return () => {
      if (watchId.current) navigator.geolocation.clearWatch(watchId.current);
      if (telemetryInterval.current) clearInterval(telemetryInterval.current);
    };
  }, [isTracking, activeShift, recordTelemetry]);

  // Sync pending telemetry when online
  useEffect(() => {
    const handleOnline = async () => {
      if (pendingTelemetry.length > 0) {
        try {
          for (const item of pendingTelemetry) {
            await addDoc(collection(db, 'telemetry'), item);
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

  // --- UI Helpers ---

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
          className="w-full max-w-md text-center space-y-8"
        >
          <div className="flex justify-center">
            <div className="w-20 h-20 bg-orange-500 rounded-3xl flex items-center justify-center shadow-2xl shadow-orange-500/20">
              <Navigation className="w-10 h-10 text-white" />
            </div>
          </div>
          <div className="space-y-2">
            <h1 className="text-4xl font-bold tracking-tight">FleetTrack</h1>
            <p className="text-neutral-400">Sürücü Takip Sistemi</p>
          </div>
          <button 
            onClick={handleLogin}
            className="w-full bg-white text-black font-bold py-4 rounded-2xl flex items-center justify-center gap-3 hover:bg-neutral-200 transition-colors"
          >
            <Shield className="w-5 h-5" />
            Google ile Giriş Yap
          </button>
          <p className="text-xs text-neutral-500">
            Şirket telefonu ile güvenli giriş yapın.
          </p>
        </motion.div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-neutral-950 text-white font-sans selection:bg-orange-500/30">
      {/* Header */}
      <header className="p-6 flex justify-between items-center border-b border-neutral-900 sticky top-0 bg-neutral-950/80 backdrop-blur-md z-50">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-orange-500 rounded-xl flex items-center justify-center">
            <Navigation className="w-6 h-6 text-white" />
          </div>
          <div>
            <h2 className="font-bold text-sm leading-tight">{profile?.displayName}</h2>
            <p className="text-[10px] text-neutral-500 uppercase tracking-widest font-bold">
              {activeShift ? 'Vardiya Aktif' : 'Vardiya Kapalı'}
            </p>
          </div>
        </div>
        <button 
          onClick={handleLogout}
          className="p-2 text-neutral-500 hover:text-white transition-colors"
        >
          <LogOut className="w-5 h-5" />
        </button>
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
                  <p className="text-xs text-neutral-500 uppercase tracking-wider font-bold">Anlık Durum</p>
                  <h3 className="text-2xl font-bold flex items-center gap-2">
                    {activeShift ? (
                      <>
                        <Zap className="w-6 h-6 text-orange-500 fill-orange-500" />
                        Sürüşte
                      </>
                    ) : (
                      <>
                        <History className="w-6 h-6 text-neutral-500" />
                        Beklemede
                      </>
                    )}
                  </h3>
                </div>
                {activeShift && (
                  <div className="bg-orange-500/10 text-orange-500 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-tighter border border-orange-500/20">
                    GPS Aktif
                  </div>
                )}
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="bg-black/40 p-4 rounded-2xl border border-neutral-800/50">
                  <p className="text-[10px] text-neutral-500 uppercase font-bold mb-1">Hız</p>
                  <p className="text-2xl font-mono font-bold">
                    {currentLocation?.speed ? Math.round(currentLocation.speed * 3.6) : 0}
                    <span className="text-xs text-neutral-600 ml-1">km/h</span>
                  </p>
                </div>
                <div className="bg-black/40 p-4 rounded-2xl border border-neutral-800/50">
                  <p className="text-[10px] text-neutral-500 uppercase font-bold mb-1">Yön</p>
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
                    <label className="text-xs text-neutral-500 uppercase font-bold ml-2">Başlangıç Kilometresi</label>
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
                    disabled={!startKm}
                    className="w-full bg-orange-500 disabled:opacity-50 text-white font-bold py-5 rounded-2xl flex items-center justify-center gap-3 shadow-lg shadow-orange-500/20 hover:bg-orange-600 transition-all active:scale-95"
                  >
                    <Play className="w-6 h-6 fill-white" />
                    İşe Başla
                  </button>
                </div>
              ) : (
                <div className="space-y-4">
                  <div className="grid grid-cols-2 gap-4 mb-4">
                    <div className="bg-neutral-900/50 p-4 rounded-2xl border border-neutral-800/50">
                      <p className="text-[10px] text-neutral-500 uppercase font-bold mb-1">Başlangıç KM</p>
                      <p className="text-xl font-mono font-bold text-neutral-300">{activeShift.startKm}</p>
                    </div>
                    <div className="bg-neutral-900/50 p-4 rounded-2xl border border-neutral-800/50">
                      <p className="text-[10px] text-neutral-500 uppercase font-bold mb-1">Durum</p>
                      <p className="text-xl font-mono font-bold text-orange-500">Takipte</p>
                    </div>
                  </div>
                  <div className="space-y-2">
                    <label className="text-xs text-neutral-500 uppercase font-bold ml-2">Bitiş Kilometresi</label>
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
                    disabled={!endKm}
                    className="w-full bg-white disabled:opacity-50 text-black font-bold py-5 rounded-2xl flex items-center justify-center gap-3 shadow-lg hover:bg-neutral-200 transition-all active:scale-95"
                  >
                    <Square className="w-6 h-6 fill-black" />
                    Vardiyayı Bitir
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
                  <p className="text-[10px] text-neutral-500 uppercase font-bold tracking-wider">Mevcut Konum</p>
                  <p className="text-sm text-neutral-300 truncate font-mono">
                    {currentLocation ? `${currentLocation.latitude.toFixed(4)}, ${currentLocation.longitude.toFixed(4)}` : 'Konum aranıyor...'}
                  </p>
                </div>
              </div>
            </section>
          </div>
        )}

        {currentView === 'history' && (
          <div className="space-y-6">
            <h3 className="text-xl font-bold">Vardiya Geçmişi</h3>
            {history.length === 0 ? (
              <div className="text-center py-12 text-neutral-500">
                <History className="w-12 h-12 mx-auto mb-4 opacity-20" />
                <p>Henüz tamamlanmış vardiya yok.</p>
              </div>
            ) : (
              <div className="space-y-4">
                {history.map(shift => (
                  <div key={shift.id} className="bg-neutral-900 p-5 rounded-2xl border border-neutral-800 flex justify-between items-center">
                    <div className="space-y-1">
                      <p className="text-xs text-neutral-500 font-bold">
                        {new Date(shift.startTime?.toDate?.() || shift.startTime).toLocaleDateString('tr-TR')}
                      </p>
                      <p className="font-bold text-lg">
                        {(shift.endKm || 0) - shift.startKm} <span className="text-xs text-neutral-500">km</span>
                      </p>
                    </div>
                    <div className="text-right">
                      <p className="text-[10px] text-neutral-500 uppercase font-bold">Kilometre</p>
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
                <h3 className="text-2xl font-bold">{profile?.displayName}</h3>
                <p className="text-neutral-500">{profile?.email}</p>
              </div>
            </div>
            
            <div className="bg-neutral-900 rounded-3xl p-6 border border-neutral-800 space-y-4">
              <div className="flex justify-between items-center py-2 border-b border-neutral-800">
                <span className="text-neutral-400">Rol</span>
                <span className="font-bold uppercase text-xs bg-orange-500/10 text-orange-500 px-2 py-1 rounded-md">{profile?.role}</span>
              </div>
              <div className="flex justify-between items-center py-2 border-b border-neutral-800">
                <span className="text-neutral-400">Cihaz Durumu</span>
                <span className="text-green-500 text-xs font-bold">ÇEVRİMİÇİ</span>
              </div>
              <div className="flex justify-between items-center py-2">
                <span className="text-neutral-400">Uygulama Versiyonu</span>
                <span className="text-neutral-500 text-xs">v1.0.0</span>
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
                <span className="text-xs font-bold">Senkronizasyon Bekliyor ({pendingTelemetry.length})</span>
              </div>
              <span className="text-[10px] uppercase font-bold opacity-80">Offline Mod</span>
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
                <h3 className="text-2xl font-bold">Vardiya Tamamlandı</h3>
                <p className="text-neutral-400 text-sm">Harika iş çıkardınız!</p>
              </div>
              
              <div className="grid grid-cols-2 gap-4 py-4">
                <div className="space-y-1">
                  <p className="text-[10px] text-neutral-500 uppercase font-bold">Toplam Yol</p>
                  <p className="text-2xl font-bold">{(showSummary.endKm || 0) - showSummary.startKm} <span className="text-xs">km</span></p>
                </div>
                <div className="space-y-1">
                  <p className="text-[10px] text-neutral-500 uppercase font-bold">Süre</p>
                  <p className="text-2xl font-bold">Tamam</p>
                </div>
              </div>

              <button 
                onClick={() => setShowSummary(null)}
                className="w-full bg-white text-black font-bold py-4 rounded-2xl hover:bg-neutral-200 transition-colors"
              >
                Kapat
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
  );
}
