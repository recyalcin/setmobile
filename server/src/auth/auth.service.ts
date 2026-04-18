import { Injectable, UnauthorizedException, Inject } from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';
import { PrismaService } from '../prisma.service';
import bcrypt from 'bcryptjs';

@Injectable()
export class AuthService {
  constructor(
    @Inject(PrismaService) private readonly prisma: PrismaService,
    @Inject(JwtService) private readonly jwtService: JwtService,
  ) {}

  async validateUser(username: string, pass: string): Promise<any> {
    const user = await this.prisma.user.findUnique({
      where: { username },
      include: { person: true },
    });
    if (!user) return null;
    const isMatch = await bcrypt.compare(pass, user.password);
    if (!isMatch) return null;
    const { password, ...result } = user;
    return result;
  }

  async login(user: any) {
    const isAdmin = user.person?.persontypeid === 1;
    const payload = {
      username: user.username,
      sub: user.id,
      role: isAdmin ? 'admin' : 'driver',
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
      },
    };
  }

  async register(data: any) {
    if (!data.username || !data.password) {
      throw new UnauthorizedException('Kullanıcı adı ve şifre zorunludur');
    }
    try {
      const hashedPassword = await bcrypt.hash(data.password, 10);

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
        },
        include: { person: true },
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
