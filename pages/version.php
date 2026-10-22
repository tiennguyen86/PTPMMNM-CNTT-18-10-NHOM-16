<?php
defined('APP') or exit;

require_login();
$id = get_int('id');
$v = load_version($id);
$ep = $v['endpoint'];
$role = $ep['my_role'];

if (is_post() && post('action') === 'delete') {
    deny_unless($role, 'editor');
    q('DELETE FROM schema_versions WHERE id = ?', [$id]);
    flash('success', 'Đã xóa phiên bản ' . $v['label'] . ' và các lần kiểm tra liên quan.');
    redirect('endpoint', ['id' => $ep['id']]);
}

$trees = ['request' => version_tree($v, 'request'), 'response' => version_tree($v, 'response')];
$issues = version_issues($v);
$author = val('SELECT full_name FROM users WHERE id = ?', [$v['created_by']]);
$prev = row('SELECT id, label FROM schema_versions WHERE endpoint_id = ? AND id < ? ORDER BY id DESC LIMIT 1', [$ep['id'], $id]);
$runCount = (int)val('SELECT COUNT(*) FROM validation_runs WHERE version_id = ?', [$id]);

$actions = '';
if ($prev) {
    $actions .= '<a class="btn" href="' . e(url('compare', ['endpoint_id' => $ep['id'], 'from' => $prev['id'], 'to' => $id])) . '">' . icon('diff') . 'So với ' . e($prev['label']) . '</a>';
}
$actions .= '<a class="btn" href="' . e(url('validate', ['endpoint_id' => $ep['id'], 'version_id' => $id])) . '">' . icon('play') . 'Kiểm tra dữ liệu</a>';
if (role_at_least($role, 'editor')) {
    $actions .= '<a class="btn btn-ghost" href="' . e(url('version_form', ['endpoint_id' => $ep['id'], 'from' => $id])) . '">' . icon('copy') . 'Tạo bản mới từ bản này</a>';
}

layout_start($v['label'] . ' · ' . $ep['path'], 'projects');
page_head('Phiên bản ' . e($v['label']), [
    'Dự án' => url('projects'),
    $ep['project_name'] => url('project', ['id' => $ep['project_id']]),
    $ep['method'] . ' ' . $ep['path'] => url('endpoint', ['id' => $ep['id']]),
    $v['label'] => null,
], $actions, e($v['note'] ?? ''));
?>
<div class="info-grid">
    <div><span>Endpoint</span><strong><?= method_badge($ep['method']) ?> <code><?= e($ep['path']) ?></code></strong></div>
    <div><span>Định dạng nguồn</span><strong><?= e(SOURCE_FORMATS[$v['source_format']] ?? $v['source_format']) ?></strong></div>
    <div><span>Người tạo</span><strong><?= e($author ?: '—') ?></strong></div>
    <div><span>Thời gian</span><strong><?= e(fmt_date($v['created_at'])) ?></strong></div>
    <div><span>Lần kiểm tra dùng bản này</span><strong><a href="<?= e(url('runs', ['endpoint_id' => $ep['id'], 'version_id' => $id])) ?>"><?= $runCount ?></a></strong></div>
</div>

<section class="card">
    <div class="card-head"><h2>Báo cáo đọc cấu trúc</h2>
        <span><?= badge(count(array_filter($issues, fn($i) => $i['level'] === 'error')) . ' chưa hỗ trợ', 'level-error') ?>
              <?= badge(count(array_filter($issues, fn($i) => $i['level'] === 'warning')) . ' đã bỏ qua', 'level-warning') ?></span>
    </div>
    <div class="card-body"><?= render_issues($issues) ?></div>
</section>

<div class="tabs" data-tabs>
    <div class="tab-list">
        <?php $first = true; foreach ($trees as $part => $tree): ?>
            <button type="button" class="tab<?= $first ? ' active' : '' ?>" data-tab="<?= $part ?>">
                <?= $part === 'request' ? 'Request' : 'Response' ?> <span class="count"><?= $tree ? SchemaTree::countFields($tree) : 0 ?></span>
            </button>
        <?php $first = false; endforeach; ?>
    </div>

    <?php $first = true; foreach ($trees as $part => $tree): ?>
        <div class="tab-panel<?= $first ? ' active' : '' ?>" data-panel="<?= $part ?>">
            <?php if (!$tree): ?>
                <div class="card"><div class="card-body muted">Phiên bản này không có mô tả cho phần <?= e($part) ?>.</div></div>
            <?php else: ?>
                <div class="grid-2 gap-lg">
                    <section class="card">
                        <div class="card-head"><h2>Cây cấu trúc</h2>
                            <span class="row gap-sm">
                                <a class="btn btn-sm btn-ghost" href="<?= e(url('version_download', ['id' => $id, 'part' => $part, 'kind' => 'raw'])) ?>"><?= icon('download') ?>Tệp gốc</a>
                                <a class="btn btn-sm btn-ghost" href="<?= e(url('version_download', ['id' => $id, 'part' => $part, 'kind' => 'schema'])) ?>"><?= icon('download') ?>JSON Schema</a>
                            </span>
                        </div>
                        <div class="card-body"><?= SchemaTree::renderTree($tree) ?></div>
                    </section>
                    <section class="card">
                        <div class="card-head"><h2>Danh sách trường</h2></div>
                        <table class="table table-compact">
                            <thead><tr><th>Đường dẫn</th><th>Kiểu</th><th>Bắt buộc</th><th>Ràng buộc</th></tr></thead>
                            <tbody>
                            <?php foreach (SchemaTree::flatten($tree) as $f): $n = $f['node']; ?>
                                <tr>
                                    <td><code><?= e($f['path']) ?></code></td>
                                    <td><?= SchemaTree::typeBadge($n) ?></td>
                                    <td><?= $f['path'] === '$' || str_ends_with($f['path'], '[]') ? '<span class="muted">—</span>' : ($f['required'] ? '✔' : '<span class="muted">không</span>') ?></td>
                                    <td class="small"><?= e(trim((isset($n['enum']) ? 'enum: ' . SchemaTree::enumText($n['enum']) . ' ' : '') . SchemaTree::constraintsText($n))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>
                </div>
                <details class="card raw">
                    <summary>Nội dung gốc đã nhập</summary>
                    <pre class="code-block"><?= e(pretty_json_string($v[$part . '_raw'])) ?></pre>
                </details>
            <?php endif; ?>
        </div>
    <?php $first = false; endforeach; ?>
</div>

<?php if (role_at_least($role, 'editor')): ?>
    <section class="card card-danger">
        <div class="card-body row-between">
            <div><strong>Xóa phiên bản</strong><p class="muted">Phiên bản đã lưu không sửa được để giữ lịch sử. Xóa phiên bản sẽ xóa cả <?= $runCount ?> lần kiểm tra dùng bản này.</p></div>
            <form method="post" data-confirm="Xóa phiên bản <?= e($v['label']) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <button class="btn btn-danger"><?= icon('trash') ?>Xóa phiên bản</button>
            </form>
        </div>
    </section>
<?php endif; ?>
<?php layout_end();
