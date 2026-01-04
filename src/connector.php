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

        'readOnlyFolders' => ['.trash', 'system', 'config'], // Folders where upload/mkdir is blocked

        'protectedFolders' => ['.trash', 'uploads', 'backup'], // Folders that cannot be renamed/deleted

        // Folders excluded from quota calculation (infinite GB allowed)
        'quotaExcludedFolders' => ['backup'], // Folders excluded from GB quota

        // Folders where upload and mkdir are completely blocked
        'uploadBlockedFolders' => ['backup'], // No upload/mkdir allowed here and subfolders
        
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

        'extensionRestrictions' => [
        'log' => [
            'read' => true,      // Can view/download
            'write' => false,    // Cannot rename/modify
            'open' => false,     // Cannot open in editor
            'delete' => false    // Cannot delete
        ],
        'txt' => [
            'read' => true,
            'write' => true,
            'open' => true,
            'delete' => true
        ],
        'pdf' => [
            'read' => true,
            'write' => false,
            'open' => true,
            'delete' => true
        ],
        // Add more extensions as needed
    ],
    
    // Default permissions for extensions not listed above
    'defaultExtensionPermissions' => [
        'read' => true,
        'write' => true,
        'open' => true,
        'delete' => true
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
        'rate_limiter' => ['enabled' => false], // this enable rate limiter for video editor
         'video_editor' => [
        'enabled' => false,
        'ffmpeg_path' => '/usr/bin/ffmpeg',  // Customizable!
        //'blur_png_path' => '' //leave blank to use the default png in the video_editor.php
    ],
         'ftpplugin' => [
             'enabled' => false
        //     'host' => 'ftp.example.com',
        //     'username' => 'user',
        //     'password' => 'pass',
        //     'port' => 21,
        //     'passive' => true
        ],
    'publiclinks' => [
        'enabled' => false, // ENABLED/DISABLED download public links plugin
    ]
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
            //error_log("Skipped plugin {$plugin_name}: disabled or not configured");
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

        // Handle public links plugin commands
if (strpos($cmd, 'publiclink_') === 0) {
    if (!isset($config['plugins']['publiclinks'])) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Public links plugin not available']);
        exit;
    }
    
    $result = $config['plugins']['publiclinks']->handleCommand($cmd, $_REQUEST, $user);
    echo json_encode($result);
    exit;
}

/**
 * Video Processing Endpoint (delegate to plugin)
 */
if ($cmd === 'video_process') {
    // Check rate limiter
    if (!isset($config['plugins']['rate_limiter'])) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Rate limiter not available']);
        exit;
    }
    
    $rateCheck = $config['plugins']['rate_limiter']->checkVideoProcessLimit($_SERVER['REMOTE_ADDR']);
    if (!$rateCheck['success']) {
        http_response_code(429);
        echo json_encode($rateCheck);
        exit;
    }
    
    // Check video processor plugin
    if (!isset($config['plugins']['video_editor'])) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Video processor plugin not available']);
        exit;
    }
    
    // Delegate to plugin
    $file_hash = $_POST['target'] ?? '';
    $params_json = $_POST['params'] ?? '{}';
    $params = json_decode($params_json, true);
    
    //error_log("DEBUG: Video process params: " . json_encode($params));
    
    $result = $config['plugins']['video_editor']->processVideo($file_hash, $params, $config['rootPath']);
    
    http_response_code($result['code'] ?? ($result['success'] ? 200 : 500));
    echo json_encode($result);
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
        // Gestisci sia singolo file che array multipli
        $fileNames = is_array($uploadFile['name']) ? $uploadFile['name'] : [$uploadFile['name']];
        
        foreach ($fileNames as $fileName) {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (in_array($ext, $config['banExtensions'])) {
                error_log("BANNED: " . $fileName);
                http_response_code(403);
                echo json_encode(['error' => 'Dangerous extension: ' . $ext]);
                exit;
            }
        }
    }

    // Fix for empty files
    if (($uploadFile['size'] ?? 0) === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Empty files not allowed', 'code' => 400]);
        exit;
    }

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

    // Check if upload is blocked in this folder
    if (isset($config['uploadBlockedFolders']) && !empty($config['uploadBlockedFolders'])) {
        $realPath = realpath($targetPath);
        $realRootPath = realpath($config['rootPath']);
        
        if ($realPath && $realRootPath) {
            // Calculate relative path from root
            $relativePathCheck = '';
            if ($realPath === $realRootPath) {
                $relativePathCheck = '';
            } elseif (strpos($realPath, $realRootPath) === 0) {
                $relativePathCheck = substr($realPath, strlen($realRootPath));
                $relativePathCheck = ltrim($relativePathCheck, DIRECTORY_SEPARATOR);
                $relativePathCheck = str_replace(DIRECTORY_SEPARATOR, '/', $relativePathCheck);
            }
            
            // Check if folder is blocked (exact match or subfolder)
            foreach ($config['uploadBlockedFolders'] as $folder) {
                $folder = trim($folder, '/');
                
                // Log for debugging
                error_log("Upload check: comparing '$relativePathCheck' with blocked folder '$folder'");
                
                // Check exact match or subfolder
                if ($relativePathCheck === $folder || strpos($relativePathCheck, $folder . '/') === 0) {
                    error_log("Upload BLOCKED in folder: $relativePathCheck");
                    http_response_code(403);
                    echo json_encode(['error' => 'Cannot upload to this folder (upload blocked)', 'code' => 403]);
                    exit;
                }
            }
            
            error_log("Upload ALLOWED in folder: $relativePathCheck");
        }
    }

    //  Use ChunkUploader for ALL files (removes < 5MB special case)
    $uploader = new ChunkUploader([
        'maxFileSize' => $config['maxFileSize'],
        'allowedMimeTypes' => $config['allowedMimeTypes'],
        'banExtensions' => $config['banExtensions']
    ]);

    $result = $uploader->handleUpload($uploadFile, $targetPath, $_POST);
    echo json_encode($result);
    exit;
}

// Clean output for download command
if ($cmd === 'download') {
    @ini_set('memory_limit', '-1');
    @ini_set('max_execution_time', '0');
    
    while (@ob_end_clean()); // Clear all buffers
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
