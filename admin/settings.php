<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'];

$success = '';
$error = '';

// Handle profile update
if (isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    
    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE id=?");
    $stmt->bind_param("sssi", $full_name, $email, $phone, $admin_id);
    
    if ($stmt->execute()) {
        $_SESSION['full_name'] = $full_name;
        $success = 'Profile updated successfully!';
    } else {
        $error = 'Failed to update profile!';
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = md5($_POST['current_password']);
    $new_password = md5($_POST['new_password']);
    $confirm_password = md5($_POST['confirm_password']);
    
    // Verify current password
    $check = $conn->query("SELECT password FROM users WHERE id='$admin_id'");
    $user = $check->fetch_assoc();
    
    if ($user['password'] != $current_password) {
        $error = 'Current password is incorrect!';
    } elseif ($new_password != $confirm_password) {
        $error = 'New passwords do not match!';
    } else {
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $new_password, $admin_id);
        if ($stmt->execute()) {
            $success = 'Password changed successfully!';
        } else {
            $error = 'Failed to change password!';
        }
    }
}

// Get user data
$user_query = $conn->query("SELECT * FROM users WHERE id='$admin_id'");
$user = $user_query->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Skynusa Academy</title>
    <link rel="stylesheet" href="../assets/css/modern-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #f8fafc; color: #1e293b; }
        
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0; width: 260px;
            background: linear-gradient(180deg, #1e293b 0%, #334155 100%);
            padding: 30px 0; overflow-y: auto; z-index: 1000;
        }
        
        .main-content { margin-left: 260px; padding: 30px; min-height: 100vh; }
        
        .header {
            background: white; padding: 25px 30px; border-radius: 15px;
            margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex; justify-content: space-between; align-items: center;
        }
        .header h1 { font-size: 28px; font-weight: 700; }
        
        .btn {
            padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;
            font-weight: 600; text-decoration: none; display: inline-block;
            transition: all 0.3s; font-size: 14px;
        }
        .btn-primary { background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; }
        .btn-secondary { background: #e2e8f0; color: #475569; }
        
        .alert {
            padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500;
        }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        
        .panel {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px;
        }
        .panel h2 { margin-bottom: 20px; font-size: 20px; }
        
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #475569; }
        .form-group input {
            padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; transition: all 0.3s;
        }
        .form-group input:focus { outline: none; border-color: #3b82f6; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar_admin.php'; ?>
    
    <div class="main-content">
        <div class="header">
            <h1>⚙️ Settings</h1>
            <a href="dashboard.php" class="btn btn-secondary">← Back</a>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="panel">
            <h2>👤 Profile Settings</h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Username (Read Only)</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" readonly style="background: #f1f5f9;">
                    </div>
                </div>
                
                <button type="submit" name="update_profile" class="btn btn-primary">💾 Update Profile</button>
            </form>
        </div>
        
        <div class="panel">
            <h2>🔒 Change Password</h2>
            <form method="POST">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Current Password *</label>
                    <input type="password" name="current_password" required>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>New Password *</label>
                        <input type="password" name="new_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password *</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                </div>
                
                <button type="submit" name="change_password" class="btn btn-primary">🔒 Change Password</button>
            </form>
        </div>
        
        <div class="panel">
            <h2>ℹ️ System Information</h2>
            <div style="padding: 15px; background: #f8fafc; border-radius: 8px;">
                <p style="margin-bottom: 10px;"><strong>Last Updated:</strong> January 2026</p>
                <p><strong>Status:</strong> <span style="color: #10b981; font-weight: 600;">● Online</span></p>
            </div>
        </div>
    </div>
</body>
</html>