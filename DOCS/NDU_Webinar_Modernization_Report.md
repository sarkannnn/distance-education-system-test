# 🎓 NDU Distant Təhsil və Vebinar Sistemi: Canlı Yayım Arxitekturasının Modernizasiya Hesabatı

Bu sənəd sistemdəki həm **Kütləvi Vebinarlar** (`webinar/` qovluğu), həm də **İnteraktiv Distant Dərslər** (`teacher/` və `student/` qovluqları) modullarında mövcud olan qüsurların fundamental diaqnostikasını, onların həlli yollarını, **LiveKit SFU** arxitekturasına keçid ehtiyacını və əlavə funksiyaları özündə əks etdirən **birləşdirilmiş yekun hesabatdır.**

---

## 🛑 BÖLMƏ 1: Mövcud Sistemin Diaqnostikası (Donma və Gecikmə Səbəbləri)

Hazırkı sistemdə (PeerJS bazalı) səs/görüntü gecikməsinə və dərslərin qopmasına səbəb olan 4 əsas böhran nöqtəsi aşkar edilmişdir:

1. **Dağıdıcı Şəbəkə Yükü (Mesh P2P):** 
   - **Distant Dərslərdə Fəlakət:** Hazırda sistem `PeerJS` istifadə edir. Distant dərs zamanı 5 tələbə və 1 müəllim kamerasını açdıqda, Mesh arxitekturası hər kəsi hər kəsə bağlayır (yəni hər kəs öz videosunu 5 dəfə fərqli adamlara upload edir). Bu həndəsi silsilə ilə artan yük bütün kompüterləri və şəbəkəni anında çökdürür.
   - **Vebinarda Darboğaz:** Mühazirəçi tək başına 30 tələbəyə dərs keçdikdə belə, eyni videonu 30 dəfə fərqli tələbələrə yükləməyə çalışır. Nəticədə yayım boğulur (throttle) və 5-10 saniyəlik qopmalar yaranır.
2. **Qeydiyyatın İnterneti Tıxaması:** 
   - Dərsin videosu brauzerdə çəkilir və **hər 10 saniyədən bir** hissələr (chunk) şəklində serverə yüklənir. 
   - **Nəticə:** Hər 10 saniyədə yaranan nəhəng HTTP yükləməsi Canlı Yayım paketlərinin qarşısını kəsir və sistemdə mütəmadi "stutter" (tıxanma) yaradır.
3. **Səs və Görüntü Sinxronizasiyasının İtməsi (Lip-sync):** 
   - Video və ağ lövhə xüsusi bir Kətanda (Canvas) emal edildiyi üçün millisaniyələrlə gecikir. Səs isə mikrofondan birbaşa gedir. Nəticədə tələbələrdə səs görüntünü qabaqlayır.
4. **TURN/STUN Server Əksikliyi:** 
   - Təhlükəsizlik divarı olan şəbəkələrdə (Universitet Wi-Fi, 4G) P2P bağlantılar bloklanır. Sənəddə TURN server (Körpü) konfiqurasiya edilmədiyi üçün bu şəbəkələrdən daxil olanlarda video ümumiyyətlə açılmır.

---

## ⚖️ BÖLMƏ 2: Niyə PeerJS Qeyri-kafi, LiveKit İdealdir?

Sistemi böyütmək və İdarəçi (Super User) nəzarətini tətbiq etmək üçün mövcud PeerJS strukturu texniki cəhətdən məhduddur. 

| Xüsusiyyət | PeerJS (Köhnə) | LiveKit SFU (Yeni Təklif) |
| :--- | :--- | :--- |
| **Yayım Yükü** | Müəllim videonu neçə tələbə varsa, o qədər upload edir. | Müəllim yalnız **1 dəfə** serverə göndərir, server paylayır. |
| **Şəbəkə İdarəsi** | TURN əksikdir, NAT/Firewall arxasında işləmir. | Daxili WebRTC Routing ilə qapalı şəbəkələrdə 100% işləyir. |
| **Dərsin Qeydiyyatı** | Brauzerdən məcburi chunk yükləməsi (Çökmə riski çoxdur). | **Egress Modulu:** Yük tamamilə serverdədir. İnterneti kəsilməz. |
| **Rollar (İdarəçi)** | Hər kəs eyni hüquqludur. Rolları ayırmaq qeyri-mümkündür. | Daxili JWT token ilə Admin, İzləyici, Müəllim rolları var. |
| **Gecikmə** | Tələbə sayı artdıqca xətti olaraq artır (3+ saniyə). | Tələbə sayından asılı olmayaraq **<100ms** (Real-time). |

---

## 🛡️ BÖLMƏ 3: Super User (İdarəçi) Səlahiyyətləri

Rəhbərliyin dərslərə keyfiyyət nəzarəti etməsi üçün aşağıdakı xüsusiyyətlər tələb olunur. **Bu xüsusiyyətlərin hamısı LiveKit ilə asanlıqla reallaşdırıla bilir, lakin PeerJS ilə demək olar ki, qeyri-mümkündür:**

> [!IMPORTANT]
> **1. Xəyalət Rejimi (Ghost Mode / Auditor)**
> - **Nədir?** İdarəçi dərsə tamamilə gizli şəkildə daxil olur. Sayğacda və siyahıda görünmür.
> - **Niyə LiveKit şərtdir?** PeerJS-də (P2P) gizli qalmaq olmur, çünki müəllimin kompyuteri videonu Adminə göndərdiyini bilir və şəbəkə yükü artır. LiveKit-də isə Admin videonu birbaşa serverdən çəkir, müəllimin heç xəbəri olmur.

> [!IMPORTANT]
> **2. Köməkçi Müəllim (Co-Host) və Müdaxilə**
> - **Nədir?** Müəllim slayd paylaşa bilmədikdə və ya problem yaşadıqda, Admin lövhəni təhvil alır, PDF yükləyir və ya öz səsini açaraq auditoriyaya müdaxilə edir.
> - **Niyə LiveKit şərtdir?** LiveKit "Role-Based Access" (Rollar) təqdim edir. Admin otağa girən kimi server ona avtomatik hər şeyi idarə etmək səlahiyyəti verir.

> [!TIP]
> **3. Uzaqdan İdarəetmə və Gizli Çat**
> - **Nədir?** İdarəçi səs-küy salan tələbələrin (və ya müəllimin) mikrofonunu uzaqdan zorla bağlayır (Force Mute), ehtiyac olduqda dərsi bitirir. Həmçinin müəllimə yalnız özünün görə biləcəyi *Gizli Pop-up Xəbərdarlıq* (Whisper) göndərir.
> - **Niyə LiveKit şərtdir?** LiveKit Server API-si vasitəsilə 1 sətir kodla istənilən istifadəçinin mikrofonunu/kamerasını bağlamaq mümkündür. 

> [!TIP]
> **4. Canlı Diaqnostika Paneli**
> - **Nədir?** Admin ekranda müəllimin internet sürətini, ping və "Packet Loss" statistikasını real vaxtda görür.
> - **Niyə LiveKit şərtdir?** LiveKit bütün bu statistikaları hər saniyə avtomatik olaraq toplayır. PeerJS-də isə bunu etmək üçün sıfırdan qəliz riyazi hesablamalar yazılmalıdır.

---

## 💎 BÖLMƏ 4: LiveKit-in Fundamental Arxitektura Üstünlükləri (Qızıl Xüsusiyyətlər)

Yeni sistemə keçid zamanı standart olaraq (kod səviyyəsində) aktivləşdiriləcək və tədrisin keyfiyyətini kökündən dəyişəcək əsas texniki üstünlüklər:

> [!NOTE]
> **1. Ağıllı Keyfiyyət Tənzimləməsi (Simulcast / Adaptive Bitrate)**
> - **İzahı:** Müəllim videonu serverə 3 fərqli keyfiyyətdə (1080p, 720p, 360p) göndərir. Server hər bir tələbənin internet sürətinə uyğun olan keyfiyyəti ona ötürür.
> - **Faydası:** Sürətli Wi-Fi-a sahib tələbələr yüksək keyfiyyət izləyərkən, kənddə və ya yolda zəif mobil internetlə qoşulan tələbələrin dərsi donmur (sadəcə piksellər azalır).

> [!NOTE]
> **2. Qırılmalara Qarşı İmmunitet (Seamless Reconnection)**
> - **İzahı:** İzləyicinin interneti saniyəlik gedib-gələrsə, sistem arxa planda avtomatik olaraq sessiyanı bərpa edir.
> - **Faydası:** Tələbə və ya müəllim qısa bir internet qopması yaşadıqda səhifəni yeniləməyə (F5 / Refresh) ehtiyac qalmır, video heç nə olmamış kimi qaldığı yerdən davam edir.

> [!NOTE]
> **3. Ekranın Səsini Paylaşmaq (Screen Share Audio)**
> - **İzahı:** Müəllim ekranını paylaşarkən sadəcə görüntünü yox, eyni zamanda həmin ekranın (kompyuterin) səsini də paylaşa bilir.
> - **Faydası:** Dərs zamanı xarici bir səsli təqdimat, proqram səsi və ya YouTube videosu açıldıqda tələbələr o səsi təmiz və sinxron şəkildə eşidəcəklər.

> [!NOTE]
> **4. Aktiv Danışanı Təyin Etmə (Active Speaker Detection)**
> - **İzahı:** Çoxsaylı mikrofonlar açıq olduqda, server real vaxtda kimin danışdığını (səs çıxardığını) təyin edir.
> - **Faydası:** İnteraktiv dərslərdə xaos yaranmır. Danışan şəxsin video pəncərəsi avtomatik önə çıxır və ya ətrafında xüsusi çərçivə (highlight) yaranır.

---

## 🛠️ BÖLMƏ 5: Tətbiq və Keçid Planı (Implementation Plan)

Sistemi dayandırmadan (Zero Downtime) LiveKit-ə keçid etmək üçün **"Paralel İnkişaf"** strategiyası tətbiq ediləcək. Yeni kodlar həm Vebinar, həm də İnteraktiv Distant dərslər üçün fərdi olaraq yazılacaq.

### Dəyişməyəcək (Qorunan) Hissələr
Sistemin əsas sümüyü tamamilə eyni qalacaq:
- **Verilənlər Bazası və Kafedra məntiqi.**
- **İdarəetmə Paneli (Dashboard).**
- **Dizayn, Ağ lövhə və alətlər paneli.**

### Kod Səviyyəsində Dəyişikliklər

**1. Backend (PHP) Əlavələri:**
- `api/livekit_token.php`: Bütün istifadəçilərə daxil olmaq üçün JWT Token verən unversal mərkəz.
- `api/livekit_record.php`: Brauzer əvəzinə serverə qeydiyyat əmri verən API.

**2. Paralel Faylların Yaradılması (Frontend):**
Köhnə sistem silinmədən qovluqlarda LiveKit-in `_livekit.php` əlavəli klonları yaradılacaq:
- **Kütləvi Vebinarlar Üçün:** `webinar/studio_livekit.php` və `webinar/view_livekit.php`
- **İnteraktiv Distant Dərslər Üçün:** `teacher/liveclass_livekit.php` və `student/liveclass_livekit.php`
*Bütün bu fayllardan `PeerJS` kodları çıxarılacaq və yerinə `livekit-client` SDK kodları yazılacaq. İnteraktiv dərslərdə hər kəs eyni otağa "Publisher" və ya "Subscriber" qismində daxil olacaq.*

**3. Server Quraşdırması:**
- Əsas layihə serverinizdə (və ya başqa bir serverdə) `Docker` vasitəsilə LiveKit Server və LiveKit Egress (Record üçün) ayağa qaldırılacaq. Bir alt-domen bağlanacaq.

---

## 🔮 BÖLMƏ 6: Təhlükəsizlik və Qonaq İnteqrasiyası

Yenilənmiş arxitektura üzərində qurulacaq xüsusi imtiyazlı funksiyalar:

> [!CAUTION]
> **1. Təhlükəsizlik və Müəllif Hüquqları**
> - **Dinamik Su Nişanı (Dynamic Watermark):** Dərsin videosu kimsə tərəfindən ekran qeydedicisi ilə çəkilərsə, videonun üzərində şəffaf şəkildə həmin tələbənin ID-si yazılır. Bu, dərslərin sızdırılmasının (leak) qarşısını tamamilə alır.

> [!TIP]
> **2. Qonaq Mühazirəçi (Sehirli Link) və JWT Bilet Sistemi**
> - **İzahı:** Kafedra tərəfindən Vebinar və ya Distant dərs yaradılır. Sistemdən alınan xüsusi "Qonaq Linki" başqa şəhərdə (məsələn, Bakıda) olan kənar bir müəllimə göndərilir. O müəllim heç bir hesab adı yazmadan linkə girən kimi birbaşa mühazirəçi qismində dərsə qoşulur. Tələbələr isə normal qaydada öz portallarından daxil olub dərsi izləyirlər.
> - **LiveKit Arxa Planı:** LiveKit-in daxili istifadəçi bazası yoxdur, o yalnız "Bilet" (JWT Token) ilə işləyir. Qonaq müəllim linkə tıkladıqda, bizim PHP serverimiz anında "Bu şəxsə Publish icazəsi ver" deyə şifrələnmiş bir JWT token yaradır. Brauzer bu tokeni LiveKit-ə təqdim edən kimi müəllim lövhəni idarə etməyə başlayır.
> - **Faydası:** Xaricdən və ya fərqli şəhərdən dəvət olunan qonaq müəllimlər üçün xüsusi hesab açmağa və texniki əngəllər yaratmağa ehtiyac qalmır. Sıfır əziyyətlə mühazirə təşkil olunur.
