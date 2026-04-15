import { Injectable, Inject } from '@nestjs/common';
import { PrismaService } from '../prisma.service';

@Injectable()
export class AdminService {
  constructor(@Inject(PrismaService) private readonly prisma: PrismaService) {}

  async getTableData(table: string) {
    const model = (this.prisma as any)[table];
    if (!model) throw new Error(`Table ${table} not found`);

    const includes: Record<string, any> = {
      driver: { person: true, assignments: { include: { vehicle: true } }, workingHours: { where: { workendat: null } } },
      vehicle: { assignments: { include: { driver: { include: { person: true } } } } },
      vehicleAssignment: { driver: { include: { person: true } }, vehicle: true },
      workingHours: { employee: { include: { person: true } }, vehicle: true },
      vehicleLocation: { driver: { include: { person: true } }, vehicle: true },
      driverActivity: { driver: { include: { person: true } }, vehicle: true },
      user: { person: true }
    };

    return model.findMany({
      take: 100,
      orderBy: { id: 'desc' },
      include: includes[table] || undefined
    });
  }

  async getLookups() {
    const [drivers, vehicles, makes, models, colors, persons] = await Promise.all([
      this.prisma.driver.findMany({ include: { person: true } }),
      this.prisma.vehicle.findMany(),
      this.prisma.make.findMany(),
      this.prisma.model.findMany(),
      this.prisma.color.findMany(),
      this.prisma.person.findMany()
    ]);

    return {
      drivers: drivers.map(d => ({ id: d.id, label: `${d.person?.firstname} ${d.person?.lastname}` })),
      vehicles: vehicles.map(v => ({ id: v.id, label: v.licenseplate })),
      makes: makes.map(m => ({ id: m.id, label: m.name })),
      models: models.map(m => ({ id: m.id, label: m.name })),
      colors: colors.map(c => ({ id: c.id, label: c.name })),
      persons: persons.map(p => ({ id: p.id, label: `${p.firstname} ${p.lastname}` }))
    };
  }

  async createRecord(table: string, data: any) {
    const model = (this.prisma as any)[table];
    if (!model) throw new Error(`Table ${table} not found`);
    return model.create({ data });
  }

  async updateRecord(table: string, id: number, data: any) {
    const model = (this.prisma as any)[table];
    if (!model) throw new Error(`Table ${table} not found`);
    return model.update({
      where: { id },
      data
    });
  }

  async deleteRecord(table: string, id: number) {
    const model = (this.prisma as any)[table];
    if (!model) throw new Error(`Table ${table} not found`);
    return model.delete({
      where: { id }
    });
  }

  async getDashboardStats() {
    const now = new Date();
    const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());

    const [
      totalDrivers,
      activeSessions,
      totalVehicles,
      sessionsStartedToday,
      sessionsEndedToday,
      latestActivities
    ] = await Promise.all([
      this.prisma.driver.count(),
      this.prisma.workingHours.count({ where: { workendat: null } }),
      this.prisma.vehicle.count(),
      this.prisma.workingHours.count({ where: { workstartat: { gte: startOfToday } } }),
      this.prisma.workingHours.count({ where: { workendat: { gte: startOfToday } } }),
      this.prisma.driverActivity.findMany({
        take: 10,
        orderBy: { datetime: 'desc' },
        include: { driver: { include: { person: true } }, vehicle: true }
      })
    ]);

    return {
      totalDrivers,
      activeDrivers: activeSessions,
      totalVehicles,
      activeSessions,
      movingVehiclesCount: activeSessions, // Simplified for MVP
      returningVehiclesCount: 0, // Simplified
      sessionsStartedToday,
      sessionsEndedToday,
      latestActivities
    };
  }

  async getLiveMapData() {
    return this.prisma.vehicleLocation.findMany({
      where: {
        datetime: {
          gte: new Date(Date.now() - 1000 * 60 * 60) // Last hour
        }
      },
      include: {
        driver: { include: { person: true } },
        vehicle: true
      },
      orderBy: { datetime: 'desc' }
    });
  }
}
