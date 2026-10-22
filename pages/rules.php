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

layout_start('Bộ quy tắc', 'rules');
page_head('Bộ quy tắc tương thích', [], '',
    'Bộ quy tắc được công bố cho mọi thành viên. Công cụ so sánh dùng bộ quy tắc này để xác định mức ảnh hưởng của từng thay đổi tới ứng dụng đang dùng API.'
    . ($lastUpdate ? ' Cập nhật lần cuối: <strong>' . e(fmt_date($lastUpdate)) . '</strong>.' : ''));
?>
<div class="grid-3 gap-lg legend">
    <div class="legend-item sev-box-breaking"><?= severity_badge('breaking') ?><p>Ứng dụng đang dùng API <strong>sẽ gặp lỗi</strong>. Cần tăng phiên bản chính (MAJOR) hoặc giữ tương thích trong giai đoạn chuyển tiếp.</p></div>
    <div class="legend-item sev-box-warning"><?= severity_badge('warning') ?><p>Có thể ảnh hưởng tới một số ứng dụng. Cần thông báo cho các nhóm sử dụng API và kiểm tra lại (MINOR).</p></div>
    <div class="legend-item sev-box-safe"><?= severity_badge('safe') ?><p>Không ảnh hưởng tới ứng dụng đang sử dụng (MINOR nếu thêm mới, PATCH nếu chỉ sửa mô tả).</p></div>
</div>

<div class="alert alert-info">
    <strong>Vì sao có hai cột mức độ?</strong> Cùng một thay đổi ảnh hưởng khác nhau tùy chiều dữ liệu.
    Ví dụ <em>thêm trường bắt buộc</em> vào <strong>request</strong> khiến ứng dụng cũ bị từ chối vì không gửi trường đó ("yêu cầu thêm thông tin"),
    nhưng thêm vào <strong>response</strong> thì ứng dụng cũ chỉ đơn giản bỏ qua. Ngược lại, <em>xóa trường</em> khỏi <strong>response</strong> làm ứng dụng cũ đọc sai dữ liệu.
</div>

<section class="card">
    <table class="table rules-table">
        <thead><tr><th>Mã</th><th>Quy tắc</th><th style="width:150px">Request</th><th style="width:150px">Response</th><th>Ảnh hưởng & khuyến nghị</th><?php if (is_admin()): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($rules as $r): ?>
            <?php if ($editing === $r['code']): ?>
                <tr class="editing">
                    <td colspan="<?= is_admin() ? 6 : 5 ?>">
                        <form method="post" class="rule-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="code" value="<?= e($r['code']) ?>">
                            <div><code><?= e($r['code']) ?></code><strong><?= e($r['name']) ?></strong><p class="muted small"><?= e($r['description']) ?></p></div>
                            <label class="field"><span>Mức độ – request</span>
                                <select class="input" name="severity_request"><?php foreach (SEVERITIES as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $r['severity_request']) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                            <label class="field"><span>Mức độ – response</span>
                                <select class="input" name="severity_response"><?php foreach (SEVERITIES as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $r['severity_response']) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                            <label class="field grow"><span>Khuyến nghị</span><textarea class="input" name="advice" rows="3"><?= e($r['advice']) ?></textarea></label>
                            <div class="row gap"><button class="btn btn-primary">Lưu</button><a class="btn btn-ghost" href="<?= e(url('rules')) ?>">Hủy</a></div>
                        </form>
                    </td>
                </tr>
            <?php else: ?>
                <tr>
                    <td><code class="small"><?= e($r['code']) ?></code></td>
                    <td><strong><?= e($r['name']) ?></strong><div class="muted small"><?= e($r['description']) ?></div></td>
                    <td><?= severity_badge($r['severity_request']) ?></td>
                    <td><?= severity_badge($r['severity_response']) ?></td>
                    <td class="small"><?= e($r['advice']) ?><?php if ($r['editor']): ?><div class="muted">Sửa bởi <?= e($r['editor']) ?> · <?= e(fmt_date($r['updated_at'])) ?></div><?php endif; ?></td>
                    <?php if (is_admin()): ?><td><a class="btn btn-sm btn-ghost" href="<?= e(url('rules', ['edit' => $r['code']])) ?>"><?= icon('edit') ?></a></td><?php endif; ?>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="card">
    <div class="card-head"><h2>Cách kết luận & gợi ý phiên bản</h2></div>
    <div class="card-body">
        <ul class="plain-list">
            <li>Có ít nhất một thay đổi <?= severity_badge('breaking') ?> → <strong>Không tương thích ngược</strong>, gợi ý tăng <strong>MAJOR</strong> (v1.2.0 → v2.0.0).</li>
            <li>Không có thay đổi phá vỡ nhưng có <?= severity_badge('warning') ?> → <strong>Tương thích, cần lưu ý</strong>, gợi ý tăng <strong>MINOR</strong>.</li>
            <li>Chỉ có thay đổi <?= severity_badge('safe') ?> → <strong>Tương thích ngược</strong>: MINOR nếu có thêm trường/giá trị, PATCH nếu chỉ đổi mô tả.</li>
            <li>Trường bị <em>đổi tên</em> được đoán khi ở cùng cấp có một trường bị xóa và một trường mới cùng kiểu dữ liệu, tên gần giống nhau.</li>
            <li>Nhánh có cấu trúc chưa hỗ trợ ($ref, oneOf...) không so sánh tự động và được đánh dấu <?= severity_badge('warning') ?> để kiểm tra thủ công.</li>
        </ul>
    </div>
</section>
<?php layout_end();
