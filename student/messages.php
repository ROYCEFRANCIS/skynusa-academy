<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

// Handle send message (including replies)
if (isset($_POST['send_message'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : NULL;
    
    if (empty($subject)) {
        $subject = 'Re: ' . ($_POST['original_subject'] ?? 'No Subject');
    }
    
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, parent_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iissi", $student_id, $receiver_id, $subject, $message, $parent_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Message sent successfully!';
        if ($parent_id) {
            header("Location: messages.php?tab=" . ($_GET['tab'] ?? 'inbox') . "&view=" . $parent_id);
        } else {
            header("Location: messages.php?tab=sent");
        }
        exit();
    }
}

// Handle delete message
if (isset($_POST['delete_message'])) {
    $msg_id = (int)$_POST['msg_id'];
    
    // Delete the message and all its replies (only if sender or receiver)
    $conn->query("DELETE FROM messages WHERE id = $msg_id AND (sender_id = '$student_id' OR receiver_id = '$student_id')");
    $conn->query("DELETE FROM messages WHERE parent_id = $msg_id AND (sender_id = '$student_id' OR receiver_id = '$student_id')");
    
    $_SESSION['success'] = 'Message deleted successfully!';
    header("Location: messages.php?tab=" . ($_GET['tab'] ?? 'inbox'));
    exit();
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
            $conn->query("UPDATE messages SET is_starred = NOT is_starred WHERE id = $msg_id AND (sender_id = '$student_id' OR receiver_id = '$student_id')");
            break;
        case 'archive':
            $conn->query("UPDATE messages SET is_archived = 1 WHERE id = $msg_id AND (sender_id = '$student_id' OR receiver_id = '$student_id')");
            header("Location: messages.php?tab=" . ($_GET['tab'] ?? 'inbox'));
            exit();
    }
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'inbox';
$view_msg_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;

// Count unread
$unread_count = $conn->query("SELECT COUNT(*) as count FROM messages WHERE receiver_id = '$student_id' AND is_read = 0")->fetch_assoc()['count'];

// Get inbox
$inbox = $conn->query("
    SELECT m.*, u.full_name as sender_name, u.role as sender_role
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.receiver_id = '$student_id' AND m.parent_id IS NULL AND m.is_archived = 0
    ORDER BY m.is_starred DESC, m.created_at DESC
");

// Get sent
$sent = $conn->query("
    SELECT m.*, u.full_name as receiver_name, u.role as receiver_role
    FROM messages m
    JOIN users u ON m.receiver_id = u.id
    WHERE m.sender_id = '$student_id' AND m.parent_id IS NULL
    ORDER BY m.created_at DESC
");

// Get starred
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

// Get admins and instructors for compose
$recipients = $conn->query("
    SELECT id, full_name, role, email 
    FROM users 
    WHERE role IN ('admin', 'instructor')
    ORDER BY role DESC, full_name
");

// Get viewed message with thread
$viewed_message = null;
$message_thread = [];
if ($view_msg_id > 0) {
    $stmt = $conn->prepare("
        SELECT m.*, u.full_name as sender_name, u.role as sender_role,
               u2.full_name as receiver_name, u2.id as receiver_id
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
        if ($viewed_message['receiver_id'] == $student_id && !$viewed_message['is_read']) {
            $conn->query("UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = $view_msg_id");
        }
        
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
    <title>Student Messages</title>
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
            background: linear-gradient(180deg, #3b82f6 0%, #1e40af 100%);
            color: white;
            display: flex;
            flex-direction: column;
        }
        .sidebar-header { padding: 25px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 22px; font-weight: 700; }
        .user-info { font-size: 13px; color: rgba(255,255,255,0.7); margin-top: 8px; }
        
        .compose-btn {
            margin: 20px 20px;
            padding: 14px;
            background: white;
            color: #3b82f6;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            font-size: 15px;
            transition: all 0.3s;
        }
        .compose-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        
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
            background: #fbbf24;
            color: #78350f;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
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
        .message-item.active { background: #dbeafe; border-left: 4px solid #3b82f6; }
        .message-sender { font-size: 14px; font-weight: 600; }
        .message-time { font-size: 12px; color: #6b7280; }
        .message-subject { font-size: 13px; color: #374151; margin: 6px 0; }
        
        .message-view-container { flex: 1; display: flex; flex-direction: column; }
        .compose-container { padding: 30px; max-width: 1000px; overflow-y: auto; }
        
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
        
        .reply-container {
            margin-top: 20px;
            padding: 20px;
            background: #f0f9ff;
            border-radius: 10px;
            border-left: 4px solid #3b82f6;
        }
        
        .reply-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .reply-toggle {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .reply-toggle:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }
        
        .reply-form {
            display: none;
        }
        
        .reply-form.active {
            display: block;
        }
        
        .thread-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 3px solid #e5e7eb;
        }
        
        .thread-item.own {
            border-left-color: #3b82f6;
            background: #f0f9ff;
        }
        
        .thread-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        .form-group textarea { min-height: 150px; resize: vertical; }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1e40af);
            color: white;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(59,130,246,0.4); }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
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
        
        .delete-confirm {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .delete-confirm.active {
            display: flex;
        }
        
        .delete-modal {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 400px;
            text-align: center;
        }
        
        .delete-modal h3 {
            margin-bottom: 15px;
            color: #ef4444;
        }
        
        .delete-modal p {
            margin-bottom: 20px;
            color: #6b7280;
        }
        
        .delete-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
    </style>
    <script>
        function toggleReply() {
            const form = document.getElementById('reply-form');
            form.classList.toggle('active');
            if (form.classList.contains('active')) {
                document.getElementById('reply-message').focus();
            }
        }
        
        let deleteMessageId = null;
        
        function confirmDelete(msgId) {
            deleteMessageId = msgId;
            document.getElementById('delete-confirm').classList.add('active');
        }
        
        function cancelDelete() {
            deleteMessageId = null;
            document.getElementById('delete-confirm').classList.remove('active');
        }
        
        function executeDelete() {
            if (deleteMessageId) {
                document.getElementById('delete-form-id').value = deleteMessageId;
                document.getElementById('delete-form').submit();
            }
        }
    </script>
</head>
<body>
    <div class="messages-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>🎓 Student Messages</h2>
                <div class="user-info">Student: <?php echo htmlspecialchars($student_name); ?></div>
            </div>
            
            <button class="compose-btn" onclick="window.location.href='?tab=compose'">
                <i class="fas fa-pen"></i> New Message
            </button>
            
            <ul class="nav-menu">
                <li><a href="?tab=inbox" class="nav-link <?php echo $active_tab == 'inbox' ? 'active' : ''; ?>">
                    <i class="fas fa-inbox"></i> Inbox
                    <?php if ($unread_count > 0): ?><span class="nav-badge"><?php echo $unread_count; ?></span><?php endif; ?>
                </a></li>
                <li><a href="?tab=sent" class="nav-link <?php echo $active_tab == 'sent' ? 'active' : ''; ?>">
                    <i class="fas fa-paper-plane"></i> Sent Messages
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
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <h2 style="margin-bottom: 25px; font-size: 24px;">
                        <i class="fas fa-pen"></i> Compose New Message
                    </h2>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label>To (Admin or Instructor) *</label>
                            <select name="receiver_id" required>
                                <option value="">-- Select Recipient --</option>
                                <optgroup label="🔴 Admin">
                                    <?php 
                                    $recipients->data_seek(0);
                                    while ($user = $recipients->fetch_assoc()): 
                                        if ($user['role'] == 'admin'):
                                    ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                        </option>
                                    <?php 
                                        endif;
                                    endwhile; 
                                    ?>
                                </optgroup>
                                <optgroup label="👨‍🏫 Instructors">
                                    <?php 
                                    $recipients->data_seek(0);
                                    while ($user = $recipients->fetch_assoc()): 
                                        if ($user['role'] == 'instructor'):
                                    ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                        </option>
                                    <?php 
                                        endif;
                                    endwhile; 
                                    ?>
                                </optgroup>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Subject *</label>
                            <input type="text" name="subject" required placeholder="Enter subject">
                        </div>
                        <div class="form-group">
                            <label>Message *</label>
                            <textarea name="message" required placeholder="Type your message..."></textarea>
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
                        <div class="message-item <?php echo (!$msg['is_read'] && $active_tab == 'inbox') ? 'unread' : ''; ?> <?php echo ($msg['id'] == $view_msg_id) ? 'active' : ''; ?>"
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
                        <button onclick="confirmDelete(<?php echo $viewed_message['id']; ?>)" class="btn btn-danger btn-sm">
                            <i class="fas fa-trash"></i> Delete
                        </button>
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
                    
                    <!-- Quick Reply -->
                    <div class="reply-container">
                        <div class="reply-header">
                            <h3 style="font-size: 16px; color: #1e40af;">
                                <i class="fas fa-reply"></i> Quick Reply
                            </h3>
                            <button onclick="toggleReply()" class="reply-toggle">
                                <i class="fas fa-pen"></i> Write Reply
                            </button>
                        </div>
                        
                        <form method="POST" id="reply-form" class="reply-form">
                            <input type="hidden" name="receiver_id" value="<?php echo $viewed_message['sender_id']; ?>">
                            <input type="hidden" name="parent_id" value="<?php echo $viewed_message['id']; ?>">
                            <input type="hidden" name="original_subject" value="<?php echo htmlspecialchars($viewed_message['subject']); ?>">
                            <input type="hidden" name="subject" value="Re: <?php echo htmlspecialchars($viewed_message['subject']); ?>">
                            
                            <div class="form-group">
                                <label>Your Reply *</label>
                                <textarea name="message" id="reply-message" required placeholder="Type your reply here..." style="min-height: 120px;"></textarea>
                            </div>
                            
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" name="send_message" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Send Reply
                                </button>
                                <button type="button" onclick="toggleReply()" class="btn btn-secondary">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <?php if (count($message_thread) > 0): ?>
                        <h3 style="margin: 30px 0 15px; font-size: 18px; color: #374151;">
                            <i class="fas fa-comments"></i> Conversation (<?php echo count($message_thread); ?>)
                        </h3>
                        <?php foreach ($message_thread as $reply): ?>
                            <div class="thread-item <?php echo $reply['sender_id'] == $student_id ? 'own' : ''; ?>">
                                <div class="thread-header">
                                    <div>
                                        <strong><?php echo htmlspecialchars($reply['sender_name']); ?></strong>
                                        <span style="color: #6b7280; font-size: 13px; margin-left: 8px;">
                                            (<?php echo ucfirst($reply['sender_role']); ?>)
                                        </span>
                                    </div>
                                    <span style="color: #6b7280; font-size: 13px;">
                                        <?php echo date('d M Y, H:i', strtotime($reply['created_at'])); ?>
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
                    <?php 
                        endwhile;
                    else: 
                    ?>
                        <div class="empty-state">
                            <div class="empty-icon">📭</div>
                            <p>No messages</p>
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
    
    <!-- Delete Confirmation Modal -->
    <div id="delete-confirm" class="delete-confirm">
        <div class="delete-modal">
            <h3><i class="fas fa-exclamation-triangle"></i> Delete Message?</h3>
            <p>Are you sure you want to delete this message and all its replies? This action cannot be undone.</p>
            <div class="delete-actions">
                <button onclick="cancelDelete()" class="btn btn-secondary">Cancel</button>
                <button onclick="executeDelete()" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
    
    <!-- Hidden delete form -->
    <form id="delete-form" method="POST" style="display: none;">
        <input type="hidden" name="msg_id" id="delete-form-id">
        <input type="hidden" name="delete_message" value="1">
    </form>
</body>
</html>