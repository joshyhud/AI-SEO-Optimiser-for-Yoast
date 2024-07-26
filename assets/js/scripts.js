jQuery(document).ready(function ($) {
  // Check if specific_page_radio is selected

  $("input[type='radio']").on("click", function () {
    if ($("#specific_page_radio").is(":checked")) {
      // Your code here
      $("#specific_page").removeAttr("disabled");
    } else {
      $("#specific_page").attr("disabled", true);
    }
  });

  $("#ai-seo-form").submit(function (event) {
    event.preventDefault();

    // Show the loading GIF and hide the text
    $("#loading-gif").show();
    $(".button.button-primary").hide();

    // Hide previous responses
    // $("#response-area").remove();

    var formData = $(this).serialize();

    $.ajax({
      type: "POST",
      url: ai_seo_ajax_object.ajax_url,
      data: formData,
      success: function (response) {
        // Hide the loading GIF
        $("#loading-gif").hide();
        $(".button.button-primary").show();

        // Append the response to the form area
        $("#response-area").html(response).fadeIn();
      },
      error: function (xhr, status, error) {
        // Hide the loading GIF
        $("#loading-gif").hide();
        $(".button.button-primary").show();

        $("#response-area").html(
          '<p class="response">An error occurred: ' + error + "</p>"
        );
      },
    });
  });
});
