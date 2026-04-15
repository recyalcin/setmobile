import { Module } from '@nestjs/common';
import { ShiftsService } from './shifts.service';
import { ShiftsController } from './shifts.controller';
import { PrismaService } from '../prisma.service';

@Module({
  providers: [ShiftsService, PrismaService],
  controllers: [ShiftsController],
})
export class ShiftsModule {}
