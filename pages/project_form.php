<?php
defined('APP') or exit;

$user = require_login();
$id = get_int('id');
$project = $id ? load_project($id, 'owner') : null;

$form = ['name' => $project['name'] ?? '', 'description' => $project['description'] ?? ''];
$errors = [];

if (is_post()) {
    $form = ['name' => post('name'), 'description' => post('description')];
    if ($form['name'] === '') $errors[] = 'Vui lòng nhập tên dự án.';
    if (mb_strlen($form['name']) > 150) $errors[] = 'Tên dự án tối đa 150 ký tự.';

    if (!$errors) {
        if ($project) {
            update('projects', $form + ['updated_at' => now()], 'id = ?', [$id]);
            flash('success', 'Đã cập nhật dự án.');
        } else {
            $id = db_transaction(function () use ($form, $user) {
                $id = insert('projects', $form + ['created_by' => $user['id'], 'created_at' => now(), 'updated_at' => now()]);
                insert('project_members', ['project_id' => $id, 'user_id' => $user['id'], 'role' => 'owner', 'added_at' => now()]);
                return $id;
            });
            flash('success', 'Đã tạo dự án. Bạn là người phụ trách API của dự án này.');
        }
        redirect('project', ['id' => $id]);
    }
}

$title = $project ? 'Sửa dự án' : 'Tạo dự án';
layout_start($title, 'projects');
$crumbs = ['Dự án' => url('projects')];
if ($project) $crumbs[$project['name']] = url('project', ['id' => $id]);
$crumbs[$title] = null;
page_head(e($title), $crumbs);
?>
<section class="card narrow">
    <div class="card-body">
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" class="stack">
            <?= csrf_field() ?>
            <label class="field"><span>Tên dự án *</span><input class="input" name="name" value="<?= e($form['name']) ?>" maxlength="150" required></label>
            <label class="field"><span>Mô tả</span><textarea class="input" name="description" rows="4"><?= e($form['description']) ?></textarea></label>
            <div class="row gap">
                <button class="btn btn-primary"><?= $project ? 'Lưu thay đổi' : 'Tạo dự án' ?></button>
                <a class="btn btn-ghost" href="<?= e($project ? url('project', ['id' => $id]) : url('projects')) ?>">Hủy</a>
            </div>
        </form>
    </div>
</section>
<?php layout_end();
