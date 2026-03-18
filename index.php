<?php

/**
 * 🎓 NDU Distant Təhsil Sistemi - Publik Cədvəl & Arxiv (V2 UI)
 * Image-based exact recreation of the public-schedule layout.
 */
require_once 'student/config/database.php';
require_once 'student/includes/helpers.php';

$db = Database::getInstance();

// Bu günün Azərbaycan dilində adını al
$weekdays = [
    1 => 'Bazar ertəsi',
    2 => 'Çərşənbə axşamı',
    3 => 'Çərşənbə',
    4 => 'Cümə axşamı',
    5 => 'Cümə',
    6 => 'Şənbə',
    0 => 'Bazar'
];
$todayName = $weekdays[date('w')];

// 1. Bugünkü dərslər - Sadece HAL-HAZIRDA canlı olan dərsləri göstər (Live)
$todayLessons = [];
try {
    $todayLessons = $db->fetchAll(
        "SELECT DISTINCT  lc.id, lc.title as topic_name, lc.status, lc.start_time,
                COALESCE(NULLIF(lc.subject_name, 'Fənn'), c.title, 'Fənn') as course_title, 
                (CASE WHEN lc.is_stream = 1 AND lc.specialty_name IS NOT NULL AND lc.specialty_name != '' AND lc.specialty_name != 'Axın (çoxlu ixtisas)' THEN lc.specialty_name ELSE COALESCE(NULLIF(NULLIF(lc.specialty_name, ''), 'Axın (çoxlu ixtisas)'), i.specialty, i.department, 'Ümumi') END) as specialization_name,
                COALESCE(NULLIF(lc.course_level, '-'), i.course_level, '-') as course_level_val,
                COALESCE(NULLIF(lc.instructor_name, ''), NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), i.name, 'Müəllim təyin edilməyib') as instructor_display_name, 
                COALESCE(NULLIF(lc.instructor_title, ''), i.title, '') as instructor_title
         FROM live_classes lc
         LEFT JOIN courses c ON lc.course_id = c.tmis_subject_id OR lc.course_id = c.id
         LEFT JOIN instructors i ON lc.instructor_id = i.user_id OR lc.instructor_id = i.id
         LEFT JOIN users u ON i.user_id = u.id OR lc.instructor_id = u.id
         WHERE lc.status = 'live'
         ORDER BY lc.start_time ASC"
    );
} catch (Exception $e) {
    if (isset($_GET['debug']))
        echo "Today Error: " . $e->getMessage();
    $todayLessons = [];
}

// 2. Keçirilmiş dərslər (Arxiv) - Ən sonuncu dərsi ən üstdə göstər
$archivedLessons = [];
try {
    // a. Canlı dərslərin yazıları
    $liveRecs = $db->fetchAll(
        "SELECT DISTINCT lc.id, 
                lc.title as topic_name, 
                COALESCE(NULLIF(lc.subject_name, 'Fənn'), c.title, 'Fənn') as course_title, 
                (CASE WHEN lc.is_stream = 1 AND lc.specialty_name IS NOT NULL AND lc.specialty_name != '' AND lc.specialty_name != 'Axın (çoxlu ixtisas)' THEN lc.specialty_name ELSE COALESCE(NULLIF(NULLIF(lc.specialty_name, ''), 'Axın (çoxlu ixtisas)'), i.specialty, i.department, 'Ümumi') END) as specialization_name,
                COALESCE(NULLIF(lc.course_level, '-'), i.course_level, '-') as course_level_val,
                COALESCE(NULLIF(lc.instructor_name, ''), NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), i.name, 'Müəllim təyin edilməyib') as instructor_display_name, 
                COALESCE(NULLIF(lc.instructor_title, ''), i.title, '') as instructor_title,
                lc.start_time as activity_date,
                lc.end_time as end_time,
                COALESCE(lc.duration_minutes, 0) as duration,
                0 as views,
                lc.recording_path as video_url,
                NULL as pdf_url,
                'live' as record_type
         FROM live_classes lc
         LEFT JOIN courses c ON lc.course_id = c.tmis_subject_id OR lc.course_id = c.id
         LEFT JOIN instructors i ON lc.instructor_id = i.user_id OR lc.instructor_id = i.id
         LEFT JOIN users u ON i.user_id = u.id OR lc.instructor_id = u.id
         WHERE lc.status = 'ended' AND lc.recording_path IS NOT NULL AND lc.recording_path != ''"
    );

    // b. Manual yüklənən arxivlər
    $manualArchives = $db->fetchAll(
        "SELECT al.id, 
                al.title as topic_name, 
                COALESCE(NULLIF(al.subject_name, 'Fənn'), c.title, 'Fənn') as course_title, 
                COALESCE(NULLIF(NULLIF(al.specialty_name, ''), 'Axın (çoxlu ixtisas)'), i.specialty, i.department, 'Ümumi') as specialization_name,
                COALESCE(NULLIF(al.course_level, '-'), i.course_level, '-') as course_level_val,
                COALESCE(NULLIF(al.instructor_name, ''), NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), i.name, 'Müəllim təyin edilməyib') as instructor_display_name, 
                COALESCE(NULLIF(al.instructor_title, ''), i.title, '') as instructor_title,
                al.created_at as activity_date,
                al.created_at as end_time,
                al.duration as duration,
                al.views as views,
                al.video_url as video_url,
                al.pdf_url as pdf_url,
                'manual' as record_type
         FROM archived_lessons al
         LEFT JOIN courses c ON al.course_id = c.id
         LEFT JOIN instructors i ON al.instructor_id = i.id OR al.instructor_id = i.user_id
         LEFT JOIN users u ON i.user_id = u.id"
    );

    $allArchives = array_merge($liveRecs, $manualArchives);
    // Sort by date (newest first - DESC)
    usort($allArchives, function ($a, $b) {
        $t1 = strtotime($a['activity_date']);
        $t2 = strtotime($b['activity_date']);
        return $t2 - $t1;
    });
    $archivedLessons = $allArchives;
} catch (Exception $e) {
    if (isset($_GET['debug']))
        echo "Archive Error: " . $e->getMessage();
    $archivedLessons = [];
}
?>
<!DOCTYPE html>
<html lang="az">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distant Təhsil Cədvəli - Naxçıvan Dövlət Universiteti</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --ndu-blue: #0e3760;
            --ndu-accent: #0284c7;
            --bg-gray: #f1f5f9;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-gray);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ===== HEADER — Compact ===== */
        .ndu-header {
            background: linear-gradient(135deg, var(--ndu-blue) 0%, #0c4a6e 100%);
            color: white;
            text-align: center;
            padding: clamp(8px, 1.5vh, 18px) 20px;
            flex-shrink: 0;
        }

        .ndu-header h1 {
            font-size: clamp(16px, 2vw, 24px);
            font-weight: 700;
            margin-bottom: 2px;
            letter-spacing: 0.5px;
        }

        .ndu-header p {
            font-size: clamp(10px, 1.2vw, 13px);
            opacity: 0.8;
        }

        /* ===== MAIN CONTENT — Fills remaining space ===== */
        .main-container {
            flex: 1;
            padding: clamp(8px, 1.2vh, 16px) clamp(10px, 1.5vw, 20px);
            overflow: hidden;
            display: flex;
            justify-content: center;
            min-height: 0;
        }

        .content-grid {
            max-width: 1500px;
            width: 100%;
            display: flex;
            gap: clamp(10px, 1.2vw, 20px);
            height: 100%;
            min-height: 0;
        }

        /* ===== SCHEDULE COLUMNS ===== */
        .schedule-column {
            background: #ffffff;
            border-radius: 12px;
            padding: clamp(12px, 1.5vh, 20px);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            flex: 1;
            height: 100%;
            min-height: 0;
            overflow: hidden;
        }

        /* ===== PORTAL SIDEBAR — Compact ===== */
        .portal-sidebar {
            width: clamp(200px, 18vw, 280px);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap: clamp(8px, 1vh, 14px);
            height: 100%;
            overflow: hidden;
        }

        .sidebar-portal-box {
            background: #ffffff;
            border-radius: 12px;
            padding: clamp(14px, 1.8vh, 22px) clamp(12px, 1.2vw, 18px);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: clamp(6px, 0.8vh, 12px);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            flex: 1;
            min-height: 0;
        }

        .sidebar-portal-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--portal-accent, #0ea5e9);
        }

        .sidebar-portal-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            border-color: var(--portal-accent, #0ea5e9);
        }

        .sidebar-portal-icon {
            width: clamp(36px, 4vh, 46px);
            height: clamp(36px, 4vh, 46px);
            background: color-mix(in srgb, var(--portal-accent, #0ea5e9) 12%, white);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--portal-accent, #0ea5e9);
            border: 1px solid color-mix(in srgb, var(--portal-accent, #0ea5e9) 25%, transparent);
            flex-shrink: 0;
        }

        .sidebar-portal-icon i {
            width: clamp(16px, 2vh, 22px);
            height: clamp(16px, 2vh, 22px);
        }

        .sidebar-portal-box h3 {
            font-size: clamp(13px, 1.4vw, 16px);
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.2;
            margin: 0;
        }

        .sidebar-portal-box p {
            font-size: clamp(11px, 1vw, 13px);
            line-height: 1.4;
            color: var(--text-muted);
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .sidebar-portal-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--portal-accent, #0ea5e9);
            color: white;
            padding: clamp(7px, 1vh, 11px) 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: clamp(11px, 1.1vw, 13px);
            transition: all 0.3s ease;
            width: 100%;
            margin-top: auto;
        }

        .sidebar-portal-btn:hover {
            filter: brightness(1.1);
            box-shadow: 0 4px 14px color-mix(in srgb, var(--portal-accent, #0ea5e9) 40%, transparent);
            transform: scale(1.02);
        }

        /* ===== COLUMN TITLES ===== */
        .column-title {
            font-size: clamp(14px, 1.4vw, 18px);
            font-weight: 700;
            color: var(--ndu-blue);
            margin-bottom: clamp(8px, 1vh, 14px);
            padding-bottom: clamp(6px, 0.8vh, 10px);
            border-bottom: 2px solid var(--bg-gray);
            flex-shrink: 0;
        }

        /* ===== SCROLL AREA — fills column ===== */
        .cards-scroll-area {
            flex: 1 1 auto;
            height: 0;
            overflow-y: auto;
            min-height: 0;
            padding-right: 6px;
            margin-right: -6px;
        }

        /* Custom Scrollbar */
        .cards-scroll-area::-webkit-scrollbar {
            width: 5px;
        }

        .cards-scroll-area::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.02);
            border-radius: 10px;
        }

        .cards-scroll-area::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .cards-scroll-area::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* ===== LESSON CARDS — Compact ===== */
        .lesson-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: clamp(10px, 1.2vh, 15px) clamp(10px, 1vw, 14px);
            margin-bottom: clamp(6px, 0.8vh, 10px);
            border-left: 4px solid var(--ndu-blue);
            transition: all 0.2s ease;
        }

        .lesson-card:last-child {
            margin-bottom: 0;
        }

        .lesson-card:hover {
            transform: translateX(3px);
            background: #f1f5f9;
        }

        .lesson-card.live {
            border-left-color: #ef4444;
            position: relative;
        }

        .card-header-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: clamp(6px, 0.7vh, 10px);
        }

        .badge-live {
            background-color: #ef4444;
            color: white;
            font-size: 9px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 4px;
            animation: blink 2s infinite;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .badge-live::before {
            content: '';
            width: 5px;
            height: 5px;
            background-color: white;
            border-radius: 50%;
            display: inline-block;
        }

        @keyframes blink {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }

        .card-header-title {
            font-size: clamp(12px, 1.2vw, 15px);
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0;
            flex: 1;
            line-height: 1.3;
        }

        .card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 12px;
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: clamp(11px, 1vw, 13px);
            color: #64748b;
            font-weight: 500;
        }

        .meta-item i {
            width: 14px;
            height: 14px;
            color: #94a3b8;
            stroke-width: 2px;
        }

        .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 16px;
            margin-top: clamp(4px, 0.6vh, 8px);
        }

        /* ===== CARD METADATA (Archive cards) ===== */
        .card-metadata-list {
            display: flex;
            flex-direction: column;
            gap: clamp(2px, 0.4vh, 5px);
            margin-bottom: clamp(8px, 1vh, 14px);
        }

        .card-metadata-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: clamp(11px, 1vw, 12px);
            line-height: 1.3;
        }

        .card-metadata-label {
            color: var(--text-muted);
            width: clamp(70px, 7vw, 90px);
            flex-shrink: 0;
            font-weight: 600;
        }

        .card-metadata-value {
            color: #1e293b;
            font-weight: 500;
            flex: 1;
        }

        .card-metadata-value.subject {
            color: var(--ndu-accent);
        }

        .archive-card-footer {
            display: flex;
            gap: 8px;
            padding-top: clamp(8px, 1vh, 14px);
            border-top: 1px solid var(--border-color);
            margin-top: auto;
        }

        .archive-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .archive-btn-primary {
            background: var(--ndu-blue);
            color: white;
            flex: 1;
        }

        .archive-btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .archive-btn-secondary {
            background: #f1f5f9;
            color: var(--ndu-accent);
            width: 38px;
            padding: 0;
        }

        .archive-btn-secondary:hover {
            background: #e2e8f0;
        }

        /* ===== FOOTER — Compact ===== */
        .ndu-footer {
            background-color: #01111d;
            background-image:
                radial-gradient(at 50% 100%, #075985 0px, transparent 40%),
                radial-gradient(at 0% 0%, rgba(12, 74, 110, 0.3) 0px, transparent 50%);
            color: white;
            padding: clamp(10px, 1.5vh, 18px) 20px clamp(8px, 1vh, 14px);
            flex-shrink: 0;
            border-top: 1px solid rgba(56, 189, 248, 0.15);
            position: relative;
            overflow: hidden;
        }

        .ndu-footer::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.6'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.05'/%3E%3C/svg%3E");
            pointer-events: none;
        }

        .footer-container {
            max-width: 1350px;
            margin: 0 auto;
            position: relative;
            z-index: 10;
        }

        .footer-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            margin-bottom: clamp(8px, 1vh, 16px);
        }

        .contact-side {
            flex: 1;
        }

        .footer-section-tag {
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #38bdf8;
            margin-bottom: 8px;
            display: block;
            opacity: 0.7;
        }

        .contact-cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .contact-glass-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 8px 12px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
        }

        .contact-glass-card:hover {
            background: rgba(255, 255, 255, 0.07);
            border-color: rgba(56, 189, 248, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
        }

        .contact-icon-box {
            width: 30px;
            height: 30px;
            background: rgba(56, 189, 248, 0.1);
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.2);
            flex-shrink: 0;
        }

        .contact-icon-box i {
            width: 14px;
            height: 14px;
        }

        .contact-glass-card:hover .contact-icon-box {
            background: #38bdf8;
            color: #01111d;
            box-shadow: 0 0 14px rgba(56, 189, 248, 0.4);
        }

        .contact-info-text h4 {
            font-size: 8px;
            color: rgba(255, 255, 255, 0.4);
            margin-bottom: 1px;
            text-transform: uppercase;
            font-weight: 700;
        }

        .contact-info-text p {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
        }

        /* Portal Side */
        .portal-side {
            display: flex;
            gap: 14px;
        }

        .portal-premium-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 12px;
            backdrop-filter: blur(20px);
            width: 180px;
            display: flex;
            flex-direction: column;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .portal-premium-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--box-accent, #0ea5e9), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .portal-premium-box:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.15);
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
        }

        .portal-premium-box:hover::before {
            opacity: 1;
        }

        .portal-icon-wrapper {
            width: 28px;
            height: 28px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            color: var(--box-accent, #0ea5e9);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .portal-icon-wrapper i {
            width: 14px;
            height: 14px;
        }

        .portal-premium-box:hover .portal-icon-wrapper {
            background: var(--box-accent, #0ea5e9);
            color: white;
            box-shadow: 0 0 12px rgba(14, 165, 233, 0.2);
        }

        .portal-premium-box h3 {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 3px;
            color: #ffffff;
            letter-spacing: -0.01em;
        }

        .portal-premium-box p {
            font-size: 10px;
            line-height: 1.3;
            color: rgba(255, 255, 255, 0.3);
            margin-bottom: 10px;
            flex-grow: 1;
        }

        .premium-action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: var(--box-accent, #0ea5e9);
            color: white;
            padding: 6px 10px;
            border-radius: 7px;
            text-decoration: none;
            font-weight: 700;
            font-size: 11px;
            transition: all 0.3s ease;
        }

        .premium-action-btn:hover {
            transform: scale(1.02);
            filter: brightness(1.1);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3);
        }

        .footer-bottom-info {
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding-top: clamp(6px, 1vh, 12px);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 9px;
            color: rgba(255, 255, 255, 0.3);
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        .dev-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            color: rgba(255, 255, 255, 0.4);
            font-weight: 800;
        }

        .dev-badge span {
            color: #38bdf8;
            background: rgba(56, 189, 248, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8px;
        }

        /* Generic responsive tables */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
        }

        /* ===== RESPONSIVE BREAKPOINTS ===== */

        /* Large laptops / small desktops — allow scroll */
        @media (max-width: 1200px) {
            body {
                height: auto;
                overflow-y: auto;
                overflow-x: hidden;
            }

            .main-container {
                overflow: visible;
                padding: 12px;
            }

            .content-grid {
                flex-wrap: wrap;
                height: auto;
            }

            .portal-sidebar {
                width: 100%;
                flex-direction: row;
                height: auto;
                order: -1;
            }

            .sidebar-portal-box {
                flex: 1;
                min-width: 0;
                flex-direction: row;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px;
                padding: 14px 16px;
            }

            .sidebar-portal-box p {
                display: none;
            }

            .sidebar-portal-btn {
                margin-top: 0;
            }

            .schedule-column {
                flex: 1 1 calc(50% - 8px);
                min-width: 280px;
                height: auto;
                overflow: visible;
            }

            .cards-scroll-area {
                height: auto;
                max-height: 450px;
                overflow-y: auto;
            }
        }

        /* Tablets */
        @media (max-width: 900px) {
            .schedule-column {
                flex: 1 1 100%;
            }

            .cards-scroll-area {
                max-height: 380px;
            }

            .contact-cards-grid {
                grid-template-columns: 1fr 1fr;
            }

            .footer-flex {
                flex-direction: column;
                gap: 16px;
            }

            .portal-side {
                width: 100%;
            }
        }

        /* Small tablets / large phones */
        @media (max-width: 768px) {
            .ndu-header {
                padding: 12px 10px;
            }

            .ndu-header h1 {
                font-size: 18px;
            }

            .ndu-header p {
                font-size: 11px;
            }

            .main-container {
                padding: 8px;
            }

            .content-grid {
                gap: 8px;
            }

            .portal-sidebar {
                gap: 8px;
            }

            .sidebar-portal-box {
                padding: 12px;
                gap: 8px;
                border-radius: 10px;
            }

            .sidebar-portal-box::before {
                height: 3px;
            }

            .sidebar-portal-icon {
                width: 36px;
                height: 36px;
            }

            .sidebar-portal-icon i {
                width: 18px;
                height: 18px;
            }

            .sidebar-portal-box h3 {
                font-size: 14px;
            }

            .sidebar-portal-btn {
                padding: 8px 12px;
                font-size: 12px;
            }

            .schedule-column {
                padding: 12px;
                border-radius: 10px;
            }

            .column-title {
                font-size: 15px;
                margin-bottom: 8px;
                padding-bottom: 6px;
            }

            .lesson-card {
                padding: 10px;
                margin-bottom: 8px;
            }

            .card-header-title {
                font-size: 13px;
            }

            .meta-item {
                font-size: 12px;
            }

            .cards-scroll-area {
                max-height: 320px;
            }

            .ndu-footer {
                padding: 12px 10px 10px;
            }

            .contact-cards-grid {
                grid-template-columns: 1fr;
                gap: 6px;
            }
        }

        /* Mobile */
        @media (max-width: 480px) {
            .ndu-header {
                padding: 10px 8px;
            }

            .ndu-header h1 {
                font-size: 16px;
            }

            .portal-sidebar {
                flex-direction: column;
                gap: 6px;
            }

            .sidebar-portal-box h3 {
                font-size: 13px;
            }

            .sidebar-portal-btn {
                padding: 7px 10px;
                font-size: 11px;
                border-radius: 7px;
            }

            .schedule-column {
                padding: 10px;
            }

            .column-title {
                font-size: 14px;
            }

            .lesson-card {
                padding: 8px 10px;
            }

            .card-header-title {
                font-size: 12px;
            }

            .card-metadata-row {
                flex-direction: column;
                gap: 1px;
                font-size: 11px;
            }

            .card-metadata-label {
                width: 100%;
            }

            .meta-row {
                flex-direction: column;
                gap: 4px;
            }

            .card-meta {
                flex-direction: column;
                gap: 4px;
            }

            .cards-scroll-area {
                max-height: 260px;
            }

            .footer-bottom-info {
                flex-direction: column;
                gap: 8px;
                text-align: center;
            }

            .portal-premium-box {
                width: 100%;
            }

            .portal-side {
                flex-direction: column;
                gap: 8px;
            }
        }
    </style>
</head>

<body>

    <div class="ndu-header">
        <h1>Naxçıvan Dövlət Universiteti</h1>
        <p>Distant Təhsil Mərkəzi</p>
    </div>

    <div class="main-container">
        <div class="content-grid">

            <!-- Portal Login Sidebar (Sol) -->
            <div class="portal-sidebar">
                <div class="sidebar-portal-box" style="--portal-accent: #0ea5e9;">
                    <div class="sidebar-portal-icon">
                        <i data-lucide="graduation-cap"></i>
                    </div>
                    <h3>Tələbə Portalı</h3>
                    <p>Canlı dərslərə qoşulmaq, tapşırıqları yerinə yetirmək və arxiv videoları izləmək üçün daxil olun.
                    </p>
                    <a href="student/login.php" class="sidebar-portal-btn">
                        <span>Tələbə Girişi</span>
                        <i data-lucide="arrow-right" style="width:14px;"></i>
                    </a>
                </div>

                <div class="sidebar-portal-box" style="--portal-accent: #6366f1;">
                    <div class="sidebar-portal-icon">
                        <i data-lucide="user-check"></i>
                    </div>
                    <h3>Müəllim Portalı</h3>
                    <p>Canlı dərsləri idarə etmək, tələbə davamiyyətini izləmək və materialları yükləmək üçün daxil
                        olun.</p>
                    <a href="teacher/login.php" class="sidebar-portal-btn">
                        <span>Müəllim Girişi</span>
                        <i data-lucide="arrow-right" style="width:14px;"></i>
                    </a>
                </div>
            </div>


            <div class="schedule-column">
                <h2 class="column-title">Bu Günün Dərsləri - <?php echo $todayName; ?></h2>

                <?php if (empty($todayLessons)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                        <i data-lucide="calendar-off" size="48" style="margin-bottom: 15px; opacity: 0.2;"></i>
                        <p>Bu gün üçün hələ ki, heç bir dərs planlaşdırılmayıb.</p>
                    </div>
                <?php else: ?>
                    <div class="cards-scroll-area">
                        <?php foreach ($todayLessons as $lesson):
                            $isLive = ($lesson['status'] === 'live');
                            $startTime = date('H:i', strtotime($lesson['start_time']));
                            $endTime = date('H:i', strtotime($lesson['start_time'] . ' +90 minutes'));
                        ?>
                            <div class="lesson-card <?php echo $isLive ? 'live' : ''; ?>">
                                <div class="card-header-wrapper">
                                    <h3 class="card-header-title">
                                        <?php echo e($lesson['topic_name'] ?: $lesson['course_title']); ?>
                                    </h3>
                                    <?php if ($isLive): ?>
                                        <div class="badge-live">Canlı</div>
                                    <?php endif; ?>
                                </div>

                                <div class="card-meta-rows" style="margin-top: 15px;">
                                    <div class="meta-row">
                                        <div class="meta-item">
                                            <i data-lucide="clock"></i>
                                            <span><?php echo $startTime; ?> - <?php echo $endTime; ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <i data-lucide="user"></i>
                                            <span><?php echo e($lesson['instructor_display_name'] ?: 'Müəllim təyin edilməyib'); ?></span>
                                        </div>
                                    </div>
                                    <div class="meta-row" style="margin-top: 12px;">
                                        <div class="meta-item">
                                            <i data-lucide="graduation-cap"></i>
                                            <span style="font-weight: 500;">Kurs: <span
                                                    style="font-weight: 400;"><?php echo $lesson['course_level_val']; ?>-ci
                                                    kurs</span></span>
                                        </div>
                                        <div class="meta-item">
                                            <i data-lucide="map-pin"></i>
                                            <span style="font-weight: 500;">İxtisas: <span
                                                    style="font-weight: 400;"><?php echo e($lesson['specialization_name']); ?></span></span>
                                        </div>
                                    </div>
                                    <div class="meta-row" style="margin-top: 12px;">
                                        <div class="meta-item">
                                            <i data-lucide="book"></i>
                                            <span style="font-weight: 500;">Fənn: <span
                                                    style="font-weight: 400;"><?php echo e($lesson['course_title']); ?></span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="schedule-column">
                <h2 class="column-title">Keçirilmiş Dərslər</h2>

                <?php if (empty($archivedLessons)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                        <i data-lucide="video-off" size="48" style="margin-bottom: 15px; opacity: 0.2;"></i>
                        <p>Hələ ki, heç bir keçirilmiş dərs yazısı yoxdur.</p>
                    </div>
                <?php else: ?>
                    <div class="cards-scroll-area">
                        <?php foreach ($archivedLessons as $archive): ?>
                            <div class="lesson-card">
                                <h3 class="card-header-title" style="margin-bottom: 20px;">
                                    <?php echo e($archive['topic_name'] ?: $archive['course_title']); ?>
                                </h3>

                                <div class="card-metadata-list">
                                    <div class="card-metadata-row">
                                        <span class="card-metadata-label">Fənn:</span>
                                        <span
                                            class="card-metadata-value subject"><?php echo e($archive['course_title']); ?></span>
                                    </div>
                                    <div class="card-metadata-row">
                                        <span class="card-metadata-label">İxtisas:</span>
                                        <span
                                            class="card-metadata-value"><?php echo e($archive['specialization_name']); ?></span>
                                    </div>
                                    <div class="card-metadata-row">
                                        <span class="card-metadata-label">Kurs:</span>
                                        <span class="card-metadata-value"><?php echo $archive['course_level_val']; ?>-cü
                                            kurs</span>
                                    </div>
                                    <div class="card-metadata-row">
                                        <span class="card-metadata-label">Dərs mövzusu:</span>
                                        <span class="card-metadata-value"><?php echo e($archive['topic_name']); ?></span>
                                    </div>
                                    <div class="card-metadata-row">
                                        <span class="card-metadata-label">Müddət:</span>
                                        <span class="card-metadata-value">
                                            <?php
                                            if (is_numeric($archive['duration'])) {
                                                echo $archive['duration'] . ' dəq';
                                            } else {
                                                echo $archive['duration'] ?: '0 dəq';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="card-meta" style="border-top: 1px solid #f1f5f9; padding-top: 15px;">
                                    <div class="meta-item">
                                        <i data-lucide="calendar"></i>
                                        <span><?php echo date('d.m.Y', strtotime($archive['activity_date'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <div class="ndu-footer">
        <div class="footer-container">
            <div class="footer-flex">
                <!-- Left side: Contact Cards -->
                <div class="contact-side">
                    <span class="footer-section-tag">Dəstək və Əlaqə</span>
                    <div class="contact-cards-grid">
                        <a href="mailto:distant@ndu.edu.az" class="contact-glass-card">
                            <div class="contact-icon-box"><i data-lucide="mail"></i></div>
                            <div class="contact-info-text">
                                <h4>E-poçt Ünvanı</h4>
                                <p>distant@ndu.edu.az</p>
                            </div>
                        </a>
                        <a href="tel:+994365440861" class="contact-glass-card">
                            <div class="contact-icon-box"><i data-lucide="phone"></i></div>
                            <div class="contact-info-text">
                                <h4>Qaynar Xətt</h4>
                                <p>+994 (36) 544 08 61</p>
                            </div>
                        </a>
                        <div class="contact-glass-card">
                            <div class="contact-icon-box"><i data-lucide="map-pin"></i></div>
                            <div class="contact-info-text">
                                <h4>Fiziki Ünvan</h4>
                                <p>Naxçıvan ş., Universitet şəhərciyi</p>
                            </div>
                        </div>
                    </div>
                </div>


            </div>

            <div class="footer-bottom-info">
                <div class="copyright-text">
                    © 2026 Naxçıvan Dövlət Universiteti • Distant Təhsil Mərkəzi
                </div>
                <div class="dev-badge">
                    Developed by <span>NSU IT Department</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>

</html>