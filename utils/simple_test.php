<?php
echo "PHP is working\n";

// Try to include config
if (file_exists('config.php')) {
    echo "config.php exists\n";
} else {
    echo "config.php does not exist\n";
}
?>