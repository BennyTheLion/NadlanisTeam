(function () {
  "use strict";

  /* ---------- ניווט מובייל ---------- */
  var toggle = document.getElementById("navToggle");
  var nav = document.getElementById("siteNav");
  if (toggle && nav) {
    var closeNav = function () {
      toggle.setAttribute("aria-expanded", "false");
      nav.setAttribute("data-open", "false");
    };
    toggle.addEventListener("click", function () {
      var isOpen = nav.getAttribute("data-open") === "true";
      toggle.setAttribute("aria-expanded", String(!isOpen));
      nav.setAttribute("data-open", String(!isOpen));
    });
    nav.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", closeNav);
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") closeNav();
    });
    document.addEventListener("click", function (e) {
      if (nav.getAttribute("data-open") !== "true") return;
      if (nav.contains(e.target) || toggle.contains(e.target)) return;
      closeNav();
    });
  }

  /* ---------- גלריית תמונות בעמוד נכס ---------- */
  var mainImg = document.getElementById("galleryMain");
  var thumbs = document.querySelectorAll("[data-thumb]");
  if (mainImg && thumbs.length) {
    thumbs.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var full = btn.getAttribute("data-full");
        if (!full) return;
        mainImg.src = full;
        mainImg.alt = btn.getAttribute("data-alt") || mainImg.alt;
        thumbs.forEach(function (b) {
          b.setAttribute("aria-current", "false");
        });
        btn.setAttribute("aria-current", "true");
      });
    });
  }

  /* ---------- סינון מתקדם (properties.php) ---------- */
  var filterToggle = document.querySelector("[data-filter-toggle]");
  var filterAdvanced = document.querySelector("[data-filter-advanced]");
  if (filterToggle && filterAdvanced) {
    var hasValues = Array.from(filterAdvanced.querySelectorAll("input, select")).some(function (el) {
      return el.value && el.value !== "";
    });
    if (hasValues) {
      filterAdvanced.setAttribute("data-open", "true");
    }
    filterToggle.addEventListener("click", function () {
      var open = filterAdvanced.getAttribute("data-open") === "true";
      filterAdvanced.setAttribute("data-open", String(!open));
      filterToggle.setAttribute("aria-expanded", String(!open));
    });
  }

  /* ---------- מיון עם שליחה אוטומטית ---------- */
  var sortSelect = document.querySelector("[data-sort-submit]");
  if (sortSelect) {
    sortSelect.addEventListener("change", function () {
      sortSelect.form.submit();
    });
  }

  /* ---------- חשיפה בגלילה ---------- */
  var revealEls = document.querySelectorAll(".reveal");
  if (revealEls.length && "IntersectionObserver" in window) {
    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            io.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15 }
    );
    revealEls.forEach(function (el) {
      io.observe(el);
    });
  } else {
    revealEls.forEach(function (el) {
      el.classList.add("is-visible");
    });
  }

  /* ---------- העלאת תמונות במנהל: תצוגה מקדימה ---------- */
  var uploadInput = document.querySelector("[data-upload-input]");
  var uploadPreview = document.querySelector("[data-upload-preview]");
  if (uploadInput && uploadPreview) {
    uploadInput.addEventListener("change", function () {
      uploadPreview.innerHTML = "";
      Array.from(uploadInput.files || []).forEach(function (file) {
        var reader = new FileReader();
        reader.onload = function (e) {
          var div = document.createElement("div");
          div.className = "upload-thumb";
          var img = document.createElement("img");
          img.src = e.target.result;
          img.alt = "";
          div.appendChild(img);
          uploadPreview.appendChild(div);
        };
        reader.readAsDataURL(file);
      });
    });
  }

  /* ---------- מחשבון משכנתא: חישוב מיידי ללא רענון (שיפור פרוגרסיבי) ---------- */
  var mortgageForm = document.getElementById("mortgageForm");
  if (mortgageForm) {
    var mPrice = document.getElementById("m-price");
    var mDown = document.getElementById("m-down");
    var mRate = document.getElementById("m-rate");
    var mYears = document.getElementById("m-years");
    var result = document.getElementById("mortgageResult");
    var rMonthly = document.getElementById("r-monthly");
    var rLoan = document.getElementById("r-loan");
    var rInterest = document.getElementById("r-interest");
    var rTotal = document.getElementById("r-total");

    var formatIls = function (n) {
      return "₪" + Math.round(n).toLocaleString("he-IL");
    };

    var recalc = function () {
      var price = parseFloat(mPrice.value);
      var down = parseFloat(mDown.value);
      var rate = parseFloat(mRate.value);
      var years = parseFloat(mYears.value);
      if (!(price > 0) || !(down >= 0) || down >= price || !(rate >= 0) || !(years > 0)) {
        return;
      }
      var loanAmount = price - down;
      var months = Math.round(years * 12);
      var monthlyRate = rate / 100 / 12;
      var monthlyPayment;
      if (monthlyRate > 0) {
        var factor = Math.pow(1 + monthlyRate, months);
        monthlyPayment = (loanAmount * monthlyRate * factor) / (factor - 1);
      } else {
        monthlyPayment = loanAmount / months;
      }
      var totalPayment = monthlyPayment * months;
      var totalInterest = totalPayment - loanAmount;

      if (result) result.style.display = "";
      if (rMonthly) rMonthly.textContent = formatIls(monthlyPayment);
      if (rLoan) rLoan.textContent = formatIls(loanAmount);
      if (rInterest) rInterest.textContent = formatIls(totalInterest);
      if (rTotal) rTotal.textContent = formatIls(totalPayment);
    };

    [mPrice, mDown, mRate, mYears].forEach(function (el) {
      if (el) el.addEventListener("input", recalc);
    });
    mortgageForm.addEventListener("submit", function (e) {
      e.preventDefault();
      recalc();
      var params = new URLSearchParams(new FormData(mortgageForm));
      history.replaceState(null, "", "?" + params.toString());
    });
  }
})();
