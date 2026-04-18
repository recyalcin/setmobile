/**
 * PM2 Ecosystem Config — Setmobile Production
 * Usage:
 *   pm2 start deploy/pm2.config.cjs
 *   pm2 save
 *   pm2 startup
 */
module.exports = {
  apps: [
    {
      name: 'setmobile-api',
      script: 'main.ts',
      interpreter: 'tsx',
      interpreter_args: '--tsconfig tsconfig.server.json',
      cwd: '/var/www/setmobile',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '512M',
      env: {
        NODE_ENV: 'production',
        PORT: 3000,
      },
      env_file: '/var/www/setmobile/.env',
      error_file: '/var/log/pm2/setmobile-api-error.log',
      out_file:   '/var/log/pm2/setmobile-api-out.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss',
    },
  ],
};
