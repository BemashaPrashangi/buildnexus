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
    <title>Opportunities - BuildNexus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .nexus-green { background-color: #22c55e; }
        .nexus-green:hover { background-color: #16a34a; }
        .text-nexus-green { color: #22c55e; }
        .nexus-orange { color: #f97316; }
        .text-nexus-orange { color: #f97316; }
        .sidebar-link:hover { background-color: #f3f4f6; color: #111827; }
        /* Active state for sidebar */
        .sidebar-link.active { background-color: #f3f4f6; font-weight: 600; color: #111827; border-right: 3px solid #22c55e; }
    </style>
</head>
<body class="bg-white text-gray-800 antialiased flex flex-col min-h-screen">

    <nav class="bg-white py-4 px-6 md:px-12 flex justify-between items-center shadow-sm sticky top-0 z-50 border-b border-gray-100">
        <div class="flex items-center gap-2 cursor-pointer">
            <a href="../index.php" class="flex items-center gap-2">
                <div class="w-8 h-8 rounded bg-orange-400 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 3.414L15.172 6.586a2 2 0 01.586 1.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" /></svg>
                </div>
                <span class="font-bold text-lg tracking-tight text-gray-900">BuildNexus</span>
            </a>
        </div>

        <div class="hidden md:flex gap-8 text-sm font-medium text-gray-600">
            <a href="#" class="hover:text-gray-900">Features <span class="text-xs">▼</span></a>
            <a href="../who-we-serve.php" class="hover:text-gray-900">Who We Serve</a>
            <a href="../pricing.php" class="hover:text-gray-900">Pricing</a>
            <a href="../contact.php" class="hover:text-gray-900">Contact Us</a>
        </div>

        <div class="flex items-center gap-4">
            <a href="../login.php" class="text-sm font-semibold text-gray-600 hover:text-gray-900">Login</a>
            <a href="../register.php" class="nexus-green text-white text-sm font-semibold px-4 py-2 rounded shadow hover:shadow-md transition">Get Started</a>
        </div>
    </nav>

    <div class="flex flex-col md:flex-row flex-grow max-w-7xl mx-auto w-full">
        
        <aside class="w-full md:w-64 bg-stone-50 border-r border-gray-200 py-8 hidden md:block flex-shrink-0">
            <nav class="space-y-1">
                <a href="projects.php" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                    Projects
                </a>
                <a href="opportunities.php" class="sidebar-link active flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                    Opportunities
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    Daily Logs
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    Scheduling
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    Work Orders
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Inspections
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    Punchlists
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray