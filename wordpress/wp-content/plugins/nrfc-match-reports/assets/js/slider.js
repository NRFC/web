document.addEventListener('DOMContentLoaded', function() {
    const sliders = document.querySelectorAll('.nrfc-match-report-slider');
    
    sliders.forEach(slider => {
        const wrapper = slider.querySelector('.slider-wrapper');
        const slides = slider.querySelectorAll('.slider-slide');
        if (slides.length <= 1) return;

        let currentIndex = 0;
        const totalSlides = slides.length;

        setInterval(() => {
            currentIndex = (currentIndex + 1) % totalSlides;
            wrapper.style.transform = `translateX(-${currentIndex * 100}%)`;
        }, 3000);
    });
});
