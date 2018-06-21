<?php
/* CLIENT */

function createRequestUrl($keywords, $page, $sort)
{
    // The region you are interested in
    require('config.php');

    $uri = "/onca/xml";

    $params = array(
        "Service" => "AWSECommerceService",
        "Operation" => "ItemSearch",
        "AWSAccessKeyId" => $access_key,
        "AssociateTag" => $associative_tag,
        "SearchIndex" => $category,
        "ResponseGroup" => "Images,ItemAttributes,Offers",
        "Keywords" => $keywords,
        "Condition" => "All",
        "ItemPage" => $page,
        "Sort" => $sort
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

    return $request_url;
}

function loadItems()
{
    require('config.php');
    require_once('item.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT * FROM products;");
    $conn->close();

    $array = array();

    while($row = $result->fetch_assoc())
    {
        $item = new item($row['ASIN'], $row['URL'], $row['Title'], $row['Price'], $row['UsedPrice'], $row['PartNumber']);
        $array[$row['ASIN']] = $item;
    }

    return $array;
}

function loadBlacklistedASIN()
{
    require('config.php');

    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT * FROM blacklist;");
    $conn->close();

    $array = array();

    while($row = $result->fetch_assoc())
    {
        $array[$row['ASIN']] = $row['ASIN'];
    }

    return $array;
}

function loadUsersAndGroup($premium = false)
{
    require('config.php');
    require_once('client.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    if($premium)
        $result = $conn->query("SELECT * FROM users_and_groups WHERE premium=1 ORDER BY rand();");
    else
        $result = $conn->query("SELECT * FROM users_and_groups WHERE premium=0 ORDER BY rand();");

    $conn->close();

    $array = array();

    while($row = $result->fetch_assoc())
    {
        $client = new client($row['id'], $row['discountPercentage'], $row['newProductNotification'], $row['whdNotification']);
        
        $array[$row['id']] = $client;
    }

    return $array;
}

function loadSingleProductClientsTracking($asin)
{
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT * FROM product_tracking WHERE ASIN = '".$asin."';");
    $conn->close();

    $array = array();

    while($row = $result->fetch_assoc())
    {       
        if(isPremium($id))
            $array[$row['id']] = $row['ASIN']; //funzionalità abilitata solo agli utenti premium
    }

    return $array;
}

function isPremium($id)
{
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT * FROM users_and_groups WHERE id = '".$id."' AND premium=1;");
    $conn->close();

    if(!$result || $result->num_rows == 0)
        return false;
    else
        return true;
}

function createItems($xmlResponse, $asinBlacklist)
{
    require_once('item.php');

    $indexedItems = loadItems();
    $xml = new DOMDocument();
    $xml->loadXML($xmlResponse);
    $items = $xml->getElementsByTagName("Items")[0];

    if(!isset($items))
        return;
        
    foreach ($items->getElementsByTagName("Item") as $item)    
    {
        $discount = null;
        $discountWHD = null;

        if(isset($item->getElementsByTagName("Request")[0])) //nessun risultato trovato
            return;
        
        $asin = $item->getElementsByTagName("ASIN")[0]->nodeValue;

        if(array_key_exists($asin, $asinBlacklist))
            return; // annullo è in blacklist

        $title = $item->getElementsByTagName("Title")[0]->nodeValue;

        if(isset($item->getElementsByTagName("LargeImage")[0]))
            $image = $item->getElementsByTagName("LargeImage")[0]->getElementsByTagName("URL")[0]->nodeValue;
        else
            $image = "https://images-na.ssl-images-amazon.com/images/G/01/nav2/dp/no-image-no-ciu._AA300_.gif";

        $newFound = false;
        $usedFound = false;
        $price = findPrice($asin, "New");
        $usedPrice = findPrice($asin, "Used");
        $isNewProduct = false;

        echo "    ".$asin."\n";
        $url = null;
        $moreOffersUrl = null;

        if($price != 0)
        {
            $url = $item->getElementsByTagName("DetailPageURL")[0]->nodeValue;
        }

        if($usedPrice != 0) // recupero link prodotti usati
        {
            $moreOffersUrl =  $item->getElementsByTagName("Offers")[0]->getElementsByTagName("MoreOffersUrl")[0]->nodeValue."&condition=used";
        }
        
        if(isset($item->getElementsByTagName("PartNumber")[0]))
            $partNumber = $item->getElementsByTagName("PartNumber")[0]->nodeValue;

        /*if($price != 0 && $url == "")
            //url non rilevata rifare*/

        if($price != 0 || $usedPrice != 0)
        {
            if(!array_key_exists($asin, $indexedItems)) //create new
            {
                if(isset($url))
                {
                    $item = new item($asin, $url, $title, $price, $usedPrice, $partNumber);
                    $isNewProduct = true;
                    $newProductType = "new";
                }
                
                if(isset($moreOffersUrl))
                {
                    if($newProductType == "new")
                    {
                        $item = new item($asin, $moreOffersUrl, $title, $price, $usedPrice, $partNumber);
                        $isNewProduct = true;
                        $newProductType = "new+used";
                    }
                    else
                    {
                        $item = new item($asin, $moreOffersUrl, $title, $price, $usedPrice, $partNumber);
                        $isNewProduct = true;
                        $newProductType = "used";
                    }
                    
                }

                $indexedItems[$asin] = $item;
                $item->saveToDb();
            }
            else //update prices
            {
                $oldPrice = $indexedItems[$asin]->getPrice();
                $oldUsedPrice = $indexedItems[$asin]->getUsedPrice();

                //echo "    Nuovo: ".$price." WHD: ".$usedPrice."\n";
                //Discount%=(Original Price - Sale price)/Original price*100
                if($oldPrice != 0) //era già disponibile
                {
                    if(isset($price)) //è ancora disponibile
                    {
                        if($oldPrice > $price) //nuovo è scontato
                        {
                            $discount = (($oldPrice-$price)*100)/$oldPrice;
                            $indexedItems[$asin]->setPrice($price);
                            updateDbPrice($asin, $price);
                        }
                        else if($oldPrice < $price) //nuovo non è scontato
                        {
                            $indexedItems[$asin]->setPrice($price);
                            updateDbPrice($asin, $price);
                        }
                    }
                }
                else //vecchio prezzo == 0 -> potrebbe essere tornato disponibile
                {
                    if(isset($price) && $price != 0) //se nuovo prezzo != 0 -> tornato disponibile
                    {
                        updateDbPrice($asin, $price);
                        $indexedItems[$asin]->setPrice($price);
                        $isNewProduct = true;
                        $newProductType = "new";
                    }
                    else //ancora non disponibile
                    {
                        updateDbPrice($asin, 0);
                        $indexedItems[$asin]->setPrice(0);
                    }
                }

                if(isset($usedPrice)) //c'è un pezzo usato
                {
                    if(($oldUsedPrice > $usedPrice) || ($oldUsedPrice == 0 && $usedPrice != 0)) //usato è scontato rispetto l'ultimo alert
                    {
                        if(isset($oldPrice)) //tendo a ricavare lo sconto dal prezzo del nuovo
                        {
                            $discountWHD = (($oldPrice-$usedPrice)*100)/$oldPrice;
                        }
                        else if(isset($oldUsedPrice)) //MA se non ho il prezzo del nuovo devo rifarmi al prezzo del whd
                        {
                            $discountWHD = (($oldUsedPrice-$usedPrice)*100)/$oldUsedPrice;
                        }
                        else
                        {
                            $discountWHD = null;
                        }

                        $indexedItems[$asin]->setUsedPrice($usedPrice);
                        updateDbUsedPrice($asin, $usedPrice);
                    }
                    else if($oldUsedPrice < $usedPrice) //costava meno prima
                    {
                        $indexedItems[$asin]->setUsedPrice($usedPrice);
                        updateDbUsedPrice($asin, $usedPrice);
                    }
                }
                else //usato non è disponibile
                {
                    $indexedItems[$asin]->setUsedPrice(0);
                    updateDbUsedPrice($asin, 0);
                }

                if(isset($discount) || isset($discountWHD)) //è nuovo o usato comunque è scontato
                {
                    if(isset($moreOffersUrl))
                    {
                        $moreOffersShortenUrl = shortenLink(replaceRefLink($moreOffersUrl));
                    }
                    
                    if(isset($url))
                    {
                        $shortenUrl = shortenLink(replaceRefLink($url));
                    }

                    if(isset($discount) && $discount > 0 && $price != 0)
                        $announceId = saveDataLog($url, $shortenUrl, $asin, $price, $oldPrice, $usedPrice, $oldUsedPrice, "DiscountNEW", "new", addslashes($title), $image, $partNumber, $discountNew, $discountWHD);
                    else if(isset($discountWHD) && $discountWHD > 0 && $usedPrice != 0)
                        $announceId = saveDataLog($moreOffersUrl, $moreOffersShortenUrl, $asin, $price, $oldPrice, $usedPrice, $oldUsedPrice, "DiscountWHD", "used", addslashes($title), $image, $partNumber, $discountNew, $discountWHD);
                    else
                    {
                        if(isset($url))
                            $announceId = saveDataLog($url, $shortenUrl, $asin, $price, $oldPrice, $usedPrice, $oldUsedPrice, "NoNotify", null, null, null, null);
                        else
                            $announceId = saveDataLog($moreOffersUrl, $moreOffersShortenUrl, $asin, $price, $oldPrice, $usedPrice, $oldUsedPrice, "NoNotify", null, null, null, null);
                    }

                    $clients = loadUsersAndGroup(true); //premium
                    sendNotificationDiscount($clients, $asin, $discount, $discountWHD, $oldPrice, $oldUsedPrice, $price, $usedPrice, $title, $partNumber, $shortenUrl, $moreOffersShortenUrl, $announceId, $image);
                    sendNotificationSingleProductTracking($asin, $discount, $discountWHD, $oldPrice, $oldUsedPrice, $price, $usedPrice, $title, $partNumber, $shortenUrl, $moreOffersShortenUrl, $image); //notifica tracciatura singola solo su prodotti nuovi
                }
                else
                {
                    if(isset($url))
                        $announceId = saveDataLog($url, $shortenUrl, $asin, $price, $oldPrice, $usedPrice, $oldUsedPrice, "NoNotify", null, null, null, null);
                    else
                        $announceId = saveDataLog($moreOffersUrl, $moreOffersShortenUrl, $asin, $price, $oldPrice, $usedPrice, $oldUsedPrice, "NoNotify", null, null, null, null);
                }
            }

            if($isNewProduct) //se è un prodotto appena trovato o tornato disponibile
            {
                $clients = loadUsersAndGroup(true); //premium
                if($newProductType == "new") //controllo se è un prodotto nuovo
                {
                    $shortenUrl = shortenLink(replaceRefLink($url));
                    $announceId = saveDataLog($url, $shortenUrl, $asin, $price, $oldPrice, $usedPrice, $oldUsedPrice, "NewProductFound", $newProductType, addslashes($title), $image, $partNumber);
                    sendNotificationNewProduct($clients, $asin, $shortenUrl, $title, $price, $usedPrice, $partNumber, $announceId, $image, $newProductType);
                }
                else //altrimenti prodotto whd e nuovo
                {
                    $shortenUrl = shortenLink(replaceRefLink($moreOffersUrl));
                    $announceId = saveDataLog($moreOffersUrl, $shortenUrl, $asin, $price, $oldPrice, $usedPrice, $oldUsedPrice, "NewProductFound", $newProductType, addslashes($title), $image, $partNumber);
                    sendNotificationNewProduct($clients, $asin, $shortenUrl, $title, $price, $usedPrice, $partNumber, $announceId, $image, $newProductType);
                }
            }
        }
        else //se non è disponibile -> la tolgo dal db nel caso ci fosse
        {
            if(array_key_exists($asin, $indexedItems)) //è nel db
            {
                $indexedItems[$asin]->deleteFromDb();
                unset($indexedItems[$asin]);
            }
        }
    }
}

function saveDataLog($url, $urlShort, $asin, $newPriceNew, $oldPriceNew, $newPriceWhd, $oldPriceWhd, $type, $type2 = null, $title = null, $imgUrl = null, $partNumber = null, $discountNew = null, $discountWHD = null)
{
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->query("INSERT INTO `logs` (`url`, `urlShort`, `asin`, `newPriceNew`, `oldPriceNew`, `newPriceWhd`, `oldPriceWhd`, `discountNew`, `discountWHD`, `type`, `type2`, `server`, `title`, `imgUrl`, `partNumber`) VALUES ('".$url."', '".$urlShort."', '".$asin."', '".$newPriceNew."', '".$oldPriceNew."', '".$newPriceWhd."', '".$oldPriceWhd."', '".$discountNew."', '".$discountWHD."', '".$type."', '".$type2."', '".$ServerName."', '".$title."', '".$imgUrl."', '".$partNumber."');");
    $id = $conn->insert_id;
    $conn->close();

    return $id;
}


function findPrice($asin, $type = "New")
{
    require('config.php');

    $uri = "/onca/xml";

    if($type == "New")
    {
        $params = array(
            "Service" => "AWSECommerceService",
            "Operation" => "ItemLookup",
            "AWSAccessKeyId" => $access_key,
            "AssociateTag" => $associative_tag,
            "ItemId" => $asin,
            "IdType" => "ASIN",
            "ResponseGroup" => "Offers",
            "Condition" => $type,
            "MerchantId" => "Amazon");
    }
    else
    {
        $params = array(
            "Service" => "AWSECommerceService",
            "Operation" => "ItemLookup",
            "AWSAccessKeyId" => $access_key,
            "AssociateTag" => $associative_tag,
            "ItemId" => $asin,
            "IdType" => "ASIN",
            "ResponseGroup" => "Offers",
            "Condition" => $type);
    }

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

    //echo $request_url;
    if($type == "New")
    {
        $price = compareAmazonPriceWithOtherPrimeSellers(extractPrice($request_url), $asin); //confronto prezzi di amazon con quelli di venditori prime
    }
    else
        $price = extractPrice($request_url); //in pratica solo amazon vende nel whd

    return $price;
}

function compareAmazonPriceWithOtherPrimeSellers($amazonPrice, $asin)
{
    require('config.php');

    $uri = "/onca/xml";

    $params = array(
        "Service" => "AWSECommerceService",
        "Operation" => "ItemLookup",
        "AWSAccessKeyId" => $access_key,
        "AssociateTag" => $associative_tag,
        "ItemId" => $asin,
        "IdType" => "ASIN",
        "ResponseGroup" => "Offers",
        "Condition" => "New");


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

    $otherSellerPrimePrice = extractPrice($request_url);

    //echo "OtherSellerPrimePrice: ".$otherSellerPrimePrice." Amazon Price: ".$amazonPrice."\n";

    if($otherSellerPrimePrice != 0 && $amazonPrice != 0)
    {
        if($otherSellerPrimePrice < $amazonPrice)
            return $otherSellerPrimePrice;
        else
            return $amazonPrice;
    }
    else if($otherSellerPrimePrice == 0 && $amazonPrice != 0)
    {
        return $amazonPrice;
    }
    else if($otherSellerPrimePrice != 0 && $amazonPrice == 0)
    {
        return $otherSellerPrimePrice;
    }
    else
    {
        return 0;
    }
}

function extractPrice($url)
{
    $response = file_get_contents($url);

    if($http_response_header[0] != "HTTP/1.1 200 OK")
    {
        sleep(1);
        return extractPrice($url);
    }

    $xml = new DOMDocument();
    $xml->loadXML($response);

    if(isset($xml->getElementsByTagName("Items")[0]))
        $item = $xml->getElementsByTagName("Items")[0]->getElementsByTagName("Item")[0];
    else
        return 0;
        
    if(isset($item->getElementsByTagName("Offers")[0]))
        $tree = $item->getElementsByTagName("Offers")[0]->getElementsByTagName("Offer")[0];
    else
        return 0;

    if(isset($tree))
    {
        if($tree->getElementsByTagName("OfferListing")[0]->getElementsByTagName("IsEligibleForSuperSaverShipping")[0]->nodeValue == "1")
        {
            if(isset($tree->getElementsByTagName("OfferListing")[0]->getElementsByTagName("SalePrice")[0]))
                $price = $tree->getElementsByTagName("OfferListing")[0]->getElementsByTagName("SalePrice")[0]->getElementsByTagName("Amount")[0]->nodeValue;
            else
                $price = $tree->getElementsByTagName("OfferListing")[0]->getElementsByTagName("Price")[0]->getElementsByTagName("Amount")[0]->nodeValue;
        }
        else
        {
            $price = 0;
        }
    }
    else
        $price = 0;

    return $price;
}

function sendNotificationSingleProductTracking($asin, $discount, $discountWHD, $oldPrice, $oldUsedPrice, $price, $usedPrice, $title, $partNumber, $shortenUrl, $moreOffersShortenUrl, $image) //notifica tracciatura singola solo su prodotti nuovi
{
    $newNotify = false;
    $whdNotify = false;

    if(intval($oldPrice) > intval($price) && intval($price) != 0)
    {     
        $newNotify = true;
    }

    if(intval($oldUsedPrice) > intval($usedPrice) && intval($usedPrice) != 0)
    {
        $whdNotify = true;
    }

    if($whdNotify || $newNotify)
    {
        $singleProductClientsTracking = loadSingleProductClientsTracking($asin);
        $notification = buildNotificationSingleProductTracking($asin, $newNotify, $whdNotify, $discount, $discountWHD, $oldPrice, $oldUsedPrice, $price, $usedPrice, $title, $partNumber, $shortenUrl, $moreOffersShortenUrl, $image);

        foreach($singleProductClientsTracking as $key => $value)
        {
            sendMessageRequest($key, $notification);
        }
    }
}

function sendNotificationDiscount($clients, $asin, $discount, $discountWHD, $oldPrice, $oldUsedPrice, $price, $usedPrice, $title, $partNumber, $shortenUrl, $moreOffersShortenUrl, $announceId, $image)
{
    $notificationNewAndWhd = buildNotificationDiscount($asin, true, true, $discount, $discountWHD, $oldPrice, $oldUsedPrice, $price, $usedPrice, $title, $partNumber, $shortenUrl, $moreOffersShortenUrl, $announceId, $image);
    $notificationOnlyWhd = buildNotificationDiscount($asin, false, true, $discount, $discountWHD, $oldPrice, $oldUsedPrice, $price, $usedPrice, $title, $partNumber, $shortenUrl, $moreOffersShortenUrl, $announceId, $image);
    $notificationOnlyNew = buildNotificationDiscount($asin, true, false, $discount, $discountWHD, $oldPrice, $oldUsedPrice, $price, $usedPrice, $title, $partNumber, $shortenUrl, $moreOffersShortenUrl, $announceId, $image);

    foreach($clients as $key => $value)
    {
        $newNotify = false;
        $whdNotify = false;

        if($value->getPercentage() <= $discount) //invio notifica nuovo
        {
            if($price != 0)
                $newNotify = true;
        }

        if($value->getPercentage() <= $discountWHD) //invio notifica usato
        {
            if($usedPrice != 0 && $value->whdNotification())
                $whdNotify = true;
        }

        if($newNotify && $whdNotify)
        {
            sendMessageRequest($key, $notificationNewAndWhd);
        }
        else if(!$newNotify && $whdNotify)
        {
            sendMessageRequest($key, $notificationOnlyWhd);
        }
        else if($newNotify && !$whdNotify)
        {
            sendMessageRequest($key, $notificationOnlyNew);
        }
    }
}

function sendNotificationNewProduct($clients, $asin, $shortenUrl, $title, $price, $usedPrice, $partNumber, $announceId, $image, $newProductType)
{
    $notification = buildNotificationNewProduct($asin, $shortenUrl, $title, $price, $usedPrice, $partNumber, $announceId, $image, $newProductType);

    foreach($clients as $key => $value)
    {
        if($value->newProductNotification()) //invio notifica nuovo
        {
            sendMessageRequest($key, $notification);
        }
    }
}

function buildNotificationDiscount($asin, $newNotify, $whdNotify, $discount, $discountWHD, $oldPrice, $oldUsedPrice, $price, $usedPrice, $title, $partNumber, $shortenUrl, $moreOffersShortenUrl, $announceId, $image)
{

    $notification = "<a href=\"".$image."\">&#160;</a>";

    if($newNotify)
    {
        $notification .= "<b>Nuova offerta trovata con sconto del ".round($discount, 0)."% [#".$announceId."]</b>";
        if($whdNotify)
        {
            $notification .= "\n<b>oppure dal Warehouse Deals con sconto del ".round($discountWHD, 0)."%</b>";
        }
    }
    else
    {
        $notification .= "<b>Nuova offerta trovata con sconto del ".round($discountWHD, 0)."% dal Warehouse Deals [#".$announceId."]</b>";
    }

    $notification .= "\n\n";
    $notification .= "📌 ".$title."\n\n";
    
    if($newNotify && !$whdNotify)
    {        
        if($shortenUrl == "")
            return;
        
        $notification .= "👉 ".$shortenUrl."\n\n";
    }
    else
    {
        if($shortenUrl == "")
            return;

        $notification .= "👉 ".$moreOffersShortenUrl."\n\n"; 
    }

    if($newNotify)
        $notification .= "💰 <b>NUOVO</b> ".priceConverter($oldPrice)."€ ➡️ <b>".priceConverter($price)."€</b>\n";
    if($whdNotify)
    {
        //if(isset($conditions))
        //    $notification .= "💰 <b>WHD</b> ".priceConverter($oldPrice)."€ ➡️ <b>".priceConverter($usedPrice)."€ || ".$conditions."</b>\n";
        //else
        $notification .= "💰 <b>WHD</b> ".priceConverter($oldPrice)."€ ➡️ <b>".priceConverter($usedPrice)."€</b>\n";
    }

    $notification .= "\nℹ️ ID Prodotto: ".$partNumber."\n";

    return urlencode($notification);
}

function buildNotificationNewProduct($asin, $shortenUrl, $title, $price, $usedPrice, $partNumber, $announceId, $image, $newProductType)
{
    $notification = "<a href=\"".$image."\">&#160;</a>";
    
    if(isset($announceId))
        $notification .= "<b>Prodotto trovato/Tornato disponibile [#".$announceId."]</b>";
    else
        $notification .= "<b>Prodotto trovato/Tornato disponibile</b>";

    $notification .= "\n\n";
    $notification .= "📌 ".$title."\n\n";
    $notification .= "👉 ".$shortenUrl."\n\n";
    
    if($newProductType == "new")
        $notification .= "💰 <b>NUOVO: </b> ".priceConverter($price)."€\n";
    else if($newProductType == "used")
        $notification .= "💰 <b>WHD: </b> ".priceConverter($usedPrice)."€\n";
    else
    {
        $notification .= "💰 <b>NUOVO: </b> ".priceConverter($price)."€\n";
        $notification .= "💰 <b>WHD: </b> ".priceConverter($usedPrice)."€\n";
    }

    $notification .= "\nℹ️ ID Prodotto: ".$partNumber."\n";

    return urlencode($notification);
}

function buildNotificationSingleProductTracking($asin, $newNotify, $whdNotify, $discount, $discountWHD, $oldPrice, $oldUsedPrice, $price, $usedPrice, $title, $partNumber, $shortenUrl, $moreOffersShortenUrl, $image)
{
    $notification = "<a href=\"".$image."\">&#160;</a>";
    $notification .= "<b>[".$asin."] Un prodotto che seguivi ha subito una riduzione di prezzo dall'ultima rilevazione</b>";

    $notification .= "\n\n";
    $notification .= "📌 ".$title."\n\n";
    if($newNotify)
    {
        $notification .= "<b>NUOVO</b>\n";
        $notification .= "👉 ".$shortenUrl."\n";
        $notification .= "Percentuale di sconto: <b>".round($discount, 0)."%</b>\n";
        $notification .= "💰 ".priceConverter($oldPrice)."€ ➡️ <b>".priceConverter($price)."€</b>\n";
    }
    if($whdNotify)
    {
        if($newNotify)
            $notification .= "\n";

        $notification .= "<b>Warehouse Deals</b>\n";
        $notification .= "👉 ".$moreOffersShortenUrl."\n";
        $notification .= "Percentuale di sconto: <b>".round($discountWHD, 0)."%</b>\n";
        $notification .= "💰 ".priceConverter($oldPrice)."€ ➡️ <b>".priceConverter($usedPrice)."€</b>\n";
    }
    $notification .= "\nℹ️ ID Prodotto: ".$partNumber."\n";

    return urlencode($notification);
}

function priceConverter($price)
{
    return substr_replace($price, ",", -2, 0);
}

function updateDbPrice($asin, $new_price) {
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    if(isset($new_price))
    {
        $conn->query("UPDATE products SET Price = ".$new_price." WHERE `ASIN` = '".$asin."';");
    }
    else
        $conn->query("UPDATE products SET Price = 0 WHERE `ASIN` = '".$asin."';");

    $conn->close();
      //update on db
}

function updateDbUsedPrice($asin, $new_usedPrice) {
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    if(isset($new_usedPrice))
        $conn->query("UPDATE products SET UsedPrice = ".$new_usedPrice." WHERE `ASIN` = '".$asin."';");
    else
        $conn->query("UPDATE products SET UsedPrice = 0 WHERE `ASIN` = '".$asin."';");

    $conn->close();
    //update on db
}

function sendNotificationToStandardUser()
{
    $standardClients = loadUsersAndGroup(false);
    $offersToSend = getOffersByTime();
    $lastId = null;

    if(!isset($offersToSend))
        return;

    while($row = $offersToSend->fetch_assoc())
    {
        if($row['type'] == "NewProductFound")
        {
            sendNotificationNewProduct($standardClients, $row['asin'], $row['urlShort'], $row['title'], $row['newPriceNew'], $row['newPriceWhd'], $row['partNumber'], $row['id'], $row['imgUrl'], $row['type2']);
        }
        else if($row['type'] == "DiscountNEW" || $row['type'] == "DiscountWHD")
        {
            sendNotificationDiscount($standardClients, $row['asin'], $row['discountNew'], $row['discountWHD'], $row['oldPriceNew'], $row['oldPriceWhd'], $row['newPriceNew'], $row['newPriceWhd'], $row['title'], $row['partNumber'], $row['urlShort'], $row['urlShort'], $row['id'], $row['imgUrl']);
        }
        $lastId = $row['id'];
    }

    if(isset($lastId))
        updateLastOfferSent($lastId);
}

function updateLastOfferSent($lastId)
{
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->query("UPDATE `settings` SET `value`='".$lastId."' WHERE `setting`='lastDelayedOfferSent';");
    $conn->close();
}

function getOffersByTime()
{
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT * FROM logs WHERE time <= DATE_SUB(NOW() , INTERVAL 10 MINUTE) AND id > ".$lastOfferId." AND type != 'NoNotify';");
    $conn->close();

    if(!$result || $result->num_rows == 0)
        return null; //nessuna offerta trovata da inviare;

    return $result;
}

//bit.ly
/*function shortenLink($url)
{
    require_once('bitly.php');
    $params = array();
    $params['access_token'] = '6856c766c65c3deca29ddffc01a75e368594cb17';
    $params['longUrl'] = $url;
    $params['domain'] = 'bit.ly';
    $results = bitly_get('shorten', $params);
    return $results['data']['url'];
}*/

//goo.gl

function shortenLink($url)
{
    return get_tiny_url($url);
    /*$int = rand(0, 1);

    if($int == 0)
        return get_tiny_url($url);
    else
        return googleShort($url);*/
}

function googleShort($url)
{
    require_once('Googl.class.php');
    $googl = new Googl();
    $shortenUrl = $googl->shorten($url);

    unset($googl);

    return $shortenUrl;
}

//gets the data from a URL  
function get_tiny_url($url)  {  
	$ch = curl_init();  
	$timeout = 5;  
	curl_setopt($ch,CURLOPT_URL,'http://tinyurl.com/api-create.php?url='.$url);  
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);  
	curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);  
	$data = curl_exec($ch);  
	curl_close($ch);  
	return $data;  
}

function loadBalancer()
{
    require_once('keywordsLoader.php');
    require('config.php');
    $keywordsArray = loadKeywords();
    $keywordsSize = count($keywordsArray);

    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT ServerId FROM last_keyword_index WHERE ServerName = '".$ServerName."' ORDER BY ServerId ASC;"); //MAIN DEVE SEMPRE AVERE SERVERID 1
    $conn->close();
    $serverId = $result->fetch_assoc()['ServerId'];

    //main deve avere il 40% del carico poichè ha maggiore potenza in quanto usa il ref principale

    //calcolo assegnazione al server main
    if($mode == "balanced")
    {
        $keywordPerServer = ceil((40*$keywordsSize)/100); //40% al server main
        $startKeywordId = 0;
        $endKeywordId = $startKeywordId+$keywordPerServer;
        $remainingWords = $keywordsSize-$endKeywordId;
        $startingKeywordForOtherServers = $endKeywordId;

        if($ServerName != $mainServerName)
        {
            $activeServer = $activeServer-1; //Main è già assegnato quindi un server in meno, spartisco il rimanente 60%
            $keywordPerServer = ceil($remainingWords/$activeServer);
            $startKeywordId = (($serverId-2)*$keywordPerServer)+$startingKeywordForOtherServers;
            $endKeywordId = $startKeywordId+$keywordPerServer;
        }
    }
    else
    {
        $keywordPerServer = ceil($keywordsSize/$activeServer);
        $startKeywordId = (($serverId-1)*$keywordPerServer);
        $endKeywordId = $startKeywordId+$keywordPerServer;
    }
    

    echo "SERVER: ".$ServerName."\nKeywordPerServer: ".$keywordPerServer." startKeywordId: ".$startKeywordId." endKeywordId: ".$endKeywordId;

    return array("start" => $startKeywordId, "end" => $endKeywordId);
}

function initializeServerOnMainDB()
{
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT * FROM last_keyword_index WHERE ServerName = '".$ServerName."';");
    if($result->num_rows == 0)
        $conn->query("INSERT INTO last_keyword_index (`ServerName`) VALUES ('".$ServerName."');"); //inizializza
    $conn->close();
}

function setLastKeywordIndex($last)
{
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->query("UPDATE last_keyword_index SET lki = ".$last." WHERE ServerName = '".$ServerName."';");
    $conn->close();
}

function loadLastKeywordIndex()
{
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT * FROM last_keyword_index WHERE ServerName = '".$ServerName."';");
    $conn->close();

    return $result->fetch_assoc()['lki'];
}

function saveLog($keyword, $response)
{
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("INSERT INTO `search_logs` (`keyword`, `response`) VALUES ('".$keyword."', '".$response."');");
    $conn->close();
}

function loadSettings($servername, $username, $password, $dbname)
{
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT * FROM settings;");
    $conn->close();

    $settingsArray = array();

    if(isset($result))
    {
        while($row = $result->fetch_assoc())
        {
            $settingsArray[$row['setting']] = $row['value'];
        }
    }
    else
    {
        return loadSettings($servername, $username, $password, $dbname);
    }

    return $settingsArray;
}

function sendMessageRequest($chat_id, $message)
{
    require('config.php');

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => "http://".$site."/amazonpricetracker/main/amazonTrackerReceiver.php?chatId=".$chat_id."&message=".$message
    ));

    curl_exec($curl);
    curl_close($curl);
}

function replaceRefLink($url)
{
    require('config.php');
    $linkSplitted = explode('&', $url);

    $newRefLink = "";
    for($i = 0; $i < count($linkSplitted); $i++)
    {
        if(substr($linkSplitted[$i], 0, 3) == "tag")
        {
            $newRefLink .= "tag=".$refMainTag."&";
        }
        else
        {
            $newRefLink .= $linkSplitted[$i]."&";
        }
    }

    return $newRefLink;
}
?>