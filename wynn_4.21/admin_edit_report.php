<?php
$pageTitle = "Edit Report";
require_once 'admin_header.php';
require_once 'admin_db_connect.php';

$message = '';
$message_type = '';
$report_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$report = null;

if (!$report_id) {
    header("Location: manage_reports.php"); // Redirect if no valid ID
    exit();
}

// Handle Form Submission (Update)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_report'])) {
    $report_id_post = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);
    $report_content = $_POST['report_content']; // Keep as raw text/HTML potentially

    if ($report_id_post !== $report_id) {
        $message = "Report ID mismatch.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("UPDATE report_file SET Report_Content = ? WHERE Report_ID = ?");
        if ($stmt) {
            $stmt->bind_param("si", $report_content, $report_id);
            if ($stmt->execute()) {
                $message = "Report content updated successfully.";
                $message_type = 'success';
            } else {
                $message = "Error updating report content: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = "Error preparing update statement: " . $conn->error;
            $message_type = 'error';
        }
    }
}

// Fetch current report details for the form
$stmt_fetch = $conn->prepare("SELECT Report_ID, Generated_Time, Report_Content FROM report_file WHERE Report_ID = ?");
if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $report_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    if ($result->num_rows === 1) {
        $report = $result->fetch_assoc();
    } else {
        $message = "Report not found.";
        $message_type = 'error';
    }
    $stmt_fetch->close();
} else {
    $message = "Error fetching report data: " . $conn->error;
    $message_type = 'error';
}

$conn->close();
?>

<h1>View/Edit Report ID: <?php echo $report ? htmlspecialchars($report['Report_ID']) : 'Not Found'; ?></h1>
<?php if($report): ?>
    <p>Generated: <?php echo date('Y-m-d H:i:s', strtotime($report['Generated_Time'])); ?></p>
<?php endif; ?>


<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($report): // Only show form if report was found ?>
<form action="admin_edit_report.php?id=<?php echo $report_id; ?>" method="post" class="admin-form">
    <input type="hidden" name="report_id" value="<?php echo $report_id; ?>">

    <div class="form-group">
        <label for="report_content">Report Content:</label>
        <textarea id="report_content" name="report_content" rows="20"><?php echo htmlspecialchars($report['Report_Content']); ?></textarea>
         <p class="form-note">Edit the text content of the report below.</p>
    </div>

    <button type="submit" name="update_report">Save Changes</button>
    <a href="admin_manage_reports.php" style="margin-left: 1rem; text-decoration: none; color: #6c757d;">Back to Reports</a>
     <a href="admin_download_report.php?id=<?php echo $report['Report_ID']; ?>" class="download-btn" target="_blank" style="margin-left: 1rem;">Download Current</a>

</form>
<?php else: ?>
    <p>Could not load report data.</p>
    <a href="manage_reports.php">Back to Reports List</a>
<?php endif; ?>


<?php require_once 'admin_footer.php'; ?>