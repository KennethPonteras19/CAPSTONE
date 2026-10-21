<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>System Logs | TMTA POS</title>

    <link rel="stylesheet" href="{{ asset('css/superadmin.css') }}">

    <link rel="stylesheet" href="{{ asset('css/admin-system-logs.css') }}">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    @include('partials.admin-sidebar-assets')
</head>

<body class="admin-workspace">


{{-- =========================================================
     SIDEBAR
========================================================= --}}

@include('partials.admin-sidebar')



{{-- =========================================================
     MAIN CONTENT
========================================================= --}}

<main class="main-content">


    {{-- =====================================================
         TOPBAR
    ====================================================== --}}

    <header class="topbar">

        <div class="page-title">

            <h2>
                System Logs
            </h2>

            <p>
                Review system activity. Read-only access: records cannot be edited, cleared, or deleted.
            </p>

        </div>


        {{-- LOG FILTERS --}}

        <form
            class="topbar-right log-filters"
            method="GET"
            action="{{ route('admin.system.logs') }}"
        >

            {{-- SEARCH --}}

            <label class="log-search">

                <span>
                    Search logs
                </span>

                <input
                    type="text"
                    name="search"
                    id="searchInput"
                    class="select-field"
                    maxlength="255"
                    value="{{ request('search') }}"
                    placeholder="Date, time, user or event"
                >

            </label>


            {{-- REMARKS --}}

            <label>

                <span>
                    Remarks
                </span>

                <select
                    name="severity"
                    id="severityFilter"
                    class="select-field"
                >

                    <option
                        value="all"
                        @selected(request('severity', 'all') === 'all')
                    >
                        All Remarks
                    </option>

                    <option
                        value="info"
                        @selected(request('severity') === 'info')
                    >
                        Info
                    </option>

                    <option
                        value="warning"
                        @selected(request('severity') === 'warning')
                    >
                        Warning
                    </option>

                    <option
                        value="danger"
                        @selected(request('severity') === 'danger')
                    >
                        Error
                    </option>

                </select>

            </label>


            {{-- SEARCH BUTTON --}}

            <button
                type="submit"
                class="btn primary"
            >

                <i class="fa-solid fa-magnifying-glass"></i>

                Search

            </button>

        </form>

    </header>



    {{-- =====================================================
         SYSTEM LOGS CARD
    ====================================================== --}}

    <section
        class="card"
        id="systemLogs"
        data-stream-url="{{ route('admin.system.logs.stream') }}"
        data-index-url="{{ route('admin.system.logs') }}"
        data-page="{{ $logs->currentPage() }}"
    >

        <h3 class="section-title">

            <i class="fa-solid fa-scroll"></i>

            Event Stream

        </h3>


        <p class="note">

            25 records per page, newest first.
            Search includes all available history.
            Live updates run every minute on page 1;
            older pages stay still while you read.

        </p>


        {{-- LOG RESULTS --}}

        <div id="logResults">

            @include('superadmin.partials.log-results')

        </div>


        {{-- ACTIONS --}}

        <div class="actions">

            <a
                href="{{ route('admin.system.logs') }}"
                class="btn primary"
            >

                <i class="fa-solid fa-filter-circle-xmark"></i>

                Reset filters

            </a>

        </div>


        {{-- LIVE STATUS --}}

        <p
            class="note"
            id="logsStatus"
            role="status"
        ></p>

    </section>

</main>



{{-- =========================================================
     JAVASCRIPT
========================================================= --}}

<script
    src="{{ asset('js/system-logs.js') . '?v=' . filemtime(public_path('js/system-logs.js')) }}"
    defer
></script>


</body>

</html>S
