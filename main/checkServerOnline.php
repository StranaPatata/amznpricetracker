<?php

require(__DIR__.'/../common/config.php');
$conn = new mysqli($servername, $username, $password, $dbname);
$result = $conn->query("SELECT server FROM logs ORDER BY id DESC LIMIT 100;");
$result2 = $conn->query("SELECT ServerName FROM last_keyword_index;");

$serverQuantity = intval($conn->query("SELECT value FROM settings WHERE setting = 'activeServers';")->fetch_assoc()['value']);
$serverArray = array();

while($row = $result2->fetch_assoc())
{
    $serverArray[$row['ServerName']] = $row['ServerName'];
}

$conn->close();

$activeServerArray = array();

while($row = $result->fetch_assoc())
{
    if(!array_key_exists($row['server'], $activeServerArray))
        $activeServerArray[$row['server']] = $row['server'];

    if(count($activeServerArray) == $serverQuantity)
        break;
}

if(count($activeServerArray) < $serverQuantity)
{
    $message = "Uno o più server sono offline!!!\n";
    $diffArray = array_diff($serverArray, $activeServerArray);
    foreach($diffArray as $key => $value)
    {
        $message .= $value."\n";
    }
    $message = urlencode($message);
}
else
{
    $message = urlencode("Tutti i server sono online");
}

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => "http://hyperspeed.it/amazonpricetracker/main/amazonTrackerReceiver.php?chatId=199685595&message=".$message
));

curl_exec($curl);
curl_close($curl);

?>