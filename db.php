<?php
// 🌟 ضبط التوقيت هنا في أول الملف لمنع التحذيرات نهائياً
date_default_timezone_set('Africa/Cairo'); 

$host = "localhost";
$user = "root"; 
$pass = "usbw"; 
$db   = "payment_gate";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}

// داخل ملف db.php
function rotateMerchantKeys($conn, $user_id) {
    $key_lifetime = 86400; // 🌟 تعديل الوقت ليكون 24 ساعة كاملة بدلاً من دقيقة
    
    $result = $conn->query("SELECT keys_created_at FROM users WHERE id = $user_id");
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $created_time = strtotime($row['keys_created_at']);
        $current_time = time();
        
        if (($current_time - $created_time) >= $key_lifetime) {
            $new_pk_test = "pk_test_" . substr(md5(uniqid(rand(), true)), 0, 16);
            $new_sk_test = "sk_test_" . substr(md5(uniqid(rand(), true)), 0, 16);
            $new_pk_live = "pk_live_" . substr(md5(uniqid(rand(), true)), 0, 16);
            $new_sk_live = "sk_live_" . substr(md5(uniqid(rand(), true)), 0, 16);
            
            $conn->query("UPDATE users SET 
                test_public_key = '$new_pk_test', 
                test_secret_key = '$new_sk_test', 
                live_public_key = '$new_pk_live', 
                live_secret_key = '$new_sk_live',
                keys_created_at = CURRENT_TIMESTAMP
                WHERE id = $user_id");
        }
    }
}
?>