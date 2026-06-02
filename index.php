<?php
require_once 'includes/functions.php';
init_session();

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    if (is_admin()) {
        header('Location: admin/index.php');
    } else {
        header('Location: user/index.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ziyafat us Shukr – Community Portal</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #1a56db;
            --primary-dark: #1e429f;
            --bg-light: #f9fafb;
            --text-main: #111827;
            --text-secondary: #4b5563;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-light);
            color: var(--text-main);
            margin: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .hero {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            text-align: center;
        }

        .container {
            max-width: 800px;
            background: white;
            padding: 3rem;
            border-radius: 1.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
        }

        .logo-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1.5rem;
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-main);
        }

        p {
            font-size: 1.1rem;
            line-height: 1.6;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-outline {
            border: 1px solid #d1d5db;
            color: var(--text-secondary);
        }

        .btn-outline:hover {
            background: #f3f4f6;
        }

        footer {
            padding: 2rem;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        footer a {
            color: var(--primary);
            text-decoration: none;
        }

        @media (max-width: 640px) {
            h1 { font-size: 1.75rem; }
            .container { padding: 2rem 1rem; }
        }
    </style>
</head>
<body>

<section class="hero">
    <div class="container">
        <div class="logo-icon">
            <i class="fas fa-mosque"></i>
        </div>
        <h1>Ziyafat us Shukr</h1>
        <p>
            Welcome to the official Amali Janib tracking portal. This platform is designed specifically for our community members to track religious activities, Quran Tilawat, and Dua progress.
        </p>
        
        <div class="btn-group">
            <a href="auth/login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i> Sign In to Portal
            </a>
            <a href="auth/register.php" class="btn btn-outline">
                <i class="fas fa-user-plus" style="margin-right: 8px;"></i> Create Account
            </a>
        </div>
    </div>
</section>

<footer>
    <p>© 1450 H · Ziyafat us Shukr · <a href="privacy-policy.php">Privacy Policy</a></p>
</footer>

</body>
</html>
