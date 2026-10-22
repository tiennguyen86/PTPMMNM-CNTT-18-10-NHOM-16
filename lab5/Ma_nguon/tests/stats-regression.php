<?php
declare(strict_types=1);
require __DIR__ . '/helper.php';
require __DIR__ . '/../src/RegistrationStats.php';
require __DIR__ . '/../src/RegistrationRepository.php';

try { $connection = db(); }
catch (Throwable $e) { fwrite(STDERR, "NOT_RUN: PostgreSQL unavailable\n"); exit(2); }
queryParams($connection, 'BEGIN', []);
try {
    $first = 500000000 + random_int(1, 100000000);
    $second = $first + 1;
    foreach ([$first, $second] as $id) {
        queryParams($connection,
            'INSERT INTO events(id,title,category,capacity) VALUES ($1,$2,$3,$4)',
            [$id, "O'Neil stats fixture", 'test', 10]);
    }
    $repo = new RegistrationRepository($connection);
    $repo->save($first, 'First', 'one@example.test');
    $repo->save($first, 'Second', 'two@example.test');
    $repo->save($second, 'Other', 'one@example.test');
    same(registrationStats($connection, $first), ['event_id' => $first, 'title' => "O'Neil stats fixture", 'total' => 2], 'only-requested-event');
    same(registrationStats($connection, $second)['total'], 1, 'other-event-independent');
    queryParams($connection, 'DELETE FROM registrations WHERE event_id = $1', [$first]);
    same(registrationStats($connection, $first)['total'], 0, 'zero-after-deletion');
    // A database failure must propagate, rather than look like a missing event.
    queryParams($connection, 'SAVEPOINT stats_error', []);
    queryParams($connection, 'ALTER TABLE registrations RENAME TO registrations_test_hidden', []);
    $failedQuery = false;
    try { registrationStats($connection, $first); }
    catch (RuntimeException $e) { $failedQuery = $e->getMessage() === 'DATABASE_OPERATION_FAILED'; }
    queryParams($connection, 'ROLLBACK TO SAVEPOINT stats_error', []);
    same($failedQuery, true, 'database-error-is-not-empty-result');
} catch (Throwable $e) {
    $failed++;
    echo 'FAIL stats regression: ' . $e->getMessage() . "\n";
} finally { pg_query($connection, 'ROLLBACK'); }
finish();
