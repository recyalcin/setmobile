import { Controller, Post, Body, UnauthorizedException, Get, UseGuards, Request, Inject } from '@nestjs/common';
import { AuthService } from './auth.service';
import { JwtAuthGuard } from './jwt-auth.guard';

@Controller('auth')
export class AuthController {
  constructor(
    @Inject(AuthService) private readonly authService: AuthService
  ) {
    console.log('AuthController initialized. AuthService is:', typeof this.authService);
  }

  @Post('login')
  async login(@Body() body: any) {
    console.log('Login attempt for:', body.username);
    const user = await this.authService.validateUser(body.username, body.password);
    if (!user) {
      throw new UnauthorizedException('Invalid credentials');
    }
    return this.authService.login(user);
  }

  @Post('register')
  async register(@Body() body: any) {
    console.log('AuthController.register - body:', body?.username);
    console.log('AuthController.register - authService type:', typeof this.authService);
    console.log('AuthController.register - authService keys:', Object.keys(this.authService || {}));
    
    if (!this.authService) {
      console.error('CRITICAL: authService is undefined in register method!');
      throw new Error('Internal server error: authService not initialized');
    }
    try {
      return await this.authService.register(body);
    } catch (error) {
      console.error('Registration error in controller:', error);
      throw error;
    }
  }

  @UseGuards(JwtAuthGuard)
  @Get('profile')
  async getProfile(@Request() req: any) {
    const user = await this.authService.findUserById(req.user.userId);
    if (!user) throw new UnauthorizedException();
    return {
      userId: user.id,
      username: user.username,
      displayName: `${user.person.firstname} ${user.person.lastname}`,
      role: user.person.persontypeid === 1 ? 'admin' : 'driver'
    };
  }
}
