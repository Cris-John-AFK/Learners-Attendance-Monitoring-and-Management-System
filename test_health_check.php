<?php

// Test backend health check
$url = 'http://127.0.0.1:8000/api/health-check';

$result = file_get_contents($url);

if ($result === FALSE) {
    echo "Backend server is not running on http://127.0.0.1:8000\n";
} else {
    echo "Backend server is running!\n";
    echo "Response: " . $result . "\n";
}

?>
