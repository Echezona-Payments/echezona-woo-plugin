# Echezona Payment Gateway for WooCommerce

A WooCommerce payment gateway plugin for accepting payments via Echezona Payment Gateway.

## Features

- Accept payments via Echezona Payment Gateway
- Support for both classic and block-based checkout
- Test mode for development and testing
- Automatic order completion after successful payment
- Webhook support for payment status updates
- Support for multiple currencies

## WordPress Blocks Support Implementation

This plugin now includes full support for WooCommerce Blocks, allowing the payment method to be used with the new checkout block experience.

### Implementation Overview

1. **Build System Setup**
   - Configured package.json with WordPress and React dependencies
   - Set up webpack configuration to build JavaScript files
   - Generated proper asset files for WordPress script registration

2. **Block Support Implementation**
   - Created EchezonaPaymentContent component in src/index.js
   - Set up proper payment method registration with WooCommerce Blocks API
   - Implemented data passing between PHP and JavaScript via localized script data

3. **PHP Integration**
   - Configured class-wc-eczp-blocks-payment-method.php for script registration
   - Set up class-wc-eczp-blocks-support.php for WooCommerce Blocks integration
   - Implemented proper data localization between server and client

### Testing the Implementation

1. Ensure the plugin is activated in WordPress
2. Go to WooCommerce > Settings > Payments
3. Enable Echezona Payment
4. Create a new page with the WooCommerce Checkout block
5. The payment method should appear in the checkout with proper styling

### Development Workflow

To make changes to the block implementation:

1. Modify the JavaScript code in `src/index.js`
2. Run `yarn build` to compile the changes
3. Test in a WordPress environment with WooCommerce and WooCommerce Blocks enabled

### Requirements

- WordPress 5.9+
- WooCommerce 6.0+
- WooCommerce Blocks 7.0+

## Installation

1. Download the plugin zip file
2. Go to WordPress admin panel > Plugins > Add New
3. Click "Upload Plugin" and select the downloaded zip file
4. Click "Install Now" and then "Activate"

## Configuration

1. Go to WooCommerce > Settings > Payments
2. Click on "Echezona Payment" to configure the gateway
3. Enter your API keys and other settings
4. Save changes

## Development

### Prerequisites

- Node.js (v14 or higher)
- npm (v6 or higher)

### Setup

1. Clone the repository
2. Run `npm install` to install dependencies
3. Run `npm run build` to build the blocks assets
4. Run `npm run start` to start development mode

### Building

```bash
npm run build
```

### Testing

```bash
npm run test:unit
```

## Support

For support, please contact [admin support](hello@echezona.com) or visit our [support portal](https://support.echezona.com).

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by Echezona Payment Gateway Team.
