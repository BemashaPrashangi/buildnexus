<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login if session is empty
    header("Location: login.php");
    exit();
}
?>
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
    <title>Contract Software - BuildNexus</title>
    <style>
        /* GLOBAL STYLES - PURE CSS */
        :root {
            --nexus-green: #22c55e;
            --nexus-orange: #f97316;
            --bg-light: #fafafa;
            --text-dark: #111827;
            --text-gray: #4b5563;
            --border-color: #e5e7eb;
            --font-main: 'Inter', system-ui, -apple-system, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-main);
            background-color: #ffffff;
            color: var(--text-dark);
            line-height: 1.6;
        }

        /* NAVIGATION */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 5rem;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 100;
        }

        .logo { font-weight: 700; font-size: 1.25rem; display: flex; align-items: center; gap: 8px; text-decoration: none; color: inherit; }
        .logo-box { width: 20px; height: 20px; background: var(--nexus-orange); border-radius: 4px; }
        
        .nav-links { display: flex; gap: 2rem; list-style: none; }
        .nav-links a { text-decoration: none; color: var(--text-gray); font-size: 0.9rem; font-weight: 500; }

        .nav-actions { display: flex; align-items: center; gap: 1.5rem; }
        .btn-green { background: var(--nexus-green); color: white; padding: 0.5rem 1.2rem; border-radius: 4px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; }

        /* LAYOUT CONTAINER */
        .main-container { display: flex; max-width: 1440px; margin: 0 auto; }

        /* SIDEBAR (Financials Context) */
        .sidebar {
            width: 260px;
            background: var(--bg-light);
            border-right: 1px solid var(--border-color);
            padding: 2rem 0;
            height: calc(100vh - 70px);
            position: sticky;
            top: 70px;
            overflow-y: auto;
        }

        .sidebar-menu { list-style: none; }
        .sidebar-item a {
            display: block;
            padding: 0.75rem 2rem;
            color: var(--text-gray);
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .sidebar-item a:hover { background: #f3f4f6; color: var(--text-dark); }
        .sidebar-item.active a {
            background: #f3f4f6;
            color: var(--text-dark);
            font-weight: 600;
            border-right: 3px solid var(--nexus-green);
        }

        /* CONTENT AREA */
        .content { flex: 1; padding: 4rem; }

        /* HERO SECTION */
        .hero { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; margin-bottom: 8rem; }
        .hero-text h1 { font-size: 2.5rem; line-height: 1.2; margin-bottom: 1.5rem; font-weight: 800; }
        .hero-text p { color: var(--text-gray); font-size: 1rem; margin-bottom: 2rem; max-width: 500px; }
        .btn-demo { background: var(--nexus-green); color: white; padding: 0.8rem 1.5rem; border-radius: 4px; text-decoration: none; font-weight: 700; display: inline-block; }

        .hero-img { border-radius: 12px; overflow: hidden; background: #e5e7eb; height: 350px; display: flex; align-items: center; justify-content: center; color: #6b7280; font-size: 0.9rem; text-align: center; padding: 2rem; }
        .hero-img img { width: 100%; height: 100%; object-fit: cover; }

        /* PROCESS SECTION */
        .process-section { text-align: center; margin-bottom: 8rem; }
        .process-section span { color: var(--nexus-green); font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 1rem; }
        .process-section h2 { font-size: 2.2rem; margin-bottom: 4rem; font-weight: 800; }
        
        .process-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 3rem; }
        .process-card { text-align: center; }
        .process-card .icon { font-size: 1.5rem; color: var(--nexus-green); margin-bottom: 1rem; display: block; }
        .process-card h3 { font-size: 1.1rem; margin-bottom: 0.8rem; font-weight: 700; }
        .process-card p { font-size: 0.85rem; color: var(--text-gray); line-height: 1.6; max-width: 250px; margin: 0 auto; }

        /* DETAIL SECTION */
        .detail-section { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; margin-bottom: 5rem; }
        .detail-text span { color: var(--nexus-green); font-weight: 700; font-size: 0.75rem; margin-bottom: 0.5rem; display: block; }
        .detail-text h2 { font-size: 2rem; margin-bottom: 1.5rem; font-weight: 800; line-height: 1.2; }
        .detail-text p { color: var(--text-gray); font-size: 0.9rem; line-height: 1.6; }

        .detail-img { border-radius: 12px; overflow: hidden; background: #e5e7eb; height: 300px; position: relative; }
        .play-btn { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 50px; height: 50px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.1); cursor: pointer; }

        /* FEATURES GRID */
        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-bottom: 5rem; }
        .feature-card { background: #fff; padding: 2rem; border-radius: 8px; border: 1px solid var(--border-color); transition: 0.3s; }
        .feature-card:hover { background: var(--bg-light); }
        .feature-card .icon-label { display: flex; align-items: center; gap: 8px; color: var(--nexus-green); font-weight: 700; font-size: 0.75rem; margin-bottom: 1rem; }
        .feature-card .dot { width: 8px; height: 8px; background: var(--nexus-green); border-radius: 50%; }
        .feature-card h3 { font-size: 1rem; margin-bottom: 1rem; font-weight: 800; color: var(--text-dark); }
        .feature-card p { font-size: 0.85rem; color: var(--text-gray); line-height: 1.6; }

        /* FOOTER */
        .footer { background: var(--bg-light); padding: 4rem 5rem 2rem; border-top: 1px solid var(--border-color); }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 4rem; margin-bottom: 3rem; }
        .footer-col h4 { font-size: 0.9rem; margin-bottom: 1.5rem; }
        .footer-col ul { list-style: none; }
        .footer-col li { margin-bottom: 0.75rem; font-size: 0.8rem; color: var(--text-gray); }
        .footer-bottom { border-top: 1px solid var(--border-color); padding-top: 2rem; text-align: left; font-size: 0.75rem; color: #9ca3af; }

        @media (max-width: 1024px) {
            .hero, .process-grid, .detail-section, .features-grid, .footer-grid { grid-template-columns: 1fr; }
            .navbar { padding: 1rem 2rem; }
            .content { padding: 2rem; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="../index.php" class="logo">
            <div class="logo-box"></div> BuildNexus
        </a>
        <ul class="nav-links">
            <li><a href="#">Features ▼</a></li>
            <li><a href="../who-we-serve.php">Who We Serve</a></li>
            <li><a href="../pricing.php">Pricing</a></li>
            <li><a href="../contact.php">Contact Us</a></li>
        </ul>
        <div class="nav-actions">
            <a href="../login.php" style="text-decoration: none; color: var(--text-gray); font-size: 0.9rem; font-weight: 600;">Log In</a>
            <a href="../register.php" class="btn-green">Get Started</a>
        </div>
    </nav>

    <div class="main-container">
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li class="sidebar-item"><a href="interactive-estimates.php">Interactive Estimates</a></li>
                <li class="sidebar-item"><a href="bid-management.php">Bid Management</a></li>
                <li class="sidebar-item"><a href="change-orders.php">Change Orders</a></li>
                <li class="sidebar-item"><a href="invoices.php">Invoices</a></li>
                <li class="sidebar-item"><a href="purchase-orders.php">Purchase Orders</a></li>
                <li class="sidebar-item active"><a href="contracts.php">Sub-Contracts</a></li>
                <li class="sidebar-item"><a href="#">Bills and Expenses</a></li>
                <li class="sidebar-item"><a href="online-payments.php">Online Payments</a></li>
            </ul>
        </aside>

        <main class="content">
            <section class="hero">
                <div class="hero-text">
                    <h1>Make Signing Your Next Win With Our Contract Software</h1>
                    <p>Create, send, and get contracts signed faster than ever. Our tools help you manage documents, track approvals, and keep your projects moving forward.</p>
                    <a href="#" class="btn-demo">Get a Free Demo</a>
                </div>
                <div class="hero-img">
                    <img src="https://images.unsplash.com/photo-1450101499163-c8848c66ca85?auto=format&fit=crop&w=800&q=80" alt="User interacting with contract software">
                </div>
            </section>

            <section class="process-section">
                <span>HOW TO KNOW WHAT TO KEEP IN CONTRACTS</span>
                <h2>Get to Know What to Keep in Contracts</h2>
                <div class="process-grid">
                    <div class="process-card">
                        <span class="icon">📝</span>
                        <h3>Create & Send</h3>
                        <p>Draft contracts from professional templates and send them for approval in minutes.</p>
                    </div>
                    <div class="process-card">
                        <span class="icon">🚀</span>
                        <h3>Secure eSigning</h3>
                        <p>Allow clients to securely sign contracts from any device, anywhere.</p>
                    </div>
                    <div class="process-card">
                        <span class="icon">✅</span>
                        <h3>Track Everything</h3>
                        <p>Get real-time status updates and notifications for every contract.</p>
                    </div>
                </div>
            </section>

            <section class="detail-section">
                <div class="detail-text">
                    <span>Sub-Contracts</span>
                    <h2>Included terms, conditions, scope of work etc</h2>
                    <p>Contracts are a necessity in today's work place. BuildNexus makes it easy for you to create Subcontracts; do it right from the Bid Manager once you've awarded a Bid. Merge in contract details using the document writer and up your game even more.</p>
                </div>
                <div class="detail-img">
                    <img src="https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?auto=format&fit=crop&w=800&q=80" alt="Contracts preview" style="width:100%; height:100%; object-fit:cover; opacity:0.8;">
                    <div class="play-btn">▶</div>
                </div>
            </section>

            <section class="features-grid">
                <div class="feature-card">
                    <div class="icon-label"><div class="dot"></div> Custom Fields</div>
                    <h3>Custom Fields</h3>
                    <p>Subcontracts are critical documents. Capture and report on the data you want and need using our powerful custom fields. Never get caught off guard again, we have you covered.</p>
                </div>
                <div class="feature-card">
                    <div class="icon-label"><div class="dot"></div> Eliminate Double Entry</div>
                    <h3>Eliminate Double Entry</h3>
                    <p>Let BuildNexus write the Subcontracts for you, two easy clicks and the entire Subcontract is written for you, ready to send for approval.</p>
                </div>
                <div class="feature-card">
                    <div class="icon-label"><div class="dot"></div> Import Existing Items</div>
                    <h3>Import Existing Items</h3>
                    <p>Bring over your Subcontract items from your Estimate in a few easy clicks. Do away with copy paste and redundant typing once and for all.</p>
                </div>
                <div class="feature-card">
                    <div class="icon-label"><div class="dot"></div> Single Click Bill Creation</div>
                    <h3>Single Click Bill Creation</h3>
                    <p>No more multiple screens, extra wasteful typing, one single click creates the Bill for your Subcontracts. Accounting is going to love you!</p>
                </div>
            </section>
        </main>
    </div>

    <footer class="footer">
        <div class="footer-grid">
            <div class="footer-col">
                <a href="../index.php" class="logo" style="margin-bottom: 1rem;"><div class="logo-box"></div> BuildNexus</a>
                <p style="font-size: 0.8rem; color: var(--text-gray);">The complete platform for construction professionals.</p>
            </div>
            <div class="footer-col">
                <h4>BuildNexus</h4>
                <ul>
                    <li>Features</li>
                    <li>Benefits</li>
                    <li>Contact</li>
                    <li>Live Chat</li>
                    <li>Estimate Generator</li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Legal</h4>
                <ul>
                    <li>Privacy Policy</li>
                    <li>Terms of Service</li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Contact</h4>
                <ul>
                    <li>123 Main Street</li>
                    <li>Colombo, Sri Lanka</li>
                    <li>+94 11 234 5678</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; 2025 BuildNexus. Developed for Tharaka Construction.
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebarLinks = document.querySelectorAll('.sidebar-item');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    sidebarLinks.forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        });
    </script>
</body>
</html>