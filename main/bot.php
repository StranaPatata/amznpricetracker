<?php
define("Telegram", "https://api.telegram.org/botBOT_KEY_ID"); 

$input = file_get_contents("php://input");
$updates = json_decode($input, true);

$photo = $updates['message']['photo'];
$message = $updates['message']['text'];
$successPayment = $updates['message']['successful_payment'];
$pre_checkout_query = $updates['pre_checkout_query'];

$originalMessage = $updates['message']['reply_to_message'];
$originalMessageId = $updates['message']['reply_to_message']['message_id'];
$originalUser = $updates['message']['reply_to_message']['from'];
$originalUserId = $updates['message']['reply_to_message']['from']['id'];

$message_id = $updates['message']['message_id'];
$chat = $updates['message']['chat'];
$chat_title = $updates['message']['chat']['title'];
$chat_id = $updates['message']['chat']['id'];
$chatType = $updates['message']['chat']['type'];
$user = $updates['message']['from']; //get user
$user_id = $updates['message']['from']['id']; //not used yet
$user_username = $updates['message']['from']['username']; //username @test
$user_firstname = $updates['message']['from']['first_name'];

$callback = $updates['callback_query'];
$callback_id = $callback['id'];
$callback_userId = $callback['message']['from']['id'];
$callback_chatId = $callback['message']['chat']['id'];
$callback_data = $callback['data'];
$callback_msgId = $callback['message']['message_id'];
$newChatMembers = $updates['message']['new_chat_members'];
$entireMessage = $message;
$message = explode(' ',trim($message));

require(__DIR__.'/../common/functions.php');

if(isset($pre_checkout_query['id']))
{
    answerPreCheckoutQuery($pre_checkout_query['id'], true);
}

if(isset($successPayment)) //pagamento andato a buon fine - messaggio inviato dall'utente che ha pagato
{
    if($successPayment['total_amount'] == "1500")
        upgradeToPremium($user_id, "permanent");
    else
        upgradeToPremium($user_id, "normal");

    setStatus($user_id, null);
}

//require(__DIR__.'/../common/functions.php');

callback($callback_data, $callback_id, $callback_userId, $callback_chatId, $callback_msgId);

if(!isPremium($chat_id))
{
    $inline_keyboard = array(
        array(array("text" => "🔰 Passa a premium per 1 mese", "callback_data" => "premium1")),            
        array(array("text" => "🔰 Passa a premium per sempre", "callback_data" => "premium2")));
    $text = "Dal 10/05 solo gli utenti premium possono accedere al bot";
    sendMessageWithButtons($chat_id, urlencode($text), $inline_keyboard);
    return;
}

if($chatType == "group" || $chatType == "supergroup") //abilitato solo nelle chat di gruppo
{
    if(isBanned($chat_id)) //controllo se intero gruppo è bannato
    {
        sendMessage($chat_id, urlencode("Il tuo accesso al bot è stato disabilitato, per maggiori info contatta un amministratore"));
        return;
    }

    if(isBanned($user_id)) //controllo se l'utente nel gruppo è stato bannato
    {
        sendMessage($chat_id, urlencode("Il tuo accesso al bot è stato disabilitato, per maggiori info contatta un amministratore"));
        return;
    }

    if($message[0] == "/impostazioni")
    {
        if(isFounder($chat_id, $user_id) || isAdmin($chat_id, $user_id))
        {
            $inline_keyboard = array(
                array(array("text" => "🛒 Prodotti tornati disponibili/Nuove aggiunte", "callback_data" => "notificaNuoviProdotti")),            
                array(array("text" => "💲 Warehouse Deals", "callback_data" => "notificaWHD")));

            
            $state = "<b>Standard</b>";
                //array_push($inline_keyboard, array(array("text" => "Passa a premium 🔰", "callback_data" => "premium")));

            sendMessageWithButtons($chat_id, urlencode("⚙️ Pannello gestione notifiche\nLa percentuale di sconto impostata è <b>".getPercentage($chat_id)."%</b>\nStato utente: ".$state."\n\nSeleziona quale notifiche vuoi attivare/disattivare oppure visualizza la lista dei prodotti seguiti"), $inline_keyboard);
        }
    }
    else if($message[0] == "/percentuale")
    {     
        if(isFounder($chat_id, $user_id) || isAdmin($chat_id, $user_id))
        {
            if(isset($message[1]) && is_numeric($message[1]) && $message[1] <= 100 && $message[1] >= 0)
            {
                setPercentage($message[1], $chat_id);
                sendMessage($chat_id, urlencode("Saranno notificati tutti i prodotti con sconto uguale o maggiore al ".$message[1]."%"));
            }
            else
                sendMessage($chat_id, urlencode("Valore percentuale non valido"));
        }
        else
            sendMessage($user_id, urlencode("Non hai i permessi necessari"));
    }
    /*else if($message[0] == "/traccia") funzione disabilitata nei gruppi
    {
        if(isPremium($chat_id))
        {
            if(isFounder($chat_id, $user_id) || isAdmin($chat_id, $user_id))
            {
                sendMessage($chat_id, urlencode("Inserisci gli ASIN da tracciare, puoi inviare anche più di un ASIN per volta, ogni ASIN deve essere separato da uno spazio"));
                setStatus($user_id, "in attesa di asin per tracciatura - gruppo");
            }
            else
                sendMessage($user_id, urlencode("Non hai i permessi necessari"));
        }
        else
        {
            sendMessage($chat_id, urlencode("Solo gli utenti premium possono usare questa funziona, passa a premium ora!"));
        }
    }
    else if(isset($message[0]) && getStatus($user_id) == "in attesa di asin per tracciatura - gruppo")
    {
        setStatus($user_id, "processando ASIN");
        for($i = 0; $i < count($message); $i++)
        {
            if(substr($message[$i], 0, 2) == 'B0')
                setProductTracking($message[$i], $chat_id, true);
            else
                sendMessage($chat_id, urlencode($message[$i]."non valido, quindi non inserito"));
        }
        sendMessage($chat_id, urlencode("ASIN caricati, saranno notificate eventuali variazioni di prezzo"));
        setStatus($user_id, null);
    }
    else if($message[0] == "/smetti")
    {
        if(isFounder($chat_id, $user_id) || isAdmin($chat_id, $user_id))
        {
            if(isset($message[1]))
            {
                setProductTracking($message[1], $chat_id, false);
                sendMessage($chat_id, urlencode("Il prodotto con ASIN ".$message[1]." non sarà più tracciato ne notificato dal bot"));
            }
            else
                sendMessage($chat_id, urlencode("Nessun ASIN inserito\n Esempio di ASIN: B06XNRQHG4 [/smetti B06XNRQHG4]"));
        }
        else
            sendMessage($user_id, urlencode("Non hai i permessi necessari"));
    }*/
    else if($message[0] == "/feedback")
    {
        if(isFounder($chat_id, $user_id) || isAdmin($chat_id, $user_id))
        {
            sendMessage($chat_id, urlencode("Comando supportato solo in privato."));
        }
    }

    if(isFounder($chat_id, $user_id) || isAdmin($chat_id, $user_id))
    {
        if(isset($user_username))
            updateUsernameGroupAdmin($chat_id, $user_username);

        updateUsername($chat_title, $chat_id);
    }
}
else
{
    if(isBanned($user_id))
    {
        sendMessage($user_id, urlencode("Il tuo accesso al bot è stato disabilitato, per maggiori info contatta un amministratore"));
        return;
    }

    if($message[0] == "/annulla")
    {
        setStatus($user_id, null);
        sendMessage($chat_id, urlencode("Procedura annullata"));
    }

    if(getAdminLevel($user_id) == '3') //comandi admin
    {
        if($message[0] == "/keyword")
        {
            if(isset($message[1]))
            {
                array_shift($message);
                $string = "";
                for($i = 0; $i < count($message)-1; $i++)
                {
                    $string .= $message[$i]." ";
                }
                $string .= $message[$i];

                addSearchKeyword($string);
                sendMessage($chat_id, urlencode("Parola chiave \"".$string."\" aggiunta con successo"));
            }
        }
        else if($message[0] == "/globale")
        {
            setStatus($user_id, "in attesa di messaggio globale");
            sendMessage($chat_id, urlencode("Inserisci messaggio da inviare"));
        }
        else if(getStatus($user_id) == "in attesa di messaggio globale")
        {
            setStatus($user_id, "inviando messaggio");
            sendGlobalMessage(urlencode($entireMessage));
            setStatus($user_id, null);
        }
        else if($message[0] == "/ban")
        {
            if(isset($message[1]))
            {
                if(ctype_digit($message[1])) //id diretto
                {
                    ban($message[1]);
                }
                else //cerco id dal db
                {
                    $id = getUserIdByUsername($message[1]);
                    if(isset($id))
                        ban($id);
                }               
            }
        }
        else if($message[0] == "/test")
        {
            sendMessage($chat_id, urlencode("Risulti avere i privilegi amministrativi su questo bot, il tuo livello è <b>".getAdminLevel($user_id)."</b>"));
        }
        else if($message[0] == "/keyword_list")
        {
            $arrayKeywords = getKeywordList();
            $stringArray = array();
            $j = 0;
            //sendMessage($chat_id, urlencode("aaaa"));
            for($i = 0; $i < count($arrayKeywords); $i++)
            {
                $stringArray[$j] .= $i.") ".$arrayKeywords[$i]."\n";
                
                if(($i % 20) == 0) //ogni 20 parole creo nuovo messaggio
                    $j++;
            }

            for($i = 0; $i < count($stringArray); $i++)
                sendMessage($chat_id, urlencode($stringArray[$i]));
        }
        else if($message[0] == "/forza_invio")
        {
            setStatus($user_id, "in attesa di forzatura invio");
            sendMessage($user_id, urlencode("Inserisci dati per /forza_invio"));
        }
        else if(getStatus($user_id) == "in attesa di forzatura invio")
        {
            setStatus($user_id, "inviando messaggio");
            if(getLastCommand() != $entireMessage)
            {
                if(isset($message[0]) && is_numeric($message[0]))
                {
                    require('forceMessage.php');
                    if(isset($message[1]))
                    {
                        saveLastCommand($entireMessage);
                        forceSendMessage($message[0], $message[1]);
                        sendMessage(199685595, urlencode("Invio ".$message[0]." terminato"));
                    }
                    else
                    {
                        sendMessage($chat_id, urlencode("Secondo parametro non valido"));
                    }
                }
                else
                {
                    sendMessage($chat_id, urlencode("Primo parametro non valido"));
                }
            }
            setStatus($user_id, null);
        }
        else if($message[0] == "/cerca_log")
        {
            if(isset($message[1]))
            {
                $id = getLatestLogId($message[1]);
                sendMessage($chat_id, urlencode("L'ultimo log per questo asin è #".$id));
            }
            else
                sendMessage($chat_id, urlencode("Parametro non valido"));
        }
        else if($message[0] == "/invia")
        {
            setStatus($user_id, "in attesa di forzatura invio con asin");
            sendMessage($user_id, urlencode("Inserisci dati per /invia"));
        }
        else if(getStatus($user_id) == "in attesa di forzatura invio con asin")
        {
            if(getLastCommand() != $entireMessage)
            {
                if(isset($message[0]))
                {
                    require('forceMessage.php');

                    if(isset($message[1]))
                    {
                        saveLastCommand($entireMessage);
                        if(isset($message[2]))
                        {
                            forceSendMessageUnindexedItem($message[0], $message[1], $message[2]);
                            sendMessage(199685595, urlencode("Invio ".$message[0]." terminato"));
                        }
                        else
                        {
                            forceSendMessageUnindexedItem($message[0], $message[1]);
                            sendMessage(199685595, urlencode("Invio ".$message[0]." terminato"));
                        }
                    }
                    else
                    {
                        sendMessage($chat_id, urlencode("Secondo parametro non valido"));
                    }
                }
                else
                {
                    sendMessage($chat_id, urlencode("Primo parametro non valido"));
                }
            }
        }
        else if($message[0] == "/rispondi")
        {
            if(isset($message[1]))
            {
                $string = $message[2];
                for($i = 3; $i < count($message); $i++)
                {
                    $string .= " ".$message[$i];
                }

                sendMessageToUser($message[1], urlencode($string));
            }
        }
    }

    if($message[0] == "/impostazioni")
    {
        $inline_keyboard = array(
            array(array("text" => "🛒 Prodotti tornati disponibili/Nuove aggiunte", "callback_data" => "notificaNuoviProdotti")),            
            array(array("text" => "💲 Warehouse Deals", "callback_data" => "notificaWHD")));
        
        if(isPremium($user_id))  
        {
            $state = "<b>PREMIUM</b>🔰";
            $endDate = date("d/m/Y", strtotime(getPremiumEndDate($user_id)));
            array_push($inline_keyboard, array(array("text" => "📋 Lista dei prodotti seguiti", "callback_data" => "listaTracciature")));
            if(isPremiumLifetime($user_id))
                if(isPremiumTop20($user_id))
                    $text = urlencode("⚙️ Pannello gestione notifiche\nLa percentuale di sconto impostata è <b>".getPercentage($chat_id)."%</b>\nStato utente: ".$state."\nData scadenza Premium: <b>TOP20</b>\n\nSeleziona quale notifiche vuoi attivare/disattivare oppure visualizza la lista dei prodotti seguiti");
                else
                    $text = urlencode("⚙️ Pannello gestione notifiche\nLa percentuale di sconto impostata è <b>".getPercentage($chat_id)."%</b>\nStato utente: ".$state."\nData scadenza Premium: <b>LIFETIME</b>\n\nSeleziona quale notifiche vuoi attivare/disattivare oppure visualizza la lista dei prodotti seguiti");
            else
                $text = urlencode("⚙️ Pannello gestione notifiche\nLa percentuale di sconto impostata è <b>".getPercentage($chat_id)."%</b>\nStato utente: ".$state."\nData scadenza Premium: ".$endDate."\n\nSeleziona quale notifiche vuoi attivare/disattivare oppure visualizza la lista dei prodotti seguiti");
        }
        else
        {
            $state = "<b>Standard</b>";
            array_push($inline_keyboard, array(array("text" => "🔰 Passa a premium per 1 mese", "callback_data" => "premium1")));
            array_push($inline_keyboard, array(array("text" => "🔰 Passa a premium per sempre", "callback_data" => "premium2")));
            $text = urlencode("⚙️ Pannello gestione notifiche\nLa percentuale di sconto impostata è <b>".getPercentage($chat_id)."%</b>\nStato utente: ".$state."\n\nSeleziona quale notifiche vuoi attivare/disattivare oppure visualizza la lista dei prodotti seguiti");
        }

        sendMessageWithButtons($chat_id, $text, $inline_keyboard);
        //sendMessage($chat_id, urlencode("test"));
    }
    else if($message[0] == "/percentuale")
    {
        if(isset($message[1]) && is_numeric($message[1]) && $message[1] <= 100 && $message[1] >= 0)
        {
            setPercentage($message[1], $user_id);
            sendMessage($chat_id, urlencode("Saranno notificati tutti i prodotti con sconto uguale o maggiore al ".$message[1]."%"));
        }
        else
            sendMessage($chat_id, urlencode("Valore percentuale non valido"));
    }
    else if($message[0] == "/traccia")
    {
        if(isPremium($user_id))
        {
            sendMessage($chat_id, urlencode("Inserisci gli ASIN da tracciare, puoi inviare anche più di un ASIN per volta, ogni ASIN deve essere separato da uno spazio"));
            setStatus($user_id, "in attesa di asin per tracciatura");
        }
        else
        {
            sendMessage($chat_id, urlencode("Solo gli utenti premium possono usare questa funziona, passa a premium ora!"));
        }
    }
    else if(isset($message[0]) && getStatus($user_id) == "in attesa di asin per tracciatura")
    {
        if(isPremium($user_id))
        {
            setStatus($user_id, "processando ASIN");
            for($i = 0; $i < count($message); $i++)
            {
                if(substr($message[$i], 0, 2) == 'B0')
                    setProductTracking($message[$i], $chat_id, true);
                else
                    sendMessage($chat_id, urlencode($message[$i]."non valido, quindi non inserito"));
            }

            sendMessage($chat_id, urlencode("ASIN caricati, saranno notificate eventuali variazioni di prezzo"));
            setStatus($user_id, null);
        }
        else
        {
            sendMessage($chat_id, urlencode("Solo gli utenti premium possono usare questa funziona, passa a premium ora!"));
        }
    }
    else if($message[0] == "/smetti")
    {
        if(isset($message[1]))
        {
            setProductTracking($message[1], $chat_id, false);
            sendMessage($chat_id, urlencode("Il prodotto con ASIN ".$message[1]." non sarà più tracciato ne notificato dal bot"));
        }
        else
            sendMessage($chat_id, urlencode("Nessun ASIN inserito\n Esempio di ASIN: B06XNRQHG4 [/smetti B06XNRQHG4]"));
    }
    else if($message[0] == "/asin")
    {
        if(isset($message[1]))
        {
            $product = searchByAsin($message[1]);
            if(!$product)
                sendMessage($chat_id, urlencode("Prodotto non presente nel database del bot"));
            else
            {
                if($product['UsedPrice'] == 0 && $product['Price'] != 0)
                    sendMessage($chat_id, urlencode("<b>ASIN:</b> ".$message[1]."\n<b>Prodotto:</b> ".$product['Title']."\n<b>Identificativo:</b> ".$product['PartNumber']."\n<b>NUOVO:</b> ".priceConverter($product['Price'])."€\n<b>Link:</b> ".shortenLink($product['URL'])."\n"));
                else if($product['UsedPrice'] != 0 && $product['Price'] == 0)
                    sendMessage($chat_id, urlencode("<b>ASIN:</b> ".$message[1]."\n<b>Prodotto:</b> ".$product['Title']."\n<b>Identificativo:</b> ".$product['PartNumber']."\n<b>WHD:</b> ".priceConverter($product['UsedPrice'])."€\n<b>Link:</b> ".shortenLink($product['URL'])."\n"));
                else
                    sendMessage($chat_id, urlencode("<b>ASIN:</b> ".$message[1]."\n<b>Prodotto:</b> ".$product['Title']."\n<b>Identificativo:</b> ".$product['PartNumber']."\n<b>NUOVO:</b> ".priceConverter($product['Price'])."€\n<b>WHD:</b> ".priceConverter($product['UsedPrice'])."€\n<b>Link:</b> ".shortenLink($product['URL'])."\n"));
            }
        }
        else
        {
            sendMessage($chat_id, urlencode("ASIN non valido o non impostato [ESEMPIO: /asin B075Y2WJF9]"));
        }
    }
    else if($message[0] == "/cerca")
    {
        if(isset($message[1]))
        {
            $array = searchByName($message[1]);

            $string = "";
            for($i = 0; $i < count($array); $i++)
            {
                if($array[$i]['UsedPrice'] == 0 && $array[$i]['Price'] != 0)
                    $string .= "<b>ASIN:</b> ".$array[$i]['ASIN']."\n<b>Prodotto:</b> ".$array[$i]['Title']."\n<b>Identificativo:</b> ".$array[$i]['PartNumber']."\n<b>NUOVO:</b> ".priceConverter($array[$i]['Price'])."€\n<b>Link:</b> ".shortenLink($array[$i]['URL'])."\n";
                else if($array[$i]['UsedPrice'] != 0 && $array[$i]['Price'] == 0)
                    $string .= "<b>ASIN:</b> ".$array[$i]['ASIN']."\n<b>Prodotto:</b> ".$array[$i]['Title']."\n<b>Identificativo:</b> ".$array[$i]['PartNumber']."\n<b>WHD:</b> ".priceConverter($array[$i]['UsedPrice'])."€\n<b>Link:</b> ".shortenLink($array[$i]['URL'])."\n";
                else
                    $string .= "<b>ASIN:</b> ".$array[$i]['ASIN']."\n<b>Prodotto:</b> ".$array[$i]['Title']."\n<b>Identificativo:</b> ".$array[$i]['PartNumber']."\n<b>NUOVO:</b> ".priceConverter($array[$i]['Price'])."€\n<b>WHD:</b> ".priceConverter($array[$i]['UsedPrice'])."€\n<b>Link:</b> ".shortenLink($array[$i]['URL'])."\n";

                $string .= "---------------------\n";
            }

            sendMessage($chat_id, urlencode($string));
        }
        else
        {
            sendMessage($chat_id, urlencode("Paramentro non valido o non impostato [ESEMPIO: /cerca i7 8700k]"));
        }
    }
    else if($message[0] == "/start")
    {
        sendMessage($user_id, urlencode("Per scoprire ulteriori comandi digita /help"));
        $inline_keyboard = array(
            array(array("text" => "10%", "callback_data" => "10")),            
            array(array("text" => "20%", "callback_data" => "20")),
            array(array("text" => "30%", "callback_data" => "30")),
            array(array("text" => "50%", "callback_data" => "50")),
            array(array("text" => "70%", "callback_data" => "70")),
        );            
        sendMessageWithButtons($chat_id, urlencode("Seleziona la percentuale di sconto minima per la quale ricevere notifiche oppure digita la percentuale direttamente in chat"), $inline_keyboard);
        setStatus($user_id, "in attesa di percentuale di sconto");
    }
    else if(isset($message[0]) && getStatus($user_id) == "in attesa di percentuale di sconto")
    {
        $percentage = substr($message[0], 0, 2);
        if(!is_numeric($percentage))
        {
            sendMessage($user_id, urlencode("Valore non valido, riprova"));
            return;
        }
        setPercentage($percentage, $chat_id);
        sendMessage($chat_id, urlencode("Saranno notificati tutti i prodotti con sconto uguale o maggiore al ".$percentage."%"));
        setStatus($user_id, null);
    }
    else if($message[0] == "/help")
    {
        sendMessage($chat_id, urlencode("Lista comandi"));
        $message = "/percentuale {VALORE} per impostare valore minimo di sconto per la ricezione di notifiche\n\n/traccia {ASIN} per tracciare singolarmente un prodotto\n\n/smetti {ASIN} per annullare la tracciatura di un prodotto\n\n/lista_tracciature per controllare i prodotti attualmente tracciati con il tuo account\n\n/nuove_aggiunte per abilitare la ricezione di notifiche per prodotti appena aggiunti al catalogo\n\n/asin {ASIN} per avere i dettagli di un prodotto tramite il suo ASIN\n\n/cerca per cercare un prodotto tramite nome";
        sendMessage($user_id, urlencode($message));
    }
    else if($message[0] == "/feedback")
    {
        setStatus($user_id, "feedback");
        sendMessage($chat_id, urlencode("Scrivi il tuo feedback o consiglio in un unico messaggio e invialo, sarà mio compito inoltrarlo agli amministratori."));
    }
    else if(isset($message[0]) && getStatus($user_id) == "feedback")
    {
        $string = "";
        for($i = 0; $i < count($message); $i++)
        {
            $string .= $message[$i]." ";
        }

        if(isset($user_username))
        {
            saveFeedback($user_id, $user_username, addslashes($string));
            sendMessageToAllAdmins(3, "<b>Nuovo feedback ricevuto da </b>@".$user_username."\n\n".$string);
        }
        else
        {
            saveFeedback($user_id, $user_firstname, addslashes($string));
            sendMessageToAllAdmins(3, "<b>Nuovo feedback ricevuto da ".$user_firstname."</b>\n\n".$string);
        }

        sendMessage($chat_id, urlencode("Il tuo feedback è stato inoltrato agli amministratori. Grazie!"));
        setStatus($user_id, null);
    }

    if(isset($user_username))
        updateUsername($user_username, $user_id);
}

function callback($callback_data, $callback_id, $callback_userId, $callback_chatId, $callback_msgId)
{
    if($chatType == "group" || $chatType == "supergroup")
    {
        if(isFounder($callback_chatId, $callback_userId) || isAdmin($callback_chatId, $callback_userId))
        {
            switch($callback_data)
            {
                case "notificaNuoviProdotti":
                    if(newProductNotificationIsEnabled($callback_chatId))
                    {
                        setNewProductNotification(0, $callback_chatId);
                        answerQuery($callback_id, urlencode("❌ Notifica nuovi prodotti disabilitata"));
                    }
                    else
                    {
                        setNewProductNotification(1, $callback_chatId);
                        answerQuery($callback_id, urlencode("✅ Notifica nuovi prodotti abilitata"));
                    }
                    break;

                case "notificaWHD":
                    if(whdNotificationIsEnabled($callback_chatId))
                    {
                        setWhdNotification(0, $callback_chatId);
                        answerQuery($callback_id, urlencode("❌ Notifica prodotti dal Warehouse deals disabilitata"));
                    }
                    else
                    {
                        setWhdNotification(1, $callback_chatId);
                        answerQuery($callback_id, urlencode("✅ Notifica prodotti dal Warehouse deals abilitata"));
                    }
                    break;

                case "listaTracciature":
                    $trackedProductsList = getTrackedProducts($callback_chatId);
                    $message = "";
                    foreach($trackedProductsList as $key => $value)
                    {
                        $message .= addslashes($value)."\n";
                        $message .= $key."\n";
                        $message .= "--------------\n";
                    }
                    $message .= "Usa il comando /asin per avere dettagli sul prodotto";
                    sendMessage($callback_chatId, urlencode($message));
                    break;

                default:
                    $percentage = $callback_data;
                    setPercentage($percentage, $callback_chatId);
                    answerQuery($callback_id, urlencode("✅ Saranno notificati tutti i prodotti con sconto uguale o maggiore al ".$percentage."%"));
                    setStatus($callback_chatId, null);
                    break;
            }
        }
        else
            sendMessage($callback_userId, urlencode("Non hai i permessi necessari"));
    }
    else
    {
        switch($callback_data)
        {
            case "notificaNuoviProdotti":
                if(newProductNotificationIsEnabled($callback_chatId))
                {
                    setNewProductNotification(0, $callback_chatId);
                    answerQuery($callback_id, urlencode("❌ Notifica nuovi prodotti disabilitata"));
                }
                else
                {
                    setNewProductNotification(1, $callback_chatId);
                    answerQuery($callback_id, urlencode("✅ Notifica nuovi prodotti abilitata"));
                }
                break;

            case "notificaWHD":
                if(whdNotificationIsEnabled($callback_chatId))
                {
                    setWhdNotification(0, $callback_chatId);
                    answerQuery($callback_id, urlencode("❌ Notifica prodotti dal Warehouse deals disabilitata"));
                }
                else
                {
                    setWhdNotification(1, $callback_chatId);
                    answerQuery($callback_id, urlencode("✅ Notifica prodotti dal Warehouse deals abilitata"));
                }
                break;

            case "listaTracciature":
                $trackedProductsList = getTrackedProducts($callback_chatId);
                $message = "";
                foreach($trackedProductsList as $key => $value)
                {
                    $message .= addslashes($value)."\n";
                    $message .= "<b>".$key."</b>\n";
                    $message .= "--------------\n";
                }
                $message .= "Usa il comando /asin per avere dettagli sul prodotto";
                sendMessage($callback_chatId, urlencode($message));
                break;

            case "premium1":
                $invoiceId = $chat_id."".date('dmy');
                sendInvoice($callback_chatId, "ACQUISTO - PREMIUM 1 MESE", "Con PREMIUM potrai ricevere le notifiche in anteprima e utilizzare la funzione per tracciare singoli prodotti", $invoiceId, $invoiceId, 100);
                break;

            case "premium2":
                $invoiceId = $chat_id."".date('dmy');
                sendInvoice($callback_chatId, "ACQUISTO - PREMIUM PERMANENTE", "Con PREMIUM potrai ricevere le notifiche in anteprima e utilizzare la funzione per tracciare singoli prodotti", $invoiceId, $invoiceId, 1000);
                break;

            default:
                $percentage = $callback_data;
                setPercentage($percentage, $callback_chatId);
                answerQuery($callback_id, urlencode("✅ Saranno notificati tutti i prodotti con sconto uguale o maggiore al ".$percentage."%"));
                setStatus($callback_chatId, null);
                break;
        }
    }
}

function answerQuery($callback_id, $text)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => Telegram."/answerCallbackQuery?callback_query_id=".$callback_id."&text=".$text
    ));

    curl_exec($curl);
    curl_close($curl);
}

function editMessageText($chat_id, $message_id, $newText, $inline_keyboard)
{
    $replyMarkup = array("inline_keyboard" => $inline_keyboard);
    $replyMarkup = json_encode($replyMarkup);

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => Telegram."/editMessageText?chat_id=".$chat_id."&message_id=".$message_id."&text=".$newText."&parse_mode=html&reply_markup=".$replyMarkup
    ));

    curl_exec($curl);
    curl_close($curl);
}

function sendGlobalMessage($text)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT * FROM users_and_groups ORDER BY RAND();");
    $conn->close();

    while($row = $result->fetch_assoc())
    {
        sendMessage($row['id'], $text);
    }
}

function isBotAdmin($userId)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT * FROM admin_users WHERE id='".$userId."';");
    $conn->close();

    if($result->num_rows > 0)
        return true;
    else
        return false;
}

function getAdminLevel($userId)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT level FROM admin_users WHERE id='".$userId."';");
    $conn->close();

    if($result->num_rows > 0)
        return $result->fetch_assoc()['level'];
    else
        return 0;
}

function sendMessageToAllAdmins($adminLevel, $text)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT * FROM admin_users WHERE level='".$adminLevel."';");
    $conn->close();

    while($row = $result->fetch_assoc())
    {
        sendMessage($row['id'], urlencode($text));
    }
}

function getKeywordList()
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT string FROM search_keywords ORDER by id;");
    $conn->close();

    $array = array();

    while($row = $result->fetch_assoc())
    {
       array_push($array, $row['string']);
    }

    return $array;
}

function updateUsername($user_username, $chat_id)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->query("UPDATE `users_and_groups` SET `name`='".$user_username."' WHERE  `id`='".$chat_id."';");
    $conn->close();
}

function updateUsernameGroupAdmin($id, $user_username)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->query("UPDATE `users_and_groups` SET `adminName`='".$user_username."' WHERE  `id`='".$id."';");
    $conn->close();
}

function newProductNotificationIsEnabled($chat_id)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT newProductNotification FROM users_and_groups WHERE id = '".$chat_id."';");
    $conn->close();

    if($result->fetch_assoc()['newProductNotification'] == 1)
        return true;
    else
        return false;
}

function whdNotificationIsEnabled($chat_id)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT whdNotification FROM users_and_groups WHERE id = '".$chat_id."';");
    $conn->close();

    if($result->fetch_assoc()['whdNotification'] == 1)
        return true;
    else
        return false;
}

function getTrackedProducts($chat_id)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT * FROM product_tracking WHERE id = '".$chat_id."';");
    $conn->close();

    $array = array();

    while($row = $result->fetch_assoc())
    {
        $array[$row['ASIN']] = $row['title'];
    }

    return $array;
}

function setProductTracking($asin, $chat_id, $enable)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    if($enable)
    {
        require_once('apiFunctionsForBot.php');
        $title = getTitleByAsin($asin);
        sendMessage($chat_id, urlencode($asin." -> ".$title));
        $conn->query("INSERT IGNORE INTO `product_tracking` (`ASIN`, `id`, `title`) VALUES ('".$asin."', '".$chat_id."', '".addslashes($title)."');");
        checkIfproductExistsOnDb($asin); //aggiunge asin alla lista di ricerca se prodotto non presente
    }
    else
        $conn->query("DELETE FROM `product_tracking` WHERE `ASIN`='".$asin."' AND `id`='".$chat_id."';");

    $conn->close();
}

function checkIfproductExistsOnDb($asin)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT ASIN FROM products WHERE ASIN = '".$asin."';");

    if($result->num_rows == 0)
        $conn->query("INSERT IGNORE INTO `search_keywords` (`string`) VALUES ('".$asin."');");

    $conn->close();
}

function setNewProductNotification($enabled, $chat_id)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("UPDATE `users_and_groups` SET `newProductNotification`='".$enabled."' WHERE `id`='".$chat_id."';");
    $conn->close();
}

function setWhdNotification($enabled, $chat_id)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("UPDATE `users_and_groups` SET `whdNotification`='".$enabled."' WHERE `id`='".$chat_id."';");
    $conn->close();
}

function addSearchKeyword($keyword)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->query("INSERT IGNORE INTO `search_keywords` (`string`) VALUES ('".$keyword."');");
    $conn->close();
}

function searchByName($name)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT  *,  LEFT(`URL`, 256),  LEFT(`Title`, 256),  `Price`,  `UsedPrice`,  LEFT(`PartNumber`, 256) FROM `products` WHERE `Title` LIKE '%".$name."%' OR `PartNumber` LIKE '%".$name."%' LIMIT 10;");
    $conn->close();

    $array = array();

    while($row = $result->fetch_assoc())
    {
        array_push($array, $row);
    }

    return $array;
}

function searchByAsin($asin)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT * FROM products WHERE ASIN = '".$asin."';");
    $conn->close();

    if(!$result)
        return false;
    else
        return $result->fetch_assoc();
}

function getLatestLogId($asin)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT id FROM logs WHERE asin = '".$asin."' ORDER BY id DESC LIMIT 1;");
    $conn->close();

    if(!$result)
        return false;
    else
        return $result->fetch_assoc()['id'];
}

function getPercentage($id)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT discountPercentage FROM users_and_groups WHERE id = '".$id."';");
    $conn->close();

    return $result->fetch_assoc()['discountPercentage'];
}

function setPercentage($percentage, $id)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->query("INSERT INTO users_and_groups (`id`, `discountPercentage`) VALUES ('".$id."','".$percentage."') ON DUPLICATE KEY UPDATE discountPercentage=".$percentage.";");
    $conn->close();
}

function getUserIdByUsername($user_username)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT id FROM users_and_groups WHERE name = '".$user_username."';");
    $conn->close();

    if($result)
        return $result->fetch_assoc()['id'];
    else
        return null;
}

function isBanned($id)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT * FROM banned_users WHERE id='".$id."';");
    $conn->close();

    if($result->num_rows >= 1)
        return true;
    else
        return false;
}

function ban($id)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->query("INSERT IGNORE INTO banned_users (`id`) VALUES ('".$id."');");
    $conn->query("DELETE FROM `product_tracking` WHERE `id`='".$id."';");
    $conn->query("DELETE FROM `users_and_groups` WHERE `id`='".$id."';");
    $conn->close();

    sendMessage($id, urlencode("Il tuo accesso al bot è stato disabilitato, contatta un amministratore per maggiori informazioni"));
}

function isAdmin($chat_id, $user_id)
{
    $member = getChatMember($chat_id, $user_id);
    if($member['result']['status'] == "administrator")
        return true;
    else
        return false;
}

function isFounder($chat_id, $user_id)
{
    $member = getChatMember($chat_id, $user_id);
    if($member['result']['status'] == "creator")
        return true;
    else
        return false;
}

function getChatMember($chat_id, $user_id)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => Telegram."/getChatMember?chat_id=".$chat_id."&user_id=".$user_id
    ));

    $json = curl_exec($curl);
    curl_close($curl);

    //$json = file_get_contents(Telegram."/getChatMember?chat_id=".$chat_id."&user_id=".$user_id);
    
    return json_decode($json, true);
}


function sendMessageToUser($user_id, $text)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => Telegram."/sendMessage?chat_id=".$user_id."&text=".$text."&parse_mode=html"
    ));

    curl_exec($curl);
    curl_close($curl);
}

function sendInvoice($chat_id, $title, $description, $payload, $start_parameter, $price)
{
    $provider_token = "STRIPE_LIVE_TOKEN";

    $prices = json_encode(array(array("label" => "Totale", "amount" => $price)));

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => Telegram."/sendInvoice?chat_id=".$chat_id."&title=".urlencode($title)."&description=".urlencode($description)."&payload=".urlencode($payload)."&provider_token=".urlencode($provider_token)."&start_parameter=".urlencode($start_parameter)."&currency=EUR&prices=".$prices
    ));
    curl_exec($curl);
    curl_close($curl);
}

function sendMessage($chat_id, $text)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => Telegram."/sendMessage?chat_id=".$chat_id."&text=".$text."&parse_mode=html"
    ));

    curl_exec($curl);
    curl_close($curl);

    //file_get_contents(Telegram."/sendMessage?chat_id=".$chat_id."&text=".$text."&parse_mode=html");
}

function sendMessageWithButtons($chat_id, $text, $inline_keyboard)
{

    $replyMarkup = array("inline_keyboard" => $inline_keyboard);
    $replyMarkup = json_encode($replyMarkup);

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => Telegram."/sendMessage?chat_id=".$chat_id."&text=".$text."&parse_mode=html&disable_web_page_preview=true&reply_markup=".$replyMarkup
    ));

    curl_exec($curl);
    curl_close($curl);

   //file_get_contents(Telegram."/sendMessage?chat_id=".$chat_id."&text=".$text."&parse_mode=html&disable_web_page_preview=true&reply_markup=".$replyMarkup);
}

function sendImage($chat_id, $image)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => Telegram."/sendPhoto?chat_id=".$chat_id."&photo=".$image
    ));

    curl_exec($curl);
    curl_close($curl);

    //file_get_contents(Telegram."/sendPhoto?chat_id=".$chat_id."&photo=".$image);
}

function sendImageWithString($chat_id, $image, $text)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => Telegram."/sendPhoto?chat_id=".$chat_id."&photo=".$image."&caption=".$text
    ));

    curl_exec($curl);
    curl_close($curl);

    //file_get_contents(Telegram."/sendPhoto?chat_id=".$chat_id."&photo=".$image."&caption=".$text);
}

function answerPreCheckoutQuery($pre_checkout_query, $ok)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => Telegram."/answerPreCheckoutQuery?pre_checkout_query_id=".urlencode($pre_checkout_query)."&ok=true"
    ));

    curl_exec($curl);
    curl_close($curl);
}

function setStatus($id, $status = null)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    if(isset($status))
        $conn->query("INSERT INTO user_status (id,status) VALUES ('".$id."', '".$status."') ON DUPLICATE KEY UPDATE status='".$status."';");
    else
        $conn->query("DELETE FROM `user_status` WHERE `id`='".$id."';");
    $conn->close();
}

function getStatus($id)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT status FROM user_status WHERE id='".$id."';");
    $conn->close();

    if($result)
        return $result->fetch_assoc()['status'];
    else
        return null;
}

function saveFeedback($userId, $user_username, $feedback)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->query("INSERT INTO feedback (userId,username,feedback) VALUES ('".$userId."', '".$user_username."', '".$feedback."');");
    $conn->close();
}

function getLastCommand()
{
    require(__DIR__.'/../common/config.php');
    return $lastCommand;   
}

function saveLastCommand($string)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->query("INSERT INTO settings (id,setting,value) VALUES (8,'lastCommand','".$string."') ON DUPLICATE KEY UPDATE value='".$string."';");
    $conn->close();
}

function upgradeToPremium($user_id, $type = "normal")
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT premium FROM users_and_groups WHERE id='".$user_id."';");
    
    if($result->fetch_assoc()['premium'] == '1') //evita di upgradare di nuovo se già attivo
    {
        $conn->close();
        return;
    }
    else { //forse non esiste proprio nel db?
        $conn->query("INSERT IGNORE INTO users_and_groups (`id`, `discountPercentage`) VALUES ('".$user_id."','20');");
    }

    $conn->query("UPDATE `users_and_groups` SET `premium`='1' WHERE  `id`='".$user_id."';");
    $conn->query("UPDATE `users_and_groups` SET `premiumStartingDate`=NOW() WHERE  `id`='".$user_id."';");
    if($type == "permanent")
        $conn->query("UPDATE `users_and_groups` SET `premium_permanent`='1' WHERE  `id`='".$user_id."';");
    $conn->close();
}

function isPremiumLifetime($user_id)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT premium_permanent FROM users_and_groups WHERE id='".$user_id."';");
    $conn->close();

    if(!$result)
        return false;
    else
    {
        if($result->fetch_assoc()['premium_permanent'] == '1')
            return true;
        else
            return false;
    }
}

function isPremiumTop20($user_id)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT top20 FROM users_and_groups WHERE id='".$user_id."';");
    $conn->close();

    if(!$result)
        return false;
    else
    {
        if($result->fetch_assoc()['top20'] == '1')
            return true;
        else
            return false;
    }
}

function getPremiumEndDate($user_id)
{
    require(__DIR__.'/../common/config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT DATE_ADD(premiumStartingDate, INTERVAL 1 MONTH) AS endDate FROM users_and_groups WHERE id='".$user_id."';");
    $conn->close();

    return $result->fetch_assoc()['endDate'];
}
?>
