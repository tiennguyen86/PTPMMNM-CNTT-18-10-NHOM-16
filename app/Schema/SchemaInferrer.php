<?php
/**
 * Suy ra cây cấu trúc từ một JSON mẫu (ví dụ: phản hồi thật của API).
 * Quy ước: mọi trường xuất hiện đều là bắt buộc; với mảng object, trường chỉ bắt buộc
 * khi xuất hiện ở mọi phần tử.
 */
class SchemaInferrer
{
    /**
     * @return array{ok: bool, tree: ?array, issues: array, error: ?string}
     */
    public function infer(string $json): array
    {
        if (trim($json) === '') {
            return ['ok' => false, 'tree' => null, 'issues' => [], 'error' => 'Nội dung trống.'];
        }
        try {
            $data = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return ['ok' => false, 'tree' => null, 'issues' => [], 'error' => json_error_vi($e)];
        }

        $tree = $this->node($data);
        $issues = [[
            'level' => 'info', 'path' => '$',
            'message' => 'Cấu trúc được suy ra từ dữ liệu mẫu: mọi trường xuất hiện đều được coi là bắt buộc. Hãy rà soát lại trước khi lưu.',
        ]];
        $this->finalize($tree, '$', $issues);
        return ['ok' => true, 'tree' => $tree, 'issues' => $issues, 'error' => null];
    }

    private function node($v): array
    {
        if ($v instanceof stdClass) {
            $props = [];
            foreach (get_object_vars($v) as $k => $child) {
                $props[(string)$k] = $this->node($child);
            }
            return ['type' => 'object', 'nullable' => false, 'properties' => $props, 'required' => array_map('strval', array_keys($props))];
        }
        if (is_array($v)) {
            $items = null;
            foreach ($v as $el) {
                $n = $this->node($el);
                $items = $items === null ? $n : $this->merge($items, $n);
            }
            return ['type' => 'array', 'nullable' => false, 'items' => $items ?? ['type' => 'any', 'nullable' => true, '_empty' => true]];
        }
        return match (true) {
            is_string($v) => ['type' => 'string', 'nullable' => false],
            is_int($v)    => ['type' => 'integer', 'nullable' => false],
            is_float($v)  => ['type' => 'number', 'nullable' => false],
            is_bool($v)   => ['type' => 'boolean', 'nullable' => false],
            default       => ['type' => 'any', 'nullable' => true, '_null' => true],
        };
    }

    /** Gộp hai cấu trúc suy ra từ hai phần tử của cùng một mảng. */
    private function merge(array $a, array $b): array
    {
        if ($a['type'] === 'any' && !empty($a['_empty'])) return $b;
        if ($b['type'] === 'any' && !empty($b['_empty'])) return $a;
        if ($a['type'] === 'any' && !empty($a['_null']) && $b['type'] !== 'any') return ['nullable' => true] + $b;
        if ($b['type'] === 'any' && !empty($b['_null']) && $a['type'] !== 'any') return ['nullable' => true] + $a;

        $nullable = !empty($a['nullable']) || !empty($b['nullable']);
        if ($a['type'] !== $b['type']) {
            $numeric = ['integer', 'number'];
            if (in_array($a['type'], $numeric, true) && in_array($b['type'], $numeric, true)) {
                return ['type' => 'number', 'nullable' => $nullable];
            }
            return ['type' => 'any', 'nullable' => true, '_mixed' => true];
        }

        $out = $a;
        $out['nullable'] = $nullable;
        if ($a['type'] === 'object') {
            $props = $a['properties'];
            foreach ($b['properties'] as $k => $n) {
                $props[$k] = isset($props[$k]) ? $this->merge($props[$k], $n) : $n;
            }
            $out['properties'] = $props;
            $out['required'] = array_values(array_intersect($a['required'], $b['required']));
        }
        if ($a['type'] === 'array') {
            $out['items'] = $this->merge($a['items'], $b['items']);
        }
        return $out;
    }

    /** Sinh cảnh báo cho các chỗ không suy ra được kiểu và xóa cờ nội bộ. */
    private function finalize(array &$node, string $path, array &$issues): void
    {
        if ($node['type'] === 'any') {
            $msg = match (true) {
                !empty($node['_mixed']) => 'Các phần tử có kiểu không đồng nhất – không suy ra được kiểu chung.',
                !empty($node['_empty']) => 'Mảng rỗng – không suy ra được kiểu phần tử.',
                default                 => 'Giá trị null – không suy ra được kiểu, chấp nhận mọi giá trị.',
            };
            $issues[] = ['level' => 'warning', 'path' => $path, 'message' => $msg];
        }
        unset($node['_mixed'], $node['_empty'], $node['_null']);

        if ($node['type'] === 'object') {
            foreach ($node['properties'] as $k => &$child) {
                $this->finalize($child, SchemaTree::childPath($path, (string)$k), $issues);
            }
            unset($child);
        }
        if ($node['type'] === 'array') {
            $this->finalize($node['items'], $path . '[]', $issues);
        }
    }
}
