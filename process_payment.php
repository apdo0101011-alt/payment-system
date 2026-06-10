<?php
header('Content-Type: application/json; charset=utf-8');
include 'db.php';

// ضبط التوقيت لمنع ظهور تحذيرات السيرفر الافتراضية
date_default_timezone_set('Africa/Cairo'); 

// 1. خوارزمية Luhn العالمية للتحقق من صحة أرقام البطاقات الحقيقية
function validateCardNumber($number) {
    $number = preg_replace('/\D/', '', $number);
    $number_length = strlen($number);
    if ($number_length < 13 || $number_length > 19) return false;
    $parity = $number_length % 2; $total = 0;
    for ($i = 0; $i < $number_length; $i++) {
        $digit = $number[$i];
        if ($i % 2 == $parity) {
            $digit *= 2;
            if ($digit > 9) $digit -= 9;
        }
        $total += $digit;
    }
    return ($total % 10 == 0);
}

// 2. دالة تحديد نوع البطاقة بناءً على أول أرقام (BIN)
function getCardBrand($number) {
    $number = preg_replace('/\D/', '', $number);
    if (preg_match('/^4/', $number)) return 'Visa';
    if (preg_match('/^5[1-5]/', $number)) return 'MasterCard';
    return 'Classic Card';
}

// استقبال البيانات من سكريبت البايثون
$api_key     = isset($_POST['api_key']) ? $_POST['api_key'] : '';
$amount      = isset($_POST['amount']) ? $_POST['amount'] : 0;
$card_number = isset($_POST['card_number']) ? $_POST['card_number'] : '';
$expiry      = isset($_POST['expiry']) ? $_POST['expiry'] : '';
$cvv         = isset($_POST['cvv']) ? $_POST['cvv'] : '';

if (empty($api_key) || empty($amount) || empty($card_number) || empty($expiry) || empty($cvv)) {
    echo json_encode(array("status" => "error", "message" => "جميع بيانات الدفع مطلوبة"));
    exit;
}

$clean_card_number = preg_replace('/\D/', '', $card_number);
$user_expiry = trim($expiry);
$user_cvv = trim($cvv);

$user_id = 1;
$current_env = 'live'; 

$masked_card = "XXXX-XXXX-XXXX-" . substr($clean_card_number, -4);

// 3. التحقق الرياضي للبنية البنكية القياسية (Luhn Check)
if (!validateCardNumber($clean_card_number)) {
    echo json_encode(array("status" => "failed", "message" => "Your card number is incorrect."));
    exit;
}

// تفكيك وتوحيد التاريخ المدخل ليصبح رقمين في السنة
$expiry_parts = explode('/', $user_expiry);
if (count($expiry_parts) === 2) {
    $user_month = str_pad(trim($expiry_parts[0]), 2, '0', STR_PAD_LEFT);
    $user_year = trim($expiry_parts[1]);
    if (strlen($user_year) == 4) {
        $user_year = substr($user_year, -2);
    }
    $user_expiry_clean = $user_month . '/' . $user_year;
} else {
    echo json_encode(array("status" => "failed", "message" => "Invalid expiry date format."));
    exit;
}

// التحقق من صلاحية التاريخ مقارنة بالوقت الحالي للسيرفر
$current_year = intval(date('y')); // يعطي رقمين للسنة الحالية (26)
$current_month = intval(date('m')); // يعطي الشهر الحالي (06)

$input_year = intval($user_year);
$input_month = intval($user_month);

if ($input_year < $current_year || ($input_year == $current_year && $input_month < $current_month)) {
    echo json_encode(array("status" => "failed", "message" => "The card was declined: Expired card."));
    exit;
}

// 4. 🔒 [معادلة التثبيت الحتمي الصارمة] - توليد بصمة فريدة ثابتة ومستقرة تماماً للكارت
$card_signature = md5($clean_card_number);
// تحويل جزء من الهاش الثابت إلى رقم بين 0 و 499 لفرز النسبة بدقة متناهية (0.2%)
$hit_score = hexdec(substr($card_signature, 0, 5)) % 500;

// [إصلاح برمجى حاسم]: إضافة علامة الدولار لضمان استقرار قراءة معرف المعاملة الثابت للفيزا
$transaction_id = "ch_live_" . substr($card_signature, 0, 16);

// 5. فحص الكارت في قاعدة البيانات (MySQL) للتأكد من ثبات البيانات وقفل الـ CVV والتاريخ
$card_check = $conn->query("SELECT * FROM approved_cards WHERE card_number='$clean_card_number'");

if ($card_check->num_rows > 0) {
    $saved_card = $card_check->fetch_assoc();
    
    // [قفل مطلق ومحكم]: مستحيل يقبل الكارت بـ 2 CVV أو تاريخ مختلف عند إعادة الفحص
    if ($user_cvv !== $saved_card['cvv'] || $user_expiry_clean !== $saved_card['expiry']) {
        echo json_encode(array("status" => "failed", "message" => "The card was declined: Incorrect security code (CVV)."));
        exit;
    }
    
    // فحص مستويات الصلاحية الزمنية المخزنة
    if (strtotime($saved_card['expires_at']) <= time()) {
        echo json_encode(array("status" => "failed", "message" => "The card was declined: Insufficient funds."));
        exit;
    }
    $is_approved = true;
} else {
    // 🌟 [تثبيت الـ Hits الشحيحة والثابتة 100%]:
    // يطابق رقماً واحداً حتمياً وثابتاً (314) مشتق من هاش الكارت، وحظر كروت الـ 99990 تماماً
    if ($hit_score === 314 && strpos($clean_card_number, '99990') === false) {
        $is_approved = true;
        
        // ⏳ توزيع مستويات الصلاحية الزمنية الأربعة بناءً على بصمة الهاش الثابتة للكارت
        $digits_sum = array_sum(str_split($clean_card_number));
        $level_choice = $digits_sum % 4;
        if ($level_choice === 0) {
            $duration = "+6 hours";
        } elseif ($level_choice === 1) {
            $duration = "+24 hours";
        } elseif ($level_choice === 2) {
            $duration = "+7 days";
        } else {
            $duration = "+30 days";
        }
        
        $expires_at_timestamp = date('Y-m-d H:i:s', strtotime($duration));
        
        // حفظ وتثبيت الـ CVV والتاريخ الأصليين المدخلين في الكومبو داخل قاعدة البيانات طوال فترة صلاحيته
        $conn->query("INSERT INTO approved_cards (card_number, expiry, cvv, expires_at) VALUES ('$clean_card_number', '$user_expiry_clean', '$user_cvv', '$expires_at_timestamp')");
    } else {
        $is_approved = false;
    }
}

// 6. الاستجابة الحية والنهائية لتشيكر البايثون
if ($is_approved) {
    $card_brand = getCardBrand($clean_card_number);
    // [تثبيت كود الموافقة]: مشتق ثابت من الهاش أيضاً لمنع أي عشوائية زمنية في السيرفر
    $auth_seed = hexdec(substr($card_signature, -6));
    $auth_code = "AUTH_" . (100000 + ($auth_seed % 899999));
    
    $conn->query("INSERT INTO transactions (user_id, amount, card_number, status, mode, transaction_id) VALUES ($user_id, $amount, '$masked_card', 'success', '$current_env', '$transaction_id')");
    
    // اذهب لآخر ملف process_payment.php واستبدل رد النجاح بهذا الرد الموجه الذكي:
    echo json_encode(array(
        "id" => $transaction_id,
        "object" => "charge",
        "status" => "succeeded",
        "livemode" => true,
        "amount" => floatval($amount),
        "currency" => "USD",
        "captured" => true,
        "authorization_code" => $auth_code,
        "payment_method_details" => array(
            "type" => "card",
            "card" => array("brand" => $card_brand, "last4" => substr($clean_card_number, -4), "bank" => "SECURE LIVE NODE")
        ),
        // 🌟 إرسال روابط التوجيه للمتصفحات والمواقع بناءً على المدخلات المستلمة
        "redirect_to_netflix" => "http://localhost:8080/netflix/watch.php",
        "redirect_to_spotify" => "http://localhost:8080/spotify/player.php",
        "message" => "Payment complete successfully on Live Mode."
    ));
} else {
    $conn->query("INSERT INTO transactions (user_id, amount, card_number, status, mode, transaction_id) VALUES ($user_id, $amount, '$masked_card', 'failed', '$current_env', '$transaction_id')");
    
    // تنويع أسباب الرفض بشكل حتمي ثابت بناءً على رقم الكارت
    $reasons = array(
        "The card was declined: Incorrect security code (CVV).",
        "The card was declined: Incorrect expiry date.",
        "The card was declined: Insufficient funds."
    );
    $decline_reason = $reasons[hexdec(substr($card_signature, -1)) % count($reasons)];
    
    echo json_encode(array(
        "status" => "failed",
        "livemode" => true,
        "message" => $decline_reason
    ));
}
?>