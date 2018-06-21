<?php

checkIntegrity();

function checkIntegrity()
{
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT * FROM search_keywords ORDER BY id ASC;");

    $i = 1;
    while($row = $result->fetch_assoc())
    {
        echo $row['id']." ".$i."\n";
        if($row['id'] != $i)
        {
            $conn->query("INSERT INTO `search_keywords` (`id`, `string`) VALUES ('".$i."', 'dummy".$i."');");
            echo "Ripristinata riga ".$i."\n";
            $i = $i+2;
        }
        else
            $i++;
    }
    $conn->close();
}

?>