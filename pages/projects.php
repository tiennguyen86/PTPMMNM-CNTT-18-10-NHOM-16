<?php
defined('APP') or exit;

$user = require_login();
[$scope, $sp] = project_scope();
$keyword = get_str('q');

$where = $scope;
$params = $sp;
if ($keyword !== '') {
    $where .= ' AND (p.name LIKE ? OR p.description LIKE ?)';
    array_push($params, "%$keyword%", "%$keyword%");
}

$projects = rows("SELECT p.*,
        (SELECT COUNT(*) FROM endpoints e WHERE e.project_id = p.id) AS endpoint_count,
        (SELECT COUNT(*) FROM project_members m WHERE m.project_id = p.id) AS member_count,
        (SELECT COUNT(*) FROM schema_versions v JOIN endpoints e ON e.id = v.endpoint_id WHERE e.project_id = p.id) AS version_count,
        (SELECT role FROM project_members m WHERE m.project_id = p.id AND m.user_id = ?) AS my_role
    FROM projects p WHERE $where ORDER BY p.id DESC", array_merge([$user['id']], $params));

layout_start('Dự án', 'projects');
page_head('Dự án', [], '<a class="btn btn-primary" href="' . e(url('project_form')) . '">' . icon('plus') . 'Tạo dự án</a>',
    'Mỗi dự án gồm các endpoint, thành viên và các phiên bản mô tả cấu trúc dữ liệu.');
?>
<form class="toolbar" method="get">
    <input type="hidden" name="page" value="projects">
    <input class="input" name="q" value="<?= e($keyword) ?>" placeholder="Tìm theo tên hoặc mô tả dự án...">
    <button class="btn">Tìm</button>
</form>

<?php if (!$projects): ?>
    <div class="empty">
        <h2>Chưa có dự án nào</h2>
        <p>Tạo dự án đầu tiên hoặc nhờ người phụ trách API thêm bạn vào dự án.</p>
        <a class="btn btn-primary" href="<?= e(url('project_form')) ?>"><?= icon('plus') ?>Tạo dự án</a>
    </div>
<?php else: ?>
    <div class="cards">
        <?php foreach ($projects as $p): ?>
            <a class="card card-link" href="<?= e(url('project', ['id' => $p['id']])) ?>">
                <div class="card-body">
                    <div class="row-between">
                        <h3><?= e($p['name']) ?></h3>
                        <?= role_badge($p['my_role'] ?: (is_admin() ? 'owner' : null)) ?>
                    </div>
                    <p class="muted clamp"><?= e($p['description'] ?: 'Chưa có mô tả.') ?></p>
                    <div class="meta-row">
                        <span><strong><?= (int)$p['endpoint_count'] ?></strong> endpoint</span>
                        <span><strong><?= (int)$p['version_count'] ?></strong> phiên bản</span>
                        <span><strong><?= (int)$p['member_count'] ?></strong> thành viên</span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php layout_end();
