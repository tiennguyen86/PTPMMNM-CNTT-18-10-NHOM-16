<?php
declare(strict_types=1);

function filterEvents(array $events, string $query, string $category = '',
    bool $availableOnly = false): array
{
    // TODO phần mở rộng: thêm category và availableOnly bằng AND.
    $result = [];
    foreach ($events as $event) {
        $matches = $query === ''
            || stripos($event['title'], $query) !== false;
        if ($matches) {
            $result[] = $event;
        }
    }
    return $result;
}
