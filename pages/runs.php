<?php
defined('APP') or exit;

require_login();
[$scope, $params] = project_scope();

$f = [
    'project_id'  => get_int('project_id') ?: '',
    'endpoint_id' => get_int('endpoint_id') ?: '',
    'version_id'  => get_int('version_id') ?: '',
    'status'      => in_array(get_str('status'), ['pass', 'fail'], true) ? get_str('status') : '',
    'q'           => get_str('q'),
    'from'        => preg_match('/^\d{4}-\d{2}-\d{2}$/', get_str('from')) ? get_str('from') : '',
    'to'          => preg_match('/^\d{4}-\d{2}-\d{2}$/', get_str('to')) ? get_str('to') : '',
];

$where = [$scope];
if ($f['project_id'])  { $where[] = 'p.id = ?'; $params[] = $f['project_id']; }
if ($f['endpoint_id']) { $where[] = 'e.id = ?'; $params[] = $f['endpoint_id']; }
if ($f['version_id'])  { $where[] = 'v.id = ?'; $params[] = $f['version_id']; }
if ($f['status'])      { $where[] = 'r.status = ?'; $params[] = $f['status']; }
if ($f['q'] !== '')    { $where[] = '(r.title LIKE ? OR e.path LIKE ?)'; array_push($params, "%{$f['q']}%", "%{$f['q']}%"); }
if ($f['from'])        { $where[] = 'r.created_at >= ?'; $params[] = $f['from'] . ' 00:00:00'; }
if ($f['to'])          { $where[] = 'r.created_at <= ?'; $params[] = $f['to'] . ' 23:59:59'; }
$whereSql = implode(' AND ', $where);

$from = 'FROM validation_runs r
    JOIN endpoints e ON e.id = r.endpoint_id
    JOIN projects p ON p.id = e.project_id
    JOIN schema_versions v ON v.id = r.version_id
    LEFT JOIN users u ON u.id = r.created_by';

$pg = paginate((int)val("SELECT COUNT(*) $from WHERE $whereSql", $params));
$runs = rows("SELECT r.*, e.method, e.path, p.name AS project_name, v.label, u.full_name AS author
    $from WHERE $whereSql ORDER BY r.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);

$totals = row("SELECT SUM(CASE WHEN r.status = 'pass' THEN 1 ELSE 0 END) AS pass, SUM(CASE WHEN r.status = 'fail' THEN 1 ELSE 0 END) AS fail $from WHERE $whereSql", $params);

layout_start('Lần kiểm tra', 'runs');
page_head('Lịch sử kiểm tra dữ liệu', [], '<a class="btn" href="' . e(url('errors')) . '">' . icon('search') . 'Tra cứu lỗi</a>',
    'Mỗi lần kiểm tra dữ liệu mẫu đều được lưu lại cùng danh sách lỗi.');
?>
<form class="card filters" method="get">
    <input type="hidden" name="page" value="runs">
    <label class="field"><span>Dự án</span>
        <select class="input" name="project_id"><option value="">Tất cả</option>
            <?php foreach (accessible_projects() as $p): ?><option value="<?= (int)$p['id'] ?>"<?= selected($p['id'], $f['project_id']) ?>><?= e($p['name']) ?></option><?php endforeach; ?>
        </select></label>
    <label class="field"><span>Endpoint</span>
        <select class="input" name="endpoint_id"><option value="">Tất cả</option>
            <?php foreach (accessible_endpoints() as $x): ?><option value="<?= (int)$x['id'] ?>"<?= selected($x['id'], $f['endpoint_id']) ?>><?= e($x['method'] . ' ' . $x['path']) ?></option><?php endforeach; ?>
        </select></label>
    <label class="field"><span>Kết quả</span>
        <select class="input" name="status"><option value="">Tất cả</option>
            <option value="pass"<?= selected('pass', $f['status']) ?>>Đạt</option>
            <option value="fail"<?= selected('fail', $f['status']) ?>>Không đạt</option>
        </select></label>
    <label class="field"><span>Từ ngày</span><input class="input" type="date" name="from" value="<?= e($f['from']) ?>"></label>
    <label class="field"><span>Đến ngày</span><input class="input" type="date" name="to" value="<?= e($f['to']) ?>"></label>
    <label class="field grow"><span>Từ khóa</span><input class="input" name="q" value="<?= e($f['q']) ?>" placeholder="Tiêu đề hoặc đường dẫn"></label>
    <?php if ($f['version_id']): ?><input type="hidden" name="version_id" value="<?= (int)$f['version_id'] ?>"><?php endif; ?>
    <div class="filter-actions"><button class="btn btn-primary">Lọc</button><a class="btn btn-ghost" href="<?= e(url('runs')) ?>">Xóa lọc</a></div>
</form>

<div class="summary-line">
    <span><?= $pg['total'] ?> lần kiểm tra</span>
    <?= badge((int)($totals['pass'] ?? 0) . ' đạt', 'status-pass') ?>
    <?= badge((int)($totals['fail'] ?? 0) . ' không đạt', 'status-fail') ?>
    <?php if ($f['version_id']): ?><span class="muted">· chỉ phiên bản #<?= (int)$f['version_id'] ?></span><?php endif; ?>
</div>

<section class="card">
    <?php if (!$runs): ?>
        <div class="card-body muted">Không có lần kiểm tra nào phù hợp.</div>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>#</th><th>Tiêu đề</th><th>Endpoint</th><th>Phiên bản</th><th>Phần</th><th>Kết quả</th><th>Người kiểm tra</th><th>Thời gian</th></tr></thead>
            <tbody>
            <?php foreach ($runs as $r): ?>
                <tr>
                    <td class="muted"><?= (int)$r['id'] ?></td>
                    <td><a href="<?= e(url('run', ['id' => $r['id']])) ?>"><strong><?= e($r['title']) ?></strong></a></td>
                    <td><?= method_badge($r['method']) ?> <code><?= e($r['path']) ?></code><div class="muted small"><?= e($r['project_name']) ?></div></td>
                    <td><?= e($r['label']) ?></td>
                    <td><?= e($r['target']) ?></td>
                    <td><?= status_badge($r['status']) ?><div class="muted small"><?= (int)$r['error_count'] ?> lỗi · <?= (int)$r['warning_count'] ?> cảnh báo</div></td>
                    <td><?= e($r['author']) ?></td>
                    <td class="nowrap muted"><?= e(fmt_date($r['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?= pagination_links($pg, 'runs', $f) ?>
<?php layout_end();
