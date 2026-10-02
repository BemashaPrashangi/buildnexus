<?php
header('Content-Type: application/json');

$requirements = $_POST['prompt'] ?? $_POST['requirements'] ?? '';

// Check if an image was uploaded
if (!isset($_FILES['floor_plan']) || $_FILES['floor_plan']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Please upload a floor plan image first so the system can analyze it.']);
    exit;
}

// ---------------------------------------------------------
//  VISION RECOGNITION API INTEGRATION
//  API Endpoint Configuration
// ---------------------------------------------------------
$api_key = getenv('VISION_API_KEY') ?: 'YOUR_API_KEY_HERE'; 

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=' . $api_key;

// Read the uploaded image file and convert to Base64
$image_path = $_FILES['floor_plan']['tmp_name'];
$image_data = base64_encode(file_get_contents($image_path));
$mime_type = mime_content_type($image_path);

// Build the structured estimation instruction payload
$full_instruction = "You are an expert Civil Engineer and Quantity Surveyor. 
Analyze the attached floor plan sketch/blueprint. The user has also provided these requirements: '$requirements'.
Based on the visual layout (number of rooms, walls, doors, kitchen, bathroom layout) and the user's description, generate a detailed and highly realistic construction/materials estimate.

Return ONLY a pure JSON object in this exact format, with no markdown formatting or backticks:
{
  \"success\": true,
  \"items\": [
    {\"category\": \"Foundation\", \"name\": \"Concrete pouring...\", \"qty\": 1, \"cost\": 50000},
    {\"category\": \"Plumbing\", \"name\": \"Sink installation...\", \"qty\": 2, \"cost\": 15000}
  ]
}";

// Format the payload for the recognition API
$data = [
    "contents" => [
        [
            "parts" => [
                ["text" => $full_instruction],
                [
                    "inline_data" => [
                        "mime_type" => $mime_type,
                        "data" => $image_data
                    ]
                ]
            ]
        ]
    ],
    // Configure parser for pure JSON output
    "generationConfig" => [
        "response_mime_type" => "application/json"
    ]
];

// Send the request via cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
// Skip SSL verification for local development (XAMPP) if needed
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

// If the API responded successfully with content
if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
    $ai_json_text = $result['candidates'][0]['content']['parts'][0]['text'];
    // Strip markdown block formatting if present to safely parse JSON response
    $ai_json_text = preg_replace('/```json\s*|\s*```/', '', $ai_json_text);
    
    // Verify returned payload is valid JSON before sending to frontend
    if (json_decode($ai_json_text) === null) {
        echo json_encode(['error' => 'System returned an invalid estimate format. Please try again with a clearer image.']);
    } else {
        echo $ai_json_text;
    }
} else {
    // PRODUCTION ERROR HANDLING:
    // If the API failed, we must not return fake data. We need to report the real error.
    $error_msg = 'The system failed to analyze the plan. ';
    
    if (isset($result['error']['message'])) {
        $error_msg .= 'API Error: ' . $result['error']['message'];
    } else {
        $error_msg .= 'Please check your API key and billing status.';
    }
    
    echo json_encode(['error' => $error_msg]);
}
?>