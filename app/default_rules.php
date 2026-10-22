<?php
/**
 * Bộ quy tắc tương thích mặc định (được nạp vào bảng compat_rules khi cài đặt).
 * Mỗi quy tắc có mức độ riêng cho phần request (ứng dụng gửi lên) và response (API trả về):
 *   breaking = phá vỡ tương thích, warning = cần lưu ý, safe = tương thích.
 */
return [
    [
        'code' => 'FIELD_REMOVED', 'name' => 'Xóa trường',
        'severity_request' => 'warning', 'severity_response' => 'breaking',
        'description' => 'Một trường có trong phiên bản cũ không còn trong phiên bản mới.',
        'advice' => 'Response: ứng dụng đang đọc trường này sẽ nhận giá trị rỗng/undefined và có thể lỗi – nên giữ lại trường (đánh dấu deprecated) ít nhất một phiên bản. Request: ứng dụng cũ vẫn gửi trường này, máy chủ cần bỏ qua thay vì từ chối.',
    ],
    [
        'code' => 'FIELD_RENAMED', 'name' => 'Đổi tên trường',
        'severity_request' => 'breaking', 'severity_response' => 'breaking',
        'description' => 'Phát hiện bằng cách so khớp: ở cùng cấp có một trường bị xóa và một trường mới cùng kiểu dữ liệu, tên gần giống nhau (ví dụ phone → phoneNumber).',
        'advice' => 'Ứng dụng dùng tên cũ sẽ không đọc/gửi được dữ liệu. Nên trả về song song tên cũ và tên mới trong giai đoạn chuyển tiếp, hoặc tăng phiên bản chính (major).',
    ],
    [
        'code' => 'FIELD_ADDED', 'name' => 'Thêm trường tùy chọn',
        'severity_request' => 'safe', 'severity_response' => 'safe',
        'description' => 'Thêm một trường mới không bắt buộc.',
        'advice' => 'An toàn. Ứng dụng nên bỏ qua các trường lạ khi đọc phản hồi.',
    ],
    [
        'code' => 'REQUIRED_FIELD_ADDED', 'name' => 'Thêm trường bắt buộc',
        'severity_request' => 'breaking', 'severity_response' => 'safe',
        'description' => 'Thêm một trường mới nằm trong danh sách bắt buộc (required).',
        'advice' => 'Request: API "yêu cầu thêm thông tin" – ứng dụng cũ không gửi trường này nên bị từ chối. Nên cho trường là tùy chọn và dùng giá trị mặc định. Response: an toàn.',
    ],
    [
        'code' => 'FIELD_BECAME_REQUIRED', 'name' => 'Trường chuyển thành bắt buộc',
        'severity_request' => 'breaking', 'severity_response' => 'safe',
        'description' => 'Trường trước đây tùy chọn, nay bắt buộc.',
        'advice' => 'Request: ứng dụng cũ có thể không gửi trường này. Nên giữ tùy chọn hoặc có giá trị mặc định phía máy chủ.',
    ],
    [
        'code' => 'FIELD_BECAME_OPTIONAL', 'name' => 'Trường chuyển thành tùy chọn',
        'severity_request' => 'safe', 'severity_response' => 'breaking',
        'description' => 'Trường trước đây bắt buộc, nay có thể vắng mặt.',
        'advice' => 'Response: ứng dụng giả định trường luôn có sẽ lỗi khi trường vắng mặt. Cần thông báo cho các nhóm sử dụng API kiểm tra trường trước khi dùng.',
    ],
    [
        'code' => 'TYPE_CHANGED', 'name' => 'Đổi kiểu dữ liệu',
        'severity_request' => 'breaking', 'severity_response' => 'breaking',
        'description' => 'Kiểu dữ liệu của trường thay đổi (ví dụ integer → string, object → array).',
        'advice' => 'Ứng dụng phân tích dữ liệu theo kiểu cũ sẽ lỗi. Nên thêm trường mới với kiểu mới thay vì đổi kiểu trường cũ.',
    ],
    [
        'code' => 'TYPE_WIDENED', 'name' => 'Mở rộng kiểu số (integer → number)',
        'severity_request' => 'safe', 'severity_response' => 'warning',
        'description' => 'Trường số nguyên chuyển sang số thực.',
        'advice' => 'Response: ứng dụng khai báo kiểu số nguyên (Java int, Kotlin Int...) có thể lỗi khi nhận số thập phân. Request: an toàn.',
    ],
    [
        'code' => 'TYPE_NARROWED', 'name' => 'Thu hẹp kiểu số (number → integer)',
        'severity_request' => 'breaking', 'severity_response' => 'safe',
        'description' => 'Trường số thực chuyển sang số nguyên.',
        'advice' => 'Request: ứng dụng cũ gửi số thập phân sẽ bị từ chối. Response: an toàn.',
    ],
    [
        'code' => 'BECAME_NULLABLE', 'name' => 'Cho phép giá trị null',
        'severity_request' => 'safe', 'severity_response' => 'breaking',
        'description' => 'Trường được phép mang giá trị null.',
        'advice' => 'Response: ứng dụng không xử lý null sẽ gặp lỗi (NullPointerException, "cannot read property of null"...).',
    ],
    [
        'code' => 'BECAME_NOT_NULLABLE', 'name' => 'Không còn cho phép null',
        'severity_request' => 'breaking', 'severity_response' => 'safe',
        'description' => 'Trường trước đây nhận null, nay không cho phép.',
        'advice' => 'Request: ứng dụng cũ gửi null sẽ bị từ chối.',
    ],
    [
        'code' => 'ENUM_VALUE_ADDED', 'name' => 'Thêm giá trị enum',
        'severity_request' => 'safe', 'severity_response' => 'warning',
        'description' => 'Danh sách giá trị cho phép có thêm giá trị mới.',
        'advice' => 'Response: ứng dụng dùng switch/case theo danh sách cũ có thể không xử lý được giá trị mới – cần có nhánh mặc định.',
    ],
    [
        'code' => 'ENUM_VALUE_REMOVED', 'name' => 'Bớt giá trị enum',
        'severity_request' => 'breaking', 'severity_response' => 'safe',
        'description' => 'Danh sách giá trị cho phép bị bớt đi.',
        'advice' => 'Request: ứng dụng cũ gửi giá trị đã bị bỏ sẽ bị từ chối.',
    ],
    [
        'code' => 'CONSTRAINT_TIGHTENED', 'name' => 'Siết chặt ràng buộc',
        'severity_request' => 'breaking', 'severity_response' => 'safe',
        'description' => 'Ràng buộc chặt hơn: tăng min, giảm max, thêm pattern hoặc thêm enum.',
        'advice' => 'Request: dữ liệu trước đây hợp lệ có thể bị từ chối. Response: an toàn.',
    ],
    [
        'code' => 'CONSTRAINT_LOOSENED', 'name' => 'Nới lỏng ràng buộc',
        'severity_request' => 'safe', 'severity_response' => 'warning',
        'description' => 'Ràng buộc lỏng hơn: giảm min, tăng max, bỏ pattern hoặc bỏ enum.',
        'advice' => 'Response: ứng dụng có thể nhận giá trị nằm ngoài phạm vi trước đây (chuỗi dài hơn, số lớn hơn...).',
    ],
    [
        'code' => 'FORMAT_CHANGED', 'name' => 'Đổi định dạng',
        'severity_request' => 'warning', 'severity_response' => 'warning',
        'description' => 'Định dạng (format) của chuỗi thay đổi, ví dụ date → date-time.',
        'advice' => 'Cần kiểm tra lại phần phân tích chuỗi (ngày giờ, email, URL...) ở các ứng dụng.',
    ],
    [
        'code' => 'DESCRIPTION_CHANGED', 'name' => 'Đổi mô tả',
        'severity_request' => 'safe', 'severity_response' => 'safe',
        'description' => 'Chỉ thay đổi phần mô tả (description), không ảnh hưởng dữ liệu.',
        'advice' => 'Không ảnh hưởng. Cập nhật tài liệu nếu cần.',
    ],
    [
        'code' => 'UNSUPPORTED_SKIPPED', 'name' => 'Không so sánh được',
        'severity_request' => 'warning', 'severity_response' => 'warning',
        'description' => 'Một trong hai phiên bản dùng cấu trúc chưa hỗ trợ ($ref, oneOf...) nên công cụ không thể so sánh nhánh đó.',
        'advice' => 'Cần người phụ trách API kiểm tra thủ công nhánh này.',
    ],
];
