// onboarding.js
// Handles multi-step onboarding form logic and API communication

document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("onboardingForm");
  const step1 = document.getElementById("step1");
  const step2 = document.getElementById("step2");
  const progressBar = document.getElementById("progressBar");
  const nextBtn = document.getElementById("nextBtn");
  const backBtn = document.getElementById("backBtn");
  const skipBtn = document.getElementById("skipBtn");
  const finishBtn = document.getElementById("finishBtn");

  const countryInput = document.getElementById("country");
  const educationLevelInput = document.getElementById("education_level");
  const fieldOfStudyGroup = document.getElementById("fieldOfStudyGroup");
  const fieldOfStudyInput = document.getElementById("field_of_study");

  let currentStep = 1;

  // Show/hide field of study based on education level
  educationLevelInput.addEventListener("change", function () {
    const level = this.value;
    if (
      level === "University" ||
      level === "Graduate" ||
      level === "Professional"
    ) {
      fieldOfStudyGroup.style.display = "block";
    } else {
      fieldOfStudyGroup.style.display = "none";
      fieldOfStudyInput.value = "";
    }
  });

  // Next button - move to step 2
  nextBtn.addEventListener("click", function () {
    // Validate step 1
    if (!validateStep1()) {
      return;
    }

    // Move to step 2
    step1.classList.remove("active");
    step2.classList.add("active");
    progressBar.style.width = "100%";
    currentStep = 2;
  });

  // Back button - return to step 1
  backBtn.addEventListener("click", function () {
    step2.classList.remove("active");
    step1.classList.add("active");
    progressBar.style.width = "50%";
    currentStep = 1;
  });

  // Skip button - submit with just required fields
  skipBtn.addEventListener("click", function (e) {
    e.preventDefault();

    // Clear optional fields
    document.getElementById("primary_language").value = "English";
    document.getElementById("field_of_study").value = "";
    document.getElementById("institution").value = "";

    // Submit the form
    submitOnboarding();
  });

  // Form submission
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    submitOnboarding();
  });

  /**
   * Validate step 1 fields
   */
  function validateStep1() {
    const errorEl = document.getElementById("step1Error");
    let errors = [];

    if (!countryInput.value) {
      errors.push("Please select your country");
    }

    if (!educationLevelInput.value) {
      errors.push("Please select your education level");
    }

    if (errors.length > 0) {
      errorEl.textContent = errors.join(". ");
      errorEl.classList.add("show");
      return false;
    }

    errorEl.classList.remove("show");
    return true;
  }

  /**
   * Submit onboarding data to the API
   */
  async function submitOnboarding() {
    // Validate step 1 again (in case they skipped)
    if (!validateStep1()) {
      // Go back to step 1
      if (currentStep === 2) {
        backBtn.click();
      }
      return;
    }

    // Disable the finish button to prevent double submission
    finishBtn.disabled = true;
    finishBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Finishing...';

    const formData = {
      country: countryInput.value,
      education_level: educationLevelInput.value,
      primary_language:
        document.getElementById("primary_language").value || "English",
      field_of_study: fieldOfStudyInput.value || null,
      institution: document.getElementById("institution").value || null,
    };

    try {
      const response = await fetch("api/user_onboarding.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(formData),
      });

      const result = await response.json();

      if (result.success) {
        // Show success message briefly
        finishBtn.innerHTML = '<i class="fas fa-check"></i> Success!';
        finishBtn.style.background = "var(--success)";

        // Redirect to main app after a short delay
        setTimeout(() => {
          window.location.href = "chat";
        }, 500);
      } else {
        // Show error
        const errorEl = document.getElementById("step2Error");
        errorEl.textContent =
          result.error || result.errors?.join(". ") || "An error occurred";
        errorEl.classList.add("show");

        // Re-enable button
        finishBtn.disabled = false;
        finishBtn.innerHTML =
          'Finish & Start Learning <i class="fas fa-check"></i>';
      }
    } catch (error) {
      console.error("Submission error:", error);
      const errorEl = document.getElementById("step2Error");
      errorEl.textContent = "An unexpected error occurred. Please try again.";
      errorEl.classList.add("show");

      // Re-enable button
      finishBtn.disabled = false;
      finishBtn.innerHTML =
        'Finish & Start Learning <i class="fas fa-check"></i>';
    }
  }
});
