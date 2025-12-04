<?php
/**
 * My File Manager - Main Connector
 * 
 * This is the main entry point for all file operations
 * Handles authentication, routing, and security
 * This file must be accessible via HTTP to function properly
 * 
 * @package MyFileManager
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */
header('X-Requested-With: XMLHttpRequest');

require_once __DIR__ . '/myfilemanager.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/chunkuploader.php';

// Start session for token management
session_start();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: application/json');

// CORS handling (customize as needed)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

/**
 * Parse size string with units (e.g., "200MB", "10GB") to bytes
 * If no unit is specified, assumes bytes
 *
 * @param mixed $size Size value (can be string with unit or integer)
 * @return int Size in bytes
 */
function parseFileSize($size) {
    // If already numeric (bytes), return as is
    if (is_numeric($size)) {
        return (int)$size;
    }
    
    // Convert to string and trim whitespace
    $size = trim($size);
    
    // Extract numeric value and unit
    if (preg_match('/^(\d+(?:\.\d+)?)\s*(B|KB|MB|GB|TB|PB)?$/i', $size, $matches)) {
        $value = (float)$matches[1];
        $unit = isset($matches[2]) ? strtoupper($matches[2]) : 'B';
        
        // Convert to bytes based on unit
        switch ($unit) {
            case 'PB':
                $value *= 1024;
            case 'TB':
                $value *= 1024;
            case 'GB':
                $value *= 1024;
            case 'MB':
                $value *= 1024;
            case 'KB':
                $value *= 1024;
            case 'B':
            default:
                break;
        }
        
        return (int)$value;
    }
    
    // If parsing fails, return 0
    return 0;
}

try {
    $PATHTOFILES = __DIR__ . '/../files/'; //the path of the folder to use for upload or download files on your system (you can customize it as you want)
    // Verify if exist
    if (!is_dir($PATHTOFILES)) {
        mkdir($PATHTOFILES, 0755, true);
        error_log("Created files folder: {$PATHTOFILES}");
    }
    // Configuration array
    $config = [
        // Root path for file operations
        'rootPath' => $PATHTOFILES,
        
        // URL path for accessing files, this url automatically adds the file name after it with a querystring ?filename=... it is usefull for custom download scripts
        'rootUrl' => '', // https://... or leave blank to use the default
        
        // Maximum quota (supports units: B, KB, MB, GB, TB, PB)
        // Examples: 5368709120, "5GB", "5000MB"
        // Default (without unit) is bytes
        'maxQuota' => parseFileSize('5GB'),
    
        // Maximum file size for upload (supports units: B, KB, MB, GB, TB, PB)
        // Examples: 524288000, "500MB", "0.5GB"
        // Default (without unit) is bytes
        'maxFileSize' => parseFileSize('0.5GB'),
        
        // Allowed mime types
        'allowedMimeTypes' => ['image/*', 'video/*', 'audio/*', 'text/*', 'application/pdf', 'text/plain'],

        // Ban dangerous extensions
        'banExtensions' => [
                // Unix/Linux executables
                'sh', 'bash', 'csh', 'ksh', 'zsh', 'tcsh', 'dash',
                'pl', 'perl', 'py', 'pyc', 'pyo', 'pyw', 'pyz',
                'rb', 'rbw', 'cgi', 'fcgi',

                // Windows executables
                'exe', 'bat', 'cmd', 'com', 'scr', 'pif', 'cpl',
                'msi', 'msp', 'dll', 'ocx', 'sys', 'scr',

                // Web server scripts
                'php', 'php3', 'php4', 'php5', 'phtml', 'php7',
                'asp', 'aspx', 'asa', 'aspx.cs', 'ashx',
                'jsp', 'jspx', 'jsw', 'jssp', 'do',
                'cfm', 'cfml', 'cfc',

                // Dangerous scripts
                'js', 'vbs', 'vbe', 'jse', 'wsf', 'wsh', 'ps1', 'psm1',

                // MAC/OS dangerous
                'app', 'dmg', 'pkg', 'mpkg',

                // dangerous config files
                'conf', 'cnf', 'ini', 'cfg', 'config',

                // htaccess and config server files
                'htaccess', 'htpasswd', 'htgroup',

                // Macro virus (Office)
                'docm', 'xlsm', 'pptm', 'dotm', 'xltm'
        ],

        
        // Disabled operations (upload, download, delete, rename, mkdir, etc.)
        'disabledOperations' => [],
        
        // Chunk size for uploads (in bytes)
        'chunkSize' => 1048576, // 1MB
        
        // Enable trash functionality
        'enableTrash' => true,
        'trashPath' => $PATHTOFILES.'.trash/', //DO NOT CHANGE THE .trash NAME OR YOU HAVE TO PASS IT AS OPTION INTO THE INITIALIZZATION OF THE JAVASCRIPT FILE
        
        // Security settings (the default tokenSecret is just an example)
        'security' => [
            'enableTokenAuth' => false,
            'tokenSecret' => base64_encode(hash('sha512', 'user123').hash('sha256', 'user123')),
            'enableIPWhitelist' => false,
            'allowedIPs' => [],
        ],

        'plugins' => [
        'rate_limiter' => [], // this enable rate limiter for video editor
        // 'ftpplugin' => [
        //     'host' => 'ftp.example.com',
        //     'username' => 'user',
        //     'password' => 'pass',
        //     'port' => 21,
        //     'passive' => true
        // ],
         ],
        // Custom authentication callback
        'authCallback' => function() {
    // Implement your authentication logic here
    // Return user info array or false
    return [
        'id' => 'user123',
        'username' => 'the_user_name',
        'quota' => '524288000', // in bytes
        'permissions' => ['read', 'write', 'delete', 'upload', 'download', 'rename', 'copy', 'move', 'mkdir', 'search', 'quota', 'info']
    ];
}
    ];

$plugins = [];
$plugin_dir = __DIR__ . '/plugins/';
if (is_dir($plugin_dir)) {
    foreach (glob($plugin_dir . '*.php') as $plugin_file) {
        require_once $plugin_file;
        $plugin_name = basename($plugin_file, '.php');
        $class_name = $plugin_name . 'Plugin';
        
        // check if plugin is enabled
        if (isset($config['plugins'][$plugin_name]) && 
            $config['plugins'][$plugin_name]['enabled'] ?? false &&
            class_exists($class_name)) {
            
            try {
                $plugins[$plugin_name] = new $class_name($config);
                //error_log("Loaded enabled plugin: {$plugin_name}");
            } catch (Exception $e) {
                error_log("Failed to load plugin {$plugin_name}: " . $e->getMessage());
            }
        } else {
            error_log("Skipped plugin {$plugin_name}: disabled or not configured");
        }
    }
}

$config['plugins'] = $plugins;
    
    // Validate authentication
    $user = call_user_func($config['authCallback']);
    if ($user === false) {
        throw new Exception('Authentication required', 401);
    }

    // Token validation for security
    if ($config['security']['enableTokenAuth']) {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_POST['token'] ?? $_GET['token'] ?? '';
        $token = str_replace('Bearer ', '', $token);
        
        if (!Security::validateToken($token, $config['security']['tokenSecret'], $user['id'])) {
            throw new Exception('Invalid security token', 403);
        }
    }
    
    // IP whitelist check
    if ($config['security']['enableIPWhitelist']) {
        if (!in_array($_SERVER['REMOTE_ADDR'], $config['security']['allowedIPs'])) {
            throw new Exception('IP address not allowed', 403);
        }
    }

    // Create trash folder if not exist
if (!is_dir($config['trashPath'])) {
    mkdir($config['trashPath'], 0755, true);
    error_log("Created trash folder: {$config['trashPath']}");
}
    
    // Get command from request
    $cmd = $_POST['cmd'] ?? $_GET['cmd'] ?? 'open';
    while (ob_get_level()) ob_end_clean();
/**
 * Video Processing Endpoint with Rate Limiting (for video editing plugin)
 * Rate limit: 4 videos every 30 minutes per IP (customizable)
 * Supports trim, crop, resize, format conversion
 * **BASE64 DECODED hash
 */
if ($cmd === 'video_process') {

// check if plugin is enabled before using it
    if (!isset($config['plugins']['rate_limiter'])) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Rate limiter plugin not available']);
        exit;
    }

    $rateCheck = $config['plugins']['rate_limiter']->checkVideoProcessLimit($_SERVER['REMOTE_ADDR']);
    
    if (!$rateCheck['success']) {
        http_response_code(429);
        echo json_encode($rateCheck);
        exit;
    }
    
    $file_hash = $_POST['target'] ?? '';
    $params_json = $_POST['params'] ?? '{}';
    $params = json_decode($params_json, true);
    
    if (empty($file_hash)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing target file hash']);
        exit;
    }
    
    $decoded_filename = base64_decode($file_hash);
    
    $input_file = $config['rootPath'] . DIRECTORY_SEPARATOR . $decoded_filename;
    
    $input_file = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $input_file);
    
    if (!file_exists($input_file)) {
        http_response_code(404);
        echo json_encode([
            'success' => false, 
            'error' => 'Target file not found',
            'debug_raw_hash' => $file_hash,
            'debug_decoded' => $decoded_filename,
            'debug_path' => $input_file
        ]);
        exit;
    }
    
    if (!is_readable($input_file)) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'error' => 'File not readable (permissions error)'
        ]);
        exit;
    }
    
    $input_file = realpath($input_file);
    $root_realpath = realpath($config['rootPath']);
    if (strpos($input_file, $root_realpath) !== 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid file path (security check failed)']);
        exit;
    }
    
    $output_file = preg_replace('/\.[^.]+$/', '_edited.' . ($params['format'] ?? 'mp4'), $input_file);
    
    if (!empty($params['overwrite'])) {
        $output_file = $input_file;
    }
    
    $ffmpeg_cmd = ['ffmpeg', '-y'];
    
    if (!empty($params['trim_start']) && (float)$params['trim_start'] > 0) {
        $ffmpeg_cmd[] = '-ss';
        $ffmpeg_cmd[] = (float)$params['trim_start'];
    }
    
    if (!empty($params['trim_end']) && (float)$params['trim_end'] > 0) {
        $duration = (float)$params['trim_end'] - ((float)($params['trim_start'] ?? 0));
        if ($duration > 0) {
            $ffmpeg_cmd[] = '-t';
            $ffmpeg_cmd[] = $duration;
        }
    }
    
    $ffmpeg_cmd[] = '-i';
    $ffmpeg_cmd[] = escapeshellarg($input_file);
    
    $video_filters = [];
    
    if (!empty($params['crop_w']) && (int)$params['crop_w'] > 0 && 
        !empty($params['crop_h']) && (int)$params['crop_h'] > 0) {
        $video_filters[] = sprintf(
            'crop=%d:%d:%d:%d',
            (int)$params['crop_w'],
            (int)$params['crop_h'],
            max(0, (int)($params['crop_x'] ?? 0)),
            max(0, (int)($params['crop_y'] ?? 0))
        );
    }
    
    if (!empty($params['resize_w']) && (int)$params['resize_w'] > 0) {
        $resize_w = (int)$params['resize_w'] + ((int)$params['resize_w'] % 2);
        $resize_h = (int)$params['resize_h'] + ((int)$params['resize_h'] % 2);
        $video_filters[] = "scale={$resize_w}:{$resize_h}";
    }
    
    if (!empty($video_filters)) {
        $ffmpeg_cmd[] = '-vf';
        $ffmpeg_cmd[] = implode(',', $video_filters);
    }
    
    $output_format = $params['format'] ?? 'mp4';
    
    if ($output_format === 'mp4') {
        $ffmpeg_cmd[] = '-c:v';
        $ffmpeg_cmd[] = 'libx264';
        $ffmpeg_cmd[] = '-preset';
        $ffmpeg_cmd[] = 'fast';
        $ffmpeg_cmd[] = '-crf';
        $ffmpeg_cmd[] = '23';
        $ffmpeg_cmd[] = '-c:a';
        $ffmpeg_cmd[] = 'aac';
        $ffmpeg_cmd[] = '-b:a';
        $ffmpeg_cmd[] = '128k';
        $ffmpeg_cmd[] = '-movflags';
        $ffmpeg_cmd[] = '+faststart';
    } else {
        $ffmpeg_cmd[] = '-c:v';
        $ffmpeg_cmd[] = 'libvpx-vp9';
        $ffmpeg_cmd[] = '-crf';
        $ffmpeg_cmd[] = '30';
        $ffmpeg_cmd[] = '-b:v';
        $ffmpeg_cmd[] = '0';
        $ffmpeg_cmd[] = '-c:a';
        $ffmpeg_cmd[] = 'libvorbis';
    }
    
    $ffmpeg_cmd[] = escapeshellarg($output_file);
    
    $command_line = implode(' ', $ffmpeg_cmd);
    
    $output_lines = [];
    $return_code = 0;
    exec($command_line, $output_lines, $return_code);
    
    if ($return_code !== 0) {
        $error_msg = implode("\n", $output_lines);
        error_log("FFmpeg ERROR [{$return_code}] for {$input_file}: {$error_msg}");
        
        echo json_encode([
            'success' => false,
            'error' => 'FFmpeg processing failed (exit code: ' . $return_code . ')',
            'debug_cmd' => $command_line,
            'debug_output' => substr($error_msg, 0, 1000)
        ]);
        exit;
    }
    
    if (!file_exists($output_file) || filesize($output_file) === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Output file not created or empty',
            'debug_path' => $output_file
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Video processed successfully',
        'input_file' => basename($input_file),
        'output_file' => basename($output_file),
        'output_size' => filesize($output_file),
        'input_hash' => $file_hash,
        'processing_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
    ]);
    exit;
}

if ($cmd === 'upload') {
    // Check if operation is disabled
    if (in_array($cmd, $config['disabledOperations'])) {
        throw new Exception('Operation not allowed', 403);
    }

    // Security check for banned extensions
    $uploadFile = $_FILES['upload'] ?? null;
    if (!empty($uploadFile['name'])) {
        $ext = strtolower(pathinfo($uploadFile['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $config['banExtensions'])) {
            error_log("BANNED: " . $uploadFile['name']);
            http_response_code(403);
            echo json_encode(['error' => 'Dangerous extension: ' . $ext]);
            exit;
        }
    }

    // Fix for empty files
    if (($uploadFile['size'] ?? 0) === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Empty files not allowed', 'code' => 400]);
        exit;
    }

    //  Use ChunkUploader for ALL files (removes < 5MB special case)
    $uploader = new ChunkUploader([
        'maxFileSize' => $config['maxFileSize'],
        'allowedMimeTypes' => $config['allowedMimeTypes'],
        'banExtensions' => $config['banExtensions']
    ]);

    // Decode target path (if base64 encoded), otherwise use root
$targetPath = $_POST['target'] ?? '';
if (!empty($targetPath)) {
    // Decode and convert to absolute path
    $relativePath = base64_decode($targetPath);
    $targetPath = $config['rootPath'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    
    // Ensure directory exists
    if (!is_dir($targetPath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Target directory does not exist', 'code' => 404]);
        exit;
    }
} else {
    $targetPath = $config['rootPath'];
}

$result = $uploader->handleUpload($uploadFile, $targetPath, $_POST);
    echo json_encode($result);
    exit;
}

    // Check if operation is disabled
    if (in_array($cmd, $config['disabledOperations'])) {
        throw new Exception('Operation not allowed', 403);
    }
    
    // Initialize file manager and execute command
$fm = new MyFileManager($config, $user);
$response = $fm->execute($cmd, $_REQUEST);
    
    // Return JSON response
    echo json_encode($response);
    exit;
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
