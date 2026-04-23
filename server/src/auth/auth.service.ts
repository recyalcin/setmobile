import { Injectable, UnauthorizedException, Inject } from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';
import { PrismaService } from '../prisma.service';
import bcrypt from 'bcryptjs';
import { Decimal } from '@prisma/client/runtime/library';

@Injectable()
export class AuthService {
  constructor(
    @Inject(PrismaService) private readonly prisma: PrismaService,
    @Inject(JwtService) private readonly jwtService: JwtService,
  ) {}

  private normalizeRole(roleName?: string | null): 'admin' | 'driver' {
    const normalized = (roleName || '').trim().toLocaleLowerCase('tr-TR');
    return normalized === 'admin' ? 'admin' : 'driver';
  }

  private async findActivityTypeId(name: string): Promise<number | null> {
    const activityType = await this.prisma.driverActivityType.findFirst({
      where: { name },
      select: { id: true },
    });
    return activityType?.id ?? null;
  }

  private async logAuthActivity(
    user: any,
    activityTypeName: string,
    meta?: {
      latitude?: number | null;
      longitude?: number | null;
      speed?: number | null;
      heading?: number | null;
      note?: string | null;
    },
  ) {
    const activityTypeId = await this.findActivityTypeId(activityTypeName);
    if (!activityTypeId || !user?.personid) return;

    const driver = await this.prisma.driver.findFirst({
      where: { personid: user.personid },
      select: { id: true },
    });

    await this.prisma.driverActivity.create({
      data: {
        driveractivitytypeid: activityTypeId,
        driverid: driver?.id ?? null,
        personid: user.personid,
        datetime: new Date(),
        lat: meta?.latitude != null ? new Decimal(meta.latitude) : null,
        lng: meta?.longitude != null ? new Decimal(meta.longitude) : null,
        speed: meta?.speed != null ? new Decimal(meta.speed) : null,
        heading: meta?.heading != null ? new Decimal(meta.heading) : null,
        note: meta?.note ?? null,
        createddate: new Date(),
        createdat: new Date(),
      },
    });
  }

  async validateUser(username: string, pass: string): Promise<any> {
    const user = await this.prisma.user.findUnique({
      where: { username },
      include: { person: true, appuserrole: true },
    });
    if (!user) return null;
    if (!user.active) return null;
    const isMatch = await bcrypt.compare(pass, user.password);
    if (!isMatch) return null;
    const { password, ...result } = user;
    return result;
  }

  async login(user: any) {
    const roleName = user.appuserrole?.name ?? null;
    const role = this.normalizeRole(roleName);
    const payload = {
      username: user.username,
      sub: user.id,
      role,
      roleName,
      displayName: user.person
        ? `${user.person.firstname || ''} ${user.person.lastname || ''}`.trim()
        : user.username,
    };
    return {
      access_token: this.jwtService.sign(payload),
      user: {
        id: user.id,
        username: user.username,
        displayName: payload.displayName || user.username,
        role: payload.role,
        roleName: payload.roleName,
      },
    };
  }

  async loginWithActivity(
    user: any,
    meta?: {
      latitude?: number | null;
      longitude?: number | null;
      speed?: number | null;
      heading?: number | null;
    },
  ) {
    const response = await this.login(user);
    await this.logAuthActivity(user, 'Login', meta);
    return response;
  }

  async logout(
    userId: number,
    meta?: {
      latitude?: number | null;
      longitude?: number | null;
      speed?: number | null;
      heading?: number | null;
    },
  ) {
    const user = await this.prisma.user.findUnique({
      where: { id: userId },
      include: { person: true, appuserrole: true },
    });
    if (!user) return { success: true };

    await this.logAuthActivity(user, 'Logout', meta);
    return { success: true };
  }

  async register(data: any) {
    if (!data.username || !data.password) {
      throw new UnauthorizedException('Kullanıcı adı ve şifre zorunludur');
    }
    try {
      const hashedPassword = await bcrypt.hash(data.password, 10);
      const driverRole = await this.prisma.appUserRole.findFirst({
        where: { name: 'Fahrer' },
        select: { id: true },
      });

      const person = await this.prisma.person.create({
        data: {
          firstname: data.displayName?.split(' ')[0] || 'Yeni',
          lastname: data.displayName?.split(' ').slice(1).join(' ') || 'Kullanıcı',
          email: data.email || null,
          persontypeid: 2,
        },
      });

      const user = await this.prisma.user.create({
        data: {
          username: data.username,
          password: hashedPassword,
          personid: person.id,
          active: true,
          appuserroleid: driverRole?.id ?? null,
        },
        include: { person: true, appuserrole: true },
      });

      await this.prisma.driver.create({ data: { personid: person.id } });
      await this.prisma.employee.create({
        data: { personid: person.id, employeenumber: `EMP-${Date.now()}` },
      });

      const { password, ...result } = user;
      return result;
    } catch (error: any) {
      if (error.code === 'P2002') {
        throw new UnauthorizedException('Bu kullanıcı adı zaten kullanılıyor');
      }
      throw error;
    }
  }
}
