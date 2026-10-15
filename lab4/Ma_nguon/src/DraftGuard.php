<?php
declare(strict_types=1);
function canComplete(array $session, int $requestedEventId, int $now, int $ttl): bool {
    // TODO: phải có bản nháp, đúng event, TTL dương,
    // created_at không ở tương lai, tuổi bản nháp < TTL.
    return false;
}
