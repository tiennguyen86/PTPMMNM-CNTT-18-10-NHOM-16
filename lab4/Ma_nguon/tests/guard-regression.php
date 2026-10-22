<?php
declare(strict_types=1);
require __DIR__ . '/helper.php';
require __DIR__ . '/../src/DraftGuard.php';

$valid = ['draft' => ['data' => ['event_id' => 1], 'created_at' => 1000]];
same(canComplete($valid, 1, 1000, 1), true, 'just-created');
same(canComplete($valid, 0, 1100, 300), false, 'missing-requested-event');
same(canComplete($valid, 1, 1100, -1), false, 'negative-ttl');
same(canComplete(['draft' => 'invalid'], 1, 1100, 300), false, 'draft-not-array');
same(canComplete(['draft' => []], 1, 1100, 300), false, 'missing-data');
same(canComplete(['draft' => ['data' => 'invalid', 'created_at' => 1000]], 1, 1100, 300), false, 'data-not-array');
same(canComplete(['draft' => ['data' => ['event_id' => 1]]], 1, 1100, 300), false, 'missing-timestamp');
foreach (['1000', null, [], -1] as $createdAt) {
    $session = $valid;
    $session['draft']['created_at'] = $createdAt;
    same(canComplete($session, 1, 1100, 300), false, 'invalid-timestamp-' . get_debug_type($createdAt));
}
$session = $valid;
$session['draft']['data']['event_id'] = '1';
same(canComplete($session, 1, 1100, 300), false, 'invalid-event-type');
same(canComplete($valid, 1, PHP_INT_MAX, 300), false, 'very-old-draft');
same($valid['draft']['created_at'], 1000, 'guard-does-not-mutate-session');
finish();
