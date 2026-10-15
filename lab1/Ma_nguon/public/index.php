<?php
// APISchemaDiff - Công cụ kiểm tra thay đổi và tính tương thích của cấu trúc dữ liệu API
// Đề tài số 2 - Môn: Phát triển phần mềm nguồn mở - Nhóm 16 (CNTT-18-10)
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>APISchemaDiff — Công cụ kiểm tra tính tương thích cấu trúc dữ liệu API</title>
<link rel="stylesheet" href="/assets/css/tokens.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="lab-intro">
<a class="skip-link" href="#main">Đến nội dung chính</a>
<header class="site-header">
<div class="header-inner">
<h1 class="brand">⚡ APISchemaDiff</h1>
<p>Công cụ kiểm tra thay đổi & tính tương thích cấu trúc dữ liệu API (OpenAPI / JSON Schema)</p>
</div>
</header>
<main id="main" tabindex="-1">
<p class="intro-status">Hệ thống phân tích cú pháp và tương thích Schema API đã sẵn sàng trên Linux/WSL.</p>
<h2>Đề tài số 2 — Phát triển phần mềm nguồn mở</h2>
<div class="team-badge" style="margin-bottom: 1.5rem; padding: 1rem; background: rgba(59, 130, 246, 0.08); border-radius: 8px; border-left: 4px solid #3b82f6;">
  <strong>Nhóm 16 — Lớp CNTT-18-10:</strong><br>
  • Nguyễn Hữu Tiến — MSV: 1871020569 (Nhóm trưởng)<br>
  • Nguyễn Việt Anh — MSV: 1871020047<br>
  • Nguyễn Tiến Thuận — MSV: 1871020562
</div>
<h3>Mục tiêu công cụ</h3>
<ul>
  <li><strong>Tự động quét và xác thực cú pháp:</strong> Script Shell tự động hóa kiểm tra tính toàn vẹn của các file đặc tả API.</li>
  <li><strong>Phát hiện thay đổi phá vỡ tương thích (Breaking Changes):</strong> Tự động cảnh báo khi xóa trường, đổi kiểu dữ liệu, hoặc thêm trường bắt buộc mới.</li>
  <li><strong>Báo cáo trực quan:</strong> So sánh trực quan (Visual Diff) giữa 2 phiên bản API Schema v1 và v2.</li>
</ul>
<h3>Lệnh kiểm tra môi trường & kiểm thử</h3>
<pre><code>bash lab.sh check   # Kiểm tra môi trường PHP & Git
bash lab.sh test    # Kiểm tra tính toàn vẹn công cụ
bash lab.sh extend  # Chạy bộ quét tự động mã nguồn & Schema</code></pre>
</main>
</body>
</html>
