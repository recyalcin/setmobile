import { Injectable, Inject } from '@nestjs/common';
import { PrismaService } from '../prisma.service';

@Injectable()
export class AdminService {
  constructor(@Inject(PrismaService) private readonly prisma: PrismaService) {}

  async getDashboardStats() {
    const now = new Date();
    const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());

    const [
      totalDrivers,
      totalVehicles,
      activeSessions,
      sessionsStartedToday,
      sessionsEndedToday,
      latestActivities,
    ] = await Promise.all([
      this.prisma.driver.count(),
      this.prisma.vehicle.count(),
      this.prisma.workingHours.count({ where: { workendat: null } }),
      this.prisma.workingHours.count({ where: { workstartat: { gte: todayStart } } }),
      this.prisma.workingHours.count({ where: { workendat: { gte: todayStart } } }),
      this.prisma.vehicleLocation.findMany({
        take: 10,
        orderBy: { datetime: 'desc' },
        include: { driver: { include: { person: true } }, vehicle: true },
      }),
    ]);

    return {
      totalDrivers,
      activeDrivers: activeSessions,
      totalVehicles,
      activeSessions,
      sessionsStartedToday,
      sessionsEndedToday,
      movingVehiclesCount: 0,
      returningVehiclesCount: 0,
      latestActivities,
    };
  }

  async getMapData() {
    return this.prisma.vehicleLocation.findMany({
      where: { datetime: { gte: new Date(Date.now() - 30 * 60 * 1000) } },
      orderBy: { datetime: 'desc' },
      take: 200,
      distinct: ['driverid'],
      include: { driver: { include: { person: true } }, vehicle: true },
    });
  }

  async getLookups() {
    const [persons, drivers, vehicles, makes, colors] = await Promise.all([
      this.prisma.person.findMany({ select: { id: true, firstname: true, lastname: true } }),
      this.prisma.driver.findMany({ include: { person: true } }),
      this.prisma.vehicle.findMany({ select: { id: true, licenseplate: true } }),
      this.prisma.make.findMany({ select: { id: true, name: true } }),
      this.prisma.color.findMany({ select: { id: true, name: true } }),
    ]);

    return {
      persons: persons.map((p) => ({ id: p.id, label: `${p.firstname} ${p.lastname}`.trim() })),
      drivers: drivers.map((d) => ({
        id: d.id,
        label: d.person ? `${d.person.firstname} ${d.person.lastname}`.trim() : `Sürücü #${d.id}`,
      })),
      vehicles: vehicles.map((v) => ({ id: v.id, label: v.licenseplate || `Araç #${v.id}` })),
      makes: makes.map((m) => ({ id: m.id, label: m.name })),
      colors: colors.map((c) => ({ id: c.id, label: c.name })),
    };
  }

  async getAll(model: string) {
    const client = this.prisma as any;
    if (!client[model]) throw new Error(`Model bulunamadı: ${model}`);

    const includeMap: Record<string, any> = {
      driver: { person: true, assignments: { include: { vehicle: true } } },
      vehicle: { assignments: { include: { driver: { include: { person: true } } } } },
      vehicleAssignment: { driver: { include: { person: true } }, vehicle: true },
      workingHours: { employee: { include: { person: true } }, vehicle: true },
      vehicleLocation: { driver: { include: { person: true } }, vehicle: true },
      user: true,
    };

    return client[model].findMany({
      include: includeMap[model] || undefined,
      orderBy: { id: 'desc' },
      take: 200,
    });
  }

  async create(model: string, data: any) {
    const client = this.prisma as any;
    if (!client[model]) throw new Error(`Model bulunamadı: ${model}`);
    const { id, createdat, updatedat, createddate, updateddate, ...cleanData } = data;
    return client[model].create({ data: cleanData });
  }

  async update(model: string, id: number, data: any) {
    const client = this.prisma as any;
    if (!client[model]) throw new Error(`Model bulunamadı: ${model}`);
    const { id: _id, createdat, updatedat, createddate, updateddate, ...cleanData } = data;
    return client[model].update({ where: { id }, data: cleanData });
  }

  async delete(model: string, id: number) {
    const client = this.prisma as any;
    if (!client[model]) throw new Error(`Model bulunamadı: ${model}`);
    return client[model].delete({ where: { id } });
  }
}
