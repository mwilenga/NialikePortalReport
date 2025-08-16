<?php
// Start session and check admin authentication
session_start();

// Simple authentication - in a production environment, use a proper authentication system
$validPassword = 'admin123'; // Change this to a strong password in production
$isAuthenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $validPassword) {
        $_SESSION['authenticated'] = true;
        $isAuthenticated = true;
    } else {
        $error = 'Invalid password';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: database_config.php');
    exit;
}

// Only proceed if authenticated
if (!$isAuthenticated) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - Nialike Portal</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background-color: #f8f9fc;
                height: 100vh;
                display: flex;
                align-items: center;
            }
            .login-container {
                max-width: 400px;
                width: 100%;
                margin: 0 auto;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 login-container">
                    <div class="card shadow">
                        <div class="card-body p-4">
                            <h4 class="text-center mb-4">Admin Login</h4>
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Login</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Load current config
$configFile = dirname(__DIR__) . '/config/database.php';
$currentConfig = file_exists($configFile) ? require $configFile : [
    'host' => 'localhost',
    'username' => '',
    'password' => '',
    'database' => '',
    'port' => '3306'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $newConfig = [
        'host' => $_POST['host'],
        'username' => $_POST['username'],
        'password' => $_POST['password'],
        'database' => $_POST['database'],
        'port' => $_POST['port']
    ];
    
    // Save the new configuration
    $configContent = "<?php\n// Database configuration\nreturn [\n";
    foreach ($newConfig as $key => $value) {
        $configContent .= "    '$key' => '" . addslashes($value) . "',\n";
    }
    $configContent .= "];\n";
    
    if (file_put_contents($configFile, $configContent)) {
        $success = 'Database configuration updated successfully!';
        $currentConfig = $newConfig;
    } else {
        $error = 'Failed to save configuration. Check file permissions.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Configuration - Nialike Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fc;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #4e73df;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.35rem;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        .sidebar .nav-link i {
            width: 1.5rem;
            text-align: center;
            margin-right: 0.5rem;
        }
        .main-content {
            padding: 2rem;
        }
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar py-3">
                <div class="text-center mb-4">
                    <h5>Nialike Portal</h5>
                    <small>Admin Panel</small>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="database_config.php" class="nav-link active">
                            <i class="bi bi-database"></i> Database
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../index.php" class="nav-link">
                            <i class="bi bi-house"></i> Back to Dashboard
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a href="?logout=1" class="nav-link text-warning">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h1 class="h3">Database Configuration</h1>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="host" class="form-label">Host</label>
                                <input type="text" class="form-control" id="host" name="host" value="<?php echo htmlspecialchars($currentConfig['host']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($currentConfig['username']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" value="<?php echo htmlspecialchars($currentConfig['password']); ?>">
                                <div class="form-text">Leave blank to keep current password</div>
                            </div>
                            <div class="mb-3">
                                <label for="database" class="form-label">Database Name</label>
                                <input type="text" class="form-control" id="database" name="database" value="<?php echo htmlspecialchars($currentConfig['database']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="port" class="form-label">Port</label>
                                <input type="number" class="form-control" id="port" name="port" value="<?php echo htmlspecialchars($currentConfig['port']); ?>" required>
                            </div>
                            <button type="submit" name="save" class="btn btn-primary">Save Configuration</button>
                        </form>
                    </div>
                </div>

                <div class="alert alert-info mt-4">
                    <h6><i class="bi bi-info-circle"></i> Important Note</h6>
                    <p class="mb-0">
                        After changing the database configuration, please test the connection to ensure everything is working correctly.
                        The application will use these settings to connect to the database.
                    </p>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
