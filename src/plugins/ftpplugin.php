<?php
/**
 * My File Manager - FTP Plugin
 * 
 * Storage plugin for FTP connections
 * 
 * @package MyFileManager
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */

require_once __DIR__ . '/plugininterface.php';

class ftppluginPlugin implements PluginInterface {
    private $config;
    private $connection;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct($config) {
        $this->config = $config;
        $this->connect();
    }
    
    /**
     * Connect to FTP server
     * 
     * @throws Exception
     */
    private function connect() {
        $this->connection = ftp_connect($this->config['host'], $this->config['port'] ?? 21);
        
        if (!$this->connection) {
            throw new Exception('Could not connect to FTP server', 500);
        }
        
        $login = ftp_login($this->connection, $this->config['username'], $this->config['password']);
        
        if (!$login) {
            throw new Exception('FTP login failed', 401);
        }
        
        // Enable passive mode if configured
        if ($this->config['passive'] ?? true) {
            ftp_pasv($this->connection, true);
        }
    }
    
    /**
     * List files and directories
     * 
     * @param string $path Path to list
     * @return array Files array
     */
    public function listFiles($path) {
        $list = ftp_nlist($this->connection, $path);
        $files = [];
        
        foreach ($list as $item) {
            $files[] = $this->getFileInfo($item);
        }
        
        return $files;
    }
    
    /**
     * Read file content
     * 
     * @param string $path File path
     * @return string File content
     */
    public function readFile($path) {
        $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');
        
        if (!ftp_get($this->connection, $tempFile, $path, FTP_BINARY)) {
            throw new Exception('Could not download file from FTP', 500);
        }
        
        $content = file_get_contents($tempFile);
        unlink($tempFile);
        
        return $content;
    }
    
    /**
     * Write file content
     * 
     * @param string $path File path
     * @param string $content File content
     * @return bool Success
     */
    public function writeFile($path, $content) {
        $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');
        file_put_contents($tempFile, $content);
        
        $result = ftp_put($this->connection, $path, $tempFile, FTP_BINARY);
        unlink($tempFile);
        
        return $result;
    }
    
    /**
     * Delete file or directory
     * 
     * @param string $path Path to delete
     * @return bool Success
     */
    public function delete($path) {
        // Check if directory or file
        $size = ftp_size($this->connection, $path);
        
        if ($size === -1) {
            // Directory
            return ftp_rmdir($this->connection, $path);
        } else {
            // File
            return ftp_delete($this->connection, $path);
        }
    }
    
    /**
     * Create directory
     * 
     * @param string $path Directory path
     * @return bool Success
     */
    public function createDirectory($path) {
        return ftp_mkdir($this->connection, $path) !== false;
    }
    
    /**
     * Rename/move file or directory
     * 
     * @param string $oldPath Old path
     * @param string $newPath New path
     * @return bool Success
     */
    public function rename($oldPath, $newPath) {
        return ftp_rename($this->connection, $oldPath, $newPath);
    }
    
    /**
     * Check if path exists
     * 
     * @param string $path Path to check
     * @return bool Exists
     */
    public function exists($path) {
        $list = ftp_nlist($this->connection, dirname($path));
        return in_array($path, $list);
    }
    
    /**
     * Get file info
     * 
     * @param string $path File path
     * @return array File information
     */
    public function getFileInfo($path) {
        $size = ftp_size($this->connection, $path);
        $mdtm = ftp_mdtm($this->connection, $path);
        
        return [
            'name' => basename($path),
            'path' => $path,
            'size' => $size === -1 ? 0 : $size,
            'mime' => $size === -1 ? 'directory' : 'application/octet-stream',
            'ts' => $mdtm === -1 ? time() : $mdtm,
            'read' => 1,
            'write' => 1
        ];
    }
    
    /**
     * Destructor - close connection
     */
    public function __destruct() {
        if ($this->connection) {
            ftp_close($this->connection);
        }
    }
}
