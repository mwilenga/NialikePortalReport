<?php
require_once dirname(__DIR__) . '/db.php';

// Initialize variables
$event_pin = isset($_GET['event_pin']) ? trim($_GET['event_pin']) : '';
$show_results = !empty($event_pin);
$result = null;
$error_message = null;
$event_id = 0;

if ($show_results && !empty($event_pin)) {
    try {
        $conn = connectToDatabase();
        
        // First, get the event_id and event name for the given event_pin
        $sql = "SELECT ep.event_id, e.name 
                FROM event_pins ep 
                JOIN events e ON e.id = ep.event_id 
                WHERE ep.event_pin = ? 
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $event_pin);
        $stmt->execute();
        $pin_result = $stmt->get_result();
        
        // Debug: Log the pin lookup
        error_log("Looking up event_pin: " . $event_pin);
        
        if ($pin_result->num_rows > 0) {
            $event_data = $pin_result->fetch_assoc();
            $event_id = $event_data['event_id'];
            $event_name = $event_data['name'] ?? '';
            
            // Debug: Log the found event information
            error_log("Found event - ID: " . $event_id . ", Name: " . $event_name);
            
            // Get guest data with attendance statistics
            $sql = "SELECT 
                g.id, g.name, g.phone_number, g.recipient_msisdn,
                g.type, g.card_number, g.card_url, g.arrive_count,
                g.wa_message_status, g.sms_message_status, 
                COALESCE(NULLIF(g.call_attendance_feedback, ''), g.attendance_feedback) as feedback
                FROM event_guests g
                WHERE g.event_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $event_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Calculate attendance statistics
            $total_guests = 0;
            $total_cards = 0;
            $attended = 0;
            $not_attended = 0;
            $wa_sent = 0;
            $wa_delivered = 0;
            $sms_sent = 0;
            $sms_delivered = 0;
            
            // Get total guests as sum of card_count
            $total_guests_sql = "SELECT COALESCE(SUM(card_count), 0) as total_guests FROM event_guests WHERE event_id = ?";
            $total_guests_stmt = $conn->prepare($total_guests_sql);
            $total_guests_stmt->bind_param('i', $event_id);
            $total_guests_stmt->execute();
            $total_guests_result = $total_guests_stmt->get_result()->fetch_assoc();
            $total_guests = (int)($total_guests_result['total_guests'] ?? 0);
            
            // Store all rows for later use
            $all_guests = [];
            while ($row = $result->fetch_assoc()) {
                $all_guests[] = $row;
                
                // Count cards (non-null card numbers)
                if (!empty($row['card_number'])) {
                    $total_cards++;
                }
                
                // Count not_attended based on attendance_feedback
                if (empty($row['attendance_feedback']) || strtolower($row['attendance_feedback']) !== 'attended') {
                    $not_attended++;
                }
                
                // Check WhatsApp status
                if (!empty($row['wa_message_status'])) {
                    $wa_sent++;
                    if (in_array(strtolower($row['wa_message_status']), ['delivered', 'read'])) {
                        $wa_delivered++;
                    }
                }
                
                // Check SMS status
                if (!empty($row['sms_message_status'])) {
                    $sms_sent++;
                    if (strtolower($row['sms_message_status']) === 'delivered') {
                        $sms_delivered++;
                    }
                }
            }
            
            // Get the sum of arrive_count for this event
            $sum_sql = "SELECT COALESCE(SUM(arrive_count), 0) as total_arrived FROM event_guests WHERE event_id = ?";
            error_log("SQL Query: " . $sum_sql);
            error_log("Event ID: " . $event_id);
            
            $sum_stmt = $conn->prepare($sum_sql);
            if ($sum_stmt === false) {
                error_log("Prepare failed: " . $conn->error);
                throw new Exception("Database error: " . $conn->error);
            }
            
            $sum_stmt->bind_param('i', $event_id);
            if (!$sum_stmt->execute()) {
                error_log("Execute failed: " . $sum_stmt->error);
                throw new Exception("Database error: " . $sum_stmt->error);
            }
            
            $sum_result = $sum_stmt->get_result();
            if ($sum_result === false) {
                error_log("Get result failed: " . $conn->error);
                throw new Exception("Database error: " . $conn->error);
            }
            
            $sum_row = $sum_result->fetch_assoc();
            error_log("Query result: " . print_r($sum_row, true));
            
            $attended = isset($sum_row['total_arrived']) ? (int)$sum_row['total_arrived'] : 0;
            error_log("Attended count: " . $attended);
            
            // Calculate percentages
            $attendance_rate = $total_guests > 0 ? round(($attended / $total_guests) * 100) : 0;
            $wa_delivery_rate = $wa_sent > 0 ? round(($wa_delivered / $wa_sent) * 100) : 0;
            $sms_delivery_rate = $sms_sent > 0 ? round(($sms_delivered / $sms_sent) * 100) : 0;
            
            // Reset result pointer for the table display
            $result->data_seek(0);
        } else {
            $error_message = "No event found with the provided pin.";
        }
        
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
} elseif (isset($_GET['export'])) {
    try {
        $conn = connectToDatabase();
        $event_pin = isset($_GET['event_pin']) ? trim($_GET['event_pin']) : '';
        
        if (!empty($event_pin)) {
            // First, get the event_id for the given event_pin
            $sql = "SELECT event_id FROM event_pins WHERE event_pin = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $event_pin);
            $stmt->execute();
            $pin_result = $stmt->get_result();
            
            if ($pin_result->num_rows > 0) {
                $event_data = $pin_result->fetch_assoc();
                $event_id = $event_data['event_id'];
                
                // Get only the fields that are visible in the report
                $sql = "SELECT 
                    g.name, 
                    g.phone_number,
                    g.recipient_msisdn,
                    g.type, 
                    g.card_number, 
                    g.card_url, 
                    g.arrive_count,
                    g.wa_message_status, 
                    g.sms_message_status, 
                    g.attendance_feedback
                FROM event_guests g 
                WHERE g.event_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $event_id);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $error_message = "No event found with the provided pin.";
            }
            
            // Get column names and filter out recipient_msisdn
            $columns = [];
            $header = [];
            $column_names = [
                'name' => 'Guest Name',
                'phone_number' => 'Phone Number',
                'type' => 'Card Type',
                'card_number' => 'Card Number',
                'card_url' => 'Digital Card',
                'arrive_count' => 'Attended Guests',
                'wa_message_status' => 'WhatsApp',
                'sms_message_status' => 'SMS',
                'attendance_feedback' => 'Attendance'
            ];
            
            // Get all fields from the result
            $fields = $result->fetch_fields();
            foreach ($fields as $field) {
                if ($field->name !== 'recipient_msisdn') {
                    $columns[] = $field;
                    $header[] = $column_names[$field->name] ?? ucwords(str_replace('_', ' ', $field->name));
                }
            }
            
            // Prepare data
            $data = array($header);
            while ($row = $result->fetch_assoc()) {
                $row_data = [];
                foreach ($columns as $column) {
                    // Use recipient_msisdn if available for phone_number
                    if ($column->name === 'phone_number' && !empty($row['recipient_msisdn'])) {
                        $row_data[] = $row['recipient_msisdn'];
                    } else {
                        $row_data[] = $row[$column->name];
                    }
                }
                $data[] = $row_data;
            }
            
            // Export as CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="event_guests_' . $event_id . '_report.csv"');
            $output = fopen('php://output', 'w');
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
        }
        
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Function to get status class for PHP
function getStatusClass($status) {
    if (empty($status)) return 'secondary';
    
    $status = strtolower(trim($status));
    if ($status === 'asante, nitafika') {
        return 'success';
    } else if ($status === 'sitoweza kufika') {
        return 'danger';
    } else if ($status === 'sina uhakika') {
        return 'warning';
    } else if ($status === 'call again to confirm') {
        return 'info';
    } else if ($status === 'attended') {
        return 'success';
    } else if ($status === 'not attended') {
        return 'secondary';
    } else if ($status === 'cancelled') {
        return 'danger';
    } else {
        return 'secondary';
    }
}

// DataTables initialization is now in the JavaScript section below
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nialike Portal - Detailed Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }
        .bg-attended { background-color: #198754; }
        .bg-not-attended { background-color: #dc3545; }
        .bg-cancelled { background-color: #6c757d; }
        .bg-sent { background-color: #0d6efd; }
        .bg-delivered { background-color: #20c997; }
        .bg-read { background-color: #198754; }
        .bg-failed { background-color: #dc3545; }
        .bg-pending { 
            background-color: #ffc107; 
            color: #000; 
            border: 1px solid #dee2e6;
        }
        .bg-not-sent { background-color: #6c757d; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
        .card-header { 
            background-color: #4e73df; 
            color: white;
            border-bottom: 1px solid rgba(0,0,0,.125); 
            padding: 1rem 1.25rem;
        }
        .card-header h5 { 
            color: white;
            margin: 0;
            font-weight: 600;
        }
        .table th { font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
        /* Add border to white buttons in WhatsApp and SMS sections */
        .bg-pending, .bg-sent, .bg-delivered, .bg-read, .bg-failed, .bg-not-sent {
            border: 1px solid #dee2e6;
        }
        
        .btn-back {
            background-color: #5a5c69;
            color: white;
            border: none;
        }
        .btn-back:hover {
            background-color: #4a4c55;
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <h5 class="mb-0">
                                <i class="bi bi-people-fill me-2"></i>Detailed Guest Report
                            </h5>
                            <?php if (!empty($event_name)): ?>
                                <span class="badge bg-light text-dark">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    <?php echo htmlspecialchars($event_name); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <a href="../index.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i> 
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <a href="../index.php" class="btn btn-back">
                                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                            </a>
                        </div>
                        <form method="GET" class="mb-4">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-8">
                                    <label for="event_pin" class="form-label">Event PIN</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                                        <input type="text" class="form-control" id="event_pin" name="event_pin" 
                                               value="<?php echo isset($_GET['event_pin']) ? htmlspecialchars($_GET['event_pin']) : ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search me-1"></i> Generate Report
                                    </button>
                                </div>
                            </div>
                        </form>

                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <div><?php echo htmlspecialchars($error_message); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($show_results && $result): ?>
                            <!-- Attendance Summary -->
                            <div id="attendanceSummary" class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">
                                        <i class="bi bi-graph-up me-2"></i>Attendance Summary
                                    </h5>
                                    <div class="row g-2">
                                        <div class="col">
                                            <div class="p-2 border rounded text-center h-100">
                                                <div class="h4 mb-1"><?php echo $total_guests; ?></div>
                                                <div class="text-muted small">Total Guests</div>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="p-2 border rounded text-center h-100">
                                                <div class="h4 mb-1"><?php echo $total_cards; ?></div>
                                                <div class="text-muted small">Total Cards</div>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="p-2 border rounded text-center h-100">
                                                <div class="h4 mb-1 text-success"><?php echo $attended; ?></div>
                                                <div class="text-muted small">Attended</div>
                                                <div class="progress mt-1" style="height: 3px;">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                         style="width: <?php echo $attendance_rate; ?>%" 
                                                         aria-valuenow="<?php echo $attendance_rate; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <small class="text-muted"><?php echo $attendance_rate; ?>%</small>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="p-2 border rounded text-center h-100">
                                                <div class="h4 mb-1 text-primary"><?php echo $wa_sent; ?></div>
                                                <div class="text-muted small">WhatsApp</div>
                                                <div class="progress mt-1" style="height: 3px;">
                                                    <div class="progress-bar bg-primary" role="progressbar" 
                                                         style="width: <?php echo $wa_delivery_rate; ?>%" 
                                                         aria-valuenow="<?php echo $wa_delivery_rate; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <small class="text-muted"><?php echo $wa_delivered; ?> (<?php echo $wa_delivery_rate; ?>%)</small>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="p-2 border rounded text-center h-100">
                                                <div class="h4 mb-1 text-info"><?php echo $sms_sent; ?></div>
                                                <div class="text-muted small">SMS</div>
                                                <div class="progress mt-1" style="height: 3px;">
                                                    <div class="progress-bar bg-info" role="progressbar" 
                                                         style="width: <?php echo $sms_delivery_rate; ?>%" 
                                                         aria-valuenow="<?php echo $sms_delivery_rate; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <small class="text-muted"><?php echo $sms_delivered; ?> (<?php echo $sms_delivery_rate; ?>%)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Attendance Status Breakdown -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">
                                        <i class="bi bi-clipboard-data me-2"></i>Attendance Status Breakdown
                                    </h5>
                                    <?php 
                                    // Get attendance status summary from database
                                    $attendance_sql = "SELECT 
                                        COALESCE(
                                            NULLIF(call_attendance_feedback, ''), 
                                            NULLIF(attendance_feedback, ''), 
                                            'not_specified'
                                        ) as status,
                                        COUNT(*) as count
                                    FROM event_guests 
                                    WHERE event_id = ?
                                    GROUP BY COALESCE(
                                        NULLIF(call_attendance_feedback, ''), 
                                        NULLIF(attendance_feedback, ''), 
                                        'not_specified'
                                    )";
                                    
                                    $stmt = $conn->prepare($attendance_sql);
                                    $stmt->bind_param('i', $event_id);
                                    $stmt->execute();
                                    $attendance_result = $stmt->get_result();
                                    $attendance_data = [];
                                    
                                    while ($row = $attendance_result->fetch_assoc()) {
                                        $status = $row['status'] === 'not_specified' ? 'Not Specified' : $row['status'];
                                        $attendance_data[] = [
                                            'status' => $status,
                                            'count' => (int)$row['count'],
                                            'percentage' => $total_guests > 0 ? round(($row['count'] / $total_guests) * 100, 1) : 0
                                        ];
                                    }
                                    
                                    // Sort by count in descending order
                                    usort($attendance_data, function($a, $b) {
                                        return $b['count'] - $a['count'];
                                    });
                                    
                                    if (!empty($attendance_data)): 
                                    ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Status</th>
                                                        <th>Count</th>
                                                        <th>Percentage</th>
                                                        <th>Progress</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($attendance_data as $item): 
                                                        $status_class = '';
                                                        $status_lower = strtolower($item['status']);
                                                        if (in_array($status_lower, ['attended', 'accepted', 'asante, nitafika'])) {
                                                            $status_class = 'bg-success';
                                                        } elseif (in_array($status_lower, ['not attended', 'sitoweza kufika'])) {
                                                            $status_class = 'bg-danger';
                                                        } else {
                                                            $status_class = 'bg-info';
                                                        }
                                                    ?>
                                                        <tr>
                                                            <td>
                                                                <span class="status-badge <?php echo $status_class; ?> text-white">
                                                                    <?php echo htmlspecialchars($item['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo $item['count']; ?></td>
                                                            <td><?php echo $item['percentage']; ?>%</td>
                                                            <td>
                                                                <div class="progress">
                                                                    <div class="progress-bar <?php echo $status_class; ?>" 
                                                                         role="progressbar" 
                                                                         style="width: <?php echo $item['percentage']; ?>%" 
                                                                         aria-valuenow="<?php echo $item['percentage']; ?>" 
                                                                         aria-valuemin="0" 
                                                                         aria-valuemax="100">
                                                                        <?php echo $item['percentage']; ?>%
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning mb-0">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            No attendance data available.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">
                                    <i class="bi bi-list-ul me-2"></i>Guest List
                                    <span class="badge bg-primary rounded-pill ms-2"><?php echo $result->num_rows; ?> Total</span>
                                </h6>
                                <a href="?event_pin=<?php echo urlencode($event_pin); ?>&export=csv" class="btn btn-sm btn-success">
                                    <i class="bi bi-download me-1"></i> Export CSV
                                </a>
                            </div>

                            <div class="table-responsive">
                                <table id="guestsTable" class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="small text-muted">ID</th>
                                            <?php
                                            // Define user-friendly column names
                                            $column_names = [
                                                'name' => 'Guest Name',
                                                'phone_number' => 'Phone Number',
                                                'type' => 'Card Type',
                                                'card_number' => 'Card Number',
                                                'card_url' => 'Digital Card',
                                                'arrive_count' => 'Attended Guests',
                                                'wa_message_status' => 'WhatsApp',
                                                'sms_message_status' => 'SMS',
                                                'feedback' => 'ATTENDANCE FEEDBACK'
                                            ];
                                            
                                            // Get column names from the result and filter out recipient_msisdn and id
                                            $columns = [];
                                            $fields = $result->fetch_fields();
                                            foreach ($fields as $field): 
                                                if ($field->name !== 'recipient_msisdn' && $field->name !== 'id'): 
                                                    $columns[] = $field;
                                                    $display_name = $column_names[$field->name] ?? ucwords(str_replace('_', ' ', $field->name));
                                                    ?>
                                                    <th class="small text-muted"><?php echo htmlspecialchars($display_name); ?></th>
                                                <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                            <th class="small text-muted">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $counter = 1; // Initialize counter for ID column
                                        $result->data_seek(0); // Reset result pointer to beginning
                                        while ($row = $result->fetch_assoc()) {
                                            $feedback = $row['feedback'] ?? '';
                                            $statusClass = getStatusClass($feedback);
                                            
                                            echo '<tr data-guest-id="' . htmlspecialchars($row['id']) . '">';
                                            // Add the sequential number as the first cell in the row
                                            echo '<td>' . $counter++ . '</td>';
                                            foreach ($columns as $column) {
                                                echo '<td>';
                                                $value = $row[$column->name] ?? '';
                                                
                                                if ($column->name === 'phone_number' && !empty($row['recipient_msisdn'])) {
                                                    echo htmlspecialchars($row['recipient_msisdn']);
                                                } elseif ($column->name === 'card_url' && !empty($value)) { 
                                                    echo '<a href="' . htmlspecialchars($value) . '" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-card-image me-1"></i> View Card
                                                    </a>';
                                                } else {
                                                    $status_class = '';
                                                    $status_value = strtolower(trim($value));
                                                    
                                                    // Format specific fields for better readability
                                                    switch($column->name) {
                                                        case 'wa_message_status':
                                                        case 'sms_message_status':
                                                            $status_map = [
                                                                'sent' => 'Sent',
                                                                'delivered' => 'Delivered',
                                                                'read' => 'Read',
                                                                'failed' => 'Failed',
                                                                'pending' => 'Pending',
                                                                'not_sent' => 'Not Sent'
                                                            ];
                                                            $status_text = $status_map[$status_value] ?? ucfirst($status_value);
                                                            $status_class = strtolower($status_value);
                                                            echo '<span class="badge bg-' . ($status_class === 'delivered' || $status_class === 'read' ? 'success' : 
                                                                  ($status_class === 'failed' ? 'danger' : 
                                                                  ($status_class === 'pending' ? 'warning' : 'secondary'))) . '">' . 
                                                                  htmlspecialchars($status_text) . '</span>';
                                                            break;
                                                        case 'arrive_count':
                                                            echo $value > 0 ? '<span class="badge bg-success">Yes (' . $value . ')</span>' : '<span class="badge bg-secondary">No</span>';
                                                            break;
                                                        case 'feedback':
                                                            echo $feedback ? htmlspecialchars($feedback) : '-';
                                                            break;
                                                        default:
                                                            echo htmlspecialchars($value);
                                                    }
                                                }
                                                echo '</td>';
                                            }
                                            
                                            // Action button
                                            echo '<td class="text-nowrap"><button class="btn btn-link p-0 border-0 bg-transparent update-status-btn" data-bs-toggle="modal" data-bs-target="#statusModal" title="' . ($feedback ? 'Update status' : 'Set status') . '">' . 
                                                 '<i class="bi bi-pencil-square ' . $statusClass . '"></i></button></td>';
                                            echo '</tr>';
                                        }
                                    ?>
                                </table>

                                <!-- Status Update Modal -->
                                <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="statusModalLabel">Update Call Status</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" id="guestId">
                                                <div class="mb-3">
                                                    <label for="callStatus" class="form-label">Select Status</label>
                                                    <select class="form-select" id="callStatus" required>
                                                        <option value="" selected disabled>-- Select Status --</option>
                                                        <option value="Asante, nitafika">Asante, nitafika</option>
                                                        <option value="Sitoweza kufika">Sitoweza kufika</option>
                                                        <option value="Sina uhakika">Sina uhakika</option>
                                                        <option value="Call to Confirm">Call to Confirm</option>
                                                    </select>
                                                    <div class="invalid-feedback">
                                                        Please select a status
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="button" class="btn btn-primary" id="saveStatusBtn">Save changes</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script>
    // Define getStatusClass globally so it can be used in PHP
    function getStatusClass(status) {
        if (!status) return 'secondary';
        
        status = status.toLowerCase().trim();
        if (status === 'asante, nitafika') {
            return 'success';
        } else if (status === 'sitoweza kufika') {
            return 'danger';
        } else if (status === 'sina uhakika') {
            return 'warning';
        } else if (status === 'call again to confirm') {
            return 'info';
        } else if (status === 'attended') {
            return 'success';
        } else if (status === 'not attended') {
            return 'secondary';
        } else if (status === 'cancelled') {
            return 'danger';
        } else {
            return 'secondary';
        }
    }

    // Function to refresh the attendance summary
    function refreshAttendanceSummary() {
        $.ajax({
            url: 'get_attendance_summary.php',
            type: 'GET',
            data: { event_pin: '<?php echo $event_pin; ?>' },
            success: function(summaryHtml) {
                $('#attendanceSummary').replaceWith(summaryHtml);
            },
            error: function() {
                console.error('Failed to refresh attendance summary');
            }
        });
    }

    // Restore scroll position if it was saved
    $(document).ready(function() {
        var savedScrollPosition = sessionStorage.getItem('scrollPosition');
        var savedEventPin = sessionStorage.getItem('eventPin');
        var currentEventPin = '<?php echo $event_pin; ?>';
        
        // Only restore position if we're on the same event
        if (savedScrollPosition !== null && savedEventPin === currentEventPin) {
            // Use setTimeout to ensure the DOM is fully loaded
            setTimeout(function() {
                window.scrollTo(0, savedScrollPosition);
                // Clear the saved position
                sessionStorage.removeItem('scrollPosition');
            }, 1);
        }
        // Initialize DataTable only if not already initialized
        if (!$.fn.DataTable.isDataTable('#guestsTable')) {
            var table = $('#guestsTable').DataTable({
                responsive: true,
                columnDefs: [
                    { orderable: false, targets: -1 } // Make action column not sortable
                ],
                order: [[0, 'asc']], // Sort by first column by default
                pageLength: 50,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']]
            });
        }

        // Initialize the modal once
        var statusModal = new bootstrap.Modal(document.getElementById('statusModal'));

        // Show status modal
        $(document).on('click', '.update-status-btn', function() {
            var $row = $(this).closest('tr');
            var guestId = $row.data('guest-id');
            var currentStatus = $row.find('td:has(.bi-pencil-square)').siblings('span').text().trim();
            
            $('#guestId').val(guestId);
            var $statusSelect = $('#callStatus');
            
            // Reset the select to show the default option
            $statusSelect.prop('selectedIndex', 0);
            
            // If there's a current status, select it
            if (currentStatus) {
                $statusSelect.val(currentStatus);
                // If the value wasn't found in the options, reset to default
                if ($statusSelect.val() === null) {
                    $statusSelect.prop('selectedIndex', 0);
                }
            }
            
            // Reset validation state
            $statusSelect.removeClass('is-invalid');
            
            statusModal.show();
        });

        // Save status
        $('#saveStatusBtn').click(function() {
            var guestId = $('#guestId').val();
            var status = $('#callStatus').val();
            
            if (!status) {
                alert('Please select a status');
                return;
            }
            
            var $saveBtn = $(this);
            var originalText = $saveBtn.html();
            $saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
            
            $.ajax({
                url: 'update_call_status.php',
                type: 'POST',
                data: {
                    guest_id: guestId,
                    status: status
                },
                dataType: 'json',
                success: function(response) {
                    $saveBtn.prop('disabled', false).html(originalText);
                    
                    if (response.success) {
                        // Get current scroll position
                        var scrollPosition = window.scrollY || document.documentElement.scrollTop;
                        
                        // Store the scroll position in sessionStorage
                        sessionStorage.setItem('scrollPosition', scrollPosition);
                        
                        // Store the event_pin in sessionStorage
                        var eventPin = '<?php echo $event_pin; ?>';
                        sessionStorage.setItem('eventPin', eventPin);
                        
                        // Redirect to the same page with the event_pin
                        window.location.href = 'detailed_report.php?event_pin=' + encodeURIComponent(eventPin);
                    } else {
                        alert('Error updating status: ' + (response.message || 'Unknown error'));
                    }
                },
                error: function() {
                    $saveBtn.prop('disabled', false).html(originalText);
                    alert('Error updating status. Please try again.');
                }
            });
        });
    });
    </script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
</div>

<!-- Toast Notification -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="statusToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto">Success</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastMessage">
            Status updated successfully
        </div>
    </div>
</div>
</body>
</html>
