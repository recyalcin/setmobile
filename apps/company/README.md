# Setmobile Company Dashboard

> **Status: External PHP project** — The company dashboard is currently a PHP application hosted at `sm1.sulak.org`.

## Overview

The existing PHP dashboard (`sm1.sulak.org`) will be connected to the shared Setmobile database (PostgreSQL in production). MySQL data will be migrated via `sm1_full_tables.json`.

## Connection Plan

1. Migrate MySQL data → Setmobile PostgreSQL via migration script
2. Update PHP app to connect to shared PostgreSQL instead of MySQL
3. Optionally replace/supplement PHP dashboard with React app in this folder

## Planned React Structure (future)

```
apps/company/
├── index.html
├── src/
│   ├── main.tsx
│   ├── App.tsx          # Admin/company dashboard
│   ├── index.css
│   └── modules/
│       └── integrations/  # Uber, Bolt fleet management
```
