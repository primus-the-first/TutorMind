<?php
require_once 'check_auth.php'; // Secure this page, only logged-in users can access
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCC CGPA Predictor | Grade Target</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="ui-overhaul.css?v=<?= time() ?>">
</head>
<body>
    <div class="page-container">
        <header class="page-header">
            <h1 class="page-title"><i class="fas fa-graduation-cap"></i> Grade Target</h1>
            <div>
                <span>Welcome, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>!</span>
                <a href="auth.php?action=logout" class="btn btn-danger" style="margin-left: 1rem;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>

        <div class="page-content">
            <!-- Course Input Section -->
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-book-open"></i> Course Details</h2>
                    <p class="text-body">Add your completed courses to calculate your current CGPA.</p>
                </div>

                <form id="cgpaForm">
                    <div class="table-container">
                        <table id="courseTable" class="table">
                            <thead>
                                <tr>
                                    <th>Course Name</th>
                                    <th>Credit Hours</th>
                                    <th>Grade</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="courseTableBody">
                                <tr class="course-row">
                                    <td><input type="text" name="course_name[]" placeholder="e.g., Mathematics 101" class="form-input" required></td>
                                    <td><input type="number" name="credit_hours[]" min="1" max="6" placeholder="3" class="form-input" required></td>
                                    <td>
                                        <select name="grade[]" class="form-select" required>
                                            <option value="">Select Grade</option>
                                            <option value="A">A (4.0)</option>
                                            <option value="B+">B+ (3.5)</option>
                                            <option value="B">B (3.0)</option>
                                            <option value="C+">C+ (2.5)</option>
                                            <option value="C">C (2.0)</option>
                                            <option value="D">D (1.0)</option>
                                            <option value="E">E (0.0)</option>
                                            <option value="F">F (0.0)</option>
                                        </select>
                                    </td>
                                    <td><button type="button" class="btn btn-danger" onclick="removeCourse(this)" disabled><i class="fas fa-trash"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="form-actions">
                        <button type="button" id="addCourseBtn" class="btn btn-secondary"><i class="fas fa-plus"></i> Add Course</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-calculator"></i> Calculate CGPA</button>
                    </div>
                </form>
            </section>

            <!-- Target Class Predictor Section -->
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-bullseye"></i> Class Path Predictor</h2>
                    <p class="text-body">Discover realistic paths to achieve your desired class.</p>
                </div>

                <div class="grid grid-cols-2">
                    <div class="form-group">
                        <label for="currentCGPA" class="form-label">Current CGPA</label>
                        <input type="number" id="currentCGPA" step="0.01" min="0" max="4" placeholder="e.g., 3.2" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="completedCredits" class="form-label">Completed Credit Hours</label>
                        <input type="number" id="completedCredits" min="0" placeholder="e.g., 72" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="remainingCourses" class="form-label">Remaining Courses</label>
                        <input type="number" id="remainingCourses" min="0" placeholder="e.g., 12" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="programType" class="form-label">Program Length</label>
                        <select id="programType" class="form-select">
                            <option value="4-year">4-Year Program</option>
                            <option value="6-year">6-Year Program</option>
                        </select>
                    </div>
                    <div class="form-group col-span-2">
                        <label for="targetClass" class="form-label">Desired Target Class</label>
                        <select id="targetClass" class="form-select">
                            <option value="Any Attainable Class">Any Attainable Class (Show All)</option>
                            <option value="First Class Honours">First Class Honours (3.55 - 4.00)</option>
                            <option value="Second Class (Upper Division)">Second Class Upper (2.95 - 3.54)</option>
                            <option value="Second Class (Lower Division)">Second Class Lower (2.45 - 2.94)</option>
                            <option value="Third Class Division">Third Class Division (1.95 - 2.44)</option>
                            <option value="Pass">Pass (1.00 - 1.94)</option>
                            <option value="Fail">Fail (0.00 - 0.99)</option>
                        </select>
                    </div>
                    <div class="form-group col-span-2">
                        <label for="topNResults" class="form-label">Number of Paths to Show</label>
                        <select id="topNResults" class="form-select">
                            <option value="3">Top 3 Paths</option>
                            <option value="5" selected>Top 5 Paths</option>
                            <option value="10">Top 10 Paths</option>
                            <option value="15">Top 15 Paths</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions" style="justify-content: center;">
                    <button type="button" id="predictBtn" class="btn btn-accent"><i class="fas fa-chart-line"></i> Predict My Class Paths</button>
                    <button type="button" id="generateStudyPlanBtn" class="btn btn-primary"><i class="fas fa-lightbulb"></i> Generate Study Plan</button>
                    <button type="button" id="getMotivationalBoostBtn" class="btn btn-secondary"><i class="fas fa-heart"></i> Get Motivational Boost</button>
                </div>
                
                <div id="predictionResult" class="hidden" style="margin-top: 2rem;"></div>
            </section>
        </div>
    </div>

    <!-- Modal -->
    <div id="geminiModal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle" class="modal-title"></h3>
                <button class="modal-close" onclick="document.getElementById('geminiModal').classList.add('hidden')">&times;</button>
            </div>
            <div id="modalBody" class="modal-body"></div>
        </div>
    </div>
    
    <script>
        // --- SCRIPT FROM OLD index.php ---
        const gradePointsMap = { 'A': 4.0, 'B+': 3.5, 'B': 3.0, 'C+': 2.5, 'C': 2.0, 'D': 1.0, 'E': 0.0, 'F': 0.0 };
        function getRoundedClassDesignation(cgpa) {
            if (cgpa >= 3.55) return "First Class Honours";
            if (cgpa >= 2.95) return "Second Class (Upper Division)";
            if (cgpa >= 2.45) return "Second Class (Lower Division)";
            if (cgpa >= 1.95) return "Third Class Division";
            if (cgpa >= 1.00) return "Pass";
            return "Fail";
        }
        document.getElementById('addCourseBtn').addEventListener('click', () => {
            const tableBody = document.getElementById('courseTableBody');
            const newRow = tableBody.querySelector('.course-row').cloneNode(true);
            newRow.querySelectorAll('input, select').forEach(input => input.value = '');
            newRow.querySelector('.btn-danger').disabled = false;
            tableBody.appendChild(newRow);
        });
        function removeCourse(button) {
            button.closest('tr').remove();
        }
        document.getElementById('cgpaForm').addEventListener('submit', function(event) {
            event.preventDefault();
            let totalCreditHours = 0, totalGradePoints = 0;
            this.querySelectorAll('.course-row').forEach(row => {
                const creditHours = parseInt(row.querySelector('input[name="credit_hours[]"]').value);
                const grade = row.querySelector('select[name="grade[]"]').value;
                if (creditHours && grade && gradePointsMap[grade] !== undefined) {
                    totalCreditHours += creditHours;
                    totalGradePoints += creditHours * gradePointsMap[grade];
                }
            });
            const currentCGPA = totalCreditHours > 0 ? totalGradePoints / totalCreditHours : 0;
            const resultDiv = document.getElementById('predictionResult');
            resultDiv.innerHTML = `<div class="card"><div class="card-header"><h3 class="card-title">Your Current CGPA is ${currentCGPA.toFixed(2)} (${getRoundedClassDesignation(currentCGPA)})</h3></div></div>`;
            resultDiv.classList.remove('hidden');
        });
        document.getElementById('predictBtn').addEventListener('click', async () => {
            const data = {
                current_cgpa: parseFloat(document.getElementById('currentCGPA').value),
                completed_credits: parseInt(document.getElementById('completedCredits').value),
                remaining_courses: parseInt(document.getElementById('remainingCourses').value),
                target_class: document.getElementById('targetClass').value,
                num_paths_to_show: parseInt(document.getElementById('topNResults').value)
            };
            if (isNaN(data.current_cgpa) || isNaN(data.completed_credits) || isNaN(data.remaining_courses)) {
                showModal('Prediction Error', 'Please enter valid numbers for all fields.');
                return;
            }
            await callPredictorAPI(data);
        });
        async function callPredictorAPI(data) {
            const predictionResultDiv = document.getElementById('predictionResult');
            predictionResultDiv.innerHTML = '<div class="loading-spinner"></div>';
            predictionResultDiv.classList.remove('hidden');
            try {
                const response = await fetch('predict.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ ...data, request_type: 'predict' })
                });
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                const result = await response.json();
                if (result.success) displayPredictionResults(result);
                else {
                    showModal('Prediction Error', result.error);
                    predictionResultDiv.classList.add('hidden');
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showModal('Network Error', 'Failed to connect to the prediction service.');
                predictionResultDiv.classList.add('hidden');
            }
        }
        function displayPredictionResults(data) {
            const resultDiv = document.getElementById('predictionResult');
            let html = '';
            if (data.status === 'no_combinations_found') {
                html = `<div class="card"><div class="card-header"><h3 class="card-title text-danger">No Realistic Combinations Found</h3><p>${data.message}</p></div></div>`;
            } else {
                const summary = data.initial_summary;
                html += `<div class="card"><div class="card-header">
                    <h3 class="card-title">Your Class Path Prediction</h3>
                    <p>Current CGPA: <strong>${summary.current_cgpa}</strong> (${summary.current_class})</p>
                    <p>Highest Attainable Class: <strong>${summary.highest_attainable_class}</strong> (Final CGPA: ${summary.max_possible_final_cgpa})</p>
                </div><div class="card-body">`;
                Object.entries(data.grade_combinations_by_class).forEach(([className, paths]) => {
                    html += `<h4>${className} Scenarios</h4>`;
                    paths.forEach(path => {
                        const finalCGPA = parseFloat(path.overall_final_cgpa_with_this_distribution);
                        const gradeDistHtml = Object.entries(path.distribution).map(([grade, count]) => `${count}x ${grade}`).join(', ');
                        html += `<div class="path-item"><p>Final CGPA: <strong>${finalCGPA.toFixed(2)}</strong> (${getRoundedClassDesignation(finalCGPA)})</p><p>Grades needed: ${gradeDistHtml}</p></div>`;
                    });
                });
                html += `</div></div>`;
            }
            resultDiv.innerHTML = html;
        }
        document.getElementById('getMotivationalBoostBtn').addEventListener('click', async () => {
            const currentCGPA = parseFloat(document.getElementById('currentCGPA').value);
            const highestPossibleCGPA = (currentCGPA * parseInt(document.getElementById('completedCredits').value) + 4.0 * (parseInt(document.getElementById('remainingCourses').value) * 3)) / (parseInt(document.getElementById('completedCredits').value) + (parseInt(document.getElementById('remainingCourses').value) * 3));
            const prompt = 
`Write a short, encouraging, and motivational message for a University of Cape Coast student.
            Their current CGPA is ${currentCGPA.toFixed(2)} (${getRoundedClassDesignation(currentCGPA)}).
            Their highest possible attainable class is ${getRoundedClassDesignation(highestPossibleCGPA)}.
            Focus on perseverance, smart work, and the potential for success. Keep it concise, around 2-3 paragraphs.
            `;
            await callGeminiAPI(prompt, 'Motivational Boost');
        });
        async function callGeminiAPI(prompt, modalTitle) {
            const modal = document.getElementById('geminiModal');
            const modalBody = document.getElementById('modalBody');
            document.getElementById('modalTitle').textContent = modalTitle;
            modalBody.innerHTML = '<div class="loading-spinner"></div>';
            modal.classList.remove('hidden');
            try {
                const response = await fetch('predict.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ prompt: prompt, request_type: 'gemini_ai' })
                });
                if (!response.ok) throw new Error(
`HTTP error! Status: ${response.status}
                `);
                const result = await response.json();
                modalBody.innerHTML = result.success ? `<p>${result.text.replace(/\n/g, '<br>')}</p>` : `<p class="text-danger">Error: ${result.error}</p>`;
            } catch (error) {
                console.error('Fetch error:', error);
                modalBody.innerHTML = `<p class="text-danger">Failed to get a response. Please check your network connection.</p>`;
            }
        }
        function showModal(title, message) {
            const modal = document.getElementById('geminiModal');
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalBody').innerHTML = `<p>${message}</p>`;
            modal.classList.remove('hidden');
        }
        window.onclick = function(event) {
            const modal = document.getElementById('geminiModal');
            if (event.target === modal) modal.classList.add('hidden');
        }
    </script>
</body>
</html>