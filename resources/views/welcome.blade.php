<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>MEGA UNI STORE</title>

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swaphttps://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Favicons -->
  <link rel="icon" type="image/png" href="{{ asset('general/assets/img/cefaempresa.png') }}">
  <link rel="shortcut icon" type="image/png" href="{{ asset('general/assets/img/cefaempresa.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('general/assets/img/cefaempresa.png') }}">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Jost:300,300i,400,400i,500,500i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="https://sicefa.com.co/general/assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="https://sicefa.com.co/general/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Add direct CDNs for missing icons (Bootstrap Icons, FontAwesome, BoxIcons, RemixIcons) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
  
  <link href="https://sicefa.com.co/general/assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="https://sicefa.com.co/general/assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
  <!-- Template Main CSS File -->
  <link href="https://sicefa.com.co/general/assets/css/style.css" rel="stylesheet">
  
  <link href="https://sicefa.com.co/css/pantCarg.css" rel="stylesheet">

  <style>
    /* Mega Uni Store Green Theme Overrides */
    :root {
      --sena-green: #2E6EA8;
      --sena-green-hover: #215480;
      --sena-green-rgb: 46, 110, 168;
    }
    
    a {
      color: var(--sena-green);
    }
    a:hover {
      color: var(--sena-green-hover);
    }
    
    .btn-get-started, .cta-btn, .portfolio-info .details-link {
      background-color: var(--sena-green) !important;
    }
    .btn-get-started:hover, .cta-btn:hover {
      background-color: var(--sena-green-hover) !important;
    }
    
    .section-title h2::after {
      background: var(--sena-white) !important;
    }
    
    #header.header-scrolled, #header {
      background: rgba(10, 17, 25, 0.95) !important;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border-bottom: 3px solid #2E6EA8;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }
    
    /* Ensure other primary elements get the green color */
    .services .icon-box:hover .icon i,
    #portfolio-flters li:hover, #portfolio-flters li.filter-active {
      color: var(--sena-green) !important;
    }
    
    .portfolio .portfolio-item .portfolio-info {
        background: rgba(var(--sena-green-rgb), 0.9) !important;
    }
    
    .why-us .accordion-list a:hover {
        color: var(--sena-green) !important;
    }
    
    /* Back to top button if it exists */
    .back-to-top {
        background: var(--sena-green) !important;
    }
    .back-to-top:hover {
        background: var(--sena-green-hover) !important;
    }

    /* Provide green overrides for main sections that have orange backgrounds */
    #hero {
        background: rgba(var(--sena-green-rgb), 0.9) !important;
    }
    
    /* Dark Navy Mega Uni Store Footer Styling */
    #footer {
        background: #0A1119 !important;
        color: #ffffff !important;
        font-size: 14px;
    }
    #footer .footer-top {
        background: #0E1723 !important;
        border-top: 4px solid #2E6EA8;
        padding: 50px 0 30px 0;
    }
    #footer .footer-top h4 {
        color: #7FB2DE !important;
        font-size: 17px;
        font-weight: 700;
        margin-bottom: 20px;
        position: relative;
        padding-bottom: 10px;
    }
    #footer .footer-top h4::after {
        content: '';
        position: absolute;
        display: block;
        width: 35px;
        height: 3px;
        background: #2E6EA8;
        bottom: 0;
        left: 0;
    }
    #footer .footer-top p {
        color: rgba(255, 255, 255, 0.82) !important;
        line-height: 1.6;
    }
    #footer .footer-top ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    #footer .footer-top ul li {
        padding: 7px 0;
        display: flex;
        align-items: center;
    }
    #footer .footer-top ul li i {
        color: #7FB2DE;
        margin-right: 8px;
        font-size: 12px;
    }
    #footer .footer-top ul a {
        color: rgba(255, 255, 255, 0.85) !important;
        transition: all 0.25s ease;
        text-decoration: none;
    }
    #footer .footer-top ul a:hover {
        color: #7FB2DE !important;
        padding-left: 6px;
    }
    .footer-social-btn {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.12);
        color: #ffffff !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 8px;
        font-size: 15px;
        transition: all 0.3s ease;
    }
    .footer-social-btn:hover {
        background: #2E6EA8 !important;
        color: #ffffff !important;
        transform: translateY(-4px);
    }
    #footer .footer-bottom {
        background: #070D14 !important;
        padding: 20px 0;
        color: rgba(255, 255, 255, 0.7) !important;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }

    .contact .info .email:hover i, .contact .info .address:hover i, .contact .info .phone:hover i {
        background: var(--sena-green) !important;
        color: #fff !important;
    }

    button[type="submit"] {
        background: var(--sena-green) !important;
    }
    /* Header & Navigation Dark Navy Theme (Matching Footer) */
    .navbar a, .navbar a:focus {
      color: rgba(255, 255, 255, 0.9) !important;
      font-weight: 600;
    }
    .navbar a:hover, .navbar .active, .navbar .active:focus, .navbar li:hover > a {
      color: #7FB2DE !important;
    }
    .navbar .dropdown ul {
      background: #0E1723 !important;
      border: 1px solid rgba(255, 255, 255, 0.15);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
      border-radius: 12px;
      overflow: hidden;
    }
    .navbar .dropdown ul a {
      color: rgba(255, 255, 255, 0.85) !important;
    }
    .navbar .dropdown ul a:hover, .navbar .dropdown ul .active:hover, .navbar .dropdown ul li:hover > a {
      background: rgba(46, 110, 168, 0.25) !important;
      color: #7FB2DE !important;
    }

    /* Process Modules & Video Banner Styling */
    #hero {
      position: relative;
      overflow: hidden;
      min-height: 100vh;
      background: linear-gradient(135deg, #0E1723 0%, #142033 100%) !important;
    }
    .hero-video-bg {
      position: absolute;
      top: 50%;
      left: 50%;
      min-width: 100%;
      min-height: 100%;
      width: auto;
      height: auto;
      z-index: 1;
      transform: translate(-50%, -50%);
      object-fit: cover;
      filter: brightness(0.85) contrast(1.05);
    }
    .hero-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 2;
      background: linear-gradient(135deg, rgba(4, 12, 24, 0.72) 0%, rgba(10, 30, 60, 0.55) 100%);
    }
    .hero-content {
      position: relative;
      z-index: 3;
    }
    .logo-header-img {
      max-height: 42px;
      width: auto;
      background: #ffffff;
      padding: 3px;
      border-radius: 50%;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
    }
    .hero-video-showcase {
      background: rgba(0, 32, 20, 0.4) !important;
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border: 2px solid rgba(255, 255, 255, 0.25) !important;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4) !important;
      transition: all 0.3s ease;
    }
    .hero-video-showcase:hover {
      transform: translateY(-5px);
      border-color: rgba(122, 170, 214, 0.6) !important;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5) !important;
    }
    .hero-social-btn {
      width: 36px;
      height: 36px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.18);
      color: #ffffff;
      transition: all 0.25s ease;
    }
    .hero-social-btn:hover {
      background: #2E6EA8;
      color: #ffffff;
      transform: translateY(-3px);
    }
    .process-category-block {
      background: #ffffff;
      transition: all 0.3s ease;
    }
    .hover-lift {
      transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .hover-lift:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
    }
    .hover-green:hover { color: #2E6EA8 !important; }
    .hover-blue:hover { color: #142033 !important; }
    .hover-warning:hover { color: #e65100 !important; }

    /* Contact / PQRS Section Corporate Styling */
    .contact .info, .contact .php-email-form {
        border-top: 4px solid #2E6EA8 !important;
        border-bottom: 4px solid #0E1723 !important;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        background: #ffffff;
        padding: 30px;
    }
    .contact .info div i {
        font-size: 18px;
        color: #ffffff !important;
        background: #2E6EA8 !important;
        float: left;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.3s ease;
        margin-right: 15px;
        box-shadow: 0 4px 10px rgba(46, 110, 168, 0.3);
    }
    .contact .info div:hover i {
        background: #0E1723 !important;
        color: #7FB2DE !important;
        transform: scale(1.1);
        box-shadow: 0 6px 15px rgba(0, 35, 54, 0.4);
    }
    .contact .info h4 {
        color: #0E1723 !important;
        font-weight: 700;
        margin-bottom: 2px;
    }
    .contact .info p {
        color: #555555 !important;
        font-size: 14px;
    }
    .contact .info a {
        text-decoration: none;
        display: flex;
        align-items: center;
        margin-bottom: 20px;
    }
    .contact .php-email-form button[type="submit"] {
        background: #2E6EA8 !important;
        border: 0;
        padding: 12px 34px;
        color: #fff;
        transition: 0.4s;
        border-radius: 50px;
        font-weight: 700;
    }
    .contact .php-email-form button[type="submit"]:hover {
        background: #0E1723 !important;
        color: #7FB2DE !important;
    }

    /* Virtual Assistant (Mega Uni Store Bot) Styles */
    .sena-bot-trigger {
      position: fixed;
      bottom: 25px;
      right: 25px;
      width: 66px;
      height: 66px;
      border-radius: 50%;
      background: linear-gradient(135deg, #0E1723 0%, #142033 100%);
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 10px 30px rgba(0, 35, 55, 0.45);
      cursor: pointer;
      z-index: 99999;
      transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      border: 3px solid #2E6EA8;
      padding: 3px;
    }
    .sena-bot-trigger:hover {
      transform: scale(1.1) rotate(4deg);
      box-shadow: 0 14px 35px rgba(46, 110, 168, 0.55);
    }
    .sena-bot-chat-icon-badge {
      position: absolute;
      bottom: -2px;
      right: -2px;
      width: 22px;
      height: 22px;
      background: #2E6EA8;
      color: #ffffff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      border: 2px solid #ffffff;
      box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    }
    .sena-bot-badge {
      position: absolute;
      top: -3px;
      right: -3px;
      width: 16px;
      height: 16px;
      background: #7FB2DE;
      border: 2px solid #ffffff;
      border-radius: 50%;
      animation: pulse-dot 2s infinite;
    }
    @keyframes pulse-dot {
      0% { box-shadow: 0 0 0 0 rgba(122, 170, 214, 0.7); }
      70% { box-shadow: 0 0 0 8px rgba(122, 170, 214, 0); }
      100% { box-shadow: 0 0 0 0 rgba(122, 170, 214, 0); }
    }
    .sena-bot-chat {
      position: fixed;
      bottom: 100px;
      right: 25px;
      width: 380px;
      max-width: calc(100vw - 40px);
      height: 540px;
      max-height: calc(100vh - 120px);
      background: #ffffff;
      border-radius: 20px;
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
      z-index: 99998;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      transition: all 0.3s ease;
      opacity: 0;
      visibility: hidden;
      transform: translateY(20px) scale(0.95);
      border: 1px solid rgba(0, 50, 77, 0.15);
    }
    .sena-bot-chat.active {
      opacity: 1;
      visibility: visible;
      transform: translateY(0) scale(1);
    }
    .sena-bot-header {
      background: linear-gradient(135deg, #0E1723 0%, #142033 100%);
      color: #ffffff;
      padding: 16px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 3px solid #2E6EA8;
    }
    .sena-bot-body {
      flex: 1;
      padding: 16px;
      overflow-y: auto;
      background: #f8f9fa;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .bot-msg, .user-msg {
      max-width: 85%;
      padding: 12px 16px;
      border-radius: 16px;
      font-size: 14px;
      line-height: 1.5;
    }
    .bot-msg {
      background: #ffffff;
      color: #1a252f;
      align-self: flex-start;
      border-bottom-left-radius: 4px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
      border-left: 3px solid #2E6EA8;
    }
    .user-msg {
      background: #2E6EA8;
      color: #ffffff;
      align-self: flex-end;
      border-bottom-right-radius: 4px;
      box-shadow: 0 2px 8px rgba(46, 110, 168, 0.25);
    }
    .sena-bot-chip {
      background: #ffffff;
      border: 1px solid #2E6EA8;
      color: #142033;
      padding: 5px 11px;
      border-radius: 20px;
      font-size: 12px;
      cursor: pointer;
      transition: all 0.2s ease;
      display: inline-block;
      margin-top: 4px;
      font-weight: 600;
    }
    .sena-bot-chip:hover {
      background: #2E6EA8;
      color: #ffffff;
    }
    .sena-bot-footer {
      padding: 12px 16px;
      background: #ffffff;
      border-top: 1px solid #e9ecef;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .sena-bot-input {
      flex: 1;
      border: 1px solid #ced4da;
      border-radius: 20px;
      padding: 8px 16px;
      font-size: 14px;
      outline: none;
      transition: border-color 0.2s;
    }
    .sena-bot-input:focus {
      border-color: #2E6EA8;
    }

    /* Overrides azul Mega Uni Store */
    .text-success{color:#2E6EA8 !important}
    .btn-success{background-color:#2E6EA8 !important;border-color:#2E6EA8 !important}
    .btn-success:hover{background-color:#215480 !important;border-color:#215480 !important}
    .bg-success{background-color:#2E6EA8 !important}
    .btn-outline-success{color:#2E6EA8 !important;border-color:#2E6EA8 !important}
    .badge.bg-success{background-color:#2E6EA8 !important}
  
    /* Header oculto en el hero, aparece al salir */
    #header{transform:translateY(-100%);transition:transform .35s ease, background .3s ease}
    #header.reveal{transform:translateY(0)}
</style>
</head>
<body class="antialiased">

    
        
    <!-- ======= Header ======= -->
<link rel="stylesheet" href="https://sicefa.com.co/css/navbar.css">
<header id="header" class="fixed-top shadow-sm">
  <div class="container d-flex align-items-center">

    <h1 class="logo me-auto"><a href="/" class="d-flex align-items-center text-white text-decoration-none"><img src="{{ asset('general/assets/img/cefaempresa.png') }}" alt="Logo Mega Uni Store" class="logo-header-img me-2"><span>MEGA UNI STORE</span></a></h1>
    <!-- Uncomment below if you prefer to use an image logo -->
    <!-- <a href="index.html" class="logo me-auto"><img src="assets/img/logo.png" alt="" class="img-fluid"></a>-->
    <nav id="navbar-top" class="navbar" style="margin-right: 20px">
      <div class="navbar-nav">
          <div class="dropdown d-lg-none">
              <a class="nav-link scrollto" data-toggle="dropdown" href="#">
                  Menu
              </a>
              <div class="dropdown-menu">
                  <a class="nav-link scrollto active" href="#hero">Inicio</a>
                  <a class="nav-link scrollto" href="#modules">Módulos</a>
                  <a class="nav-link scrollto" href="#why-us">Soluciones</a>
                  <a class="nav-link scrollto" href="#about">Nosotros</a>
                  <a class="nav-link scrollto" href="#contact">Contacto</a>
              </div>
          </div>
          <div class="dropdown d-lg-none">
              @auth
                  <a href="{{ url('/') }}"><i class="fas fa-user-circle me-1"></i> {{ Auth::user()->full_name }} ({{ Auth::user()->primary_role }})</a>
              @else
                  <a href="{{ route('login') }}">Iniciar sesión</a>
              @endauth
          </div>
          <div class="dropdown lang d-lg-none">
              <a class="nav-link scrollto" data-toggle="dropdown" href="#">
                   <i class="fas fa-globe"></i>
              </a>
              <div class="dropdown-menu">
                  <a href="https://sicefa.com.co/lang/es" class="dropdown-item scrollto">Español</a>
                  <a href="https://sicefa.com.co/lang/en" class="dropdown-item scrollto">English</a>
              </div>
          </div>
      </div>
  </nav>
    <nav id="navbar" class="navbar">
      <ul>
        <li><a class="nav-link scrollto active" href="#hero">Inicio</a></li>
        <li><a class="nav-link scrollto" href="#modules">Módulos</a></li>
        <li><a class="nav-link scrollto" href="#why-us">Soluciones</a></li>
        <li><a class="nav-link scrollto" href="#about">Nosotros</a></li>
        <li><a class="nav-link scrollto" href="#contact">Contacto</a></li>
        @guest
          <li class="dropdown">
              <a href="{{ route('login') }}"><i class="fas fa-right-to-bracket me-1 text-success"></i> Log in</a>
          </li>
        @else
          <li class="dropdown">
              <a href="#" class="d-flex align-items-center gap-1">
                  <span class="badge bg-success text-white me-1" style="font-size: 11px;">{{ Auth::user()->primary_role }}</span>
                  <span>{{ Str::limit(Auth::user()->full_name, 15) }}</span>
                  <i class="bi bi-chevron-down"></i>
              </a>
              <ul>
                  <li><a href="{{ url('/') }}"><i class="fas fa-compass me-1 text-success"></i> Módulo Dirección</a></li>
                  <li><a href="{{ url('/dashboard') }}"><i class="fas fa-chart-pie me-1 text-primary"></i> Dashboard</a></li>
                  <li><a href="{{ route('logout') }}" class="text-danger" onclick="event.preventDefault();document.getElementById('welcome-logout-form').submit();"><i class="fas fa-sign-out-alt me-1"></i> Cerrar Sesión</a><form id="welcome-logout-form" action="{{ route('logout') }}" method="POST" style="display:none">@csrf</form></li>
              </ul>
          </li>
        @endguest
          
              <!-- languaje Dropdown Menu-->
        <li class="dropdown">
          <a class="nav-link scrollto" data-toggle="dropdown" href="#">
             <i class="fas fa-globe"></i>
          </a>
          <ul>
            <li><a href="https://sicefa.com.co/lang/es" class="dropdown-item scrollto">Español</a></li>
            <li><a href="https://sicefa.com.co/lang/en" class="dropdown-item scrollto">English</a></li>
          </ul>
        </li>

      </ul>

    </nav><!-- .navbar -->

  </div>

</header><!-- End Header -->

    <!-- ======= Hero Section ======= -->
  <section id="hero" class="d-flex align-items-center position-relative overflow-hidden py-5">
    <!-- Background Video Ambient Layer -->
    <video class="hero-video-bg" autoplay muted loop playsinline poster="{{ asset('general/assets/img/cefaempresa.png') }}">
      <source src="{{ asset('video/banner.mp4') }}" type="video/mp4">
    </video>

    <!-- Overlay Gradient -->
    <div class="hero-overlay"></div>

    <!-- Content Container -->
    <div class="container position-relative hero-content py-5">
      <div class="row align-items-center">
        <!-- Left Column: Title, Info, Actions -->
        <div class="col-lg-6 d-flex flex-column justify-content-center text-start" data-aos="fade-up" data-aos-delay="200">
          
          <div class="mb-3">
            <span class="badge bg-white bg-opacity-25 text-white px-3 py-2 rounded-pill fw-bold shadow-sm d-inline-flex align-items-center gap-2" style="border: 1px solid rgba(255,255,255,0.4); backdrop-filter: blur(8px);">
              <img src="{{ asset('general/assets/img/cefaempresa.png') }}" alt="Logo Mega Uni Store" style="height: 24px; width: 24px; object-fit: contain; background: white; border-radius: 50%; padding: 2px;">
              <span>Tienda universitaria online • Mega Uni Store</span>
            </span>
          </div>

          <h1 class="text-white fw-bold display-4 mb-2" style="text-shadow: 0 4px 15px rgba(0,0,0,0.6); font-weight: 800;">
            Bienvenido a <br><span style="color: #7FB2DE; text-shadow: 0 0 20px rgba(122, 170, 214, 0.5);">Mega Uni Store</span>
          </h1>

          <h2 class="text-white-50 fs-5 mb-3" style="text-shadow: 0 2px 4px rgba(0,0,0,0.5);">
            <i class="fas fa-store text-warning me-1"></i> Tu tienda universitaria — compra fácil, rápido y seguro
          </h2>

          <p class="text-white fs-6 mb-4 opacity-90 leading-relaxed" style="max-width: 580px; text-shadow: 0 2px 8px rgba(0,0,0,0.7);">
            En Mega Uni Store encuentras todo lo que necesitas en un solo lugar: explora el catálogo, arma tu carrito y compra de forma fácil y segura. Tu tienda, siempre disponible.
          </p>

          <!-- Social Links -->
          <div class="d-flex align-items-center gap-2 mb-4">
            <span class="text-white-50 fs-7 me-2">Síguenos:</span>
            <a href="#" target="_blank" class="hero-social-btn" title="Instagram Mega Uni Store"><i class="fab fa-instagram"></i></a>
            <a href="#" target="_blank" class="hero-social-btn" title="Facebook Mega Uni Store"><i class="fab fa-facebook-f"></i></a>
            <a href="#" target="_blank" class="hero-social-btn" title="YouTube CEFA"><i class="fab fa-youtube"></i></a>
            <a href="#" target="_blank" class="hero-social-btn" title="TikTok Mega Uni Store"><i class="fab fa-tiktok"></i></a>
          </div>

          <!-- Buttons -->
          <div class="d-flex flex-wrap gap-3">
            <a href="#modules" class="btn btn-success text-white px-4 py-3 rounded-pill shadow-lg d-inline-flex align-items-center gap-2 fw-bold" style="background-color: #2E6EA8; border: none;">
              <i class="bi bi-grid-3x3-gap-fill fs-5"></i> Ver catálogo
            </a>
            <a href="{{ asset('video/banner.mp4') }}" class="btn btn-outline-light px-4 py-3 rounded-pill shadow-sm glightbox d-inline-flex align-items-center gap-2 fw-semibold">
              <i class="bi bi-play-circle-fill fs-5 text-warning"></i> Pantalla Completa
            </a>
          </div>

        </div>

        <!-- Right Column: Interactive Video Showcase Player -->
        <div class="col-lg-6 mt-4 mt-lg-0 text-center" data-aos="zoom-in" data-aos-delay="200">
          <div class="hero-video-showcase rounded-4 overflow-hidden position-relative">
            <video class="w-100 h-100 rounded-4" style="object-fit: cover; min-height: 320px; max-height: 400px; display: block;" autoplay muted loop playsinline controls poster="{{ asset('general/assets/img/cefaempresa.png') }}">
              <source src="{{ asset('video/banner.mp4') }}" type="video/mp4">
              Tu navegador no soporta la reproducción de vídeo.
            </video>
            <div class="position-absolute top-0 start-0 m-3 px-3 py-1 bg-dark bg-opacity-75 text-white rounded-pill fs-7 border border-secondary shadow-sm">
              <i class="bi bi-camera-reels-fill text-success me-1"></i> Conoce Mega Uni Store
            </div>
          </div>
        </div>

      </div>
    </div>
  </section><!-- End Hero -->
<main id="main">

    <!-- ======= Cliens Section ======= -->
    <section id="clients" class="cliens section-bg">
      <div class="container">

        <div class="row" data-aos="zoom-in">

          <div class="col-lg-3 col-md-6 col-6 d-flex align-items-center justify-content-center">
            <a href="http://oferta.senasofiaplus.edu.co/sofia-oferta/"><img src="https://sicefa.com.co/general/assets/img/clients/client-1.png" class="img-fluid"></a>
          </div>

          <div class="col-lg-2 col-md-6 col-6 d-flex align-items-center justify-content-center">
            <a href="https://sena.territorio.la/index.php?login=true#"><img src="https://sicefa.com.co/general/assets/img/clients/client-2.png" class="img-fluid"></a>
          </div>

          <div class="col-lg-2 col-md-6 col-6 d-flex align-items-center justify-content-center">
            <a href="https://biblioteca.sena.edu.co"><img src="https://sicefa.com.co/general/assets/img/clients/client-3.png" class="img-fluid"></a>
          </div>

          <div class="col-lg-3 col-md-6 col-6 d-flex align-items-center justify-content-center">
            <a href="https://ape.sena.edu.co/Paginas/Inicio.aspx"><img src="https://sicefa.com.co/general/assets/img/clients/client-4.png" class="img-fluid"></a>
          </div>  

          <div class="col-lg-2 col-md-6 col-6 d-flex align-items-center justify-content-center">
            <a href="https://ape.sena.edu.co/Paginas/Inicio.aspx"><img src="https://sicefa.com.co/general/assets/img/clients/client-5.png" class="img-fluid"></a>
          </div>

          

        </div>

      </div>
    </section><!-- End Cliens Section -->

    <!-- ======= Portfolio Section ======= -->
    <!-- ======= Noticias Mega Uni Store Section ======= -->
    <section id="noticias" class="news py-5" style="background-color: #f8f9fa;">
      <div class="container" data-aos="fade-up">

        <div class="section-title text-center mb-5">
          <span class="text-uppercase fw-bold text-success" style="color: #2E6EA8 !important; letter-spacing: 1.5px;">Actualidad y Novedades</span>
          <h2 class="fw-bold text-dark fs-2">Noticias Mega Uni Store</h2>
          <p class="text-muted">Infórmate sobre los avances, actividades y convocatorias de la empresa didáctica en Mega Uni Store</p>
        </div>

        <div class="row g-4">

          <!-- Noticia 1 -->
          <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
            <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden transition-all hover-lift">
              <div class="position-relative overflow-hidden" style="height: 210px; background: #142033;">
                <img src="{{ asset('general/assets/img/cefaempresa.png') }}" class="w-100 h-100 p-4" style="object-fit: contain; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.3));" alt="Noticia ERP">
                <span class="position-absolute top-0 end-0 m-3 badge text-white px-3 py-2 rounded-pill fs-7" style="background-color: #2E6EA8 !important;">
                  <i class="fas fa-laptop-code me-1"></i> Tecnología ERP
                </span>
              </div>
              <div class="card-body p-4 d-flex flex-column">
                <div class="text-muted fs-7 mb-2">
                  <i class="far fa-calendar-alt me-1 text-success"></i> 05 de Agosto, 2026
                </div>
                <h5 class="card-title fw-bold text-dark mb-3 fs-5">
                  Lanzamiento de la Plataforma Integrada ERP Mega Uni Store
                </h5>
                <p class="card-text text-muted fs-6 mb-4 flex-grow-1">
                  Se inicia la operación del nuevo sistema ERP centralizado para la administración de procesos estratégicos, misionales de inventario y ventas, y apoyo contable en el centro Mega Uni Store.
                </p>
                <div class="pt-3 border-top d-flex align-items-center justify-content-between">
                  <span class="fs-7 text-muted fw-semibold">Centro Mega Uni Store</span>
                  <a href="#noticias" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold">
                    Leer más <i class="fas fa-arrow-right ms-1"></i>
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- Noticia 2 -->
          <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
            <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden transition-all hover-lift">
              <div class="position-relative overflow-hidden" style="height: 210px; background: linear-gradient(135deg, #2E6EA8, #142033);">
                <div class="d-flex align-items-center justify-content-center h-100 text-white p-4 text-center">
                  <div>
                    <i class="fas fa-users-cog display-4 mb-2 opacity-75"></i>
                    <h6 class="text-white-50 mb-0">Etapa Lectiva y Productiva</h6>
                  </div>
                </div>
                <span class="position-absolute top-0 end-0 m-3 badge text-white px-3 py-2 rounded-pill fs-7" style="background-color: #142033 !important;">
                  <i class="fas fa-user-graduate me-1"></i> Convocatoria
                </span>
              </div>
              <div class="card-body p-4 d-flex flex-column">
                <div class="text-muted fs-7 mb-2">
                  <i class="far fa-calendar-alt me-1 text-success"></i> 02 de Agosto, 2026
                </div>
                <h5 class="card-title fw-bold text-dark mb-3 fs-5">
                  Abierta Convocatoria para Turnos de Aprendices en Mega Uni Store
                </h5>
                <p class="card-text text-muted fs-6 mb-4 flex-grow-1">
                  Aprendices de las áreas agrícola, pecuaria y agroindustrial se vinculan a la rotación de turnos rutinarios y especiales en las gerencias de producción y comercial.
                </p>
                <div class="pt-3 border-top d-flex align-items-center justify-content-between">
                  <span class="fs-7 text-muted fw-semibold">Talento Humano</span>
                  <a href="#noticias" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold">
                    Leer más <i class="fas fa-arrow-right ms-1"></i>
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- Noticia 3 -->
          <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
            <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden transition-all hover-lift">
              <div class="position-relative overflow-hidden" style="height: 210px; background: linear-gradient(135deg, #142033, #20c997);">
                <div class="d-flex align-items-center justify-content-center h-100 text-white p-4 text-center">
                  <div>
                    <i class="fas fa-seedling display-4 mb-2 opacity-75"></i>
                    <h6 class="text-white-50 mb-0">Producción Agroindustrial</h6>
                  </div>
                </div>
                <span class="position-absolute top-0 end-0 m-3 badge bg-warning text-dark px-3 py-2 rounded-pill fs-7">
                  <i class="fas fa-chart-line me-1"></i> Resultados
                </span>
              </div>
              <div class="card-body p-4 d-flex flex-column">
                <div class="text-muted fs-7 mb-2">
                  <i class="far fa-calendar-alt me-1 text-success"></i> 28 de Julio, 2026
                </div>
                <h5 class="card-title fw-bold text-dark mb-3 fs-5">
                  Balance Positivo en la Comercialización de Productos del Centro
                </h5>
                <p class="card-text text-muted fs-6 mb-4 flex-grow-1">
                  Se destacan excelentes indicadores de ventas en los derivados lácteos, cárnicos, productos de panadería y café producidos por los aprendices en las unidades.
                </p>
                <div class="pt-3 border-top d-flex align-items-center justify-content-between">
                  <span class="fs-7 text-muted fw-semibold">Gerencia Comercial</span>
                  <a href="#noticias" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold">
                    Leer más <i class="fas fa-arrow-right ms-1"></i>
                  </a>
                </div>
              </div>
            </div>
          </div>

        </div>

      </div>
    </section><!-- End Noticias Mega Uni Store Section -->

    <!-- ======= Organigrama Section ======= -->
    <section id="organigrama" class="portfolio section-bg py-5">
        <div class="container" data-aos="fade-up">

            <div class="section-title text-center mb-4">
                <span class="text-uppercase fw-bold text-success" style="color: #2E6EA8 !important; letter-spacing: 1.5px;">Estructura Organizacional</span>
                <h2 class="fw-bold text-dark fs-2">Organigrama Mega Uni Store</h2>
                <p class="text-muted">Tu tienda universitaria • Colombia</p>
            </div>

            <!-- Organigrama Diagram Showcase Card -->
            <div class="organigrama-card p-3 p-md-4 bg-white rounded-4 shadow-lg border border-1 border-light position-relative overflow-hidden" data-aos="zoom-in" data-aos-delay="150">
                <div class="text-center mb-3">
                    <a href="{{ asset('general/assets/img/organigrama.png') }}" class="glightbox" title="Organigrama Mega Uni Store Mega Uni Store">
                        <img src="{{ asset('general/assets/img/organigrama.png') }}" class="img-fluid rounded-3 shadow-sm hover-zoom" alt="Organigrama Mega Uni Store Mega Uni Store" style="max-height: 650px; width: auto; object-fit: contain;">
                    </a>
                </div>
                <div class="d-flex flex-wrap align-items-center justify-content-between pt-3 border-top px-2">
                    <div class="d-flex align-items-center me-3 mb-2 mb-md-0">
                        <img src="{{ asset('general/assets/img/cefaempresa.png') }}" alt="Logo Mega Uni Store" style="height: 32px; width: 32px; object-fit: contain; margin-right: 10px;">
                        <span class="fs-7 text-muted fw-semibold">Estructura de Funcionarios y Aprendices (Etapa Lectiva y Productiva)</span>
                    </div>
                    <a href="{{ asset('general/assets/img/organigrama.png') }}" class="btn btn-sm text-white rounded-pill px-4 fw-semibold glightbox" style="background-color: #2E6EA8;">
                        <i class="fas fa-search-plus me-1"></i> Ampliar Organigrama
                    </a>
                </div>
            </div>

        </div>
    </section><!-- End Organigrama Section -->
    
    <!-- ======= Visítanos / Puertas Abiertas Section ======= -->
    <section id="visitas" class="py-5" style="background: linear-gradient(135deg, #f0f7f2 0%, #ffffff 100%); border-top: 1px solid #e2ece5; border-bottom: 1px solid #e2ece5;">
      <div class="container" data-aos="fade-up">

        <div class="section-title text-center mb-5">
          <span class="text-uppercase fw-bold text-success" style="color: #2E6EA8 !important; letter-spacing: 1.5px;">Puertas Abiertas • Experiencia Didáctica</span>
          <h2 class="fw-bold text-dark fs-2">¡Visita el Centro Mega Uni Store y Mega Uni Store!</h2>
          <p class="text-muted fs-6" style="max-width: 750px; margin: 0 auto;">
            Invitamos a colegios, escuelas, universidades, grupos de investigación, empresarios y a la comunidad en general a conocer nuestro modelo de formación profesional y unidades de producción.
          </p>
        </div>

        <div class="row g-4 mb-5">

          <!-- Card 1: Colegios e Instituciones Educativas -->
          <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
            <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center bg-white transition-all hover-lift">
              <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 70px; height: 70px; background-color: rgba(46, 110, 168, 0.12); color: #2E6EA8;">
                <i class="fas fa-school fs-2"></i>
              </div>
              <h4 class="fw-bold text-dark mb-3 fs-5">Colegios e Instituciones</h4>
              <p class="text-muted fs-6 mb-3">
                Recorridos guiados pedagógicos para estudiantes de básica y media. Orientación vocacional en agricultura, pecuaria, agroindustria y tecnología.
              </p>
              <ul class="text-start text-muted fs-7 list-unstyled mb-0 border-top pt-3">
                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Recorrido por unidades formativas</li>
                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Demostración del modelo didáctico</li>
                <li><i class="fas fa-check-circle text-success me-2"></i> Charlas de orientación técnica</li>
              </ul>
            </div>
          </div>

          <!-- Card 2: Universidades e Investigadores -->
          <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
            <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center bg-white transition-all hover-lift">
              <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 70px; height: 70px; background-color: rgba(0, 50, 77, 0.12); color: #142033;">
                <i class="fas fa-university fs-2"></i>
              </div>
              <h4 class="fw-bold text-dark mb-3 fs-5">Universidades y Académicos</h4>
              <p class="text-muted fs-6 mb-3">
                Pasantías, proyectos de investigación aplicada e intercambio de conocimientos con nuestros programas de educación superior y fábrica de software.
              </p>
              <ul class="text-start text-muted fs-7 list-unstyled mb-0 border-top pt-3">
                <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i> Transferencia tecnológica y ERP</li>
                <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i> Enfoque en innovación y prototipado</li>
                <li><i class="fas fa-check-circle text-primary me-2"></i> Alianzas de investigación aplicada</li>
              </ul>
            </div>
          </div>

          <!-- Card 3: Comunidad y Emprendedores -->
          <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
            <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center bg-white transition-all hover-lift">
              <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 70px; height: 70px; background-color: rgba(245, 124, 0, 0.12); color: #f57c00;">
                <i class="fas fa-users-viewfinder fs-2"></i>
              </div>
              <h4 class="fw-bold text-dark mb-3 fs-5">Comunidad y Emprendedores</h4>
              <p class="text-muted fs-6 mb-3">
                Conoce la oferta de productos lácteos, cárnicos, café y panadería en el punto de venta de Mega Uni Store y participa en muestras de emprendimiento.
              </p>
              <ul class="text-start text-muted fs-7 list-unstyled mb-0 border-top pt-3">
                <li class="mb-2"><i class="fas fa-check-circle me-2" style="color: #f57c00;"></i> Adquisición de productos del centro</li>
                <li class="mb-2"><i class="fas fa-check-circle me-2" style="color: #f57c00;"></i> Ferias de emprendimiento rural</li>
                <li><i class="fas fa-check-circle me-2" style="color: #f57c00;"></i> Interacción con aprendices en turnos</li>
              </ul>
            </div>
          </div>

        </div>

        <!-- Banner Call-to-Action for Visit Booking -->
        <div class="card border-0 rounded-4 overflow-hidden shadow-lg text-white" style="background: linear-gradient(135deg, #0A1119 0%, #142033 60%, #2E6EA8 100%);">
          <div class="card-body p-4 p-md-5">
            <div class="row align-items-center">
              <div class="col-lg-8 mb-4 mb-lg-0">
                <span class="badge text-dark px-3 py-2 rounded-pill fs-7 mb-3 fw-bold" style="background-color: #7FB2DE !important;">
                  <i class="fas fa-calendar-alt me-1"></i> Agende su Visita Guiada
                </span>
                <h3 class="fw-bold fs-2 text-white mb-2">¿Deseas programar una visita pedagógica?</h3>
                <p class="text-white-50 fs-6 mb-0">
                  Escríbenos a través del formulario de contacto para definir la agenda, fecha y número de asistentes de tu delegación educativa o empresarial.
                </p>
              </div>
              <div class="col-lg-4 text-lg-end">
                <a href="#contact" class="btn text-white btn-lg rounded-pill px-4 py-3 fw-bold shadow-lg border-0 hover-lift" style="background-color: #2E6EA8;">
                  <i class="fas fa-paper-plane me-2"></i> Agendar Visita
                </a>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section><!-- End Visítanos Section -->
    
    
    
    <!-- ======= Services / Aplicativos Section ======= -->
    <section id="modules" class="services section-bg py-5">
      <div class="container" data-aos="fade-up">

        <div class="section-title text-center mb-5">
          <h2 style="color: #2E6EA8 !important;">Arquitectura de Módulos</h2>
          <p>Módulos de gestión organizados por categorías institucionales para Mega Uni Store</p>
        </div>

        @if(isset($bloques) && count($bloques) > 0)
          @foreach($bloques as $bloque)
            <!-- BLOQUE: {{ strtoupper($bloque->name) }} -->
            <div id="modules-{{ $bloque->slug }}" class="process-category-block mb-5 p-4 rounded-4 shadow-sm bg-white border-start border-5" style="border-left-color: {{ $bloque->color ?? '#2E6EA8' }} !important;" data-aos="fade-up">
              <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                <div class="process-icon-box me-3 p-3 rounded-circle" style="background-color: {{ $bloque->color ?? '#2E6EA8' }}1f; color: {{ $bloque->color ?? '#2E6EA8' }};">
                  <i class="{{ $bloque->icon ?? 'fas fa-cubes' }} fs-2"></i>
                </div>
                <div>
                  <h3 class="mb-1 fs-4 fw-bold text-dark">{{ $bloque->name }}</h3>
                  <p class="mb-0 text-muted fs-6">{{ $bloque->description }}</p>
                </div>
              </div>

              <div class="row g-4">
                @forelse($bloque->apps as $app)
                  <div class="col-xl-{{ count($bloque->apps) <= 2 ? '6' : '4' }} col-md-6" data-aos="zoom-in">
                    <div class="icon-box h-100 p-4 rounded-3 border bg-light shadow-sm hover-lift">
                      <div class="d-flex align-items-center mb-3">
                        <div class="icon-circle p-3 rounded-3 me-3 text-white" style="background-color: {{ $app->color ?? $bloque->color ?? '#2E6EA8' }};">
                          <i class="{{ $app->icon ?? 'fas fa-cube' }} fs-4"></i>
                        </div>
                        <div>
                          <h4 class="mb-0 fs-5 fw-bold"><a href="{{ url($app->url) }}" class="text-dark">{{ $app->name }}</a></h4>
                          <span class="badge text-dark fs-7" style="background-color: {{ $bloque->color ?? '#2E6EA8' }}22; border: 1px solid {{ $bloque->color ?? '#2E6EA8' }}44;">{{ $bloque->name }}</span>
                        </div>
                      </div>
                      <p class="text-muted fs-6 mb-3">
                        {{ $app->description }}
                      </p>
                      <a href="{{ url($app->url) }}" class="btn btn-sm text-white rounded-pill px-3 fw-semibold shadow-sm" style="background-color: {{ $app->color ?? $bloque->color ?? '#2E6EA8' }};">
                        Acceder a {{ $app->name }} <i class="fas fa-arrow-right ms-1"></i>
                      </a>
                    </div>
                  </div>
                @empty
                  <div class="col-12">
                    <p class="text-muted fst-italic">No hay submódulos asignados a este proceso actualmente.</p>
                  </div>
                @endforelse
              </div>
            </div>
          @endforeach
        @endif

      </div>
    </section><!-- End Services Section -->
    

    <!--include('layouts/partials/skins')--->
    
    <!-- ======= sefa Section ======= -->
    <section id="cefa" class="cta">
      <div class="container" data-aos="zoom-in">

        <div class="row">
          <div class="col-lg-9 text-center text-lg-start">
            <h3>Conoce nuestro centro de formación</h3>
            <p>Realiza un paseo virtual por el centro de formación conoce las áreas de producción y todos los servicios a tu disposición</p>
          </div>
          <div class="col-lg-3 cta-btn-container text-center">
            <a class="cta-btn align-middle" href="https://sicefa.com.co/cefamaps/index">Ir a Cefamaps</a>
          </div>
        </div>

      </div>
    </section><!-- End sefa Section -->
    <!-- ======= Why Us Section ======= -->
<section id="why-us" class="why-us section-bg">
    <div class="container-fluid" data-aos="fade-up">

        <div class="row">

            <div class="col-lg-7 d-flex flex-column justify-content-center align-items-stretch  order-2 order-lg-1">

                <div class="content">
                    <h3>Mega Uni Store</h3>
                    <p>
                        Es el espacio ofrecido para formar profesionales integrales con capacidad de análisis y toma
                        dediciones en el entorno empresarial, mediante la innovación e investigación.
                    </p>
                </div>

                <div class="accordion-list">
                    <ul>
                        <li>
                            <a data-bs-toggle="collapse" class="collapse"
                                data-bs-target="#accordion-list-1"><span>01</span> ¿Que es? <i
                                    class="bx bx-chevron-down icon-show"></i><i
                                    class="bx bx-chevron-up icon-close"></i></a>
                            <div id="accordion-list-1" class="collapse show" data-bs-parent=".accordion-list">
                                <p>
                                    Mega Uni Store es un modelo didáctico de empresa, que busca impartir y trasmitir a el
                                    aprendiz los conocimientos administrativos, productivos, técnicos, financieros,
                                    ambiéntales, y de comercialización adquiridos en el proceso de formación por medio
                                    del manejo real de una empresa en las diferentes áreas y unidades productivas.
                                </p>
                            </div>
                        </li>

                        <li>
                            <a data-bs-toggle="collapse" data-bs-target="#accordion-list-2"
                                class="collapsed"><span>02</span> Msión <i
                                    class="bx bx-chevron-down icon-show"></i><i
                                    class="bx bx-chevron-up icon-close"></i></a>
                            <div id="accordion-list-2" class="collapse" data-bs-parent=".accordion-list">
                                <p>
                                    Fortalecer la formación por competencias desde las estrategias de formación por
                                    proyectos, impulsando el emprendimiento, la innovación, la investigación y el
                                    trabajo colaborativo, para tener un ambiente de aprendizaje integral en que los
                                    aprendices a través del aprender haciendo y el hacer transformado se integren con
                                    diferentes especialidades que lo lleven a adquirir todas las competencias adquiridas
                                    para la gestión empresarial de proyectos.
                                </p>
                            </div>
                        </li>

                        <li>
                            <a data-bs-toggle="collapse" data-bs-target="#accordion-list-3"
                                class="collapsed"><span>03</span> visión <i
                                    class="bx bx-chevron-down icon-show"></i><i
                                    class="bx bx-chevron-up icon-close"></i></a>
                            <div id="accordion-list-3" class="collapse" data-bs-parent=".accordion-list">
                                <p>
                                    Un modelo empresarial organizado mostrando resultados con proyectos
                                    formativos-productivos que lleven al aprendiz, al empresario y a cualquier ciudadano
                                    a implementar en su entorno proyectos empresariales enfocados al desarrollo de la
                                    región (nación, departamento, municipio) implementando tecnologías innovadoras que
                                    conduzcan a un desarrollo sostenible.
                                </p>
                            </div>
                        </li>

                        <li>
                            <a data-bs-toggle="collapse" data-bs-target="#accordion-list-4"
                                class="collapsed"><span>04</span> Modelo Mega Uni Store <i
                                    class="bx bx-chevron-down icon-show"></i><i
                                    class="bx bx-chevron-up icon-close"></i></a>
                            <div id="accordion-list-4" class="collapse" data-bs-parent=".accordion-list">
                                <p>
                                    La estrategia Mega Uni Store consta de Resultados de Aprendizaje en: Desarrollo de
                                    labores administrativas y de mercadeo en la empresa agropecuaria o agroindustrial.
                                    Operación del sistema productivo de tipo agropecuario o agroindustrial. Competencias
                                    comportamentales.
                                </p>
                            </div>
                        </li>

                    </ul>
                </div>

            </div>

            <div class="col-lg-5 d-flex align-items-center justify-content-center order-1 order-lg-2 py-4 py-lg-0" data-aos="zoom-in" data-aos-delay="150">
                <div class="position-relative p-2 bg-white rounded-4 shadow-lg border border-2 border-light overflow-hidden">
                    <img src="{{ asset('general/assets/img/sena-empresa-equipo.jpg') }}" alt="Equipo Mega Uni Store Mega Uni Store" class="img-fluid rounded-3 shadow-sm hover-zoom" style="max-height: 450px; width: 100%; object-fit: cover;">
                    <div class="position-absolute bottom-0 start-0 end-0 p-3 bg-dark bg-opacity-75 text-white m-3 rounded-3 backdrop-blur border border-secondary border-opacity-50">
                        <div class="d-flex align-items-center">
                            <img src="{{ asset('general/assets/img/cefaempresa.png') }}" alt="Logo Mega Uni Store" class="bg-white rounded-circle p-1 me-2" style="width: 32px; height: 32px; object-fit: contain;">
                            <div>
                                <span class="fw-bold fs-7 d-block text-white">Aprendices e Instructores Mega Uni Store</span>
                                <small class="text-white-50 fs-8">Mega Uni Store • Colombia</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section><!-- End Why Us Section -->

    <!-- ======= About Us Section ======= -->
<section id="about" class="about">
    <div class="container" data-aos="fade-up">

        <div class="section-title">
            <h2>Acerca de Nosotros</h2>
        </div>

        <div class="row content">
            <div class="col-lg-6">
                <h2>Mega Uni Store y su comunidad</h2>
                <p>
                    Mega Uni Store ofrece al centro de formación agroindustrial “Mega Uni Store”, un conjunto de aplicaciones web
                    2.0, que permiten gestionar eficientemente la información académica, técnica y administrativa de las
                    áreas productivas de la finca, así como también permite gestionar otras áreas que aportan
                    significativamente al control y buen manejo de los bienes y talento humano del CEFA.</p>

                <P>Mega Uni Store Nació como proyecto formativo del programa Tecnológico en Análisis y Desarrollo de sistemas de
                    Información (ADSI), el cual ha evolucionado en su concepción, funcionalidad y metodología de
                    desarrollo desde el primer curso que inicio el proyecto en 2009; Hoy podemos decir que tenemos una
                    herramienta madura en cuanto al conocimiento de las áreas implicadas y que cuenta con estandares de
                    calidad en el desarrollo usando herramientas actuales de gran acogida.
                </p>
            </div>
            <div class="col-lg-6 pt-4 pt-lg-0">
                <h2>Mega Uni Store y nuestra misión</h2>
                <p>
                    La estrategia Mega Uni Store "Emprecefa", vincula a los aprendices a una experiencia empresarial, en
                    la cual los involucrados adquieren capacidades, desestrezas y habilidades administrativas y
                    operativas para su futuro profesional. Son variados los procesos que se manejan en una empresa real,
                    por lo que se necesita un sistema de información completo tipo ERP (Enterprice Resource Planning ->
                    Planeación de recursos empresariales), que integre las operaciones administrativas y de producción
                    de bienes o servicios.
                </p>
                <p>
                    Mega Uni Store responde a dicha necesidad tecnologica y permite La planificación de los recursos de
                    Emprecefa, manejando la producción, logística, inventario y contabilidad de forma modular. Ademas,
                    Mega Uni Store tambien interviene en los procesos administrativos, academicos y tecnicos de las unidades
                    productivas del CEFA.
                </p>
            </div>
        </div>

    </div>
</section><!-- End About Us Section -->

    

    

    

   

    <!-- ======= Contact Section ======= -->
<section id="contact" class="contact">
    <div class="container" data-aos="fade-up">

        <div class="section-title">
            <h2>Contacto</h2>
            <p>En esta sección podrán depositar todas peticiones, quejas, reclamos y sugerencias relacionadas con el
                manejo de la página web y sus distintas aplicaciones.</p>
            <p>Los campos con un ( * ) son obligatorios</p>
        </div>

        <div class="row g-4">

            <div class="col-lg-5 d-flex align-items-stretch">
                <div class="info w-100">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <a href="#" target="_blank" class="d-flex align-items-center">
                                <i class="fab fa-facebook-f"></i>
                                <div>
                                    <h4>Facebook</h4>
                                    <p class="mb-0">Página oficial</p>
                                </div>
                            </a>
                        </div>

                        <div class="col-md-6 mb-3">
                            <a href="#" target="_blank" class="d-flex align-items-center">
                                <i class="fab fa-instagram"></i>
                                <div>
                                    <h4>Instagram</h4>
                                    <p class="mb-0">@megaunistore</p>
                                </div>
                            </a>
                        </div>

                        <div class="col-md-6 mb-3">
                            <a href="#" target="_blank" class="d-flex align-items-center">
                                <i class="fab fa-youtube"></i>
                                <div>
                                    <h4>YouTube</h4>
                                    <p class="mb-0">CEFASCHANNEL</p>
                                </div>
                            </a>
                        </div>

                        <div class="col-md-6 mb-3">
                            <a href="#" target="_blank" class="d-flex align-items-center">
                                <i class="fab fa-tiktok"></i>
                                <div>
                                    <h4>TikTok</h4>
                                    <p class="mb-0">@megaunistore_45</p>
                                </div>
                            </a>
                        </div>
                    </div>

                    <div class="mt-3 rounded-3 overflow-hidden shadow-sm">
                        <iframe
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3253.9238532120967!2d-75.36235506309659!3d2.613761562673928!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8e3b3f4b1c54ddc5%3A0x6a0d5a458d5d190d!2sCentro%20de%20Formaci%C3%B3n%20Agroindustrial%20La%20Angostura!5e1!3m2!1ses!2sco!4v1637598702785!5m2!1ses!2sco"
                            width="100%" height="320" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>

            </div>

            <div class="col-lg-7 mt-5 mt-lg-0 d-flex align-items-stretch">
                <form action="#" method="post" role="form" class="php-email-form">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="name">Nombre</label>
                            <input type="text" name="name" class="form-control" id="name" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="name">Apellido</label>
                            <input type="text" name="name" class="form-control" id="name" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="name">Correo</label>
                            <input type="Correo" class="form-control" name="email" id="email" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="name">Telefono</label>
                            <input type="telefono" class="form-control" name="email" id="email" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="name">Asunto *</label>
                        <input type="text" class="form-control" name="subject" id="subject" required>
                    </div>
                    <div class="form-group">
                        <label for="name">Tipo *</label>
                        <select name="" id="" class="form-control">
                            <option>...</option>
                            <option value="">Peticiones</option>
                            <option value="">Queja</option>
                            <option value="">Reclamo</option>
                            <option value="">Suerencias</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="name">Mensaje *</label>
                        <textarea class="form-control" name="message" rows="10" required></textarea>
                    </div>
                    <div class="my-3">
                        <div class="loading">Loading</div>
                        <div class="error-message"></div>
                        <div class="sent-message">Your message has been sent. Thank you!</div>
                    </div>
                    <div class="text-center"><button type="submit">Send Message</button></div>
                </form>
            </div>

        </div>

    </div>
</section><!-- End Contact Section -->

</main>

    <footer id="footer">
    <div class="footer-top">
        <div class="container">
            <div class="row g-4">

                <!-- Columna 1: Identidad Mega Uni Store -->
                <div class="col-lg-4 col-md-6 footer-info">
                    <div class="d-flex align-items-center mb-3">
                        <img src="{{ asset('general/assets/img/cefaempresa.png') }}" alt="Logo Mega Uni Store" class="me-3 rounded-circle bg-white p-2 shadow-sm" style="width: 55px; height: 55px; object-fit: contain;">
                        <div>
                            <h3 class="text-white fw-bold mb-0 fs-4">MEGA UNI STORE</h3>
                            <span class="fs-7 fw-semibold" style="color: #7FB2DE !important;">Plataforma ERP Integrada</span>
                        </div>
                    </div>
                    <p class="fs-6 opacity-90 mb-3" style="color: rgba(255,255,255,0.82);">
                        Modelo didáctico de formación profesional para la vivencia real del entorno empresarial en el comunidad universitaria <strong>"Mega Uni Store"</strong> (Colombia).
                    </p>
                    <div class="d-flex align-items-center mt-3">
                        <a href="#" target="_blank" class="footer-social-btn" title="Instagram Mega Uni Store"><i class="fab fa-instagram"></i></a>
                        <a href="#" target="_blank" class="footer-social-btn" title="Facebook Mega Uni Store"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" target="_blank" class="footer-social-btn" title="YouTube CEFA"><i class="fab fa-youtube"></i></a>
                        <a href="#" target="_blank" class="footer-social-btn" title="TikTok Mega Uni Store"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>

                <!-- Columna 2: Módulos -->
                <div class="col-lg-3 col-md-6 footer-links">
                    <h4>Módulos</h4>
                    <ul>
                        <li><i class="fas fa-chevron-right"></i> <a href="#modules-estrategicos">Procesos Estratégicos</a></li>
                        <li><i class="fas fa-chevron-right"></i> <a href="#modules-misionales">Procesos Misionales</a></li>
                        <li><i class="fas fa-chevron-right"></i> <a href="#modules-apoyo">Procesos de Apoyo</a></li>
                        <li><i class="fas fa-chevron-right"></i> <a href="#organigrama">Organigrama Mega Uni Store</a></li>
                        <li><i class="fas fa-chevron-right"></i> <a href="#cefa">CEFAMAPS (Zonificación)</a></li>
                    </ul>
                </div>

                <!-- Columna 3: Enlaces de Interés -->
                <div class="col-lg-2 col-md-6 footer-links">
                    <h4>Enlaces Útiles</h4>
                    <ul>
                        <li><i class="fas fa-chevron-right"></i> <a href="https://gestorasig.wixsite.com/megaunistore" target="_blank">Portal Oficial Wix</a></li>
                        <li><i class="fas fa-chevron-right"></i> <a href="https://oferta.senasofiaplus.edu.co/sofia-oferta/" target="_blank">Ayuda y soporte</a></li>
                        <li><i class="fas fa-chevron-right"></i> <a href="https://ape.sena.edu.co" target="_blank">Agencia de Empleo</a></li>
                        <li><i class="fas fa-chevron-right"></i> <a href="https://centroagroindustrial.blogspot.com" target="_blank">Blog CEFA</a></li>
                        <li><i class="fas fa-chevron-right"></i> <a href="#why-us">Acerca de Mega Uni Store</a></li>
                    </ul>
                </div>

                <!-- Columna 4: Contacto Institucional -->
                <div class="col-lg-3 col-md-6 footer-contact">
                    <h4>Contacto CEFA</h4>
                    <p style="color: rgba(255,255,255,0.85);">
                        <i class="fas fa-map-marker-alt me-2" style="color: #7FB2DE !important;"></i> Km 38 Vía al Sur, Neiva<br>
                        Colombia<br><br>
                        <i class="fas fa-building me-2" style="color: #7FB2DE !important;"></i> comunidad universitaria<br><br>
                        <i class="fas fa-envelope me-2" style="color: #7FB2DE !important;"></i> Mega Uni Store Mega Uni Store
                    </p>
                </div>

            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="container d-md-flex py-3 justify-content-between align-items-center text-center text-md-start">
            <div class="copyright text-white-50">
                &copy; {{ date('Y') }} <strong><span class="text-white">Mega Uni Store</span></strong> • comunidad universitaria. Todos los derechos reservados.
            </div>
            <div class="credits text-white-50 mt-2 mt-md-0">
                Plataforma ERP Integrada Mega Uni Store
            </div>
        </div>
    </div>
</footer> <!-- End Footer -->

    <!-- Vendor JS Files -->
  <script src="https://sicefa.com.co/general/assets/vendor/aos/aos.js"></script>
  <script src="https://sicefa.com.co/general/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="https://sicefa.com.co/general/assets/vendor/glightbox/js/glightbox.min.js"></script>
  <script src="https://sicefa.com.co/general/assets/vendor/isotope-layout/isotope.pkgd.min.js"></script>
  <script src="https://sicefa.com.co/general/assets/vendor/php-email-form/validate.js"></script>
  <script src="https://sicefa.com.co/general/assets/vendor/swiper/swiper-bundle.min.js"></script>
  <script src="https://sicefa.com.co/general/assets/vendor/waypoints/noframework.waypoints.js"></script>

  <!-- Template Main JS File -->
  <script src="https://sicefa.com.co/general/assets/js/main.js"></script>
<!-- JQuery -->
  <script src="https://sicefa.com.co/AdminLTE/plugins/jquery/jquery.min.js"></script>
    <!-- ======= Asistente Virtual Mega Uni Store Bot Widget ======= -->
    <div id="senaBotTrigger" class="sena-bot-trigger" title="Asistente Virtual Mega Uni Store">
        <div class="position-relative d-flex align-items-center justify-content-center w-100 h-100">
            <img src="{{ asset('general/assets/img/cefaempresa.png') }}" alt="Asistente Mega Uni Store" class="rounded-circle bg-white p-1 shadow-sm" style="width: 48px; height: 48px; object-fit: contain;">
            <div class="sena-bot-chat-icon-badge" title="Soporte Mega Uni Store Bot">
                <i class="fas fa-headset" style="font-size: 10px;"></i>
            </div>
        </div>
        <span class="sena-bot-badge"></span>
    </div>

    <div id="senaBotChat" class="sena-bot-chat">
        <div class="sena-bot-header">
            <div class="d-flex align-items-center">
                <img src="{{ asset('general/assets/img/cefaempresa.png') }}" class="rounded-circle bg-white p-1 me-2" style="width: 38px; height: 38px; object-fit: contain;">
                <div>
                    <h6 class="mb-0 text-white fw-bold fs-6">Mega Uni Store Bot</h6>
                    <span class="fs-7 text-success" style="color: #7FB2DE !important;"><i class="fas fa-circle me-1" style="font-size: 8px;"></i> Asistente En Línea</span>
                </div>
            </div>
            <button id="senaBotClose" class="btn text-white p-0 border-0 fs-5 opacity-75">&times;</button>
        </div>

        <div id="senaBotBody" class="sena-bot-body">
            <div class="bot-msg">
                ¡Hola! 👋 Soy <strong>Mega Uni Store Bot</strong>, tu asistente virtual para la plataforma ERP de Mega Uni Store Mega Uni Store. <br><br>
                ¿En qué puedo ayudarte hoy? Selecciona una opción o escríbeme:
                <div class="mt-2 d-flex flex-wrap gap-1">
                    <span class="sena-bot-chip" onclick="askSenaBot('¿Qué es Mega Uni Store?')">🏢 ¿Qué es Mega Uni Store?</span>
                    <span class="sena-bot-chip" onclick="askSenaBot('Ver Módulos')">📊 Módulos</span>
                    <span class="sena-bot-chip" onclick="askSenaBot('Ver Organigrama')">🗺️ Organigrama</span>
                    <span class="sena-bot-chip" onclick="askSenaBot('Ver Noticias')">📰 Noticias</span>
                    <span class="sena-bot-chip" onclick="askSenaBot('Contacto')">📞 Contacto</span>
                </div>
            </div>
        </div>

        <div class="sena-bot-footer">
            <input type="text" id="senaBotInput" class="sena-bot-input" placeholder="Escribe tu consulta..." onkeypress="if(event.key==='Enter') sendSenaBotMessage()">
            <button onclick="sendSenaBotMessage()" class="btn text-white rounded-circle p-0 d-flex align-items-center justify-content-center" style="background-color: #2E6EA8; width: 38px; height: 38px;">
                <i class="fas fa-paper-plane fs-7"></i>
            </button>
        </div>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const trigger = document.getElementById('senaBotTrigger');
        const chat = document.getElementById('senaBotChat');
        const closeBtn = document.getElementById('senaBotClose');
        const input = document.getElementById('senaBotInput');
        const body = document.getElementById('senaBotBody');

        if (!trigger || !chat) return;

        trigger.addEventListener('click', function() {
          chat.classList.toggle('active');
        });

        closeBtn.addEventListener('click', function() {
          chat.classList.remove('active');
        });

        window.askSenaBot = function(text) {
          input.value = text;
          sendSenaBotMessage();
        };

        window.sendSenaBotMessage = function() {
          const text = input.value.trim();
          if (!text) return;

          // Append User Message
          const userDiv = document.createElement('div');
          userDiv.className = 'user-msg';
          userDiv.textContent = text;
          body.appendChild(userDiv);
          input.value = '';
          body.scrollTop = body.scrollHeight;

          // Generate Bot Response
          setTimeout(function() {
            const botDiv = document.createElement('div');
            botDiv.className = 'bot-msg';
            
            const lower = text.toLowerCase();
            let response = '';

            if (lower.includes('qué es') || lower.includes('que es') || lower.includes('sena empresa')) {
              response = '🏢 <strong>Mega Uni Store</strong> es un modelo didáctico de formación profesional en el Centro Agroindustrial "Mega Uni Store" (Colombia). Permite a los aprendices vivir la operación real de una empresa en áreas administrativas, productivas y comerciales.';
            } else if (lower.includes('proceso') || lower.includes('erp') || lower.includes('modulo') || lower.includes('módulo')) {
              response = '📊 <strong>Módulos Mega Uni Store:</strong><br>• 🟢 <strong>Estratégicos:</strong> Planeación e Indicadores.<br>• 🔵 <strong>Misionales:</strong> Inventario, Ventas y Compras.<br>• 🟠 <strong>Apoyo:</strong> Contabilidad y Talento Humano.<br><br><a href="#modules" onclick="document.getElementById(\'senaBotChat\').classList.remove(\'active\')">Ir a Módulos &rarr;</a>';
            } else if (lower.includes('organigrama') || lower.includes('estructura')) {
              response = '🗺️ El <strong>Organigrama de Mega Uni Store</strong> muestra la jerarquía desde la Subdirección y Coordinación hasta los Gerentes (Administrativo, Producción, Comercial), Gestores y Aprendices.<br><br><a href="#organigrama" onclick="document.getElementById(\'senaBotChat\').classList.remove(\'active\')">Ver Organigrama Completo &rarr;</a>';
            } else if (lower.includes('noticia') || lower.includes('novedad')) {
              response = '📰 Entérate de las últimas novedades sobre la plataforma ERP, convocatorias de turnos para aprendices y balance comercial.<br><br><a href="#noticias" onclick="document.getElementById(\'senaBotChat\').classList.remove(\'active\')">Ver Noticias &rarr;</a>';
            } else if (lower.includes('contacto') || lower.includes('telefono') || lower.includes('ubicacion')) {
              response = '📍 <strong>Mega Uni Store:</strong> tu tienda universitaria online, disponible 24/7.<br>📱 Síguenos en nuestras redes sociales.';
            } else {
              response = '🤖 Gracias por tu consulta. Puedes explorar los procesos ERP, consultar el organigrama o ver las noticias más recientes.<br><br>¿Te gustaría revisar los <a href="#modules" onclick="document.getElementById(\'senaBotChat\').classList.remove(\'active\')">Módulos ERP</a>?';
            }

            botDiv.innerHTML = response;
            body.appendChild(botDiv);
            body.scrollTop = body.scrollHeight;
          }, 500);
        };
      });
    </script>

    <!-- Submodule Explorer Modal -->
    <div class="modal fade" id="moduleModal" tabindex="-1" aria-labelledby="moduleModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
          <div class="modal-header text-white" id="moduleModalHeader" style="background: linear-gradient(135deg, #0A1119, #142033); border-bottom: 3px solid #2E6EA8;">
            <div class="d-flex align-items-center">
              <div id="moduleModalIcon" class="p-2 rounded-circle bg-white text-dark me-3 shadow-sm" style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-cubes fs-4 text-success"></i>
              </div>
              <div>
                <h5 class="modal-title fw-bold mb-0 text-white" id="moduleModalTitle">Submódulo ERP</h5>
                <span class="badge bg-success text-white fs-7" id="moduleModalCategory">Proceso Mega Uni Store</span>
              </div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-4">
            <p class="lead text-dark fs-6 mb-3" id="moduleModalDescription">Descripción del submódulo ERP.</p>
            
            <div class="card border-0 bg-light rounded-3 p-3 mb-3">
              <h6 class="fw-bold text-dark mb-2"><i class="fas fa-cogs text-primary me-2"></i> Funcionalidades Principales de la Arquitectura:</h6>
              <ul class="text-muted fs-6 mb-0 ps-3" id="moduleModalFeatures">
                <li>Gestión de registros y control en tiempo real.</li>
              </ul>
            </div>

            <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success d-flex align-items-center rounded-3 mb-0" role="alert">
              <i class="fas fa-info-circle me-2 fs-5"></i>
              <div class="fs-7">
                Este submódulo forma parte de la arquitectura modular ERP de Mega Uni Store Mega Uni Store. Requiere autenticación de usuario o aprendiz asignado al turno.
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light border-top">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
            <a href="/login" class="btn btn-success text-white rounded-pill px-4 fw-semibold" style="background-color: #2E6EA8; border: none;">
              <i class="fas fa-sign-in-alt me-1"></i> Iniciar Sesión para Acceder
            </a>
          </div>
        </div>
      </div>
    </div>

    <script>
      const moduleData = {
        planeacion: {
          title: "Submódulo de Planeación",
          category: "Procesos Estratégicos",
          icon: "fas fa-clipboard-list text-success",
          desc: "Herramienta de direccionamiento estratégico para la formulación de planes de acción anuales, metas organizacionales y distribución de objetivos en las unidades de producción de Mega Uni Store.",
          features: [
            "Formulación y aprobación de planes de acción por área.",
            "Asignación de presupuestos y metas periódicas.",
            "Seguimiento a planes de mejora e investigación aplicada.",
            "Consolidación de informes gerenciales para la Subdirección."
          ]
        },
        indicadores: {
          title: "Submódulo de Indicadores",
          category: "Procesos Estratégicos",
          icon: "fas fa-chart-line text-success",
          desc: "Tablero de control ejecutivo y evaluación continua de KPIs (Key Performance Indicators) empresariales, académicos y de rendimiento de Mega Uni Store.",
          features: [
            "Medición de efectividad en ventas y producción agroindustrial.",
            "Gráficos analíticos de rendimiento por unidad productiva.",
            "Monitoreo de cumplimiento de aprendices en turnos.",
            "Exportación de reportes ejecutivos en PDF y Excel."
          ]
        },
        inventario: {
          title: "Submódulo de Inventario",
          category: "Procesos Misionales",
          icon: "fas fa-boxes text-primary",
          desc: "Control automatizado de bodegas, existencias de insumos agrícolas, pecuarios y agroindustriales, materias primas y productos terminados.",
          features: [
            "Trazabilidad de entradas, salidas y transferencias entre bodegas.",
            "Alertas automáticas de nivel mínimo de stock.",
            "Kardex valorizado por método PEPS/Promedio Ponderado.",
            "Control de lotes y fechas de vencimiento de alimentos procesados."
          ]
        },
        ventas: {
          title: "Submódulo de Ventas",
          category: "Procesos Misionales",
          icon: "fas fa-cash-register text-primary",
          desc: "Gestión de comercialización y Puntos de Venta (POS) para los productos elaborados por los aprendices en el Centro Mega Uni Store (lácteos, cárnicos, café, panadería).",
          features: [
            "Terminal Punto de Venta (POS) rápido e intuitivo.",
            "Generación de comprobantes y recibos de venta.",
            "Cierre de caja diario y arqueos por turno de aprendiz.",
            "Integración en tiempo real con la reducción de inventario."
          ]
        },
        compras: {
          title: "Submódulo de Compras",
          category: "Procesos Misionales",
          icon: "fas fa-shopping-cart text-primary",
          desc: "Administración del ciclo de abastecimiento, solicitudes de requisición de insumos, cotizaciones de proveedores y órdenes de compra.",
          features: [
            "Registro y calificación de proveedores regionales.",
            "Gestión de órdenes de compra con flujo de aprobación.",
            "Recepción e inspección de materias primas en almacén.",
            "Integración automática con cuentas por pagar de Contabilidad."
          ]
        },
        contabilidad: {
          title: "Submódulo de Contabilidad",
          category: "Procesos de Apoyo",
          icon: "fas fa-file-invoice-dollar text-warning",
          desc: "Registro contable y financiero de todas las operaciones comerciales, comprobantes de ingreso, egreso, asientos contables y estados financieros.",
          features: [
            "Plan Único de Cuentas (PUC) adaptado a la empresa didáctica.",
            "Emisión de comprobantes diarios y libro mayor.",
            "Generación de Balance General y Estado de Resultados.",
            "Auditoría y control de costos de producción agroindustrial."
          ]
        },
        talento: {
          title: "Submódulo de Talento Humano",
          category: "Procesos de Apoyo",
          icon: "fas fa-users-cog text-warning",
          desc: "Gestión del capital humano, asignación y rotación de turnos de aprendices (rutinarios y especiales), instructores líderes y evaluación de competencias.",
          features: [
            "Programación de turnos operacionales por unidad de producción.",
            "Control biométrico / digital de asistencia de aprendices.",
            "Evaluación de desempeño y competencias comportamentales.",
            "Directorio de funcionarios, instructores y pasantes."
          ]
        }
      };

      window.openModuleModal = function(moduleKey) {
        const data = moduleData[moduleKey];
        if (!data) return;

        document.getElementById('moduleModalTitle').textContent = data.title;
        document.getElementById('moduleModalCategory').textContent = data.category;
        document.getElementById('moduleModalDescription').textContent = data.desc;
        
        const iconDiv = document.getElementById('moduleModalIcon');
        iconDiv.innerHTML = `<i class="${data.icon} fs-4"></i>`;

        const list = document.getElementById('moduleModalFeatures');
        list.innerHTML = '';
        data.features.forEach(f => {
          const li = document.createElement('li');
          li.className = 'mb-2';
          li.innerHTML = `<i class="fas fa-check text-success me-2 fs-7"></i> ${f}`;
          list.appendChild(li);
        });

        const modalEl = document.getElementById('moduleModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
      };
    </script>

    
<script>
(function(){
  var h=document.getElementById('header');
  function upd(){
    var hero=document.getElementById('hero');
    var trigger=hero?(hero.offsetHeight-90):500;
    if(window.scrollY>trigger){h.classList.add('reveal');}else{h.classList.remove('reveal');}
  }
  window.addEventListener('scroll',upd,{passive:true});
  window.addEventListener('resize',upd);
  document.addEventListener('DOMContentLoaded',upd); upd();
})();
</script>

@include('partials.page-transition')

</body>
</html>