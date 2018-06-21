<?php
//main
$servername = "db host";
$username = "db username";
$password = "db password";
$dbname = "db name";

$access_key = "INSERT ACCESS KEY HERE";
$secret_key = "INSERT SECRET KEY HERE";
$associative_tag = "INSERT TAG HERE";
$endpoint = "webservices.amazon.it";

require_once('functions.php');
$settings = loadSettings($servername, $username, $password, $dbname);
$pages = $settings['pagesToScan'];
$activeServer = $settings['activeServers'];
$ServerName = "Main";
$refMainTag = $settings['associateId'];
$mode = $settings['mode'];
$site = $settings['site'];
$mainServerName = $settings['mainServer'];
$category = $settings['category'];
$lastCommand = $settings['lastCommand'];
$lastOfferId = $settings['lastDelayedOfferSent'];
$onlyPremiumMode = $settings['onlyPremium'];
?>