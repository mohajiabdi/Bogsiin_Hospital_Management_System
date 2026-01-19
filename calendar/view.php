<?php
$pageTitle = "Calendar • Hospital";
require_once __DIR__ . "/../includes/auth_guard.php";
require_once __DIR__ . "/../includes/db.php";
include_once __DIR__ . "/../includes/header.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$month = (int)($_GET["m"] ?? date("n"));
$year  = (int)($_GET["y"] ?? date("Y"));

if ($month < 1 || $month > 12) $month = (int)date("n");
if ($year < 2000 || $year > 2100) $year = (int)date("Y");

$firstDay = sprintf("%04d-%02d-01", $year, $month);
$lastDay  = date("Y-m-t", strtotime($firstDay));

$rows = $pdo->prepare("
  SELECT DATE(appointment_datetime) d, COUNT(*) cnt
  FROM appointments
  WHERE DATE(appointment_datetime) BETWEEN :from AND :to
  GROUP BY DATE(appointment_datetime)
");
$rows->execute([":from"=>$firstDay, ":to"=>$lastDay]);
$data = $rows->fetchAll() ?: [];

$map = [];
foreach ($data as $r) $map[$r["d"]] = (int)$r["cnt"];

// month navigation
$prev = strtotime("-1 month", strtotime($firstDay));
$next = strtotime("+1 month", strtotime($firstDay));
?>
<div class="min-h-screen bg-slate-50 md:pl-72">
  <div class="md:flex">
    <?php include __DIR__ . "/../includes/sidebar.php"; ?>

    <div class="flex-1">
      <div class="mx-auto max-w-6xl px-4 py-6">

        <div class="flex items-center justify-between">
          <div>
            <div class="text-2xl font-extrabold tracking-tight">Appointments Calendar</div>
            <div class="text-sm font-semibold text-slate-500">Monthly view (click a day to see appointments)</div>
          </div>
          <a href="/hospital/dashboard.php" class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50">
            Back to Dashboard
          </a>
        </div>

        <div class="mt-6 rounded-3xl border bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <div class="font-extrabold text-slate-900">
              <?= h(date("F Y", strtotime($firstDay))); ?>
            </div>
            <div class="flex gap-2">
              <a class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50"
                 href="?m=<?= (int)date("n",$prev); ?>&y=<?= (int)date("Y",$prev); ?>">← Prev</a>
              <a class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50"
                 href="?m=<?= (int)date("n",$next); ?>&y=<?= (int)date("Y",$next); ?>">Next →</a>
            </div>
          </div>

          <!-- day name header -->
          <div class="mt-4 grid grid-cols-7 gap-2">
            <?php
              $week = ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"];
              foreach ($week as $w) {
                echo '<div class="rounded-2xl border bg-slate-50 py-2 text-center text-[11px] font-extrabold tracking-widest text-slate-500">'
                  . strtoupper($w) .
                '</div>';
              }
            ?>
          </div>

          <?php
            $startDow = (int)date("w", strtotime($firstDay)); // 0=Sun
            $daysInMonth = (int)date("t", strtotime($firstDay));
            $cells = $startDow + $daysInMonth;
            $rowsCount = (int)ceil($cells / 7);
            $totalCells = $rowsCount * 7;

            $today = date("Y-m-d");
          ?>

          <!-- calendar grid -->
          <div class="mt-2 grid grid-cols-7 gap-2">
            <?php for ($i=0; $i<$totalCells; $i++):
              $day = $i - $startDow + 1;

              // empty cells
              if ($day < 1 || $day > $daysInMonth) {
                echo '<div class="h-24 rounded-2xl border bg-white/40"></div>';
                continue;
              }

              $d = sprintf("%04d-%02d-%02d", $year, $month, $day);
              $cnt = (int)($map[$d] ?? 0);

              $cls = "bg-white";
              if ($d === $today) $cls = "bg-orange-50 border-orange-200";

              $base = "h-24 rounded-2xl border $cls p-3 flex flex-col justify-between";
            ?>

              <?php if ($cnt > 0): ?>
                <!-- ✅ clickable only if appointments exist -->
                <a href="/hospital/appointments/view.php?date=<?= h($d); ?>"
                   class="<?= $base; ?> hover:bg-slate-50 transition">
                  <div class="flex items-center justify-between">
                    <div class="text-sm font-extrabold text-slate-900"><?= (int)$day; ?></div>
                    <span class="rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-extrabold text-orange-700"><?= $cnt; ?></span>
                  </div>
                  <div class="text-[11px] font-semibold text-slate-600">appointments</div>
                </a>
              <?php else: ?>
                <!-- ✅ NOT clickable if no appointments -->
                <div class="<?= $base; ?> opacity-60 cursor-not-allowed select-none">
                  <div class="flex items-center justify-between">
                    <div class="text-sm font-extrabold text-slate-900"><?= (int)$day; ?></div>
                    <span class="text-[10px] font-extrabold text-slate-400">—</span>
                  </div>
                  <div class="text-[11px] font-semibold text-slate-500">no appointments</div>
                </div>
              <?php endif; ?>

            <?php endfor; ?>
          </div>

          <div class="mt-4 text-xs font-semibold text-slate-500">
            Tip: Clicking a day opens appointments list filtered by that date.
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
