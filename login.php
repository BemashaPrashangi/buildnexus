<!DOCTYPE html>
<html lang="en">

<head>
    <!-- BuildNexus Favicon & Brand Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/buildnexus/images/logo.png?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="/buildnexus/images/logo.png?v=2">
    <link rel="shortcut icon" href="/buildnexus/images/logo.png?v=2">
    <link rel="apple-touch-icon" href="/buildnexus/images/logo.png?v=2">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome Back - BuildNexus</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --nexus-green: #22c55e;
            --nexus-green-hover: #16a34a;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f9fafb;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1rem;
            box-sizing: border-box;
        }

        .login-card {
            background: #ffffff;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 400px;
            text-align: center;
            box-sizing: border-box;
        }

        .logo {
            width: 70px;
            height: auto;
            margin-bottom: 1.25rem;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        h2 {
            margin: 0 0 0.5rem 0;
            color: #1e293b;
            font-size: 1.5rem;
            font-weight: 700;
        }

        p.subtitle {
            margin: 0 0 1.5rem 0;
            color: #64748b;
            font-size: 0.9rem;
        }

        .form-group {
            text-align: left;
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.35rem;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem 0.9rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 0.95rem;
            color: #1e293b;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: var(--nexus-green);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15);
        }

        .btn-signin {
            width: 100%;
            padding: 0.85rem;
            background: var(--nexus-green);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 1rem;
            transition: background 0.2s;
        }

        .btn-signin:hover {
            background: var(--nexus-green-hover);
        }

        .footer-text {
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: #64748b;
        }

        .footer-text a {
            color: var(--nexus-green);
            text-decoration: none;
            font-weight: 600;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <a href="index.php">
            <img src="images/logo.png" alt="BuildNexus Logo" class="logo" onerror="this.style.display='none'">
        </a>
        <h2>Welcome Back</h2>
        <p class="subtitle">Sign in to continue to BuildNexus.</p>

        <form action="process_login.php" method="POST">
            <div class="form-group">
                <label for="emailInput">Email Address</label>
                <input type="email" id="emailInput" name="email" placeholder="name@company.com" required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="passwordInput">Password</label>
                <input type="password" id="passwordInput" name="password" placeholder="••••••••" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-signin">Sign In</button>
        </form>

        <p class="footer-text">Don't have an account? <a href="register.php">Create account</a></p>
    </div>
</body>

</html>