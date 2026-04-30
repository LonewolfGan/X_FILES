const themeToggle = document.getElementById("theme-toggle");
const html = document.documentElement;
// Utiliser le thème déjà appliqué par le script dans le <head>
let currentTheme = html.getAttribute("data-theme") || "light";

function setTheme(theme) {
  html.setAttribute("data-theme", theme);
  localStorage.setItem("theme", theme);
  if (themeToggle) {
    themeToggle.innerHTML =
      theme === "dark"
        ? '<i class="fa-solid fa-sun"></i> Mode clair'
        : '<i class="fa-solid fa-moon"></i> Mode sombre';
  }
}

if (themeToggle) {
  themeToggle.addEventListener("click", () => {
    currentTheme = currentTheme === "dark" ? "light" : "dark";
    setTheme(currentTheme);
  });
}

// Initialiser le thème au chargement
setTheme(currentTheme);

const mobileMenuBtn = document.getElementById("mobile-menu-btn");
const navMenu = document.getElementById("nav-menu");
if (mobileMenuBtn && navMenu) {
  mobileMenuBtn.addEventListener("click", () => {
    navMenu.classList.toggle("active");
    mobileMenuBtn.innerHTML = navMenu.classList.contains("active")
      ? '<i class="fa-solid fa-xmark"></i>'
      : '<i class="fa-solid fa-bars"></i>';
  });
}
