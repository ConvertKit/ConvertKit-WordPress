# ConvertKit for WordPress

Welcome to the ConvertKit for WordPress repository on GitHub. Here you can view the plugin source code, review open issues, and see releases of the plugin.

**If you're not a developer, please download the plugin from [WordPress.org](https://wordpress.org/plugins/convertkit/). Downloading and installing directly from GitHub will not work.**

## Support

If you are having issues setting up this plugin on your WordPress site, or have issues with your ConvertKit forms please do the following:

* Check the [Knowledge Base](https://help.convertkit.com/) for an answer to your question.
* If you're still having trouble contact [ConvertKit Support](https://convertkit.com/support/).

## Getting Started

If you're a developer and would like to run this plugin locally:

1. Make sure you have a local web server running PHP. This can be the built-in server that comes with Macs, a Docker setup, [MAMP](https://mamp.info), or [Local by Flywheel](https://local.getflywheel.com/) or [DevKinsta] (https://kinsta.com/devkinsta/).
2. [Download](https://wordpress.org/download/) and [setup](https://codex.wordpress.org/Installing_WordPress#Famous_5-Minute_Installation) WordPress.
3. Download a [zip release](https://github.com/ConvertKit/ConvertKit-WordPress/releases) of this repo, or clone it to the `/wp-contents/plugins/` folder of your local WordPress install.
4. Run `composer install` in the ConvertKit plugin directory.
4. Login to WordPress as an Administrator user.
5. Go to Settings > ConvertKit.
6. Add your ConvertKit API Key and API Secret.
7. Check the Debug checkbox.
8. Press Save Changes.

If your API key is correct, and your computer can connect to the ConvertKit API, the Default Form dropdown will populate with forms from your ConvertKit account. Set a default form and press Save Changes.
