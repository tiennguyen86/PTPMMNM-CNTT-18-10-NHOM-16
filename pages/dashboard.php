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
page_head('Xin chào, ' . e($user['full_name']), [], '<a class="btn btn-primary" href="' . e(url('project_form')) . '">' . icon('plus') . 'Tạo dự án</a>',
    'Theo dõi thay đổi cấu trúc dữ liệu API và mức độ ảnh hưởng tới các ứng dụng đang sử dụng.');
?>
<div class="stats">
    <a class="stat" href="<?= e(url('projects')) ?>"><span class="stat-label">Dự án</span><span class="stat-value"><?= (int)$stats['projects'] ?></span></a>
    <a class="stat" href="<?= e(url('projects')) ?>"><span class="stat-label">Endpoint</span><span class="stat-value"><?= (int)$stats['endpoints'] ?></span></a>
    <div class="stat"><span class="stat-label">Phiên bản mô tả</span><span class="stat-value"><?= (int)$stats['versions'] ?></span></div>
    <a class="stat" href="<?= e(url('runs')) ?>"><span class="stat-label">Lần kiểm tra dữ liệu</span><span class="stat-value"><?= (int)$stats['runs'] ?></span></a>
    <a class="stat stat-danger" href="<?= e(url('runs', ['status' => 'fail'])) ?>"><span class="stat-label">Lần kiểm tra không đạt</span><span class="stat-value"><?= (int)$stats['failed'] ?></span></a>
</div>

<div class="grid-2 gap-lg">
    <section class="card">
        <div class="card-head"><h2>Phiên bản mô tả mới nhất</h2></div>
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
        <div class="card-head"><h2>Lần kiểm tra gần đây</h2><a class="link" href="<?= e(url('runs')) ?>">Xem tất cả →</a></div>
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
    <div class="card-head"><h2>Quy trình sử dụng</h2></div>
    <div class="card-body steps">
        <div class="step"><span class="step-no">1</span><div><strong>Quản lý dự án</strong><p>Tạo dự án, thêm endpoint và thành viên (quyền xem hoặc sửa). Nhập bản mô tả cấu trúc từ tệp JSON.</p></div></div>
        <div class="step"><span class="step-no">2</span><div><strong>Đọc cấu trúc</strong><p>Hệ thống đọc object lồng nhau, mảng, kiểu cơ bản, trường bắt buộc và báo rõ cấu trúc chưa hỗ trợ.</p></div></div>
        <div class="step"><span class="step-no">3</span><div><strong>So sánh phiên bản</strong><p>Chỉ ra trường thay đổi và mức ảnh hưởng tới ứng dụng theo <a href="<?= e(url('rules')) ?>">bộ quy tắc đã công bố</a>.</p></div></div>
        <div class="step"><span class="step-no">4</span><div><strong>Kiểm tra dữ liệu</strong><p>Kiểm tra phản hồi mẫu có đúng mô tả; lưu lại từng lần kiểm tra, <a href="<?= e(url('errors')) ?>">tìm và lọc lỗi</a>.</p></div></div>
    </div>
</section>
<?php layout_end();
