<?php
defined('APP') or exit;

const SOURCE_FORMATS = [
    'json-schema' => 'JSON Schema',
    'sample'      => 'JSON mẫu (tự suy ra cấu trúc)',
];
const TARGETS = [
    'response' => 'Phản hồi (response)',
    'request'  => 'Yêu cầu (request)',
];
const HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
const SEVERITIES = [
    'breaking' => 'Phá vỡ tương thích',
    'warning'  => 'Cần lưu ý',
    'safe'     => 'Tương thích',
];
const SEVERITY_IMPACT = [
    'breaking' => 'Ứng dụng đang dùng API sẽ gặp lỗi',
    'warning'  => 'Có thể ảnh hưởng – cần kiểm tra',
    'safe'     => 'Không ảnh hưởng ứng dụng',
];

/** Đọc bản mô tả theo định dạng đã chọn. */
function parse_schema_input(string $format, string $json): array
{
    return $format === 'sample' ? (new SchemaInferrer())->infer($json) : (new SchemaParser())->parse($json);
}

/** Bộ quy tắc đang công bố: [code => row]. */
function load_rules(): array
{
    $out = [];
    foreach (rows('SELECT * FROM compat_rules ORDER BY sort_order, id') as $r) {
        $out[$r['code']] = $r;
    }
    return $out;
}

function version_tree(array $version, string $part): ?array
{
    $json = $version[$part . '_tree'] ?? null;
    return $json ? json_decode($json, true) : null;
}

function version_issues(array $version): array
{
    return $version['issues'] ? (json_decode($version['issues'], true) ?: []) : [];
}

/** So sánh hai phiên bản (cả request và response). */
function compare_versions(array $old, array $new): array
{
    $diff = new SchemaDiff(load_rules());
    $changes = [];
    foreach (['request', 'response'] as $part) {
        $changes = array_merge($changes, $diff->compare(version_tree($old, $part), version_tree($new, $part), $part));
    }
    $changes = SchemaDiff::sort($changes);
    return ['changes' => $changes, 'summary' => SchemaDiff::summarize($changes)];
}

/** Cắt bớt dữ liệu lỗi cho vừa cột trong CSDL. */
function error_row(array $e): array
{
    $cut = fn($s, $n) => mb_strlen((string)$s) > $n ? mb_substr((string)$s, 0, $n - 3) . '...' : (string)$s;
    return [
        'level' => $e['level'], 'code' => $e['code'],
        'path' => $cut($e['path'], 500), 'message' => $e['message'],
        'expected' => $cut($e['expected'] ?? '', 255), 'actual' => $cut($e['actual'] ?? '', 255),
    ];
}

/** Chạy kiểm tra dữ liệu mẫu, lưu kết quả và trả về id lần kiểm tra. */
function save_validation_run(array $version, string $target, string $title, string $json, $data): int
{
    $tree = version_tree($version, $target);
    $errors = (new SchemaValidator())->validate($tree, $data);
    $errCount = count(array_filter($errors, fn($e) => $e['level'] === 'error'));

    db()->beginTransaction();
    $runId = insert('validation_runs', [
        'endpoint_id' => $version['endpoint_id'], 'version_id' => $version['id'], 'target' => $target,
        'title' => $title, 'sample_json' => $json,
        'status' => $errCount ? 'fail' : 'pass', 'error_count' => $errCount, 'warning_count' => count($errors) - $errCount,
        'created_by' => current_user()['id'], 'created_at' => now(),
    ]);
    foreach ($errors as $e) {
        insert('validation_errors', ['run_id' => $runId] + error_row($e));
    }
    db()->commit();
    return $runId;
}

/** Danh sách endpoint người dùng được xem (dùng cho bộ lọc). */
function accessible_endpoints(): array
{
    [$scope, $params] = project_scope();
    return rows("SELECT e.id, e.method, e.path, p.name AS project_name
                 FROM endpoints e JOIN projects p ON p.id = e.project_id
                 WHERE $scope ORDER BY p.name, e.path, e.method", $params);
}

function accessible_projects(): array
{
    [$scope, $params] = project_scope();
    return rows("SELECT p.id, p.name FROM projects p WHERE $scope ORDER BY p.name", $params);
}
