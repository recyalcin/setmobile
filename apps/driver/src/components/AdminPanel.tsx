import React, { useState, useEffect, useMemo } from 'react';
import { 
  LayoutDashboard, 
  Truck, 
  Users, 
  Clock, 
  Building2, 
  Plus, 
  Trash2, 
  Edit2, 
  Search,
  ChevronRight,
  Database,
  ArrowLeft,
  Map as MapIcon,
  Link,
  History,
  AlertTriangle,
  Settings as SettingsIcon,
  Navigation,
  Activity,
  Filter,
  Maximize2,
  X,
  CheckCircle2,
  XCircle,
  Info
} from 'lucide-react';
import { motion, AnimatePresence } from 'motion/react';
import { MapContainer, TileLayer, Marker, Popup, Polyline, useMap } from 'react-leaflet';
import L from 'leaflet';

// Fix Leaflet marker icon issue
const DefaultIcon = L.icon({
    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41]
});
L.Marker.prototype.options.icon = DefaultIcon;

interface AdminPanelProps {
  onBack: () => void;
  apiFetch: (path: string, options?: any) => Promise<any>;
}

type AdminTab = 'dashboard' | 'map' | 'drivers' | 'vehicles' | 'assignments' | 'sessions' | 'logs' | 'alerts' | 'settings';

export default function AdminPanel({ onBack, apiFetch }: AdminPanelProps) {
  const [activeTab, setActiveTab] = useState<AdminTab>('dashboard');
  const [data, setData] = useState<any[]>([]);
  const [stats, setStats] = useState<any>(null);
  const [loading, setLoading] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedItem, setSelectedItem] = useState<any>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingRecord, setEditingRecord] = useState<any>(null);
  const [formData, setFormData] = useState<any>({});
  const [lookups, setLookups] = useState<any>({});

  const tabs: { id: AdminTab, name: string, icon: any }[] = [
    { id: 'dashboard', name: 'Dashboard', icon: LayoutDashboard },
    { id: 'map', name: 'Canlı Harita', icon: MapIcon },
    { id: 'drivers', name: 'Sürücüler', icon: Users },
    { id: 'vehicles', name: 'Araçlar', icon: Truck },
    { id: 'assignments', name: 'Atamalar', icon: Link },
    { id: 'sessions', name: 'Vardiyalar', icon: Clock },
    { id: 'logs', name: 'Konum Logları', icon: Activity },
    { id: 'alerts', name: 'Alarmlar', icon: AlertTriangle },
    { id: 'settings', name: 'Ayarlar', icon: SettingsIcon },
  ];

  const fetchData = async () => {
    setLoading(true);
    try {
      // Fetch lookups once
      if (Object.keys(lookups).length === 0) {
        const lks = await apiFetch('/admin/lookups');
        setLookups(lks);
      }

      if (activeTab === 'dashboard') {
        const res = await apiFetch('/admin/stats/dashboard');
        setStats(res);
      } else if (activeTab === 'map') {
        const res = await apiFetch('/admin/stats/map');
        setData(res);
      } else {
        const tableMap: Record<string, string> = {
          drivers: 'driver',
          vehicles: 'vehicle',
          assignments: 'vehicleAssignment',
          sessions: 'workingHours',
          logs: 'vehicleLocation',
          alerts: 'ticket',
          settings: 'user'
        };
        const res = await apiFetch(`/admin/db/${tableMap[activeTab]}`);
        setData(res);
      }
    } catch (err) {
      console.error('Fetch error:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
    setSelectedItem(null);
  }, [activeTab]);

  const handleSave = async () => {
    const tableMap: Record<string, string> = {
      drivers: 'driver',
      vehicles: 'vehicle',
      assignments: 'vehicleAssignment',
      sessions: 'workingHours',
      logs: 'vehicleLocation',
      alerts: 'ticket',
      settings: 'user'
    };
    const table = tableMap[activeTab];
    try {
      if (editingRecord) {
        await apiFetch(`/admin/db/${table}/${editingRecord.id}`, {
          method: 'PUT',
          body: JSON.stringify(formData)
        });
      } else {
        await apiFetch(`/admin/db/${table}`, {
          method: 'POST',
          body: JSON.stringify(formData)
        });
      }
      setIsModalOpen(false);
      fetchData();
    } catch (err) {
      console.error('Save error:', err);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Bu kaydı silmek istediğinize emin misiniz?')) return;
    const tableMap: Record<string, string> = {
      drivers: 'driver',
      vehicles: 'vehicle',
      assignments: 'vehicleAssignment',
      sessions: 'workingHours',
      logs: 'vehicleLocation',
      alerts: 'ticket',
      settings: 'user'
    };
    try {
      await apiFetch(`/admin/db/${tableMap[activeTab]}/${id}`, {
        method: 'DELETE'
      });
      fetchData();
    } catch (err) {
      console.error('Delete error:', err);
    }
  };

  const openModal = (record: any = null) => {
    setEditingRecord(record);
    setFormData(record || {});
    setIsModalOpen(true);
  };

  return (
    <div className="min-h-screen bg-neutral-950 text-white flex flex-col lg:flex-row font-sans">
      {/* Sidebar */}
      <aside className="w-full lg:w-64 bg-neutral-900 border-r border-neutral-800 flex flex-col z-20">
        <div className="p-6 border-b border-neutral-800 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center">
              <Navigation className="w-5 h-5 text-white" />
            </div>
            <h1 className="font-bold text-lg tracking-tight">FleetAdmin</h1>
          </div>
          <button onClick={onBack} className="lg:hidden p-2 text-neutral-500">
            <ArrowLeft className="w-5 h-5" />
          </button>
        </div>

        <nav className="flex-1 p-4 space-y-1 overflow-y-auto">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all group ${
                activeTab === tab.id 
                  ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/20' 
                  : 'text-neutral-400 hover:bg-neutral-800 hover:text-white'
              }`}
            >
              <tab.icon className={`w-5 h-5 ${activeTab === tab.id ? '' : 'group-hover:scale-110 transition-transform'}`} />
              <span className="font-medium text-sm">{tab.name}</span>
            </button>
          ))}
        </nav>

        <div className="p-4 border-t border-neutral-800">
          <button 
            onClick={onBack}
            className="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-neutral-500 hover:text-white transition-colors text-sm"
          >
            <ArrowLeft className="w-5 h-5" />
            <span>Sürücü Uygulaması</span>
          </button>
        </div>
      </aside>

      {/* Main Content */}
      <main className="flex-1 flex flex-col min-w-0 relative">
        {/* Topbar */}
        <header className="h-16 border-b border-neutral-900 bg-neutral-950/50 backdrop-blur-md flex items-center justify-between px-8 sticky top-0 z-10">
          <div className="flex items-center gap-4">
            <h2 className="text-lg font-bold">{tabs.find(t => t.id === activeTab)?.name}</h2>
            {loading && <Clock className="w-4 h-4 text-orange-500 animate-spin" />}
          </div>
          
          <div className="flex items-center gap-4">
            <div className="relative hidden md:block">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-500" />
              <input 
                type="text"
                placeholder="Hızlı ara..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="bg-neutral-900 border border-neutral-800 rounded-lg py-1.5 pl-10 pr-4 text-xs focus:outline-none focus:border-orange-500 w-48 transition-all focus:w-64"
              />
            </div>
            {['drivers', 'vehicles', 'assignments', 'alerts', 'settings'].includes(activeTab) && (
              <button 
                onClick={() => openModal()}
                className="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-2 transition-colors"
              >
                <Plus className="w-4 h-4" />
                Yeni
              </button>
            )}
          </div>
        </header>

        <div className="flex-1 overflow-auto">
          {activeTab === 'dashboard' && stats && (
            <DashboardView stats={stats} />
          )}

          {activeTab === 'map' && (
            <LiveMapView data={data} selectedItem={selectedItem} setSelectedItem={setSelectedItem} />
          )}

          {['drivers', 'vehicles', 'assignments', 'sessions', 'logs', 'alerts', 'settings'].includes(activeTab) && (
            <DataTableView 
              activeTab={activeTab} 
              data={data} 
              loading={loading} 
              onEdit={openModal} 
              onDelete={handleDelete}
              onSelect={setSelectedItem}
            />
          )}
        </div>

        {/* Detail Panel */}
        <AnimatePresence>
          {selectedItem && (
            <DetailPanel 
              item={selectedItem} 
              onClose={() => setSelectedItem(null)} 
              type={activeTab}
              lookups={lookups}
            />
          )}
        </AnimatePresence>
      </main>

      {/* CRUD Modal */}
      <AnimatePresence>
        {isModalOpen && (
          <div className="fixed inset-0 bg-black/80 backdrop-blur-sm z-[100] flex items-center justify-center p-6">
            <motion.div 
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.95 }}
              className="bg-neutral-900 w-full max-w-lg rounded-3xl border border-neutral-800 overflow-hidden shadow-2xl"
            >
              <div className="p-6 border-b border-neutral-800 flex justify-between items-center">
                <h3 className="text-xl font-bold">
                  {editingRecord ? 'Kaydı Düzenle' : 'Yeni Kayıt Ekle'}
                </h3>
                <button onClick={() => setIsModalOpen(false)} className="text-neutral-500 hover:text-white">
                  <X className="w-5 h-5" />
                </button>
              </div>

              <div className="p-6 max-h-[60vh] overflow-y-auto space-y-4">
                {Object.keys(formData).length > 0 ? Object.keys(formData).filter(k => k !== 'id' && typeof formData[k] !== 'object').map(key => {
                  const isIdField = key.toLowerCase().endsWith('id');
                  const lookupKey = key.toLowerCase().replace('id', 's'); // e.g. driverid -> drivers
                  const options = lookups[lookupKey] || lookups[key.toLowerCase().replace('id', 's') + 's'] || []; // handle pluralization variations if any

                  // Special mapping for common fields
                  let finalOptions = options;
                  if (key === 'personid') finalOptions = lookups.persons;
                  if (key === 'driverid') finalOptions = lookups.drivers;
                  if (key === 'vehicleid') finalOptions = lookups.vehicles;
                  if (key === 'makeid') finalOptions = lookups.makes;
                  if (key === 'modelid') finalOptions = lookups.models;
                  if (key === 'colorid') finalOptions = lookups.colors;

                  return (
                    <div key={key} className="space-y-1">
                      <label className="text-xs text-neutral-500 uppercase font-bold">{key}</label>
                      {isIdField && finalOptions ? (
                        <select
                          value={formData[key] || ''}
                          onChange={(e) => setFormData({ ...formData, [key]: Number(e.target.value) })}
                          className="w-full bg-neutral-950 border border-neutral-800 rounded-xl py-3 px-4 focus:outline-none focus:border-orange-500 transition-colors appearance-none"
                        >
                          <option value="">Seçiniz...</option>
                          {finalOptions.map((opt: any) => (
                            <option key={opt.id} value={opt.id}>{opt.label}</option>
                          ))}
                        </select>
                      ) : (
                        <input 
                          type="text"
                          value={formData[key] || ''}
                          onChange={(e) => setFormData({ ...formData, [key]: e.target.value })}
                          className="w-full bg-neutral-950 border border-neutral-800 rounded-xl py-3 px-4 focus:outline-none focus:border-orange-500 transition-colors"
                        />
                      )}
                    </div>
                  );
                }) : <p className="text-neutral-500 text-sm">Form alanları yüklenemedi.</p>}
              </div>

              <div className="p-6 border-t border-neutral-800 flex gap-3">
                <button 
                  onClick={() => setIsModalOpen(false)}
                  className="flex-1 px-6 py-3 rounded-xl border border-neutral-800 font-bold hover:bg-neutral-800 transition-colors"
                >
                  İptal
                </button>
                <button 
                  onClick={handleSave}
                  className="flex-1 px-6 py-3 rounded-xl bg-orange-500 text-white font-bold hover:bg-orange-600 transition-colors"
                >
                  Kaydet
                </button>
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>
    </div>
  );
}

function DashboardView({ stats }: { stats: any }) {
  const cards = [
    { label: 'Toplam Sürücü', value: stats.totalDrivers, icon: Users, color: 'text-blue-500' },
    { label: 'Aktif Sürücü', value: stats.activeDrivers, icon: Activity, color: 'text-green-500' },
    { label: 'Toplam Araç', value: stats.totalVehicles, icon: Truck, color: 'text-orange-500' },
    { label: 'Aktif Vardiya', value: stats.activeSessions, icon: Clock, color: 'text-purple-500' },
    { label: 'Hareketli Araç', value: stats.movingVehiclesCount, icon: Zap, color: 'text-yellow-500' },
    { label: 'Dönen Araç', value: stats.returningVehiclesCount, icon: ArrowLeft, color: 'text-cyan-500' },
    { label: 'Bugün Başlayan', value: stats.sessionsStartedToday, icon: Play, color: 'text-emerald-500' },
    { label: 'Bugün Biten', value: stats.sessionsEndedToday, icon: CheckCircle2, color: 'text-red-500' },
  ];

  return (
    <div className="p-8 space-y-8">
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {cards.map((card, i) => (
          <div key={i} className="bg-neutral-900 p-6 rounded-2xl border border-neutral-800 hover:border-neutral-700 transition-all group">
            <div className="flex justify-between items-start mb-4">
              <p className="text-[10px] text-neutral-500 uppercase font-bold tracking-widest">{card.label}</p>
              <card.icon className={`w-5 h-5 ${card.color} opacity-50 group-hover:opacity-100 transition-opacity`} />
            </div>
            <p className="text-3xl font-bold">{card.value}</p>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div className="bg-neutral-900 rounded-2xl border border-neutral-800 overflow-hidden">
          <div className="p-6 border-b border-neutral-800 flex items-center justify-between">
            <h3 className="font-bold">Son Aktiviteler</h3>
            <Activity className="w-4 h-4 text-neutral-500" />
          </div>
          <div className="divide-y divide-neutral-800">
            {stats.latestActivities.map((act: any) => (
              <div key={act.id} className="p-4 flex items-center gap-4 hover:bg-neutral-800/50 transition-colors">
                <div className="w-10 h-10 bg-neutral-800 rounded-full flex items-center justify-center shrink-0">
                  <UserIcon className="w-5 h-5 text-neutral-500" />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-bold truncate">
                    {act.driver?.person?.firstname} {act.driver?.person?.lastname}
                  </p>
                  <p className="text-xs text-neutral-500 truncate">{act.note || 'Konum güncellemesi'}</p>
                </div>
                <div className="text-right shrink-0">
                  <p className="text-[10px] text-neutral-500 font-bold">{new Date(act.datetime).toLocaleTimeString()}</p>
                  <p className="text-[10px] text-orange-500 font-bold uppercase">{act.vehicle?.licenseplate}</p>
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="bg-neutral-900 rounded-2xl border border-neutral-800 overflow-hidden">
          <div className="p-6 border-b border-neutral-800 flex items-center justify-between">
            <h3 className="font-bold">Aktif Sürücüler</h3>
            <Users className="w-4 h-4 text-neutral-500" />
          </div>
          <div className="p-6 text-center text-neutral-500 italic text-sm">
            Canlı harita üzerinden detaylı takip yapabilirsiniz.
          </div>
        </div>
      </div>
    </div>
  );
}

function LiveMapView({ data, selectedItem, setSelectedItem }: { data: any[], selectedItem: any, setSelectedItem: (i: any) => void }) {
  const center: [number, number] = [41.0082, 28.9784]; // Istanbul
  const [showHistory, setShowHistory] = useState(false);
  const [mapFilter, setMapFilter] = useState<'all' | 'active' | 'moving'>('all');

  const filteredData = useMemo(() => {
    if (mapFilter === 'all') return data;
    if (mapFilter === 'active') return data.filter(loc => loc.speed > 0 || loc.driver?.workingHours?.some((sh: any) => !sh.workendat));
    if (mapFilter === 'moving') return data.filter(loc => loc.speed > 0);
    return data;
  }, [data, mapFilter]);

  return (
    <div className="h-full relative flex">
      <div className="flex-1 z-0">
        <MapContainer center={center} zoom={11} style={{ height: '100%', width: '100%', background: '#0a0a0a' }}>
          <TileLayer
            url="https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png"
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
          />
          {filteredData.map((loc) => {
            const rotation = loc.heading || 0;
            const icon = L.divIcon({
              className: 'custom-marker',
              html: `<div style="transform: rotate(${rotation}deg); color: #f97316;">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="white" stroke-width="2">
                        <path d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z" />
                      </svg>
                    </div>`,
              iconSize: [24, 24],
              iconAnchor: [12, 12]
            });

            return (
              <Marker 
                key={loc.id} 
                position={[Number(loc.lat), Number(loc.lng)]}
                icon={icon}
                eventHandlers={{
                  click: () => setSelectedItem(loc),
                }}
              >
                <Popup>
                  <div className="text-black p-2">
                    <p className="font-bold">{loc.driver?.person?.firstname} {loc.driver?.person?.lastname}</p>
                    <p className="text-xs">{loc.vehicle?.licenseplate}</p>
                    <p className="text-xs font-bold text-orange-500 mt-1">{Math.round(loc.speed)} km/h</p>
                  </div>
                </Popup>
              </Marker>
            );
          })}
          {showHistory && selectedItem && (
            <Polyline 
              positions={[[Number(selectedItem.lat), Number(selectedItem.lng)], [41.01, 28.98]]} // Mock history
              color="#f97316"
              weight={3}
              opacity={0.6}
            />
          )}
        </MapContainer>
      </div>

      {/* Map Filters Overlay */}
      <div className="absolute top-4 left-4 z-[400] flex flex-col gap-2">
        <div className="bg-neutral-900/90 backdrop-blur-md border border-neutral-800 rounded-xl p-2 flex gap-2 shadow-2xl">
          <button 
            onClick={() => setMapFilter('all')}
            className={`px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase transition-colors ${mapFilter === 'all' ? 'bg-orange-500 text-white' : 'text-neutral-400 hover:text-white'}`}
          >
            Hepsi
          </button>
          <button 
            onClick={() => setMapFilter('active')}
            className={`px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase transition-colors ${mapFilter === 'active' ? 'bg-orange-500 text-white' : 'text-neutral-400 hover:text-white'}`}
          >
            Aktif
          </button>
          <button 
            onClick={() => setMapFilter('moving')}
            className={`px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase transition-colors ${mapFilter === 'moving' ? 'bg-orange-500 text-white' : 'text-neutral-400 hover:text-white'}`}
          >
            Hareketli
          </button>
        </div>
        <div className="bg-neutral-900/90 backdrop-blur-md border border-neutral-800 rounded-xl p-2 flex gap-2 shadow-2xl">
          <button 
            onClick={() => setShowHistory(!showHistory)}
            className={`px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase transition-colors ${showHistory ? 'bg-orange-500 text-white' : 'text-neutral-400 hover:text-white'}`}
          >
            Geçmiş İzlemi {showHistory ? 'Açık' : 'Kapalı'}
          </button>
        </div>
      </div>
    </div>
  );
}

function DataTableView({ activeTab, data, loading, onEdit, onDelete, onSelect }: any) {
  const columnsMap: Record<string, string[]> = {
    drivers: ['Ad Soyad', 'Kullanıcı Adı', 'Telefon', 'Durum', 'Atanan Araç', 'Aktif Vardiya'],
    vehicles: ['Plaka', 'Marka/Model', 'Renk', 'Durum', 'Atanan Sürücü', 'Aktif Vardiya'],
    assignments: ['Sürücü', 'Araç', 'Atama Tarihi', 'Bitiş Tarihi'],
    sessions: ['Sürücü', 'Araç', 'Durum', 'Başlangıç', 'Bitiş', 'KM'],
    logs: ['Sürücü', 'Araç', 'Hız', 'Koordinat', 'Zaman'],
    alerts: ['Konu', 'Öncelik', 'Durum', 'Zaman'],
    settings: ['Kullanıcı Adı', 'Rol', 'Durum']
  };

  const columns = columnsMap[activeTab] || [];

  return (
    <div className="p-8">
      <div className="bg-neutral-900 rounded-2xl border border-neutral-800 overflow-hidden shadow-xl">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-neutral-950 text-neutral-500 uppercase text-[10px] font-bold tracking-widest">
              <tr>
                <th className="px-6 py-4">ID</th>
                {columns.map(col => <th key={col} className="px-6 py-4">{col}</th>)}
                <th className="px-6 py-4 text-right">İşlemler</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-800">
              {loading ? (
                <tr>
                  <td colSpan={columns.length + 2} className="px-6 py-12 text-center">
                    <Loader2 className="w-6 h-6 animate-spin mx-auto text-orange-500 mb-2" />
                    <span className="text-neutral-500 text-xs font-bold uppercase tracking-widest">Yükleniyor...</span>
                  </td>
                </tr>
              ) : data.length === 0 ? (
                <tr>
                  <td colSpan={columns.length + 2} className="px-6 py-12 text-center text-neutral-500 text-xs font-bold uppercase tracking-widest">
                    Veri bulunamadı.
                  </td>
                </tr>
              ) : (
                data.map((row) => (
                  <tr 
                    key={row.id} 
                    onClick={() => onSelect(row)}
                    className="hover:bg-neutral-800/50 transition-colors group cursor-pointer"
                  >
                    <td className="px-6 py-4 font-mono text-orange-500 text-xs">#{row.id}</td>
                    {activeTab === 'drivers' && (
                      <>
                        <td className="px-6 py-4 font-bold">{row.person?.firstname} {row.person?.lastname}</td>
                        <td className="px-6 py-4 text-neutral-400">{row.username || row.person?.email}</td>
                        <td className="px-6 py-4 text-neutral-400">{row.person?.phone || '-'}</td>
                        <td className="px-6 py-4">
                          <span className={`px-2 py-1 rounded-md text-[10px] font-bold uppercase ${row.active !== false ? 'bg-green-500/10 text-green-500' : 'bg-red-500/10 text-red-500'}`}>
                            {row.active !== false ? 'Aktif' : 'Pasif'}
                          </span>
                        </td>
                        <td className="px-6 py-4 text-neutral-400">
                          {row.assignments?.[0]?.vehicle?.licenseplate || '-'}
                        </td>
                        <td className="px-6 py-4 text-neutral-400">
                          {row.workingHours?.some((sh: any) => !sh.workendat) ? 'Evet' : 'Hayır'}
                        </td>
                      </>
                    )}
                    {activeTab === 'vehicles' && (
                      <>
                        <td className="px-6 py-4 font-bold">{row.licenseplate}</td>
                        <td className="px-6 py-4 text-neutral-400">Araç #{row.id}</td>
                        <td className="px-6 py-4 text-neutral-400">Gümüş</td>
                        <td className="px-6 py-4">
                          <span className="px-2 py-1 rounded-md bg-green-500/10 text-green-500 text-[10px] font-bold uppercase">Aktif</span>
                        </td>
                        <td className="px-6 py-4 text-neutral-400">
                          {row.assignments?.[0]?.driver?.person?.firstname} {row.assignments?.[0]?.driver?.person?.lastname || '-'}
                        </td>
                        <td className="px-6 py-4 text-neutral-400">Hayır</td>
                      </>
                    )}
                    {activeTab === 'assignments' && (
                      <>
                        <td className="px-6 py-4 font-bold">{row.driver?.person?.firstname} {row.driver?.person?.lastname}</td>
                        <td className="px-6 py-4 text-neutral-400">{row.vehicle?.licenseplate}</td>
                        <td className="px-6 py-4 text-neutral-400">{new Date(row.assignedat).toLocaleDateString()}</td>
                        <td className="px-6 py-4 text-neutral-400">{row.unassignedat ? new Date(row.unassignedat).toLocaleDateString() : '-'}</td>
                      </>
                    )}
                    {activeTab === 'sessions' && (
                      <>
                        <td className="px-6 py-4 font-bold">{row.employee?.person?.firstname} {row.employee?.person?.lastname}</td>
                        <td className="px-6 py-4 text-neutral-400">{row.vehicle?.licenseplate || `Araç #${row.vehicleid}`}</td>
                        <td className="px-6 py-4">
                          <span className={`px-2 py-1 rounded-md text-[10px] font-bold uppercase ${row.workendat ? 'bg-neutral-800 text-neutral-400' : 'bg-green-500/10 text-green-500'}`}>
                            {row.workendat ? 'Tamamlandı' : 'Aktif'}
                          </span>
                        </td>
                        <td className="px-6 py-4 text-neutral-400">{new Date(row.workstartat).toLocaleTimeString()}</td>
                        <td className="px-6 py-4 text-neutral-400">{row.workendat ? new Date(row.workendat).toLocaleTimeString() : '-'}</td>
                        <td className="px-6 py-4 text-neutral-400">{Number(row.hours2006 || 0) - Number(row.hours0004)} km</td>
                      </>
                    )}
                    {activeTab === 'logs' && (
                      <>
                        <td className="px-6 py-4 font-bold">{row.driver?.person?.firstname} {row.driver?.person?.lastname}</td>
                        <td className="px-6 py-4 text-neutral-400">{row.vehicle?.licenseplate}</td>
                        <td className="px-6 py-4 text-neutral-400">{Math.round(row.speed)} km/h</td>
                        <td className="px-6 py-4 text-neutral-400 font-mono text-[10px]">{Number(row.lat).toFixed(4)}, {Number(row.lng).toFixed(4)}</td>
                        <td className="px-6 py-4 text-neutral-400">{new Date(row.datetime).toLocaleTimeString()}</td>
                      </>
                    )}
                    
                    {activeTab === 'alerts' && (
                      <>
                        <td className="px-6 py-4 font-bold">{row.subject}</td>
                        <td className="px-6 py-4">
                          <span className={`px-2 py-1 rounded-md text-[10px] font-bold uppercase ${row.priorityid === 3 ? 'bg-red-500/10 text-red-500' : 'bg-yellow-500/10 text-yellow-500'}`}>
                            {row.priorityid === 3 ? 'Kritik' : 'Normal'}
                          </span>
                        </td>
                        <td className="px-6 py-4">
                          <span className="px-2 py-1 rounded-md bg-neutral-800 text-neutral-400 text-[10px] font-bold uppercase">Yeni</span>
                        </td>
                        <td className="px-6 py-4 text-neutral-400">{new Date(row.createdat).toLocaleString()}</td>
                      </>
                    )}
                    {activeTab === 'settings' && (
                      <>
                        <td className="px-6 py-4 font-bold">{row.username}</td>
                        <td className="px-6 py-4 text-neutral-400">{row.role || 'Admin'}</td>
                        <td className="px-6 py-4">
                          <span className="px-2 py-1 rounded-md bg-green-500/10 text-green-500 text-[10px] font-bold uppercase">Aktif</span>
                        </td>
                      </>
                    )}
                    
                    {/* Fallback for other tabs */}
                    {!['drivers', 'vehicles', 'assignments', 'sessions', 'logs', 'alerts', 'settings'].includes(activeTab) && columns.map((_, i) => (
                      <td key={i} className="px-6 py-4 text-neutral-400">Veri</td>
                    ))}
                    
                    <td className="px-6 py-4 text-right">
                      <div className="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button 
                          onClick={(e) => { e.stopPropagation(); onEdit(row); }}
                          className="p-2 text-neutral-400 hover:text-white hover:bg-neutral-700 rounded-lg"
                        >
                          <Edit2 className="w-4 h-4" />
                        </button>
                        <button 
                          onClick={(e) => { e.stopPropagation(); onDelete(row.id); }}
                          className="p-2 text-neutral-400 hover:text-red-500 hover:bg-red-500/10 rounded-lg"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

function DetailPanel({ item, onClose, type, lookups }: { item: any, onClose: () => void, type: string, lookups: any }) {
  const isMapItem = type === 'map';
  const title = isMapItem 
    ? `${item.driver?.person?.firstname} ${item.driver?.person?.lastname}`
    : type === 'drivers' 
      ? `${item.person?.firstname} ${item.person?.lastname}` 
      : item.licenseplate || `Kayıt #${item.id}`;

  const renderValue = (key: string, value: any) => {
    if (typeof value === 'object') return null;
    if (key.toLowerCase().endsWith('id')) {
      const lookupKey = key.toLowerCase().replace('id', 's');
      const options = lookups[lookupKey] || lookups[key.toLowerCase().replace('id', 's') + 's'] || [];
      
      let finalOptions = options;
      if (key === 'personid') finalOptions = lookups.persons;
      if (key === 'driverid') finalOptions = lookups.drivers;
      if (key === 'vehicleid') finalOptions = lookups.vehicles;
      if (key === 'makeid') finalOptions = lookups.makes;
      if (key === 'modelid') finalOptions = lookups.models;
      if (key === 'colorid') finalOptions = lookups.colors;

      const found = finalOptions?.find((o: any) => o.id === value);
      return found ? found.label : value;
    }
    if (key.toLowerCase().includes('date') || key.toLowerCase().endsWith('at')) {
      return new Date(value).toLocaleString();
    }
    return String(value);
  };

  return (
    <motion.div 
      initial={{ x: '100%' }}
      animate={{ x: 0 }}
      exit={{ x: '100%' }}
      className="absolute top-0 right-0 bottom-0 w-96 bg-neutral-900 border-l border-neutral-800 shadow-2xl z-30 flex flex-col"
    >
      <div className="p-6 border-b border-neutral-800 flex justify-between items-center">
        <h3 className="font-bold">Detay Görünümü</h3>
        <button onClick={onClose} className="text-neutral-500 hover:text-white">
          <X className="w-5 h-5" />
        </button>
      </div>

      <div className="flex-1 overflow-y-auto p-6 space-y-6">
        <div className="space-y-4">
          <div className="flex flex-col items-center text-center space-y-3">
            <div className="w-20 h-20 bg-neutral-800 rounded-2xl flex items-center justify-center border border-neutral-700">
              {type === 'drivers' || (isMapItem && item.driver) ? <UserIcon className="w-10 h-10 text-neutral-500" /> : <Truck className="w-10 h-10 text-neutral-500" />}
            </div>
            <div>
              <h4 className="text-xl font-bold">{title}</h4>
              <p className="text-xs text-neutral-500 uppercase font-bold tracking-widest mt-1">
                {isMapItem ? 'Canlı Takip' : type === 'drivers' ? 'Sürücü' : 'Araç'}
              </p>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="bg-neutral-950 p-3 rounded-xl border border-neutral-800">
              <p className="text-[10px] text-neutral-500 uppercase font-bold mb-1">Durum</p>
              <div className="flex items-center gap-2">
                <span className="w-2 h-2 bg-green-500 rounded-full animate-pulse" />
                <p className="text-xs font-bold text-green-500">Aktif</p>
              </div>
            </div>
            <div className="bg-neutral-950 p-3 rounded-xl border border-neutral-800">
              <p className="text-[10px] text-neutral-500 uppercase font-bold mb-1">Hız</p>
              <p className="text-xs font-bold">{item.speed ? Math.round(item.speed) : 0} km/h</p>
            </div>
          </div>

          {isMapItem && (
            <div className="bg-neutral-950 p-4 rounded-xl border border-neutral-800 space-y-3">
              <h5 className="text-[10px] text-neutral-500 uppercase font-bold tracking-widest border-b border-neutral-800 pb-2">Vardiya Bilgileri</h5>
              <div className="space-y-2">
                <div className="flex justify-between text-xs">
                  <span className="text-neutral-500">Başlangıç</span>
                  <span className="font-medium">{new Date(item.datetime).toLocaleTimeString()}</span>
                </div>
                <div className="flex justify-between text-xs">
                  <span className="text-neutral-500">Başlangıç KM</span>
                  <span className="font-medium">124,500</span>
                </div>
                <div className="flex justify-between text-xs">
                  <span className="text-neutral-500">Süre</span>
                  <span className="font-medium">02:45:12</span>
                </div>
              </div>
            </div>
          )}

          <div className="space-y-4">
            <h5 className="text-[10px] text-neutral-500 uppercase font-bold tracking-widest border-b border-neutral-800 pb-2">Sistem Kayıtları</h5>
            <div className="space-y-3">
              {Object.entries(item).filter(([k, v]) => typeof v !== 'object' && k !== 'id').map(([key, value]) => (
                <div key={key} className="flex justify-between items-center text-xs">
                  <span className="text-neutral-500 capitalize">{key}</span>
                  <span className="font-medium truncate ml-4">{renderValue(key, value)}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>

      <div className="p-6 border-t border-neutral-800">
        <p className="text-[10px] text-neutral-500 text-center italic">Düzenleme işlemleri için ana listedeki kalem ikonunu kullanınız.</p>
      </div>
    </motion.div>
  );
}

function UserIcon(props: any) {
  return (
    <svg
      {...props}
      xmlns="http://www.w3.org/2000/svg"
      width="24"
      height="24"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
      <circle cx="12" cy="7" r="4" />
    </svg>
  );
}

function Play(props: any) {
  return (
    <svg
      {...props}
      xmlns="http://www.w3.org/2000/svg"
      width="24"
      height="24"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <polygon points="5 3 19 12 5 21 5 3" />
    </svg>
  );
}

function Zap(props: any) {
  return (
    <svg
      {...props}
      xmlns="http://www.w3.org/2000/svg"
      width="24"
      height="24"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
    </svg>
  );
}

function Loader2(props: any) {
  return (
    <svg
      {...props}
      xmlns="http://www.w3.org/2000/svg"
      width="24"
      height="24"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d="M21 12a9 9 0 1 1-6.219-8.56" />
    </svg>
  );
}
