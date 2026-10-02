<?php
require_once '../db.php';
session_start();

// Security: Client or Manager access
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

try {
    // Identify Project ID
    $stmt = $pdo->prepare("SELECT project_id FROM clients WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $project_id = $stmt->fetchColumn() ?: 1; // Default to 1 for mock display

    // Fetch live files
    $stmt_files = $pdo->prepare("SELECT * FROM project_files WHERE project_id = ? ORDER BY created_at DESC");
    $stmt_files->execute([$project_id]);
    $db_files = $stmt_files->fetchAll(PDO::FETCH_ASSOC);

    // Initial Mock Data to match your screenshot
    if (empty($db_files)) {
        $db_files = [
            ['file_name' => 'Floor_Plan_v3.pdf', 'file_size' => '2.1 MB', 'created_at' => '2024-10-25'],
            ['file_name' => 'Kitchen_Moodboard.png', 'file_size' => '850 KB', 'created_at' => '2024-10-22'],
            ['file_name' => 'Contract_Signed.pdf', 'file_size' => '1.2 MB', 'created_at' => '2024-09-15']
        ];
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
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
    <title>File Sharing - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- PURE CSS CUSTOM STYLING --- */
        body { font-family: 'Inter', sans-serif; background-color: #fcfcfc; color: #1e293b; padding: 2rem; }
        .nexus-card { background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; }
        
        /* Upload Area */
        .upload-zone { border: 2px dashed #e2e8f0; border-radius: 12px; padding: 3rem; text-align: center; background: #fff; transition: 0.2s; }
        .upload-zone:hover { border-color: #22c55e; background: #f0fdf4; }
        .btn-browse { background-color: #22c55e; color: #fff; border: none; padding: 10px 24px; border-radius: 6px; font-weight: 600; margin-top: 1rem; }

        /* File List */
        .table thead th { background: #fcfcfc; border-bottom: 1px solid #eee; color: #64748b; font-size: 0.85rem; padding: 1rem; font-weight: 500; }
        .table tbody td { padding: 1.25rem 1rem; border-bottom: 1px solid #f8fafc; vertical-align: middle; font-size: 0.875rem; }
        .file-icon { font-size: 1.25rem; margin-right: 12px; color: #94a3b8; }
    </style>
</head>
<body>

    <h2 class="fw-bold mb-4">Files & Documents</h2>

    <div class="nexus-card">
        <h5 class="fw-bold mb-1">Upload New File</h5>
        <p class="text-muted small mb-4">Share documents, plans, or images with the project team.</p>
        
        <form action="upload_handler.php" method="POST" enctype="multipart/form-data">
            <div class="upload-zone">
                <i class="bi bi-cloud-upload display-4 text-muted mb-3"></i>
                <p class="mb-0">Drag & drop files here or</p>
                <input type="file" name="project_file" id="fileInput" class="d-none" onchange="this.form.submit()">
                <button type="button" class="btn-browse" onclick="document.getElementById('fileInput').click()">Browse Files</button>
                <p class="text-muted small mt-3">Max file size: 25MB</p>
            </div>
        </form>
    </div>

    <div class="nexus-card">
        <h5 class="fw-bold mb-1">Shared Files</h5>
        <p class="text-muted small mb-4">All documents shared on this project.</p>
        
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th width="50%">File Name</th>
                        <th width="15%">Size</th>
                        <th width="25%">Date Added</th>
                        <th width="10%"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($db_files as $file): ?>
                    <tr>
                        <td>
                            <?php 
                                $ext = pathinfo($file['file_name'], PATHINFO_EXTENSION);
                                $icon = ($ext == 'pdf') ? 'bi-file-earmark-pdf' : 'bi-image';
                            ?>
                            <i class="bi <?= $icon ?> file-icon"></i>
                            <span class="fw-medium text-dark"><?= htmlspecialchars($file['file_name']) ?></span>
                        </td>
                        <td class="text-muted"><?= $file['file_size'] ?></td>
                        <td class="text-muted"><?= date('Y-m-d', strtotime($file['created_at'])) ?></td>
                        <td class="text-end pe-3">
                            <i class="bi bi-three-dots" style="cursor:pointer"></i>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>