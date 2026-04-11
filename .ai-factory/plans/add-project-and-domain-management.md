# Reja: Project va Domain Management

## Holat: ✅ AMALGA OSHIRILGAN

Ushbu vazifa uchun barcha talab qilingan funksionallik allaqachon amalga oshirilgan. Quyida mavjud komponentlar ro'yxati va kichik yaxshilashlar keltirilgan.

---

## Mavjud Komponentlar

### 1. Database Migrations
- [x] `database/migrations/2026_04_10_130000_create_projects_table.php` — `projects` jadvali (tenant_id, name, slug, widget_key_hash, widget_key_prefix, description, primary_domain, settings, is_active)
- [x] `database/migrations/2026_04_10_130001_create_project_domains_table.php` — `project_domains` jadvali (project_id, domain, verification_status, verification_token, verified_at, verification_error, is_active, notes)

### 2. Models
- [x] `app/Models/Project.php` — TenantScope global scope, widget key boshqaruvi (`generateWidgetKey`, `revokeWidgetKey`, `regenerateWidgetKey`), cache'langan verified domenlar, settings o'qish
- [x] `app/Models/ProjectDomain.php` — Domain normalizatsiya, verifikatsiya metodlari (`markAsVerified`, `markAsFailed`, `generateVerificationToken`), DNS/HTTP validation

### 3. API Controllers
- [x] `app/Http/Controllers/Api/ProjectController.php` — CRUD (`index`, `store`, `show`, `update`, `destroy`) + `regenerateKey`, `revokeKey`
- [x] `app/Http/Controllers/Api/ProjectDomainController.php` — CRUD + `verify` (DNS + HTTP verifikatsiya)

### 4. Filament Admin Panel (Tenant)
- [x] `app/Filament/Tenant/Resources/ProjectResource.php` — Form (project details, widget config, embed code), Table, DomainsRelationManager
- [x] `app/Filament/Tenant/Resources/ProjectResource/Pages/CreateProject.php` — Avtomatik widget key generatsiya
- [x] `app/Filament/Tenant/Resources/ProjectResource/Pages/EditProject.php` — Widget key boshqaruvi (generate/regenerate/revoke), widget ko'rinishi sozlamalari
- [x] `app/Filament/Tenant/Resources/ProjectResource/Pages/ListProjects.php` — Ro'yxat sahifasi
- [x] `app/Filament/Tenant/Resources/ProjectResource/RelationManagers/DomainsRelationManager.php` — Domen qo'shish, tahrirlash, o'chirish, verifikatsiya

### 5. Services
- [x] `app/Services/WidgetKeyService.php` — Widget key yaratish/validatsiya/cache (SHA-256 hash, 900s TTL)
- [x] `app/Services/DomainVerificationService.php` — DNS TXT record va HTTP file verifikatsiya, SSRF himoyasi

### 6. Observers
- [x] `app/Observers/ProjectDomainObserver.php` — Avtomatik verification token generatsiya, cache invalidatsiya

### 7. Routes
- [x] `routes/api.php` — `/api/tenant/projects` (CRUD), `/api/tenant/projects/{project}/regenerate-key`, `/api/tenant/projects/{project}/revoke-key`, `/api/tenant/project-domains` (CRUD), `/api/tenant/project-domains/{domain}/verify`

---

## Aniqlangan Kichik Yaxshilashlar

### 1. Project Delete — Aktiv Suhbatlar Tekshiruvi
**Muammo:** Project o'chirilganda, unga bog'langan suhbatlar ham o'chadi (cascade). Lekin faol suhbatlar bo'lsa, foydalanuvchi ogohlantirilishi kerak.

**Yechim:** `ProjectController@destroy` va `Filament DeleteAction` da faol suhbatlar sonini tekshirish va tasdiqlash modalida ko'rsatish.

**O'zgartiriladigan fayllar:**
- [x] `app/Http/Controllers/Api/ProjectController.php` — `destroy()` metodida conversations count
- [x] `app/Filament/Tenant/Resources/ProjectResource.php` — `DeleteAction` da `modalDescription`
- [x] `app/Models/Project.php` — `hasActiveConversations()` metodi qo'shish

### 2. Project Domain — Bulk Verify
**Muammo:** Har bir domen uchun alohida verify tugmasini bosish kerak. Bir nechta domenlarni bir vaqtda verifikatsiya qilish imkoni yo'q.

**Yechim:** `DomainsRelationManager` da bulk verify action qo'shish.

**O'zgartiriladigan fayllar:**
- [x] `app/Filament/Tenant/Resources/ProjectResource/RelationManagers/DomainsRelationManager.php` — `BulkAction` qo'shish

### 3. Widget Embed Code — Blade View
**Muammo:** Embed code Filament form ichida HTML string sifatida generatsiya qilinadi. Uni alohida Blade view ga ajratish yaxshiroq bo'ladi.

**Yechim:** `resources/views/widget/embed-code.blade.php` yaratish va Filament formda `view()` orqali ishlatish.

**Yangi fayllar:**
- [x] `resources/views/widget/embed-code.blade.php`

**O'zgartiriladigan fayllar:**
- [x] `app/Filament/Tenant/Resources/ProjectResource.php` — Embed Code section

### 4. API Response — Project Domains Include
**Muammo:** `ProjectController@show` da domenlar avtomatik yuklanmaydi. API response da domenlar ma'lumotlarini qaytarish foydali bo'ladi.

**Yechim:** `show` metodida `domains` relation ini eager load qilish, yoki `?include=domains` query parameter qo'llab-quvvatlash.

**O'zgartiriladigan fayllar:**
- [x] `app/Http/Controllers/Api/ProjectController.php` — `show()` metodi

### 5. Project Slug — Avtomatik Generatsiya
**Muammo:** Filament form da slug `afterStateUpdated` callback orqali generatsiya qilinadi, lekin API da bu avtomatik emas. Foydalanuvchi slug ni kiritishi shart.

**Yechim:** API `store` metodida agar slug berilmasa, name dan avtomatik generatsiya qilish.

**O'zgartiriladigan fayllar:**
- [x] `app/Http/Controllers/Api/ProjectController.php` — `store()` metodi

---

## Vazifalar Ro'yxati

### Task 1: Project Delete — Aktiv Suhbatlar Tekshiruvi
- [x] `app/Models/Project.php` — `hasActiveConversations(): bool` metodi qo'shish
- [x] `app/Http/Controllers/Api/ProjectController.php` — `destroy()` da tekshirish va response da ogohlantirish
- [x] `app/Filament/Tenant/Resources/ProjectResource.php` — `DeleteAction` da `modalDescription` bilan ogohlantirish

### Task 2: Domain Bulk Verify
- [x] `app/Filament/Tenant/Resources/ProjectResource/RelationManagers/DomainsRelationManager.php` — `BulkAction::make('bulkVerify')` qo'shish

### Task 3: Widget Embed Code Blade View
- [x] `resources/views/widget/embed-code.blade.php` — Yangi fayl yaratish
- [x] `app/Filament/Tenant/Resources/ProjectResource.php` — Embed Code section ni yangilash

### Task 4: API Response — Project Domains
- [x] `app/Http/Controllers/Api/ProjectController.php` — `show()` da `domains` relation load qilish

### Task 5: Auto Slug Generation
- [x] `app/Http/Controllers/Api/ProjectController.php` — `store()` da slug auto-generatsiya

---

## Xulosa

Asosiy funksionallik to'liq amalga oshirilgan. Yuqoridagi 5 ta kichik yaxshilanish **amalga oshirildi** ✅

---

## Auto Review Fix — 2026-04-10

Review Gate (`request_changes`) dan kelgan 3 ta kritik muammo hal qilindi:

### Review Fix #1: `embed-code.blade.php` — `url()` PHP Function Chaqiruvi
**Muammo:** `{{ url('/widget.js') }}` Blade sintaksisi heredoc ichida to'g'ridan-to'g'ri interpolyatsiya qilinmaydi — natijada literal `{{ url('/widget.js') }}` string chiqadi.

**Yechim:** `url()` funksiyasini heredoc dan oldin chaqirib, natijani o'zgaruvchiga saqlash va heredoc da `{$widgetJsUrl}` sifatida ishlatish.

**O'zgartirilgan fayl:**
- `resources/views/widget/embed-code.blade.php` — `$widgetJsUrl = url('/widget.js');` heredoc dan oldin, keyin `src="{$widgetJsUrl}"`

### Review Fix #2: `verifyViaDns()` — `isInternalHostname()` SSRF Tekshiruvi
**Muammo:** DNS verifikatsiyasida `isInternalHostname()` tekshiruvi yo'q edi. Bu `localhost.local`, `.internal`, `.corp` kabi internal hostnamelarga DNS so'rov yuborishga imkon berardi (SSRF zaifligi).

**Yechim:** `verifyViaDns()` metodiga DNS so'rovdan oldin `isInternalHostname()` tekshiruvini qo'shish. Agar internal hostname bo'lsa, darhol `markAsFailed()` qaytarish.

**O'zgartirilgan fayl:**
- `app/Services/DomainVerificationService.php` — `verifyViaDns()` metodiga SSRF protection qo'shildi

### Review Fix #3: `settings.widget.custom_css` — Sanitizatsiya
**Muammo:** `custom_css` maydoni to'g'ridan-to'g'ri saqlanardi, HTML injection va CSS-based XSS hujumlariga ochiq edi.

**Yechim:** `Project` modelida `setSettingsAttribute` mutator orqali avtomatik sanitizatsiya:
1. `strip_tags()` — barcha HTML teglarini olib tashlash
2. CSS whitelist filter — xavfli patternlarni bloklash:
   - `expression(` — IE CSS expression
   - `javascript:` — JS protocol handler
   - `vbscript:` — VBScript protocol
   - `url(...javascript:` — JS yuklash urinishi
   - `@import` — tashqi CSS import
   - `behavior:` — HTC behavior
   - `-moz-binding:` — XBL binding

**O'zgartirilgan fayl:**
- `app/Models/Project.php` — `setSettingsAttribute()` va `sanitizeSettings()` metodlari qo'shildi

## Amalga Oshirilgan O'zgarishlar — Qisqacha

| Task | Fayl | O'zgarish |
|------|------|-----------|
| 1 | `app/Models/Project.php` | `hasActiveConversations()` va `activeConversationsCount()` metodlari qo'shildi |
| 1 | `app/Http/Controllers/Api/ProjectController.php` | `destroy()` metodida faol suhbatlar tekshiriladi va ogohlantirish qaytariladi |
| 1 | `app/Filament/Tenant/Resources/ProjectResource.php` | `DeleteAction` da faol suhbatlar soni ko'rsatiladi |
| 2 | `app/Filament/Tenant/Resources/ProjectResource/RelationManagers/DomainsRelationManager.php` | Bulk verify action qo'shildi — bir nechta domenlarni bir vaqtda verifikatsiya qilish |
| 3 | `resources/views/widget/embed-code.blade.php` | Yangi Blade view yaratildi |
| 3 | `app/Filament/Tenant/Resources/ProjectResource.php` | Embed Code section `ViewField` ga o'zgartirildi |
| 4 | `app/Http/Controllers/Api/ProjectController.php` | `show()` metodida `domains` relation eager load qilinadi |
| 5 | `app/Http/Controllers/Api/ProjectController.php` | `store()` metodida slug avtomatik generatsiya qilinadi (agar berilmasa) |

---

## Auto Review Fix #2 — 2026-04-10 (Rework — Review Iteration 2/3)

Review Gate (`request_changes`) dan kelgan 3 ta kritik muammo hal qilindi:

### Review Fix #1: CSRF Himoyasi — Faqat Webhook Endpointlarni Istisno Qilish
**Muammo:** `bootstrap/app.php` da `'api/*'` — barcha API endpointlari CSRF himoyasidan istisno qilingan. Bu `api/tenant/projects`, `api/tenant/project-domains` kabi CRUD endpointlarni CSRF hujumlariga ochiq qoldirardi.

**Yechim:** CSRF istisnosini faqat tashqi servislar chaqiradigan endpointlar bilan cheklash:
1. `api/telegram/webhook/*` — Telegram webhook (CSRF token qo'sha olmaydi)
2. `api/widget/messages` — Widget SDK (iframe ichidan CSRF token yuborilmaydi)
3. Boshqa barcha API route'lar (`/api/tenant/*`) CSRF himoyasida qoladi — SPA cookie-based auth uchun

**O'zgartirilgan fayl:**
- `bootstrap/app.php` — `validateCsrfTokens(except: [...])` ni toraytirish

### Review Fix #2: Widget Embed View — XSS / CSS Sanitizatsiya (Defense-in-Depth)
**Muammo:** `embed.blade.php` da `{!! file_get_contents() !!}` orqali widget.css va `custom_css` setting to'g'ridan-to'g'ri chiqarilardi. Model darajasidagi sanitizatsiya yetarli bo'lmasa, XSS xavfi bo'lardi.

**Yechim:** Yangi `CssSanitizer` service yaratildi va view darajasida qo'shimcha sanitizatsiya qatlami qo'shildi:
1. **`app/Services/CssSanitizer.php`** — Yangi service:
   - `strip_tags()` — barcha HTML teglarini olib tashlash
   - Null byte removal
   - CSS hex escape decoding va filtering (`\65\78...` → "ex...")
   - Comment removal
   - Dangerous pattern blocking: `expression()`, `javascript:`, `vbscript:`, `url(data:)`, `@import`, `behavior:`, `-moz-binding:`, `-webkit-binding:`
   - File size limit (500KB)
2. **`resources/views/widget/embed.blade.php`** — `{!! file_get_contents() !!}` o'rniga `CssSanitizer::sanitizeFile()` va `CssSanitizer::sanitize()` ishlatish

**Yangi fayllar:**
- `app/Services/CssSanitizer.php`

**O'zgartirilgan fayllar:**
- `resources/views/widget/embed.blade.php` — CSS render sanitizatsiya bilan

### Review Fix #3: DNS Verification — IP Address Validation (SSRF Xavfi)
**Muammo:** `verifyViaDns()` da hostname internal IP ga resolve bo'lsa, DNS so'rov internal serverga yuborilardi (SSRF). Faqat hostname tekshirildi, lekin resolved IP addresslar tekshirilmadi.

**Yechim:** DNS verifikatsiyasidan oldin domain ni resolve qilib, barcha IP addresslar public ekanligini tekshirish:
1. **`validateDnsVerificationTarget()`** — Yangi metod:
   - IP address bo'lsa — to'g'ridan-to'g'ri `isPublicIpAddress()` bilan tekshirish
   - Hostname bo'lsa — `resolveHostIpAddresses()` orqali resolve qilish va har bir IP ni validate qilish
   - Private/reserved IP (10.x, 172.16.x, 192.168.x, 127.x) aniqlansa — verification rad etiladi
2. DNS TXT record target (`_widget-verify.{domain}`) uchun ham alohida tekshiruv
3. DNS rebinding hujumlariga qarshi himoya

**O'zgartirilgan fayl:**
- `app/Services/DomainVerificationService.php` — `verifyViaDns()` ga IP validation va `validateDnsVerificationTarget()` metodi qo'shildi
