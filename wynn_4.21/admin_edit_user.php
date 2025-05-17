<?php
$pageTitle = "Edit User";
require_once 'admin_header.php';
require_once 'admin_db_connect.php';

// Optional: Show errors for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = '';
$message_type = ''; // 'success' or 'error'

// 1. Validate the user_id from GET
$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$user_id) {
    header("Location: admin_manage_users.php");
    exit();
}

// 2. Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    // Re-validate user ID from hidden input
    $user_id_post = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

    $login_name = trim($_POST['login_name']);
    $gender     = trim($_POST['gender']);
    $email      = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $new_pass   = $_POST['new_password']; // new password if any

    // Basic checks
    if ($user_id_post !== $user_id) {
        $message = "User ID mismatch.";
        $message_type = 'error';
    } elseif (empty($login_name) || empty($gender) || !$email) {
        $message = "All fields except new password must be filled correctly.";
        $message_type = 'error';
    } else {
        // Build SQL
        $sql = "UPDATE user_file
                SET User_Login_Name = ?,
                    Gender = ?,
                    Email = ?";

        $params = [$login_name, $gender, $email];
        $types  = "sss";  // all strings

        // If user provided a new password
        if (!empty($new_pass)) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            if (!$hashed) {
                $message = "Error hashing password.";
                $message_type = 'error';
            } else {
                $sql .= ", Password = ?";
                $params[] = $hashed;
                $types   .= "s";
            }
        }

        // Complete WHERE clause
        $sql .= " WHERE User_ID = ?";
        $params[] = $user_id;
        $types   .= "i";

        // Only update if no hashing error
        if ($message_type !== 'error') {
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $message = "User details updated successfully.";
                    $message_type = 'success';
                } else {
                    $message = "Update failed: " . $stmt->error;
                    $message_type = 'error';
                }
                $stmt->close();
            } else {
                $message = "Error preparing statement: " . $conn->error;
                $message_type = 'error';
            }
        }
    }
}

// 3. Fetch the user’s current data to display
$user = null;
$query = "SELECT User_ID, User_Login_Name, Gender, Email, Add_Time 
          FROM user_file WHERE User_ID = ?";
$stmt_fetch = $conn->prepare($query);
if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $user_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
    } else {
        $message = "User not found.";
        $message_type = 'error';
    }
    $stmt_fetch->close();
} else {
    $message = "Error fetching user data: " . $conn->error;
    $message_type = 'error';
}


?>

<!-- PAGE CONTENT -->
<h1 style="font-size:1.5rem; margin-bottom:1rem;">
  Edit User:
  <span style="color:#0A74DA;">
    <?php echo $user ? htmlspecialchars($user['User_Login_Name']) : 'Not Found'; ?>
  </span>
</h1>

<!-- Display messages (success/error) -->
<?php if ($message): ?>
<div style="
    padding:1rem;
    margin-bottom:1rem;
    border-radius:5px;
    <?php if ($message_type === 'success'): ?>
        background-color:#d4edda; color:#155724;
    <?php else: ?>
        background-color:#f8d7da; color:#721c24;
    <?php endif; ?>
">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Show form only if user is found -->
<?php if ($user): ?>
<div style="
    background-color:#fff; 
    padding:1.5rem; 
    border-radius:8px; 
    max-width:600px;
    box-shadow:0 1px 3px rgba(0,0,0,0.1);
    margin-bottom:2rem;
">
    <form action="admin_edit_user.php?id=<?php echo $user_id; ?>" method="POST" style="display:flex; flex-direction:column; gap:1rem;">
        <!-- Hidden ID field -->
        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">

        <!-- Login Name -->
        <div>
            <label for="login_name" style="font-weight:600; margin-bottom:0.5rem; display:block;">
                Login Name:
            </label>
            <input
                type="text"
                id="login_name"
                name="login_name"
                value="<?php echo htmlspecialchars($user['User_Login_Name']); ?>"
                required
                style="width:100%; padding:0.5rem; border:1px solid #ccc; border-radius:4px;"
            >
        </div>



        <!-- Gender -->
        <div>
            <label for="gender" style="font-weight:600; margin-bottom:0.5rem; display:block;">
                Gender:
            </label>
            <select id="gender" name="gender" required style="width:100%; padding:0.5rem; border:1px solid #ccc; border-radius:4px;">
                <option value="" disabled <?php echo empty($user['Gender']) ? 'selected' : ''; ?>>Select Gender</option>
                <option value="Male" <?php echo ($user['Gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo ($user['Gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                <option value="Other" <?php echo ($user['Gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
            </select>
    </div>


        <!-- Email -->
        <div>
            <label for="email" style="font-weight:600; margin-bottom:0.5rem; display:block;">
                Email:
            </label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?php echo htmlspecialchars($user['Email']); ?>"
                required
                style="width:100%; padding:0.5rem; border:1px solid #ccc; border-radius:4px;"
            >
        </div>

        <!-- Optional: Display Add_Time as read-only info -->
        <div>
            <label style="font-weight:600; margin-bottom:0.5rem; display:block;">
                Registered On:
            </label>
            <input
                type="text"
                value="<?php echo htmlspecialchars($user['Add_Time']); ?>"
                disabled
                style="width:100%; padding:0.5rem; border:1px solid #e0e0e0; background:#f9f9f9; border-radius:4px;"
            >
        </div>

        <!-- New Password (optional) -->
        <div>
            <label for="new_password" style="font-weight:600; margin-bottom:0.5rem; display:block;">
                New Password (optional):
            </label>
            <input
                type="password"
                id="new_password"
                name="new_password"
                style="width:100%; padding:0.5rem; border:1px solid #ccc; border-radius:4px;"
            >
            <p style="margin-top:0.3rem; font-size:0.9rem; color:#666;">
                Leave blank to keep the current password.
            </p>
        </div>

        <!-- Action buttons -->
        <div style="display:flex; gap:1rem; margin-top:1rem;">
            <button
                type="submit"
                name="update_user"
                style="
                    padding:0.75rem 1.5rem; 
                    background-color:#0A74DA; 
                    color:#fff; 
                    border:none; 
                    border-radius:4px; 
                    cursor:pointer; 
                    font-weight:600;
                "
            >
                Update User
            </button>
            <a
                href="admin_manage_users.php"
                style="
                    padding:0.75rem 1.5rem; 
                    background-color:#6c757d; 
                    color:#fff; 
                    text-decoration:none; 
                    border-radius:4px; 
                    display:inline-block; 
                    font-weight:600;
                "
            >
                Cancel
            </a>
        </div>
    </form>
</div>
<?php else: ?>
    <p>User data could not be loaded.</p>
    <a href="admin_manage_users.php">Back to User List</a>
<?php endif; ?>

<?php require_once 'admin_footer.php'; ?>
