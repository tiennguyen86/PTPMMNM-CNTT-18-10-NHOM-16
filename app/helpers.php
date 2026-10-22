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
        if (!in_array($ext, ['json', 'txt'], true)) {
            return [$text, 'Chỉ nhận tệp .json hoặc .txt.'];
        }
        $text = (string)file_get_contents($file['tmp_name']);
    }

    // Bỏ BOM UTF-8 (tệp lưu bằng Notepad trên Windows hay có)
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    return [trim($text), null];
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

/** Gợi ý nhãn phiên bản tiếp theo theo Semantic Versioning. */
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
