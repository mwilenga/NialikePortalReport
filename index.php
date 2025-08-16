<?php
require_once 'db.php';

// Test database connection
try {
    $conn = connectToDatabase();
    $dbStatus = [
        'success' => true,
        'message' => 'Database connection successful!',
        'icon' => 'check-circle-fill',
        'class' => 'success'
    ];
    $conn->close();
} catch (Exception $e) {
    $dbStatus = [
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage(),
        'icon' => 'exclamation-triangle-fill',
        'class' => 'danger'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nialike Portal - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --accent-color: #2e59d9;
        }
        body {
            background-color: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background-color: #fff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 600;
            color: #4e73df;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-primary:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        .status-card {
            border-left: 0.25rem solid var(--primary-color);
        }
        .feature-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="#">
                <i class="bi bi-people-fill me-2"></i>Nialike Portal
            </a>
            <div class="d-flex align-items-center">
                <div class="badge bg-<?php echo $dbStatus['class']; ?>-subtle text-<?php echo $dbStatus['class']; ?> p-2">
                    <i class="bi bi-<?php echo $dbStatus['icon']; ?> me-1"></i>
                    <?php echo $dbStatus['success'] ? 'Connected' : 'Disconnected'; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Welcome Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-white border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div>
                            <h4 class="mb-1">Welcome to Nialike Portal</h4>
                            <p class="text-muted mb-0">Manage your event attendance and guest information</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Section -->
        <h5 class="mb-3 fw-bold text-muted">Reports</h5>
        <div class="row g-4 mb-4">
            <!-- Attendance Summary Card -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-clipboard-data"></i>
                        </div>
                        <h5 class="card-title">Attendance Summary</h5>
                        <p class="card-text text-muted">View and analyze event attendance statistics and summaries.</p>
                        <a href="reports/attendance_summary.php" class="btn btn-primary w-100">
                            <i class="bi bi-arrow-right-circle me-2"></i>View Report
                        </a>
                    </div>
                </div>
            </div>

            <!-- Detailed Report Card -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-list-ul"></i>
                        </div>
                        <h5 class="card-title">Detailed Report</h5>
                        <p class="card-text text-muted">Access detailed guest information and attendance records.</p>
                        <a href="reports/detailed_report.php" class="btn btn-primary w-100">
                            <i class="bi bi-arrow-right-circle me-2"></i>View Report
                        </a>
                    </div>
                </div>
            </div>

            <!-- System Status Card -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-3">System Status</h5>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-<?php echo $dbStatus['class']; ?>-subtle p-3 rounded-circle me-3">
                                <i class="bi bi-database text-<?php echo $dbStatus['class']; ?>" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Database</h6>
                                <span class="text-<?php echo $dbStatus['class']; ?> small">
                                    <i class="bi bi-<?php echo $dbStatus['icon']; ?>"></i>
                                    <?php echo $dbStatus['message']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="bg-success-subtle p-3 rounded-circle me-3">
                                <i class="bi bi-check-circle text-success" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Application</h6>
                                <span class="text-success small">
                                    <i class="bi bi-check-circle"></i>
                                    All systems operational
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-white py-4 mt-5">
        <div class="container">
            <div class="text-center text-muted small">
                &copy; <?php echo date('Y'); ?> Nialike Portal. All rights reserved.
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
