<?php
/**
 * Chatbot Analytics Dashboard
 * Monitoring AI interactions and FAQs.
 */
$currentPage = 'chatbot_analytics';
$pageTitle = 'Chatbot Analitikası';

// Check permissions (Admin Only)
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once 'includes/helpers.php';

$auth = new Auth();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();
$isAdmin = ($_SESSION['user_role'] === 'admin');

// --- Statistics Calculation ---
$today = date('Y-m-d');

// 1. Total Queries (All time)
$totalQueries = $db->fetch("SELECT COUNT(*) as cnt FROM chatbot_logs")['cnt'];

// 2. Today's Queries
$todayQueries = $db->fetch("SELECT COUNT(*) as cnt FROM chatbot_logs WHERE DATE(created_at) = ?", [$today])['cnt'];

// 3. Source Distribution (AI vs FAQ)
$sources = $db->fetchAll("SELECT source, COUNT(*) as cnt FROM chatbot_logs GROUP BY source");
$sourceStats = ['ai' => 0, 'faq' => 0];
foreach ($sources as $s) {
    if (in_array($s['source'], ['gemini', 'openai'])) {
        $sourceStats['ai'] += $s['cnt'];
    } else if ($s['source'] === 'local_faq') {
        $sourceStats['faq'] += $s['cnt'];
    }
}

// 4. Latest Interactions
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$logs = $db->fetchAll("
    SELECT l.*, u.first_name, u.last_name 
    FROM chatbot_logs l 
    LEFT JOIN users u ON (
        (l.user_role = 'student' AND (l.user_id = u.student_id OR l.user_id = u.id)) OR
        (l.user_role = 'instructor' AND (l.user_id = u.id OR l.user_id = u.student_id))
    )
    ORDER BY l.created_at DESC 
    LIMIT $limit OFFSET $offset
");

// 5. Top Questions (FAQ)
$topFaqs = $db->fetchAll("
    SELECT query, COUNT(*) as cnt 
    FROM chatbot_logs 
    WHERE source = 'local_faq' 
    GROUP BY query 
    ORDER BY cnt DESC 
    LIMIT 5
");

require_once 'includes/header.php';
?>

<!-- Sidebar -->
<?php require_once 'includes/sidebar.php'; ?>

<div class="main-wrapper">
    <?php require_once 'includes/topnav.php'; ?>

    <main class="main-content">
        <div class="content-container space-y-6">
            <div class="page-header">
                <h1>Chatbot Analitikası</h1>
                <p>AI köməkçi və FAQ istifadə statistikaları</p>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="card stat-card">
                    <div class="flex items-center gap-4">
                        <div class="stat-icon" style="background: var(--info-light); color: var(--info);">
                            <i data-lucide="message-square"></i>
                        </div>
                        <div>
                            <p class="text-sm text-secondary">Cəmi Sorğu</p>
                            <h3 class="text-2xl font-bold"><?php echo number_format($totalQueries); ?></h3>
                        </div>
                    </div>
                </div>

                <div class="card stat-card">
                    <div class="flex items-center gap-4">
                        <div class="stat-icon" style="background: var(--success-light); color: var(--success);">
                            <i data-lucide="calendar"></i>
                        </div>
                        <div>
                            <p class="text-sm text-secondary">Bugünkü Sorğu</p>
                            <h3 class="text-2xl font-bold"><?php echo number_format($todayQueries); ?></h3>
                        </div>
                    </div>
                </div>

                <div class="card stat-card">
                    <div class="flex items-center gap-4">
                        <div class="stat-icon" style="background: var(--warning-light); color: var(--warning);">
                            <i data-lucide="zap"></i>
                        </div>
                        <div>
                            <p class="text-sm text-secondary">AI Cavabları</p>
                            <h3 class="text-2xl font-bold"><?php echo number_format($sourceStats['ai']); ?></h3>
                        </div>
                    </div>
                </div>

                <div class="card stat-card">
                    <div class="flex items-center gap-4">
                        <div class="stat-icon" style="background: #e9d5ff; color: #a855f7;">
                            <i data-lucide="help-circle"></i>
                        </div>
                        <div>
                            <p class="text-sm text-secondary">FAQ Klikləri</p>
                            <h3 class="text-2xl font-bold"><?php echo number_format($sourceStats['faq']); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Log Table -->
                <div class="lg:col-span-2 card">
                    <div class="card-header mb-6">
                        <h2 class="text-lg font-bold">Son Qarşılıqlı Əlaqələr</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left" style="border-collapse: separate; border-spacing: 0 8px;">
                            <thead>
                                <tr class="text-secondary text-sm">
                                    <th class="pb-4 font-semibold">Tarix</th>
                                    <th class="pb-4 font-semibold">İstifadəçi</th>
                                    <th class="pb-4 font-semibold">Sual</th>
                                    <th class="pb-4 font-semibold">Mənbə</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="py-3 text-sm text-secondary">
                                            <?php echo date('H:i:s', strtotime($log['created_at'])); ?><br>
                                            <small><?php echo date('d.m.Y', strtotime($log['created_at'])); ?></small>
                                        </td>
                                        <td class="py-3">
                                            <div class="flex flex-col gap-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="badge <?php echo $log['user_role'] === 'student' ? 'badge-primary' : ($log['user_role'] === 'instructor' ? 'badge-success' : 'badge-light'); ?>">
                                                        <?php echo $log['user_role'] === 'instructor' ? 'Müəllim' : ($log['user_role'] === 'student' ? 'Tələbə' : 'Qonaq'); ?>
                                                    </span>
                                                </div>
                                                <span class="text-sm font-medium">
                                                    <?php 
                                                        if (!empty($log['first_name'])) {
                                                            echo e($log['first_name'] . ' ' . $log['last_name']);
                                                        } else if ($log['user_id']) {
                                                            echo '<span class="text-secondary italic">ID: ' . e($log['user_id']) . '</span>';
                                                        } else {
                                                            echo '<span class="text-secondary italic">Naməlum</span>';
                                                        }
                                                    ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-3 text-sm">
                                            <div title="<?php echo e($log['query']); ?>" style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <?php echo e($log['query']); ?>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            <?php 
                                                $sourceClass = 'badge-light';
                                                if ($log['source'] === 'gemini') $sourceClass = 'badge-info';
                                                if ($log['source'] === 'openai') $sourceClass = 'badge-warning';
                                                if ($log['source'] === 'local_faq') $sourceClass = 'badge-success';
                                            ?>
                                            <span class="badge <?php echo $sourceClass; ?>">
                                                <?php echo ucfirst($log['source']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top FAQ Column -->
                <div class="card">
                    <div class="card-header mb-6">
                        <h2 class="text-lg font-bold">Ən Populyar FAQ-lar</h2>
                    </div>
                    <div class="space-y-4">
                        <?php if (empty($topFaqs)): ?>
                            <p class="text-sm text-secondary text-center py-8">Məlumat yoxdur</p>
                        <?php else: ?>
                            <?php foreach ($topFaqs as $faq): ?>
                                <div class="p-4 bg-gray-50 rounded-xl flex items-center justify-between gap-4">
                                    <p class="text-sm font-medium line-clamp-1" title="<?php echo e($faq['query']); ?>">
                                        <?php echo e($faq['query']); ?>
                                    </p>
                                    <span class="badge badge-primary px-3"><?php echo $faq['cnt']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
    .stat-card {
        padding: 20px;
        border-radius: 20px;
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .badge {
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
    }
    .badge-primary { background: var(--primary-light); color: white; }
    .badge-success { background: var(--success); color: white; }
    .badge-info { background: var(--info); color: white; }
    .badge-warning { background: var(--warning); color: white; }
    .badge-light { background: var(--gray-100); color: var(--text-secondary); }
    
    .grid { display: grid; }
    .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
    @media (min-width: 768px) { .md\:grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    @media (min-width: 1024px) { .lg\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); } .lg\:col-span-2 { grid-column: span 2 / span 2; } }
    .gap-6 { gap: 1.5rem; }
    .space-y-6 > * + * { margin-top: 1.5rem; }
    .flex { display: flex; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .gap-4 { gap: 1rem; }
    .text-sm { font-size: 0.875rem; }
    .text-2xl { font-size: 1.5rem; }
    .font-bold { font-weight: 700; }
    .font-semibold { font-weight: 600; }
    .text-secondary { color: var(--text-secondary); }
</style>

<?php require_once 'includes/footer.php'; ?>
