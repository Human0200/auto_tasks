<?php

require_once '../auth.php';
require_once '../../bitrix/settings.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$elid = $_REQUEST['elid'];

session_id('isp-token');
session_start();

$isp_token = '';
if (! isset($_SESSION['token']) || isset($_SESSION['token']) && (time() - $_SESSION['expires'] > 60 * 59)) {
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
$db->exec('BEGIN');
$stmt = $db->prepare('UPDATE TimemanCmds Set Active = 0 WHERE ELID = :elid');
$stmt->bindValue(':elid', $elid, SQLITE3_TEXT);
$err = $stmt->execute();
if (! $err) {
    $db->exec('ROLLBACK');
    fatal_with_http_header($db->lastErrorMsg(), 500);
}

$req = [
    'auth' => $isp_token,
    'out' => 'xml',
    'active' => 'on',
    'func' => 'scheduler.suspend',
    'elid' => $elid,
];
$query = http_build_query($req);
$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded'.PHP_EOL,
        'content' => $query,
    ],
]);
libxml_set_streams_context($ctx);
$xml = XMLReader::open(ISP_URL);
if (! $xml) {
    $db->exec('ROLLBACK');
    fatal_with_http_header('isp: error suspending cron', 500);
}

function getRes(XMLReader $xml): array|bool
{
    $err = parseResFromXML($xml);
    if (! $xml->close()) {
        return false;
    }

    return $err;
}

function parseResFromXML(XMLReader $xml): array
{
    while ($xml->read()) {
        if ($xml->nodeType == XMLReader::ELEMENT && $xml->name === 'error') {
            return ['error' => $xml->readInnerXml()];
        }
    }

    return [];
}

$err = getRes($xml);
if (! empty($err)) {
    $db->exec('ROLLBACK');
    fatal_with_http_header($err, 500);
}

$db->exec('COMMIT');

function fatal($msg): void
{
    $date = date('Y-m-d H:i:s');
    error_log("[$date] $msg\n", 3, '../../errors.log');
    exit;
}

function error($msg): void
{
    $date = date('Y-m-d H:i:s');
    error_log("[$date] $msg\n", 3, '../../errors.log');
}
