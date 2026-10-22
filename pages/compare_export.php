<?php
defined('APP') or exit;

require_login();
$endpointId = get_int('endpoint_id');
$fromId = get_int('from');
$toId = get_int('to');
$ep = load_endpoint($endpointId);

$old = row('SELECT * FROM schema_versions WHERE id = ? AND endpoint_id = ?', [$fromId, $endpointId]);
$new = row('SELECT * FROM schema_versions WHERE id = ? AND endpoint_id = ?', [$toId, $endpointId]);
if (!$old || !$new || $fromId === $toId) {
    abort(400, 'Không xuất được báo cáo: cần hai phiên bản khác nhau hợp lệ.');
}

$result = compare_versions($old, $new);
$md = compare_report_markdown($ep, $old, $new, $result);
$filename = sprintf(
    'compare_%s_%s_to_%s.md',
    preg_replace('/[^a-zA-Z0-9_-]+/', '_', $ep['method'] . $ep['path']),
    preg_replace('/[^a-zA-Z0-9._-]+/', '_', $old['label']),
    preg_replace('/[^a-zA-Z0-9._-]+/', '_', $new['label'])
);

header('Content-Type: text/markdown; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
echo $md;
exit;
