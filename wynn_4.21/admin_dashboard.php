<?php
$pageTitle = "Dashboard";
require_once 'admin_header.php';
require_once 'admin_db_connect.php'; // Now this file should set $conn to null/false on failure instead of dying

// --- Initialize Stats ---
// $databaseStatus, $authStatus, $reportStatus are no longer needed for display
$userCount = 0;
$adminCount = 0;
$reportCount = 0;

// --- Fetch Stats IF Database Connected ---
if ($conn) { // Check if $conn is a valid object (not null/false)
    // No need to ping just for status display anymore

    // Use try-catch for queries
    try {
        // Fetch basic stats
        $userResult = $conn->query("SELECT COUNT(*) as count FROM user_file");
        if ($userResult) {
            $userCount = $userResult->fetch_assoc()['count'];
        } // If query fails, count remains 0

        $adminResult = $conn->query("SELECT COUNT(*) as count FROM admin_file");
        if ($adminResult) {
            $adminCount = $adminResult->fetch_assoc()['count'];
        } // If query fails, count remains 0

        $reportResult = $conn->query("SELECT COUNT(*) as count FROM report_file");
        if ($reportResult) {
            $reportCount = $reportResult->fetch_assoc()['count'];
        } // If query fails, count remains 0

        $conn->close(); // Close the connection

    } catch (mysqli_sql_exception $e) {
        // If any count query fails, counts will remain 0.
        // Log error if needed:
        // error_log("Dashboard count query failed: " . $e->getMessage());
    }

}
// If $conn was null/false, the counts simply remain 0.

?>

<!-- BEGIN Improved Layout -->
<div class="dashboard-wrapper">

  <!-- Welcome Message -->
  <div class="welcome-header">
    <h1>Welcome Admin <?php echo isset($adminName) ? htmlspecialchars($adminName) : 'Admin'; ?>!</h1>
    <p>This is the administration dashboard for FinSight. Manage your site's content and users from here.</p>
  </div>

  <!-- System Overview Section -->
  <div class="section-header">
    <h2><i class="fas fa-tachometer-alt"></i> System Overview</h2>
  </div>

  <!-- Stats Cards -->
  <div class="admin-dashboard-stats">
    <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-users"></i></div>
      <div class="stat-value"><?php echo $userCount; ?></div>
      <div class="stat-label">Total Users</div>
      <a href="admin_manage_users.php" class="stat-link">Manage <i class="fas fa-arrow-right"></i></a>
    </div>

    <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
      <div class="stat-value"><?php echo $adminCount; ?></div>
      <div class="stat-label">Admin Accounts</div>
      <a href="admin_manage_admins.php" class="stat-link">Manage <i class="fas fa-arrow-right"></i></a>
    </div>

    <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
      <div class="stat-value"><?php echo $reportCount; ?></div>
      <div class="stat-label">Generated Reports</div>
      <a href="admin_manage_reports.php" class="stat-link">Manage <i class="fas fa-arrow-right"></i></a>
    </div>
  </div>

  <!-- Quick Access Section -->
  <div class="section-header">
    <h2><i class="fas fa-bolt"></i> Quick Access</h2>
  </div>

  <div class="quick-access-grid">
    <a href="add_admin.php" class="quick-access-card">
      <div class="card-icon"><i class="fas fa-user-plus"></i></div>
      <div class="card-title">Add New Admin</div>
      <div class="card-desc">Create a new admin account</div>
    </a>

    <a href="admin_manage_users.php" class="quick-access-card">
      <div class="card-icon"><i class="fas fa-user-edit"></i></div>
      <div class="card-title">Edit Users</div>
      <div class="card-desc">Modify user accounts</div>
    </a>

    <a href="admin_user_statistics.php" class="quick-access-card">
      <div class="card-icon"><i class="fas fa-chart-bar"></i></div>
      <div class="card-title">View Statistics</div>
      <div class="card-desc">User activity and reports</div>
    </a>

    <a href="admin_manage_reports.php" class="quick-access-card">
      <div class="card-icon"><i class="fas fa-download"></i></div>
      <div class="card-title">Download Reports</div>
      <div class="card-desc">Get system reports</div>
    </a>
  </div>

  <!-- System Status Section - REMOVED -->
  <!-- No longer displaying Database Connection, User Auth, Report Gen status -->

</div>

<style>
  /* Paste your previous CSS styles here */
  .dashboard-wrapper {
    padding: 1.5rem 2rem;
    max-width: 1400px;
    margin: 0 auto;
  }

  .welcome-header {
    margin-bottom: 2.5rem;
    position: relative;
    padding-bottom: 1.5rem;
  }

  .welcome-header::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: 0;
    width: 80px;
    height: 4px;
    background: linear-gradient(90deg, #007bff, #00c6ff);
    border-radius: 2px;
  }

  .welcome-header h1 {
    color: #0A2540;
    font-size: 2rem;
    margin-bottom: 0.75rem;
    font-weight: 700;
  }

  .welcome-header p {
    color: #6c757d;
    font-size: 1.1rem;
    max-width: 700px;
  }

  .section-header {
    margin: 2.5rem 0 1.5rem 0;
    display: flex;
    align-items: center;
  }

  .section-header h2 {
    font-size: 1.5rem;
    color: #0A2540;
    font-weight: 600;
    display: flex;
    align-items: center;
  }

  .section-header h2 i {
    margin-right: 0.75rem;
    color: #007bff;
    font-size: 1.25rem;
  }

  .admin-dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1rem;
  }

  .stat-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
    padding: 1.75rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
  }

  .stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 6px;
    height: 100%;
    background: linear-gradient(180deg, #007bff 0%, #0056b3 100%);
  }

  .stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
  }

  .stat-card .stat-icon {
    position: absolute;
    top: 1.25rem;
    right: 1.25rem;
    font-size: 2.5rem;
    color: rgba(0, 123, 255, 0.15);
  }

  .stat-card .stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: #0A2540;
    margin-bottom: 0.5rem;
    line-height: 1.2;
    position: relative;
    z-index: 1;
  }

  .stat-card .stat-label {
    color: #6c757d;
    font-size: 1rem;
    font-weight: 500;
    position: relative;
    z-index: 1;
  }

  .stat-card .stat-link {
    margin-top: 1rem;
    display: inline-flex;
    align-items: center;
    color: #007bff;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.2s ease;
  }

  .stat-card .stat-link i {
    margin-left: 0.5rem;
    transition: transform 0.2s ease;
  }

  .stat-card .stat-link:hover {
    color: #0056b3;
  }

  .stat-card .stat-link:hover i {
    transform: translateX(3px);
  }

  .quick-access-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem; /* Adjusted margin as status section is gone */
  }

  .quick-access-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
    padding: 1.75rem;
    text-decoration: none;
    text-align: center;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    overflow: hidden;
    border: 1px solid #E9ECEF;
  }

  .quick-access-card::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #007bff, #00c6ff);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
  }

  .quick-access-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
  }

  .quick-access-card:hover::after {
    transform: scaleX(1);
  }

  .quick-access-card .card-icon {
    background: linear-gradient(135deg, #f4f9ff 0%, #eaf4ff 100%);
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.25rem;
    color: #007bff;
    font-size: 1.75rem;
    transition: all 0.3s ease;
  }

  .quick-access-card:hover .card-icon {
    background: linear-gradient(135deg, #007bff 0%, #00c6ff 100%);
    color: #fff;
    transform: scale(1.1);
  }

  .quick-access-card .card-title {
    color: #0A2540;
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
  }

  .quick-access-card .card-desc {
    color: #6c757d;
    font-size: 0.9rem;
  }

  /* Styles for status-card, status-icon etc. are no longer needed */
  /* .system-status-container, .status-card, .status-icon, ... can be removed */

  @media (max-width: 768px) {
    .dashboard-wrapper {
      padding: 1rem;
    }

    .welcome-header h1 {
      font-size: 1.75rem;
    }

    .quick-access-grid {
      grid-template-columns: 1fr;
    }
  }

</style>

<!-- END Improved Layout -->

<?php require_once 'admin_footer.php'; ?>