<?php
echo "Current working directory: " . getcwd() . "\n";

// Check if lib directory exists
if (file_exists('lib')) {
    echo "lib directory exists\n";
} else {
    echo "lib directory does not exist\n";
}

// Check if lib/fpdf directory exists
if (file_exists('lib/fpdf')) {
    echo "lib/fpdf directory exists\n";
} else {
    echo "lib/fpdf directory does not exist\n";
}

// Check if fpdf.php exists
if (file_exists('lib/fpdf/fpdf.php')) {
    echo "lib/fpdf/fpdf.php exists\n";
} else {
    echo "lib/fpdf/fpdf.php does not exist\n";
}

// Try to include the file
if (file_exists('../lib/fpdf/fpdf.php')) {
    echo "Can include ../lib/fpdf/fpdf.php from admin directory\n";
} else {
    echo "Cannot include ../lib/fpdf/fpdf.php from admin directory\n";
}

// List contents of current directory
echo "Contents of current directory:\n";
print_r(scandir('.'));

// If lib exists, list its contents
if (file_exists('lib')) {
    echo "Contents of lib directory:\n";
    print_r(scandir('lib'));
    
    if (file_exists('lib/fpdf')) {
        echo "Contents of lib/fpdf directory:\n";
        print_r(scandir('lib/fpdf'));
    }
}
?>