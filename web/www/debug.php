<?php
if (!isset($_SESSION)) session_start();

echo "<h1>DevOps test</h1>"; 
echo "<h2>Session counter</h2>"; 
$count = isset($_SESSION['count']) ? $_SESSION['count'] : 1;

echo "This page was visited " . $count . " times";

echo "<h2>Time</h2>"; 
$now = new DateTime();
echo $now->format('Y-m-d H:i:s');


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

echo "<h2>IP Address:</h2>"; 
echo $ipaddress;
echo "<ul>";
echo "<li>HTTP_CLIENT_IP=", $_SERVER['HTTP_CLIENT_IP'], "</li>";
echo "<li>HTTP_X_FORWARDED_FOR=", $_SERVER['HTTP_X_FORWARDED_FOR'], "</li>";
echo "<li>HTTP_X_FORWARDED=", $_SERVER['HTTP_X_FORWARDED'], "</li>";
echo "<li>HTTP_FORWARDED_FOR=", $_SERVER['HTTP_FORWARDED_FOR'], "</li>";
echo "<li>HTTP_FORWARDED=", $_SERVER['HTTP_FORWARDED'], "</li>";
echo "<li>REMOTE_ADDR=", $_SERVER['REMOTE_ADDR'], "</li>";
echo "</ul>";

echo "<h2>PHP Info</h2>"; 
phpinfo();

# Increment the visits counter
$_SESSION['count'] = ++$count;



?>
