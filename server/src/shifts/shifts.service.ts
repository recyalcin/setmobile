import { Injectable, Inject } from '@nestjs/common';
import { PrismaService } from '../prisma.service';
import { Decimal } from '@prisma/client/runtime/library';

@Injectable()
export class ShiftsService {
  constructor(@Inject(PrismaService) private readonly prisma: PrismaService) {}

  private async getEmployeeId(userId: number): Promise<number | null> {
    const user = await this.prisma.user.findUnique({
      where: { id: userId },
      include: { person: { include: { employees: true } } },
    });
    return user?.person?.employees?.[0]?.id ?? null;
  }

  private async getDriverId(userId: number): Promise<number | null> {
    const user = await this.prisma.user.findUnique({
      where: { id: userId },
      include: { person: { include: { drivers: true } } },
    });
    return user?.person?.drivers?.[0]?.id ?? null;
  }

  async createShift(userId: number, startKm: number) {
    const employeeId = await this.getEmployeeId(userId);
    if (!employeeId) throw new Error('Çalışan kaydı bulunamadı');

    const wh = await this.prisma.workingHours.create({
      data: {
        employeeid: employeeId,
        workstartat: new Date(),
        startkm: new Decimal(startKm),
        date: new Date(),
      },
    });

    return { id: wh.id, startKm: Number(wh.startkm), startTime: wh.workstartat, status: 'active' };
  }

  async endShift(shiftId: number, endKm: number) {
    const wh = await this.prisma.workingHours.update({
      where: { id: shiftId },
      data: { workendat: new Date(), endkm: new Decimal(endKm) },
    });

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
    return this.prisma.vehicleLocation.create({
      data: {
        driverid: driverId,
        tripid: data.shiftId,
        lat: new Decimal(data.latitude),
        lng: new Decimal(data.longitude),
        speed: new Decimal(data.speed || 0),
        heading: data.heading || 0,
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
