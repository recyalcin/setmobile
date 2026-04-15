import 'reflect-metadata';
import { NestFactory } from '@nestjs/core';
import { AppModule } from './src/server/app.module';
import { createServer as createViteServer } from 'vite';
import path from 'path';
import { NestExpressApplication } from '@nestjs/platform-express';
import express from 'express';

async function bootstrap() {
  const app = await NestFactory.create<NestExpressApplication>(AppModule);
  
  app.use(express.json());
  app.use(express.urlencoded({ extended: true }));
  
  app.setGlobalPrefix('api');
  app.enableCors();
  
  const server = app.getHttpAdapter().getInstance();
  const PORT = 3000;

  // Vite middleware for development
  if (process.env.NODE_ENV !== 'production') {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: 'spa',
    });
    server.use((req: any, res: any, next: any) => {
      if (req.url.startsWith('/api')) {
        return next();
      }
      vite.middlewares(req, res, next);
    });
  } else {
    const distPath = path.join(process.cwd(), 'dist');
    server.use(distPath, (req: any, res: any, next: any) => next()); // Placeholder for static
    // Note: In production, NestJS can serve static files or we can use express.static
  }

  await app.listen(PORT, '0.0.0.0');
  console.log(`Server running on http://localhost:${PORT}`);
}

bootstrap();
