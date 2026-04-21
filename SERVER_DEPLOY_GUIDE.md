# 🚀 Vebinar Sistemi - Server Quraşdırma Təlimatı

Bu təlimat vebinar sisteminin son yenilənmələrini serverə tətbiq etmək üçün nəzərdə tutulub. Son dəyişikliklərlə sistem **Kafedra (Department)** strukturuna keçib və Super User xətaları aradan qaldırılıb.

## 1. Məlumat Bazası (SQL) Yeniləmələri

Ən vacib addım budur. Super User-lərin vebinar yarada bilməsi üçün fakültə məhdudiyyəti NULLABLE edilməlidir.

### Variant A (Məsləhət görülən):
Layihənin kök qovluğundakı **`webinar_complete_setup.sql`** faylını birbaşa bazaya import edin. Bu fayl həm strukturu düzəldir, həm də bütün kafedraları və test hesablarını avtomatik yaradır.

### Variant B (Manual SQL):
Əgər manual etmək istəyirsinizsə, aşağıdakı SQL kodlarını bazada icra edin:

```sql
-- 1. İstifadəçilər cədvəlində fakültəni boş (NULL) buraxmağa icazə vermək
ALTER TABLE `webinar_users` MODIFY faculty_id INT NULL;
-- 2. Kafedra sütunu əlavə etmək
ALTER TABLE `webinar_users` ADD COLUMN IF NOT EXISTS `department_id` INT DEFAULT NULL AFTER `faculty_id`;

-- 3. Vebinalar cədvəlində fakültəni boş (NULL) buraxmağa icazə vermək 
ALTER TABLE `webinars` MODIFY faculty_id INT NULL;
-- 4. Kafedra sütunu əlavə etmək
ALTER TABLE `webinars` ADD COLUMN IF NOT EXISTS `department_id` INT DEFAULT NULL AFTER `faculty_id`;

-- 5. Kafedra cədvəlini yaradırıq (əgər yoxdursa)
CREATE TABLE IF NOT EXISTS `webinar_departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 2. Hesabların Sinxronizasiyası

Super User-lərin (adminlərin) vebinar daxilində tanınması üçün ID-lər sinxron olmalıdır. Kodları serverə atdıqdan sonra brauzerdə aşağıdakı ünvanı bir dəfə işlədin:

`https://sizin-sayt.com/scratch/sync_all_admins.php`

Bu skript əsas portaldakı bütün idarəçiləri vebinar bazasına köçürəcək.

## 3. Kodda edilən əsas dəyişikliklər

1.  **`webinar/dashboard.php`**: Mühazirəçilərin öz kafedralarına aid olan, lakin digərləri (Super User) tərəfindən yaradılan vebinarları görməsi üçün filtr düzəldilib.
2.  **`webinar/api/create_webinar.php`**: Adminlər üçün kafedra seçimi və ID uyğunlaşdırılması təmin edilib.
3.  **`webinar/config/auth.php`**: Vebinar və əsas portal arasında vahid Super User autentifikasiyası gücləndirilib.

## 4. Test üçün Hesablar

Bütün yeni yaradılan kafedra hesablarının (məs: `informatika_muhazireci`) şifrəsi: **`Ndu2026!`** olaraq təyin edilib.

---
*P.S. Bu təlimat Antigravity tərəfindən hazırlanıb.*
