-- API SchemaCheck – cấu trúc CSDL MySQL/MariaDB kèm dữ liệu mẫu
-- Import bằng phpMyAdmin: tạo CSDL api_schema_checker (utf8mb4_unicode_ci) rồi Import tệp này.
-- (Không bắt buộc: ứng dụng tự tạo bảng + dữ liệu mẫu ở lần chạy đầu nếu CSDL còn trống.)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `validation_errors`;
DROP TABLE IF EXISTS `validation_runs`;
DROP TABLE IF EXISTS `compat_rules`;
DROP TABLE IF EXISTS `schema_versions`;
DROP TABLE IF EXISTS `endpoints`;
DROP TABLE IF EXISTS `project_members`;
DROP TABLE IF EXISTS `projects`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'user',
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  user_id INT NOT NULL,
  role VARCHAR(20) NOT NULL,
  added_at DATETIME NOT NULL,
  UNIQUE (project_id, user_id),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE endpoints (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE schema_versions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  endpoint_id INT NOT NULL,
  label VARCHAR(50) NOT NULL,
  note TEXT NULL,
  source_format VARCHAR(20) NOT NULL,
  request_raw LONGTEXT NULL,
  response_raw LONGTEXT NULL,
  request_tree LONGTEXT NULL,
  response_tree LONGTEXT NULL,
  issues LONGTEXT NULL,
  issue_count INT NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE (endpoint_id, label),
  FOREIGN KEY (endpoint_id) REFERENCES endpoints(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE compat_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE validation_runs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  endpoint_id INT NOT NULL,
  version_id INT NOT NULL,
  target VARCHAR(10) NOT NULL,
  title VARCHAR(200) NOT NULL,
  sample_json LONGTEXT NOT NULL,
  status VARCHAR(10) NOT NULL,
  error_count INT NOT NULL DEFAULT 0,
  warning_count INT NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (endpoint_id) REFERENCES endpoints(id) ON DELETE CASCADE,
  FOREIGN KEY (version_id) REFERENCES schema_versions(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE validation_errors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  run_id INT NOT NULL,
  level VARCHAR(10) NOT NULL,
  code VARCHAR(40) NOT NULL,
  path VARCHAR(500) NOT NULL,
  message TEXT NOT NULL,
  expected VARCHAR(255) NULL,
  actual VARCHAR(255) NULL,
  FOREIGN KEY (run_id) REFERENCES validation_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_members_user ON project_members (user_id);

CREATE INDEX idx_endpoints_project ON endpoints (project_id);

CREATE INDEX idx_versions_endpoint ON schema_versions (endpoint_id);

CREATE INDEX idx_runs_endpoint ON validation_runs (endpoint_id);

CREATE INDEX idx_runs_created ON validation_runs (created_at);

CREATE INDEX idx_errors_run ON validation_errors (run_id);

CREATE INDEX idx_errors_code ON validation_errors (code);

-- Dữ liệu bảng users
INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `role`, `created_at`) VALUES (1, 'admin', '$2y$12$BiipeRaszniNswlqAn.49uPd1lDNlZsquP7J1qq9mKnGBuqtS0Vl6', 'Quản trị hệ thống', 'admin@example.com', 'admin', '2026-10-11 22:17:07');
INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `role`, `created_at`) VALUES (2, 'phutrach', '$2y$12$mEnwJEJcuV./dQt1Nf0c2ehWRjSOZiStYXaBGOx39ysE32/PT7yge', 'Nguyễn Văn An', 'an@example.com', 'user', '2026-10-11 22:17:08');
INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `role`, `created_at`) VALUES (3, 'dev1', '$2y$12$FqdpFX5m7OS1vzCzvD4cCOxsUUHbScbKPkBlaIiBxzUNeNiexfQT2', 'Trần Thị Bình', 'binh@example.com', 'user', '2026-10-12 22:17:08');
INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `role`, `created_at`) VALUES (4, 'tester', '$2y$12$iYw8HDfODAiiaJyNlXevMuqpREQ6oYZtopsfZkixfCdi5IIBf.YNm', 'Lê Minh Cường', 'cuong@example.com', 'user', '2026-10-12 22:17:08');

-- Dữ liệu bảng projects
INSERT INTO `projects` (`id`, `name`, `description`, `created_by`, `created_at`, `updated_at`) VALUES (1, 'Hệ thống bán hàng (demo)', 'API cho ứng dụng di động và website bán hàng. Dự án mẫu để thử các chức năng so sánh phiên bản và kiểm tra dữ liệu.', 2, '2026-10-12 22:17:08', '2026-10-12 22:17:08');

-- Dữ liệu bảng project_members
INSERT INTO `project_members` (`id`, `project_id`, `user_id`, `role`, `added_at`) VALUES (1, 1, 2, 'owner', '2026-10-12 22:17:08');
INSERT INTO `project_members` (`id`, `project_id`, `user_id`, `role`, `added_at`) VALUES (2, 1, 3, 'editor', '2026-10-12 22:17:08');
INSERT INTO `project_members` (`id`, `project_id`, `user_id`, `role`, `added_at`) VALUES (3, 1, 4, 'viewer', '2026-10-12 22:17:08');

-- Dữ liệu bảng endpoints
INSERT INTO `endpoints` (`id`, `project_id`, `method`, `path`, `name`, `description`, `created_by`, `created_at`) VALUES (1, 1, 'GET', '/api/users/{id}', 'Xem thông tin người dùng', 'Trả về hồ sơ người dùng theo mã.', 2, '2026-10-12 22:17:08');
INSERT INTO `endpoints` (`id`, `project_id`, `method`, `path`, `name`, `description`, `created_by`, `created_at`) VALUES (2, 1, 'POST', '/api/orders', 'Tạo đơn hàng', 'Ứng dụng gửi danh sách sản phẩm, API trả về đơn hàng đã tạo.', 2, '2026-10-12 22:17:08');
INSERT INTO `endpoints` (`id`, `project_id`, `method`, `path`, `name`, `description`, `created_by`, `created_at`) VALUES (3, 1, 'GET', '/api/products', 'Danh sách sản phẩm', 'Bản mô tả dùng $ref, oneOf... để minh họa báo cáo cấu trúc chưa hỗ trợ.', 3, '2026-10-16 22:17:08');

-- Dữ liệu bảng schema_versions
INSERT INTO `schema_versions` (`id`, `endpoint_id`, `label`, `note`, `source_format`, `request_raw`, `response_raw`, `request_tree`, `response_tree`, `issues`, `issue_count`, `created_by`, `created_at`) VALUES (1, 1, 'v1.0.0', 'Phiên bản đầu tiên.', 'json-schema', NULL, '{\n  "$schema": "https://json-schema.org/draft/2020-12/schema",\n  "title": "Thông tin người dùng – v1",\n  "type": "object",\n  "required": ["id", "username", "email", "phone", "age", "status", "address"],\n  "properties": {\n    "id": { "type": "integer", "description": "Mã người dùng" },\n    "username": { "type": "string", "minLength": 3, "maxLength": 50 },\n    "email": { "type": "string", "format": "email" },\n    "phone": { "type": "string", "description": "Số điện thoại" },\n    "age": { "type": "integer", "minimum": 0 },\n    "status": { "type": "string", "enum": ["active", "inactive"] },\n    "address": {\n      "type": "object",\n      "required": ["city", "zip"],\n      "properties": {\n        "street": { "type": "string" },\n        "city": { "type": "string" },\n        "zip": { "type": "string" }\n      }\n    },\n    "roles": { "type": "array", "items": { "type": "string" } },\n    "createdAt": { "type": "string", "format": "date-time" }\n  }\n}\n', NULL, '{"type":"object","nullable":false,"properties":{"id":{"type":"integer","nullable":false,"description":"Mã người dùng"},"username":{"type":"string","nullable":false,"constraints":{"minLength":3,"maxLength":50}},"email":{"type":"string","nullable":false,"format":"email"},"phone":{"type":"string","nullable":false,"description":"Số điện thoại"},"age":{"type":"integer","nullable":false,"constraints":{"minimum":0}},"status":{"type":"string","nullable":false,"enum":["active","inactive"]},"address":{"type":"object","nullable":false,"properties":{"street":{"type":"string","nullable":false},"city":{"type":"string","nullable":false},"zip":{"type":"string","nullable":false}},"required":["city","zip"]},"roles":{"type":"array","nullable":false,"items":{"type":"string","nullable":false}},"createdAt":{"type":"string","nullable":false,"format":"date-time"}},"required":["id","username","email","phone","age","status","address"]}', '[]', 0, 2, '2026-10-13 22:17:08');
INSERT INTO `schema_versions` (`id`, `endpoint_id`, `label`, `note`, `source_format`, `request_raw`, `response_raw`, `request_tree`, `response_tree`, `issues`, `issue_count`, `created_by`, `created_at`) VALUES (2, 1, 'v2.0.0', 'Đổi tên phone → phoneNumber, age chuyển sang chuỗi, bỏ address.zip, thêm avatarUrl.', 'json-schema', NULL, '{\n  "$schema": "https://json-schema.org/draft/2020-12/schema",\n  "title": "Thông tin người dùng – v2",\n  "type": "object",\n  "required": ["id", "username", "email", "phoneNumber", "age", "status", "address", "createdAt"],\n  "properties": {\n    "id": { "type": "integer", "description": "Mã định danh người dùng" },\n    "username": { "type": "string", "minLength": 3, "maxLength": 100 },\n    "email": { "type": ["string", "null"], "format": "email" },\n    "phoneNumber": { "type": "string", "description": "Số điện thoại" },\n    "age": { "type": "string" },\n    "status": { "type": "string", "enum": ["active", "inactive", "banned"] },\n    "address": {\n      "type": "object",\n      "required": ["city"],\n      "properties": {\n        "street": { "type": "string" },\n        "city": { "type": "string" },\n        "district": { "type": "string" }\n      }\n    },\n    "roles": { "type": "array", "items": { "type": "string" } },\n    "avatarUrl": { "type": "string", "format": "uri" },\n    "createdAt": { "type": "string", "format": "date-time" }\n  }\n}\n', NULL, '{"type":"object","nullable":false,"properties":{"id":{"type":"integer","nullable":false,"description":"Mã định danh người dùng"},"username":{"type":"string","nullable":false,"constraints":{"minLength":3,"maxLength":100}},"email":{"type":"string","nullable":true,"format":"email"},"phoneNumber":{"type":"string","nullable":false,"description":"Số điện thoại"},"age":{"type":"string","nullable":false},"status":{"type":"string","nullable":false,"enum":["active","inactive","banned"]},"address":{"type":"object","nullable":false,"properties":{"street":{"type":"string","nullable":false},"city":{"type":"string","nullable":false},"district":{"type":"string","nullable":false}},"required":["city"]},"roles":{"type":"array","nullable":false,"items":{"type":"string","nullable":false}},"avatarUrl":{"type":"string","nullable":false,"format":"uri"},"createdAt":{"type":"string","nullable":false,"format":"date-time"}},"required":["id","username","email","phoneNumber","age","status","address","createdAt"]}', '[]', 0, 3, '2026-10-18 22:17:08');
INSERT INTO `schema_versions` (`id`, `endpoint_id`, `label`, `note`, `source_format`, `request_raw`, `response_raw`, `request_tree`, `response_tree`, `issues`, `issue_count`, `created_by`, `created_at`) VALUES (3, 2, 'v1.0.0', 'Phiên bản đầu tiên.', 'json-schema', '{\n  "type": "object",\n  "required": ["customerId", "items"],\n  "properties": {\n    "customerId": { "type": "integer" },\n    "items": {\n      "type": "array",\n      "minItems": 1,\n      "items": {\n        "type": "object",\n        "required": ["productId", "quantity"],\n        "properties": {\n          "productId": { "type": "integer" },\n          "quantity": { "type": "integer", "minimum": 1 }\n        }\n      }\n    },\n    "note": { "type": "string", "maxLength": 500 },\n    "couponCode": { "type": "string" }\n  }\n}\n', '{\n  "type": "object",\n  "required": ["orderId", "status", "total", "items"],\n  "properties": {\n    "orderId": { "type": "string", "format": "uuid" },\n    "status": { "type": "string", "enum": ["pending", "paid", "shipped"] },\n    "total": { "type": "integer", "minimum": 0 },\n    "items": {\n      "type": "array",\n      "items": {\n        "type": "object",\n        "required": ["productId", "price"],\n        "properties": {\n          "productId": { "type": "integer" },\n          "price": { "type": "integer" }\n        }\n      }\n    }\n  }\n}\n', '{"type":"object","nullable":false,"properties":{"customerId":{"type":"integer","nullable":false},"items":{"type":"array","nullable":false,"constraints":{"minItems":1},"items":{"type":"object","nullable":false,"properties":{"productId":{"type":"integer","nullable":false},"quantity":{"type":"integer","nullable":false,"constraints":{"minimum":1}}},"required":["productId","quantity"]}},"note":{"type":"string","nullable":false,"constraints":{"maxLength":500}},"couponCode":{"type":"string","nullable":false}},"required":["customerId","items"]}', '{"type":"object","nullable":false,"properties":{"orderId":{"type":"string","nullable":false,"format":"uuid"},"status":{"type":"string","nullable":false,"enum":["pending","paid","shipped"]},"total":{"type":"integer","nullable":false,"constraints":{"minimum":0}},"items":{"type":"array","nullable":false,"items":{"type":"object","nullable":false,"properties":{"productId":{"type":"integer","nullable":false},"price":{"type":"integer","nullable":false}},"required":["productId","price"]}}},"required":["orderId","status","total","items"]}', '[]', 0, 2, '2026-10-13 22:17:08');
INSERT INTO `schema_versions` (`id`, `endpoint_id`, `label`, `note`, `source_format`, `request_raw`, `response_raw`, `request_tree`, `response_tree`, `issues`, `issue_count`, `created_by`, `created_at`) VALUES (4, 2, 'v1.1.0', 'Bắt buộc gửi paymentMethod, giá chuyển sang số thực.', 'json-schema', '{\n  "type": "object",\n  "required": ["customerId", "items", "paymentMethod"],\n  "properties": {\n    "customerId": { "type": "integer" },\n    "items": {\n      "type": "array",\n      "minItems": 1,\n      "items": {\n        "type": "object",\n        "required": ["productId", "quantity"],\n        "properties": {\n          "productId": { "type": "integer" },\n          "quantity": { "type": "integer", "minimum": 1 }\n        }\n      }\n    },\n    "note": { "type": "string", "maxLength": 200 },\n    "paymentMethod": { "type": "string", "enum": ["cod", "card", "bank"] },\n    "shippingAddress": { "type": "string" }\n  }\n}\n', '{\n  "type": "object",\n  "required": ["orderId", "status", "total", "items"],\n  "properties": {\n    "orderId": { "type": "string", "format": "uuid" },\n    "status": { "type": "string", "enum": ["pending", "paid", "delivered"] },\n    "total": { "type": "number", "minimum": 0 },\n    "items": {\n      "type": "array",\n      "items": {\n        "type": "object",\n        "required": ["productId", "price"],\n        "properties": {\n          "productId": { "type": "integer" },\n          "price": { "type": "number" }\n        }\n      }\n    },\n    "paymentUrl": { "type": ["string", "null"], "format": "uri" }\n  }\n}\n', '{"type":"object","nullable":false,"properties":{"customerId":{"type":"integer","nullable":false},"items":{"type":"array","nullable":false,"constraints":{"minItems":1},"items":{"type":"object","nullable":false,"properties":{"productId":{"type":"integer","nullable":false},"quantity":{"type":"integer","nullable":false,"constraints":{"minimum":1}}},"required":["productId","quantity"]}},"note":{"type":"string","nullable":false,"constraints":{"maxLength":200}},"paymentMethod":{"type":"string","nullable":false,"enum":["cod","card","bank"]},"shippingAddress":{"type":"string","nullable":false}},"required":["customerId","items","paymentMethod"]}', '{"type":"object","nullable":false,"properties":{"orderId":{"type":"string","nullable":false,"format":"uuid"},"status":{"type":"string","nullable":false,"enum":["pending","paid","delivered"]},"total":{"type":"number","nullable":false,"constraints":{"minimum":0}},"items":{"type":"array","nullable":false,"items":{"type":"object","nullable":false,"properties":{"productId":{"type":"integer","nullable":false},"price":{"type":"number","nullable":false}},"required":["productId","price"]}},"paymentUrl":{"type":"string","nullable":true,"format":"uri"}},"required":["orderId","status","total","items"]}', '[]', 0, 3, '2026-10-19 22:17:08');
INSERT INTO `schema_versions` (`id`, `endpoint_id`, `label`, `note`, `source_format`, `request_raw`, `response_raw`, `request_tree`, `response_tree`, `issues`, `issue_count`, `created_by`, `created_at`) VALUES (5, 3, 'v1.0.0', 'Nhập từ tài liệu OpenAPI cũ.', 'json-schema', NULL, '{\n  "$schema": "https://json-schema.org/draft/2020-12/schema",\n  "type": "object",\n  "required": ["data", "total"],\n  "x-internal-note": "Từ khóa mở rộng x-* được bỏ qua",\n  "$defs": {\n    "Product": {\n      "type": "object",\n      "properties": { "id": { "type": "integer" }, "name": { "type": "string" } }\n    }\n  },\n  "properties": {\n    "data": { "type": "array", "items": { "$ref": "#/$defs/Product" } },\n    "total": { "type": "integer", "minimum": 0 },\n    "price": { "oneOf": [{ "type": "integer" }, { "type": "string" }] },\n    "tags": { "type": ["string", "integer"] },\n    "dimensions": { "type": "array", "items": [{ "type": "number" }, { "type": "number" }] },\n    "meta": { "type": "object", "additionalProperties": { "type": "string" } },\n    "sku": { "type": "string", "pattern": "^[A-Z]{3}-\\\\d{4}$" },\n    "rating": { "type": "number", "exclusiveMaximum": 5 },\n    "discountPercent": { "type": "number", "maximum": 100, "unit": "%" }\n  }\n}\n', NULL, '{"type":"object","nullable":false,"properties":{"data":{"type":"array","nullable":false,"items":{"type":"unknown","nullable":false,"reason":"$ref"}},"total":{"type":"integer","nullable":false,"constraints":{"minimum":0}},"price":{"type":"unknown","nullable":false,"reason":"oneOf"},"tags":{"type":"unknown","nullable":false,"reason":"kiểu hợp string|integer"},"dimensions":{"type":"array","nullable":false,"items":{"type":"unknown","nullable":false,"reason":"mảng tuple"}},"meta":{"type":"object","nullable":false,"properties":[],"required":[]},"sku":{"type":"string","nullable":false,"constraints":{"pattern":"^[A-Z]{3}-\\\\d{4}$"}},"rating":{"type":"number","nullable":false},"discountPercent":{"type":"number","nullable":false,"constraints":{"maximum":100}}},"required":["data","total"]}', '[{"level":"error","path":"$.data[]","message":"Cấu trúc chưa được hỗ trợ: tham chiếu $ref (từ khóa `$ref`). Nhánh này sẽ bị bỏ qua khi so sánh và kiểm tra dữ liệu.","part":"response"},{"level":"error","path":"$.price","message":"Cấu trúc chưa được hỗ trợ: kết hợp oneOf (từ khóa `oneOf`). Nhánh này sẽ bị bỏ qua khi so sánh và kiểm tra dữ liệu.","part":"response"},{"level":"error","path":"$.tags","message":"Cấu trúc chưa được hỗ trợ: kiểu hợp nhiều kiểu (string | integer). Chỉ hỗ trợ một kiểu, có thể kèm \\"null\\".","part":"response"},{"level":"error","path":"$.dimensions[]","message":"Cấu trúc chưa được hỗ trợ: mảng dạng tuple (`items` là danh sách schema).","part":"response"},{"level":"error","path":"$.meta","message":"Cấu trúc chưa được hỗ trợ: `additionalProperties` dạng schema (object kiểu từ điển\\/map). Các trường phát sinh sẽ không được kiểm tra kiểu.","part":"response"},{"level":"warning","path":"$.rating","message":"Ràng buộc `exclusiveMaximum` chưa được hỗ trợ – đã bỏ qua.","part":"response"},{"level":"warning","path":"$.discountPercent","message":"Từ khóa `unit` không được nhận diện – đã bỏ qua.","part":"response"},{"level":"warning","path":"$","message":"Bỏ qua phần định nghĩa dùng chung (`$defs`\\/`definitions`) vì chưa hỗ trợ `$ref`.","part":"response"}]', 8, 3, '2026-10-16 22:17:08');

-- Dữ liệu bảng compat_rules
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (1, 'FIELD_REMOVED', 'Xóa trường', 'Một trường có trong phiên bản cũ không còn trong phiên bản mới.', 'warning', 'breaking', 'Response: ứng dụng đang đọc trường này sẽ nhận giá trị rỗng/undefined và có thể lỗi – nên giữ lại trường (đánh dấu deprecated) ít nhất một phiên bản. Request: ứng dụng cũ vẫn gửi trường này, máy chủ cần bỏ qua thay vì từ chối.', 1, NULL, '2026-10-11 22:17:07');
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (2, 'FIELD_RENAMED', 'Đổi tên trường', 'Phát hiện bằng cách so khớp: ở cùng cấp có một trường bị xóa và một trường mới cùng kiểu dữ liệu, tên gần giống nhau (ví dụ phone → phoneNumber).', 'breaking', 'breaking', 'Ứng dụng dùng tên cũ sẽ không đọc/gửi được dữ liệu. Nên trả về song song tên cũ và tên mới trong giai đoạn chuyển tiếp, hoặc tăng phiên bản chính (major).', 2, NULL, '2026-10-11 22:17:07');
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (3, 'FIELD_ADDED', 'Thêm trường tùy chọn', 'Thêm một trường mới không bắt buộc.', 'safe', 'safe', 'An toàn. Ứng dụng nên bỏ qua các trường lạ khi đọc phản hồi.', 3, NULL, '2026-10-11 22:17:07');
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (4, 'REQUIRED_FIELD_ADDED', 'Thêm trường bắt buộc', 'Thêm một trường mới nằm trong danh sách bắt buộc (required).', 'breaking', 'safe', 'Request: API "yêu cầu thêm thông tin" – ứng dụng cũ không gửi trường này nên bị từ chối. Nên cho trường là tùy chọn và dùng giá trị mặc định. Response: an toàn.', 4, NULL, '2026-10-11 22:17:07');
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (5, 'FIELD_BECAME_REQUIRED', 'Trường chuyển thành bắt buộc', 'Trường trước đây tùy chọn, nay bắt buộc.', 'breaking', 'safe', 'Request: ứng dụng cũ có thể không gửi trường này. Nên giữ tùy chọn hoặc có giá trị mặc định phía máy chủ.', 5, NULL, '2026-10-11 22:17:07');
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (6, 'FIELD_BECAME_OPTIONAL', 'Trường chuyển thành tùy chọn', 'Trường trước đây bắt buộc, nay có thể vắng mặt.', 'safe', 'breaking', 'Response: ứng dụng giả định trường luôn có sẽ lỗi khi trường vắng mặt. Cần thông báo cho các nhóm sử dụng API kiểm tra trường trước khi dùng.', 6, NULL, '2026-10-11 22:17:07');
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (7, 'TYPE_CHANGED', 'Đổi kiểu dữ liệu', 'Kiểu dữ liệu của trường thay đổi (ví dụ integer → string, object → array).', 'breaking', 'breaking', 'Ứng dụng phân tích dữ liệu theo kiểu cũ sẽ lỗi. Nên thêm trường mới với kiểu mới thay vì đổi kiểu trường cũ.', 7, NULL, '2026-10-11 22:17:07');
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (8, 'TYPE_WIDENED', 'Mở rộng kiểu số (integer → number)', 'Trường số nguyên chuyển sang số thực.', 'safe', 'warning', 'Response: ứng dụng khai báo kiểu số nguyên (Java int, Kotlin Int...) có thể lỗi khi nhận số thập phân. Request: an toàn.', 8, NULL, '2026-10-11 22:17:07');
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (9, 'TYPE_NARROWED', 'Thu hẹp kiểu số (number → integer)', 'Trường số thực chuyển sang số nguyên.', 'breaking', 'safe', 'Request: ứng dụng cũ gửi số thập phân sẽ bị từ chối. Response: an toàn.', 9, NULL, '2026-10-11 22:17:07');
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (10, 'BECAME_NULLABLE', 'Cho phép giá trị null', 'Trường được phép mang giá trị null.', 'safe', 'breaking', 'Response: ứng dụng không xử lý null sẽ gặp lỗi (NullPointerException, "cannot read property of null"...).', 10, NULL, '2026-10-11 22:17:07');
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (11, 'BECAME_NOT_NULLABLE', 'Không còn cho phép null', 'Trường trước đây nhận null, nay không cho phép.', 'breaking', 'safe', 'Request: ứng dụng cũ gửi null sẽ bị từ chối.', 11, NULL, '2026-10-11 22:17:07');
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (12, 'ENUM_VALUE_ADDED', 'Thêm giá trị enum', 'Danh sách giá trị cho phép có thêm giá trị mới.', 'safe', 'warning', 'Response: ứng dụng dùng switch/case theo danh sách cũ có thể không xử lý được giá trị mới – cần có nhánh mặc định.', 12, NULL, '2026-10-11 22:17:07');
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (13, 'ENUM_VALUE_REMOVED', 'Bớt giá trị enum', 'Danh sách giá trị cho phép bị bớt đi.', 'breaking', 'safe', 'Request: ứng dụng cũ gửi giá trị đã bị bỏ sẽ bị từ chối.', 13, NULL, '2026-10-11 22:17:07');
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (14, 'CONSTRAINT_TIGHTENED', 'Siết chặt ràng buộc', 'Ràng buộc chặt hơn: tăng min, giảm max, thêm pattern hoặc thêm enum.', 'breaking', 'safe', 'Request: dữ liệu trước đây hợp lệ có thể bị từ chối. Response: an toàn.', 14, NULL, '2026-10-11 22:17:07');
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (15, 'CONSTRAINT_LOOSENED', 'Nới lỏng ràng buộc', 'Ràng buộc lỏng hơn: giảm min, tăng max, bỏ pattern hoặc bỏ enum.', 'safe', 'warning', 'Response: ứng dụng có thể nhận giá trị nằm ngoài phạm vi trước đây (chuỗi dài hơn, số lớn hơn...).', 15, NULL, '2026-10-11 22:17:07');
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (16, 'FORMAT_CHANGED', 'Đổi định dạng', 'Định dạng (format) của chuỗi thay đổi, ví dụ date → date-time.', 'warning', 'warning', 'Cần kiểm tra lại phần phân tích chuỗi (ngày giờ, email, URL...) ở các ứng dụng.', 16, NULL, '2026-10-11 22:17:07');
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (17, 'DESCRIPTION_CHANGED', 'Đổi mô tả', 'Chỉ thay đổi phần mô tả (description), không ảnh hưởng dữ liệu.', 'safe', 'safe', 'Không ảnh hưởng. Cập nhật tài liệu nếu cần.', 17, NULL, '2026-10-11 22:17:07');
INSERT INTO `compat_rules` (`id`, `code`, `name`, `description`, `severity_request`, `severity_response`, `advice`, `sort_order`, `updated_by`, `updated_at`) VALUES (18, 'UNSUPPORTED_SKIPPED', 'Không so sánh được', 'Một trong hai phiên bản dùng cấu trúc chưa hỗ trợ ($ref, oneOf...) nên công cụ không thể so sánh nhánh đó.', 'warning', 'warning', 'Cần người phụ trách API kiểm tra thủ công nhánh này.', 18, NULL, '2026-10-11 22:17:07');

-- Dữ liệu bảng validation_runs
INSERT INTO `validation_runs` (`id`, `endpoint_id`, `version_id`, `target`, `title`, `sample_json`, `status`, `error_count`, `warning_count`, `created_by`, `created_at`) VALUES (1, 1, 2, 'response', 'Phản hồi từ máy chủ staging', '{\n  "id": 1,\n  "username": "nguyenvana",\n  "email": null,\n  "phoneNumber": "0901234567",\n  "age": "25",\n  "status": "active",\n  "address": {\n    "street": "12 Lê Lợi",\n    "city": "Hà Nội",\n    "district": "Hoàn Kiếm"\n  },\n  "roles": ["admin", "editor"],\n  "avatarUrl": "https://example.com/avatar/1.png",\n  "createdAt": "2026-10-01T08:30:00+07:00"\n}\n', 'pass', 0, 0, 4, '2026-10-19 22:17:08');
INSERT INTO `validation_runs` (`id`, `endpoint_id`, `version_id`, `target`, `title`, `sample_json`, `status`, `error_count`, `warning_count`, `created_by`, `created_at`) VALUES (2, 1, 2, 'response', 'Phản hồi app Android cũ (định dạng v1)', '{\n  "id": "1",\n  "username": "ab",\n  "email": "khong-phai-email",\n  "phone": "0901234567",\n  "age": 25,\n  "status": "deleted",\n  "address": {\n    "street": "12 Lê Lợi",\n    "zip": "100000"\n  },\n  "roles": ["admin", 3],\n  "createdAt": "21/10/2026"\n}\n', 'fail', 9, 2, 4, '2026-10-20 22:17:08');

-- Dữ liệu bảng validation_errors
INSERT INTO `validation_errors` (`id`, `run_id`, `level`, `code`, `path`, `message`, `expected`, `actual`) VALUES (1, 2, 'error', 'MISSING_REQUIRED', '$.phoneNumber', 'Thiếu trường bắt buộc "phoneNumber".', 'string', '(không có)');
INSERT INTO `validation_errors` (`id`, `run_id`, `level`, `code`, `path`, `message`, `expected`, `actual`) VALUES (2, 2, 'error', 'TYPE_MISMATCH', '$.id', 'Sai kiểu dữ liệu: cần integer nhưng nhận được string.', 'integer', '"1"');
INSERT INTO `validation_errors` (`id`, `run_id`, `level`, `code`, `path`, `message`, `expected`, `actual`) VALUES (3, 2, 'error', 'CONSTRAINT_VIOLATION', '$.username', 'Chuỗi cần ít nhất 3 ký tự.', 'độ dài ≥ 3', 'độ dài 2');
INSERT INTO `validation_errors` (`id`, `run_id`, `level`, `code`, `path`, `message`, `expected`, `actual`) VALUES (4, 2, 'error', 'FORMAT_INVALID', '$.email', 'Giá trị không đúng định dạng email.', 'email', '"khong-phai-email"');
INSERT INTO `validation_errors` (`id`, `run_id`, `level`, `code`, `path`, `message`, `expected`, `actual`) VALUES (5, 2, 'warning', 'UNKNOWN_FIELD', '$.phone', 'Trường "phone" không có trong bản mô tả.', '(không khai báo)', '"0901234567"');
INSERT INTO `validation_errors` (`id`, `run_id`, `level`, `code`, `path`, `message`, `expected`, `actual`) VALUES (6, 2, 'error', 'TYPE_MISMATCH', '$.age', 'Sai kiểu dữ liệu: cần string nhưng nhận được integer.', 'string', '25');
INSERT INTO `validation_errors` (`id`, `run_id`, `level`, `code`, `path`, `message`, `expected`, `actual`) VALUES (7, 2, 'error', 'ENUM_MISMATCH', '$.status', 'Giá trị không nằm trong danh sách cho phép.', '"active", "inactive", "banned"', '"deleted"');
INSERT INTO `validation_errors` (`id`, `run_id`, `level`, `code`, `path`, `message`, `expected`, `actual`) VALUES (8, 2, 'error', 'MISSING_REQUIRED', '$.address.city', 'Thiếu trường bắt buộc "city".', 'string', '(không có)');
INSERT INTO `validation_errors` (`id`, `run_id`, `level`, `code`, `path`, `message`, `expected`, `actual`) VALUES (9, 2, 'warning', 'UNKNOWN_FIELD', '$.address.zip', 'Trường "zip" không có trong bản mô tả.', '(không khai báo)', '"100000"');
INSERT INTO `validation_errors` (`id`, `run_id`, `level`, `code`, `path`, `message`, `expected`, `actual`) VALUES (10, 2, 'error', 'TYPE_MISMATCH', '$.roles[1]', 'Sai kiểu dữ liệu: cần string nhưng nhận được integer.', 'string', '3');
INSERT INTO `validation_errors` (`id`, `run_id`, `level`, `code`, `path`, `message`, `expected`, `actual`) VALUES (11, 2, 'error', 'FORMAT_INVALID', '$.createdAt', 'Giá trị không đúng định dạng date-time.', 'date-time', '"21/10/2026"');

SET FOREIGN_KEY_CHECKS = 1;
