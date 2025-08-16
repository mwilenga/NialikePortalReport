<?php
// Include the database configuration
require_once '../db.php';

// Get current settings from database if they exist
$currentSettings = array(
    'host' => DB_HOST,
    'database' => DB_NAME,
    'username' => DB_USER,
    'password' => DB_PASS,
    'port' => defined('DB_PORT') ? DB_PORT : '3306'
);

// Save settings if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        // Update the database configuration file
        $configContent = "<?php\ndefine('DB_HOST', '" . addslashes($_POST['host']) . "');\ndefine('DB_USER', '" . addslashes($_POST['username']) . "');\ndefine('DB_PASS', '" . addslashes($_POST['password']) . "');\ndefine('DB_NAME', '" . addslashes($_POST['database']) . "');\ndefine('DB_PORT', '" . addslashes($_POST['port']) . "');\n";
        
        // Write to the configuration file
        if (file_put_contents('../db.php', $configContent)) {
            $_SESSION['success_message'] = 'Database configuration updated successfully';
        } else {
            $_SESSION['error_message'] = 'Failed to update database configuration';
        }
    }
}

// Test connection function
function testConnection($settings) {
    try {
        $conn = new mysqli(
            $settings['host'],
            $settings['username'],
            $settings['password'],
            $settings['database']
        );
        
        if ($conn->connect_error) {
            return array('success' => false, 'message' => 'Connection failed: ' . $conn->connect_error);
        }
        
        return array('success' => true, 'message' => 'Database connection successful');
    } catch (Exception $e) {
        return array('success' => false, 'message' => 'Error: ' . $e->getMessage());
    }
}

// Test connection if button was clicked
$testResult = null;
if (isset($_POST['test_connection'])) {
    $testResult = testConnection($currentSettings);
}
?>

<?php
// Show any session messages
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Configuration - Nialike Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .form-control {
            margin-bottom: 1rem;
        }
        .connection-status {
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0">Database Configuration</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($testResult): ?>
                        <div class="alert <?php echo $testResult['success'] ? 'alert-success' : 'alert-danger' ?>">
                            <?php echo htmlspecialchars($testResult['message']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="host" class="form-label">Host</label>
                                <input type="text" class="form-control" id="host" name="host" 
                                       value="<?php echo htmlspecialchars($currentSettings['host']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="port" class="form-label">Port</label>
                                <input type="number" class="form-control" id="port" name="port" 
                                       value="<?php echo htmlspecialchars($currentSettings['port']); ?>" min="1" max="65535">
                                <div class="form-text">
                                    Default MySQL port is 3306
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="database" class="form-label">Database</label>
                                <input type="text" class="form-control" id="database" name="database" 
                                       value="<?php echo htmlspecialchars($currentSettings['database']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($currentSettings['username']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       value="<?php echo htmlspecialchars($currentSettings['password']); ?>" required>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <button type="submit" name="test_connection" class="btn btn-primary">
                                    Test Connection
                                </button>
                                <button type="submit" name="save_settings" class="btn btn-success">
                                    Save Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict'
            
            var forms = document.querySelectorAll('.needs-validation')
            
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>
</html>
