/* =========================================================================
   CAMPUS LOST & FOUND — SLIDE-IN ACCOUNT MENU (menu.js)
   =========================================================================
   The three-line button (top-left) opens a panel that slides in from the
   left. The sliding itself is done by CSS transitions (see extras.css);
   this file only adds/removes the "is-open" class that triggers them.
   ========================================================================= */

(function () {
  const button = document.getElementById("menu-btn");
  const menu = document.getElementById("side-menu");
  const backdrop = document.getElementById("menu-backdrop");
  if (!button || !menu || !backdrop) return;

  function setOpen(isOpen) {
    button.classList.toggle("is-open", isOpen);        // three lines -> X
    menu.classList.toggle("is-open", isOpen);          // panel slides in/out
    backdrop.classList.toggle("is-visible", isOpen);   // page dims
    document.body.classList.toggle("menu-open", isOpen); // stops page scrolling behind it
    button.setAttribute("aria-expanded", isOpen ? "true" : "false");
    button.setAttribute("aria-label", isOpen ? "Close menu" : "Open menu");
  }

  // Click the button: toggle
  button.addEventListener("click", function () {
    setOpen(!menu.classList.contains("is-open"));
  });

  // Click the dimmed background: close
  backdrop.addEventListener("click", function () {
    setOpen(false);
  });

  // Press Escape: close
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") setOpen(false);
  });

  // Click any link inside the menu: close (matters for #my-reports style links)
  menu.querySelectorAll("a").forEach(function (link) {
    link.addEventListener("click", function () {
      setOpen(false);
    });
  });
})();
