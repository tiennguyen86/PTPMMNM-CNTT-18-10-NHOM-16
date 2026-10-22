<?php
defined('APP') or exit;

if (current_user()) redirect('dashboard');

$username = '';
$error = null;
if (is_post()) {
    $username = post('username');
    if ($locked = login_rate_limited($username)) {
        $error = $locked;
    } else {
        $user = row('SELECT * FROM users WHERE username = ?', [$username]);
        if ($user && password_verify(post('password'), $user['password_hash'])) {
            record_login_attempt($username, true);
            login_user($user);
            flash('success', 'Xin chào ' . $user['full_name'] . '!');
            redirect('dashboard');
        }
        record_login_attempt($username, false);
        $error = 'Sai tên đăng nhập hoặc mật khẩu.';
    }
}

auth_layout_start('Đăng nhập');
?>
<h1>Chào mừng trở lại.</h1>
<p class="muted">Đăng nhập để tiếp tục quản lý và kiểm tra API cùng nhóm của bạn.</p>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="stack">
    <?= csrf_field() ?>
    <label class="field"><span>Tên đăng nhập</span><input class="input" name="username" value="<?= e($username) ?>" placeholder="Nhập tên đăng nhập" autocomplete="username" required autofocus></label>
    <label class="field"><span>Mật khẩu</span><span class="password-input"><input class="input" type="password" name="password" id="login-password" placeholder="Nhập mật khẩu" autocomplete="current-password" required><button type="button" class="password-toggle" data-password-toggle="login-password" aria-controls="login-password" aria-pressed="false">Hiện</button></span></label>
    <button class="btn btn-primary btn-block">Đăng nhập <?= icon('arrow') ?></button>
</form>
<?php if (config('allow_register')): ?>
<p class="muted center">Chưa có tài khoản? <a href="<?= e(url('register')) ?>">Đăng ký</a></p>
<?php endif; ?>
<?php if (config('debug')): ?>
<div class="demo-accounts">
    <strong>Tài khoản dùng thử</strong>
    <table>
        <tr><td><code>admin</code> / <code>admin123</code></td><td>Quản trị hệ thống</td></tr>
        <tr><td><code>phutrach</code> / <code>123456</code></td><td>Người phụ trách API</td></tr>
        <tr><td><code>dev1</code> / <code>123456</code></td><td>Thành viên – được sửa</td></tr>
        <tr><td><code>tester</code> / <code>123456</code></td><td>Thành viên – chỉ xem</td></tr>
    </table>
</div>
<?php endif; ?>
<?php auth_layout_end();
