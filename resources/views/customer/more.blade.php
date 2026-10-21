
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>More - TMTA Construction Supply</title>

@include('customer.partials.assets')


</head>
<body>

<div class="app">

    <!-- SIDEBAR -->
    @include('customer.partials.sidebar')

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <header class="topbar">
            <div class="page-title">More</div>
        </header>

        <section class="more-main">

            <!-- WELCOME BANNER -->
            <div class="more-hero">
                <div class="hero-text">
                    <span class="hero-label">WELCOME TO</span>

                    <h1>
                        TMTA Hardware &amp;<br>
                        Construction Supplies
                    </h1>
                </div>

                <div class="hero-image">
                    <img src="{{ $storeSetting?->logo ? asset('storage/'.$storeSetting->logo) : asset('images/logo.png') }}"
                         alt="TMTA store logo">
                </div>
            </div>

            <!-- STORE INFORMATION -->
            <div class="more-info-grid">

                <!-- CONTACT INFORMATION -->
                <section class="info-panel contact-panel" id="contact">

                    <h2>Contact Information</h2>

                    <!-- Replace these sample details with your real details -->
                    <div class="contact-item">
                        <span class="contact-icon">☎</span>
                        <div>
                            <strong>+63 969 523 4471 / +63 916 465 0758</strong>
                            <p>Call us for inquiries</p>
                        </div>
                    </div>

                    <div class="contact-item">
                        <span class="contact-icon">✉</span>
                        <div>
                            <strong>tmtahardware@gmail.com</strong>
                            <p>Send us your questions</p>
                        </div>
                    </div>

                    <div class="contact-item">
                        <span class="contact-icon">⌖</span>
                        <div>
                            <strong>TMTA Hardware &amp; Construction Supplies</strong>
                            <p>Balubad 1st Rd, Lalaan 1st, Silang, 4118 Cavite</p>
                        </div>
                    </div>
                </section>

                <!-- COMPACT STORE HOURS -->
                <section class="info-panel hours-panel">

                    <div class="hours-heading">
                        <span class="hours-icon">◷</span>
                        <h2>Store Hours</h2>
                    </div>

                    <div class="hours-details">
                        <span class="calendar-icon">▦</span>

                        <div>
                            <strong>Monday – Sunday</strong>
                            <p>7:00 AM – 5:00 PM</p>
                        </div>
                    </div>

                    <div class="hours-status">
                        <span class="status-dot"></span>
                        Daily store schedule
                    </div>

                </section>

                <!-- STORE LOCATION -->
                <section class="info-panel location-panel" id="location">

                    <div class="map-placeholder">
                        <div class="map-pin">⌖</div>
                        <strong>TMTA Hardware &amp; Construction Supplies</strong>
                        <p>Visit us in Lalaan 1st, Silang, Cavite.</p>
                    </div>

                    <a class="map-link"
                       href="https://www.google.com/maps/search/?api=1&amp;query=TMTA+Hardware+Construction+Supplies"
                       target="_blank"
                       rel="noopener noreferrer">
                        <span>⌖ &nbsp; View on Google Maps</span>
                        <span>→</span>
                    </a>

                </section>

            </div>

            <!-- ABOUT SECTION -->
            <section class="info-panel about-panel" id="about">
                <div class="about-icon">▦</div>

                <div>
                    <h2>About TMTA</h2>
                    <p>
                        TMTA Hardware &amp; Construction Supplies provides
                        hardware and construction materials for your
                        building, repair, and renovation needs.
                        We aim to make finding the supplies you need
                        simple and convenient.
                    </p>
                </div>
            </section>

            <!-- CUSTOMER SERVICES -->
            <section class="info-panel services-panel">

                <h2>
                    <span class="services-heading-icon">⚙</span>
                    Customer Services
                </h2>

                <div class="services-grid">

                    <a href="#delivery" class="service-link">
                        <span class="service-icon">🚚</span>
                        <span>
                            <strong>Delivery Information</strong>
                            <small>Shipping and delivery details</small>
                        </span>
                        <span class="service-arrow">→</span>
                    </a>

                    <a href="#returns" class="service-link">
                        <span class="service-icon">↶</span>
                        <span>
                            <strong>Returns &amp; Refunds</strong>
                            <small>Our return policy</small>
                        </span>
                        <span class="service-arrow">→</span>
                    </a>

                    <a href="#payment" class="service-link">
                        <span class="service-icon">▤</span>
                        <span>
                            <strong>Payment Methods</strong>
                            <small>Available payment options</small>
                        </span>
                        <span class="service-arrow">→</span>
                    </a>

                    <a href="#terms" class="service-link">
                        <span class="service-icon">▧</span>
                        <span>
                            <strong>Terms &amp; Conditions</strong>
                            <small>Rules and regulations</small>
                        </span>
                        <span class="service-arrow">→</span>
                    </a>

                    <a href="#privacy" class="service-link">
                        <span class="service-icon">♢</span>
                        <span>
                            <strong>Privacy Policy</strong>
                            <small>How we protect your data</small>
                        </span>
                        <span class="service-arrow">→</span>
                    </a>

                </div>

            </section>

            <!-- FAQ -->
            <section class="info-panel faq-panel" id="faqs">
                <h2>Frequently Asked Questions</h2>

                <details>
                    <summary>What time does the store open?</summary>
                    <p>Our listed store hours are 7:00 AM to 5:00 PM daily.</p>
                </details>

                <details id="delivery">
                    <summary>Do you offer delivery?</summary>
                    <p>Please contact our store to confirm delivery availability and fees.</p>
                </details>

                <details id="returns">
                    <summary>How can I return an item?</summary>
                    <p>Contact the store to confirm the applicable return and refund policy.</p>
                </details>

                <details id="payment">
                    <summary>What payment methods do you accept?</summary>
                    <p>Contact the store for the current list of accepted payment methods.</p>
                </details>

                <div id="terms"></div>
                <div id="privacy"></div>
            </section>

            <!-- FOOTER -->
            <footer class="more-footer">
                <span>TMTA Hardware &amp; Construction Supplies</span>
                <span>
                    © 2026 TMTA Construction Supply Hardware Store.
                    All rights reserved.
                </span>
            </footer>

        </section>

    </main>

</div>

</body>
</html>
