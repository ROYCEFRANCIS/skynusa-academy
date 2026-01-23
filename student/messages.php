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

// Handle send message
if (isset($_POST['send_message'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $student_id, $receiver_id, $subject, $message);
    
    if ($stmt->execute()) {
        $success = 'Message sent successfully!';
    } else {
        $error = 'Failed to send message!';
    }
}

// Mark as read
if (isset($_GET['read'])) {
    $msg_id = (int)$_GET['read'];
    $conn->query("UPDATE messages SET is_read = 1 WHERE id = $msg_id AND receiver_id = '$student_id'");
}

// Get inbox messages
$inbox = $conn->query("
    SELECT m.*, u.full_name as sender_name, u.role as sender_role
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.receiver_id = '$student_id'
    ORDER BY m.created_at DESC
");

// Get sent messages
$sent = $conn->query("
    SELECT m.*, u.full_name as receiver_name, u.role as receiver_role
    FROM messages m
    JOIN users u ON m.receiver_id = u.id
    WHERE m.sender_id = '$student_id'
    ORDER BY m.created_at DESC
");

// Get instructors for compose
$instructors = $conn->query("
    SELECT DISTINCT u.id, u.full_name, c.course_name
    FROM users u
    JOIN courses c ON u.id = c.instructor_id
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = '$student_id' AND u.role = 'instructor'
    ORDER BY u.full_name
");

// Count unread
$unread_count = $conn->query("SELECT COUNT(*) as count FROM messages WHERE receiver_id = '$student_id' AND is_read = 0")->fetch_assoc()['count'];

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'inbox';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Skynusa Academy</title>
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
        .btn-sm { padding: 8px 16px; font-size: 13px; }
        
        .alert {
            padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500;
        }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        
        .tabs {
            display: flex; gap: 10px; margin-bottom: 20px;
            background: white; padding: 10px; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .tab {
            padding: 12px 24px; border-radius: 8px; cursor: pointer;
            font-weight: 600; transition: all 0.3s; text-decoration: none;
            color: #6b7280;
        }
        .tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .message-container {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .message-list {
            display: grid; gap: 15px;
        }
        
        .message-item {
            padding: 20px; border: 2px solid #e5e7eb; border-radius: 12px;
            cursor: pointer; transition: all 0.3s;
        }
        .message-item:hover { border-color: #667eea; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
        .message-item.unread { background: #f0f9ff; border-color: #3b82f6; }
        
        .message-header {
            display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;
        }
        .message-sender {
            font-weight: 700; font-size: 16px; color: #1a1a1a;
        }
        .message-time {
            font-size: 13px; color: #6b7280;
        }
        .message-subject {
            font-weight: 600; font-size: 15px; color: #374151; margin-bottom: 8px;
        }
        .message-preview {
            color: #6b7280; font-size: 14px; line-height: 1.5;
        }
        
        .compose-form {
            max-width: 800px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #374151;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 12px; border: 2px solid #e5e7eb;
            border-radius: 8px; font-size: 14px;
        }
        .form-group textarea {
            min-height: 150px; resize: vertical;
        }
        
        .badge {
            padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;
        }
        .badge-unread { background: #dbeafe; color: #1e40af; }
        
        .empty-state {
            text-align: center; padding: 60px 20px; color: #9ca3af;
        }
        .empty-state-icon { font-size: 48px; margin-bottom: 15px; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
<?php include 'sidebar_student.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <h1>💬 Messages <?php if ($unread_count > 0): ?><span style="background: #ef4444; color: white; padding: 4px 12px; border-radius: 20px; font-size: 14px; margin-left: 10px;"><?php echo $unread_count; ?></span><?php endif; ?></h1>
                <a href="dashboard.php" class="btn btn-secondary">← Back</a>
            </header>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="tabs">
                <a href="?tab=inbox" class="tab <?php echo $active_tab == 'inbox' ? 'active' : ''; ?>">
                    📥 Inbox <?php if ($unread_count > 0): ?>(<?php echo $unread_count; ?>)<?php endif; ?>
                </a>
                <a href="?tab=sent" class="tab <?php echo $active_tab == 'sent' ? 'active' : ''; ?>">
                    📤 Sent
                </a>
                <a href="?tab=compose" class="tab <?php echo $active_tab == 'compose' ? 'active' : ''; ?>">
                    ✏️ Compose
                </a>
            </div>
            
            <div class="message-container">
                <?php if ($active_tab == 'inbox'): ?>
                    <h2 style="margin-bottom: 20px;">📥 Inbox</h2>
                    <?php if ($inbox->num_rows > 0): ?>
                        <div class="message-list">
                            <?php while ($message = $inbox->fetch_assoc()): ?>
                                <div class="message-item <?php echo !$message['is_read'] ? 'unread' : ''; ?>" 
                                     onclick="window.location.href='?tab=inbox&read=<?php echo $message['id']; ?>'">
                                    <div class="message-header">
                                        <div>
                                            <div class="message-sender">
                                                <?php echo htmlspecialchars($message['sender_name']); ?>
                                                <span style="font-size: 12px; color: #6b7280; font-weight: 400;">
                                                    (<?php echo ucfirst($message['sender_role']); ?>)
                                                </span>
                                            </div>
                                            <?php if (!$message['is_read']): ?>
                                                <span class="badge badge-unread">New</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="message-time">
                                            <?php echo date('d M Y, H:i', strtotime($message['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="message-subject"><?php echo htmlspecialchars($message['subject']); ?></div>
                                    <div class="message-preview">
                                        <?php echo htmlspecialchars(substr($message['message'], 0, 120)); ?>
                                        <?php echo strlen($message['message']) > 120 ? '...' : ''; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📥</div>
                            <p>No messages in inbox</p>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($active_tab == 'sent'): ?>
                    <h2 style="margin-bottom: 20px;">📤 Sent Messages</h2>
                    <?php if ($sent->num_rows > 0): ?>
                        <div class="message-list">
                            <?php while ($message = $sent->fetch_assoc()): ?>
                                <div class="message-item">
                                    <div class="message-header">
                                        <div>
                                            <div class="message-sender">
                                                To: <?php echo htmlspecialchars($message['receiver_name']); ?>
                                                <span style="font-size: 12px; color: #6b7280; font-weight: 400;">
                                                    (<?php echo ucfirst($message['receiver_role']); ?>)
                                                </span>
                                            </div>
                                        </div>
                                        <div class="message-time">
                                            <?php echo date('d M Y, H:i', strtotime($message['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="message-subject"><?php echo htmlspecialchars($message['subject']); ?></div>
                                    <div class="message-preview">
                                        <?php echo htmlspecialchars(substr($message['message'], 0, 120)); ?>
                                        <?php echo strlen($message['message']) > 120 ? '...' : ''; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📤</div>
                            <p>No sent messages</p>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <h2 style="margin-bottom: 20px;">✏️ Compose Message</h2>
                    <form method="POST" class="compose-form">
                        <div class="form-group">
                            <label>To (Instructor) *</label>
                            <select name="receiver_id" required>
                                <option value="">Select Instructor</option>
                                <?php while ($instructor = $instructors->fetch_assoc()): ?>
                                    <option value="<?php echo $instructor['id']; ?>">
                                        <?php echo htmlspecialchars($instructor['full_name']); ?> 
                                        (<?php echo htmlspecialchars($instructor['course_name']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Subject *</label>
                            <input type="text" name="subject" required placeholder="Enter subject">
                        </div>
                        
                        <div class="form-group">
                            <label>Message *</label>
                            <textarea name="message" required placeholder="Type your message here..."></textarea>
                        </div>
                        
                        <button type="submit" name="send_message" class="btn btn-primary">
                            📤 Send Message
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>