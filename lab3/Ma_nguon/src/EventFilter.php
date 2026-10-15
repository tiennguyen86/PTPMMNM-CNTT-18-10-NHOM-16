<?php
declare(strict_types=1);

function filterEvents(array $events, string $query, string $category = '',
    bool $availableOnly = false): array
{
    $result = [];
    foreach ($events as $event) {
        $matchesQuery = $query === ''
            || stripos($event['title'], $query) !== false;
        $matchesCategory = $category === ''
            || $event['category'] === $category;
        $hasAvailability = $event['registered'] < $event['capacity'];
        $matchesAvailability = !$availableOnly || $hasAvailability;
        $matches = $matchesQuery && $matchesCategory && $matchesAvailability;
        if ($matches) {
            $result[] = $event;
        }
    }
    return $result;
}
