
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

                <!-- STORE HOURS AND LOCATION -->
                <section class="info-panel location-panel" id="location">
                    <h2>Visit Us</h2>

                    <div class="visit-hours">
                        <div class="hours-heading">
                            <span class="hours-icon">◷</span>
                            <h3>Store Hours</h3>
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
                    </div>

                    <div class="store-map">
                        <iframe
                            src="https://maps.google.com/maps?q=TMTA+Hardware+Construction+Supplies,+Balubad+1st+Rd,+Lalaan+1st,+Silang,+Cavite&amp;z=15&amp;output=embed"
                            title="Map to TMTA Hardware &amp; Construction Supplies in Silang, Cavite"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen>
                        </iframe>
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

                    <button type="button" class="service-link" data-service-target="delivery-info" data-service-title="Delivery Information" aria-haspopup="dialog" aria-controls="service-info-dialog">
                        <span class="service-icon">🚚</span>
                        <span>
                            <strong>Delivery Information</strong>
                            <small>Shipping and delivery details</small>
                        </span>
                        <span class="service-arrow">→</span>
                    </button>

                    <button type="button" class="service-link" data-service-target="returns-info" data-service-title="Returns &amp; Refunds" aria-haspopup="dialog" aria-controls="service-info-dialog">
                        <span class="service-icon">↶</span>
                        <span>
                            <strong>Returns &amp; Refunds</strong>
                            <small>Our return policy</small>
                        </span>
                        <span class="service-arrow">→</span>
                    </button>

                    <button type="button" class="service-link" data-service-target="payment-info" data-service-title="Payment Methods" aria-haspopup="dialog" aria-controls="service-info-dialog">
                        <span class="service-icon">▤</span>
                        <span>
                            <strong>Payment Methods</strong>
                            <small>Available payment options</small>
                        </span>
                        <span class="service-arrow">→</span>
                    </button>

                    <button type="button" class="service-link" data-service-target="terms-info" data-service-title="Terms &amp; Conditions" aria-haspopup="dialog" aria-controls="service-info-dialog">
                        <span class="service-icon">▧</span>
                        <span>
                            <strong>Terms &amp; Conditions</strong>
                            <small>Rules and regulations</small>
                        </span>
                        <span class="service-arrow">→</span>
                    </button>

                    <button type="button" class="service-link" data-service-target="privacy-info" data-service-title="Privacy Policy" aria-haspopup="dialog" aria-controls="service-info-dialog">
                        <span class="service-icon">♢</span>
                        <span>
                            <strong>Privacy Policy</strong>
                            <small>How we protect your data</small>
                        </span>
                        <span class="service-arrow">→</span>
                    </button>

                </div>

            </section>

            <dialog class="service-info-dialog" id="service-info-dialog" aria-labelledby="service-info-title">
                <div class="service-dialog-header">
                    <h2 id="service-info-title"></h2>
                    <button type="button" class="service-dialog-close" aria-label="Close information window">&times;</button>
                </div>

                <div class="service-dialog-content" id="delivery-info" hidden>
                    <p>Delivery availability, fees, and timing can depend on your location and the items in your order. Please contact the store before placing your order to confirm:</p>
                    <ul>
                        <li>Whether delivery is available to your address</li>
                        <li>The delivery fee and expected schedule</li>
                        <li>Whether your items are currently in stock</li>
                    </ul>
                    <p>Call <a href="tel:+639695234471">+63 969 523 4471</a> or email <a href="mailto:tmtahardware@gmail.com">tmtahardware@gmail.com</a>.</p>
                </div>

                <div class="service-dialog-content" id="returns-info" hidden>
                    <p>Please contact the store before returning an item so the team can confirm whether it is eligible and explain the available refund or exchange options.</p>
                    <ul>
                        <li>Have your receipt or order details ready</li>
                        <li>Keep the item and its packaging in their current condition</li>
                        <li>Ask about any time limits or item-specific exclusions</li>
                    </ul>
                    <p>Return eligibility and resolution must be confirmed by the store.</p>
                </div>

                <div class="service-dialog-content" id="payment-info" hidden>
                    <p>Payment options may vary between in-store purchases and online orders. Before checkout, contact the store to confirm which payment methods are currently accepted and when payment is due.</p>
                    <p>For payment questions, call <a href="tel:+639695234471">+63 969 523 4471</a> or email <a href="mailto:tmtahardware@gmail.com">tmtahardware@gmail.com</a>.</p>
                </div>

                <div class="service-dialog-content" id="terms-info" hidden>
                    <p>Product availability, pricing, delivery arrangements, and order details should be confirmed with the store before purchase. Any special quote or arrangement should be confirmed directly by TMTA.</p>
                    <p>For terms that apply to a specific order, contact the store before completing your purchase.</p>
                </div>

                <div class="service-dialog-content" id="privacy-info" hidden>
                    <p>When contacting or ordering from the store, share only the information needed to handle your inquiry or order. Avoid sending passwords or payment-card security details through email or chat.</p>
                    <p>For questions about information connected to your account or order, contact the store at <a href="mailto:tmtahardware@gmail.com">tmtahardware@gmail.com</a>.</p>
                </div>
            </dialog>

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

<script>
    (() => {
        const dialog = document.getElementById('service-info-dialog');
        const title = document.getElementById('service-info-title');
        const closeButton = dialog.querySelector('.service-dialog-close');

        document.querySelectorAll('[data-service-target]').forEach((button) => {
            button.addEventListener('click', () => {
                dialog.querySelectorAll('.service-dialog-content').forEach((content) => {
                    content.hidden = content.id !== button.dataset.serviceTarget;
                });
                title.textContent = button.dataset.serviceTitle;
                dialog.showModal();
            });
        });

        closeButton.addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    })();
</script>

</body>
</html>
