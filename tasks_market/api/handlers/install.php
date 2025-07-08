<?php

$auth = $_REQUEST['auth'];

if (! is_dir(__DIR__.'/../../db')) {
    mkdir(__DIR__.'/../../db');
}

$db = new SQLite3('../../db/database.db');
$db->query('CREATE TABLE IF NOT EXISTS Clients (
    ID INTEGER PRIMARY KEY,
    Domain TEXT UNIQUE NOT NULL,
    MemberID TEXT UNIQUE NOT NULL,
    Access TEXT NOT NULL,
    Refresh TEXT NOT NULL,
    Expires DATETIME NOT NULL
)');

$db->query('CREATE TABLE IF NOT EXISTS TaskCmds (
    ID INTEGER PRIMARY KEY,
    ClientID INTEGER NOT NULL,
    ELID TEXT UNIQUE NOT NULL,
    StatusesToUpdate TEXT NOT NULL,
    NewStatus TEXT NOT NULL,
    Users TEXT NOT NULL,
    Active INTEGER NOT NULL DEFAULT 1,
    Minutes INTEGER NOT NULL,
    Hours INTEGER NOT NULL,
    WeekDays TEXT NOT NULL,
    MonthDays TEXT NOT NULL,
    Months TEXT NOT NULL,
    FOREIGN KEY (ClientID) REFERENCES Clients(ID)
)');

$db->query('CREATE TABLE IF NOT EXISTS TimemanCmds (
    ID INTEGER PRIMARY KEY,
    ClientID INTEGER NOT NULL,
    ELID TEXT UNIQUE NOT NULL,
    NewStatus TEXT NOT NULL,
    Users TEXT NOT NULL,
    Active INTEGER NOT NULL DEFAULT 1,
    Minutes INTEGER NOT NULL,
    Hours INTEGER NOT NULL,
    WeekDays TEXT NOT NULL,
    MonthDays TEXT NOT NULL,
    Months TEXT NOT NULL,
    FOREIGN KEY (ClientID) REFERENCES Clients(ID)
)');

$db->exec('BEGIN');
// Check if it's already in the DB
$stmt = $db->prepare('SELECT count(Domain) FROM Clients WHERE Domain = :domain');
$stmt->bindValue(':domain', $_REQUEST['auth']['domain'], SQLITE3_TEXT);
$res = $stmt->execute();
if (! $res) {
    $db->exec('ROLLBACK');
    fatal($db->lastErrorMsg());
}

$exists = $res->fetchArray()[0];
if ($exists) {
    updateUser($db, $auth);
} else {
    insertUser($db, $auth);
}
$db->exec('COMMIT');

function updateUser(SQLite3 $db, array $auth)
{
    $stmt = $db->prepare('UPDATE Clients SET Refresh = :refresh, Access = :access, Expires = :expires, MemberID = :member_id WHERE Domain = :domain');
    $stmt->bindValue(':domain', $auth['domain'], SQLITE3_TEXT);
    $stmt->bindValue(':member_id', $auth['member_id'], SQLITE3_TEXT);
    $stmt->bindValue(':refresh', $auth['refresh_token'], SQLITE3_TEXT);
    $stmt->bindValue(':access', $auth['access_token'], SQLITE3_TEXT);
    $stmt->bindValue(':expires', $auth['expires'], SQLITE3_TEXT);
    $res = $stmt->execute();
    if (! $res) {
        $db->exec('ROLLBACK');
        fatal_with_http_header($db->lastErrorMsg(), 500);
    }
}

function insertUser(SQLite3 $db, array $auth)
{
    $stmt = $db->prepare('INSERT INTO Clients ("Domain", "MemberID","Access", "Refresh", "Expires") VALUES (:domain, :member_id, :access, :refresh, :expires)');
    $stmt->bindValue(':domain', $auth['domain'], SQLITE3_TEXT);
    $stmt->bindValue(':member_id', $auth['member_id'], SQLITE3_TEXT);
    $stmt->bindValue(':access', $auth['access_token'], SQLITE3_TEXT);
    $stmt->bindValue(':refresh', $auth['refresh_token'], SQLITE3_TEXT);
    $stmt->bindValue(':expires', $auth['expires'], SQLITE3_TEXT);
    $res = $stmt->execute();
    if (! $res) {
        $db->exec('ROLLBACK');
        fatal_with_http_header($db->lastErrorMsg(), 500);
    }
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
