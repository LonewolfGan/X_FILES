/**
 * Affiche ou cache le mot de passe
 */
function togglePassword(id, btn) {
  const input = document.getElementById(id);
  const icon = btn.querySelector("i");
  if (input && icon) {
    if (input.type === "password") {
      input.type = "text";
      icon.classList.replace("fa-eye", "fa-eye-slash");
    } else {
      input.type = "password";
      icon.classList.replace("fa-eye-slash", "fa-eye");
    }
  }
}

/**
 * Fonctions d'Accessibilité et d'Affichage d'erreurs (Inline)
 */
function setFieldError(inputElement, errorMessage) {
  // Navigation DOM pour s'adapter aux champs avec icones et wrapper (comme password)
  const wrapper =
    inputElement.closest(".input-password-wrapper") || inputElement;
  const parent = wrapper.parentElement;

  // Gestion spécifique pour jQuery Nice Select
  let targetElement = inputElement;
  if (inputElement.tagName === "SELECT") {
    const niceSelect = parent.querySelector(".nice-select");
    if (niceSelect) targetElement = niceSelect;
  }

  // Nettoyer l'alerte précédente sur ce champ
  clearFieldError(inputElement);

  // Focus ARIA
  targetElement.setAttribute("aria-invalid", "true");

  // Créer le Span d'erreur
  const errorDiv = document.createElement("div");
  errorDiv.className = "field-error-message";
  errorDiv.id = inputElement.id + "-error";
  errorDiv.setAttribute("aria-live", "polite"); // Annonce aux lecteurs d'écran
  errorDiv.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> <span>${errorMessage}</span>`;

  // Injecter en bas de la form-group
  parent.appendChild(errorDiv);

  // Link l'input à la description d'erreur (ARIA)
  targetElement.setAttribute("aria-describedby", errorDiv.id);
}

function clearFieldError(inputElement) {
  const wrapper =
    inputElement.closest(".input-password-wrapper") || inputElement;
  const parent = wrapper.parentElement;

  let targetElement = inputElement;
  if (inputElement.tagName === "SELECT") {
    const niceSelect = parent.querySelector(".nice-select");
    if (niceSelect) targetElement = niceSelect;
  }

  targetElement.removeAttribute("aria-invalid");
  targetElement.removeAttribute("aria-describedby");

  const existingError = parent.querySelector(".field-error-message");
  if (existingError) {
    existingError.remove();
  }
}

function hasError(inputElement) {
  const wrapper =
    inputElement.closest(".input-password-wrapper") || inputElement;
  return wrapper.parentElement.querySelector(".field-error-message") !== null;
}

/**
 * Validation Automatique Logique
 */
const validators = {
  name: (val) => {
    if (!val) return "Le nom est obligatoire.";
    if (val.length < 3) return "Le nom doit contenir au moins 3 caractères.";
    return "";
  },
  email: (val) => {
    if (!val) return "L'email est obligatoire.";
    const req = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return req.test(val) ? "" : "Format d'email invalide.";
  },
  filiere: (val) => (!val ? "Veuillez choisir une filière." : ""),
  passwordReg: (val) =>
    val.length < 8
      ? "Le mot de passe doit contenir au moins 8 caractères."
      : "",
  passwordLog: (val) => (!val ? "Le mot de passe est obligatoire." : ""),
  confirm: (val, pwd) =>
    val !== pwd ? "Les mots de passe ne correspondent pas." : "",
};



document.addEventListener("DOMContentLoaded", function () {
  const registerForm = document.getElementById("register-form");
  const loginForm = document.getElementById("login-form");

  /**
   * Universal attacher pour valider un input au format (Blur / Input Correctif)
   */
  function attachListeners(input, validateFn) {
    if (!input) return;

    // Règle 1: Valider "on blur" pour ne pas spammer d'erreurs en pleine frappe
    input.addEventListener("blur", () => {
      const err = validateFn();
      if (err) setFieldError(input, err);
      else clearFieldError(input);
    });

    // Règle 2: Si erreur en cours, on revalide à chaque frappe (input) pour enlever l'erreur instantanément
    input.addEventListener("input", () => {
      if (hasError(input)) {
        const err = validateFn();
        if (err) setFieldError(input, err);
        else clearFieldError(input);
      }
    });

    // Custom check pour Nice Select
    if (input.tagName === "SELECT") {
      $(input).on("change", () => {
        if (hasError(input)) {
          const err = validateFn();
          if (err) setFieldError(input, err);
          else clearFieldError(input);
        }
      });
    }
  }

  // --- VALIDATION REGISTER ---
  if (registerForm) {
    const nameEl = document.getElementById("name");
    const emailEl = document.getElementById("email");
    const filiereEl = document.getElementById("filiere");
    const passwordEl = document.getElementById("password");
    const confirmEl = document.getElementById("confirm");

    const getValidateName = () => validators.name(nameEl.value.trim());
    const getValidateEmail = () => validators.email(emailEl.value.trim());
    const getValidateFiliere = () => validators.filiere(filiereEl.value);
    const getValidatePassword = () => validators.passwordReg(passwordEl.value);
    const getValidateConfirm = () =>
      validators.confirm(confirmEl.value, passwordEl.value);

    attachListeners(nameEl, getValidateName);
    attachListeners(emailEl, getValidateEmail);
    attachListeners(filiereEl, getValidateFiliere);
    attachListeners(passwordEl, getValidatePassword);
    attachListeners(confirmEl, getValidateConfirm);


    // Validation Finale au Submit
    registerForm.addEventListener("submit", function (e) {
      let isFormValid = true;
      let firstInvalidEl = null;

      const validations = [
        { el: nameEl, fn: getValidateName },
        { el: emailEl, fn: getValidateEmail },
        { el: filiereEl, fn: getValidateFiliere },
        { el: passwordEl, fn: getValidatePassword },
        { el: confirmEl, fn: getValidateConfirm },
      ];

      validations.forEach(({ el, fn }) => {
        if (el) {
          const err = fn();
          if (err) {
            setFieldError(el, err);
            isFormValid = false;
            // Stocker le premier élément fautif
            if (!firstInvalidEl) firstInvalidEl = el;
          } else {
            clearFieldError(el);
          }
        }
      });

      if (!isFormValid) {
        e.preventDefault(); // On bloque l'envoi SQL

        // Navigation d'accessibilité: Scroll et Focus vers l'erreur
        if (firstInvalidEl) {
          if (firstInvalidEl.tagName === "SELECT") {
            const niceSelect =
              firstInvalidEl.parentNode.querySelector(".nice-select");
            if (niceSelect) {
              niceSelect.scrollIntoView({
                behavior: "smooth",
                block: "center",
              });
              niceSelect.focus();
            }
          } else {
            firstInvalidEl.scrollIntoView({
              behavior: "smooth",
              block: "center",
            });
            firstInvalidEl.focus();
          }
        }
      }
    });
  }

  // --- VALIDATION LOGIN ---
  if (loginForm) {
    const emailEl = document.getElementById("email");
    const passwordEl = document.getElementById("password");

    const getValidateEmail = () => validators.email(emailEl.value.trim());
    const getValidatePasswordLog = () =>
      validators.passwordLog(passwordEl.value);

    attachListeners(emailEl, getValidateEmail);
    attachListeners(passwordEl, getValidatePasswordLog);

    loginForm.addEventListener("submit", function (e) {
      let isFormValid = true;
      let firstInvalidEl = null;

      const validations = [
        { el: emailEl, fn: getValidateEmail },
        { el: passwordEl, fn: getValidatePasswordLog },
      ];

      validations.forEach(({ el, fn }) => {
        if (el) {
          const err = fn();
          if (err) {
            setFieldError(el, err);
            isFormValid = false;
            if (!firstInvalidEl) firstInvalidEl = el;
          } else {
            clearFieldError(el);
          }
        }
      });

      if (!isFormValid) {
        e.preventDefault();

        if (firstInvalidEl) {
          firstInvalidEl.scrollIntoView({
            behavior: "smooth",
            block: "center",
          });
          firstInvalidEl.focus();
        }
      }
    });
  }
});
