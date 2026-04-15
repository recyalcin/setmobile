import { Injectable, UnauthorizedException, Inject } from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';
import { PrismaService } from '../prisma.service';
import bcrypt from 'bcryptjs';

@Injectable()
export class AuthService {
  constructor(
    @Inject(PrismaService) private readonly prisma: PrismaService,
    @Inject(JwtService) private readonly jwtService: JwtService,
  ) {
    console.log('AuthService initialized. Prisma is:', !!this.prisma);
  }

  async validateUser(username: string, pass: string): Promise<any> {
    console.log(`Validating user: ${username}`);
    const user = await this.prisma.user.findUnique({ 
      where: { username: username },
      include: { person: true }
    });
    if (!user) {
      console.log(`User not found: ${username}`);
      return null;
    }
    
    const isMatch = await bcrypt.compare(pass, user.password);
    console.log(`Password match for ${username}: ${isMatch}`);
    
    if (isMatch) {
      const { password, ...result } = user;
      return result;
    }
    return null;
  }

  async login(user: any) {
    const payload = { 
      username: user.username, 
      sub: user.id, 
      role: user.person?.persontypeid === 1 ? 'admin' : 'driver' 
    };
    return {
      access_token: this.jwtService.sign(payload),
      user: {
        id: user.id,
        username: user.username,
        displayName: user.person ? `${user.person.firstname} ${user.person.lastname}` : user.username,
        role: payload.role,
      },
    };
  }

  async register(data: any) {
    if (!data.username || !data.password) {
      throw new UnauthorizedException('Username and password are required');
    }
    try {
      const hashedPassword = await bcrypt.hash(data.password, 10);
      
      // Create Person first
      const person = await this.prisma.person.create({
        data: {
          firstname: data.displayName?.split(' ')[0] || 'New',
          lastname: data.displayName?.split(' ').slice(1).join(' ') || 'User',
          email: data.email || null,
          persontypeid: 2 // Driver type
        }
      });

      // Create User linked to Person
      const user = await this.prisma.user.create({
        data: {
          username: data.username,
          password: hashedPassword,
          personid: person.id,
          active: true
        },
        include: { person: true }
      });

      // Also create Driver record
      await this.prisma.driver.create({
        data: {
          personid: person.id
        }
      });

      // Also create Employee record for working hours tracking
      await this.prisma.employee.create({
        data: {
          personid: person.id,
          employeenumber: `EMP-${Date.now()}`
        }
      });

      const { password, ...result } = user;
      return result;
    } catch (error: any) {
      if (error.code === 'P2002') {
        throw new UnauthorizedException('User with this username already exists');
      }
      throw error;
    }
  }

  async findUserById(id: number) {
    return this.prisma.user.findUnique({ 
      where: { id },
      include: { person: true }
    });
  }
}
