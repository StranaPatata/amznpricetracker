<?php
require(__DIR__.'/../common/config.php');
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->query("UPDATE users_and_groups SET premium=0, premiumStartingDate=NULL WHERE premium=1 AND premium_permanent=0 AND NOW() > DATE_ADD(premiumStartingDate, INTERVAL 1 MONTH);");
$conn->query("UPDATE settings SET value='0' WHERE setting='lastDelayedOfferSent';");
$conn->close();
?>