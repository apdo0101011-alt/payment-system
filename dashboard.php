<?php
header('Content-Type: text/html; charset=utf-8');
include 'db.php';
$conn->set_charset("utf8mb4");

$user_id = 1; // التاجر الافتراضي

// تشغيل دالة الفحص والتحديث التلقائي للمفاتيح قبل عرض الصفحة
rotateMerchantKeys($conn, $user_id);

// تحديث الوضع عند الضغط على الزر
if (isset($_POST['toggle_env'])) {
    $current_env = $_POST['current_env'] == 'test' ? 'live' : 'test';
    $conn->query("UPDATE users SET environment='$current_env' WHERE id=$user_id");
}

// جلب بيانات المستخدم الحالية
$result = $conn->query("SELECT * FROM users WHERE id=$user_id");
$user = $result->fetch_assoc();
$env = $user['environment'];

// حساب إجمالي الأرباح في الوضع الحالي
$revenue_result = $conn->query("SELECT SUM(amount) as total FROM transactions WHERE user_id=$user_id AND mode='$env' AND status='success'");
$revenue_row = $revenue_result->fetch_assoc();
$total_revenue = isset($revenue_row['total']) ? $revenue_row['total'] : 0;

// جلب آخر 5 عمليات مسجلة في قاعدة البيانات
$transactions = $conn->query("SELECT * FROM transactions WHERE user_id=$user_id ORDER BY id DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة تحكم البوابة الخاصة بك</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; background: #f4f7f6; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .flex-grid { display: flex; gap: 20px; }
        .col { flex: 1; }
        .btn { padding: 12px 24px; border: none; cursor: pointer; color: white; font-weight: bold; border-radius: 5px; font-size: 15px; }
        .btn-live { background: #28a745; } .btn-test { background: #ffc107; color: black; }
        .key-box { background: #f8f9fa; padding: 10px; margin: 8px 0; font-family: monospace; border-right: 4px solid #007bff; word-break: break-all; direction: ltr; text-align: left; }
        .status-badge { padding: 4px 8px; border-radius: 4px; color: white; font-weight: bold; font-size: 13px; }
        .badge-live { background: #28a745; } .badge-test { background: #ffc107; color: black; }
        .badge-success { background: #28a745; } .badge-failed { background: #dc3545; }
        .stats-num { font-size: 32px; font-weight: bold; color: #333; margin-top: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: right; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; color: #555; }
    </style>
</head>
<body>

<div class="container">
    <div class="flex-grid">
        <!-- الكارد الرئيسي للتحكم بالوضع -->
        <div class="card col" style="flex: 2;">
            <h2>لوحة تحكم المفاتيح (<?php echo htmlspecialchars($user['merchant_name']); ?>)</h2>
            <form method="POST" action="dashboard.php">
                <input type="hidden" name="current_env" value="<?php echo $env; ?>">
                <p>الوضع النشط حالياً: 
                    <span class="status-badge <?php echo $env == 'live' ? 'badge-live' : 'badge-test'; ?>">
                        <?php echo strtoupper($env); ?> MODE
                    </span>
                </p>
                <button type="submit" name="toggle_env" class="btn <?php echo $env == 'live' ? 'btn-test' : 'btn-live'; ?>">
                    التحويل إلى وضع <?php echo $env == 'test' ? 'الحي (Live)' : 'التجريبي (Test)'; ?>
                </button>
            </form>
        </div>

        <!-- كارد الإحصائيات والأرباح -->
        <div class="card col">
            <h3>أرباح وضع (<?php echo strtoupper($env); ?>)</h3>
            <div class="stats-num"><?php echo number_format($total_revenue, 2); ?> <span style="font-size:16px;">EGP</span></div>
        </div>
    </div>

    <!-- كارد المفاتيح البرمجية -->
    <div class="card">
        <h3>مفاتيح الربط البرمجي الافتراضية للـ API</h3>
        <p>مفتاح التجربة العام (Test Public Key):</p>
        <div class="key-box"><?php echo htmlspecialchars($user['test_public_key']); ?></div>
        
        <p>مفتاح الحي العام (Live Public Key):</p>
        <div class="key-box"><?php echo htmlspecialchars($user['live_public_key']); ?></div>
    </div>

    <!-- كارد جدول العمليات الأخيرة -->
    <div class="card">
        <h3>آخر المعاملات المالية المستلمة</h3>
        <table>
            <thead>
                <tr>
                    <th>معرّف العملية</th>
                    <th>المبلغ</th>
                    <th>رقم الكارت</th>
                    <th>الوضع</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transactions->num_rows > 0): ?>
                    <?php while($txn = $transactions->fetch_assoc()): ?>
                        <tr>
                            <td style="font-family: monospace; font-size: 13px;"><?php echo $txn['transaction_id']; ?></td>
                            <strong><td><?php echo $txn['amount']; ?> EGP</td></strong>
                            <td><?php echo $txn['card_number']; ?></td>
                            <td>
                                <span class="status-badge <?php echo $txn['mode'] == 'live' ? 'badge-live' : 'badge-test'; ?>">
                                    <?php echo strtoupper($txn['mode']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $txn['status'] == 'success' ? 'badge-success' : 'badge-failed'; ?>">
                                    <?php echo $txn['status'] == 'success' ? 'ناجحة' : 'فاشلة'; ?>
                                </span>
                            </td>
                            <td style="font-size: 13px; color: #777;"><?php echo $txn['created_at']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #999;">لا توجد معاملات مسجلة حتى الآن.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>