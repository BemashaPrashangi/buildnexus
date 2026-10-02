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
    <title>Product Clipper & Library - BuildNexus</title>
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

        /* SIDEBAR (Planning Category) */
        .sidebar {
            width: 260px;
            background: var(--bg-light);
            border-right: 1px solid var(--border-color);
            padding: 2rem 0;
            height: calc(100vh - 70px);
            position: sticky;
            top: 70px;
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
        .hero { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; margin-bottom: 6rem; }
        .hero-text span { color: var(--nexus-green); font-weight: 700; font-size: 0.8rem; text-transform: uppercase; }
        .hero-text h1 { font-size: 2.5rem; line-height: 1.2; margin: 1rem 0; font-weight: 800; }
        .hero-text p { color: var(--text-gray); font-size: 1rem; margin-bottom: 2.5rem; }

        .hero-img { border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid var(--border-color); }
        .hero-img img { width: 100%; display: block; }

        /* FEATURES GRID */
        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; }
        .feature-card { background: #fff; padding: 2rem; border-radius: 8px; border: 1px solid var(--border-color); transition: 0.3s; }
        .feature-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .feature-card .icon { font-size: 1.5rem; display: block; margin-bottom: 1rem; }
        .feature-card h3 { font-size: 1.1rem; margin-bottom: 1rem; font-weight: 800; }
        .feature-card p { font-size: 0.85rem; color: var(--text-gray); line-height: 1.6; }

        /* FOOTER */
        .footer { background: var(--bg-light); padding: 4rem 5rem 2rem; border-top: 1px solid var(--border-color); margin-top: 4rem;}
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 4rem; margin-bottom: 3rem; }
        .footer-col h4 { font-size: 0.9rem; margin-bottom: 1.5rem; }
        .footer-col ul { list-style: none; }
        .footer-col li { margin-bottom: 0.75rem; font-size: 0.8rem; color: var(--text-gray); }
        .footer-bottom { border-top: 1px solid var(--border-color); padding-top: 2rem; text-align: left; font-size: 0.75rem; color: #9ca3af; }

        @media (max-width: 1024px) {
            .hero, .features-grid, .footer-grid { grid-template-columns: 1fr; }
            .navbar { padding: 1rem 2rem; }
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
                <li class="sidebar-item"><a href="3d-floor-plans.php">3D Floor Plans</a></li>
                <li class="sidebar-item"><a href="takeoffs.php">Takeoffs</a></li>
                <li class="sidebar-item"><a href="contracts.php">Contracts</a></li>
                <li class="sidebar-item"><a href="bid-management.php">Bid Management</a></li>
                <li class="sidebar-item active"><a href="product-clipper.php">Product Clipper & Library</a></li>
                <li class="sidebar-item"><a href="selections.php">Selections</a></li>
                <li class="sidebar-item"><a href="crm.php">CRM</a></li>
                <li class="sidebar-item"><a href="mood-boards.php">Mood Boards</a></li>
            </ul>
        </aside>

        <main class="content">
            <section class="hero">
                <div class="hero-text">
                    <span>Product Clipper & Library</span>
                    <h1>Save Products from Anywhere on the Web</h1>
                    <p>Building your project specifications is now faster than ever. Use our browser tool to "clip" images, prices, and descriptions from any website and save them directly to your BuildNexus product library.</p>
                    <a href="#" class="btn-green" style="padding: 0.8rem 1.5rem;">Download Clipper Extension</a>
                </div>
                <div class="hero-img">
                    <img src="https://images.unsplash.com/photo-1558655146-d09347e92766?auto=format&fit=crop&w=800&q=80" alt="Product Library Interface">
                </div>
            </section>

            <section class="features-grid">
                <div class="feature-card">
                    <span class="icon">✂️</span>
                    <h3>One-Click Clipping</h3>
                    <p>Found the perfect fixture on Wayfair or Kohler? Just click the clipper icon to grab the details and import them into your project selections instantly.</p>
                </div>
                <div class="feature-card">
                    <span class="icon">📂</span>
                    <h3>Centralized Library</h3>
                    <p>Create a master catalog of your most-used items. Categorize products by room, trade, or manufacturer for quick access across all projects.</p>
                </div>
                <div class="feature-card">
                    <span class="icon">🔄</span>
                    <h3>Instant Estimates</h3>
                    <p>Once an item is in your library, you can drag it into an Estimate or Proposal. Pricing and descriptions are automatically synced to save you typing.</p>
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