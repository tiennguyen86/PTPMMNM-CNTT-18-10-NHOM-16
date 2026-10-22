<?php
/**
 * Đọc tệp .env (KEY=VALUE) vào môi trường process.
 * Không ghi đè biến đã có sẵn (docker/systemd ưu tiên hơn).
 */
function load_env(string $path): void
{
    if (!is_readable($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '') continue;

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) !== false) continue;
        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}

/** Đọc biến môi trường với giá trị mặc định. */
function env(string $key, $default = null)
{
    $v = $_ENV[$key] ?? getenv($key);
    if ($v === false || $v === null || $v === '') return $default;
    return $v;
}

function env_bool(string $key, bool $default = false): bool
{
    $v = env($key);
    if ($v === null) return $default;
    return in_array(strtolower((string)$v), ['1', 'true', 'yes', 'on'], true);
}

function env_int(string $key, int $default = 0): int
{
    $v = env($key);
    return is_numeric($v) ? (int)$v : $default;
}
