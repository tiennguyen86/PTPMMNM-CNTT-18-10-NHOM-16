<?php

declare(strict_types=1);
require_once __DIR__ . '/Database.php';
function registrationStats(PgSql\Connection $connection, int $eventId): array
{
    // TODO: SELECT event + COUNT registrations with LEFT JOIN.
    // Return ['event_id'=>int, 'title'=>string, 'total'=>int]. Unknown -> [].
    return [];
}
