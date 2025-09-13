<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Misbar - Trading Application</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            text-align: center;
            color: white;
            max-width: 600px;
            padding: 2rem;
        }

        .logo {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .tagline {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .description {
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
            opacity: 0.8;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .feature {
            background: rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .feature h3 {
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .feature p {
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .status {
            margin-top: 2rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .version {
            margin-top: 1rem;
            font-size: 0.9rem;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">Laravel Misbar</div>
        <div class="tagline">Trading Application with HTTP Capture SDK</div>

        <div class="description">
            A modern Laravel application demonstrating the Misbar HTTP capture SDK with multiple API integrations for trading operations.
        </div>

        <div class="features">
            <div class="feature">
                <h3>🔒 Sanctum Authentication</h3>
                <p>Secure API authentication with Laravel Sanctum</p>
            </div>

            <div class="feature">
                <h3>📊 Trading APIs</h3>
                <p>Multiple API integrations for trading data</p>
            </div>

            <div class="feature">
                <h3>🚀 Laravel 12</h3>
                <p>Built with the latest Laravel framework</p>
            </div>
        </div>

        <div class="status">
            <strong>🟢 Application Status: Online</strong>
            <div class="version">Laravel {{ app()->version() }} • PHP {{ PHP_VERSION }}</div>
        </div>
    </div>
</body>
</html>