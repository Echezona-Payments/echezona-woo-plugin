const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings;
const { decodeEntities } = window.wp.htmlEntities;
const { getCurrencyFromPriceResponse } = window.wc.blocksCheckout;

// Add error logging
const logError = (message, error = null) => {
  // Send to WordPress error log
  if (window.wp && window.wp.data) {
    window.wp.data
      .dispatch("core/notices")
      .createErrorNotice(`Echezona Payment Error: ${message}`);
  }
};

try {
  const settings = getSetting("echezona_payment_data", {});
  console.log("Echezona Payment settings:", settings);

  const EchezonaPaymentMethod = {
    name: "echezona_payment",
    label: decodeEntities(settings.title || "Echezona Payment"),
    content: React.createElement(
      "div",
      { className: "echezona-payment-content" },
      React.createElement("img", {
        src: settings.logo_url || ECHEPAY_GATEWAY_FOR_WOOCOMMERCE_PLUGIN_URL + "assets/images/logo.png",
        alt: "Echezona Payment",
        style: { maxWidth: "100px", marginBottom: "10px" },
      }),
      React.createElement(
        "div",
        null,
        decodeEntities(settings.description || "No description available.")
      )
    ),
    edit: React.createElement(
      "div",
      { className: "echezona-payment-content" },
      React.createElement("img", {
        src: settings.logo_url || ECHEPAY_GATEWAY_FOR_WOOCOMMERCE_PLUGIN_URL + "assets/images/logo.png",
        alt: "Echezona Payment",
        style: { maxWidth: "100px", marginBottom: "10px" },
      }),
      React.createElement(
        "div",
        null,
        decodeEntities(settings.description || "No description available.")
      )
    ),
    canMakePayment: () => true,
    ariaLabel: "Echezona Payment Gateway",
    supports: {
      features: settings.supports || [],
    },
  };

  registerPaymentMethod(EchezonaPaymentMethod);
} catch (error) {
  logError("Failed to register payment method", error);
}
