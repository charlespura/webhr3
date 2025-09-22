<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    // 🔑 Replace with your actual API key
    $apiKey = "AIzaSyDpq5yUMBi_jn6dSRxSNmzpD3H2Wmv3J14";

    // Read uploaded file
    $fileData = file_get_contents($_FILES['photo']['tmp_name']);
    $base64Image = base64_encode($fileData);

    // Build request for FACE_DETECTION
    $requestBody = json_encode([
        "requests" => [[
            "image" => ["content" => $base64Image],
            "features" => [[ "type" => "FACE_DETECTION", "maxResults" => 1 ]]
        ]]
    ]);

    // Send request
    $ch = curl_init("https://vision.googleapis.com/v1/images:annotate?key=$apiKey");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Face Emotion Detection</title>
</head>
<body>
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <h2>Detection Result</h2>
    <?php if (isset($data['responses'][0]['faceAnnotations'][0])): 
        $face = $data['responses'][0]['faceAnnotations'][0]; ?>
        <p><b>Joy:</b> <?= $face['joyLikelihood'] ?></p>
        <p><b>Anger:</b> <?= $face['angerLikelihood'] ?></p>
        <p><b>Sorrow:</b> <?= $face['sorrowLikelihood'] ?></p>
        <p><b>Surprise:</b> <?= $face['surpriseLikelihood'] ?></p>
        <hr>
        <h3>Your uploaded photo:</h3>
        <img src="data:image/jpeg;base64,<?= $base64Image ?>" width="400">
    <?php else: ?>
        <p>No face detected.</p>
        <pre><?php print_r($data); ?></pre>
    <?php endif; ?>
    <br><a href="index.php">Try Another Photo</a>
<?php else: ?>
    <h2>Upload a Photo</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="photo" accept="image/*" required>
        <button type="submit">Detect Face & Emotion</button>
    </form>
<?php endif; ?>
</body>
</html>
