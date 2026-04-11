# User Guide

## URL

- App: `http://localhost:7080`
- Tenant login: `http://localhost:7080/auth/login`
- Tenant register: `http://localhost:7080/auth/register`
- Admin login: `http://localhost:7080/admin/login`

## Test qilingan accountlar

- Tenant user:
  - email: `verified@example.com`
  - password: `Verified123!`
- Admin user:
  - email: `admin@example.com`
  - password: `Admin123!`

## Playwright bilan tekshirilgan flowlar

2026-04-12 kuni quyidagi Playwright flowlar ishga tushirildi:

- Landing page ochilishi
- Landing -> Register
- Landing -> Login
- Register -> Dashboard
- Login -> Dashboard
- Noto'g'ri login holati
- Logout
- Tenant dashboard sahifalari:
  - Projects
  - Conversations
  - Domains
  - Profile
  - Telegram Bot Settings
- Project yaratish sahifasiga o'tish
- Admin login -> dashboard
- Admin tenants sahifasi
- Admin users sahifasi
- Responsive smoke:
  - Mobile `375x812`
  - Tablet `768x1024`
  - Desktop `1280x800`

## Natija

- Playwright run: `30/30 passed`
- Screenshots:
  - `test-results/screenshots/dashboard-success.png`
  - `test-results/screenshots/register-to-dashboard.png`
  - `test-results/screenshots/admin-login.png`
  - `test-results/screenshots/admin-dashboard.png`
  - `test-results/screenshots/admin-tenants.png`
  - `test-results/screenshots/admin-users.png`

## Qayta tekshirish

```bash
npx playwright test tests/e2e/smoke-tests.spec.js tests/e2e/full-flow.spec.js tests/e2e/admin-panel.spec.js
```

## Eslatma

- Docker servislar ishga tushgan bo'lishi kerak.
- App hozir `http://localhost:7080` da javob beryapti.
