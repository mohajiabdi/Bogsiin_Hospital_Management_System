<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" type="image/png" href="/hospital/assets/images/logo.png" />
  <title><?php echo isset($pageTitle) ? $pageTitle : "Hospital Management System"; ?></title>
<!-- <style>
  @media (min-width: 768px){
    .mobile-topbar{ display:none !important; }
  }
</style> -->

  <!-- Tailwind output -->
  <!-- <link rel="stylesheet" href="/hospital/assets/css/style.css" /> -->
   <script src="https://cdn.tailwindcss.com"></script>


  <!-- Optional: Google Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Google Material Icons -->
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

  <style>
    body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
  </style>
</head>
<body class="bg-slate-50 text-slate-900">
 
