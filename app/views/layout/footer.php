<?php
/**
 * BCMS — Layout Footer
 * Closes the layout with copyright notice and script bundles.
 */
declare(strict_types=1);
?>
<footer class="bcms-footer">
  <div class="text-center text-muted small py-2">
    &copy; <?= date('Y') ?> Building Construction Management System. All rights reserved.
  </div>
</footer>
<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- BCMS JS -->
<script src="<?= APP_URL ?>/assets/js/bcms.js"></script>
</body>
</html>
