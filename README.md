# Echezona Payment Gateway for WooCommerce

A WooCommerce payment gateway plugin for accepting payments via Echezona Payment Gateway.

## Features

- Accept payments via Echezona Payment Gateway
- Support for both classic and block-based checkout
- Test mode for development and testing
- Automatic order completion after successful payment
- Webhook support for payment status updates
- Support for multiple currencies

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

For support, please contact hello@echezona.com or visit our [support portal](https://support.echezona.com).

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by Echezona Payment Gateway Team.
