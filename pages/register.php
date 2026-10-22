<?php
defined('APP') or exit;

if (!config('allow_register')) {
    flash('info', 'Đăng ký công khai đang tắt. Liên hệ quản trị để được cấp tài khoản.');
    redirect('login');
}

if (current_user()) redirect('dashboard');

$form = ['username' => '', 'full_name' => '', 'email' => ''];
$errors = [];
if (is_post()) {
    foreach ($form as $k => $_) $form[$k] = post($k);
    $password = post('password');

    if (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $form['username'])) {
        $errors[] = 'Tên đăng nhập 3–50 ký tự, chỉ gồm chữ không dấu, số, dấu chấm hoặc gạch dưới.';
    } elseif (val('SELECT COUNT(*) FROM users WHERE username = ?', [$form['username']])) {
        $errors[] = 'Tên đăng nhập đã tồn tại.';
    }
    if ($form['full_name'] === '') $errors[] = 'Vui lòng nhập họ tên.';
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';
    if (mb_strlen($password) < 6) $errors[] = 'Mật khẩu cần ít nhất 6 ký tự.';
    if ($password !== post('password_confirm')) $errors[] = 'Mật khẩu nhập lại không khớp.';

    if (!$errors) {
        $id = insert('users', [
            'username' => $form['username'], 'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'full_name' => $form['full_name'], 'email' => $form['email'] ?: null, 'role' => 'user', 'created_at' => now(),
        ]);
        login_user(['id' => $id]);
        flash('success', 'Đăng ký thành công. Hãy tạo dự án mới hoặc nhờ người phụ trách thêm bạn vào dự án.');
        redirect('dashboard');
    }
}

auth_layout_start('Đăng ký');
?>
<h1>Tạo tài khoản</h1>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" class="stack">
    <?= csrf_field() ?>
    <label class="field"><span>Tên đăng nhập</span><input class="input" name="username" value="<?= e($form['username']) ?>" required></label>
    <label class="field"><span>Họ tên</span><input class="input" name="full_name" value="<?= e($form['full_name']) ?>" required></label>
    <label class="field"><span>Email</span><input class="input" type="email" name="email" value="<?= e($form['email']) ?>"></label>
    <div class="grid-2">
        <label class="field"><span>Mật khẩu</span><input class="input" type="password" name="password" required minlength="6"></label>
        <label class="field"><span>Nhập lại</span><input class="input" type="password" name="password_confirm" required></label>
    </div>
    <button class="btn btn-primary btn-block">Đăng ký</button>
</form>
<p class="muted center">Đã có tài khoản? <a href="<?= e(url('login')) ?>">Đăng nhập</a></p>
<?php auth_layout_end();
