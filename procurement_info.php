<?php include 'db.php'; ?>
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
    <title>Procurement Management | BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .feature-icon {
            font-size: 1.5rem;
            color: #22c55e;
            margin-right: 15px;
        }
        .hero-section {
            padding: 80px 0;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <<img src="images/logo.png" alt="BuildNexus Logo" style="width: 45px; height: 45px; margin-right: 12px;">
                BuildNexus
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <li class="nav-item ms-lg-3">
                        <a class="nav-link btn btn-primary text-white px-4" href="register.php">Get Started</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="display-4 text-primary fw-bold mb-3">Streamlined Procurement</h1>
                    <p class="lead mb-4">Manage your construction supply chain with precision using BuildNexus. Our platform bridges the gap between site requirements and back-office financial control.</p>
                    
                    <div class="mt-4">
                        <div class="d-flex align-items-start mb-3">
                            <i class="bi bi-check-circle-fill feature-icon"></i>
                            <div>
                                <h5 class="fw-bold mb-0">Purchase Order Management</h5>
                                <p class="text-muted">Create and track POs directly linked to project budgets to prevent overspending.</p>
                            </div>
                        </div>

                        <div class="d-flex align-items-start mb-3">
                            <i class="bi bi-check-circle-fill feature-icon"></i>
                            <div>
                                <h5 class="fw-bold mb-0">Goods Received Notes (GRN)</h5>
                                <p class="text-muted">Verify deliveries instantly via mobile updates directly from the construction site.</p>
                            </div>
                        </div>

                        <div class="d-flex align-items-start mb-3">
                            <i class="bi bi-check-circle-fill feature-icon"></i>
                            <div>
                                <h5 class="fw-bold mb-0">Supplier Integration</h5>
                                <p class="text-muted">Maintain a centralized database of verified suppliers and manage relationships effectively.</p>
                            </div>
                        </div>

                        <div class="d-flex align-items-start mb-3">
                            <i class="bi bi-check-circle-fill feature-icon"></i>
                            <div>
                                <h5 class="fw-bold mb-0">Real-time Status Tracking</h5>
                                <p class="text-muted">Monitor orders from "Pending" to "Approved" and "Delivered" instantly on your dashboard.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-5">
                        <a href="login.php" class="btn btn-lg btn-primary shadow px-5">Access System Now</a>
                    </div>
                </div>

                <div class="col-md-6 mt-5 mt-md-0">
                    <div class="position-relative">
                        <img src="images/workflow-2.jpg" class="img-fluid rounded-4 shadow-lg border" alt="Procurement Workflow" onerror="this.src='https://via.placeholder.com/600x450?text=Procurement+System+Preview'">
                        <div class="position-absolute bottom-0 start-0 m-4 bg-white p-3 rounded-3 shadow-sm border-start border-primary border-4 d-none d-lg-block">
                            <p class="small fw-bold mb-0 text-dark"><i class="bi bi-graph-up-arrow text-success"></i> Real-time Monitoring Active</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-dark text-white text-center py-4 mt-auto">
        <div class="container">
            <p class="mb-0">&copy; 2025 BuildNexus | Tharaka Construction Management Solution.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>