<?php
declare(strict_types=1);

/**
 * Đề tài số 2: Xây dựng công cụ kiểm tra thay đổi và tính tương thích của cấu trúc dữ liệu API
 * Nhóm 16 — Lớp CNTT-18-10 (Nguyễn Hữu Tiến, Nguyễn Việt Anh, Nguyễn Tiến Thuận)
 * 
 * Class ApiCompatibilityChecker
 * Kiểm tra thay đổi và tính tương thích ngược (Backward Compatibility) giữa 2 phiên bản API Schema
 */
final class ApiCompatibilityChecker {
    public const CHANGE_BREAKING = 'BREAKING';
    public const CHANGE_NON_BREAKING = 'NON_BREAKING';
    public const CHANGE_INFO = 'INFO';

    /**
     * So sánh 2 schema API và trả về chi tiết thay đổi cùng kết luận tương thích
     */
    public function compareSchemas(array $oldSchema, array $newSchema): array {
        $changes = [];
        $oldProps = $oldSchema['properties'] ?? [];
        $newProps = $newSchema['properties'] ?? [];
        $oldRequired = $oldSchema['required'] ?? [];
        $newRequired = $newSchema['required'] ?? [];

        // 1. Kiểm tra các trường bị xóa (Removed properties) -> BREAKING CHANGE
        foreach ($oldProps as $fieldName => $oldProp) {
            if (!array_key_exists($fieldName, $newProps)) {
                $changes[] = [
                    'field' => $fieldName,
                    'type' => self::CHANGE_BREAKING,
                    'action' => 'REMOVED',
                    'message' => "Trường '{$fieldName}' đã bị xóa khỏi API Schema mới. Đây là thay đổi phá vỡ tương thích (Breaking Change).",
                    'old_detail' => $oldProp,
                    'new_detail' => null,
                ];
            }
        }

        // 2. Kiểm tra các trường được thêm mới hoặc sửa đổi
        foreach ($newProps as $fieldName => $newProp) {
            // Trường mới được thêm vào
            if (!array_key_exists($fieldName, $oldProps)) {
                $isRequired = in_array($fieldName, $newRequired, true);
                if ($isRequired) {
                    // Thêm trường mới nhưng bắt buộc -> BREAKING CHANGE (Client cũ không gửi trường này sẽ lỗi)
                    $changes[] = [
                        'field' => $fieldName,
                        'type' => self::CHANGE_BREAKING,
                        'action' => 'ADDED_REQUIRED',
                        'message' => "Trường bắt buộc mới '{$fieldName}' được thêm vào. Client cũ chưa biết trường này sẽ bị lỗi.",
                        'old_detail' => null,
                        'new_detail' => $newProp,
                    ];
                } else {
                    // Thêm trường mới tùy chọn (optional) -> NON-BREAKING CHANGE
                    $changes[] = [
                        'field' => $fieldName,
                        'type' => self::CHANGE_NON_BREAKING,
                        'action' => 'ADDED_OPTIONAL',
                        'message' => "Trường tùy chọn mới '{$fieldName}' được thêm vào an toàn (Backward Compatible).",
                        'old_detail' => null,
                        'new_detail' => $newProp,
                    ];
                }
                continue;
            }

            // Trường đã tồn tại ở cả 2 phiên bản -> Kiểm tra thay đổi kiểu dữ liệu (Type changed)
            $oldProp = $oldProps[$fieldName];
            $oldType = $oldProp['type'] ?? 'unknown';
            $newType = $newProp['type'] ?? 'unknown';

            if ($oldType !== $newType) {
                $changes[] = [
                    'field' => $fieldName,
                    'type' => self::CHANGE_BREAKING,
                    'action' => 'TYPE_CHANGED',
                    'message' => "Kiểu dữ liệu của '{$fieldName}' thay đổi từ '{$oldType}' sang '{$newType}'. Gây lỗi phân tích ở client cũ.",
                    'old_detail' => $oldProp,
                    'new_detail' => $newProp,
                ];
            } else {
                // Kiểm tra ràng buộc bắt buộc (required) thay đổi
                $wasRequired = in_array($fieldName, $oldRequired, true);
                $nowRequired = in_array($fieldName, $newRequired, true);

                if (!$wasRequired && $nowRequired) {
                    $changes[] = [
                        'field' => $fieldName,
                        'type' => self::CHANGE_BREAKING,
                        'action' => 'MADE_REQUIRED',
                        'message' => "Trường '{$fieldName}' trước đây là tùy chọn, nay trở thành bắt buộc (Breaking Change).",
                        'old_detail' => $oldProp,
                        'new_detail' => $newProp,
                    ];
                } elseif ($wasRequired && !$nowRequired) {
                    $changes[] = [
                        'field' => $fieldName,
                        'type' => self::CHANGE_NON_BREAKING,
                        'action' => 'MADE_OPTIONAL',
                        'message' => "Trường '{$fieldName}' chuyển từ bắt buộc sang tùy chọn (An toàn tương thích ngược).",
                        'old_detail' => $oldProp,
                        'new_detail' => $newProp,
                    ];
                }
            }
        }

        $breakingCount = count(array_filter($changes, fn($c) => $c['type'] === self::CHANGE_BREAKING));
        $isCompatible = ($breakingCount === 0);

        return [
            'is_compatible' => $isCompatible,
            'breaking_count' => $breakingCount,
            'non_breaking_count' => count($changes) - $breakingCount,
            'total_changes' => count($changes),
            'changes' => $changes,
            'summary' => $isCompatible
                ? 'COMPATIBLE: Phiên bản API mới tương thích ngược hoàn toàn.'
                : "INCOMPATIBLE: Phát hiện {$breakingCount} thay đổi phá vỡ tính tương thích (Breaking Changes)."
        ];
    }
}
