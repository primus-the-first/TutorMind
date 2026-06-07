// ============================================
// SHARED MOUSE STATE
// ============================================
let rawMouseX = 0, rawMouseY = 0;
let smoothMouseX = 0, smoothMouseY = 0;

document.addEventListener('mousemove', (e) => {
    rawMouseX = e.clientX - window.innerWidth / 2;
    rawMouseY = e.clientY - window.innerHeight / 2;
});

function lerp(a, b, t) {
    return a + (b - a) * t;
}

// ============================================
// THREE.JS PARTICLE BACKGROUND
// ============================================
const canvasContainer = document.getElementById('canvas-container');

if (canvasContainer) {
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer({ alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setSize(window.innerWidth, window.innerHeight);
    canvasContainer.appendChild(renderer.domElement);

    const particleCount = 200;
    const particles = new THREE.BufferGeometry();
    const positions = new Float32Array(particleCount * 3);
    const particleVelocities = [];

    for (let i = 0; i < particleCount; i++) {
        const i3 = i * 3;
        positions[i3]     = (Math.random() - 0.5) * 30;
        positions[i3 + 1] = (Math.random() - 0.5) * 30;
        positions[i3 + 2] = (Math.random() - 0.5) * 30;
        particleVelocities.push(new THREE.Vector3(
            (Math.random() - 0.5) * 0.1,
            (Math.random() - 0.5) * 0.1,
            (Math.random() - 0.5) * 0.1
        ));
    }

    particles.setAttribute('position', new THREE.BufferAttribute(positions, 3));

    function getParticleColor() {
        return document.body.classList.contains('light-mode') ? 0x9D6BF5 : 0xC4B5FD;
    }

    const particleMaterial = new THREE.PointsMaterial({
        color: getParticleColor(),
        size: 0.15,
        blending: THREE.AdditiveBlending,
        transparent: true,
        opacity: 0.4
    });

    const particleSystem = new THREE.Points(particles, particleMaterial);
    scene.add(particleSystem);

    camera.position.z = 50;

    function updateThreeJsTheme() {
        const isLight = document.body.classList.contains('light-mode');
        particleMaterial.color.setHex(getParticleColor());
        particleMaterial.opacity = isLight ? 0.4 : 0.8;
        particleMaterial.size   = isLight ? 0.15 : 0.25;
        particleMaterial.needsUpdate = true;
    }

    window._updateThreeJsTheme = updateThreeJsTheme;

    function animateThree() {
        requestAnimationFrame(animateThree);

        const pos = particleSystem.geometry.attributes.position.array;

        for (let i = 0; i < particleCount; i++) {
            const i3 = i * 3;
            pos[i3]     += particleVelocities[i].x;
            pos[i3 + 1] += particleVelocities[i].y;
            pos[i3 + 2] += particleVelocities[i].z;
            if (pos[i3]     > 15 || pos[i3]     < -15) particleVelocities[i].x *= -1;
            if (pos[i3 + 1] > 15 || pos[i3 + 1] < -15) particleVelocities[i].y *= -1;
            if (pos[i3 + 2] > 15 || pos[i3 + 2] < -15) particleVelocities[i].z *= -1;
        }

        particleSystem.geometry.attributes.position.needsUpdate = true;
        particleSystem.rotation.y += 0.001;

        // Camera is the deepest parallax layer — very subtle drift
        camera.position.x += (rawMouseX / 200 - camera.position.x) * 0.05;
        camera.position.y += (-rawMouseY / 200 - camera.position.y) * 0.05;
        camera.lookAt(scene.position);

        renderer.render(scene, camera);
    }

    animateThree();

    window.addEventListener('resize', () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    });
}

// ============================================
// HTML ELEMENT PARALLAX
// Uses CSS `translate` property (individual
// transform) so it composes with existing CSS
// animations that use `transform` — no conflicts.
// ============================================
const parallaxLayers = document.querySelectorAll('[data-depth]');

function tickParallax() {
    smoothMouseX = lerp(smoothMouseX, rawMouseX, 0.06);
    smoothMouseY = lerp(smoothMouseY, rawMouseY, 0.06);

    parallaxLayers.forEach(el => {
        const depth = parseFloat(el.dataset.depth);
        el.style.translate = `${smoothMouseX * depth}px ${smoothMouseY * depth}px`;
    });

    requestAnimationFrame(tickParallax);
}

tickParallax();
