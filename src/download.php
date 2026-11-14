<?php
/**
 * // download.php
 * Public Download Page - Standalone
 * Generic version with configurable authentication
 */

// ============================================================================
// CONFIGURATION
// ============================================================================

// FILE PATH OF THE JSON DIRECTORY (MUST MATCH THE PLUGIN)
define('PUBLIC_LINKS_DIR', __DIR__ . '/files/public_links');

// DATE/TIME TIMEZONE
define('DISPLAY_TIMEZONE', 'UTC');

// AUTHENTICATION CONFIGURATION
// Implement your own authentication by modifying isUserAuthenticated() below
define('AUTH_ENABLED', false); // Set to true to enable custom authentication
define('AUTH_LOGIN_URL', '/login.php'); // URL to redirect for login

// ============================================================================
// PREVENT BROWSER CACHING
// ============================================================================
header_remove('ETag');
header_remove('Pragma');
header_remove('Cache-Control');
header_remove('Last-Modified');
header_remove('Expires');

header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Cloudflare / CDN cache bypass headers
header('CF-Cache-Status: BYPASS');
header('CDN-Cache-Control: no-cache');
header('Vary: *');

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Validate token format (64 lowercase hex characters)
 *
 * @param string $token
 * @return bool
 */
function isValidToken($token) {
    return is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1;
}

/**
 * Find link file by token safely
 *
 * @param string $token
 * @return string|null
 */
function findLinkFile($token) {
    if (!isValidToken($token)) {
        return null;
    }
    $linkFile = rtrim(PUBLIC_LINKS_DIR, '/\\') . DIRECTORY_SEPARATOR . $token . '.json';
    return is_file($linkFile) ? $linkFile : null;
}

/**
 * Load link data by token
 *
 * @param string $token
 * @return array|null
 */
function loadLinkData($token) {
    $linkFile = findLinkFile($token);

    if (!$linkFile || !is_readable($linkFile)) {
        return null;
    }

    $content = @file_get_contents($linkFile);
    if ($content === false) {
        return null;
    }

    $data = json_decode($content, true);
    if (!is_array($data)) {
        error_log('JSON decode error: ' . json_last_error_msg());
        return null;
    }

    return $data;
}

/**
 * Atomically validate download limit and increment download counter
 *
 * @param string $token
 * @return array|false Updated link data on success, false if limit reached or error
 */
function incrementDownload($token) {
    $linkFile = findLinkFile($token);
    if (!$linkFile) {
        return false;
    }

    $fp = @fopen($linkFile, 'c+');
    if (!$fp) {
        return false;
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return false;
    }

    $raw = stream_get_contents($fp);
    $linkData = json_decode($raw ?: '', true);

    if (!is_array($linkData)) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }

    $maxDownloads = (int)($linkData['max_downloads'] ?? 0);
    $currentCount = (int)($linkData['download_count'] ?? 0);

    if ($maxDownloads > 0 && $currentCount >= $maxDownloads) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }

    $linkData['download_count'] = $currentCount + 1;
    $linkData['last_download_at'] = time();

    $encoded = json_encode($linkData, JSON_PRETTY_PRINT);
    if ($encoded === false) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $encoded);
    fflush($fp);

    flock($fp, LOCK_UN);
    fclose($fp);

    return $linkData;
}

/**
 * Check if user is authenticated
 * CUSTOMIZE THIS FUNCTION FOR YOUR AUTHENTICATION SYSTEM
 *
 * @return bool
 */
function isUserAuthenticated() {
    if (!AUTH_ENABLED) {
        return false;
    }

    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current authenticated user info (optional)
 *
 * @return array|null
 */
function getCurrentUser() {
    if (!isUserAuthenticated()) {
        return null;
    }

    return [
        'username' => $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'User',
        'id' => $_SESSION['user_id'] ?? null
    ];
}

// ============================================================================
// MAIN LOGIC
// ============================================================================

$token = trim((string)($_GET['t'] ?? ''));
$action = (string)($_GET['action'] ?? 'view');

if (!isValidToken($token)) {
    http_response_code(400);
    die('❌ Invalid download link');
}

$link = loadLinkData($token);

if (!$link) {
    http_response_code(404);
    die('❌ Link not found or expired');
}

// Check expiration
if ((int)($link['expires_at'] ?? 0) < time()) {
    http_response_code(410);
    die('❌ Link has expired on ' . htmlspecialchars(date('Y-m-d H:i:s', (int)$link['expires_at']), ENT_QUOTES, 'UTF-8'));
}

// Check max downloads
if ((int)($link['max_downloads'] ?? 0) > 0 && (int)($link['download_count'] ?? 0) >= (int)$link['max_downloads']) {
    http_response_code(410);
    die('❌ Download limit reached (' . (int)$link['max_downloads'] . ' downloads)');
}

// Check if registered users only
if (($link['link_type'] ?? 'public') === 'registered') {
    if (!AUTH_ENABLED) {
        http_response_code(403);
        die('🔒 This link requires authentication, but authentication is not enabled on this server.');
    }

    if (!isUserAuthenticated()) {
        http_response_code(403);
        $loginUrl = htmlspecialchars(AUTH_LOGIN_URL, ENT_QUOTES, 'UTF-8');
        die('🔒 This link requires login. Please <a href="' . $loginUrl . '" style="color: #667eea; text-decoration: underline;">login</a> first to access this file.');
    }
}

// ============================================================================
// DOWNLOAD ACTION
// ============================================================================

if ($action === 'download') {
    if (($link['link_type'] ?? 'public') === 'registered') {
        if (!AUTH_ENABLED || !isUserAuthenticated()) {
            http_response_code(403);
            die('🔒 Authentication required for download');
        }
    }

    // Verify wait time has passed
    $waitKey = 'wait_' . $token;
    $waitSeconds = max(0, (int)($link['wait_seconds'] ?? 0));
    if ($waitSeconds > 0 && (!isset($_SESSION[$waitKey]) || (time() - (int)$_SESSION[$waitKey]) < $waitSeconds)) {
        http_response_code(429);
        die('⏳ Please wait the required time before downloading');
    }

    // Decode and strictly confine file path inside root_path
    $fileHash = (string)($link['file_hash'] ?? '');
    $decodedPath = base64_decode($fileHash, true);
    $rootPath = rtrim((string)($link['root_path'] ?? ''), '/\\');

    if ($decodedPath === false || $decodedPath === '' || $rootPath === '' || strpos($decodedPath, "\0") !== false) {
        http_response_code(400);
        die('❌ Invalid file reference');
    }

    $decodedPath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $decodedPath), DIRECTORY_SEPARATOR);
    $realRoot = realpath($rootPath);
    $realFullPath = realpath($rootPath . DIRECTORY_SEPARATOR . $decodedPath);

    if (!$realRoot || !$realFullPath || !is_file($realFullPath)) {
        http_response_code(404);
        die('❌ File not found on server');
    }

    $realRootPrefix = rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($realFullPath, $realRootPrefix) !== 0) {
        http_response_code(403);
        die('❌ Access denied');
    }

    // Atomically check max_downloads and increment counter before streaming
    $updatedLink = incrementDownload($token);
    if ($updatedLink === false) {
        http_response_code(410);
        die('❌ Download limit reached or link unavailable');
    }

    $fp = @fopen($realFullPath, 'rb');
    if ($fp === false) {
        http_response_code(500);
        die('❌ Cannot open file');
    }

    $rawName = basename((string)($link['file_name'] ?? basename($realFullPath)));
    $asciiFallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $rawName);
    $utf8Encoded = rawurlencode($rawName);

    // Clean all output buffers
    while (@ob_end_clean());

    @ini_set('zlib.output_compression', '0');
    @ini_set('memory_limit', '-1');
    @ini_set('max_execution_time', '0');

    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $asciiFallback . '"; filename*=UTF-8\'\'' . $utf8Encoded);
    header('Content-Length: ' . filesize($realFullPath));
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    while (!feof($fp) && !connection_aborted()) {
        $buffer = fread($fp, 8192);
        if ($buffer === false) {
            break;
        }
        echo $buffer;
        flush();
    }
    fclose($fp);
    exit;
}

// ============================================================================
// WAIT PAGE
// ============================================================================

$_SESSION['wait_' . $token] = time();

$expiresIn = max(0, (int)$link['expires_at'] - time());
$expiresMinutes = (int)ceil($expiresIn / 60);
$fileSizeMB = number_format(((int)$link['file_size']) / 1024 / 1024, 2);
$waitSeconds = max(0, (int)($link['wait_seconds'] ?? 0));

$timezone = DISPLAY_TIMEZONE;
$expiresDate = new DateTime('@' . (int)$link['expires_at']);
$expiresDate->setTimezone(new DateTimeZone($timezone));
$expiresFormatted = $expiresDate->format('Y-m-d H:i');

$isRegisteredOnly = (($link['link_type'] ?? 'public') === 'registered');
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Download: <?= htmlspecialchars($link['file_name'], ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .dl-page-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 40px);
        }

        .dl-wrapper {
            background: white;
            max-width: 600px;
            width: 100%;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .dl-title {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .dl-user-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .dl-file-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            border-left: 4px solid #667eea;
        }

        .dl-file-info div {
            margin: 8px 0;
            color: #555;
            word-break: break-word;
        }

        .dl-file-info strong {
            color: #333;
        }

        .dl-link-type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }

        .dl-link-type-public {
            background: #e3f2fd;
            color: #1976d2;
        }

        .dl-link-type-registered {
            background: #fff3e0;
            color: #f57c00;
        }

        .dl-countdown {
            font-size: 72px;
            color: #667eea;
            text-align: center;
            margin: 40px 0;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .dl-status-text {
            text-align: center;
            color: #666;
            margin: 20px 0;
            font-size: 16px;
        }

        .dl-button {
            display: block;
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            margin-top: 20px;
            transition: transform 0.2s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            border: none;
            cursor: pointer;
        }

        .dl-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }

        .dl-progress-wrapper {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin: 20px 0;
        }

        .dl-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            width: 0%;
            transition: width 1s linear;
        }

        .dl-footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 12px;
        }

        .dl-footer .timezone-info {
            color: #999;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .dl-wrapper {
                padding: 30px 20px;
            }

            .dl-title {
                font-size: 24px;
            }

            .dl-countdown {
                font-size: 56px;
            }
        }
    </style>
</head>
<body>
    <div class="dl-page-wrapper">
        <div class="dl-wrapper">
            <h1 class="dl-title">📥 Download File</h1>

            <?php if ($currentUser): ?>
                <div class="dl-user-badge">
                    👤 Logged in as: <?= htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="dl-file-info">
                <div>
                    <strong>📄 File:</strong> <?= htmlspecialchars($link['file_name'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div>
                    <strong>🔒 Access Type:</strong>
                    <?php if ($isRegisteredOnly): ?>
                        <span class="dl-link-type-badge dl-link-type-registered">🔐 Registered Users Only</span>
                    <?php else: ?>
                        <span class="dl-link-type-badge dl-link-type-public">🌐 Public Link</span>
                    <?php endif; ?>
                </div>
                <div><strong>💾 Size:</strong> <?= $fileSizeMB ?> MB</div>
                <div><strong>📊 Downloads:</strong> <?= (int)$link['download_count'] ?><?= (int)$link['max_downloads'] > 0 ? ' / ' . (int)$link['max_downloads'] : '' ?></div>
                <div><strong>⏰ Expires in:</strong> <?= $expiresMinutes ?> minutes</div>
            </div>

            <p class="dl-status-text">⏳ Please wait while we prepare your download...</p>

            <div class="dl-progress-wrapper">
                <div class="dl-progress-bar" id="dlProgressBar"></div>
            </div>

            <div class="dl-countdown" id="dlTimer"><?= $waitSeconds ?></div>

            <a href="?t=<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>&action=download"
               class="dl-button"
               id="dlButton"
               style="display: none;">
                ⬇️ Download Now
            </a>

            <div class="dl-footer">
                Secure download • Expires <strong><?= htmlspecialchars($expiresFormatted, ENT_QUOTES, 'UTF-8') ?></strong> <span class="timezone-info">(<?= htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8') ?>)</span>
            </div>
        </div>
    </div>

    <script>
        (function () {
            let timeLeft = <?= $waitSeconds ?>;
            const totalTime = timeLeft;
            const timerEl = document.getElementById('dlTimer');
            const downloadBtn = document.getElementById('dlButton');
            const progressBar = document.getElementById('dlProgressBar');
            const statusText = document.querySelector('.dl-status-text');

            function showReady() {
                timerEl.textContent = '✅';
                timerEl.style.color = '#28a745';
                progressBar.style.width = '100%';
                downloadBtn.style.display = 'block';
                statusText.textContent = '✅ Ready to download!';
            }

            if (timeLeft <= 0) {
                showReady();
                return;
            }

            const countdown = setInterval(function () {
                timeLeft--;
                timerEl.textContent = Math.max(0, timeLeft);
                progressBar.style.width = (((totalTime - timeLeft) / totalTime) * 100) + '%';

                if (timeLeft <= 0) {
                    clearInterval(countdown);
                    showReady();
                }
            }, 1000);
        })();
    </script>
</body>
</html>
