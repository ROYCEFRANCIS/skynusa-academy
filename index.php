<?php
session_start();
require_once 'config/database.php';

// Jika sudah login, redirect ke dashboard sesuai role
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    header("Location: $role/dashboard.php");
    exit();
}

$error = '';

// Proses login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = escape($_POST['username']);
    $password = md5($_POST['password']);
    
    $sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    $result = query($sql);
    
    if (mysqli_num_rows($result) == 1) {
        $user = fetch_one($result);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        
        header("Location: " . $user['role'] . "/dashboard.php");
        exit();
    } else {
        $error = 'Username atau password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Skynusa Academy</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <img src="assets/img/logo.png" alt="Skynusa Tech" class="logo" onerror="this.style.display='none'">
                <h1>Skynusa Academy</h1>
                <p>Sistem Informasi Akademi</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
            
            <div class="login-footer">
                <p>Demo Account:</p>
                <small>Admin: admin / admin123</small><br>
                <small>Instructor: instructor1 / instructor123</small><br>
                <small>Student: student1 / student123</small>
            </div>
        </div>
    </div>
</body>
</html>