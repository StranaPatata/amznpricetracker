<?php
getTitleByAsin("B01MS79QSP");

function getTitleByAsin($asin)
{
    require(__DIR__.'/../common/config.php');

    $uri = "/onca/xml";

    $params = array(
        "Service" => "AWSECommerceService",
        "Operation" => "ItemLookup",
        "AWSAccessKeyId" => $access_key,
        "AssociateTag" => $associative_tag,
        "ItemId" => $asin,
        "IdType" => "ASIN",
        "ResponseGroup" => "ItemAttributes"
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
        return getTitleByAsin($asin);
    }

    $xml = new DOMDocument();
    $xml->loadXML($response);

    $item = $xml->getElementsByTagName("Items")[0]->getElementsByTagName("Item")[0];

    $title = $item->getElementsByTagName("ItemAttributes")[0]->getElementsByTagName("Title")[0]->nodeValue;

    return $title;
}

?>