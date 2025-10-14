<?php
echo "Starting FPDF installation from GitHub...\n";

// Create lib directory if it doesn't exist
if (!file_exists('lib')) {
    echo "Creating lib directory...\n";
    mkdir('lib', 0777, true);
}

// Create lib/fpdf directory if it doesn't exist
if (!file_exists('lib/fpdf')) {
    echo "Creating lib/fpdf directory...\n";
    mkdir('lib/fpdf', 0777, true);
}

// Download FPDF from GitHub
$fpdfUrl = 'https://github.com/Setasign/FPDF/archive/master.zip';
$zipFile = 'fpdf_github.zip';

echo "Downloading FPDF from GitHub...\n";

// Download the file using cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fpdfUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$fileData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200) {
    die("Failed to download FPDF from GitHub. HTTP Code: $httpCode\n");
}

// Save the file
$result = file_put_contents($zipFile, $fileData);
if ($result === false) {
    die("Failed to save downloaded file.\n");
}

echo "File saved successfully. Size: " . filesize($zipFile) . " bytes\n";
echo "Extracting FPDF...\n";

// Extract the ZIP file
$zip = new ZipArchive;
$res = $zip->open($zipFile);
if ($res === TRUE) {
    $zip->extractTo('temp_fpdf_github');
    $zip->close();
    
    echo "Extracted FPDF successfully.\n";
    
    // Find the FPDF directory (it might have a different name)
    $extractedDirs = scandir('temp_fpdf_github');
    $fpdfDir = null;
    foreach ($extractedDirs as $dir) {
        if ($dir !== '.' && $dir !== '..' && is_dir("temp_fpdf_github/$dir")) {
            if (file_exists("temp_fpdf_github/$dir/fpdf.php")) {
                $fpdfDir = "temp_fpdf_github/$dir";
                break;
            }
        }
    }
    
    if ($fpdfDir && file_exists("$fpdfDir/fpdf.php")) {
        echo "Found fpdf.php in $fpdfDir\n";
        
        // Copy fpdf.php to lib/fpdf/
        if (copy("$fpdfDir/fpdf.php", 'lib/fpdf/fpdf.php')) {
            echo "Copied fpdf.php successfully.\n";
        } else {
            echo "Failed to copy fpdf.php.\n";
        }
        
        // Copy font directory if it exists
        if (file_exists("$fpdfDir/font")) {
            echo "Copying font directory...\n";
            copyDirectory("$fpdfDir/font", 'lib/fpdf/font');
            echo "Font directory copied.\n";
        } else {
            echo "Font directory not found.\n";
        }
    } else {
        echo "Could not find fpdf.php in extracted files.\n";
        // List contents for debugging
        print_r($extractedDirs);
        foreach ($extractedDirs as $dir) {
            if ($dir !== '.' && $dir !== '..' && is_dir("temp_fpdf_github/$dir")) {
                echo "Contents of temp_fpdf_github/$dir:\n";
                print_r(scandir("temp_fpdf_github/$dir"));
            }
        }
    }
    
    // Clean up
    echo "Cleaning up temporary files...\n";
    unlink($zipFile);
    removeDirectory('temp_fpdf_github');
    
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