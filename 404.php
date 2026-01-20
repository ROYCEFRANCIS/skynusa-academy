<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | Skynusa Academy</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .error-container {
            text-align: center;
            color: white;
            max-width: 600px;
        }
        .error-code {
            font-size: 120px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 20px;
            text-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: bounce 2s infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        .error-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: shake 3s infinite;
        }
        @keyframes shake {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-10deg); }
            75% { transform: rotate(10deg); }
        }
        h1 {
            font-size: 36px;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        p {
            font-size: 18px;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-primary {
            background: white;
            color: #667eea;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(255,255,255,0.3);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid white;
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-3px);
        }
        @media (max-width: 768px) {
            .error-code { font-size: 80px; }
            .error-icon { font-size: 60px; }
            h1 { font-size: 28px; }
            p { font-size: 16px; }
            .btn-group { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">🔍</div>
        <div class="error-code">404</div>
        <h1>Oops! Page Not Found</h1>
        <p>The page you're looking for doesn't exist or has been moved.</p>
        <div class="btn-group">
            <a href="javascript:history.back()" class="btn btn-secondary">← Go Back</a>
            <a href="/" class="btn btn-primary">🏠 Back to Home</a>
        </div>
    </div>
</body>
</html>