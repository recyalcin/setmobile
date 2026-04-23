import { Injectable, Inject, BadRequestException, NotFoundException } from '@nestjs/common';
import { PrismaService } from '../prisma.service';
import { Decimal } from '@prisma/client/runtime/library';

@Injectable()
export class ShiftsService {
  constructor(@Inject(PrismaService) private readonly prisma: PrismaService) {}

  private async findActivityTypeId(name: string): Promise<number | null> {
    const activityType = await this.prisma.driverActivityType.findFirst({
      where: { name },
      select: { id: true },
    });
    return activityType?.id ?? null;
  }

  private async getEmployeeId(userId: number): Promise<number | null> {
    const user = await this.prisma.user.findUnique({
      where: { id: userId },
      include: { person: { include: { employees: true } } },
    });
    return user?.person?.employees?.[0]?.id ?? null;
  }

  private async getUserContext(userId: number) {
    const user = await this.prisma.user.findUnique({
      where: { id: userId },
      include: { person: { include: { drivers: true } } },
    });

    return {
      user,
      driverId: user?.person?.drivers?.[0]?.id ?? null,
      personId: user?.personid ?? null,
    };
  }

  private async getDriverId(userId: number): Promise<number | null> {
    const context = await this.getUserContext(userId);
    return context.driverId;
  }

  private async logShiftActivity(
    userId: number,
    activityTypeName: string,
    meta?: {
      odometer?: number | null;
      latitude?: number | null;
      longitude?: number | null;
      speed?: number | null;
      heading?: number | null;
      vehicleId?: number | null;
      note?: string | null;
    },
  ) {
    const activityTypeId = await this.findActivityTypeId(activityTypeName);
    if (!activityTypeId) return;

    const context = await this.getUserContext(userId);

    await this.prisma.driverActivity.create({
      data: {
        driveractivitytypeid: activityTypeId,
        driverid: context.driverId,
        personid: context.personId,
        vehicleid: meta?.vehicleId ?? null,
        datetime: new Date(),
        lat: meta?.latitude != null ? new Decimal(meta.latitude) : null,
        lng: meta?.longitude != null ? new Decimal(meta.longitude) : null,
        odometer: meta?.odometer != null ? Math.round(meta.odometer) : null,
        speed: meta?.speed != null ? new Decimal(meta.speed) : null,
        heading: meta?.heading != null ? new Decimal(meta.heading) : null,
        note: meta?.note ?? null,
        createddate: new Date(),
        createdat: new Date(),
      },
    });
  }

  async createShift(
    userId: number,
    startKm: number,
    meta?: { latitude?: number | null; longitude?: number | null; speed?: number | null; heading?: number | null },
  ) {
    const employeeId = await this.getEmployeeId(userId);
    if (!employeeId) {
      throw new BadRequestException('Bu kullanıcı için employee kaydı bulunamadı.');
    }

    const context = await this.getUserContext(userId);
    if (!context.driverId) {
      throw new BadRequestException('Bu kullanıcı için driver kaydı bulunamadı.');
    }

    const existingShift = await this.prisma.workingHours.findFirst({
      where: { employeeid: employeeId, workendat: null },
      orderBy: { workstartat: 'desc' },
    });

    if (existingShift) {
      return {
        id: existingShift.id,
        startKm: Number(existingShift.startkm ?? 0),
        startTime: existingShift.workstartat,
        status: 'active',
      };
    }

    const wh = await this.prisma.workingHours.create({
      data: {
        employeeid: employeeId,
        workstartat: new Date(),
        startkm: new Decimal(startKm),
        date: new Date(),
      },
    });

    await this.logShiftActivity(userId, 'Km Anfang', {
      odometer: startKm,
      latitude: meta?.latitude,
      longitude: meta?.longitude,
      speed: meta?.speed,
      heading: meta?.heading,
      vehicleId: wh.vehicleid,
    });

    return { id: wh.id, startKm: Number(wh.startkm), startTime: wh.workstartat, status: 'active' };
  }

  async endShift(
    shiftId: number,
    endKm: number,
    meta?: { userId?: number; latitude?: number | null; longitude?: number | null; speed?: number | null; heading?: number | null },
  ) {
    const existing = await this.prisma.workingHours.findUnique({
      where: { id: shiftId },
    });

    if (!existing) {
      throw new NotFoundException('Aktif vardiya kaydı bulunamadı.');
    }

    const wh = await this.prisma.workingHours.update({
      where: { id: shiftId },
      data: { workendat: new Date(), endkm: new Decimal(endKm) },
    });

    if (meta?.userId) {
      await this.logShiftActivity(meta.userId, 'Km Ende', {
        odometer: endKm,
        latitude: meta?.latitude,
        longitude: meta?.longitude,
        speed: meta?.speed,
        heading: meta?.heading,
        vehicleId: wh.vehicleid,
      });
    }

    return {
      id: wh.id,
      startKm: Number(wh.startkm),
      endKm: Number(wh.endkm),
      startTime: wh.workstartat,
      endTime: wh.workendat,
      status: 'completed',
    };
  }

  async getActiveShift(userId: number) {
    const employeeId = await this.getEmployeeId(userId);
    if (!employeeId) return null;

    const wh = await this.prisma.workingHours.findFirst({
      where: { employeeid: employeeId, workendat: null },
      orderBy: { workstartat: 'desc' },
    });
    if (!wh) return null;

    return { id: wh.id, startKm: Number(wh.startkm), startTime: wh.workstartat, status: 'active' };
  }

  async getHistory(userId: number) {
    const employeeId = await this.getEmployeeId(userId);
    if (!employeeId) return [];

    const history = await this.prisma.workingHours.findMany({
      where: { employeeid: employeeId, workendat: { not: null } },
      orderBy: { workstartat: 'desc' },
      take: 30,
    });

    return history.map((wh) => ({
      id: wh.id,
      startTime: wh.workstartat,
      endTime: wh.workendat,
      startKm: Number(wh.startkm ?? 0),
      endKm: Number(wh.endkm ?? 0),
      status: 'completed',
    }));
  }

  async addTelemetry(data: any) {
    const driverId = await this.getDriverId(data.driverId);
    if (!driverId) {
      throw new BadRequestException('Bu kullanıcı için driver kaydı bulunamadı.');
    }

    return this.prisma.vehicleLocation.create({
      data: {
        driverid: driverId,
        tripid: data.shiftId,
        lat: new Decimal(data.latitude),
        lng: new Decimal(data.longitude),
        speed: new Decimal(data.speed || 0),
        heading: data.heading != null ? Math.round(data.heading) : 0,
        datetime: new Date(data.timestamp),
      },
    });
  }

  async getAllActiveShifts() {
    return this.prisma.workingHours.findMany({
      where: { workendat: null },
      include: { employee: { include: { person: true } }, vehicle: true },
      orderBy: { workstartat: 'desc' },
    });
  }

  async getAllHistory() {
    return this.prisma.workingHours.findMany({
      orderBy: { workstartat: 'desc' },
      take: 100,
      include: { employee: { include: { person: true } }, vehicle: true },
    });
  }
}
