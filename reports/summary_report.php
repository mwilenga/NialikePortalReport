<?php
require_once dirname(__DIR__) . '/db.php';

// Initialize variables
$event_pin = isset($_POST['event_pin']) ? trim($_POST['event_pin']) : '';
$show_results = isset($_POST['submit']);
$summary_data = null;
$error_message = null;
$event_id = 0;

if ($show_results && !empty($event_pin)) {
    try {
        $conn = connectToDatabase();
        
        // First, get the event_id and event name for the given event_pin
        $sql = "SELECT ep.event_id as event_id, e.name 
                FROM event_pins ep 
                JOIN events e ON e.id = ep.event_id 
                WHERE ep.event_pin = ? 
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $event_pin);
        $stmt->execute();
        $pin_result = $stmt->get_result();
        
        if ($pin_result->num_rows > 0) {
            $event_data = $pin_result->fetch_assoc();
            $event_id = (int)$event_data['event_id'];
            $event_name = $event_data['name'] ?? 'Event';
            
            // Get summary data grouped by attendance, whatsapp, and sms statuses
            $sql = "SELECT 
                        COUNT(*) as total_guests,
                        COALESCE(attendance_feedback, 'not_specified') as attendance_status,
                        COALESCE(wa_message_status, 'not_sent') as whatsapp_status,
                        COALESCE(sms_message_status, 'not_sent') as sms_status
                    FROM event_guests 
                    WHERE event_id = ? AND is_deleted = 0
                    GROUP BY attendance_feedback, wa_message_status, sms_message_status
                    ORDER BY attendance_feedback, wa_message_status, sms_status";
                    
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $event_id);
            $stmt->execute();
            $summary_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Calculate totals
            $total_guests = array_sum(array_column($summary_data, 'total_guests'));
            
        } else {
            $error_message = "No event found with the provided pin.";
        }
        
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Define display mappings
$status_mappings = [
    'wa_message_status' => [
        'sent' => 'Sent',
        'delivered' => 'Delivered',
        'read' => 'Read',
        'failed' => 'Failed',
        'pending' => 'Pending',
        'not_sent' => 'Not Sent'
    ],
    'sms_message_status' => [
        'sent' => 'Sent',
        'delivered' => 'Delivered',
        'failed' => 'Failed',
        'pending' => 'Pending',
        'not_sent' => 'Not Sent'
    ],
    'attendance_feedback' => [
        'attended' => 'Attended',
        'not_attended' => 'Not Attended',
        'cancelled' => 'Cancelled',
        'not_specified' => 'Not Specified'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nialike Portal - Summary Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Nialike Portal - Summary Report</h1>
        
        <div class="card mb-4">
            <div class="card-body">
                <form method="POST" class="mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="event_pin" class="form-label">Event Pin</label>
                            <input type="text" class="form-control" id="event_pin" name="event_pin" required 
                                   value="<?php echo htmlspecialchars($event_pin); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" name="submit" class="btn btn-primary mt-4">Generate Report</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($show_results && $summary_data): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3>Event Summary: <?php echo htmlspecialchars($event_name); ?></h3>
                    <p class="mb-0">Total Guests: <?php echo $total_guests; ?></p>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="summaryTable" class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Attendance Status</th>
                                    <th>WhatsApp Status</th>
                                    <th>SMS Status</th>
                                    <th>Number of Guests</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($summary_data as $row): 
                                    $attendance = $status_mappings['attendance_feedback'][$row['attendance_status']] ?? ucfirst($row['attendance_status']);
                                    $whatsapp = $status_mappings['wa_message_status'][$row['whatsapp_status']] ?? ucfirst($row['whatsapp_status']);
                                    $sms = $status_mappings['sms_message_status'][$row['sms_status']] ?? ucfirst($row['sms_status']);
                                    $percentage = $total_guests > 0 ? round(($row['total_guests'] / $total_guests) * 100, 2) : 0;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($attendance); ?></td>
                                        <td><?php echo htmlspecialchars($whatsapp); ?></td>
                                        <td><?php echo htmlspecialchars($sms); ?></td>
                                        <td><?php echo $row['total_guests']; ?></td>
                                        <td><?php echo $percentage; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            if ($('#summaryTable').length) {
                $('#summaryTable').DataTable({
                    "pageLength": 50,
                    "responsive": true,
                    "dom": '<"top"lfrtip>',
                    "order": [[0, 'asc'], [1, 'asc'], [2, 'asc']],
                    "language": {
                        "paginate": {
                            "previous": '<i class="fas fa-arrow-left"></i>',
                            "next": '<i class="fas fa-arrow-right"></i>',
                            "first": '<i class="fas fa-fast-backward"></i>',
                            "last": '<i class="fas fa-fast-forward"></i>'
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
