<?php
header('Content-Type: text/html; charset=utf-8');
include 'db.php';

// جلب بيانات التاجر من قاعدة البيانات
$user_id = 1; // معرّف التاجر التجريبي في قاعدتك
$result = $conn->query("SELECT live_public_key FROM users WHERE id=$user_id");
$user = $result->fetch_assoc();

// استخدام مفتاح الـ Live الخاص ببوابتك مباشرة
$merchant_public_key = $user['live_public_key']; // سيأخذ تلقائياً pk_live_51Mv9B2xY8 من القاعدة
$mode_badge = "<span style='background:#28a745; color:white; padding:5px 12px; border-radius:4px; font-size:14px; font-weight:bold;'>LIVE MODE (وضع حقيقي)</span>";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>بوابة الدفع المستقلة - Live Mode</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .payment-box { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .payment-box h2 { text-align: center; color: #333; margin-bottom: 5px; }
        .mode-container { text-align: center; margin-bottom: 20px; margin-top: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #666; font-weight: bold; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 16px; }
        .btn-pay { width: 100%; padding: 12px; background: #28a745; border: none; color: white; font-size: 18px; font-weight: bold; border-radius: 5px; cursor: pointer; transition: 0.3s; }
        .btn-pay:hover { background: #218838; }
        .footer-text { text-align: center; font-size: 11px; color: #999; margin-top: 15px; word-break: break-all; }
    </style>
</head>
<body>

<div class="payment-box">
    <h2>بوابة الدفع الإلكتروني</h2>
    <div class="mode-container"><?php echo $mode_badge; ?></div>
    
    <form action="process_payment.php" method="POST">
        <!-- إرسال مفتاح الـ Live الخاص ببوابتك بشكل مخفي -->
        <input type="hidden" name="api_key" value="<?php echo $merchant_public_key; ?>">
        
        <div class="form-group">
            <label>المبلغ المطلوب للدفع (بالجنيه):</label>
            <input type="number" name="amount" value="150.00" min="1" step="0.01" required>
        </div>

        <div class="form-group">
            <label>رقم البطاقة الحقيقية (Visa / MasterCard / Meeza):</label>
            <!-- يمكنك الآن إدخال أي رقم بطاقة حقيقية لتجاوز فحص البنية الرياضية بنجاح -->
            <input type="text" name="card_number" placeholder="أدخل رقم بطاقة صحيح رياضاياً" required>
        </div>

        <div class="form-group" style="display: flex; gap: 10px;">
            <div style="flex: 1;">
                <label>تاريخ الانتهاء:</label>
                <input type="text" name="expiry" placeholder="MM/YY" maxlength="5" required>
            </div>
            <div style="flex: 1;">
                <label>رمز الأمان (CVV):</label>
                <input type="password" name="cvv" placeholder="123" maxlength="3" required>
            </div>
        </div>

        <button type="submit" class="btn-pay">إتمام الدفع في الوضع الحي</button>
    </form>
    <p class="footer-text">🔑 مفتاح الربط النشط: <?php echo $merchant_public_key; ?></p>
</div>

</body>
</html>