<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | TMTA POS</title>

    <link rel="stylesheet" href="{{ asset('css/settings.css') }}">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    @include('partials.password-toggle-assets')
    @include('partials.admin-sidebar-assets')
</head>

<body class="admin-workspace">

    <!-- SIDEBAR -->
    @include('partials.admin-sidebar')



    <!-- MAIN -->
    <main class="main-content">


        <!-- TOPBAR -->
        <header class="topbar">

            <div class="page-title">

                <h2>
                    System Settings
                </h2>

                <p>
                    Manage store preferences and security.
                </p>

            </div>


            <input
                type="text"
                class="search"
                placeholder="Search settings..."
            >

        </header>



        <!-- TABS -->
        <div class="tab-navigation">

            <button
                class="tab-link active"
                data-tab="profile">

                Profile

            </button>


            <button
                class="tab-link"
                data-tab="store">

                Store Info

            </button>


            <button
                class="tab-link"
                data-tab="business">

                Business Rules

            </button>


            <button
                class="tab-link"
                data-tab="maintenance">

                Maintenance

            </button>

        </div>



        <!-- ==================================================
             PROFILE
        ================================================== -->

        <section
            class="tab-pane active"
            id="profile">

            <div class="card">

                <h3>

                    <i class="fa-solid fa-user"></i>

                    Profile Settings

                </h3>


                <div class="form-group">

                    <label>
                        Username
                    </label>

                    <input
                        type="text"
                        class="input-field readonly"
                        value="{{ Auth::user()->username ?? 'User' }}"
                        readonly>

                </div>


                <div class="form-group">

                    <label>
                        New Password
                    </label>

                    <input
                        type="password"
                        class="input-field"
                        placeholder="Enter new password">

                </div>


                <button
                    class="action-btn blue-btn">

                    Update Profile

                </button>

            </div>

        </section>



        <!-- ==================================================
             STORE INFORMATION
        ================================================== -->

        <section
            class="tab-pane"
            id="store">

            <div class="card">


                <h3>

                    <i class="fa-solid fa-store"></i>

                    Store Information

                </h3>



                <!-- SUCCESS MESSAGE -->

                @if(session('success'))

                    <div class="alert-success">

                        {{ session('success') }}

                    </div>

                @endif



                <!-- ERROR MESSAGE -->

                @if($errors->any())

                    <div class="alert-error">

                        <ul>

                            @foreach($errors->all() as $error)

                                <li>
                                    {{ $error }}
                                </li>

                            @endforeach

                        </ul>

                    </div>

                @endif



                <!-- STORE FORM -->

                <form
                    action="{{ route('settings.store.update') }}"
                    method="POST"
                    enctype="multipart/form-data">

                    @csrf



                    <!-- STORE NAME -->

                    <div class="form-group">

                        <label>
                            Store Name
                        </label>

                        <input
                            type="text"
                            name="store_name"
                            class="input-field"
                            value="{{ $storeSetting?->store_name ?? 'TMTA General Merchandise' }}"
                            placeholder="Enter store name"
                            required>

                    </div>



                    <!-- BRAND NAME -->

                    <div class="form-group">

                        <label>
                            Brand Name
                        </label>

                        <input
                            type="text"
                            name="brand_name"
                            class="input-field"
                            value="{{ $storeSetting?->brand_name ?? 'TMTA' }}"
                            placeholder="Enter brand name"
                            required>

                    </div>



                    <!-- LOGO -->

                    <div class="form-group">

                        <label>
                            Store Logo
                        </label>

                        <input
                            type="file"
                            name="logo"
                            class="input-field"
                            accept=".jpg,.jpeg,.png">

                        <small
                            style="
                                display:block;
                                margin-top:8px;
                                color:#777;
                            ">

                            JPG, JPEG, or PNG.
                            Maximum size: 2MB.

                        </small>

                    </div>



                    <!-- CURRENT LOGO -->
<div class="form-group">

    <label>
        Current Logo
    </label>

    <div style="margin-top:10px;">

        @if(!empty($storeSetting?->logo))

            <img
                src="{{ asset('storage/' . $storeSetting->logo) }}"
                alt="Store Logo"
                style="
                    width:120px;
                    height:120px;
                    object-fit:contain;
                    border:1px solid #ddd;
                    border-radius:10px;
                    padding:10px;
                    background:white;
                ">

        @else

            <img
                src="{{ asset('images/logo.png') }}"
                alt="Default TMTA Logo"
                style="
                    width:120px;
                    height:120px;
                    object-fit:contain;
                    border:1px solid #ddd;
                    border-radius:10px;
                    padding:10px;
                    background:white;
                ">

        @endif

    </div>

</div>



                    <!-- SAVE -->

                    <button
                        type="submit"
                        class="action-btn green-btn">

                        <i class="fa-solid fa-floppy-disk"></i>

                        Save Information

                    </button>


                </form>

            </div>

        </section>



        <!-- ==================================================
             BUSINESS RULES
        ================================================== -->

        <section
            class="tab-pane"
            id="business">

            <div class="card">

                <h3>

                    <i class="fa-solid fa-file-invoice-dollar"></i>

                    Business Rules

                </h3>


                <div class="form-group">

                    <label>
                        VAT Rate (%)
                    </label>

                    <input
                        type="number"
                        class="input-field"
                        value="12">

                </div>


                <button
                    class="action-btn blue-btn">

                    Apply Rules

                </button>

            </div>

        </section>



        <!-- ==================================================
             MAINTENANCE
        ================================================== -->

        <section
            class="tab-pane"
            id="maintenance">

            <div class="card">

                <h3>

                    <i class="fa-solid fa-tools"></i>

                    Maintenance

                </h3>


                <div class="btn-group">

                    <button
                        class="action-btn sky-btn">

                        Backup Database

                    </button>


                    <button
                        class="action-btn orange-btn">

                        Clear Cache

                    </button>

                </div>

            </div>

        </section>


    </main>



    <!-- ==================================================
         JAVASCRIPT
    ================================================== -->

    <script>

        document.addEventListener("DOMContentLoaded", function () {

            const tabs =
                document.querySelectorAll(".tab-link");

            const panes =
                document.querySelectorAll(".tab-pane");


            tabs.forEach(tab => {

                tab.addEventListener("click", function () {

                    const target =
                        this.dataset.tab;


                    tabs.forEach(btn =>
                        btn.classList.remove("active")
                    );


                    panes.forEach(pane =>
                        pane.classList.remove("active")
                    );


                    this.classList.add("active");


                    document
                        .getElementById(target)
                        .classList.add("active");

                });

            });

        });

    </script>


</body>
</html>