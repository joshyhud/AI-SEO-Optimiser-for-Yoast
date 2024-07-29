jQuery(document).ready(function ($) {
  "use strict";

  // Check if specific_page_radio is selected
  $("input[type='radio']").on("click", function () {
    if ($("#specific_page_radio").is(":checked")) {
      $("#specific_page").removeAttr("disabled"); // Enable the specific_page input
    } else {
      $("#specific_page").attr("disabled", true); // Disable the specific_page input
    }
  });

  // Handle form submission
  $("#ai-seo-form").submit(function (event) {
    event.preventDefault(); // Prevent the default form submission behavior

    // Show the loading GIF and hide the submit button
    $("#loading-gif").show();
    $(".button.button-primary").hide();

    // Hide previous responses
    $("#response-area").fadeOut();

    // Include the nonce in the form data
    var formData =
      $(this).serialize() + "&security=" + ai_seo_ajax_object.nonce;

    // Send an AJAX request to the server
    $.ajax({
      type: "POST",
      url: ai_seo_ajax_object.ajax_url,
      data: formData,
      success: function (response) {
        // Hide the loading GIF and show the submit button
        $("#loading-gif").hide();
        $(".button.button-primary").show();

        // Append the response to the form area and fade it in
        $("#response-area").html(response).fadeIn();
      },
      error: function (xhr, status, error) {
        // Hide the loading GIF and show the submit button
        $("#loading-gif").hide();
        $(".button.button-primary").show();

        // Display an error message
        $("#response-area")
          .html('<p class="response">An error occurred: ' + error + "</p>")
          .fadeIn();
      },
    });
  });
});
