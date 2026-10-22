<?php
defined('APP') or exit;

$user = require_login();
$id = get_int('id');
$project = load_project($id);
$role = $project['my_role'];

if (is_post()) {
    $action = post('action');
    deny_unless($role, 'owner');

    if ($action === 'delete_project') {
        q('DELETE FROM projects WHERE id = ?', [$id]);
        flash('success', 'Đã xóa dự án "' . $project['name'] . '".');
        redirect('projects');
    }

    if ($action === 'add_member') {
        $who = post('who');
        $newRole = post('role');
        $member = row('SELECT * FROM users WHERE username = ? OR email = ?', [$who, $who]);
        if (!isset(PROJECT_ROLES[$newRole])) {
            flash('error', 'Quyền không hợp lệ.');
        } elseif (!$member) {
            flash('error', 'Không tìm thấy người dùng "' . $who . '". Người đó cần đăng ký tài khoản trước.');
        } elseif (val('SELECT COUNT(*) FROM project_members WHERE project_id = ? AND user_id = ?', [$id, $member['id']])) {
            flash('error', $member['full_name'] . ' đã là thành viên của dự án.');
        } else {
            insert('project_members', ['project_id' => $id, 'user_id' => $member['id'], 'role' => $newRole, 'added_at' => now()]);
            flash('success', 'Đã thêm ' . $member['full_name'] . ' với quyền "' . PROJECT_ROLES[$newRole] . '".');
        }
        redirect('project', ['id' => $id]);
    }

    if (in_array($action, ['change_role', 'remove_member'], true)) {
        $memberId = (int)post('member_id');
        $m = row('SELECT * FROM project_members WHERE id = ? AND project_id = ?', [$memberId, $id]);
        if (!$m) abort(404, 'Thành viên không tồn tại.');
        $owners = (int)val("SELECT COUNT(*) FROM project_members WHERE project_id = ? AND role = 'owner'", [$id]);
        $losingOwner = $m['role'] === 'owner' && ($action === 'remove_member' || post('role') !== 'owner');

        if ($losingOwner && $owners <= 1) {
            flash('error', 'Dự án cần ít nhất một người phụ trách API.');
        } elseif ($action === 'remove_member') {
            q('DELETE FROM project_members WHERE id = ?', [$memberId]);
            flash('success', 'Đã xóa thành viên khỏi dự án.');
        } elseif (isset(PROJECT_ROLES[post('role')])) {
            update('project_members', ['role' => post('role')], 'id = ?', [$memberId]);
            flash('success', 'Đã cập nhật quyền thành viên.');
        }
        redirect('project', ['id' => $id]);
    }
}

$endpoints = rows("SELECT e.*,
        (SELECT COUNT(*) FROM schema_versions v WHERE v.endpoint_id = e.id) AS version_count,
        (SELECT v.label FROM schema_versions v WHERE v.endpoint_id = e.id ORDER BY v.id DESC LIMIT 1) AS latest_label,
        (SELECT COUNT(*) FROM validation_runs r WHERE r.endpoint_id = e.id AND r.status = 'fail') AS failed_runs
    FROM endpoints e WHERE e.project_id = ? ORDER BY e.path, e.method", [$id]);

$members = rows('SELECT m.*, u.username, u.full_name, u.email FROM project_members m JOIN users u ON u.id = m.user_id
    WHERE m.project_id = ? ORDER BY CASE m.role WHEN \'owner\' THEN 1 WHEN \'editor\' THEN 2 ELSE 3 END, u.full_name', [$id]);

$actions = '';
if (role_at_least($role, 'editor')) {
    $actions .= '<a class="btn btn-primary" href="' . e(url('endpoint_form', ['project_id' => $id])) . '">' . icon('plus') . 'Thêm endpoint</a>';
}
if (role_at_least($role, 'owner')) {
    $actions .= '<a class="btn" href="' . e(url('project_form', ['id' => $id])) . '">' . icon('edit') . 'Sửa</a>';
}

layout_start($project['name'], 'projects');
page_head(e($project['name']) . ' ' . role_badge($role), ['Dự án' => url('projects'), $project['name'] => null], $actions, e($project['description'] ?? ''));
?>
<section class="card">
    <div class="card-head"><h2>Endpoint <span class="count"><?= count($endpoints) ?></span></h2></div>
    <?php if (!$endpoints): ?>
        <div class="card-body muted">Chưa có endpoint nào.<?php if (role_at_least($role, 'editor')): ?> <a href="<?= e(url('endpoint_form', ['project_id' => $id])) ?>">Thêm endpoint đầu tiên</a>.<?php endif; ?></div>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Endpoint</th><th>Tên</th><th>Phiên bản</th><th>Mới nhất</th><th>Kiểm tra lỗi</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($endpoints as $ep): ?>
                <tr>
                    <td><a href="<?= e(url('endpoint', ['id' => $ep['id']])) ?>"><?= method_badge($ep['method']) ?> <code><?= e($ep['path']) ?></code></a></td>
                    <td><?= e($ep['name']) ?></td>
                    <td><?= (int)$ep['version_count'] ?></td>
                    <td><?= $ep['latest_label'] ? '<strong>' . e($ep['latest_label']) . '</strong>' : '<span class="muted">—</span>' ?></td>
                    <td><?= $ep['failed_runs'] ? badge($ep['failed_runs'] . ' lần không đạt', 'status-fail') : '<span class="muted">—</span>' ?></td>
                    <td class="right"><a class="btn btn-sm" href="<?= e(url('endpoint', ['id' => $ep['id']])) ?>">Mở</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-head"><h2>Thành viên <span class="count"><?= count($members) ?></span></h2></div>
    <table class="table">
        <thead><tr><th>Họ tên</th><th>Tài khoản</th><th>Quyền</th><th>Ngày tham gia</th><?php if ($role === 'owner'): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($members as $m): ?>
            <tr>
                <td><strong><?= e($m['full_name']) ?></strong><?= (int)$m['user_id'] === (int)$user['id'] ? ' <span class="muted">(bạn)</span>' : '' ?></td>
                <td><?= e($m['username']) ?><div class="muted small"><?= e($m['email']) ?></div></td>
                <td>
                    <?php if ($role === 'owner'): ?>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="change_role">
                            <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>">
                            <select class="input input-sm" name="role" data-autosubmit>
                                <?php foreach (PROJECT_ROLES as $k => $label): ?>
                                    <option value="<?= e($k) ?>"<?= selected($k, $m['role']) ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php else: ?>
                        <?= role_badge($m['role']) ?>
                    <?php endif; ?>
                </td>
                <td class="muted"><?= e(fmt_date($m['added_at'])) ?></td>
                <?php if ($role === 'owner'): ?>
                    <td class="right">
                        <form method="post" data-confirm="Xóa <?= e($m['full_name']) ?> khỏi dự án?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove_member">
                            <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>">
                            <button class="btn btn-sm btn-ghost-danger"><?= icon('trash') ?>Xóa</button>
                        </form>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($role === 'owner'): ?>
        <form method="post" class="card-foot add-member">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_member">
            <input class="input" name="who" placeholder="Tên đăng nhập hoặc email" required>
            <select class="input" name="role">
                <?php foreach (PROJECT_ROLES as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= selected($k, 'viewer') ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary"><?= icon('plus') ?>Thêm thành viên</button>
        </form>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-head"><h2>Phân quyền trong dự án</h2></div>
    <div class="card-body">
        <table class="table table-compact">
            <thead><tr><th>Quyền</th><th>Xem mô tả, so sánh, kiểm tra dữ liệu</th><th>Thêm/sửa endpoint & phiên bản mô tả</th><th>Quản lý thành viên, sửa/xóa dự án</th></tr></thead>
            <tbody>
                <tr><td><?= role_badge('viewer') ?></td><td>✔</td><td>—</td><td>—</td></tr>
                <tr><td><?= role_badge('editor') ?></td><td>✔</td><td>✔</td><td>—</td></tr>
                <tr><td><?= role_badge('owner') ?></td><td>✔</td><td>✔</td><td>✔</td></tr>
            </tbody>
        </table>
    </div>
</section>

<?php if ($role === 'owner'): ?>
    <section class="card card-danger">
        <div class="card-body row-between">
            <div><strong>Xóa dự án</strong><p class="muted">Xóa toàn bộ endpoint, phiên bản mô tả và lịch sử kiểm tra của dự án. Không thể hoàn tác.</p></div>
            <form method="post" data-confirm="Xóa vĩnh viễn dự án &quot;<?= e($project['name']) ?>&quot; và toàn bộ dữ liệu?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_project">
                <button class="btn btn-danger"><?= icon('trash') ?>Xóa dự án</button>
            </form>
        </div>
    </section>
<?php endif; ?>
<?php layout_end();
