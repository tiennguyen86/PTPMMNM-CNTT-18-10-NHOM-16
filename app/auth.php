<?php
defined('APP') or exit;

/** Vai trò trong dự án, xếp theo mức quyền tăng dần. */
const PROJECT_ROLES = [
    'viewer' => 'Chỉ xem',
    'editor' => 'Được sửa mô tả',
    'owner'  => 'Người phụ trách API',
];
const ROLE_RANK = ['viewer' => 1, 'editor' => 2, 'owner' => 3];

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
    $id = $_SESSION['uid'] ?? null;
    $user = $id ? row('SELECT * FROM users WHERE id = ?', [$id]) : null;
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('info', 'Vui lòng đăng nhập để tiếp tục.');
        redirect('login');
    }
    return $user;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) abort(403, 'Chức năng này chỉ dành cho quản trị hệ thống.');
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$user['id'];
}

/** Vai trò của người dùng hiện tại trong dự án (admin được coi là owner). */
function project_role(int $projectId): ?string
{
    if (is_admin()) return 'owner';
    $uid = current_user()['id'] ?? 0;
    return val('SELECT role FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $uid]) ?: null;
}

function role_at_least(?string $role, string $min): bool
{
    return $role !== null && (ROLE_RANK[$role] ?? 0) >= ROLE_RANK[$min];
}

function deny_unless(?string $role, string $min): void
{
    if ($role === null) abort(403, 'Bạn không phải thành viên của dự án này.');
    if (!role_at_least($role, $min)) {
        abort(403, 'Bạn cần quyền "' . PROJECT_ROLES[$min] . '" để thực hiện thao tác này (quyền hiện tại: ' . PROJECT_ROLES[$role] . ').');
    }
}

function load_project(int $id, string $min = 'viewer'): array
{
    $p = row('SELECT * FROM projects WHERE id = ?', [$id]);
    if (!$p) abort(404, 'Dự án không tồn tại.');
    $p['my_role'] = project_role($id);
    deny_unless($p['my_role'], $min);
    return $p;
}

function load_endpoint(int $id, string $min = 'viewer'): array
{
    $ep = row('SELECT e.*, p.name AS project_name FROM endpoints e JOIN projects p ON p.id = e.project_id WHERE e.id = ?', [$id]);
    if (!$ep) abort(404, 'Endpoint không tồn tại.');
    $ep['my_role'] = project_role((int)$ep['project_id']);
    deny_unless($ep['my_role'], $min);
    return $ep;
}

function load_version(int $id, string $min = 'viewer'): array
{
    $v = row('SELECT * FROM schema_versions WHERE id = ?', [$id]);
    if (!$v) abort(404, 'Phiên bản không tồn tại.');
    $v['endpoint'] = load_endpoint((int)$v['endpoint_id'], $min);
    return $v;
}

/**
 * Điều kiện SQL giới hạn theo các dự án người dùng được xem.
 * @return array [sql, params]  – ví dụ: ["p.id IN (SELECT ...)", [uid]]
 */
function project_scope(string $column = 'p.id'): array
{
    if (is_admin()) return ['1 = 1', []];
    return ["$column IN (SELECT project_id FROM project_members WHERE user_id = ?)", [current_user()['id']]];
}
