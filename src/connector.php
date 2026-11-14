<?php
/**
 * // src/connector.php
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

require_once __DIR__ . '/myfilemanager.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/chunkuploader.php';

// Start session for token management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: application/json; charset=utf-8');

/**
 * Parse size string with units (e.g., "200MB", "10GB") to bytes
 * If no unit is specified, assumes bytes
 *
 * @param mixed $size Size value (can be string with unit or integer)
 * @return int Size in bytes
 */
function parseFileSize($size) {
    if (is_numeric($size)) {
        return (int)$size;
    }

    $size = trim((string)$size);

    if (preg_match('/^(\d+(?:\.\d+)?)\s*(B|KB|MB|GB|TB|PB)?$/i', $size, $matches)) {
        $value = (float)$matches[1];
        $unit = isset($matches[2]) ? strtoupper($matches[2]) : 'B';

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

    return 0;
}

try {
    // Path of the root folder used for file operations (customize as needed)
    $PATHTOFILES = __DIR__ . '/../files/';

    if (!is_dir($PATHTOFILES)) {
        mkdir($PATHTOFILES, 0750, true);
        error_log("Created files folder: {$PATHTOFILES}");
    }

    // Configuration array
    $config = [
        // Root path for file operations
        'rootPath' => $PATHTOFILES,

        // URL path for accessing files (leave blank to use default relative paths)
        'rootUrl' => '',

        // Maximum quota (supports units: B, KB, MB, GB, TB, PB)
        'maxQuota' => parseFileSize('5GB'),

        // Maximum file size for upload (supports units: B, KB, MB, GB, TB, PB)
        'maxFileSize' => parseFileSize('0.5GB'),

        // Folders where upload/mkdir/rename are blocked
        'readOnlyFolders' => ['.trash', 'system', 'config'],

        // Folders that cannot be renamed/deleted
        'protectedFolders' => ['.trash', 'uploads', 'backup'],

        // Folders excluded from quota calculation
        'quotaExcludedFolders' => ['backup'],

        // Folders where upload and mkdir are completely blocked (including subfolders)
        'uploadBlockedFolders' => ['backup'],

        // Allowed MIME types
        // Note: if JS cryptFiles is enabled, add 'application/octet-stream' to allow .encrypted files
        'allowedMimeTypes' => ['image/*', 'video/*', 'audio/*', 'text/*', 'application/pdf', 'text/plain'],

        // Ban dangerous extensions
        'banExtensions' => [
            // Unix/Linux executables
            'sh', 'bash', 'csh', 'ksh', 'zsh', 'tcsh', 'dash',
            'pl', 'perl', 'py', 'pyc', 'pyo', 'pyw', 'pyz',
            'rb', 'rbw', 'cgi', 'fcgi',

            // Windows executables
            'exe', 'bat', 'cmd', 'com', 'scr', 'pif', 'cpl',
            'msi', 'msp', 'dll', 'ocx', 'sys',

            // Web server scripts
            'php', 'php3', 'php4', 'php5', 'phtml', 'php7', 'phar',
            'asp', 'aspx', 'asa', 'aspx.cs', 'ashx',
            'jsp', 'jspx', 'jsw', 'jssp', 'do',
            'cfm', 'cfml', 'cfc',

            // Dangerous scripts
            'js', 'vbs', 'vbe', 'jse', 'wsf', 'wsh', 'ps1', 'psm1',

            // macOS dangerous
            'app', 'dmg', 'pkg', 'mpkg',

            // Dangerous config files
            'conf', 'cnf', 'ini', 'cfg', 'config',

            // htaccess and config server files
            'htaccess', 'htpasswd', 'htgroup',

            // Macro virus (Office)
            'docm', 'xlsm', 'pptm', 'dotm', 'xltm'
        ],

        'extensionRestrictions' => [
            'log' => [
                'read' => true,
                'write' => false,
                'open' => false,
                'delete' => false
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
            ]
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
        'trashPath' => $PATHTOFILES . '.trash/', // Keep '.trash' aligned with JS trashPath option

        // Security settings (replace tokenSecret in production)
        'security' => [
            'enableTokenAuth' => false,
            'tokenSecret' => base64_encode(hash('sha512', 'user123') . hash('sha256', 'user123')),
            'enableIPWhitelist' => false,
            'allowedIPs' => [],
            // Explicit CORS allowlist (empty = same-origin only)
            'allowedOrigins' => [],
        ],

        'plugins' => [
            'clamav' => [
                'enabled' => false,
                'clamdscan_path' => '/usr/bin/clamdscan',
            ],
            'rate_limiter' => [
                'enabled' => false
            ],
            'video_editor' => [
                'enabled' => false,
                'ffmpeg_path' => '/usr/bin/ffmpeg',
            ],
            'ftpplugin' => [
                'enabled' => false
            ],
            'publiclinks' => [
                'enabled' => false,
            ]
        ],

        // Custom authentication callback
        'authCallback' => function () {
            // Implement your authentication logic here
            return [
                'id' => 'user123',
                'username' => 'the_user_name',
                'quota' => parseFileSize('5GB'),
                'permissions' => ['read', 'write', 'delete', 'upload', 'download', 'rename', 'copy', 'move', 'mkdir', 'search', 'quota', 'info']
            ];
        }
    ];

    // Safe CORS handling (allow same-origin or explicitly whitelisted origins only)
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = $_SERVER['HTTP_ORIGIN'];
        $originHost = parse_url($origin, PHP_URL_HOST);
        $serverHost = $_SERVER['HTTP_HOST'] ?? '';
        $serverHostOnly = explode(':', $serverHost)[0];
        $allowedOrigins = $config['security']['allowedOrigins'] ?? [];

        if (($originHost && strcasecmp($originHost, $serverHostOnly) === 0) || in_array($origin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Vary: Origin');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        }
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        }
        exit(0);
    }

    // Load enabled plugins
    $plugins = [];
    $plugin_dir = __DIR__ . '/plugins/';
    if (is_dir($plugin_dir)) {
        foreach (glob($plugin_dir . '*.php') ?: [] as $plugin_file) {
            require_once $plugin_file;
            $plugin_name = basename($plugin_file, '.php');
            $class_name = $plugin_name . 'Plugin';

            if (!empty($config['plugins'][$plugin_name]['enabled']) && class_exists($class_name)) {
                try {
                    $plugins[$plugin_name] = new $class_name($config);
                } catch (Exception $e) {
                    error_log("Failed to load plugin {$plugin_name}: " . $e->getMessage());
                }
            }
        }
    }

    $config['plugins'] = $plugins;

    // Validate authentication
    $user = call_user_func($config['authCallback']);
    if ($user === false || !is_array($user)) {
        throw new Exception('Authentication required', 401);
    }

    // Token validation for security
    if (!empty($config['security']['enableTokenAuth'])) {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_POST['token'] ?? $_GET['token'] ?? '';
        if (stripos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }
        $token = trim($token);

        if (!Security::validateToken($token, $config['security']['tokenSecret'], $user['id'])) {
            throw new Exception('Invalid security token', 403);
        }
    }

    // IP whitelist check
    if (!empty($config['security']['enableIPWhitelist'])) {
        if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $config['security']['allowedIPs'] ?? [], true)) {
            throw new Exception('IP address not allowed', 403);
        }
    }

    // Create trash folder if enabled and missing
    if (!empty($config['enableTrash']) && !is_dir($config['trashPath'])) {
        mkdir($config['trashPath'], 0750, true);
        error_log("Created trash folder: {$config['trashPath']}");
    }

    // Merge GET and POST parameters (avoid $_COOKIE pollution from $_REQUEST)
    $requestParams = array_merge($_GET, $_POST);
    $cmd = strtolower(trim((string)($requestParams['cmd'] ?? 'open')));

    while (ob_get_level()) {
        ob_end_clean();
    }

    // Check if operation is disabled
    if (in_array($cmd, $config['disabledOperations'], true)) {
        throw new Exception('Operation not allowed', 403);
    }

    // Handle public links plugin commands
    if (strpos($cmd, 'publiclink_') === 0) {
        if (!isset($config['plugins']['publiclinks'])) {
            http_response_code(503);
            echo json_encode(['success' => false, 'error' => 'Public links plugin not available']);
            exit;
        }

        $result = $config['plugins']['publiclinks']->handleCommand($cmd, $requestParams, $user);
        echo json_encode($result);
        exit;
    }

    // Video Processing Endpoint (delegate to plugin)
    if ($cmd === 'video_process') {
        if (!in_array('write', $user['permissions'] ?? [], true)) {
            throw new Exception('Permission denied', 403);
        }

        if (!isset($config['plugins']['rate_limiter'])) {
            http_response_code(503);
            echo json_encode(['success' => false, 'error' => 'Rate limiter not available']);
            exit;
        }

        $rateCheck = $config['plugins']['rate_limiter']->checkVideoProcessLimit($_SERVER['REMOTE_ADDR'] ?? '');
        if (!$rateCheck['success']) {
            http_response_code(429);
            echo json_encode($rateCheck);
            exit;
        }

        if (!isset($config['plugins']['video_editor'])) {
            http_response_code(503);
            echo json_encode(['success' => false, 'error' => 'Video processor plugin not available']);
            exit;
        }

        $file_hash = $requestParams['target'] ?? '';
        $params_json = $requestParams['params'] ?? '{}';
        $params = json_decode($params_json, true);
        if (!is_array($params)) {
            throw new Exception('Invalid video processing parameters', 400);
        }

        $result = $config['plugins']['video_editor']->processVideo($file_hash, $params, $config['rootPath']);

        http_response_code($result['code'] ?? ($result['success'] ? 200 : 500));
        echo json_encode($result);
        exit;
    }

    // Handle file upload with full security validation + ClamAV hook
    if ($cmd === 'upload') {
        if (!in_array('write', $user['permissions'] ?? [], true)) {
            throw new Exception('Permission denied', 403);
        }

        $security = new Security();
        $uploadFile = $_FILES['upload'] ?? null;

        if (!$uploadFile || empty($uploadFile['name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No file uploaded', 'code' => 400]);
            exit;
        }

        // Security check for banned extensions after sanitization
        $fileNames = is_array($uploadFile['name']) ? $uploadFile['name'] : [$uploadFile['name']];
        foreach ($fileNames as $fileName) {
            $sanitizedName = $security->sanitizeFilename((string)$fileName);
            if ($sanitizedName === '' || $security->hasBlockedExtension($sanitizedName, $config['banExtensions'])) {
                $ext = strtolower(pathinfo($sanitizedName, PATHINFO_EXTENSION));
                error_log('BANNED: ' . $fileName);
                http_response_code(403);
                echo json_encode(['error' => 'Dangerous extension: ' . $ext, 'code' => 403]);
                exit;
            }
        }

        $rawSize = is_array($uploadFile['size']) ? (int)($uploadFile['size'][0] ?? 0) : (int)($uploadFile['size'] ?? 0);
        if ($rawSize <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Empty files not allowed', 'code' => 400]);
            exit;
        }

        // Resolve and strictly validate target directory inside rootPath
        $encodedTarget = $_POST['target'] ?? '';
        if ($encodedTarget !== '') {
            $relativePath = base64_decode((string)$encodedTarget, true);
            if ($relativePath === false) {
                throw new Exception('Invalid target hash', 400);
            }
            $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
            $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);

            if (!$security->isPathSafe($relativePath, $config['rootPath'])) {
                throw new Exception('Invalid target path', 403);
            }

            $targetPath = $relativePath === ''
                ? $config['rootPath']
                : rtrim($config['rootPath'], '/\\') . DIRECTORY_SEPARATOR . $relativePath;
        } else {
            $targetPath = $config['rootPath'];
        }

        $realTargetPath = realpath($targetPath);
        $realRootPath = realpath($config['rootPath']);

        if (!$realTargetPath || !is_dir($realTargetPath) || !$security->isPathSafe($realTargetPath, $config['rootPath'])) {
            http_response_code(404);
            echo json_encode(['error' => 'Target directory does not exist', 'code' => 404]);
            exit;
        }

        // Calculate relative path from root for folder policy checks
        $relativePathCheck = '';
        if ($realRootPath && strpos($realTargetPath, $realRootPath) === 0) {
            $relativePathCheck = substr($realTargetPath, strlen($realRootPath));
            $relativePathCheck = ltrim($relativePathCheck, DIRECTORY_SEPARATOR);
            $relativePathCheck = str_replace(DIRECTORY_SEPARATOR, '/', $relativePathCheck);
        }

        // Enforce readOnlyFolders
        foreach ($config['readOnlyFolders'] ?? [] as $folder) {
            $folder = trim($folder, '/\\');
            if ($relativePathCheck === $folder || strpos($relativePathCheck, $folder . '/') === 0) {
                http_response_code(403);
                echo json_encode(['error' => 'Cannot upload to this folder: read-only', 'code' => 403]);
                exit;
            }
        }

        // Enforce uploadBlockedFolders
        foreach ($config['uploadBlockedFolders'] ?? [] as $folder) {
            $folder = trim($folder, '/\\');
            if ($relativePathCheck === $folder || strpos($relativePathCheck, $folder . '/') === 0) {
                http_response_code(403);
                echo json_encode(['error' => 'Cannot upload to this folder (upload blocked)', 'code' => 403]);
                exit;
            }
        }

        // Check available quota
        $fm = new MyFileManager($config, $user);
        $quota = $fm->getQuota();
        $quotaFree = null;

        if ($quota && $quota['total'] > 0) {
            $quotaFree = $quota['free'];
            if ($quota['free'] < $rawSize) {
                http_response_code(507);
                echo json_encode([
                    'error' => 'Quota exceeded. Available: ' . $quota['free'] . ' bytes, Required: ' . $rawSize . ' bytes',
                    'code' => 507
                ]);
                exit;
            }
        }

        $uploader = new ChunkUploader([
            'maxFileSize' => $config['maxFileSize'],
            'allowedMimeTypes' => $config['allowedMimeTypes'],
            'banExtensions' => $config['banExtensions'],
            'quotaFree' => $quotaFree
        ]);

        $result = $uploader->handleUpload($uploadFile, $realTargetPath, $_POST);

        // ClamAV antivirus scan on completed file
        if (isset($result['completed']) && $result['completed'] === true && isset($result['path'])) {
            if (isset($config['plugins']['clamav'])) {
                $clamavScanner = $config['plugins']['clamav'];
                $scanResult = $clamavScanner->scanFile($result['path']);

                if (!$scanResult['success'] || !$scanResult['is_safe']) {
                    @unlink($result['path']);
                    error_log('ClamAV Security Alert: Malware detected in ' . $result['path'] . ' - File destroyed.');

                    http_response_code(406);
                    echo json_encode([
                        'error' => 'Security Alert: Potential Malware detected. The upload has been blocked and the file destroyed.',
                        'code' => 406
                    ]);
                    exit;
                }
            }
        }

        echo json_encode($result);
        exit;
    }

    // Clean output limits for download command
    if ($cmd === 'download') {
        @ini_set('memory_limit', '-1');
        @ini_set('max_execution_time', '0');

        while (@ob_end_clean());
    }

    // Initialize file manager and execute command
    $fm = new MyFileManager($config, $user);
    $response = $fm->execute($cmd, $requestParams);

    echo json_encode($response);
    exit;
} catch (Exception $e) {
    $code = (int)$e->getCode();
    http_response_code(($code >= 400 && $code < 600) ? $code : 500);
    echo json_encode([
        'error' => $e->getMessage(),
        'code' => $code ?: 500
    ]);
}
