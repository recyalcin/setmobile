import { Injectable, Inject } from '@nestjs/common';
import { PrismaService } from '../prisma.service';
import { Decimal } from '@prisma/client/runtime/library';

@Injectable()
export class ShiftsService {
  constructor(
    @Inject(PrismaService) private readonly prisma: PrismaService
  ) {
    console.log('ShiftsService initialized. Prisma is:', !!this.prisma);
  }

  async createShift(userId: number, startKm: number) {
    // Find driver linked to this user
    const user = await this.prisma.user.findUnique({
      where: { id: userId },
      include: { person: { include: { drivers: true, employees: true } } }
    });

    if (!user?.person?.employees?.[0]) throw new Error('Employee record not found');
    
    return this.prisma.workingHours.create({
      data: {
        employeeid: user.person.employees[0].id,
        workstartat: new Date(),
        hours0004: new Decimal(startKm), // Using hours0004 as startKm for now as per schema mapping
        date: new Date()
      },
    });
  }

  async endShift(shiftId: number, endKm: number) {
    return this.prisma.workingHours.update({
      where: { id: shiftId },
      data: {
        workendat: new Date(),
        hours2006: new Decimal(endKm), // Using hours2006 as endKm
      },
    });
  }

  async getActiveShift(userId: number) {
    const user = await this.prisma.user.findUnique({
      where: { id: userId },
      include: { person: { include: { employees: true } } }
    });
    if (!user?.person?.employees?.[0]) return null;

    const wh = await this.prisma.workingHours.findFirst({
      where: { 
        employeeid: user.person.employees[0].id,
        workendat: null 
      },
    });

    if (!wh) return null;

    // Map back to the frontend expected format
    return {
      id: wh.id,
      startKm: Number(wh.hours0004),
      startTime: wh.workstartat,
      status: 'active'
    };
  }

  async getHistory(userId: number) {
    const user = await this.prisma.user.findUnique({
      where: { id: userId },
      include: { person: { include: { employees: true } } }
    });
    if (!user?.person?.employees?.[0]) return [];

    const history = await this.prisma.workingHours.findMany({
      where: { 
        employeeid: user.person.employees[0].id,
        workendat: { not: null }
      },
      orderBy: { workstartat: 'desc' },
      take: 20,
    });

    return history.map(wh => ({
      id: wh.id,
      startTime: wh.workstartat,
      endTime: wh.workendat,
      startKm: Number(wh.hours0004),
      endKm: Number(wh.hours2006),
      status: 'completed'
    }));
  }

  async addTelemetry(data: any) {
    const user = await this.prisma.user.findUnique({
      where: { id: data.driverId },
      include: { person: { include: { drivers: true } } }
    });

    return this.prisma.vehicleLocation.create({
      data: {
        driverid: user?.person?.drivers?.[0]?.id,
        tripid: data.shiftId,
        lat: new Decimal(data.latitude),
        lng: new Decimal(data.longitude),
        speed: new Decimal(data.speed),
        heading: data.heading,
        datetime: new Date(data.timestamp)
      },
    });
  }

  // Admin Methods
  async getAllActiveShifts() {
    return this.prisma.workingHours.findMany({
      where: { workendat: null },
      include: {
        employee: {
          include: {
            person: true
          }
        }
      }
    });
  }

  async getAllHistory() {
    return this.prisma.workingHours.findMany({
      orderBy: { workstartat: 'desc' },
      take: 100,
      include: {
        employee: {
          include: {
            person: true
          }
        }
      }
    });
  }

  async getShiftTelemetry(shiftId: number) {
    return this.prisma.vehicleLocation.findMany({
      where: { tripid: shiftId },
      orderBy: { datetime: 'asc' }
    });
  }
}
