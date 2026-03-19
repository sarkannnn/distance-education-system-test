# 🛠 Verilənlər Bazası Miqrasiya Rəhbəri (Database Migration Guide)

Bu layihədə verilənlər bazası strukturunda edilən hər bir dəyişiklik miqrasiya faylları vasitəsilə idarə olunmalıdır. Bu, yerli (local) və server (production) mühitləri arasında sinxronizasiyanı təmin edir.

## 🚀 Yeni Dəyişiklik Necə Edilməlidir?

1.  **Miqrasiya Faylı Yaradın**: `database/migrations/` qovluğunda yeni bir `.sql` faylı yaradın.
    *   Adlandırma: `00X_qısa_təsvir.sql` (məsələn: `002_add_phone_to_users.sql`)
2.  **SQL Komandalarını Yazın**: Faylın daxilinə müvafiq `ALTER TABLE`, `CREATE TABLE` və s. komandaları əlavə edin.
3.  **Yerli Bazada Yoxlayın**: Komandanı öz yerli bazanızda işlədin.
4.  **schema.sql-i Yeniləyin**: Eyni dəyişikliyi `student/database/schema.sql` faylına da tətbiq edin (yeni quraşdırmalar üçün).
5.  **Git-ə Əlavə Edin**: Faylı commit edin və göndərin.
6.  **Serverdə İcra Edin**: Serverə deploy etdikdən sonra yeni miqrasiya faylını server bazasında işlədin.

## 📂 Miqrasiya Tarixçəsi

*   `001_add_is_visible_to_lessons.sql`: `archived_lessons` və `live_classes` cədvəllərinə `is_visible` sütunu əlavə edildi.

---
© 2026 NDU Distant Təhsil Portalı
