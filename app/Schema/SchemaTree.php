<?php
/**
 * Tiện ích làm việc với "cây cấu trúc" đã chuẩn hóa.
 *
 * Mỗi nút (node) là một mảng:
 *   type        : object | array | string | number | integer | boolean | null | any | unknown
 *   nullable    : bool
 *   description : ?string
 *   enum        : ?array   (danh sách giá trị cho phép)
 *   format      : ?string  (email, date-time, uuid, ...)
 *   constraints : ?array   (minimum, maximum, minLength, maxLength, minItems, maxItems, pattern)
 *   properties  : [tên => node]       (chỉ với object)
 *   required    : [tên trường]        (chỉ với object)
 *   additionalProperties : bool       (chỉ với object, mặc định true)
 *   items       : node                (chỉ với array)
 *   reason      : string              (chỉ với unknown – lý do chưa hỗ trợ)
 */
class SchemaTree
{
    public const CONSTRAINT_LABELS = [
        'minimum'   => 'giá trị ≥',
        'maximum'   => 'giá trị ≤',
        'minLength' => 'độ dài ≥',
        'maxLength' => 'độ dài ≤',
        'minItems'  => 'số phần tử ≥',
        'maxItems'  => 'số phần tử ≤',
        'pattern'   => 'mẫu',
    ];

    /** Đường dẫn con kiểu JSONPath: $.user.address.city, $["first name"] */
    public static function childPath(string $parent, string $name): string
    {
        if (preg_match('/^[A-Za-z_$][A-Za-z0-9_$-]*$/', $name)) {
            return $parent . '.' . $name;
        }
        return $parent . '[' . json_encode($name, JSON_UNESCAPED_UNICODE) . ']';
    }

    public static function typeLabel(?array $node): string
    {
        if (!$node || $node['type'] === 'none') return '(không có)';
        $t = $node['type'];
        if ($t === 'unknown') return 'chưa hỗ trợ';
        $label = $t === 'array' ? 'array<' . self::typeLabel($node['items'] ?? null) . '>' : $t;
        if (!empty($node['format'])) $label .= ' (' . $node['format'] . ')';
        if (!empty($node['nullable']) && !in_array($t, ['null', 'any'], true)) $label .= ' | null';
        return $label;
    }

    /** Chữ ký kiểu – dùng để đoán trường bị đổi tên. */
    public static function signature(array $node): string
    {
        $t = $node['type'];
        if ($t === 'object') {
            $keys = array_map('strval', array_keys($node['properties'] ?? []));
            sort($keys);
            return 'object{' . implode(',', $keys) . '}';
        }
        if ($t === 'array') return 'array<' . self::signature($node['items']) . '>';
        return $t;
    }

    /** Duyệt cây thành danh sách phẳng: [path, node, required]. */
    public static function flatten(?array $node): array
    {
        $out = [];
        if ($node) self::walk($node, '$', true, $out);
        return $out;
    }

    private static function walk(array $node, string $path, bool $required, array &$out): void
    {
        $out[] = ['path' => $path, 'node' => $node, 'required' => $required];
        if ($node['type'] === 'object') {
            foreach ($node['properties'] ?? [] as $name => $child) {
                $name = (string)$name;
                self::walk($child, self::childPath($path, $name), in_array($name, $node['required'] ?? [], true), $out);
            }
        }
        if ($node['type'] === 'array' && isset($node['items'])) {
            self::walk($node['items'], $path . '[]', true, $out);
        }
    }

    /** Số trường (không tính nút gốc). */
    public static function countFields(?array $node): int
    {
        return max(0, count(self::flatten($node)) - 1);
    }

    public static function constraintsText(array $node): string
    {
        $parts = [];
        foreach ($node['constraints'] ?? [] as $k => $v) {
            $parts[] = (self::CONSTRAINT_LABELS[$k] ?? $k) . ' ' . (is_string($v) ? $v : json_encode($v));
        }
        return implode(', ', $parts);
    }

    public static function enumText(array $values): string
    {
        return implode(', ', array_map(fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE), $values));
    }

    /** Xuất cây chuẩn hóa ra JSON Schema (draft 2020-12). */
    public static function toJsonSchema(array $node, bool $root = true): array
    {
        $s = $root ? ['$schema' => 'https://json-schema.org/draft/2020-12/schema'] : [];
        $t = $node['type'];
        if ($t === 'unknown') {
            return $s + ['description' => 'Cấu trúc chưa hỗ trợ (' . ($node['reason'] ?? '') . ')'];
        }
        if ($t !== 'any') {
            $s['type'] = !empty($node['nullable']) && $t !== 'null' ? [$t, 'null'] : $t;
        }
        if (!empty($node['description'])) $s['description'] = $node['description'];
        if (isset($node['enum'])) $s['enum'] = $node['enum'];
        if (!empty($node['format'])) $s['format'] = $node['format'];
        foreach ($node['constraints'] ?? [] as $k => $v) $s[$k] = $v;

        if ($t === 'object') {
            $props = new stdClass();
            foreach ($node['properties'] ?? [] as $name => $child) {
                $props->{(string)$name} = self::toJsonSchema($child, false);
            }
            $s['properties'] = $props;
            if (!empty($node['required'])) $s['required'] = array_values($node['required']);
            if (($node['additionalProperties'] ?? true) === false) $s['additionalProperties'] = false;
        }
        if ($t === 'array') {
            $s['items'] = self::toJsonSchema($node['items'], false);
        }
        return $s;
    }

    /* ------------------------------------------------------------------ */
    /* Hiển thị cây dạng HTML                                             */
    /* ------------------------------------------------------------------ */

    public static function renderTree(?array $node): string
    {
        if (!$node) return '<p class="muted">Không có mô tả cho phần này.</p>';
        return '<ul class="tree">' . self::renderNode('(gốc)', $node, null) . '</ul>';
    }

    public static function typeBadge(?array $node): string
    {
        $t = $node['type'] ?? 'none';
        return '<span class="type type-' . self::h($t) . '">' . self::h(self::typeLabel($node)) . '</span>';
    }

    /** @param bool|null $required null = không hiển thị (nút gốc / phần tử mảng) */
    private static function renderNode(string $name, array $node, ?bool $required): string
    {
        $head = '<span class="tree-name">' . self::h($name) . '</span> ' . self::typeBadge($node);
        if ($required === true) $head .= ' <span class="tag tag-req">bắt buộc</span>';
        if ($required === false) $head .= ' <span class="tag tag-opt">tùy chọn</span>';
        if ($node['type'] === 'unknown') {
            $head .= ' <span class="tag tag-unsupported">' . self::h($node['reason'] ?? '') . '</span>';
        }
        if (isset($node['enum'])) {
            $head .= ' <span class="tree-meta">enum: ' . self::h(self::enumText($node['enum'])) . '</span>';
        }
        if (!empty($node['constraints'])) {
            $head .= ' <span class="tree-meta">' . self::h(self::constraintsText($node)) . '</span>';
        }
        if (($node['additionalProperties'] ?? true) === false) {
            $head .= ' <span class="tree-meta">không nhận trường lạ</span>';
        }
        if (!empty($node['description'])) {
            $head .= ' <span class="tree-desc">— ' . self::h($node['description']) . '</span>';
        }

        $children = '';
        if ($node['type'] === 'object') {
            foreach ($node['properties'] ?? [] as $child => $sub) {
                $child = (string)$child;
                $children .= self::renderNode($child, $sub, in_array($child, $node['required'] ?? [], true));
            }
        }
        if ($node['type'] === 'array' && isset($node['items'])) {
            $children .= self::renderNode('[ phần tử ]', $node['items'], null);
        }

        if ($children === '') {
            return '<li><div class="tree-leaf">' . $head . '</div></li>';
        }
        return '<li><details open><summary>' . $head . '</summary><ul>' . $children . '</ul></details></li>';
    }

    private static function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
