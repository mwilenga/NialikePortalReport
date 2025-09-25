<?php
require_once dirname(__DIR__) . '/db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize variables
$event_pin = isset($_GET['event_pin']) ? trim($_GET['event_pin']) : '';
$show_results = !empty($event_pin);
$error_message = '';
$event_name = '';
$total_guests = 0;
$attendance_data = [];
$whatsapp_data = [];

if ($show_results) {
    try {
        $conn = connectToDatabase();
        
        // 1. Get event details
        $sql = "SELECT ep.event_id, e.name 
                FROM event_pins ep 
                JOIN events e ON e.id = ep.event_id 
                WHERE ep.event_pin = ? 
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $event_pin);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $event = $result->fetch_assoc();
            $event_id = $event['event_id'];
            $event_name = $event['name'] ?? 'Event';
            
            // 2. Get total guests (sum of card_count) and total cards (count of non-null card_numbers)
            $count_sql = "SELECT 
                            COALESCE(SUM(card_count), 0) as total_guests,
                            SUM(CASE WHEN card_number IS NOT NULL AND card_number != '' THEN 1 ELSE 0 END) as total_cards
                          FROM event_guests 
                          WHERE event_id = ? AND (is_deleted <> 1 OR is_deleted IS NULL)";
            $count_stmt = $conn->prepare($count_sql);
            $count_stmt->bind_param('i', $event_id);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result()->fetch_assoc();
            $total_guests = (int)($count_result['total_guests'] ?? 0);
            $total_cards = (int)($count_result['total_cards'] ?? 0);
            
            if ($total_guests > 0) {
                // 3. Get attended count (sum of arrive_count)
                $arrived_sql = "SELECT COALESCE(SUM(arrive_count), 0) as total_arrived FROM event_guests WHERE event_id = ? AND is_deleted = 0";
                $stmt = $conn->prepare($arrived_sql);
                $stmt->bind_param('i', $event_id);
                $stmt->execute();
                $arrived_result = $stmt->get_result();
                $arrived_row = $arrived_result->fetch_assoc();
                $attended = (int)$arrived_row['total_arrived'];
                
                // 4. Get attendance summary
                $attendance_sql = "SELECT 
                                    COALESCE(
                                        NULLIF(call_attendance_feedback, ''), 
                                        NULLIF(attendance_feedback, ''), 
                                        'not_specified'
                                    ) as status,
                                    COUNT(*) as count
                                  FROM event_guests 
                                  WHERE event_id = ? AND is_deleted = 0
                                  GROUP BY COALESCE(
                                      NULLIF(call_attendance_feedback, ''), 
                                      NULLIF(attendance_feedback, ''), 
                                      'not_specified'
                                  )";
                
                $stmt = $conn->prepare($attendance_sql);
                $stmt->bind_param('i', $event_id);
                $stmt->execute();
                $attendance_result = $stmt->get_result();
                
                // Initialize counters
                $wa_sent = 0;
                $wa_delivered = 0;
                $sms_sent = 0;
                $sms_delivered = 0;
                
                while ($row = $attendance_result->fetch_assoc()) {
                    $status = $row['status'] === 'not_specified' ? 'Not Specified' : $row['status'];
                    $attendance_data[] = [
                        'status' => $status,
                        'count' => (int)$row['count'],
                        'percentage' => round(($row['count'] / $total_guests) * 100, 1)
                    ];
                }
                
                // Sort by count in descending order
                usort($attendance_data, function($a, $b) {
                    return $b['count'] - $a['count'];
                });
                
                // Calculate attendance rate
                $attendance_rate = $total_guests > 0 ? round(($attended / $total_guests) * 100) : 0;
                
                // 5. Get WhatsApp status summary
                $whatsapp_sql = "SELECT 
                                COALESCE(wa_message_status, 'not_sent') as status,
                                COUNT(*) as count
                              FROM event_guests 
                              WHERE event_id = ? AND is_deleted = 0
                              GROUP BY wa_message_status";
                
                error_log("WhatsApp SQL: " . $whatsapp_sql);
                error_log("Event ID: " . $event_id);
                
                $stmt = $conn->prepare($whatsapp_sql);
                if ($stmt === false) {
                    error_log("Prepare failed: " . $conn->error);
                    throw new Exception("Database error: " . $conn->error);
                }
                
                $stmt->bind_param('i', $event_id);
                if (!$stmt->execute()) {
                    error_log("Execute failed: " . $stmt->error);
                    throw new Exception("Query failed: " . $stmt->error);
                }
                
                $whatsapp_result = $stmt->get_result();
                error_log("WhatsApp result rows: " . $whatsapp_result->num_rows);
                
                while ($row = $whatsapp_result->fetch_assoc()) {
                    error_log("WhatsApp status row: " . print_r($row, true));
                    $whatsapp_data[] = [
                        'status' => $row['status'], // Keep original case for mapping
                        'count' => (int)$row['count'],
                        'percentage' => round(($row['count'] / $total_guests) * 100, 2)
                    ];
                    
                    // Count WhatsApp sent/delivered
                    if (!empty($row['status']) && $row['status'] !== 'not_sent') {
                        $wa_sent += $row['count'];
                        if (strtolower($row['status']) === 'delivered' || strtolower($row['status']) === 'read') {
                            $wa_delivered += $row['count'];
                        }
                    }
                }
                error_log("WhatsApp data: " . print_r($whatsapp_data, true));
                
                // Calculate WhatsApp delivery rate
                $wa_delivery_rate = $wa_sent > 0 ? round(($wa_delivered / $wa_sent) * 100) : 0;
                
                // 6. Get SMS status summary
                $sms_sql = "SELECT 
                            COALESCE(sms_message_status, 'not_sent') as status,
                            COUNT(*) as count
                          FROM event_guests 
                          WHERE event_id = ? AND is_deleted = 0
                          GROUP BY sms_message_status";
                
                $stmt = $conn->prepare($sms_sql);
                $stmt->bind_param('i', $event_id);
                $stmt->execute();
                $sms_result = $stmt->get_result();
                
                while ($row = $sms_result->fetch_assoc()) {
                    // Count SMS sent/delivered
                    if (!empty($row['status']) && $row['status'] !== 'not_sent') {
                        $sms_sent += $row['count'];
                        if (strtolower($row['status']) === 'delivered') {
                            $sms_delivered += $row['count'];
                        }
                    }
                }
                
                // Calculate SMS delivery rate
                $sms_delivery_rate = $sms_sent > 0 ? round(($sms_delivered / $sms_sent) * 100) : 0;
            }
        } else {
            throw new Exception("No event found with pin: " . htmlspecialchars($event_pin));
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("Error in attendance_summary.php: " . $error_message);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nialike Portal - Attendance Summary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .card { 
            border: none; 
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); 
        }
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
        .btn-back {
            background-color: #5a5c69;
            color: white;
            border: none;
        }
        .btn-back:hover {
            background-color: #4a4c55;
            color: white;
        }
        .progress {
            height: 25px;
            border-radius: 4px;
        }
        .progress-bar {
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .status-badge {
            font-size: 0.8em;
            padding: 3px 8px;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-people-fill me-2"></i>Attendance Summary Report
                        </h5>
                        <?php if (!empty($event_name)): ?>
                            <span class="badge bg-light text-dark">
                                <i class="bi bi-calendar-event me-1"></i>
                                <?php echo htmlspecialchars($event_name); ?>
                            </span>
                        <?php endif; ?>
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

                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <div><?php echo htmlspecialchars($error_message); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($show_results && $total_guests > 0): ?>
                            <!-- Attendance Summary -->
                            <div class="card mb-4">
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

                            <?php if ($total_guests > 0 && !empty($attendance_data)): ?>
                                <!-- Charts Row -->
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <div class="card h-100">
                                            <div class="card-body p-2">
                                                <div style="height: 250px; width: 100%;">
                                                    <canvas id="attendancePieChart"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-body p-2">
                                                <div style="height: 250px; width: 100%;">
                                                    <canvas id="whatsappBarChart"></canvas>
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
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    No attendance data available for this event.
                                </div>
                                
                                <!-- WhatsApp Status Summary -->
                                <div class="mt-5">
                                    <h5 class="mb-3">
                                        <i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp Message Status
                                    </h5>
                                    <?php if (!empty($whatsapp_data)): ?>
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
                                                    <?php foreach ($whatsapp_data as $item): 
                                                        $status_class = '';
                                                        $status_lower = strtolower($item['status']);
                                                        if (in_array($status_lower, ['delivered', 'read', 'accepted'])) {
                                                            $status_class = 'bg-success';
                                                        } elseif ($status_lower === 'failed') {
                                                            $status_class = 'bg-danger';
                                                        } elseif ($status_lower === 'sent') {
                                                            $status_class = 'bg-primary';
                                                        } else {
                                                            $status_class = 'bg-info';
                                                        }
                                                    ?>
                                                        <tr>
                                                            <td>
                                                                <span class="status-badge <?php echo $status_class; ?> text-white">
                                                                    <?php 
                                                                    $status_map = [
                                                        'sent' => 'Sent',
                                                        'delivered' => 'Delivered',
                                                        'read' => 'Read',
                                                        'failed' => 'Failed',
                                                        'pending' => 'Pending',
                                                        'not_sent' => 'Not Sent',
                                                        'not_available' => 'Not Available'
                                                    ];
                                                    $status_value = strtolower(trim($item['status']));
                                                    $display_value = $status_map[$status_value] ?? ucfirst($status_value);
                                                    echo htmlspecialchars($display_value);
                                                                    ?>
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
                                        <div class="alert alert-warning">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            No WhatsApp status data available.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Attendance Status Pie Chart
            const attendanceCtx = document.getElementById('attendancePieChart');
            if (attendanceCtx) {
                new Chart(attendanceCtx, {
                    type: 'pie',
                    data: {
                        labels: [
                            <?php 
                            foreach ($attendance_data as $item) {
                                echo "'" . htmlspecialchars($item['status']) . "',";
                            }
                            ?>
                        ],
                        datasets: [{
                            data: [
                                <?php 
                                foreach ($attendance_data as $item) {
                                    echo $item['count'] . ",";
                                }
                                ?>
                            ],
                            backgroundColor: [
                                <?php 
                                foreach ($attendance_data as $item) {
                                    $status_lower = strtolower($item['status']);
                                    if (in_array($status_lower, ['attended', 'accepted', 'asante, nitafika'])) {
                                        echo "'#28a745',"; // Green for attended
                                    } elseif (in_array($status_lower, ['not attended', 'sitoweza kufika'])) {
                                        echo "'#dc3545',"; // Red for not attended
                                    } else {
                                        echo "'#17a2b8',"; // Blue for other statuses
                                    }
                                }
                                ?>
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    padding: 10,
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            title: {
                                display: true,
                                text: 'Attendance Status',
                                font: {
                                    size: 13
                                },
                                padding: {
                                    bottom: 10
                                }
                            }
                        }
                    }
                });
            }

            // WhatsApp Status Bar Chart
            const whatsappCtx = document.getElementById('whatsappBarChart');
            if (whatsappCtx) {
                new Chart(whatsappCtx, {
                    type: 'bar',
                    data: {
                        labels: [
                            <?php 
                            if (!empty($whatsapp_data)) {
                                foreach ($whatsapp_data as $item) {
                                    $status_map = [
                                        'sent' => 'Sent',
                                        'delivered' => 'Delivered',
                                        'read' => 'Read',
                                        'failed' => 'Failed',
                                        'pending' => 'Pending'
                                    ];
                                    $status = strtolower(trim($item['status']));
                                    $label = $status_map[$status] ?? ucfirst($status);
                                    echo "'" . $label . "',";
                                }
                            }
                            ?>
                        ],
                        datasets: [{
                            label: 'WhatsApp Messages',
                            data: [
                                <?php 
                                if (!empty($whatsapp_data)) {
                                    foreach ($whatsapp_data as $item) {
                                        echo $item['count'] . ",";
                                    }
                                }
                                ?>
                            ],
                            backgroundColor: [
                                <?php 
                                if (!empty($whatsapp_data)) {
                                    foreach ($whatsapp_data as $item) {
                                        $status_lower = strtolower($item['status']);
                                        if (in_array($status_lower, ['delivered', 'read', 'accepted'])) {
                                            echo "'#28a745',"; // Green for successful
                                        } elseif ($status_lower === 'failed') {
                                            echo "'#dc3545',"; // Red for failed
                                        } else if ($status_lower === 'sent') {
                                            echo "'#007bff',"; // Blue for sent
                                        } else {
                                            echo "'#6c757d',"; // Gray for other statuses
                                        }
                                    }
                                }
                                ?>
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    font: {
                                        size: 10
                                    }
                                },
                                grid: {
                                    display: true
                                }
                            },
                            x: {
                                ticks: {
                                    font: {
                                        size: 10
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'WhatsApp Status',
                                font: {
                                    size: 13
                                },
                                padding: {
                                    bottom: 10
                                }
                            }
                        },
                        barPercentage: 0.6,
                        categoryPercentage: 0.8
                    }
                });
            }
        });
    </script>
</body>
</html>
