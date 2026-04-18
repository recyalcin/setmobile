import 'reflect-metadata';
import { NestFactory } from '@nestjs/core';
import { AppModule } from './server/src/app.module';
import { NestExpressApplication } from '@nestjs/platform-express';
import express from 'express';

async function bootstrap() {
  const app = await NestFactory.create<NestExpressApplication>(AppModule);

  app.use(express.json());
  app.use(express.urlencoded({ extended: true }));

  app.setGlobalPrefix('api');

  const isProd = process.env.NODE_ENV === 'production';
  const allowedOrigins = isProd
    ? [
        'https://driver.setmobile.eu',
        'https://passenger.setmobile.eu',
        'https://sm1.setmobile.eu',
      ]
    : ['http://localhost:5173', 'http://localhost:4173', 'http://localhost:8080'];

  app.enableCors({
    origin: allowedOrigins,
    credentials: true,
    methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'Authorization'],
  });

  await app.listen(3000, '0.0.0.0');
  console.log(`🚀 Setmobile API running on http://localhost:3000 [${isProd ? 'production' : 'development'}]`);
  if (!isProd) {
    console.log('💡 Frontend dev server: npm run dev:ui');
  }
}

bootstrap();
