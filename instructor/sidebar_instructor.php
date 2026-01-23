<?php
// Instructor Sidebar Component
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$instructor_name = $_SESSION['full_name'] ?? 'Instructor';
?>

<!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <h2>🎓 SKYNUSA</h2>
            <p>Instructor Panel</p>
        </div>
    </div>
    
    <div class="instructor-profile">
        <div class="avatar"><?php echo strtoupper(substr($instructor_name, 0, 1)); ?></div>
        <div class="profile-info">
            <h3><?php echo htmlspecialchars($instructor_name); ?></h3>
            <p>Instructor</p>
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
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Teaching</div>
            <a href="schedules.php" class="nav-item <?php echo $current_page == 'schedules' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Schedule</span>
            </a>
            <a href="materials.php" class="nav-item <?php echo $current_page == 'materials' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                <span>Materials</span>
            </a>
            <a href="students.php" class="nav-item <?php echo $current_page == 'students' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Students</span>
            </a>
            <a href="evaluations.php" class="nav-item <?php echo $current_page == 'evaluations' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i>
                <span>Evaluations</span>
            </a>
                        <a href="messages.php" class="nav-item <?php echo $current_page == 'messages' ? 'active' : ''; ?>">
                <i class="fas fa-comment-alt"></i>
                <span>messages</span>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Account</div>
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
    background: linear-gradient(180deg, #2c5282 0%, #2b6cb0 100%);
    color: white;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 4px 0 20px rgba(0,0,0,0.1);
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
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

.instructor-profile {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: rgba(255,255,255,0.08);
    margin: 15px;
    border-radius: 12px;
}

.instructor-profile .avatar {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #4299e1, #667eea);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 700;
    flex-shrink: 0;
    border: 3px solid rgba(255,255,255,0.2);
}

.instructor-profile .profile-info h3 {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 3px;
}

.instructor-profile .profile-info p {
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
    background: rgba(255,255,255,0.1);
    color: white;
    transform: translateX(5px);
}

.nav-item.active {
    background: rgba(255,255,255,0.15);
    color: white;
    border-left: 3px solid #60a5fa;
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
    .instructor-profile .profile-info,
    .nav-section-title,
    .nav-item span {
        display: none;
    }
    
    .instructor-profile {
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
        transform: none;
    }
}
</style>