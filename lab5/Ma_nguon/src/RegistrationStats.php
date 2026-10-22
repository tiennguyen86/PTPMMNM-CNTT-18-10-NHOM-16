<?php

declare(strict_types=1);
require_once __DIR__ . '/Database.php';
function registrationStats(PgSql\Connection $connection, int $eventId): array
{
    $result = queryParams($connection,
        'SELECT e.id AS event_id, e.title, COUNT(r.id) AS total
         FROM events e
         LEFT JOIN registrations r ON r.event_id = e.id
         WHERE e.id = $1
         GROUP BY e.id, e.title',
        [$eventId]);
    $row = pg_fetch_assoc($result);
    if ($row === false) {
        return [];
    }

    // pgsql trả các cột số dưới dạng chuỗi; hợp đồng hàm yêu cầu int.
    return [
        'event_id' => (int)$row['event_id'],
        'title' => $row['title'],
        'total' => (int)$row['total'],
    ];
}
