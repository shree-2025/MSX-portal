<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php'; // If using composer for JWT
use Firebase\JWT\JWT;

// ZEGOCLOUD credentials (move these to config.php for security)
$app_id = '692772824';
$server_secret = '6ed8f569289bbc466031a5839d1bf1be';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id = $_POST['room_id'] ?? '';
    $user_id = $_POST['user_id'] ?? '';
    $user_name = $_POST['user_name'] ?? 'Participant';
    
    if (empty($room_id) || empty($user_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }
    
    // Generate token (using JWT as per ZEGOCLOUD docs)
    $payload = [
        'room_id' => $room_id,
        'user_id' => $user_id,
        'user_name' => $user_name,
        'exp' => time() + 3600 // 1 hour expiration
    ];
    $token = JWT::encode($payload, $server_secret, 'HS256');
    
    echo json_encode(['token' => $token]);
}