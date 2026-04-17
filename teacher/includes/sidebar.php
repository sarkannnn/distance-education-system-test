<?php
/**
 * Sidebar Template for Teacher Portal
 */

$isAdminUser = ($_SESSION['user_role'] ?? 'guest') === 'admin';

$menuItems = [
    ['id' => 'dashboard', 'label' => 'İdarəetmə Paneli', 'icon' => 'home', 'url' => './', 'admin_visible' => true],
    ['id' => 'live', 'label' => 'Canlı Dərslər', 'icon' => 'video', 'url' => 'live-lessons.php', 'admin_visible' => false],
    ['id' => 'plan', 'label' => ($isAdminUser ? 'Distant Arxiv və Resurslar' : 'Arxiv və Resurslar'), 'icon' => 'folder-archive', 'url' => 'plan.php', 'admin_visible' => true],
    ['id' => 'webinar_plan', 'label' => 'Vebinar Arxiv və Resurslar', 'icon' => 'library', 'url' => 'webinar_plan.php', 'admin_visible' => true, 'admin_only' => true],
    ['id' => 'analytics', 'label' => 'Analitika', 'icon' => 'bar-chart-3', 'url' => 'analytics.php', 'admin_visible' => true],
    ['id' => 'chatbot_analytics', 'label' => 'Chatbot Analitikası', 'icon' => 'bot', 'url' => 'chatbot_analytics.php', 'admin_visible' => true, 'admin_only' => true],
    ['id' => 'webinar', 'label' => 'Vebinar Portalı', 'icon' => 'radio', 'url' => '../webinar/dashboard.php', 'admin_visible' => true, 'admin_only' => true],
];

$currentPage = $currentPage ?? 'dashboard';
?>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-container">
            <img src="assets/img/nsu_logo.png" alt="NSU Logo">
        </div>
        <div class="logo-details">
            <div class="uni-name">Naxçıvan Dövlət Universiteti</div>
            <div class="system-name"><?php echo $isAdminUser ? 'Sistem Administratoru' : 'Müəllim Distant Təhsil Sistemi'; ?></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menuItems as $item): ?>
            <?php 
                // Admin only items: show only to admin
                if (!empty($item['admin_only']) && !$isAdminUser) {
                    continue;
                }
                // Non-admin-visible items: hide from admin
                if ($isAdminUser && empty($item['admin_visible'])) {
                    continue;
                }
            ?>
            <a href="<?php echo $item['url']; ?>"
                class="nav-item <?php echo $currentPage === $item['id'] ? 'active' : ''; ?>"
                <?php echo ($item['id'] === 'webinar') ? 'target="_blank"' : ''; ?>>
                <?php if ($currentPage === $item['id']): ?>
                    <div class="nav-indicator"></div>
                <?php endif; ?>
                <i data-lucide="<?php echo $item['icon']; ?>"></i>
                <span>
                    <?php echo $item['label']; ?>
                </span>
            </a>
        <?php endforeach; ?>

        <!-- Logout Item (Visible only on mobile) -->
        <a href="logout.php?action=exit" class="nav-item sidebar-logout-item">
            <i data-lucide="log-out"></i>
            <span>Distant təhsildən çıx</span>
        </a>
    </nav>
</aside>