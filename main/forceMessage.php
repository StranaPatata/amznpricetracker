<?php

function forceSendMessage($messageId, $productType)
{
    require(__DIR__.'/../common/config.php');
    require_once(__DIR__.'/../common/functions.php');

    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT * FROM logs WHERE id = '".$messageId."';");

    if(!$result)
        return;

    $data = $result->fetch_assoc();

    $uri = "/onca/xml";

    $params = array(
        "Service" => "AWSECommerceService",
        "Operation" => "ItemSearch",
        "AWSAccessKeyId" => $access_key,
        "AssociateTag" => $associative_tag,
        "SearchIndex" => "All",
        "ResponseGroup" => "Images,ItemAttributes",
        "Keywords" => $data['asin']
    );

    // Set current timestamp if not set
    if (!isset($params["Timestamp"])) {
        $params["Timestamp"] = gmdate('Y-m-d\TH:i:s\Z');
    }

    // Sort the parameters by key
    ksort($params);

    $pairs = array();

    foreach ($params as $key => $value) {
        array_push($pairs, rawurlencode($key)."=".rawurlencode($value));
    }

    // Generate the canonical query
    $canonical_query_string = join("&", $pairs);

    // Generate the string to be signed
    $string_to_sign = "GET\n".$endpoint."\n".$uri."\n".$canonical_query_string;

    // Generate the signature required by the Product Advertising API
    $signature = base64_encode(hash_hmac("sha256", $string_to_sign, $secret_key, true));

    // Generate the signed URL
    $request_url = 'http://'.$endpoint.$uri.'?'.$canonical_query_string.'&Signature='.rawurlencode($signature);

    $response = file_get_contents($request_url);

    if($http_response_header[0] != "HTTP/1.1 200 OK")
    {
        sendMessage(199685595, "Riprovo invio");
        forceSendMessage($messageId, $productType);
        return;
    }

    $xml = new DOMDocument();
    $xml->loadXML($response);

    $item = $xml->getElementsByTagName("Items")[0]->getElementsByTagName("Item")[0];

    if(!isset($item))
    {
        sendMessageRequest(199685595, "Errore invio");
        return;
    }

    if(isset($item->getElementsByTagName("LargeImage")[0]))
        $image = $item->getElementsByTagName("LargeImage")[0]->getElementsByTagName("URL")[0]->nodeValue;
    else
        $image = "https://images-na.ssl-images-amazon.com/images/G/01/nav2/dp/no-image-no-ciu._AA300_.gif";

    if(isset($item->getElementsByTagName("PartNumber")[0]))
        $partNumber = $item->getElementsByTagName("PartNumber")[0]->nodeValue;

    $title = $item->getElementsByTagName("Title")[0]->nodeValue;

    //devo recuperare title partNumber image

    $shortenUrl = shortenLink(replaceRefLink($data['url']));

    if($productType == "used")
    {
        $notification = buildNotificationNewProduct($data['asin'], $shortenUrl, $title, $price, $data['newPriceWhd'], $partNumber, $messageId, $image, $productType);
    }
    else
        $notification = buildNotificationNewProduct($data['asin'], $shortenUrl, $title, $data['newPriceNew'], $usedPrice, $partNumber, $messageId, $image, $productType);

    $result2 = $conn->query("SELECT id FROM users_and_groups WHERE newProductNotification = 0;");
    $conn->close();

    while($row = $result2->fetch_assoc())
    {
        sendMessageRequest($row['id'], $notification);
    }
}

function forceSendMessageUnindexedItem($asin, $productType, $price)
{
    require(__DIR__.'/../common/config.php');
    require_once(__DIR__.'/../common/functions.php');

    $uri = "/onca/xml";

    $params = array(
        "Service" => "AWSECommerceService",
        "Operation" => "ItemSearch",
        "AWSAccessKeyId" => $access_key,
        "AssociateTag" => $associative_tag,
        "SearchIndex" => "All",
        "ResponseGroup" => "Images,ItemAttributes,Offers",
        "Keywords" => $asin,
        "Condition" => "All",
        "ItemPage" => 1,
        "Sort" => $sort
    );

    /*$params = array(
        "Service" => "AWSECommerceService",
        "Operation" => "ItemSearch",
        "AWSAccessKeyId" => $access_key,
        "AssociateTag" => $associative_tag,
        "SearchIndex" => "All",
        "ResponseGroup" => "Images,ItemAttributes,Offers",
        "Keywords" => $asin
    );*/

    // Set current timestamp if not set
    if (!isset($params["Timestamp"])) {
        $params["Timestamp"] = gmdate('Y-m-d\TH:i:s\Z');
    }

    // Sort the parameters by key
    ksort($params);

    $pairs = array();

    foreach ($params as $key => $value) {
        array_push($pairs, rawurlencode($key)."=".rawurlencode($value));
    }

    // Generate the canonical query
    $canonical_query_string = join("&", $pairs);

    // Generate the string to be signed
    $string_to_sign = "GET\n".$endpoint."\n".$uri."\n".$canonical_query_string;

    // Generate the signature required by the Product Advertising API
    $signature = base64_encode(hash_hmac("sha256", $string_to_sign, $secret_key, true));

    // Generate the signed URL
    $request_url = 'http://'.$endpoint.$uri.'?'.$canonical_query_string.'&Signature='.rawurlencode($signature);

    $response = file_get_contents($request_url);

    if($http_response_header[0] != "HTTP/1.1 200 OK")
    {
        sendMessage(199685595, "Riprovo invio");
        if(isset($price))
            forceSendMessageUnindexedItem($asin, $productType, $price);
        else
            forceSendMessageUnindexedItem($asin, $productType);
        return;
    }

    $xml = new DOMDocument();
    $xml->loadXML($response);

    $item = $xml->getElementsByTagName("Items")[0]->getElementsByTagName("Item")[0];

    if(!isset($item))
    {
        sendMessageRequest(199685595, "Errore nell'invio");
        return;
    }

    if(isset($item->getElementsByTagName("LargeImage")[0]))
        $image = $item->getElementsByTagName("LargeImage")[0]->getElementsByTagName("URL")[0]->nodeValue;
    else
        $image = "https://images-na.ssl-images-amazon.com/images/G/01/nav2/dp/no-image-no-ciu._AA300_.gif";

    if(isset($item->getElementsByTagName("PartNumber")[0]))
        $partNumber = $item->getElementsByTagName("PartNumber")[0]->nodeValue;

    $title = $item->getElementsByTagName("Title")[0]->nodeValue;

    if($productType == "used")
    {
        if(!isset($price))
            $price = findPrice($asin, "Used");

        $moreOffersUrl =  $item->getElementsByTagName("Offers")[0]->getElementsByTagName("MoreOffersUrl")[0]->nodeValue."&condition=used";
        $notification = buildNotificationNewProduct($asin, shortenLink(replaceRefLink($moreOffersUrl)), $title, null, $price, $partNumber, $messageId, $image, $productType);
    }
    else
    {
        if(!isset($price))
            $price = findPrice($asin, "New");

        $url = $item->getElementsByTagName("DetailPageURL")[0]->nodeValue;
        $notification = buildNotificationNewProduct($asin, shortenLink(replaceRefLink($url)), $title, $price, null, $partNumber, $messageId, $image, $productType);
    }

    $conn = new mysqli($servername, $username, $password, $dbname);
    $result2 = $conn->query("SELECT id FROM users_and_groups;");
    $conn->close();

    while($row = $result2->fetch_assoc())
    {
        sendMessageRequest($row['id'], $notification);
    }
}

?>