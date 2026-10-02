<?php
require_once '../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = $_POST['project_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $cost = $_POST['cost_impact'];
    $user_id = $_SESSION['user_id'];

    try {
        //
        $sql = "INSERT INTO change_orders (project_id, title, description, cost_impact, status, created_by) 
                VALUES (?, ?, ?, ?, 'Pending', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$project_id, $title, $description, $cost, $user_id]);

        header("Location: change-orders.php?msg=sent_success");
        exit();
    } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
}

$projects = $pdo->query("SELECT id, project_name FROM projects")->fetchAll();
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
    <title>New Change Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">
    <div class="card shadow-sm border-0 m-auto" style="max-width: 600px;">
        <div class="card-body p-4">
            <h4 class="fw-bold mb-4">Create New Change Order</h4>
            <form method="POST">
                <div class="mb-3">
                    <label class="small fw-bold">Select Project</label>
                    <select name="project_id" class="form-select" required>
                        <?php foreach($projects as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g., Lighting Upgrade" required>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">Description</label>
                    <textarea name="description" class="form-control" rows="4" required></textarea>
                </div>
                <div class="mb-4">
                    <label class="small fw-bold">Cost Impact (Rs.)</label>
                    <input type="number" name="cost_impact" class="form-control" value="0" required>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success fw-bold px-4">Create & Notify Client</button>
                    <a href="change-orders.php" class="btn btn-light border px-4">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>