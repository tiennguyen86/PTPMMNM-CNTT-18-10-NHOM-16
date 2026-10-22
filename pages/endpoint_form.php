<?php
defined('APP') or exit;

$user = require_login();
$id = get_int('id');
if ($id) {
    $endpoint = load_endpoint($id, 'editor');
    $project = load_project((int)$endpoint['project_id'], 'editor');
} else {
    $endpoint = null;
    $project = load_project(get_int('project_id'), 'editor');
}

$form = [
    'method' => $endpoint['method'] ?? 'GET',
    'path' => $endpoint['path'] ?? '/api/',
    'name' => $endpoint['name'] ?? '',
    'description' => $endpoint['description'] ?? '',
];
$errors = [];

if (is_post()) {
    if (post('action') === 'delete' && $endpoint) {
        q('DELETE FROM endpoints WHERE id = ?', [$id]);
        flash('success', 'Đã xóa endpoint ' . $endpoint['method'] . ' ' . $endpoint['path'] . '.');
        redirect('project', ['id' => $project['id']]);
    }

    $form = ['method' => strtoupper(post('method')), 'path' => post('path'), 'name' => post('name'), 'description' => post('description')];
    if (!in_array($form['method'], HTTP_METHODS, true)) $errors[] = 'Phương thức HTTP không hợp lệ.';
    if (!preg_match('#^/\S*$#', $form['path'])) $errors[] = 'Đường dẫn phải bắt đầu bằng "/" và không chứa khoảng trắng, ví dụ /api/users/{id}.';
    if (mb_strlen($form['path']) > 255) $errors[] = 'Đường dẫn tối đa 255 ký tự.';
    $dup = val('SELECT id FROM endpoints WHERE project_id = ? AND method = ? AND path = ?', [$project['id'], $form['method'], $form['path']]);
    if ($dup && (int)$dup !== $id) $errors[] = 'Endpoint ' . $form['method'] . ' ' . $form['path'] . ' đã tồn tại trong dự án.';

    if (!$errors) {
        if ($endpoint) {
            update('endpoints', $form, 'id = ?', [$id]);
            flash('success', 'Đã cập nhật endpoint.');
        } else {
            $id = insert('endpoints', $form + ['project_id' => $project['id'], 'created_by' => $user['id'], 'created_at' => now()]);
            flash('success', 'Đã thêm endpoint. Hãy thêm phiên bản mô tả cấu trúc đầu tiên.');
        }
        redirect('endpoint', ['id' => $id]);
    }
}

$title = $endpoint ? 'Sửa endpoint' : 'Thêm endpoint';
layout_start($title, 'projects');
page_head(e($title), ['Dự án' => url('projects'), $project['name'] => url('project', ['id' => $project['id']]), $title => null]);
?>
<section class="card narrow">
    <div class="card-body">
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" class="stack">
            <?= csrf_field() ?>
            <div class="grid-method">
                <label class="field"><span>Phương thức</span>
                    <select class="input" name="method">
                        <?php foreach (HTTP_METHODS as $m): ?><option<?= selected($m, $form['method']) ?>><?= $m ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="field"><span>Đường dẫn (endpoint) *</span><input class="input mono" name="path" value="<?= e($form['path']) ?>" required placeholder="/api/users/{id}"></label>
            </div>
            <label class="field"><span>Tên gợi nhớ</span><input class="input" name="name" value="<?= e($form['name']) ?>" maxlength="150" placeholder="Xem thông tin người dùng"></label>
            <label class="field"><span>Mô tả</span><textarea class="input" name="description" rows="3"><?= e($form['description']) ?></textarea></label>
            <div class="row gap">
                <button class="btn btn-primary"><?= $endpoint ? 'Lưu thay đổi' : 'Thêm endpoint' ?></button>
                <a class="btn btn-ghost" href="<?= e($endpoint ? url('endpoint', ['id' => $id]) : url('project', ['id' => $project['id']])) ?>">Hủy</a>
            </div>
        </form>
    </div>
</section>
<?php if ($endpoint): ?>
    <section class="card card-danger narrow">
        <div class="card-body row-between">
            <div><strong>Xóa endpoint</strong><p class="muted">Xóa mọi phiên bản mô tả và lịch sử kiểm tra của endpoint này.</p></div>
            <form method="post" data-confirm="Xóa endpoint và toàn bộ phiên bản mô tả?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <button class="btn btn-danger"><?= icon('trash') ?>Xóa endpoint</button>
            </form>
        </div>
    </section>
<?php endif; ?>
<?php layout_end();
