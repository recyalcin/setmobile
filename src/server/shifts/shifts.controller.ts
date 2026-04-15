import { Controller, Post, Body, Get, UseGuards, Request, Param, ParseIntPipe, Inject } from '@nestjs/common';
import { ShiftsService } from './shifts.service';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';

@Controller('shifts')
@UseGuards(JwtAuthGuard)
export class ShiftsController {
  constructor(
    @Inject(ShiftsService) private readonly shiftsService: ShiftsService
  ) {
    console.log('ShiftsController initialized. ShiftsService is:', typeof this.shiftsService);
  }

  @Post('start')
  async startShift(@Request() req: any, @Body() body: { startKm: number }) {
    console.log('Starting shift for user:', req.user.userId);
    return this.shiftsService.createShift(req.user.userId, body.startKm);
  }

  @Post(':id/end')
  async endShift(@Param('id', ParseIntPipe) id: number, @Body() body: { endKm: number }) {
    console.log('Ending shift:', id);
    return this.shiftsService.endShift(id, body.endKm);
  }

  @Get('active')
  async getActive(@Request() req: any) {
    console.log('Getting active shift for user:', req.user.userId);
    if (!this.shiftsService) {
      console.error('CRITICAL: shiftsService is undefined in getActive!');
      throw new Error('Internal server error: shiftsService not initialized');
    }
    return this.shiftsService.getActiveShift(req.user.userId);
  }

  @Get('history')
  async getHistory(@Request() req: any) {
    console.log('Getting history for user:', req.user.userId);
    if (!this.shiftsService) {
      console.error('CRITICAL: shiftsService is undefined in getHistory!');
      throw new Error('Internal server error: shiftsService not initialized');
    }
    return this.shiftsService.getHistory(req.user.userId);
  }

  @Post('telemetry')
  async addTelemetry(@Request() req: any, @Body() body: any) {
    return this.shiftsService.addTelemetry({
      ...body,
      driverId: req.user.userId,
    });
  }

  // Admin Endpoints
  @Get('admin/active')
  async getAllActive(@Request() req: any) {
    if (req.user.role !== 'admin') throw new Error('Unauthorized');
    return this.shiftsService.getAllActiveShifts();
  }

  @Get('admin/history')
  async getAllHistory(@Request() req: any) {
    if (req.user.role !== 'admin') throw new Error('Unauthorized');
    return this.shiftsService.getAllHistory();
  }

  @Get('admin/telemetry/:shiftId')
  async getTelemetry(@Request() req: any, @Param('shiftId', ParseIntPipe) shiftId: number) {
    if (req.user.role !== 'admin') throw new Error('Unauthorized');
    return this.shiftsService.getShiftTelemetry(shiftId);
  }
}
