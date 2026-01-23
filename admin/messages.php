<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'];

// Handle send message (including broadcast to all)
if (isset($_POST['send_message'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : NULL;
    
    if (empty($subject)) {
        $subject = 'System Announcement';
    }
    
    // Check broadcast type
    if (isset($_POST['broadcast_type']) && $_POST['broadcast_type'] != '') {
        $broadcast_type = $_POST['broadcast_type'];
        
        $users_query = "";
        switch($broadcast_type) {
            case 'all':
                $users_query = "SELECT id FROM users WHERE role != 'admin'";
                break;
            case 'students':
                $users_query = "SELECT id FROM users WHERE role = 'student'";
                break;
            case 'instructors':
                $users_query = "SELECT id FROM users WHERE role = 'instructor'";
                break;
        }
        
        if ($users_query) {
            $users = $conn->query($users_query);
            $sent_count = 0;
            
            while ($user = $users->fetch_assoc()) {
                $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, parent_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iissi", $admin_id, $user['id'], $subject, $message, $parent_id);
                if ($stmt->execute()) $sent_count++;
            }
            
            $_SESSION['success'] = "System broadcast sent to $sent_count users!";
            header("Location: messages.php?tab=sent");
            exit();
        }
    } else {
        // Send to single recipient
        $receiver_id = (int)$_POST['receiver_id'];
        
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, parent_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iissi", $admin_id, $receiver_id, $subject, $message, $parent_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Message sent successfully!';
            header("Location: messages.php?tab=sent");
            exit();
        }
    }
}

// Handle actions
if (isset($_GET['action'])) {
    $msg_id = isset($_GET['msg_id']) ? (int)$_GET['msg_id'] : 0;
    
    switch($_GET['action']) {
        case 'read':
            $conn->query("UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = $msg_id");
            break;
        case 'unread':
            $conn->query("UPDATE messages SET is_read = 0, read_at = NULL WHERE id = $msg_id");
            break;
        case 'star':
            $conn->query("UPDATE messages SET is_starred = NOT is_starred WHERE id = $msg_id");
            break;
        case 'archive':
            $conn->query("UPDATE messages SET is_archived = 1 WHERE id = $msg_id");
            header("Location: messages.php?tab=" . ($_GET['tab'] ?? 'inbox'));
            exit();
    }
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'inbox';
$view_msg_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;

// Count unread
$unread_count = $conn->query("SELECT COUNT(*) as count FROM messages WHERE receiver_id = '$admin_id' AND is_read = 0")->fetch_assoc()['count'];

// Get inbox
$inbox = $conn->query("
    SELECT m.*, u.full_name as sender_name, u.role as sender_role
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.receiver_id = '$admin_id' AND m.parent_id IS NULL
    ORDER BY m.is_starred DESC, m.created_at DESC
");

// Get sent
$sent = $conn->query("
    SELECT m.*, u.full_name as receiver_name, u.role as receiver_role
    FROM messages m
    JOIN users u ON m.receiver_id = u.id
    WHERE m.sender_id = '$admin_id' AND m.parent_id IS NULL
    ORDER BY m.created_at DESC
    LIMIT 100
");

// Get starred
$starred = $conn->query("
    SELECT m.*, 
           IF(m.sender_id = '$admin_id', u2.full_name, u1.full_name) as other_name,
           IF(m.sender_id = '$admin_id', 'sent', 'received') as direction,
           u1.role as sender_role
    FROM messages m
    LEFT JOIN users u1 ON m.sender_id = u1.id
    LEFT JOIN users u2 ON m.receiver_id = u2.id
    WHERE (m.receiver_id = '$admin_id' OR m.sender_id = '$admin_id') 
    AND m.is_starred = 1
    ORDER BY m.created_at DESC
");

// Get all users for individual messaging
$all_users = $conn->query("
    SELECT id, full_name, role, email 
    FROM users 
    WHERE role != 'admin'
    ORDER BY role, full_name
");

// Message statistics
$stats = [
    'total_messages' => $conn->query("SELECT COUNT(*) as count FROM messages")->fetch_assoc()['count'],
    'today_messages' => $conn->query("SELECT COUNT(*) as count FROM messages WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'],
    'unread_system' => $conn->query("SELECT COUNT(*) as count FROM messages WHERE is_read = 0")->fetch_assoc()['count']
];

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
    $stmt->bind_param("iii", $view_msg_id, $admin_id, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $viewed_message = $result->fetch_assoc();
    
    if ($viewed_message) {
        if ($viewed_message['receiver_id'] == $admin_id && !$viewed_message['is_read']) {
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
    <title>Admin Messages - System Broadcast</title>
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
            background: linear-gradient(180deg, #dc2626 0%, #991b1b 100%);
            color: white;
            display: flex;
            flex-direction: column;
        }
        .sidebar-header { padding: 25px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 22px; font-weight: 700; }
        .user-info { font-size: 13px; color: rgba(255,255,255,0.7); margin-top: 8px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            padding: 15px 20px;
            background: rgba(0,0,0,0.1);
            margin: 15px 20px;
            border-radius: 10px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 24px;
            font-weight: 700;
            display: block;
        }
        .stat-label {
            font-size: 11px;
            opacity: 0.8;
            text-transform: uppercase;
        }
        
        .compose-btn {
            margin: 0 20px 20px;
            padding: 14px;
            background: white;
            color: #dc2626;
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
        .message-item.unread { background: #fef2f2; font-weight: 600; }
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
        
        .broadcast-mega {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 5px solid #f59e0b;
        }
        .broadcast-mega h3 {
            font-size: 20px;
            color: #78350f;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .broadcast-mega p {
            font-size: 14px;
            color: #92400e;
            margin-bottom: 20px;
        }
        
        .broadcast-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .broadcast-option {
            padding: 20px;
            background: white;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s;
        }
        .broadcast-option:hover {
            border-color: #f59e0b;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .broadcast-option.selected {
            border-color: #f59e0b;
            background: #fffbeb;
        }
        .broadcast-option-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        .broadcast-option-title {
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 5px;
        }
        .broadcast-option-desc {
            font-size: 12px;
            color: #6b7280;
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
        .form-group textarea { min-height: 200px; resize: vertical; }
        
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
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(220,38,38,0.4); }
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
    <script>
        function selectBroadcast(type) {
            document.querySelectorAll('.broadcast-option').forEach(el => {
                el.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            document.getElementById('broadcast_type').value = type;
            document.getElementById('individual_section').style.display = 'none';
            document.getElementById('broadcast_form').style.display = 'block';
        }
        
        function showIndividual() {
            document.querySelectorAll('.broadcast-option').forEach(el => {
                el.classList.remove('selected');
            });
            document.getElementById('broadcast_type').value = '';
            document.getElementById('broadcast_form').style.display = 'none';
            document.getElementById('individual_section').style.display = 'block';
        }
    </script>
</head>
<body>
    <div class="messages-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>🔴 Admin Messages</h2>
                <div class="user-info">System Administrator: <?php echo htmlspecialchars($admin_name); ?></div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['total_messages']; ?></span>
                    <span class="stat-label">Total</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['today_messages']; ?></span>
                    <span class="stat-label">Today</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['unread_system']; ?></span>
                    <span class="stat-label">Unread</span>
                </div>
            </div>
            
            <button class="compose-btn" onclick="window.location.href='?tab=compose'">
                <i class="fas fa-bullhorn"></i> System Broadcast
            </button>
            
            <ul class="nav-menu">
                <li><a href="?tab=inbox" class="nav-link <?php echo $active_tab == 'inbox' ? 'active' : ''; ?>">
                    <i class="fas fa-inbox"></i> Inbox
                    <?php if ($unread_count > 0): ?><span class="nav-badge"><?php echo $unread_count; ?></span><?php endif; ?>
                </a></li>
                <li><a href="?tab=sent" class="nav-link <?php echo $active_tab == 'sent' ? 'active' : ''; ?>">
                    <i class="fas fa-paper-plane"></i> Sent Broadcasts
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
                    
                    <div class="broadcast-mega">
                        <h3><i class="fas fa-megaphone"></i> System Broadcast Center</h3>
                        <p>Send announcements and important messages to multiple users at once</p>
                        
                        <div class="broadcast-options">
                            <div class="broadcast-option" onclick="selectBroadcast('all')">
                                <div class="broadcast-option-icon">🌐</div>
                                <div class="broadcast-option-title">All Users</div>
                                <div class="broadcast-option-desc">Students & Instructors</div>
                            </div>
                            <div class="broadcast-option" onclick="selectBroadcast('students')">
                                <div class="broadcast-option-icon">🎓</div>
                                <div class="broadcast-option-title">All Students</div>
                                <div class="broadcast-option-desc">Students only</div>
                            </div>
                            <div class="broadcast-option" onclick="selectBroadcast('instructors')">
                                <div class="broadcast-option-icon">👨‍🏫</div>
                                <div class="broadcast-option-title">All Instructors</div>
                                <div class="broadcast-option-desc">Instructors only</div>
                            </div>
                        </div>
                        
                        <div id="broadcast_form" style="display: none;">
                            <form method="POST">
                                <input type="hidden" name="broadcast_type" id="broadcast_type" value="">
                                <div class="form-group">
                                    <label>📢 Broadcast Subject *</label>
                                    <input type="text" name="subject" required placeholder="e.g., Important System Announcement">
                                </div>
                                <div class="form-group">
                                    <label>💬 Broadcast Message *</label>
                                    <textarea name="message" required placeholder="Type your announcement here..."></textarea>
                                </div>
                                <button type="submit" name="send_message" class="btn btn-primary">
                                    <i class="fas fa-bullhorn"></i> Send System Broadcast
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <span style="color: #9ca3af; font-weight: 600;">OR</span>
                    </div>
                    
                    <div style="text-align: center; margin-bottom: 20px;">
                        <button onclick="showIndividual()" class="btn btn-secondary" style="padding: 12px 30px;">
                            📧 Send Individual Message
                        </button>
                    </div>
                    
                    <div id="individual_section" style="display: none;">
                        <h3 style="margin: 20px 0; font-size: 18px;">📩 Individual Message</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label>To (User) *</label>
                                <select name="receiver_id" required>
                                    <option value="">-- Select User --</option>
                                    <optgroup label="👨‍🏫 Instructors">
                                        <?php 
                                        $all_users->data_seek(0);
                                        while ($user = $all_users->fetch_assoc()): 
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
                                    <optgroup label="🎓 Students">
                                        <?php 
                                        $all_users->data_seek(0);
                                        while ($user = $all_users->fetch_assoc()): 
                                            if ($user['role'] == 'student'):
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
                        <div class="message-item <?php echo (!$msg['is_read'] && $active_tab == 'inbox') ? 'unread' : ''; ?><?php echo ($msg['id'] == $view_msg_id) ? ' active' : ''; ?>"
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
                        <?php if ($viewed_message['receiver_id'] == $admin_id): ?>
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
</body>
</html>