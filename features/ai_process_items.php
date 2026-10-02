<?php
header('Content-Type: application/json');

$requirement = strtolower($_POST['prompt'] ?? $_POST['requirement'] ?? '');

// Automated Estimation Logic based on project specifications
$estimate_suggestions = [
    ['category' => 'Demolition', 'name' => 'Removal and disposal of existing items', 'cost' => 30000],
    ['category' => 'Cabinetry', 'name' => 'Supply and installation of base cabinets', 'cost' => 144000],
    ['category' => 'Countertops', 'name' => 'Supply and installation of quartz countertops', 'cost' => 440000],
    ['category' => 'Plumbing', 'name' => 'New stainless steel undermount sink', 'cost' => 20000]
];

// If the specification is specifically about painting or floors, we add those
if (strpos($requirement, 'paint') !== false) {
    $estimate_suggestions[] = ['category' => 'Finishing', 'name' => 'Prepare and paint kitchen walls/ceiling', 'cost' => 150];
}

echo json_encode(['success' => true, 'items' => $estimate_suggestions]);