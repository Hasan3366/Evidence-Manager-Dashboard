/**
 * assets/js/app.js
 * Evidence Manager Dashboard — Client-side JavaScript
 *
 * Responsibilities:
 *  1. Mobile navigation toggle
 *  2. Flash / alert message dismissal
 *  3. Confirm dialogs for destructive actions
 *  4. Client-side form validation helpers
 *  5. Search input debouncing
 *  6. File upload feedback (filename display)
 *  7. Auto-dismissing alerts
 *
 * No external dependencies — plain ES6+ JavaScript.
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

  // ── 1. Mobile navigation toggle ─────────────────────────────
  initNavToggle();

  // ── 2. Flash message dismissal ──────────────────────────────
  initAlertDismiss();

  // ── 3. Confirm on dangerous actions ─────────────────────────
  initDangerConfirm();

  // ── 4. Form validation ───────────────────────────────────────
  initFormValidation();

  // ── 5. Debounced search auto-submit ─────────────────────────
  initSearchDebounce();

  // ── 6. File input — display chosen filename ─────────────────
  initFileInputLabels();

  // ── 7. Auto-dismiss success alerts after 5 s ────────────────
  initAutoDismiss();

});

/* ── 1. Mobile navigation toggle ──────────────────────────────── */
function initNavToggle() {
  const toggle = document.getElementById('navToggle');
  const navLinks = document.getElementById('navLinks');

  if (!toggle || !navLinks) return;

  toggle.addEventListener('click', () => {
    const isOpen = navLinks.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  // Close mobile nav when a link is tapped
  navLinks.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', () => {
      navLinks.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
    });
  });

  // Close nav when clicking outside
  document.addEventListener('click', (e) => {
    if (!toggle.contains(e.target) && !navLinks.contains(e.target)) {
      navLinks.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
    }
  });
}

/* ── 2. Alert / flash message dismissal ───────────────────────── */
function initAlertDismiss() {
  document.addEventListener('click', (e) => {
    if (e.target.matches('.alert-close')) {
      const alert = e.target.closest('.alert');
      if (alert) {
        alert.style.transition = 'opacity 200ms ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 200);
      }
    }
  });
}

/* ── 3. Confirm dialogs for destructive actions ────────────────── */
/**
 * Any element with [data-confirm="Your message here"] will trigger
 * a confirmation dialog before the action is followed.
 *
 * Usage in PHP templates:
 *   <a href="delete.php?id=1" data-confirm="Delete this record?">Delete</a>
 *   <button form="deleteForm" data-confirm="This cannot be undone.">Delete</button>
 */
function initDangerConfirm() {
  document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-confirm]');
    if (!el) return;

    const message = el.getAttribute('data-confirm') || 'Are you sure?';
    if (!window.confirm(message)) {
      e.preventDefault();
      e.stopPropagation();
    }
  });
}

/* ── 4. Client-side form validation ───────────────────────────── */
/**
 * Marks required fields as invalid before submission if they are empty.
 * Server-side validation ALWAYS runs — this is a UX improvement only.
 *
 * Add class="needs-validation" to any <form> to enable this.
 */
function initFormValidation() {
  document.querySelectorAll('form.needs-validation').forEach(form => {
    form.addEventListener('submit', (e) => {
      let valid = true;

      // Clear previous errors
      form.querySelectorAll('.form-error.js-error').forEach(el => el.remove());
      form.querySelectorAll('.form-control.is-invalid').forEach(el => {
        el.classList.remove('is-invalid');
      });

      // Check required fields
      form.querySelectorAll('[required]').forEach(field => {
        if (!field.value.trim()) {
          valid = false;
          field.classList.add('is-invalid');
          const errorEl = document.createElement('span');
          errorEl.className = 'form-error js-error';
          errorEl.textContent = 'This field is required.';
          field.insertAdjacentElement('afterend', errorEl);
        }
      });

      if (!valid) {
        e.preventDefault();
        // Scroll to the first invalid field
        const firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid) {
          firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
          firstInvalid.focus();
        }
      }
    });

    // Clear individual field error on input
    form.addEventListener('input', (e) => {
      if (e.target.matches('.is-invalid')) {
        e.target.classList.remove('is-invalid');
        const err = e.target.nextElementSibling;
        if (err && err.classList.contains('js-error')) {
          err.remove();
        }
      }
    });
  });
}

/* ── 5. Search input — debounced auto-submit ──────────────────── */
/**
 * Add data-search-form="formId" to a text input to auto-submit
 * its parent form after the user stops typing (400 ms delay).
 *
 * Usage:
 *   <input type="text" name="q" data-search-form="searchForm">
 *   <form id="searchForm" method="GET">...</form>
 */
function initSearchDebounce() {
  let debounceTimer = null;

  document.querySelectorAll('[data-search-form]').forEach(input => {
    input.addEventListener('input', () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        const formId = input.getAttribute('data-search-form');
        const form   = formId
          ? document.getElementById(formId)
          : input.closest('form');
        if (form) form.submit();
      }, 400);
    });
  });
}

/* ── 6. File input — show selected filename ───────────────────── */
/**
 * When a user picks a file, update the label text to show
 * the filename so they get clear feedback.
 *
 * Requires the file input to be wrapped by a <label class="form-file-label">
 * with a child <span class="file-name-display">.
 */
function initFileInputLabels() {
  document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', () => {
      const label   = input.closest('.form-file-label');
      const display = label && label.querySelector('.file-name-display');
      if (!display) return;

      if (input.files && input.files.length > 0) {
        const names = Array.from(input.files).map(f => f.name).join(', ');
        display.textContent = names;
      } else {
        display.textContent = 'No file chosen';
      }
    });
  });
}

/* ── 7. Auto-dismiss success alerts ──────────────────────────── */
/**
 * Success alerts are removed automatically after 5 seconds.
 * Other alert types (errors, warnings) stay until dismissed manually.
 */
function initAutoDismiss() {
  document.querySelectorAll('.alert-success').forEach(alert => {
    setTimeout(() => {
      if (!document.body.contains(alert)) return;
      alert.style.transition = 'opacity 400ms ease';
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 400);
    }, 5000);
  });
}

/* ── Utility: Show/hide a modal by ID ─────────────────────────── */
/**
 * Simple modal open/close utility.
 * Call from inline onclick or from your own JS.
 *
 * HTML structure required:
 *   <div id="myModal" class="modal" role="dialog" aria-modal="true">
 *     <div class="modal-overlay" onclick="closeModal('myModal')"></div>
 *     <div class="modal-box">
 *       <button onclick="closeModal('myModal')">Close</button>
 *       ...
 *     </div>
 *   </div>
 */
function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';

    // Focus the first focusable element inside the modal
    const focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusable) focusable.focus();
  }
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.remove('is-open');
    document.body.style.overflow = '';
  }
}

// Close modals with Escape key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal.is-open').forEach(modal => {
      closeModal(modal.id);
    });
  }
});

// Expose modal helpers globally so PHP templates can call them via onclick
window.openModal  = openModal;
window.closeModal = closeModal;
