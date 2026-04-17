# 🎓 NDU Distant Təhsil Sistemi

> **Naxçıvan Dövlət Universiteti** üçün hazırlanmış tam inteqrasiyalı, müasir distant təhsil platforması.  
> Canlı dərs (WebRTC), arxiv, analitika, davamiyyət izləmə və TMİS SSO inteqrasiyasını birləşdirir.

---

## 📋 Mündəricat

- [Əsas Funksiyalar](#-əsas-funksiyalar)
- [Canlı Dərs Sistemi](#-canlı-dərs-sistemi-webrtc)
- [TMİS İnteqrasiyası](#-tmis-i̇nteqrasiyası)
- [Texnoloji Stek](#️-texnoloji-stek)
- [Qovluq Strukturu](#-qovluq-strukturu)
- [Quraşdırma](#️-quraşdırma)
- [API Endpointləri](#-api-endpointləri)
- [Verilənlər Bazası](#-verilənlər-bazası)
- [Nginx Konfiqurasiyas](#-nginx-konfiqurasiyas)
- [Təhlükəsizlik](#-təhlükəsizlik)

---

## 🚀 Əsas Funksiyalar

### 👨‍🎓 Tələbə Paneli (`/student`)

| Fayl | Funksiya |
|------|----------|
| `index.php` | **Dashboard** – Şəxsi dərs qrafiki, həftəlik/aylıq canlı dərs sayları, tezliklə başlayacaq dərslər |
| `live-classes.php` | **Aktiv Dərslər** – Hal-hazırda davam edən dərslərə baxış və birbaşa qoşulma |
| `live-view.php` | **Canlı Dərs Ekranı (v8.0)** – WebRTC izləmə, sidebar chat, whiteboard, fayl göndərmə, kamera/mikrofon idarəetməsi, timer, mobil responsive |
| `archive.php` | **Arxiv** – Keçmiş dərslərin video yazıları + PDF materialların listi, axtarış və filtr |
| `lessons.php` | **Dərslər** – Fənn bazalı dərs siyahısı |
| `watch.php` | **Video İzləmə** – Arxivdən video izləmə, baxış sayı artırma |
| `statistics.php` | **Statistika** – Şəxsi akademik performans göstəriciləri |
| `login.php` | **Giriş** – TMİS SSO ilə autentifikasiya |
| `sso.php` | **SSO Callback** – TMİS-dən geri dönüş nöqtəsi; FIN kodu + token yoxlama |
| `bridge.php` | **Bridge** – TMİS-TMİS arası məlumat körpüsü |

#### Tələbə Paneli – Canlı Dərs Xüsusiyyətləri (`live-view.php` – v8.0)
- **Sidebar çat:** Sal-sağ panel, real-vaxt mesajlaşma, fayl göndərmə dəstəyi
- **Özəl mesaj:** "Müəllimə Özəl" rejimi – yalnız müəllim görür
- **Whiteboard:** Müəllimlə sinxron lövhə (real-vaxt canvas)
- **Kamera/Mikrofon idarəsi:** Söz istəmə, kamera açıb-bağlama, ekran paylaşımı
- **Dərs timer:** Dərs müddətini real-vaxt göstərir
- **Mobil responsive:**
  - `≤1024px`: Sidebar panel alt-yarım ekran overlay kimi açılır (slide-up animasiya)
  - `≤600px`: Kamera önizləmə kiçilir, kontrol düymələri statik bar kimi
  - Müəllim adı, canlı badge, "Ayrıl" düyməsi adaptiv olaraq ölçülür
- **Heartbeat:** Meta-refresh əvəzinə JS heartbeat ilə bağlantı saxlanılır (WebRTC kəsilmir)
- **Laser kursor:** Whiteboard üzərində müəllim lazeri görünür

---

### 👨‍🏫 Müəllim Paneli (`/teacher`)

| Fayl | Funksiya |
|------|----------|
| `index.php` | **Dashboard** – Aktiv fənlər, bu günün dərsi, tezliklə başlayacaqlar |
| `live-studio.php` | **Canlı Studio (v8.0)** – WebRTC yayım, 3-sütunlu responsive layout, ekran paylaşımı, chat, whiteboard, iştirakçı jurnalı |
| `live-lessons.php` | **Aktiv Dərslər** – Canlı dərs başlatma/bitirmə, dərs statusu idarəsi |
| `plan.php` | **Arxiv və Resurslar** – TMİS + lokal arxiv, görünürlük idarəetməsi (is_visible toggle per lesson), fənn filtrı, axtarış |
| `courses.php` | **Fənlər** – Tədris etdiyi fənlər siyahısı, TMİS-dən sinxronizasiya |
| `course-details.php` | **Fənn Detalları** – Materiallara baxış, PDF yükləmə, tələbə siyahısı |
| `analytics.php` | **Analitika** – Dərslərə baxış sayları, tələbə davamiyyəti, trend qrafiklər |
| `attendance_report.php` | **Davamiyyət Hesabatı** – Canlı dərsdə kimin qoşulduğu, neçə dəq qaldığı; popup pəncərə kimi açılır |
| `schedule.php` | **Cədvəl** – Dərs cədvəlinin idarə edilməsi |
| `help.php` | **Yardım** – Sual göndərə bilmə (Gmail compose link) |
| `login.php` | **Giriş** – TMİS SSO ilə autentifikasiya |
| `sso.php` | **SSO Callback** – Müəllim token yoxlama |

#### Müəllim Paneli – Canlı Studio Xüsusiyyətləri (`live-studio.php` – v8.0)
- **3 sütunlu layout:** Sol (iştirakçılar) | Mərkəz (video) | Sağ (chat + qeydlər)
- **Canlı İştirak paneli:** Tələbə qoşulduqda real-vaxt siyahı (`liveAttendanceList`)
- **İştirakçı Jurnalı:** Popup pəncərə (`attendance_report.php?id=...&minimal=1`)
- **Kontrol düymələri:** Mikrofon, kamera, ekran paylaşımı, whiteboard, lazeri, qeyd başlatma/dayandırma
- **Ekran paylaşımı:** Müəllim ekranı paylaşa bilər, tələbə izləyir
- **Whiteboard:** Canvas əsaslı, rəng seçici, silgi, kalem ölçüsü, lazer kursor, gizlənə bilən toolbar
- **Dərsi Bitir:** `stopAndUpload()` – qeyd saxlanılır, status `ended` olur
- **Mobil responsive:**
  - `≤1024px`: Sol + sağ sidebarlər "slide-up" overlay panelə çevrilir, backdrop göstərilir
  - `≤600px`: Başlıq gizlənir, düymələr kiçilir, horizontal scroll görünür
  - `≤400px`: Ultra kiçik ekranlar üçün əlavə optimizasiya
- **Portrait kamera:** `$portraitCameraOnPhone = false` – telefonda şaquli kamera rejimini aç/bağla
- **Bitmiş dərs qoruması:** Status `ended/completed` istəsə, `live-lessons.php`-ə yönləndirilir

#### Müəllim Paneli – Arxiv Xüsusiyyətləri (`plan.php`)
- **3-mənbəli arxiv:**
  1. TMİS API arxivi (`TmisApi::getArchive()`)
  2. Lokal `archived_lessons` cədvəli
  3. Lokal `live_classes` yazısı (recording_path dolu olanlar)
- **Dublikat filtrı:** TMİS + lokal arasında `tmis_session_id`, `başlıq+kurs`, `tarix±2saat` əsasında dublikat siyahı karta əlavə olunmur
- **Mövzu axtarışı 3 pillə:**
  1. Fayl adından `lesson_ID_` pattern
  2. Lokal `live_classes`-da `subject_id + tarix` match (±2 saat)
  3. TMİS Activities API-dən `subject_id + tarix` match (±3 saat)
- **Görünürlük toggle:** Hər dərsin `is_visible` sahəsi müəllim tərəfindən idarə edilir; tələbə yalnız `is_visible=1` olanları görür
- **Lokal video önceliyi:** Əgər TMİS URL-dən yerli fayl tapılırsa, lokal video istifadə edilir
- **Statistika kartları:** Ümumi dərslər, video sayı, PDF sayı, ümumi baxışlar

---

### 📅 Publik Cədvəl (`/index.php`)

- Qeydiyyatsız giriş – gündəlik dərs cədvəlinə baxış
- Arxiv materiallarına açıq əlçatanlıq
- **Qaranlıq/İşıqlı rejim:** Dinamik animasyonlu toggle (localStorage saxlanılır)
- Yaxınlaşan dərslər, davam edən dərslər, tamamlanmışlar

---

### 👑 Super User (Admin) Paneli

- **Vebinar Arxiv və Resurslar:** Yalnız Admin üçün əlçatan olan ayrıca qlobal Vebinar arxivi (`/teacher/webinar_plan.php`).
- **Qlobal Analitika:** Dərslərə baxış sayları, ən aktiv müəllimlər və fakültələr üzrə statistikanı bütün universitet miqyasında analiz edə bilmə.
- **İştirakçı Jurnalı (Tam Keçid):** Admin tərəfindən istənilən fənnin iştirakçı siyahısına fasiləsiz nəzarət.
- **Vahid Platforma Modeli:** "Distant Təhsil" və "Vebinar" sistemlərinin eyni bazadan mərkəzləşdirilmiş idarəolunması.

---

### 🌍 Vebinar Portalı (`/webinar`)

| Fayl | Funksiya |
|------|----------|
| `dashboard.php` | **Vebinar Paneli** – Qarşıdan gələn vebinarlar, aktiv sessiyalar, illik/aylıq vebinar analitikası |
| `manage.php` | **Vebinar İdarəetməsi** – Yeni vebinar yaradılması, statusların tənzimlənməsi (Aktiv/Ləğv), poster/plakat yüklənməsi |
| `users.php` | **İstifadəçilər** – Vebinara xüsusi hesabların cədvəl şəklində idarəsi və fakültə mənsubiyyətləri |
| `archive.php` | **Vebinar Arxivi** – Keçmiş vebinar qeydləri (MP4/WebM) və təqdimat materialları (PDF) |
| `course-details.php`| **Detallı Baxış** – Xüsusi vebinara aid tərkibə, iştirakçılara və iclas materiallarına dərindən baxış |

---

## 📡 Canlı Dərs Sistemi (WebRTC)

### Bağlantı Arxitekturası

```
Müəllim (Broadcaster)
    │
    ├─── WebRTC Peer Connection
    │         │
    │    ICE Negotiation (STUN/TURN)
    │         │
    └─── Tələbə (Viewer) × N
```

### ICE Server Konfigurasiyas (`api/get_turn_credentials.php`)

**Prioritet sırası:**
1. `METERED_DOMAIN` subdomain API endpoint → TURN + STUN credentials
2. `www.metered.ca` global API fallback (HTTP 404 / DNS xətasında)
3. Google STUN-only fallback (TURN yoxsa — mobil/LTE işləmir)

**TURN Server TTL:** 86400 saniyə (24 saat)

**Cavab formatı:**
```json
{
  "success": true,
  "iceServers": [
    { "urls": "stun:stun.l.google.com:19302" },
    { "urls": "turn:...", "username": "...", "credential": "..." }
  ],
  "source": "metered",
  "ttl": 86400
}
```

**Əgər TURN konfiqurasiya edilməyibsə** (`METERED_API_KEY` boşdursa):
```json
{
  "source": "fallback_stun_only",
  "warning": "No TURN server configured. Mobile/LTE connections will fail."
}
```

> ⚠️ **Mobil/LTE istifadəçilər üçün TURN mütləqdir.** STUN-only rejim LAN-da işləyir, amma mobil şəbəkə NAT-ından keçə bilmir.

### Chat Fayl Göndərmə (`api/upload_chat_file.php`)
- Dərs çatında fayl yükləmə (PDF, şəkil, s.)
- Yüklənmiş fayllar `uploads/` altında saxlanılır

### Canlı Video Chunk Yükləmə (`api/live/upload_chunk.php`)
- Videonu hissə-hissə serverə yükləmə
- Dərs yazısı bitdikdən sonra chunk-lar birləşdirilir

---

## 🔗 TMİS İnteqrasiyası

**Təhsil İdarəetmə İnformasiya Sistemi (TMİS)** – `https://tmis.ndu.edu.az`

### SSO (Tək Giriş)

| Axın | Addımlar |
|------|----------|
| Tələbə | `student/login.php` → TMİS redirect → `student/sso.php` (callback) → `$_SESSION` qurulur |
| Müəllim | `teacher/login.php` → TMİS redirect → `teacher/sso.php` (callback) → `$_SESSION` qurulur |

**Autentifikasiya:** HMAC SHA256 imzalı token  
**Gizli açar:** `.env`-dəki `SSO_API_SECRET`

### API Qatları (`TmisApi` sinfi)

| Metod | Funksiya |
|-------|----------|
| `TmisApi::getToken()` | Cari istifadəçi üçün TMİS token al |
| `TmisApi::getSubjectsList($token)` | Müəllimin fənlərini gətir |
| `TmisApi::getArchive($token)` | Arxivləşdirilmiş materialları gətir |
| `TmisApi::getActivities($token)` | Dərs aktivitilərini gətir (mövzu adları, müddətlər) |

### Data Sinxronizasiyası (`sync_and_backfill.php`)
- Köhnə dərs qeydlərini TMİS-dən geri doldurur
- Fənn adları, ixtisaslar, tələbə qruplarını sinxronizasiya edir
- `cron` ilə işlədilə bilər

### Ətraflı İnteqrasiya Sənədləri
- [TMİS_STUDENT_INTEGRATION_GUIDE.md](TMİS_STUDENT_INTEGRATION_GUIDE.md) – Tələbə SSO axını, token strukturu
- [TMİS_TEACHER_INTEGRATION_GUIDE.md](TMİS_TEACHER_INTEGRATION_GUIDE.md) – Müəllim SSO, fənn sinxronizasiyas

---

## 🛠️ Texnoloji Stek

| Qat | Texnologiya | Qeyd |
|-----|------------|------|
| **Backend** | PHP (Native) | MVC pattern, sinif əsaslı `Auth`, `Database`, `TmisApi` |
| **Frontend** | Vanilla CSS + JavaScript ES6+ | Framework yoxdur |
| **İkonlar** | Lucide Icons (CDN) | SVG əsaslı |
| **Şrift** | Inter, JetBrains Mono (Google Fonts) | |
| **Canlı Yayım** | WebRTC API (Browser native) | ICE/STUN/TURN |
| **TURN Server** | Metered.ca | Dinamik credentials, mobil dəstəyi |
| **Verilənlər Bazası** | MariaDB / MySQL | `utf8mb4_unicode_ci` |
| **Web Serveri** | Nginx | `nginx.conf` konfiqurasiyas |
| **Yerli İnkişaf** | Laragon | Windows + PHP + MySQL |

---

## 📁 Qovluq Strukturu

```
distant-tehsil/
│
├── index.php                        # Publik cədvəl (ana səhifə, Dark/Light mode)
├── sync_and_backfill.php            # TMİS data sinxronizasiya skripti (cron üçün)
├── nginx.conf                       # Nginx server konfiqurasiyas
├── nginx_config_guide.txt           # Nginx quraşdırma təlimatı
├── .env                             # 🔒 Gizli mühit dəyişənləri (git-ə əlavə edilmir)
├── .env.example                     # Nümunə konfiqurasiya şablonu
├── .gitignore                       # Git istisnalar
├── .user.ini                        # PHP upload/memory limitləri
│
├── api/                             # Ümumi API endpointləri
│   ├── get_turn_credentials.php     # WebRTC ICE servers (Metered.ca TURN)
│   ├── get_active_alerts.php        # Aktiv bildirişlər (tezliklə başlayacaq dərslər)
│   ├── upload_chat_file.php         # Dərs çatında fayl yükləmə
│   └── live/
│       └── upload_chunk.php         # Dərs video chunk yükləmə
│
├── student/                         # 🎓 Tələbə Paneli
│   ├── index.php                    # Dashboard
│   ├── live-view.php                # Canlı dərs izləmə (WebRTC, v8.0)
│   ├── live-classes.php             # Aktiv dərslər siyahısı
│   ├── archive.php                  # Video + PDF arxivi
│   ├── lessons.php                  # Fənn bazalı dərslər
│   ├── watch.php                    # Video izləmə səhifəsi
│   ├── statistics.php               # Şəxsi akademik statistika
│   ├── login.php                    # TMİS SSO giriş
│   ├── sso.php                      # SSO callback işleyicisi
│   ├── logout.php                   # Çıxış
│   ├── bridge.php                   # TMİS data körpüsü
│   ├── .htaccess                    # URL yönləndirmə qaydaları
│   ├── config/                      # DB + konfiqurasiya faylları
│   ├── includes/                    # auth.php, helpers.php, header.php, sidebar.php
│   ├── assets/                      # CSS, JS, şəkillər
│   ├── api/                         # Tələbəyə aid ajax endpointləri
│   └── database/                    # Tələbə paneli DB sorğuları
│
├── teacher/                         # 🏫 Müəllim Paneli
│   ├── index.php                    # Dashboard
│   ├── live-studio.php              # Canlı yayım studiosu (WebRTC, v8.0)
│   ├── live-lessons.php             # Aktiv canlı dərslər
│   ├── plan.php                     # Arxiv + görünürlük idarəetməsi
│   ├── courses.php                  # Fənn idarəetməsi
│   ├── course-details.php           # Fənn detalları, materiallar
│   ├── analytics.php                # Dərs analitikası
│   ├── attendance_report.php        # Davamiyyət hesabatı (popup)
│   ├── schedule.php                 # Dərs cədvəli
│   ├── help.php                     # Yardım (Gmail compose link)
│   ├── login.php                    # TMİS SSO giriş
│   ├── sso.php                      # SSO callback işleyicisi
│   ├── logout.php                   # Çıxış
│   ├── bridge.php                   # TMİS data körpüsü
│   ├── .htaccess                    # URL yönləndirmə qaydaları
│   ├── config/                      # DB + konfiqurasiya faylları
│   ├── includes/                    # auth.php, helpers.php, header.php, sidebar.php
│   ├── assets/                      # CSS, JS, şəkillər
│   ├── api/                         # Müəllimə aid ajax endpointləri
│   ├── database/                    # Müəllim paneli DB sorğuları
│   └── uploads/                     # Müəllimə aid yükləmələr
│
├── database/                        # Verilənlər Bazası
│   ├── MIGRATION_GUIDE.md           # Miqrasiya təlimatı
│   └── migrations/
│       └── 001_add_is_visible_to_lessons.sql  # Dərs görünürlük sahəsi
│
├── webinar/                         # 🌍 Vebinar Portalı
│   ├── dashboard.php                # Vebinar qlobal idarəetmə paneli
│   ├── manage.php                   # Vebinar yaratma və nizamlama
│   ├── archive.php                  # Vebinar materialları və video arxivi
│   ├── users.php                    # İstifadəçi idarəetməsi
│   └── api/                         # Vebinarlara aid CRUD metodları
│
├── tests/                           # Test faylları
│
└── uploads/                         # Yüklənmiş fayllar
    ├── videos/                      # Canlı dərs yazıları (MP4/WebM)
    └── webinar_recordings/          # Vebinar video arxivləri
```

---

## ⚙️ Quraşdırma

### Tələblər
- PHP ≥ 7.4 (cURL, mbstring, PDO_MySQL aktiv)
- MariaDB / MySQL ≥ 10.4
- Nginx (tövsiyə olunur) və ya Apache
- [Laragon](https://laragon.org/) (Windows üçün)

---

### Addım 1 – Layihəni Klonla

```bash
git clone https://github.com/sarkannnn/distance-education-system.git distant-tehsil
```

---

### Addım 2 – Mühit Dəyişənlərini Ayarla

`.env.example` faylını kopyalayıb `.env` adlandır:

```bash
copy .env.example .env
```

`.env` faylını redaktə et:

```env
# ── Verilənlər Bazası ────────────────────────
DB_HOST=localhost
DB_NAME=distant_tehsil
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

# ── TMİS SSO ─────────────────────────────────
TMIS_URL=https://tmis.ndu.edu.az
SSO_API_SECRET=your_sso_secret_here

# ── Metered.ca TURN Server ───────────────────
# https://www.metered.ca/stun-turn — pulsuz hesab aç
METERED_API_KEY=your_metered_api_key_here
METERED_DOMAIN=your-app-name.metered.live
```

> 💡 **TURN serveri olmadan mobil/LTE istifadəçilər dərsə qoşula bilməz!**  
> [metered.ca](https://www.metered.ca/stun-turn) saytında pulsuz hesab aç, API key + domain alıb `.env`-ə əlavə et.

---

### Addım 3 – Verilənlər Bazasını Yarat

```sql
CREATE DATABASE distant_tehsil 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;
```

Miqrasiya skriptlərini tətbiq et:

```sql
-- database/migrations/ qovluğundakı bütün .sql faylları ardıcıllıqla icra et
SOURCE database/migrations/001_add_is_visible_to_lessons.sql;
```

---

### Addım 4 – Nginx Konfiqurasiyas

`nginx.conf` faylını Laragon-un Nginx konfigurasiya qovluğuna kopyala:

```
C:\laragon\bin\nginx\conf\sites-enabled\distant-tehsil.conf
```

Laragon-u yenidən başlat.

Ətraflı: [nginx_config_guide.txt](nginx_config_guide.txt)

---

### Addım 5 – PHP Upload Limiti

`.user.ini` faylı artıq konfiqurasiya olunub:
```ini
upload_max_filesize = 2048M
post_max_size = 2048M
memory_limit = 512M
max_execution_time = 300
```

---

### Addım 6 – Sistemə Daxil Ol

| URL | Məzmun |
|-----|--------|
| `http://distant-tehsil.test/` | Publik cədvəl |
| `http://distant-tehsil.test/student/` | Tələbə paneli |
| `http://distant-tehsil.test/teacher/` | Müəllim paneli |

---

## 🌐 API Endpointləri

### `GET /api/get_turn_credentials.php`
WebRTC ICE server konfiqurasyonunu qaytarır.

**Cavab (uğurlu):**
```json
{
  "success": true,
  "iceServers": [
    { "urls": "stun:stun.l.google.com:19302" },
    { "urls": "stun:stun1.l.google.com:19302" },
    { "urls": "turn:...", "username": "xxx", "credential": "yyy" }
  ],
  "source": "metered",
  "ttl": 86400
}
```

**Cavab (TURN yoxdursa):**
```json
{
  "success": true,
  "iceServers": [...stun only...],
  "source": "fallback_stun_only",
  "warning": "No TURN server configured. Mobile/LTE connections will fail."
}
```

---

### `GET /api/get_active_alerts.php`
Tezliklə başlayacaq aktiv dərsləri qaytarır (bildirişlər üçün).

---

### `POST /api/upload_chat_file.php`
Canlı dərs çatında fayl yükləmə.

**Form data:** `file` (multipart)  
**Cavab:** `{ "success": true, "url": "/uploads/chat/..." }`

---

### `POST /api/live/upload_chunk.php`
Canlı dərs video chunk-larını yükləmə (dərs yazısı üçün).

---

## 🗄️ Verilənlər Bazası

### Əsas Cədvəllər

| Cədvəl | Məzmun |
|--------|--------|
| `users` | Sistem istifadəçiləri (tələbə, müəllim, admin) |
| `instructors` | Müəllim profayları (TMİS `user_id` ilə əlaqəli) |
| `courses` | Fənlər (`tmis_subject_id` da saxlanılır) |
| `live_classes` | Canlı dərslər – başlama vaxtı, status, `recording_path`, `is_visible`, `views`, `duration_minutes` |
| `archived_lessons` | Manual arxivlər – `video_url`, `pdf_url`, `is_visible` |
| `live_attendance` | Canlı dərsdə iştirak – kim, nə vaxt qoşuldu/ayrıldı |
| `webinars` | Vebinarlar – mövzu, aid olduğu fakültə, tarix, plakat və statuslar |
| `webinar_users` | Vebinarın idarəolunması üçün nəzərdə tutulmuş istifadəçilər (Təşkilatçı) |

### Miqrasiyalar

```
database/migrations/
└── 001_add_is_visible_to_lessons.sql     # live_classes.is_visible (DEFAULT 1)
                                          # archived_lessons.is_visible (DEFAULT 1)
```

> Yeni miqrasiya faylları `002_`, `003_` formatında ardıcıl əlavə edilməlidir.

---

## 🌐 Nginx Konfiqurasiyas

`nginx.conf` faylı aşağıdakı əsas konfiqurasyonları əhatə edir:

- Böyük fayl yükləmə dəstəyi (`client_max_body_size 2G`)
- PHP-FPM integration
- API endpointləri üçün CORS başlıqları
- WebRTC üçün uzun müddətli conexiyalar (`proxy_read_timeout`)
- Uploads qovluğu üçün content-type başlıqları

---

## 🔒 Təhlükəsizlik

| Sahə | Mexanizm |
|------|---------|
| **SSO Autentifikasiya** | HMAC SHA256 imzalı token; `SSO_API_SECRET` `.env`-də saxlanılır |
| **TURN Credentials** | Hər sorğuda Metered.ca API-dən dinamik alınır (cache yoxdur) |
| **`.env` Faylı** | `.gitignore`-a əlavə edilib — heç vaxt commit etmə |
| **Upload Qovluğu** | PHP icrasına bağlı deyil |
| **SQLi Qorunma** | Prepared statements (PDO) |
| **XSS Qorunma** | `e()` helper funksiyası (`htmlspecialchars`) |
| **Session** | `requireInstructor()` / `$auth->isLoggedIn()` ilə hər səhifə qorunur |

---

## 📖 Əlavə Sənədlər

| Fayl | Məzmun |
|------|--------|
| [TMİS_STUDENT_INTEGRATION_GUIDE.md](TMİS_STUDENT_INTEGRATION_GUIDE.md) | Tələbə SSO axını, token strukturu, API cavab formatları |
| [TMİS_TEACHER_INTEGRATION_GUIDE.md](TMİS_TEACHER_INTEGRATION_GUIDE.md) | Müəllim SSO, fənn sinxronizasiyas, həll yolları |
| [database/MIGRATION_GUIDE.md](database/MIGRATION_GUIDE.md) | Verilənlər bazası miqrasiya qaydaları |
| [nginx_config_guide.txt](nginx_config_guide.txt) | Nginx quraşdırma addımları |

---

## 🔄 TMİS Data Sinxronizasiyası

```bash
# Köhnə dərslər üçün data geri doldurma (manual icra)
php sync_and_backfill.php

# Cron ilə avtomatik (hər gün sübh 3:00-da)
0 3 * * * php /path/to/distant-tehsil/sync_and_backfill.php
```

---

© 2024–2026 NDU Distant Təhsil Portalı · Naxçıvan Dövlət Universiteti
