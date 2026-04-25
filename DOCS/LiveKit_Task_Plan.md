# 🚀 Yekun Texniki Tapşırıqlar (Vebinar və Distant Təhsilin LiveKit Keçidi)

Bu fayl, sistemdəki həm **Kütləvi Vebinarların**, həm də **İnteraktiv Distant Dərslərin (Teacher/Student)** LiveKit SFU arxitekturasına keçirilməsi üçün nəzərdə tutulmuş yekun və ən təkmil iş siyahısıdır. Bütün link paylaşımı (Sehirli Link) və interfeys əməliyyatları tam olaraq buraya daxil edilmişdir.

## Mərhələ 1: Hazırlıq və Verilənlər Bazası (Database)
- [ ] Layihədə Composer-in olub-olmamasını yoxlamaq. Yoxdursa, JWT Token yaradılması üçün xüsusi PHP klası əlavə etmək.
- [ ] Layihənin `.env` faylına `LIVEKIT_URL`, `LIVEKIT_API_KEY` və `LIVEKIT_API_SECRET` dəyişənlərini əlavə etmək.
- [ ] `webinars` və ya distant dərslər cədvəlinə (Table) Qonaq Mühazirəçi sistemi üçün `guest_token` (VARCHAR) adlı yeni sütun (column) əlavə etmək.

## Mərhələ 2: Kafedra Paneli və "Sehirli Link" UI Dəyişiklikləri
- [ ] **Dərs Yaratma Ekranı:** Kafedranın dərs/vebinar yaratma səhifəsində "Kənardan Qonaq Müəllim Dəvət Et" quşçuğu (Checkbox) əlavə etmək.
- [ ] **Linkin Yaradılması (PHP):** Dərs yaradılanda arxa planda 30 simvolluq qırılmaz `guest_token` şifrəsi yaradıb bazaya yazmaq.
- [ ] **Linkin Kopyalanması:** Kafedranın idarəetmə panelindəki (Dashboard) dərslər siyahısına xüsusi "🔗 Qonaq Linkini Kopyala" düyməsi əlavə etmək.

## Mərhələ 3: Universal Backend API-lərin Yaradılması (PHP)
- [ ] `api/livekit_token.php` faylını yaratmaq.
  - *Funksiyası:* İstər rəsmi müəllim, istər tələbə, istərsə də qonaq URL-dən (`?guest=token`) gələn müraciəti yoxlayıb uyğun JWT biletini (Publisher/Subscriber rolları ilə) qaytaracaq universal bilet mərkəzi.
- [ ] `api/livekit_record.php` faylını yaratmaq.
  - *Funksiyası:* Dərs başlayanda arxa planda LiveKit Egress (Server-side recording) modulunu işə salacaq API.

## Mərhələ 4: Frontend İnkişafı (Paralel Faylların Yaradılması)
- [ ] **Qonaq Girişi Ekranı (Modal):** Əgər URL-də `?guest=token` varsa, sistem qonaq müəllimi rəsmi SSO-ya yönləndirmədən birbaşa *"Ad və Soyadınızı daxil edin"* adlı sürətli giriş pəncərəsi açacaq.
- [ ] **Kütləvi Vebinar Modulu Üçün:** 
  - `webinar/studio.php` kopyalanaraq `webinar/studio_livekit.php` yaradılacaq (PeerJS silinib LiveKit Publish qoyulacaq).
  - `webinar/view.php` kopyalanaraq `webinar/view_livekit.php` yaradılacaq (PeerJS silinib LiveKit Subscribe qoyulacaq).
- [ ] **İnteraktiv Distant Təhsil Modulu Üçün:** 
  - `teacher/` qovluğundakı canlı dərs idarəetmə faylının kopyası alınıb `teacher/liveclass_livekit.php` yaradılacaq (Çoxsaylı kameralı sistem üçün LiveKit "Publish" məntiqi).
  - `student/` qovluğundakı dərs izləmə faylının kopyası alınıb `student/liveclass_livekit.php` yaradılacaq (Qarşılıqlı əlaqə üçün həm Publish, həm Subscribe məntiqi).

## Mərhələ 5: Server Quraşdırılması (Müştəri / Dostunuzun İşi)
- [ ] VPS serverdə Docker və Docker-Compose quraşdırılması.
- [ ] Serverdə `livekit-cli generate` komandası ilə LiveKit Server və Egress konfiqurasiyasının ayağa qaldırılması.
- [ ] Layihə üçün Alt-domen (məsələn: `live.ndu.edu.az`) yaradıb SSL (HTTPS) ilə serverə bağlamaq.

## Mərhələ 6: Test və Canlıya Çıxış (Production)
- [ ] "Sehirli Link" (`?guest=...`) kopyalanaraq Gizli Pəncərədə (Incognito) test ediləcək.
- [ ] Həm Vebinar (`webinar/`), həm də Distant Dərs (`teacher/` və `student/`) faylları vasitəsilə ən az 3 cihazdan səs/video/ağ lövhə sinxronizasiyası sınaqdan keçiriləcək.
- [ ] Hər şey 100% işlədikdən sonra köhnə fayllar arxivə atılacaq və LiveKit faylları əsas sistem kimi dövriyyəyə girəcək.
