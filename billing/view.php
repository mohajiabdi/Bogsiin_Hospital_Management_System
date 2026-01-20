<?php
// /hospital/billing/view.php
$pageTitle = "Billing • Hospital";
require_once __DIR__ . "/../includes/auth_guard.php";
require_once __DIR__ . "/../includes/db.php";

if (session_status() === PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function flash_set($type, $msg){ $_SESSION["flash"] = ["type"=>$type, "msg"=>$msg]; }
function flash_get(){ if (!isset($_SESSION["flash"])) return null; $f=$_SESSION["flash"]; unset($_SESSION["flash"]); return $f; }

function money2($v): float { return round((float)$v, 2); }

function make_receipt_no(): string {
  $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
  return "RCPT-" . date("Ymd") . "-" . $rand;
}

/**
 * Calculate prescription total from prescription_items + pharmacy_items.unit_price
 * Returns: ["ok"=>bool, "patient_id"=>int, "employee_id"=>int, "amount"=>float, "desc"=>string]
 */
function compute_prescription_bill(PDO $pdo, int $prescriptionId): array {
  $stmt = $pdo->prepare("
    SELECT pr.id, pr.patient_id, pr.prescribed_by_employee_id,
           p.full_name AS patient_name, p.phone AS patient_phone
    FROM prescriptions pr
    JOIN patients p ON p.id = pr.patient_id
    WHERE pr.id=? LIMIT 1
  ");
  $stmt->execute([$prescriptionId]);
  $pr = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$pr) return ["ok"=>false, "error"=>"Prescription not found"];

  $stmt2 = $pdo->prepare("
    SELECT COALESCE(SUM(pi.unit_price * pri.quantity),0) AS total_amount
    FROM prescription_items pri
    JOIN pharmacy_items pi ON pi.id = pri.pharmacy_item_id
    WHERE pri.prescription_id=?
  ");
  $stmt2->execute([$prescriptionId]);
  $amount = (float)$stmt2->fetchColumn();
  $amount = money2($amount);

  $desc = "Prescription #".$prescriptionId;

  return [
    "ok"=>true,
    "patient_id" => (int)$pr["patient_id"],
    "employee_id" => (int)$pr["prescribed_by_employee_id"],
    "amount" => $amount,
    "desc" => $desc,
  ];
}

/** Ensure ONE-TIME prescription bill (create if not exists) */
function ensure_prescription_bill(PDO $pdo, int $prescriptionId): ?int {
  // if already exists, return it
  $chk = $pdo->prepare("SELECT id, status FROM bills WHERE prescription_id=? AND bill_type='PRESCRIPTION' LIMIT 1");
  $chk->execute([$prescriptionId]);
  $row = $chk->fetch(PDO::FETCH_ASSOC);
  if ($row) return (int)$row["id"];

  $calc = compute_prescription_bill($pdo, $prescriptionId);
  if (!$calc["ok"]) return null;

  $amount = $calc["amount"];
  // If prescription has zero cost, still create bill as UNPAID with amount 0? Up to you.
  // We'll allow it.
  $discount = 0.00;
  $total = money2($amount - $discount);

  $ins = $pdo->prepare("
    INSERT INTO bills (appointment_id, patient_id, employee_id, prescription_id, bill_type, description, amount, discount, total, status, payment_method, receipt_no, paid_at)
    VALUES (NULL, :patient_id, :employee_id, :prescription_id, 'PRESCRIPTION', :desc, :amount, :discount, :total, 'UNPAID', NULL, NULL, NULL)
  ");
  $ins->execute([
    ":patient_id" => $calc["patient_id"],
    ":employee_id" => $calc["employee_id"] ?: null,
    ":prescription_id" => $prescriptionId,
    ":desc" => $calc["desc"],
    ":amount" => $amount,
    ":discount" => $discount,
    ":total" => $total,
  ]);

  return (int)$pdo->lastInsertId();
}

// ---------------- POST handlers ----------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "";

    // ================= DELETE BILL (UNPAID ONLY) =================
  if ($action === "delete_bill") {
    $id = (int)($_POST["id"] ?? 0);

    if ($id <= 0) { flash_set("error","Invalid bill ID."); header("Location:/hospital/billing/view.php"); exit; }

    $stmt = $pdo->prepare("SELECT id, status FROM bills WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $b = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$b) { flash_set("error","Bill not found."); header("Location:/hospital/billing/view.php"); exit; }

    if (($b["status"] ?? "") !== "UNPAID") {
      flash_set("error","Cannot delete paid bill.");
      header("Location:/hospital/billing/view.php"); exit;
    }

    $del = $pdo->prepare("DELETE FROM bills WHERE id=?");
    $del->execute([$id]);

    flash_set("success","Bill deleted.");
    header("Location:/hospital/billing/view.php"); exit;
  }

  // ================= UPDATE BILL (EDIT) - UNPAID ONLY =================
  if ($action === "update_bill") {
    $id = (int)($_POST["id"] ?? 0);
    $description = trim($_POST["description"] ?? "");
    $amount = money2($_POST["amount"] ?? 0);
    $discount = money2($_POST["discount"] ?? 0);

    if ($id <= 0) { flash_set("error","Invalid bill ID."); header("Location:/hospital/billing/view.php"); exit; }

    $stmt = $pdo->prepare("SELECT id, bill_type, status, amount AS old_amount FROM bills WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $b = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$b) { flash_set("error","Bill not found."); header("Location:/hospital/billing/view.php"); exit; }

    if (($b["status"] ?? "") !== "UNPAID") {
      flash_set("error","You can only edit UNPAID bills.");
      header("Location:/hospital/billing/view.php"); exit;
    }

    $billType = $b["bill_type"] ?? "OTHER";

    // Don’t allow editing prescription bills here
    if ($billType === "PRESCRIPTION") {
      flash_set("error","Prescription bills cannot be edited here.");
      header("Location:/hospital/billing/view.php"); exit;
    }

    // enforce description
    if ($description === "" && $billType !== "CONSULTATION" && $billType !== "SURGERY") {
      flash_set("error","Description is required.");
      header("Location:/hospital/billing/view.php"); exit;
    }

    // enforce fixed consultation
    if ($billType === "CONSULTATION") {
      $amount = 10.00;
      if ($description === "") $description = "Consultation fee";
    }

    // keep surgery amount unchanged (cost from DB)
    if ($billType === "SURGERY") {
      $amount = money2($b["old_amount"] ?? 0);
      if ($description === "") $description = "Surgery";
    }

    if ($amount < 0) $amount = 0;
    if ($discount < 0) $discount = 0;

    // Limit discount to 20%
    $maxDiscount = money2($amount * 0.20);
    if ($discount > $maxDiscount) $discount = $maxDiscount;

    $total = money2($amount - $discount);

    $u = $pdo->prepare("
      UPDATE bills
      SET description=?,
          amount=?,
          discount=?,
          total=?
      WHERE id=? AND status='UNPAID'
    ");
    $u->execute([$description, $amount, $discount, $total, $id]);

    flash_set("success","Bill updated.");
    header("Location:/hospital/billing/view.php"); exit;
  }


  // Add OTHER/CONSULTATION/SURGERY manual bill (walk-in supported)
  if ($action === "add_bill") {
    $patient_id = (int)($_POST["patient_id"] ?? 0);
    $bill_type = trim($_POST["bill_type"] ?? "OTHER");
    $description = trim($_POST["description"] ?? "");
    $amount = money2($_POST["amount"] ?? 0);
    $discount = money2($_POST["discount"] ?? 0);

    $allowedTypes = ["CONSULTATION","PRESCRIPTION","SURGERY","OTHER"];
    if (!in_array($bill_type, $allowedTypes, true)) $bill_type = "OTHER";

    if ($patient_id <= 0) { flash_set("error","Select a patient."); header("Location:/hospital/billing/view.php"); exit; }
    // For CONSULTATION and SURGERY description may be auto-filled
    if ($description === "" && !in_array($bill_type, ['CONSULTATION','SURGERY'], true)) { flash_set("error","Description is required."); header("Location:/hospital/billing/view.php"); exit; }
    if ($amount < 0) { flash_set("error","Amount must be >= 0."); header("Location:/hospital/billing/view.php"); exit; }
    if ($discount < 0) $discount = 0;
    // enforce consultation fixed price
    if ($bill_type === 'CONSULTATION') {
      $amount = money2(10.00);
      if ($description === '') $description = 'Consultation fee';
    }
    // handle surgery: take cost from surgery_items
    if ($bill_type === 'SURGERY') {
      $surgery_item_id = (int)($_POST['surgery_item_id'] ?? 0);
      if ($surgery_item_id <= 0) {
        flash_set('error','Select a surgery item.'); header('Location:/hospital/billing/view.php'); exit;
      }
      // doctor handling: require employee_id (doctor)
      $employee_id = (int)($_POST['employee_id'] ?? 0);
      if ($employee_id <= 0) { flash_set('error','Select the doctor performing the surgery.'); header('Location:/hospital/billing/view.php'); exit; }
      $sstmt = $pdo->prepare('SELECT id, surgery_name, cost FROM surgery_items WHERE id=? LIMIT 1');
      $sstmt->execute([$surgery_item_id]);
      $si = $sstmt->fetch(PDO::FETCH_ASSOC);
      if (!$si) { flash_set('error','Invalid surgery selected.'); header('Location:/hospital/billing/view.php'); exit; }
      $amount = money2($si['cost']);
      if ($description === '') $description = $si['surgery_name'];
      $dchk = $pdo->prepare('SELECT id, job_title, status FROM employees WHERE id=? LIMIT 1');
      $dchk->execute([$employee_id]);
      $drow = $dchk->fetch(PDO::FETCH_ASSOC);
      if (!$drow || ($drow['job_title'] ?? '') !== 'DOCTOR' || ($drow['status'] ?? '') !== 'ACTIVE') {
        flash_set('error','Invalid or inactive doctor selected.'); header('Location:/hospital/billing/view.php'); exit;
      }
    } else {
      $employee_id = null;
    }

   // Limit discount to 20% of amount
$maxDiscount = money2($amount * 0.20);
if ($discount > $maxDiscount) $discount = $maxDiscount;

$total = money2($amount - $discount);

    // IMPORTANT: if user tries to create PRESCRIPTION bill manually without prescription_id, block it
    if ($bill_type === "PRESCRIPTION") {
      flash_set("error","Prescription bills must be created from the prescription page (linked to prescription_id).");
      header("Location:/hospital/billing/view.php"); exit;
    }

    $stmt = $pdo->prepare("
      INSERT INTO bills (appointment_id, patient_id, employee_id, prescription_id, bill_type, description, amount, discount, total, status, payment_method, receipt_no, paid_at)
      VALUES (NULL, :patient_id, NULL, NULL, :bill_type, :description, :amount, :discount, :total, 'UNPAID', NULL, NULL, NULL)
    ");
      $stmt = $pdo->prepare("\n      INSERT INTO bills (appointment_id, patient_id, employee_id, prescription_id, bill_type, description, amount, discount, total, status, payment_method, receipt_no, paid_at)\n      VALUES (NULL, :patient_id, :employee_id, NULL, :bill_type, :description, :amount, :discount, :total, 'UNPAID', NULL, NULL, NULL)\n    ");
      $stmt->execute([
        ":patient_id"=>$patient_id,
        ":employee_id"=>($employee_id ?: null),
        ":bill_type"=>$bill_type,
        ":description"=>$description,
        ":amount"=>$amount,
        ":discount"=>$discount,
        ":total"=>$total
      ]);

    flash_set("success","Bill added.");
    header("Location:/hospital/billing/view.php"); exit;
  }

  // Pay bill (generate receipt for all PAID bills)
  if ($action === "pay_bill") {
    $id = (int)($_POST["id"] ?? 0);
    $status = trim($_POST["status"] ?? "UNPAID");
    $method = trim($_POST["payment_method"] ?? "");

    $allowedStatus = ["UNPAID","PAID"];
    $allowedMethod = ["","CASH","EVCPLUS","CARD","BANK"];
    if (!in_array($status, $allowedStatus, true)) $status = "UNPAID";
    if (!in_array($method, $allowedMethod, true)) $method = "";

    $stmt = $pdo->prepare("SELECT * FROM bills WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $b = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$b) { flash_set("error","Bill not found."); header("Location:/hospital/billing/view.php"); exit; }

    // Prevent paying a bill that's already PAID
    if ((($b["status"] ?? "") === "PAID") && $status === "PAID") {
      flash_set("error","This bill is already paid.");
      $prescId = (int)($b["prescription_id"] ?? 0);
      if ($prescId > 0) { header("Location:/hospital/billing/view.php?prescription_id=".$prescId); exit; }
      header("Location:/hospital/billing/view.php"); exit;
    }

    // If it's prescription and already PAID, prevent double-charging
    if (($b["bill_type"] ?? "") === "PRESCRIPTION" && ($b["status"] ?? "") === "PAID" && $status === "PAID") {
      flash_set("error","This prescription is already paid (one-time payment).");
      header("Location:/hospital/billing/view.php?prescription_id=".((int)$b["prescription_id"])); exit;
    }

    if ($status === "PAID") {
      if ($method === "") {
        flash_set("error","Select payment method to complete payment.");
        header("Location:/hospital/billing/view.php"); exit;
      }

      $receipt = $b["receipt_no"];
      if (!$receipt) {
        for ($i=0; $i<5; $i++) {
          $candidate = make_receipt_no();
          $chk = $pdo->prepare("SELECT COUNT(*) FROM bills WHERE receipt_no=?");
          $chk->execute([$candidate]);
          if ((int)$chk->fetchColumn() === 0) { $receipt = $candidate; break; }
        }
        if (!$receipt) $receipt = make_receipt_no();
      }

      $u = $pdo->prepare("
        UPDATE bills
        SET status='PAID',
            payment_method=?,
            receipt_no=?,
            paid_at=COALESCE(paid_at, NOW())
        WHERE id=?
      ");
      $u->execute([($method===""?null:$method), $receipt, $id]);

      // redirect back to prescription billing if this bill is linked
      $prescId = (int)($b["prescription_id"] ?? 0);
      flash_set("success","Payment saved. Receipt generated.");
      if ($prescId > 0) { header("Location:/hospital/billing/view.php?prescription_id=".$prescId); exit; }

      header("Location:/hospital/billing/view.php"); exit;
    }

    // Set UNPAID (keep receipt for audit is better, but you asked "one time": we just set UNPAID without deleting receipt)
    $u = $pdo->prepare("UPDATE bills SET status='UNPAID', payment_method=? WHERE id=?");
    $u->execute([($method===""?null:$method), $id]);

    flash_set("success","Bill updated.");
    header("Location:/hospital/billing/view.php"); exit;
  }

  header("Location:/hospital/billing/view.php"); exit;
}





// ---------------- If coming from prescription page ----------------
$prescription_id = (int)($_GET["prescription_id"] ?? 0);
$focusBillId = null;
if ($prescription_id > 0) {
  $focusBillId = ensure_prescription_bill($pdo, $prescription_id);
  if (!$focusBillId) {
    flash_set("error", "Cannot create bill for this prescription.");
    header("Location:/hospital/billing/view.php"); exit;
  }
}

// ---------------- Filters (fixed like prescription) ----------------
$q = trim($_GET["q"] ?? "");
$st = trim($_GET["st"] ?? "");
$pm = trim($_GET["pm"] ?? "");
$type = trim($_GET["type"] ?? "");
$from = trim($_GET["from"] ?? "");
$to = trim($_GET["to"] ?? "");
$sort = trim($_GET["sort"] ?? "");

$where = [];
$params = [];

if ($prescription_id > 0) {
  $where[] = "b.prescription_id = :presc_id";
  $params[":presc_id"] = $prescription_id;
}

if ($st !== "" && in_array($st, ["UNPAID","PAID"], true)) { $where[]="b.status=:st"; $params[":st"]=$st; }
if ($pm !== "" && in_array($pm, ["CASH","EVCPLUS","CARD","BANK"], true)) { $where[]="b.payment_method=:pm"; $params[":pm"]=$pm; }
if ($type !== "" && in_array($type, ["CONSULTATION","PRESCRIPTION","SURGERY","OTHER"], true)) { $where[]="b.bill_type=:bt"; $params[":bt"]=$type; }
if ($from !== "") { $where[]="b.created_at >= :fromdt"; $params[":fromdt"]=$from." 00:00:00"; }
if ($to !== "") { $where[]="b.created_at <= :todt"; $params[":todt"]=$to." 23:59:59"; }

if ($q !== "") {
  // Use distinct parameter names to avoid issues on drivers that don't
  // support reusing the same named parameter multiple times.
  $where[] = "(p.full_name LIKE :q1 OR p.phone LIKE :q2 OR b.description LIKE :q3 OR b.receipt_no LIKE :q4)";
  $like = "%$q%";
  $params[":q1"] = $like;
  $params[":q2"] = $like;
  $params[":q3"] = $like;
  $params[":q4"] = $like;
}

$whereSql = $where ? ("WHERE ".implode(" AND ", $where)) : "";

// sorting
$allowedSort = [
  'id_asc' => 'b.id ASC',
  'id_desc' => 'b.id DESC',
  'patient_asc' => 'p.full_name ASC',
  'patient_desc' => 'p.full_name DESC',
  'created_asc' => 'b.created_at ASC',
  'created_desc' => 'b.created_at DESC',
];
$orderBy = $allowedSort[$sort] ?? 'b.created_at DESC';

// ---------------- Dropdown patients (walk-in) ----------------
// Patients (for client-side search), Surgery items and Doctors
$patients = $pdo->query("SELECT id, full_name, phone FROM patients ORDER BY full_name ASC LIMIT 2000")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$surgeryItems = $pdo->query("SELECT id, surgery_name, cost FROM surgery_items ORDER BY surgery_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$doctors = $pdo->query("SELECT id, full_name, status FROM employees WHERE job_title='DOCTOR' AND status='ACTIVE' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// ---------------- Stats ----------------
$totalBills = (int)$pdo->query("SELECT COUNT(*) FROM bills")->fetchColumn();
$unpaidBills = (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='UNPAID'")->fetchColumn();
$paidBills = (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='PAID'")->fetchColumn();
$incomeTotal = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM bills WHERE status='PAID'")->fetchColumn();

// ---------------- List ----------------
$sql = "
  SELECT
    b.*,
    p.full_name AS patient_name, p.phone AS patient_phone,
    e.full_name AS employee_name, e.job_title
  FROM bills b
  JOIN patients p ON p.id=b.patient_id
  LEFT JOIN employees e ON e.id=b.employee_id
  $whereSql
  ORDER BY " . $orderBy . "
  LIMIT 300
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$flash = flash_get();
include_once __DIR__ . "/../includes/header.php";
?>
 <div class="min-h-screen bg-slate-50 md:pl-72">
<div class="md:flex min-h-screen">
  <?php include __DIR__ . "/../includes/sidebar.php"; ?>

  <div class="flex-1">
    <div class="mx-auto max-w-6xl px-4 py-6">

      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
          <div class="text-2xl font-extrabold tracking-tight">Billing</div>
          <div class="text-sm font-semibold text-slate-500">
            <?php if ($prescription_id>0): ?>
              Billing for Prescription #<?php echo (int)$prescription_id; ?> (one-time payment)
            <?php else: ?>
              Walk-in & general billing (no appointment required)
            <?php endif; ?>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <a href="/hospital/billing/view.php" class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50">Refresh</a>
          <?php if ($prescription_id<=0): ?>
            <button id="openAddBillModal"
              class="rounded-2xl bg-orange-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-orange-600">
              + Add Bill
            </button>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($flash): ?>
        <div class="mt-4 rounded-2xl border px-4 py-3 text-sm font-semibold
          <?php echo $flash["type"] === "success" ? "border-green-200 bg-green-50 text-green-700" : "border-red-200 bg-red-50 text-red-700"; ?>">
          <?php echo h($flash["msg"]); ?>
        </div>
      <?php endif; ?>

      <section class="mt-6 grid gap-4 md:grid-cols-4">
        <div class="rounded-3xl border bg-white p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">TOTAL BILLS</div>
          <div class="mt-2 text-3xl font-extrabold"><?php echo $totalBills; ?></div>
        </div>
        <div class="rounded-3xl border bg-orange-50 p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">UNPAID</div>
          <div class="mt-2 text-3xl font-extrabold"><?php echo $unpaidBills; ?></div>
        </div>
        <div class="rounded-3xl border bg-emerald-50 p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">PAID</div>
          <div class="mt-2 text-3xl font-extrabold"><?php echo $paidBills; ?></div>
        </div>
        <div class="rounded-3xl border bg-sky-50 p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">TOTAL INCOME</div>
          <div class="mt-2 text-3xl font-extrabold">$<?php echo number_format($incomeTotal, 2); ?></div>
        </div>
      </section>

      <!-- Filters -->
      <section class="mt-6 rounded-3xl border bg-white p-4 shadow-sm">
        <form class="grid gap-3 lg:grid-cols-12" method="GET">
          <?php if ($prescription_id>0): ?>
            <input type="hidden" name="prescription_id" value="<?php echo (int)$prescription_id; ?>">
          <?php endif; ?>

          <div class="lg:col-span-5">
            <div class="flex items-center gap-2 rounded-2xl border bg-white px-4 py-3">
              <span class="text-slate-400">🔎</span>
              <input name="q" value="<?php echo h($q); ?>"
                class="w-full bg-transparent text-sm outline-none"
                placeholder="Search patient, phone, receipt, description..." />
            </div>
          </div>

          <div class="lg:col-span-2">
            <select name="type" class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">Type</option>
              <?php foreach (["CONSULTATION","PRESCRIPTION","SURGERY","OTHER"] as $t): ?>
                <option value="<?php echo h($t); ?>" <?php echo $type===$t?"selected":""; ?>><?php echo h($t); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="lg:col-span-2">
            <select name="st" class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">Status</option>
              <option value="UNPAID" <?php echo $st==="UNPAID"?"selected":""; ?>>UNPAID</option>
              <option value="PAID" <?php echo $st==="PAID"?"selected":""; ?>>PAID</option>
            </select>
          </div>

          <div class="lg:col-span-2">
            <select name="pm" class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">Payment</option>
              <?php foreach (["CASH","EVCPLUS","CARD","BANK"] as $m): ?>
                <option value="<?php echo h($m); ?>" <?php echo $pm===$m?"selected":""; ?>><?php echo h($m); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="lg:col-span-1">
            <input type="date" name="from" value="<?php echo h($from); ?>"
              class="w-full rounded-2xl border bg-white px-3 py-3 text-sm font-semibold outline-none">
          </div>
          <div class="lg:col-span-1">
            <input type="date" name="to" value="<?php echo h($to); ?>"
              class="w-full rounded-2xl border bg-white px-3 py-3 text-sm font-semibold outline-none">
          </div>

          <div class="lg:col-span-12">
            <?php if ($from || $to): ?>
              <div class="text-xs text-slate-500 mt-1">Date range: <span class="font-extrabold text-slate-700"><?php echo ($from?:'start'); ?> → <?php echo ($to?:'end'); ?></span></div>
            <?php endif; ?>
          </div>

          <div class="lg:col-span-12 flex items-center justify-between pt-1 text-sm">
            <div class="font-semibold text-slate-600">Showing:
              <span class="font-extrabold text-slate-900"><?php echo count($rows); ?></span>
            </div>
            <div class="flex items-center gap-2">
              <select name="sort" class="rounded-2xl border bg-white px-3 py-2 text-sm font-semibold outline-none">
                <option value="">Sort</option>
                <option value="id_desc" <?php echo $sort==='id_desc'?'selected':''; ?>>ID ↓</option>
                <option value="id_asc" <?php echo $sort==='id_asc'?'selected':''; ?>>ID ↑</option>
                <option value="patient_asc" <?php echo $sort==='patient_asc'?'selected':''; ?>>Patient A→Z</option>
                <option value="patient_desc" <?php echo $sort==='patient_desc'?'selected':''; ?>>Patient Z→A</option>
                <option value="created_desc" <?php echo $sort==='created_desc'?'selected':''; ?>>Newest</option>
                <option value="created_asc" <?php echo $sort==='created_asc'?'selected':''; ?>>Oldest</option>
              </select>

              <button class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50" type="submit">Filter</button>
              <a href="<?php echo $prescription_id>0 ? "/hospital/billing/view.php?prescription_id=".$prescription_id : "/hospital/billing/view.php"; ?>"
                 class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50">Reset</a>
            </div>
          </div>
        </form>
      </section>

      <!-- Bills table -->
      <section class="mt-6 rounded-3xl border bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
           <thead class="bg-slate-50 text-xs font-extrabold tracking-widest text-slate-500">
  <tr>
    <th class="px-5 py-4">BILL</th>
    <th class="px-5 py-4">PATIENT</th>
    <th class="px-5 py-4">TYPE</th>
    <th class="px-5 py-4">TOTAL</th>
    <th class="px-5 py-4">STATUS</th>
    <th class="px-5 py-4">RECEIPT</th>
    <th class="px-5 py-4 text-right">PAY / UPDATE</th>
    <th class="px-5 py-4 text-right">ACTIONS</th> <!-- New column -->
  </tr>
</thead>

            <tbody class="divide-y">
              <?php if (!$rows): ?>
                <tr><td colspan="7" class="px-5 py-10 text-center text-slate-500 font-semibold">No bills found.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <?php
                    $badge = ($r["status"] === "PAID") ? "bg-emerald-100 text-emerald-700" : "bg-orange-100 text-orange-700";
                    $receipt = $r["receipt_no"] ?? "";
                    $isPaid = (($r["status"] ?? "") === "PAID");
                  ?>
                  <tr class="hover:bg-slate-50/60 <?php echo ($focusBillId && (int)$r["id"]===$focusBillId) ? "bg-amber-50/60" : ""; ?>">
                    <td class="px-5 py-4">
                      <div class="font-extrabold text-slate-900">#<?php echo (int)$r["id"]; ?></div>
                      <div class="text-xs font-semibold text-slate-500">
                        <?php echo h($r["description"] ?? "—"); ?>
                        • <?php echo h(date("Y-m-d H:i", strtotime($r["created_at"]))); ?>
                      </div>
                      <?php if (!empty($r["prescription_id"])): ?>
                        <div class="text-xs font-semibold text-slate-500">Prescription: #<?php echo (int)$r["prescription_id"]; ?></div>
                      <?php endif; ?>
                    </td>

                    <td class="px-5 py-4">
                      <div class="font-extrabold text-slate-900"><?php echo h($r["patient_name"]); ?></div>
                      <div class="text-xs font-semibold text-slate-500"><?php echo h($r["patient_phone"] ?? "-"); ?></div>
                    </td>

                    <td class="px-5 py-4 font-extrabold text-slate-700"><?php echo h($r["bill_type"]); ?></td>

                    <td class="px-5 py-4">
                      <div class="text-lg font-extrabold text-slate-900">$<?php echo number_format((float)$r["total"], 2); ?></div>
                      <div class="text-xs font-semibold text-slate-500">
                        Amt: $<?php echo number_format((float)$r["amount"], 2); ?>
                        • Disc: $<?php echo number_format((float)$r["discount"], 2); ?>
                      </div>
                    </td>

                    <td class="px-5 py-4">
                      <span class="inline-flex rounded-full px-3 py-1 text-xs font-extrabold <?php echo $badge; ?>">
                        <?php echo h($r["status"]); ?>
                      </span>
                      <?php if (!empty($r["paid_at"])): ?>
                        <div class="mt-1 text-xs font-semibold text-slate-500">
                          <?php echo h(date("Y-m-d H:i", strtotime($r["paid_at"]))); ?>
                        </div>
                      <?php endif; ?>
                    </td>

                   <td class="px-5 py-4">
  <?php if ($isPaid && $receipt): ?>
    <a href="/hospital/billing/receipt.php?id=<?php echo (int)$r["id"]; ?>" target="_blank"
       class="grid h-10 place-items-center rounded-2xl bg-sky-600 px-3 text-xs font-extrabold text-white hover:bg-sky-700">
      RECEIPT
    </a>
  <?php else: ?>
    <span class="text-sm font-semibold text-slate-400">—</span>
  <?php endif; ?>
</td>


                    <td class="px-5 py-4">
                      <div class="flex justify-end gap-2">
                        <?php if ($isPaid): ?>
                          <span class="inline-flex h-10 items-center rounded-2xl border bg-emerald-50 px-4 py-2 text-sm font-extrabold text-emerald-700">PAID</span>
                        <?php else: ?>
                          <button type="button"
                            class="grid h-10 w-10 place-items-center rounded-2xl border bg-white hover:bg-slate-50 open-pay"
                            title="Pay / Update"
                            data-id="<?php echo (int)$r['id']; ?>"
                            data-total="<?php echo h((float)$r['total']); ?>"
                            data-status="<?php echo h($r['status'] ?? 'UNPAID'); ?>"
                            data-method="<?php echo h($r['payment_method'] ?? ''); ?>"
                            data-type="<?php echo h($r['bill_type'] ?? ''); ?>">💳</button>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td class="px-5 py-4 text-right">
  <?php if (!$isPaid): ?>
    <div class="flex justify-end gap-2">
      <!-- Edit button -->
     <button type="button"
  class="open-edit grid h-10 w-10 place-items-center rounded-2xl border bg-white hover:bg-slate-50 cursor-pointer"
  title="Edit bill"
  data-id="<?php echo (int)$r['id']; ?>"
  data-type="<?php echo h($r['bill_type'] ?? ''); ?>"
  data-description="<?php echo h($r['description']); ?>"
  data-amount="<?php echo h((float)$r['amount']); ?>"
  data-discount="<?php echo h((float)$r['discount']); ?>">
  ✏️
</button>



      <!-- Delete button -->
      <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this bill?');">
        <input type="hidden" name="action" value="delete_bill">
        <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
        <button type="submit" name="submit_delete"
          class="grid h-10 w-10 place-items-center rounded-2xl border border-rose-200 bg-rose-50 hover:bg-rose-100">
          🗑️
        </button>
      </form>
    </div>
  <?php else: ?>
    <span class="text-sm font-semibold text-slate-400">—</span>
  <?php endif; ?>
</td>

                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

    </div>
  </div>
</div>

<!-- Modal: Add Bill (walk-in) -->
<div id="addBillModal" class="fixed inset-0 z-50 hidden">
  <div id="addBillOverlay" class="absolute inset-0 bg-slate-900/50 backdrop-blur-[1px]"></div>

  <div class="relative mx-auto flex min-h-full max-w-3xl items-center justify-center p-4">
    <div class="w-full max-w-2xl rounded-3xl bg-white shadow-2xl ring-1 ring-black/5 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between border-b px-6 py-4">
        <div class="text-lg font-extrabold">Add bill (walk-in)</div>
        <button type="button" id="closeAddBill"
          class="grid h-10 w-10 place-items-center rounded-2xl hover:bg-slate-50 text-slate-500">✕</button>
      </div>

      <form method="POST" class="p-6">
        <input type="hidden" name="action" value="add_bill">

        <div class="grid gap-4 md:grid-cols-2">
          <div class="md:col-span-2">
            <label class="text-xs font-extrabold tracking-widest text-slate-500">PATIENT *</label>
            <div class="mt-2">
              <input type="hidden" name="patient_id" id="add_patient_id" value="">
              <input id="add_patientSearch" type="text"
                class="w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
                placeholder="Search patient name or phone..." autocomplete="off">
              <div class="mt-2 text-xs font-semibold text-slate-500">Tip: type at least 2 characters.</div>

              <div id="add_patientResults" class="mt-2 hidden overflow-hidden rounded-2xl border bg-white">
                <div class="max-h-48 overflow-y-auto divide-y" id="add_patientResultsList"></div>
              </div>

              <div class="mt-2 rounded-2xl border bg-white px-4 py-3">
                <div class="text-[11px] font-extrabold tracking-widest text-slate-500">SELECTED</div>
                <div class="mt-1 text-sm font-extrabold text-slate-900" id="add_patientSelectedLabel">None</div>
              </div>
            </div>
          </div>

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">TYPE *</label>
            <select id="add_bill_type" name="bill_type"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="CONSULTATION">CONSULTATION</option>
              <option value="SURGERY">SURGERY</option>
              <option value="OTHER" selected>OTHER</option>
            </select>
            <div id="add_surgery_select" class="mt-2 hidden">
              <select id="add_surgery_item" name="surgery_item_id"
                class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
                <option value="">Select surgery</option>
                <?php foreach ($surgeryItems as $si): ?>
                  <option value="<?php echo (int)$si['id']; ?>" data-price="<?php echo h($si['cost']); ?>">
                    <?php echo h($si['surgery_name']); ?> • $<?php echo number_format((float)$si['cost'],2); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div id="add_doctor_wrap" class="mt-3 hidden">
              <label class="text-xs font-extrabold tracking-widest text-slate-500">Doctor (performing surgery)</label>
              <input type="hidden" name="employee_id" id="add_doctor_id" value="">
              <input id="add_doctorSearch" type="text"
                class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
                placeholder="Search doctor name..." autocomplete="off">
              <div id="add_doctorResults" class="mt-2 hidden overflow-hidden rounded-2xl border bg-white">
                <div class="max-h-40 overflow-y-auto divide-y" id="add_doctorResultsList"></div>
              </div>
              <div class="mt-2 rounded-2xl border bg-white px-4 py-3">
                <div class="text-[11px] font-extrabold tracking-widest text-slate-500">SELECTED</div>
                <div class="mt-1 text-sm font-extrabold text-slate-900" id="add_doctorSelectedLabel">None</div>
              </div>
              <div class="mt-2 text-xs font-semibold text-slate-500">Select the doctor performing the surgery.</div>
            </div>
            <div class="mt-1 text-xs font-semibold text-slate-500">Prescription bills must be created from prescription page.</div>
          </div>

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">AMOUNT *</label>
            <input id="add_amount" name="amount" type="number" step="0.01" min="0" required
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none">
          </div>

          <div class="md:col-span-2">
            <label class="text-xs font-extrabold tracking-widest text-slate-500">DESCRIPTION *</label>
            <input id="add_description" name="description" required
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
              placeholder="e.g. Consultation fee, Surgery charge, Service charge">
          </div>

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">DISCOUNT</label>
            <input name="discount" type="number" step="0.01" min="0"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
              value="0">
          </div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button type="button" id="cancelAddBill"
            class="rounded-2xl border bg-white px-5 py-3 text-sm font-extrabold hover:bg-slate-50">Cancel</button>
          <button type="submit"
            class="rounded-2xl bg-orange-500 px-5 py-3 text-sm font-extrabold text-white hover:bg-orange-600">
            Add Bill →
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Pay Bill -->
<div id="payModal" class="fixed inset-0 z-50 hidden">
  <div id="payOverlay" class="absolute inset-0 bg-slate-900/50 backdrop-blur-[1px]"></div>

  <div class="relative mx-auto flex min-h-full max-w-xl items-center justify-center p-4">
    <div class="w-full max-w-lg rounded-3xl bg-white shadow-2xl ring-1 ring-black/5 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between border-b px-6 py-4">
        <div class="text-lg font-extrabold">Payment</div>
        <button type="button" id="closePay"
          class="grid h-10 w-10 place-items-center rounded-2xl hover:bg-slate-50 text-slate-500">✕</button>
      </div>

      <form method="POST" class="p-6">
        <input type="hidden" name="action" value="pay_bill">
        <input type="hidden" name="id" id="pay_id" value="">

        <div class="rounded-2xl border bg-slate-50 p-4">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">YOU WILL PAY</div>
          <div id="pay_amount" class="mt-1 text-3xl font-extrabold">$0.00</div>
          <div id="pay_note" class="mt-2 text-sm font-semibold text-slate-600"></div>
        </div>

        <div class="mt-4 grid gap-4">
          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">STATUS</label>
            <select id="pay_status" name="status"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="UNPAID">UNPAID</option>
              <option value="PAID">PAID</option>
            </select>
          </div>

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">PAYMENT METHOD (required when PAID)</label>
            <select id="pay_method" name="payment_method"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">Select</option>
              <option value="CASH">CASH</option>
              <option value="EVCPLUS">EVCPLUS</option>
              <option value="CARD">CARD</option>
              <option value="BANK">BANK</option>
            </select>
          </div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button type="button" id="cancelPay"
            class="rounded-2xl border bg-white px-5 py-3 text-sm font-extrabold hover:bg-slate-50">Cancel</button>
          <button type="submit"
            class="rounded-2xl bg-orange-500 px-5 py-3 text-sm font-extrabold text-white hover:bg-orange-600">
            Save payment →
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Edit Bill (UNPAID only) -->
<div id="editModal" class="fixed inset-0 z-50 hidden">
  <div id="editOverlay" class="absolute inset-0 bg-slate-900/50 backdrop-blur-[1px]"></div>

  <div class="relative mx-auto flex min-h-full max-w-xl items-center justify-center p-4">
    <div class="w-full max-w-lg rounded-3xl bg-white shadow-2xl ring-1 ring-black/5 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between border-b px-6 py-4">
        <div class="text-lg font-extrabold">Edit Bill</div>
        <button type="button" id="closeEdit"
          class="grid h-10 w-10 place-items-center rounded-2xl hover:bg-slate-50 text-slate-500">✕</button>
      </div>

      <form method="POST" class="p-6">
        <input type="hidden" name="action" value="update_bill">
        <input type="hidden" name="id" id="edit_id" value="">

        <div class="grid gap-4">
          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">DESCRIPTION</label>
            <input id="edit_description" name="description"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
              placeholder="Description">
            <div class="mt-2 text-xs font-semibold text-slate-500" id="edit_note"></div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="text-xs font-extrabold tracking-widest text-slate-500">AMOUNT</label>
              <input id="edit_amount" name="amount" type="number" step="0.01" min="0"
                class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none">
            </div>

            <div>
              <label class="text-xs font-extrabold tracking-widest text-slate-500">DISCOUNT</label>
              <input id="edit_discount" name="discount" type="number" step="0.01" min="0"
                class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none" value="0">
              <div class="mt-2 text-xs font-semibold text-slate-500">Discount max is 20%.</div>
            </div>
          </div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button type="button" id="cancelEdit"
            class="rounded-2xl border bg-white px-5 py-3 text-sm font-extrabold hover:bg-slate-50">Cancel</button>
          <button type="submit"
            class="rounded-2xl bg-orange-500 px-5 py-3 text-sm font-extrabold text-white hover:bg-orange-600">
            Save changes →
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
(function(){
  const addBtn = document.getElementById("openAddBillModal");
  const addM = document.getElementById("addBillModal");
  const addO = document.getElementById("addBillOverlay");
  const closeAdd = document.getElementById("closeAddBill");
  const cancelAdd = document.getElementById("cancelAddBill");

  const payM = document.getElementById("payModal");
  const payO = document.getElementById("payOverlay");
  const closePay = document.getElementById("closePay");
  const cancelPay = document.getElementById("cancelPay");
    const editM = document.getElementById("editModal");
  const editO = document.getElementById("editOverlay");
  const closeEdit = document.getElementById("closeEdit");
  const cancelEdit = document.getElementById("cancelEdit");

  editO?.addEventListener("click", ()=>closeM(editM));
  closeEdit?.addEventListener("click", ()=>closeM(editM));
  cancelEdit?.addEventListener("click", ()=>closeM(editM));

  const editId = document.getElementById("edit_id");
  const editDesc = document.getElementById("edit_description");
  const editAmount = document.getElementById("edit_amount");
  const editDiscount = document.getElementById("edit_discount");
  const editNote = document.getElementById("edit_note");


  function openM(el){ el.classList.remove("hidden"); document.body.style.overflow="hidden"; }
  function closeM(el){ el.classList.add("hidden"); document.body.style.overflow=""; }

  function escapeHtml(str){
    return String(str)
      .replaceAll("&","&amp;")
      .replaceAll("<","&lt;")
      .replaceAll(">","&gt;")
      .replaceAll('"',"&quot;")
      .replaceAll("'","&#039;");
  }

    addBtn?.addEventListener("click", ()=>{
      // ensure modal fields default state
      openM(addM);
      try {
        if (typeof addBillType !== 'undefined' && addBillType) {
          addBillType.value = 'OTHER';
          addBillType.dispatchEvent(new Event('change'));
        }
        // reset patient/doctor fields
        const pid = document.getElementById('add_patient_id'); if (pid) pid.value='';
        const pl = document.getElementById('add_patientSelectedLabel'); if (pl) pl.textContent='None';
        const did = document.getElementById('add_doctor_id'); if (did) did.value='';
        const dl = document.getElementById('add_doctorResultsList'); if (dl) dl.innerHTML='';
      } catch(e){ /* ignore */ }
    });
  addO?.addEventListener("click", ()=>closeM(addM));
  closeAdd?.addEventListener("click", ()=>closeM(addM));
  cancelAdd?.addEventListener("click", ()=>closeM(addM));

  payO?.addEventListener("click", ()=>closeM(payM));
  closePay?.addEventListener("click", ()=>closeM(payM));
  cancelPay?.addEventListener("click", ()=>closeM(payM));

  const pid = document.getElementById("pay_id");
  const pamt = document.getElementById("pay_amount");
  const pnote = document.getElementById("pay_note");
  const pstatus = document.getElementById("pay_status");
  const pmethod = document.getElementById("pay_method");

  // Add bill modal controls (consultation fixed, surgery selection)
  const addBillType = document.getElementById("add_bill_type");
  const addSurgeryWrap = document.getElementById("add_surgery_select");
  const addSurgeryItem = document.getElementById("add_surgery_item");
  const addAmount = document.getElementById("add_amount");
  const addDescription = document.getElementById("add_description");

  function setConsultationState(){
    addAmount.value = Number(10).toFixed(2);
    addAmount.readOnly = true;
    addSurgeryWrap.classList.add('hidden');
    if (addDescription && addDescription.value.trim()==='') addDescription.value = 'Consultation fee';
  }

  function setOtherState(){
    addAmount.readOnly = false;
    addSurgeryWrap.classList.add('hidden');
  }

  function setSurgeryState(){
    addAmount.readOnly = true;
    addSurgeryWrap.classList.remove('hidden');
    // if an item selected, apply its price
    const opt = addSurgeryItem?.selectedOptions?.[0];
    if (opt && opt.value) {
      const p = opt.dataset.price || opt.getAttribute('data-price') || '0';
      addAmount.value = Number(p).toFixed(2);
      if (addDescription && addDescription.value.trim()==='') addDescription.value = opt.textContent.trim();
    }
  }

  addBillType?.addEventListener('change', function(){
    const v = this.value;
    if (v === 'CONSULTATION') setConsultationState();
    else if (v === 'SURGERY') setSurgeryState();
    else setOtherState();
  });

  addSurgeryItem?.addEventListener('change', function(){
    const opt = this.selectedOptions?.[0];
    if (opt && opt.value) {
      const p = opt.dataset.price || opt.getAttribute('data-price') || '0';
      addAmount.value = Number(p).toFixed(2);
      if (addDescription && addDescription.value.trim()==='') addDescription.value = opt.textContent.trim();
    }
  });

  // Client-side patient + doctor search (uses preloaded arrays)
  const BILL_PATIENTS = <?php echo json_encode($patients, JSON_UNESCAPED_UNICODE); ?>;
  const DOCTORS = <?php echo json_encode($doctors, JSON_UNESCAPED_UNICODE); ?>;

  function wireSimpleSearch(opts){
    const input = document.getElementById(opts.inputId);
    const hidden = document.getElementById(opts.hiddenId);
    const resultsWrap = document.getElementById(opts.resultsWrapId);
    const resultsList = document.getElementById(opts.resultsListId);
    const selectedLabel = document.getElementById(opts.selectedLabelId);
    const data = opts.data || [];

    if (!input) return;

    input.addEventListener('input', function(){
      const term = this.value.trim().toLowerCase();
      resultsList.innerHTML = '';
      if (term.length < 2) { resultsWrap.classList.add('hidden'); return; }
      const matches = data.filter(d => (d.full_name || '').toLowerCase().includes(term) || (d.phone||'').toLowerCase().includes(term)).slice(0,40);
      if (matches.length === 0) { resultsList.innerHTML = '<div class="px-4 py-3 text-sm font-semibold text-slate-500">No matches</div>'; resultsWrap.classList.remove('hidden'); return; }
      for (const m of matches){
        const btn = document.createElement('button'); btn.type='button'; btn.className='w-full text-left px-4 py-3 hover:bg-slate-50';
        btn.innerHTML = `<div class="text-sm font-extrabold text-slate-900">${escapeHtml(m.full_name)} <span class="text-xs font-semibold text-slate-500">• #${m.id}</span></div><div class="text-xs font-semibold text-slate-500">${escapeHtml(m.phone||'-')}</div>`;
        btn.addEventListener('click', ()=>{
          hidden.value = m.id;
          selectedLabel.textContent = `${m.full_name} (${m.phone||'-'})`;
          resultsList.innerHTML = '';
          resultsWrap.classList.add('hidden');
          input.value = '';
        });
        resultsList.appendChild(btn);
      }
      resultsWrap.classList.remove('hidden');
    });

    document.addEventListener('click', function(e){ if (!input.contains(e.target) && !resultsWrap.contains(e.target)) resultsWrap.classList.add('hidden'); });
  }

  wireSimpleSearch({inputId:'add_patientSearch', hiddenId:'add_patient_id', resultsWrapId:'add_patientResults', resultsListId:'add_patientResultsList', selectedLabelId:'add_patientSelectedLabel', data:BILL_PATIENTS});
  wireSimpleSearch({inputId:'add_doctorSearch', hiddenId:'add_doctor_id', resultsWrapId:'add_doctorResults', resultsListId:'add_doctorResultsList', selectedLabelId:'add_doctorSelectedLabel', data:DOCTORS});

  // show/hide surgery doctor section
  addBillType?.addEventListener('change', function(){
    const v=this.value;
    if (v==='SURGERY'){
      document.getElementById('add_surgery_select').classList.remove('hidden');
      document.getElementById('add_doctor_wrap').classList.remove('hidden');
    } else {
      document.getElementById('add_surgery_select').classList.add('hidden');
      document.getElementById('add_doctor_wrap').classList.add('hidden');
    }
  });

  // Event delegation for pay buttons (safer than inline onclick)
  document.addEventListener('click', function(e){
    const btn = e.target.closest && e.target.closest('.open-pay');
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const total = btn.getAttribute('data-total');
    const status = btn.getAttribute('data-status');
    const method = btn.getAttribute('data-method');
    const type = btn.getAttribute('data-type');
    if (typeof window.openPayModal === 'function') {
      window.openPayModal(id, total, status, method, type);
    }
  });

  window.openPayModal = function(id, total, status, method, billType){
    pid.value = id;
    pamt.textContent = `$${Number(total).toFixed(2)}`;
    pstatus.value = status || "UNPAID";
    pmethod.value = method || "";

    pnote.textContent = (billType === "PRESCRIPTION")
      ? "Prescription bills are one-time payment."
      : "Update payment status for this bill.";

    openM(payM);
  };

  document.addEventListener("keydown", (e)=>{
    if (e.key === "Escape") {
      if (!addM.classList.contains("hidden")) closeM(addM);
      if (!payM.classList.contains("hidden")) closeM(payM);
    }
  });
    document.addEventListener('click', function(e){
    const btn = e.target.closest && e.target.closest('.open-edit');
    if (!btn) return;

    const id = btn.getAttribute('data-id');
    const desc = btn.getAttribute('data-description') || '';
    const amount = btn.getAttribute('data-amount') || '0';
    const discount = btn.getAttribute('data-discount') || '0';
    const type = btn.getAttribute('data-type') || '';

    editId.value = id;
    editDesc.value = desc;
    editAmount.value = Number(amount).toFixed(2);
    editDiscount.value = Number(discount).toFixed(2);

    // Lock amount for consultation & surgery (backend enforces too)
    if (type === 'CONSULTATION') {
      editAmount.readOnly = true;
      editNote.textContent = "Consultation amount is fixed ($10).";
    } else if (type === 'SURGERY') {
      editAmount.readOnly = true;
      editNote.textContent = "Surgery amount comes from surgery_items (cannot change).";
    } else {
      editAmount.readOnly = false;
      editNote.textContent = "You can edit description, amount and discount (discount max 20%).";
    }

    openM(editM);
  });

})();
</script>


