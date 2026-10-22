<?php
defined('APP') or exit;

$user = require_login();

if (is_post()) {
    require_admin();
    $code = post('code');
    $rule = row('SELECT * FROM compat_rules WHERE code = ?', [$code]);
    if (!$rule) abort(404, 'Quy tắc không tồn tại.');
    $req = post('severity_request');
    $res = post('severity_response');
    if (!isset(SEVERITIES[$req], SEVERITIES[$res])) {
        flash('error', 'Mức độ không hợp lệ.');
    } else {
        update('compat_rules', [
            'severity_request' => $req, 'severity_response' => $res,
            'advice' => post('advice'), 'updated_by' => $user['id'], 'updated_at' => now(),
        ], 'code = ?', [$code]);
        flash('success', 'Đã cập nhật quy tắc ' . $rule['name'] . '. Các lần so sánh sau sẽ dùng mức độ mới.');
    }
    redirect('rules');
}

$rules = rows('SELECT r.*, u.full_name AS editor FROM compat_rules r LEFT JOIN users u ON u.id = r.updated_by ORDER BY r.sort_order, r.id');
$lastUpdate = val('SELECT MAX(updated_at) FROM compat_rules');
$editing = is_admin() ? get_str('edit') : '';

$keyword = get_str('q');
$severity = get_str('severity');
$direction = get_str('direction', 'either');
if (!isset(SEVERITIES[$severity])) $severity = '';
if (!in_array($direction, ['either', 'request', 'response'], true)) $direction = 'either';
$counts = array_fill_keys(array_keys(SEVERITIES), 0);
foreach ($rules as $rule) {
    foreach ($counts as $level => $_) {
        if ($rule['severity_request'] === $level || $rule['severity_response'] === $level) $counts[$level]++;
    }
}
$filtered = array_filter($rules, function (array $rule) use ($keyword, $severity, $direction): bool {
    $haystack = implode(' ', [$rule['code'], $rule['name'], $rule['description'], $rule['advice']]);
    if ($keyword !== '' && mb_stripos($haystack, $keyword) === false) return false;
    if ($severity === '') return true;
    if ($direction !== 'either') return $rule['severity_' . $direction] === $severity;
    return $rule['severity_request'] === $severity || $rule['severity_response'] === $severity;
});
$levelInfo = [
    'breaking' => ['Cần cập nhật ứng dụng sử dụng API hoặc có phương án chuyển tiếp.', 'MAJOR', 'v1.2.0 → v2.0.0'],
    'warning' => ['Cần rà soát tác động và kiểm tra lại trước khi phát hành.', 'MINOR', 'v1.2.0 → v1.3.0'],
    'safe' => ['Thay đổi được đánh giá tương thích theo bộ quy tắc hiện tại.', 'MINOR / PATCH', 'Tuỳ loại thay đổi'],
];

layout_start('Bộ quy tắc', 'rules');
?>
<div class="rules-page">
    <header class="rules-heading">
        <div><div class="eyebrow">TIÊU CHUẨN ĐÁNH GIÁ API</div><h1>Bộ quy tắc tương thích</h1><p>Hiểu tác động của mỗi thay đổi trước khi chuyển sang phiên bản mới.</p></div>
        <a class="btn" href="#rules-method"><?= icon('book') ?>Cách đánh giá</a>
    </header>

    <div class="rules-meta">
        <span><?= icon('shield') ?>Áp dụng cho mọi lần so sánh</span>
        <span><?= icon('layers') ?><?= count($rules) ?> quy tắc</span>
        <?php if ($lastUpdate): ?><span>Cập nhật <?= e(fmt_date($lastUpdate)) ?></span><?php endif; ?>
        <span class="rules-access"><?= is_admin() ? 'Bạn có quyền chỉnh sửa' : 'Chế độ chỉ xem' ?></span>
    </div>

    <div class="rules-levels">
        <?php foreach ($levelInfo as $level => [$description, $bump, $example]): ?>
        <section class="rules-level rules-level-<?= e($level) ?>">
            <div class="rules-level-top"><?= severity_badge($level) ?><span class="rules-level-count"><?= $counts[$level] ?><small>quy tắc</small></span></div>
            <p><?= e($description) ?></p>
            <div class="rules-level-foot"><span>Gợi ý phiên bản</span><strong><?= e($bump) ?></strong></div>
        </section>
        <?php endforeach; ?>
    </div>
    <p class="rules-count-note">Một quy tắc có thể có mức độ khác nhau giữa request và response, nên có thể thuộc nhiều nhóm.</p>

    <section class="rules-catalog card" aria-labelledby="rules-catalog-title">
        <div class="rules-catalog-heading">
            <div><h2 id="rules-catalog-title">Danh mục quy tắc <span class="count"><?= count($rules) ?></span></h2><p>Chọn một quy tắc để xem mô tả và khuyến nghị xử lý.</p></div>
            <button class="btn btn-sm btn-ghost rules-expand" type="button" data-rules-expand aria-expanded="false" aria-controls="rules-list">Mở tất cả <?= icon('plus') ?></button>
        </div>
        <form class="rules-filters" method="get" role="search" aria-label="Lọc quy tắc">
            <input type="hidden" name="page" value="rules">
            <label class="rules-search"><?= icon('search') ?><input class="input" name="q" value="<?= e($keyword) ?>" placeholder="Tìm tên, mã hoặc nội dung quy tắc…" aria-label="Tìm quy tắc"></label>
            <label class="field"><span>Mức ảnh hưởng</span><select class="input" name="severity"><option value="">Tất cả mức độ</option><?php foreach (SEVERITIES as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($key, $severity) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Chiều dữ liệu</span><select class="input" name="direction"><?php foreach (['either' => 'Request & response', 'request' => 'Chỉ request', 'response' => 'Chỉ response'] as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($key, $direction) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <button class="btn" type="submit">Lọc</button>
        </form>
        <div class="rules-result-count"><span>Hiển thị <strong><?= count($filtered) ?></strong> / <?= count($rules) ?> quy tắc</span><?php if ($keyword !== '' || $severity !== '' || $direction !== 'either'): ?><a href="<?= e(url('rules')) ?>">Xóa bộ lọc</a><?php else: ?><span>Mức độ được đánh giá riêng theo từng chiều dữ liệu</span><?php endif; ?></div>
        <div class="rules-list-head" aria-hidden="true"><span>QUY TẮC THAY ĐỔI</span><span>REQUEST</span><span>RESPONSE</span><span></span></div>
        <div id="rules-list">
        <?php foreach ($filtered as $index => $r): $isEditing = $editing === $r['code']; ?>
            <details class="rule-entry<?= $isEditing ? ' rule-entry-editing' : '' ?>" id="rule-<?= e($r['code']) ?>"<?= $isEditing ? ' open' : '' ?>>
                <summary class="rule-summary">
                    <span class="rule-identity"><span class="rule-index"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></span><span class="rule-title"><strong><?= e($r['name']) ?></strong><code><?= e($r['code']) ?></code></span></span>
                    <span class="rule-severity"><span class="rule-mobile-label">Request</span><?= severity_badge($r['severity_request']) ?></span>
                    <span class="rule-severity"><span class="rule-mobile-label">Response</span><?= severity_badge($r['severity_response']) ?></span>
                    <span class="rule-chevron" aria-hidden="true">⌄</span>
                </summary>
                <div class="rule-detail">
                    <?php if ($isEditing): ?>
                    <form method="post" class="rule-editor">
                        <?= csrf_field() ?><input type="hidden" name="code" value="<?= e($r['code']) ?>">
                        <div class="rule-editor-heading"><span><?= icon('edit') ?>Chỉnh sửa quy tắc</span><span>Thay đổi áp dụng cho các lần so sánh tiếp theo.</span></div>
                        <div class="rule-editor-levels">
                            <label class="field"><span>Mức độ request</span><select class="input" name="severity_request"><?php foreach (SEVERITIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $r['severity_request']) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                            <label class="field"><span>Mức độ response</span><select class="input" name="severity_response"><?php foreach (SEVERITIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $r['severity_response']) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                        </div>
                        <label class="field"><span>Khuyến nghị xử lý</span><textarea class="input" name="advice" rows="4"><?= e($r['advice']) ?></textarea></label>
                        <div class="rule-editor-actions"><a class="btn btn-ghost" href="<?= e(url('rules')) ?>#rule-<?= e($r['code']) ?>">Hủy thay đổi</a><button class="btn btn-primary" type="submit"><?= icon('check') ?>Lưu quy tắc</button></div>
                    </form>
                    <?php else: ?>
                    <div class="rule-detail-grid"><div><h3>Mô tả thay đổi</h3><p><?= e($r['description']) ?></p></div><div class="rule-advice"><h3><?= icon('shield') ?>Khuyến nghị xử lý</h3><p><?= e($r['advice'] ?: 'Chưa có khuyến nghị cho quy tắc này.') ?></p></div></div>
                    <div class="rule-detail-foot"><span><?php if ($r['editor']): ?>Cập nhật bởi <?= e($r['editor']) ?> · <?= e(fmt_date($r['updated_at'])) ?><?php else: ?>Quy tắc được công bố trong hệ thống<?php endif; ?></span><?php if (is_admin()): ?><a class="btn btn-sm" href="<?= e(url('rules', ['edit' => $r['code']])) ?>#rule-<?= e($r['code']) ?>"><?= icon('edit') ?>Chỉnh sửa quy tắc</a><?php endif; ?></div>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
        <?php if (!$filtered): ?><div class="rules-empty"><?= icon('search') ?><h3>Không tìm thấy quy tắc phù hợp</h3><p>Thử từ khóa khác hoặc bỏ bớt điều kiện lọc.</p><a class="btn" href="<?= e(url('rules')) ?>">Xem tất cả quy tắc</a></div><?php endif; ?>
        </div>
    </section>

    <section class="rules-explainer" aria-labelledby="rules-direction-title"><span class="rules-explainer-icon"><?= icon('diff') ?></span><div><h2 id="rules-direction-title">Cùng một thay đổi. Hai chiều ảnh hưởng.</h2><p>Thêm trường bắt buộc vào <strong>request</strong> có thể khiến ứng dụng cũ bị từ chối vì thiếu dữ liệu. Thêm trường đó vào <strong>response</strong> thường vẫn tương thích vì ứng dụng cũ có thể bỏ qua trường mới.</p></div></section>

    <section class="rules-method card" id="rules-method" aria-labelledby="rules-method-title">
        <div class="card-head"><div><h2 id="rules-method-title">Từ thay đổi đến kết luận</h2><p class="muted small">Mức ảnh hưởng cao nhất quyết định kết quả so sánh.</p></div><?= icon('layers') ?></div>
        <div class="rules-method-grid">
            <div><span class="rules-method-step">01 / PHÁ VỠ TƯƠNG THÍCH</span><h3>Có ít nhất một thay đổi phá vỡ</h3><p>Kết luận không tương thích ngược. Gợi ý tăng phiên bản chính.</p><code>v1.2.0 → v2.0.0</code><strong>MAJOR</strong></div>
            <div><span class="rules-method-step">02 / CẦN LƯU Ý</span><h3>Không phá vỡ, nhưng cần rà soát</h3><p>Có thay đổi cần lưu ý. Gợi ý tăng phiên bản phụ và kiểm tra ứng dụng sử dụng API.</p><code>v1.2.0 → v1.3.0</code><strong>MINOR</strong></div>
            <div><span class="rules-method-step">03 / TƯƠNG THÍCH</span><h3>Chỉ có thay đổi tương thích</h3><p>Gợi ý MINOR cho thay đổi bổ sung hoặc nới ràng buộc; PATCH cho các thay đổi tương thích còn lại.</p><code>MINOR / PATCH</code></div>
        </div>
        <div class="rules-method-note"><?= icon('book') ?><p>Không có thay đổi thì không gợi ý tăng phiên bản. Đổi tên trường được suy đoán theo độ giống tên và kiểu dữ liệu; các nhánh được đánh dấu chưa hỗ trợ cần kiểm tra thủ công.</p></div>
    </section>
</div>
<?php layout_end();
