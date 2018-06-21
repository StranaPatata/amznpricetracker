<?php

class item 
{
    function __construct($asin, $url, $title, $price, $usedPrice, $partNumber) {		
        $this->asin = $asin;
        $this->url = $url;
        $this->title = $title;
        $this->price = $price;
        $this->usedPrice = $usedPrice;
        $this->partNumber = $partNumber;
    }	

    function setPrice($new_price)
    {
        $this->price = $new_price;
    }

    function setUsedPrice($new_usedPrice)
    {
        $this->price = $new_usedPrice;
    }

    function getPrice() {		
          return $this->price;		
    }
    
    function getUsedPrice() {		
        return $this->usedPrice;		
    }

    function getAsin() {		
        return $this->asin;		
    }

    function getUrl() {		
        return $this->url;		
    }

    function getTitle() {		
        return $this->title;		
    }

    function getPartNumber() {		
        return $this->partNumber;		
    }

    function saveToDb()
    {
        require('config.php');
        $conn = new mysqli($servername, $username, $password, $dbname);
        $conn->query("INSERT INTO products (`ASIN`,`URL`,`Title`,`Price`,`UsedPrice`,`PartNumber`) VALUES ('".addslashes($this->asin)."','".addslashes($this->url)."','".addslashes($this->title)."','".$this->price."','".$this->usedPrice."','".addslashes($this->partNumber)."');");
        $conn->close();
    }

    function deleteFromDb()
    {
        require('config.php');
        $conn = new mysqli($servername, $username, $password, $dbname);
        $conn->query("DELETE FROM `products` WHERE  `ASIN`='".addslashes($this->asin)."';");
        $conn->close();
    }
}
?>