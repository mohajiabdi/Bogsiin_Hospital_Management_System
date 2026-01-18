<?php
// doctors/view.php  (Employees module)
$pageTitle = "Employees • Hospital";
require_once __DIR__ . "/../includes/auth_guard.php";
require_once __DIR__ . "/../includes/db.php";

// ---------- helpers ----------
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function flash_set($type, $msg){
  $_SESSION["flash"] = ["type"=>$type, "msg"=>$msg];
}
function flash_get(){
  if (!isset($_SESSION["flash"])) return null;
  $f = $_SESSION["flash"];
  unset($_SESSION["flash"]);
  return $f;
}

// ---------- handle POST actions ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "";

  // ADD or EDIT
  if ($action === "save_employee") {
    $id = trim($_POST["id"] ?? "");

    $full_name = trim($_POST["full_name"] ?? "");
    $job_title = trim($_POST["job_title"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $specialization = trim($_POST["specialization"] ?? "");
    $gender = trim($_POST["gender"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $hire_date = trim($_POST["hire_date"] ?? "");
    $status = trim($_POST["status"] ?? "ACTIVE");
    $emp_code = trim($_POST["emp_code"] ?? "");

    if ($full_name === "" || $job_title === "") {
      flash_set("error", "Full name and job title are required.");
      header("Location: /hospital/employees/view.php");
      exit;
    }

    // normalize optional date
    $hire_date_db = ($hire_date !== "") ? $hire_date : null;

    if ($id === "") {
      // INSERT
      $stmt = $pdo->prepare("
        INSERT INTO employees (emp_code, full_name, gender, phone, email, job_title, department, specialization, hire_date, status)
        VALUES (:emp_code, :full_name, :gender, :phone, :email, :job_title, :department, :specialization, :hire_date, :status)
      ");
      $stmt->execute([
        ":emp_code" => $emp_code !== "" ? $emp_code : null,
        ":full_name" => $full_name,
        ":gender" => ($gender !== "") ? $gender : null,
        ":phone" => ($phone !== "") ? $phone : null,
        ":email" => ($email !== "") ? $email : null,
        ":job_title" => $job_title,
        ":department" => ($department !== "") ? $department : null,
        ":specialization" => ($specialization !== "") ? $specialization : null,
        ":hire_date" => $hire_date_db,
        ":status" => ($status !== "") ? $status : "ACTIVE",
      ]);

      flash_set("success", "Employee added successfully.");
      header("Location: /hospital/employees/view.php");
      exit;
    } else {
      // UPDATE
      $stmt = $pdo->prepare("
        UPDATE employees SET
          emp_code = :emp_code,
          full_name = :full_name,
          gender = :gender,
          phone = :phone,
          email = :email,
          job_title = :job_title,
          department = :department,
          specialization = :specialization,
          hire_date = :hire_date,
          status = :status
        WHERE id = :id
      ");
      $stmt->execute([
        ":emp_code" => $emp_code !== "" ? $emp_code : null,
        ":full_name" => $full_name,
        ":gender" => ($gender !== "") ? $gender : null,
        ":phone" => ($phone !== "") ? $phone : null,
        ":email" => ($email !== "") ? $email : null,
        ":job_title" => $job_title,
        ":department" => ($department !== "") ? $department : null,
        ":specialization" => ($specialization !== "") ? $specialization : null,
        ":hire_date" => $hire_date_db,
        ":status" => ($status !== "") ? $status : "ACTIVE",
        ":id" => (int)$id,
      ]);

      flash_set("success", "Employee updated successfully.");
      header("Location: /hospital/employees/view.php");
      exit;
    }
  }

  // DELETE
  if ($action === "delete_employee") {
    $id = (int)($_POST["id"] ?? 0);
    if ($id > 0) {
      $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
      $stmt->execute([$id]);
      flash_set("success", "Employee deleted.");
    } else {
      flash_set("error", "Invalid employee.");
    }
    header("Location: /hospital/employees/view.php");
    exit;
  }
}

// ---------- filters ----------
$q = trim($_GET["q"] ?? "");
$job = trim($_GET["job"] ?? "");
$st = trim($_GET["st"] ?? "");
$sort = trim($_GET["sort"] ?? "");

// ---------- AJAX: search employees (JSON) ----------
if (isset($_GET["ajax"]) && $_GET["ajax"] === "search_employees") {
  if (ob_get_length()) ob_clean();
  header("Content-Type: application/json; charset=utf-8");
  $term = trim($_GET["term"] ?? "");
  if ($term === "" || mb_strlen($term) < 2) { echo json_encode(["ok"=>true, "rows"=>[]]); exit; }
  $stmt = $pdo->prepare("SELECT id, full_name, phone, email, emp_code FROM employees WHERE full_name LIKE :t OR phone LIKE :t OR email LIKE :t OR emp_code LIKE :t ORDER BY full_name ASC LIMIT 30");
  $like = "%".$term."%";
  $stmt->execute([
    ":t" => $like,
  ]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  echo json_encode(["ok"=>true, "rows"=>$rows]);
  exit;
}

// build WHERE
$where = [];
$params = [];

if ($q !== "") {
  $terms = preg_split('/\s+/', $q);
  $terms = array_values(array_filter($terms, fn($t) => $t !== ""));
  $i = 0;
  foreach ($terms as $t) {
    $tn = ":t{$i}_name";
    $tp = ":t{$i}_phone";
    $te = ":t{$i}_email";
    $tc = ":t{$i}_code";
    $ts = ":t{$i}_spec";
    $td = ":t{$i}_dept";
    $clause = "(full_name LIKE $tn OR phone LIKE $tp OR email LIKE $te OR emp_code LIKE $tc OR specialization LIKE $ts OR department LIKE $td";
    if (is_numeric($t)) {
      $ik = ":id".$i;
      $clause .= " OR id = $ik";
      $params[$ik] = (int)$t;
    }
    $clause .= ")";
    $where[] = $clause;
    $params[$tn] = "%$t%";
    $params[$tp] = "%$t%";
    $params[$te] = "%$t%";
    $params[$tc] = "%$t%";
    $params[$ts] = "%$t%";
    $params[$td] = "%$t%";
    $i++;
  }
}
if ($job !== "") {
  $where[] = "job_title = :job";
  $params[":job"] = $job;
}
if ($st !== "") {
  $where[] = "status = :st";
  $params[":st"] = $st;
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// stats
$totalEmployees = (int)$pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$activeEmployees = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status='ACTIVE'")->fetchColumn();
$totalDoctors = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE job_title='DOCTOR'")->fetchColumn();
$totalNurses = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE job_title='NURSE'")->fetchColumn();

// list
$rows = [];
// determine ordering from `sort` param
$sort = strtolower(trim($sort));
switch ($sort) {
  case 'id_up':
    $orderSql = 'ORDER BY id ASC';
    break;
  case 'id_down':
    $orderSql = 'ORDER BY id DESC';
    break;
  case 'name_az':
    $orderSql = 'ORDER BY full_name ASC';
    break;
  case 'name_za':
    $orderSql = 'ORDER BY full_name DESC';
    break;
  case 'oldest':
    $orderSql = 'ORDER BY created_at ASC';
    break;
  case 'newest':
  default:
    $orderSql = 'ORDER BY created_at DESC';
    break;
}

try {
  $sql = "SELECT * FROM employees $whereSql $orderSql LIMIT 300";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
  $rows = [];
  flash_set("error", "Filter error: " . $e->getMessage());
}

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
          <div class="text-2xl font-extrabold tracking-tight">Employees</div>
          <div class="text-sm font-semibold text-slate-500">Manage doctors, nurses, administrators, and staff</div>
        </div>

        <div class="flex items-center gap-2">
          <a href="/hospital/employees/view.php"
             class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50">
            Refresh
          </a>
          <button id="openAddModal"
                  class="rounded-2xl bg-orange-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-orange-600">
            + Add Employee
          </button>
        </div>
      </div>

      <?php if ($flash): ?>
        <div class="mt-4 rounded-2xl border px-4 py-3 text-sm font-semibold
          <?php echo $flash["type"] === "success" ? "border-green-200 bg-green-50 text-green-700" : "border-red-200 bg-red-50 text-red-700"; ?>">
          <?php echo h($flash["msg"]); ?>
        </div>
      <?php endif; ?>

      <!-- Stat cards (like your reference) -->
      <section class="mt-6 grid gap-4 md:grid-cols-4">
        <div class="rounded-3xl border bg-white p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">TOTAL</div>
          <div class="mt-2 text-3xl font-extrabold"><?php echo $totalEmployees; ?></div>
        </div>

        <div class="rounded-3xl border bg-orange-50 p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">ACTIVE</div>
          <div class="mt-2 text-3xl font-extrabold"><?php echo $activeEmployees; ?></div>
        </div>

        <div class="rounded-3xl border bg-rose-50 p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">DOCTORS</div>
          <div class="mt-2 text-3xl font-extrabold"><?php echo $totalDoctors; ?></div>
        </div>

        <div class="rounded-3xl border bg-sky-50 p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">NURSES</div>
          <div class="mt-2 text-3xl font-extrabold"><?php echo $totalNurses; ?></div>
        </div>
      </section>

      <!-- Search + Filters bar -->
      <section class="mt-6 rounded-3xl border bg-white p-4 shadow-sm">
        <form class="grid gap-3 lg:grid-cols-12" method="GET">
          <div class="lg:col-span-6">
            <div class="flex items-center gap-2 rounded-2xl border bg-white px-4 py-3">
              <span class="text-slate-400">🔎</span>
              <input name="q" value="<?php echo h($q); ?>"
                     class="w-full bg-transparent text-sm outline-none"
                     placeholder="Search by name, phone, id, or code… (multi-word supported)" />
            </div>
          </div>

          <div class="lg:col-span-3">
            <select name="sort" class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">Sort</option>
              <option value="id_up" <?php echo $sort==="id_up"?"selected":""; ?>>ID ↑</option>
              <option value="id_down" <?php echo $sort==="id_down"?"selected":""; ?>>ID ↓</option>
              <option value="name_az" <?php echo $sort==="name_az"?"selected":""; ?>>Name A–Z</option>
              <option value="name_za" <?php echo $sort==="name_za"?"selected":""; ?>>Name Z–A</option>
              <option value="newest" <?php echo ($sort==="newest" || $sort==="")?"selected":""; ?>>Newest</option>
              <option value="oldest" <?php echo $sort==="oldest"?"selected":""; ?>>Oldest</option>
            </select>
          </div>

          <div class="lg:col-span-2">
            <select name="job" class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">Job Title</option>
              <?php
              $jobs = ["DOCTOR","NURSE","ADMINISTRATOR","LAB","PHARMACIST","RECEPTIONIST"];
              foreach ($jobs as $j) {
                $sel = ($job === $j) ? "selected" : "";
                echo "<option $sel value='".h($j)."'>".h($j)."</option>";
              }
              ?>
            </select>
          </div>

          <div class="lg:col-span-1 flex gap-2">
            <button class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-extrabold hover:bg-slate-50" type="submit">
              Filter
            </button>
          </div>
        </form>

        <div class="mt-3 flex items-center justify-between text-sm">
          <div class="font-semibold text-slate-600">Showing: <span class="font-extrabold text-slate-900"><?php echo count($rows); ?></span></div>
          <a href="/hospital/employees/view.php" class="font-extrabold text-orange-600 hover:text-orange-700">Reset</a>
        </div>
      </section>

      <!-- Table -->
      <section class="mt-6 rounded-3xl border bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs font-extrabold tracking-widest text-slate-500">
              <tr>
                <th class="px-5 py-4">EMPLOYEE</th>
                <th class="px-5 py-4">JOB</th>
                <th class="px-5 py-4">DEPARTMENT</th>
                <th class="px-5 py-4">CONTACT</th>
                <th class="px-5 py-4">STATUS</th>
                <th class="px-5 py-4 text-right">ACTIONS</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <?php if (!$rows): ?>
                <tr>
                  <td colspan="6" class="px-5 py-10 text-center text-slate-500 font-semibold">
                    No employees found.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <?php
                    $badge = ($r["status"] === "ACTIVE")
                      ? "bg-emerald-100 text-emerald-700"
                      : "bg-slate-100 text-slate-700";
                  ?>
                  <tr class="hover:bg-slate-50/60">
                    <td class="px-5 py-4">
                      <div class="flex items-center gap-3">
                        <?php
                          $icon_name = 'person';
                          $bg = 'bg-orange-100';
                          $g = strtolower(trim((string)($r['gender'] ?? '')));
                          if ($g === 'female' || $g === 'f') { $icon_name = 'female'; $bg = 'bg-rose-100'; }
                          else if ($g === 'male' || $g === 'm') { $icon_name = 'male'; $bg = 'bg-sky-100'; }
                        ?>
                        <div class="h-10 w-10 rounded-2xl <?php echo $bg; ?> flex items-center justify-center">
                          <span class="material-icons text-slate-700 text-xl" aria-hidden="true"><?php echo $icon_name; ?></span>
                        </div>
                        <div class="min-w-0">
                          <div class="font-extrabold text-slate-900 truncate"><?php echo h($r["full_name"]); ?></div>
                          <div class="text-xs font-semibold text-slate-500">
                            <?php echo $r["emp_code"] ? "ID: ".h($r["emp_code"]) : "ID: EMP-".h($r["id"]); ?>
                            <?php if (!empty($r["specialization"])): ?>
                              • <?php echo h($r["specialization"]); ?>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </td>

                    <td class="px-5 py-4 font-bold text-slate-800"><?php echo h($r["job_title"]); ?></td>
                    <td class="px-5 py-4 font-semibold text-slate-700"><?php echo h($r["department"] ?? "-"); ?></td>

                    <td class="px-5 py-4">
                      <div class="text-sm font-bold text-slate-800"><?php echo h($r["phone"] ?? "-"); ?></div>
                      <div class="text-xs font-semibold text-slate-500"><?php echo h($r["email"] ?? "-"); ?></div>
                    </td>

                    <td class="px-5 py-4">
                      <span class="inline-flex rounded-full px-3 py-1 text-xs font-extrabold <?php echo $badge; ?>">
                        <?php echo h($r["status"]); ?>
                      </span>
                    </td>

                    <td class="px-5 py-4">
                      <div class="flex justify-end gap-2">
                        <button
                          type="button"
                          class="grid h-10 w-10 place-items-center rounded-2xl border bg-white hover:bg-slate-50"
                          title="Edit"
                        data-edit="<?= base64_encode(json_encode($r)) ?>"

                          onclick="openEdit(this)"
                        >✏️</button>

                        <form method="POST" onsubmit="return confirm('Delete this employee?');">
                          <input type="hidden" name="action" value="delete_employee">
                          <input type="hidden" name="id" value="<?php echo (int)$r["id"]; ?>">
                          <button
                            type="submit"
                            class="grid h-10 w-10 place-items-center rounded-2xl border border-rose-200 bg-rose-50 hover:bg-rose-100"
                            title="Delete"
                          >🗑️</button>
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

<!-- Modal (Add/Edit) -->
<div id="empModal" class="fixed inset-0 z-50 hidden">
  <div id="empOverlay" class="absolute inset-0 bg-slate-900/50 backdrop-blur-[1px]"></div>

  <div class="relative mx-auto flex min-h-full max-w-5xl items-center justify-center p-4">
    <div class="w-full max-w-4xl max-h-[90vh] overflow-auto rounded-3xl bg-white shadow-2xl ring-1 ring-black/5">
      <div class="flex items-center justify-between border-b px-6 py-4">
        <div class="text-lg font-extrabold" id="empModalTitle">Add Employee</div>
        <button type="button" id="closeEmpModal"
                class="grid h-10 w-10 place-items-center rounded-2xl hover:bg-slate-50 text-slate-500">✕</button>
      </div>

      <form method="POST" class="p-6">
        <input type="hidden" name="action" value="save_employee">
        <input type="hidden" name="id" id="emp_id" value="">

        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">FULL NAME *</label>
            <input id="full_name" name="full_name" required
                   class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
                   placeholder="e.g. Dr. Ahmed Ali">
          </div>

          

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">JOB TITLE *</label>
            <select id="job_title" name="job_title" required
                    class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">Select job</option>
              <option>DOCTOR</option>
              <option>NURSE</option>
              <option>ADMINISTRATOR</option>
              <option>LAB</option>
              <option>PHARMACIST</option>
              <option>RECEPTIONIST</option>
            </select>
          </div>

          

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">GENDER (optional)</label>
            <select id="gender" name="gender"
                    class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">Select gender</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">PHONE (optional)</label>
            <input id="phone" name="phone"
                   class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
                   placeholder="+252 ...">
          </div>

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">EMAIL (optional)</label>
            <input id="email" name="email" type="email"
                   class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
                   placeholder="name@hospital.com">
          </div>

          

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">STATUS</label>
            <select id="status" name="status"
                    class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="ACTIVE">ACTIVE</option>
              <option value="INACTIVE">INACTIVE</option>
            </select>
          </div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button type="button" id="cancelEmp"
                  class="rounded-2xl border bg-white px-5 py-3 text-sm font-extrabold hover:bg-slate-50">
            Cancel
          </button>
          <button type="submit"
                  class="rounded-2xl bg-orange-500 px-5 py-3 text-sm font-extrabold text-white hover:bg-orange-600">
            Save Employee →
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const modal = document.getElementById("empModal");
  const overlay = document.getElementById("empOverlay");
  const openAdd = document.getElementById("openAddModal");
  const closeBtn = document.getElementById("closeEmpModal");
  const cancelBtn = document.getElementById("cancelEmp");
  const title = document.getElementById("empModalTitle");

  // fields
  const f = {
    id: document.getElementById("emp_id"),
    full_name: document.getElementById("full_name"),
    job_title: document.getElementById("job_title"),
    gender: document.getElementById("gender"),
    phone: document.getElementById("phone"),
    email: document.getElementById("email"),
    status: document.getElementById("status"),
  };

  function openModal() {
    modal.classList.remove("hidden");
    document.body.style.overflow = "hidden";
  }
  function closeModal() {
    modal.classList.add("hidden");
    document.body.style.overflow = "";
  }

  function resetForm() {
    f.id.value = "";
    f.full_name.value = "";
    f.job_title.value = "";
    f.gender.value = "";
    f.phone.value = "";
    f.email.value = "";
    f.status.value = "ACTIVE";
  }

  openAdd?.addEventListener("click", () => {
    title.textContent = "Add Employee";
    resetForm();
    openModal();
    setTimeout(() => f.full_name.focus(), 50);
  });

  overlay?.addEventListener("click", closeModal);
  closeBtn?.addEventListener("click", closeModal);
  cancelBtn?.addEventListener("click", closeModal);
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !modal.classList.contains("hidden")) closeModal();
  });

  // edit handler
  window.openEdit = function(btn) {
    const raw = btn.getAttribute("data-edit");
    if (!raw) return;
  let data = {};
try {
  data = JSON.parse(atob(raw));
} catch (e) {
  return;
}


    title.textContent = "Edit Employee";
    f.id.value = data.id || "";
    f.full_name.value = data.full_name || "";
    f.job_title.value = data.job_title || "";
    f.gender.value = data.gender || "";
    f.phone.value = data.phone || "";
    f.email.value = data.email || "";
    f.status.value = data.status || "ACTIVE";

    openModal();
    setTimeout(() => f.full_name.focus(), 50);
  }
</script>


