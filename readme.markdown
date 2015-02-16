Fat Zebra WHMCS Library 1.0
===========================

Adds gateway support for Fat Zebra to WHMCS.

Tested with WHMCS version 5.3.5

Installation
------------

1. Extract the archive to your local filesystem.
2. Using a FTP client or similar copy the **modules** folder to your WHMCS root path
   Alternatively you can upload **fatzebra.php** and **cacert.pem** to [whmcs_root]/modules/gateways
3. In WHMCS Admin goto the **Setup** menu and select **Payments** -> **Payment Gateways**
4. Select **Credit Card (Fat Zebra)** from the **Activate Module** drop down list and click **Activate**
5. Update the settings (username, token, enable or disable sandbox) and click **Save Changes** - payments via Fat Zebra will now be enabled.

**Note:** If you are a service provider who stores the customers credit card details under PCI-DSS standards you will require a **Cart Token** from Fat Zebra to permit the transactions
to be processed without the security code (CVV etc). Please contact Fat Zebra Support (support@fatzebra.com.au) to request this token if you have not been provided with one.

Support
-------

For support with this library please contact support@fatzebra.com.au. While no warranty or guarantee is incldued with this library Fat Zebra will do its best to provide support to merchants experiencing issues.