# Android App Icons & Splash Screens

Place the following source files in this folder before running `@capacitor/assets generate`:

| File | Size | Purpose |
|------|------|---------|
| `icon.png` | 1024×1024 px | App icon (adaptive icon background layer) |
| `icon-foreground.png` | 1024×1024 px | App icon foreground (safe zone: center 500×500 px) |
| `splash.png` | 2732×2732 px | Splash screen (logo centered) |

Then run:
```
npx @capacitor/assets generate --android
```

This auto-generates all required mipmap and drawable sizes.
