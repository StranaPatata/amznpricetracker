<?php
function getItemConditions($url, $counter = 0)
{
    $options = array(
        'http'=> array(
          'method'=>"GET",
          'header'=>"Accept-language: en\r\n" .
                    "Cookie: foo=bar\r\n" .  // check function.stream-context-create on php.net
                    "User-Agent: Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36\r\n" // i.e. An iPad 
        )
    );

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if(!isset($url))
    {
        return;
    }

    if($http_response_header[0] != "HTTP/1.0 200 OK")
    {
        if($counter >= 50)
            return null;
        else
            return getItemConditions($url, $counter+1);
    }

    $dom = new DOMDocument;
    @$dom->loadHTML($response);

    $xpath = new DOMXPath($dom);
              //*[@id=\"olpOfferList\"]/div/div/div[2]/div[2]/div[1]/span
    $query = "//*[@id=\"olpOfferList\"]/div/div/div[2]/div[2]/div[1]/span";
    $entries = $xpath->query($query);


    //echo $response;

    //echo "INSERT INTO `parser_log_test` (`link`, `condition`, `condition1`, `html`) VALUES ('".$url."', '".trim(preg_replace('/\s\s+/', ' ', $entries[0]->nodeValue))."', '".$entries[0]->nodeValue."', '".$response."');";

    $conditions = trim(preg_replace('/\s\s+/', ' ', $entries[0]->nodeValue));
    
    if(!isset($conditions) || ($conditions == "") || (strlen($conditions) == 0))
    {
        sleep(5);
        return getItemConditions($url);
    }

    return trim(preg_replace('/\s\s+/', ' ', $entries[0]->nodeValue));
}



?>