<?php
// reports/view.php
$pageTitle = "Reports • Hospital";
require_once __DIR__ . "/../includes/auth_guard.php";
require_once __DIR__ . "/../includes/db.php";

if (session_status() === PHP_SESSION_NONE) session_start();

// Only ADMIN can view reports
$user = $_SESSION["user"] ?? null;
if (!$user || ($user["role"] ?? "") !== "ADMIN") {
  header("Location: /hospital/dashboard.php");
  exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// -------- filters --------
$range = trim($_GET["range"] ?? "30d"); // 7d, 30d, 90d, ytd, custom
$from = trim($_GET["from"] ?? "");
$to   = trim($_GET["to"] ?? "");

$today = date("Y-m-d");
if ($range === "7d") { $fromDate = date("Y-m-d", strtotime("-6 days")); $toDate = $today; }
elseif ($range === "30d") { $fromDate = date("Y-m-d", strtotime("-29 days")); $toDate = $today; }
elseif ($range === "90d") { $fromDate = date("Y-m-d", strtotime("-89 days")); $toDate = $today; }
elseif ($range === "ytd") { $fromDate = date("Y-01-01"); $toDate = $today; }
elseif ($range === "custom" && $from !== "" && $to !== "") { $fromDate = $from; $toDate = $to; }
else { $fromDate = date("Y-m-d", strtotime("-29 days")); $toDate = $today; $range = "30d"; }

// -------- Income summary (PAID only) --------
$incomeSummary = [
  "paid_count" => 0,
  "gross" => 0,
  "discount" => 0,
  "net" => 0,
];

$stmt = $pdo->prepare("
  SELECT
    COUNT(*) AS paid_count,
    COALESCE(SUM(amount),0) AS gross,
    COALESCE(SUM(discount),0) AS discount,
    COALESCE(SUM(total),0) AS net
  FROM bills
  WHERE status='PAID'
    AND DATE(paid_at) BETWEEN :from AND :to
");
$stmt->execute([":from"=>$fromDate, ":to"=>$toDate]);
$incomeSummary = $stmt->fetch() ?: $incomeSummary;

// income by payment method
$incomeByMethod = [];
$stmt = $pdo->prepare("
  SELECT payment_method, COUNT(*) cnt, COALESCE(SUM(total),0) total
  FROM bills
  WHERE status='PAID'
    AND DATE(paid_at) BETWEEN :from AND :to
  GROUP BY payment_method
  ORDER BY total DESC
");
$stmt->execute([":from"=>$fromDate, ":to"=>$toDate]);
$incomeByMethod = $stmt->fetchAll() ?: [];

// income by day
$incomeByDay = [];
$stmt = $pdo->prepare("
  SELECT DATE(paid_at) d, COALESCE(SUM(total),0) total
  FROM bills
  WHERE status='PAID'
    AND DATE(paid_at) BETWEEN :from AND :to
  GROUP BY DATE(paid_at)
  ORDER BY d ASC
");
$stmt->execute([":from"=>$fromDate, ":to"=>$toDate]);
$incomeByDay = $stmt->fetchAll() ?: [];

// -------- Appointments reports --------
$apptStats = [];
$stmt = $pdo->prepare("
  SELECT status, COUNT(*) cnt
  FROM appointments
  WHERE DATE(appointment_datetime) BETWEEN :from AND :to
  GROUP BY status
");
$stmt->execute([":from"=>$fromDate, ":to"=>$toDate]);
$apptStats = $stmt->fetchAll() ?: [];

$upcomingAppts = [];
$stmt = $pdo->prepare("
  SELECT
    a.id,
    a.appointment_datetime,
    a.status,
    p.full_name AS patient_name,
    e.full_name AS Doctor
  FROM appointments a
  JOIN patients p ON p.id=a.patient_id
  JOIN employees e ON e.id=a.employee_id
  WHERE a.appointment_datetime >= NOW()
  ORDER BY a.appointment_datetime ASC
  LIMIT 10
");
$stmt->execute();
$upcomingAppts = $stmt->fetchAll() ?: [];

// -------- Patients reports --------
$newPatients = 0;
$stmt = $pdo->prepare("
  SELECT COUNT(*) FROM patients
  WHERE DATE(created_at) BETWEEN :from AND :to
");
$stmt->execute([":from"=>$fromDate, ":to"=>$toDate]);
$newPatients = (int)$stmt->fetchColumn();

$patientsByDay = [];
$stmt = $pdo->prepare("
  SELECT DATE(created_at) d, COUNT(*) cnt
  FROM patients
  WHERE DATE(created_at) BETWEEN :from AND :to
  GROUP BY DATE(created_at)
  ORDER BY d ASC
");
$stmt->execute([":from"=>$fromDate, ":to"=>$toDate]);
$patientsByDay = $stmt->fetchAll() ?: [];

// -------- Employees reports --------
$totalEmployees = (int)$pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$activeEmployees = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status='ACTIVE'")->fetchColumn();

$employeesByJob = $pdo->query("
  SELECT job_title, COUNT(*) cnt
  FROM employees
  GROUP BY job_title
  ORDER BY cnt DESC
")->fetchAll() ?: [];

// -------- Prescriptions reports --------
$totalPresc = 0;
$stmt = $pdo->prepare("
  SELECT COUNT(*) FROM prescriptions
  WHERE DATE(created_at) BETWEEN :from AND :to
");
$stmt->execute([":from"=>$fromDate, ":to"=>$toDate]);
$totalPresc = (int)$stmt->fetchColumn();

$topPrescribers = [];
$stmt = $pdo->prepare("
  SELECT e.full_name, COUNT(*) cnt
  FROM prescriptions pr
  JOIN employees e ON e.id = pr.prescribed_by_employee_id
  WHERE DATE(pr.created_at) BETWEEN :from AND :to
  GROUP BY pr.prescribed_by_employee_id
  ORDER BY cnt DESC
  LIMIT 10
");
$stmt->execute([":from"=>$fromDate, ":to"=>$toDate]);
$topPrescribers = $stmt->fetchAll() ?: [];

// ---------- AJAX: statements (returns JSON rows for selected statement type) ----------
if (isset($_GET["ajax"]) && $_GET["ajax"] === "statements") {
  if (ob_get_length()) ob_clean();
  header("Content-Type: application/json; charset=utf-8");
  $type = trim($_GET["type"] ?? "paid_bills");
  $fromA = trim($_GET["from"] ?? $fromDate);
  $toA = trim($_GET["to"] ?? $toDate);

  $res = ["ok"=>true, "rows"=>[]];
  try {
    if ($type === 'paid_bills') {
      $s = $pdo->prepare("SELECT b.id, b.receipt_no, b.description, b.amount, b.discount, b.total, b.payment_method, b.paid_at, p.full_name AS patient_name, e.full_name AS Doctor FROM bills b LEFT JOIN patients p ON p.id=b.patient_id LEFT JOIN employees e ON e.id=b.employee_id WHERE b.status='PAID' AND DATE(b.paid_at) BETWEEN :from AND :to ORDER BY b.paid_at DESC LIMIT 2000");
      $s->execute([":from"=>$fromA, ":to"=>$toA]);
      $res["rows"] = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } elseif ($type === 'appointments') {
      $s = $pdo->prepare("SELECT a.id, a.appointment_datetime, a.status, p.full_name AS patient_name, e.full_name AS doctor_name FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN employees e ON e.id=a.employee_id WHERE DATE(a.appointment_datetime) BETWEEN :from AND :to ORDER BY a.appointment_datetime DESC LIMIT 2000");
      $s->execute([":from"=>$fromA, ":to"=>$toA]);
      $res["rows"] = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } elseif ($type === 'patients') {
      $s = $pdo->prepare("SELECT id, full_name, phone, created_at FROM patients WHERE DATE(created_at) BETWEEN :from AND :to ORDER BY created_at DESC LIMIT 2000");
      $s->execute([":from"=>$fromA, ":to"=>$toA]);
      $res["rows"] = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } elseif ($type === 'prescriptions') {
      $s = $pdo->prepare("SELECT pr.id, pr.created_at, p.full_name AS patient_name, e.full_name AS prescribed_by FROM prescriptions pr JOIN patients p ON p.id=pr.patient_id LEFT JOIN employees e ON e.id=pr.prescribed_by_employee_id WHERE DATE(pr.created_at) BETWEEN :from AND :to ORDER BY pr.created_at DESC LIMIT 2000");
      $s->execute([":from"=>$fromA, ":to"=>$toA]);
      $res["rows"] = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
      $res = ["ok"=>false, "error"=>"Unknown type"]; 
    }
  } catch (Throwable $e) {
    $res = ["ok"=>false, "error"=>$e->getMessage()];
  }
  echo json_encode($res);
  exit;
}

include_once __DIR__ . "/../includes/header.php";
?>
 <div class="min-h-screen bg-slate-50 md:pl-72">
<div class="md:flex min-h-screen">
  <?php include __DIR__ . "/../includes/sidebar.php"; ?>

  <div class="flex-1">
    <div class="mx-auto max-w-6xl px-4 py-6">

      <!-- Header -->
      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
          <div class="text-2xl font-extrabold tracking-tight">Reports</div>
          <div class="text-sm font-semibold text-slate-500">
            Overview of income, appointments, patients, employees, and prescriptions
          </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="rounded-3xl border bg-white p-3 shadow-sm flex flex-wrap gap-2 items-center">
          <select name="range" class="rounded-2xl border bg-white px-4 py-2 text-sm font-semibold outline-none">
            <option value="7d"  <?= $range==="7d"?"selected":""; ?>>Last 7 days</option>
            <option value="30d" <?= $range==="30d"?"selected":""; ?>>Last 30 days</option>
            <option value="90d" <?= $range==="90d"?"selected":""; ?>>Last 90 days</option>
            <option value="ytd" <?= $range==="ytd"?"selected":""; ?>>Year to date</option>
            <option value="custom" <?= $range==="custom"?"selected":""; ?>>Custom</option>
          </select>

          <input type="date" name="from" value="<?= h($fromDate); ?>"
                 class="rounded-2xl border bg-white px-3 py-2 text-sm font-semibold outline-none">
          <input type="date" name="to" value="<?= h($toDate); ?>"
                 class="rounded-2xl border bg-white px-3 py-2 text-sm font-semibold outline-none">

          <button class="rounded-2xl bg-orange-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-orange-600">
            Apply
          </button>
          <button type="button" id="openStatements" class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50">
            Statements
          </button>
        </form>
      </div>

      <!-- Stat cards -->
      <section class="mt-6 grid gap-4 md:grid-cols-4">
        <div class="rounded-3xl border bg-white p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">INCOME (NET)</div>
          <div class="mt-2 text-3xl font-extrabold">$<?= number_format((float)$incomeSummary["net"], 2); ?></div>
          <div class="mt-1 text-xs font-semibold text-slate-500">Paid bills: <?= (int)$incomeSummary["paid_count"]; ?></div>
        </div>

        <div class="rounded-3xl border bg-orange-50 p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">APPOINTMENTS</div>
          <div class="mt-2 text-3xl font-extrabold">
            <?php
              $sumAppts = 0;
              foreach ($apptStats as $s) $sumAppts += (int)$s["cnt"];
              echo $sumAppts;
            ?>
          </div>
          <div class="mt-1 text-xs font-semibold text-slate-500"><?= h($fromDate); ?> → <?= h($toDate); ?></div>
        </div>

        <div class="rounded-3xl border bg-sky-50 p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">NEW PATIENTS</div>
          <div class="mt-2 text-3xl font-extrabold"><?= (int)$newPatients; ?></div>
          <div class="mt-1 text-xs font-semibold text-slate-500">Registrations</div>
        </div>

        <div class="rounded-3xl border bg-emerald-50 p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">PRESCRIPTIONS</div>
          <div class="mt-2 text-3xl font-extrabold"><?= (int)$totalPresc; ?></div>
          <div class="mt-1 text-xs font-semibold text-slate-500">Created in range</div>
        </div>
      </section>


      <!-- Statements Modal -->
      <div id="statementsModal" class="fixed inset-0 z-50 hidden">
        <div id="statementsOverlay" class="absolute inset-0 bg-slate-900/50"></div>
        <div class="relative mx-auto flex min-h-full max-w-6xl items-center justify-center p-4">
          <div class="w-full max-w-5xl rounded-3xl bg-white shadow-2xl ring-1 ring-black/5 max-h-[90vh] overflow-auto">
            <div class="flex items-center justify-between border-b px-6 py-4">
              <div class="text-lg font-extrabold">Statements & Reports</div>
              <button type="button" id="closeStatements" class="grid h-10 w-10 place-items-center rounded-2xl hover:bg-slate-50">✕</button>
            </div>

            <div class="p-6">
              <div class="flex items-center gap-3 mb-4">
                <label class="text-xs font-extrabold text-slate-500">Type</label>
                <select id="stmtType" class="rounded-2xl border px-3 py-2 text-sm">
                  <option value="paid_bills">Paid Bills (Statements)</option>
                  <option value="appointments">Appointments</option>
                  <option value="patients">Patients</option>
                  <option value="prescriptions">Prescriptions</option>
                </select>

                <label class="text-xs font-extrabold text-slate-500">From</label>
                <input type="date" id="stmtFrom" class="rounded-2xl border px-3 py-2 text-sm" value="<?= h($fromDate); ?>">
                <label class="text-xs font-extrabold text-slate-500">To</label>
                <input type="date" id="stmtTo" class="rounded-2xl border px-3 py-2 text-sm" value="<?= h($toDate); ?>">

                <button id="loadStatements" class="ml-auto rounded-2xl bg-sky-500 px-4 py-2 text-sm font-extrabold text-white">Load</button>
              </div>

              <div class="mb-4 flex gap-2">
                <button id="printStatements" class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold">Print</button>
                <button id="downloadCsv" class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold">Download CSV</button>
                <button id="downloadXls" class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold">Download Excel</button>
              </div>

              <div id="statementsContainer" class="overflow-auto rounded-2xl border p-4 bg-slate-50 text-sm">
                <div id="statementsPlaceholder" class="text-slate-500 font-semibold">No data loaded.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Income breakdown -->
      <section class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="rounded-3xl border bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <div class="text-sm font-extrabold">Income breakdown</div>
            <div class="text-xs font-semibold text-slate-500">PAID only</div>
          </div>

          <div class="mt-4 grid gap-3">
            <div class="flex justify-between text-sm font-semibold">
              <span class="text-slate-500">Gross</span>
              <span class="font-extrabold">$<?= number_format((float)$incomeSummary["gross"],2); ?></span>
            </div>
            <div class="flex justify-between text-sm font-semibold">
              <span class="text-slate-500">Discount</span>
              <span class="font-extrabold">$<?= number_format((float)$incomeSummary["discount"],2); ?></span>
            </div>
            <div class="h-px bg-slate-100"></div>
            <div class="flex justify-between text-sm font-semibold">
              <span class="text-slate-500">Net</span>
              <span class="text-lg font-extrabold">$<?= number_format((float)$incomeSummary["net"],2); ?></span>
            </div>
          </div>

          <div class="mt-5">
            <div class="text-xs font-extrabold tracking-widest text-slate-500">BY PAYMENT METHOD</div>
            <div class="mt-3 space-y-2">
              <?php if (!$incomeByMethod): ?>
                <div class="text-sm font-semibold text-slate-500">No paid bills in this range.</div>
              <?php else: ?>
                <?php foreach ($incomeByMethod as $m): ?>
                  <div class="flex items-center justify-between rounded-2xl border bg-slate-50 px-4 py-3">
                    <div>
                      <div class="text-sm font-extrabold text-slate-900"><?= h($m["payment_method"] ?? "UNKNOWN"); ?></div>
                      <div class="text-xs font-semibold text-slate-500">Bills: <?= (int)$m["cnt"]; ?></div>
                    </div>
                    <div class="text-sm font-extrabold">$<?= number_format((float)$m["total"],2); ?></div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="rounded-3xl border bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <div class="text-sm font-extrabold">Income by day</div>
            <div class="text-xs font-semibold text-slate-500"><?= h($fromDate); ?> → <?= h($toDate); ?></div>
          </div>

          <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead class="bg-slate-50 text-xs font-extrabold tracking-widest text-slate-500">
                <tr>
                  <th class="px-4 py-3">DATE</th>
                  <th class="px-4 py-3 text-right">TOTAL</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <?php if (!$incomeByDay): ?>
                  <tr><td colspan="2" class="px-4 py-6 text-center text-slate-500 font-semibold">No data.</td></tr>
                <?php else: ?>
                  <?php foreach ($incomeByDay as $d): ?>
                    <tr class="hover:bg-slate-50/60">
                      <td class="px-4 py-3 font-semibold text-slate-700"><?= h($d["d"]); ?></td>
                      <td class="px-4 py-3 text-right font-extrabold">$<?= number_format((float)$d["total"],2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Appointments + Employees -->
      <section class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="rounded-3xl border bg-white p-5 shadow-sm">
          <div class="text-sm font-extrabold">Appointments status</div>
          <div class="mt-4 space-y-2">
            <?php if (!$apptStats): ?>
              <div class="text-sm font-semibold text-slate-500">No appointments in this range.</div>
            <?php else: ?>
              <?php foreach ($apptStats as $s): ?>
                <div class="flex items-center justify-between rounded-2xl border bg-slate-50 px-4 py-3">
                  <div class="text-sm font-extrabold text-slate-900"><?= h($s["status"]); ?></div>
                  <div class="text-sm font-extrabold"><?= (int)$s["cnt"]; ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="mt-5">
            <div class="text-xs font-extrabold tracking-widest text-slate-500">UPCOMING (NEXT 10)</div>
            <div class="mt-3 space-y-2">
              <?php if (!$upcomingAppts): ?>
                <div class="text-sm font-semibold text-slate-500">No upcoming appointments.</div>
              <?php else: ?>
                <?php foreach ($upcomingAppts as $a): ?>
                  <div class="rounded-2xl border bg-white px-4 py-3">
                    <div class="flex items-center justify-between">
                      <div class="font-extrabold text-slate-900"><?= h($a["patient_name"]); ?></div>
                      <div class="text-xs font-extrabold text-orange-600"><?= h($a["status"]); ?></div>
                    </div>
                    <div class="mt-1 text-xs font-semibold text-slate-500">
                      <?= h(date("Y-m-d H:i", strtotime($a["appointment_datetime"]))); ?> • <?= h($a["Doctor"]); ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="rounded-3xl border bg-white p-5 shadow-sm">
          <div class="text-sm font-extrabold">Employees overview</div>

          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div class="rounded-2xl border bg-slate-50 p-4">
              <div class="text-xs font-extrabold tracking-widest text-slate-500">TOTAL</div>
              <div class="mt-1 text-2xl font-extrabold"><?= (int)$totalEmployees; ?></div>
            </div>
            <div class="rounded-2xl border bg-emerald-50 p-4">
              <div class="text-xs font-extrabold tracking-widest text-slate-500">ACTIVE</div>
              <div class="mt-1 text-2xl font-extrabold"><?= (int)$activeEmployees; ?></div>
            </div>
          </div>

          <div class="mt-5">
            <div class="text-xs font-extrabold tracking-widest text-slate-500">BY JOB TITLE</div>
            <div class="mt-3 space-y-2">
              <?php foreach ($employeesByJob as $j): ?>
                <div class="flex items-center justify-between rounded-2xl border bg-slate-50 px-4 py-3">
                  <div class="text-sm font-extrabold text-slate-900"><?= h($j["job_title"]); ?></div>
                  <div class="text-sm font-extrabold"><?= (int)$j["cnt"]; ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </section>

      <!-- Patients + Prescriptions -->
      <section class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="rounded-3xl border bg-white p-5 shadow-sm">
          <div class="text-sm font-extrabold">Patients registrations by day</div>
          <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead class="bg-slate-50 text-xs font-extrabold tracking-widest text-slate-500">
                <tr><th class="px-4 py-3">DATE</th><th class="px-4 py-3 text-right">NEW</th></tr>
              </thead>
              <tbody class="divide-y">
                <?php if (!$patientsByDay): ?>
                  <tr><td colspan="2" class="px-4 py-6 text-center text-slate-500 font-semibold">No data.</td></tr>
                <?php else: ?>
                  <?php foreach ($patientsByDay as $d): ?>
                    <tr class="hover:bg-slate-50/60">
                      <td class="px-4 py-3 font-semibold text-slate-700"><?= h($d["d"]); ?></td>
                      <td class="px-4 py-3 text-right font-extrabold"><?= (int)$d["cnt"]; ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="rounded-3xl border bg-white p-5 shadow-sm">
          <div class="text-sm font-extrabold">Top prescribers</div>
          <div class="mt-4 space-y-2">
            <?php if (!$topPrescribers): ?>
              <div class="text-sm font-semibold text-slate-500">No prescriptions in this range.</div>
            <?php else: ?>
              <?php foreach ($topPrescribers as $t): ?>
                <div class="flex items-center justify-between rounded-2xl border bg-slate-50 px-4 py-3">
                  <div class="text-sm font-extrabold text-slate-900"><?= h($t["full_name"]); ?></div>
                  <div class="text-sm font-extrabold"><?= (int)$t["cnt"]; ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <div class="mt-8 text-xs font-semibold text-slate-400">
        Reports are filtered by date range. Income uses <span class="font-extrabold">PAID</span> bills only.
      </div>

    </div>
  </div>
</div>
      <script>
      (function(){
        console.log('reports: statements script loaded');
        const openBtn = document.getElementById('openStatements');
        const modal = document.getElementById('statementsModal');
        const overlay = document.getElementById('statementsOverlay');
        const closeBtn = document.getElementById('closeStatements');
        const loadBtn = document.getElementById('loadStatements');
        const container = document.getElementById('statementsContainer');
        const placeholder = document.getElementById('statementsPlaceholder');
        const typeSel = document.getElementById('stmtType');
        const fromInp = document.getElementById('stmtFrom');
        const toInp = document.getElementById('stmtTo');
        const printBtn = document.getElementById('printStatements');
        const csvBtn = document.getElementById('downloadCsv');
        const xlsBtn = document.getElementById('downloadXls');

        function openM(){ modal.classList.remove('hidden'); document.body.style.overflow='hidden'; }
        function closeM(){ modal.classList.add('hidden'); document.body.style.overflow=''; }

        openBtn?.addEventListener('click', ()=>{ console.log('openBtn clicked'); openM(); });
        // Delegated fallback in case direct listener fails
        document.addEventListener('click', function(e){
          const t = e.target;
          if (!t) return;
          if (t.id === 'openStatements' || t.closest && t.closest('#openStatements')) { console.log('delegated open'); openM(); }
        });
        overlay?.addEventListener('click', closeM);
        closeBtn?.addEventListener('click', closeM);

        async function load(){
          placeholder.textContent = 'Loading...';
          const t = typeSel.value;
          const f = fromInp.value;
          const to = toInp.value;
          try{
            const res = await fetch(`/hospital/reports/view.php?ajax=statements&type=${encodeURIComponent(t)}&from=${encodeURIComponent(f)}&to=${encodeURIComponent(to)}`);
            const j = await res.json();
            if (!j.ok){ placeholder.textContent = j.error || 'Error'; return; }
            const rows = j.rows || [];
            if (rows.length === 0){ container.innerHTML = '<div class="text-slate-500 font-semibold">No rows.</div>'; return; }
            // build table
            const table = document.createElement('table');
            table.className = 'w-full text-left text-sm';
            const thead = document.createElement('thead'); thead.className='bg-slate-100 text-xs font-extrabold text-slate-600';
            const tbody = document.createElement('tbody'); tbody.className='divide-y';
            // compute headers from first row keys
            const keys = Object.keys(rows[0]);
            const trh = document.createElement('tr');
            keys.forEach(k => { const th = document.createElement('th'); th.className='px-3 py-2'; th.textContent = k.toUpperCase(); trh.appendChild(th); });
            thead.appendChild(trh);
            rows.forEach(r => {
              const tr = document.createElement('tr'); tr.className='hover:bg-white/60';
              keys.forEach(k => { const td = document.createElement('td'); td.className='px-3 py-2'; td.textContent = r[k] ?? ''; tr.appendChild(td); });
              tbody.appendChild(tr);
            });
            table.appendChild(thead); table.appendChild(tbody);
            container.innerHTML = ''; container.appendChild(table);
            // attach current rows for export
            container._rows = rows; container._keys = keys;
          } catch(e){ container.innerHTML = '<div class="text-rose-600">Error loading data</div>'; }
        }

        loadBtn?.addEventListener('click', load);

        function downloadCsv(filename, rows, keys){
          if (!rows || rows.length===0) return;
          const esc = v => '"'+String(v ?? '').replace(/"/g,'""')+'"';
          let csv = keys.map(k=>esc(k)).join(',') + '\n';
          for (const r of rows) csv += keys.map(k=>esc(r[k])).join(',') + '\n';
          const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a'); a.href = url; a.download = filename; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
        }

        csvBtn?.addEventListener('click', ()=>{
          const rows = container._rows || [];
          const keys = container._keys || [];
          const fname = 'statements_' + (typeSel.value) + '_' + fromInp.value + '_' + toInp.value + '.csv';
          downloadCsv(fname, rows, keys);
        });

        xlsBtn?.addEventListener('click', ()=>{
          const rows = container._rows || [];
          const keys = container._keys || [];
          const fname = 'statements_' + (typeSel.value) + '_' + fromInp.value + '_' + toInp.value + '.xls';
          downloadCsv(fname, rows, keys);
        });

        printBtn?.addEventListener('click', ()=>{
          const html = container.innerHTML;
          const w = window.open('','_blank');
          w.document.write('<html><head><title>Statements</title><meta charset="utf-8"><style>body{font-family:Arial,sans-serif;padding:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:8px;text-align:left}</style></head><body>');
          w.document.write(html);
          w.document.write('</body></html>');
          w.document.close();
          w.focus();
          setTimeout(()=>{ w.print(); }, 300);
        });

      })();
      </script>