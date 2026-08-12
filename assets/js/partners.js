(function () {
  "use strict";

  var form = document.getElementById("finderForm");
  if (!form) return;

  var step2 = form.querySelector('[data-finder-step="2"]');
  var submitBtn = form.querySelector("[data-finder-submit]");
  var categoryInputs = form.querySelectorAll('input[name="category"]');

  if (!step2 || !submitBtn || !categoryInputs.length) return;

  // שיפור פרוגרסיבי בלבד: מסתירים שלב 2 + הכפתור עד שנבחרה קטגוריה בשלב 1.
  // בלי JS שני האלמנטים האלה פשוט נשארים גלויים והטופס עדיין עובד במלואו.
  var hasSelection = Array.from(categoryInputs).some(function (el) {
    return el.checked;
  });
  if (!hasSelection) {
    step2.hidden = true;
    submitBtn.hidden = true;
  }

  categoryInputs.forEach(function (input) {
    input.addEventListener("change", function () {
      step2.hidden = false;
      submitBtn.hidden = false;
    });
  });
})();
