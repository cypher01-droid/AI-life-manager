<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Decode preferences JSON
$preferences = json_decode($user['preferences'], true) ?? [];
$role = $preferences['role'] ?? 'user';
$onboarding_completed = $preferences['onboarding_completed'] ?? false;

// Handle form submissions
$success_message = '';
$error_message = '';

// Update profile (name, email, timezone, avatar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $timezone = trim($_POST['timezone']);
        $avatar_url = trim($_POST['avatar_url']);
        $new_role = $_POST['role'];

        // Validation
        $errors = [];
        if (empty($full_name)) $errors[] = 'Full name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
        
        // Check if email is already taken by another user
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) $errors[] = 'Email already in use by another account.';
        }

        if (empty($errors)) {
            // Update user table
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, timezone = ?, avatar_url = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $timezone, $avatar_url, $user_id]);
            
            // Update preferences role
            $preferences['role'] = $new_role;
            $preferences['onboarding_completed'] = true;
            $stmt = $pdo->prepare("UPDATE users SET preferences = ? WHERE id = ?");
            $stmt->execute([json_encode($preferences), $user_id]);
            
            // Update session name (for header)
            $_SESSION['user_name'] = $full_name;
            $_SESSION['user_email'] = $email;
            
            $success_message = 'Profile updated successfully.';
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            $preferences = json_decode($user['preferences'], true) ?? [];
            $role = $preferences['role'] ?? 'user';
        } else {
            $error_message = implode('<br>', $errors);
        }
    }
    
    // Change password
    elseif ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $errors = [];
        if (empty($current_password)) $errors[] = 'Current password is required.';
        if (strlen($new_password) < 8) $errors[] = 'New password must be at least 8 characters.';
        if ($new_password !== $confirm_password) $errors[] = 'New passwords do not match.';
        
        if (empty($errors)) {
            // Verify current password
            if (password_verify($current_password, $user['password_hash'])) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$new_hash, $user_id]);
                $success_message = 'Password changed successfully.';
            } else {
                $error_message = 'Current password is incorrect.';
            }
        } else {
            $error_message = implode('<br>', $errors);
        }
    }
}

$pageTitle = 'Profile';
include 'header.php';
?>

<style>
/* Button styles (if not already in header) */
.btn-primary {
    background: linear-gradient(145deg, #7c3aed, #4f46e5);
    color: white;
    border: none;
    padding: 0.7rem 1.5rem;
    border-radius: 40px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 5px 15px rgba(124, 58, 237, 0.3);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(124, 58, 237, 0.5);
}
.btn-secondary {
    background: transparent;
    border: 1.5px solid #333;
    color: #e0e0e0;
    padding: 0.5rem 1rem;
    border-radius: 40px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    font-size: 0.9rem;
}
.btn-secondary:hover {
    border-color: #a78bfa;
    color: #a78bfa;
}
.add-link {
    font-size: 0.85rem;
    margin-left: 0.5rem;
    color: #a78bfa;
    cursor: pointer;
    text-decoration: none;
}
.add-link:hover {
    text-decoration: underline;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 100;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    align-items: center;
    justify-content: center;
}
.modal.active {
    display: flex;
}
.modal-content {
    background: rgba(30, 30, 40, 0.95);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 32px;
    padding: 2rem;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.modal-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #f0f0f0;
}
.close {
    font-size: 1.5rem;
    cursor: pointer;
    color: #9ca3af;
    transition: color 0.2s;
}
.close:hover {
    color: #ffffff;
}

/* Form elements */
.form-group {
    margin-bottom: 1.2rem;
}
label {
    display: block;
    margin-bottom: 0.3rem;
    font-weight: 500;
    color: #d1d5db;
    font-size: 0.9rem;
}
input, textarea, select {
    width: 100%;
    padding: 0.8rem 1rem;
    background: #2a2a3a;
    border: 1.5px solid #333;
    border-radius: 16px;
    font-size: 0.95rem;
    color: #f0f0f0;
    transition: all 0.2s;
    font-family: 'Inter', sans-serif;
}
input:focus, textarea:focus, select:focus {
    outline: none;
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.2);
}
textarea {
    resize: vertical;
}
.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
}
    .profile-container {
        max-width: 800px;
        margin: 0 auto;
    }
    .profile-section {
        margin-bottom: 2rem;
    }
    .profile-section h2 {
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: #f0f0f0;
    }
    .avatar-preview {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        background: #2a2a3a;
        border: 2px solid #7c3aed;
        margin-bottom: 1rem;
    }
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .info-label {
        font-weight: 500;
        color: #9ca3af;
    }
    .info-value {
        color: #f0f0f0;
    }
    .success-message {
        background: rgba(16,185,129,0.2);
        border-left: 4px solid #10b981;
        padding: 0.8rem 1rem;
        border-radius: 12px;
        margin-bottom: 1rem;
        color: #d1fae5;
    }
    .error-message {
        background: rgba(239,68,68,0.2);
        border-left: 4px solid #ef4444;
        padding: 0.8rem 1rem;
        border-radius: 12px;
        margin-bottom: 1rem;
        color: #fecaca;
    }
    @media (max-width: 768px) {
        .profile-container {
            padding: 0 1rem;
        }
    }
</style>

<div class="profile-container">
    <h1 style="margin-bottom: 2rem;">👤 My Profile</h1>
    
    <?php if ($success_message): ?>
        <div class="success-message"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="error-message"><?= $error_message ?></div>
    <?php endif; ?>
    
    <!-- Profile Information -->
    <div class="glass-card profile-section">
        <h2>Profile Information</h2>
        <form method="post" action="profile.php">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="form-group">
                <label for="avatar_url">Avatar URL</label>
                <input type="url" id="avatar_url" name="avatar_url" value="<?= htmlspecialchars($user['avatar_url'] ?? '') ?>" placeholder="https://example.com/avatar.jpg">
                <?php if ($user['avatar_url']): ?>
                    <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="Avatar" class="avatar-preview" style="margin-top: 0.5rem;">
                <?php else: ?>
                    <div class="avatar-preview" style="display: flex; align-items: center; justify-content: center;">No avatar</div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="full_name">Full Name *</label>
                <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="username">Username (cannot be changed)</label>
                <input type="text" id="username" value="<?= htmlspecialchars($user['username']) ?>" disabled style="opacity: 0.7;">
            </div>
            
            <div class="form-group">
                <label for="role">I am a...</label>
                <select id="role" name="role">
                    <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>User</option>
                    <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Student</option>
                    <option value="teacher" <?= $role === 'teacher' ? 'selected' : '' ?>>Teacher / Educator</option>
                    <option value="entrepreneur" <?= $role === 'entrepreneur' ? 'selected' : '' ?>>Entrepreneur</option>
                    <option value="webdev" <?= $role === 'webdev' ? 'selected' : '' ?>>Web Developer</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="timezone">Timezone</label>
                <select id="timezone" name="timezone">
                    <?php
                    $timezones = DateTimeZone::listIdentifiers();
                    $selected_tz = $user['timezone'] ?? 'UTC';
                    foreach ($timezones as $tz):
                    ?>
                        <option value="<?= $tz ?>" <?= $tz === $selected_tz ? 'selected' : '' ?>><?= $tz ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-primary">Update Profile</button>
            </div>
        </form>
    </div>
    
    <!-- Change Password -->
    <div class="glass-card profile-section">
        <h2>Change Password</h2>
        <form method="post" action="profile.php">
            <input type="hidden" name="action" value="change_password">
            
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password (min. 8 characters)</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-primary">Change Password</button>
            </div>
        </form>
    </div>
    
    <!-- Account Information (read-only) -->
    <div class="glass-card profile-section">
        <h2>Account Information</h2>
        <div class="info-row">
            <span class="info-label">Account created:</span>
            <span class="info-value"><?= date('F j, Y, g:i a', strtotime($user['created_at'])) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Last updated:</span>
            <span class="info-value"><?= date('F j, Y, g:i a', strtotime($user['updated_at'])) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Onboarding completed:</span>
            <span class="info-value"><?= $onboarding_completed ? 'Yes' : 'No' ?></span>
        </div>
    </div>
    
    <!-- Danger zone (optional) -->
    <div class="glass-card profile-section" style="border-left: 3px solid #ef4444;">
        <h2 style="color: #ef4444;">Danger Zone</h2>
        <p style="margin-bottom: 1rem; color: #9ca3af;">Once you delete your account, there is no going back. Please be certain.</p>
        <a href="#" onclick="alert('Account deletion is disabled in this demo. Contact support.'); return false;" class="btn-danger" style="display: inline-block;">Delete Account</a>
    </div>
</div>

<script>
    // Preview avatar when URL changes (optional)
    const avatarUrlInput = document.getElementById('avatar_url');
    const avatarPreview = document.querySelector('.avatar-preview');
    if (avatarUrlInput && avatarPreview) {
        avatarUrlInput.addEventListener('change', function() {
            const url = this.value;
            if (url) {
                if (avatarPreview.tagName === 'IMG') {
                    avatarPreview.src = url;
                } else {
                    const img = document.createElement('img');
                    img.src = url;
                    img.classList.add('avatar-preview');
                    avatarPreview.parentNode.replaceChild(img, avatarPreview);
                }
            }
        });
    }
</script>

<?php include 'footer.php'; ?>