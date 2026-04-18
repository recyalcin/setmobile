import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'eu.setmobile.driver',
  appName: 'Setmobile Driver',
  webDir: 'dist/driver',
  server: {
    // Allow cleartext (HTTP) traffic only in development; in prod all traffic goes to HTTPS
    androidScheme: 'https',
  },
  android: {
    buildOptions: {
      // Use release keystroke from environment or local keystore
      keystorePath: process.env.KEYSTORE_PATH,
      keystoreAlias: process.env.KEYSTORE_ALIAS,
      keystorePassword: process.env.KEYSTORE_PASSWORD,
      keystoreAliasPassword: process.env.KEYSTORE_ALIAS_PASSWORD,
    },
  },
  plugins: {
    SplashScreen: {
      launchShowDuration: 2000,
      launchAutoHide: true,
      backgroundColor: '#1a1a2e',
      androidSplashResourceName: 'splash',
      showSpinner: false,
    },
    StatusBar: {
      style: 'Dark',
      backgroundColor: '#1a1a2e',
    },
  },
};

export default config;
