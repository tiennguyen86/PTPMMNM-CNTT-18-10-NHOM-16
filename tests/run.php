#!/usr/bin/env php
<?php
/**
 * Bộ kiểm thử không phụ thuộc PHPUnit.
 * Chạy: php tests/run.php
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Chỉ chạy trên CLI.\n");
    exit(1);
}

define('APP', true);
$root = dirname(__DIR__);
require $root . '/app/env.php';
load_env($root . '/.env');

$GLOBALS['config'] = [
    'app_name' => 'API SchemaCheck',
    'debug' => true,
    'upload_max_bytes' => 2 * 1024 * 1024,
    'timezone' => 'Asia/Ho_Chi_Minh',
    'https' => false,
    'allow_register' => true,
    'login_max_attempts' => 8,
    'login_window_minutes' => 15,
    'db' => [
        'driver' => 'sqlite',
        'sqlite_path' => $root . '/database/test_' . getmypid() . '.sqlite',
        'host' => '', 'port' => 0, 'database' => '', 'username' => '', 'password' => '',
    ],
];

require $root . '/app/helpers.php';
require $root . '/app/Schema/SchemaTree.php';
require $root . '/app/Schema/SchemaParser.php';
require $root . '/app/Schema/SchemaInferrer.php';
require $root . '/app/Schema/SchemaDiff.php';
require $root . '/app/Schema/SchemaValidator.php';
require $root . '/app/Schema/OpenApiImporter.php';
require $root . '/app/db.php';
require $root . '/app/install.php';
require $root . '/app/auth.php';
require $root . '/app/services.php';

$pass = 0;
$fail = 0;
$failures = [];

function assert_true(bool $cond, string $msg): void
{
    global $pass, $fail, $failures;
    if ($cond) {
        $pass++;
        echo "  OK  $msg\n";
    } else {
        $fail++;
        $failures[] = $msg;
        echo " FAIL $msg\n";
    }
}

function assert_eq($expected, $actual, string $msg): void
{
    assert_true($expected === $actual, $msg . ' (expected ' . json_encode($expected) . ', got ' . json_encode($actual) . ')');
}

echo "== SchemaParser ==\n";
$parser = new SchemaParser();
$r = $parser->parse('{"type":"object","properties":{"id":{"type":"integer"}},"required":["id"]}');
assert_true($r['ok'], 'parse object cơ bản');
assert_eq('object', $r['tree']['type'] ?? null, 'type object');

$ref = $parser->parse(json_encode([
    '$defs' => ['User' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']]],
    'type' => 'object',
    'properties' => ['user' => ['$ref' => '#/$defs/User']],
]));
assert_true($ref['ok'], 'parse $ref nội bộ');
assert_eq('object', $ref['tree']['properties']['user']['type'] ?? null, '$ref resolve User');
assert_eq('string', $ref['tree']['properties']['user']['properties']['name']['type'] ?? null, '$ref name string');

$allOf = $parser->parse(json_encode([
    'allOf' => [
        ['type' => 'object', 'properties' => ['a' => ['type' => 'string']], 'required' => ['a']],
        ['type' => 'object', 'properties' => ['b' => ['type' => 'integer']]],
    ],
]));
assert_true($allOf['ok'], 'parse allOf');
assert_true(isset($allOf['tree']['properties']['a'], $allOf['tree']['properties']['b']), 'allOf merge properties');

$unsafe = $parser->parse('{"type":"string","pattern":"(a+)+b"}');
assert_true($unsafe['ok'], 'parse pattern nguy hiểm vẫn ok');
assert_true(empty($unsafe['tree']['constraints']['pattern'] ?? null), 'pattern ReDoS bị bỏ qua');

echo "\n== SchemaDiff ==\n";
$rules = [];
foreach ((require $root . '/app/default_rules.php') as $i => $row) {
    $rules[$row['code']] = $row + ['sort_order' => $i];
}
$diff = new SchemaDiff($rules);
$old = $parser->parse('{"type":"object","properties":{"phone":{"type":"string"}},"required":["phone"]}');
$new = $parser->parse('{"type":"object","properties":{"phoneNumber":{"type":"string"}},"required":["phoneNumber"]}');
$changes = $diff->compare($old['tree'], $new['tree'], 'response');
$codes = array_column($changes, 'code');
assert_true(in_array('FIELD_RENAMED', $codes, true), 'detect rename phone→phoneNumber');

$old2 = $parser->parse('{"type":"object","properties":{"age":{"type":"integer"}}}');
$new2 = $parser->parse('{"type":"object","properties":{"age":{"type":"number"}}}');
$c2 = $diff->compare($old2['tree'], $new2['tree'], 'response');
assert_true(in_array('TYPE_WIDENED', array_column($c2, 'code'), true), 'TYPE_WIDENED integer→number');

// array thiếu items không được nổ
$c3 = $diff->compare(
    ['type' => 'array', 'nullable' => false],
    ['type' => 'array', 'nullable' => false, 'items' => ['type' => 'string', 'nullable' => false]],
    'response'
);
assert_true(is_array($c3), 'diff array thiếu items không crash');

$sum = SchemaDiff::summarize($changes);
assert_eq('major', $sum['bump'], 'bump major khi breaking');

echo "\n== SemVer helpers ==\n";
assert_eq('v2.0.0', next_version_label('v1.2.3', 'major'), 'next major from v1');
assert_eq('v3.0.0', suggest_version_after_compare('v1.0.0', 'v2.0.0', 'major'), 'suggest từ bản mới');

echo "\n== SchemaValidator ==\n";
$tree = $parser->parse('{"type":"object","properties":{"email":{"type":"string","format":"email"}},"required":["email"],"additionalProperties":false}');
$ok = (new SchemaValidator())->validate($tree['tree'], json_decode('{"email":"a@b.com"}'));
assert_eq(0, count(array_filter($ok, fn($e) => $e['level'] === 'error')), 'email hợp lệ');
$bad = (new SchemaValidator())->validate($tree['tree'], json_decode('{"email":"not-an-email","extra":1}'));
$badCodes = array_column($bad, 'code');
assert_true(in_array('FORMAT_INVALID', $badCodes, true), 'email sai format');
assert_true(in_array('UNKNOWN_FIELD', $badCodes, true), 'trường lạ');

assert_true(SchemaValidator::isSafePattern('^[a-z]+$'), 'pattern an toàn');
assert_true(!SchemaValidator::isSafePattern('(a+)+$'), 'pattern ReDoS bị chặn');

echo "\n== OpenApiImporter ==\n";
$oas = <<<'JSON'
{
  "openapi": "3.0.3",
  "info": {"title": "Demo", "version": "1.0.0"},
  "paths": {
    "/users/{id}": {
      "get": {
        "summary": "Get user",
        "responses": {
          "200": {
            "description": "ok",
            "content": {
              "application/json": {
                "schema": {"$ref": "#/components/schemas/User"}
              }
            }
          }
        }
      }
    },
    "/users": {
      "post": {
        "summary": "Create user",
        "requestBody": {
          "content": {
            "application/json": {
              "schema": {"$ref": "#/components/schemas/User"}
            }
          }
        },
        "responses": {
          "201": {
            "content": {
              "application/json": {
                "schema": {"$ref": "#/components/schemas/User"}
              }
            }
          }
        }
      }
    }
  },
  "components": {
    "schemas": {
      "User": {
        "type": "object",
        "required": ["id", "name"],
        "properties": {
          "id": {"type": "integer"},
          "name": {"type": "string"}
        }
      }
    }
  }
}
JSON;
$imp = (new OpenApiImporter())->parse($oas);
assert_true($imp['ok'], 'import OpenAPI ok');
assert_eq(2, count($imp['operations']), '2 operations');
assert_true($imp['operations'][0]['has_response'], 'GET có response schema');
assert_true($imp['operations'][1]['has_request'], 'POST có request schema');

echo "\n== Payload size ==\n";
$big = str_repeat('a', (int)$GLOBALS['config']['upload_max_bytes'] + 10);
assert_true(assert_payload_size($big) !== null, 'chặn payload quá lớn');
assert_true(assert_payload_size('{}') === null, 'payload nhỏ OK');

echo "\n== DB install (SQLite tạm) ==\n";
$testDb = $GLOBALS['config']['db']['sqlite_path'];
try {
    $pdo = db();
    assert_true(table_exists($pdo, 'sqlite', 'users'), 'bảng users');
    assert_true(table_exists($pdo, 'sqlite', 'login_attempts'), 'bảng login_attempts');
    assert_true((int)val('SELECT COUNT(*) FROM users') >= 1, 'có user seed');

    $id = db_transaction(function () {
        $pid = insert('projects', [
            'name' => 'Test', 'description' => null,
            'created_by' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        insert('project_members', [
            'project_id' => $pid, 'user_id' => 1, 'role' => 'owner', 'added_at' => now(),
        ]);
        return $pid;
    });
    assert_true($id > 0, 'db_transaction tạo project');
} catch (Throwable $e) {
    assert_true(false, 'DB error: ' . $e->getMessage());
} finally {
    // đóng PDO static khó – xóa file sau process
    if (is_file($testDb)) @unlink($testDb);
    foreach ([$testDb . '-shm', $testDb . '-wal'] as $extra) {
        if (is_file($extra)) @unlink($extra);
    }
}

echo "\n== Sample fixtures ==\n";
foreach (['users_v1.response.json', 'users_v2.response.json', 'sample_user_ok.json', 'sample_user_error.json'] as $f) {
    $path = $root . '/samples/' . $f;
    assert_true(is_readable($path), "sample $f tồn tại");
}
$u1 = $parser->parse(file_get_contents($root . '/samples/users_v1.response.json'));
$u2 = $parser->parse(file_get_contents($root . '/samples/users_v2.response.json'));
assert_true($u1['ok'] && $u2['ok'], 'parse users v1/v2');
$demo = (new SchemaDiff($rules))->compare($u1['tree'], $u2['tree'], 'response');
assert_true(count($demo) > 0, 'users v1→v2 có thay đổi');

echo "\n--------------------------------\n";
echo "Passed: $pass  Failed: $fail\n";
if ($fail) {
    echo "Failures:\n- " . implode("\n- ", $failures) . "\n";
    exit(1);
}
echo "ALL TESTS PASSED\n";
exit(0);
