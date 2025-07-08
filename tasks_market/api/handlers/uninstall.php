<?php

exit;

$db = new SQLite3('../../db/database.db');
$db->exec('BEGIN');

$clients = $db->query('SELECT ID, Refresh FROM Clients');
foreach ($clients->fetchArray(SQLITE3_ASSOC) as $client) {
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
    $i = 0;
    for (; $i < 3; $i++) {
        $tokens = file_get_contents('https://oauth.bitrix.info/oauth/token/', false, $ctx);
        if (! $tokens) {
            $db->exec('ROLLBACK');
            file_put_contents('../errors.log', json_encode($tokens, JSON_PRETTY_PRINT));

            continue;
        } else {
            break;
        }
    }
    if ($i == 3) {
        $stmt = $db->prepare('DELETE FROM TaskCmds WHERE ClientID = :client_id');
        $stmt->bindValue(':client_id', $client['ID'], SQLITE3_INTEGER);
        $res = $stmt->execute();
        if (! $res) {
            error($db->lastErrorMsg());
        }

        $stmt = $db->prepare('DELET FROM Clients WHERE ID = :id');
        $stmt->bindValue(':id', $client['ID'], SQLITE3_INTEGER);
        $res = $stmt->execute();
        if (! $res) {
            error($db->lastErrorMsg());
        }
    }
}

function error($msg): void
{
    $date = date('Y-m-d H:i:s');
    error_log("[$date] $msg\n", 3, '../../errors.log');
}
// TODO: try to ge oauth token from bitrix a few times. if an error keep happening - delete user.

function fatal_with_http_header(string $msg, int $code)
{
    $date = date('Y-m-d H:i:s');
    error_log("[$date] $msg\n", 3, '../../errors.log');
    http_response_code($code);
    exit;
}
