<?php
defined('APP') or exit;

$user = require_login();
$id = get_int('id');
$run = row('SELECT r.*, v.label, u.full_name AS author FROM validation_runs r
    JOIN schema_versions v ON v.id = r.version_id LEFT JOIN users u ON u.id = r.created_by WHERE r.id = ?', [$id]);
if (!$run) abort(404, 'Lần kiểm tra không tồn tại.');
$ep = load_endpoint((int)$run['endpoint_id']);
$canDelete = role_at_least($ep['my_role'], 'editor') || (int)$run['created_by'] === (int)$user['id'];

if (is_post() && post('action') === 'delete') {
    if (!$canDelete) abort(403, 'Bạn không có quyền xóa lần kiểm tra này.');
    q('DELETE FROM validation_runs WHERE id = ?', [$id]);
    flash('success', 'Đã xóa lần kiểm tra.');
    redirect('runs');
}

// Lọc lỗi trong lần kiểm tra
$f = [
    'level' => in_array(get_str('level'), ['error', 'warning'], true) ? get_str('level') : '',
    'code'  => isset(SchemaValidator::CODES[get_str('code')]) ? get_str('code') : '',
    'q'     => get_str('q'),
];
$where = ['run_id = ?'];
$params = [$id];
if ($f['level']) { $where[] = 'level = ?'; $params[] = $f['level']; }
if ($f['code'])  { $where[] = 'code = ?'; $params[] = $f['code']; }
if ($f['q'] !== '') { $where[] = '(path LIKE ? OR message LIKE ?)'; array_push($params, "%{$f['q']}%", "%{$f['q']}%"); }
$errors = rows('SELECT * FROM validation_errors WHERE ' . implode(' AND ', $where) . ' ORDER BY id', $params);
$byCode = rows('SELECT code, level, COUNT(*) AS n FROM validation_errors WHERE run_id = ? GROUP BY code, level ORDER BY n DESC', [$id]);

$actions = '<a class="btn" href="' . e(url('validate', ['endpoint_id' => $ep['id'], 'from_run' => $id])) . '">' . icon('play') . 'Chạy lại</a>';

layout_start($run['title'], 'runs');
page_head(e($run['title']) . ' ' . status_badge($run['status']), [
    'Lần kiểm tra' => url('runs'),
    '#' . $id => null,
], $actions);
?>
<div class="info-grid">
    <div><span>Endpoint</span><strong><a href="<?= e(url('endpoint', ['id' => $ep['id']])) ?>"><?= method_badge($ep['method']) ?> <code><?= e($ep['path']) ?></code></a></strong></div>
    <div><span>Phiên bản mô tả</span><strong><a href="<?= e(url('version', ['id' => $run['version_id']])) ?>"><?= e($run['label']) ?></a></strong></div>
    <div><span>Phần kiểm tra</span><strong><?= e(TARGETS[$run['target']] ?? $run['target']) ?></strong></div>
    <div><span>Người kiểm tra</span><strong><?= e($run['author'] ?: '—') ?></strong></div>
    <div><span>Thời gian</span><strong><?= e(fmt_date($run['created_at'])) ?></strong></div>
</div>

<div class="stats stats-3">
    <div class="stat <?= $run['status'] === 'pass' ? 'stat-success' : 'stat-danger' ?>"><span class="stat-label">Kết quả</span><span class="stat-value"><?= $run['status'] === 'pass' ? 'Đạt' : 'Không đạt' ?></span></div>
    <div class="stat"><span class="stat-label">Lỗi</span><span class="stat-value"><?= (int)$run['error_count'] ?></span></div>
    <div class="stat"><span class="stat-label">Cảnh báo</span><span class="stat-value"><?= (int)$run['warning_count'] ?></span></div>
</div>

<?php if ($byCode): ?>
    <div class="code-summary">
        <?php foreach ($byCode as $c): ?>
            <a class="code-pill level-<?= e($c['level']) ?>" href="<?= e(url('run', ['id' => $id, 'code' => $c['code'], 'level' => $c['level']])) ?>">
                <?= e(SchemaValidator::CODES[$c['code']] ?? $c['code']) ?> <strong><?= (int)$c['n'] ?></strong>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="card">
    <div class="card-head"><h2>Danh sách lỗi <span class="count"><?= count($errors) ?></span></h2></div>
    <form class="filters filters-inline" method="get">
        <input type="hidden" name="page" value="run">
        <input type="hidden" name="id" value="<?= $id ?>">
        <select class="input" name="level">
            <option value="">Mọi mức</option>
            <option value="error"<?= selected('error', $f['level']) ?>>Lỗi</option>
            <option value="warning"<?= selected('warning', $f['level']) ?>>Cảnh báo</option>
        </select>
        <select class="input" name="code">
            <option value="">Mọi loại lỗi</option>
            <?php foreach (SchemaValidator::CODES as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $f['code']) ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <input class="input grow" name="q" value="<?= e($f['q']) ?>" placeholder="Tìm theo đường dẫn trường hoặc nội dung lỗi">
        <button class="btn btn-primary">Lọc</button>
        <a class="btn btn-ghost" href="<?= e(url('run', ['id' => $id])) ?>">Xóa lọc</a>
    </form>
    <?php if (!$errors): ?>
        <div class="card-body muted"><?= $run['error_count'] + $run['warning_count'] ? 'Không có lỗi phù hợp bộ lọc.' : 'Dữ liệu khớp hoàn toàn với bản mô tả.' ?></div>
    <?php else: ?>
        <table class="table">
            <thead><tr><th style="width:100px">Mức</th><th>Loại lỗi</th><th>Đường dẫn</th><th>Nội dung</th><th>Mong đợi</th><th>Thực tế</th></tr></thead>
            <tbody>
            <?php foreach ($errors as $er): ?>
                <tr>
                    <td><?= level_badge($er['level']) ?></td>
                    <td class="nowrap"><?= e(SchemaValidator::CODES[$er['code']] ?? $er['code']) ?></td>
                    <td><code><?= e($er['path']) ?></code></td>
                    <td><?= e($er['message']) ?></td>
                    <td class="small mono"><?= e($er['expected']) ?></td>
                    <td class="small mono"><?= e($er['actual']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<details class="card raw">
    <summary>Dữ liệu mẫu đã kiểm tra</summary>
    <pre class="code-block"><?= e(pretty_json_string($run['sample_json'])) ?></pre>
</details>

<?php if ($canDelete): ?>
    <form method="post" class="right" data-confirm="Xóa lần kiểm tra này?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <button class="btn btn-ghost-danger"><?= icon('trash') ?>Xóa lần kiểm tra</button>
    </form>
<?php endif; ?>
<?php layout_end();
