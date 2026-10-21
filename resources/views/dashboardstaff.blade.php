<!DOCTYPE html>
<html>
<head>
    <title>Staff Transactions | TMTA POS</title>

    <link rel="stylesheet" href="{{ asset('css/sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/transactionstaff.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo-section">
        <a href="{{ route('dashboardstaff') }}" style="text-decoration:none; color:white;">
            <img src="{{ asset('images/logo.png') }}" class="logo">
            <h2 class="system-name">TMTA POS</h2>
        </a>
    </div>

    <ul>
        <li class="active">
            <a href="{{ route('dashboardstaff') }}"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        </li>

        <li>
            <a href="{{ route('newsales') }}"><i class="fa-solid fa-cash-register"></i> New Sale</a>
        </li>

        <li>
            <a href="{{ route('transactions') }}"><i class="fa-solid fa-receipt"></i> Transactions</a>
        </li>

        <li>
            <a href="{{ route('staff.inventory') }}"><i class="fa-solid fa-box"></i> Inventory</a>
        </li>
    </ul>

    <div class="logout-section">
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </button>
        </form>
    </div>
</div>

<!-- MAIN -->
<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
        <input type="text" placeholder="Search Receipt / Product">

        <div class="right-section">
            <div id="datetime" class="datetime"></div>
            <div class="user">{{ Auth::guard('staff')->user()->name ?? 'Staff' }}</div>
        </div>
    </div>


    <h2>Staff Dashboard</h2>

    <!-- CARDS -->
    <div class="cards">
        <div class="card gray">
            <div class="
