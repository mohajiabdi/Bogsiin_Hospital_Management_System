<?php
// /hospital/billing/view.php
$pageTitle = "Billing • Hospital";
require_once __DIR__ . "/../includes/auth_guard.php";
require_once __DIR__ . "/../includes/db.php";

if (session_status() === PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function flash_set($type, $msg){ $_SESSION["flash"] = ["type"=>$type, "msg"=>$msg]; }
function flash_get(){ if (!isset($_SESSION["flash"])) return null; $f=$_SESSION["flash"]; unset($_SESSION["flash"]); return $f; }

const CONSULT_FEE = 10.00;
const COVER_DAYS = 7;

/**
 * Check if consultation is covered within 7 days for same patient + same employee.
 * Returns array: [covered => bool, until => 'YYYY-MM-DD', last_paid_at => 'YYYY-MM-DD HH:MM:SS'|null]
 */
function consult_coverage(PDO $pdo, int $appointmentId): array {
  // get appointment + patient + employee
  $stmt = $pdo->prepare("
    SELECT a.id, a.appointment_datetime, a.patient_id, a.employee_id
    FROM appointments a
    WHERE a.id = ?
    LIMIT 1
  ");
  $stmt->execute([$appointmentId]);
  $a = $stmt->fetch();
  if (!$a) return ["covered"=>false, "until"=>null, "last_paid_at"=>null];

  $patientId = (int)$a["patient_id"];
  $employeeId = (int)$a["employee_id"];
  $apptDt = $a["appointment_datetime"];

  // Find last PAID consultation bill for SAME patient+employee within the last 7 days from appointment time
  // We identify consultation bill by description = 'Consultation fee'
  $stmt = $pdo->prepare("
    SELECT b.created_at
    FROM bills b
    JOIN appointments a2 ON a2.id = b.appointment_id
    WHERE
      b.status = 'PAID'
      AND (b.description = 'Consultation fee' OR b.description IS NULL)  /* allow legacy rows */
      AND a2.patient_id = ?
      AND a2.employee_id = ?
      AND b.total >= 10.00
    ORDER BY b.created_at DESC
    LIMIT 1
  ");
  $stmt->execute([$patientId, $employeeId]);
  $last = $stmt->fetchColumn();

  if (!$last) return ["covered"=>false, "until"=>null, "last_paid_at"=>null];

  $lastTs = strtotime($last);
  $apptTs = strtotime($apptDt);
  if ($lastTs === false || $apptTs === false) return ["covered"=>false, "until"=>null, "last_paid_at"=>null];

  $untilTs = $lastTs + (COVER_DAYS * 24 * 60 * 60);

  return [
    "covered" => ($apptTs <= $untilTs),
    "until" => date("Y-m-d", $untilTs),
    "last_paid_at" => $last,
  ];
}

/** safely compute totals */
function money2($v): float {
  $n = (float)$v;
  return round($n, 2);
}

// ---------- Load appointments for dropdown ----------
$appointments = $pdo->query("
  SELECT
    a.id, a.appointment_datetime, a.status,
    p.full_name AS patient_name, p.phone AS patient_phone,
    e.full_name AS employee_name, e.job_title
  FROM appointments a
  JOIN patients p ON p.id = a.patient_id
  JOIN employees e ON e.id = a.employee_id
  ORDER BY a.appointment_datetime DESC
  LIMIT 500
")->fetchAll() ?: [];

// ---------- Handle POST ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "";

  // Create consultation bill (fixed 10) unless covered
  if ($action === "create_consultation_bill") {
    $appointment_id = (int)($_POST["appointment_id"] ?? 0);
    if ($appointment_id <= 0) {
      flash_set("error", "Invalid appointment.");
      header("Location: /hospital/billing/view.php"); exit;
    }

    $cov = consult_coverage($pdo, $appointment_id);
    if ($cov["covered"]) {
      flash_set("error", "Consultation is covered until {$cov["until"]}. No new $10 bill needed.");
      header("Location: /hospital/billing/view.php"); exit;
    }

    // prevent duplicate consultation bill for same appointment
    $chk = $pdo->prepare("SELECT COUNT(*) FROM bills WHERE appointment_id=? AND description='Consultation fee'");
    $chk->execute([$appointment_id]);
    $exists = (int)$chk->fetchColumn();
    if ($exists > 0) {
      flash_set("error", "Consultation bill already exists for this appointment.");
      header("Location: /hospital/billing/view.php"); exit;
    }

    $amount = CONSULT_FEE;
    $discount = 0.00;
    $total = $amount - $discount;

    $stmt = $pdo->prepare("
      INSERT INTO bills (appointment_id, description, amount, discount, total, status, payment_method)
      VALUES (:appointment_id, :description, :amount, :discount, :total, 'UNPAID', NULL)
    ");
    $stmt->execute([
      ":appointment_id" => $appointment_id,
      ":description" => "Consultation fee",
      ":amount" => $amount,
      ":discount" => $discount,
      ":total" => $total,
    ]);

    flash_set("success", "Consultation bill ($10) created.");
    header("Location: /hospital/billing/view.php"); exit;
  }

  // Add other bill (custom)
  if ($action === "add_other_bill") {
    $appointment_id = (int)($_POST["appointment_id"] ?? 0);
    $description = trim($_POST["description"] ?? "");
    $amount = money2($_POST["amount"] ?? 0);
    $discount = money2($_POST["discount"] ?? 0);

    if ($appointment_id <= 0) {
      flash_set("error", "Select an appointment.");
      header("Location: /hospital/billing/view.php"); exit;
    }
    if ($description === "") {
      flash_set("error", "Description is required for other bills.");
      header("Location: /hospital/billing/view.php"); exit;
    }
    if ($amount <= 0) {
      flash_set("error", "Amount must be greater than 0.");
      header("Location: /hospital/billing/view.php"); exit;
    }
    if ($discount < 0) $discount = 0;
    if ($discount > $amount) $discount = $amount;

    $total = money2($amount - $discount);

    $stmt = $pdo->prepare("
      INSERT INTO bills (appointment_id, description, amount, discount, total, status, payment_method)
      VALUES (?, ?, ?, ?, ?, 'UNPAID', NULL)
    ");
    $stmt->execute([$appointment_id, $description, $amount, $discount, $total]);

    flash_set("success", "Other bill added.");
    header("Location: /hospital/billing/view.php"); exit;
  }

  // Update bill (only status & payment_method for consultation bills)
  if ($action === "update_bill") {
    $id = (int)($_POST["id"] ?? 0);
    $status = trim($_POST["status"] ?? "UNPAID");
    $method = trim($_POST["payment_method"] ?? "");

    $allowedStatus = ["UNPAID","PAID"];
    $allowedMethod = ["","CASH","EVCPLUS","CARD","BANK"];

    if (!in_array($status, $allowedStatus, true)) $status = "UNPAID";
    if (!in_array($method, $allowedMethod, true)) $method = "";

    // fetch bill
    $stmt = $pdo->prepare("SELECT id, description FROM bills WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $b = $stmt->fetch();
    if (!$b) {
      flash_set("error", "Bill not found.");
      header("Location: /hospital/billing/view.php"); exit;
    }

    // For consultation: lock amount/discount/total in DB (safety)
    if (($b["description"] ?? "") === "Consultation fee") {
      $stmt = $pdo->prepare("
        UPDATE bills
        SET amount=?, discount=?, total=?, status=?, payment_method=?
        WHERE id=?
      ");
      $stmt->execute([CONSULT_FEE, 0.00, CONSULT_FEE, $status, ($method===""?null:$method), $id]);
    } else {
      // For other bills: allow only status + method here to keep simple (no editing money)
      $stmt = $pdo->prepare("UPDATE bills SET status=?, payment_method=? WHERE id=?");
      $stmt->execute([$status, ($method===""?null:$method), $id]);
    }

    flash_set("success", "Bill updated.");
    header("Location: /hospital/billing/view.php"); exit;
  }

  // Delete bill (optional) - block deleting consultation if you want strict
  if ($action === "delete_bill") {
    $id = (int)($_POST["id"] ?? 0);

    $stmt = $pdo->prepare("SELECT id, description FROM bills WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $b = $stmt->fetch();
    if (!$b) {
      flash_set("error", "Bill not found.");
      header("Location: /hospital/billing/view.php"); exit;
    }

    // protect consultation bills
    if (($b["description"] ?? "") === "Consultation fee") {
      flash_set("error", "Consultation bills cannot be deleted.");
      header("Location: /hospital/billing/view.php"); exit;
    }

    $stmt = $pdo->prepare("DELETE FROM bills WHERE id=?");
    $stmt->execute([$id]);
    flash_set("success", "Bill deleted.");
    header("Location: /hospital/billing/view.php"); exit;
  }

  header("Location: /hospital/billing/view.php"); exit;
}

// ---------- Filters ----------
$q = trim($_GET["q"] ?? "");
$st = trim($_GET["st"] ?? "");
$pm = trim($_GET["pm"] ?? "");
$from = trim($_GET["from"] ?? "");
$to = trim($_GET["to"] ?? "");

$where = [];
$params = [];

if ($st !== "" && in_array($st, ["UNPAID","PAID"], true)) {
  $where[] = "b.status = :st";
  $params[":st"] = $st;
}
if ($pm !== "" && in_array($pm, ["CASH","EVCPLUS","CARD","BANK"], true)) {
  $where[] = "b.payment_method = :pm";
  $params[":pm"] = $pm;
}
if ($from !== "") {
  $where[] = "b.created_at >= :fromdt";
  $params[":fromdt"] = $from . " 00:00:00";
}
if ($to !== "") {
  $where[] = "b.created_at <= :todt";
  $params[":todt"] = $to . " 23:59:59";
}

// smart multi-word search: patient name/phone, employee name, description
if ($q !== "") {
  $terms = preg_split('/\s+/', $q);
  $terms = array_values(array_filter($terms, fn($t)=>$t!==""));
  $i = 0;
  foreach ($terms as $t) {
    $k = ":t".$i;
    $where[] = "(
      p.full_name LIKE $k OR p.phone LIKE $k
      OR e.full_name LIKE $k OR e.job_title LIKE $k
      OR b.description LIKE $k
      OR CAST(b.total AS CHAR) LIKE $k
    )";
    $params[$k] = "%$t%";
    $i++;
  }
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// ---------- Stats ----------
$totalBills = (int)$pdo->query("SELECT COUNT(*) FROM bills")->fetchColumn();
$unpaidBills = (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='UNPAID'")->fetchColumn();
$paidBills = (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='PAID'")->fetchColumn();
$sumUnpaid = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM bills WHERE status='UNPAID'")->fetchColumn();

// ---------- List ----------
$sql = "
  SELECT
    b.*,
    a.appointment_datetime,
    p.full_name AS patient_name, p.phone AS patient_phone,
    e.full_name AS employee_name, e.job_title
  FROM bills b
  JOIN appointments a ON a.id = b.appointment_id
  JOIN patients p ON p.id = a.patient_id
  JOIN employees e ON e.id = a.employee_id
  $whereSql
  ORDER BY b.created_at DESC
  LIMIT 300
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];

$flash = flash_get();
include_once __DIR__ . "/../includes/header.php";
?>

<div class="md:flex min-h-screen">
  <?php include __DIR__ . "/../includes/sidebar.php"; ?>

  <div class="flex-1">
    <div class="mx-auto max-w-6xl px-4 py-6">

      <!-- Header -->
      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
          <div class="text-2xl font-extrabold tracking-tight">Billing</div>
          <div class="text-sm font-semibold text-slate-500">Consultation bills ($10) + other charges</div>
        </div>

        <div class="flex items-center gap-2">
          <a href="/hospital/billing/view.php"
             class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50">
            Refresh
          </a>
          <button id="openOtherBillModal"
            class="rounded-2xl bg-orange-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-orange-600">
            + Add Other Bill
          </button>
        </div>
      </div>

      <?php if ($flash): ?>
        <div class="mt-4 rounded-2xl border px-4 py-3 text-sm font-semibold
          <?php echo $flash["type"] === "success" ? "border-green-200 bg-green-50 text-green-700" : "border-red-200 bg-red-50 text-red-700"; ?>">
          <?php echo h($flash["msg"]); ?>
        </div>
      <?php endif; ?>

      <!-- Stats -->
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
          <div class="text-xs font-extrabold tracking-widest text-slate-500">UNPAID TOTAL</div>
          <div class="mt-2 text-3xl font-extrabold">$<?php echo number_format($sumUnpaid, 2); ?></div>
        </div>
      </section>

      <!-- Filters -->
      <section class="mt-6 rounded-3xl border bg-white p-4 shadow-sm">
        <form class="grid gap-3 lg:grid-cols-12" method="GET">
          <div class="lg:col-span-6">
            <div class="flex items-center gap-2 rounded-2xl border bg-white px-4 py-3">
              <span class="text-slate-400">🔎</span>
              <input name="q" value="<?php echo h($q); ?>"
                class="w-full bg-transparent text-sm outline-none"
                placeholder="Search patient, employee, description, amount… (multi-word)" />
            </div>
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
              class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none" title="From">
          </div>

          <div class="lg:col-span-1">
            <input type="date" name="to" value="<?php echo h($to); ?>"
              class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none" title="To">
          </div>

          <div class="lg:col-span-12 flex items-center justify-between pt-1 text-sm">
            <div class="font-semibold text-slate-600">
              Showing: <span class="font-extrabold text-slate-900"><?php echo count($rows); ?></span>
            </div>
            <div class="flex gap-2">
              <button class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50" type="submit">Filter</button>
              <a href="/hospital/billing/view.php" class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50">Reset</a>
            </div>
          </div>
        </form>
      </section>

      <!-- Quick: create consultation bill -->
      <section class="mt-6 rounded-3xl border bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <div class="font-extrabold">Create consultation bill</div>
            <div class="text-sm font-semibold text-slate-500">Fixed $10, covered for 7 days per patient+doctor after paid</div>
          </div>

          <form method="POST" class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <input type="hidden" name="action" value="create_consultation_bill">
            <select name="appointment_id" class="w-80 rounded-2xl border bg-white px-4 py-2 text-sm font-semibold outline-none" required>
              <option value="">Select appointment</option>
              <?php foreach ($appointments as $a): ?>
                <?php
                  $cov = consult_coverage($pdo, (int)$a["id"]);
                  $label = date("Y-m-d H:i", strtotime($a["appointment_datetime"])) .
                           " • " . $a["patient_name"] .
                           " • " . $a["employee_name"] . " (" . $a["job_title"] . ")";
                  if ($cov["covered"]) $label .= " • COVERED until " . $cov["until"];
                ?>
                <option value="<?php echo (int)$a["id"]; ?>"><?php echo h($label); ?></option>
              <?php endforeach; ?>
            </select>

            <button type="submit"
              class="rounded-2xl bg-orange-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-orange-600">
              Create $10 Bill →
            </button>
          </form>
        </div>
      </section>

      <!-- Bills table -->
      <section class="mt-6 rounded-3xl border bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs font-extrabold tracking-widest text-slate-500">
              <tr>
                <th class="px-5 py-4">BILL</th>
                <th class="px-5 py-4">PATIENT</th>
                <th class="px-5 py-4">DOCTOR/EMPLOYEE</th>
                <th class="px-5 py-4">TOTAL</th>
                <th class="px-5 py-4">STATUS</th>
                <th class="px-5 py-4">PAYMENT</th>
                <th class="px-5 py-4 text-right">ACTIONS</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <?php if (!$rows): ?>
                <tr><td colspan="7" class="px-5 py-10 text-center text-slate-500 font-semibold">No bills found.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <?php
                    $isConsult = (($r["description"] ?? "") === "Consultation fee");
                    $badge = ($r["status"] === "PAID") ? "bg-emerald-100 text-emerald-700" : "bg-orange-100 text-orange-700";
                  ?>
                  <tr class="hover:bg-slate-50/60">
                    <td class="px-5 py-4">
                      <div class="font-extrabold text-slate-900">
                        #<?php echo (int)$r["id"]; ?> <?php echo $isConsult ? "• Consultation" : ""; ?>
                      </div>
                      <div class="text-xs font-semibold text-slate-500">
                        <?php echo h($r["description"] ?? ($isConsult ? "Consultation fee" : "—")); ?>
                        • Appt: <?php echo h(date("Y-m-d H:i", strtotime($r["appointment_datetime"]))); ?>
                      </div>
                    </td>

                    <td class="px-5 py-4">
                      <div class="font-extrabold text-slate-900"><?php echo h($r["patient_name"]); ?></div>
                      <div class="text-xs font-semibold text-slate-500"><?php echo h($r["patient_phone"] ?? "-"); ?></div>
                    </td>

                    <td class="px-5 py-4">
                      <div class="font-extrabold text-slate-900"><?php echo h($r["employee_name"]); ?></div>
                      <div class="text-xs font-semibold text-slate-500"><?php echo h($r["job_title"]); ?></div>
                    </td>

                    <td class="px-5 py-4 font-extrabold text-slate-900">
                      $<?php echo number_format((float)$r["total"], 2); ?>
                      <div class="text-xs font-semibold text-slate-500">
                        Amt: $<?php echo number_format((float)$r["amount"], 2); ?> • Disc: $<?php echo number_format((float)$r["discount"], 2); ?>
                      </div>
                    </td>

                    <td class="px-5 py-4">
                      <span class="inline-flex rounded-full px-3 py-1 text-xs font-extrabold <?php echo $badge; ?>">
                        <?php echo h($r["status"]); ?>
                      </span>
                    </td>

                    <td class="px-5 py-4 text-sm font-semibold text-slate-700">
                      <?php echo h($r["payment_method"] ?? "-"); ?>
                    </td>

                    <td class="px-5 py-4">
                      <div class="flex justify-end gap-2">
                        <!-- Update (status/method) -->
                        <button type="button"
                          class="grid h-10 w-10 place-items-center rounded-2xl border bg-white hover:bg-slate-50"
                          title="Update"
                          onclick="openUpdate(
                            <?php echo (int)$r['id']; ?>,
                            '<?php echo h($r['status']); ?>',
                            '<?php echo h($r['payment_method'] ?? ''); ?>',
                            <?php echo $isConsult ? 'true' : 'false'; ?>
                          )">⚙️</button>

                        <!-- Delete only for non-consult -->
                        <?php if (!$isConsult): ?>
                          <form method="POST" onsubmit="return confirm('Delete this bill?');">
                            <input type="hidden" name="action" value="delete_bill">
                            <input type="hidden" name="id" value="<?php echo (int)$r["id"]; ?>">
                            <button type="submit"
                              class="grid h-10 w-10 place-items-center rounded-2xl border border-rose-200 bg-rose-50 hover:bg-rose-100"
                              title="Delete">🗑️</button>
                          </form>
                        <?php else: ?>
                          <div class="grid h-10 w-10 place-items-center rounded-2xl border bg-slate-50 text-slate-300" title="Locked">🔒</div>
                        <?php endif; ?>
                      </div>
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

<!-- Modal: Add Other Bill -->
<div id="otherBillModal" class="fixed inset-0 z-50 hidden">
  <div id="otherBillOverlay" class="absolute inset-0 bg-slate-900/50 backdrop-blur-[1px]"></div>

  <div class="relative mx-auto flex min-h-full max-w-3xl items-center justify-center p-4">
    <div class="w-full max-w-2xl rounded-3xl bg-white shadow-2xl ring-1 ring-black/5">
      <div class="flex items-center justify-between border-b px-6 py-4">
        <div class="text-lg font-extrabold">Add other bill</div>
        <button type="button" id="closeOtherBill"
          class="grid h-10 w-10 place-items-center rounded-2xl hover:bg-slate-50 text-slate-500">✕</button>
      </div>

      <form method="POST" class="p-6">
        <input type="hidden" name="action" value="add_other_bill">

        <div class="grid gap-4 md:grid-cols-2">
          <div class="md:col-span-2">
            <label class="text-xs font-extrabold tracking-widest text-slate-500">APPOINTMENT *</label>
            <select name="appointment_id" required
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">Select appointment</option>
              <?php foreach ($appointments as $a): ?>
                <option value="<?php echo (int)$a["id"]; ?>">
                  <?php echo h(date("Y-m-d H:i", strtotime($a["appointment_datetime"])) . " • " . $a["patient_name"] . " • " . $a["employee_name"]); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="md:col-span-2">
            <label class="text-xs font-extrabold tracking-widest text-slate-500">DESCRIPTION *</label>
            <input name="description" required
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
              placeholder="e.g. Lab test, Injection, Service charge">
          </div>

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">AMOUNT *</label>
            <input name="amount" type="number" step="0.01" min="0"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
              placeholder="e.g. 5.00">
          </div>

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">DISCOUNT</label>
            <input name="discount" type="number" step="0.01" min="0"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
              placeholder="e.g. 0.00">
          </div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button type="button" id="cancelOtherBill"
            class="rounded-2xl border bg-white px-5 py-3 text-sm font-extrabold hover:bg-slate-50">
            Cancel
          </button>
          <button type="submit"
            class="rounded-2xl bg-orange-500 px-5 py-3 text-sm font-extrabold text-white hover:bg-orange-600">
            Add Bill →
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Update Bill -->
<div id="updateBillModal" class="fixed inset-0 z-50 hidden">
  <div id="updateBillOverlay" class="absolute inset-0 bg-slate-900/50 backdrop-blur-[1px]"></div>

  <div class="relative mx-auto flex min-h-full max-w-xl items-center justify-center p-4">
    <div class="w-full max-w-lg rounded-3xl bg-white shadow-2xl ring-1 ring-black/5">
      <div class="flex items-center justify-between border-b px-6 py-4">
        <div class="text-lg font-extrabold">Update bill</div>
        <button type="button" id="closeUpdateBill"
          class="grid h-10 w-10 place-items-center rounded-2xl hover:bg-slate-50 text-slate-500">✕</button>
      </div>

      <form method="POST" class="p-6">
        <input type="hidden" name="action" value="update_bill">
        <input type="hidden" name="id" id="ub_id" value="">

        <div class="rounded-2xl border bg-slate-50 p-4 text-sm font-semibold text-slate-700" id="ub_note">
          Consultation bills are fixed ($10) and not editable.
        </div>

        <div class="mt-4 grid gap-4">
          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">STATUS</label>
            <select id="ub_status" name="status"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="UNPAID">UNPAID</option>
              <option value="PAID">PAID</option>
            </select>
          </div>

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">PAYMENT METHOD</label>
            <select id="ub_method" name="payment_method"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">-</option>
              <option value="CASH">CASH</option>
              <option value="EVCPLUS">EVCPLUS</option>
              <option value="CARD">CARD</option>
              <option value="BANK">BANK</option>
            </select>
          </div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button type="button" id="cancelUpdateBill"
            class="rounded-2xl border bg-white px-5 py-3 text-sm font-extrabold hover:bg-slate-50">Cancel</button>
          <button type="submit"
            class="rounded-2xl bg-orange-500 px-5 py-3 text-sm font-extrabold text-white hover:bg-orange-600">
            Save →
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  // Other bill modal
  const obm = document.getElementById("otherBillModal");
  const obo = document.getElementById("otherBillOverlay");
  const openOther = document.getElementById("openOtherBillModal");
  const closeOther = document.getElementById("closeOtherBill");
  const cancelOther = document.getElementById("cancelOtherBill");

  function openM(el){ el.classList.remove("hidden"); document.body.style.overflow="hidden"; }
  function closeM(el){ el.classList.add("hidden"); document.body.style.overflow=""; }

  openOther?.addEventListener("click", ()=>openM(obm));
  obo?.addEventListener("click", ()=>closeM(obm));
  closeOther?.addEventListener("click", ()=>closeM(obm));
  cancelOther?.addEventListener("click", ()=>closeM(obm));

  // Update bill modal
  const ubm = document.getElementById("updateBillModal");
  const ubo = document.getElementById("updateBillOverlay");
  const closeUb = document.getElementById("closeUpdateBill");
  const cancelUb = document.getElementById("cancelUpdateBill");

  window.openUpdate = function(id, status, method, isConsult){
    document.getElementById("ub_id").value = id;
    document.getElementById("ub_status").value = status || "UNPAID";
    document.getElementById("ub_method").value = method || "";

    const note = document.getElementById("ub_note");
    note.textContent = isConsult
      ? "Consultation bill is fixed ($10). You can only change payment status/method."
      : "Other bill: you can change payment status/method (amount is kept as-is).";

    openM(ubm);
  };

  ubo?.addEventListener("click", ()=>closeM(ubm));
  closeUb?.addEventListener("click", ()=>closeM(ubm));
  cancelUb?.addEventListener("click", ()=>closeM(ubm));

  document.addEventListener("keydown", (e)=>{
    if (e.key === "Escape") {
      if (!obm.classList.contains("hidden")) closeM(obm);
      if (!ubm.classList.contains("hidden")) closeM(ubm);
    }
  });
})();
</script>

<?php include_once __DIR__ . "/../includes/footer.php"; ?>
