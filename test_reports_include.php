<?php
echo "Testing FPDF include...\n";

// Test the include path
$includePath = '../lib/fpdf/fpdf.php';
echo "Checking if file exists: $includePath\n";

if (file_exists($includePath)) {
    echo "File exists!\n";
    
    // Try to include the file
    try {
        require_once $includePath;
        echo "Successfully included FPDF!\n";
        
        // Try to create an FPDF instance
        $pdf = new FPDF();
        echo "Successfully created FPDF instance!\n";
        
    } catch (Exception $e) {
        echo "Error creating FPDF instance: " . $e->getMessage() . "\n";
    }
} else {
    echo "File does not exist!\n";
    echo "Current working directory: " . getcwd() . "\n";
    
    // List files in the expected directory
    $expectedDir = dirname($includePath);
    if (is_dir($expectedDir)) {
        echo "Contents of $expectedDir:\n";
        print_r(scandir($expectedDir));
    } else {
        echo "Directory $expectedDir does not exist!\n";
    }
}
?>