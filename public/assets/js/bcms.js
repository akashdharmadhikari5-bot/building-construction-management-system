/**
 * BCMS — Building Construction Management System
 * Core Frontend JavaScript
 * 
 * Features:
 * 1. Sidebar toggle functionality (add/remove 'sidebar-collapsed' class on body)
 * 2. Mobile responsive sidebar overlay & backdrop
 * 3. Auto-dismiss flash alerts after 5 seconds
 * 4. Confirm dialog for delete/action triggers (data-confirm)
 * 5. Form validation feedback (Bootstrap 5 styles)
 * 6. Format currency inputs with INR formatting on blur
 * 7. Table row hover & click navigation effects
 * 8. Print report button functionality
 * 9. Bootstrap tooltips & popovers initialization
 */

(function () {
  'use strict';

  // ============================================================
  // 1. UTILITY HELPERS
  // ============================================================

  /**
   * Format number in Indian Rupee format (e.g. 1,50,000.00).
   * @param {number|string} val
   * @returns {string}
   */
  function formatINR(val) {
    if (val === null || val === undefined || val === '') return '';
    const clean = String(val).replace(/[^0-9.-]/g, '');
    const num = parseFloat(clean);
    if (isNaN(num)) return '';

    const parts = num.toFixed(2).split('.');
    let integerPart = parts[0];
    const decimalPart = parts[1];

    const isNegative = integerPart.startsWith('-');
    if (isNegative) integerPart = integerPart.substring(1);

    let lastThree = integerPart.substring(integerPart.length - 3);
    const otherNumbers = integerPart.substring(0, integerPart.length - 3);
    if (otherNumbers !== '') {
      lastThree = ',' + lastThree;
    }
    const formattedInteger = otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + lastThree;
    return (isNegative ? '-' : '') + formattedInteger + '.' + decimalPart;
  }

  /**
   * Strip INR formatting and return raw numeric string.
   * @param {string} val
   * @returns {string}
   */
  function unformatINR(val) {
    if (!val) return '';
    return String(val).replace(/[^0-9.-]/g, '');
  }

  // ============================================================
  // 2. SIDEBAR TOGGLE & MOBILE RESPONSIVE OVERLAY
  // ============================================================

  function initSidebar() {
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const body = document.body;

    // Restore desktop preference from localStorage
    if (window.innerWidth >= 992) {
      const isCollapsed = localStorage.getItem('bcms_sidebar_collapsed') === 'true';
      if (isCollapsed) {
        body.classList.add('sidebar-collapsed');
      }
    }

    // Toggle function
    function toggleSidebar() {
      if (window.innerWidth < 992) {
        // Mobile behavior: toggle overlay
        body.classList.toggle('sidebar-mobile-open');
        body.classList.toggle('sidebar-open');
        body.classList.remove('sidebar-collapsed');
      } else {
        // Desktop behavior: toggle collapsed state
        body.classList.toggle('sidebar-collapsed');
        localStorage.setItem(
          'bcms_sidebar_collapsed',
          body.classList.contains('sidebar-collapsed') ? 'true' : 'false'
        );
      }
    }

    // Close mobile sidebar
    function closeMobileSidebar() {
      body.classList.remove('sidebar-mobile-open');
      body.classList.remove('sidebar-open');
    }

    if (sidebarToggleBtn) {
      sidebarToggleBtn.addEventListener('click', function (e) {
        e.preventDefault();
        toggleSidebar();
      });
    }

    if (sidebarCloseBtn) {
      sidebarCloseBtn.addEventListener('click', function (e) {
        e.preventDefault();
        closeMobileSidebar();
      });
    }

    if (sidebarBackdrop) {
      sidebarBackdrop.addEventListener('click', function () {
        closeMobileSidebar();
      });
    }

    // Handle escape key to close mobile sidebar
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && (body.classList.contains('sidebar-mobile-open') || body.classList.contains('sidebar-open'))) {
        closeMobileSidebar();
      }
    });

    // Handle resize transitions
    window.addEventListener('resize', function () {
      if (window.innerWidth >= 992) {
        closeMobileSidebar();
      }
    });
  }

  // ============================================================
  // 3. AUTO-DISMISS FLASH ALERTS (AFTER 5 SECONDS)
  // ============================================================

  function initAutoDismissAlerts() {
    const alerts = document.querySelectorAll(
      '.alert.alert-dismissible:not(.alert-permanent):not([data-auto-dismiss="false"])'
    );

    alerts.forEach(function (alert) {
      setTimeout(function () {
        if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
          const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
          if (bsAlert) {
            bsAlert.close();
          }
        } else {
          // Fallback smooth fade
          alert.style.transition = 'opacity 0.4s ease';
          alert.style.opacity = '0';
          setTimeout(function () {
            alert.remove();
          }, 400);
        }
      }, 5000);
    });
  }

  // ============================================================
  // 4. CONFIRM DIALOG FOR DELETE / SENSITIVE ACTIONS
  // ============================================================

  function initConfirmDialogs() {
    document.addEventListener('click', function (e) {
      const target = e.target.closest('[data-confirm]');
      if (!target) return;

      const message = target.getAttribute('data-confirm') || 'Are you sure you want to perform this action?';
      if (!window.confirm(message)) {
        e.preventDefault();
        e.stopPropagation();
        return false;
      }
    }, true);

    // Also handle forms marked with data-confirm
    document.addEventListener('submit', function (e) {
      const form = e.target;
      if (form && form.hasAttribute('data-confirm')) {
        const message = form.getAttribute('data-confirm') || 'Are you sure you want to submit this form?';
        if (!window.confirm(message)) {
          e.preventDefault();
          e.stopPropagation();
          return false;
        }
      }
    }, true);
  }

  // ============================================================
  // 5. FORM VALIDATION FEEDBACK (BOOTSTRAP 5)
  // ============================================================

  function initFormValidation() {
    const forms = document.querySelectorAll('form.needs-validation');

    forms.forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();

          // Scroll first invalid field into view smoothly
          const firstInvalid = form.querySelector(':invalid');
          if (firstInvalid) {
            firstInvalid.focus();
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        }
        form.classList.add('was-validated');
      }, false);
    });
  }

  // ============================================================
  // 6. INR CURRENCY FORMATTING ON BLUR
  // ============================================================

  function initCurrencyFormatting() {
    const currencyInputs = document.querySelectorAll(
      'input[data-format="inr"], input.currency-input, input[data-currency="true"]'
    );

    currencyInputs.forEach(function (input) {
      // Format initial value if present and input is not type="number"
      if (input.type !== 'number' && input.value) {
        input.value = formatINR(input.value);
      }

      // On blur: format with INR commas
      input.addEventListener('blur', function () {
        if (this.type === 'number') return; // type=number cannot store commas
        const raw = unformatINR(this.value);
        if (raw !== '') {
          this.value = formatINR(raw);
          this.setAttribute('data-raw-value', raw);
        }
      });

      // On focus: clean back to raw number for easier typing
      input.addEventListener('focus', function () {
        if (this.type === 'number') return;
        const raw = unformatINR(this.value);
        if (raw !== '') {
          this.value = raw;
        }
      });
    });

    // Before form submit, clean currency inputs so backend receives clean decimals
    document.addEventListener('submit', function (e) {
      const form = e.target;
      const formattedFields = form.querySelectorAll('input[data-format="inr"], input.currency-input');
      formattedFields.forEach(function (input) {
        if (input.type !== 'number' && input.value) {
          input.value = unformatINR(input.value);
        }
      });
    });
  }

  // ============================================================
  // 7. TABLE ROW HOVER & CLICK NAVIGATION
  // ============================================================

  function initTableNavigation() {
    document.addEventListener('click', function (e) {
      const row = e.target.closest('tr[data-href]');
      if (!row) return;

      // Ignore if clicking on interactive elements inside row
      if (e.target.closest('a, button, input, select, textarea, label, .dropdown, [data-bs-toggle]')) {
        return;
      }

      const href = row.getAttribute('data-href');
      if (href) {
        window.location.href = href;
      }
    });
  }

  // ============================================================
  // 8. PRINT REPORT FUNCTIONALITY
  // ============================================================

  function initPrintButtons() {
    document.addEventListener('click', function (e) {
      const printBtn = e.target.closest('[data-action="print"], .btn-print, #printReportBtn');
      if (!printBtn) return;

      e.preventDefault();
      window.print();
    });
  }

  // ============================================================
  // 9. INITIALIZE BOOTSTRAP TOOLTIPS & POPOVERS
  // ============================================================

  function initBootstrapComponents() {
    if (typeof bootstrap === 'undefined') return;

    // Tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
      new bootstrap.Tooltip(tooltipTriggerEl, {
        trigger: 'hover',
      });
    });

    // Popovers
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    popoverTriggerList.forEach(function (popoverTriggerEl) {
      new bootstrap.Popover(popoverTriggerEl);
    });
  }

  // ============================================================
  // 10. DARK / LIGHT THEME TOGGLE
  // ============================================================

  function initThemeToggle() {
    // Helper: Determine current theme
    function getStoredTheme() {
      try {
        const stored = localStorage.getItem('bcms_theme');
        if (stored === 'dark' || stored === 'light') {
          return stored;
        }
      } catch (e) {}
      return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
    }

    // Helper: Apply theme & update button icons
    function applyTheme(theme) {
      document.documentElement.setAttribute('data-bs-theme', theme);
      document.documentElement.setAttribute('data-theme', theme);
      if (theme === 'dark') {
        document.documentElement.classList.add('dark-theme');
        if (document.body) document.body.classList.add('dark-theme');
      } else {
        document.documentElement.classList.remove('dark-theme');
        if (document.body) document.body.classList.remove('dark-theme');
      }

      // Update all toggle buttons in document
      document.querySelectorAll('#themeToggleBtn, .bcms-theme-toggle-btn').forEach(function (btn) {
        const darkIcon = btn.querySelector('.theme-icon-dark');
        const lightIcon = btn.querySelector('.theme-icon-light');
        if (darkIcon && lightIcon) {
          if (theme === 'dark') {
            darkIcon.classList.add('d-none');
            lightIcon.classList.remove('d-none');
            btn.setAttribute('title', 'Switch to Light Mode');
            btn.setAttribute('aria-label', 'Switch to Light Mode');
          } else {
            darkIcon.classList.remove('d-none');
            lightIcon.classList.add('d-none');
            btn.setAttribute('title', 'Switch to Dark Mode');
            btn.setAttribute('aria-label', 'Switch to Dark Mode');
          }
        }
      });
    }

    // Apply initially
    applyTheme(getStoredTheme());

    // Toggle on click (using delegated event so dynamic buttons are supported)
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('#themeToggleBtn, .bcms-theme-toggle-btn');
      if (!btn) return;

      e.preventDefault();
      const current = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
      const next = current === 'dark' ? 'light' : 'dark';
      try {
        localStorage.setItem('bcms_theme', next);
      } catch (err) {}
      applyTheme(next);
    });

    // Watch OS preference if user hasn't set explicit theme
    if (window.matchMedia) {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
        if (!localStorage.getItem('bcms_theme')) {
          applyTheme(e.matches ? 'dark' : 'light');
        }
      });
    }
  }

  // ============================================================
  // INITIALIZE ON DOM READY
  // ============================================================

  document.addEventListener('DOMContentLoaded', function () {
    initThemeToggle();
    initSidebar();
    initAutoDismissAlerts();
    initConfirmDialogs();
    initFormValidation();
    initCurrencyFormatting();
    initTableNavigation();
    initPrintButtons();
    initBootstrapComponents();
  });

  // Expose BCMS utilities to global scope for dynamic script usage
  window.BCMS = {
    formatINR: formatINR,
    unformatINR: unformatINR,
  };

})();
