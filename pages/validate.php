<?php
defined('APP') or exit;

require_login();
$endpointId = get_int('endpoint_id');
$ep = load_endpoint($endpointId);

$versions = rows('SELECT id, label, request_tree IS NOT NULL AS has_request, response_tree IS NOT NULL AS has_response
    FROM schema_versions WHERE endpoint_id = ? ORDER BY id DESC', [$endpointId]);
if (!$versions) {
    flash('info', 'Endpoint chưa có phiên bản mô tả nào để kiểm tra.');
    redirect('endpoint', ['id' => $endpointId]);
}

$fromRun = get_int('from_run') ? row('SELECT * FROM validation_runs WHERE id = ? AND endpoint_id = ?', [get_int('from_run'), $endpointId]) : null;
$form = [
    'version_id' => get_int('version_id', (int)$versions[0]['id']),
    'target' => $fromRun['target'] ?? 'response',
    'title' => $fromRun ? $fromRun['title'] . ' (chạy lại)' : 'Kiểm tra ' . date('d/m/Y H:i'),
    'sample' => $fromRun['sample_json'] ?? '',
];
$errors = [];

if (is_post()) {
    $form['version_id'] = (int)post('version_id');
    $form['target'] = isset(TARGETS[post('target')]) ? post('target') : 'response';
    $form['title'] = post('title') ?: 'Kiểm tra ' . date('d/m/Y H:i');
    [$form['sample'], $fileErr] = read_json_input('sample', 'sample_file');
    if ($fileErr) $errors[] = $fileErr;

    $version = row('SELECT * FROM schema_versions WHERE id = ? AND endpoint_id = ?', [$form['version_id'], $endpointId]);
    if (!$version) {
        $errors[] = 'Phiên bản không hợp lệ.';
    } elseif (!version_tree($version, $form['target'])) {
        $errors[] = 'Phiên bản ' . $version['label'] . ' không có mô tả cho phần ' . $form['target'] . '.';
    }
    if (mb_strlen($form['title']) > 200) $errors[] = 'Tiêu đề tối đa 200 ký tự.';

    $data = null;
    if ($form['sample'] === '') {
        $errors[] = 'Vui lòng nhập hoặc tải lên dữ liệu mẫu.';
    } else {
        try {
            $data = json_decode($form['sample'], false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $errors[] = 'Dữ liệu mẫu không phải JSON hợp lệ: ' . json_error_vi($e);
        }
    }

    if (!$errors) {
        $runId = save_validation_run($version, $form['target'], $form['title'], $form['sample'], $data);
        $run = row('SELECT status, error_count, warning_count FROM validation_runs WHERE id = ?', [$runId]);
        flash($run['status'] === 'pass' ? 'success' : 'warning',
            $run['status'] === 'pass'
                ? 'Dữ liệu khớp với bản mô tả ' . $version['label'] . ($run['warning_count'] ? ' (có ' . $run['warning_count'] . ' cảnh báo).' : '.')
                : 'Dữ liệu KHÔNG khớp bản mô tả ' . $version['label'] . ': ' . $run['error_count'] . ' lỗi, ' . $run['warning_count'] . ' cảnh báo.');
        redirect('run', ['id' => $runId]);
    }
}

layout_start('Kiểm tra dữ liệu mẫu', 'runs');
page_head('Kiểm tra dữ liệu mẫu', [
    'Dự án' => url('projects'),
    $ep['project_name'] => url('project', ['id' => $ep['project_id']]),
    $ep['method'] . ' ' . $ep['path'] => url('endpoint', ['id' => $endpointId]),
    'Kiểm tra dữ liệu' => null,
], '', 'Dán phản hồi thực tế của API (hoặc tải tệp JSON) để kiểm tra có đúng bản mô tả hay không. Kết quả được lưu lại để tra cứu.');
?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <section class="card">
        <div class="card-body grid-3">
            <label class="field"><span>Phiên bản mô tả</span>
                <select class="input" name="version_id">
                    <?php foreach ($versions as $i => $v): ?>
                        <option value="<?= (int)$v['id'] ?>"<?= selected($v['id'], $form['version_id']) ?>><?= e($v['label']) ?><?= $i === 0 ? ' (mới nhất)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Kiểm tra phần</span>
                <select class="input" name="target">
                    <?php foreach (TARGETS as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $form['target']) ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Tiêu đề lần kiểm tra</span><input class="input" name="title" value="<?= e($form['title']) ?>" maxlength="200"></label>
        </div>
    </section>
    <section class="card">
        <div class="card-head"><h2>Dữ liệu mẫu (JSON)</h2><button type="button" class="btn btn-sm btn-ghost" data-format="sample">Định dạng JSON</button></div>
        <div class="card-body stack">
            <label class="file-drop">
                <?= icon('upload') ?><span>Chọn tệp .json chứa phản hồi mẫu</span>
                <input type="file" name="sample_file" accept=".json,.txt,application/json" data-load-into="sample">
            </label>
            <textarea class="input code" id="sample" name="sample" rows="20" spellcheck="false" data-json placeholder='{ "id": 1, "username": "nguyenvana", ... }'><?= e($form['sample']) ?></textarea>
            <div class="json-status" data-status-for="sample"></div>
        </div>
    </section>
    <div class="row gap sticky-actions">
        <button class="btn btn-primary"><?= icon('play') ?>Chạy kiểm tra</button>
        <a class="btn btn-ghost" href="<?= e(url('endpoint', ['id' => $endpointId])) ?>">Hủy</a>
    </div>
</form>
<?php layout_end();
