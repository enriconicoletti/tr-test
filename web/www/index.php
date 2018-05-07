<?php
if (!isset($_SESSION)) session_start();
header('Content-Type: application/json');
$count = isset($_SESSION['count']) ? $_SESSION['count'] : 1;
$now = new DateTime();

$ipaddress = '';

if (isset($_SERVER['HTTP_CLIENT_IP']))
	$ipaddress = $_SERVER['HTTP_CLIENT_IP'];

else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];

else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];

else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];

else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];

else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
else
        $ipaddress = 'UNKNOWN';

echo "{\"ip\": \"" . $ipaddress . "\",\"time\": \"" . $now->format('Y-m-d H:i:s') . "\",\"visits\": \"" . $count . "\"}";

# Increment the visits counter
$_SESSION['count'] = ++$count;

?>
