<?php
// Start session IMPORTANT: Must be called before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in. Redirect to login page if not.
// Allow access to login page itself without being logged in.
$currentPage = basename($_SERVER['PHP_SELF']); // Get the current script name
if (!isset($_SESSION['admin_id']) && $currentPage != 'admin_login.php') {
    header("Location: admin_login.php");
    exit();
}

$adminName = isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name'], ENT_QUOTES, 'UTF-8') : 'Admin';

// Function to check if a nav link is active
function isActive($pageName) {
    return basename($_SERVER['PHP_SELF']) == $pageName ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinSight Admin - <?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="admin_style.css">
    <!-- Add any other CSS or JS libraries needed -->
</head>
<body>
    <div class="admin-container">
        <?php if (isset($_SESSION['admin_id'])): // Only show sidebar if logged in ?>
        <aside class="admin-sidebar">
            <h2>FinSight Admin</h2>
            <nav>
                <ul>
                    <li><a href="admin_dashboard.php" class="<?php echo isActive('admin_dashboard.php'); ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="admin_manage_users.php" class="<?php echo isActive('manage_users.php'); echo isActive('edit_user.php'); echo isActive('edit_user_preferences.php'); ?>"><i class="fas fa-users"></i> Manage Users</a></li>
                    <li><a href="admin_manage_admins.php" class="<?php echo isActive('manage_admins.php'); echo isActive('add_admin.php'); ?>"><i class="fas fa-user-shield"></i> Manage Admins</a></li>
                    <li><a href="admin_manage_reports.php" class="<?php echo isActive('manage_reports.php'); echo isActive('edit_report.php'); ?>"><i class="fas fa-file-alt"></i> Manage Reports</a></li>
                    <li><a href="admin_user_statistics.php" class="<?php echo isActive('user_statistics.php'); ?>"><i class="fas fa-chart-line"></i> User Statistics</a></li>
                    <li class="logout-link"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>
        <?php endif; ?>
        <main class="admin-content">
        <!-- Page content starts here -->