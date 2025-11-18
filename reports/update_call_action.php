<?php
require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$guest_id = isset($_POST['guest_id']) ? intval($_POST['guest_id']) : 0;
$call_status = isset($_POST['call_status']) ? trim($_POST['call_status']) : '';

if ($guest_id <= 0 || $call_status === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    $conn = connectToDatabase();

    // Fetch event_pin for redirect context
    $sql = "SELECT ep.event_pin 
            FROM event_guests eg 
            JOIN event_pins ep ON eg.event_id = ep.event_id 
            WHERE eg.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $guest_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        throw new Exception('Guest not found');
    }

    $row = $res->fetch_assoc();
    $event_pin = $row['event_pin'];

    // Update call_status (new column)
    $sql = "UPDATE event_guests SET call_status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $call_status, $guest_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'redirect' => 'detailed_report.php?event_pin=' . urlencode($event_pin)
        ]);
    } else {
        throw new Exception('Failed to update call action');
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
