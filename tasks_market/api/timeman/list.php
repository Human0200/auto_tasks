<?php

require_once __DIR__.'/../../bitrix/settings.php';

$member_id = $_REQUEST['memberId'];

$db = new SQLite3('../../db/database.db');

$db->exec('BEGIN');
$stmt = $db->prepare('SELECT ID FROM Clients WHERE MemberID = :member_id');
$stmt->bindValue(':member_id', $member_id, SQLITE3_TEXT);
$client = $stmt->execute();
if (! $client) {
    $db->exec('ROLLBACK');
    fatal_with_http_header($db->lastErrorMsg(), 500);
}
$client = $client->fetchArray(SQLITE3_ASSOC);
$stmt = $db->prepare('SELECT ELID, NewStatus, Users, Active, Minutes, Hours, WeekDays, MonthDays, Months FROM TimemanCmds WHERE ClientID = :client_id');
$stmt->bindValue(':client_id', $client['ID']);
$cmds = $stmt->execute();
if (! $cmds) {
    $db->exec('ROLLBACK');
    fatal_with_http_header($db->lastErrorMsg(), 500);
}

$res = [];
while ($cmd = $cmds->fetchArray(SQLITE3_ASSOC)) {
    $res[] = $cmd;
}
$db->exec('COMMIT');
echo json_encode($res);

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
