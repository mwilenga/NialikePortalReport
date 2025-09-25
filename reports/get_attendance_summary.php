<?php
require_once '../config/config.php';

// Get the event PIN from the request
$event_pin = isset($_GET['event_pin']) ? trim($_GET['event_pin']) : '';

if (empty($event_pin)) {
    http_response_code(400);
    die('Event PIN is required');
}

try {
    // Get database connection
    $pdo = getDbConnection();
    
    // Get event details
    $stmt = $pdo->prepare("SELECT * FROM event_pins WHERE pin = ?");
    $stmt->execute([$event_pin]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        throw new Exception("Event not found");
    }
    
    // Get attendance summary
    $event_id = $event['event_id'];
    
    // Total guests (exclude deleted; treat NULL as not deleted)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM event_guests WHERE event_id = ? AND (is_deleted <> 1 OR is_deleted IS NULL)");
    $stmt->execute([$event_id]);
    $total_guests = $stmt->fetchColumn();
    
    // Attended guests
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(arrive_count), 0) as attended FROM event_guests WHERE event_id = ? AND arrive_count > 0 AND is_deleted = 0");
    $stmt->execute([$event_id]);
    $attended = (int)$stmt->fetchColumn();
    
    // WhatsApp sent
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM event_guests WHERE event_id = ? AND whatsapp_sent = 1 AND is_deleted = 0");
    $stmt->execute([$event_id]);
    $wa_sent = (int)$stmt->fetchColumn();
    
    // WhatsApp delivered
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM event_guests WHERE event_id = ? AND whatsapp_delivered = 1 AND is_deleted = 0");
    $stmt->execute([$event_id]);
    $wa_delivered = (int)$stmt->fetchColumn();
    
    // SMS sent
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM event_guests WHERE event_id = ? AND sms_sent = 1 AND is_deleted = 0");
    $stmt->execute([$event_id]);
    $sms_sent = (int)$stmt->fetchColumn();
    
    // SMS delivered
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM event_guests WHERE event_id = ? AND sms_delivered = 1 AND is_deleted = 0");
    $stmt->execute([$event_id]);
    $sms_delivered = (int)$stmt->fetchColumn();
    
    // Calculate percentages
    $wa_delivery_rate = $wa_sent > 0 ? round(($wa_delivered / $wa_sent) * 100, 1) : 0;
    $sms_delivery_rate = $sms_sent > 0 ? round(($sms_delivered / $sms_sent) * 100, 1) : 0;
    $attendance_rate = $total_guests > 0 ? round(($attended / $total_guests) * 100, 1) : 0;
    
    // Generate the attendance summary HTML
    ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4">Attendance Summary</h5>
                    <div class="row">
                        <!-- Total Guests -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="card-subtitle mb-0 text-muted">Total Guests</h6>
                                        <i class="bi bi-people fs-4 text-primary"></i>
                                    </div>
                                    <h3 class="mb-0"><?php echo number_format($total_guests); ?></h3>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Attended -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="card-subtitle mb-0 text-muted">Attended</h6>
                                        <i class="bi bi-check-circle-fill fs-4 text-success"></i>
                                    </div>
                                    <h3 class="mb-0"><?php echo number_format($attended); ?></h3>
                                    <small class="text-muted"><?php echo $attendance_rate; ?>% of total</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- WhatsApp -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="card-subtitle mb-0 text-muted">WhatsApp</h6>
                                        <i class="bi bi-whatsapp fs-4 text-success"></i>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted">Sent:</small>
                                            <span class="fw-bold"><?php echo number_format($wa_sent); ?></span>
                                        </div>
                                        <div>
                                            <small class="text-muted">Delivered:</small>
                                            <span class="fw-bold"><?php echo number_format($wa_delivered); ?></span>
                                        </div>
                                    </div>
                                    <div class="progress mt-2" style="height: 5px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $wa_delivery_rate; ?>%;" 
                                             aria-valuenow="<?php echo $wa_delivery_rate; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted"><?php echo $wa_delivery_rate; ?>% delivery rate</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- SMS -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="card-subtitle mb-0 text-muted">SMS</h6>
                                        <i class="bi bi-chat-dots fs-4 text-primary"></i>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted">Sent:</small>
                                            <span class="fw-bold"><?php echo number_format($sms_sent); ?></span>
                                        </div>
                                        <div>
                                            <small class="text-muted">Delivered:</small>
                                            <span class="fw-bold"><?php echo number_format($sms_delivered); ?></span>
                                        </div>
                                    </div>
                                    <div class="progress mt-2" style="height: 5px;">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $sms_delivery_rate; ?>%;" 
                                             aria-valuenow="<?php echo $sms_delivery_rate; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted"><?php echo $sms_delivery_rate; ?>% delivery rate</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    
} catch (Exception $e) {
    http_response_code(500);
    echo '<div class="alert alert-danger">Error loading attendance summary: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
