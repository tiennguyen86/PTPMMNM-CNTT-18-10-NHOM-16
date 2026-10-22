<?php
defined('APP') or exit;

function icon(string $name): string
{
    $paths = [
        'home'    => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'folder'  => '<path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'check'   => '<path d="M9 11l3 3 8-8"/><path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/>',
        'search'  => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'book'    => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/>',
        'users'   => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M16 4a4 4 0 0 1 0 8"/><path d="M22 21a7 7 0 0 0-4-6.3"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'logout'  => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'diff'    => '<path d="M6 3v12"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="6" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/>',
        'upload'  => '<path d="M12 16V4"/><path d="m6 10 6-6 6 6"/><path d="M4 20h16"/>',
        'download'=> '<path d="M12 4v12"/><path d="m6 10 6 6 6-6"/><path d="M4 20h16"/>',
        'trash'   => '<path d="M4 7h16"/><path d="M10 11v6M14 11v6"/><path d="M6 7l1 13h10l1-13"/><path d="M9 7V4h6v3"/>',
        'edit'    => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
        'copy'    => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
        'play'    => '<path d="M7 4v16l13-8z"/>',
        'print'   => '<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 14h12v7H6z"/>',
        'layers'  => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

function badge(string $text, string $class = ''): string
{
    return '<span class="badge ' . e($class) . '">' . e($text) . '</span>';
}

function method_badge(string $method): string
{
    return '<span class="method method-' . e(strtolower($method)) . '">' . e($method) . '</span>';
}

function severity_badge(string $sev): string
{
    return badge(SEVERITIES[$sev] ?? $sev, 'sev-' . $sev);
}

function role_badge(?string $role): string
{
    return $role ? badge(PROJECT_ROLES[$role] ?? $role, 'role-' . $role) : '';
}

function status_badge(string $status): string
{
    return $status === 'pass' ? badge('Đạt', 'status-pass') : badge('Không đạt', 'status-fail');
}

function level_badge(string $level): string
{
    return match ($level) {
        'error'   => badge('Lỗi', 'level-error'),
        'warning' => badge('Cảnh báo', 'level-warning'),
        default   => badge('Thông tin', 'level-info'),
    };
}

function issue_level_badge(string $level): string
{
    return match ($level) {
        'error'   => badge('Chưa hỗ trợ', 'level-error'),
        'warning' => badge('Đã bỏ qua', 'level-warning'),
        default   => badge('Ghi chú', 'level-info'),
    };
}

function selected($a, $b): string
{
    return (string)$a === (string)$b ? ' selected' : '';
}

function pagination_links(array $pg, string $page, array $params): string
{
    if ($pg['pages'] <= 1) return '';
    $html = '<nav class="pagination"><span class="muted">Trang ' . $pg['page'] . '/' . $pg['pages'] . ' · ' . $pg['total'] . ' mục</span>';
    $start = max(1, $pg['page'] - 3);
    $end = min($pg['pages'], $pg['page'] + 3);
    for ($i = $start; $i <= $end; $i++) {
        $cls = $i === $pg['page'] ? 'active' : '';
        $html .= '<a class="' . $cls . '" href="' . e(url($page, $params + ['p' => $i])) . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}

function render_issues(array $issues): string
{
    if (!$issues) {
        return '<div class="alert alert-success">Đọc cấu trúc thành công – không có cấu trúc chưa hỗ trợ.</div>';
    }
    $html = '<table class="table table-compact"><thead><tr><th style="width:120px">Mức</th><th style="width:90px">Phần</th><th>Đường dẫn</th><th>Nội dung</th></tr></thead><tbody>';
    foreach ($issues as $i) {
        $html .= '<tr><td>' . issue_level_badge($i['level']) . '</td><td>' . e($i['part'] ?? '') . '</td>'
            . '<td><code>' . e($i['path']) . '</code></td><td>' . e($i['message']) . '</td></tr>';
    }
    return $html . '</tbody></table>';
}

/* ---------------------------------------------------------------------- */
/* Bố cục trang                                                           */
/* ---------------------------------------------------------------------- */

function html_head(string $title): void
{
    ?><!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e(config('app_name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css?v=1">
</head>
<?php
}

function layout_start(string $title, string $active = ''): void
{
    $user = current_user();
    html_head($title);
    $nav = [
        'dashboard' => ['Tổng quan', 'home'],
        'projects'  => ['Dự án & endpoint', 'folder'],
        'runs'      => ['Lần kiểm tra', 'check'],
        'errors'    => ['Tra cứu lỗi', 'search'],
        'rules'     => ['Bộ quy tắc', 'book'],
    ];
    if (is_admin()) $nav['users'] = ['Người dùng', 'users'];
    ?>
<body>
<div class="app">
    <aside class="sidebar">
        <a class="brand" href="<?= e(url('dashboard')) ?>">
            <span class="brand-mark">{ }</span>
            <span class="brand-text"><?= e(config('app_name')) ?><small>Kiểm tra thay đổi & tương thích</small></span>
        </a>
        <nav class="nav">
            <?php foreach ($nav as $key => [$label, $ic]): ?>
                <a href="<?= e(url($key)) ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= icon($ic) ?><span><?= e($label) ?></span></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-user">
            <a class="user-chip" href="<?= e(url('profile')) ?>" title="Hồ sơ cá nhân">
                <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?></span>
                <span class="user-meta"><strong><?= e($user['full_name']) ?></strong><small><?= e($user['username']) ?><?= $user['role'] === 'admin' ? ' · quản trị' : '' ?></small></span>
            </a>
            <form method="post" action="<?= e(url('logout')) ?>">
                <?= csrf_field() ?>
                <button class="icon-btn" title="Đăng xuất"><?= icon('logout') ?></button>
            </form>
        </div>
    </aside>
    <main class="main">
        <?php foreach (take_flashes() as [$type, $msg]): ?>
            <div class="alert alert-<?= e($type) ?>"><?= e($msg) ?></div>
        <?php endforeach; ?>
<?php
}

function layout_end(): void
{
    ?>
    </main>
</div>
<script src="assets/js/app.js?v=1"></script>
</body>
</html>
<?php
}

function auth_layout_start(string $title): void
{
    html_head($title);
    ?>
<body class="auth-body">
<div class="auth-wrap">
    <div class="auth-side">
        <div class="brand brand-lg"><span class="brand-mark">{ }</span><span class="brand-text"><?= e(config('app_name')) ?></span></div>
        <h2>Công cụ kiểm tra thay đổi và tính tương thích của cấu trúc dữ liệu API</h2>
        <ul class="auth-points">
            <li>Lưu bản mô tả cấu trúc theo từng phiên bản</li>
            <li>So sánh hai phiên bản, chỉ ra thay đổi phá vỡ tương thích</li>
            <li>Kiểm tra dữ liệu API trả về có đúng mô tả hay không</li>
        </ul>
    </div>
    <div class="auth-card">
        <?php foreach (take_flashes() as [$type, $msg]): ?>
            <div class="alert alert-<?= e($type) ?>"><?= e($msg) ?></div>
        <?php endforeach; ?>
<?php
}

function auth_layout_end(): void
{
    ?>
    </div>
</div>
</body>
</html>
<?php
}

/** Tiêu đề trang với breadcrumb và nút thao tác. */
function page_head(string $title, array $crumbs = [], string $actions = '', string $sub = ''): void
{
    echo '<div class="page-head"><div>';
    if ($crumbs) {
        $parts = [];
        foreach ($crumbs as $label => $href) {
            $parts[] = $href ? '<a href="' . e($href) . '">' . e($label) . '</a>' : '<span>' . e($label) . '</span>';
        }
        echo '<div class="crumbs">' . implode('<span class="sep">/</span>', $parts) . '</div>';
    }
    echo '<h1>' . $title . '</h1>';
    if ($sub !== '') echo '<p class="sub">' . $sub . '</p>';
    echo '</div>';
    if ($actions !== '') echo '<div class="actions">' . $actions . '</div>';
    echo '</div>';
}
