<?php
$pageTitle = "Edit Admin Profile";
require_once 'admin_header.php';
require_once 'admin_db_connect.php';

$message = '';
$message_type = '';

$admin_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$admin_id) {
    header("Location: admin_manage_admins.php");
    exit();
}

$admin = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $login_name = trim($_POST['login_name']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    if (empty($login_name)) {
         $message = "Login Name cannot be empty.";
         $message_type = 'error';
    } elseif ($email === false) {
         $message = "Invalid Email format.";
         $message_type = 'error';
    } elseif (!empty($new_password) && $new_password !== $confirm_new_password) {
         $message = "New passwords do not match.";
         $message_type = 'error';
    } else {
        $params = [$login_name, $email];
        $types = "ss";
        $sql = "UPDATE admin_file SET Admin_Login_Name = ?, Email = ?";

        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            if ($hashed_password === false) {
                $message = "Error hashing password.";
                $message_type = 'error';
            } else {
                $sql .= ", Password = ?";
                $params[] = $hashed_password;
                $types .= "s";
            }
        }

        if ($message_type !== 'error') {
            $sql .= ", Change_Timestamp = NOW() WHERE Admin_ID = ?";
            $params[] = $admin_id;
            $types .= "i";

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $message = "Admin profile updated successfully.";
                    $message_type = 'success';
                } else {
                    $message = "Update failed: " . $stmt->error;
                    $message_type = 'error';
                }
                $stmt->close();
            } else {
                $message = "Error preparing update statement: " . $conn->error;
                $message_type = 'error';
            }
        }
    }
}

$stmt_fetch = $conn->prepare("SELECT Admin_Login_Name, Email FROM admin_file WHERE Admin_ID = ?");
if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $admin_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
    } else {
        $message = "Admin not found.";
        $message_type = 'error';
    }
    $stmt_fetch->close();
} else {
    $message = "Error fetching admin data: " . $conn->error;
    $message_type = 'error';
}
?>

<h1>Edit Admin</h1>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($admin): ?>
<form action="admin_edit_other_admin.php?id=<?php echo $admin_id; ?>" method="post" class="admin-form">

    <div class="form-group">
        <label for="login_name">Login Name:</label>
        <input type="text" id="login_name" name="login_name" value="<?php echo htmlspecialchars($admin['Admin_Login_Name']); ?>" required>
    </div>

    <div class="form-group">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin['Email']); ?>" required>
    </div>

    <fieldset style="margin-top: 2rem; border: 1px solid #ccc; padding: 1.5rem; border-radius: 4px;">
        <legend style="padding: 0 0.5rem; font-weight: bold;">Change Password (Optional)</legend>
        <p class="form-note">Leave fields blank to keep current password.</p>
        <div class="form-group">
            <label for="new_password">New Password:</label>
            <input type="password" id="new_password" name="new_password">
        </div>
        <div class="form-group">
            <label for="confirm_new_password">Confirm New Password:</label>
            <input type="password" id="confirm_new_password" name="confirm_new_password">
        </div>
    </fieldset>

    <button type="submit" name="update_profile" style="margin-top: 1.5rem;">Update Admin</button>
    <a href="admin_manage_admins.php" style="margin-left: 1rem; text-decoration: none; color: #6c757d;">Cancel</a>
</form>
<?php else: ?>
    <p>Could not load admin data.</p>
<?php endif; ?>

<?php require_once 'admin_footer.php'; ?>
