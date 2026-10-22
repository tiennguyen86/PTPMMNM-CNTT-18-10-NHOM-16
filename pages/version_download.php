<?php
defined('APP') or exit;

require_login();
$v = load_version(get_int('id'));
$part = get_str('part') === 'request' ? 'request' : 'response';
$kind = get_str('kind') === 'schema' ? 'schema' : 'raw';

if ($kind === 'raw') {
    $content = $v[$part . '_raw'] ?? '';
} else {
    $tree = version_tree($v, $part);
    $content = $tree ? pretty_json(SchemaTree::toJsonSchema($tree)) : '';
}
if ($content === '') abort(404, 'Phiên bản này không có mô tả cho phần ' . $part . '.');

$slug = trim(preg_replace('/[^a-z0-9]+/i', '-', $v['endpoint']['method'] . '-' . $v['endpoint']['path']), '-');
$filename = strtolower($slug) . '_' . preg_replace('/[^a-z0-9.]+/i', '-', $v['label']) . '_' . $part . ($kind === 'schema' ? '.schema' : '') . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $content;
