<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    
    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, address=? WHERE id=?");
    $stmt->bind_param("ssssi", $full_name, $email, $phone, $address, $student_id);
    
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
    $check = $conn->query("SELECT password FROM users WHERE id='$student_id'");
    $user = $check->fetch_assoc();
    
    if ($user['password'] != $current_password) {
        $error = 'Current password is incorrect!';
    } elseif ($new_password != $confirm_password) {
        $error = 'New passwords do not match!';
    } else {
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $new_password, $student_id);
        if ($stmt->execute()) {
            $success = 'Password changed successfully!';
        } else {
            $error = 'Failed to change password!';
        }
    }
}

// Get user data
$user_query = $conn->query("SELECT * FROM users WHERE id='$student_id'");
$user = $user_query->fetch_assoc();

// Get statistics
$total_courses = mysqli_num_rows($conn->query("SELECT * FROM enrollments WHERE student_id='$student_id'"));
$completed_courses = mysqli_num_rows($conn->query("SELECT * FROM enrollments WHERE student_id='$student_id' AND status='completed'"));
$total_assignments = mysqli_num_rows($conn->query("
    SELECT DISTINCT a.id FROM assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id='$student_id'
"));
$completed_assignments = mysqli_num_rows($conn->query("
    SELECT * FROM assignment_submissions 
    WHERE student_id='$student_id' AND status='graded'
"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Skynusa Academy</title>
    <link rel="stylesheet" href="../assets/css/modern-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #f0f2f5; color: #1a1a1a; }
        
        .dashboard { display: flex; min-height: 100vh; }
        
        .sidebar {
            width: 280px; background: linear-gradient(180deg, #1e3a8a 0%, #312e81 100%);
            color: white; padding: 30px 0; position: fixed; height: 100vh; overflow-y: auto;
        }
        .sidebar-header { padding: 0 30px 30px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 24px; font-weight: 700; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; color: rgba(255,255,255,0.7); }
        
        .user-profile { padding: 25px 30px; background: rgba(255,255,255,0.05); margin: 20px 0; }
        .user-profile .avatar {
            width: 50px; height: 50px; background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 22px; font-weight: 700; margin-bottom: 10px;
        }
        
        .sidebar-menu { list-style: none; padding: 20px 0; }
        .sidebar-menu li { margin: 5px 0; }
        .sidebar-menu a {
            display: flex; align-items: center; padding: 14px 30px; color: rgba(255,255,255,0.8);
            text-decoration: none; transition: all 0.3s; font-size: 15px;
        }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.1); color: white; padding-left: 35px; }
        .sidebar-menu a.active { background: rgba(255,255,255,0.15); color: white; border-left: 4px solid #60a5fa; }
        .sidebar-menu a span { margin-right: 12px; font-size: 18px; }
        
        .main-content { flex: 1; margin-left: 280px; padding: 30px 40px; }
        
        .header {
            background: white; padding: 25px 30px; border-radius: 15px; margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center;
        }
        .header h1 { font-size: 28px; color: #1a1a1a; }
        
        .btn {
            padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;
            text-decoration: none; display: inline-block; transition: all 0.3s; font-size: 14px;
        }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
        .btn-secondary { background: #e5e7eb; color: #4b5563; }
        
        .alert {
            padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500;
        }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        
        .content-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 25px; }
        
        .panel {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px;
        }
        .panel h2 { font-size: 20px; margin-bottom: 20px; color: #1a1a1a; }
        
        .profile-card {
            text-align: center; padding: 30px;
        }
        .profile-avatar {
            width: 120px; height: 120px; margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 48px; font-weight: 700; color: white;
            border: 5px solid #f0f2f5;
        }
        .profile-name { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        .profile-role {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; padding: 6px 16px; border-radius: 20px;
            font-size: 13px; display: inline-block; margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;
            margin-top: 20px;
        }
        .stat-item {
            padding: 15px; background: #f9fafb; border-radius: 10px; text-align: center;
        }
        .stat-item .value { font-size: 28px; font-weight: 700; color: #667eea; }
        .stat-item .label { font-size: 13px; color: #6b7280; margin-top: 5px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #374151;
        }
        .form-group input, .form-group textarea {
            width: 100%; padding: 12px; border: 2px solid #e5e7eb;
            border-radius: 8px; font-size: 14px; transition: all 0.3s;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none; border-color: #667eea;
        }
        .form-group textarea { min-height: 100px; resize: vertical; }
        
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        
        @media (max-width: 1024px) {
            .content-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
<?php include 'sidebar_student.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <h1>👤 My Profile</h1>
                <a href="dashboard.php" class="btn btn-secondary">← Back</a>
            </header>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="content-grid">
                <div>
                    <div class="panel profile-card">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                        </div>
                        <div class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                        <span class="profile-role">Student</span>
                        
                        <div style="text-align: left; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                            <p style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">
                                <strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?>
                            </p>
                            <p style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">
                                <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?>
                            </p>
                            <p style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">
                                <strong>Phone:</strong> <?php echo htmlspecialchars($user['phone']); ?>
                            </p>
                            <p style="font-size: 13px; color: #6b7280;">
                                <strong>Member Since:</strong> <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                            </p>
                        </div>
                        
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="value"><?php echo $total_courses; ?></div>
                                <div class="label">Total Courses</div>
                            </div>
                            <div class="stat-item">
                                <div class="value"><?php echo $completed_courses; ?></div>
                                <div class="label">Completed</div>
                            </div>
                            <div class="stat-item">
                                <div class="value"><?php echo $total_assignments; ?></div>
                                <div class="label">Assignments</div>
                            </div>
                            <div class="stat-item">
                                <div class="value"><?php echo $completed_assignments; ?></div>
                                <div class="label">Graded</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <div class="panel">
                        <h2>✏️ Edit Profile</h2>
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
                                    <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" readonly style="background: #f9fafb;">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address"><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">💾 Update Profile</button>
                        </form>
                    </div>
                    
                    <div class="panel">
                        <h2>🔒 Change Password</h2>
                        <form method="POST">
                            <div class="form-group">
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
                </div>
            </div>
        </main>
    </div>
</body>
</html>