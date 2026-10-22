<?php
/**
 * Kiểm tra một dữ liệu JSON (phản hồi/yêu cầu mẫu) có đúng với cây cấu trúc hay không.
 * Mỗi lỗi gồm: level (error|warning), code, path, message, expected, actual.
 */
class SchemaValidator
{
    public const CODES = [
        'TYPE_MISMATCH'        => 'Sai kiểu dữ liệu',
        'MISSING_REQUIRED'     => 'Thiếu trường bắt buộc',
        'NULL_NOT_ALLOWED'     => 'Không cho phép null',
        'ENUM_MISMATCH'        => 'Giá trị ngoài danh sách cho phép',
        'FORMAT_INVALID'       => 'Sai định dạng',
        'CONSTRAINT_VIOLATION' => 'Vi phạm ràng buộc',
        'UNKNOWN_FIELD'        => 'Trường không có trong mô tả',
        'UNSUPPORTED_SKIPPED'  => 'Bỏ qua (cấu trúc chưa hỗ trợ)',
    ];

    private const MAX_ERRORS = 500;

    private array $errors = [];

    public function validate(array $tree, $data): array
    {
        $this->errors = [];
        $this->check($tree, $data, '$');
        return $this->errors;
    }

    private function add(string $level, string $code, string $path, string $message, string $expected = '', string $actual = ''): void
    {
        if (count($this->errors) >= self::MAX_ERRORS) return;
        $this->errors[] = compact('level', 'code', 'path', 'message', 'expected', 'actual');
    }

    private function check(array $node, $v, string $path): void
    {
        if (count($this->errors) >= self::MAX_ERRORS) return;
        $t = $node['type'];

        if ($t === 'unknown') {
            $this->add('warning', 'UNSUPPORTED_SKIPPED', $path, 'Bỏ qua kiểm tra vì bản mô tả dùng cấu trúc chưa hỗ trợ (' . ($node['reason'] ?? '') . ').');
            return;
        }

        if ($v === null) {
            if (in_array($t, ['null', 'any'], true) || !empty($node['nullable'])) return;
            $this->add('error', 'NULL_NOT_ALLOWED', $path, 'Trường không được phép mang giá trị null.', SchemaTree::typeLabel($node), 'null');
            return;
        }

        if ($t !== 'any' && !self::matchesType($t, $v)) {
            $this->add('error', 'TYPE_MISMATCH', $path,
                sprintf('Sai kiểu dữ liệu: cần %s nhưng nhận được %s.', $t, self::jsonType($v)),
                SchemaTree::typeLabel($node), self::preview($v));
            return;
        }

        if (isset($node['enum'])) {
            $allowed = array_map(fn($x) => json_encode($x, JSON_UNESCAPED_UNICODE), $node['enum']);
            if (!in_array(json_encode($v, JSON_UNESCAPED_UNICODE), $allowed, true)) {
                $this->add('error', 'ENUM_MISMATCH', $path, 'Giá trị không nằm trong danh sách cho phép.', implode(', ', $allowed), self::preview($v));
            }
        }

        $c = $node['constraints'] ?? [];
        switch ($t) {
            case 'object':
                $this->checkObject($node, $v, $path);
                break;

            case 'array':
                $n = count($v);
                if (isset($c['minItems']) && $n < $c['minItems']) {
                    $this->add('error', 'CONSTRAINT_VIOLATION', $path, "Mảng cần ít nhất {$c['minItems']} phần tử.", '≥ ' . $c['minItems'] . ' phần tử', $n . ' phần tử');
                }
                if (isset($c['maxItems']) && $n > $c['maxItems']) {
                    $this->add('error', 'CONSTRAINT_VIOLATION', $path, "Mảng có tối đa {$c['maxItems']} phần tử.", '≤ ' . $c['maxItems'] . ' phần tử', $n . ' phần tử');
                }
                $items = $node['items'] ?? ['type' => 'any', 'nullable' => true];
                foreach ($v as $i => $item) {
                    $this->check($items, $item, $path . '[' . $i . ']');
                }
                break;

            case 'string':
                if (!empty($node['format']) && !self::checkFormat($node['format'], $v)) {
                    $this->add('error', 'FORMAT_INVALID', $path, "Giá trị không đúng định dạng {$node['format']}.", $node['format'], self::preview($v));
                }
                $len = mb_strlen($v);
                if (isset($c['minLength']) && $len < $c['minLength']) {
                    $this->add('error', 'CONSTRAINT_VIOLATION', $path, "Chuỗi cần ít nhất {$c['minLength']} ký tự.", 'độ dài ≥ ' . $c['minLength'], 'độ dài ' . $len);
                }
                if (isset($c['maxLength']) && $len > $c['maxLength']) {
                    $this->add('error', 'CONSTRAINT_VIOLATION', $path, "Chuỗi tối đa {$c['maxLength']} ký tự.", 'độ dài ≤ ' . $c['maxLength'], 'độ dài ' . $len);
                }
                if (isset($c['pattern'])) {
                    $match = self::safePregMatch($c['pattern'], $v);
                    if ($match === false) {
                        $this->add('warning', 'CONSTRAINT_VIOLATION', $path, 'Không kiểm tra được `pattern` (biểu thức không an toàn hoặc không hợp lệ).', $c['pattern'], self::preview($v));
                    } elseif ($match === 0) {
                        $this->add('error', 'CONSTRAINT_VIOLATION', $path, 'Chuỗi không khớp mẫu yêu cầu.', $c['pattern'], self::preview($v));
                    }
                }
                break;

            case 'integer':
            case 'number':
                if (isset($c['minimum']) && $v < $c['minimum']) {
                    $this->add('error', 'CONSTRAINT_VIOLATION', $path, "Giá trị phải ≥ {$c['minimum']}.", '≥ ' . $c['minimum'], self::preview($v));
                }
                if (isset($c['maximum']) && $v > $c['maximum']) {
                    $this->add('error', 'CONSTRAINT_VIOLATION', $path, "Giá trị phải ≤ {$c['maximum']}.", '≤ ' . $c['maximum'], self::preview($v));
                }
                break;
        }
    }

    private function checkObject(array $node, stdClass $v, string $path): void
    {
        $props = $node['properties'] ?? [];
        $data = get_object_vars($v);
        $present = array_map('strval', array_keys($data));

        foreach ($node['required'] ?? [] as $req) {
            if (!in_array((string)$req, $present, true)) {
                $this->add('error', 'MISSING_REQUIRED', SchemaTree::childPath($path, (string)$req),
                    "Thiếu trường bắt buộc \"$req\".", SchemaTree::typeLabel($props[$req] ?? null), '(không có)');
            }
        }

        $strict = ($node['additionalProperties'] ?? true) === false;
        foreach ($data as $key => $value) {
            $key = (string)$key;
            $childPath = SchemaTree::childPath($path, $key);
            if (array_key_exists($key, $props)) {
                $this->check($props[$key], $value, $childPath);
            } else {
                $this->add($strict ? 'error' : 'warning', 'UNKNOWN_FIELD', $childPath,
                    $strict ? "Trường \"$key\" không được phép (mô tả không nhận trường lạ)." : "Trường \"$key\" không có trong bản mô tả.",
                    '(không khai báo)', self::preview($value));
            }
        }
    }

    public static function matchesType(string $type, $v): bool
    {
        return match ($type) {
            'object'  => $v instanceof stdClass,
            'array'   => is_array($v),
            'string'  => is_string($v),
            'integer' => is_int($v) || (is_float($v) && is_finite($v) && floor($v) == $v),
            'number'  => is_int($v) || is_float($v),
            'boolean' => is_bool($v),
            'null'    => $v === null,
            default   => true,
        };
    }

    public static function jsonType($v): string
    {
        return match (true) {
            $v === null          => 'null',
            is_bool($v)          => 'boolean',
            is_int($v)           => 'integer',
            is_float($v)         => 'number',
            is_string($v)        => 'string',
            is_array($v)         => 'array',
            $v instanceof stdClass => 'object',
            default              => gettype($v),
        };
    }

    public static function preview($v): string
    {
        $s = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return mb_strlen($s) > 120 ? mb_substr($s, 0, 117) . '...' : $s;
    }

    public static function regex(string $pattern): string
    {
        return '~' . str_replace('~', '\~', $pattern) . '~u';
    }

    /** Độ dài pattern tối đa để giảm ReDoS. */
    public const PATTERN_MAX_LENGTH = 200;

    /**
     * Kiểm tra pattern có quá phức tạp / nguy hiểm không.
     * Không chứng minh được an toàn tuyệt đối – chỉ lọc các mẫu thường gây ReDoS.
     */
    public static function isSafePattern(string $pattern): bool
    {
        if ($pattern === '' || strlen($pattern) > self::PATTERN_MAX_LENGTH) return false;
        // Lồng quantifier kiểu (a+)+, (a*)*, (a+){2,}, v.v.
        if (preg_match('/\((?:[^()\\\\]|\\\\.)*[+*]\)[+*{]/', $pattern)) return false;
        if (preg_match('/\((?:[^()\\\\]|\\\\.)*[+*]\)\{/', $pattern)) return false;
        // Nhiều .* hoặc .+ liền nhau
        if (preg_match('/(\.\*[+?]?){3,}|(\.\+[+?]?){3,}/', $pattern)) return false;
        return @preg_match(self::regex($pattern), '') !== false;
    }

    /** @return int|false 1 khớp, 0 không khớp, false lỗi/không an toàn */
    public static function safePregMatch(string $pattern, string $subject)
    {
        if (!self::isSafePattern($pattern)) return false;
        return @preg_match(self::regex($pattern), $subject);
    }

    public static function checkFormat(string $format, string $v): bool
    {
        switch ($format) {
            case 'email':
                return filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
            case 'uri':
            case 'url':
                return filter_var($v, FILTER_VALIDATE_URL) !== false;
            case 'uuid':
                return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v);
            case 'date':
                return (bool)preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
            case 'time':
                return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d(\.\d+)?([Zz]|[+-]\d{2}:\d{2})?$/', $v);
            case 'date-time':
                return (bool)preg_match('/^(\d{4})-(\d{2})-(\d{2})[Tt ]([01]\d|2[0-3]):[0-5]\d:[0-5]\d(\.\d+)?([Zz]|[+-]\d{2}:\d{2})$/', $v, $m)
                    && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
            case 'ipv4':
                return filter_var($v, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
            case 'ipv6':
                return filter_var($v, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
            case 'hostname':
                return (bool)preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/i', $v);
            default:
                return true; // định dạng chưa hỗ trợ: không kiểm tra
        }
    }
}
