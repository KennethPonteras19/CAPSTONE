<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TMTA Construction Supply</title>
    @include('customer.partials.assets')
</head>

<body>

<div class="app">

    <!-- SIDEBAR -->
    @include('customer.partials.sidebar')


    <!-- MAIN CONTENT -->
    <main class="main-content">

        <header class="customer-page-heading">
            <h1>Welcome to TMTA</h1>
            <p>Hardware and construction supplies for your next project.</p>
        </header>

        <!-- FEATURED PRODUCT SLIDES -->
        <section class="hero featured-carousel" data-product-carousel aria-label="Featured products">
            @if($featuredProducts->isNotEmpty())
                <div class="carousel-slides" aria-live="polite">
                    @foreach($featuredProducts as $product)
                        <article class="product-slide {{ $loop->first ? 'active' : '' }}"
                                 data-carousel-slide
                                 aria-hidden="{{ $loop->first ? 'false' : 'true' }}">
                            <div class="product-slide-copy">
                                <p class="slide-eyebrow">Featured product <span>{{ sprintf('%02d', $loop->iteration) }}</span></p>
                                <h1>{{ $product['name'] }}</h1>
                                <p class="slide-description">{{ $product['category'] }} supplies for your next project.</p>
                                <p class="slide-price">₱{{ number_format($product['price'], 2) }} <span>/ {{ $product['unit'] }}</span></p>
                                @if(\App\Support\ModuleAccess::allows('customer', 'order'))
                                    <a class="slide-cta" href="{{ route('customer.order') }}">Browse products <span aria-hidden="true">→</span></a>
                                @endif
                            </div>

                            <div class="product-slide-visual">
                                @if($product['image_url'])
                                    <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}" loading="lazy">
                                @else
                                    <div class="product-slide-placeholder" aria-hidden="true">
                                        <span>TMTA</span>
                                        <strong>{{ sprintf('%02d', $loop->iteration) }}</strong>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                @if($featuredProducts->count() > 1)
                    <div class="carousel-controls">
                        <div class="carousel-indicators" aria-label="Choose a featured product">
                            @foreach($featuredProducts as $product)
                                <button class="carousel-indicator {{ $loop->first ? 'active' : '' }}"
                                        type="button"
                                        data-carousel-to="{{ $loop->index }}"
                                        aria-label="Show Product {{ $loop->iteration }}"
                                        aria-current="{{ $loop->first ? 'true' : 'false' }}"></button>
                            @endforeach
                        </div>
                        <div class="carousel-arrows">
                            <button type="button" data-carousel-prev aria-label="Previous product">←</button>
                            <button type="button" data-carousel-next aria-label="Next product">→</button>
                        </div>
                    </div>
                @endif
            @else
                <article class="product-slide active">
                    <div class="product-slide-copy">
                        <p class="slide-eyebrow">TMTA Construction Supply</p>
                        <h1>Build your next project</h1>
                        <p class="slide-product-name">Quality products for your next project.</p>
                        @if(\App\Support\ModuleAccess::allows('customer', 'order'))
                            <a class="slide-cta" href="{{ route('customer.order') }}">Browse products <span aria-hidden="true">→</span></a>
                        @endif
                    </div>
                    <div class="product-slide-visual">
                        <div class="product-slide-placeholder" aria-hidden="true">
                            <span>TMTA</span>
                            <strong>01</strong>
                        </div>
                    </div>
                </article>
            @endif
        </section>


        <!-- PRODUCT CATEGORIES -->
        <section class="categories">

            <div class="section-heading">

                <h2>
                    Product Categories
                </h2>

                <div class="heading-line"></div>

            </div>


            <!-- CATEGORY GRID -->
            <div class="category-grid">


                <!-- ELECTRICAL -->
                <button
                    class="category-card"
                    @disabled(!\App\Support\ModuleAccess::allows('customer', 'order')) onclick="goToCategory('Electrical')">

                    <div class="category-icon electrical">
                        ⚡
                    </div>

                    <h3>
                        Electrical
                    </h3>

                    <p>
                        Electrical supplies and equipment
                    </p>

                </button>


                <!-- TOOLS -->
                <button
                    class="category-card"
                    @disabled(!\App\Support\ModuleAccess::allows('customer', 'order')) onclick="goToCategory('Tools')">

                    <div class="category-icon tools">
                        🔧
                    </div>

                    <h3>
                        Tools
                    </h3>

                    <p>
                        Tools and equipment
                    </p>

                </button>


                <!-- HARDWARE -->
                <button
                    class="category-card"
                    @disabled(!\App\Support\ModuleAccess::allows('customer', 'order')) onclick="goToCategory('Hardware')">

                    <div class="category-icon hardware">
                        🔩
                    </div>

                    <h3>
                        Hardware
                    </h3>

                    <p>
                        Construction hardware
                    </p>

                </button>


                <!-- OTHERS -->
                <button
                    class="category-card"
                    @disabled(!\App\Support\ModuleAccess::allows('customer', 'order')) onclick="goToCategory('Others')">

                    <div class="category-icon others">
                        ⋯
                    </div>

                    <h3>
                        Others
                    </h3>

                    <p>
                        Other available products
                    </p>

                </button>


            </div>

        </section>

    </main>

</div>


<!-- CATEGORY NAVIGATION -->
<script>

(() => {
    const carousel = document.querySelector('[data-product-carousel]');
    if (!carousel) return;

    const slides = [...carousel.querySelectorAll('[data-carousel-slide]')];
    const indicators = [...carousel.querySelectorAll('[data-carousel-to]')];
    if (slides.length < 2) return;

    let activeIndex = 0;
    let rotation;

    const showSlide = (index) => {
        activeIndex = (index + slides.length) % slides.length;
        slides.forEach((slide, position) => {
            const isActive = position === activeIndex;
            slide.classList.toggle('active', isActive);
            slide.setAttribute('aria-hidden', String(!isActive));
        });
        indicators.forEach((indicator, position) => {
            const isActive = position === activeIndex;
            indicator.classList.toggle('active', isActive);
            indicator.setAttribute('aria-current', String(isActive));
        });
    };

    const stopRotation = () => window.clearInterval(rotation);
    const startRotation = () => {
        stopRotation();
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            rotation = window.setInterval(() => showSlide(activeIndex + 1), 6000);
        }
    };

    carousel.querySelector('[data-carousel-prev]')?.addEventListener('click', () => {
        showSlide(activeIndex - 1);
        startRotation();
    });
    carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => {
        showSlide(activeIndex + 1);
        startRotation();
    });
    indicators.forEach((indicator) => indicator.addEventListener('click', () => {
        showSlide(Number(indicator.dataset.carouselTo));
        startRotation();
    }));

    carousel.addEventListener('mouseenter', stopRotation);
    carousel.addEventListener('mouseleave', startRotation);
    carousel.addEventListener('focusin', stopRotation);
    carousel.addEventListener('focusout', (event) => {
        if (!carousel.contains(event.relatedTarget)) startRotation();
    });
    startRotation();
})();

function goToCategory(category) {

    window.location.href =
        "{{ route('customer.order') }}?category=" +
        encodeURIComponent(category);

}

</script>

</body>
</html>
