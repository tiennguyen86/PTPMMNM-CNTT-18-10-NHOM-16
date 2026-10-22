<?php
defined('APP') or exit;

$user = require_login();
[$scope, $sp] = project_scope();

$stats = [
    'projects'  => val("SELECT COUNT(*) FROM projects p WHERE $scope", $sp),
    'endpoints' => val("SELECT COUNT(*) FROM endpoints e JOIN projects p ON p.id = e.project_id WHERE $scope", $sp),
    'versions'  => val("SELECT COUNT(*) FROM schema_versions v JOIN endpoints e ON e.id = v.endpoint_id JOIN projects p ON p.id = e.project_id WHERE $scope", $sp),
    'runs'      => val("SELECT COUNT(*) FROM validation_runs r JOIN endpoints e ON e.id = r.endpoint_id JOIN projects p ON p.id = e.project_id WHERE $scope", $sp),
    'failed'    => val("SELECT COUNT(*) FROM validation_runs r JOIN endpoints e ON e.id = r.endpoint_id JOIN projects p ON p.id = e.project_id WHERE r.status = 'fail' AND $scope", $sp),
];

$recentVersions = rows("SELECT v.id, v.label, v.issue_count, v.created_at, e.id AS endpoint_id, e.method, e.path, p.name AS project_name, u.full_name AS author
    FROM schema_versions v
    JOIN endpoints e ON e.id = v.endpoint_id
    JOIN projects p ON p.id = e.project_id
    LEFT JOIN users u ON u.id = v.created_by
    WHERE $scope ORDER BY v.id DESC LIMIT 6", $sp);

$recentRuns = rows("SELECT r.id, r.title, r.status, r.error_count, r.warning_count, r.created_at, e.method, e.path, v.label
    FROM validation_runs r
    JOIN endpoints e ON e.id = r.endpoint_id
    JOIN projects p ON p.id = e.project_id
    JOIN schema_versions v ON v.id = r.version_id
    WHERE $scope ORDER BY r.id DESC LIMIT 6", $sp);

layout_start('Tổng quan', 'dashboard');
?>
<div class="dashboard-heading">
    <div><div class="eyebrow">TỔNG QUAN HỆ THỐNG</div><h1>Xin chào, <?= e($user['full_name']) ?><span class="greeting-dot">.</span></h1><p class="muted">Mọi thay đổi API, trong một tầm nhìn.</p></div>
    <a class="btn btn-primary" href="<?= e(url('project_form')) ?>"><?= icon('plus') ?>Tạo dự án</a>
</div>
<section class="dashboard-hero">
    <div class="hero-copy">
        <span class="hero-eyebrow"><?= icon('layers') ?> API SCHEMACHECK</span>
        <h2>Thay đổi có kiểm soát.<br>API luôn đồng bộ.</h2>
        <p>Quản lý phiên bản cấu trúc, phát hiện thay đổi ảnh hưởng và kiểm tra dữ liệu trước bước phát hành tiếp theo.</p>
        <a class="btn hero-button" href="<?= e(url('projects')) ?>">Khám phá dự án <?= icon('arrow') ?></a>
    </div>
    <div class="hero-flow" aria-label="Quy trình quản lý API">
        <div class="flow-node"><span class="flow-icon"><?= icon('layers') ?></span><div><small>BƯỚC 01</small><strong>Lưu cấu trúc</strong></div><span class="flow-file">schema.json</span></div>
        <div class="flow-connector"></div>
        <div class="flow-node"><span class="flow-icon"><?= icon('diff') ?></span><div><small>BƯỚC 02</small><strong>So sánh phiên bản</strong></div><span class="flow-file">v1 → v2</span></div>
        <div class="flow-connector"></div>
        <div class="flow-node flow-node-last"><span class="flow-icon"><?= icon('shield') ?></span><div><small>BƯỚC 03</small><strong>Kiểm tra dữ liệu</strong></div><?= icon('check') ?></div>
    </div>
</section>
<div class="section-heading"><div><h2>Không gian API của bạn</h2><p class="muted small">Dữ liệu tổng hợp từ các dự án bạn có quyền truy cập.</p></div><span class="section-caption">TỔNG SỐ HIỆN TẠI</span></div>
<div class="stats">
    <?php foreach ([
        ['projects', 'Dự án', 'folder', 'projects', [], 'Nhóm và tài nguyên'],
        ['endpoints', 'Endpoint', 'activity', 'projects', [], 'Điểm kết nối API'],
        ['versions', 'Phiên bản mô tả', 'layers', 'projects', [], 'Lịch sử cấu trúc'],
        ['runs', 'Lần kiểm tra', 'check', 'runs', [], 'Dữ liệu đã kiểm tra'],
        ['failed', 'Không đạt', 'diff', 'runs', ['status' => 'fail'], 'Cần xem xét lại'],
    ] as [$key, $label, $ic, $targetPage, $params, $caption]): ?>
    <a class="stat<?= $key === 'failed' ? ' stat-danger' : '' ?>" href="<?= e(url($targetPage, $params)) ?>">
        <div class="stat-top"><span class="stat-label"><?= e($label) ?></span><span class="stat-icon"><?= icon($ic) ?></span></div>
        <span class="stat-value"><?= (int)$stats[$key] ?></span><span class="stat-caption"><?= e($caption) ?></span>
    </a>
    <?php endforeach; ?>
</div>

<div class="grid-2 gap-lg dashboard-activity">
    <section class="card">
        <div class="card-head"><div class="card-title"><?= icon('layers') ?><h2>Phiên bản mới nhất</h2></div><a class="link" href="<?= e(url('projects')) ?>">Xem dự án →</a></div>
        <?php if (!$recentVersions): ?>
            <div class="card-body muted">Chưa có phiên bản nào.</div>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Endpoint</th><th>Phiên bản</th><th>Người tạo</th><th>Thời gian</th></tr></thead>
                <tbody>
                <?php foreach ($recentVersions as $v): ?>
                    <tr>
                        <td><a href="<?= e(url('endpoint', ['id' => $v['endpoint_id']])) ?>"><?= method_badge($v['method']) ?> <code><?= e($v['path']) ?></code></a><div class="muted small"><?= e($v['project_name']) ?></div></td>
                        <td><a href="<?= e(url('version', ['id' => $v['id']])) ?>"><strong><?= e($v['label']) ?></strong></a>
                            <?php if ($v['issue_count']): ?> <?= badge($v['issue_count'] . ' vấn đề', 'level-warning') ?><?php endif; ?></td>
                        <td><?= e($v['author']) ?></td>
                        <td class="nowrap muted"><?= e(fmt_date($v['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head"><div class="card-title"><?= icon('activity') ?><h2>Kiểm tra gần đây</h2></div><a class="link" href="<?= e(url('runs')) ?>">Xem tất cả →</a></div>
        <?php if (!$recentRuns): ?>
            <div class="card-body muted">Chưa có lần kiểm tra nào.</div>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Lần kiểm tra</th><th>Kết quả</th><th>Thời gian</th></tr></thead>
                <tbody>
                <?php foreach ($recentRuns as $r): ?>
                    <tr>
                        <td><a href="<?= e(url('run', ['id' => $r['id']])) ?>"><?= e($r['title']) ?></a>
                            <div class="muted small"><?= e($r['method'] . ' ' . $r['path']) ?> · <?= e($r['label']) ?></div></td>
                        <td><?= status_badge($r['status']) ?><div class="muted small"><?= (int)$r['error_count'] ?> lỗi, <?= (int)$r['warning_count'] ?> cảnh báo</div></td>
                        <td class="nowrap muted"><?= e(fmt_date($r['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>

<section class="card">
    <div class="card-head"><div><h2>Từ cấu trúc đến chất lượng</h2><p class="muted small">Bốn bước để hiểu và kiểm soát API của bạn.</p></div><?= icon('arrow') ?></div>
    <div class="card-body steps">
        <div class="step"><span class="step-no">1</span><div><strong>Quản lý dự án</strong><p>Tạo dự án, thêm endpoint và thành viên (quyền xem hoặc sửa). Nhập bản mô tả cấu trúc từ tệp JSON.</p></div></div>
        <div class="step"><span class="step-no">2</span><div><strong>Đọc cấu trúc</strong><p>Hệ thống đọc object lồng nhau, mảng, kiểu cơ bản, trường bắt buộc và báo rõ cấu trúc chưa hỗ trợ.</p></div></div>
        <div class="step"><span class="step-no">3</span><div><strong>So sánh phiên bản</strong><p>Chỉ ra trường thay đổi và mức ảnh hưởng tới ứng dụng theo <a href="<?= e(url('rules')) ?>">bộ quy tắc đã công bố</a>.</p></div></div>
        <div class="step"><span class="step-no">4</span><div><strong>Kiểm tra dữ liệu</strong><p>Kiểm tra phản hồi mẫu có đúng mô tả; lưu lại từng lần kiểm tra, <a href="<?= e(url('errors')) ?>">tìm và lọc lỗi</a>.</p></div></div>
    </div>
</section>
<?php layout_end();
