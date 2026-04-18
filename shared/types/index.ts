// Shared types across all Setmobile apps

export interface User {
  id: number;
  username: string;
  displayName: string;
  role: 'driver' | 'admin' | 'passenger';
}

export interface Shift {
  id: number;
  driverId?: number;
  employeeId?: number;
  startTime: string;
  endTime?: string;
  startKm: number;
  endKm?: number;
  status: 'active' | 'completed';
}

export interface Telemetry {
  id?: number;
  shiftId: number;
  driverId: number;
  timestamp: string;
  latitude: number;
  longitude: number;
  speed: number;
  heading: number;
}

export interface Vehicle {
  id: number;
  licenseplate?: string;
  makeName?: string;
  modelName?: string;
  colorName?: string;
}

export interface Driver {
  id: number;
  personId?: number;
  firstName?: string;
  lastName?: string;
}

export interface LocationPoint {
  lat: number;
  lng: number;
  speed?: number;
  heading?: number;
  timestamp?: string;
  driverId?: number;
  vehiclePlate?: string;
}
