/**
 * Prayer Times Carousel Functionality
 */
document.addEventListener('DOMContentLoaded', function() {
    initPrayerTimesCarousels();
});

/**
 * Initialize all prayer times carousels on the page
 */
function initPrayerTimesCarousels() {
    const carousels = document.querySelectorAll('.prayer-times-carousel');
    
    carousels.forEach(carousel => {
        const carouselInner = carousel.querySelector('.prayer-times-carousel-inner');
        const dots = carousel.querySelectorAll('.prayer-times-carousel-dot');
        const prevButton = carousel.querySelector('.prayer-times-carousel-prev');
        const nextButton = carousel.querySelector('.prayer-times-carousel-next');
        const totalSlides = dots.length;
        let currentSlide = 0;
        
        // Set up initial state
        updateCarouselPosition();
        dots[0].classList.add('active');
        
        // Set up event listeners
        if (prevButton) {
            prevButton.addEventListener('click', goToPrevSlide);
        }
        
        if (nextButton) {
            nextButton.addEventListener('click', goToNextSlide);
        }
        
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => goToSlide(index));
        });
        
        // Enable touch swipe for mobile devices
        let touchStartX = 0;
        let touchEndX = 0;
        
        carousel.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        });
        
        carousel.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        });
        
        function handleSwipe() {
            const swipeThreshold = 50;
            if (touchEndX < touchStartX - swipeThreshold) {
                // Swipe left - go to next slide
                goToNextSlide();
            } else if (touchEndX > touchStartX + swipeThreshold) {
                // Swipe right - go to previous slide
                goToPrevSlide();
            }
        }
        
        // Navigation functions
        function goToSlide(slideIndex) {
            if (slideIndex < 0) {
                slideIndex = totalSlides - 1;
            } else if (slideIndex >= totalSlides) {
                slideIndex = 0;
            }
            
            currentSlide = slideIndex;
            updateCarouselPosition();
            updateActiveDot();
        }
        
        function goToPrevSlide() {
            goToSlide(currentSlide - 1);
        }
        
        function goToNextSlide() {
            goToSlide(currentSlide + 1);
        }
        
        function updateCarouselPosition() {
            carouselInner.style.transform = `translateX(-${currentSlide * 100}%)`;
        }
        
        function updateActiveDot() {
            dots.forEach(dot => dot.classList.remove('active'));
            dots[currentSlide].classList.add('active');
        }
    });
}
