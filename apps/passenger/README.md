# Setmobile Passenger App

> **Status: Placeholder** — Implementation coming in a future sprint.

## Overview

The passenger-facing app will allow users to:
- Request rides or track assigned vehicles in real time
- View driver location on a live map
- Manage booking history and preferences
- Integration modules (e.g. Uber, Bolt) via `modules/` folder

## Planned Structure

```
apps/passenger/
├── index.html
├── src/
│   ├── main.tsx
│   ├── App.tsx
│   ├── index.css
│   ├── i18n.ts
│   └── modules/
│       ├── uber/
│       └── bolt/
```

## Shared Backend

Uses the same NestJS backend (`server/`) and Prisma database as the driver app.
