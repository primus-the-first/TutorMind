// CGPA Calculator JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // Initialize event listeners
    document.getElementById('addCourseBtn').addEventListener('click', addCourse);
    document.getElementById('predictBtn').addEventListener('click', predictTargetGPA);
    document.getElementById('cgpaForm').addEventListener('submit', handleFormSubmit);
    
    // Initialize modal close functionality
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeModal();
        }
    });
    
    // Initialize escape key to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
    
    // Update remove button states
    updateRemoveButtons();
}

function addCourse() {
    const tableBody = document.getElementById('courseTableBody');
    const newRow = createCourseRow();
    
    // Add animation class
    newRow.classList.add('adding');
    tableBody.appendChild(newRow);
    
    // Remove animation class after animation completes
    setTimeout(() => {
        newRow.classList.remove('adding');
    }, 300);
    
    updateRemoveButtons();
    
    // Focus on the first input of the new row
    const firstInput = newRow.querySelector('input[name="course_name[]"]');
    if (firstInput) {
        firstInput.focus();
    }
}

function createCourseRow() {
    const row = document.createElement('tr');
    row.className = 'course-row';
    row.innerHTML = `
        <td>
            <input type="text" name="course_name[]" placeholder="e.g., Mathematics 101" class="form-input" required>
        </td>
        <td>
            <input type="number" name="credit_hours[]" min="1" max="6" placeholder="3" class="form-input" required>
        </td>
        <td>
            <select name="grade[]" class="form-select" required>
                <option value="">Select Grade</option>
                <option value="A">A (Excellent)</option>
                <option value="B+">B+ (Very Good)</option>
                <option value="B">B (Good)</option>
                <option value="C+">C+ (Fairly Good)</option>
                <option value="C">C (Average)</option>
                <option value="D">D (Pass)</option>
                <option value="E">E (Fail)</option>
                <option value="F">F (Fail)</option>
            </select>
        </td>
        <td>
            <button type="button" class="btn-remove" onclick="removeCourse(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    return row;
}

function removeCourse(button) {
    const row = button.closest('tr');
    const tableBody = document.getElementById('courseTableBody');
    
    if (tableBody.children.length <= 1) {
        return; // Don't remove the last row
    }
    
    // Add removing animation
    row.classList.add('removing');
    
    // Remove the row after animation
    setTimeout(() => {
        row.remove();
        updateRemoveButtons();
    }, 300);
}

function updateRemoveButtons() {
    const tableBody = document.getElementById('courseTableBody');
    const removeButtons = tableBody.querySelectorAll('.btn-remove');
    
    // Disable remove button if only one row exists
    removeButtons.forEach(button => {
        button.disabled = tableBody.children.length <= 1;
    });
}

function handleFormSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const button = e.target.querySelector('button[type="submit"]');
    
    // Add loading state
    button.classList.add('loading');
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calculating...';
    
    // Simulate form submission to PHP
    fetch('calculate.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        displayResults(data);
    })
    .catch(error => {
        console.error('Error:', error);
        // For demo purposes, calculate on frontend
        calculateCGPAFrontend(formData);
    })
    .finally(() => {
        // Remove loading state
        button.classList.remove('loading');
        button.innerHTML = '<i class="fas fa-calculator"></i> Calculate CGPA';
    });
}

function calculateCGPAFrontend(formData) {
    const courses = [];
    const courseNames = formData.getAll('course_name[]');
    const creditHours = formData.getAll('credit_hours[]');
    const grades = formData.getAll('grade[]');
    
    let totalGradePoints = 0;
    let totalCredits = 0;
    
    const gradeValues = {
        'A': 4.0, 'B+': 3.5, 'B': 3.0, 'C+': 2.5,
        'C': 2.0, 'D': 1.0, 'E': 0.0, 'F': 0.0
    };
    
    for (let i = 0; i < courseNames.length; i++) {
        const credits = parseFloat(creditHours[i]);
        const gradePoint = gradeValues[grades[i]];
        const courseGradePoints = credits * gradePoint;
        
        courses.push({
            name: courseNames[i],
            credits: credits,
            grade: grades[i],
            gradePoint: gradePoint,
            courseGradePoints: courseGradePoints
        });
        
        totalCredits += credits;
        totalGradePoints += courseGradePoints;
    }
    
    const cgpa = totalCredits > 0 ? (totalGradePoints / totalCredits) : 0;
    const classification = getClassification(cgpa);
    
    const results = {
        success: true,
        cgpa: cgpa.toFixed(2),
        classification: classification,
        totalCredits: totalCredits,
        totalGradePoints: totalGradePoints.toFixed(2),
        courses: courses,
        targetAdvice: getTargetAdvice(cgpa)
    };
    
    displayResults(results);
}

function getClassification(cgpa) {
    if (cgpa >= 3.6) return { name: 'First Class', color: '#ffd700', icon: 'fas fa-crown' };
    if (cgpa >= 3.0) return { name: 'Second Class Upper', color: '#00c851', icon: 'fas fa-medal' };
    if (cgpa >= 2.5) return { name: 'Second Class Lower', color: '#39c0ed', icon: 'fas fa-award' };
    if (cgpa >= 2.0) return { name: 'Third Class', color: '#ffbb33', icon: 'fas fa-certificate' };
    if (cgpa >= 1.0) return { name: 'Pass', color: '#ff8800', icon: 'fas fa-check' };
    return { name: 'Fail (No Award)', color: '#dc3545', icon: 'fas fa-times' };
}

function getTargetAdvice(cgpa) {
    if (cgpa < 3.6) {
        return {
            show: true,
            message: `To achieve First Class (3.6+ CGPA), you'll need to maintain higher grades in your remaining courses.`,
            target: 'First Class'
        };
    }
    return { show: false };
}

function displayResults(data) {
    if (!data.success) {
        alert('Error calculating CGPA. Please check your inputs.');
        return;
    }
    
    const modalResults = document.getElementById('modalResults');
    const classification = data.classification;
    
    modalResults.innerHTML = `
        <div class="result-display">
            <div class="cgpa-display">
                <div class="cgpa-value">${data.cgpa}</div>
                <div class="cgpa-label">Your CGPA</div>
            </div>
            
            <div class="class-display" style="background: ${classification.color};">
                <i class="${classification.icon}"></i>
                ${classification.name}
            </div>
            
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0;">
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 1.5rem; font-weight: 600; color: #667eea;">${data.totalCredits}</div>
                    <div style="font-size: 0.9rem; color: #666;">Total Credits</div>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 1.5rem; font-weight: 600; color: #764ba2;">${data.totalGradePoints}</div>
                    <div style="font-size: 0.9rem; color: #666;">Grade Points</div>
                </div>
            </div>
            
            ${data.targetAdvice && data.targetAdvice.show ? `
                <div class="target-advice" style="background: linear-gradient(135deg, #fa709a, #fee140); color: #fff; padding: 20px; border-radius: 12px; margin: 20px 0;">
                    <h4><i class="fas fa-lightbulb"></i> Improvement Tip</h4>
                    <p>${data.targetAdvice.message}</p>
                </div>
            ` : ''}
            
            <div class="course-breakdown">
                <h4><i class="fas fa-list"></i> Course Breakdown</h4>
                <div class="course-list">
                    ${data.courses.map(course => `
                        <div class="course-item">
                            <div class="course-name">${course.name}</div>
                            <div class="course-details">
                                <span>${course.credits} credits</span>
                                <span>Grade: ${course.grade}</span>
                                <span>${course.gradePoint.toFixed(1)} points</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('resultModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('resultModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function predictTargetGPA() {
    const currentCGPA = parseFloat(document.getElementById('currentCGPA').value);
    const completedCredits = parseFloat(document.getElementById('completedCredits').value);
    const remainingCredits = parseFloat(document.getElementById('remainingCredits').value);
    const courseCreditValue = parseFloat(document.getElementById('courseCreditValue').value) || 3;
    const targetClass = document.getElementById('targetClass').value;
    const maxAs = document.getElementById('maxAs').value ? parseInt(document.getElementById('maxAs').value) : null;
    const excludeAllA = document.getElementById('excludeAllA').checked;
    const skipCGrades = document.getElementById('skipCGrades').checked;
    const topNResults = parseInt(document.getElementById('topNResults').value) || 5;
    
    if (!currentCGPA || !completedCredits || !remainingCredits || !targetClass) {
        alert('Please fill in all required fields for target prediction.');
        return;
    }
    
    // Add loading state
    const button = document.getElementById('predictBtn');
    button.classList.add('loading');
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calculating Paths...';
    
    // Prepare form data for PHP
    const formData = new FormData();
    formData.append('current_cgpa', currentCGPA);
    formData.append('completed_credits', completedCredits);
    formData.append('remaining_credits', remainingCredits);
    formData.append('course_credit_value', courseCreditValue);
    formData.append('target_class', targetClass);
    if (maxAs !== null) formData.append('max_as', maxAs);
    formData.append('exclude_all_a', excludeAllA);
    formData.append('skip_c_grades', skipCGrades);
    formData.append('top_n_results', topNResults);
    
    // Call PHP backend
    fetch('predict.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayAdvancedPredictionResults(data.results, targetClass, data.neededAvgGPA);
        } else {
            showErrorMessage(data.error || 'Failed to calculate predictions');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Fallback to client-side calculation
        calculateClientSide(currentCGPA, completedCredits, remainingCredits, courseCreditValue,
                           targetClass, maxAs, excludeAllA, skipCGrades, topNResults);
    })
    .finally(() => {
        // Remove loading state
        button.classList.remove('loading');
        button.innerHTML = '<i class="fas fa-magic"></i> Predict Required GPA';
    });
}

function calculateClientSide(currentCGPA, completedCredits, remainingCredits, courseCreditValue,
                            targetClass, maxAs, excludeAllA, skipCGrades, topNResults) {
    
    // UCC Grade Points and Class Thresholds
    const gradePointsMap = {
        'A': 4.0,
        'B+': 3.5,
        'B': 3.0,
        'C+': 2.5,
        'C': 2.0
    };
    
    const classThresholds = {
        'first': 3.60,
        'second_upper': 3.00,
        'second_lower': 2.50,
        'third': 2.00
    };
    
    const targetGPA = classThresholds[targetClass];
    const totalCredits = completedCredits + remainingCredits;
    const totalCourses = Math.floor(remainingCredits / courseCreditValue);
    
    const earnedPoints = currentCGPA * completedCredits;
    const requiredTotalPoints = targetGPA * totalCredits;
    const remainingPointsNeeded = requiredTotalPoints - earnedPoints;
    
    // Check if target is mathematically possible
    if (remainingPointsNeeded / remainingCredits > 4.0) {
        displayAdvancedPredictionResults(["Target class is mathematically impossible"], targetClass, 0);
        return;
    }
    
    const neededAvgGPA = remainingPointsNeeded / remainingCredits;
    
    const results = predictClassPaths(
        currentCGPA, completedCredits, remainingCredits, courseCreditValue,
        targetClass, maxAs, excludeAllA, skipCGrades, topNResults,
        gradePointsMap, classThresholds
    );
    
    displayAdvancedPredictionResults(results, targetClass, neededAvgGPA);
}

function showErrorMessage(message) {
    const resultDiv = document.getElementById('predictionResult');
    resultDiv.innerHTML = `
        <div style="background: linear-gradient(135deg, #ff6b6b, #ffa500); color: #fff; padding: 25px; border-radius: 12px; text-align: center;">
            <h3><i class="fas fa-exclamation-triangle"></i> Error</h3>
            <p style="font-size: 1.1rem; margin-top: 15px;">${message}</p>
        </div>
    `;
    resultDiv.classList.remove('hidden');
}

function predictClassPaths(currentCGPA, completedCredits, remainingCredits, courseCreditValue,
                          targetClass, maxAs, excludeAllA, skipCGrades, topNResults,
                          gradePointsMap, classThresholds) {
    
    const totalCredits = completedCredits + remainingCredits;
    const totalCourses = Math.floor(remainingCredits / courseCreditValue);
    const targetGPA = classThresholds[targetClass];
    
    const earnedPoints = currentCGPA * completedCredits;
    const requiredTotalPoints = targetGPA * totalCredits;
    const remainingPointsNeeded = requiredTotalPoints - earnedPoints;
    const neededAvgGPA = remainingPointsNeeded / remainingCredits;
    
    const validCombinations = [];
    const grades = ['A', 'B+', 'B', 'C+', 'C'];
    
    function backtrack(path, totalPoints, gradeCountMap, depth) {
        if (depth === totalCourses) {
            const avg = totalPoints / remainingCredits;
            if (avg >= neededAvgGPA) {
                validCombinations.push([...path]);
            }
            return;
        }
        
        for (const grade of grades) {
            // Apply constraints
            if (grade === 'A' && maxAs !== null && (gradeCountMap[grade] || 0) >= maxAs) {
                continue;
            }
            if (skipCGrades && grade === 'C') {
                continue;
            }
            
            const point = gradePointsMap[grade];
            const predictedPoints = totalPoints + point * courseCreditValue;
            
            // Pruning: check if remaining courses can achieve target even with all A's
            const maxPossiblePoints = predictedPoints + (totalCourses - depth - 1) * 4.0 * courseCreditValue;
            if (maxPossiblePoints / remainingCredits < neededAvgGPA) {
                continue;
            }
            
            path.push(grade);
            gradeCountMap[grade] = (gradeCountMap[grade] || 0) + 1;
            backtrack(path, predictedPoints, gradeCountMap, depth + 1);
            path.pop();
            gradeCountMap[grade]--;
        }
    }
    
    backtrack([], 0, {}, 0);
    
    // Filter out all-A combinations if requested
    if (excludeAllA) {
        validCombinations.filter(combo => !combo.every(g => g === 'A'));
    }
    
    if (validCombinations.length === 0) {
        return ["No realistic combinations found"];
    }
    
    // Sort combinations by GPA (descending) and A count (descending)
    const sortedCombos = validCombinations.sort((a, b) => {
        const gpaA = computeGPA(a, gradePointsMap, courseCreditValue, remainingCredits);
        const gpaB = computeGPA(b, gradePointsMap, courseCreditValue, remainingCredits);
        if (gpaA !== gpaB) return gpaB - gpaA;
        return b.filter(g => g === 'A').length - a.filter(g => g === 'A').length;
    });
    
    // Limit results
    const limitedCombos = sortedCombos.slice(0, topNResults);
    
    return limitedCombos.map(combo => {
        const gpa = computeGPA(combo, gradePointsMap, courseCreditValue, remainingCredits);
        const breakdown = {};
        combo.forEach(grade => {
            breakdown[grade] = (breakdown[grade] || 0) + 1;
        });
        
        return {
            grades: combo,
            GPA: parseFloat(gpa.toFixed(2)),
            breakdown: breakdown
        };
    });
}

function computeGPA(combo, gradePointsMap, courseCreditValue, remainingCredits) {
    const total = combo.reduce((sum, grade) => sum + gradePointsMap[grade], 0) * courseCreditValue;
    return total / remainingCredits;
}

function displayAdvancedPredictionResults(results, targetClass, neededAvgGPA) {
    const resultDiv = document.getElementById('predictionResult');
    
    if (typeof results === 'string' || (Array.isArray(results) && typeof results[0] === 'string')) {
        const message = Array.isArray(results) ? results[0] : results;
        resultDiv.innerHTML = `
            <div style="background: linear-gradient(135deg, #ff6b6b, #ffa500); color: #fff; padding: 25px; border-radius: 12px; text-align: center;">
                <h3><i class="fas fa-exclamation-triangle"></i> ${message}</h3>
            </div>
        `;
        resultDiv.classList.remove('hidden');
        return;
    }
    
    const targetClassName = getTargetClassName(targetClass);
    
    let resultsHTML = `
        <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; padding: 25px; border-radius: 12px; margin-bottom: 20px;">
            <h3><i class="fas fa-route"></i> Path Analysis for ${targetClassName}</h3>
            <p style="margin: 10px 0;"><strong>Required Average GPA:</strong> ${neededAvgGPA.toFixed(2)}</p>
            <p style="margin: 0;"><strong>Found ${results.length} optimal path${results.length > 1 ? 's' : ''}:</strong></p>
        </div>
    `;
    
    results.forEach((result, index) => {
        const pathColor = index === 0 ? '#00c851' : index === 1 ? '#39c0ed' : '#ffbb33';
        
        resultsHTML += `
            <div style="background: #fff; border-left: 4px solid ${pathColor}; margin-bottom: 15px; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <div style="background: ${pathColor}; color: #fff; padding: 15px; font-weight: 600;">
                    <i class="fas fa-trophy"></i> Path ${index + 1} - Target GPA: ${result.GPA}
                </div>
                <div style="padding: 20px;">
                    <div style="margin-bottom: 15px;">
                        <h4 style="margin: 0 0 10px 0; color: #333;"><i class="fas fa-chart-bar"></i> Grade Breakdown:</h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                            ${Object.entries(result.breakdown).map(([grade, count]) => `
                                <span style="background: ${getGradeColor(grade)}; color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 0.9rem; font-weight: 500;">
                                    ${count}x ${grade}
                                </span>
                            `).join('')}
                        </div>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 10px 0; color: #333;"><i class="fas fa-list"></i> Course-by-Course Plan:</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(40px, 1fr)); gap: 8px; max-width: 400px;">
                            ${result.grades.map((grade, courseIndex) => `
                                <div style="background: ${getGradeColor(grade)}; color: #fff; padding: 8px; border-radius: 6px; text-align: center; font-weight: 600; font-size: 0.9rem;">
                                    ${grade}
                                </div>
                            `).join('')}
                        </div>
                        <p style="font-size: 0.9rem; color: #666; margin-top: 10px;">
                            <i class="fas fa-info-circle"></i> ${result.grades.length} courses total
                        </p>
                    </div>
                </div>
            </div>
        `;
    });
    
    resultDiv.innerHTML = resultsHTML;
    resultDiv.classList.remove('hidden');
    
    // Smooth scroll to result
    resultDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function getGradeColor(grade) {
    const colors = {
        'A': '#00c851',
        'B+': '#39c0ed', 
        'B': '#2e7aff',
        'C+': '#ffbb33',
        'C': '#ff8800'
    };
    return colors[grade] || '#666';
}

function getTargetClassName(targetClass) {
    const names = {
        'first': 'First Class',
        'second_upper': 'Second Class Upper',
        'second_lower': 'Second Class Lower',
        'third': 'Third Class'
    };
    return names[targetClass] || targetClass;
}

// Add some interactive enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to grade items
    const gradeItems = document.querySelectorAll('.grade-item');
    gradeItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Add click effect to class items
    const classItems = document.querySelectorAll('.class-item');
    classItems.forEach(item => {
        item.addEventListener('click', function() {
            const range = this.querySelector('.class-range').textContent;
            const name = this.querySelector('.class-name').textContent;
            
            // Show a tooltip or highlight effect
            const tooltip = document.createElement('div');
            tooltip.innerHTML = `<strong>${name}</strong><br>CGPA Range: ${range}`;
            tooltip.style.cssText = `
                position: absolute;
                background: rgba(0,0,0,0.8);
                color: white;
                padding: 10px 15px;
                border-radius: 8px;
                font-size: 0.9rem;
                pointer-events: none;
                z-index: 1000;
                top: -60px;
                left: 50%;
                transform: translateX(-50%);
                white-space: nowrap;
            `;
            
            this.style.position = 'relative';
            this.appendChild(tooltip);
            
            setTimeout(() => {
                if (tooltip.parentNode) {
                    tooltip.parentNode.removeChild(tooltip);
                }
            }, 2000);
        });
    });
});