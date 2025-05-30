import { decodeEntities } from '@wordpress/html-entities';
import { registerBlockType } from '@wordpress/blocks';
import { useEffect } from '@wordpress/element';

const EchezonaPaymentContent = () => {
    const settings = window?.echezonaPaymentData || {};
    
    return (
        <div className="wc-block-components-payment-method-echezona">
            {settings.icon && (
                <img 
                    src={settings.icon} 
                    alt={decodeEntities(settings.title || '')}
                    className="wc-block-components-payment-method-icon"
                />
            )}
            <div className="wc-block-components-payment-method-label">
                {decodeEntities(settings.title || '')}
            </div>
            <div className="wc-block-components-payment-method-description">
                {decodeEntities(settings.description || '')}
            </div>
        </div>
    );
};

const canMakePayment = () => {
    return true;
};

const paymentMethod = {
    name: 'echezona_payment',
    label: window?.echezonaPaymentData?.title || 'Echezona Payment',
    content: <EchezonaPaymentContent />,
    edit: <EchezonaPaymentContent />,
    canMakePayment,
    ariaLabel: window?.echezonaPaymentData?.title || 'Echezona Payment',
    supports: {
        features: window?.echezonaPaymentData?.supports || ['products']
    },
};

// Register the payment method with WooCommerce
const registerPaymentMethod = () => {
    if (window?.wc?.wcBlocksRegistry?.registerPaymentMethod) {
        window.wc.wcBlocksRegistry.registerPaymentMethod(paymentMethod);
    }
};

// Initialize on DOM load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', registerPaymentMethod);
} else {
    registerPaymentMethod();
}
