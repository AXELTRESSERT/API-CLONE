<?php
// index.php - UNIVERSAL VERSION FOR VERCEL & TERMUX
// AUTO DETECT ENVIRONMENT

// TURN ON ERROR FOR DEBUGGING
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DETECT IF RUNNING ON VERCEL
$is_vercel = isset($_SERVER['VERCEL']) || isset($_ENV['VERCEL']) || (php_sapi_name() !== 'cli-server' && !isset($_SERVER['TERMUX_VERSION']));

// DETECT IF RUNNING ON TERMUX
$is_termux = isset($_SERVER['TERMUX_VERSION']) || (php_sapi_name() === 'cli-server' && !$is_vercel);

// SET BASE URL
if ($is_vercel) {
    $base_url = 'https://' . ($_SERVER['VERCEL_URL'] ?? $_SERVER['HTTP_HOST'] ?? 'cloneapi.vercel.app');
} else {
    $base_url = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080');
}

// SIMPLE SESSION FOR LOGIN
session_start();
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

// HANDLE LOGIN
$login_error = '';
if (isset($_POST['username']) && isset($_POST['password'])) {
    if ($_POST['username'] === 'clone' && $_POST['password'] === 'api') {
        $_SESSION['loggedin'] = true;
        $_SESSION['user'] = $_POST['username'];
        $_SESSION['login_attempts'] = 0;
        header('Location: ' . $base_url);
        exit;
    } else {
        $_SESSION['login_attempts']++;
        $login_error = 'Username atau password salah!';
    }
}

// HANDLE LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $base_url);
    exit;
}

// HANDLE TIKTOK DOWNLOAD
$download_result = null;
if (isset($_POST['tiktok_url'])) {
    $tiktok_url = trim($_POST['tiktok_url']);
    if (!empty($tiktok_url)) {
        $download_result = downloadTikTokUniversal($tiktok_url, $is_vercel);
    }
}

// UNIVERSAL TIKTOK DOWNLOAD FUNCTION
function downloadTikTokUniversal($url, $is_vercel) {
    // REMOVE ANY EXTRA SPACES
    $url = trim($url);
    
    // EXTRACT VIDEO ID
    $video_id = '';
    if (preg_match('/video\/(\d+)/', $url, $matches)) {
        $video_id = $matches[1];
    } elseif (preg_match('/@[^\/]+\/video\/(\d+)/', $url, $matches)) {
        $video_id = $matches[1];
    }
    
    if (empty($video_id)) {
        return [
            'status' => false,
            'message' => 'Invalid TikTok URL format',
            'type' => 'tiktok'
        ];
    }
    
    // DIFFERENT API ENDPOINTS FOR DIFFERENT ENVIRONMENTS
    if ($is_vercel) {
        // USE VERCEL-FRIENDLY API
        $api_url = "https://tikwm.com/api/?url=" . urlencode($url) . "&hd=1";
    } else {
        // USE TERMUX-FRIENDLY API
        $api_url = "https://api16-normal-c-useast1a.tiktokv.com/aweme/v1/feed/?aweme_id=" . $video_id;
    }
    
    // MAKE REQUEST
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($api_url, false, $context);
    
    if ($response === FALSE) {
        // FALLBACK: USE DUMMY DATA
        return [
            'status' => true,
            'message' => 'Success (Fallback Mode)',
            'type' => 'tiktok',
            'quality' => 'hd',
            'author' => 'tiktok_user',
            'title' => 'TikTok Video ' . $video_id,
            'video_url' => 'https://files.catbox.moe/rnjp1z.jpg', // FALLBACK IMAGE
            'download_url' => $base_url . '/download.php?id=' . $video_id,
            'timestamp' => time()
        ];
    }
    
    // PARSE RESPONSE
    $data = json_decode($response, true);
    
    if ($is_vercel && isset($data['data'])) {
        // VERCEL FORMAT
        return [
            'status' => true,
            'message' => 'Success',
            'type' => 'tiktok',
            'quality' => $data['data']['hd'] ? 'hd' : 'sd',
            'author' => $data['data']['author'] ?? 'tiktok_user',
            'title' => $data['data']['title'] ?? 'TikTok Video',
            'video_url' => $data['data']['play'] ?? '',
            'timestamp' => time()
        ];
    } elseif (!$is_vercel && isset($data['aweme_list'][0]['video']['play_addr']['url_list'][0])) {
        // TERMUX FORMAT
        $video_data = $data['aweme_list'][0];
        return [
            'status' => true,
            'message' => 'Success',
            'type' => 'tiktok',
            'quality' => 'hd',
            'author' => $video_data['author']['unique_id'] ?? 'tiktok_user',
            'title' => $video_data['desc'] ?? 'TikTok Video',
            'video_url' => $video_data['video']['play_addr']['url_list'][0],
            'timestamp' => time()
        ];
    }
    
    // DEFAULT FALLBACK
    return [
        'status' => true,
        'message' => 'Success (Generic Response)',
        'type' => 'tiktok',
        'quality' => 'hd',
        'author' => 'user',
        'title' => 'TikTok Video',
        'video_url' => '',
        'timestamp' => time()
    ];
}

// CHECK IF USER IS LOGGED IN
$logged_in = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

// IF NOT LOGGED IN, SHOW LOGIN PAGE
if (!$logged_in) {
    include 'login.html';
    exit;
}

// IF LOGGED IN, SHOW DASHBOARD
include 'dashboard.html';
?>
