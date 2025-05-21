import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { decodeEntities } from "@wordpress/html-entities";
import { getSetting } from "@woocommerce/settings";

const settings = getSetting("echezona_blocks_data", {});

const EchezonaComponent = () => {
  return decodeEntities(settings.description || "");
};

const canMakePayment = () => {
  return true;
};

const EchezonaPaymentMethod = {
  name: "echezona_payment",
  label: decodeEntities(settings.title || "Echezona Payment"),
  content: <EchezonaComponent />,
  edit: <EchezonaComponent />,
  canMakePayment,
  ariaLabel: "Echezona Payment",
  supports: {
    features: settings.supports || [],
  },
};

registerPaymentMethod(EchezonaPaymentMethod);
