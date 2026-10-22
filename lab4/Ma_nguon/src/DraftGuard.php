<?php
declare(strict_types=1);
function canComplete(array $session, int $requestedEventId, int $now, int $ttl): bool {
    if ($requestedEventId <= 0 || $ttl <= 0 || $now < 0) {
        return false;
    }

    $draft = $session['draft'] ?? null;
    if (!is_array($draft) || !is_array($draft['data'] ?? null)) {
        return false;
    }

    $eventId = $draft['data']['event_id'] ?? null;
    $createdAt = $draft['created_at'] ?? null;
    if (!is_int($eventId) || $eventId !== $requestedEventId
        || !is_int($createdAt) || $createdAt < 0 || $createdAt > $now) {
        return false;
    }

    // Đúng mốc hết hạn cũng phải từ chối, không chỉ sau mốc đó.
    return $now - $createdAt < $ttl;
}
