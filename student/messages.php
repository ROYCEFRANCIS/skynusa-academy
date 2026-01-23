<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

// Handle send message
if (isset($_POST['send_message'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : NULL;
    
    if (empty($subject)) {
        $subject = 'No Subject';
    }
    
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, parent_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iissi", $student_id, $receiver_id, $subject, $message, $parent_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Message sent successfully!';
        header("Location: messages.php?tab=sent");
        exit();
    }
}

// Handle actions
if (isset($_GET['action'])) {
    $msg_id = isset($_GET['msg_id']) ? (int)$_GET['msg_id'] : 0;
    
    switch($_GET['action']) {
        case 'read':
            $conn->query("UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = $msg_id AND receiver_id = '$student_id'");
            break;
        case 'unread':
            $conn->query("UPDATE messages SET is_read = 0, read_at = NULL WHERE id = $msg_id AND receiver_id = '$student_id'");
            break;
        case 'star':
            $conn->query("UPDATE messages SET is_starred = NOT is_starred WHERE id = $msg_id AND (receiver_id = '$student_id' OR sender_id = '$student_id')");
            break;
        case 'archive':
            $conn->query("UPDATE messages SET is_archived = 1 WHERE id = $msg_id AND (receiver_id = '$student_id' OR sender_id = '$student_id')");
            header("Location: messages.php?tab=" . ($_GET['tab'] ?? 'inbox'));
            exit();
    }
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'inbox';
$view_msg_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;

// Get inbox messages
$inbox = $conn->query("
    SELECT m.*, u.full_name as sender_name, u.role as sender_role,
           (SELECT COUNT(*) FROM messages WHERE parent_id = m.id) as reply_count
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.receiver_id = '$student_id' AND m.is_archived = 0 AND m.parent_id IS NULL
    ORDER BY m.is_starred DESC, m.created_at DESC
");

// Get sent messages
$sent = $conn->query("
    SELECT m.*, u.full_name as receiver_name, u.role as receiver_role,
           (SELECT COUNT(*) FROM messages WHERE parent_id = m.id) as reply_count
    FROM messages m
    JOIN users u ON m.receiver_id = u.id
    WHERE m.sender_id = '$student_id' AND m.is_archived = 0 AND m.parent_id IS NULL
    ORDER BY m.created_at DESC
");

// Get starred messages
$starred = $conn->query("
    SELECT m.*, 
           IF(m.sender_id = '$student_id', u2.full_name, u1.full_name) as other_name,
           IF(m.sender_id = '$student_id', 'sent', 'received') as direction,
           u1.role as sender_role
    FROM messages m
    LEFT JOIN users u1 ON m.sender_id = u1.id
    LEFT JOIN users u2 ON m.receiver_id = u2.id
    WHERE (m.receiver_id = '$student_id' OR m.sender_id = '$student_id') 
    AND m.is_starred = 1 AND m.is_archived = 0
    ORDER BY m.created_at DESC
");

// Get instructors for compose
$instructors = $conn->query("
    SELECT DISTINCT u.id, u.full_name, c.course_name, c.course_code
    FROM users u
    JOIN courses c ON u.id = c.instructor_id
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = '$student_id' AND u.role = 'instructor'
    ORDER BY u.full_name
");

// Get admins for compose
$admins = $conn->query("
    SELECT id, full_name FROM users WHERE role = 'admin' ORDER BY full_name
");

// Count unread
$unread_count = $conn->query("SELECT COUNT(*) as count FROM messages WHERE receiver_id = '$student_id' AND is_read = 0 AND is_archived = 0")->fetch_assoc()['count'];

// Get viewed message with thread
$viewed_message = null;
$message_thread = [];
if ($view_msg_id > 0) {
    $stmt = $conn->prepare("
        SELECT m.*, u.full_name as sender_name, u.role as sender_role,
               u2.full_name as receiver_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        JOIN users u2 ON m.receiver_id = u2.id
        WHERE m.id = ? AND (m.receiver_id = ? OR m.sender_id = ?)
    ");
    $stmt->bind_param("iii", $view_msg_id, $student_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $viewed_message = $result->fetch_assoc();
    
    if ($viewed_message) {
        // Mark as read
        if ($viewed_message['receiver_id'] == $student_id && !$viewed_message['is_read']) {
            $conn->query("UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = $view_msg_id");
        }
        
        // Get thread (replies)
        $thread_query = $conn->query("
            SELECT m.*, u.full_name as sender_name, u.role as sender_role
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.parent_id = $view_msg_id
            ORDER BY m.created_at ASC
        ");
        while ($row = $thread_query->fetch_assoc()) {
            $message_thread[] = $row;
        }
    }
}

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Messages - Skynusa Academy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f0f2f5;
        }
        .messages-container { display: flex; height: 100vh; max-width: 1600px; margin: 0 auto; background: white; }
        
        .sidebar {
            width: 300px;
            background: linear-gradient(180deg, #1e3a8a 0%, #312e81 100%);
            color: white;
            display: flex;
            flex-direction: column;
        }
        .sidebar-header { padding: 25px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 22px; font-weight: 700; }
        .user-info { font-size: 13px; color: rgba(255,255,255,0.7); margin-top: 10px; }
        
        .compose-btn {
            margin: 20px;
            padding: 14px;
            background: white;
            color: #1e3a8a;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            font-size: 15px;
            transition: all 0.3s;
        }
        .compose-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        
        .nav-menu { list-style: none; padding: 0 10px; }
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 8px;
            margin: 5px 0;
            transition: all 0.3s;
        }
        .nav-link:hover { background: rgba(255,255,255,0.1); }
        .nav-link.active { background: rgba(255,255,255,0.15); font-weight: 600; }
        .nav-link i { width: 24px; margin-right: 12px; }
        .nav-badge {
            margin-left: auto;
            background: #ef4444;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
        }
        
        .message-list-container {
            width: 400px;
            border-right: 1px solid #e5e7eb;
            background: #fafafa;
        }
        .list-header { padding: 20px; background: white; border-bottom: 1px solid #e5e7eb; }
        .list-header h3 { font-size: 20px; font-weight: 700; }
        
        .messages-list { overflow-y: auto; height: calc(100vh - 100px); }
        .message-item {
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
            cursor: pointer;
            background: white;
            transition: all 0.2s;
        }
        .message-item:hover { background: #f9fafb; }
        .message-item.unread { background: #eff6ff; font-weight: 600; }
        .message-sender { font-size: 14px; font-weight: 600; }
        .message-time { font-size: 12px; color: #6b7280; }
        .message-subject { font-size: 13px; color: #374151; margin: 6px 0; }
        .message-preview { font-size: 13px; color: #9ca3af; }
        
        .message-view-container { flex: 1; display: flex; flex-direction: column; }
        .compose-container { padding: 30px; max-width: 900px; overflow-y: auto; }
        
        .message-view {
            padding: 30px;
            overflow-y: auto;
        }
        
        .message-header-view {
            background: #f9fafb;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .message-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .message-body {
            background: white;
            padding: 25px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            line-height: 1.6;
        }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group textarea { min-height: 200px; resize: vertical; }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #1e3a8a, #312e81);
            color: white;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(30,58,138,0.4); }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .btn-sm { padding: 8px 16px; font-size: 13px; }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        
        .empty-state { text-align: center; padding: 80px 20px; color: #9ca3af; }
        .empty-icon { font-size: 64px; margin-bottom: 20px; opacity: 0.5; }
    </style>
</head>
<body>
    <div class="messages-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>💬 Messages</h2>
                <div class="user-info">Student: <?php echo htmlspecialchars($student_name); ?></div>
            </div>
            
            <button class="compose-btn" onclick="window.location.href='?tab=compose'">
                <i class="fas fa-pen"></i> Compose Message
            </button>
            
            <ul class="nav-menu">
                <li><a href="?tab=inbox" class="nav-link <?php echo $active_tab == 'inbox' ? 'active' : ''; ?>">
                    <i class="fas fa-inbox"></i> Inbox
                    <?php if ($unread_count > 0): ?><span class="nav-badge"><?php echo $unread_count; ?></span><?php endif; ?>
                </a></li>
                <li><a href="?tab=sent" class="nav-link <?php echo $active_tab == 'sent' ? 'active' : ''; ?>">
                    <i class="fas fa-paper-plane"></i> Sent
                </a></li>
                <li><a href="?tab=starred" class="nav-link <?php echo $active_tab == 'starred' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i> Starred
                </a></li>
                <li style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </li>
            </ul>
        </aside>
        
        <?php if ($active_tab == 'compose'): ?>
            <div class="message-view-container">
                <div class="compose-container">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <h2 style="margin-bottom: 25px;">✏️ Compose Message</h2>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label>To *</label>
                            <select name="receiver_id" required>
                                <option value="">-- Select Recipient --</option>
                                <?php if ($instructors->num_rows > 0): ?>
                                    <optgroup label="👨‍🏫 My Instructors">
                                        <?php while ($instructor = $instructors->fetch_assoc()): ?>
                                            <option value="<?php echo $instructor['id']; ?>">
                                                <?php echo htmlspecialchars($instructor['full_name']); ?> 
                                                (<?php echo htmlspecialchars($instructor['course_name']); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if ($admins->num_rows > 0): ?>
                                    <optgroup label="🔴 Administrators">
                                        <?php while ($admin = $admins->fetch_assoc()): ?>
                                            <option value="<?php echo $admin['id']; ?>">
                                                <?php echo htmlspecialchars($admin['full_name']); ?> (Admin)
                                            </option>
                                        <?php endwhile; ?>
                                    </optgroup>
                                <?php endif; ?>
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
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>
        <?php elseif ($view_msg_id > 0 && $viewed_message): ?>
            <div class="message-list-container">
                <div class="list-header">
                    <h3><?php echo $active_tab == 'inbox' ? '📥 Inbox' : ($active_tab == 'sent' ? '📤 Sent' : '⭐ Starred'); ?></h3>
                </div>
                <div class="messages-list">
                    <?php 
                    $messages = $active_tab == 'inbox' ? $inbox : ($active_tab == 'sent' ? $sent : $starred);
                    mysqli_data_seek($messages, 0);
                    while ($msg = $messages->fetch_assoc()): 
                    ?>
                        <div class="message-item <?php echo (!$msg['is_read'] && $active_tab == 'inbox') ? 'unread' : ''; ?>"
                             onclick="window.location.href='?tab=<?php echo $active_tab; ?>&view=<?php echo $msg['id']; ?>'">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                <div class="message-sender">
                                    <?php 
                                    if ($active_tab == 'inbox') {
                                        echo htmlspecialchars($msg['sender_name']);
                                    } elseif ($active_tab == 'sent') {
                                        echo 'To: ' . htmlspecialchars($msg['receiver_name']);
                                    } else {
                                        echo htmlspecialchars($msg['other_name']);
                                    }
                                    ?>
                                </div>
                                <div class="message-time"><?php echo date('d M, H:i', strtotime($msg['created_at'])); ?></div>
                            </div>
                            <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <div class="message-view-container">
                <div class="message-view">
                    <div class="message-actions">
                        <a href="?tab=<?php echo $active_tab; ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <a href="?tab=<?php echo $active_tab; ?>&action=star&msg_id=<?php echo $viewed_message['id']; ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-star"></i> <?php echo $viewed_message['is_starred'] ? 'Unstar' : 'Star'; ?>
                        </a>
                        <?php if ($viewed_message['receiver_id'] == $student_id): ?>
                        <a href="?tab=<?php echo $active_tab; ?>&action=<?php echo $viewed_message['is_read'] ? 'unread' : 'read'; ?>&msg_id=<?php echo $viewed_message['id']; ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-envelope"></i> Mark as <?php echo $viewed_message['is_read'] ? 'Unread' : 'Read'; ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="message-header-view">
                        <h2 style="margin-bottom: 15px;"><?php echo htmlspecialchars($viewed_message['subject']); ?></h2>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>From:</strong> <?php echo htmlspecialchars($viewed_message['sender_name']); ?> (<?php echo ucfirst($viewed_message['sender_role']); ?>)<br>
                                <strong>To:</strong> <?php echo htmlspecialchars($viewed_message['receiver_name']); ?>
                            </div>
                            <div style="text-align: right; color: #6b7280;">
                                <?php echo date('d M Y, H:i', strtotime($viewed_message['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="message-body">
                        <?php echo nl2br(htmlspecialchars($viewed_message['message'])); ?>
                    </div>
                    
                    <?php if (count($message_thread) > 0): ?>
                        <h3 style="margin: 30px 0 15px;">Replies (<?php echo count($message_thread); ?>)</h3>
                        <?php foreach ($message_thread as $reply): ?>
                            <div class="message-body" style="margin-bottom: 15px; background: #f9fafb;">
                                <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #e5e7eb;">
                                    <strong><?php echo htmlspecialchars($reply['sender_name']); ?></strong>
                                    <span style="color: #6b7280; font-size: 13px;">
                                        - <?php echo date('d M Y, H:i', strtotime($reply['created_at'])); ?>
                                    </span>
                                </div>
                                <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="message-list-container">
                <div class="list-header">
                    <h3><?php echo $active_tab == 'inbox' ? '📥 Inbox' : ($active_tab == 'sent' ? '📤 Sent' : '⭐ Starred'); ?></h3>
                </div>
                <div class="messages-list">
                    <?php 
                    $messages = $active_tab == 'inbox' ? $inbox : ($active_tab == 'sent' ? $sent : $starred);
                    if ($messages->num_rows > 0): 
                        while ($msg = $messages->fetch_assoc()): 
                    ?>
                        <div class="message-item <?php echo (!$msg['is_read'] && $active_tab == 'inbox') ? 'unread' : ''; ?>"
                             onclick="window.location.href='?tab=<?php echo $active_tab; ?>&view=<?php echo $msg['id']; ?>'">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                <div class="message-sender">
                                    <?php 
                                    if ($active_tab == 'inbox') {
                                        echo htmlspecialchars($msg['sender_name']);
                                        echo ' <span style="font-size: 12px; color: #6b7280; font-weight: 400;">(' . ucfirst($msg['sender_role']) . ')</span>';
                                    } elseif ($active_tab == 'sent') {
                                        echo 'To: ' . htmlspecialchars($msg['receiver_name']);
                                        echo ' <span style="font-size: 12px; color: #6b7280; font-weight: 400;">(' . ucfirst($msg['receiver_role']) . ')</span>';
                                    } else {
                                        echo htmlspecialchars($msg['other_name']);
                                    }
                                    ?>
                                </div>
                                <div class="message-time"><?php echo date('d M, H:i', strtotime($msg['created_at'])); ?></div>
                            </div>
                            <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                            <div class="message-preview">
                                <?php echo htmlspecialchars(substr($msg['message'], 0, 100)); ?>
                                <?php echo strlen($msg['message']) > 100 ? '...' : ''; ?>
                            </div>
                        </div>
                    <?php 
                        endwhile;
                    else: 
                    ?>
                        <div class="empty-state">
                            <div class="empty-icon">📭</div>
                            <p>No messages found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="message-view-container">
                <div class="empty-state">
                    <div class="empty-icon">✉️</div>
                    <p>Select a message to view</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>