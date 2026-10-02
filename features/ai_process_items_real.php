<?php
header('Content-Type: application/json');

$apiKey = 'YOUR_API_KEY'; // Replace with your real key
$userRequirement = $_POST['prompt'] ?? $_POST['requirement'] ?? '';

$data = [
    "model" => "gpt-4o-mini",
    "messages" => [
        ["role" => "system", "content" => "You are BuildNexus Assistant. Convert the user's construction request into a JSON array. Each object must have: 'name', 'unit', 'cost' (numerical in Sri Lankan Rupees), and 'tax' (percentage). Output ONLY the JSON array."],
        ["role" => "user", "content" => "Generate an estimate for: " . $userRequirement]
    ],
    "response_format" => ["type" => "json_object"]
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);

$result = curl_exec($ch);
$decoded = json_decode($result, true);

// Extract the structured JSON content
$jsonContent = $decoded['choices'][0]['message']['content'] ?? '{}';
echo $jsonContent; // This returns the structured items back to estimates_new.php
?>