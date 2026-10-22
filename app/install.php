<?php
defined('APP') or exit;

/** Câu lệnh tạo bảng cho từng loại CSDL. */
function schema_statements(string $driver): array
{
    $mysql = $driver === 'mysql';
    $pk = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $big = $mysql ? 'LONGTEXT' : 'TEXT';
    $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    return [
        "CREATE TABLE users (
            id $pk,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'user',
            created_at DATETIME NOT NULL
        )$tail",

        "CREATE TABLE projects (
            id $pk,
            name VARCHAR(150) NOT NULL,
            description TEXT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )$tail",

        "CREATE TABLE project_members (
            id $pk,
            project_id INT NOT NULL,
            user_id INT NOT NULL,
            role VARCHAR(20) NOT NULL,
            added_at DATETIME NOT NULL,
            UNIQUE (project_id, user_id),
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )$tail",

        "CREATE TABLE endpoints (
            id $pk,
            project_id INT NOT NULL,
            method VARCHAR(10) NOT NULL,
            path VARCHAR(255) NOT NULL,
            name VARCHAR(150) NULL,
            description TEXT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE (project_id, method, path),
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )$tail",

        "CREATE TABLE schema_versions (
            id $pk,
            endpoint_id INT NOT NULL,
            label VARCHAR(50) NOT NULL,
            note TEXT NULL,
            source_format VARCHAR(20) NOT NULL,
            request_raw $big NULL,
            response_raw $big NULL,
            request_tree $big NULL,
            response_tree $big NULL,
            issues $big NULL,
            issue_count INT NOT NULL DEFAULT 0,
            created_by INT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE (endpoint_id, label),
            FOREIGN KEY (endpoint_id) REFERENCES endpoints(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )$tail",

        "CREATE TABLE compat_rules (
            id $pk,
            code VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(150) NOT NULL,
            description TEXT NULL,
            severity_request VARCHAR(10) NOT NULL,
            severity_response VARCHAR(10) NOT NULL,
            advice TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            updated_by INT NULL,
            updated_at DATETIME NULL,
            FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        )$tail",

        "CREATE TABLE validation_runs (
            id $pk,
            endpoint_id INT NOT NULL,
            version_id INT NOT NULL,
            target VARCHAR(10) NOT NULL,
            title VARCHAR(200) NOT NULL,
            sample_json $big NOT NULL,
            status VARCHAR(10) NOT NULL,
            error_count INT NOT NULL DEFAULT 0,
            warning_count INT NOT NULL DEFAULT 0,
            created_by INT NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (endpoint_id) REFERENCES endpoints(id) ON DELETE CASCADE,
            FOREIGN KEY (version_id) REFERENCES schema_versions(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )$tail",

        "CREATE TABLE validation_errors (
            id $pk,
            run_id INT NOT NULL,
            level VARCHAR(10) NOT NULL,
            code VARCHAR(40) NOT NULL,
            path VARCHAR(500) NOT NULL,
            message TEXT NOT NULL,
            expected VARCHAR(255) NULL,
            actual VARCHAR(255) NULL,
            FOREIGN KEY (run_id) REFERENCES validation_runs(id) ON DELETE CASCADE
        )$tail",

        "CREATE TABLE login_attempts (
            id $pk,
            username VARCHAR(100) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            success TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
        )$tail",

        "CREATE TABLE schema_migrations (
            id $pk,
            name VARCHAR(100) NOT NULL UNIQUE,
            applied_at DATETIME NOT NULL
        )$tail",

        "CREATE INDEX idx_members_user ON project_members (user_id)",
        "CREATE INDEX idx_endpoints_project ON endpoints (project_id)",
        "CREATE INDEX idx_versions_endpoint ON schema_versions (endpoint_id)",
        "CREATE INDEX idx_runs_endpoint ON validation_runs (endpoint_id)",
        "CREATE INDEX idx_runs_created ON validation_runs (created_at)",
        "CREATE INDEX idx_errors_run ON validation_errors (run_id)",
        "CREATE INDEX idx_errors_code ON validation_errors (code)",
        "CREATE INDEX idx_login_attempts_created ON login_attempts (created_at)",
        "CREATE INDEX idx_login_attempts_user ON login_attempts (username)",
    ];
}

function table_exists(PDO $pdo, string $driver, string $table): bool
{
    if ($driver === 'mysql') {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function install_if_needed(PDO $pdo, string $driver): void
{
    if (!table_exists($pdo, $driver, 'users')) {
        foreach (schema_statements($driver) as $sql) {
            $pdo->exec($sql);
        }
        seed_database($pdo);
        return;
    }

    // Migration nhẹ cho CSDL đã có từ bản trước
    migrate_existing($pdo, $driver);
}

/** Áp dụng các thay đổi schema bổ sung (idempotent). */
function migrate_existing(PDO $pdo, string $driver): void
{
    $mysql = $driver === 'mysql';
    $pk = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    if (!table_exists($pdo, $driver, 'login_attempts')) {
        $pdo->exec("CREATE TABLE login_attempts (
            id $pk,
            username VARCHAR(100) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            success TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
        )$tail");
        $pdo->exec('CREATE INDEX idx_login_attempts_created ON login_attempts (created_at)');
        $pdo->exec('CREATE INDEX idx_login_attempts_user ON login_attempts (username)');
    }

    if (!table_exists($pdo, $driver, 'schema_migrations')) {
        $pdo->exec("CREATE TABLE schema_migrations (
            id $pk,
            name VARCHAR(100) NOT NULL UNIQUE,
            applied_at DATETIME NOT NULL
        )$tail");
    }
}

/** Dữ liệu mẫu để chạy thử ngay. */
function seed_database(PDO $pdo): void
{
    $ins = function (string $table, array $data) use ($pdo): int {
        $cols = array_keys($data);
        $stmt = $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')');
        $stmt->execute(array_values($data));
        return (int)$pdo->lastInsertId();
    };
    $ago = fn(string $rel) => date('Y-m-d H:i:s', strtotime($rel));

    $pdo->beginTransaction();

    // Quy tắc
    foreach (require __DIR__ . '/default_rules.php' as $i => $r) {
        $ins('compat_rules', $r + ['sort_order' => $i + 1, 'updated_at' => $ago('-10 days')]);
    }

    // Người dùng
    $hash = fn($p) => password_hash($p, PASSWORD_DEFAULT);
    $admin  = $ins('users', ['username' => 'admin', 'password_hash' => $hash('admin123'), 'full_name' => 'Quản trị hệ thống', 'email' => 'admin@example.com', 'role' => 'admin', 'created_at' => $ago('-10 days')]);
    $owner  = $ins('users', ['username' => 'phutrach', 'password_hash' => $hash('123456'), 'full_name' => 'Nguyễn Văn An', 'email' => 'an@example.com', 'role' => 'user', 'created_at' => $ago('-10 days')]);
    $dev    = $ins('users', ['username' => 'dev1', 'password_hash' => $hash('123456'), 'full_name' => 'Trần Thị Bình', 'email' => 'binh@example.com', 'role' => 'user', 'created_at' => $ago('-9 days')]);
    $viewer = $ins('users', ['username' => 'tester', 'password_hash' => $hash('123456'), 'full_name' => 'Lê Minh Cường', 'email' => 'cuong@example.com', 'role' => 'user', 'created_at' => $ago('-9 days')]);

    // Dự án
    $project = $ins('projects', [
        'name' => 'Hệ thống bán hàng (demo)',
        'description' => 'API cho ứng dụng di động và website bán hàng. Dự án mẫu để thử các chức năng so sánh phiên bản và kiểm tra dữ liệu.',
        'created_by' => $owner, 'created_at' => $ago('-9 days'), 'updated_at' => $ago('-9 days'),
    ]);
    foreach ([[$owner, 'owner'], [$dev, 'editor'], [$viewer, 'viewer']] as [$uid, $role]) {
        $ins('project_members', ['project_id' => $project, 'user_id' => $uid, 'role' => $role, 'added_at' => $ago('-9 days')]);
    }

    $sample = fn(string $f) => (string)file_get_contents(__DIR__ . '/../samples/' . $f);
    $parser = new SchemaParser();
    $addVersion = function (int $endpoint, string $label, string $note, ?string $reqFile, ?string $resFile, int $by, string $when) use ($ins, $sample, $parser): int {
        $issues = [];
        $trees = [];
        foreach (['request' => $reqFile, 'response' => $resFile] as $part => $file) {
            $trees[$part] = null;
            if (!$file) continue;
            $r = $parser->parse($sample($file));
            $trees[$part] = $r['tree'];
            foreach ($r['issues'] as $i) $issues[] = $i + ['part' => $part];
        }
        return $ins('schema_versions', [
            'endpoint_id' => $endpoint, 'label' => $label, 'note' => $note, 'source_format' => 'json-schema',
            'request_raw' => $reqFile ? $sample($reqFile) : null,
            'response_raw' => $resFile ? $sample($resFile) : null,
            'request_tree' => $trees['request'] ? json_encode($trees['request'], JSON_UNESCAPED_UNICODE) : null,
            'response_tree' => $trees['response'] ? json_encode($trees['response'], JSON_UNESCAPED_UNICODE) : null,
            'issues' => json_encode($issues, JSON_UNESCAPED_UNICODE),
            'issue_count' => count(array_filter($issues, fn($i) => $i['level'] !== 'info')),
            'created_by' => $by, 'created_at' => $when,
        ]);
    };

    // Endpoint 1: thông tin người dùng
    $epUser = $ins('endpoints', ['project_id' => $project, 'method' => 'GET', 'path' => '/api/users/{id}', 'name' => 'Xem thông tin người dùng',
        'description' => 'Trả về hồ sơ người dùng theo mã.', 'created_by' => $owner, 'created_at' => $ago('-9 days')]);
    $addVersion($epUser, 'v1.0.0', 'Phiên bản đầu tiên.', null, 'users_v1.response.json', $owner, $ago('-8 days'));
    $userV2 = $addVersion($epUser, 'v2.0.0', 'Đổi tên phone → phoneNumber, age chuyển sang chuỗi, bỏ address.zip, thêm avatarUrl.', null, 'users_v2.response.json', $dev, $ago('-3 days'));

    // Endpoint 2: tạo đơn hàng
    $epOrder = $ins('endpoints', ['project_id' => $project, 'method' => 'POST', 'path' => '/api/orders', 'name' => 'Tạo đơn hàng',
        'description' => 'Ứng dụng gửi danh sách sản phẩm, API trả về đơn hàng đã tạo.', 'created_by' => $owner, 'created_at' => $ago('-9 days')]);
    $addVersion($epOrder, 'v1.0.0', 'Phiên bản đầu tiên.', 'orders_v1.request.json', 'orders_v1.response.json', $owner, $ago('-8 days'));
    $addVersion($epOrder, 'v1.1.0', 'Bắt buộc gửi paymentMethod, giá chuyển sang số thực.', 'orders_v2.request.json', 'orders_v2.response.json', $dev, $ago('-2 days'));

    // Endpoint 3: minh họa cấu trúc chưa hỗ trợ
    $epProduct = $ins('endpoints', ['project_id' => $project, 'method' => 'GET', 'path' => '/api/products', 'name' => 'Danh sách sản phẩm',
        'description' => 'Bản mô tả dùng $ref, oneOf... để minh họa báo cáo cấu trúc chưa hỗ trợ.', 'created_by' => $dev, 'created_at' => $ago('-5 days')]);
    $addVersion($epProduct, 'v1.0.0', 'Nhập từ tài liệu OpenAPI cũ.', null, 'products_unsupported.response.json', $dev, $ago('-5 days'));

    // Lần kiểm tra dữ liệu mẫu
    $tree = json_decode(json_encode((new SchemaParser())->parse($sample('users_v2.response.json'))['tree']), true);
    $validator = new SchemaValidator();
    foreach ([
        ['Phản hồi từ máy chủ staging', 'sample_user_ok.json', $viewer, '-2 days'],
        ['Phản hồi app Android cũ (định dạng v1)', 'sample_user_error.json', $viewer, '-1 day'],
    ] as [$title, $file, $by, $when]) {
        $json = $sample($file);
        $errors = $validator->validate($tree, json_decode($json));
        $errCount = count(array_filter($errors, fn($e) => $e['level'] === 'error'));
        $runId = $ins('validation_runs', [
            'endpoint_id' => $epUser, 'version_id' => $userV2, 'target' => 'response', 'title' => $title, 'sample_json' => $json,
            'status' => $errCount ? 'fail' : 'pass', 'error_count' => $errCount, 'warning_count' => count($errors) - $errCount,
            'created_by' => $by, 'created_at' => $ago($when),
        ]);
        foreach ($errors as $e) {
            $ins('validation_errors', ['run_id' => $runId] + error_row($e));
        }
    }

    $pdo->commit();
}
