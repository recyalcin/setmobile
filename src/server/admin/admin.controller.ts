import { Controller, Get, Post, Put, Delete, Body, Param, UseGuards, Request, ParseIntPipe, Inject } from '@nestjs/common';
import { AdminService } from './admin.service';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';

@Controller('admin')
@UseGuards(JwtAuthGuard)
export class AdminController {
  constructor(@Inject(AdminService) private readonly adminService: AdminService) {}

  private checkAdmin(req: any) {
    if (req.user.role !== 'admin') throw new Error('Unauthorized');
  }

  @Get('db/:table')
  async getTable(@Request() req: any, @Param('table') table: string) {
    this.checkAdmin(req);
    return this.adminService.getTableData(table);
  }

  @Post('db/:table')
  async create(@Request() req: any, @Param('table') table: string, @Body() body: any) {
    this.checkAdmin(req);
    return this.adminService.createRecord(table, body);
  }

  @Put('db/:table/:id')
  async update(@Request() req: any, @Param('table') table: string, @Param('id', ParseIntPipe) id: number, @Body() body: any) {
    this.checkAdmin(req);
    return this.adminService.updateRecord(table, id, body);
  }

  @Delete('db/:table/:id')
  async remove(@Request() req: any, @Param('table') table: string, @Param('id', ParseIntPipe) id: number) {
    this.checkAdmin(req);
    return this.adminService.deleteRecord(table, id);
  }

  @Get('stats/dashboard')
  async getDashboard(@Request() req: any) {
    this.checkAdmin(req);
    return this.adminService.getDashboardStats();
  }

  @Get('stats/map')
  async getMapData(@Request() req: any) {
    this.checkAdmin(req);
    return this.adminService.getLiveMapData();
  }

  @Get('lookups')
  async getLookups(@Request() req: any) {
    this.checkAdmin(req);
    return this.adminService.getLookups();
  }
}
