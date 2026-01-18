<?php
$pageTitle = "Dashboard • Hospital";
require_once __DIR__ . "/includes/auth_guard.php";
require_once __DIR__ . "/includes/db.php";
include_once __DIR__ . "/includes/header.php";


// Example counts (safe even if tables empty)
$patientsCount = (int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$employeesCount = (int)$pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$appointmentsToday = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_datetime)=CURDATE()")->fetchColumn();
$unpaidBills = (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='UNPAID'")->fetchColumn();
?>
 <div class="min-h-screen bg-slate-50 md:pl-72">
<div class="min-h-[calc(100vh-0px)] md:flex">
  <?php include __DIR__ . "/includes/sidebar.php"; ?>

  <div class="flex-1">
    <div class="mx-auto max-w-6xl px-4 py-6">
      

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
              <div class="text-xs font-extrabold tracking-widest text-slate-500">'.htmlspecialchars($c[0]).'</div>
              <div class="mt-2 text-3xl font-extrabold">'.htmlspecialchars((string)$c[1]).'</div>
              <div class="mt-2 text-sm font-semibold text-slate-600">'.htmlspecialchars($c[2]).'</div>
            </div>';
          }
        ?>
      </section>

      <!-- Content grid -->
      <section class="mt-6 grid gap-4 lg:grid-cols-3">
        <!-- Recent appointments -->
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
                $stmt = $pdo->query("
                  SELECT a.id, a.appointment_datetime, a.status,
                         p.full_name AS patient_name,
                         e.full_name AS employee_name
                  FROM appointments a
                  JOIN patients p ON p.id = a.patient_id
                  JOIN employees e ON e.id = a.employee_id
                  ORDER BY a.appointment_datetime DESC
                  LIMIT 6
                ");
                $rows = $stmt->fetchAll();
                if (!$rows) {
                  echo '<tr><td colspan="4" class="py-6 text-center text-slate-500 font-semibold">No appointments yet.</td></tr>';
                } else {
                  foreach ($rows as $r) {
                    $status = $r["status"];
                    $badge = "bg-slate-100 text-slate-700";
                    if ($status === "CONFIRMED") $badge = "bg-emerald-100 text-emerald-700";
                    if ($status === "PENDING") $badge = "bg-amber-100 text-amber-700";
                    if ($status === "CANCELLED") $badge = "bg-rose-100 text-rose-700";
                    if ($status === "COMPLETED") $badge = "bg-sky-100 text-sky-700";

                    echo "<tr>
                      <td class='py-4 pr-3 font-bold text-slate-800'>".htmlspecialchars($r["patient_name"])."</td>
                      <td class='py-4 pr-3 font-semibold text-slate-700'>".htmlspecialchars($r["employee_name"])."</td>
                      <td class='py-4 pr-3 font-semibold text-slate-700'>".htmlspecialchars(date("Y-m-d H:i", strtotime($r["appointment_datetime"])))."</td>
                      <td class='py-4 pr-3'>
                        <span class='inline-flex rounded-full px-3 py-1 text-xs font-extrabold $badge'>".htmlspecialchars($status)."</span>
                      </td>
                    </tr>";
                  }
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Quick actions -->
        <div class="rounded-3xl border bg-white p-5 shadow-sm">
          <div class="font-extrabold">Quick Actions</div>
          <div class="mt-4 grid gap-2">
            <a class="rounded-2xl border bg-white px-4 py-3 text-sm font-extrabold hover:bg-slate-50" href="/hospital/patients/view.php">
              ➕ New Patient
            </a>
            <a class="rounded-2xl border bg-white px-4 py-3 text-sm font-extrabold hover:bg-slate-50" href="/hospital/employees/view.php">
              ➕ Add Employee
            </a>
            <a class="rounded-2xl border bg-white px-4 py-3 text-sm font-extrabold hover:bg-slate-50" href="/hospital/appointments/view.php">
              ➕ New Appointment
            </a>
            <a class="rounded-2xl border bg-white px-4 py-3 text-sm font-extrabold hover:bg-slate-50" href="/hospital/billing/view.php">
              ➕ Create Bill
            </a>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>

<?php include_once __DIR__ . "/includes/footer.php"; ?>
