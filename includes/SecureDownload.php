<?php
/**
 * Secure Download Utility Functions
 */

class SecureDownload
{
    /**
     * Securely download a file with proper headers and security checks
     *
     * @param string $file_path Path to the file to download
     * @param string $download_name Name to use for the downloaded file
     * @param array $allowed_extensions List of allowed file extensions
     * @return bool True if download was successful, false otherwise
     */
    public static function downloadFile($file_path, $download_name = '', $allowed_extensions = [])
    {
        // Set default allowed extensions if none provided
        if (empty($allowed_extensions)) {
            $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
        }

        // Validate inputs
        if (empty($file_path)) {
            self::sendJsonResponse(400, ['error' => 'File path is required']);
            return false;
        }

        // Sanitize the file path to prevent directory traversal
        $sanitized_file = basename($file_path);
        $full_path = $file_path;

        // Additional security check - ensure the resolved path is within allowed directory
        $resolved_path = realpath($full_path);
        if (!$resolved_path) {
            self::sendJsonResponse(404, ['error' => 'File not found']);
            return false;
        }

        // Verify the file exists and is readable
        if (!file_exists($full_path) || !is_readable($full_path)) {
            self::sendJsonResponse(404, ['error' => 'File not found or not readable']);
            return false;
        }

        // Validate file extension
        $file_extension = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_extensions)) {
            self::sendJsonResponse(403, ['error' => 'File type not allowed']);
            return false;
        }

        // Use provided download name or fallback to original filename
        $download_filename = !empty($download_name) ? $download_name : basename($full_path);

        // Get file size
        $file_size = filesize($full_path);
        if ($file_size === false) {
            self::sendJsonResponse(500, ['error' => 'Could not get file size']);
            return false;
        }

        // Get MIME type
        $mime_types = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];

        $mimetype = $mime_types[$file_extension] ?? 'application/octet-stream';

        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers for download
        header('Content-Type: ' . $mimetype);
        header('Content-Disposition: attachment; filename="' . rawurlencode($download_filename) . '"');
        header('Content-Length: ' . $file_size);
        header('Accept-Ranges: none');
        header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Expires: 0');

        // Flush headers
        if (ob_get_level()) {
            ob_flush();
        }
        flush();

        // Read and output the file in chunks for better memory management
        $handle = fopen($full_path, 'rb');
        if (!$handle) {
            self::sendJsonResponse(500, ['error' => 'Could not open file for reading']);
            return false;
        }

        $bytes_sent = 0;
        $chunk_size = 8192; // 8KB chunks

        while (!feof($handle) && $bytes_sent < $file_size) {
            $chunk = fread($handle, min($chunk_size, $file_size - $bytes_sent));
            if ($chunk === false) {
                fclose($handle);
                self::sendJsonResponse(500, ['error' => 'Error reading file']);
                return false;
            }

            echo $chunk;
            $bytes_sent += strlen($chunk);

            // Flush output to browser
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        }

        fclose($handle);

        return true;
    }

    /**
     * Send a JSON response with proper headers
     *
     * @param int $status_code HTTP status code
     * @param array $data Response data
     */
    public static function sendJsonResponse($status_code, $data)
    {
        if (ob_get_level()) {
            ob_end_clean();
        }

        http_response_code($status_code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Validate and sanitize file path to prevent directory traversal
     *
     * @param string $file_path Input file path
     * @param string $allowed_directory Base directory that files should be in
     * @return string|false Sanitized path or false if invalid
     */
    public static function validateFilePath($file_path, $allowed_directory)
    {
        if (empty($file_path) || empty($allowed_directory)) {
            return false;
        }

        $sanitized_file = basename($file_path);
        $full_path = rtrim($allowed_directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $sanitized_file;

        // Additional security check - ensure the resolved path is within allowed directory
        $resolved_path = realpath($full_path);
        $expected_prefix = realpath(rtrim($allowed_directory, DIRECTORY_SEPARATOR));

        if (!$resolved_path || !$expected_prefix || strpos($resolved_path, $expected_prefix) !== 0) {
            return false;
        }

        return $full_path;
    }
}
?>