// Scene setup
const scene = new THREE.Scene();
const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
const renderer = new THREE.WebGLRenderer({ alpha: true });
renderer.setSize(window.innerWidth, window.innerHeight);
document.getElementById('canvas-container').appendChild(renderer.domElement);

const particleCount = 200;
const particles = new THREE.BufferGeometry();
const positions = new Float32Array(particleCount * 3);

const particleVelocities = [];

for (let i = 0; i < particleCount; i++) {
    const i3 = i * 3;
    positions[i3] = (Math.random() - 0.5) * 30;
    positions[i3 + 1] = (Math.random() - 0.5) * 30;
    positions[i3 + 2] = (Math.random() - 0.5) * 30;

    particleVelocities.push(new THREE.Vector3(
        (Math.random() - 0.5) * 0.1,
        (Math.random() - 0.5) * 0.1,
        (Math.random() - 0.5) * 0.1
    ));
}

particles.setAttribute('position', new THREE.BufferAttribute(positions, 3));

// Function to get particle color based on theme
function getParticleColor() {
    const isLightMode = document.body.classList.contains('light-mode');
    return isLightMode ? 0x9D6BF5 : 0xC4B5FD; // Lighter purple for light, Pale lavender for dark
}

// Function to update particle material based on theme
function updateParticleMaterial() {
    const isLightMode = document.body.classList.contains('light-mode');
    
    if (isLightMode) {
        // Subtle in light mode
        particleMaterial.opacity = 0.4;
        particleMaterial.blending = THREE.AdditiveBlending;
        particleMaterial.size = 0.15;
    } else {
        // Glowy in dark mode
        particleMaterial.opacity = 0.8;
        particleMaterial.blending = THREE.AdditiveBlending;
        particleMaterial.size = 0.25;
    }
    particleMaterial.needsUpdate = true;
}

const particleMaterial = new THREE.PointsMaterial({
    color: getParticleColor(),
    size: 0.15,
    blending: THREE.AdditiveBlending,
    transparent: true,
    opacity: 0.4
});

updateParticleMaterial();

const particleSystem = new THREE.Points(particles, particleMaterial);
scene.add(particleSystem);

const linesGeometry = new THREE.BufferGeometry();
const linePositions = [];

const maxConnections = 2;
const connectionDistance = 5;

for (let i = 0; i < particleCount; i++) {
    let connections = 0;
    for (let j = i + 1; j < particleCount; j++) {
        const i3 = i * 3;
        const j3 = j * 3;
        const dx = positions[i3] - positions[j3];
        const dy = positions[i3 + 1] - positions[j3 + 1];
        const dz = positions[i3 + 2] - positions[j3 + 2];
        const distance = Math.sqrt(dx * dx + dy * dy + dz * dz);

        if (distance < connectionDistance && connections < maxConnections) {
            linePositions.push(positions[i3], positions[i3 + 1], positions[i3 + 2]);
            linePositions.push(positions[j3], positions[j3 + 1], positions[j3 + 2]);
            connections++;
        }
    }
}

linesGeometry.setAttribute('position', new THREE.Float32BufferAttribute(linePositions, 3));

// Function to update line colors based on theme
function updateLineColors() {
    const isLightMode = document.body.classList.contains('light-mode');
    const lineColors = [];
    
    for (let i = 0; i < linePositions.length / 3; i++) {
        if (isLightMode) {
            // Very deep purple/indigo for bold visibility
            lineColors.push(0.2, 0.05, 0.4); 
        } else {
            // Pale lavender for dark mode - shiny but thematic
            lineColors.push(0.75, 0.7, 1.0); 
        }
    }
    
    linesGeometry.setAttribute('color', new THREE.Float32BufferAttribute(lineColors, 3));
}

updateLineColors();

// Function to update line material based on theme
function updateLineMaterial() {
    const isLightMode = document.body.classList.contains('light-mode');
    linesMaterial.opacity = isLightMode ? 0.6 : 0.5; // Bolder in light mode
}

const linesMaterial = new THREE.LineBasicMaterial({
    vertexColors: true,
    blending: THREE.AdditiveBlending,
    transparent: true,
    opacity: 0.6,
    linewidth: 2 // Note: Only works in some browsers/contexts
});

updateLineMaterial();

const lines = new THREE.LineSegments(linesGeometry, linesMaterial);
scene.add(lines);

camera.position.z = 50;

let mouseX = 0;
let mouseY = 0;
document.addEventListener('mousemove', (event) => {
    mouseX = (event.clientX - window.innerWidth / 2) / 100;
    mouseY = (event.clientY - window.innerHeight / 2) / 100;
});

function animate() {
    requestAnimationFrame(animate);

    const positions = particleSystem.geometry.attributes.position.array;
    const linePositions = lines.geometry.attributes.position.array;

    for (let i = 0; i < particleCount; i++) {
        const i3 = i * 3;
        positions[i3] += particleVelocities[i].x;
        positions[i3 + 1] += particleVelocities[i].y;
        positions[i3 + 2] += particleVelocities[i].z;

        if (positions[i3] > 15 || positions[i3] < -15) particleVelocities[i].x *= -1;
        if (positions[i3 + 1] > 15 || positions[i3 + 1] < -15) particleVelocities[i].y *= -1;
        if (positions[i3 + 2] > 15 || positions[i3 + 2] < -15) particleVelocities[i].z *= -1;
    }

    let lineIdx = 0;
    for (let i = 0; i < particleCount; i++) {
        let connections = 0;
        for (let j = i + 1; j < particleCount; j++) {
            const i3 = i * 3;
            const j3 = j * 3;
            const dx = positions[i3] - positions[j3];
            const dy = positions[i3 + 1] - positions[j3 + 1];
            const dz = positions[i3 + 2] - positions[j3 + 2];
            const distance = Math.sqrt(dx * dx + dy * dy + dz * dz);

            if (distance < connectionDistance && connections < maxConnections) {
                if (lineIdx < linePositions.length) {
                    linePositions[lineIdx++] = positions[i3];
                    linePositions[lineIdx++] = positions[i3 + 1];
                    linePositions[lineIdx++] = positions[i3 + 2];
                    linePositions[lineIdx++] = positions[j3];
                    linePositions[lineIdx++] = positions[j3 + 1];
                    linePositions[lineIdx++] = positions[j3 + 2];
                }
                connections++;
            }
        }
    }

    particleSystem.geometry.attributes.position.needsUpdate = true;
    lines.geometry.attributes.position.needsUpdate = true;

    particleSystem.rotation.y += 0.001;
    lines.rotation.y += 0.001;

    camera.position.x += (mouseX - camera.position.x) * 0.05;
    camera.position.y += (-mouseY - camera.position.y) * 0.05;
    camera.lookAt(scene.position);

    renderer.render(scene, camera);
}
animate();

window.addEventListener('resize', () => {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
});

const themeToggle = document.getElementById('theme-toggle');
themeToggle.addEventListener('click', (e) => {
    e.preventDefault();
    document.body.classList.toggle('light-mode');
    
    // Update particle color and material based on new theme
    particleMaterial.color.setHex(getParticleColor());
    updateParticleMaterial();
    
    // Update line colors and material based on new theme
    updateLineColors();
    updateLineMaterial();
    lines.geometry.attributes.color.needsUpdate = true;
    
    // Update icon
    const icon = themeToggle.querySelector('i');
    if (document.body.classList.contains('light-mode')) {
        icon.classList.remove('fa-moon');
        icon.classList.add('fa-sun');
    } else {
        icon.classList.remove('fa-sun');
        icon.classList.add('fa-moon');
    }
});