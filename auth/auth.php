<?php
session_start();
require_once dirname(__DIR__) . '/local_db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    if (!isLoggedIn()) return null;
    
    $conn = connectToUserDB();
    $stmt = $conn->prepare("SELECT r.permissions 
                           FROM users u 
                           JOIN roles r ON u.role_id = r.id 
                           WHERE u.id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? json_decode($row['permissions']) : null;
}

function hasPermission($permission) {
    $permissions = getUserRole();
    return $permissions && isset($permissions->$permission) && $permissions->$permission === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requirePermission($permission) {
    if (!hasPermission($permission)) {
        header('HTTP/1.0 403 Forbidden');
        echo "You don't have permission to access this page.";
        exit;
    }
}

function getUserEvents() {
    if (!isLoggedIn()) return [];
    
    $conn = connectToUserDB();
    $stmt = $conn->prepare("SELECT e.* 
                           FROM user_event ue 
                           JOIN events e ON ue.event_id = e.id 
                           WHERE ue.user_id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}
