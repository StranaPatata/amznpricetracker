<?php

require(__DIR__.'/../common/config.php');
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->query("TRUNCATE logs;");
$conn->close();

?>