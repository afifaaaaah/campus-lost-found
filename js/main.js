/* =========================================================================
   CAMPUS LOST & FOUND — GENERAL INTERACTIVITY (main.js)
   =========================================================================
   Only one job now: the small-screen menu button on the PUBLIC (logged-out)
   navigation bar. The logged-in slide-in menu lives in menu.js.

   (The old client-side search/filter demo was removed: searching is now
   done properly by PHP + MySQL on the server, in dashboard.php.)
   ========================================================================= */

function initNavToggle() {
  const toggleButton = document.querySelector(".nav-toggle");
  const navLinks = document.querySelector(".nav-links");
  if (!toggleButton || !navLinks) return; // this page has no public nav

  toggleButton.addEventListener("click", function () {
    const isOpen = navLinks.classList.toggle("is-open");
    // aria-expanded tells screen readers whether the menu is open
    toggleButton.setAttribute("aria-expanded", isOpen ? "true" : "false");
  });
}

document.addEventListener("DOMContentLoaded", initNavToggle);
