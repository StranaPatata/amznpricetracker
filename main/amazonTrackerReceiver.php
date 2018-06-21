<?php
require('bot.php');
$chat_id = $_GET['chatId'];
$message = $_GET['message'];

if(isset($chat_id) && isset($message))
    sendMessage($chat_id, urlencode($message));

?>