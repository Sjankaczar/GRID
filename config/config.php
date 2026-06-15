<?php

// Informasi Aplikasi
define('APP_NAME', 'GRID');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost:8000');

// Path
define('ROOT_PATH', dirname(__DIR__) . '/');
define('UPLOAD_PATH', ROOT_PATH . 'uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');

// Batas Upload
define('MAX_FILE_SIZE_KB', 10240); 
define('ALLOWED_SPRITE_EXT', ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg']);
define('ALLOWED_AUDIO_EXT',  ['mp3', 'wav', 'ogg', 'flac']);
define('ALLOWED_SCRIPT_EXT', ['php', 'js', 'lua', 'cs', 'gd', 'py', 'txt', 'json', 'xml', 'zip']);

// Role
define('ROLE_ADMIN',  'Admin');
define('ROLE_MEMBER', 'Member');
define('ROLE_GUEST',  'Guest');

// Session
define('SESSION_TIMEOUT', 3600); 

// Timezone
date_default_timezone_set('Asia/Jakarta');
