<?php
/**
 * Đọc OpenAPI 3.x (JSON) và trích xuất endpoint + schema request/response.
 */
class OpenApiImporter
{
    /**
     * @return array{ok:bool, error:?string, info:array, operations:array}
     */
    public function parse(string $json): array
    {
        if (trim($json) === '') {
            return ['ok' => false, 'error' => 'Nội dung trống.', 'info' => [], 'operations' => []];
        }
        try {
            $doc = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return ['ok' => false, 'error' => 'OpenAPI phải là JSON hợp lệ: ' . json_error_vi($e), 'info' => [], 'operations' => []];
        }
        if (!is_array($doc)) {
            return ['ok' => false, 'error' => 'OpenAPI phải là một object JSON.', 'info' => [], 'operations' => []];
        }

        $openapi = (string)($doc['openapi'] ?? $doc['swagger'] ?? '');
        if ($openapi === '' || (!str_starts_with($openapi, '3.') && $openapi !== '2.0')) {
            return ['ok' => false, 'error' => 'Chỉ hỗ trợ OpenAPI 3.x (hoặc Swagger 2.0 dạng JSON).', 'info' => [], 'operations' => []];
        }

        $info = [
            'title'       => (string)($doc['info']['title'] ?? 'API'),
            'version'     => (string)($doc['info']['version'] ?? '1.0.0'),
            'openapi'     => $openapi,
            'description' => (string)($doc['info']['description'] ?? ''),
        ];

        $components = $doc['components']['schemas'] ?? $doc['definitions'] ?? [];
        if (!is_array($components)) $components = [];

        $paths = $doc['paths'] ?? [];
        if (!is_array($paths) || !$paths) {
            return ['ok' => false, 'error' => 'OpenAPI không có mục `paths`.', 'info' => $info, 'operations' => []];
        }

        $ops = [];
        foreach ($paths as $path => $item) {
            if (!is_array($item)) continue;
            foreach (['get', 'post', 'put', 'patch', 'delete', 'head', 'options'] as $method) {
                if (!isset($item[$method]) || !is_array($item[$method])) continue;
                $op = $item[$method];
                $ops[] = $this->extractOperation((string)$path, strtoupper($method), $op, $components, $doc);
            }
        }

        if (!$ops) {
            return ['ok' => false, 'error' => 'Không tìm thấy operation nào trong `paths`.', 'info' => $info, 'operations' => []];
        }

        return ['ok' => true, 'error' => null, 'info' => $info, 'operations' => $ops];
    }

    private function extractOperation(string $path, string $method, array $op, array $components, array $doc): array
    {
        $summary = (string)($op['summary'] ?? $op['operationId'] ?? ($method . ' ' . $path));
        $description = (string)($op['description'] ?? '');

        $requestSchema = null;
        $body = $op['requestBody']['content'] ?? null;
        if (is_array($body)) {
            $requestSchema = $this->schemaFromContent($body, $components, $doc);
        }

        $responseSchema = null;
        $responses = $op['responses'] ?? [];
        if (is_array($responses)) {
            foreach (['200', '201', 'default'] as $code) {
                if (!isset($responses[$code]['content']) || !is_array($responses[$code]['content'])) continue;
                $responseSchema = $this->schemaFromContent($responses[$code]['content'], $components, $doc);
                if ($responseSchema !== null) break;
            }
            if ($responseSchema === null) {
                foreach ($responses as $resp) {
                    if (!is_array($resp) || !isset($resp['content']) || !is_array($resp['content'])) continue;
                    $responseSchema = $this->schemaFromContent($resp['content'], $components, $doc);
                    if ($responseSchema !== null) break;
                }
            }
        }

        return [
            'method'          => $method,
            'path'            => $path,
            'name'            => mb_substr($summary, 0, 150),
            'description'     => mb_substr($description, 0, 2000),
            'request_schema'  => $requestSchema,
            'response_schema' => $responseSchema,
            'has_request'     => $requestSchema !== null,
            'has_response'    => $responseSchema !== null,
        ];
    }

    private function schemaFromContent(array $content, array $components, array $doc): ?array
    {
        foreach (['application/json', 'application/vnd.api+json', 'text/json'] as $ct) {
            if (!isset($content[$ct]['schema'])) continue;
            return $this->materializeSchema($content[$ct]['schema'], $components, $doc, 0);
        }
        foreach ($content as $media) {
            if (!is_array($media) || !isset($media['schema'])) continue;
            return $this->materializeSchema($media['schema'], $components, $doc, 0);
        }
        return null;
    }

    /** Giải $ref nội bộ components/schemas và trả về schema dạng mảng thuần. */
    private function materializeSchema($schema, array $components, array $doc, int $depth, array $stack = []): ?array
    {
        if ($depth > 24 || !is_array($schema)) return is_array($schema) ? $schema : null;

        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            $ref = $schema['$ref'];
            if (in_array($ref, $stack, true)) {
                return ['type' => 'object', 'description' => 'circular $ref: ' . $ref];
            }
            $resolved = $this->resolveRefArray($ref, $components, $doc);
            if ($resolved === null) return ['$ref' => $ref];
            $stack[] = $ref;
            $out = $this->materializeSchema($resolved, $components, $doc, $depth + 1, $stack);
            // Giữ annotation kèm $ref (OpenAPI hay dùng)
            foreach ($schema as $k => $v) {
                if ($k === '$ref') continue;
                if ($out !== null && !array_key_exists($k, $out)) $out[$k] = $v;
            }
            return $out;
        }

        $out = $schema;
        if (isset($out['properties']) && is_array($out['properties'])) {
            foreach ($out['properties'] as $name => $child) {
                $out['properties'][$name] = $this->materializeSchema($child, $components, $doc, $depth + 1, $stack) ?? $child;
            }
        }
        if (isset($out['items'])) {
            $out['items'] = $this->materializeSchema($out['items'], $components, $doc, $depth + 1, $stack) ?? $out['items'];
        }
        if (isset($out['allOf']) && is_array($out['allOf'])) {
            foreach ($out['allOf'] as $i => $child) {
                $out['allOf'][$i] = $this->materializeSchema($child, $components, $doc, $depth + 1, $stack) ?? $child;
            }
        }
        return $out;
    }

    private function resolveRefArray(string $ref, array $components, array $doc): ?array
    {
        if (preg_match('~^#/components/schemas/([^/]+)$~', $ref, $m)) {
            $name = rawurldecode($m[1]);
            return isset($components[$name]) && is_array($components[$name]) ? $components[$name] : null;
        }
        if (preg_match('~^#/definitions/([^/]+)$~', $ref, $m)) {
            $name = rawurldecode($m[1]);
            $defs = $doc['definitions'] ?? [];
            return isset($defs[$name]) && is_array($defs[$name]) ? $defs[$name] : null;
        }
        // JSON pointer đơn giản trong document
        if (str_starts_with($ref, '#/')) {
            $parts = explode('/', substr($ref, 2));
            $cur = $doc;
            foreach ($parts as $part) {
                $part = str_replace(['~1', '~0'], ['/', '~'], rawurldecode($part));
                if (!is_array($cur) || !array_key_exists($part, $cur)) return null;
                $cur = $cur[$part];
            }
            return is_array($cur) ? $cur : null;
        }
        return null;
    }
}
