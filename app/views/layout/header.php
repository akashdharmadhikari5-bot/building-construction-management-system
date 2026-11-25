<?php
/**
 * BCMS — Layout Header
 * HTML <head> section and opening <body> tag
 */
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Building Construction Management System - Manage your construction projects efficiently">
<title><?= e($pageTitle ?? 'Dashboard') . ' | ' . APP_NAME ?></title>
<!-- Anti-FOUC Early Theme Script -->
<script>
(function() {
  try {
    const savedTheme = localStorage.getItem('bcms_theme') || 
      (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-bs-theme', savedTheme);
    document.documentElement.setAttribute('data-theme', savedTheme);
    if (savedTheme === 'dark') {
      document.documentElement.classList.add('dark-theme');
    }
  } catch (e) {}
})();
</script>
<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<!-- BCMS Custom CSS -->
<link href="<?= APP_URL ?>/assets/css/bcms.css" rel="stylesheet">
</head>
<body class="has-sidebar">
