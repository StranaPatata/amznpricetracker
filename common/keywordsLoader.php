<?php

function loadKeywords()
{
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_errno) {
        printf("Connect failed: %s\n", $conn->connect_error);
        exit();
    }

    $result = $conn->query("SELECT id,string,sort FROM search_keywords ORDER BY id ASC");
    $conn->close();

    $array = array();

    while($row = $result->fetch_assoc())
    {
        $array[$row['id']] = array("string" => $row['string'], "sort" => $row['sort']);
    }

    return $array;
}

?>