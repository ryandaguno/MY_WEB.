/**
 * Selah Aesthetics — Main JavaScript
 */
document.addEventListener('DOMContentLoaded', function () {

    /* ============================================================
       SECTION A — GENERAL UTILITIES (all pages)
       ============================================================ */

    // Auto-dismiss flash alerts after 4 s
    document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
        setTimeout(function () {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 4000);
    });

    // Confirm dialogs for destructive actions
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.dataset.confirm)) e.preventDefault();
        });
    });

    // GCash receipt upload preview
    var receiptInput = document.querySelector('input[name="receipt"]');
    if (receiptInput) {
        receiptInput.addEventListener('change', function () {
            var file = this.files[0];
            if (!file) return;
            var allowed = ['image/jpeg','image/png','image/gif'];
            if (!allowed.includes(file.type)) { alert('Please upload a JPEG, PNG, or GIF image only.'); this.value = ''; return; }
            if (file.size > 5242880)          { alert('File size must not exceed 5 MB.');                this.value = ''; return; }
            var preview = document.getElementById('receiptPreview');
            if (preview) {
                var reader = new FileReader();
                reader.onload = function (e) { preview.src = e.target.result; preview.style.display = 'block'; };
                reader.readAsDataURL(file);
            }
        });
    }

    // Booking step — prevent back-button session loss
    if (document.querySelector('.booking-steps')) {
        window.history.pushState(null, null, window.location.href);
        window.addEventListener('popstate', function () {
            window.history.pushState(null, null, window.location.href);
        });
    }

    // Admin sidebar — highlight active link
    var currentPath = window.location.pathname;
    document.querySelectorAll('.admin-sidebar .nav-link').forEach(function (link) {
        if (currentPath.includes(link.getAttribute('href'))) link.classList.add('active');
    });

    /* ============================================================
       SECTION B — HOMEPAGE ONLY
       ============================================================ */
    var heroSection = document.querySelector('.hero-section');
    if (!heroSection) return;

    var saNavbar = document.querySelector('.sa-navbar');

    // Navbar glass on scroll — kept as it doesn't affect hero text
    if (saNavbar) {
        saNavbar.style.transition = 'background 0.4s ease, backdrop-filter 0.4s ease, box-shadow 0.4s ease';
    }
    function updateNavbar() {
        if (!saNavbar) return;
        if (window.scrollY > 60) {
            saNavbar.style.background           = 'rgba(20,10,35,0.78)';
            saNavbar.style.backdropFilter       = 'blur(14px)';
            saNavbar.style.webkitBackdropFilter = 'blur(14px)';
            saNavbar.style.boxShadow            = '0 4px 24px rgba(0,0,0,0.35)';
        } else {
            saNavbar.style.background           = 'transparent';
            saNavbar.style.backdropFilter       = 'none';
            saNavbar.style.webkitBackdropFilter = 'none';
            saNavbar.style.boxShadow            = 'none';
        }
    }
    updateNavbar();
    window.addEventListener('scroll', updateNavbar, { passive: true });

    // Typewriter subtitle (keeps the badge alive, does not touch hero title)
    var subtitleEl = document.getElementById('heroSubtitle');
    if (subtitleEl) {
        var phrases  = ['Book \u00B7 Relax \u00B7 Glow', 'Your Beauty, Our Priority', 'Selah Aesthetics \u2014 Midsayap'];
        var tpi = 0, tci = 0, tDeleting = false;
        var tCursor = document.createElement('span');
        tCursor.className = 'cursor';
        subtitleEl.appendChild(tCursor);

        function typeWriter() {
            var phrase = phrases[tpi];
            if (!tDeleting) {
                subtitleEl.textContent = phrase.slice(0, tci + 1);
                subtitleEl.appendChild(tCursor);
                tci++;
                if (tci === phrase.length) { tDeleting = true; setTimeout(typeWriter, 1800); return; }
                setTimeout(typeWriter, 75);
            } else {
                subtitleEl.textContent = phrase.slice(0, tci - 1);
                subtitleEl.appendChild(tCursor);
                tci--;
                if (tci === 0) { tDeleting = false; tpi = (tpi + 1) % phrases.length; setTimeout(typeWriter, 400); return; }
                setTimeout(typeWriter, 38);
            }
        }
        setTimeout(typeWriter, 900);
    }

});
