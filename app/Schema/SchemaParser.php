<?php
/**
 * Đọc bản mô tả cấu trúc dữ liệu (JSON Schema) và chuyển thành cây chuẩn hóa.
 *
 * Hỗ trợ: object lồng nhau, mảng, kiểu cơ bản, required, nullable, enum/const, format,
 * ràng buộc, $ref nội bộ (#/$defs/..., #/definitions/...), allOf (gộp schema).
 *
 * Các cấu trúc chưa hỗ trợ (oneOf/anyOf đầy đủ, $ref ngoài file, tuple…) được ghi nhận
 * thành "vấn đề" (issue); nhánh tương ứng có type = 'unknown' khi không giải được.
 */
class SchemaParser
{
    public const TYPES   = ['object', 'array', 'string', 'number', 'integer', 'boolean', 'null'];
    public const FORMATS = ['date-time', 'date', 'time', 'email', 'uri', 'url', 'uuid', 'ipv4', 'ipv6', 'hostname'];
    public const CONSTRAINTS = ['minimum', 'maximum', 'minLength', 'maxLength', 'minItems', 'maxItems', 'pattern'];

    private const MAX_DEPTH = 32;
    private const MAX_REF_DEPTH = 16;

    /** Từ khóa chỉ mang tính chú thích – bỏ qua, không ảnh hưởng cấu trúc. */
    private const ANNOTATIONS = [
        '$schema', '$id', '$comment', 'title', 'description', 'default', 'example', 'examples',
        'deprecated', 'readOnly', 'writeOnly', 'contentMediaType', 'contentEncoding',
    ];

    private const STRUCTURE = [
        'type', 'properties', 'required', 'items', 'enum', 'const', 'format', 'nullable',
        'additionalProperties', '$ref', 'allOf', 'oneOf', 'anyOf',
    ];

    /** Ràng buộc nhận diện được nhưng chưa kiểm tra – cảnh báo rồi bỏ qua. */
    private const IGNORED_CONSTRAINTS = ['exclusiveMinimum', 'exclusiveMaximum', 'multipleOf', 'uniqueItems', 'minProperties', 'maxProperties'];

    /** Cấu trúc chưa hỗ trợ – cả nhánh bị đánh dấu unknown (trừ khi đã xử lý riêng). */
    public const UNSUPPORTED = [
        'not'                    => 'phủ định not',
        'if'                     => 'điều kiện if/then/else',
        'then'                   => 'điều kiện if/then/else',
        'else'                   => 'điều kiện if/then/else',
        'patternProperties'      => 'trường theo mẫu patternProperties',
        'dependentSchemas'       => 'phụ thuộc dependentSchemas',
        'dependentRequired'      => 'phụ thuộc dependentRequired',
        'dependencies'           => 'phụ thuộc dependencies',
        'prefixItems'            => 'mảng dạng tuple (prefixItems)',
        'additionalItems'        => 'mảng dạng tuple (additionalItems)',
        'contains'               => 'ràng buộc contains',
        'unevaluatedProperties'  => 'unevaluatedProperties',
        'unevaluatedItems'       => 'unevaluatedItems',
        'propertyNames'          => 'ràng buộc propertyNames',
    ];

    private array $issues = [];
    private ?stdClass $root = null;
    private array $refStack = [];

    /**
     * @return array{ok: bool, tree: ?array, issues: array, error: ?string}
     */
    public function parse(string $json): array
    {
        $this->issues = [];
        $this->root = null;
        $this->refStack = [];
        if (trim($json) === '') {
            return ['ok' => false, 'tree' => null, 'issues' => [], 'error' => 'Nội dung trống.'];
        }
        try {
            $data = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return ['ok' => false, 'tree' => null, 'issues' => [], 'error' => json_error_vi($e)];
        }
        if (!($data instanceof stdClass)) {
            return ['ok' => false, 'tree' => null, 'issues' => [],
                'error' => 'Bản mô tả JSON Schema phải là một object (bắt đầu bằng dấu {). Nếu đây là dữ liệu mẫu, hãy chọn định dạng "JSON mẫu".'];
        }
        $this->root = $data;
        $tree = $this->node($data, '$', 0);
        return ['ok' => true, 'tree' => $tree, 'issues' => $this->issues, 'error' => null];
    }

    private function issue(string $level, string $path, string $message): void
    {
        $this->issues[] = ['level' => $level, 'path' => $path, 'message' => $message];
    }

    private function unknown(string $reason): array
    {
        return ['type' => 'unknown', 'nullable' => false, 'reason' => $reason];
    }

    private function node($s, string $path, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            $this->issue('error', $path, 'Cấu trúc lồng nhau quá sâu (hơn ' . self::MAX_DEPTH . ' cấp).');
            return $this->unknown('lồng quá sâu');
        }
        if ($s === true) {
            return ['type' => 'any', 'nullable' => true];
        }
        if ($s === false) {
            $this->issue('error', $path, 'Cấu trúc chưa được hỗ trợ: schema `false` (không chấp nhận giá trị nào).');
            return $this->unknown('schema false');
        }
        if (!($s instanceof stdClass)) {
            $this->issue('error', $path, 'Mô tả của trường phải là một object JSON, nhận được ' . SchemaValidator::jsonType($s) . '.');
            return $this->unknown('mô tả không hợp lệ');
        }

        $p = get_object_vars($s);
        $keys = array_map('strval', array_keys($p));

        // 0. $ref nội bộ
        if (isset($p['$ref'])) {
            return $this->resolveRef($p['$ref'], $path, $depth, $p);
        }

        // 0b. allOf – gộp các schema
        if (isset($p['allOf'])) {
            $merged = $this->mergeAllOf($p, $path);
            if ($merged === null) {
                return $this->unknown('allOf');
            }
            return $this->node($merged, $path, $depth);
        }

        // 0c. oneOf / anyOf – chưa validate đầy đủ; ghi nhận và bỏ qua nhánh
        if (isset($p['oneOf']) || isset($p['anyOf'])) {
            $kw = isset($p['oneOf']) ? 'oneOf' : 'anyOf';
            $this->issue('error', $path, sprintf(
                'Cấu trúc chưa được hỗ trợ đầy đủ: kết hợp %s. Nhánh này sẽ bị bỏ qua khi so sánh và kiểm tra dữ liệu.',
                $kw
            ));
            return $this->unknown($kw);
        }

        // 1. Cấu trúc chưa hỗ trợ khác
        $found = array_values(array_intersect($keys, array_keys(self::UNSUPPORTED)));
        if ($found) {
            foreach ($found as $kw) {
                $this->issue('error', $path, sprintf(
                    'Cấu trúc chưa được hỗ trợ: %s (từ khóa `%s`). Nhánh này sẽ bị bỏ qua khi so sánh và kiểm tra dữ liệu.',
                    self::UNSUPPORTED[$kw], $kw
                ));
            }
            return $this->unknown(implode(', ', $found));
        }

        // 2. Kiểu dữ liệu
        $type = $p['type'] ?? null;
        $nullable = !empty($p['nullable']); // cú pháp OpenAPI 3.0
        if (is_array($type)) {
            $nonNull = array_values(array_filter($type, fn($t) => $t !== 'null'));
            $nullable = $nullable || count($nonNull) < count($type);
            if (count($nonNull) === 0) {
                $type = 'null';
            } elseif (count($nonNull) === 1) {
                $type = $nonNull[0];
            } else {
                $this->issue('error', $path, 'Cấu trúc chưa được hỗ trợ: kiểu hợp nhiều kiểu (' . implode(' | ', array_map('strval', $type)) . '). Chỉ hỗ trợ một kiểu, có thể kèm "null".');
                return $this->unknown('kiểu hợp ' . implode('|', array_map('strval', $type)));
            }
        }
        if ($type === null) {
            if (isset($p['properties']) || isset($p['required'])) {
                $type = 'object';
                $this->issue('info', $path, 'Không khai báo `type` nhưng có `properties` – hiểu là object.');
            } elseif (isset($p['items'])) {
                $type = 'array';
                $this->issue('info', $path, 'Không khai báo `type` nhưng có `items` – hiểu là array.');
            } else {
                $type = 'any';
                $this->issue('warning', $path, 'Không khai báo kiểu dữ liệu – mọi giá trị đều được chấp nhận.');
            }
        }
        if ($type !== 'any' && (!is_string($type) || !in_array($type, self::TYPES, true))) {
            $this->issue('error', $path, 'Kiểu dữ liệu không hợp lệ hoặc chưa hỗ trợ: ' . json_encode($type, JSON_UNESCAPED_UNICODE) . '.');
            return $this->unknown('kiểu ' . (is_string($type) ? $type : 'không hợp lệ'));
        }

        $node = ['type' => $type, 'nullable' => $nullable];
        if (isset($p['description']) && is_string($p['description']) && $p['description'] !== '') {
            $node['description'] = $p['description'];
        }

        // 3. enum / const
        $enum = null;
        if (array_key_exists('const', $p)) $enum = [$p['const']];
        if (array_key_exists('enum', $p)) {
            if (is_array($p['enum']) && $p['enum']) {
                $enum = $p['enum'];
            } else {
                $this->issue('warning', $path, '`enum` phải là mảng có ít nhất một giá trị – đã bỏ qua.');
            }
        }
        if ($enum !== null) {
            $scalar = array_values(array_filter($enum, fn($v) => is_scalar($v) || $v === null));
            if (count($scalar) < count($enum)) {
                $this->issue('warning', $path, 'Giá trị enum dạng object/mảng chưa được hỗ trợ – đã bỏ qua các giá trị đó.');
            }
            if ($scalar) $node['enum'] = $scalar;
        }

        // 4. format
        if (isset($p['format'])) {
            if (is_string($p['format'])) {
                $node['format'] = $p['format'];
                if (!in_array($p['format'], self::FORMATS, true)) {
                    $this->issue('warning', $path, 'Định dạng `' . $p['format'] . '` chưa được hỗ trợ kiểm tra – chỉ lưu để so sánh.');
                }
            } else {
                $this->issue('warning', $path, '`format` phải là chuỗi – đã bỏ qua.');
            }
        }

        // 5. Ràng buộc
        foreach (self::CONSTRAINTS as $c) {
            if (!array_key_exists($c, $p)) continue;
            $v = $p[$c];
            if ($c === 'pattern') {
                if (!is_string($v) || !SchemaValidator::isSafePattern($v)) {
                    $this->issue('warning', $path, '`pattern` không hợp lệ hoặc không an toàn (ReDoS) – đã bỏ qua.');
                    continue;
                }
            } elseif (!is_int($v) && !is_float($v)) {
                $this->issue('warning', $path, "`$c` phải là số – đã bỏ qua.");
                continue;
            }
            $node['constraints'][$c] = $v;
        }
        foreach (self::IGNORED_CONSTRAINTS as $c) {
            if (array_key_exists($c, $p)) {
                $this->issue('warning', $path, "Ràng buộc `$c` chưa được hỗ trợ – đã bỏ qua.");
            }
        }

        // 6. Object
        if ($type === 'object') {
            $node['properties'] = [];
            $props = $p['properties'] ?? new stdClass();
            if (!($props instanceof stdClass)) {
                $this->issue('error', $path, '`properties` phải là một object.');
                $props = new stdClass();
            }
            foreach (get_object_vars($props) as $name => $child) {
                $name = (string)$name;
                $node['properties'][$name] = $this->node($child, SchemaTree::childPath($path, $name), $depth + 1);
            }

            $node['required'] = [];
            $req = $p['required'] ?? [];
            if (!is_array($req)) {
                $this->issue('warning', $path, '`required` phải là mảng tên trường – đã bỏ qua.');
                $req = [];
            }
            foreach ($req as $r) {
                if (!is_string($r)) {
                    $this->issue('warning', $path, 'Tên trường trong `required` phải là chuỗi – đã bỏ qua ' . json_encode($r) . '.');
                } elseif (!array_key_exists($r, $node['properties'])) {
                    $this->issue('warning', $path, "Trường bắt buộc `$r` chưa được khai báo trong `properties`.");
                } elseif (!in_array($r, $node['required'], true)) {
                    $node['required'][] = $r;
                }
            }

            if (array_key_exists('additionalProperties', $p)) {
                $ap = $p['additionalProperties'];
                if (is_bool($ap)) {
                    $node['additionalProperties'] = $ap;
                } else {
                    $this->issue('error', $path, 'Cấu trúc chưa được hỗ trợ: `additionalProperties` dạng schema (object kiểu từ điển/map). Các trường phát sinh sẽ không được kiểm tra kiểu.');
                }
            }
        } elseif (isset($p['properties']) || isset($p['required'])) {
            $this->issue('warning', $path, "`properties`/`required` chỉ áp dụng cho object nhưng trường này có kiểu $type – đã bỏ qua.");
        }

        // 7. Array
        if ($type === 'array') {
            if (!array_key_exists('items', $p)) {
                $this->issue('warning', $path, 'Mảng không khai báo `items` – phần tử có thể là bất kỳ kiểu nào.');
                $node['items'] = ['type' => 'any', 'nullable' => true];
            } elseif (is_array($p['items'])) {
                $this->issue('error', $path . '[]', 'Cấu trúc chưa được hỗ trợ: mảng dạng tuple (`items` là danh sách schema).');
                $node['items'] = $this->unknown('mảng tuple');
            } else {
                $node['items'] = $this->node($p['items'], $path . '[]', $depth + 1);
            }
        } elseif (isset($p['items'])) {
            $this->issue('warning', $path, "`items` chỉ áp dụng cho array nhưng trường này có kiểu $type – đã bỏ qua.");
        }

        // 8. Từ khóa lạ
        $known = array_merge(self::ANNOTATIONS, self::STRUCTURE, self::CONSTRAINTS, self::IGNORED_CONSTRAINTS, ['$defs', 'definitions', 'components']);
        foreach ($keys as $k) {
            if (!in_array($k, $known, true) && !str_starts_with($k, 'x-')) {
                $this->issue('warning', $path, "Từ khóa `$k` không được nhận diện – đã bỏ qua.");
            }
        }

        return $node;
    }

    /**
     * Giải $ref nội bộ dạng #/$defs/Name hoặc #/definitions/Name hoặc #/components/schemas/Name.
     * Các khóa khác trên cùng object (sibling) được gộp nhẹ sau khi resolve.
     */
    private function resolveRef($ref, string $path, int $depth, array $siblings): array
    {
        if (!is_string($ref) || $ref === '') {
            $this->issue('error', $path, '`$ref` phải là chuỗi.');
            return $this->unknown('$ref');
        }
        if (!str_starts_with($ref, '#/')) {
            $this->issue('error', $path, 'Chỉ hỗ trợ `$ref` nội bộ bắt đầu bằng `#/` (ví dụ `#/$defs/User`). Nhận được: ' . $ref);
            return $this->unknown('$ref ngoài');
        }
        if (count($this->refStack) >= self::MAX_REF_DEPTH) {
            $this->issue('error', $path, 'Chuỗi `$ref` quá sâu hoặc vòng lặp.');
            return $this->unknown('$ref vòng');
        }
        if (in_array($ref, $this->refStack, true)) {
            $this->issue('error', $path, 'Phát hiện `$ref` vòng: ' . $ref);
            return $this->unknown('$ref vòng');
        }

        $target = $this->lookupPointer($ref);
        if ($target === null) {
            $this->issue('error', $path, 'Không tìm thấy đích `$ref`: ' . $ref);
            return $this->unknown('$ref thiếu');
        }

        $this->refStack[] = $ref;
        $this->issue('info', $path, 'Đã giải `$ref` → ' . $ref);
        $resolved = $this->node($target, $path, $depth + 1);
        array_pop($this->refStack);

        // Sibling keywords (OpenAPI hay dùng): gộp description/nullable đơn giản
        unset($siblings['$ref']);
        if ($siblings) {
            if (isset($siblings['description']) && is_string($siblings['description']) && $siblings['description'] !== '') {
                $resolved['description'] = $siblings['description'];
            }
            if (!empty($siblings['nullable'])) {
                $resolved['nullable'] = true;
            }
        }
        return $resolved;
    }

    private function lookupPointer(string $ref)
    {
        if ($this->root === null) return null;
        $parts = explode('/', substr($ref, 2)); // bỏ "#/"
        $cur = $this->root;
        foreach ($parts as $part) {
            $part = str_replace(['~1', '~0'], ['/', '~'], $part);
            if ($cur instanceof stdClass) {
                $vars = get_object_vars($cur);
                if (!array_key_exists($part, $vars)) return null;
                $cur = $vars[$part];
            } elseif (is_array($cur) && array_key_exists($part, $cur)) {
                $cur = $cur[$part];
            } else {
                return null;
            }
        }
        return $cur;
    }

    /**
     * Gộp allOf thành một schema object; trả về null nếu không gộp được.
     */
    private function mergeAllOf(array $p, string $path): ?stdClass
    {
        $parts = $p['allOf'];
        if (!is_array($parts) || !$parts) {
            $this->issue('error', $path, '`allOf` phải là mảng schema không rỗng.');
            return null;
        }

        $merged = new stdClass();
        foreach ($p as $k => $v) {
            if ($k === 'allOf') continue;
            $merged->$k = $v;
        }

        foreach ($parts as $i => $part) {
            if ($part === true) continue;
            if ($part === false) {
                $this->issue('error', $path, '`allOf` chứa schema `false` – không thể gộp.');
                return null;
            }
            if (!($part instanceof stdClass)) {
                $this->issue('error', $path . '/allOf/' . $i, 'Phần tử allOf phải là object schema.');
                return null;
            }
            // Resolve $ref trong phần tử allOf trước khi gộp
            $piece = $part;
            if (isset($part->{'$ref'})) {
                $resolved = $this->lookupPointer((string)$part->{'$ref'});
                if ($resolved === null) {
                    $this->issue('error', $path . '/allOf/' . $i, 'Không giải được $ref trong allOf: ' . $part->{'$ref'});
                    return null;
                }
                if (!($resolved instanceof stdClass)) {
                    $this->issue('error', $path . '/allOf/' . $i, 'Đích $ref trong allOf không phải object.');
                    return null;
                }
                $piece = $this->shallowMergeObjects($resolved, $part, ['$ref']);
            }
            $merged = $this->shallowMergeObjects($merged, $piece, []);
        }

        $this->issue('info', $path, 'Đã gộp ' . count($parts) . ' schema trong `allOf`.');
        return $merged;
    }

    private function shallowMergeObjects(stdClass $a, stdClass $b, array $skipKeys): stdClass
    {
        $out = clone $a;
        foreach (get_object_vars($b) as $k => $v) {
            if (in_array($k, $skipKeys, true)) continue;
            if ($k === 'properties' && ($out->properties ?? null) instanceof stdClass && $v instanceof stdClass) {
                $props = clone $out->properties;
                foreach (get_object_vars($v) as $pk => $pv) {
                    $props->$pk = $pv;
                }
                $out->properties = $props;
            } elseif ($k === 'required' && is_array($v)) {
                $out->required = array_values(array_unique(array_merge(
                    is_array($out->required ?? null) ? $out->required : [],
                    $v
                )));
            } elseif ($k === 'allOf' && is_array($v)) {
                // Lồng allOf – nối vào để vòng sau xử lý (hiếm)
                $existing = is_array($out->allOf ?? null) ? $out->allOf : [];
                $out->allOf = array_merge($existing, $v);
            } else {
                $out->$k = $v;
            }
        }
        return $out;
    }
}
