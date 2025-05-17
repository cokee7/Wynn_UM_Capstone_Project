<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = "Manage Users";
require_once 'admin_header.php';
require_once 'admin_db_connect.php';

$message = '';
$message_type = ''; // 'success' or 'error'

// Handle User Deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_user'])) {
    $user_id_to_delete = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

    if ($user_id_to_delete) {
        // Start transaction - useful if deleting related data too (e.g., interests)
        $conn->begin_transaction();

        try {

            // Now delete from the main user table
            $stmt_user = $conn->prepare("DELETE FROM user_file WHERE User_ID = ?");
            $stmt_user->bind_param("i", $user_id_to_delete);
            $stmt_user->execute();

            if ($stmt_user->affected_rows > 0) {
                $conn->commit();
                $message = "User deleted successfully.";
                $message_type = 'success';
            } else {
                $conn->rollback(); // Rollback if user wasn't found/deleted
                $message = "User not found or could not be deleted.";
                $message_type = 'error';
            }
            $stmt_user->close();

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $message = "Error deleting user: " . $exception->getMessage();
            $message_type = 'error';
            // Log the detailed error: error_log("Error deleting user ID $user_id_to_delete: " . $exception->getMessage());
        }
    } else {
        $message = "Invalid user ID for deletion.";
        $message_type = 'error';
    }
}


// Fetch all users
$users = [];
$sql = "SELECT User_ID, User_Login_Name, Email, Add_Time FROM user_file ORDER BY Add_Time DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
} elseif (!$result) {
     $message = "Error fetching users: " . $conn->error;
     $message_type = 'error';
}

?>

<h1>Manage Users</h1>

<div style="display: flex; justify-content: flex-end; margin-bottom: 1rem;">
  <input type="text" id="userSearch" placeholder="🔍 Search by ID, name, or email..." style="padding: 0.5rem 1rem; border-radius: 4px; border: 1px solid #ccc; width: 280px;">
</div>


<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th>User ID</th>
            <th>Login Name</th>
            <th>Email</th>
            <th>Registered On</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($users)): ?>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['User_ID']); ?></td>
                    <td><?php echo htmlspecialchars($user['User_Login_Name']); ?></td>
                    <td><?php echo htmlspecialchars($user['Email']); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($user['Add_Time'])); ?></td>
                    <td>
                        <a href="admin_edit_user.php?id=<?php echo $user['User_ID']; ?>" class="edit-btn">Edit Info</a>
                        <form action="admin_manage_users.php" method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete user <?php echo htmlspecialchars($user['User_Login_Name']); ?>? This action cannot be undone.');">
                            <input type="hidden" name="user_id" value="<?php echo $user['User_ID']; ?>">
                            <button type="submit" name="delete_user" class="delete-btn">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6">No users found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once 'admin_footer.php'; ?>

<script>
document.getElementById('userSearch').addEventListener('input', function () {
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
