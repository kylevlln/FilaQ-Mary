<?php
require_once __DIR__ . '/config/config.php';

if (isset($_SESSION['user_id'])) {
    log_activity('LOGOUT', 'Signed out');
}
$_SESSION = [];
session_destroy();
set_flash('You have been signed out. See you soon!', 'info');
redirect('index.php');