<?php
require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$guest_id = isset($_POST['guest_id']) ? intval($_POST['guest_id']) : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

// Validate input
if ($guest_id <= 0 || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    // Connect to database
    $conn = connectToDatabase();
    
    // First, get the event_pin for this guest
    $sql = "SELECT ep.event_pin 
            FROM event_guests eg 
            JOIN event_pins ep ON eg.event_id = ep.event_id 
            WHERE eg.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $guest_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Guest not found');
    }
    
    $row = $result->fetch_assoc();
    $event_pin = $row['event_pin'];
    
    // Now update the status
    $sql = "UPDATE event_guests SET call_attendance_feedback = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $status, $guest_id);
    
    if ($stmt->execute()) {
        // Return success response with redirect URL
        $redirectUrl = 'detailed_report.php?event_pin=' . urlencode($event_pin);
        echo json_encode([
            'success' => true,
            'redirect' => $redirectUrl
        ]);
    } else {
        throw new Exception('Failed to update status');
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
