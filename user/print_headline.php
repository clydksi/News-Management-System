<?php
require '../db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
  echo "Invalid ID.";
  exit;
}

$stmt = $pdo->prepare("SELECT n.*, 
                              u.username, 
                              d.name AS dept_name, 
                              c.name AS category_name
                       FROM news n
                       JOIN users u ON n.created_by = u.id
                       JOIN departments d ON n.department_id = d.id
                       LEFT JOIN categories c ON n.category_id = c.id
                       WHERE n.id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();


if (!$row) {
  echo "Headline not found.";
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>News Report - <?= htmlspecialchars($row['title']) ?></title>
  <style>
    body {
      font-family: 'Georgia', serif;
      background: #EFEEEA;
      color: #000;
      margin: 40px;
    }
    .report-container {
      border: 1px solid #333;
      padding: 30px;
      max-width: 900px;
      margin: auto;
    }
    .report-header {
      text-align: center;
      border-bottom: 2px solid #000;
      padding-bottom: 10px;
      margin-bottom: 20px;
    }
    .report-header h2 {
      margin: 0;
      font-size: 28px;
      text-transform: uppercase;
    }
    .report-header small {
      display: block;
      margin-top: 5px;
      font-size: 14px;
      color: #666;
    }
    .report-meta {
      margin-bottom: 20px;
      font-size: 14px;
    }
    .report-meta div {
      margin-bottom: 5px;
    }
    .report-title {
      font-size: 22px;
      font-weight: bold;
      margin-bottom: 10px;
    }
    .report-content {
      font-size: 16px;
      line-height: 1.6;
      text-align: justify;
    }
    .report-footer {
      border-top: 1px dashed #aaa;
      margin-top: 40px;
      padding-top: 10px;
      font-size: 12px;
      text-align: center;
      color: #777;
    }
    @media print {
      body {
        margin: 0;
      }
      .report-container {
        border: none;
        padding: 0;
        margin: 0;
      }
    }
  </style>
</head>
<body onload="window.print()">
  <div class="report-container">
    <div class="report-header">
      <h2>News Report</h2>
      <small>Generated on <?= date('F d, Y') ?></small>
    </div>

    <div class="report-meta">
      <div><strong>Title:</strong> <?= htmlspecialchars($row['title']) ?></div>
      <div><strong>Category:</strong> <?= htmlspecialchars($row['category_name']) ?></div>
      <div><strong>Author:</strong> <?= htmlspecialchars(explode('@', $row['username'])[0]) ?></div>
      <div><strong>Date Published:</strong> <?= date('F j, Y', strtotime($row['created_at'])) ?></div>
    </div>

    <div class="report-content">
      <?= nl2br(htmlspecialchars($row['content'])) ?>
    </div>

    <div class="report-footer">
      <p>© <?= date('Y') ?> MMG Media Group — News Report System</p>
    </div>
  </div>
</body>
</html>