import { Module } from '@nestjs/common';
import { AuthModule } from './auth/auth.module';
import { ShiftsModule } from './shifts/shifts.module';
import { AdminModule } from './admin/admin.module';

@Module({
  imports: [AuthModule, ShiftsModule, AdminModule],
})
export class AppModule {}
