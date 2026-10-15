<?php
require __DIR__ . '/src/CapacityPolicy.php';
require __DIR__ . '/src/ApiCompatibilityChecker.php';

// Kiểm tra giới hạn tải & ràng buộc schema
$policy = new CapacityPolicy();
$available = $policy->canRegister(10, 9);
if ($available) {
    echo "AVAILABLE\n";
} else {
    echo "FULL\n";
}

echo "\n=== ĐỀ TÀI SỐ 2: DEMO KIỂM TRA TƯƠNG THÍCH CẤU TRÚC API ===\n";
$oldSchema = [
    'title' => 'User API v1.0',
    'type' => 'object',
    'required' => ['id', 'username', 'email'],
    'properties' => [
        'id' => ['type' => 'integer', 'description' => 'Mã định danh'],
        'username' => ['type' => 'string', 'description' => 'Tên đăng nhập'],
        'email' => ['type' => 'string', 'description' => 'Địa chỉ email'],
        'age' => ['type' => 'integer', 'description' => 'Tuổi']
    ]
];

$newSchema = [
    'title' => 'User API v2.0',
    'type' => 'object',
    'required' => ['id', 'username', 'role'], // 'role' là required mới -> Breaking! 'email' bị bỏ required
    'properties' => [
        'id' => ['type' => 'string', 'description' => 'Mã định danh (đổi thành UUID)'], // type đổi integer -> string -> Breaking!
        'username' => ['type' => 'string', 'description' => 'Tên đăng nhập'],
        'role' => ['type' => 'string', 'description' => 'Quyền hạn người dùng'], // Thêm trường required mới -> Breaking!
        'bio' => ['type' => 'string', 'description' => 'Tiểu sử'] // Thêm trường optional -> Non-breaking
        // 'email' và 'age' bị xóa -> Breaking!
    ]
];

$checker = new ApiCompatibilityChecker();
$result = $checker->compareSchemas($oldSchema, $newSchema);

echo "Kết luận: " . $result['summary'] . "\n";
echo "Tổng số thay đổi: " . $result['total_changes'] . " (Breaking: " . $result['breaking_count'] . ", Non-breaking: " . $result['non_breaking_count'] . ")\n";
echo "Chi tiết thay đổi:\n";
foreach ($result['changes'] as $idx => $change) {
    $num = $idx + 1;
    echo "  [{$num}] [{$change['type']}] {$change['action']} (Trường: {$change['field']}): {$change['message']}\n";
}
echo "===============================================================\n";
