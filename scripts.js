window.onload = function () {
  /**
   * Represents the specific page radio element.
   * @type {HTMLElement}
   */
  const specificPageRadio = document.getElementById("specific_page_radio");
  const specificPageInput = document.getElementById("specific_page");

  // Add event listener to specificPageRadio
  specificPageRadio.addEventListener("change", function () {
    if (this.checked) {
      specificPageInput.disabled = false;
    } else {
      specificPageInput.disabled = true;
    }
  });

  specificPageInput.addEventListener("input", function () {
    if (!specificPageRadio.checked) {
      specificPageInput.disabled = true;
    }
  });
};
