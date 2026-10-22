#!/usr/bin/env php
<?php
/**
 * CLI cho CI: so sánh schema hoặc validate JSON mẫu.
 *
 *   php cli/schemacheck.php compare old.json new.json [--part=response|request|both] [--fail-on=breaking|warning]
 *   php cli/schemacheck.php validate schema.json sample.json
 *   php cli/schemacheck.php parse schema.json
 *
 * Exit code: 0 OK, 1 lỗi dùng, 2 có breaking (hoặc warning nếu --fail-on=warning), 3 lỗi runtime.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Chỉ chạy trên CLI.\n");
    exit(1);
}

define('APP', true);
$root = dirname(__DIR__);
require $root . '/app/env.php';
load_env($root . '/.env');

// Bootstrap tối giản (không session/DB)
// stub config() cho helpers
$GLOBALS['config'] = [
    'app_name' => 'API SchemaCheck',
    'debug' => true,
    'upload_max_bytes' => 2 * 1024 * 1024,
    'timezone' => 'Asia/Ho_Chi_Minh',
];
require $root . '/app/helpers.php';
require $root . '/app/Schema/SchemaTree.php';
require $root . '/app/Schema/SchemaParser.php';
require $root . '/app/Schema/SchemaInferrer.php';
require $root . '/app/Schema/SchemaDiff.php';
require $root . '/app/Schema/SchemaValidator.php';

$argv = $_SERVER['argv'];
array_shift($argv);
$cmd = $argv[0] ?? '';
$args = array_slice($argv, 1);

$opts = [];
$files = [];
foreach ($args as $a) {
    if (str_starts_with($a, '--')) {
        [$k, $v] = array_pad(explode('=', substr($a, 2), 2), 2, '1');
        $opts[$k] = $v;
    } else {
        $files[] = $a;
    }
}

function cli_read(string $path): string
{
    if (!is_readable($path)) {
        fwrite(STDERR, "Không đọc được tệp: $path\n");
        exit(1);
    }
    return (string)file_get_contents($path);
}

function cli_rules(): array
{
    global $root;
    $out = [];
    foreach ((require $root . '/app/default_rules.php') as $i => $r) {
        $out[$r['code']] = $r + ['sort_order' => $i];
    }
    return $out;
}

function cli_parse_file(string $path): array
{
    $raw = cli_read($path);
    $r = (new SchemaParser())->parse($raw);
    if (!$r['ok']) {
        fwrite(STDERR, "Parse lỗi ($path): {$r['error']}\n");
        exit(1);
    }
    return $r;
}

switch ($cmd) {
    case 'parse':
        if (count($files) < 1) {
            fwrite(STDERR, "Usage: parse schema.json\n");
            exit(1);
        }
        $r = cli_parse_file($files[0]);
        $errs = count(array_filter($r['issues'], fn($i) => $i['level'] === 'error'));
        echo pretty_json(['ok' => true, 'unsupported_errors' => $errs, 'issues' => $r['issues'], 'tree' => $r['tree']]) . "\n";
        exit($errs ? 2 : 0);

    case 'validate':
        if (count($files) < 2) {
            fwrite(STDERR, "Usage: validate schema.json sample.json\n");
            exit(1);
        }
        $schema = cli_parse_file($files[0]);
        try {
            $data = json_decode(cli_read($files[1]), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            fwrite(STDERR, 'Sample JSON lỗi: ' . $e->getMessage() . "\n");
            exit(1);
        }
        $errors = (new SchemaValidator())->validate($schema['tree'], $data);
        $errCount = count(array_filter($errors, fn($e) => $e['level'] === 'error'));
        echo pretty_json(['ok' => $errCount === 0, 'error_count' => $errCount, 'errors' => $errors]) . "\n";
        exit($errCount ? 2 : 0);

    case 'compare':
        if (count($files) < 2) {
            fwrite(STDERR, "Usage: compare old.json new.json [--part=response|request|both] [--fail-on=breaking|warning]\n");
            exit(1);
        }
        $part = $opts['part'] ?? 'response';
        $failOn = $opts['fail-on'] ?? 'breaking';
        $old = cli_parse_file($files[0]);
        $new = cli_parse_file($files[1]);
        $diff = new SchemaDiff(cli_rules());
        $changes = [];
        $parts = $part === 'both' ? ['request', 'response'] : [$part];
        foreach ($parts as $p) {
            // CLI so sánh một cây / mỗi lần; gán vào part tương ứng
            $changes = array_merge($changes, $diff->compare($old['tree'], $new['tree'], $p));
        }
        $changes = SchemaDiff::sort($changes);
        $summary = SchemaDiff::summarize($changes);
        echo pretty_json(['summary' => $summary, 'changes' => $changes]) . "\n";

        $fail = false;
        if ($failOn === 'warning') {
            $fail = ($summary['counts']['breaking'] + $summary['counts']['warning']) > 0;
        } else {
            $fail = $summary['counts']['breaking'] > 0;
        }
        exit($fail ? 2 : 0);

    default:
        fwrite(STDERR, "Lệnh không hợp lệ. Dùng: compare | validate | parse\n");
        exit(1);
}
