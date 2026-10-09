<?php
require_once 'db.php';
requireRole(array(3));

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $user = esc($_POST['user']);
    $mpass = esc($_POST['mpass']);
    $role = intval($_POST['role']);
    
    if ($id > 0) {
        $passSql = $mpass ? ", mpass = '$mpass'" : "";
        mysqli_query($mysqli, "UPDATE mpass SET user='$user', role=$role $passSql WHERE id=$id");
    } else {
        mysqli_query($mysqli, "INSERT INTO mpass (user, mpass, role, active, dateent) VALUES ('$user', '$mpass', $role, 1, NOW())");
    }
    header('Location: admin_users.php?saved=1');
    exit;
}

if (isset($_GET['delete'])) {
    $delId = intval($_GET['delete']);
    if ($delId != 1) { // Don't delete first admin
        mysqli_query($mysqli, "DELETE FROM mpass WHERE id=$delId");
    }
    header('Location: admin_users.php');
    exit;
}

$res = mysqli_query($mysqli, "SELECT * FROM mpass ORDER BY role DESC, user ASC");
$users = array();
while ($r = mysqli_fetch_assoc($res)) $users[] = $r;

$pageTitle = 'User Management';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>User Management - Admin</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<?php include 'includes/header.php'; ?>

        <?php if (isset($_GET['saved'])): ?>
        <div style="background:#d4edda;color:#155724;padding:12px;border-radius:8px;margin-bottom:16px;">Saved!</div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 2fr;gap:16px;">
            <!-- ADD/EDIT FORM -->
            <div class="data-card">
                <h4 id="formTitle">Add New User</h4>
                <form method="POST" style="margin-top:16px;">
                    <input type="hidden" name="id" id="userId" value="0">
                    <div class="form-group" style="margin-bottom:12px;">
                        <label style="font-size:12px;font-weight:600;color:#6c3483;">Username</label>
                        <input type="text" name="user" id="userInput" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                    </div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label style="font-size:12px;font-weight:600;color:#6c3483;">Password <span id="passNote" style="color:#888;font-weight:normal;"></span></label>
                        <input type="text" name="mpass" id="passInput" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                    </div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label style="font-size:12px;font-weight:600;color:#6c3483;">Role</label>
                        <select name="role" id="roleInput" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                            <option value="1">1 - POS User</option>
                            <option value="2">2 - Kitchen Staff</option>
                            <option value="3">3 - Admin</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">Save</button>
                    <button type="button" class="btn btn-outline" style="width:100%;margin-top:8px;" onclick="resetForm()">Cancel</button>
                </form>
            </div>

            <!-- USERS LIST -->
            <div class="data-card">
                <h4>All Users (<?php echo count($users); ?>)</h4>
                <table>
                    <thead>
                        <tr><th>ID</th><th>Username</th><th>Role</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): 
                            $roleNames = array(1 => 'POS User', 2 => 'Kitchen', 3 => 'Admin');
                            $roleColors = array(1 => '#3498db', 2 => '#e67e22', 3 => '#e74c3c');
                            $rName = isset($roleNames[$u['role']]) ? $roleNames[$u['role']] : 'Unknown';
                            $rColor = isset($roleColors[$u['role']]) ? $roleColors[$u['role']] : '#999';
                        ?>
                        <tr>
                            <td><?php echo $u['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($u['user']); ?></strong>
                                <?php if ($u['user'] == $_SESSION['user']): ?>
                                <small style="color:#6c3483;">(You)</small>
                                <?php endif; ?>
                            </td>
                            <td><span style="background:<?php echo $rColor; ?>;color:#fff;padding:3px 10px;border-radius:12px;font-size:11px;">
                                <?php echo $u['role']; ?> - <?php echo $rName; ?>
                            </span></td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="editUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['user'], ENT_QUOTES); ?>', <?php echo $u['role']; ?>)">Edit</button>
                                <?php if ($u['id'] != 1 && $u['user'] != $_SESSION['user']): ?>
                                <a href="?delete=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete user <?php echo htmlspecialchars($u['user']); ?>?')">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editUser(id, user, role) {
    document.getElementById('formTitle').textContent = 'Edit User: ' + user;
    document.getElementById('userId').value = id;
    document.getElementById('userInput').value = user;
    document.getElementById('roleInput').value = role;
    document.getElementById('passInput').value = '';
    document.getElementById('passNote').textContent = '(leave blank to keep current)';
    window.scrollTo(0, 0);
}
function resetForm() {
    document.getElementById('formTitle').textContent = 'Add New User';
    document.getElementById('userId').value = 0;
    document.getElementById('userInput').value = '';
    document.getElementById('passInput').value = '';
    document.getElementById('roleInput').value = 1;
    document.getElementById('passNote').textContent = '';
}
</script>
</body>
</html>