<?php
require_once 'config/config.php';

if (isset($_SESSION['user_id'])) {
    logAudit($_SESSION['user_id'], 'logout', 'auth', 'User logged out');
    session_unset();
    session_destroy();
}

redirect('/login.php');