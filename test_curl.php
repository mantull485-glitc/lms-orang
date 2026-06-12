<?php
$url = 'https://lms-orang.vercel.app/api/midtrans_token.php';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
// We don't have the session cookie, so it should return: {"error":"Tidak ada pending order."}
// If it returns HTML, we will see the PHP error!
$response = curl_exec($ch);
curl_close($ch);
echo "RESPONSE FROM VERCEL:\n\n";
echo $response;
