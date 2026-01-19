<?php
$pageTitle = "Dashboard • Hospital";
require_once __DIR__ . "/includes/auth_guard.php";
require_once __DIR__ . "/includes/db.php";
include_once __DIR__ . "/includes/header.php";

/* ========== COUNTS ========== */
$patientsCount      = (int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$employeesCount     = (int)$pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$appointmentsToday  = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_datetime)=CURDATE()")->fetchColumn();
$unpaidBills        = (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='UNPAID'")->fetchColumn();

/* ========== INCOME ========== */
$incomeToday = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM bills WHERE status='PAID' AND DATE(paid_at)=CURDATE()")->fetchColumn();
$incomeMonth = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM bills WHERE status='PAID' AND YEAR(paid_at)=YEAR(CURDATE()) AND MONTH(paid_at)=MONTH(CURDATE())")->fetchColumn();
$income30d   = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM bills WHERE status='PAID' AND DATE(paid_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$incomeCategoryRows = $pdo->query("SELECT bill_type AS type, COALESCE(SUM(total),0) total FROM bills WHERE status='PAID' AND DATE(paid_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY bill_type")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$incomeByCategory = [];
foreach ($incomeCategoryRows as $r) {
    $incomeByCategory[$r['type']] = (float)$r['total'];
}


/* ========== RECENT APPOINTMENTS (table) ========== */
$recentStmt = $pdo->query("
  SELECT a.id, a.appointment_datetime, a.status,
         p.full_name AS patient_name,
         e.full_name AS employee_name
  FROM appointments a
  JOIN patients p ON p.id = a.patient_id
  JOIN employees e ON e.id = a.employee_id
  ORDER BY a.appointment_datetime DESC
  LIMIT 7
");
$recentAppointments = $recentStmt->fetchAll() ?: [];

/* ========== APPOINTMENTS BY STATUS (for chart) ========== */
$statusRows = $pdo->query("
  SELECT status, COUNT(*) cnt
  FROM appointments
  WHERE DATE(appointment_datetime) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  GROUP BY status
")->fetchAll() ?: [];

$apptByStatus = [];
foreach ($statusRows as $r) {
  $apptByStatus[$r["status"]] = (int)$r["cnt"];
}
$allStatuses = ["PENDING","CONFIRMED","COMPLETED","CANCELLED"];
$statusTotal = 0;
foreach ($allStatuses as $s) $statusTotal += (int)($apptByStatus[$s] ?? 0);
if ($statusTotal <= 0) $statusTotal = 1; // avoid divide by zero

/* ========== INCOME BY DAY (last 14 days chart) ========== */
$incomeDayRows = $pdo->query("
  SELECT DATE(paid_at) d, COALESCE(SUM(total),0) total
  FROM bills
  WHERE status='PAID'
    AND DATE(paid_at) >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
  GROUP BY DATE(paid_at)
  ORDER BY d ASC
")->fetchAll() ?: [];

$incomeByDay = [];
foreach ($incomeDayRows as $r) $incomeByDay[$r["d"]] = (float)$r["total"];

// build last 14 days list (including missing days => 0)
$days = [];
for ($i = 13; $i >= 0; $i--) {
  $d = date("Y-m-d", strtotime("-$i days"));
  $days[] = ["d"=>$d, "total"=> (float)($incomeByDay[$d] ?? 0)];
}
$maxIncome = 0;
foreach ($days as $x) if ($x["total"] > $maxIncome) $maxIncome = $x["total"];
if ($maxIncome <= 0) $maxIncome = 1;

/* ========== MINI CALENDAR (next 30 days, counts per day) ========== */
$calRows = $pdo->query("
  SELECT DATE(appointment_datetime) d, COUNT(*) cnt
  FROM appointments
  WHERE DATE(appointment_datetime) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
  GROUP BY DATE(appointment_datetime)
")->fetchAll() ?: [];

$calMap = [];
foreach ($calRows as $r) $calMap[$r["d"]] = (int)$r["cnt"];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

?>
<div class="min-h-screen bg-slate-50 md:pl-72">
  <div class="min-h-[calc(100vh-0px)] md:flex">
    <?php include __DIR__ . "/includes/sidebar.php"; ?>

    <div class="flex-1">
      <div class="mx-auto max-w-6xl px-4 py-6">

        <!-- Header -->
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div>
            <div class="text-2xl font-extrabold tracking-tight">Dashboard</div>
            <div class="text-sm font-semibold text-slate-500">Overview of your hospital activity</div>
          </div>

          <div class="flex flex-wrap gap-2">
            <a href="/hospital/reports/view.php"
              class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50">
              Reports
            </a>
            <a href="/hospital/calendar/view.php"
              class="rounded-2xl bg-orange-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-orange-600">
              Calendar →
            </a>
          </div>
        </div>

        <!-- Stat cards -->
        <section class="mt-6 grid gap-4 md:grid-cols-4">
          <?php
            $cards = [
              ["Patients", $patientsCount, "Total registered"],
              ["Employees", $employeesCount, "Doctors / Nurses / Admins"],
              ["Today Appointments", $appointmentsToday, "Scheduled today"],
              ["Unpaid Bills", $unpaidBills, "Need payment"],
            ];
            foreach ($cards as $c) {
              echo '
              <div class="rounded-3xl border bg-white p-5 shadow-sm">
                <div class="text-xs font-extrabold tracking-widest text-slate-500">'.h($c[0]).'</div>
                <div class="mt-2 text-3xl font-extrabold">'.h($c[1]).'</div>
                <div class="mt-2 text-sm font-semibold text-slate-600">'.h($c[2]).'</div>
              </div>';
            }
          ?>
        </section>

        <!-- Income cards -->
      <!-- Income (horizontal scroll like calendar) -->
<section class="mt-6 rounded-3xl border bg-white p-5 shadow-sm">
  <div class="flex items-start justify-between gap-3">
    <div>
      <div class="font-extrabold">Income Overview</div>
      <div class="text-sm font-semibold text-slate-500">Paid totals summary</div>
    </div>
    <a href="/hospital/reports/view.php"
       class="text-sm font-extrabold text-orange-600 hover:text-orange-700">
      View Reports →
    </a>
  </div>

  <!-- horizontal row -->
  <div class="mt-4 overflow-x-auto">
    <div class="flex gap-4 min-w-[820px]">
      <div class="flex-1 rounded-3xl border bg-slate-50 p-5">
        <div class="text-xs font-extrabold tracking-widest text-slate-500">INCOME TODAY</div>
        <div class="mt-2 text-3xl font-extrabold">$<?= number_format($incomeToday, 2); ?></div>
        <div class="mt-2 text-sm font-semibold text-slate-600">Paid bills today</div>
      </div>

      <div class="flex-1 rounded-3xl border bg-slate-50 p-5">
        <div class="text-xs font-extrabold tracking-widest text-slate-500">INCOME THIS MONTH</div>
        <div class="mt-2 text-3xl font-extrabold">$<?= number_format($incomeMonth, 2); ?></div>
        <div class="mt-2 text-sm font-semibold text-slate-600">Month-to-date</div>
      </div>

      <div class="flex-1 rounded-3xl border bg-slate-50 p-5">
        <div class="text-xs font-extrabold tracking-widest text-slate-500">INCOME (LAST 30 DAYS)</div>
        <div class="mt-2 text-3xl font-extrabold">$<?= number_format($income30d, 2); ?></div>
        <div class="mt-2 text-sm font-semibold text-slate-600">Rolling 30-day total</div>
      </div>

      <!-- optional 4th card (nice addition) -->
      <div class="flex-1 rounded-3xl border bg-slate-50 p-5">
        <div class="text-xs font-extrabold tracking-widest text-slate-500">UNPAID BILLS</div>
        <div class="mt-2 text-3xl font-extrabold"><?= (int)$unpaidBills; ?></div>
        <div class="mt-2 text-sm font-semibold text-slate-600">Need payment</div>
      </div>
    </div>
  </div>
</section>


        <!-- Charts row -->
        <section class="mt-6 grid gap-4 lg:grid-cols-3">
          <!-- Income chart -->
          <div class="lg:col-span-2 rounded-3xl border bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
              <div>
                <div class="font-extrabold">Income (last 14 days)</div>
                <div class="text-sm font-semibold text-slate-500">Paid totals per day</div>
              </div>
              <a class="text-sm font-extrabold text-orange-600 hover:text-orange-700" href="/hospital/reports/view.php">
                Details
              </a>
            </div>

           <div class="mt-5">
  <canvas id="incomeChart" class="w-full h-40"></canvas>
</div>

          </div>
          <div class="rounded-3xl border bg-white p-5 shadow-sm mt-6">
  <div class="flex items-center justify-between">
    <div>
      <div class="font-extrabold">Income by Category (last 30 days)</div>
      <div class="text-sm font-semibold text-slate-500">Consultation, Surgery, Prescription</div>
    </div>
  </div>

  <canvas id="incomeCategoryChart" class="w-full h-64 mt-4"></canvas>
</div>


          <!-- Status chart -->
          <div class="rounded-3xl border bg-white p-5 shadow-sm">
            <div class="font-extrabold">Appointments (last 30 days)</div>
            <div class="text-sm font-semibold text-slate-500">By status</div>

            <div class="mt-5 space-y-3">
              <?php foreach ($allStatuses as $s):
                $v = (int)($apptByStatus[$s] ?? 0);
                $w = (int)round(($v / $statusTotal) * 100);
                if ($w < 2 && $v > 0) $w = 2;
              ?>
                <div>
                  <div class="flex justify-between text-sm font-semibold">
                    <span class="text-slate-700"><?= h($s); ?></span>
                    <span class="text-slate-500"><?= (int)$v; ?></span>
                  </div>
                  <div class="mt-2 h-3 w-full rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full bg-orange-200" style="width: <?= $w; ?>%;"></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="mt-5">
              <a href="/hospital/appointments/view.php"
                class="inline-flex w-full items-center justify-center rounded-2xl border bg-white px-4 py-3 text-sm font-extrabold hover:bg-slate-50">
                View appointments →
              </a>
            </div>
          </div>
        </section>

        <!-- Calendar + Recent appointments -->
        <section class="mt-6 grid gap-4 lg:grid-cols-3">
          <!-- Mini calendar -->
          <div class="rounded-3xl border bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
              <div>
                <div class="font-extrabold">Calendar (next 30 days)</div>
                <div class="text-sm font-semibold text-slate-500">Appointments per day</div>
              </div>
              <a href="/hospital/calendar/view.php" class="text-sm font-extrabold text-orange-600 hover:text-orange-700">
                Open
              </a>
            </div>

            <div class="mt-4 grid grid-cols-7 gap-2 text-center">
              <?php
                // show 35 cells (5 weeks)
                $start = date("Y-m-d"); // today
                for ($i=0; $i<35; $i++):
                  $d = date("Y-m-d", strtotime("+$i days"));
                  $dayNum = (int)date("d", strtotime($d));
                  $cnt = (int)($calMap[$d] ?? 0);

                  $cell = "bg-white border";
                  if ($i === 0) $cell = "bg-orange-50 border-orange-200";
              ?>
                <div class="rounded-2xl border <?= $cell; ?> p-2">
                  <div class="text-xs font-extrabold text-slate-700"><?= $dayNum; ?></div>
                  <?php if ($cnt > 0): ?>
                    <div class="mt-1 rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-extrabold text-orange-700">
                      <?= $cnt; ?>
                    </div>
                  <?php else: ?>
                    <div class="mt-1 text-[10px] font-semibold text-slate-400">—</div>
                  <?php endif; ?>
                </div>
              <?php endfor; ?>
            </div>

            <div class="mt-4 text-xs font-semibold text-slate-500">
              Tip: Click “Open” to view full calendar and day details.
            </div>
          </div>

          <!-- Recent appointments table -->
          <div class="lg:col-span-2 rounded-3xl border bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
              <div>
                <div class="font-extrabold">Recent Appointments</div>
                <div class="text-sm font-semibold text-slate-500">Latest scheduled visits</div>
              </div>
              <a href="/hospital/appointments/view.php" class="text-sm font-extrabold text-orange-600 hover:text-orange-700">
                View All
              </a>
            </div>

            <div class="mt-4 overflow-x-auto">
              <table class="w-full text-left text-sm">
                <thead class="text-xs font-extrabold tracking-widest text-slate-500">
                  <tr>
                    <th class="py-3 pr-3">PATIENT</th>
                    <th class="py-3 pr-3">EMPLOYEE</th>
                    <th class="py-3 pr-3">DATE</th>
                    <th class="py-3 pr-3">STATUS</th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  <?php
                    if (!$recentAppointments) {
                      echo '<tr><td colspan="4" class="py-8 text-center text-slate-500 font-semibold">No appointments yet.</td></tr>';
                    } else {
                      foreach ($recentAppointments as $r) {
                        $status = $r["status"];
                        $badge = "bg-slate-100 text-slate-700";
                        if ($status === "CONFIRMED") $badge = "bg-emerald-100 text-emerald-700";
                        if ($status === "PENDING")   $badge = "bg-amber-100 text-amber-700";
                        if ($status === "CANCELLED") $badge = "bg-rose-100 text-rose-700";
                        if ($status === "COMPLETED") $badge = "bg-sky-100 text-sky-700";

                        echo "<tr>
                          <td class='py-4 pr-3 font-bold text-slate-800'>".h($r["patient_name"])."</td>
                          <td class='py-4 pr-3 font-semibold text-slate-700'>".h($r["employee_name"])."</td>
                          <td class='py-4 pr-3 font-semibold text-slate-700'>".h(date("Y-m-d H:i", strtotime($r["appointment_datetime"])))."</td>
                          <td class='py-4 pr-3'>
                            <span class='inline-flex rounded-full px-3 py-1 text-xs font-extrabold $badge'>".h($status)."</span>
                          </td>
                        </tr>";
                      }
                    }
                  ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>

      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('incomeChart').getContext('2d');
const incomeLabels = <?php echo json_encode(array_map(fn($x) => date("m/d", strtotime($x["d"])), $days)); ?>;
const incomeData = <?php echo json_encode(array_map(fn($x) => $x["total"], $days)); ?>;

new Chart(ctx, {
    type: 'bar', // vertical bars
    data: {
        labels: incomeLabels,
        datasets: [{
            label: 'Income ($)',
            data: incomeData,
            backgroundColor: 'rgba(251,146,60,0.7)', // orange
            borderColor: 'rgba(251,146,60,1)',
            borderWidth: 1,
            borderRadius: 6,
            maxBarThickness: 32
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { mode: 'index', intersect: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0 }
            },
            x: {
                ticks: { autoSkip: false }
            }
        }
    }
});
</script>
<script>
const ctxCat = document.getElementById('incomeCategoryChart').getContext('2d');
new Chart(ctxCat, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_keys($incomeByCategory)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_values($incomeByCategory)); ?>,
            backgroundColor: ['#f97316','#10b981','#3b82f6','#facc15'], // orange, green, blue, yellow
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: { callbacks: { label: ctx => ctx.label + ': $' + ctx.raw.toLocaleString() } }
        }
    }
});
</script>
