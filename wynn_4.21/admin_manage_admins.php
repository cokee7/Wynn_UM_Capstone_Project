<?php
$pageTitle = "Manage Admins";
require_once 'admin_header.php';
require_once 'admin_db_connect.php';

$debug = false;

$message = '';
$message_type = '';

// --- Deletion Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_admin'])) {
    $admin_id_to_delete = filter_input(INPUT_POST, 'admin_id', FILTER_VALIDATE_INT);
    $current_admin_id = $_SESSION['admin_id'];

    if ($admin_id_to_delete && $admin_id_to_delete != $current_admin_id) {
        $count_result = $conn->query("SELECT COUNT(*) as count FROM admin_file");
        $count_row = $count_result ? $count_result->fetch_assoc() : null;
        $count = $count_row ? $count_row['count'] : 0;

        if ($count > 1) {
            $stmt = $conn->prepare("DELETE FROM admin_file WHERE Admin_ID = ?");
            $stmt->bind_param("i", $admin_id_to_delete);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = "Admin deleted successfully.";
                $message_type = 'success';
            } else {
                $message = "Could not delete admin.";
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = "Cannot delete the last admin account.";
            $message_type = 'error';
        }
    } elseif ($admin_id_to_delete == $current_admin_id) {
        $message = "You cannot delete your own account.";
        $message_type = 'error';
    } else {
        $message = "Invalid Admin ID for deletion.";
        $message_type = 'error';
    }
}

// --- Fetch Admins ---
$admins = [];
$sql = "SELECT Admin_ID, Admin_Login_Name, Email, Privileges, Add_Time FROM admin_file ORDER BY Add_Time DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
} elseif (!$result) {
    $message = "Error fetching admins: " . $conn->error;
    $message_type = 'error';
}

// --- Get Current Admin's Privileges ---
$current_admin_id = $_SESSION['admin_id'];
$current_privileges = null;
foreach ($admins as $a) {
    if ($a['Admin_ID'] == $current_admin_id) {
        $current_privileges = $a['Privileges'];
        break;
    }
}
?>

<h1>Manage Administrators</h1>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
  <a href="add_admin.php" style="background-color: #28a745; color: white; padding: 0.6rem 1.2rem; text-decoration: none; border-radius: 4px;">+ Add New Admin</a>

  <input type="text" id="adminSearch" placeholder="🔍 Search by ID, name, or email..." style="padding: 0.5rem 1rem; border-radius: 4px; border: 1px solid #ccc; width: 280px;">
</div>


<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<table style="width:100%; border-collapse: collapse;">
    <thead>
        <tr style="background-color:#f0f0f0;">
            <th style="padding: 0.5rem; text-align:left;">Admin ID</th>
            <th style="padding: 0.5rem; text-align:left;">Login Name</th>
            <th style="padding: 0.5rem; text-align:left;">Email</th>
            <th style="padding: 0.5rem; text-align:left;">Privileges</th>
            <th style="padding: 0.5rem; text-align:left;">Added On</th>
            <th style="padding: 0.5rem; text-align:left;">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($admins)): ?>
            <?php foreach ($admins as $admin): ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 0.5rem;"><?php echo htmlspecialchars($admin['Admin_ID']); ?></td>
                    <td style="padding: 0.5rem;"><?php echo htmlspecialchars($admin['Admin_Login_Name']); ?></td>
                    <td style="padding: 0.5rem;"><?php echo htmlspecialchars($admin['Email']); ?></td>
                    <td style="padding: 0.5rem;"><?php echo htmlspecialchars($admin['Privileges']); ?></td>
                    <td style="padding: 0.5rem;"><?php echo date('Y-m-d H:i', strtotime($admin['Add_Time'])); ?></td>
                    <td style="padding: 0.5rem;">
                    <?php
                    if ($current_privileges === 'all') {
                        // Can edit all
                        if ($admin['Admin_ID'] == $current_admin_id) {
                            echo '<a href="admin_edit_admin_profile.php" class="edit-btn" style="margin-right:0.5rem;">Edit My Profile</a>';
                        } else {
                            echo '<a href="admin_edit_other_admin.php?id=' . $admin['Admin_ID'] . '" class="edit-btn" style="margin-right:0.5rem;">Edit</a>';
                        }
                    } elseif ($current_privileges === 'edit') {
                        // Can only edit self
                        if ($admin['Admin_ID'] == $current_admin_id) {
                            echo '<a href="admin_edit_admin_profile.php" class="edit-btn" style="margin-right:0.5rem;">Edit My Profile</a>';
                        } else {
                            echo '<span style="color:#888;">(No actions)</span>';
                        }
                    } else {
                        // 'view' cannot edit anything
                        echo '<span style="color:#888;">(No actions)</span>';
                    }
                    ?>


                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" style="padding: 0.5rem;">No administrators found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once 'admin_footer.php'; ?>

<script>
document.getElementById('adminSearch').addEventListener('input', function () {
  const keyword = this.value.trim().toLowerCase();
  const rows = document.querySelectorAll('tbody tr');

  rows.forEach(row => {
    const id = row.children[0]?.textContent.trim().toLowerCase() || '';
    const name = row.children[1]?.textContent.trim().toLowerCase() || '';
    const email = row.children[2]?.textContent.trim().toLowerCase() || '';

    const match = id.includes(keyword) || name.includes(keyword) || email.includes(keyword);
    row.style.display = match ? '' : 'none';
  });
});
</script>

