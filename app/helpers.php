<?php
defined('APP') or exit;

function config(string $key)
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        $value = $value[$part] ?? null;
    }
    return $value;
}

/** Escape dữ liệu khi in ra HTML. */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function url(string $page, array $params = []): string
{
    $params = array_filter($params, fn($v) => $v !== null && $v !== '');
    return 'index.php?' . http_build_query(['page' => $page] + $params);
}

function redirect(string $page, array $params = []): void
{
    header('Location: ' . url($page, $params));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function get_str(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function get_int(string $key, int $default = 0): int
{
    $v = $_GET[$key] ?? null;
    return is_numeric($v) ? (int)$v : $default;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function fmt_date(?string $value): string
{
    if (!$value) return '';
    $ts = strtotime($value);
    return $ts ? date('d/m/Y H:i', $ts) : $value;
}

function pretty_json($data): string
{
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Định dạng lại chuỗi JSON cho dễ đọc (giữ nguyên nếu không hợp lệ). */
function pretty_json_string(?string $json): string
{
    if ($json === null || $json === '') return '';
    $data = json_decode($json);
    return json_last_error() === JSON_ERROR_NONE ? pretty_json($data) : $json;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if (!is_post()) return;
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        abort(419, 'Phiên làm việc đã hết hạn hoặc biểu mẫu không hợp lệ. Vui lòng tải lại trang và thử lại.');
    }
}

/** Giới hạn dung lượng nội dung text/JSON (upload hoặc dán). */
function assert_payload_size(string $text): ?string
{
    $max = (int)config('upload_max_bytes');
    if (strlen($text) > $max) {
        return 'Nội dung quá lớn (tối đa ' . round($max / 1048576, 1) . ' MB).';
    }
    return null;
}

/**
 * Lấy nội dung JSON người dùng nhập: ưu tiên tệp tải lên, nếu không có thì lấy ô nhập.
 * Trả về [nội dung, thông báo lỗi|null].
 */
function read_json_input(string $textField, string $fileField): array
{
    $text = $_POST[$textField] ?? '';
    $text = is_string($text) ? $text : '';
    $file = $_FILES[$fileField] ?? null;

    if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [$text, 'Tải tệp thất bại (mã lỗi ' . (int)$file['error'] . ').'];
        }
        if ($file['size'] > config('upload_max_bytes')) {
            return [$text, 'Tệp quá lớn (tối đa ' . round(config('upload_max_bytes') / 1048576, 1) . ' MB).'];
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['json', 'txt', 'yaml', 'yml'], true)) {
            return [$text, 'Chỉ nhận tệp .json, .txt hoặc .yaml.'];
        }
        $text = (string)file_get_contents($file['tmp_name']);
    }

    // Bỏ BOM UTF-8 (tệp lưu bằng Notepad trên Windows hay có)
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    $text = trim($text);
    if ($err = assert_payload_size($text)) {
        return ['', $err];
    }
    return [$text, null];
}

/**
 * Kiểm tra đăng nhập có bị khóa do quá nhiều lần sai không.
 * @return string|null thông báo lỗi nếu bị khóa
 */
function login_rate_limited(string $username): ?string
{
    $max = (int)config('login_max_attempts');
    $window = (int)config('login_window_minutes');
    if ($max <= 0) return null;

    $ip = client_ip();
    $since = date('Y-m-d H:i:s', time() - $window * 60);
    $count = (int)val(
        'SELECT COUNT(*) FROM login_attempts WHERE created_at >= ? AND success = 0 AND (username = ? OR ip = ?)',
        [$since, mb_strtolower($username), $ip]
    );
    if ($count >= $max) {
        return "Đăng nhập tạm khóa do quá nhiều lần sai. Thử lại sau $window phút.";
    }
    return null;
}

function record_login_attempt(string $username, bool $success): void
{
    try {
        insert('login_attempts', [
            'username'   => mb_strtolower(mb_substr($username, 0, 100)),
            'ip'         => client_ip(),
            'success'    => $success ? 1 : 0,
            'created_at' => now(),
        ]);
    } catch (Throwable $e) {
        // Bảng có thể chưa migrate trên bản cũ – bỏ qua để không chặn đăng nhập
    }
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return is_string($ip) ? mb_substr($ip, 0, 45) : '0.0.0.0';
}

/**
 * Gọi HTTP lấy JSON (kiểm tra dữ liệu live).
 * @return array{ok:bool, body:?string, status:?int, error:?string}
 */
function http_fetch_json(string $url, string $method = 'GET', array $headers = [], ?string $body = null): array
{
    $method = strtoupper($method);
    if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD'], true)) {
        return ['ok' => false, 'body' => null, 'status' => null, 'error' => 'Phương thức HTTP không hợp lệ.'];
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['ok' => false, 'body' => null, 'status' => null, 'error' => 'URL không hợp lệ.'];
    }
    $parts = parse_url($url);
    $scheme = strtolower($parts['scheme'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true)) {
        return ['ok' => false, 'body' => null, 'status' => null, 'error' => 'Chỉ hỗ trợ URL http/https.'];
    }
    $host = $parts['host'] ?? '';
    if ($host === '' || is_blocked_fetch_host($host)) {
        return ['ok' => false, 'body' => null, 'status' => null, 'error' => 'Không được gọi tới địa chỉ nội bộ / metadata (SSRF).'];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'body' => null, 'status' => null, 'error' => 'PHP chưa bật extension curl.'];
    }

    $timeout = max(1, (int)config('http_timeout_seconds'));
    $maxBytes = max(1024, (int)config('http_max_bytes'));
    $hdrs = [];
    foreach ($headers as $h) {
        $h = trim((string)$h);
        if ($h === '' || !str_contains($h, ':')) continue;
        if (preg_match('/[\r\n]/', $h)) continue;
        $hdrs[] = $h;
    }
    if (!array_filter($hdrs, fn($h) => stripos($h, 'accept:') === 0)) {
        $hdrs[] = 'Accept: application/json';
    }

    $ch = curl_init($url);
    $buf = '';
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER     => $hdrs,
        CURLOPT_USERAGENT      => 'API-SchemaCheck/1.0',
        CURLOPT_WRITEFUNCTION  => function ($ch, $data) use (&$buf, $maxBytes) {
            $buf .= $data;
            if (strlen($buf) > $maxBytes) return 0;
            return strlen($data);
        },
    ]);
    if ($body !== null && $body !== '' && $method !== 'GET' && $method !== 'HEAD') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $ok = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($ok === false) {
        $msg = $err !== '' ? $err : 'Không nhận đủ dữ liệu (có thể vượt giới hạn dung lượng).';
        return ['ok' => false, 'body' => null, 'status' => $status ?: null, 'error' => 'Gọi API thất bại: ' . $msg];
    }
    if (strlen($buf) > $maxBytes) {
        return ['ok' => false, 'body' => null, 'status' => $status, 'error' => 'Phản hồi API vượt giới hạn dung lượng.'];
    }
    return ['ok' => true, 'body' => $buf, 'status' => $status, 'error' => null];
}

/** Chặn host nội bộ / cloud metadata để giảm rủi ro SSRF. */
function is_blocked_fetch_host(string $host): bool
{
    $host = strtolower(trim($host, '[]'));
    if (in_array($host, ['localhost', 'metadata.google.internal', 'metadata'], true)) return true;
    if (str_ends_with($host, '.localhost')) return true;

    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips[] = $host;
    } else {
        $resolved = @gethostbynamel($host);
        if (is_array($resolved)) $ips = $resolved;
    }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }
    }
    return false;
}

/** Xuất báo cáo so sánh ra Markdown. */
function compare_report_markdown(array $ep, array $old, array $new, array $result): string
{
    $s = $result['summary'];
    $verdict = [
        'breaking' => 'Không tương thích ngược',
        'warning'  => 'Tương thích, cần lưu ý',
        'safe'     => 'Tương thích ngược',
        'none'     => 'Không có thay đổi',
    ][$s['verdict']] ?? $s['verdict'];
    $lines = [
        '# Báo cáo so sánh schema API',
        '',
        '- **Endpoint:** `' . $ep['method'] . ' ' . $ep['path'] . '`',
        '- **Dự án:** ' . ($ep['project_name'] ?? ''),
        '- **Phiên bản:** ' . $old['label'] . ' → ' . $new['label'],
        '- **Kết luận:** ' . $verdict,
        '- **Phá vỡ / Cần lưu ý / Tương thích:** '
            . $s['counts']['breaking'] . ' / ' . $s['counts']['warning'] . ' / ' . $s['counts']['safe'],
        '- **Gợi ý SemVer:** ' . strtoupper($s['bump']),
        '- **Thời điểm xuất:** ' . now(),
        '',
        '## Chi tiết thay đổi',
        '',
        '| Mức | Phần | Đường dẫn | Quy tắc | Thay đổi | Cũ | Mới |',
        '|---|---|---|---|---|---|---|',
    ];
    foreach ($result['changes'] as $c) {
        $cell = fn($v) => str_replace(['|', "\n"], ['\\|', ' '], (string)($v ?? '—'));
        $lines[] = '| ' . $cell($c['severity']) . ' | ' . $cell($c['part']) . ' | `' . $cell($c['path']) . '` | '
            . $cell($c['rule']) . ' | ' . $cell($c['message']) . ' | ' . $cell($c['old']) . ' | ' . $cell($c['new']) . ' |';
    }
    if (!$result['changes']) {
        $lines[] = '';
        $lines[] = '_Không phát hiện thay đổi._';
    }
    $lines[] = '';
    return implode("\n", $lines);
}

function json_error_vi(JsonException $e): string
{
    return match ($e->getCode()) {
        JSON_ERROR_SYNTAX         => 'Lỗi cú pháp JSON (thiếu dấu phẩy, dấu ngoặc, dấu nháy kép hoặc thừa dấu phẩy cuối).',
        JSON_ERROR_DEPTH          => 'Cấu trúc JSON lồng nhau quá sâu.',
        JSON_ERROR_UTF8           => 'Tệp chứa ký tự không phải UTF-8. Hãy lưu tệp với mã hóa UTF-8.',
        JSON_ERROR_CTRL_CHAR      => 'JSON chứa ký tự điều khiển không hợp lệ.',
        JSON_ERROR_STATE_MISMATCH => 'Cấu trúc JSON không hợp lệ.',
        default                   => 'JSON không hợp lệ: ' . $e->getMessage(),
    };
}

/** Phân trang đơn giản. */
function paginate(int $total, int $perPage = 20): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min(max(1, get_int('p', 1)), $pages);
    return ['page' => $page, 'pages' => $pages, 'per' => $perPage, 'offset' => ($page - 1) * $perPage, 'total' => $total];
}

function abort(int $code, string $message): void
{
    http_response_code($code);
    $title = match ($code) {
        403 => 'Không có quyền truy cập',
        404 => 'Không tìm thấy',
        419 => 'Phiên làm việc hết hạn',
        default => 'Có lỗi xảy ra',
    };
    if (function_exists('layout_start') && current_user()) {
        layout_start($title);
        echo '<div class="empty"><div class="empty-code">' . $code . '</div><h2>' . e($title) . '</h2><p>' . e($message) . '</p>'
            . '<a class="btn btn-primary" href="' . e(url('dashboard')) . '">Về trang tổng quan</a></div>';
        layout_end();
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>' . e($title) . '</title><p style="font-family:system-ui;padding:40px">'
            . e($message) . ' <a href="index.php">Về trang chủ</a></p>';
    }
    exit;
}

/** Gợi ý nhãn phiên bản tiếp theo theo Semantic Versioning (bump từ nhãn cơ sở). */
function next_version_label(string $label, string $bump): ?string
{
    if (!preg_match('/^(v?)(\d+)\.(\d+)(?:\.(\d+))?/i', $label, $m)) return null;
    [$prefix, $major, $minor, $patch] = [$m[1], (int)$m[2], (int)$m[3], (int)($m[4] ?? 0)];
    return match ($bump) {
        'major' => sprintf('%s%d.0.0', $prefix, $major + 1),
        'minor' => sprintf('%s%d.%d.0', $prefix, $major, $minor + 1),
        'patch' => sprintf('%s%d.%d.%d', $prefix, $major, $minor, $patch + 1),
        default => null,
    };
}

/**
 * Gợi ý nhãn sau khi so sánh: bump từ bản mới (tránh gợi ý lại phiên bản đã tồn tại).
 */
function suggest_version_after_compare(string $oldLabel, string $newLabel, string $bump): ?string
{
    if ($bump === 'none') return null;
    $fromNew = next_version_label($newLabel, $bump);
    if ($fromNew) return $fromNew;
    return next_version_label($oldLabel, $bump);
}
