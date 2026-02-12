<?php
$sqlDump = file_get_contents('c:\xampp\htdocs\srms\lsucmpph_lsucsms_lsuc (1).sql');

// Extract all table names from the SQL dump
preg_match_all('/CREATE TABLE `(.*?)`/', $sqlDump, $matches);

$tables = $matches[1];

echo "Tables in online database dump:\n";
foreach ($tables as $table) {
    echo "- $table\n";
}

echo "\nTotal tables: " . count($tables) . "\n";
?>