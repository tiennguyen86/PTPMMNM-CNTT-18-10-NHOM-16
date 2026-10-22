<?php
defined('APP') or exit;

$user = require_login();
$projectId = get_int('project_id');
$project = load_project($projectId, 'editor');

$form = ['label' => 'v1.0.0', 'openapi_json' => '', 'selected' => []];
$errors = [];
$preview = null;

if (is_post()) {
    $form['label'] = post('label') ?: 'v1.0.0';
    [$form['openapi_json'], $fileErr] = read_json_input('openapi_json', 'openapi_file');
    if ($fileErr) $errors[] = $fileErr;
    if (mb_strlen($form['label']) > 50) $errors[] = 'Nhãn phiên bản tối đa 50 ký tự.';

    $importer = new OpenApiImporter();
    $preview = $importer->parse($form['openapi_json']);
    if (!$preview['ok']) {
        $errors[] = $preview['error'] ?? 'Không đọc được OpenAPI.';
        $preview = null;
    }

    $selected = $_POST['ops'] ?? [];
    if (!is_array($selected)) $selected = [];
    $form['selected'] = array_map('strval', $selected);

    if (post('action') === 'import' && !$errors && $preview) {
        if (!$form['selected']) {
            $errors[] = 'Hãy chọn ít nhất một endpoint để import.';
        } else {
            $created = 0;
            $skipped = 0;
            $parser = new SchemaParser();

            db_transaction(function () use ($preview, $form, $projectId, $user, $parser, &$created, &$skipped) {
                foreach ($preview['operations'] as $i => $op) {
                    if (!in_array((string)$i, $form['selected'], true)) continue;

                    $exists = row(
                        'SELECT id FROM endpoints WHERE project_id = ? AND method = ? AND path = ?',
                        [$projectId, $op['method'], $op['path']]
                    );
                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    $epId = insert('endpoints', [
                        'project_id'  => $projectId,
                        'method'      => $op['method'],
                        'path'        => $op['path'],
                        'name'        => $op['name'],
                        'description' => $op['description'] ?: null,
                        'created_by'  => $user['id'],
                        'created_at'  => now(),
                    ]);

                    $reqRaw = $op['request_schema'] ? pretty_json($op['request_schema']) : null;
                    $resRaw = $op['response_schema'] ? pretty_json($op['response_schema']) : null;
                    $reqResult = $reqRaw ? $parser->parse($reqRaw) : null;
                    $resResult = $resRaw ? $parser->parse($resRaw) : null;

                    $issues = [];
                    if ($reqResult) {
                        foreach ($reqResult['issues'] as $iss) $issues[] = $iss + ['part' => 'request'];
                    }
                    if ($resResult) {
                        foreach ($resResult['issues'] as $iss) $issues[] = $iss + ['part' => 'response'];
                    }

                    insert('schema_versions', [
                        'endpoint_id'   => $epId,
                        'label'         => $form['label'],
                        'note'          => 'Import từ OpenAPI: ' . ($preview['info']['title'] ?? ''),
                        'source_format' => 'json-schema',
                        'request_raw'   => $reqRaw,
                        'response_raw'  => $resRaw,
                        'request_tree'  => ($reqResult && $reqResult['ok']) ? json_encode($reqResult['tree'], JSON_UNESCAPED_UNICODE) : null,
                        'response_tree' => ($resResult && $resResult['ok']) ? json_encode($resResult['tree'], JSON_UNESCAPED_UNICODE) : null,
                        'issues'        => json_encode($issues, JSON_UNESCAPED_UNICODE),
                        'issue_count'   => count(array_filter($issues, fn($x) => $x['level'] !== 'info')),
                        'created_by'    => $user['id'],
                        'created_at'    => now(),
                    ]);
                    $created++;
                }
            });

            flash(
                $created ? 'success' : 'warning',
                "Đã import $created endpoint" . ($skipped ? ", bỏ qua $skipped endpoint đã tồn tại" : '') . '.'
            );
            redirect('project', ['id' => $projectId]);
        }
    }
}

layout_start('Import OpenAPI', 'projects');
page_head('Import OpenAPI / Swagger', [
    'Dự án' => url('projects'),
    $project['name'] => url('project', ['id' => $projectId]),
    'Import OpenAPI' => null,
], '', 'Tải tệp OpenAPI 3.x (JSON) để tạo nhiều endpoint và phiên bản mô tả cùng lúc.');
?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <section class="card">
        <div class="card-body grid-2">
            <label class="field"><span>Nhãn phiên bản tạo kèm</span>
                <input class="input" name="label" value="<?= e($form['label']) ?>" maxlength="50" required>
            </label>
            <label class="field"><span>Tệp OpenAPI (.json)</span>
                <input class="input" type="file" name="openapi_file" accept=".json,application/json" data-load-into="openapi_json">
            </label>
        </div>
        <div class="card-body">
            <label class="field"><span>Hoặc dán nội dung JSON</span>
                <textarea class="input code" id="openapi_json" name="openapi_json" rows="12" spellcheck="false" data-json><?= e($form['openapi_json']) ?></textarea>
            </label>
        </div>
        <div class="card-body row gap">
            <button class="btn" name="action" value="preview">Phân tích thử</button>
            <?php if ($preview): ?>
                <button class="btn btn-primary" name="action" value="import"><?= icon('upload') ?>Import đã chọn</button>
            <?php endif; ?>
            <a class="btn btn-ghost" href="<?= e(url('project', ['id' => $projectId])) ?>">Hủy</a>
        </div>
    </section>

    <?php if ($preview): ?>
        <section class="card">
            <div class="card-head">
                <h2><?= e($preview['info']['title']) ?>
                    <span class="muted small">OpenAPI <?= e($preview['info']['openapi']) ?> · API <?= e($preview['info']['version']) ?></span>
                </h2>
                <span class="count"><?= count($preview['operations']) ?> operation</span>
            </div>
            <table class="table">
                <thead>
                <tr>
                    <th style="width:40px"><input type="checkbox" checked data-check-all="ops[]"></th>
                    <th>Method</th>
                    <th>Path</th>
                    <th>Tên</th>
                    <th>Request</th>
                    <th>Response</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($preview['operations'] as $i => $op):
                    $checked = !$form['selected'] || in_array((string)$i, $form['selected'], true);
                    ?>
                    <tr>
                        <td><input type="checkbox" name="ops[]" value="<?= (int)$i ?>"<?= $checked ? ' checked' : '' ?>></td>
                        <td><?= method_badge($op['method']) ?></td>
                        <td><code><?= e($op['path']) ?></code></td>
                        <td><?= e($op['name']) ?></td>
                        <td><?= $op['has_request'] ? badge('Có', 'status-pass') : badge('Không', 'level-info') ?></td>
                        <td><?= $op['has_response'] ? badge('Có', 'status-pass') : badge('Không', 'level-info') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</form>
<?php layout_end();
