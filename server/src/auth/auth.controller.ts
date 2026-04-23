import { Controller, Post, Body, Get, UseGuards, Request, Inject, UnauthorizedException, HttpCode } from '@nestjs/common';
import { AuthService } from './auth.service';
import { JwtAuthGuard } from './jwt-auth.guard';

@Controller('auth')
export class AuthController {
  constructor(@Inject(AuthService) private authService: AuthService) {}

  @Post('login')
  @HttpCode(200)
  async login(@Body() body: { username: string; password: string; latitude?: number; longitude?: number; speed?: number; heading?: number }) {
    const user = await this.authService.validateUser(body.username, body.password);
    if (!user) {
      throw new UnauthorizedException('GeÃ§ersiz kullanÄ±cÄ± adÄ± veya ÅŸifre');
    }
    return this.authService.loginWithActivity(user, {
      latitude: body.latitude,
      longitude: body.longitude,
      speed: body.speed,
      heading: body.heading,
    });
  }

  @Post('register')
  async register(@Body() body: any) {
    return this.authService.register(body);
  }

  @UseGuards(JwtAuthGuard)
  @Get('profile')
  getProfile(@Request() req: any) {
    return req.user;
  }

  @UseGuards(JwtAuthGuard)
  @Post('logout')
  @HttpCode(200)
  logout(@Request() req: any, @Body() body: { latitude?: number; longitude?: number; speed?: number; heading?: number }) {
    return this.authService.logout(req.user.userId, {
      latitude: body.latitude,
      longitude: body.longitude,
      speed: body.speed,
      heading: body.heading,
    });
  }
}
