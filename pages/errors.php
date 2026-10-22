<?php
defined('APP') or exit;

require_login();
[$scope, $params] = project_scope();

$f = [
    'q'           => get_str('q'),
    'code'        => isset(SchemaValidator::CODES[get_str('code')]) ? get_str('code') : '',
    'level'       => in_array(get_str('level'), ['error', 'warning'], true) ? get_str('level') : '',
    'project_id'  => get_int('project_id') ?: '',
    'endpoint_id' => get_int('endpoint_id') ?: '',
    'from'        => preg_match('/^\d{4}-\d{2}-\d{2}$/', get_str('from')) ? get_str('from') : '',
    'to'          => preg_match('/^\d{4}-\d{2}-\d{2}$/', get_str('to')) ? get_str('to') : '',
];

$where = [$scope];
if ($f['q'] !== '')    { $where[] = '(er.path LIKE ? OR er.message LIKE ? OR er.actual LIKE ?)'; array_push($params, "%{$f['q']}%", "%{$f['q']}%", "%{$f['q']}%"); }
if ($f['code'])        { $where[] = 'er.code = ?'; $params[] = $f['code']; }
if ($f['level'])       { $where[] = 'er.level = ?'; $params[] = $f['level']; }
if ($f['project_id'])  { $where[] = 'p.id = ?'; $params[] = $f['project_id']; }
if ($f['endpoint_id']) { $where[] = 'e.id = ?'; $params[] = $f['endpoint_id']; }
if ($f['from'])        { $where[] = 'r.created_at >= ?'; $params[] = $f['from'] . ' 00:00:00'; }
if ($f['to'])          { $where[] = 'r.created_at <= ?'; $params[] = $f['to'] . ' 23:59:59'; }
$whereSql = implode(' AND ', $where);

$from = 'FROM validation_errors er
    JOIN validation_runs r ON r.id = er.run_id
    JOIN endpoints e ON e.id = r.endpoint_id
    JOIN projects p ON p.id = e.project_id
    JOIN schema_versions v ON v.id = r.version_id';

$pg = paginate((int)val("SELECT COUNT(*) $from WHERE $whereSql", $params), 30);
$items = rows("SELECT er.*, r.title AS run_title, r.created_at AS run_at, e.id AS endpoint_id, e.method, e.path AS ep_path, v.label
    $from WHERE $whereSql ORDER BY er.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);

$byCode = rows("SELECT er.code, COUNT(*) AS n $from WHERE $whereSql GROUP BY er.code ORDER BY n DESC", $params);
$maxN = $byCode ? max(array_column($byCode, 'n')) : 1;

$topPaths = rows("SELECT er.path, e.method, e.path AS ep_path, COUNT(*) AS n $from WHERE $whereSql AND er.level = 'error'
    GROUP BY er.path, e.method, e.path ORDER BY n DESC LIMIT 5", $params);

layout_start('Tra cứu lỗi', 'errors');
page_head('Tra cứu lỗi', [], '', 'Tìm và lọc lỗi trong tất cả các lần kiểm tra dữ liệu đã lưu.');
?>
<form class="card filters" method="get">
    <input type="hidden" name="page" value="errors">
    <label class="field grow"><span>Từ khóa</span><input class="input" name="q" value="<?= e($f['q']) ?>" placeholder="Đường dẫn trường (vd: address), nội dung lỗi, giá trị thực tế..."></label>
    <label class="field"><span>Loại lỗi</span>
        <select class="input" name="code"><option value="">Tất cả</option>
            <?php foreach (SchemaValidator::CODES as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $f['code']) ?>><?= e($label) ?></option><?php endforeach; ?>
        </select></label>
    <label class="field"><span>Mức</span>
        <select class="input" name="level"><option value="">Tất cả</option>
            <option value="error"<?= selected('error', $f['level']) ?>>Lỗi</option>
            <option value="warning"<?= selected('warning', $f['level']) ?>>Cảnh báo</option>
        </select></label>
    <label class="field"><span>Dự án</span>
        <select class="input" name="project_id"><option value="">Tất cả</option>
            <?php foreach (accessible_projects() as $p): ?><option value="<?= (int)$p['id'] ?>"<?= selected($p['id'], $f['project_id']) ?>><?= e($p['name']) ?></option><?php endforeach; ?>
        </select></label>
    <label class="field"><span>Endpoint</span>
        <select class="input" name="endpoint_id"><option value="">Tất cả</option>
            <?php foreach (accessible_endpoints() as $x): ?><option value="<?= (int)$x['id'] ?>"<?= selected($x['id'], $f['endpoint_id']) ?>><?= e($x['method'] . ' ' . $x['path']) ?></option><?php endforeach; ?>
        </select></label>
    <label class="field"><span>Từ ngày</span><input class="input" type="date" name="from" value="<?= e($f['from']) ?>"></label>
    <label class="field"><span>Đến ngày</span><input class="input" type="date" name="to" value="<?= e($f['to']) ?>"></label>
    <div class="filter-actions"><button class="btn btn-primary"><?= icon('search') ?>Tìm</button><a class="btn btn-ghost" href="<?= e(url('errors')) ?>">Xóa lọc</a></div>
</form>

<?php if ($byCode): ?>
    <div class="grid-2 gap-lg">
        <section class="card">
            <div class="card-head"><h2>Thống kê theo loại lỗi</h2></div>
            <div class="card-body bars">
                <?php foreach ($byCode as $c): ?>
                    <a class="bar-row" href="<?= e(url('errors', array_merge($f, ['code' => $c['code']]))) ?>">
                        <span class="bar-label"><?= e(SchemaValidator::CODES[$c['code']] ?? $c['code']) ?></span>
                        <span class="bar-track"><span class="bar-fill" style="width: <?= round($c['n'] / $maxN * 100) ?>%"></span></span>
                        <span class="bar-value"><?= (int)$c['n'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="card">
            <div class="card-head"><h2>Trường hay bị lỗi nhất</h2></div>
            <?php if (!$topPaths): ?>
                <div class="card-body muted">Không có lỗi (mức Lỗi) trong kết quả lọc.</div>
            <?php else: ?>
                <table class="table table-compact">
                    <thead><tr><th>Trường</th><th>Endpoint</th><th class="right">Số lỗi</th></tr></thead>
                    <tbody>
                    <?php foreach ($topPaths as $t): ?>
                        <tr><td><a href="<?= e(url('errors', array_merge($f, ['q' => $t['path']]))) ?>"><code><?= e($t['path']) ?></code></a></td>
                            <td class="small"><?= e($t['method'] . ' ' . $t['ep_path']) ?></td><td class="right"><strong><?= (int)$t['n'] ?></strong></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>
<?php endif; ?>

<section class="card">
    <div class="card-head"><h2>Kết quả <span class="count"><?= $pg['total'] ?></span></h2></div>
    <?php if (!$items): ?>
        <div class="card-body muted">Không tìm thấy lỗi nào phù hợp.</div>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Mức</th><th>Loại lỗi</th><th>Đường dẫn</th><th>Nội dung</th><th>Thực tế</th><th>Lần kiểm tra</th><th>Thời gian</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= level_badge($it['level']) ?></td>
                    <td class="nowrap small"><?= e(SchemaValidator::CODES[$it['code']] ?? $it['code']) ?></td>
                    <td><code><?= e($it['path']) ?></code></td>
                    <td class="small"><?= e($it['message']) ?></td>
                    <td class="small mono"><?= e($it['actual']) ?></td>
                    <td class="small"><a href="<?= e(url('run', ['id' => $it['run_id']])) ?>"><?= e($it['run_title']) ?></a>
                        <div class="muted"><?= e($it['method'] . ' ' . $it['ep_path']) ?> · <?= e($it['label']) ?></div></td>
                    <td class="nowrap muted small"><?= e(fmt_date($it['run_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?= pagination_links($pg, 'errors', $f) ?>
<?php layout_end();
