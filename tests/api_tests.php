<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dbPath = sys_get_temp_dir() . '/agile_tracker_test_' . bin2hex(random_bytes(4)) . '.sqlite';
$port = random_int(18080, 18999);
$baseUrl = "http://127.0.0.1:$port";

$command = sprintf(
    'AGILE_TRACKER_DB=%s php -S 127.0.0.1:%d -t %s %s',
    escapeshellarg($dbPath),
    $port,
    escapeshellarg($root . '/public'),
    escapeshellarg($root . '/public/index.php')
);

$process = proc_open($command, [
    0 => ['pipe', 'r'],
    1 => ['file', sys_get_temp_dir() . '/agile_tracker_test_stdout.log', 'a'],
    2 => ['file', sys_get_temp_dir() . '/agile_tracker_test_stderr.log', 'a'],
], $pipes, $root);

if (!is_resource($process)) {
    fwrite(STDERR, "Testiserveri käivitamine ebaõnnestus.\n");
    exit(1);
}

try {
    waitForServer($baseUrl);

    $stories = request('GET', "$baseUrl/api/stories");
    assertTrue(count($stories['json']) >= 4, 'GET /api/stories tagastab näidisstoryd');

    $created = request('POST', "$baseUrl/api/stories", [
        'title' => 'Test story',
        'description' => 'API test',
        'status' => 'todo',
        'points' => 2,
        'acceptanceCriteria' => ['Story salvestub API kaudu.'],
    ]);
    assertSame(201, $created['status'], 'POST /api/stories tagastab 201');
    assertSame('Test story', $created['json']['title'], 'POST salvestab pealkirja');

    $id = $created['json']['id'];
    $invalid = request('POST', "$baseUrl/api/stories", [
        'title' => 'Vigane',
        'description' => '',
        'status' => 'todo',
        'points' => -1,
        'acceptanceCriteria' => ['Tingimus'],
    ]);
    assertSame(400, $invalid['status'], 'Negatiivsed punktid tagastavad 400');

    $updated = request('PUT', "$baseUrl/api/stories/$id", [
        'title' => 'Muudetud story',
        'description' => 'Muudetud',
        'status' => 'doing',
        'points' => 5,
        'acceptanceCriteria' => ['Muudatus salvestub.'],
    ]);
    assertSame('Muudetud story', $updated['json']['title'], 'PUT muudab storyt');

    $status = request('PATCH', "$baseUrl/api/stories/$id/status", ['status' => 'done']);
    assertSame('done', $status['json']['status'], 'PATCH status muudab staatust');

    $commented = request('POST', "$baseUrl/api/stories/$id/comments", ['text' => 'Kontrollkommentaar']);
    assertSame(201, $commented['status'], 'POST kommentaar tagastab 201');
    assertSame('Kontrollkommentaar', $commented['json']['comments'][0]['text'], 'Kommentaar salvestub');

    $all = request('GET', "$baseUrl/api/stories");
    $todoItems = array_values(array_filter($all['json'], fn (array $story): bool => $story['status'] === 'todo'));
    $reorderPayload = array_map(fn (array $story): array => [
        'id' => $story['id'],
        'status' => $story['status'],
    ], array_reverse($todoItems));
    $reordered = request('PATCH', "$baseUrl/api/stories/reorder", ['stories' => $reorderPayload]);
    assertSame(200, $reordered['status'], 'PATCH reorder tagastab 200');

    $deleted = request('DELETE', "$baseUrl/api/stories/$id");
    assertSame(204, $deleted['status'], 'DELETE story tagastab 204');

    echo "Kõik API testid läbisid edukalt.\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    @unlink($dbPath);
}

function waitForServer(string $baseUrl): void
{
    $deadline = microtime(true) + 5;
    do {
        $result = @file_get_contents("$baseUrl/api/stories");
        if ($result !== false) {
            return;
        }
        usleep(100000);
    } while (microtime(true) < $deadline);

    throw new RuntimeException('Testiserver ei hakanud õigel ajal vastama.');
}

/** @return array{status:int,json:mixed} */
function request(string $method, string $url, ?array $body = null): array
{
    $headers = "Content-Type: application/json\r\n";
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => $headers,
            'content' => $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE),
            'ignore_errors' => true,
        ],
    ]);
    $raw = file_get_contents($url, false, $context);
    $status = 0;

    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }

    return [
        'status' => $status,
        'json' => $raw === '' || $raw === false ? null : json_decode($raw, true),
    ];
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("$message. Oodatud: " . var_export($expected, true) . ', tegelik: ' . var_export($actual, true));
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

