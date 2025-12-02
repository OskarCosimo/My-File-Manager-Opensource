<?php
/**
 * My File Manager - Core Class
 * 
 * Main class handling all file operations with security checks
 * This file can be safely excluded from http access
 * 
 * @package MyFileManager
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */

class MyFileManager {
    private $config;
    private $user;
    private $security;
    private $currentPath;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     * @param array $user User information
     */
    public function __construct($config, $user) {
        $this->config = $config;
        $this->user = $user;
        $this->security = new Security();
        $this->currentPath = $config['rootPath'];
        
        // Create root directory if not exists
        if (!is_dir($this->config['rootPath'])) {
            mkdir($this->config['rootPath'], 0750, true);
        }
        
        // Create trash directory if enabled
        if ($this->config['enableTrash'] && !is_dir($this->config['trashPath'])) {
            mkdir($this->config['trashPath'], 0750, true);
        }
    }
    
    /**
     * Execute command
     * 
     * @param string $cmd Command name
     * @param array $params Request parameters
     * @return array Response data
     */
    public function execute($cmd, $params) {
    $method = 'cmd' . ucfirst($cmd);
    
    // Add support for restore command
    if ($cmd === 'restore') {
        return $this->cmdRestore($params);
    }
    
    if (!method_exists($this, $method)) {
        throw new Exception("Unknown command: {$cmd}", 400);
    }
    
    return $this->$method($params);
}
    
    /**
     * Open directory command
     * 
     * @param array $params Parameters
     * @return array Directory contents
     */
    private function cmdOpen($params) {
    $target = $params['target'] ?? '';
    $path = $this->resolvePath($target);
    
    // Check permissions
    if (!$this->hasPermission('read')) {
        throw new Exception('Permission denied', 403);
    }
    
    // Get files in directory
    $files = [];
    if (is_dir($path)) {
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            $fileInfo = $this->getFileInfo($itemPath);
            
            if ($fileInfo) {
                $files[] = $fileInfo;
            }
        }
    }
    
    // Normalize both paths with realpath to ensure proper comparison
    $realPath = realpath($path);
    $realRootPath = realpath($this->config['rootPath']);
    
    // Calculate relative path from root
    $relativePath = '';
    if ($realPath && $realRootPath && strpos($realPath, $realRootPath) === 0) {
        $relativePath = substr($realPath, strlen($realRootPath));
        $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);
    }
    
    // Normalize to forward slashes for consistency
    $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
    
    // Build response
    $result = [
        'cwd' => [
            'hash' => base64_encode($relativePath),
            'name' => basename($path) ?: 'Root',
            'mime' => 'directory',
            'read' => true,
            'write' => $this->hasPermission('write')
        ],
        'files' => $files
    ];
    
    // Add quota if available
    if ($this->hasPermission('quota')) {
        $result['quota'] = $this->getQuota();
    }
    
    return $result;
}

/**
 * Restore files from trash folder
 *
 * @param array $params Should include 'hashes' => array of base64 encoded paths in trash
 * @return array Restore result summary
 */
private function cmdRestore($params) {
    if (!$this->hasPermission('write')) {
        throw new Exception('Permission denied', 403);
    }

    $hashes = $params['hashes'] ?? [];
    if (!is_array($hashes) || empty($hashes)) {
        throw new Exception('No files specified for restore', 400);
    }
    
    $restored = [];
    $errors = [];
    
    foreach ($hashes as $hash) {
        $trashPathEncoded = $hash;
        $trashPath = $this->resolvePath($trashPathEncoded);  // Path of the trash
        
        $relativeTrashPath = base64_decode($trashPathEncoded);
        $originalRelativePath = preg_replace('#^\.trash/?#', '', $relativeTrashPath);
        
        $originalPath = $this->config['rootPath'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $originalRelativePath);
        
        // verify paths
        if (!file_exists($trashPath)) {
            $errors[] = "{$trashPath} does not exist in trash";
            continue;
        }
        if (file_exists($originalPath)) {
            $errors[] = "{$originalPath} already exists";
            continue;
        }
        
        // Create original directory if not exists
        $originalDir = dirname($originalPath);
        if (!is_dir($originalDir)) {
            mkdir($originalDir, 0750, true);
        }
        
        // Move file back to original location
        if (@rename($trashPath, $originalPath)) {
            $restored[] = $originalPath;
        } else {
            $errors[] = "Failed to restore {$trashPath}";
        }
    }
    
    return [
        'success' => count($restored),
        'restored' => $restored,
        'errors' => $errors
    ];
}
    
    /**
     * Upload file command (chunked support)
     * 
     * @param array $params Parameters
     * @return array Upload result
     */
    private function cmdUpload($params) {
    if (!$this->hasPermission('write')) {
        throw new Exception('Permission denied', 403);
    }
    
    $target = $params['target'] ?? '';
    $path = $this->resolvePath($target);
    
    // Check quota before upload
    if (!$this->checkQuota()) {
        throw new Exception('Quota exceeded', 507);
    }
    
    $uploader = new ChunkUploader($this->config);
    $result = $uploader->handleUpload($_FILES['upload'] ?? [], $path, $params);
    
    // Check if path exists in result
    if (!isset($result['path']) || empty($result['path'])) {
        // Chunk upload in progress - return without file info
        return [
            'chunkMerged' => $result['completed'] ?? false,
            'received' => $result['received'] ?? 0,
            'total' => $result['total'] ?? 0
        ];
    }
    
    // Verify file exists before getting info
    if (!file_exists($result['path'])) {
        throw new Exception('Uploaded file not found', 500);
    }
    
    return [
        'added' => [$this->getFileInfo($result['path'])],
        'chunkMerged' => $result['completed'] ?? false
    ];
}
    
    /**
     * Download file command
     * 
     * @param array $params Parameters
     */
    /**
 * Download file command
 * 
 * @param array $params Parameters
 */
private function cmdDownload($params) {
    if (!$this->hasPermission('read')) {
        throw new Exception('Permission denied', 403);
    }
    
    $target = $params['target'] ?? '';
    $path = $this->resolvePath($target);
    
    if (!file_exists($path) || !is_file($path)) {
        throw new Exception('File not found', 404);
    }
    
    // Get file info
    $fileSize = filesize($path);
    $fileName = basename($path);
    
    // CRITICAL: Clean ALL output buffers before sending file
    while (@ob_end_clean());
    
    // Disable output compression
    @ini_set('zlib.output_compression', 0);
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    
    // Set headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . $fileSize);
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Expires: 0');
    
    // Use fpassthru instead of readfile (more reliable)
    $fp = @fopen($path, 'rb');
    if ($fp === false) {
        throw new Exception('Cannot open file', 500);
    }
    
    fpassthru($fp);
    fclose($fp);
    
    exit;
}
    
/**
 * Delete file/folder command
 * 
 * @param array $params Parameters
 * @return array Deletion result
 */
private function cmdDelete($params) {
    if (!$this->hasPermission('delete')) {
        throw new Exception('Permission denied', 403);
    }
    
    $targets = $params['targets'] ?? [];
    $removed = [];
    
    foreach ($targets as $target) {
        $path = $this->resolvePath($target);
        
        // If deleting from trash folder, delete permanently
        $realTrashPath = realpath($this->config['trashPath']);
        $realPath = realpath($path);
        
        if ($realTrashPath && $realPath && strpos($realPath, $realTrashPath) === 0) {
            // Permanent delete
            if (is_dir($realPath)) {
                $this->deleteDirectory($realPath);
            } else {
                unlink($realPath);
            }
        } else {
            // Move to trash instead of permanent delete
            $trashPath = $this->config['trashPath'] . DIRECTORY_SEPARATOR . basename($path);
            rename($path, $trashPath);
        }
        
        $removed[] = $target;
    }
    
    return ['removed' => $removed];
}
    
    /**
     * Rename file/folder command
     * 
     * @param array $params Parameters
     * @return array Rename result
     */
    private function cmdRename($params) {
    if (!$this->hasPermission('write')) {
        throw new Exception('Permission denied', 403);
    }
    
    $target = $params['target'] ?? '';
    $name = $params['name'] ?? '';

    // SECURITY: Sanitize & Check banned extensions
$name = $this->security->sanitizeFilename($name);

// Block dangerous extensions
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if (!empty($ext) && isset($this->config['banExtensions']) && in_array($ext, $this->config['banExtensions'])) {
    throw new Exception('Dangerous extension not allowed: ' . $ext, 403);
}
    
    $oldPath = $this->resolvePath($target);
    $newPath = dirname($oldPath) . DIRECTORY_SEPARATOR . $name;
    
    if (file_exists($newPath)) {
        throw new Exception('File already exists', 409);
    }
    
    rename($oldPath, $newPath);
    
    return [
        'added' => [$this->getFileInfo($newPath)],
        'removed' => [$target]
    ];
}
    
    /**
     * Copy file/folder command
     * 
     * @param array $params Parameters
     * @return array Copy result
     */
    private function cmdCopy($params) {
        if (!$this->hasPermission('write')) {
            throw new Exception('Permission denied', 403);
        }
        
        $targets = $params['targets'] ?? [];
        $destination = $params['dst'] ?? '';
        $added = [];
        
        $dstPath = $this->resolvePath($destination);
        
        foreach ($targets as $target) {
            $srcPath = $this->resolvePath($target);
            $newPath = $dstPath . DIRECTORY_SEPARATOR . basename($srcPath);
            
            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $newPath);
            } else {
                copy($srcPath, $newPath);
            }
            
            $added[] = $this->getFileInfo($newPath);
        }
        
        return ['added' => $added];
    }
    
    /**
     * Cut (move) file/folder command
     * 
     * @param array $params Parameters
     * @return array Move result
     */
    private function cmdCut($params) {
        if (!$this->hasPermission('write')) {
            throw new Exception('Permission denied', 403);
        }
        
        $targets = $params['targets'] ?? [];
        $destination = $params['dst'] ?? '';
        $added = [];
        $removed = [];
        
        $dstPath = $this->resolvePath($destination);
        
        foreach ($targets as $target) {
            $srcPath = $this->resolvePath($target);
            $newPath = $dstPath . DIRECTORY_SEPARATOR . basename($srcPath);
            
            rename($srcPath, $newPath);
            
            $added[] = $this->getFileInfo($newPath);
            $removed[] = $target;
        }
        
        return [
            'added' => $added,
            'removed' => $removed
        ];
    }
    
 /**
 * Create directory command
 *
 * @param array $params Parameters
 * @return array Directory creation result
 */
private function cmdMkdir($params) {
    if (!$this->hasPermission('write')) {
        throw new Exception('Permission denied', 403);
    }
    
    $target = $params['target'] ?? '';
    $name = $params['name'] ?? 'New Folder';
    
    // Prevent creating folders starting with dot
    if (substr($name, 0, 1) === '.') {
        throw new Exception('Folder names cannot start with a dot (.)', 400);
    }
    
    // Sanitize directory name
    $name = $this->security->sanitizeFilename($name);
    
    $parentPath = $this->resolvePath($target);
    $newPath = $parentPath . DIRECTORY_SEPARATOR . $name;
    
    if (file_exists($newPath)) {
        throw new Exception('Directory already exists', 409);
    }
    
    mkdir($newPath, 0750, true);
    return ['added' => [$this->getFileInfo($newPath)]];
}
    
    /**
     * Get file/folder info command
     * 
     * @param array $params Parameters
     * @return array File information
     */
    private function cmdInfo($params) {
        if (!$this->hasPermission('read')) {
            throw new Exception('Permission denied', 403);
        }
        
        $target = $params['target'] ?? '';
        $path = $this->resolvePath($target);
        
        return $this->getFileInfo($path, true);
    }
    
    /**
     * Search files command
     * 
     * @param array $params Parameters
     * @return array Search results
     */
    private function cmdSearch($params) {
        if (!$this->hasPermission('read')) {
            throw new Exception('Permission denied', 403);
        }
        
        $query = $params['q'] ?? '';
        $target = $params['target'] ?? '';
        
        $searchPath = $this->resolvePath($target);
        $results = $this->searchFiles($searchPath, $query);
        
        return ['files' => $results];
    }
    
    /**
     * Resolve path from hash/target
     * 
     * @param string $target Target hash
     * @return string Real filesystem path
     * @throws Exception
     */
    private function resolvePath($target) {
    if (empty($target)) {
        return $this->config['rootPath'];
    }
    
    // Decode target (base64 encoded path)
    $decoded = base64_decode($target);
    
    // Remove leading slash to make it relative
    $decoded = ltrim($decoded, '/\\');
    
    // Security: Prevent path traversal
    if (!$this->security->isPathSafe($decoded, $this->config['rootPath'])) {
        throw new Exception('Invalid path: Path traversal detected', 403);
    }
    
    $realPath = realpath($this->config['rootPath'] . DIRECTORY_SEPARATOR . $decoded);
    
    if ($realPath === false) {
        // Path doesn't exist yet (for new file/folder creation)
        $realPath = $this->config['rootPath'] . DIRECTORY_SEPARATOR . $decoded;
    }
    
    // Double-check path is within root
    if (!$this->security->isPathSafe($realPath, $this->config['rootPath'])) {
        throw new Exception('Access denied: Path outside root', 403);
    }
    
    return $realPath;
}
    
    /**
     * Get file information
     * 
     * @param string $path File path
     * @param bool $detailed Include detailed info
     * @return array File information
     */
    private function getFileInfo($path, $detailed = false) {
    // Check if path is valid
    if (empty($path) || !file_exists($path)) {
        error_log("getFileInfo called with invalid path: " . var_export($path, true));
        return null;
    }
    
    // Normalize paths with realpath
    $realPath = realpath($path);
    $realRootPath = realpath($this->config['rootPath']);
    
    // Calculate relative path
    $relativePath = '';
    if ($realPath && $realRootPath && strpos($realPath, $realRootPath) === 0) {
        $relativePath = substr($realPath, strlen($realRootPath));
        $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);
    }
    
    // Normalize to forward slashes
    $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
    
    $hash = base64_encode($relativePath);
    
    // Safe MIME type detection
    $mime = 'application/octet-stream';
    if (is_dir($path)) {
        $mime = 'directory';
    } elseif (is_file($path) && is_readable($path)) {
        $mime = mime_content_type($path);
    }
    
    $info = [
        'name' => basename($path),
        'hash' => $hash,
        'mime' => $mime,
        'ts' => filemtime($path),
        'size' => is_file($path) ? filesize($path) : 0,
        'read' => true,
        'write' => $this->hasPermission('write'),
        'locked' => false
    ];
        
        if (is_dir($path)) {
            $info['volumeid'] = 'l1_';
            $info['dirs'] = $this->hasDirs($path) ? 1 : 0;
        }
        
        if ($detailed) {
            $info['path'] = $relativePath;
            $info['url'] = $this->config['rootUrl'] . ltrim($relativePath, '/');
            
            if (is_file($path)) {
                $info['dim'] = $this->getImageDimensions($path);
            }
        }
        
        return $info;
    }
    
    /**
     * Check if directory has subdirectories
     * 
     * @param string $path Directory path
     * @return bool
     */
    private function hasDirs($path) {
        if (!is_dir($path)) return false;
        
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (is_dir($path . DIRECTORY_SEPARATOR . $item)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get MIME type of file
     * 
     * @param string $path File path
     * @return string MIME type
     */
    private function getMimeType($path) {
        if (is_dir($path)) {
            return 'directory';
        }
        
        if (function_exists('mime_content_type')) {
            return mime_content_type($path);
        }
        
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);
            return $mime;
        }
        
        return 'application/octet-stream';
    }
    
    /**
     * Get image dimensions
     * 
     * @param string $path Image path
     * @return string|null Dimensions string
     */
    private function getImageDimensions($path) {
        if (!is_file($path)) return null;
        
        $mime = $this->getMimeType($path);
        if (strpos($mime, 'image/') !== 0) return null;
        
        $size = @getimagesize($path);
        if ($size) {
            return $size[0] . 'x' . $size[1];
        }
        
        return null;
    }
    
    /**
     * Get quota information
     * 
     * @return array Quota info
     */
    private function getQuota() {
    // User-specific quota
    $userQuota = isset($this->user['quota']) ? (int)$this->user['quota'] : 0;
    
    // Calculate used, total, and free space
    if ($userQuota <= 0) {
        $totalSpace = disk_total_space($this->config['rootPath']);
        $freeSpace = disk_free_space($this->config['rootPath']);
        $usedSpace = $totalSpace - $freeSpace;
    } else {
        //  User has specific quota
        $usedSpace = $this->calculateUserSpace($this->config['rootPath']);
        $totalSpace = $userQuota;
        $freeSpace = $userQuota - $usedSpace;
    }
    
    // Get max upload size from PHP settings
    $uploadMaxSize = $this->parseSize(ini_get('upload_max_filesize'));
    $postMaxSize = $this->parseSize(ini_get('post_max_size'));
    $memoryLimit = $this->parseSize(ini_get('memory_limit'));
    
    $maxUpload = min($uploadMaxSize, $postMaxSize, $memoryLimit);
    
    // Adjust max upload based on quota
    if ($userQuota > 0 && $freeSpace > 0) {
        $maxUpload = min($maxUpload, $freeSpace);
    }
    
    return [
        'total' => (int)$totalSpace,
        'used' => (int)$usedSpace,
        'free' => max(0, (int)$freeSpace),
        'maxUpload' => max(0, (int)$maxUpload)
    ];
}

/**
 * Calculate user space usage recursively
 */
private function calculateUserSpace($path) {
    $size = 0;
    
    if (!is_dir($path) || !is_readable($path)) {
        return 0;
    }
    
    try {
        $items = scandir($path);
        if ($items === false) {
            return 0;
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            
            if (is_file($itemPath)) {
                $size += filesize($itemPath);
            } elseif (is_dir($itemPath)) {
                $size += $this->calculateUserSpace($itemPath);
            }
        }
    } catch (Exception $e) {
        error_log("Error calculating user space: " . $e->getMessage());
        return 0;
    }
    
    return $size;
}

/**
 * Parse size string (e.g., "128M") to bytes
 */
private function parseSize($size) {
    if (empty($size) || $size === '-1') {
        return PHP_INT_MAX;
    }
    
    $size = trim($size);
    $unit = strtolower(substr($size, -1));
    $value = (int)substr($size, 0, -1);
    
    if (!is_numeric($unit)) {
        switch($unit) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }
    } else {
        $value = (int)$size;
    }
    
    return $value;
}
    
/**
 * Check if quota allows upload
 * 
 * @return bool
 */
private function checkQuota() {
    if (!$this->hasPermission('quota')) {
        return true; // No quota check if permission not granted
    }
    
    $quota = $this->getQuota();
    
    if (!$quota || $quota['total'] <= 0) {
        return true; // No quota limit
    }
    
    return $quota['free'] > 0;
}
    
    /**
     * Calculate directory size
     * 
     * @param string $path Directory path
     * @return int Size in bytes
     */
    private function getDirectorySize($path) {
        $size = 0;
        
        if (!is_dir($path)) {
            return is_file($path) ? filesize($path) : 0;
        }
        
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            
            if (is_dir($fullPath)) {
                $size += $this->getDirectorySize($fullPath);
            } else {
                $size += filesize($fullPath);
            }
        }
        
        return $size;
    }
    
    /**
     * Delete directory recursively
     * 
     * @param string $path Directory path
     */
    private function deleteDirectory($path) {
        if (!is_dir($path)) return;
        
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            
            if (is_dir($fullPath)) {
                $this->deleteDirectory($fullPath);
            } else {
                unlink($fullPath);
            }
        }
        
        rmdir($path);
    }
    
    /**
     * Copy directory recursively
     * 
     * @param string $src Source path
     * @param string $dst Destination path
     */
    private function copyDirectory($src, $dst) {
        if (!is_dir($src)) return;
        
        if (!is_dir($dst)) {
            mkdir($dst, 0750, true);
        }
        
        $items = scandir($src);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $srcPath = $src . DIRECTORY_SEPARATOR . $item;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $item;
            
            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
    }
    
    /**
     * Search files recursively
     * 
     * @param string $path Search path
     * @param string $query Search query
     * @return array Found files
     */
    private function searchFiles($path, $query) {
        $results = [];
        
        if (!is_dir($path)) return $results;
        
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            
            // Check if name matches query
            if (stripos($item, $query) !== false) {
                $results[] = $this->getFileInfo($fullPath);
            }
            
            // Recursively search subdirectories
            if (is_dir($fullPath)) {
                $subResults = $this->searchFiles($fullPath, $query);
                $results = array_merge($results, $subResults);
            }
        }
        
        return $results;
    }
    
    /**
     * Check user permission
     * 
     * @param string $permission Permission name
     * @return bool
     */
    private function hasPermission($permission) {
        return in_array($permission, $this->user['permissions'] ?? []);
    }
}