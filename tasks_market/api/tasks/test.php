<?php

$db = new SQLite3('../../db/database.db');
echo $db->lastErrorMsg();
