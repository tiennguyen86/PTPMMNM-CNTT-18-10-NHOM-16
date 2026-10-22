<?php
defined('APP') or exit;

require_login();
$id = get_int('id');
$ep = load_endpoint($id);
$role = $ep['my_role'];

$versions = rows('SELECT v.id, v.label, v.note, v.source_format, v.issue_count, v.created_at, v.request_tree, v.response_tree, u.full_name AS author,
        (SELECT COUNT(*) FROM validation_runs r WHERE r.version_id = v.id) AS run_count
    FROM schema_versions v LEFT JOIN users u ON u.id = v.created_by
    WHERE v.endpoint_id = ? ORDER BY v.id DESC', [$id]);

$runs = rows('SELECT r.*, v.label, u.full_name AS author FROM validation_runs r
    JOIN schema_versions v ON v.id = r.version_id LEFT JOIN users u ON u.id = r.created_by
    WHERE r.endpoint_id = ? ORDER BY r.id DESC LIMIT 8', [$id]);

$actions = '';
if (role_at_least($role, 'editor')) {
    $actions .= '<a class="btn btn-primary" href="' . e(url('version_form', ['endpoint_id' => $id])) . '">' . icon('upload') . 'Thêm phiên bản mô tả</a>';
}
if ($versions) {
    $actions .= '<a class="btn" href="' . e(url('validate', ['endpoint_id' => $id])) . '">' . icon('play') . 'Kiểm tra dữ liệu</a>';
}
if (role_at_least($role, 'editor')) {
    $actions .= '<a class="btn btn-ghost" href="' . e(url('endpoint_form', ['id' => $id])) . '">' . icon('edit') . 'Sửa</a>';
}

layout_start($ep['method'] . ' ' . $ep['path'], 'projects');
page_head(method_badge($ep['method']) . ' <code class="h-code">' . e($ep['path']) . '</code>',
    ['Dự án' => url('projects'), $ep['project_name'] => url('project', ['id' => $ep['project_id']]), $ep['method'] . ' ' . $ep['path'] => null],
    $actions, e(trim(($ep['name'] ?? '') . ($ep['description'] ? ' — ' . $ep['description'] : ''))));
?>

<?php if (count($versions) >= 2): ?>
    <form class="card compare-bar" method="get">
        <input type="hidden" name="page" value="compare">
        <input type="hidden" name="endpoint_id" value="<?= $id ?>">
        <span class="compare-title"><?= icon('diff') ?> So sánh hai phiên bản</span>
        <select class="input" name="from">
            <?php foreach ($versions as $i => $v): ?><option value="<?= (int)$v['id'] ?>"<?= $i === 1 ? ' selected' : '' ?>><?= e($v['label']) ?></option><?php endforeach; ?>
        </select>
        <span class="arrow">→</span>
        <select class="input" name="to">
            <?php foreach ($versions as $i => $v): ?><option value="<?= (int)$v['id'] ?>"<?= $i === 0 ? ' selected' : '' ?>><?= e($v['label']) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-primary">So sánh</button>
    </form>
<?php endif; ?>

<section class="card">
    <div class="card-head"><h2>Phiên bản mô tả cấu trúc <span class="count"><?= count($versions) ?></span></h2></div>
    <?php if (!$versions): ?>
        <div class="empty empty-inline">
            <p>Endpoint chưa có bản mô tả cấu trúc dữ liệu.</p>
            <?php if (role_at_least($role, 'editor')): ?>
                <a class="btn btn-primary" href="<?= e(url('version_form', ['endpoint_id' => $id])) ?>"><?= icon('upload') ?>Nhập bản mô tả đầu tiên</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Phiên bản</th><th>Ghi chú</th><th>Request</th><th>Response</th><th>Cấu trúc chưa hỗ trợ</th><th>Người tạo</th><th>Thời gian</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($versions as $i => $v):
                $req = $v['request_tree'] ? json_decode($v['request_tree'], true) : null;
                $res = $v['response_tree'] ? json_decode($v['response_tree'], true) : null; ?>
                <tr>
                    <td><a href="<?= e(url('version', ['id' => $v['id']])) ?>"><strong><?= e($v['label']) ?></strong></a>
                        <?= $i === 0 ? badge('mới nhất', 'badge-accent') : '' ?></td>
                    <td class="muted clamp-1" title="<?= e($v['note']) ?>"><?= e($v['note']) ?></td>
                    <td><?= $req ? SchemaTree::countFields($req) . ' trường' : '<span class="muted">—</span>' ?></td>
                    <td><?= $res ? SchemaTree::countFields($res) . ' trường' : '<span class="muted">—</span>' ?></td>
                    <td><?= $v['issue_count'] ? badge($v['issue_count'] . ' vấn đề', 'level-warning') : badge('Không', 'status-pass') ?></td>
                    <td><?= e($v['author']) ?></td>
                    <td class="nowrap muted"><?= e(fmt_date($v['created_at'])) ?></td>
                    <td class="right nowrap">
                        <?php if (isset($versions[$i + 1])): ?>
                            <a class="btn btn-sm" href="<?= e(url('compare', ['endpoint_id' => $id, 'from' => $versions[$i + 1]['id'], 'to' => $v['id']])) ?>" title="So sánh với <?= e($versions[$i + 1]['label']) ?>"><?= icon('diff') ?>So với bản trước</a>
                        <?php endif; ?>
                        <a class="btn btn-sm" href="<?= e(url('version', ['id' => $v['id']])) ?>">Xem</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-head"><h2>Lần kiểm tra dữ liệu gần đây</h2><a class="link" href="<?= e(url('runs', ['endpoint_id' => $id])) ?>">Xem tất cả →</a></div>
    <?php if (!$runs): ?>
        <div class="card-body muted">Chưa có lần kiểm tra nào.</div>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Tiêu đề</th><th>Phiên bản</th><th>Phần</th><th>Kết quả</th><th>Người kiểm tra</th><th>Thời gian</th></tr></thead>
            <tbody>
            <?php foreach ($runs as $r): ?>
                <tr>
                    <td><a href="<?= e(url('run', ['id' => $r['id']])) ?>"><?= e($r['title']) ?></a></td>
                    <td><?= e($r['label']) ?></td>
                    <td><?= e(TARGETS[$r['target']] ?? $r['target']) ?></td>
                    <td><?= status_badge($r['status']) ?> <span class="muted small"><?= (int)$r['error_count'] ?> lỗi · <?= (int)$r['warning_count'] ?> cảnh báo</span></td>
                    <td><?= e($r['author']) ?></td>
                    <td class="nowrap muted"><?= e(fmt_date($r['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php layout_end();
