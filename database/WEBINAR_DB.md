# Webinar Sistemi Verilənlər Bazası Sənədləri

Bu sənəd webinar sistemi üçün yaradılmış cədvəllər, onların strukturu və sistemə daxil edilmiş ilkin məlumatlar haqqında məlumat verir.

## 1. Cədvəl Strukturları

### `webinar_faculties`
Fakultələrin siyahısını saxlayan cədvəl.
| Sütun | Tip | Təsvir |
| :--- | :--- | :--- |
| `id` | INT | Primary Key, Auto Increment |
| `name` | VARCHAR(255) | Fakultənin tam adı |
| `slug` | VARCHAR(100) | URL və istifadəçi adları üçün qısaltma (Unique) |
| `created_at` | TIMESTAMP | Yaradılma vaxtı |

### `webinar_users`
Webinar sisteminə giriş üçün nəzərdə tutulmuş təcrid olunmuş istifadəçi hesabları.
| Sütun | Tip | Təsvir |
| :--- | :--- | :--- |
| `id` | INT | Primary Key, Auto Increment |
| `faculty_id` | INT | Fakultə ID (Foreign Key) |
| `role` | ENUM | 'teacher' və ya 'student' |
| `username` | VARCHAR(100) | Giriş üçün login (Unique) |
| `password_hash` | VARCHAR(255) | Şifrənin hash variantı |
| `full_name` | VARCHAR(255) | İstifadəçinin tam adı |
| `is_active` | TINYINT(1) | Hesabın aktivlik statusu |

### `webinars`
Canlı yayımlar və qeydə alınmış dərslər haqqında məlumatlar.
| Sütun | Tip | Təsvir |
| :--- | :--- | :--- |
| `id` | INT | Primary Key, Auto Increment |
| `faculty_id` | INT | Fakultə ID (Foreign Key) |
| `teacher_id` | INT | Müəllim ID (Foreign Key) |
| `title` | VARCHAR(255) | Webinarın başlığı |
| `description` | TEXT | Webinarın təsviri |
| `status` | ENUM | 'scheduled', 'live', 'ended' |
| `scheduled_at` | DATETIME | Planlaşdırılan vaxt |
| `started_at` | DATETIME | Faktiki başlama vaxtı |
| `ended_at` | DATETIME | Bitmə vaxtı |
| `recording_path`| VARCHAR(255) | Qeydə alınmış videonun yolu |

---

## 2. İlkin Daxil Edilmiş Məlumatlar (Seeds)

### Fakultələr
Sistemə 17 əsas fakultə və mərkəz əlavə edilib. Bəzi nümunələr:
- Fizika-Riyaziyyat (`fizika-riyaziyyat`)
- Tibb (`tibb`)
- Memarlıq və Mühəndislik (`memarliq-muhendislik`)
- Və digərləri...

### İstifadəçi Hesabları
Hər bir fakultə üçün avtomatik olaraq bir müəllim və bir tələbə hesabı yaradılıb.

**Giriş məlumatları formatı:**
- **Müəllim**: `{slug}_teacher`
- **Tələbə**: `{slug}_student`
- **Ortaq Şifrə**: `NDU_Webinar_2024!`

**Nümunə:**
- Tibb Fakultəsi üçün:
    - Müəllim: `tibb_teacher` / `NDU_Webinar_2024!`
    - Tələbə: `tibb_student` / `NDU_Webinar_2024!`

---

## 3. SQL Yaradılma Skripti

```sql
CREATE TABLE IF NOT EXISTS webinar_faculties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS webinar_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    role ENUM('teacher', 'student') NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES webinar_faculties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS webinars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('scheduled', 'live', 'ended') DEFAULT 'scheduled',
    scheduled_at DATETIME,
    started_at DATETIME NULL,
    ended_at DATETIME NULL,
    recording_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (faculty_id),
    INDEX (status),
    FOREIGN KEY (faculty_id) REFERENCES webinar_faculties(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES webinar_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
