/**
 * Screen 1: Welcome Animation
 * Uses GSAP for smooth hero animations
 */

// Load GSAP from CDN (will be included in HTML)
// This file contains the animation sequences for the welcome screen

function initWelcomeAnimation() {
  // Kill any existing animations first to prevent conflicts
  gsap.killTweensOf('#screen1 h1, #screen1 .subtitle, .hero-icon, #get-started-btn, .gradient-bg');
  
  // Reset elements to initial state
  gsap.set('#screen1 h1', { y: 0, opacity: 1 });
  gsap.set('#screen1 .subtitle', { y: 0, opacity: 1 });
  gsap.set('.hero-icon', { scale: 1, opacity: 1, y: 0 });
  
  // Hero text animation - stagger effect
  gsap.from('#screen1 h1', {
    duration: 1,
    y: 50,
    opacity: 0,
    ease: 'power3.out',
    delay: 0.1
  });
  
  gsap.from('#screen1 .subtitle', {
    duration: 1,
    y: 30,
    opacity: 0,
    ease: 'power3.out',
    delay: 0.3
  });
  
  // Animated icons - floating effect
  gsap.from('.hero-icon', {
    duration: 1.2,
    scale: 0,
    opacity: 0,
    stagger: 0.2,
    ease: 'back.out(1.7)',
    delay: 0.5
  });
  
  // Continuous floating animation for icons
  gsap.to('.hero-icon', {
    y: -15,
    duration: 2,
    repeat: -1,
    yoyo: true,
    ease: 'sine.inOut',
    stagger: {
      each: 0.3,
      repeat: -1
    }
  });
  
  /*
  // CTA button - pop in
  gsap.from('#get-started-btn', {
    duration: 0.8,
    scale: 0.8,
    opacity: 0,
    ease: 'elastic.out(1, 0.5)',
    delay: 1.2
  });
  */
  
  // Pulse animation for CTA
  gsap.to('#get-started-btn', {
    boxShadow: '0 0 30px rgba(123, 63, 242, 0.5)',
    duration: 1.5,
    repeat: -1,
    yoyo: true,
    ease: 'sine.inOut'
  });
  
  // Background gradient animation
  const gradient = document.querySelector('.gradient-bg');
  if (gradient) {
    gsap.to(gradient, {
      backgroundPosition: '200% 50%',
      duration: 15,
      repeat: -1,
      ease: 'none'
    });
  }
}

// Make this globally accessible for wizard to call
window.initWelcomeAnimation = initWelcomeAnimation;

// Initialize when this screen becomes active
document.addEventListener('DOMContentLoaded', () => {
  // Check if GSAP is loaded
  if (typeof gsap !== 'undefined') {
    // Run animation when screen 1 is visible initially
    setTimeout(() => {
      const screen1 = document.getElementById('screen1');
      if (screen1 && screen1.classList.contains('active')) {
        initWelcomeAnimation();
      }
    }, 100);
  } else {
    console.warn('GSAP not loaded - animations disabled');
  }
});
