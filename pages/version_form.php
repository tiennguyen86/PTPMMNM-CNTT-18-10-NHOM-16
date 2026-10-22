<?php
defined('APP') or exit;

$user = require_login();
$endpointId = get_int('endpoint_id');
$ep = load_endpoint($endpointId, 'editor');

$latest = row('SELECT * FROM schema_versions WHERE endpoint_id = ? ORDER BY id DESC LIMIT 1', [$endpointId]);
$source = get_int('from') ? row('SELECT * FROM schema_versions WHERE id = ? AND endpoint_id = ?', [get_int('from'), $endpointId]) : null;

$form = [
    'label' => $latest ? (next_version_label($latest['label'], 'minor') ?? '') : 'v1.0.0',
    'note' => '',
    'source_format' => $source['source_format'] ?? 'json-schema',
    'request_json' => $source['request_raw'] ?? '',
    'response_json' => $source['response_raw'] ?? '',
];
$errors = [];
$results = null; // kết quả phân tích từng phần

if (is_post()) {
    $form['label'] = post('label');
    $form['note'] = post('note');
    $form['source_format'] = isset(SOURCE_FORMATS[post('source_format')]) ? post('source_format') : 'json-schema';

    foreach (['request', 'response'] as $part) {
        [$text, $err] = read_json_input($part . '_json', $part . '_file');
        $form[$part . '_json'] = $text;
        if ($err) $errors[] = ucfirst($part) . ': ' . $err;
    }

    if ($form['label'] === '') $errors[] = 'Vui lòng nhập nhãn phiên bản (ví dụ v1.0.0).';
    if (mb_strlen($form['label']) > 50) $errors[] = 'Nhãn phiên bản tối đa 50 ký tự.';
    if (val('SELECT COUNT(*) FROM schema_versions WHERE endpoint_id = ? AND label = ?', [$endpointId, $form['label']])) {
        $errors[] = 'Phiên bản "' . $form['label'] . '" đã tồn tại. Các phiên bản đã lưu không sửa được – hãy dùng nhãn mới.';
    }
    if ($form['request_json'] === '' && $form['response_json'] === '') {
        $errors[] = 'Cần nhập ít nhất một trong hai phần: mô tả request hoặc response.';
    }

    $results = [];
    foreach (['request', 'response'] as $part) {
        if ($form[$part . '_json'] === '') continue;
        $results[$part] = parse_schema_input($form['source_format'], $form[$part . '_json']);
        if (!$results[$part]['ok']) {
            $errors[] = 'Không đọc được mô tả ' . $part . ': ' . $results[$part]['error'];
        }
    }

    if (post('action') === 'save' && !$errors) {
        $issues = [];
        foreach ($results as $part => $r) {
            foreach ($r['issues'] as $i) $issues[] = $i + ['part' => $part];
        }
        $vid = insert('schema_versions', [
            'endpoint_id' => $endpointId,
            'label' => $form['label'],
            'note' => $form['note'],
            'source_format' => $form['source_format'],
            'request_raw' => $form['request_json'] ?: null,
            'response_raw' => $form['response_json'] ?: null,
            'request_tree' => isset($results['request']) ? json_encode($results['request']['tree'], JSON_UNESCAPED_UNICODE) : null,
            'response_tree' => isset($results['response']) ? json_encode($results['response']['tree'], JSON_UNESCAPED_UNICODE) : null,
            'issues' => json_encode($issues, JSON_UNESCAPED_UNICODE),
            'issue_count' => count(array_filter($issues, fn($i) => $i['level'] !== 'info')),
            'created_by' => $user['id'],
            'created_at' => now(),
        ]);
        $unsupported = count(array_filter($issues, fn($i) => $i['level'] === 'error'));
        flash($unsupported ? 'warning' : 'success', 'Đã lưu phiên bản ' . $form['label'] . '.'
            . ($unsupported ? " Có $unsupported cấu trúc chưa hỗ trợ – xem chi tiết bên dưới." : ''));
        redirect('version', ['id' => $vid]);
    }
}

layout_start('Thêm phiên bản mô tả', 'projects');
page_head('Thêm phiên bản mô tả', [
    'Dự án' => url('projects'),
    $ep['project_name'] => url('project', ['id' => $ep['project_id']]),
    $ep['method'] . ' ' . $ep['path'] => url('endpoint', ['id' => $endpointId]),
    'Thêm phiên bản' => null,
], '', 'Nhập bản mô tả cấu trúc dữ liệu từ tệp hoặc dán trực tiếp. Bấm <strong>Phân tích thử</strong> để xem kết quả đọc cấu trúc trước khi lưu.');
?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <section class="card">
        <div class="card-body grid-3">
            <label class="field"><span>Nhãn phiên bản *</span><input class="input mono" name="label" value="<?= e($form['label']) ?>" required maxlength="50" placeholder="v1.0.0"></label>
            <label class="field"><span>Định dạng bản mô tả</span>
                <select class="input" name="source_format">
                    <?php foreach (SOURCE_FORMATS as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $form['source_format']) ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Ghi chú thay đổi</span><input class="input" name="note" value="<?= e($form['note']) ?>" placeholder="Ví dụ: đổi tên trường phone"></label>
        </div>
        <div class="card-body hint">
            <strong>JSON Schema</strong>: hỗ trợ <code>type</code>, <code>properties</code>, <code>required</code>, <code>items</code>, <code>enum</code>/<code>const</code>, <code>format</code>,
            <code>nullable</code> hoặc <code>"type": ["string","null"]</code>, <code>minimum</code>/<code>maximum</code>, <code>minLength</code>/<code>maxLength</code>, <code>minItems</code>/<code>maxItems</code>, <code>pattern</code>, <code>additionalProperties: false</code>.
            Các cấu trúc như <code>$ref</code>, <code>oneOf</code>, <code>anyOf</code>, <code>allOf</code>, mảng tuple… sẽ được báo là <em>chưa hỗ trợ</em>.
            <strong>JSON mẫu</strong>: dán một phản hồi thật, hệ thống tự suy ra cấu trúc. Tệp mẫu có trong thư mục <code>samples/</code>.
        </div>
    </section>

    <div class="grid-2 gap-lg">
        <?php foreach (['request' => 'Request – dữ liệu ứng dụng gửi lên', 'response' => 'Response – dữ liệu API trả về'] as $part => $title): ?>
            <section class="card">
                <div class="card-head"><h2><?= e($title) ?></h2>
                    <button type="button" class="btn btn-sm btn-ghost" data-format="<?= $part ?>_json">Định dạng JSON</button>
                </div>
                <div class="card-body stack">
                    <label class="file-drop">
                        <?= icon('upload') ?><span>Chọn tệp .json để nhập</span>
                        <input type="file" name="<?= $part ?>_file" accept=".json,.txt,application/json" data-load-into="<?= $part ?>_json">
                    </label>
                    <textarea class="input code" id="<?= $part ?>_json" name="<?= $part ?>_json" rows="18" spellcheck="false" data-json
                              placeholder="<?= $part === 'request' ? 'Để trống nếu endpoint không nhận dữ liệu (ví dụ GET)' : '{ &quot;type&quot;: &quot;object&quot;, ... }' ?>"><?= e($form[$part . '_json']) ?></textarea>
                    <div class="json-status" data-status-for="<?= $part ?>_json"></div>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="row gap sticky-actions">
        <button class="btn" name="action" value="preview"><?= icon('search') ?>Phân tích thử</button>
        <button class="btn btn-primary" name="action" value="save">Lưu phiên bản</button>
        <a class="btn btn-ghost" href="<?= e(url('endpoint', ['id' => $endpointId])) ?>">Hủy</a>
    </div>
</form>

<?php $results = array_filter($results ?? [], fn($r) => $r['ok']); ?>
<?php if ($results): ?>
    <h2 class="section-title">Kết quả đọc cấu trúc</h2>
    <?php
    $allIssues = [];
    foreach ($results as $part => $r) foreach ($r['issues'] as $i) $allIssues[] = $i + ['part' => $part];
    ?>
    <section class="card">
        <div class="card-head"><h2>Cấu trúc chưa hỗ trợ & cảnh báo</h2></div>
        <div class="card-body"><?= render_issues($allIssues) ?></div>
    </section>
    <div class="grid-2 gap-lg">
        <?php foreach ($results as $part => $r): ?>
            <section class="card">
                <div class="card-head"><h2>Cây cấu trúc <?= e($part) ?></h2><span class="muted"><?= SchemaTree::countFields($r['tree']) ?> trường</span></div>
                <div class="card-body"><?= SchemaTree::renderTree($r['tree']) ?></div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php layout_end();
