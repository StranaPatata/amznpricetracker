<?php
error_reporting(E_ERROR | E_PARSE);
//2014-08-18T12:00:00Z
indexItemsMultiServer();
//test();

function test()
{
    require_once('functions.php');

    $requestUrl = createRequestUrl("B00A0VCJPI");
    $response = file_get_contents($requestUrl);    
    //saveLog($keywordsArray[$i], $http_response_header[0]);
    if($http_response_header[0] != "HTTP/1.1 200 OK")
    {
        return;
    }

    //setLastKeywordIndex($i);
    createItems($response);
}

//multiple server
function indexItemsMultiServer()
{
    require('config.php');
    require_once('keywordsLoader.php');
    require_once('functions.php');
    
    initializeServerOnMainDB();
    
    $scanRange = loadBalancer();
    $keywordsArray = loadKeywords();
    $lastKeywordIndex = intval(loadLastKeywordIndex());
    $asinBlacklist = loadBlacklistedASIN();

    if($lastKeywordIndex < $scanRange['start'] || $lastKeywordIndex > $scanRange['end'])
    {
        setLastKeywordIndex($scanRange['start']);       
        $lastKeywordIndex = $scanRange['start'];
    }

    if(!isset($keywordsArray))
        indexItems();

    while(1)
    {
        //implemento invio ritardato agli utenti tradizionali
        if($ServerName == $mainServerName && $onlyPremiumMode == "0")
            sendNotificationToStandardUser();

        echo "\n".$keywordsArray[$lastKeywordIndex]['string']."\n";
        $i = 1;

        if(substr($keywordsArray[$lastKeywordIndex]['string'], 0, 5) != "dummy") //salto dummy
        {
            while($i <= $pages)
            {
                echo "  ".$keywordsArray[$lastKeywordIndex]['string']."-".$i."\n";
                $requestUrl = createRequestUrl($keywordsArray[$lastKeywordIndex]['string'], $i, $keywordsArray[$lastKeywordIndex]['sort']);
                $response = file_get_contents($requestUrl);
                saveLog($keywordsArray[$lastKeywordIndex]['string']."-".$i, $http_response_header[0]);

                if($http_response_header[0] == "HTTP/1.1 200 OK")
                {
                    createItems($response, $asinBlacklist);
                    $i++;
                }
            }
        }

        if($lastKeywordIndex >= $scanRange['end'])
        {

            $keywordsArray = loadKeywords(); //ricarico parole con lista aggiornata
            $asinBlacklist = loadBlacklistedASIN(); //ricarico blacklist
            $scanRange = loadBalancer(); //ricarico range di scansione

            setLastKeywordIndex($scanRange['start']);       
            $lastKeywordIndex = $scanRange['start'];            
        }
        else
        {
            $lastKeywordIndex++;
            setLastKeywordIndex($lastKeywordIndex);
        }
    }
}


//single server
/*function indexItems()
{
    require('config.php');
    require_once('keywordsLoader.php');
    require_once('functions.php');
    
    initializeServerOnMainDB();
    $keywordsArray = loadKeywords();
    $lastKeywordIndex = intval(loadLastKeywordIndex());

    if(!isset($keywordsArray))
        indexItems();

    while(1)
    {
        echo "\n".$keywordsArray[$lastKeywordIndex]."\n";
        $i = 1;

        if(substr($keywordsArray[$lastKeywordIndex], 0, 5) != "dummy") //salto dummy
        {
            while($i <= $pages)
            {
                echo "  ".$keywordsArray[$lastKeywordIndex]."-".$i."\n";
                $requestUrl = createRequestUrl($keywordsArray[$lastKeywordIndex], $i);
                $response = file_get_contents($requestUrl);
                saveLog($keywordsArray[$lastKeywordIndex]."-".$i, $http_response_header[0]);

                if($http_response_header[0] == "HTTP/1.1 200 OK")
                {
                    createItems($response);
                    $i++;
                }
                else
                {
                    sleep(5);
                }
            }
            sleep(10);
        }

        if($lastKeywordIndex == count($keywordsArray))
        {
            setLastKeywordIndex(0);       
            $lastKeywordIndex = 0;
            $keywordsArray = loadKeywords(); //ricarico parole con lista aggiornata
        }
        else
        {
            setLastKeywordIndex($lastKeywordIndex);
            $lastKeywordIndex++;
        }
    }
}*/
?>