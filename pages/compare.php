<?php
defined('APP') or exit;

require_login();
$endpointId = get_int('endpoint_id');
$ep = load_endpoint($endpointId);

$versions = rows('SELECT id, label FROM schema_versions WHERE endpoint_id = ? ORDER BY id DESC', [$endpointId]);
$fromId = get_int('from', $versions[1]['id'] ?? 0);
$toId = get_int('to', $versions[0]['id'] ?? 0);

$result = null;
$error = null;
if ($fromId && $toId) {
    $old = row('SELECT * FROM schema_versions WHERE id = ? AND endpoint_id = ?', [$fromId, $endpointId]);
    $new = row('SELECT * FROM schema_versions WHERE id = ? AND endpoint_id = ?', [$toId, $endpointId]);
    if (!$old || !$new) {
        $error = 'Phiên bản không thuộc endpoint này.';
    } elseif ($fromId === $toId) {
        $error = 'Hãy chọn hai phiên bản khác nhau.';
    } else {
        $result = compare_versions($old, $new);
        $unsupported = array_filter(array_merge(version_issues($old), version_issues($new)), fn($i) => $i['level'] === 'error');
    }
}

layout_start('So sánh phiên bản', 'projects');
page_head('So sánh phiên bản', [
    'Dự án' => url('projects'),
    $ep['project_name'] => url('project', ['id' => $ep['project_id']]),
    $ep['method'] . ' ' . $ep['path'] => url('endpoint', ['id' => $endpointId]),
    'So sánh' => null,
], $result
    ? '<a class="btn btn-ghost" href="' . e(url('compare_export', ['endpoint_id' => $endpointId, 'from' => $fromId, 'to' => $toId])) . '">' . icon('download') . 'Xuất Markdown</a>'
      . '<button type="button" class="btn btn-ghost" data-print>' . icon('print') . 'In báo cáo</button>'
    : '',
    'Phân loại từng thay đổi theo <a href="' . e(url('rules')) . '">bộ quy tắc tương thích đã công bố</a>.');
?>
<?php if (count($versions) < 2): ?>
    <div class="alert alert-info">Endpoint cần ít nhất hai phiên bản mô tả để so sánh.</div>
<?php else: ?>
    <form class="card compare-bar no-print" method="get">
        <input type="hidden" name="page" value="compare">
        <input type="hidden" name="endpoint_id" value="<?= $endpointId ?>">
        <span class="compare-title"><?= method_badge($ep['method']) ?> <code><?= e($ep['path']) ?></code></span>
        <label class="muted">Bản cũ</label>
        <select class="input" name="from">
            <?php foreach ($versions as $v): ?><option value="<?= (int)$v['id'] ?>"<?= selected($v['id'], $fromId) ?>><?= e($v['label']) ?></option><?php endforeach; ?>
        </select>
        <span class="arrow">→</span>
        <label class="muted">Bản mới</label>
        <select class="input" name="to">
            <?php foreach ($versions as $v): ?><option value="<?= (int)$v['id'] ?>"<?= selected($v['id'], $toId) ?>><?= e($v['label']) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-primary">So sánh</button>
        <a class="btn btn-ghost" href="<?= e(url('compare', ['endpoint_id' => $endpointId, 'from' => $toId, 'to' => $fromId])) ?>" title="Đảo chiều">⇄</a>
    </form>
<?php endif; ?>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<?php if ($result):
    $s = $result['summary'];
    $verdictText = [
        'breaking' => ['Không tương thích ngược', 'Có thay đổi phá vỡ tương thích – ứng dụng đang dùng phiên bản ' . $old['label'] . ' sẽ gặp lỗi nếu chuyển sang ' . $new['label'] . ' mà không cập nhật.'],
        'warning'  => ['Tương thích, cần lưu ý', 'Không có thay đổi phá vỡ, nhưng có thay đổi có thể ảnh hưởng tới một số ứng dụng. Nên thông báo cho các nhóm sử dụng API.'],
        'safe'     => ['Tương thích ngược', 'Các thay đổi đều an toàn với ứng dụng đang sử dụng API.'],
        'none'     => ['Không có thay đổi', 'Hai phiên bản có cấu trúc giống nhau.'],
    ][$s['verdict']];
    $bumpText = ['major' => 'MAJOR (phiên bản chính)', 'minor' => 'MINOR (phiên bản phụ)', 'patch' => 'PATCH (bản vá)', 'none' => 'Không cần'][$s['bump']];
    $suggest = suggest_version_after_compare($old['label'], $new['label'], $s['bump']);
    ?>
    <section class="verdict verdict-<?= e($s['verdict']) ?>">
        <div class="verdict-main">
            <div class="verdict-label"><?= e($old['label']) ?> → <?= e($new['label']) ?></div>
            <h2><?= e($verdictText[0]) ?></h2>
            <p><?= e($verdictText[1]) ?></p>
            <p class="small">Gợi ý tăng phiên bản theo Semantic Versioning: <strong><?= e($bumpText) ?></strong><?= $suggest && $s['bump'] !== 'none' ? ' – từ bản mới, ví dụ ' . e($new['label']) . ' → <strong>' . e($suggest) . '</strong>' : '' ?>.</p>
        </div>
        <div class="verdict-counts">
            <div class="vc vc-breaking"><strong><?= $s['counts']['breaking'] ?></strong><span>Phá vỡ</span></div>
            <div class="vc vc-warning"><strong><?= $s['counts']['warning'] ?></strong><span>Cần lưu ý</span></div>
            <div class="vc vc-safe"><strong><?= $s['counts']['safe'] ?></strong><span>Tương thích</span></div>
        </div>
    </section>

    <?php if ($unsupported): ?>
        <div class="alert alert-warning">Một trong hai phiên bản có <?= count($unsupported) ?> cấu trúc chưa hỗ trợ – các nhánh đó không được so sánh tự động. Xem báo cáo đọc cấu trúc của
            <a href="<?= e(url('version', ['id' => $old['id']])) ?>"><?= e($old['label']) ?></a> và <a href="<?= e(url('version', ['id' => $new['id']])) ?>"><?= e($new['label']) ?></a>.</div>
    <?php endif; ?>

    <section class="card">
        <div class="card-head">
            <h2>Chi tiết thay đổi <span class="count"><?= $s['total'] ?></span></h2>
            <div class="chips no-print" data-filter-group="changes">
                <button type="button" class="chip active" data-filter="all">Tất cả</button>
                <button type="button" class="chip" data-filter="breaking">Phá vỡ (<?= $s['counts']['breaking'] ?>)</button>
                <button type="button" class="chip" data-filter="warning">Cần lưu ý (<?= $s['counts']['warning'] ?>)</button>
                <button type="button" class="chip" data-filter="safe">Tương thích (<?= $s['counts']['safe'] ?>)</button>
                <span class="chip-sep"></span>
                <button type="button" class="chip" data-filter-part="request">Request</button>
                <button type="button" class="chip" data-filter-part="response">Response</button>
            </div>
        </div>
        <?php if (!$result['changes']): ?>
            <div class="card-body muted">Không phát hiện thay đổi nào.</div>
        <?php else: ?>
            <table class="table changes" id="changes">
                <thead><tr><th style="width:150px">Mức độ</th><th style="width:90px">Phần</th><th>Trường</th><th>Thay đổi</th><th style="width:150px">Cũ</th><th style="width:150px">Mới</th><th>Ảnh hưởng tới ứng dụng</th></tr></thead>
                <tbody>
                <?php foreach ($result['changes'] as $c): ?>
                    <tr data-sev="<?= e($c['severity']) ?>" data-part="<?= e($c['part']) ?>" class="row-<?= e($c['severity']) ?>">
                        <td><?= severity_badge($c['severity']) ?></td>
                        <td><?= e($c['part']) ?></td>
                        <td><code><?= e($c['path']) ?></code></td>
                        <td><strong><?= e($c['rule']) ?></strong><div class="small"><?= e($c['message']) ?></div></td>
                        <td class="small mono"><?= e($c['old'] ?? '—') ?></td>
                        <td class="small mono"><?= e($c['new'] ?? '—') ?></td>
                        <td class="small"><strong><?= e(SEVERITY_IMPACT[$c['severity']] ?? '') ?></strong>
                            <?php if ($c['advice']): ?><details><summary>Khuyến nghị</summary><?= e($c['advice']) ?></details><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <div class="grid-2 gap-lg">
        <?php foreach (['response', 'request'] as $part):
            $ot = version_tree($old, $part);
            $nt = version_tree($new, $part);
            if (!$ot && !$nt) continue; ?>
            <section class="card">
                <div class="card-head"><h2>Cấu trúc <?= e($part) ?> – <?= e($old['label']) ?></h2></div>
                <div class="card-body"><?= SchemaTree::renderTree($ot) ?></div>
            </section>
            <section class="card">
                <div class="card-head"><h2>Cấu trúc <?= e($part) ?> – <?= e($new['label']) ?></h2></div>
                <div class="card-body"><?= SchemaTree::renderTree($nt) ?></div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php layout_end();
