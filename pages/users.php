<?php
defined('APP') or exit;

require_admin();
$me = current_user();

if (is_post()) {
    $action = post('action');

    if ($action === 'create') {
        $username = post('username');
        $errors = [];
        if (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $username)) $errors[] = 'Tên đăng nhập 3–50 ký tự (chữ không dấu, số, . hoặc _).';
        elseif (val('SELECT COUNT(*) FROM users WHERE username = ?', [$username])) $errors[] = 'Tên đăng nhập đã tồn tại.';
        if (post('full_name') === '') $errors[] = 'Vui lòng nhập họ tên.';
        if (mb_strlen(post('password')) < 6) $errors[] = 'Mật khẩu cần ít nhất 6 ký tự.';
        if ($errors) {
            foreach ($errors as $err) flash('error', $err);
        } else {
            insert('users', [
                'username' => $username, 'password_hash' => password_hash(post('password'), PASSWORD_DEFAULT),
                'full_name' => post('full_name'), 'email' => post('email') ?: null,
                'role' => post('role') === 'admin' ? 'admin' : 'user', 'created_at' => now(),
            ]);
            flash('success', 'Đã tạo tài khoản ' . $username . '.');
        }
        redirect('users');
    }

    $target = row('SELECT * FROM users WHERE id = ?', [(int)post('user_id')]);
    if (!$target) abort(404, 'Người dùng không tồn tại.');
    $self = (int)$target['id'] === (int)$me['id'];

    if ($action === 'role' && !$self) {
        update('users', ['role' => post('role') === 'admin' ? 'admin' : 'user'], 'id = ?', [$target['id']]);
        flash('success', 'Đã cập nhật vai trò của ' . $target['username'] . '.');
    } elseif ($action === 'reset') {
        $pass = post('password');
        if (mb_strlen($pass) < 6) {
            flash('error', 'Mật khẩu mới cần ít nhất 6 ký tự.');
        } else {
            update('users', ['password_hash' => password_hash($pass, PASSWORD_DEFAULT)], 'id = ?', [$target['id']]);
            flash('success', 'Đã đặt lại mật khẩu cho ' . $target['username'] . '.');
        }
    } elseif ($action === 'delete' && !$self) {
        $soleOwner = val("SELECT COUNT(*) FROM project_members m WHERE m.user_id = ? AND m.role = 'owner'
            AND (SELECT COUNT(*) FROM project_members m2 WHERE m2.project_id = m.project_id AND m2.role = 'owner') = 1", [$target['id']]);
        if ($soleOwner) {
            flash('error', $target['username'] . ' là người phụ trách duy nhất của ' . $soleOwner . ' dự án. Hãy chuyển quyền trước khi xóa.');
        } else {
            q('DELETE FROM users WHERE id = ?', [$target['id']]);
            flash('success', 'Đã xóa tài khoản ' . $target['username'] . '.');
        }
    }
    redirect('users');
}

$users = rows('SELECT u.*, (SELECT COUNT(*) FROM project_members m WHERE m.user_id = u.id) AS project_count FROM users u ORDER BY u.id');

layout_start('Người dùng', 'users');
page_head('Người dùng', [], '', 'Quản lý tài khoản trong hệ thống. Quyền trong từng dự án do người phụ trách API của dự án đó cấp.');
?>
<section class="card">
    <table class="table">
        <thead><tr><th>#</th><th>Họ tên</th><th>Tài khoản</th><th>Vai trò hệ thống</th><th>Số dự án</th><th>Ngày tạo</th><th>Đặt lại mật khẩu</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): $self = (int)$u['id'] === (int)$me['id']; ?>
            <tr>
                <td class="muted"><?= (int)$u['id'] ?></td>
                <td><strong><?= e($u['full_name']) ?></strong><?= $self ? ' <span class="muted">(bạn)</span>' : '' ?></td>
                <td><?= e($u['username']) ?><div class="muted small"><?= e($u['email']) ?></div></td>
                <td>
                    <?php if ($self): ?>
                        <?= badge('Quản trị', 'role-owner') ?>
                    <?php else: ?>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <select class="input input-sm" name="role" data-autosubmit>
                                <option value="user"<?= selected('user', $u['role']) ?>>Người dùng</option>
                                <option value="admin"<?= selected('admin', $u['role']) ?>>Quản trị</option>
                            </select>
                        </form>
                    <?php endif; ?>
                </td>
                <td><?= (int)$u['project_count'] ?></td>
                <td class="muted nowrap"><?= e(fmt_date($u['created_at'])) ?></td>
                <td>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <input class="input input-sm" type="password" name="password" placeholder="Mật khẩu mới" minlength="6" required>
                        <button class="btn btn-sm">Đặt</button>
                    </form>
                </td>
                <td class="right">
                    <?php if (!$self): ?>
                        <form method="post" data-confirm="Xóa tài khoản <?= e($u['username']) ?>?">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <button class="btn btn-sm btn-ghost-danger"><?= icon('trash') ?></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="card">
    <div class="card-head"><h2>Tạo tài khoản</h2></div>
    <form method="post" class="card-body grid-5">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <label class="field"><span>Tên đăng nhập</span><input class="input" name="username" required></label>
        <label class="field"><span>Họ tên</span><input class="input" name="full_name" required></label>
        <label class="field"><span>Email</span><input class="input" type="email" name="email"></label>
        <label class="field"><span>Mật khẩu</span><input class="input" type="password" name="password" minlength="6" required></label>
        <label class="field"><span>Vai trò</span><select class="input" name="role"><option value="user">Người dùng</option><option value="admin">Quản trị</option></select></label>
        <div><button class="btn btn-primary"><?= icon('plus') ?>Tạo tài khoản</button></div>
    </form>
</section>
<?php layout_end();
