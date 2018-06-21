<?php

class client 
{
    function __construct($id, $percentge, $newProductNotification, $whdNotification) 
    {		
        $this->id = $id;
        $this->percentage = $percentge;
        $this->newProductNotification = $newProductNotification;
        $this->whdNotification = $whdNotification;
    }

    function getId() {		
        return $this->id;
    }

    function getPercentage() {		
        return $this->percentage;		
    }

    function newProductNotification() {		
        if($this->newProductNotification == 1)
            return true;
        else
            return false;
    }

    function whdNotification() {		
        if($this->whdNotification == 1)
            return true;
        else
            return false;
    }

    function isGroup() {		
        if(substr($this->id, 0, 1) == '-')
            return true;
        else
            return false;		
    }
}	


?>