<?php
/**
 * So sánh hai cây cấu trúc và phân loại từng thay đổi theo bộ quy tắc đã công bố.
 *
 * Cùng một thay đổi có thể ảnh hưởng khác nhau tùy chiều dữ liệu:
 *  - request  (ứng dụng gửi lên → máy chủ đọc): thêm trường bắt buộc làm ứng dụng cũ bị từ chối.
 *  - response (máy chủ trả về → ứng dụng đọc): xóa/đổi tên trường làm ứng dụng cũ đọc sai.
 * Vì vậy mỗi quy tắc có hai mức độ: severity_request và severity_response.
 */
class SchemaDiff
{
    public const SEVERITY_ORDER = ['breaking' => 0, 'warning' => 1, 'safe' => 2];

    private array $rules;
    private array $changes = [];
    private string $part = 'response';

    /** @param array $rules [code => rule row] */
    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    /**
     * @param string $part 'request' | 'response'
     * @return array danh sách thay đổi
     */
    public function compare(?array $old, ?array $new, string $part): array
    {
        $this->changes = [];
        $this->part = $part;
        if (!$old && !$new) return [];

        $old ??= $this->placeholderFor($new);
        $new ??= $this->placeholderFor($old);
        $this->node($old, $new, '$');
        return $this->changes;
    }

    /** Khi một phiên bản không có phần này: coi như object rỗng (nếu bên kia là object). */
    private function placeholderFor(array $other): array
    {
        return $other['type'] === 'object'
            ? ['type' => 'object', 'nullable' => false, 'properties' => [], 'required' => []]
            : ['type' => 'none', 'nullable' => false];
    }

    private function add(string $code, string $path, string $message, ?string $old = null, ?string $new = null): void
    {
        $rule = $this->rules[$code] ?? ['name' => $code, 'severity_request' => 'warning', 'severity_response' => 'warning', 'advice' => ''];
        $this->changes[] = [
            'part'     => $this->part,
            'code'     => $code,
            'rule'     => $rule['name'],
            'severity' => $this->part === 'request' ? $rule['severity_request'] : $rule['severity_response'],
            'path'     => $path,
            'message'  => $message,
            'old'      => $old,
            'new'      => $new,
            'advice'   => $rule['advice'] ?? '',
        ];
    }

    private function node(array $o, array $n, string $path): void
    {
        if ($o['type'] === 'unknown' || $n['type'] === 'unknown') {
            $this->add('UNSUPPORTED_SKIPPED', $path, 'Không so sánh được nhánh này vì có cấu trúc chưa hỗ trợ – cần kiểm tra thủ công.',
                SchemaTree::typeLabel($o), SchemaTree::typeLabel($n));
            return;
        }

        // Kiểu dữ liệu
        if ($o['type'] !== $n['type']) {
            $code = match (true) {
                $o['type'] === 'integer' && $n['type'] === 'number' => 'TYPE_WIDENED',
                $o['type'] === 'number' && $n['type'] === 'integer' => 'TYPE_NARROWED',
                default => 'TYPE_CHANGED',
            };
            $this->add($code, $path, sprintf('Kiểu dữ liệu đổi từ %s sang %s.', SchemaTree::typeLabel($o), SchemaTree::typeLabel($n)),
                SchemaTree::typeLabel($o), SchemaTree::typeLabel($n));
            return; // các thay đổi bên trong không còn ý nghĩa
        }

        // null
        $on = !empty($o['nullable']);
        $nn = !empty($n['nullable']);
        if (!$on && $nn) $this->add('BECAME_NULLABLE', $path, 'Trường được phép nhận giá trị null.', 'không null', 'có thể null');
        if ($on && !$nn) $this->add('BECAME_NOT_NULLABLE', $path, 'Trường không còn được phép nhận giá trị null.', 'có thể null', 'không null');

        // Mô tả
        if (($o['description'] ?? '') !== ($n['description'] ?? '')) {
            $this->add('DESCRIPTION_CHANGED', $path, 'Chỉ thay đổi phần mô tả của trường.', $o['description'] ?? '', $n['description'] ?? '');
        }

        $this->compareEnum($o, $n, $path);

        // Định dạng
        if (($o['format'] ?? '') !== ($n['format'] ?? '')) {
            $this->add('FORMAT_CHANGED', $path, sprintf('Định dạng đổi từ "%s" sang "%s".', $o['format'] ?? 'không có', $n['format'] ?? 'không có'),
                $o['format'] ?? '—', $n['format'] ?? '—');
        }

        $this->compareConstraints($o, $n, $path);

        if ($o['type'] === 'object') $this->compareProperties($o, $n, $path);
        if ($o['type'] === 'array') $this->node($o['items'], $n['items'], $path . '[]');
    }

    private function compareEnum(array $o, array $n, string $path): void
    {
        $oe = $o['enum'] ?? null;
        $ne = $n['enum'] ?? null;
        if ($oe === null && $ne === null) return;
        if ($oe === null) {
            $this->add('CONSTRAINT_TIGHTENED', $path, 'Bắt đầu giới hạn giá trị trong danh sách: ' . SchemaTree::enumText($ne) . '.', 'mọi giá trị', SchemaTree::enumText($ne));
            return;
        }
        if ($ne === null) {
            $this->add('CONSTRAINT_LOOSENED', $path, 'Bỏ giới hạn danh sách giá trị (enum).', SchemaTree::enumText($oe), 'mọi giá trị');
            return;
        }
        $key = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE);
        $oldSet = array_map($key, $oe);
        $newSet = array_map($key, $ne);
        $added = array_values(array_diff($newSet, $oldSet));
        $removed = array_values(array_diff($oldSet, $newSet));
        if ($added) {
            $this->add('ENUM_VALUE_ADDED', $path, 'Thêm giá trị được phép: ' . implode(', ', $added) . '.', SchemaTree::enumText($oe), SchemaTree::enumText($ne));
        }
        if ($removed) {
            $this->add('ENUM_VALUE_REMOVED', $path, 'Bỏ giá trị được phép: ' . implode(', ', $removed) . '.', SchemaTree::enumText($oe), SchemaTree::enumText($ne));
        }
    }

    private function compareConstraints(array $o, array $n, string $path): void
    {
        $oc = $o['constraints'] ?? [];
        $nc = $n['constraints'] ?? [];
        foreach (SchemaParser::CONSTRAINTS as $k) {
            $ov = $oc[$k] ?? null;
            $nv = $nc[$k] ?? null;
            if ($ov === $nv) continue;

            if ($nv === null) {
                $tight = false;
            } elseif ($ov === null || $k === 'pattern') {
                $tight = true;
            } elseif (str_starts_with($k, 'min')) {
                $tight = $nv > $ov;
            } else {
                $tight = $nv < $ov;
            }
            $label = SchemaTree::CONSTRAINT_LABELS[$k] ?? $k;
            $fmt = fn($v) => $v === null ? 'không có' : (is_string($v) ? $v : json_encode($v));
            $this->add($tight ? 'CONSTRAINT_TIGHTENED' : 'CONSTRAINT_LOOSENED', $path,
                sprintf('Ràng buộc %s (%s): %s → %s.', $k, $label, $fmt($ov), $fmt($nv)), $fmt($ov), $fmt($nv));
        }
    }

    private function compareProperties(array $o, array $n, string $path): void
    {
        $op = $o['properties'] ?? [];
        $np = $n['properties'] ?? [];
        $oreq = $o['required'] ?? [];
        $nreq = $n['required'] ?? [];

        $removed = [];
        foreach ($op as $k => $v) if (!array_key_exists($k, $np)) $removed[(string)$k] = $v;
        $added = [];
        foreach ($np as $k => $v) if (!array_key_exists($k, $op)) $added[(string)$k] = $v;

        // Đoán đổi tên
        foreach ($this->detectRenames($removed, $added) as $from => $to) {
            unset($removed[$from], $added[$to]);
            $this->add('FIELD_RENAMED', SchemaTree::childPath($path, $from),
                sprintf('Trường "%s" có thể đã được đổi tên thành "%s" (cùng kiểu %s).', $from, $to, SchemaTree::typeLabel($np[$to])), $from, $to);
            $this->node($op[$from], $np[$to], SchemaTree::childPath($path, $to));
        }

        foreach ($removed as $name => $node) {
            $name = (string)$name;
            $wasReq = in_array($name, $oreq, true);
            $this->add('FIELD_REMOVED', SchemaTree::childPath($path, $name),
                sprintf('Trường "%s" (%s, %s) bị xóa.', $name, SchemaTree::typeLabel($node), $wasReq ? 'bắt buộc' : 'tùy chọn'),
                SchemaTree::typeLabel($node), null);
        }

        foreach ($added as $name => $node) {
            $name = (string)$name;
            $isReq = in_array($name, $nreq, true);
            $this->add($isReq ? 'REQUIRED_FIELD_ADDED' : 'FIELD_ADDED', SchemaTree::childPath($path, $name),
                sprintf('Thêm trường "%s" (%s, %s).', $name, SchemaTree::typeLabel($node), $isReq ? 'bắt buộc' : 'tùy chọn'),
                null, SchemaTree::typeLabel($node));
        }

        foreach ($op as $name => $oldChild) {
            $name = (string)$name;
            if (!array_key_exists($name, $np)) continue;
            $childPath = SchemaTree::childPath($path, $name);
            $wasReq = in_array($name, $oreq, true);
            $isReq = in_array($name, $nreq, true);
            if ($wasReq && !$isReq) $this->add('FIELD_BECAME_OPTIONAL', $childPath, 'Trường chuyển từ bắt buộc sang tùy chọn.', 'bắt buộc', 'tùy chọn');
            if (!$wasReq && $isReq) $this->add('FIELD_BECAME_REQUIRED', $childPath, 'Trường chuyển từ tùy chọn sang bắt buộc.', 'tùy chọn', 'bắt buộc');
            $this->node($oldChild, $np[$name], $childPath);
        }
    }

    /**
     * Ghép cặp trường bị xóa – trường mới thêm ở cùng cấp, cùng kiểu và tên gần giống nhau.
     * @return array [tên cũ => tên mới]
     */
    private function detectRenames(array $removed, array $added): array
    {
        if (!$removed || !$added) return [];
        $single = count($removed) === 1 && count($added) === 1;
        $candidates = [];
        foreach ($removed as $r => $rn) {
            foreach ($added as $a => $an) {
                if (SchemaTree::signature($rn) !== SchemaTree::signature($an)) continue;
                $score = self::nameSimilarity((string)$r, (string)$a);
                if ($score >= 0.5 || ($single && $score >= 0.25)) {
                    $candidates[] = [$score, (string)$r, (string)$a];
                }
            }
        }
        usort($candidates, fn($x, $y) => $y[0] <=> $x[0]);

        $pairs = [];
        foreach ($candidates as [, $r, $a]) {
            if (isset($pairs[$r]) || in_array($a, $pairs, true)) continue;
            $pairs[$r] = $a;
        }
        return $pairs;
    }

    public static function nameSimilarity(string $a, string $b): float
    {
        $norm = fn($s) => strtolower(preg_replace('/[^a-z0-9]/i', '', $s));
        $a = $norm($a);
        $b = $norm($b);
        if ($a === '' || $b === '') return 0.0;
        if ($a === $b) return 1.0;
        $short = strlen($a) < strlen($b) ? $a : $b;
        $long = $short === $a ? $b : $a;
        if (strlen($short) >= 3 && str_contains($long, $short)) return 0.8;
        similar_text($a, $b, $percent);
        return $percent / 100;
    }

    /** Tổng hợp kết quả: số lượng theo mức độ, kết luận và gợi ý tăng phiên bản. */
    public static function summarize(array $changes): array
    {
        $counts = ['breaking' => 0, 'warning' => 0, 'safe' => 0];
        foreach ($changes as $c) $counts[$c['severity']] = ($counts[$c['severity']] ?? 0) + 1;

        $additive = (bool)array_filter($changes, fn($c) => in_array($c['code'], ['FIELD_ADDED', 'REQUIRED_FIELD_ADDED', 'ENUM_VALUE_ADDED', 'CONSTRAINT_LOOSENED'], true));
        if ($counts['breaking'] > 0) {
            [$verdict, $bump] = ['breaking', 'major'];
        } elseif ($counts['warning'] > 0) {
            [$verdict, $bump] = ['warning', 'minor'];
        } elseif ($changes) {
            [$verdict, $bump] = ['safe', $additive ? 'minor' : 'patch'];
        } else {
            [$verdict, $bump] = ['none', 'none'];
        }
        return ['counts' => $counts, 'total' => count($changes), 'verdict' => $verdict, 'bump' => $bump];
    }

    public static function sort(array $changes): array
    {
        usort($changes, fn($a, $b) => (self::SEVERITY_ORDER[$a['severity']] ?? 9) <=> (self::SEVERITY_ORDER[$b['severity']] ?? 9));
        return $changes;
    }
}
