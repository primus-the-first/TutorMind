<?php
require_once 'check_auth.php'; // Secure this page, only logged-in users can access
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCC CGPA Predictor | Grade Target</title>
    <!-- Tailwind CSS CDN for modern styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Outfit font for clean and modern typography -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Custom styles for gradients, shadows, and specific elements for a polished look */
        body {
            font-family: 'Outfit', sans-serif; /* Changed font to Outfit */
            background: linear-gradient(to bottom right, #f0f9ff, #e0f2fe); /* Soft blue gradient */
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start; /* Align to top for better scrolling on content */
            padding: 2rem 0;
        }
        .container {
            max-width: 960px; /* Increased max-width for a more spacious feel */
        }
        .header-decoration {
            background: linear-gradient(to right, #4c51bf, #6b46c1); /* Deeper purple gradient */
            height: 10px; /* Slightly thicker */
            width: 100%;
            border-radius: 0 0 15px 15px; /* More pronounced rounded corners */
        }
        .btn-primary {
            background: linear-gradient(to right, #10b981, #059669); /* Emerald green gradient */
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
        }
        .btn-primary:hover {
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5);
            transform: translateY(-2px);
        }
        .btn-accent {
            background: linear-gradient(to right, #6366f1, #8b5cf6); /* Indigo to purple gradient */
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }
        .btn-accent:hover {
            box-shadow: 0 6px 15px rgba(99, 102, 241, 0.5);
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: linear-gradient(to right, #f97316, #ea580c); /* Vibrant orange gradient */
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(249, 115, 22, 0.3);
        }
        .btn-secondary:hover {
            box-shadow: 0 6px 15px rgba(249, 115, 22, 0.5);
            transform: translateY(-2px);
        }
        .btn-remove {
            background-color: #ef4444; /* Red */
            transition: background-color 0.2s ease;
            box-shadow: 0 2px 5px rgba(239, 68, 68, 0.2);
        }
        .btn-remove:hover {
            background-color: #dc2626;
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.4);
        }
        .card {
            border-radius: 15px; /* More rounded corners for cards */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); /* Softer, larger shadow */
            padding: 2.5rem; /* More internal padding */
        }
        .modal {
            background-color: rgba(0, 0, 0, 0.7); /* Darker overlay for modal */
        }
        .modal-content {
            background-color: #ffffff;
            border-radius: 20px; /* Very rounded modal */
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
            max-height: 90vh;
            overflow-y: auto;
            animation: fadeInScale 0.3s ease-out forwards; /* Add animation */
        }
        .modal-header {
            background: linear-gradient(to right, #4c51bf, #6b46c1); /* Matching header gradient */
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 1.5rem;
        }
        .modal-close {
            background: none;
            color: white;
            font-size: 2rem; /* Larger close button */
            line-height: 1;
            transition: transform 0.2s ease;
        }
        .modal-close:hover {
            transform: rotate(90deg);
        }
        .grade-item, .class-item {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background-color: #f8fafc; /* Light background for grid items */
            border: 1px solid #e2e8f0; /* Subtle border */
        }
        .grade-item:hover, .class-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        /* Specific colors for grade items */
        .grade-a { background-color: #dcfce7; color: #16a34a; }
        .grade-b-plus { background-color: #e0f2fe; color: #2563eb; }
        .grade-b { background-color: #e0f7fa; color: #00bcd4; }
        .grade-c-plus { background-color: #fffde7; color: #facc15; }
        .grade-c { background-color: #fff3e0; color: #fb923c; }
        .grade-d { background-color: #ffebee; color: #ef4444; }
        .grade-f { background-color: #fce7f3; color: #ec4899; }

        /* Specific colors for class items */
        .first-class { background-color: #fef3c7; color: #d97706; }
        .second-upper { background-color: #e0f2fe; color: #2563eb; }
        .second-lower { background-color: #dbeafe; color: #3b82f6; }
        .third-class { background-color: #e5e7eb; color: #4b5563; }
        .pass { background-color: #dcfce7; color: #16a34a; }

        /* Table styling */
        .course-table th, .course-table td {
            padding: 1rem; /* More padding for table cells */
            border-bottom: 1px solid #e5e7eb;
        }
        .course-table th {
            background-color: #f8fafc; /* Lighter header background */
            text-align: left;
            font-weight: 600;
            color: #4b5563;
        }
        .course-table tbody tr:last-child td {
            border-bottom: none;
        }
        .course-table input, .course-table select {
            width: 100%;
            padding: 0.65rem; /* Slightly more padding */
            border: 1px solid #cbd5e1; /* Slightly darker border */
            border-radius: 0.5rem; /* More rounded */
            font-size: 0.95rem; /* Slightly larger font */
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .course-table input:focus, .course-table select:focus {
            border-color: #6366f1; /* Indigo focus color */
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        .table-container {
            overflow-x: auto; /* Enable horizontal scrolling on small screens */
            width: 100%;
            border-radius: 12px; /* Rounded corners for table container */
            border: 1px solid #e2e8f0; /* Subtle border for the table container */
        }
        .prediction-table th, .prediction-table td {
            padding: 1rem;
            border: 1px solid #e5e7eb;
            text-align: center;
        }
        .prediction-table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #4b5563;
        }
        .prediction-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5rem;
            border-radius: 12px;
            overflow: hidden; /* Ensures rounded corners apply to table content */
        }
        .loading-spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left-color: #6366f1; /* Indigo spinner */
            border-radius: 50%;
            width: 40px; /* Larger spinner */
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 30px auto; /* More space */
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .container {
                padding: 0 1rem;
            }
            .main-content {
                padding: 1.5rem;
            }
            .card {
                padding: 1.5rem;
            }
            .section-header h2 {
                font-size: 1.75rem;
            }
            .section-header p {
                font-size: 0.9rem;
            }
            .form-actions, .predictor-form > div, .flex-col.sm:flex-row {
                flex-direction: column;
                align-items: stretch;
            }
            .form-actions button, .predictor-form button {
                width: 100%;
            }
            .space-y-4.sm:space-y-0.sm:space-x-4 {
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            .grade-grid, .class-grid {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }
            .modal-content {
                width: 95%;
                margin: 0 auto;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center py-8">
    <div class="container mx-auto bg-white shadow-xl rounded-xl overflow-hidden">
        <!-- Header Section -->
        <header class="header bg-white rounded-t-xl border-b border-gray-200">
            <div class="header-content p-6 flex items-center justify-between">
                <div class="logo flex items-center text-indigo-600">
                    <i class="fas fa-graduation-cap text-4xl mr-3"></i>
                    <h1 class="text-3xl font-bold">Grade Target</h1>
                </div>
                <div class="user-actions flex items-center gap-4">
                    <a href="profile.php" class="text-gray-600 hover:text-indigo-600 transition">
                        Welcome, <strong class="font-semibold"><?= htmlspecialchars($_SESSION['username']) ?></strong>!
                    </a>
                    <a href="auth.php?action=logout" class="btn-remove px-4 py-2 text-white font-semibold rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-75">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content p-8 space-y-10">
            <!-- Course Input Section - Retained for individual CGPA calculation -->
            <section class="card bg-white p-6 rounded-lg shadow-md">
                <div class="section-header mb-8 text-center">
                    <h2 class="text-3xl font-semibold text-gray-800 mb-3"><i class="fas fa-book-open text-indigo-500 mr-3"></i> Course Details</h2>
                    <p class="text-gray-600 text-lg">Add your completed courses to calculate your current CGPA</p>
                </div>

                <form id="cgpaForm" class="space-y-6">
                    <div class="table-container">
                        <table id="courseTable" class="course-table min-w-full bg-white rounded-lg overflow-hidden">
                            <thead>
                                <tr>
                                    <th class="py-4 px-5 bg-gray-50 text-left text-sm font-medium text-gray-500 uppercase tracking-wider rounded-tl-lg">
                                        <i class="fas fa-book mr-2"></i> Course Name
                                    </th>
                                    <th class="py-4 px-5 bg-gray-50 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">
                                        <i class="fas fa-clock mr-2"></i> Credit Hours
                                    </th>
                                    <th class="py-4 px-5 bg-gray-50 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">
                                        <i class="fas fa-star mr-2"></i> Grade
                                    </th>
                                    <th class="py-4 px-5 bg-gray-50 text-left text-sm font-medium text-gray-500 uppercase tracking-wider rounded-tr-lg">
                                        <i class="fas fa-trash mr-2"></i> Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="courseTableBody" class="divide-y divide-gray-200">
                                <tr class="course-row">
                                    <td class="py-4 px-5">
                                        <input type="text" name="course_name[]" placeholder="e.g., Mathematics 101" class="form-input block w-full" required>
                                    </td>
                                    <td class="py-4 px-5">
                                        <input type="number" name="credit_hours[]" min="1" max="6" placeholder="3" class="form-input block w-full" required>
                                    </td>
                                    <td class="py-4 px-5">
                                        <select name="grade[]" class="form-select block w-full" required>
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
                                    <td class="py-4 px-5">
                                        <button type="button" class="btn-remove px-4 py-2 text-white font-semibold rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-75" onclick="removeCourse(this)" disabled>
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="form-actions flex flex-col sm:flex-row justify-end space-y-4 sm:space-y-0 sm:space-x-4 mt-8">
                        <button type="button" id="addCourseBtn" class="btn btn-secondary px-7 py-3 text-white font-semibold rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-opacity-75">
                            <i class="fas fa-plus mr-2"></i> Add Course
                        </button>
                        <button type="submit" class="btn btn-primary px-7 py-3 text-white font-semibold rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-75">
                            <i class="fas fa-calculator mr-2"></i> Calculate CGPA
                        </button>
                    </div>
                </form>
            </section>

            <!-- Target Class Predictor Section -->
            <section class="card bg-white p-6 rounded-lg shadow-md">
                <div class="section-header mb-8 text-center">
                    <h2 class="text-3xl font-semibold text-gray-800 mb-3"><i class="fas fa-bullseye text-indigo-500 mr-3"></i> Class Path Predictor</h2>
                    <p class="text-gray-600 text-lg">Discover realistic paths to achieve your desired class</p>
                </div>

                <div class="predictor-form grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="input-group">
                        <label for="currentCGPA" class="block text-gray-700 text-base font-medium mb-2">Current CGPA</label>
                        <input type="number" id="currentCGPA" step="0.01" min="0" max="4" placeholder="e.g., 3.2" class="form-input block w-full">
                    </div>
                    <div class="input-group">
                        <label for="completedCredits" class="block text-gray-700 text-base font-medium mb-2">Completed Credit Hours</label>
                        <input type="number" id="completedCredits" min="0" placeholder="e.g., 72" class="form-input block w-full">
                    </div>
                    <div class="input-group">
                        <label for="remainingCourses" class="block text-gray-700 text-base font-medium mb-2">Remaining Courses</label>
                        <input type="number" id="remainingCourses" min="0" placeholder="e.g., 12" class="form-input block w-full">
                    </div>
                    <div class="input-group">
                        <label for="programType" class="block text-gray-700 text-base font-medium mb-2">Program Length</label>
                        <select id="programType" class="form-select block w-full">
                            <option value="4-year">4-Year Program</option>
                            <option value="6-year">6-Year Program</option>
                        </select>
                    </div>
                    <!-- New: Desired Target Class input -->
                    <div class="input-group col-span-1 md:col-span-2">
                        <label for="targetClass" class="block text-gray-700 text-base font-medium mb-2">Desired Target Class</label>
                        <select id="targetClass" class="form-select block w-full">
                            <option value="Any Attainable Class">Any Attainable Class (Show All)</option>
                            <option value="First Class Honours">First Class Honours (3.55 - 4.00)</option>
                            <option value="Second Class (Upper Division)">Second Class Upper (2.95 - 3.54)</option>
                            <option value="Second Class (Lower Division)">Second Class Lower (2.45 - 2.94)</option>
                            <option value="Third Class Division">Third Class Division (1.95 - 2.44)</option>
                            <option value="Pass">Pass (1.00 - 1.94)</option>
                            <option value="Fail">Fail (0.00 - 0.99)</option>
                        </select>
                    </div>
                    <div class="input-group col-span-1 md:col-span-2">
                        <label for="topNResults" class="block text-gray-700 text-base font-medium mb-2">Number of Paths to Show</label>
                        <select id="topNResults" class="form-select block w-full">
                            <option value="3">Top 3 Paths</option>
                            <option value="5" selected>Top 5 Paths</option>
                            <option value="10">Top 10 Paths</option>
                            <option value="15">Top 15 Paths</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row justify-center space-y-6 sm:space-y-0 sm:space-x-6 mt-10">
                    <button type="button" id="predictBtn" class="btn btn-accent px-10 py-4 text-white font-bold text-xl rounded-full shadow-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-opacity-75 transform transition-transform duration-200 hover:scale-105">
                        <i class="fas fa-chart-line mr-3"></i> Predict My Class Paths
                    </button>
                    <button type="button" id="generateStudyPlanBtn" class="btn btn-primary px-10 py-4 text-white font-bold text-xl rounded-full shadow-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-75 transform transition-transform duration-200 hover:scale-105">
                        <i class="fas fa-lightbulb mr-3"></i> Generate Study Plan ✨
                    </button>
                    <button type="button" id="getMotivationalBoostBtn" class="btn btn-secondary px-10 py-4 text-white font-bold text-xl rounded-full shadow-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-opacity-75 transform transition-transform duration-200 hover:scale-105">
                        <i class="fas fa-heart mr-3"></i> Get Motivational Boost ✨
                    </button>
                </div>
                
                <div id="predictionResult" class="prediction-result hidden mt-10 p-6 bg-blue-50 rounded-lg border border-blue-200 text-gray-800">
                    <!-- Prediction results will be displayed here -->
                </div>

            </section>

            <!-- Grading Scale Reference -->
            <section class="card bg-white p-6 rounded-lg shadow-md">
                <div class="section-header mb-8 text-center">
                    <h2 class="text-3xl font-semibold text-gray-800 mb-3"><i class="fas fa-list-ul text-indigo-500 mr-3"></i> Grading Scale</h2>
                    <p class="text-gray-600 text-lg">Understand the grades and their equivalent points</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                    <div class="grade-item p-5 text-center">
                        <h4 class="font-bold text-2xl text-green-600">A</h4>
                        <p class="text-xl text-gray-600">4.0</p>
                    </div>
                    <div class="grade-item p-5 text-center">
                        <h4 class="font-bold text-2xl text-blue-600">B+</h4>
                        <p class="text-xl text-gray-600">3.5</p>
                    </div>
                    <div class="grade-item p-5 text-center">
                        <h4 class="font-bold text-2xl text-cyan-500">B</h4>
                        <p class="text-xl text-gray-600">3.0</p>
                    </div>
                    <div class="grade-item p-5 text-center">
                        <h4 class="font-bold text-2xl text-yellow-500">C+</h4>
                        <p class="text-xl text-gray-600">2.5</p>
                    </div>
                    <div class="grade-item p-5 text-center">
                        <h4 class="font-bold text-2xl text-orange-500">C</h4>
                        <p class="text-xl text-gray-600">2.0</p>
                    </div>
                    <div class="grade-item p-5 text-center">
                        <h4 class="font-bold text-2xl text-red-500">D</h4>
                        <p class="text-xl text-gray-600">1.0</p>
                    </div>
                    <div class="grade-item p-5 text-center">
                        <h4 class="font-bold text-2xl text-pink-500">E/F</h4>
                        <p class="text-xl text-gray-600">0.0</p>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- The Modal for the Motivational Boost -->
    <div id="geminiModal" class="modal fixed inset-0 flex items-center justify-center p-4 z-50 hidden">
        <div class="modal-content w-full max-w-xl mx-auto p-6 relative">
            <button class="modal-close absolute top-4 right-6 text-white text-3xl leading-none">&times;</button>
            <div class="modal-header text-center p-6 mb-6">
                <h3 id="modalTitle" class="text-3xl font-bold text-white">Motivational Boost</h3>
            </div>
            <div id="modalBody" class="p-6 text-gray-700 text-lg space-y-4">
                <!-- Content will be injected here -->
            </div>
            <div class="flex justify-center p-6">
                <button class="btn-primary px-6 py-2 text-white font-semibold rounded-lg shadow-md" onclick="document.getElementById('geminiModal').classList.add('hidden')">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        // Mapping of grades to points
        const gradePointsMap = {
            'A': 4.0, 'B+': 3.5, 'B': 3.0, 'C+': 2.5, 'C': 2.0, 'D': 1.0, 'E': 0.0, 'F': 0.0
        };

        // Function to determine class designation with specific rounding rules (to match PHP)
        function getRoundedClassDesignation(cgpa) {
            if (cgpa >= 3.55) return "First Class Honours";
            if (cgpa >= 2.95) return "Second Class (Upper Division)";
            if (cgpa >= 2.45) return "Second Class (Lower Division)";
            if (cgpa >= 1.95) return "Third Class Division";
            if (cgpa >= 1.00) return "Pass";
            return "Fail";
        }
        
        // --- Course Input Section Logic ---
        document.getElementById('addCourseBtn').addEventListener('click', () => {
            const tableBody = document.getElementById('courseTableBody');
            const newRow = tableBody.querySelector('.course-row').cloneNode(true);
            const inputs = newRow.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.value = '';
            });
            newRow.querySelector('.btn-remove').disabled = false;
            tableBody.appendChild(newRow);
        });

        function removeCourse(button) {
            const row = button.closest('tr');
            row.remove();
        }

        document.getElementById('cgpaForm').addEventListener('submit', function(event) {
            event.preventDefault();
            
            const rows = this.querySelectorAll('.course-row');
            let totalCreditHours = 0;
            let totalGradePoints = 0;

            rows.forEach(row => {
                const creditHours = parseInt(row.querySelector('input[name="credit_hours[]"]').value);
                const grade = row.querySelector('select[name="grade[]"]').value;

                if (creditHours && grade && gradePointsMap[grade] !== undefined) {
                    totalCreditHours += creditHours;
                    totalGradePoints += creditHours * gradePointsMap[grade];
                }
            });

            const currentCGPA = totalCreditHours > 0 ? totalGradePoints / totalCreditHours : 0;
            const currentClass = getRoundedClassDesignation(currentCGPA);

            // Display the results
            const resultDiv = document.getElementById('predictionResult');
            resultDiv.innerHTML = `
                <h3 class="text-2xl font-semibold text-center mb-4">Your Current CGPA</h3>
                <div class="text-center text-xl space-y-2">
                    <p>Current CGPA: <span class="font-bold text-indigo-600">${currentCGPA.toFixed(2)}</span></p>
                    <p>Current Class: <span class="font-bold text-indigo-600">${currentClass}</span></p>
                </div>
            `;
            resultDiv.classList.remove('hidden');
        });

        // --- Class Predictor Section Logic ---
        document.getElementById('predictBtn').addEventListener('click', async () => {
            const currentCGPA = parseFloat(document.getElementById('currentCGPA').value);
            const completedCredits = parseInt(document.getElementById('completedCredits').value);
            const remainingCourses = parseInt(document.getElementById('remainingCourses').value);
            const targetClass = document.getElementById('targetClass').value;
            const numPathsToShow = parseInt(document.getElementById('topNResults').value);

            // Simple validation
            if (isNaN(currentCGPA) || isNaN(completedCredits) || isNaN(remainingCourses) || currentCGPA < 0 || currentCGPA > 4 || completedCredits < 0 || remainingCourses < 0) {
                showModal('Prediction Error', 'Please enter valid numbers for all fields.');
                return;
            }
            
            await callPredictorAPI({
                current_cgpa: currentCGPA,
                completed_credits: completedCredits,
                remaining_courses: remainingCourses,
                target_class: targetClass,
                num_paths_to_show: numPathsToShow
            });
        });

        async function callPredictorAPI(data) {
            const predictionResultDiv = document.getElementById('predictionResult');
            predictionResultDiv.innerHTML = '<div class="loading-spinner"></div>';
            predictionResultDiv.classList.remove('hidden');

            const payload = new URLSearchParams({
                ...data,
                request_type: 'predict'
            });

            try {
                const response = await fetch('predict.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: payload
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }

                const result = await response.json();
                
                if (result.success) {
                    displayPredictionResults(result);
                } else {
                    showModal('Prediction Error', result.error);
                    predictionResultDiv.classList.add('hidden');
                }

            } catch (error) {
                console.error('Fetch error:', error);
                showModal('Network Error', 'Failed to connect to the prediction service. Please try again.');
                predictionResultDiv.classList.add('hidden');
            }
        }
        
        function displayPredictionResults(data) {
            const resultDiv = document.getElementById('predictionResult');
            let html = '';

            // Handle the case where no combinations were found
            if (data.status === 'no_combinations_found') {
                html = `
                    <div class="text-center space-y-4">
                        <h3 class="text-2xl font-semibold text-red-600 mb-2">No Realistic Combinations Found</h3>
                        <p class="text-lg">${data.message}</p>
                    </div>
                `;
            } else {
                const summary = data.initial_summary;
                html += `
                    <div class="text-center mb-8">
                        <h3 class="text-2xl font-semibold text-gray-800 mb-4">Your Class Path Prediction</h3>
                        <p class="text-lg text-gray-600">
                            Current CGPA: <span class="font-bold text-indigo-600">${summary.current_cgpa}</span> (${summary.current_class})
                        </p>
                        <p class="text-lg text-gray-600">
                            Credits: ${summary.credits_completed} completed, ${summary.credits_remaining} remaining
                        </p>
                        <p class="text-lg text-gray-600">
                            Highest Attainable Class: <span class="font-bold text-emerald-600">${summary.highest_attainable_class}</span> (Final CGPA: ${summary.max_possible_final_cgpa})
                        </p>
                    </div>
                    <div id="scenariosContainer">
                        ${Object.entries(data.grade_combinations_by_class).map(([className, paths]) => {
                            let classHtml = `<h4 class="text-xl font-bold text-indigo-700 mb-4">${className} Scenarios</h4>`;
                            classHtml += `<div class="space-y-6">`;
                            paths.forEach(path => {
                                const finalCGPA = parseFloat(path.overall_final_cgpa_with_this_distribution);
                                const finalClass = getRoundedClassDesignation(finalCGPA);
                                const gradeDistHtml = Object.entries(path.distribution).map(([grade, count]) => `${count}x ${grade}`).join(', ');
                                
                                classHtml += `
                                    <div class="p-4 bg-gray-50 rounded-lg shadow-sm border border-gray-200">
                                        <p class="text-lg font-semibold text-gray-800">
                                            Final CGPA: <span class="text-blue-600">${finalCGPA.toFixed(2)}</span>
                                            <span class="text-sm text-gray-500 ml-2">(${finalClass})</span>
                                        </p>
                                        <p class="text-md text-gray-700 mt-1">
                                            Grades needed in remaining courses:
                                            <span class="font-medium text-purple-600">${gradeDistHtml}</span>
                                        </p>
                                    </div>
                                `;
                            });
                            classHtml += `</div>`;
                            return classHtml;
                        }).join('')}
                    </div>
                `;
            }
            
            resultDiv.innerHTML = html;
        }

        // --- Gemini API Logic for Motivational Boost ---
        document.getElementById('getMotivationalBoostBtn').addEventListener('click', async () => {
            const currentCGPA = parseFloat(document.getElementById('currentCGPA').value);
            const completedCredits = parseInt(document.getElementById('completedCredits').value);
            const remainingCourses = parseInt(document.getElementById('remainingCourses').value);
            const programType = document.getElementById('programType').value;
            const creditsPerCourse = 3; // Assuming a standard 3 credit hours per course
            const totalCredits = completedCredits + (remainingCourses * creditsPerCourse);
            const currentClass = getRoundedClassDesignation(currentCGPA);

            const highestPossibleCGPA = (currentCGPA * completedCredits + 4.0 * (remainingCourses * creditsPerCourse)) / totalCredits;
            const highestPossibleClass = getRoundedClassDesignation(highestPossibleCGPA);

            const prompt = `Write a short, encouraging, and motivational message for a University of Cape Coast student.
            Their current CGPA is ${currentCGPA.toFixed(2)} (${currentClass}).
            They have ${remainingCourses} courses remaining in their ${programType} program.
            Their highest possible attainable class is ${highestPossibleClass}.
            Focus on perseverance, smart work, and the potential for success. Keep it concise, around 2-3 paragraphs.`;
            
            await callGeminiAPI(prompt, 'Motivational Boost');
        });

        // Re-usable function to call the Gemini API for any prompt
        async function callGeminiAPI(prompt, modalTitle) {
            const modal = document.getElementById('geminiModal');
            const modalBody = document.getElementById('modalBody');
            const titleElement = document.getElementById('modalTitle');
            
            titleElement.textContent = modalTitle;
            modalBody.innerHTML = '<div class="loading-spinner"></div>';
            modal.classList.remove('hidden');

            const payload = new URLSearchParams({
                prompt: prompt,
                request_type: 'gemini_ai'
            });

            try {
                const response = await fetch('predict.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: payload
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }

                const result = await response.json();
                
                if (result.success) {
                    modalBody.innerHTML = `<p>${result.text.replace(/\n/g, '<br>')}</p>`;
                } else {
                    modalBody.innerHTML = `<p class="text-red-600">Error: ${result.error}</p>`;
                }

            } catch (error) {
                console.error('Fetch error:', error);
                modalBody.innerHTML = `<p class="text-red-600">Failed to get a response. Please check your network connection.</p>`;
            }
        }
        
        // --- Modal Logic ---
        function showModal(title, message) {
            const modal = document.getElementById('geminiModal');
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalBody').innerHTML = `<p>${message}</p>`;
            modal.classList.remove('hidden');
        }

        document.querySelector('.modal-close').addEventListener('click', () => {
            document.getElementById('geminiModal').classList.add('hidden');
        });
        
        window.onclick = function(event) {
            const modal = document.getElementById('geminiModal');
            if (event.target === modal) {
                modal.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
