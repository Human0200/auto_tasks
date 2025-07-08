<?php

require_once __DIR__.'/../auth.php';
require_once '../../bitrix/settings.php';

$data = $_REQUEST;

session_id('isp-token');
session_start();

$isp_token = '';
if (! isset($_SESSION['token']) || isset($_SESSION['token']) && ($_SESSION['expires'] - time() < 100)) {
    $isp_token = Auth::auth();
    if (! $isp_token) {
        fatal_with_http_header('auth: failed to authorize', 500);
    }
    $_SESSION['token'] = $isp_token;
    $_SESSION['expires'] = time() + 60 * 60;
} else {
    $isp_token = $_SESSION['token'];
}

if ($isp_token === '') {
    fatal_with_http_header('auth: failed to authorize (empty ISP token)', 401);
}
session_write_close();

$db = new SQLite3('../../db/database.db');

$stmt = $db->prepare('SELECT ID, MemberID, Domain, Access, Refresh, Expires FROM Clients WHERE MemberID = :member_id');
$stmt->bindValue(':member_id', $data['member_id'], SQLITE3_TEXT);
$client = $stmt->execute();
if (! $client) {
    fatal_with_http_header($db->lastErrorMsg(), 500);
}

$client = $client->fetchArray(SQLITE3_ASSOC);

$db->exec('BEGIN');

$stmt = $db->prepare('INSERT INTO TimemanCmds ("ClientID", "ELID", "NewStatus", "Users", "Minutes", "Hours", "WeekDays", "MonthDays", "Months")
     VALUES (:client_id, "CREATING", :new_status, :users, :minutes, :hours, :week_days, :month_days, :months)');
$stmt->bindValue(':client_id', $client['ID'], SQLITE3_INTEGER);
$stmt->bindValue(':new_status', $data['new_status'], SQLITE3_TEXT);
$stmt->bindValue(':users', $data['users'], SQLITE3_TEXT);
$stmt->bindValue(':minutes', $data['time_min'], SQLITE3_TEXT);
$stmt->bindValue(':hours', $data['time_hour'], SQLITE3_TEXT);
$stmt->bindValue(':week_days', $data['week_days'], SQLITE3_TEXT);
$stmt->bindValue(':month_days', $data['month_days'], SQLITE3_TEXT);
$stmt->bindValue(':months', $data['months'], SQLITE3_TEXT);
$cmd = $stmt->execute();
if (! $cmd) {
    $db->exec('ROLLBACK');
    fatal_with_http_header($db->lastErrorMsg(), 500);
}

$cmd_id = $db->querySingle('SELECT * FROM TimemanCmds WHERE ELID = "CREATING"');
if (! $cmd_id) {
    $db->exec('ROLLBACK');
    fatal_with_http_header($db->lastErrorMsg(), 500);
}

$req = [
    'auth' => $isp_token,
    'out' => 'xml',
    'mailto' => 'ruant02@mail.ru',
    'command' => "GET https://bg59.online/Apps/tasks_market_dev/api/cron/timeman.php?id={$cmd_id}",
    'active' => 'on',
    'hideout' => 'on',
    'func' => 'scheduler.edit',
    'sok' => 'ok',
    'run_every' => 'every_day',
    'schedule_type' => 'type_expert',
    'input_min' => $data['time_min'],
    'input_hour' => $data['time_hour'],
    'input_month' => $data['months'],
    'input_dweek' => $data['week_days'],
    'input_dmonth' => $data['month_days'],
];
$query = http_build_query($req);
$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => $query,
    ],
]);
libxml_set_streams_context($ctx);
$xml = XMLReader::open(ISP_URL);
if (! $xml) {
    $db->exec('ROLLBACK');
    fatal_with_http_header('isp: error adding cron', 500);
}

function getElid(XMLReader $xml): string|array
{
    $data = parseElidFromXML($xml);
    if (! $xml->close()) {
        return false;
    }

    return $data;
}

function parseElidFromXML(XMLReader $xml): array|string
{
    $elid = '';
    while ($xml->read()) {
        if ($xml->nodeType == XMLReader::ELEMENT && $xml->name === 'id') {
            $elid = $xml->readInnerXml();
            break; // We don't need to read anymore, because we've already retrived the elid
        }

        if ($xml->nodeType == XMLReader::ELEMENT && $xml->name === 'error') {
            return ['error' => $xml->readInnerXml()];
        }
    }

    return $elid;
}

$elid = getElid($xml);
if (is_array($elid) && isset($elid['error'])) {
    error($elid['error']);
    $db->exec('ROLLBACK');
    fatal_with_http_header('isp: failed to get elid while parsing response', 500);
}

$stmt = $db->prepare('UPDATE TimemanCmds SET ELID = :elid WHERE ID = :id');
$stmt->bindValue(':elid', $elid, SQLITE3_TEXT);
$stmt->bindValue(':id', $cmd_id, SQLITE3_INTEGER);
$res = $stmt->execute();
if (! $res) {
    $db->exec('ROLLBACK');
    fatal_with_http_header($db->lastErrorMsg(), 500);
}

$db->exec('COMMIT');
http_response_code(201);

function info($msg): void
{
    $date = date('Y-m-d H:i:s');
    error_log("[$date] $msg\n", 3, '../../info.log');
}

function error($msg): void
{
    $date = date('Y-m-d H:i:s');
    error_log("[$date] $msg\n", 3, '../../errors.log');
}

function fatal_with_http_header(string $msg, int $code)
{
    $date = date('Y-m-d H:i:s');
    error_log("[$date] $msg\n", 3, '../../errors.log');
    http_response_code($code);
    exit;
}
