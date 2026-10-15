<?php
require __DIR__ . '/../src/Input.php';
require __DIR__ . '/../src/EventFilter.php';
$events = require __DIR__ . '/../data/events.php';
$query = field($_GET, 'q');
$category = field($_GET, 'category');
$availableOnly = field($_GET, 'available') === '1';
$visible = filterEvents($events, $query, $category, $availableOnly);
require_once __DIR__ . '/../templates/event-presentation.php';
require __DIR__ . '/../templates/header.php';
?>
<div class="page-heading page-heading--list page-search">
<h1>⚡ Danh Mục API & Điểm Cuối Dịch Vụ</h1>
<p>Đề tài số 2: Xây dựng công cụ kiểm tra thay đổi và tính tương thích của cấu trúc dữ liệu API — Nhóm 16 (CNTT-18-10)</p>
</div>

<div style="background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: #fff; padding: 1.25rem 1.5rem; border-radius: 12px; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 4px 12px rgba(37,99,235,0.2);">
    <div>
        <h3 style="margin: 0; color: #fff; font-size: 1.2rem;">⚡ Công cụ Kiểm Tra Tương Thích & So Sánh API Schema (Đề 2)</h3>
        <p style="margin: 0.25rem 0 0 0; opacity: 0.9; font-size: 0.95rem;">Tự động phát hiện Breaking Changes, thay đổi kiểu dữ liệu và trường bắt buộc giữa 2 bản API.</p>
    </div>
    <a href="/api-diff.php" style="background: #fff; color: #1e3a8a; padding: 0.6rem 1.25rem; border-radius: 8px; text-decoration: none; font-weight: 700; display: inline-block;">
        Trải nghiệm công cụ Diff ➔
    </a>
</div>
<form class="search-form" method="get">
    <label class="form-field" for="q">Từ khóa
<input id="q" name="q" value="<?= e($query) ?>">
    </label>
    <label class="form-field" for="category">Loại
        <select id="category" name="category">
            <option value="">-- Tất cả loại --</option>
            <option value="tech" <?= $category === 'tech' ? 'selected' : '' ?>>Công nghệ (tech)</option>
            <option value="design" <?= $category === 'design' ? 'selected' : '' ?>>Thiết kế (design)</option>
        </select>
    </label>
    <label class="form-field form-field--checkbox" for="available">
        <input type="checkbox" id="available" name="available" value="1" <?= $availableOnly ? 'checked' : '' ?>>
        Chỉ xem sự kiện còn chỗ
    </label>
    <button>Tìm</button>
</form>
<p class="result-count">Kết quả: <?= count($visible) ?></p>
<section class="event-list" aria-label="Danh sách sự kiện">
<?php foreach ($visible as $event): ?>
    <article class="event-item event-card">
<?php $content = uiContent((int)$event['id']); ?>
<figure class="event-cover">
<img src="<?= e(uiImage($content['image'])) ?>" alt="<?= e($content['alt']) ?>" width="960" height="540">
<figcaption>Minh họa</figcaption>
</figure>
<div class="event-card-content">
        <h2 class="event-title"><?= e($event['title']) ?></h2>
<p class="event-date"><?= e(uiDate($content['start'])) ?></p>
<p class="event-venue"><?= e($content['venue']) ?></p>
<p><?= e($content['summary']) ?></p>
<p class="event-meta">
        Loại: <?= e($event['category']) ?>. 
        Còn <?= max(0, $event['capacity'] - $event['registered']) ?>
        chỗ (dữ liệu mẫu).
    </p>
<a class="text-button" href="/event.php?id=<?= (int)$event['id'] ?>">Xem chi tiết</a>
</div>
</article>
<?php endforeach; ?>
</section>
<?php if (!$visible): ?>
    <p class="empty-state">Không có kết quả.<span>Thử tìm với một từ khóa khác.</span>
</p>
<?php endif; ?>
<p class="content-note">Thời gian, địa điểm và ảnh bổ sung là nội dung hư cấu phục vụ học tập. Dữ liệu đăng ký vẫn lấy từ nguồn gốc của lab.</p>
<section class="demo-entry"><h2>Sáu bối cảnh, một khu thử riêng</h2>
<p>Hội thảo, workshop, âm nhạc, nghề nghiệp, online và triển lãm. Thử vé và ghế mà không đưa lựa chọn vào đăng ký của lab.</p>
<a class="text-button" href="/ui-demo.php">Khám phá sự kiện mẫu</a></section>
<?php require __DIR__ . '/../templates/footer.php'; ?>
