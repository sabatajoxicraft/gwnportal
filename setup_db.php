<?php
require_once 'includes/config.php';
require_once 'includes/functions.php'; // Added to access password functions

$success = false;
$error = '';
$log = [];
$results = []; // Array to store detailed results for each query

// Function to extract table name from SQL query
function extractTableName($query) {
    $query = trim($query);
    
    // For CREATE TABLE statements
    if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`]?(\w+)[`]?/i', $query, $matches)) {
        return $matches[1];
    }
    
    // For ALTER TABLE statements
    if (preg_match('/ALTER\s+TABLE\s+[`]?(\w+)[`]?/i', $query, $matches)) {
        return $matches[1];
    }
    
    // For DROP TABLE statements
    if (preg_match('/DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?[`]?(\w+)[`]?/i', $query, $matches)) {
        return $matches[1];
    }
    
    // For INSERT INTO statements
    if (preg_match('/INSERT\s+INTO\s+[`]?(\w+)[`]?/i', $query, $matches)) {
        return $matches[1];
    }
    
    // For other statements, return first few characters
    return substr($query, 0, 20) . '...';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .progress-bar-animated {
            animation: progress-bar-stripes 1s linear infinite;
        }
        .log-entry {
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="public/index.php"><?= APP_NAME ?></a>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Database Setup Process</h4>
                    </div>
                    <div class="card-body">
                        <div class="progress mb-4">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%;" id="progress-bar"></div>
                        </div>
                        <?php
                        try {
                            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
                            if ($conn->connect_error) {
                                throw new Exception("Connection failed: " . $conn->connect_error);
                            }
                            $log[] = "Connected to MySQL server successfully.";

                            // Check if database exists
                            $result = $conn->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
                            if ($result->num_rows > 0) {
                                $log[] = "Database '" . DB_NAME . "' already exists.";
                                if (!$conn->select_db(DB_NAME)) {
                                    throw new Exception("Error selecting database: " . $conn->error);
                                }
                                $log[] = "Selected database '" . DB_NAME . "'.";
                            } else {
                                $log[] = "Database '" . DB_NAME . "' does not exist. Creating...";
                                $sql = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
                                if ($conn->query($sql) !== TRUE) {
                                    throw new Exception("Error creating database: " . $conn->error);
                                }
                                $log[] = "Database created successfully.";
                                if (!$conn->select_db(DB_NAME)) {
                                    throw new Exception("Error selecting database: " . $conn->error);
                                }
                                $log[] = "Selected database '" . DB_NAME . "'.";
                            }

                            $log[] = "Applying database schema...";
                            $schemaFile = file_get_contents('db/schema.sql');
                            if ($schemaFile === false) {
                                throw new Exception("Error reading schema.sql file.");
                            }

                            // Split the schema into individual queries
                            $queries = preg_split('/;\s*\n/', $schemaFile);
                            $totalQueries = count($queries);
                            $completedQueries = 0;

                            foreach ($queries as $query) {
                                $query = trim($query);
                                if (!empty($query)) {
                                    if ($conn->query($query) === TRUE) {
                                        $tableName = extractTableName($query);
                                        $results[] = [
                                            'query' => $query,
                                            'status' => 'success',
                                            'message' => 'Query executed successfully.',
                                            'table' => $tableName
                                        ];
                                    } else {
                                        if (strpos($conn->error, 'Unknown table') !== false) {
                                            $tableName = extractTableName($query);
                                            $results[] = [
                                                'query' => $query,
                                                'status' => 'warning',
                                                'message' => 'Table does not exist, skipping DROP TABLE statement.',
                                                'table' => $tableName
                                            ];
                                        } else {
                                            $tableName = extractTableName($query);
                                            $results[] = [
                                                'query' => $query,
                                                'status' => 'error',
                                                'message' => $conn->error,
                                                'table' => $tableName
                                            ];
                                        }
                                    }
                                    $completedQueries++;
                                    $progress = ($completedQueries / $totalQueries) * 100;
                                    echo "<script>document.getElementById('progress-bar').style.width = '$progress%';</script>";
                                    flush();
                                    ob_flush();
                                }
                            }

                            // Hash default passwords for admin and owner users
                            $adminPassword = createPasswordHash('password123');
                            $ownerPassword = createPasswordHash('password123');

                            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $stmt->bind_param("si", $adminPassword, $adminId);
                            $adminId = 1;
                            $stmt->execute();

                            $stmt->bind_param("si", $ownerPassword, $ownerId);
                            $ownerId = 2;
                            $stmt->execute();

                            $log[] = "Default passwords hashed and updated successfully.";

                            // Check if any errors occurred in the results
                            $hasErrors = false;
                            foreach ($results as $result) {
                                if ($result['status'] === 'error') {
                                    $hasErrors = true;
                                    break;
                                }
                            }

                            $log[] = "Database setup completed successfully!";
                            $success = !isset($error) && !$hasErrors;
                        } catch (Exception $e) {
                            $error = $e->getMessage();
                            $log[] = "ERROR: " . $error;
                        } finally {
                            if (isset($conn)) {
                                $conn->close();
                            }
                        }
                        ?>
                        <div class="setup-log bg-light p-3 mb-4" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px;">
                            <div class="accordion" id="queryAccordion">
                                <?php foreach ($results as $index => $result): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="heading<?= $index ?>">
                                            <button class="accordion-button <?= ($result['status'] !== 'success') ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $index ?>" aria-expanded="<?= ($result['status'] !== 'success') ? 'true' : 'false' ?>" aria-controls="collapse<?= $index ?>">
                                                <?php if ($result['status'] === 'success'): ?>
                                                    <span class="text-success"><i class="bi bi-check-circle-fill me-2"></i></span>
                                                <?php elseif ($result['status'] === 'warning'): ?>
                                                    <span class="text-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i></span>
                                                <?php elseif ($result['status'] === 'info'): ?>
                                                    <span class="text-info"><i class="bi bi-info-circle-fill me-2"></i></span>
                                                <?php else: ?>
                                                    <span class="text-danger"><i class="bi bi-x-circle-fill me-2"></i></span>
                                                <?php endif; ?>
                                                <strong><?= htmlspecialchars($result['table']) ?></strong> - <?= htmlspecialchars($result['message']) ?>
                                            </button>
                                        </h2>
                                        <div id="collapse<?= $index ?>" class="accordion-collapse collapse <?= ($result['status'] !== 'success') ? 'show' : '' ?>" aria-labelledby="heading<?= $index ?>" data-bs-parent="#queryAccordion">
                                            <div class="accordion-body">
                                                <pre class="mb-0 p-2 bg-dark text-light rounded" style="overflow-x: auto;"><code><?= htmlspecialchars($result['query']) ?></code></pre>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <h4 class="alert-heading"><i class="bi bi-check-circle-fill me-2"></i>Setup Complete!</h4>
                                <p>The database has been successfully set up. You can now use the application.</p>
                                <hr>
                                <p class="mb-0">
                                    <strong>Default Admin Login:</strong> Username: <code>admin</code> <br>
                                    <strong>Default Owner Login:</strong> Username: <code>thabo</code> <br>
                                    <em>You will be prompted to set your password on first login.</em>
                                </p>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="public/index.php" class="btn btn-primary">Go to Homepage</a>
                                <a href="public/login.php" class="btn btn-outline-primary">Login</a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Setup Failed</h4>
                                <p>There was an error during the setup process. Please check the logs above for more details.</p>
                                <hr>
                                <p class="mb-0">Try checking your database credentials in config.php and make sure your MySQL server is running.</p>
                            </div>
                            <div class="d-grid">
                                <a href="setup_db.php" class="btn btn-primary">Try Again</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-5 py-3 bg-light text-center">
        <div class="container">
            <p class="mb-0">&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
