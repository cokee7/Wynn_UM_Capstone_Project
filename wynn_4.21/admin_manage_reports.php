<?php
$pageTitle = "Manage Reports";
require_once 'admin_header.php';
require_once 'admin_db_connect.php';

$message = '';
$message_type = '';

// Handle Report Deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_report'])) {
    $report_id_to_delete = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);

    if ($report_id_to_delete) {
        $stmt = $conn->prepare("DELETE FROM report_file WHERE Report_ID = ?");
        if ($stmt) {
            $stmt->bind_param("i", $report_id_to_delete);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = "Report deleted successfully.";
                $message_type = 'success';
            } else if ($stmt->affected_rows == 0) {
                $message = "Report not found or already deleted.";
                $message_type = 'error';
            } else {
                $message = "Error deleting report: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = "Error preparing delete statement: " . $conn->error;
            $message_type = 'error';
        }
    } else {
        $message = "Invalid Report ID for deletion.";
        $message_type = 'error';
    }
}


// Fetch all reports
$reports = [];
$sql = "SELECT Report_ID, Topic, Generated_Time FROM report_file ORDER BY Report_ID DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
} elseif (!$result) {
     $message = "Error fetching reports: " . $conn->error;
     $message_type = 'error';
}

?>

<h1>Manage Reports</h1>

<div style="display: flex; justify-content: flex-end; margin-bottom: 1rem;">
  <input type="text" id="reportSearch" placeholder="🔍 Search by ID or topic..." style="padding: 0.5rem 1rem; border-radius: 4px; border: 1px solid #ccc; width: 280px;">
</div>


<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- Add button/form here to GENERATE new reports if that's part of your system -->

<table>
    <thead>
        <tr>
            <th>Report ID</th>
            <th>Topic</th>
            <th>Generated Time</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($reports)): ?>
            <?php foreach ($reports as $report): ?>
                <tr>
                    <td><?php echo htmlspecialchars($report['Report_ID']); ?></td>
                    <td><?php echo htmlspecialchars($report['Topic'] ?? '—'); ?></td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($report['Generated_Time'])); ?></td>
                    <td>
                        <a href="admin_edit_report.php?id=<?php echo $report['Report_ID']; ?>" class="edit-btn">View/Edit</a>
                        <a href="admin_download_report.php?id=<?php echo $report['Report_ID']; ?>" class="download-btn" target="_blank">Download</a>
                         <form action="admin_manage_reports.php" method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete Report ID <?php echo $report['Report_ID']; ?>?');">
                            <input type="hidden" name="report_id" value="<?php echo $report['Report_ID']; ?>">
                            <button type="submit" name="delete_report" class="delete-btn">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="3">No reports found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once 'admin_footer.php'; ?>

<script>
document.getElementById('reportSearch').addEventListener('input', function () {
  const keyword = this.value.trim().toLowerCase();
  const rows = document.querySelectorAll('tbody tr');

  rows.forEach(row => {
    const id = row.children[0]?.textContent.trim().toLowerCase() || '';
    const topic = row.children[1]?.textContent.trim().toLowerCase() || '';

    const match = id.includes(keyword) || topic.includes(keyword);
    row.style.display = match ? '' : 'none';
  });
});
</script>
