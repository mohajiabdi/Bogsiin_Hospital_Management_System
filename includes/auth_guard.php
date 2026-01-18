<?php
// includes/auth_guard.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION["user"])) {
  header("Location: /hospital/auth/login.php");
  exit;
}


if (session_status() === PHP_SESSION_NONE) session_start();

$isAjax = isset($_GET["ajax"]) || (isset($_SERVER["HTTP_ACCEPT"]) && str_contains($_SERVER["HTTP_ACCEPT"], "application/json"));

if (!isset($_SESSION["user"])) {
  if ($isAjax) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["ok"=>false,"error"=>"AUTH_REQUIRED"]);
    exit;
  }
  header("Location: /hospital/auth/login.php");
  exit;
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
