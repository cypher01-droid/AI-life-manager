<?php
session_start();
require_once 'config/database.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate a secure token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Delete any existing tokens for this email
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);

            // Insert new token
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$email, $token, $expires]);

            // Create reset link
            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;

            // Attempt to send email
            $subject = "Password Reset Request - AI Life Manager";
            $body = "Hello,\n\nYou requested a password reset. Click the link below to reset your password:\n\n$resetLink\n\nThis link expires in 1 hour.\n\nIf you did not request this, please ignore this email.";
            $headers = "From: noreply@ailifemanager.com\r\n" .
                       "Reply-To: noreply@ailifemanager.com\r\n" .
                       "X-Mailer: PHP/" . phpversion();

            if (mail($email, $subject, $body, $headers)) {
                $message = "A reset link has been sent to your email address.";
            } else {
                // For development: show the link (remove in production)
                $message = "Mail could not be sent. For testing, use this link: <a href='$resetLink'>$resetLink</a>";
            }
        } else {
            // Don't reveal that email doesn't exist for security
            $message = "If that email is registered, you will receive a reset link shortly.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password – AI Life Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #0a0a0a;
            background-image: radial-gradient(circle at 10% 20%, #1a1a1a, #000000);
            color: #e0e0e0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 5%;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #a78bfa, #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .back-link {
            color: #b0b0b0;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: #a78bfa;
        }

        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 5%;
        }

        .forgot-card {
            background: rgba(20, 20, 30, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid #2a2a3a;
            border-radius: 32px;
            padding: 2.5rem;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8);
        }

        .forgot-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .forgot-header h1 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(to right, #ffffff, #c0c0ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .forgot-header p {
            color: #b0b0b0;
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #d1d5db;
            font-size: 0.95rem;
        }

        input {
            width: 100%;
            padding: 0.9rem 1.2rem;
            background: #1e1e2e;
            border: 1.5px solid #2a2a3a;
            border-radius: 16px;
            font-size: 1rem;
            color: #f0f0f0;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        input:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.2);
        }

        .message {
            background: rgba(16, 185, 129, 0.2);
            border-left: 4px solid #10b981;
            padding: 0.8rem 1.2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            color: #d1fae5;
            font-size: 0.95rem;
            word-break: break-word;
        }

        .message a {
            color: #a78bfa;
            text-decoration: underline;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.2);
            border-left: 4px solid #ef4444;
            padding: 0.8rem 1.2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            color: #fecaca;
            font-size: 0.95rem;
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(145deg, #7c3aed, #4f46e5);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 10px 25px -5px rgba(124, 58, 237, 0.4);
            margin-top: 1rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(124, 58, 237, 0.6);
        }

        .back-to-login {
            text-align: center;
            margin-top: 1.8rem;
            color: #9ca3af;
        }

        .back-to-login a {
            color: #a78bfa;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .back-to-login a:hover {
            color: #c4b5fd;
        }

        @media (max-width: 480px) {
            .forgot-card {
                padding: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">AI Life Manager</div>
        <a href="login.php" class="back-link">← Back to Login</a>
    </nav>

    <main class="main-content">
        <div class="forgot-card">
            <div class="forgot-header">
                <h1>Forgot Password?</h1>
                <p>Enter your email to receive a reset link</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="message"><?= $message ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" placeholder="alex@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <button type="submit" class="submit-btn">Send Reset Link</button>
            </form>

            <div class="back-to-login">
                Remember your password? <a href="login.php">Log in</a>
            </div>
        </div>
    </main>
</body>
</html>