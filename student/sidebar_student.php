<?php
// Student Sidebar Component
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$student_name = $_SESSION['full_name'] ?? 'Student';
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <h2>🎓 SKYNUSA</h2>
            <p>Student Portal</p>
        </div>
    </div>
    
    <div class="student-profile">
        <div class="avatar"><?php echo strtoupper(substr($student_name, 0, 1)); ?></div>
        <div class="profile-info">
            <h3><?php echo htmlspecialchars($student_name); ?></h3>
            <p>Student</p>
        </div>
    </div>
    
    <nav class="nav-menu">
        <div class="nav-section">
            <div class="nav-section-title">Main Menu</div>
            <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="my_courses.php" class="nav-item <?php echo $current_page == 'my_courses' || $current_page == 'course_detail' ? 'active' : ''; ?>">
                <i class="fas fa-book"></i>
                <span>My Courses</span>
            </a>
            <a href="browse_courses.php" class="nav-item <?php echo $current_page == 'browse_courses' ? 'active' : ''; ?>">
                <i class="fas fa-search"></i>
                <span>Browse Courses</span>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Learning</div>
            <a href="schedule.php" class="nav-item <?php echo $current_page == 'schedule' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Schedule</span>
            </a>
            <a href="assignments.php" class="nav-item <?php echo $current_page == 'assignments' ? 'active' : ''; ?>">
                <i class="fas fa-tasks"></i>
                <span>Assignments</span>
            </a>
            <a href="materials.php" class="nav-item <?php echo $current_page == 'materials' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                <span>Materials</span>
            </a>
            <a href="grades.php" class="nav-item <?php echo $current_page == 'grades' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i>
                <span>Grades</span>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Communication</div>
            <a href="messages.php" class="nav-item <?php echo $current_page == 'messages' ? 'active' : ''; ?>">
                <i class="fas fa-comment-alt"></i>
                <span>Messages</span>
                <?php
                // Check for unread messages
                if (isset($_SESSION['user_id'])) {
                    $student_id = $_SESSION['user_id'];
                    $unread_query = $conn->query("SELECT COUNT(*) as count FROM messages WHERE receiver_id = '$student_id' AND is_read = 0");
                    $unread_data = $unread_query->fetch_assoc();
                    if ($unread_data['count'] > 0) {
                        echo '<span class="badge-notification">' . $unread_data['count'] . '</span>';
                    }
                }
                ?>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Account</div>
            <a href="certificates.php" class="nav-item <?php echo $current_page == 'certificates' ? 'active' : ''; ?>">
                <i class="fas fa-certificate"></i>
                <span>Certificates</span>
            </a>
            <a href="profile.php" class="nav-item <?php echo $current_page == 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </div>
        
        <div class="nav-section nav-footer">
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>
</aside>

<style>
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    width: 280px;
    background: linear-gradient(180deg, #1e3a8a 0%, #312e81 100%);
    color: white;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 4px 0 20px rgba(0,0,0,0.1);
}

.sidebar-header {
    padding: 25px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar-header .logo h2 {
    color: white;
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 5px;
}

.sidebar-header .logo p {
    color: rgba(255,255,255,0.7);
    font-size: 12px;
}

.student-profile {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: rgba(255,255,255,0.05);
    margin: 15px;
    border-radius: 12px;
}

.student-profile .avatar {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 700;
    flex-shrink: 0;
}

.student-profile .profile-info h3 {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 3px;
}

.student-profile .profile-info p {
    font-size: 12px;
    color: rgba(255,255,255,0.7);
}

.nav-menu {
    padding: 10px 0;
}

.nav-section {
    margin-bottom: 20px;
}

.nav-section-title {
    padding: 10px 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: rgba(255,255,255,0.5);
    letter-spacing: 0.5px;
}

.nav-item {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    transition: all 0.3s;
    position: relative;
}

.nav-item i {
    margin-right: 12px;
    width: 20px;
    text-align: center;
    font-size: 16px;
}

.nav-item:hover {
    background: rgba(255,255,255,0.08);
    color: white;
    padding-left: 25px;
}

.nav-item.active {
    background: rgba(255,255,255,0.12);
    color: white;
    border-left: 3px solid #60a5fa;
}

.badge-notification {
    margin-left: auto;
    background: #ef4444;
    color: white;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 10px;
    min-width: 20px;
    text-align: center;
}

.nav-footer {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        width: 70px;
    }
    
    .sidebar-header .logo p,
    .student-profile .profile-info,
    .nav-section-title,
    .nav-item span,
    .badge-notification {
        display: none;
    }
    
    .student-profile {
        justify-content: center;
        padding: 15px;
    }
    
    .nav-item {
        justify-content: center;
    }
    
    .nav-item i {
        margin-right: 0;
    }
    
    .nav-item:hover {
        padding-left: 20px;
    }
}
</style>
<link rel="stylesheet" href="../assets/css/modern-theme.css">