<?php
// /hospital/appointments/view.php
$pageTitle = "Appointments • Hospital";
require_once __DIR__ . "/../includes/auth_guard.php";
require_once __DIR__ . "/../includes/db.php";

if (session_status() === PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function flash_set($type, $msg){ $_SESSION["flash"] = ["type"=>$type, "msg"=>$msg]; }
function flash_get(){ if (!isset($_SESSION["flash"])) return null; $f=$_SESSION["flash"]; unset($_SESSION["flash"]); return $f; }

// ---------------- Load dropdown data (patients/employees) ----------------
// Keep these light for university project. You can increase LIMIT if needed.
$patients = $pdo->query("SELECT id, full_name, patient_code, phone FROM patients ORDER BY full_name ASC LIMIT 500")->fetchAll();
// Only active doctors can have appointments
$employees = $pdo->query("SELECT id, full_name, emp_code, job_title, phone FROM employees WHERE status='ACTIVE' AND job_title='DOCTOR' ORDER BY full_name ASC LIMIT 500")->fetchAll();

// ---------------- POST actions ----------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "";

  if ($action === "save_appointment") {
    $id = trim($_POST["id"] ?? "");

    $patient_id = (int)($_POST["patient_id"] ?? 0);
    $employee_id = (int)($_POST["employee_id"] ?? 0);
    $appointment_datetime = trim($_POST["appointment_datetime"] ?? "");
    $reason = trim($_POST["reason"] ?? "");
    $status = trim($_POST["status"] ?? "PENDING");

    $allowedStatus = ["PENDING","CONFIRMED","COMPLETED","CANCELLED"];
    if (!in_array($status, $allowedStatus, true)) $status = "PENDING";

    if ($patient_id <= 0 || $employee_id <= 0 || $appointment_datetime === "") {
      flash_set("error", "Patient, employee and appointment date/time are required.");
      header("Location: /hospital/appointments/view.php"); exit;
    }

    // Normalize datetime (HTML datetime-local => "YYYY-MM-DDTHH:MM")
    $appointment_datetime = str_replace("T", " ", $appointment_datetime);

    try {
      if ($id === "") {
        $stmt = $pdo->prepare("
          INSERT INTO appointments (patient_id, employee_id, appointment_datetime, reason, status)
          VALUES (:patient_id, :employee_id, :appointment_datetime, :reason, :status)
        ");
        // Before inserting, ensure selected employee is an active doctor
        $chk = $pdo->prepare("SELECT id, job_title, status FROM employees WHERE id=? LIMIT 1");
        $chk->execute([$employee_id]);
        $er = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$er || strtoupper(($er['job_title'] ?? '')) !== 'DOCTOR' || ($er['status'] ?? '') !== 'ACTIVE') {
          flash_set('error', 'Selected doctor is invalid or not active.');
          header('Location: /hospital/appointments/view.php'); exit;
        }

        // Check for conflicting appointment for this doctor at same datetime
        $confChk = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE employee_id = ? AND appointment_datetime = ?");
        $confChk->execute([$employee_id, $appointment_datetime]);
        if ((int)$confChk->fetchColumn() > 0) {
          flash_set('error', 'Selected doctor already has an appointment at that time.');
          header('Location: /hospital/appointments/view.php'); exit;
        }

        $stmt->execute([
          ":patient_id" => $patient_id,
          ":employee_id" => $employee_id,
          ":appointment_datetime" => $appointment_datetime,
          ":reason" => ($reason !== "") ? $reason : null,
          ":status" => $status,
        ]);

        flash_set("success", "Appointment created.");
        header("Location: /hospital/appointments/view.php"); exit;
      } else {
        $stmt = $pdo->prepare("
          UPDATE appointments SET
            patient_id = :patient_id,
            employee_id = :employee_id,
            appointment_datetime = :appointment_datetime,
            reason = :reason,
            status = :status
          WHERE id = :id
        ");
        // For updates, ensure doctor is valid and not double-booked (ignore this appointment id)
        $chk = $pdo->prepare("SELECT id, job_title, status FROM employees WHERE id=? LIMIT 1");
        $chk->execute([$employee_id]);
        $er = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$er || strtoupper(($er['job_title'] ?? '')) !== 'DOCTOR' || ($er['status'] ?? '') !== 'ACTIVE') {
          flash_set('error', 'Selected doctor is invalid or not active.');
          header('Location: /hospital/appointments/view.php'); exit;
        }
        $confChk = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE employee_id = ? AND appointment_datetime = ? AND id != ?");
        $confChk->execute([$employee_id, $appointment_datetime, (int)$id]);
        if ((int)$confChk->fetchColumn() > 0) {
          flash_set('error', 'Selected doctor already has an appointment at that time.');
          header('Location: /hospital/appointments/view.php'); exit;
        }

        $stmt->execute([
          ":patient_id" => $patient_id,
          ":employee_id" => $employee_id,
          ":appointment_datetime" => $appointment_datetime,
          ":reason" => ($reason !== "") ? $reason : null,
          ":status" => $status,
          ":id" => (int)$id,
        ]);

        flash_set("success", "Appointment updated.");
        header("Location: /hospital/appointments/view.php"); exit;
      }
    } catch (Throwable $e) {
      flash_set("error", "Save failed: " . $e->getMessage());
      header("Location: /hospital/appointments/view.php"); exit;
    }
  }

  if ($action === "delete_appointment") {
    $id = (int)($_POST["id"] ?? 0);
    if ($id > 0) {
      $stmt = $pdo->prepare("DELETE FROM appointments WHERE id=?");
      $stmt->execute([$id]);
      flash_set("success", "Appointment deleted.");
    } else {
      flash_set("error", "Invalid appointment.");
    }
    header("Location: /hospital/appointments/view.php"); exit;
  }

  header("Location: /hospital/appointments/view.php"); exit;
}

// ---------------- Filters (SMART SEARCH like React) ----------------
$q = trim($_GET["q"] ?? "");
$st = trim($_GET["st"] ?? "");
$from = trim($_GET["from"] ?? "");
$to = trim($_GET["to"] ?? "");
$sort = trim($_GET["sort"] ?? "");

// ---------- AJAX: search appointments (return patients and doctors separately) ----------
if (isset($_GET["ajax"]) && $_GET["ajax"] === "search_appointments") {
  if (ob_get_length()) ob_clean();
  header("Content-Type: application/json; charset=utf-8");
  $term = trim($_GET["term"] ?? "");
  $scope = trim($_GET["scope"] ?? "both"); // 'patients', 'doctors', or 'both'
  if ($term === "" || mb_strlen($term) < 1) { echo json_encode(["ok"=>true, "patients"=>[], "doctors"=>[]]); exit; }

  $like = "%" . $term . "%";
  $limit = 30;
  $result = ["ok"=>true, "patients"=>[], "doctors"=>[]];

  if ($scope === 'both' || $scope === 'patients') {
    $sqlP = "SELECT DISTINCT p.id, p.full_name, p.phone, p.patient_code
      FROM patients p
      JOIN appointments a ON a.patient_id = p.id
      WHERE (p.full_name LIKE :t OR p.phone LIKE :t)
      ORDER BY p.full_name ASC LIMIT " . (int)$limit;
    $stmtP = $pdo->prepare($sqlP);
    $stmtP->bindValue(":t", $like);
    $stmtP->execute();
    $result["patients"] = $stmtP->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  if ($scope === 'both' || $scope === 'doctors' || $scope === 'employees') {
    $sqlD = "SELECT DISTINCT e.id, e.full_name, e.job_title, e.phone, e.emp_code
      FROM employees e
      JOIN appointments a ON a.employee_id = e.id
      WHERE (e.full_name LIKE :t OR e.phone LIKE :t) AND e.status='ACTIVE'
      ORDER BY e.full_name ASC LIMIT " . (int)$limit;
    $stmtD = $pdo->prepare($sqlD);
    $stmtD->bindValue(":t", $like);
    $stmtD->execute();
    $result["doctors"] = $stmtD->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  $format = trim($_GET["format"] ?? ""); // optional: 'rows' to return combined rows like some endpoints
  if ($format === 'rows') {
    $rows = [];
    foreach ($result["patients"] as $p) { $p["type"] = "patient"; $rows[] = $p; }
    foreach ($result["doctors"] as $d) { $d["type"] = "doctor"; $rows[] = $d; }
    echo json_encode(["ok"=>true, "rows"=>$rows]);
    exit;
  }

  echo json_encode($result);
  exit;
}

// ---------- AJAX: doctor schedule (taken appointments for doctor on a date) ----------
if (isset($_GET["ajax"]) && $_GET["ajax"] === "doctor_schedule") {
  if (ob_get_length()) ob_clean();
  header("Content-Type: application/json; charset=utf-8");
  $doctor_id = (int)($_GET["doctor_id"] ?? 0);
  $date = trim($_GET["date"] ?? ""); // YYYY-MM-DD
  if ($doctor_id <= 0 || $date === "") { echo json_encode(["ok"=>false, "error"=>"Missing parameters"]); exit; }

  $stmtS = $pdo->prepare("SELECT a.id, a.appointment_datetime, p.full_name AS patient_name, p.phone AS patient_phone FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.employee_id = :did AND DATE(a.appointment_datetime) = :d ORDER BY a.appointment_datetime ASC");
  $stmtS->bindValue(':did', $doctor_id, PDO::PARAM_INT);
  $stmtS->bindValue(':d', $date);
  $stmtS->execute();
  $rowsS = $stmtS->fetchAll(PDO::FETCH_ASSOC) ?: [];
  echo json_encode(["ok"=>true, "rows"=>$rowsS]);
  exit;
}

// Build WHERE
$where = [];
$params = [];

$allowedStatus = ["PENDING","CONFIRMED","COMPLETED","CANCELLED"];
if ($st !== "" && in_array($st, $allowedStatus, true)) {
  $where[] = "a.status = :st";
  $params[":st"] = $st;
}

// date range
if ($from !== "") {
  // from date => 00:00
  $where[] = "a.appointment_datetime >= :fromdt";
  $params[":fromdt"] = $from . " 00:00:00";
}
if ($to !== "") {
  // to date => end of day
  $where[] = "a.appointment_datetime <= :todt";
  $params[":todt"] = $to . " 23:59:59";
}

// multi-term search: patient name/phone OR doctor name/phone (NO reused placeholders)
if ($q !== "") {
  $terms = preg_split('/\s+/', $q);
  $terms = array_values(array_filter($terms, fn($t)=>$t !== ""));

  $i = 0;
  foreach ($terms as $t) {
    $k1 = ":t{$i}_pname";
    $k2 = ":t{$i}_pphone";
    $k3 = ":t{$i}_dname";
    $k4 = ":t{$i}_dphone";

    $where[] = "(
      p.full_name LIKE $k1
      OR p.phone LIKE $k2
      OR e.full_name LIKE $k3
      OR e.phone LIKE $k4
    )";

    $val = "%$t%";
    $params[$k1] = $val;
    $params[$k2] = $val;
    $params[$k3] = $val;
    $params[$k4] = $val;

    $i++;
  }
}


$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// Determine ordering from single `sort` param (defaults to newest/date desc)
$orderSql = 'ORDER BY a.appointment_datetime DESC'; // Default ordering
$s = strtolower($sort);
switch ($s) {
  case 'date_asc':
    $orderSql = 'ORDER BY a.appointment_datetime ASC';
    break;
  case 'date_desc':
    $orderSql = 'ORDER BY a.appointment_datetime DESC';
    break;
  case 'patient_az':
    $orderSql = 'ORDER BY p.full_name ASC';
    break;
  case 'patient_za':
    $orderSql = 'ORDER BY p.full_name DESC';
    break;
  case 'employee_az':
    $orderSql = 'ORDER BY e.full_name ASC';
    break;
  case 'employee_za':
    $orderSql = 'ORDER BY e.full_name DESC';
    break;
  default:
    // keep default
    break;
}

// ---------------- Stats ----------------
$totalAppt = (int)$pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$pendingAppt = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status='PENDING'")->fetchColumn();
$todayAppt = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_datetime)=CURDATE()")->fetchColumn();
$completedAppt = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status='COMPLETED'")->fetchColumn();

// ---------------- List ----------------
$sql = "
  SELECT
    a.*,
    p.full_name AS patient_name, p.patient_code, p.phone AS patient_phone,
    e.full_name AS employee_name, e.emp_code, e.job_title, e.phone AS employee_phone
  FROM appointments a
  JOIN patients p ON p.id = a.patient_id
  JOIN employees e ON e.id = a.employee_id
  $whereSql
  $orderSql
  LIMIT 300
";
$stmt = $pdo->prepare($sql);
$placeholders = [];
if (preg_match_all('/(:[a-zA-Z0-9_]+)/', $sql, $m)) {
  $placeholders = array_values(array_unique($m[1]));
}
error_log('appointments/view.php - sql_placeholders: ' . implode(', ', $placeholders));
error_log('appointments/view.php - params_keys: ' . implode(', ', array_keys($params)));

$unmatchedParams = [];
$unmatchedPlaceholders = [];

// Bind values for placeholders found in SQL
foreach ($placeholders as $ph) {
  // ph includes leading ':'
  if (array_key_exists($ph, $params)) {
    $stmt->bindValue($ph, $params[$ph]);
    continue;
  }
  // try without colon
  $phNo = ltrim($ph, ':');
  if (array_key_exists($phNo, $params)) {
    $stmt->bindValue($ph, $params[$phNo]);
    continue;
  }
  $unmatchedPlaceholders[] = $ph;
}

// Log any params that don't map to placeholders
foreach ($params as $k => $v) {
  if (!is_string($k)) continue;
  if (strpos($k, ':') !== 0) $kname = ':' . $k; else $kname = $k;
  if (!in_array($kname, $placeholders, true)) $unmatchedParams[] = $kname;
}

if ($unmatchedPlaceholders) error_log('appointments/view.php - unmatched_sql_placeholders: ' . implode(', ', $unmatchedPlaceholders));
if ($unmatchedParams) error_log('appointments/view.php - unmatched_params: ' . implode(', ', $unmatchedParams));

$stmt->execute();
$rows = $stmt->fetchAll() ?: [];

$flash = flash_get();
include_once __DIR__ . "/../includes/header.php";
?>
 <div class="min-h-screen bg-slate-50 md:pl-72">
<div class="md:flex min-h-screen">
  <?php include __DIR__ . "/../includes/sidebar.php"; ?>

  <div class="flex-1">
    <div class="mx-auto max-w-6xl px-4 py-6">

      <!-- Page header -->
      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
          <div class="text-2xl font-extrabold tracking-tight">Appointments</div>
          <div class="text-sm font-semibold text-slate-500">Schedule and track visits</div>
        </div>

        <div class="flex items-center gap-2">
          <a href="/hospital/appointments/view.php"
             class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50">
            Refresh
          </a>
          <button id="openAddModal"
                  class="rounded-2xl bg-orange-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-orange-600">
            + Add Appointment
          </button>
        </div>
      </div>

      <?php if ($flash): ?>
        <div class="mt-4 rounded-2xl border px-4 py-3 text-sm font-semibold
          <?php echo $flash["type"] === "success" ? "border-green-200 bg-green-50 text-green-700" : "border-red-200 bg-red-50 text-red-700"; ?>">
          <?php echo h($flash["msg"]); ?>
        </div>
      <?php endif; ?>

      <!-- Stat cards -->
      <section class="mt-6 grid gap-4 md:grid-cols-4">
        <div class="rounded-3xl border bg-white p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">TOTAL</div>
          <div class="mt-2 text-3xl font-extrabold"><?php echo $totalAppt; ?></div>
        </div>
        <div class="rounded-3xl border bg-orange-50 p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">PENDING</div>
          <div class="mt-2 text-3xl font-extrabold"><?php echo $pendingAppt; ?></div>
        </div>
        <div class="rounded-3xl border bg-sky-50 p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">TODAY</div>
          <div class="mt-2 text-3xl font-extrabold"><?php echo $todayAppt; ?></div>
        </div>
        <div class="rounded-3xl border bg-emerald-50 p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">COMPLETED</div>
          <div class="mt-2 text-3xl font-extrabold"><?php echo $completedAppt; ?></div>
        </div>
      </section>

      <!-- Filters -->
      <section class="mt-6 rounded-3xl border bg-white p-4 shadow-sm">
        <form class="grid gap-3 lg:grid-cols-12" method="GET">
          <div class="lg:col-span-5">
            <div class="flex items-center gap-2 rounded-2xl border bg-white px-4 py-3">
              <span class="text-slate-400">🔎</span>
              <input name="q" value="<?php echo h($q); ?>"
                     class="w-full bg-transparent text-sm outline-none"
                   placeholder="Search patient name/phone or doctor name… (multi-word supported)" />
            </div>
          </div>

            <div class="lg:col-span-1">
              <select name="sort" class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
                <option value="">Sort</option>
                <option value="date_desc" <?php echo $sort==='date_desc' || $sort===''?"selected":""; ?>>Date ↓</option>
                <option value="date_asc" <?php echo $sort==='date_asc'?"selected":""; ?>>Date ↑</option>
                <option value="patient_az" <?php echo $sort==='patient_az'?"selected":""; ?>>Patient A–Z</option>
                <option value="patient_za" <?php echo $sort==='patient_za'?"selected":""; ?>>Patient Z–A</option>
                <option value="employee_az" <?php echo $sort==='employee_az'?"selected":""; ?>>Employee A–Z</option>
                <option value="employee_za" <?php echo $sort==='employee_za'?"selected":""; ?>>Employee Z–A</option>
              </select>
            </div>

          <div class="lg:col-span-2">
            <select name="st" class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">Status</option>
              <?php foreach (["PENDING","CONFIRMED","COMPLETED","CANCELLED"] as $s): ?>
                <option value="<?php echo h($s); ?>" <?php echo $st===$s?"selected":""; ?>><?php echo h($s); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="lg:col-span-2">
            <input type="date" name="from" value="<?php echo h($from); ?>"
              class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none"
              title="From date">
          </div>

          <div class="lg:col-span-2">
            <input type="date" name="to" value="<?php echo h($to); ?>"
              class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none"
              title="To date">
          </div>

          <div class="lg:col-span-12 flex items-center justify-between pt-1 text-sm">
            <div class="font-semibold text-slate-600">
              Showing: <span class="font-extrabold text-slate-900"><?php echo count($rows); ?></span>
            </div>
            <div class="flex gap-2">
              <button class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50" type="submit">
                Filter
              </button>
              <a href="/hospital/appointments/view.php" class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50">
                Reset
              </a>
            </div>
          </div>
        </form>
      </section>

      <!-- Table -->
      <section class="mt-6 rounded-3xl border bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs font-extrabold tracking-widest text-slate-500">
              <tr>
                <th class="px-5 py-4">DATE & TIME</th>
                <th class="px-5 py-4">PATIENT</th>
                <th class="px-5 py-4">EMPLOYEE</th>
                <th class="px-5 py-4">REASON</th>
                <th class="px-5 py-4">STATUS</th>
                <th class="px-5 py-4 text-right">ACTIONS</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <?php if (!$rows): ?>
                <tr>
                  <td colspan="6" class="px-5 py-10 text-center text-slate-500 font-semibold">
                    No appointments found.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <?php
                    $payload = base64_encode(json_encode($r));
                    $badge = match($r["status"]) {
                      "PENDING" => "bg-orange-100 text-orange-700",
                      "CONFIRMED" => "bg-sky-100 text-sky-700",
                      "COMPLETED" => "bg-emerald-100 text-emerald-700",
                      "CANCELLED" => "bg-rose-100 text-rose-700",
                      default => "bg-slate-100 text-slate-700"
                    };

                    $dt = date("Y-m-d H:i", strtotime($r["appointment_datetime"]));
                  ?>
                  <tr class="hover:bg-slate-50/60">
                    <td class="px-5 py-4 font-extrabold text-slate-900"><?php echo h($dt); ?></td>

                    <td class="px-5 py-4">
                      <div class="font-extrabold text-slate-900"><?php echo h($r["patient_name"]); ?></div>
                      <div class="text-xs font-semibold text-slate-500">
                        <?php echo h($r["patient_phone"] ?? "-"); ?>
                        <?php if (!empty($r["patient_code"])): ?> • <?php echo h($r["patient_code"]); ?><?php endif; ?>
                      </div>
                    </td>

                    <td class="px-5 py-4">
                      <div class="font-extrabold text-slate-900"><?php echo h($r["employee_name"]); ?></div>
                      <div class="text-xs font-semibold text-slate-500">
                        <?php echo h($r["job_title"] ?? "-"); ?>
                        <?php if (!empty($r["emp_code"])): ?> • <?php echo h($r["emp_code"]); ?><?php endif; ?>
                      </div>
                    </td>

                    <td class="px-5 py-4 font-semibold text-slate-700">
                      <?php echo h($r["reason"] ?? "-"); ?>
                    </td>

                    <td class="px-5 py-4">
                      <span class="inline-flex rounded-full px-3 py-1 text-xs font-extrabold <?php echo $badge; ?>">
                        <?php echo h($r["status"]); ?>
                      </span>
                    </td>

                    <td class="px-5 py-4">
                      <div class="flex justify-end gap-2">
                        <button type="button"
                          class="grid h-10 w-10 place-items-center rounded-2xl border bg-white hover:bg-slate-50"
                          title="Edit"
                          data-a="<?php echo h($payload); ?>"
                          onclick="openEdit(this)">✏️</button>

                        <form method="POST" onsubmit="return confirm('Delete this appointment?');">
                          <input type="hidden" name="action" value="delete_appointment">
                          <input type="hidden" name="id" value="<?php echo (int)$r["id"]; ?>">
                          <button type="submit"
                            class="grid h-10 w-10 place-items-center rounded-2xl border border-rose-200 bg-rose-50 hover:bg-rose-100"
                            title="Delete">🗑️</button>
                        </form>
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

  <!-- Modal: Doctor Schedule -->
  <div id="docScheduleModal" class="fixed inset-0 z-50 hidden">
    <div id="docScheduleOverlay" class="absolute inset-0 bg-slate-900/50 backdrop-blur-[1px]"></div>
    <div class="relative mx-auto flex min-h-full max-w-2xl items-center justify-center p-4">
      <div class="w-full max-w-2xl rounded-3xl bg-white shadow-2xl ring-1 ring-black/5 max-h-[80vh] overflow-auto">
        <div class="flex items-center justify-between border-b px-6 py-4">
          <div class="text-lg font-extrabold" id="docScheduleTitle">Doctor Schedule</div>
          <button type="button" id="closeDocSchedule" class="grid h-10 w-10 place-items-center rounded-2xl hover:bg-slate-50 text-slate-500">✕</button>
        </div>
        <div class="p-6" id="docScheduleBody">
          <div class="mb-4 flex items-center gap-2">
            <label class="text-xs font-extrabold text-slate-500">Date</label>
            <input type="date" id="docScheduleDate" class="ml-2 rounded-2xl border px-3 py-2 text-sm" />
            <button id="refreshDocSchedule" class="ml-2 rounded-2xl bg-sky-100 px-3 py-2 text-sm font-semibold">Refresh</button>
          </div>
          <div id="docScheduleList" class="text-sm text-slate-700">
            Loading...
          </div>
        </div>
      </div>
    </div>
  </div>

<!-- Modal Add/Edit -->
<div id="apptModal" class="fixed inset-0 z-50 hidden">
  <div id="apptOverlay" class="absolute inset-0 bg-slate-900/50 backdrop-blur-[1px]"></div>

  <div class="relative mx-auto flex min-h-full max-w-5xl items-center justify-center p-4">
    <div class="w-full max-w-4xl rounded-3xl bg-white shadow-2xl ring-1 ring-black/5 max-h-[90vh] overflow-auto">
      <div class="flex items-center justify-between border-b px-6 py-4">
        <div class="text-lg font-extrabold" id="apptTitle">Add Appointment</div>
        <button type="button" id="closeAppt"
          class="grid h-10 w-10 place-items-center rounded-2xl hover:bg-slate-50 text-slate-500">✕</button>
      </div>

      <form method="POST" class="p-6">
        <input type="hidden" name="action" value="save_appointment">
        <input type="hidden" name="id" id="a_id" value="">

        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">PATIENT *</label>
            <select id="a_patient" name="patient_id" required
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">Select patient</option>
              <?php foreach ($patients as $p): ?>
                <option value="<?php echo (int)$p["id"]; ?>">
                  <?php echo h($p["full_name"]); ?>
                  <?php if (!empty($p["phone"])) echo " • ".h($p["phone"]); ?>
                  <?php if (!empty($p["patient_code"])) echo " • ".h($p["patient_code"]); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">DOCTOR *</label>
            <select id="a_employee" name="employee_id" required
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">Select Doctor</option>
              <?php foreach ($employees as $e): ?>
                <option value="<?php echo (int)$e["id"]; ?>">
                  <?php echo h($e["full_name"]); ?> • <?php echo h($e["job_title"]); ?>
                  <?php if (!empty($e["phone"])) echo " • ".h($e["phone"]); ?>
                  <?php if (!empty($e["emp_code"])) echo " • ".h($e["emp_code"]); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">DATE & TIME *</label>
            <div class="flex items-center gap-2">
              <input id="a_datetime" name="appointment_datetime" type="datetime-local" required
                class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none">
              <button type="button" onclick="openDoctorSchedule()"
                class="mt-2 rounded-2xl border bg-white px-3 py-2 text-sm font-semibold hover:bg-slate-50">Show Taken</button>
            </div>
          </div>

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">STATUS</label>
            <select id="a_status" name="status"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="PENDING">PENDING</option>
              <option value="CONFIRMED">CONFIRMED</option>
              <option value="COMPLETED">COMPLETED</option>
              <option value="CANCELLED">CANCELLED</option>
            </select>
          </div>

          <div class="md:col-span-2">
            <label class="text-xs font-extrabold tracking-widest text-slate-500">REASON (optional)</label>
            <input id="a_reason" name="reason"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
              placeholder="e.g. checkup, fever, lab test">
          </div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button type="button" id="cancelAppt"
            class="rounded-2xl border bg-white px-5 py-3 text-sm font-extrabold hover:bg-slate-50">
            Cancel
          </button>
          <button type="submit"
            class="rounded-2xl bg-orange-500 px-5 py-3 text-sm font-extrabold text-white hover:bg-orange-600">
            Save Appointment →
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const modal = document.getElementById("apptModal");
  const overlay = document.getElementById("apptOverlay");
  const openAdd = document.getElementById("openAddModal");
  const closeBtn = document.getElementById("closeAppt");
  const cancelBtn = document.getElementById("cancelAppt");
  const title = document.getElementById("apptTitle");

  const f = {
    id: document.getElementById("a_id"),
    patient: document.getElementById("a_patient"),
    employee: document.getElementById("a_employee"),
    dt: document.getElementById("a_datetime"),
    reason: document.getElementById("a_reason"),
    status: document.getElementById("a_status"),
  };

  function openM(){ modal.classList.remove("hidden"); document.body.style.overflow="hidden"; }
  function closeM(){ modal.classList.add("hidden"); document.body.style.overflow=""; }
  function reset(){
    f.id.value="";
    f.patient.value="";
    f.employee.value="";
    f.dt.value="";
    f.reason.value="";
    f.status.value="PENDING";
  }

  openAdd?.addEventListener("click", () => {
    title.textContent = "Add Appointment";
    reset(); openM();
  });

  overlay?.addEventListener("click", closeM);
  closeBtn?.addEventListener("click", closeM);
  cancelBtn?.addEventListener("click", closeM);
  document.addEventListener("keydown", (e)=>{ if(e.key==="Escape" && !modal.classList.contains("hidden")) closeM(); });

  // Edit
  window.openEdit = function(btn){
    const raw = btn.getAttribute("data-a");
    if(!raw) return;
    let data = {};
    try { data = JSON.parse(atob(raw)); } catch(e){ return; }

    title.textContent = "Edit Appointment";
    f.id.value = data.id || "";
    f.patient.value = data.patient_id || "";
    f.employee.value = data.employee_id || "";
    f.reason.value = data.reason || "";
    f.status.value = data.status || "PENDING";

    // Convert "YYYY-MM-DD HH:MM:SS" to datetime-local format
    if (data.appointment_datetime) {
      const s = String(data.appointment_datetime).replace(" ", "T").slice(0,16);
      f.dt.value = s;
    } else {
      f.dt.value = "";
    }

    openM();
  }
})();
</script>

<script>
(function(){
  const modal = document.getElementById('docScheduleModal');
  const overlay = document.getElementById('docScheduleOverlay');
  const closeBtn = document.getElementById('closeDocSchedule');
  const title = document.getElementById('docScheduleTitle');
  const bodyList = document.getElementById('docScheduleList');
  const dateInput = document.getElementById('docScheduleDate');
  const refreshBtn = document.getElementById('refreshDocSchedule');

  function open() { modal.classList.remove('hidden'); document.body.style.overflow='hidden'; }
  function close(){ modal.classList.add('hidden'); document.body.style.overflow=''; }

  overlay?.addEventListener('click', close);
  closeBtn?.addEventListener('click', close);

  function formatTime(dtStr){ try{ const d=new Date(dtStr); return d.toLocaleString(); }catch(e){ return dtStr; } }

  async function loadSchedule(){
    bodyList.textContent = 'Loading...';
    const docSel = document.getElementById('a_employee');
    const did = docSel ? docSel.value : '';
    const d = dateInput.value || (document.getElementById('a_datetime')?.value || '').split('T')[0];
    if (!did){ bodyList.textContent='Select a doctor first.'; return; }
    if (!d){ bodyList.textContent='Select a date.'; return; }

    try{
      const res = await fetch(`/hospital/appointments/view.php?ajax=doctor_schedule&doctor_id=${encodeURIComponent(did)}&date=${encodeURIComponent(d)}`);
      const j = await res.json();
      if (!j.ok){ bodyList.textContent='No data'; return; }
      const rows = j.rows || [];
      if (rows.length === 0){ bodyList.innerHTML = '<div class="text-sm text-slate-500">No appointments for this doctor on the selected date.</div>'; return; }
      const ul = document.createElement('div');
      ul.className = 'grid gap-2';
      rows.forEach(r => {
        const el = document.createElement('div');
        el.className = 'rounded-2xl border px-4 py-2 bg-slate-50';
        el.textContent = (r.appointment_datetime ? formatTime(r.appointment_datetime) : r.appointment_datetime) + ' — ' + (r.patient_name || '');
        ul.appendChild(el);
      });
      bodyList.innerHTML = '';
      bodyList.appendChild(ul);
    } catch(e){ bodyList.textContent = 'Error loading schedule.'; }
  }

  // open schedule modal when called
  window.openDoctorSchedule = function(){
    // set date default from datetime input
    const dt = document.getElementById('a_datetime')?.value || '';
    if (dt && dt.includes('T')) dateInput.value = dt.split('T')[0];
    open(); loadSchedule();
  }

  refreshBtn?.addEventListener('click', loadSchedule);
})();
</script>

<?php include_once __DIR__ . "/../includes/footer.php"; ?>
