<?php // accounting/_partials/header.php ?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $pageTitle ?? 'Accounting'; ?></title>
  <link rel="stylesheet" href="/accounting/assets/css/main.css">
</head>
<body>
  <header class="container">
    <h1>JudaCargo 會計系統</h1>
    <nav class="grid" style="margin-top:12px">
      <a class="card" href="/accounting/cash/">Cash</a>
      <a class="card" href="/accounting/banking/">Banking</a>
      <a class="card" href="/accounting/payroll/">Payroll</a>
      <a class="card" href="/accounting/expenses/">Expenses</a>
      <a class="card" href="/accounting/receivable/">Receivable</a>
      <a class="card" href="/accounting/vehicle-cost/">Vehicle Cost</a>
    </nav>
  </header>
  <main class="container">
