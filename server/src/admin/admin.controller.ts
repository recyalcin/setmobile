import { Controller, Get, Post, Put, Delete, Body, Param, UseGuards, Request, ParseIntPipe, Inject } from '@nestjs/common';
import { AdminService } from './admin.service';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';

@Controller('admin')
@UseGuards(JwtAuthGuard)
export class AdminController {
  constructor(@Inject(AdminService) private readonly adminService: AdminService) {}

  private checkAdmin(req: any) {
    if (req.user.role !== 'admin') throw new Error('Yetkisiz erişim');
  }

  @Get('stats/dashboard')
  getDashboard(@Request() req: any) {
    this.checkAdmin(req);
    return this.adminService.getDashboardStats();
  }

  @Get('stats/map')
  getMapData(@Request() req: any) {
    this.checkAdmin(req);
    return this.adminService.getMapData();
  }

  @Get('lookups')
  getLookups(@Request() req: any) {
    this.checkAdmin(req);
    return this.adminService.getLookups();
  }

  @Get('db/:model')
  getAll(@Request() req: any, @Param('model') model: string) {
    this.checkAdmin(req);
    return this.adminService.getAll(model);
  }

  @Post('db/:model')
  create(@Request() req: any, @Param('model') model: string, @Body() data: any) {
    this.checkAdmin(req);
    return this.adminService.create(model, data);
  }

  @Put('db/:model/:id')
  update(
    @Request() req: any,
    @Param('model') model: string,
    @Param('id', ParseIntPipe) id: number,
    @Body() data: any,
  ) {
    this.checkAdmin(req);
    return this.adminService.update(model, id, data);
  }

  @Delete('db/:model/:id')
  remove(@Request() req: any, @Param('model') model: string, @Param('id', ParseIntPipe) id: number) {
    this.checkAdmin(req);
    return this.adminService.delete(model, id);
  }
}
