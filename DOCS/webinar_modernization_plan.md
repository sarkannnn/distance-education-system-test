# 🚀 Vebinar Sisteminin Modernləşdirilməsi: Texniki Tapşırıq (Webinar V2)

Bu sənəd mövcud Vebinar modulunun PeerJS-dən LiveKit infrastrukturuna keçidini və "Zoom/Teams" səviyyəli yeni xüsusiyyətlərin əlavə edilməsini təsvir edir.

---

## 1️⃣ Mövzu: PeerJS-dən LiveKit SFU-ya Keçid (İnfrastruktur)

### ❌ Bu özəllik yoxdur (Mövcud Vəziyyət)
Hazırda sistem PeerJS (Mesh) arxitekturası ilə işləyir. İştirakçılar bir-birinə birbaşa qoşulur. 5-10 nəfərdən çox iştirakçı eyni anda kamera açdıqda sistem donur və internet yükü həddindən artıq artır.

### 🎯 Niyə lazımdır?
Vebinarlar adətən böyük auditoriyalar (50-100+ nəfər) üçün nəzərdə tutulur. PeerJS-in texniki limiti buna imkan vermir. LiveKit (SFU) isə bütün yükü serverə götürərək, hər kəsin stabil və kəsintisiz video izləməsini təmin edir.

### 🛠️ Necə edəcəyik?
- [x] **Backend:** `api/livekit_token.php` yaradılıb.
- [x] **Frontend:** PeerJS SDK-sı çıxarılıp, `livekit-client` SDK-sı əlavə olunub.
- [x] **Transport:** Video axınları artıq brauzerdən-brauzerə yox, `wss://distant-l.ndu.edu.az` serverinə göndərilir.

---

## 2️⃣ Mövzu: Qalereya Görünüşü (Zoom Style - Gallery View)

### ❌ Bu özəllik yoxdur
Hazırda mühazirəçi yalnız bir tələbəni "Səhnəyə" (Stage) çıxara bilir. Digər tələbələr bir-birini görə bilmir.

### 🎯 Niyə lazımdır?
Real interaktivlik üçün bütün iştirakçıların (və ya icazə verilənlərin) eyni anda ekranda görünməsi, bir-birini eşitməsi (Zoom rejimi) təhsil keyfiyyətini artırır. Hətta eyni hesabla girən 10 nəfər belə fərqli pəncərələrdə görünməlidir.

### 🛠️ Necə edəcəyik?
- [x] **Unique Identity:** Hər bir bağlantı üçün `userId_random` formatında unikal şəxsiyyət ID-si yaradılıb.
- [x] **CSS Grid Layout:** `webinar/view.php` daxilində dinamik şəbəkə sistemi qurulub. İştirakçı sayı artdıqca pəncərələr avtomatik olaraq 2x2, 3x3 və s. formasına düşür.
- [ ] **Selective Speaker:** İnterneti zəif olanlar üçün yalnız danışan şəxsin videosunu görmə rejimi (Active Speaker) əlavə ediləcək. (In Progress)

---

## 3️⃣ Mövzu: Magic Link (Qonaq Mühazirəçi Girişi)

### ❌ Bu özəllik yoxdur
Hazırda mühazirəçi paneline daxil olmaq üçün mütləq sistemdə "Teacher" hesabı olmalıdır. Kənardan dəvət edilən qonaqlar üçün bu çətinlik yaradır.

### 🎯 Niyə lazımdır?
Universitetə dəvət olunan qonaq professorların sistemi öyrənməsinə və ya qeydiyyatdan keçməsinə vaxt yoxdur. Onlara sadəcə bir link atmaqla "Teams"dəki kimi yayıma qoşulmalarını təmin etmək lazımdır.

### 🛠️ Necə edəcəyik?
- [x] **Token Generator:** Admin panelində "Qonaq Linki Yarat" düyməsi əlavə olunub.
- [x] **Bypass Auth:** `webinar/studio_guest.php` səhifəsi yaradılıb. Bu səhifə URL-dəki unikal JWT token ilə işləyir.
- [x] **Simplified UI:** Qonaq üçün yalnız ən vacib düymələr (Kamera, Mikrofon, Ekran Paylaşımı) olan sadə studiya görünüşü təqdim olunur.

---

## 4️⃣ Mövzu: Gözləmə Otağı (Lobby / Green Room)

### ❌ Bu özəllik yoxdur
Mühazirəçi studiyanı açan kimi dərhal canlı yayıma başlayır. Hazırlıq etməyə (saçını, səsini, arxa fonunu yoxlamağa) imkanı olmur.

### 🎯 Niyə lazımdır?
Mühazirəçinin yayım öncəsi özünü görməsi, texniki problemləri (məs: mikrofonda səs yoxdursa) əvvəlcədə bilməsi və hər şey hazır olduqda "CANLIYA KEÇ" düyməsinə basması peşəkarlıqdır.

### 🛠️ Necə edəcəyik?
- [x] **Pre-join UI:** Studiyaya girişdən öncə "Setup" pərdəsi (Lobby) əlavə olunub.
- [x] **Local Preview:** Kamera videosu lokal olaraq göstərilir, serverə (LiveKit) yalnız qoşulduqda göndərilir.
- [x] **Ready Switch:** Mühazirəçi təsdiq etdikdən sonra yayım başlayır.

---

## 5️⃣ Mövzu: Picture-in-Picture (PiP) və Arxa Fon Bulandırma

### ❌ Bu özəllik yoxdur
Tələbə yayımı izləyərkən brauzer tab-ını dəyişəndə videonu itirir. Mühazirəçi isə arxa fonunu gizlədə bilmir.

### 🎯 Niyə lazımdır?
İstifadəçi rahatlığı (UX) üçün vacibdir. Tələbə qeydlər apararkən müəllimi görməyə davam etməli, müəllim isə məxfiliyini (fonunu) qoruya bilməlidir.

### 🛠️ Necə edəcəyik?
- [x] **Web API PiP:** Brauzerin nativ `requestPictureInPicture()` funksiyası aktivləşdirilib.
- [ ] **Virtual Background:** LiveKit-in prosessor imkanları ilə mühazirəçi üçün "Background Blur" (Arxa fonu bulandır) seçimi əlavə ediləcək. (Planned)

---

> [!IMPORTANT]
> **Tətbiq Ardıcıllığı:** 
> 1. İlk olaraq **Mərhələ 1 (İnfrastruktur)** bitirilməlidir.
> 2. Digər bütün xüsusiyyətlər LiveKit üzərində "plugin" kimi bir-bir əlavə olunacaq.
