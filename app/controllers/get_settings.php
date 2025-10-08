<?php
require_once '../../config/config.php';
checkAuth();

// Prevent any output before headers
ob_start();

header('Content-Type: application/json');

try {
    // First check if settings table exists
    $tableExists = false;
    try {
        $stmt = $conn->query("SHOW TABLES LIKE 'settings'");
        $tableExists = $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        throw new Exception("Could not check settings table: " . $e->getMessage());
    }

    // Create settings table if it doesn't exist
    if (!$tableExists) {
        $conn->exec("CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Insert default settings
        $defaultSettings = [
            'shop_name' => 'POSFlix',
            'shop_address' => '123 Main Street',
            'shop_phone' => '123-456-7890',
            'currency' => 'USD',
            'tax_rate' => '10',
            'footer_text' => 'Thank you for your purchase!'
        ];

        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($defaultSettings as $key => $value) {
            $stmt->execute([$key, $value]);
        }
    }

    // Get all settings
    $stmt = $conn->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // If no settings found, use defaults
    if (empty($settings)) {
        $settings = [
            'shop_name' => 'POSFlix',
            'shop_address' => '123 Main Street',
            'shop_phone' => '123-456-7890',
            'currency' => 'USD',
            'tax_rate' => '10',
            'footer_text' => 'Thank you for your purchase!'
        ];
    }

    // Clear any buffered output
    ob_clean();
    
    // Return settings
    echo json_encode($settings);

} catch (Exception $e) {
    // Clear any buffered output
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

// End output buffer
ob_end_flush();