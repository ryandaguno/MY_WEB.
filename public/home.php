<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modules/SessionGuard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/countries.php';
SessionGuard::start();

$isLoggedIn  = SessionGuard::isClientLoggedIn();
$currentUser = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');

// Build user initials from username (e.g. "Jane Doe" → "JD", "alice" → "A")
$userInitials = '';
if ($isLoggedIn && $currentUser !== '') {
    $parts = preg_split('/\s+/', trim($currentUser));
    foreach ($parts as $part) {
        if ($part !== '') $userInitials .= strtoupper($part[0]);
    }
    $userInitials = substr($userInitials, 0, 2); // cap at 2 chars
}

// Determine which modal to auto-open
$autoModal = '';
if (!$isLoggedIn) {
    $queryModal = $_GET['modal'] ?? '';
    if ($queryModal === 'register') $autoModal = 'registerModal';
    elseif ($queryModal === 'login')    $autoModal = 'loginModal';
}

// Pre-fill registration form on validation error
$regForm = [];
$regErrors = [];
if (!empty($_SESSION['reg_form'])) {
    $regForm = $_SESSION['reg_form'];
    unset($_SESSION['reg_form']);
}
if (!empty($_SESSION['reg_errors'])) {
    $regErrors = $_SESSION['reg_errors'];
    unset($_SESSION['reg_errors']);
}
$regName    = htmlspecialchars($regForm['username'] ?? '', ENT_QUOTES, 'UTF-8');
$regEmail   = htmlspecialchars($regForm['email']    ?? '', ENT_QUOTES, 'UTF-8');
$regPhone   = htmlspecialchars($regForm['phone']    ?? '', ENT_QUOTES, 'UTF-8');
$regCountry = htmlspecialchars($regForm['country']  ?? '', ENT_QUOTES, 'UTF-8');

// Fetch active services from DB
$services = [];
try {
    $db = getDB();
    $stmt = $db->query("SELECT id, name, description, price, category FROM services WHERE is_active = 1 ORDER BY category, name");
    $services = $stmt->fetchAll();
} catch (Exception $e) {
    $services = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Selah Aesthetics</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    html {
      scroll-behavior: smooth;
    }

    html, body {
      width: 100%; height: 100%;
      font-family: 'Poppins', sans-serif;
      overflow-x: hidden;
      /* ONE background image for the entire page */
      background-image: url('../assets/images/salon_bg.jpg?v=3');
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      background-repeat: no-repeat;
      background-color: #b89cc8;
    }

    /* =============================================
       HERO / LANDING PAGE
       ============================================= */
    .hero-section {
      position: relative;
      width: 100%;
      height: 100vh;
      min-height: 600px;
      background: transparent;
      display: flex;
      flex-direction: column;
    }

    .hero-overlay {
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, 0.28);
      z-index: 0;
    }

    /* =============================================
       NAVBAR
       ============================================= */
    .sa-navbar {
      position: relative;
      z-index: 10;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 18px 40px;
    }

    .sa-logo {
      width: 76px; height: 76px;
      border-radius: 50%;
      background: rgba(224, 196, 162, 0.88);
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      color: #5a3a1a;
      font-family: 'Poppins', serif;
      text-transform: uppercase;
      letter-spacing: 2px;
      border: 2px solid rgba(255,255,255,.35);
      text-decoration: none;
      line-height: 1;
    }
    .sa-logo .logo-symbol {
      font-size: 1.4rem;
      font-style: italic;
      font-weight: 700;
      font-family: Georgia, serif;
    }
    .sa-logo .logo-text {
      font-size: .55rem;
      font-weight: 700;
      letter-spacing: 3px;
    }

    .sa-nav-links {
      display: flex;
      align-items: center;
      gap: 32px;
      list-style: none;
    }
    .sa-nav-links a {
      color: white;
      text-decoration: none;
      font-size: .82rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .8px;
      position: relative;
      padding-bottom: 4px;
    }
    .sa-nav-links a::after {
      content: '';
      position: absolute;
      bottom: 0; left: 0;
      width: 0;
      height: 2px;
      background: white;
      border-radius: 2px;
      transition: width .25s ease;
    }
    .sa-nav-links a:hover::after {
      width: 100%;
    }
    .sa-nav-links a:hover { opacity: 1; }

    .btn-mybookings {
      display: flex; align-items: center; gap: 8px;
      background: rgba(255,255,255,.12);
      border: 1.5px solid rgba(255,255,255,.7);
      color: white;
      border-radius: 8px;
      padding: 7px 20px;
      font-size: .8rem;
      font-weight: 700;
      text-transform: uppercase;
      text-decoration: none;
      letter-spacing: .5px;
      transition: background .2s;
    }
    .btn-mybookings:hover { background: rgba(255,255,255,.25); color: white; }

    .btn-login-nav {
      background: white;
      color: #5a1a8a;
      border-radius: 8px;
      padding: 7px 26px;
      font-size: .8rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
      text-decoration: none;
      border: none;
      transition: opacity .2s;
    }
    .btn-login-nav:hover { opacity: .88; color: #5a1a8a; }

    .btn-register-nav {
      background: transparent;
      color: white;
      border-radius: 8px;
      padding: 7px 20px;
      font-size: .8rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
      border: 1.5px solid rgba(255,255,255,.7);
      transition: background .2s, opacity .2s;
      cursor: pointer;
    }
    .btn-register-nav:hover { background: rgba(255,255,255,.15); }

    .user-avatar-btn {
      width: 40px; height: 40px;
      border-radius: 50%;
      background: white;
      color: #5a1a8a;
      font-size: .78rem;
      font-weight: 800;
      border: 2px solid rgba(255,255,255,.7);
      display: flex;
      align-items: center;
      justify-content: center;
      letter-spacing: .5px;
      transition: background .2s;
      padding: 0;
    }
    .user-avatar-btn::after { display: none; }
    .user-avatar-btn:hover { background: #f0e0ff; }

    /* =============================================
       HERO CONTENT
       ============================================= */
    .hero-content {
      position: relative;
      z-index: 5;
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 0 24px 60px;
    }

    .hero-title {
      font-family: 'Bangers', cursive;
      font-size: clamp(1.9rem, 4.8vw, 4rem);
      color: #ffffff;
      text-transform: uppercase;
      letter-spacing: 2px;
      line-height: 1.2;
      text-shadow: 2px 4px 16px rgba(0,0,0,.75), 0 0 40px rgba(0,0,0,.45);
      margin-bottom: 40px;
      max-width: 880px;
    }

    .hero-buttons {
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
      justify-content: center;
    }

    .btn-hero-black {
      background: #111111;
      color: white;
      border: none;
      border-radius: 8px;
      padding: 14px 36px;
      font-size: .88rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      text-decoration: none;
      transition: background .2s, transform .15s;
      white-space: nowrap;
    }
    .btn-hero-black:hover {
      background: #333;
      color: white;
      transform: translateY(-2px);
    }

    /* =============================================
       HERO SUPPORT STYLES (no 3D animation)
       ============================================= */

    /* Typewriter subtitle */
    .hero-subtitle {
      font-size: .95rem;
      font-weight: 500;
      color: rgba(255,255,255,.75);
      letter-spacing: 3px;
      text-transform: uppercase;
      margin-bottom: 18px;
      min-height: 1.4em;
      font-family: 'Poppins', sans-serif;
    }
    .hero-subtitle .cursor {
      display: inline-block;
      width: 2px;
      height: 1em;
      background: rgba(255,255,255,.8);
      margin-left: 2px;
      vertical-align: middle;
      animation: blink .7s step-end infinite;
    }
    @keyframes blink { 50% { opacity: 0; } }

    /* Badge */
    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(255,255,255,.1);
      border: 1px solid rgba(255,255,255,.25);
      border-radius: 30px;
      padding: 6px 20px;
      font-size: .72rem;
      font-weight: 700;
      color: rgba(255,255,255,.9);
      text-transform: uppercase;
      letter-spacing: 2.5px;
      margin-bottom: 20px;
      backdrop-filter: blur(6px);
    }
    .hero-badge .dot {
      width: 7px; height: 7px;
      border-radius: 50%;
      background: #b97dff;
      box-shadow: 0 0 8px #b97dff, 0 0 16px #b97dff;
      animation: pulseDot 1.6s ease-in-out infinite;
    }
    @keyframes pulseDot { 0%,100% { transform: scale(1); } 50% { transform: scale(1.5); } }

    /* Scroll indicator */
    .scroll-indicator {
      position: absolute;
      bottom: 28px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 6;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
    }
    .scroll-indicator span {
      font-size: .65rem;
      font-weight: 600;
      color: rgba(255,255,255,.55);
      letter-spacing: 2px;
      text-transform: uppercase;
    }
    .scroll-mouse {
      width: 22px; height: 36px;
      border: 2px solid rgba(255,255,255,.4);
      border-radius: 12px;
      display: flex;
      justify-content: center;
      padding-top: 6px;
    }
    .scroll-mouse::before {
      content: '';
      width: 3px; height: 7px;
      background: rgba(255,255,255,.7);
      border-radius: 2px;
      animation: scrollWheel 1.6s ease-in-out infinite;
    }
    @keyframes scrollWheel {
      0%,100% { transform: translateY(0); opacity: 1; }
      60%      { transform: translateY(8px); opacity: 0.2; }
    }
    .flash-container {
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 9999;
      min-width: 320px;
    }

    /* =============================================
       SERVICES SECTION
       ============================================= */
    .services-section {
      background: transparent;
      position: relative;
      padding: 90px 0 80px;
    }
    .services-section::before {
      content: '';
      position: absolute;
      inset: 0;
      background: rgba(20, 5, 40, 0.55);
      pointer-events: none;
    }
    .services-section > * { position: relative; z-index: 1; }
    .section-heading {
      font-family: 'Bangers', cursive;
      font-size: clamp(2.8rem, 6vw, 5rem);
      color: white;
      text-transform: uppercase;
      letter-spacing: 4px;
      text-align: center;
      margin-bottom: 12px;
      text-shadow: 2px 4px 16px rgba(0,0,0,.6);
    }
    .section-subheading {
      text-align: center;
      color: rgba(255,255,255,.75);
      font-size: .92rem;
      margin-bottom: 56px;
      letter-spacing: 1px;
    }

    .service-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 28px;
      padding: 0 40px;
      max-width: 1280px;
      margin: 0 auto;
    }

    .svc-card {
      border-radius: 16px;
      overflow: hidden;
      background: #1a0a2e;
      border: 1px solid rgba(255,255,255,.08);
      transition: transform .25s, box-shadow .25s;
      display: flex;
      flex-direction: column;
    }
    .svc-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 16px 40px rgba(0,0,0,.45);
    }

    .svc-card-banner {
      height: 140px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 3.2rem;
    }
    /* Category colour bands */
    .svc-banner-hair   { background: linear-gradient(135deg, #5b21b6, #8B4FC8); }
    .svc-banner-nail   { background: linear-gradient(135deg, #0d6e6e, #0D9488); }
    .svc-banner-skin   { background: linear-gradient(135deg, #9d174d, #ec4899); }
    .svc-banner-lash   { background: linear-gradient(135deg, #1e3a5f, #3b82f6); }
    .svc-banner-lip    { background: linear-gradient(135deg, #7f1d1d, #ef4444); }
    .svc-banner-brow   { background: linear-gradient(135deg, #78350f, #f59e0b); }
    .svc-banner-other  { background: linear-gradient(135deg, #1f2937, #4b5563); }

    .svc-card-body {
      padding: 20px 22px 22px;
      flex: 1;
      display: flex;
      flex-direction: column;
    }
    .svc-category-badge {
      display: inline-block;
      font-size: .68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      padding: 3px 10px;
      border-radius: 20px;
      margin-bottom: 10px;
      background: rgba(255,255,255,.1);
      color: rgba(255,255,255,.7);
    }
    .svc-name {
      font-family: 'Poppins', sans-serif;
      font-weight: 700;
      font-size: 1.05rem;
      color: white;
      margin-bottom: 8px;
      line-height: 1.3;
    }
    .svc-desc {
      font-size: .78rem;
      color: rgba(255,255,255,.5);
      flex: 1;
      margin-bottom: 16px;
      line-height: 1.5;
    }
    .svc-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .svc-price {
      font-size: 1.15rem;
      font-weight: 800;
      color: #0D9488;
    }
    .btn-book-now {
      background: linear-gradient(135deg, #6B2D8B, #0D9488);
      color: white;
      border: none;
      border-radius: 8px;
      padding: 8px 18px;
      font-size: .78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
      text-decoration: none;
      cursor: pointer;
      transition: opacity .2s, transform .15s;
    }
    .btn-book-now:hover { opacity: .85; color: white; transform: translateY(-1px); }

    /* =============================================
       GET IN TOUCH / MAP SECTION
       ============================================= */
    .map-section {
      background: transparent;
      position: relative;
      padding: 90px 0 80px;
      overflow: hidden;
    }
    .map-section::before {
      content: '';
      position: absolute;
      inset: 0;
      background: rgba(20, 5, 40, 0.55);
      pointer-events: none;
    }
    .map-section > * { position: relative; z-index: 1; }

    .map-wrapper {
      position: relative;
      z-index: 1;
      max-width: 880px;
      margin: 0 auto;
      padding: 0 24px;
    }
    .map-iframe-container {
      border-radius: 16px;
      overflow: hidden;
      border: 2px solid rgba(255,255,255,.1);
      box-shadow: 0 20px 60px rgba(0,0,0,.5);
      margin-bottom: 28px;
    }
    .map-iframe-container iframe {
      display: block;
      width: 100%;
      height: 380px;
      border: none;
    }
    .map-buttons {
      display: flex;
      gap: 16px;
      justify-content: center;
      flex-wrap: wrap;
    }
    .btn-map {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: #0D9488;
      color: white;
      border: none;
      border-radius: 10px;
      padding: 14px 28px;
      font-size: .85rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      text-decoration: none;
      transition: background .2s, transform .15s;
    }
    .btn-map:hover { background: #0a7a70; color: white; transform: translateY(-2px); }

    /* =============================================
       CONTACT SECTION
       ============================================= */
    .contact-section {
      background: transparent;
      position: relative;
      padding: 90px 0 80px;
    }
    .contact-section::before {
      content: '';
      position: absolute;
      inset: 0;
      background: rgba(20, 5, 40, 0.55);
      pointer-events: none;
    }
    .contact-section > * { position: relative; z-index: 1; }
    .contact-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 24px;
      max-width: 1160px;
      margin: 0 auto;
      padding: 0 40px;
    }
    .contact-box {
      background: linear-gradient(135deg, #6B2D8B, #9b4dca);
      border: none;
      border-radius: 16px;
      padding: 28px 26px;
      transition: transform .25s, box-shadow .25s;
    }
    .contact-box:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 32px rgba(107,45,139,.3);
    }
    .contact-box-icon {
      width: 48px; height: 48px;
      background: rgba(255,255,255,.2);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.4rem;
      color: white;
      margin-bottom: 16px;
    }
    .contact-box-label {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: rgba(255,255,255,.8);
      margin-bottom: 8px;
    }
    .contact-box-value {
      font-size: .9rem;
      color: white;
      line-height: 1.6;
    }
    .contact-box-value a {
      color: #d4f5f2;
      text-decoration: none;
    }
    .contact-box-value a:hover { text-decoration: underline; }

    /* =============================================
       CTA SECTION
       ============================================= */
    .cta-section {
      background: linear-gradient(135deg, #4a1f62 0%, #6B2D8B 45%, #0D9488 100%);
      padding: 100px 24px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .cta-section::before {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse at 50% 0%, rgba(255,255,255,.08) 0%, transparent 60%);
      pointer-events: none;
    }
    .cta-section h2 {
      font-family: 'Bangers', cursive;
      font-size: clamp(2.2rem, 5vw, 4rem);
      color: white;
      letter-spacing: 3px;
      margin-bottom: 16px;
      position: relative;
      z-index: 1;
    }
    .cta-section p {
      color: rgba(255,255,255,.8);
      font-size: 1.05rem;
      margin-bottom: 40px;
      position: relative;
      z-index: 1;
    }
    .btn-cta {
      background: white;
      color: #6B2D8B;
      border: none;
      border-radius: 50px;
      padding: 18px 52px;
      font-size: 1rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 2px;
      text-decoration: none;
      transition: transform .2s, box-shadow .2s;
      position: relative;
      z-index: 1;
    }
    .btn-cta:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 32px rgba(0,0,0,.3);
      color: #5a1a8a;
    }

    /* =============================================
       FOOTER
       ============================================= */
    .sa-footer {
      background: #080410;
      color: rgba(255,255,255,.6);
      padding: 48px 40px 32px;
    }
    .sa-footer-inner {
      max-width: 1160px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 40px;
    }
    .sa-footer h5 {
      color: white;
      font-weight: 700;
      font-size: .85rem;
      text-transform: uppercase;
      letter-spacing: 2px;
      margin-bottom: 16px;
    }
    .sa-footer p, .sa-footer li { font-size: .83rem; line-height: 1.8; }
    .sa-footer ul { list-style: none; padding: 0; margin: 0; }
    .sa-footer a { color: rgba(255,255,255,.6); text-decoration: none; transition: color .2s; }
    .sa-footer a:hover { color: #0D9488; }
    .sa-footer-bottom {
      max-width: 1160px;
      margin: 32px auto 0;
      padding-top: 20px;
      border-top: 1px solid rgba(255,255,255,.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      font-size: .78rem;
    }
    .social-links { display: flex; gap: 14px; }
    .social-links a {
      width: 36px; height: 36px;
      border-radius: 50%;
      background: rgba(255,255,255,.08);
      color: rgba(255,255,255,.7);
      display: flex; align-items: center; justify-content: center;
      font-size: 1rem;
      transition: background .2s, color .2s;
      text-decoration: none;
    }
    .social-links a:hover { background: #0D9488; color: white; }

    /* =============================================
       RESPONSIVE
       ============================================= */
    @media (max-width: 768px) {
      /* ---- Navbar ---- */
      .sa-navbar {
        padding: 12px 16px;
      }
      .sa-nav-links { display: none !important; }

      /* Logo: shrink a bit */
      .sa-logo { width: 54px; height: 54px; }
      .sa-logo .logo-symbol { font-size: 1.05rem; }
      .sa-logo .logo-text   { font-size: .5rem; }

      /* ---- Hero content ---- */
      .hero-title {
        font-size: clamp(1.45rem, 5.5vw, 2.2rem);
        letter-spacing: 1px;
        margin-bottom: 28px;
      }
      .hero-content {
        padding: 0 18px 50px;
      }
      .hero-subtitle {
        font-size: .78rem;
        letter-spacing: 2px;
      }
      .hero-badge {
        font-size: .65rem;
        padding: 5px 14px;
      }
      .btn-hero-black {
        padding: 13px 22px;
        font-size: .82rem;
        width: 100%;
        text-align: center;
      }
      .hero-buttons {
        flex-direction: column;
        gap: 12px;
        width: 100%;
        max-width: 320px;
      }

      /* ---- Sections ---- */
      .service-grid  { padding: 0 16px; }
      .contact-grid  { padding: 0 16px; }

      /* ---- Footer ---- */
      .sa-footer { padding: 40px 20px 28px; }
      .sa-footer-inner { grid-template-columns: 1fr; gap: 28px; }
      .sa-footer-bottom { flex-direction: column; text-align: center; }
    }

    /* Extra-small phones (≤ 380px) */
    @media (max-width: 380px) {
      .hero-title { font-size: clamp(1.3rem, 5vw, 1.9rem); }
    }

    /* =============================================
       MOBILE HAMBURGER BUTTON
       ============================================= */
    .mobile-menu-btn {
      background: rgba(255,255,255,.15);
      border: 1.5px solid rgba(255,255,255,.6);
      border-radius: 8px;
      color: white;
      font-size: 1.6rem;
      width: 42px;
      height: 42px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: background .2s;
      padding: 0;
      line-height: 1;
    }
    .mobile-menu-btn:hover { background: rgba(255,255,255,.28); }

    /* =============================================
       MOBILE SLIDE-DOWN DRAWER
       ============================================= */
    .mobile-nav-drawer {
      position: relative;
      z-index: 9;
      background: rgba(15, 5, 30, 0.97);
      backdrop-filter: blur(12px);
      max-height: 0;
      overflow: hidden;
      transition: max-height .35s ease, padding .35s ease;
    }
    .mobile-nav-drawer.open {
      max-height: 500px;
      padding: 16px 0 20px;
    }

    .mobile-nav-links {
      list-style: none;
      margin: 0;
      padding: 0 20px 12px;
      border-bottom: 1px solid rgba(255,255,255,.1);
    }
    .mobile-nav-links li a {
      display: block;
      color: rgba(255,255,255,.85);
      text-decoration: none;
      font-size: .9rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      padding: 12px 4px;
      border-bottom: 1px solid rgba(255,255,255,.06);
      transition: color .2s;
    }
    .mobile-nav-links li:last-child a { border-bottom: none; }
    .mobile-nav-links li a:hover { color: #d4b8e8; }

    .mobile-nav-actions {
      padding: 14px 20px 0;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .mobile-nav-btn {
      display: block;
      width: 100%;
      padding: 13px 18px;
      border-radius: 10px;
      font-size: .85rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
      text-align: center;
      text-decoration: none;
      cursor: pointer;
      border: none;
      transition: opacity .2s, transform .15s;
    }
    .mobile-nav-btn:hover { opacity: .88; transform: translateY(-1px); }

    .mobile-nav-btn-solid {
      background: white;
      color: #5a1a8a;
    }
    .mobile-nav-btn-outline {
      background: transparent;
      color: white;
      border: 1.5px solid rgba(255,255,255,.7);
    }
    .mobile-nav-btn-ghost {
      background: rgba(255,255,255,.08);
      color: rgba(255,255,255,.75);
    }
    .mobile-nav-btn-danger {
      background: rgba(220,38,38,.2);
      color: #fca5a5;
      border: 1px solid rgba(220,38,38,.4);
    }
  </style>
</head>
<body>

<!-- FLASH MESSAGES -->
<?php
$successMsg = SessionGuard::getFlash('success');
$errorMsg   = SessionGuard::getFlash('error');
?>
<?php if ($successMsg || $errorMsg): ?>
<div class="flash-container">
  <?php if ($successMsg): ?>
  <div class="alert alert-success alert-dismissible shadow-lg" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($successMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
  <div class="alert alert-danger alert-dismissible shadow-lg" role="alert">
    <i class="bi bi-exclamation-circle-fill me-2"></i><?= htmlspecialchars($errorMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ================================================
     HERO SECTION
     ================================================ -->
<section class="hero-section">
  <div class="hero-overlay"></div>

  <nav class="sa-navbar">
    <a href="<?= BASE_URL ?>/public/home.php" class="sa-logo">
      <span class="logo-symbol">𝓢</span>
      <span class="logo-text">Selah</span>
    </a>

    <!-- Desktop nav links -->
    <ul class="sa-nav-links d-none d-lg-flex">
      <li><a href="<?= BASE_URL ?>/public/home.php" class="active">Home</a></li>
      <li><a href="#services">Services</a></li>
      <li><a href="#about">About</a></li>
      <li><a href="#contact">Contact</a></li>
    </ul>

    <!-- Desktop right buttons (hidden on mobile) -->
    <div class="d-none d-lg-flex align-items-center gap-3">
      <?php if ($isLoggedIn): ?>
        <a href="<?= BASE_URL ?>/public/my_bookings.php" class="btn-mybookings">
          <i class="bi bi-calendar2-check-fill"></i>
          MY BOOKINGS
        </a>
        <div class="dropdown">
          <button class="user-avatar-btn dropdown-toggle" data-bs-toggle="dropdown"
                  title="<?= $currentUser ?>" aria-label="Account menu for <?= $currentUser ?>">
            <?= htmlspecialchars($userInitials) ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow">
            <li class="px-3 py-1 text-muted small"><?= $currentUser ?></li>
            <li><hr class="dropdown-divider my-1"></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/my_bookings.php">
              <i class="bi bi-calendar-check me-2"></i>My Bookings
            </a></li>
            <li><hr class="dropdown-divider my-1"></li>
            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/public/auth/logout.php">
              <i class="bi bi-box-arrow-right me-2"></i>Logout
            </a></li>
          </ul>
        </div>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/public/my_bookings.php" class="btn-mybookings">
          <i class="bi bi-calendar2-check-fill"></i>
          MY BOOKINGS
        </a>
        <button class="btn-register-nav" data-bs-toggle="modal" data-bs-target="#registerModal">REGISTER</button>
        <button class="btn-login-nav" data-bs-toggle="modal" data-bs-target="#loginModal">LOG IN</button>
      <?php endif; ?>
    </div>

    <!-- Mobile hamburger (visible only on mobile) -->
    <button class="mobile-menu-btn d-lg-none" id="mobileMenuBtn" aria-label="Open menu">
      <i class="bi bi-list"></i>
    </button>
  </nav>

  <!-- Mobile slide-down menu -->
  <div class="mobile-nav-drawer" id="mobileNavDrawer">
    <ul class="mobile-nav-links">
      <li><a href="<?= BASE_URL ?>/public/home.php"><i class="bi bi-house me-2"></i>Home</a></li>
      <li><a href="#services"><i class="bi bi-scissors me-2"></i>Services</a></li>
      <li><a href="#about"><i class="bi bi-info-circle me-2"></i>About</a></li>
      <li><a href="#contact"><i class="bi bi-telephone me-2"></i>Contact</a></li>
    </ul>
    <div class="mobile-nav-actions">
      <?php if ($isLoggedIn): ?>
        <a href="<?= BASE_URL ?>/public/my_bookings.php" class="mobile-nav-btn mobile-nav-btn-outline">
          <i class="bi bi-calendar2-check-fill me-2"></i>My Bookings
        </a>
        <a href="<?= BASE_URL ?>/public/auth/logout.php" class="mobile-nav-btn mobile-nav-btn-danger">
          <i class="bi bi-box-arrow-right me-2"></i>Logout (<?= $currentUser ?>)
        </a>
      <?php else: ?>
        <button class="mobile-nav-btn mobile-nav-btn-outline" data-bs-toggle="modal" data-bs-target="#registerModal">
          <i class="bi bi-person-plus me-2"></i>Register
        </button>
        <button class="mobile-nav-btn mobile-nav-btn-solid" data-bs-toggle="modal" data-bs-target="#loginModal">
          <i class="bi bi-box-arrow-in-right me-2"></i>Log In
        </button>
        <a href="<?= BASE_URL ?>/public/my_bookings.php" class="mobile-nav-btn mobile-nav-btn-ghost">
          <i class="bi bi-calendar2-check-fill me-2"></i>My Bookings
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="hero-content">

    <!-- Typewriter subtitle -->
    <div class="hero-subtitle" id="heroSubtitle"></div>

    <h1 class="hero-title">
      A WEB-BASED SALON APPOINTMENT AND<br>
      SCHEDULING SYSTEM FOR SELAH AESTHETICS
    </h1>
    <div class="hero-buttons">
      <a href="<?= $isLoggedIn ? BASE_URL.'/public/booking/step1_service.php' : '#' ?>"
         <?= !$isLoggedIn ? 'data-bs-toggle="modal" data-bs-target="#loginModal"' : '' ?>
         class="btn-hero-black">
        BOOK YOUR APPOINTMENT
      </a>
      <a href="#services" class="btn-hero-black" style="background:#2a2a2a">
        VIEW OUR SERVICES
      </a>
    </div>

    <!-- No scroll indicator -->
  </div>
</section>

<!-- ================================================
     SECTION 2: SERVICES
     ================================================ -->
<section id="services" class="services-section">
  <h2 class="section-heading">SERVICES</h2>
  <p class="section-subheading">Our most popular beauty treatments</p>

  <?php
  $bookBase  = $isLoggedIn ? BASE_URL.'/public/booking/step1_service.php' : '#';
  $loginAttr = !$isLoggedIn ? 'data-bs-toggle="modal" data-bs-target="#loginModal"' : '';
  ?>

  <style>
  /* ── Mosaic grid ── */
  .svc-mosaic {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 10px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 32px;
  }

  .svc-tile {
    position: relative;
    border-radius: 14px;
    overflow: hidden;
    min-height: 280px;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    cursor: pointer;
    transition: transform .25s, box-shadow .25s;
  }
  .svc-tile:hover { transform: translateY(-6px); box-shadow: 0 20px 50px rgba(0,0,0,.55); }

  /* Fallback gradient backgrounds (shown when image is missing) */
  .svc-tile-hair,
  .svc-tile-lip,
  .svc-tile-nail,
  .svc-tile-skin,
  .svc-tile-lash { background: rgba(20, 5, 40, 0.45); }

  /* Service image */
  .svc-tile-img-wrap {
    position: absolute;
    inset: 0;
    z-index: 0;
  }
  .svc-tile-img-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
    transition: transform .4s ease;
  }
  .svc-tile:hover .svc-tile-img-wrap img {
    transform: scale(1.07);
  }
  /* Dark gradient overlay on top of the image */
  .svc-tile-img-wrap::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom,
      rgba(0,0,0,.05) 0%,
      rgba(0,0,0,.15) 40%,
      rgba(0,0,0,.65) 100%);
  }

  /* Bottom label bar */
  .svc-tile-label {
    position: relative;
    z-index: 2;
    background: rgba(0,0,0,.55);
    backdrop-filter: blur(6px);
    padding: 14px 16px 12px;
    text-align: center;
  }
  .svc-tile-name {
    font-family: 'Poppins', sans-serif;
    font-weight: 800;
    font-size: .8rem;
    color: white;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    line-height: 1.3;
    margin-bottom: 10px;
  }
  .svc-tile-book {
    display: inline-block;
    background: linear-gradient(135deg, #6B2D8B, #0D9488);
    color: white;
    border: none;
    border-radius: 20px;
    padding: 6px 18px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    text-decoration: none;
    transition: opacity .2s;
  }
  .svc-tile-book:hover { opacity: .85; color: white; }

  .svc-tile-view {
    display: inline-block;
    background: rgba(255,255,255,.18);
    color: white;
    border: 1.5px solid rgba(255,255,255,.5);
    border-radius: 20px;
    padding: 6px 14px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    cursor: pointer;
    transition: background .2s;
  }
  .svc-tile-view:hover { background: rgba(255,255,255,.32); }

  @media (max-width: 900px) {
    .svc-mosaic { grid-template-columns: repeat(3, 1fr); }
  }
  @media (max-width: 600px) {
    .svc-mosaic { grid-template-columns: repeat(2, 1fr); padding: 0 12px; }
    .svc-tile   { min-height: 200px; }
  }
  </style>

  <div class="svc-mosaic">

    <!-- 1. Hair Styling -->
    <div class="svc-tile svc-tile-hair">
      <div class="svc-tile-img-wrap">
        <img src="<?= BASE_URL ?>/assets/services/hair-styling.jpg"
             alt="Hair Styling"
             onerror="this.style.display='none'">
      </div>
      <div class="svc-tile-label">
        <div class="svc-tile-name">Hair Styling</div>
        <a href="<?= $bookBase ?>" <?= $loginAttr ?> class="svc-tile-book">Book Now</a>
      </div>
    </div>

    <!-- 2. Lip Blush -->
    <div class="svc-tile svc-tile-lip">
      <div class="svc-tile-img-wrap">
        <img src="<?= BASE_URL ?>/assets/services/lip-blush.jpg"
             alt="Lip Blush"
             onerror="this.style.display='none'">
      </div>
      <div class="svc-tile-label">
        <div class="svc-tile-name">Lip Blush<br><span style="font-size:.68rem;opacity:.8">Semi-Permanent Lip Tint</span></div>
        <a href="<?= $bookBase ?>" <?= $loginAttr ?> class="svc-tile-book">Book Now</a>
      </div>
    </div>

    <!-- 3. Nail Care -->
    <div class="svc-tile svc-tile-nail">
      <div class="svc-tile-img-wrap">
        <img src="<?= BASE_URL ?>/assets/services/nail-care.jpg"
             alt="Nail Care"
             onerror="this.style.display='none'">
      </div>
      <div class="svc-tile-label">
        <div class="svc-tile-name">Nail Care</div>
        <a href="<?= $bookBase ?>" <?= $loginAttr ?> class="svc-tile-book">Book Now</a>
      </div>
    </div>

    <!-- 4. Skin Treatments -->
    <div class="svc-tile svc-tile-skin">
      <div class="svc-tile-img-wrap">
        <img src="<?= BASE_URL ?>/assets/services/skin-treatment.jpg"
             alt="Skin Treatments"
             onerror="this.style.display='none'">
      </div>
      <div class="svc-tile-label">
        <div class="svc-tile-name">Skin Treatments</div>
        <a href="<?= $bookBase ?>" <?= $loginAttr ?> class="svc-tile-book">Book Now</a>
      </div>
    </div>

    <!-- 5. Eyelash Tint -->
    <div class="svc-tile svc-tile-lash">
      <div class="svc-tile-img-wrap">
        <img src="<?= BASE_URL ?>/assets/services/eyelash-tint.jpg"
             alt="Eyelash Tint"
             onerror="this.style.display='none'">
      </div>
      <div class="svc-tile-label">
        <div class="svc-tile-name">Eyelash Tint</div>
        <a href="<?= $bookBase ?>" <?= $loginAttr ?> class="svc-tile-book">Book Now</a>
      </div>
    </div>

  </div>
</section>

<!-- Service Image Lightbox -->
<div class="modal fade" id="svcImgModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:680px">
    <div class="modal-content border-0 shadow" style="background:#0f0a1e; border-radius:16px; overflow:hidden;">
      <div class="modal-header border-0 pb-0" style="background:linear-gradient(135deg,#6B2D8B,#0D9488);">
        <h5 class="modal-title text-white fw-bold" id="svcImgModalTitle"></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <img id="svcImgModalImg" src="" alt=""
             style="width:100%; max-height:520px; object-fit:cover; display:block;">
      </div>
    </div>
  </div>
</div>

<!-- ================================================
     SECTION 3: GET IN TOUCH / MAP
     ================================================ -->
<section id="about" class="map-section">
  <div class="map-wrapper">
    <h2 class="section-heading">GET IN TOUCH</h2>
    <p class="section-subheading">Find us at the heart of Midsayap, North Cotabato</p>

    <div class="map-iframe-container">
      <!--
        Embed centered on Poblacion 8 Villarica, Midsayap with a single pin.
        Uses the static map embed with a marker at exact coordinates.
        lat=7.1972, lng=124.5285 — Poblacion 8, Villarica, Midsayap
      -->
      <iframe
        src="https://maps.google.com/maps?q=7.1972,124.5285&z=17&output=embed&hl=en&markers=color:red%7Clabel:S%7C7.1972,124.5285"
        allowfullscreen
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
        title="Selah Aesthetics location map"
      ></iframe>
    </div>

    <div class="map-buttons">
      <a href="https://maps.google.com/?q=7.1972,124.5285&z=17"
         target="_blank" rel="noopener noreferrer" class="btn-map">
        <i class="bi bi-map-fill"></i>
        OPEN IN GOOGLE MAPS
      </a>
      <a href="https://maps.apple.com/?ll=7.1972,124.5285&z=17&q=Selah+Aesthetics"
         target="_blank" rel="noopener noreferrer" class="btn-map" style="background:#1c7a74">
        <i class="bi bi-map"></i>
        OPEN IN APPLE MAPS
      </a>
    </div>
  </div>
</section>

<!-- ================================================
     SECTION 4: CONTACT
     ================================================ -->
<section id="contact" class="contact-section">
  <h2 class="section-heading">CONTACT US</h2>
  <p class="section-subheading">Questions, directions, or just want to say hi?</p>

  <div class="contact-grid">

    <!-- ADDRESS -->
    <div class="contact-box">
      <div class="contact-box-icon"><i class="bi bi-geo-alt-fill"></i></div>
      <div class="contact-box-label">Address</div>
      <div class="contact-box-value">
        Poblacion 8, Villarica,<br>
        Midsayap, Philippines, 9410
      </div>
    </div>

    <!-- PHONE / MESSENGER -->
    <div class="contact-box">
      <div class="contact-box-icon"><i class="bi bi-telephone-fill"></i></div>
      <div class="contact-box-label">Phone / Messenger</div>
      <div class="contact-box-value">
        <a href="tel:+639995901804">0999 590 1804</a><br>
        <span style="color:rgba(255,255,255,.55);font-size:.78rem">Messenger:</span>
        <a href="https://m.me/SelahAesthetics" target="_blank" rel="noopener noreferrer">Selah Aesthetics</a>
      </div>
    </div>

    <!-- EMAIL -->
    <div class="contact-box">
      <div class="contact-box-icon"><i class="bi bi-envelope-fill"></i></div>
      <div class="contact-box-label">Email</div>
      <div class="contact-box-value">
        <a href="mailto:selahhouseofbeauty@gmail.com">selahhouseofbeauty@gmail.com</a>
      </div>
    </div>

    <!-- CANCELLATION POLICY -->
    <div class="contact-box">
      <div class="contact-box-icon"><i class="bi bi-calendar-x-fill"></i></div>
      <div class="contact-box-label">Cancellation Policy</div>
      <div class="contact-box-value">
        Please provide 24 hours notice for cancellations.
      </div>
    </div>

    <!-- HOURS -->
    <div class="contact-box">
      <div class="contact-box-icon"><i class="bi bi-clock-fill"></i></div>
      <div class="contact-box-label">Business Hours</div>
      <div class="contact-box-value">
        Mon – Fri &nbsp;·&nbsp; 9:00 AM – 8:00 PM<br>
        Saturday &nbsp;·&nbsp; 8:00 AM – 6:00 PM<br>
        Sunday &nbsp;·&nbsp; 10:00 AM – 5:00 PM
      </div>
    </div>

  </div>
</section>

<!-- ================================================
     SECTION 5: FINAL CTA
     ================================================ -->
<section class="cta-section">
  <h2>Ready for Your Beauty Appointment?</h2>
  <p>Book your slot today and let us take care of you.</p>
  <a href="<?= $isLoggedIn ? BASE_URL.'/public/booking/step1_service.php' : '#' ?>"
     <?= !$isLoggedIn ? 'data-bs-toggle="modal" data-bs-target="#loginModal"' : '' ?>
     class="btn-cta">
    BOOK AN APPOINTMENT
  </a>
</section>

<!-- ================================================
     FOOTER
     ================================================ -->
<footer class="sa-footer">
  <div class="sa-footer-inner">
    <div>
      <h5>Selah Aesthetics</h5>
      <p>Your premier beauty destination in Midsayap, North Cotabato. We blend artistry with care to help you look and feel your best.</p>
    </div>
    <div>
      <h5>Quick Links</h5>
      <ul>
        <li><a href="<?= BASE_URL ?>/public/home.php">Home</a></li>
        <li><a href="#services">Services</a></li>
        <li><a href="#about">About / Location</a></li>
        <li><a href="#contact">Contact</a></li>
        <li><a href="<?= BASE_URL ?>/public/booking/step1_service.php">Book Appointment</a></li>
      </ul>
    </div>
    <div>
      <h5>Contact</h5>
      <p>
        Poblacion 8, Villarica<br>
        Midsayap, Philippines 9410<br>
        <a href="tel:+639995901804">0999 590 1804</a><br>
        <a href="mailto:selahhouseofbeauty@gmail.com">selahhouseofbeauty@gmail.com</a>
      </p>
    </div>
  </div>
  <div class="sa-footer-bottom">
    <span>&copy; <?= date('Y') ?> Selah Aesthetics. All rights reserved.</span>
    <div class="social-links">
      <a href="mailto:selahhouseofbeauty@gmail.com" title="Email" aria-label="Email us">
        <i class="bi bi-envelope-fill"></i>
      </a>
      <a href="https://m.me/SelahAesthetics" target="_blank" rel="noopener noreferrer" title="Messenger" aria-label="Message us on Facebook">
        <i class="bi bi-messenger"></i>
      </a>
      <a href="https://www.facebook.com/SelahAesthetics" target="_blank" rel="noopener noreferrer" title="Facebook" aria-label="Visit our Facebook page">
        <i class="bi bi-facebook"></i>
      </a>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Mobile hamburger menu ──
(function () {
  var btn    = document.getElementById('mobileMenuBtn');
  var drawer = document.getElementById('mobileNavDrawer');
  if (!btn || !drawer) return;

  btn.addEventListener('click', function () {
    var isOpen = drawer.classList.toggle('open');
    btn.setAttribute('aria-expanded', isOpen);
    btn.querySelector('i').className = isOpen ? 'bi bi-x-lg' : 'bi bi-list';
  });

  // Close drawer when any link inside it is clicked
  drawer.querySelectorAll('a, button').forEach(function (el) {
    el.addEventListener('click', function () {
      drawer.classList.remove('open');
      btn.querySelector('i').className = 'bi bi-list';
    });
  });
})();
// Auto-dismiss flash messages after 4s
setTimeout(() => {
  document.querySelectorAll('.flash-container .alert').forEach(a => {
    bootstrap.Alert.getOrCreateInstance(a)?.close();
  });
}, 4000);

function switchModal(hideId, showId) {
  bootstrap.Modal.getInstance(document.getElementById(hideId))?.hide();
  setTimeout(() => new bootstrap.Modal(document.getElementById(showId)).show(), 400);
}

function toggleRegPw() {
  const inp = document.getElementById('regPassword');
  const ico = document.getElementById('regPwIcon');
  if (inp.type === 'password') {
    inp.type = 'text'; ico.className = 'bi bi-eye';
  } else {
    inp.type = 'password'; ico.className = 'bi bi-eye-slash';
  }
}

function checkPwStrength(val) {
  const bar  = document.getElementById('pwStrengthBar');
  const text = document.getElementById('pwStrengthText');
  const len  = val.length;
  let score = 0;
  if (len >= 8)  score++;
  if (len >= 12) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  const levels = [
    { pct: '0%',   bg: 'transparent', label: 'Minimum 8 characters required' },
    { pct: '25%',  bg: '#ef4444',     label: 'Too short — need at least 8 characters' },
    { pct: '50%',  bg: '#f59e0b',     label: 'Weak password' },
    { pct: '75%',  bg: '#3b82f6',     label: 'Good password' },
    { pct: '90%',  bg: '#10b981',     label: 'Strong password' },
    { pct: '100%', bg: '#059669',     label: 'Very strong password ✓' },
  ];

  const lvl = len === 0 ? levels[0] : levels[Math.min(score, 5)];
  bar.style.width      = lvl.pct;
  bar.style.background = lvl.bg;
  text.textContent     = lvl.label;
  text.style.color     = len === 0 ? 'rgba(255,255,255,.6)' : lvl.bg;
}

// ── Register form: AJAX submit ──
document.addEventListener('DOMContentLoaded', function() {
  var form = document.getElementById('registerModalForm');
  if (!form) return;

  form.addEventListener('submit', async function(e) {
    e.preventDefault();

    // Clear previous errors
    ['username','email','country','phone','password'].forEach(function(field) {
      var errEl = document.getElementById('err_' + field);
      var inpEl = document.getElementById('reg_' + field) || (field === 'password' ? document.getElementById('regPassword') : null);
      if (errEl) errEl.textContent = '';
      if (inpEl) inpEl.classList.remove('reg-error');
    });
    document.getElementById('err_terms').textContent = '';
    document.getElementById('regGeneralError').style.display = 'none';

    // Client-side checks
    var pw    = document.getElementById('regPassword').value;
    var terms = document.getElementById('regTerms').checked;

    if (pw.length < 8) {
      document.getElementById('err_password').textContent = 'Password must be at least 8 characters.';
      document.getElementById('regPassword').classList.add('reg-error');
      document.getElementById('pwStrengthText').textContent = '⚠ Password must be at least 8 characters!';
      document.getElementById('pwStrengthText').style.color = '#f87171';
      return;
    }
    if (!terms) {
      document.getElementById('err_terms').textContent = 'You must agree to the Terms & Conditions.';
      return;
    }

    // Disable button while submitting
    var btn = document.getElementById('regSubmitBtn');
    btn.disabled    = true;
    btn.textContent = 'Creating Account...';
    btn.style.opacity = '.7';

    try {
      var formData = new FormData(form);
      var resp = await fetch('<?= BASE_URL ?>/public/auth/register_process.php', {
        method: 'POST',
        body:   formData
      });

      var text = await resp.text();
      var data;
      try {
        data = JSON.parse(text);
      } catch(parseErr) {
        throw new Error('Server returned unexpected response: ' + text.substring(0, 100));
      }

      if (data.success) {
        bootstrap.Modal.getInstance(document.getElementById('registerModal'))?.hide();
        setTimeout(function() {
          new bootstrap.Modal(document.getElementById('regSuccessModal')).show();
        }, 350);
      } else if (data.errors && Object.keys(data.errors).length > 0) {
        Object.entries(data.errors).forEach(function([field, msg]) {
          var errEl = document.getElementById('err_' + field);
          var inpEl = document.getElementById('reg_' + field) || (field === 'password' ? document.getElementById('regPassword') : null);
          if (errEl) errEl.textContent = msg;
          if (inpEl) inpEl.classList.add('reg-error');
        });
      } else {
        var genErr = document.getElementById('regGeneralError');
        genErr.textContent = data.message || 'Something went wrong. Please try again.';
        genErr.style.display = 'block';
      }
    } catch (err) {
      var genErr = document.getElementById('regGeneralError');
      genErr.textContent = 'Network error. Please check your connection and try again.';
      genErr.style.display = 'block';
    }

    btn.disabled    = false;
    btn.textContent = 'CREATE ACCOUNT';
    btn.style.opacity = '1';
  });
});

function viewServiceImg(src, name) {
  document.getElementById('svcImgModalImg').src   = src;
  document.getElementById('svcImgModalTitle').textContent = name;
  new bootstrap.Modal(document.getElementById('svcImgModal')).show();
}

<?php if ($autoModal): ?>
document.addEventListener('DOMContentLoaded', function () {
  new bootstrap.Modal(document.getElementById('<?= $autoModal ?>')).show();
});
<?php endif; ?>
</script>

<!-- LOGIN MODAL -->
<div class="modal fade" id="loginModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
    <div class="modal-content border-0 shadow-lg" style="border-radius:20px;overflow:hidden">
      <div style="background:linear-gradient(135deg,#8B4FC8,#0D9488);padding:32px 32px 24px;text-align:center;color:white;position:relative">
        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
        <h4 class="fw-bold mb-0">sign in</h4>
      </div>
      <div style="background:linear-gradient(160deg,#7B3FC0 0%,#0D9488 100%);padding:28px 36px 32px">
        <form method="post" action="<?= BASE_URL ?>/public/auth/login_process.php">
          <input type="hidden" name="csrf_token" value="<?= SessionGuard::generateCsrfToken() ?>">
          <div class="mb-3">
            <input type="text" name="login" class="form-control" placeholder="Username"
                   style="border-radius:25px;border:none;padding:12px 20px;font-size:.9rem;background:rgba(255,255,255,.85)" required>
          </div>
          <div class="mb-2">
            <input type="password" name="password" class="form-control" placeholder="Password"
                   style="border-radius:25px;border:none;padding:12px 20px;font-size:.9rem;background:rgba(255,255,255,.85)" required>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-4" style="color:rgba(255,255,255,.85);font-size:.82rem">
            <div class="form-check mb-0">
              <input class="form-check-input" type="checkbox" id="rememberMe">
              <label class="form-check-label" for="rememberMe" style="color:rgba(255,255,255,.85)">Remember me</label>
            </div>
            <a href="<?= BASE_URL ?>/public/auth/forgot_password.php" style="color:rgba(255,255,255,.85);text-decoration:none">Forgot Password</a>
          </div>
          <button type="submit" style="width:100%;padding:13px;background:#111;color:white;border:none;border-radius:30px;font-weight:800;font-size:1rem;letter-spacing:2px;text-transform:uppercase">
            LOGIN
          </button>
        </form>
        <div class="text-center mt-3" style="color:rgba(255,255,255,.8);font-size:.82rem">
          Don't have an account?
          <a href="#" onclick="switchModal('loginModal','registerModal')"
             style="background:#0D9488;color:white;padding:3px 14px;border-radius:20px;text-decoration:none;font-weight:700;font-size:.8rem">
            REGISTER HERE
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- REGISTER MODAL -->
<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:460px">
    <div class="modal-content border-0 shadow-lg" style="border-radius:20px;overflow:hidden">
      <div style="background:linear-gradient(135deg,#8B4FC8,#0D9488);padding:20px 32px 16px;text-align:center;color:white;position:relative">
        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3"
                data-bs-dismiss="modal" aria-label="Close"></button>
        <h5 id="registerModalLabel" class="fw-bold mb-0" style="letter-spacing:2px;font-size:1rem">
          · Registration Form ·
        </h5>
      </div>
      <div style="background:linear-gradient(160deg,#7B3FC0 0%,#0D9488 100%);padding:20px 36px 28px">
        <!-- General error banner -->
        <div id="regGeneralError" style="display:none;background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.5);
             border-radius:8px;padding:10px 14px;margin-bottom:14px;color:#fca5a5;font-size:.82rem;text-align:center"></div>

        <form id="registerModalForm" method="post" action="<?= BASE_URL ?>/public/auth/register_process.php" novalidate>
          <input type="hidden" name="csrf_token" value="<?= SessionGuard::generateCsrfToken() ?>">

          <div class="mb-3">
            <label style="color:rgba(255,255,255,.75);font-size:.78rem;margin-bottom:2px">* Name</label>
            <input type="text" name="username" id="reg_username" class="reg-input form-control"
                   maxlength="50" required autocomplete="name" value="<?= $regName ?>">
            <span class="reg-field-error" id="err_username"></span>
          </div>

          <div class="mb-3">
            <label style="color:rgba(255,255,255,.75);font-size:.78rem;margin-bottom:2px">* Email address</label>
            <input type="email" name="email" id="reg_email" class="reg-input form-control"
                   maxlength="254" required autocomplete="email" value="<?= $regEmail ?>">
            <span class="reg-field-error" id="err_email"></span>
          </div>

          <div class="mb-3">
            <label style="color:rgba(255,255,255,.75);font-size:.78rem;margin-bottom:2px">* Country</label>
            <select name="country" id="reg_country" class="reg-input form-control" required>
              <option value="">— Select your country —</option>
              <?php foreach (getCountryList() as $c): ?>
              <option value="<?= htmlspecialchars($c) ?>"
                <?= ($regCountry === $c || ($regCountry === '' && $c === 'Philippines')) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <span class="reg-field-error" id="err_country"></span>
          </div>

          <div class="mb-3">
            <label style="color:rgba(255,255,255,.75);font-size:.78rem;margin-bottom:2px">* Phone</label>
            <input type="tel" name="phone" id="reg_phone" class="reg-input form-control"
                   maxlength="20" required autocomplete="tel" value="<?= $regPhone ?>">
            <span class="reg-field-error" id="err_phone"></span>
          </div>

          <div class="mb-3 position-relative">
            <label style="color:rgba(255,255,255,.75);font-size:.78rem;margin-bottom:2px">* Password</label>
            <div class="d-flex align-items-center">
              <input type="password" name="password" id="regPassword" class="reg-input form-control"
                     minlength="8" maxlength="128" required autocomplete="new-password"
                     style="flex:1" oninput="checkPwStrength(this.value)">
              <button type="button" onclick="toggleRegPw()" tabindex="-1"
                      style="background:none;border:none;color:rgba(255,255,255,.7);margin-left:-32px;z-index:5;cursor:pointer;font-size:1rem" aria-label="Toggle password visibility">
                <i class="bi bi-eye-slash" id="regPwIcon"></i>
              </button>
            </div>
            <span class="reg-field-error" id="err_password"></span>
            <div style="margin-top:6px">
              <div style="height:4px;border-radius:2px;background:rgba(255,255,255,.2);overflow:hidden">
                <div id="pwStrengthBar" style="height:100%;width:0%;border-radius:2px;transition:width .3s,background .3s"></div>
              </div>
              <div id="pwStrengthText" style="font-size:.7rem;color:rgba(255,255,255,.6);margin-top:3px">
                Minimum 8 characters required
              </div>
            </div>
          </div>

          <div class="mb-4 form-check">
            <input type="checkbox" class="form-check-input" id="regTerms" required>
            <label class="form-check-label" for="regTerms"
                   style="color:rgba(255,255,255,.8);font-size:.76rem;line-height:1.4">
              I agree to the Terms &amp; Conditions and Privacy Policy of Selah Aesthetics.
            </label>
            <span class="reg-field-error" id="err_terms"></span>
          </div>

          <button type="submit" id="regSubmitBtn"
                  style="width:100%;padding:13px;background:#111827;color:white;border:none;border-radius:30px;font-weight:800;font-size:.9rem;letter-spacing:2px;text-transform:uppercase;transition:opacity .2s"
                  onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            CREATE ACCOUNT
          </button>
        </form>

        <div class="text-center mt-3" style="color:rgba(255,255,255,.8);font-size:.82rem">
          Already have an account?
          <a href="#" onclick="switchModal('registerModal','loginModal')"
             style="color:white;font-weight:700;text-decoration:underline">Sign in</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- REGISTRATION SUCCESS POPUP -->
<div class="modal fade" id="regSuccessModal" tabindex="-1" aria-labelledby="regSuccessModalLabel" aria-modal="true" role="dialog" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;overflow:hidden;background:#fff">
      <!-- Gradient top band -->
      <div style="background:linear-gradient(135deg,#6B2D8B,#0D9488);padding:32px 24px 28px;text-align:center">
        <div style="width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.2);
                    display:flex;align-items:center;justify-content:center;
                    margin:0 auto 16px;font-size:2.4rem;color:white">
          <i class="bi bi-patch-check-fill"></i>
        </div>
        <h5 id="regSuccessModalLabel" style="color:white;font-weight:800;font-size:1.2rem;margin:0;letter-spacing:.5px">
          Registration Successful!
        </h5>
      </div>
      <!-- Body -->
      <div style="padding:28px 32px 32px;text-align:center">
        <p style="color:#1a1a2e;font-size:.95rem;font-weight:600;margin-bottom:10px">
          🎉 Thank you for signing up!
        </p>
        <p style="color:#555;font-size:.87rem;line-height:1.7;margin-bottom:24px">
          Your account has been submitted.<br>
          Please wait for <strong>admin approval</strong> before you can log in.<br>
          We'll notify you once your account is approved.
        </p>
        <!-- Steps -->
        <div style="background:#f8f4fb;border-radius:12px;padding:18px 20px;text-align:left;margin-bottom:24px">
          <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px;font-size:.83rem;color:#444">
            <div style="width:26px;height:26px;border-radius:50%;background:#6B2D8B;color:white;
                        font-weight:800;font-size:.75rem;display:flex;align-items:center;
                        justify-content:center;flex-shrink:0">1</div>
            <div><strong>Verify your email</strong> — check your inbox and click the verification link we sent.</div>
          </div>
          <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px;font-size:.83rem;color:#444">
            <div style="width:26px;height:26px;border-radius:50%;background:#6B2D8B;color:white;
                        font-weight:800;font-size:.75rem;display:flex;align-items:center;
                        justify-content:center;flex-shrink:0">2</div>
            <div><strong>Wait for admin approval</strong> — our team will review your account shortly.</div>
          </div>
          <div style="display:flex;align-items:flex-start;gap:12px;font-size:.83rem;color:#444">
            <div style="width:26px;height:26px;border-radius:50%;background:#0D9488;color:white;
                        font-weight:800;font-size:.75rem;display:flex;align-items:center;
                        justify-content:center;flex-shrink:0">3</div>
            <div><strong>You're in!</strong> — once approved, log in and book your appointment.</div>
          </div>
        </div>
        <a href="<?= BASE_URL ?>/public/home.php"
           style="display:inline-block;background:linear-gradient(135deg,#6B2D8B,#0D9488);
                  color:white;border:none;border-radius:30px;padding:13px 36px;
                  font-size:.9rem;font-weight:800;text-decoration:none;
                  letter-spacing:1px;text-transform:uppercase;transition:opacity .2s"
           onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
          <i class="bi bi-house me-2"></i>Back to Home
        </a>
      </div>
    </div>
  </div>
</div>

<style>
.reg-input {
  border-radius: 0 !important; border: none !important;
  border-bottom: 1.5px solid rgba(255,255,255,.55) !important;
  background: transparent !important; color: white !important;
  padding: 7px 4px !important; font-size: .88rem !important;
  box-shadow: none !important; transition: border-color .2s;
}
.reg-input:focus { border-bottom-color: white !important; background: transparent !important; color: white !important; box-shadow: none !important; }
.reg-input::placeholder { color: rgba(255,255,255,.4) !important; }
.reg-input:-webkit-autofill { -webkit-box-shadow: 0 0 0 1000px #7B3FC0 inset !important; -webkit-text-fill-color: white !important; }
.reg-input.reg-error { border-bottom-color: #f87171 !important; }
.reg-field-error {
  display: block;
  color: #fca5a5;
  font-size: .75rem;
  margin-top: 4px;
  min-height: 1em;
}
/* Style the country select to match other reg-inputs */
select.reg-input {
  appearance: none;
  -webkit-appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='rgba(255,255,255,0.7)' d='M6 8L1 3h10z'/%3E%3C/svg%3E") !important;
  background-repeat: no-repeat !important;
  background-position: right 4px center !important;
  background-size: 12px !important;
  padding-right: 24px !important;
  cursor: pointer;
}
select.reg-input option {
  background: #5a2080;
  color: white;
}
</style>

</body>
</html>
