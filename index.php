<?php $pageTitle = 'Dashboard · JudaCargo Accounting';
require $_SERVER['DOCUMENT_ROOT'].'/accounting/_partials/header.php'; ?>

<h1 class="page-title">Dashboard</h1>
<p class="breadcrumbs">Home</p>

<div class="grid">
  <a class="card" href="cash/">
    <h3>Cash</h3>
    <p>現金收支、零用金、日記帳。</p>
  </a>
  <a class="card" href="banking/">
    <h3>Banking</h3>
    <p>銀行明細、對帳、轉帳、存取款。</p>
  </a>
  <a class="card" href="payroll/">
    <h3>Payroll</h3>
    <p>薪資登錄、津貼、扣繳紀錄。</p>
  </a>
  <a class="card" href="expenses/">
    <h3>Expenses</h3>
    <p>費用報銷、供應商支出、分類。</p>
  </a>
  <a class="card" href="receivable/">
    <h3>Receivable</h3>
    <p>應收帳款、發票、收款追蹤。</p>
  </a>
  <a class="card" href="vehicle-cost/">
    <h3>Vehicle Cost</h3>
    <p>車輛油資、保險、維修、里程。</p>
  </a>
</div>

<?php require $_SERVER['DOCUMENT_ROOT'].'/accounting/_partials/footer.php'; ?>