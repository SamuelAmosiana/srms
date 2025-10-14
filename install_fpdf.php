<?php
echo "Starting FPDF installation...\n";

// Create lib directory if it doesn't exist
if (!file_exists('lib')) {
    echo "Creating lib directory...\n";
    $result = mkdir('lib', 0777, true);
    if ($result) {
        echo "lib directory created successfully.\n";
    } else {
        echo "Failed to create lib directory.\n";
        exit(1);
    }
} else {
    echo "lib directory already exists.\n";
}

// Create lib/fpdf directory if it doesn't exist
if (!file_exists('lib/fpdf')) {
    echo "Creating lib/fpdf directory...\n";
    $result = mkdir('lib/fpdf', 0777, true);
    if ($result) {
        echo "lib/fpdf directory created successfully.\n";
    } else {
        echo "Failed to create lib/fpdf directory.\n";
        exit(1);
    }
} else {
    echo "lib/fpdf directory already exists.\n";
}

// Download FPDF
$fpdfUrl = 'http://www.fpdf.org/fpdf185.zip';
$zipFile = 'fpdf.zip';

echo "Downloading FPDF from $fpdfUrl...\n";

// Download the file
$fileData = file_get_contents($fpdfUrl);
if ($fileData === false) {
    die("Failed to download FPDF from $fpdfUrl.\n");
}

echo "Download completed. Saving to $zipFile...\n";

// Save the file
$result = file_put_contents($zipFile, $fileData);
if ($result === false) {
    die("Failed to save downloaded file to $zipFile.\n");
}

echo "File saved successfully. Size: " . filesize($zipFile) . " bytes\n";
echo "Extracting FPDF...\n";

// Extract the ZIP file
$zip = new ZipArchive;
$res = $zip->open($zipFile);
if ($res === TRUE) {
    echo "ZIP file opened successfully. Extracting...\n";
    $zip->extractTo('temp_fpdf');
    $zip->close();
    
    echo "Extracted FPDF successfully.\n";
    
    // Check if the extracted files exist
    if (file_exists('temp_fpdf/fpdf185/fpdf.php')) {
        echo "Found fpdf.php in extracted files.\n";
        
        // Copy fpdf.php to lib/fpdf/
        if (copy('temp_fpdf/fpdf185/fpdf.php', 'lib/fpdf/fpdf.php')) {
            echo "Copied fpdf.php successfully.\n";
        } else {
            echo "Failed to copy fpdf.php.\n";
        }
    } else {
        echo "Could not find fpdf.php in extracted files.\n";
        // List contents of temp_fpdf
        if (file_exists('temp_fpdf')) {
            echo "Contents of temp_fpdf:\n";
            print_r(scandir('temp_fpdf'));
        }
        if (file_exists('temp_fpdf/fpdf185')) {
            echo "Contents of temp_fpdf/fpdf185:\n";
            print_r(scandir('temp_fpdf/fpdf185'));
        }
    }
    
    // Copy font directory to lib/fpdf/
    if (file_exists('temp_fpdf/fpdf185/font')) {
        echo "Copying font directory...\n";
        copyDirectory('temp_fpdf/fpdf185/font', 'lib/fpdf/font');
        echo "Font directory copied.\n";
    } else {
        echo "Font directory not found in extracted files.\n";
    }
    
    // Clean up
    echo "Cleaning up temporary files...\n";
    unlink($zipFile);
    removeDirectory('temp_fpdf');
    
    echo "FPDF installation completed!\n";
    
    // Verify installation
    if (file_exists('lib/fpdf/fpdf.php')) {
        echo "Installation verified: lib/fpdf/fpdf.php exists.\n";
    } else {
        echo "Installation verification failed: lib/fpdf/fpdf.php does not exist.\n";
    }
} else {
    echo "Failed to extract FPDF. Error code: $res\n";
}

function copyDirectory($src, $dst) {
    echo "Copying directory from $src to $dst\n";
    $dir = opendir($src);
    @mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                copyDirectory($src . '/' . $file, $dst . '/' . $file);
            }
            else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function removeDirectory($dir) {
    echo "Removing directory: $dir\n";
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
                    removeDirectory($dir. DIRECTORY_SEPARATOR .$object);
                else
                    unlink($dir. DIRECTORY_SEPARATOR .$object);
            }
        }
        rmdir($dir);
    }
}
?>