<?php

require_once '../../bitrix/settings.php';

$db = new SQLite3('../../db/database.db');

$db->exec('BEGIN');
$stmt = $db->prepare('SELECT ClientID, ELID, StatusesToUpdate, NewStatus, Users FROM TaskCmds WHERE ID = :id');
$stmt->bindValue(':id', (int) $_REQUEST['id'], SQLITE3_INTEGER);
$cmd = $stmt->execute();
if (! $cmd) {
    $db->exec('ROLLBACK');
    fatal_with_http_header($db->lastErrorMsg(), 500);
}
$cmd = $cmd->fetchArray(SQLITE3_ASSOC);

$stmt = $db->prepare('SELECT Domain, Access, Refresh, Expires FROM Clients WHERE ID = :client_id');
$stmt->bindValue(':client_id', $cmd['ClientID'], SQLITE3_INTEGER);
$client = $stmt->execute();
if (! $client) {
    $db->exec('ROLLBACK');
    fatal_with_http_header($db->lastErrorMsg(), 500);
}
$client = $client->fetchArray(SQLITE3_ASSOC);

if (($client['Expires'] - time()) < 100) {
    // refresh access
    // https://oauth.bitrix.info/oauth/token/?
    // grant_type=refresh_token
    // &client_id=app.573ad8a0346747.09223434
    // &client_secret=LJSl0lNB76B5YY6u0YVQ3AW0DrVADcRTwVr4y99PXU1BWQybWK
    // &refresh_token=nfhxkzk3gvrg375wl7u7xex9awz6o3k8

    $params = [
        'grant_type' => 'refresh_token',
        'client_id' => C_REST_CLIENT_ID,
        'client_secret' => C_REST_CLIENT_SECRET,
        'refresh_token' => $client['Refresh'],
    ];
    $query = http_build_query($params);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded'.PHP_EOL,
            'content' => $query,
        ],
    ]);
    $tokens = file_get_contents('https://oauth.bitrix.info/oauth/token/', false, $ctx);
    if (! $tokens) {
        $db->exec('ROLLBACK');
        fatal_with_http_header("auth: couldn't retrieve new authorization tokens while the old ones have expired", 401);
    }
    $tokens = json_decode($tokens, true);
    if (isset($tokens['error'])) {
        fatal($tokens['error']);
    }

    $stmt = $db->prepare('UPDATE Clients SET Access = :access_token, Refresh = :refresh_token, Expires = :expires WHERE ID = :client_id');
    $stmt->bindValue(':client_id', $cmd['ClientID'], SQLITE3_INTEGER);
    $stmt->bindValue(':access_token', $tokens['access_token'], SQLITE3_TEXT);
    $stmt->bindValue(':refresh_token', $tokens['refresh_token'], SQLITE3_TEXT);
    $stmt->bindValue(':expires', $tokens['expires'], SQLITE3_TEXT);
    $res = $stmt->execute();
    if (! $res) {
        $db->exec('ROLLBACK');
        fatal_with_http_header($db->lastErrorMsg(), 500);
    }
    $client['Access'] = $tokens['access_token'];
    $client['Refresh'] = $tokens['refresh_token'];
}
$db->exec('COMMIT');

// TODO: get over 50 tasks
$tasks = [];
$last_id = 0;
while (true) {
    $data = [
        'filter' => [
            'RESPONSIBLE_ID' => explode(',', $cmd['Users']),
            'REAL_STATUS' => explode(',', $cmd['StatusesToUpdate']),
            '>ID' => $last_id,
        ],
        'sort' => ['ID' => 'asc'],
        'select' => ['ID', 'STATUS'],
        'auth' => $client['Access'],
    ];
    $body = json_encode($data);
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, "https://{$client['Domain']}/rest/tasks.task.list");
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($curl);
    curl_close($curl);
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $body = substr($res, $header_size);
    $new_tasks = json_decode($body, true)['result']['tasks'];

    $tasks = array_merge($new_tasks, $tasks);
    if (count($new_tasks) < 50) {
        break;
    } else {
        $last_id = $new_tasks[49]['id'];
    }
}

// update batch
for ($start = 0; $start < count($tasks); $start += 50) {
    $data = [
        'auth' => $client['Access'],
        'cmd' => [],
    ];

    $sub_tasks = array_slice($tasks, $start, 50);
    foreach ($sub_tasks as $task) {
        $new_status = $cmd['NewStatus'];
        $data['cmd'][] = 'tasks.task.update?'.http_build_query([
            'taskId' => $task['id'],
            'fields' => [
                'STATUS' => $new_status,
            ],
        ]);
    }

    $body = json_encode($data);
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, "https://{$client['Domain']}/rest/batch");
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($curl);
    curl_close($curl);

    if (! $res) {
        fatal_with_http_header('batch: failed to call batch', 500);
    }
}

function error($msg): void
{
    $date = date('Y-m-d H:i:s');
    error_log("[$date] $msg\n", 3, '../../errors.log');
}

function fatal($msg): void
{
    $date = date('Y-m-d H:i:s');
    error_log("[$date] $msg\n", 3, '../../errors.log');
    exit;
}

function fatal_with_http_header(string $msg, int $code)
{
    $date = date('Y-m-d H:i:s');
    error_log("[$date] $msg\n", 3, '../../errors.log');
    http_response_code($code);
    exit;
}
