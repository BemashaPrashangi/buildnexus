<!DOCTYPE html>
<html lang="en">

<head>
    <!-- BuildNexus Favicon & Brand Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/buildnexus/images/logo.png?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="/buildnexus/images/logo.png?v=2">
    <link rel="shortcut icon" href="/buildnexus/images/logo.png?v=2">
    <link rel="apple-touch-icon" href="/buildnexus/images/logo.png?v=2">
    <meta charset="UTF-8">
    <title>Create an Account - BuildNexus</title>
    <style>
        :root {
            --nexus-green: #22c55e;
            --border: #e5e7eb;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f9fafb;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px 0;
        }

        .card {
            background: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 420px;
            text-align: center;
        }

        /* Update this part in your <style> section */
        .logo {
            width: 80px;
            /* Increased from 40px/60px to 80px */
            height: auto;
            /* Keeps the proportions correct */
            margin-bottom: 1.5rem;
            /* Added a bit more space below the logo */
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        h2 {
            margin: 0 0 5px;
            font-weight: 800;
        }

        p {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 25px;
        }

        .form-group {
            text-align: left;
            margin-bottom: 15px;
        }

        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 0.9rem;
        }

        textarea {
            resize: vertical;
            height: 80px;
        }

        .btn-register {
            width: 100%;
            padding: 0.9rem;
            background: var(--nexus-green);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
            font-size: 1rem;
        }

        .footer-link {
            margin-top: 20px;
            font-size: 0.9rem;
        }

        .footer-link a {
            color: var(--nexus-green);
            text-decoration: none;
            font-weight: 700;
        }
    </style>
</head>

<body>
    <div class="card">
        <img src="images/logo.png" alt="BuildNexus" class="logo">
        <h2>Create an Account</h2>
        <p>Enter your details to get started.</p>

        <form action="process_register.php" method="POST">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="full_name" placeholder="Your Name" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="name@example.com" required>
            </div>

            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone_number" placeholder="Your phone number" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required>
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea name="address" placeholder="Your address" required></textarea>
            </div>

            <div class="form-group">
                <label>I am a...</label>
                <select name="role" required>
                    <option value="" disabled selected>Select Your Role</option>
                    <option value="Project Manager">Project Manager</option>
                    <option value="Foreman">Foreman</option>
                    <option value="Client">Client</option>
                </select>
            </div>

            <button type="submit" class="btn-register">Create Account</button>
        </form>

        <div class="footer-link">
            Already have an account? <a href="login.php">Sign in</a>
        </div>
    </div>
</body>

</html>