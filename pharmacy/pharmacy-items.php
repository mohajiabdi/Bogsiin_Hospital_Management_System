<?php
// /hospital/pharmacy/seed_pharmacy_items.php
require_once __DIR__ . "/../includes/auth_guard.php";
require_once __DIR__ . "/../includes/db.php";

/**
 * Inserts 300 diversified pharmacy items into pharmacy_items table:
 * (item_name includes strength mg/g/ml, plus type)
 * Columns: item_name, company_name, unit_price
 */

function pick(array $arr, int $i) { return $arr[$i % count($arr)]; }

// OPTIONAL: Clear existing data first
$CLEAR_FIRST = false; // set true if you want to wipe table before seeding

if ($CLEAR_FIRST) {
  $pdo->exec("TRUNCATE TABLE pharmacy_items");
}

$companies = [
  "Hikma", "Cipla", "Sandoz", "Teva", "Pfizer", "GSK", "Sanofi", "Bayer",
  "Abbott", "Novartis", "Roche", "AstraZeneca", "MSD", "Sun Pharma",
  "Dr. Reddy's", "Ajanta", "Lupin", "Zydus", "Aurobindo", "Alkem",
  "Local Pharma Co.", "SomMed", "EastAfrica Pharma", "Mogadishu Medical"
];

// --- 1) Medicines base list (will expand to ~200 items) ---
$medicineBases = [
  ["Paracetamol", "Tablet"],
  ["Ibuprofen", "Tablet"],
  ["Diclofenac", "Tablet"],
  ["Aspirin", "Tablet"],
  ["Amoxicillin", "Capsule"],
  ["Amoxicillin/Clavulanate", "Tablet"],
  ["Azithromycin", "Tablet"],
  ["Ciprofloxacin", "Tablet"],
  ["Metronidazole", "Tablet"],
  ["Doxycycline", "Capsule"],
  ["Cephalexin", "Capsule"],
  ["Cefixime", "Tablet"],
  ["Ceftriaxone", "Injection"],
  ["Gentamicin", "Injection"],
  ["Dexamethasone", "Injection"],
  ["Hydrocortisone", "Injection"],
  ["Omeprazole", "Capsule"],
  ["Pantoprazole", "Tablet"],
  ["Famotidine", "Tablet"],
  ["Loratadine", "Tablet"],
  ["Cetirizine", "Tablet"],
  ["Chlorpheniramine", "Tablet"],
  ["Salbutamol", "Inhaler"],
  ["Salbutamol", "Syrup"],
  ["Prednisolone", "Tablet"],
  ["Amlodipine", "Tablet"],
  ["Losartan", "Tablet"],
  ["Atenolol", "Tablet"],
  ["Metformin", "Tablet"],
  ["Glibenclamide", "Tablet"],
  ["Insulin Regular", "Injection"],
  ["Insulin NPH", "Injection"],
  ["ORS", "Sachet"],
  ["Zinc", "Tablet"],
  ["Ferrous Sulfate", "Tablet"],
  ["Folic Acid", "Tablet"],
  ["Vitamin C", "Tablet"],
  ["Vitamin B-Complex", "Tablet"],
  ["Calcium Carbonate", "Tablet"],
  ["Magnesium", "Tablet"],
  ["Fluconazole", "Capsule"],
  ["Clotrimazole", "Cream"],
  ["Miconazole", "Cream"],
  ["Acyclovir", "Tablet"],
  ["Permethrin", "Lotion"],
  ["Albendazole", "Tablet"],
  ["Mebendazole", "Tablet"],
  ["Loperamide", "Capsule"],
  ["Ondansetron", "Tablet"],
  ["Domperidone", "Tablet"],
  ["Hydroxyzine", "Tablet"],
  ["Tramadol", "Capsule"],
  ["Morphine", "Injection"],
  ["Lidocaine", "Injection"],
  ["Lidocaine", "Gel"],
  ["Povidone-Iodine", "Solution"],
  ["Chlorhexidine", "Solution"],
  ["Normal Saline", "IV Fluid"],
  ["Ringer Lactate", "IV Fluid"],
  ["Dextrose", "IV Fluid"],
  ["Cough Syrup", "Syrup"],
  ["Antacid", "Suspension"],
  ["Multivitamin", "Syrup"]
];

// Strength sets by form/type
$strengthsByForm = [
  "Tablet"    => ["50mg","75mg","100mg","250mg","500mg","1g"],
  "Capsule"   => ["100mg","200mg","250mg","500mg"],
  "Injection" => ["40mg/2ml","80mg/2ml","250mg/vial","500mg/vial","1g/vial","2g/vial"],
  "Syrup"     => ["60ml","100ml","120ml","150ml"],
  "Cream"     => ["10g","15g","20g","30g"],
  "Lotion"    => ["50ml","100ml","120ml"],
  "Gel"       => ["10g","20g","30g"],
  "Solution"  => ["100ml","200ml","500ml"],
  "Sachet"    => ["20.5g","27.9g","5g","10g"],
  "Inhaler"   => ["100mcg","200mcg"],
  "IV Fluid"  => ["250ml","500ml","1000ml"]
];

// Price heuristic based on form + strength length (simple but consistent)
function price_for(string $form, string $strength, int $i): float {
  $base = 0.30;
  if ($form === "Tablet") $base = 0.15;
  if ($form === "Capsule") $base = 0.25;
  if ($form === "Syrup") $base = 1.20;
  if ($form === "Cream" || $form === "Gel" || $form === "Lotion") $base = 1.00;
  if ($form === "Injection") $base = 2.50;
  if ($form === "IV Fluid") $base = 1.80;
  if ($form === "Inhaler") $base = 3.50;
  if ($form === "Solution") $base = 1.10;
  if ($form === "Sachet") $base = 0.35;

  // adjust by “bigger” strengths
  $mul = 1.0;
  if (str_contains($strength, "1g")) $mul = 1.4;
  if (str_contains($strength, "2g")) $mul = 1.8;
  if (str_contains($strength, "1000ml")) $mul = 1.7;
  if (str_contains($strength, "500ml")) $mul = 1.4;

  // slight variation so not all equal
  $jitter = (($i % 7) * 0.05);
  return round($base * $mul + $jitter, 2);
}

// --- 2) Supplies list (we will generate ~100 items) ---
$supplyTemplates = [
  ["IV Drip Set", ["Adult","Pediatric"], ["1 set"]],
  ["Cannula", ["18G","20G","22G","24G"], ["1 pc"]],
  ["Syringe", ["2ml","5ml","10ml","20ml","50ml"], ["1 pc"]],
  ["Needle", ["18G","20G","22G","23G","25G"], ["1 pc"]],
  ["Gauze Roll", ["5cm x 4m","7.5cm x 4m","10cm x 4m"], ["1 roll"]],
  ["Bandage", ["5cm","7.5cm","10cm","15cm"], ["1 roll"]],
  ["Plaster Tape", ["1 inch","2 inch","3 inch"], ["1 roll"]],
  ["Cotton Wool", ["50g","100g","200g","500g"], ["1 pack"]],
  ["Gloves", ["S","M","L"], ["1 pair","1 box"]],
  ["Face Mask", ["3-ply"], ["1 pc","1 box"]],
  ["Alcohol Swab", ["70%"], ["1 pc","1 box"]],
  ["Hand Sanitizer", ["100ml","250ml","500ml"], ["1 bottle"]],
  ["Thermometer", ["Digital"], ["1 pc"]],
  ["Blood Pressure Cuff", ["Adult","Large"], ["1 pc"]],
  ["Glucometer Strip", ["50 strips","100 strips"], ["1 box"]],
  ["Nebulizer Mask", ["Adult","Child"], ["1 pc"]],
  ["Urine Bag", ["2000ml"], ["1 pc"]],
  ["Catheter", ["14Fr","16Fr","18Fr"], ["1 pc"]],
  ["Surgical Blade", ["No.10","No.11","No.15"], ["1 pc"]],
  ["Suture", ["2-0","3-0","4-0"], ["1 pc"]],
];

// prices for supplies (simple)
function supply_price(string $name, string $size, string $pack, int $i): float {
  $base = 0.50;
  if (str_contains($name, "IV Drip")) $base = 1.20;
  if (str_contains($name, "Cannula")) $base = 0.40;
  if (str_contains($name, "Syringe")) $base = 0.30;
  if (str_contains($name, "Gloves") && $pack === "1 box") $base = 4.50;
  if (str_contains($name, "Face Mask") && $pack === "1 box") $base = 3.50;
  if (str_contains($name, "Thermometer")) $base = 3.00;
  if (str_contains($name, "Blood Pressure")) $base = 9.00;
  if (str_contains($name, "Glucometer")) $base = 6.00;
  if (str_contains($name, "Hand Sanitizer")) $base = 1.50;
  if (str_contains($name, "Urine Bag")) $base = 1.00;

  $jitter = (($i % 5) * 0.10);
  return round($base + $jitter, 2);
}

// ---------- Build 300 items ----------
$items = [];

// A) build 200 medicine items
$targetMeds = 200;
$i = 0;
while (count($items) < $targetMeds) {
  $base = $medicineBases[$i % count($medicineBases)];
  [$drug, $form] = $base;

  $strengths = $strengthsByForm[$form] ?? ["100mg"];
  $strength = pick($strengths, $i);

  $company = pick($companies, $i);
  $price = price_for($form, $strength, $i);

  // item_name includes strength and form so you can display it in prescribing
  $item_name = "{$drug} {$strength} ({$form})";

  $items[] = [$item_name, $company, $price];
  $i++;
}

// B) build 100 supply items
$targetSupplies = 100;
$j = 0;
while (count($items) < 300) {
  $tpl = $supplyTemplates[$j % count($supplyTemplates)];
  [$name, $sizes, $packs] = $tpl;

  $size = pick($sizes, $j);
  $pack = pick($packs, $j);

  $company = pick($companies, $j + 77);
  $price = supply_price($name, $size, $pack, $j);

  $item_name = "{$name} {$size} ({$pack})";

  $items[] = [$item_name, $company, $price];
  $j++;
}

// ---------- Insert into DB (skip duplicates by exact item_name+company+price check) ----------
$pdo->beginTransaction();

$insert = $pdo->prepare("
  INSERT INTO pharmacy_items (item_name, company_name, unit_price)
  VALUES (:item_name, :company_name, :unit_price)
");

$inserted = 0;
foreach ($items as $row) {
  [$item_name, $company_name, $unit_price] = $row;

  // prevent exact duplicates
  $chk = $pdo->prepare("SELECT id FROM pharmacy_items WHERE item_name=? AND company_name=? AND unit_price=? LIMIT 1");
  $chk->execute([$item_name, $company_name, $unit_price]);
  if ($chk->fetchColumn()) continue;

  $insert->execute([
    ":item_name" => $item_name,
    ":company_name" => $company_name,
    ":unit_price" => $unit_price,
  ]);
  $inserted++;
}

$pdo->commit();

echo "<pre>✅ Pharmacy items seed complete.\nInserted: {$inserted}\nTotal intended: 300\n</pre>";
