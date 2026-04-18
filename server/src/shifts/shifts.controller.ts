import { Controller, Post, Get, Body, Param, UseGuards, Request, ParseIntPipe, Inject } from '@nestjs/common';
import { ShiftsService } from './shifts.service';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';

@Controller('shifts')
@UseGuards(JwtAuthGuard)
export class ShiftsController {
  constructor(@Inject(ShiftsService) private readonly shiftsService: ShiftsService) {}

  @Post('start')
  startShift(@Request() req: any, @Body() body: { startKm: number }) {
    return this.shiftsService.createShift(req.user.userId, body.startKm);
  }

  @Post(':id/end')
  endShift(@Param('id', ParseIntPipe) id: number, @Body() body: { endKm: number }) {
    return this.shiftsService.endShift(id, body.endKm);
  }

  @Get('active')
  getActiveShift(@Request() req: any) {
    return this.shiftsService.getActiveShift(req.user.userId);
  }

  @Get('history')
  getHistory(@Request() req: any) {
    return this.shiftsService.getHistory(req.user.userId);
  }

  @Post('telemetry')
  addTelemetry(@Request() req: any, @Body() body: any) {
    return this.shiftsService.addTelemetry({ ...body, driverId: req.user.userId });
  }

  @Get('admin/active')
  getAllActive(@Request() req: any) {
    if (req.user.role !== 'admin') return [];
    return this.shiftsService.getAllActiveShifts();
  }

  @Get('admin/history')
  getAllHistory(@Request() req: any) {
    if (req.user.role !== 'admin') return [];
    return this.shiftsService.getAllHistory();
  }
}
