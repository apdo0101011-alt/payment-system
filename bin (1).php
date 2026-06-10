<?php
header('Content-Type: application/json; charset=utf-8');

// استقبال الـ BIN من البوت
$bin = isset($_GET['bin']) ? preg_replace('/\D/', '', $_GET['bin']) : '';
$bin = substr($bin, 0, 6);

if (strlen($bin) < 6) {
    echo json_encode(["status" => "error", "message" => "Invalid BIN"]);
    exit;
}

$csv_file = 'bin-list-data.csv';

if (!file_exists($csv_file)) {
    echo json_encode(["status" => "error", "message" => "CSV file missing"]);
    exit;
}

$found = false;
// فتح ملف الـ CSV للقراءة الصافية
if (($handle = fopen($csv_file, "r")) !== FALSE) {
    // قراءة الملف سطر بسطر لتوفير استهلاك الذاكرة
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        // إذا كان العمود الأول (أو العمود الذي يحتوي على الـ BIN) يطابق رقمنا
        if ($data[0] == $bin) {
            echo json_encode([
                "status" => "success",
                "brand" => isset($data[1]) ? strtoupper(trim($data[1])) : "VISA",
                "bank" => isset($data[2]) ? strtoupper(trim($data[2])) : "SECURE LIVE NODE",
                "type" => isset($data[3]) ? strtoupper(trim($data[3])) : "CREDIT",
                "country" => isset($data[4]) ? strtoupper(trim($data[4])) : "UNKNOWN"
            ]);
            $found = true;
            break;
        }
    }
    fclose($handle);
}

// رد احتياطي احترافي في حال لم يعثر على الـ BIN بداخل ملف الـ CSV
if (!$found) {
    $brand = (str_starts_with($bin, '4')) ? "VISA" : "MASTERCARD";
    echo json_encode([
        "status" => "not_found",
        "brand" => $brand,
        "bank" => "SECURE LIVE NODE",
        "type" => "CREDIT",
        "country" => "UNKNOWN"
    ]);
}
?>