<?php
// /hospital/billing/receipt.php
$pageTitle = "Receipt • Hospital";
require_once __DIR__ . "/../includes/auth_guard.php";
require_once __DIR__ . "/../includes/db.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) { die("Invalid receipt id"); }

$stmt = $pdo->prepare("
  SELECT
    b.*,
    p.full_name AS patient_name, p.phone AS patient_phone,
    e.full_name AS employee_name, e.job_title
  FROM bills b
  JOIN patients p ON p.id=b.patient_id
  LEFT JOIN employees e ON e.id=b.employee_id
  WHERE b.id=? LIMIT 1
");
$stmt->execute([$id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$r) die("Receipt not found");

if (($r["status"] ?? "") !== "PAID") die("This bill is not paid yet.");

?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Receipt <?php echo h($r["receipt_no"]); ?></title>
  <style>
    body{ font-family: Arial, sans-serif; margin:20px; color:#111; }
    .box{ max-width:720px; margin:0 auto; border:1px solid #ddd; border-radius:12px; padding:18px; }
    .row{ display:flex; justify-content:space-between; gap:20px; }
    .muted{ color:#555; font-size:13px; }
    .title{ font-size:20px; font-weight:800; margin:0; }
    table{ width:100%; border-collapse:collapse; margin-top:12px; }
    th,td{ text-align:left; padding:10px; border-bottom:1px solid #eee; }
    th{ font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#666; }
    .total{ font-size:18px; font-weight:900; }
    .btns{ display:flex; gap:10px; margin-top:14px; }
    button{ padding:10px 14px; border-radius:10px; border:1px solid #ddd; background:#fff; font-weight:700; cursor:pointer; }
  </style>
</head>
<body>
  <div class="box">
    <div class="row">
      <div>
        <p class="title">Hospital Receipt</p>
        <div class="muted">Receipt No: <b><?php echo h($r["receipt_no"]); ?></b></div>
        <div class="muted">Paid At: <?php echo h(date("Y-m-d H:i", strtotime($r["paid_at"]))); ?></div>
      </div>
      <div style="text-align:right">
        <div class="muted">Status: <b><?php echo h($r["status"]); ?></b></div>
        <div class="muted">Method: <b><?php echo h($r["payment_method"] ?? "-"); ?></b></div>
        <div class="muted">Type: <b><?php echo h($r["bill_type"]); ?></b></div>
      </div>
    </div>

    <hr style="border:none;border-top:1px solid #eee; margin:14px 0;">

    <div class="row">
      <div>
        <div class="muted">Patient</div>
        <div><b><?php echo h($r["patient_name"]); ?></b></div>
        <div class="muted"><?php echo h($r["patient_phone"] ?? "-"); ?></div>
      </div>
      <div style="text-align:right">
        <div class="muted">Handled by</div>
        <div><b><?php echo h($r["employee_name"] ?? "-"); ?></b></div>
        <div class="muted"><?php echo h($r["job_title"] ?? "-"); ?></div>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Description</th>
          <th>Amount</th>
          <th>Discount</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <?php echo h($r["description"] ?? "-"); ?>
            <?php if (!empty($r["prescription_id"])): ?>
              <div class="muted">Prescription #<?php echo (int)$r["prescription_id"]; ?></div>
            <?php endif; ?>
          </td>
          <td>$<?php echo number_format((float)$r["amount"],2); ?></td>
          <td>$<?php echo number_format((float)$r["discount"],2); ?></td>
          <td class="total">$<?php echo number_format((float)$r["total"],2); ?></td>
        </tr>
      </tbody>
    </table>

    <div class="btns">
      <button onclick="window.print()">Print</button>
      <button onclick="window.close()">Close</button>
    </div>
  </div>
</body>
</html>
