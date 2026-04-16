<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    echo "<script>location.href='../../index';</script>";
    exit;
}
date_default_timezone_set("Asia/Kolkata");

/**
 * MASTER DB (app_master): users, clients, user_access, etc.
 * CLIENT DB (client-wise): module tables (opd/crm/payroll etc.)
 */

require_once '../db_conn.php';
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="E-Clinic Solutions by Abhitechbot offers advanced healthcare software services, including patient management systems, online appointment bookings, diagnostic tools, and more to streamline medical operations efficiently.">
    <meta name="keywords" content="E-Clinic Solutions, Abhitechbot, Healthcare Software, Patient Management System, Online Appointment Booking, Diagnostic Software, Clinic Management, Healthcare IT, Medical Software, Digital Healthcare">
    <meta name="author" content="Abhitechbot">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../opd/admin/images/favicon.ico">
    <title>Rhythm E-Clinic Solutions</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../opd/admin/css/vendors_css.css">
    <link rel="stylesheet" href="../opd/admin/css/style.css">
    <link rel="stylesheet" href="../opd/admin/css/header.css">
    <link rel="stylesheet" href="../opd/admin/css/skin_color.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <!-- JS -->
    <script src="../opd/admin/js/jquery-3.7.1.js"></script>
    <script src="../opd/admin/js/sweetalert.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Echarts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/6.0.0/echarts.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <!-- InputMask -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.9/jquery.inputmask.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="hold-transition light-skin sidebar-mini theme-success fixed dashboard-zoom" id="body">
<div class="wrapper">
    <!-- ===================== FULL HEADER (FIXED) ===================== -->
    <header class="main-header" style="box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);">
        <div class="d-flex align-items-center logo-box justify-content-start">
            <a href="index" class="logo">
                <div class="logo-lg">
                    <span class="light-logo">
                        <img src="../opd/images/logo-letter.png" alt="logo">
                    </span>
                    <span class="dark-logo">
                        <img src="../opd/images/logo-letter.png" alt="logo">
                    </span>
                </div>
            </a>
        </div>

        <nav class="navbar navbar-static-top">
            <div class="app-menu">
                <ul class="header-megamenu nav">
                    <li class="btn-group nav-item">
                        <a href="#" class="waves-effect waves-light nav-link push-btn btn-primary-light"
                           data-toggle="push-menu" role="button">
                            <i class="fa-solid fa-bars"></i>
                        </a>
                    </li>

                    <li class="btn-group d-lg-inline-flex d-none">
                        <div class="app-menu">
                            <div class="search-bx mx-5">
                                <form autocomplete="off">
                                    <div class="input-group">
                                        <input type="search" id="searchInput" class="form-control" placeholder="Search...">
                                    </div>
                                    <div id="suggestion-box" class="list-group position-absolute"></div>
                                </form>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="navbar-custom-menu r-side">
                <ul class="nav navbar-nav">

                    <!-- Main Menu -->
                    <li class="btn-group nav-item d-lg-inline-flex d-none">
                        <a href="#" class="waves-effect waves-light push-btn btn btn-primary-light"
                           data-bs-toggle="modal" data-bs-target="#main-menu-modal">
                            <i class="fa-solid fa-layer-group"></i>
                        </a>
                    </li>

                    <!-- Full Screen -->
                    <li class="btn-group nav-item d-lg-inline-flex d-none">
                        <a href="#" data-provide="fullscreen"
                           class="waves-effect waves-light nav-link full-screen btn-warning-light"
                           title="Full Screen">
                            <i class="fa-solid fa-expand"></i>
                        </a>
                    </li>

                    <!-- Notifications -->
                    <li class="dropdown notifications-menu">
                        <a href="#"
                           class="position-relative d-flex align-items-center justify-content-center dropdown-toggle"
                           data-bs-toggle="dropdown" title="Notifications"
                           style="background: #00c8ff; width: 50px; height: 50px; border-radius: 12px; box-shadow: 0 3px 6px rgba(0,0,0,0.1);">
                            <i class="fa-solid fa-bell fa-lg text-white"></i>
                            <span id="notif-badge"
                                  class="position-absolute bg-danger text-white rounded-circle fw-bold shadow-sm"
                                  style="display:none; top:2px; left:50%; transform:translateX(-50%);
                                  font-size:0.65rem; width:18px; height:18px;
                                  align-items:center; justify-content:center; border:2px solid #00c8ff;"></span>
                        </a>

                        <ul class="dropdown-menu animated bounceIn" id="notifications"
                            style="width:320px; max-height:420px; overflow-y:auto;">
                            <li class="header">
                                <div class="p-2 px-3 d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Notifications</h5>
                                    <a href="#" class="text-danger" onclick="clearNotifications()">Clear All</a>
                                </div>
                            </li>
                            <li>
                                <ul class="menu sm-scrol" id="notif-list">
                                    <li><div class="text-center text-muted py-3">Loading...</div></li>
                                </ul>
                            </li>
                            <li class="footer text-center py-2">
                                <a href="wb_chat">View all</a>
                            </li>
                        </ul>
                    </li>

                </ul>
            </div>

            <div class="navbar-custom-menu r-side">
                <ul class="nav navbar-nav">
                    <!-- Theme toggle -->
                    <li class="nav-item d-flex align-items-center px-2">
                        <button class="btn btn-sm" style="background-color: #1DBFC1;" id="themeToggle" title="Toggle Theme">
                            <i class="fa-solid fa-moon" id="themeIcon"></i>
                        </button>
                    </li>

                    <!-- User Account -->
                    <li class="dropdown user user-menu">
                        <a href="#"
                           class="waves-effect waves-light dropdown-toggle w-auto l-h-12 bg-transparent py-0 no-shadow"
                           data-bs-toggle="dropdown" title="User">
                            <div class="d-flex pt-5">
                                <div class="text-end me-10">
                                    <p class="pt-5 fs-14 mb-0 fw-700 text-primary" style="text-transform: capitalize;">
                                    Super Admin
                                    </p>
                                </div>
                                <img src="../opd/admin/assets/images/avatar/avatar-1.png"
                                     class="avatar rounded-10 bg-primary-light h-40 w-40" alt="" />
                            </div>
                        </a>

                        <ul class="dropdown-menu animated flipInX">
                            <li class="user-body">
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="logout"><i class="fa-solid fa-lock"></i>Logout</a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>

        </nav>
    </header>
    <!-- ===================== /FULL HEADER ===================== -->


    <!-- Main Menu Modal (same as yours) -->
    <div class="modal fade" id="main-menu-modal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body custom-scroll">
                    <div class="search-box mb-4">
                        <input type="text" class="form-control search-input" placeholder="Search">
                    </div>
                    <div class="app-grid">                     
                        <a href="index" class="app-item">
                            <div class="app-icon">
                                <img src="../opd/assets/images/main-menu/dashboard.png" alt="dashboard" class="icon-invert">
                            </div>
                            <div class="app-title">Dashboard</div>
                        </a>          

                        <a href="#" class="app-item">
                            <div class="app-icon">
                                <img src="../opd/assets/images/main-menu/opd.png" alt="Payroll">
                            </div>
                            <div class="app-title">Payroll</div>
                        </a>                     

                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- ===================== SIDEBAR (FIXED + FULL) ===================== -->
    <aside class="main-sidebar" style="box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);">
        <section class="sidebar position-relative">
            <div class="help-bt">
                <a href="tel:+918101202074" class="d-flex align-items-center">
                    <div class="bg-danger rounded10 h-50 w-50 l-h-50 text-center me-15">
                        <i data-feather="mic"></i>
                        <img src="../images/emergency.png" height="40px">
                    </div>
                    <h4 class="mb-0">Emergency<br>help</h4>
                </a>
            </div>

            <div class="multinav">
                <div class="multinav-scroll" style="height: 100%;">
                    <ul class="sidebar-menu" data-widget="tree">
                        <li>
                            <a href="index">
                                <i class="fa-solid fa-desktop"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="client_databases">
                                <i class="fa-solid fa-database"></i> Client Databases
                            </a>
                        </li>
                        <li>
                            <a href="model_key">
                                <i class="fa-solid fa-key"></i> Model Key
                            </a>
                        </li>
                        <li>
                            <a href="clients">
                                <i class="fa-solid fa-user-tie"></i> Clients
                            </a>
                        </li>
                    </ul>

                    <div class="sidebar-widgets">
                        <div class="copyright text-center m-25">
                            <p>
                                <strong class="d-block">Rhythm E-Clinic Solutions</strong>
                                © <script>document.write(new Date().getFullYear())</script> All Rights Reserved
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </section>
    </aside>

    <!-- Notification Sound -->
    <audio id="notif-sound" src="https://rhythm.abhitechbot.in/opd/images/notify.mp3" preload="auto"></audio>
    <div id="toast-container" style="position: fixed; bottom: 20px; right: 20px; z-index: 99999;"></div>

    <script>
        if (typeof feather !== "undefined") feather.replace();

        const searchInput = document.getElementById('searchInput');
        const suggestionBox = document.getElementById('suggestion-box');

        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                let query = this.value.trim();
                if (query.length > 1) {
                    fetch('load/search_suggestion.php?q=' + encodeURIComponent(query))
                        .then(r => r.text())
                        .then(html => {
                            suggestionBox.innerHTML = html;
                            suggestionBox.style.display = 'block';
                        });
                } else {
                    suggestionBox.style.display = 'none';
                }
            });
        }

        document.addEventListener('click', function(e) {
            if (e.target.closest('.suggestion-item')) {
                const item = e.target.closest('.suggestion-item');
                const patientName = item.querySelector('strong') ? item.querySelector('strong').innerText : '';
                if (patientName && searchInput) searchInput.value = patientName;
                if (suggestionBox) suggestionBox.style.display = 'none';
            } else {
                if (suggestionBox) suggestionBox.style.display = 'none';
            }
        });
    </script>