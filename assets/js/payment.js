jQuery(function ($) {
  "use strict";

  // Handle form submission
  $("form.checkout").on("checkout_place_order_echezona_payment", function () {
    var $form = $(this);
    var $submitButton = $form.find('button[type="submit"]');

    // Disable the submit button to prevent double submission
    $submitButton.prop("disabled", true);

    // Clear any previous error messages
    $(".woocommerce-error").remove();

    // The form will be submitted normally, and WooCommerce will handle the redirect
    return true;
  });
});
