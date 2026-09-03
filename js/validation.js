/* =========================================================================
   CAMPUS LOST & FOUND — CLIENT-SIDE VALIDATION
   =========================================================================
   IMPORTANT CONCEPT: this file ONLY improves the experience by catching
   obvious mistakes before a form is submitted (e.g. an empty required
   field, a badly formatted email). It is NOT a security measure.

   The PHP backend must re-validate every single field again on the server,
   because a user can disable JavaScript or submit a request directly
   (e.g. with a tool like curl or Postman), completely bypassing this file.

   Rule of thumb we'll follow in this project:
     JavaScript validation  -> better user experience (instant feedback)
     PHP validation         -> the actual security boundary
   ========================================================================= */

/* -------------------------------------------------------------------------
   Small helper functions, reused by every validator below.
   ---------------------------------------------------------------------- */

/**
 * Shows or clears an error message on one form field.
 * Expects the field's wrapper <div class="form-field"> to contain
 * an element with class="error-message".
 */
function setFieldError(fieldWrapper, message) {
  const errorEl = fieldWrapper.querySelector(".error-message");
  if (message) {
    fieldWrapper.classList.add("has-error");
    if (errorEl) errorEl.textContent = message;
  } else {
    fieldWrapper.classList.remove("has-error");
    if (errorEl) errorEl.textContent = "";
  }
}

/** Very small, deliberately simple email check (not a full RFC parser). */
function isValidEmail(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
}

/** Requires at least 8 characters, one letter and one number. */
function isStrongEnoughPassword(value) {
  return /^(?=.*[A-Za-z])(?=.*\d).{8,}$/.test(value);
}

/* -------------------------------------------------------------------------
   REGISTRATION FORM
   ---------------------------------------------------------------------- */
function initRegisterForm() {
  const form = document.getElementById("register-form");
  if (!form) return; // this page isn't the register page — do nothing

  form.addEventListener("submit", function (event) {
    let isFormValid = true;

    // --- Full name ---
    const nameField = form.querySelector("#field-full-name");
    const nameInput = document.getElementById("full_name");
    if (nameInput.value.trim().length < 2) {
      setFieldError(nameField, "Please enter your full name.");
      isFormValid = false;
    } else {
      setFieldError(nameField, "");
    }

    // --- Email ---
    const emailField = form.querySelector("#field-email");
    const emailInput = document.getElementById("email");
    if (!isValidEmail(emailInput.value)) {
      setFieldError(emailField, "Enter a valid email address.");
      isFormValid = false;
    } else {
      setFieldError(emailField, "");
    }

    // --- Phone (optional, but if filled must look like digits) ---
    const phoneField = form.querySelector("#field-phone");
    const phoneInput = document.getElementById("phone");
    if (phoneInput.value.trim() && !/^\+?[0-9\s-]{7,15}$/.test(phoneInput.value.trim())) {
      setFieldError(phoneField, "Enter a valid phone number, or leave this blank.");
      isFormValid = false;
    } else {
      setFieldError(phoneField, "");
    }

    // --- Password ---
    const passwordField = form.querySelector("#field-password");
    const passwordInput = document.getElementById("password");
    if (!isStrongEnoughPassword(passwordInput.value)) {
      setFieldError(
        passwordField,
        "Password needs at least 8 characters, including a letter and a number."
      );
      isFormValid = false;
    } else {
      setFieldError(passwordField, "");
    }

    // --- Confirm password ---
    const confirmField = form.querySelector("#field-confirm-password");
    const confirmInput = document.getElementById("confirm_password");
    if (confirmInput.value !== passwordInput.value) {
      setFieldError(confirmField, "Passwords do not match.");
      isFormValid = false;
    } else {
      setFieldError(confirmField, "");
    }

    if (!isFormValid) {
      event.preventDefault(); // stop the submit so the user sees the errors
    }
  });
}

/* -------------------------------------------------------------------------
   LOGIN FORM
   ---------------------------------------------------------------------- */
function initLoginForm() {
  const form = document.getElementById("login-form");
  if (!form) return;

  form.addEventListener("submit", function (event) {
    let isFormValid = true;

    const emailField = form.querySelector("#field-login-email");
    const emailInput = document.getElementById("login_email");
    if (!isValidEmail(emailInput.value)) {
      setFieldError(emailField, "Enter the email you registered with.");
      isFormValid = false;
    } else {
      setFieldError(emailField, "");
    }

    const passwordField = form.querySelector("#field-login-password");
    const passwordInput = document.getElementById("login_password");
    if (passwordInput.value.length === 0) {
      setFieldError(passwordField, "Enter your password.");
      isFormValid = false;
    } else {
      setFieldError(passwordField, "");
    }

    if (!isFormValid) {
      event.preventDefault();
    }
  });
}

/* -------------------------------------------------------------------------
   REPORT ITEM FORM (used for both lost and found reports)
   ---------------------------------------------------------------------- */
function initReportForm() {
  const form = document.getElementById("report-form");
  if (!form) return;

  const imageInput = document.getElementById("item_image");
  const maxImageSizeInBytes = 3 * 1024 * 1024; // 3 MB

  form.addEventListener("submit", function (event) {
    let isFormValid = true;

    // --- Item name ---
    const nameField = form.querySelector("#field-item-name");
    const nameInput = document.getElementById("item_name");
    if (nameInput.value.trim().length < 3) {
      setFieldError(nameField, "Give the item a short, specific name.");
      isFormValid = false;
    } else {
      setFieldError(nameField, "");
    }

    // --- Category ---
    const categoryField = form.querySelector("#field-category");
    const categoryInput = document.getElementById("category");
    if (!categoryInput.value) {
      setFieldError(categoryField, "Choose a category.");
      isFormValid = false;
    } else {
      setFieldError(categoryField, "");
    }

    // --- Description ---
    const descField = form.querySelector("#field-description");
    const descInput = document.getElementById("description");
    if (descInput.value.trim().length < 10) {
      setFieldError(descField, "Add a few more details (at least 10 characters).");
      isFormValid = false;
    } else {
      setFieldError(descField, "");
    }

    // --- Location ---
    const locationField = form.querySelector("#field-location");
    const locationInput = document.getElementById("location");
    if (locationInput.value.trim().length < 2) {
      setFieldError(locationField, "Tell us roughly where this happened.");
      isFormValid = false;
    } else {
      setFieldError(locationField, "");
    }

    // --- Date ---
    const dateField = form.querySelector("#field-date");
    const dateInput = document.getElementById("item_date");
    if (!dateInput.value) {
      setFieldError(dateField, "Choose a date.");
      isFormValid = false;
    } else if (new Date(dateInput.value) > new Date()) {
      setFieldError(dateField, "The date can't be in the future.");
      isFormValid = false;
    } else {
      setFieldError(dateField, "");
    }

    // --- Image (optional, but checked if provided) ---
    if (imageInput && imageInput.files.length > 0) {
      const imageField = form.querySelector("#field-image");
      const file = imageInput.files[0];
      const allowedTypes = ["image/jpeg", "image/png", "image/webp"];

      if (!allowedTypes.includes(file.type)) {
        setFieldError(imageField, "Upload a JPG, PNG or WEBP image.");
        isFormValid = false;
      } else if (file.size > maxImageSizeInBytes) {
        setFieldError(imageField, "Image must be smaller than 3 MB.");
        isFormValid = false;
      } else {
        setFieldError(imageField, "");
      }
    }

    if (!isFormValid) {
      event.preventDefault();
    }
  });
}

/* -------------------------------------------------------------------------
   Run the relevant initializer once the page has loaded.
   Each init function quietly does nothing if its form isn't on the page,
   so it's safe to call all of them from every page.
   ---------------------------------------------------------------------- */
document.addEventListener("DOMContentLoaded", function () {
  initRegisterForm();
  initLoginForm();
  initReportForm();
});