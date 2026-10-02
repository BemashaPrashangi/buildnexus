<?php
require '../db.php'; // දත්ත සමුදා සම්බන්ධතාවය
session_start();

// 1. ආරක්ෂාව: Foreman හෝ Admin පමණක් ඇතුල් විය හැක
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Foreman' && $_SESSION['role'] !== 'Admin')) {
    header("Location: ../login.php");
    exit();
}

$message = "";

// 2. වාර්තාව සුරැකීමේ තර්කය (Save Logic)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_report'])) {
    $project_id = $_POST['project_id'];
    $summary    = $_POST['summary'];
    $foreman_id = $_SESSION['user_id'];

    // Save report to database
    $stmt = $pdo->prepare("INSERT INTO daily_reports (project_id, foreman_id, work_summary, weather_condition, status) VALUES (?, ?, ?, 'Sunny, 30°C', 'Submitted')");
    if ($stmt->execute([$project_id, $foreman_id, $summary])) {
        $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4 font-bold text-center'>Daily report submitted successfully!</div>";
    }
}

// 3. දැනට පවතින සක්‍රීය ව්‍යාපෘති ලැයිස්තුව ලබාගැනීම
$projects = $pdo->query("SELECT id, project_name FROM projects WHERE status = 'Active'")->fetchAll();
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
    <title>Daily Report - BuildNexus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-slate-50 font-['Inter'] p-4 md:p-8">

    <div class="max-w-xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold text-slate-900">📝 Site Daily Report</h1>
            <a href="../foreman_logs.php" class="text-slate-500 hover:text-slate-800 text-sm font-semibold">← Back to Portal</a>
        </div>

        <?php echo $message; ?>

        <div class="bg-white p-8 rounded-2xl shadow-xl border border-slate-100">
            <form method="POST">
                <div class="mb-6">
                    <label class="block text-sm font-bold text-slate-700 mb-2">Assign to Project Site</label>
                    <select name="project_id" class="w-full border border-slate-200 rounded-xl p-4 bg-slate-50 focus:ring-2 focus:ring-green-500 outline-none transition" required>
                        <option value="">-- Select Active Site --</option>
                        <?php foreach($projects as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['project_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-bold text-slate-700 mb-2">Work Progress Summary</label>
                    <textarea name="summary" rows="6" class="w-full border border-slate-200 rounded-xl p-4 focus:ring-2 focus:ring-green-500 outline-none transition" placeholder="Describe the tasks completed, labor on site, or any delays..." required></textarea>
                </div>

                <div class="mb-8">
                    <label class="block text-sm font-bold text-slate-700 mb-2">Attach Site Photo (Optional)</label>
                    <input type="file" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100 transition">
                </div>

                <button type="submit" name="submit_report" class="w-full bg-green-500 text-white py-4 rounded-xl font-bold shadow-lg shadow-green-500/30 hover:bg-green-600 active:scale-[0.98] transition-all">
                    Send Report to Office
                </button>
            </form>
        </div>

        <p class="text-center text-slate-400 text-xs mt-8 italic">Reports are automatically timestamped upon submission.</p>
    </div>

</body>
</html>