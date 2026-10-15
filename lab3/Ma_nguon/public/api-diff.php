<?php
require __DIR__ . '/../src/Input.php';
require __DIR__ . '/../src/EventFilter.php';
require_once __DIR__ . '/../templates/header.php';

// Các bộ dữ liệu mẫu đặc tả API
$presets = [
    'user' => [
        'name' => 'User Profile API (V1.0 ➔ V2.0)',
        'v1' => [
            'title' => 'User API v1.0',
            'type' => 'object',
            'required' => ['id', 'username', 'email'],
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'Mã định danh duy nhất'],
                'username' => ['type' => 'string', 'description' => 'Tên đăng nhập hệ thống'],
                'email' => ['type' => 'string', 'description' => 'Địa chỉ hòm thư điện tử'],
                'age' => ['type' => 'integer', 'description' => 'Tuổi người dùng']
            ]
        ],
        'v2' => [
            'title' => 'User API v2.0',
            'type' => 'object',
            'required' => ['id', 'username', 'role'],
            'properties' => [
                'id' => ['type' => 'string', 'description' => 'UUID dạng chuỗi (thay vì integer)'],
                'username' => ['type' => 'string', 'description' => 'Tên đăng nhập hệ thống'],
                'role' => ['type' => 'string', 'description' => 'Vai trò phân quyền bắt buộc mới'],
                'bio' => ['type' => 'string', 'description' => 'Mô tả cá nhân tùy chọn']
            ]
        ]
    ],
    'order' => [
        'name' => 'Order Checkout API (V1.0 ➔ V1.1 - Tương thích ngược)',
        'v1' => [
            'title' => 'Order API v1.0',
            'type' => 'object',
            'required' => ['order_id', 'amount'],
            'properties' => [
                'order_id' => ['type' => 'string', 'description' => 'Mã đơn hàng'],
                'amount' => ['type' => 'number', 'description' => 'Tổng số tiền thanh toán']
            ]
        ],
        'v2' => [
            'title' => 'Order API v1.1',
            'type' => 'object',
            'required' => ['order_id', 'amount'],
            'properties' => [
                'order_id' => ['type' => 'string', 'description' => 'Mã đơn hàng'],
                'amount' => ['type' => 'number', 'description' => 'Tổng số tiền thanh toán'],
                'tracking_code' => ['type' => 'string', 'description' => 'Mã vận đơn bưu cục (tùy chọn)'],
                'note' => ['type' => 'string', 'description' => 'Ghi chú giao hàng (tùy chọn)']
            ]
        ]
    ],
    'product' => [
        'name' => 'Product Catalog API (V1.0 ➔ V2.0 - Phá vỡ cấu trúc)',
        'v1' => [
            'title' => 'Product API v1.0',
            'type' => 'object',
            'required' => ['sku', 'price', 'in_stock'],
            'properties' => [
                'sku' => ['type' => 'string', 'description' => 'Mã sản phẩm'],
                'price' => ['type' => 'number', 'description' => 'Giá bán niêm yết'],
                'in_stock' => ['type' => 'boolean', 'description' => 'Còn hàng trong kho']
            ]
        ],
        'v2' => [
            'title' => 'Product API v2.0',
            'type' => 'object',
            'required' => ['sku', 'currency'],
            'properties' => [
                'sku' => ['type' => 'integer', 'description' => 'Mã sản phẩm đổi sang ID số'],
                'currency' => ['type' => 'string', 'description' => 'Đơn vị tiền tệ bắt buộc mới']
            ]
        ]
    ]
];

$selectedPreset = field($_POST, 'preset', 'user');
if (!isset($presets[$selectedPreset])) {
    $selectedPreset = 'user';
}

$rawV1 = field($_POST, 'schema_v1', json_encode($presets[$selectedPreset]['v1'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$rawV2 = field($_POST, 'schema_v2', json_encode($presets[$selectedPreset]['v2'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$filterSeverity = field($_GET, 'severity', 'ALL');

// Thuật toán phân tích Diff & Tính tương thích API
$errorMsg = null;
$analysis = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['preset'])) {
    if (isset($_GET['preset']) && isset($presets[$_GET['preset']])) {
        $selectedPreset = $_GET['preset'];
        $rawV1 = json_encode($presets[$selectedPreset]['v1'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $rawV2 = json_encode($presets[$selectedPreset]['v2'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    $schema1 = json_decode($rawV1, true);
    $schema2 = json_decode($rawV2, true);

    if (!is_array($schema1) || !is_array($schema2)) {
        $errorMsg = 'Lỗi cú pháp: Một trong hai tài liệu JSON Schema không hợp lệ. Vui lòng kiểm tra lại cú pháp JSON.';
    } else {
        $changes = [];
        $props1 = $schema1['properties'] ?? [];
        $props2 = $schema2['properties'] ?? [];
        $req1 = $schema1['required'] ?? [];
        $req2 = $schema2['required'] ?? [];

        // 1. Kiểm tra trường bị xóa
        foreach ($props1 as $field => $def) {
            if (!array_key_exists($field, $props2)) {
                $changes[] = [
                    'field' => $field,
                    'severity' => 'BREAKING',
                    'action' => 'REMOVED',
                    'badge' => 'Xóa trường',
                    'detail' => "Trường '{$field}' tồn tại ở V1 nhưng đã bị xóa khỏi V2.",
                    'impact' => 'Client cũ gửi hoặc kỳ vọng trường này sẽ bị lỗi (Major Breaking Change).'
                ];
            }
        }

        // 2. Kiểm tra trường mới & trường thay đổi
        foreach ($props2 as $field => $def) {
            if (!array_key_exists($field, $props1)) {
                $isReq = in_array($field, $req2, true);
                if ($isReq) {
                    $changes[] = [
                        'field' => $field,
                        'severity' => 'BREAKING',
                        'action' => 'ADDED_REQUIRED',
                        'badge' => 'Thêm trường bắt buộc',
                        'detail' => "Trường '{$field}' mới được thêm vào và được đánh dấu là BẮT BUỘC (required).",
                        'impact' => 'Client cũ không gửi trường này khi gọi API sẽ bị từ chối 400 Bad Request.'
                    ];
                } else {
                    $changes[] = [
                        'field' => $field,
                        'severity' => 'NON_BREAKING',
                        'action' => 'ADDED_OPTIONAL',
                        'badge' => 'Thêm trường tùy chọn',
                        'detail' => "Trường '{$field}' mới được thêm vào ở dạng tùy chọn (optional).",
                        'impact' => 'Tương thích ngược hoàn toàn. Client cũ hoạt động bình thường.'
                    ];
                }
                continue;
            }

            // Đổi kiểu dữ liệu
            $t1 = $props1[$field]['type'] ?? 'any';
            $t2 = $def['type'] ?? 'any';
            if ($t1 !== $t2) {
                $changes[] = [
                    'field' => $field,
                    'severity' => 'BREAKING',
                    'action' => 'TYPE_CHANGED',
                    'badge' => 'Đổi kiểu dữ liệu',
                    'detail' => "Kiểu dữ liệu của '{$field}' thay đổi từ '{$t1}' sang '{$t2}'.",
                    'impact' => 'Parser của client cũ sẽ gặp lỗi kiểu dữ liệu (Type Mismatch).'
                ];
            }

            // Đổi tính chất required
            $wasReq = in_array($field, $req1, true);
            $nowReq = in_array($field, $req2, true);
            if (!$wasReq && $nowReq) {
                $changes[] = [
                    'field' => $field,
                    'severity' => 'BREAKING',
                    'action' => 'MADE_REQUIRED',
                    'badge' => 'Chuyển thành bắt buộc',
                    'detail' => "Trường '{$field}' từ tùy chọn trở thành bắt buộc.",
                    'impact' => 'Client cũ không truyền trường này sẽ gặp lỗi validation.'
                ];
            } elseif ($wasReq && !$nowReq) {
                $changes[] = [
                    'field' => $field,
                    'severity' => 'NON_BREAKING',
                    'action' => 'MADE_OPTIONAL',
                    'badge' => 'Chuyển thành tùy chọn',
                    'detail' => "Trường '{$field}' từ bắt buộc được nới lỏng thành tùy chọn.",
                    'impact' => 'Tương thích ngược an toàn.'
                ];
            }
        }

        $breakingCount = count(array_filter($changes, fn($c) => $c['severity'] === 'BREAKING'));
        $nonBreakingCount = count(array_filter($changes, fn($c) => $c['severity'] === 'NON_BREAKING'));

        $analysis = [
            'is_compatible' => ($breakingCount === 0),
            'breaking' => $breakingCount,
            'non_breaking' => $nonBreakingCount,
            'total' => count($changes),
            'changes' => $changes
        ];
    }
}
?>

<div class="page-heading">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1>⚡ Công cụ So Sánh & Kiểm Tra Tương Thích API</h1>
            <p>Đề tài số 2: Phát hiện thay đổi cấu trúc dữ liệu và đánh giá tính tương thích ngược giữa các phiên bản API.</p>
        </div>
        <div style="background: rgba(37, 99, 235, 0.1); border-left: 4px solid #2563eb; padding: 0.75rem 1.25rem; border-radius: 6px;">
            <strong>Nhóm 16 — Lớp CNTT-18-10</strong><br>
            <small>Nguyễn Hữu Tiến (NT) • Nguyễn Việt Anh • Nguyễn Tiến Thuận</small>
        </div>
    </div>
</div>

<!-- Chọn kịch bản mẫu nhanh -->
<div style="margin: 1.5rem 0; padding: 1.25rem; background: var(--surface-variant, #f1f5f9); border-radius: 10px; border: 1px solid var(--outline, #e2e8f0);">
    <strong style="display: block; margin-bottom: 0.75rem; font-size: 1.05rem;">Chọn kịch bản so sánh API mẫu:</strong>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <?php foreach ($presets as $key => $preset): ?>
            <a href="/api-diff.php?preset=<?= e($key) ?>" 
               style="display: inline-block; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; border: 1px solid #cbd5e1; background: <?= $selectedPreset === $key ? '#2563eb; color: #fff;' : '#fff; color: #1e293b;' ?> font-weight: 500;">
               <?= e($preset['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($errorMsg): ?>
    <div class="error-summary" role="alert" style="margin-bottom: 1.5rem; background: #fef2f2; border: 1px solid #f87171; color: #991b1b; padding: 1rem; border-radius: 8px;">
        <p><strong>Lỗi phân tích:</strong> <?= e($errorMsg) ?></p>
    </div>
<?php endif; ?>

<!-- Form nhập Schema V1 và V2 -->
<form method="post" style="margin-bottom: 2rem;">
    <input type="hidden" name="preset" value="<?= e($selectedPreset) ?>">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <div>
            <label for="schema_v1" style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #334155;">
                Phiên bản V1 (Gốc / Trước khi thay đổi):
            </label>
            <textarea id="schema_v1" name="schema_v1" rows="14" style="width: 100%; font-family: monospace; font-size: 0.9rem; padding: 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #f8fafc; resize: vertical;"><?= e($rawV1) ?></textarea>
        </div>
        <div>
            <label for="schema_v2" style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #334155;">
                Phiên bản V2 (Mới / Sau khi thay đổi):
            </label>
            <textarea id="schema_v2" name="schema_v2" rows="14" style="width: 100%; font-family: monospace; font-size: 0.9rem; padding: 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #f8fafc; resize: vertical;"><?= e($rawV2) ?></textarea>
        </div>
    </div>
    <div style="margin-top: 1rem; text-align: center;">
        <button type="submit" style="background: #2563eb; color: #fff; padding: 0.75rem 2.5rem; font-size: 1.05rem; font-weight: 600; border-radius: 8px; border: none; cursor: pointer; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);">
            🔍 Phân tích Thay Đổi & Kiểm Tra Tương Thích
        </button>
    </div>
</form>

<!-- Bảng kết quả phân tích -->
<?php if ($analysis !== null): ?>
    <section class="analysis-results" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; margin-top: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="margin: 0; font-size: 1.4rem;">Kết Quả Phân Tích Tính Tương Thích</h2>
                <p style="margin: 0.25rem 0 0 0; color: #64748b;">Tổng số thay đổi phát hiện: <strong><?= $analysis['total'] ?></strong></p>
            </div>
            <div>
                <?php if ($analysis['is_compatible']): ?>
                    <span style="display: inline-block; padding: 0.5rem 1.25rem; background: #dcfce7; color: #15803d; border-radius: 9999px; font-weight: 700; font-size: 1rem; border: 1px solid #86efac;">
                        ✓ TƯƠNG THÍCH NGƯỢC (Backward Compatible)
                    </span>
                <?php else: ?>
                    <span style="display: inline-block; padding: 0.5rem 1.25rem; background: #fee2e2; color: #b91c1c; border-radius: 9999px; font-weight: 700; font-size: 1rem; border: 1px solid #fca5a5;">
                        ✕ PHÁ VỠ TƯƠNG THÍCH (Breaking Changes: <?= $analysis['breaking'] ?>)
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; border-left: 4px solid #64748b;">
                <span style="color: #64748b; font-size: 0.85rem; text-transform: uppercase; font-weight: 600;">Tổng thay đổi</span>
                <p style="margin: 0.25rem 0 0 0; font-size: 1.5rem; font-weight: 700;"><?= $analysis['total'] ?></p>
            </div>
            <div style="background: #fef2f2; padding: 1rem; border-radius: 8px; border-left: 4px solid #ef4444;">
                <span style="color: #dc2626; font-size: 0.85rem; text-transform: uppercase; font-weight: 600;">Breaking Changes</span>
                <p style="margin: 0.25rem 0 0 0; font-size: 1.5rem; font-weight: 700; color: #b91c1c;"><?= $analysis['breaking'] ?></p>
            </div>
            <div style="background: #f0fdf4; padding: 1rem; border-radius: 8px; border-left: 4px solid #22c55e;">
                <span style="color: #16a34a; font-size: 0.85rem; text-transform: uppercase; font-weight: 600;">Non-breaking Changes</span>
                <p style="margin: 0.25rem 0 0 0; font-size: 1.5rem; font-weight: 700; color: #15803d;"><?= $analysis['non_breaking'] ?></p>
            </div>
        </div>

        <h3>Chi tiết từng thay đổi trong cấu trúc Schema:</h3>
        <?php if ($analysis['total'] === 0): ?>
            <p style="padding: 1.5rem; text-align: center; color: #64748b; background: #f8fafc; border-radius: 8px;">Hai phiên bản API có cấu trúc hoàn toàn đồng nhất. Không có thay đổi nào.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <?php foreach ($analysis['changes'] as $change): ?>
                    <?php 
                        $isBreaking = ($change['severity'] === 'BREAKING');
                        $bgColor = $isBreaking ? '#fff5f5' : '#f0fdf4';
                        $borderColor = $isBreaking ? '#fca5a5' : '#bbf7d0';
                        $badgeColor = $isBreaking ? '#ef4444' : '#16a34a';
                    ?>
                    <div style="padding: 1rem; border-radius: 8px; background: <?= $bgColor ?>; border: 1px solid <?= $borderColor ?>; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 4px; color: #fff; background: <?= $badgeColor ?>;">
                                    <?= e($change['severity']) ?>
                                </span>
                                <strong style="font-size: 1.05rem; font-family: monospace; color: #0f172a;"><?= e($change['field']) ?></strong>
                                <span style="font-size: 0.85rem; color: #64748b; background: rgba(0,0,0,0.05); padding: 0.15rem 0.4rem; border-radius: 4px;"><?= e($change['badge']) ?></span>
                            </div>
                            <p style="margin: 0.25rem 0; color: #334155; font-size: 0.95rem;"><?= e($change['detail']) ?></p>
                            <p style="margin: 0.25rem 0 0 0; color: #64748b; font-size: 0.85rem; font-style: italic;">
                                <strong>Tác động:</strong> <?= e($change['impact']) ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
