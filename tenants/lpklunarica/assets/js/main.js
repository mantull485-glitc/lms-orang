/**
 * LPK Lunarica - Main Interaction Script
 * Premium UI/UX Animations
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Animations
    initNavbarScroll();
    initCounterAnimation();
    initMouseParallax();
    initScrollReveal();
    initScrollProgress();
    initTiltEffect();

    // 2. Navbar Scroll Effect
    function initNavbarScroll() {
        const navbar = document.querySelector('.navbar');
        if (!navbar) return;

        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('navbar-scrolled');
            } else {
                navbar.classList.remove('navbar-scrolled');
            }
        });
    }

    // 3. Scroll Progress Bar
    function initScrollProgress() {
        const progressBar = document.createElement('div');
        progressBar.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            height: 4px;
            background: var(--primary-gradient);
            z-index: 9999;
            width: 0%;
            transition: width 0.1s ease-out;
        `;
        document.body.appendChild(progressBar);

        window.addEventListener('scroll', () => {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            progressBar.style.width = scrolled + "%";
        });
    }

    // 4. Counter Animation for Dashboard Stats
    function initCounterAnimation() {
        const counters = document.querySelectorAll('.animate-count');
        const speed = 200;

        const startCounting = (counter) => {
            const target = +counter.getAttribute('data-target');
            const count = +counter.innerText.replace(/[^0-9]/g, '');
            const inc = target / speed;

            if (count < target) {
                counter.innerText = Math.ceil(count + inc);
                setTimeout(() => startCounting(counter), 1);
            } else {
                counter.innerText = target;
            }
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const counter = entry.target;
                    counter.classList.add('visible');
                    setTimeout(() => startCounting(counter), 400);
                    observer.unobserve(counter);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(counter => observer.observe(counter));
    }

    // 5. Mouse Parallax for Hero Glow Blobs
    function initMouseParallax() {
        const blobs = document.querySelectorAll('.glow-blob');
        if (blobs.length === 0) return;

        document.addEventListener('mousemove', (e) => {
            const { clientX, clientY } = e;
            const centerX = window.innerWidth / 2;
            const centerY = window.innerHeight / 2;

            blobs.forEach((blob, index) => {
                const ratio = (index + 1) * 0.03;
                const x = (clientX - centerX) * ratio;
                const y = (clientY - centerY) * ratio;
                blob.style.transform = `translate(${x}px, ${y}px)`;
            });
        });
    }

    // 6. 3D Tilt Effect for Cards
    function initTiltEffect() {
        const cards = document.querySelectorAll('.modern-card, .card-class');
        
        cards.forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const { left, top, width, height } = card.getBoundingClientRect();
                const x = (e.clientX - left) / width;
                const y = (e.clientY - top) / height;
                
                const tiltX = (y - 0.5) * 10;
                const tiltY = (x - 0.5) * -10;
                
                card.style.transform = `perspective(1000px) rotateX(${tiltX}deg) rotateY(${tiltY}deg) translateY(-10px)`;
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = `perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0px)`;
            });
        });
    }

    // 7. Enhanced Scroll Reveal (Staggered)
    function initScrollReveal() {
        const reveals = document.querySelectorAll('.reveal-on-scroll');
        
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.classList.add('active');
                    }, index * 100);
                }
            });
        }, { threshold: 0.1 });

        reveals.forEach(el => revealObserver.observe(el));
    }

    // 6. Sidebar Tooltips/Active enhancements
    const activeLink = document.querySelector('.admin-sidebar .nav-link.active');
    if (activeLink) {
        activeLink.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
