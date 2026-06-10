<?php
header('Content-Type: application/json; charset=utf-8');
include 'db.php';

// ضبط التوقيت لمنع ظهور تحذيرات السيرفر
date_default_timezone_set('Africa/Cairo'); 

// تحديث المفاتيح تلقائياً لو وقتها انتهى قبل تقديمها للبايثون
rotateMerchantKeys($conn, 1);

// جلب المفتاح الحي النشط حالياً من القاعدة
$result = $conn->query("SELECT live_public_key FROM users WHERE id = 1");
$user = $result->fetch_assoc();

// إرسال المفتاح للبايثون في شكل JSON نظيف
echo json_encode(array("live_key" => $user['live_public_key']));
?>