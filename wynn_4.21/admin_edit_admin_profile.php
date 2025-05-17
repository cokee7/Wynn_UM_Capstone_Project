<?php
$pageTitle = "Edit My Profile";
require_once 'admin_header.php'; // Ensures logged in and gets admin_id
require_once 'admin_db_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = '';
$message_type = '';
$admin_id = $_SESSION['admin_id']; // Get current admin's ID
$admin = null;

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $login_name = trim($_POST['login_name']); // Usually good to allow changing login name too
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $current_password_verify = $_POST['current_password_verify']; // For changing password
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    // Basic Validation
    if (empty($login_name)) {
         $message = "Login Name cannot be empty.";
         $message_type = 'error';
    } elseif ($email === false) {
         $message = "Invalid Email format.";
         $message_type = 'error';
    } else {
        // Prepare base update query
        $params = [];
        $types = "";
        $sql = "UPDATE admin_file SET Admin_Login_Name = ?, Email = ?";
        $params[] = $login_name; $types .= "s";
        $params[] = $email; $types .= "s";

        // --- Handle Password Change ---
        $password_change_ok = true;
        if (!empty($new_password)) {
            if (empty($current_password_verify)) {
                $message = "Please enter your current password to set a new one.";
                $message_type = 'error';
                $password_change_ok = false;
            } elseif ($new_password !== $confirm_new_password) {
                 $message = "New passwords do not match.";
                 $message_type = 'error';
                 $password_change_ok = false;
            } else {
                // Verify current password
                $stmt_verify = $conn->prepare("SELECT Password FROM admin_file WHERE Admin_ID = ?");
                $stmt_verify->bind_param("i", $admin_id);
                $stmt_verify->execute();
                $result_verify = $stmt_verify->get_result();
                if ($admin_data = $result_verify->fetch_assoc()) {
                    if (password_verify($current_password_verify, $admin_data['Password'])) {
                        // Current password verified, hash the new one
                        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                        if ($hashed_new_password === false) {
                            $message = "Error hashing new password.";
                            $message_type = 'error';
                            $password_change_ok = false;
                        } else {
                             $sql .= ", Password = ?";
                             $params[] = $hashed_new_password; $types .= "s";
                        }
                    } else {
                        $message = "Incorrect current password. Password not changed.";
                        $message_type = 'error';
                        $password_change_ok = false;
                    }
                } else {
                     $message = "Could not verify current password. User not found?"; // Should not happen
                     $message_type = 'error';
                     $password_change_ok = false;
                }
                 $stmt_verify->close();
            }
        } // --- End Password Change Handling ---


        // --- Execute Update if No Errors ---
        if ($message_type !== 'error' && $password_change_ok) {
             // Add Change_Timestamp update
            $sql .= ", Change_Timestamp = NOW()";

            $sql .= " WHERE Admin_ID = ?";
            $params[] = $admin_id; $types .= "i";

            $stmt_update = $conn->prepare($sql);
            if ($stmt_update) {
                $stmt_update->bind_param($types, ...$params);
                if ($stmt_update->execute()) {
                    $message = "Profile updated successfully.";
                    $message_type = 'success';
                    // Update session variables if name changed
                    $_SESSION['admin_login_name'] = $login_name;
                } else {
                    // Check for duplicate login name error specifically
                    if ($conn->errno == 1062) { // Error code for duplicate entry
                         $message = "Error: Login Name '$login_name' is already taken.";
                    } else {
                        $message = "Error updating profile: " . $stmt_update->error;
                    }
                    $message_type = 'error';
                }
                $stmt_update->close();
            } else {
                 $message = "Error preparing update statement: " . $conn->error;
                 $message_type = 'error';
            }
        }
    }
} // End POST handling


// Fetch current admin details for the form (always fetch fresh data after potential update)
$stmt_fetch = $conn->prepare("SELECT Admin_Login_Name, Email FROM admin_file WHERE Admin_ID = ?");
if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $admin_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
    } else {
        // Should not happen if session is valid, but handle defensively
        $message = "Could not find your profile data.";
        $message_type = 'error';
        session_destroy(); // Log out if admin ID is invalid
        header("Location: admin_login.php");
        exit();
    }
    $stmt_fetch->close();
} else {
    $message = "Error fetching profile data: " . $conn->error;
    $message_type = 'error';
}

?>


<h1>Edit My Profile</h1>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($admin): // Only show form if admin data was loaded ?>
<form action="admin_edit_admin_profile.php" method="post" class="admin-form">

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
        <p class="form-note">Leave these fields blank to keep your current password.</p>
        <div class="form-group">
            <label for="current_password_verify">Current Password:</label>
            <input type="password" id="current_password_verify" name="current_password_verify">
            <p class="form-note">Required only if setting a new password.</p>
        </div>
         <div class="form-group">
            <label for="new_password">New Password:</label>
            <input type="password" id="new_password" name="new_password">
        </div>
         <div class="form-group">
            <label for="confirm_new_password">Confirm New Password:</label>
            <input type="password" id="confirm_new_password" name="confirm_new_password">
        </div>
    </fieldset>

    <button type="submit" name="update_profile" style="margin-top: 1.5rem;">Update Profile</button>
    <a href="admin_dashboard.php" style="margin-left: 1rem; text-decoration: none; color: #6c757d;">Cancel</a>
</form>
<?php else: ?>
    <p>Could not load your profile data. Please try logging out and back in.</p>
<?php endif; ?>


<?php require_once 'admin_footer.php'; ?>