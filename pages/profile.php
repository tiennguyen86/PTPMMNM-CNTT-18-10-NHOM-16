<?php
defined('APP') or exit;

$user = require_login();

if (is_post()) {
    if (post('action') === 'info') {
        $name = post('full_name');
        $email = post('email');
        if ($name === '') {
            flash('error', 'Vui lòng nhập họ tên.');
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Email không hợp lệ.');
        } else {
            update('users', ['full_name' => $name, 'email' => $email ?: null], 'id = ?', [$user['id']]);
            flash('success', 'Đã cập nhật thông tin.');
        }
    } elseif (post('action') === 'password') {
        if (!password_verify(post('current'), $user['password_hash'])) {
            flash('error', 'Mật khẩu hiện tại không đúng.');
        } elseif (mb_strlen(post('new')) < 6) {
            flash('error', 'Mật khẩu mới cần ít nhất 6 ký tự.');
        } elseif (post('new') !== post('confirm')) {
            flash('error', 'Mật khẩu nhập lại không khớp.');
        } else {
            update('users', ['password_hash' => password_hash(post('new'), PASSWORD_DEFAULT)], 'id = ?', [$user['id']]);
            flash('success', 'Đã đổi mật khẩu.');
        }
    }
    redirect('profile');
}

$memberships = rows('SELECT p.id, p.name, m.role FROM project_members m JOIN projects p ON p.id = m.project_id WHERE m.user_id = ? ORDER BY p.name', [$user['id']]);

layout_start('Hồ sơ cá nhân');
page_head('Hồ sơ cá nhân', [], '', e($user['username']) . ($user['role'] === 'admin' ? ' · Quản trị hệ thống' : ''));
?>
<div class="grid-2 gap-lg">
    <section class="card">
        <div class="card-head"><h2>Thông tin</h2></div>
        <form method="post" class="card-body stack">
            <?= csrf_field() ?><input type="hidden" name="action" value="info">
            <label class="field"><span>Họ tên</span><input class="input" name="full_name" value="<?= e($user['full_name']) ?>" required></label>
            <label class="field"><span>Email</span><input class="input" type="email" name="email" value="<?= e($user['email']) ?>"></label>
            <div><button class="btn btn-primary">Lưu</button></div>
        </form>
    </section>
    <section class="card">
        <div class="card-head"><h2>Đổi mật khẩu</h2></div>
        <form method="post" class="card-body stack">
            <?= csrf_field() ?><input type="hidden" name="action" value="password">
            <label class="field"><span>Mật khẩu hiện tại</span><input class="input" type="password" name="current" required></label>
            <div class="grid-2">
                <label class="field"><span>Mật khẩu mới</span><input class="input" type="password" name="new" minlength="6" required></label>
                <label class="field"><span>Nhập lại</span><input class="input" type="password" name="confirm" required></label>
            </div>
            <div><button class="btn btn-primary">Đổi mật khẩu</button></div>
        </form>
    </section>
</div>
<section class="card">
    <div class="card-head"><h2>Dự án tham gia</h2></div>
    <?php if (!$memberships): ?>
        <div class="card-body muted">Bạn chưa tham gia dự án nào.</div>
    <?php else: ?>
        <table class="table">
            <tbody>
            <?php foreach ($memberships as $m): ?>
                <tr><td><a href="<?= e(url('project', ['id' => $m['id']])) ?>"><?= e($m['name']) ?></a></td><td class="right"><?= role_badge($m['role']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php layout_end();
