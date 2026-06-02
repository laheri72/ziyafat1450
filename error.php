<?php
$error_code = $_GET['code'] ?? 'Unknown';
$error_titles = [
    '400' => 'Bad Request',
    '401' => 'Unauthorized',
    '403' => 'Access Forbidden',
    '404' => 'Page Not Found',
    '500' => 'Internal Server Error'
];

$error_messages = [
    '400' => 'The request could not be understood by the server due to malformed syntax.',
    '401' => 'You are not authorized to access this page.',
    '403' => 'You don\'t have permission to access this resource.',
    '404' => 'The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.',
    '500' => 'The server encountered an internal error and was unable to complete your request.'
];

$title = $error_titles[$error_code] ?? 'Error';
$message = $error_messages[$error_code] ?? 'An unexpected error occurred.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $error_code; ?> <?php echo $title; ?> – Ziyafat us Shukr</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f9fafb;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            text-align: center;
        }
        .error-container {
            max-width: 500px;
            padding: 2rem;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
        }
        .error-code {
            font-size: 5rem;
            font-weight: 800;
            color: #1a56db;
            margin: 0;
            line-height: 1;
        }
        h1 {
            font-size: 1.5rem;
            margin: 1rem 0;
        }
        p {
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            background-color: #1a56db;
            color: white;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn:hover {
            background-color: #1e429f;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <p class="error-code"><?php echo htmlspecialchars($error_code); ?></p>
        <h1><?php echo htmlspecialchars($title); ?></h1>
        <p><?php echo htmlspecialchars($message); ?></p>
        <a href="/index.php" class="btn">
            <i class="fas fa-home" style="margin-right: 8px;"></i> Back to Home
        </a>
    </div>
</body>
</html>
