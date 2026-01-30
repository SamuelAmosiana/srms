<?php
header('Content-Type: text/plain');
echo "Format received: " . ($_GET['format'] ?? 'NONE') . "\n";
echo "All parameters: " . print_r($_GET, true) . "\n";
?>