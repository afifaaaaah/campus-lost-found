/* =========================================================================
   CAMPUS LOST & FOUND — GENERAL INTERACTIVITY
   =========================================================================
   Two independent, small pieces of behaviour:

   1. Mobile navigation toggle (used on every page)
   2. Dashboard search/filter demo (only runs on dashboard.html)

   Later, step 2 will be replaced by a real PHP + MySQL query — for now it
   filters the sample rows that are already in the HTML, purely on the
   client, so you can see and explain the interaction before the backend
   exists.
   ========================================================================= */

/* -------------------------------------------------------------------------
   1. MOBILE NAV TOGGLE
   The button with class="nav-toggle" shows/hides the <ul class="nav-links">
   on small screens. See the CSS media query for .nav-links.is-open.
   ---------------------------------------------------------------------- */
function initNavToggle() {
  const toggleButton = document.querySelector(".nav-toggle");
  const navLinks = document.querySelector(".nav-links");
  if (!toggleButton || !navLinks) return;

  toggleButton.addEventListener("click", function () {
    const isOpen = navLinks.classList.toggle("is-open");
    // aria-expanded tells screen readers whether the menu is open
    toggleButton.setAttribute("aria-expanded", isOpen ? "true" : "false");
  });
}

/* -------------------------------------------------------------------------
   2. DASHBOARD SEARCH / FILTER DEMO
   Filters the <tr> rows inside #items-table-body based on:
     - free-text search (matches item name)
     - a category dropdown
     - a status dropdown (lost / found / claimed)

   Each <tr> carries data-* attributes (data-name, data-category,
   data-status) that we read here. In the real backend, this same
   filtering will instead happen in a PHP query with a WHERE clause —
   the UI behaviour stays identical either way, only where the filtering
   happens changes.
   ---------------------------------------------------------------------- */
function initDashboardFilters() {
  const searchInput = document.getElementById("search-input");
  const categorySelect = document.getElementById("filter-category");
  const statusSelect = document.getElementById("filter-status");
  const tableBody = document.getElementById("items-table-body");
  const emptyState = document.getElementById("no-results");

  // If any of these elements are missing, we're not on the dashboard page.
  if (!searchInput || !categorySelect || !statusSelect || !tableBody) return;

  function applyFilters() {
    const searchTerm = searchInput.value.trim().toLowerCase();
    const category = categorySelect.value;
    const status = statusSelect.value;
    const rows = tableBody.querySelectorAll("tr");
    let visibleCount = 0;

    rows.forEach(function (row) {
      const matchesSearch =
        searchTerm === "" || row.dataset.name.toLowerCase().includes(searchTerm);
      const matchesCategory = category === "all" || row.dataset.category === category;
      const matchesStatus = status === "all" || row.dataset.status === status;

      const isVisible = matchesSearch && matchesCategory && matchesStatus;
      row.style.display = isVisible ? "" : "none";
      if (isVisible) visibleCount++;
    });

    if (emptyState) {
      emptyState.style.display = visibleCount === 0 ? "block" : "none";
    }
  }

  searchInput.addEventListener("input", applyFilters);
  categorySelect.addEventListener("change", applyFilters);
  statusSelect.addEventListener("change", applyFilters);
}

/* -------------------------------------------------------------------------
   Run everything once the page has finished loading.
   ---------------------------------------------------------------------- */
document.addEventListener("DOMContentLoaded", function () {
  initNavToggle();
  initDashboardFilters();
});