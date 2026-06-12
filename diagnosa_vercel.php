<?php
$url = 'https://lms-orang.vercel.app/api/midtrans_token.php';
$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => 'test=1'
    ]
];
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
if ($result === FALSE) {
    echo "Error fetching URL";
} else {
    echo "<h1>Response from Vercel API:</h1>";
    echo "<pre>" . htmlspecialchars($result) . "</pre>";
}
