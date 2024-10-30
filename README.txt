=== Biocryptology Login ===
Contributors: biocryptology
Tags: biocryptology, comments, security, password, login, identification, online identification, authentication, fingerprint, fingerprint recognition, fingerprint reader, biometrics, biometric recognition, biometric data, identity theft, identity fraud, privacy, single sign on
Requires at least: 4.0.0
Tested up to: 5.6.0
Requires PHP: 5.6
Stable tag: 1.2.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Provide your users with secure and frictionless access without usernames and passwords.

== Description ==

Free your users and visitors from usernames and passwords thanks to the safe, personal and easy to use service supported by Biocryptology.

The solution to the problems of identity fraud and identity theft, data breaches, access inconvenience and physical access is in biometrics. Thanks to the security ecosystem of Biocryptology, our partners offer a simple, secure and fast way of identification and authentication to their users and visitors.

Biocryptology helps protect users and companies that need a secure access protocol by providing a trusted and secure identification system for access control. The system’s core part is a platform based on OpenID Connect as the standard way to relay user access data (Federated Identity Service), providing thus security for authentication and authorization of users. So, Biocryptology is designed to be used in almost any situation you can imagine, enabling to login with biometric data (fingerprint or face recognition) anywhere for free.

Biocryptology is based on the highest requirements of performance, security and privacy in order to develop an end-to-end security environment. In order to guarantee the best integration with services and sites developed in WordPress, here you have the Biocryptology Login plugin. Download and install it now and offer the most secure way of access to your users and visitors. They will no longer need to use passwords and usernames.

Customer satisfaction is our top priority. Please, write us your feedback about your experience or any problem you may have at support@biocryptology.com.

== Installation ==

1.  Install Biocryptology Login automatically or by uploading the ZIP file.
2.  Activate the plugin through the Plugins menu in WordPress.
3.  Configure the plugin:
    3.1.  Access WordPress with administrator privileges. Select the Settings menu from the dashboard, then click on the Biocryptology Config option to start the process.
    3.2.  In the Biocryptology Connect Settings screen, copy the URL provided in the Redirect/Callback URL field to enable redirection of users to the Biocryptology login page.
    3.3.  Then, enter the URL to redirect users after logging out the system in the Logout URL field. This field shows the home  page of the site developed with Wordpress. Edit this field with a desired URL, if appropriate.
    3.4.  Log into Biocryptology through our website or following this URL: http://id.biocryptology.com.
    3.5.  In the Clients option from the dashboard, click the Add Client button to enable the plugin.
    3.6.  Indicate the corresponding data:
        3.6.1.  Select a picture that illustrates the website developed with WordPress.
        3.6.2.  Indicate a descriptive name for the web site that is being integrating with Biocryptology.
        3.6.3.  Paste the URL copied previously in step 3.2, followed by the return URL set in step 3.3, separated with commas (,).
        3.6.4.  Once completed, click on the Save plugin button to enable the plugin.
    3.7.  If the plugin has been successfully enabled, it will be shown in the OpenID Clients List of the main screen. 3.8.  Click the corresponding Edit button and copy the Client ID number (generated automatically when enabling the plugin).
    3.9.  Go to the Settings menu from the WordPress dashboard and paste the client’s identifier (previously copied) in the Client ID field from the API Config tab.
    3.10.  Save changes to complete the process. Now the Biocryptology plugin is properly configured in WordPress.

Once the Biocryptology plugin for WordPress is correctly installed and configured, the login button for Biocryptology will be automatically displayed in the login page of the corresponding website. Thus, the website’s users will login securely via Biocryptology.

== Frequently Asked Questions ==

== Screenshots ==

1.  The Biocryptology plugin for WordPress now appears in the list of installed plugins.
2.  Access to WordPress with administrator privileges. Select the Settings menu from the dashboard, then click on Biocryptology Config to start the process.
3.  In the Biocryptology Connect: Settings screen, copy the URL provided in the Redirect or Callback url field. This information is automatically provided by the Biocryptology plugin installed, and it allows to redirect users to the Biocryptology login page.
4.  Screen with uninstallation options.

== Changelog ==

= 1.0.0 =
* Initial version.

= 1.0.1 =
* "Logout url" field added.

= 1.0.2 =
* Visual improvements in the plugin configuration.
* Added the use of real nonce.

= 1.0.3 =
* Solved the error with the nonces.

= 1.0.4 =
* Fixed synchronization problem between user tables.

= 1.0.5 =
* Improvements in the auxiliary table.

= 1.0.6 =
* New Oauth2 client library.

= 1.0.7 =
* Improvements in the control of emails with unicode characters.

= 1.0.8 =
* The Post Logout URL field in the administration panel has been deleted.
* The auto creation of new users is now configurable, allowing you to provide a specific URL for user creation when deactivated.
* You can now configure the user data info sent to Wordpress when signing in with the Biocryptology app.

= 1.0.9 =
* The new "Secret key" field was added to improve security.

= 1.0.10 =
* Security improvements.

= 1.1.0. =
* Quick setup added.

= 1.2.0. =
* Auxiliary user data capture - The option has been completed so that your website can receive all auxiliary user data, such as address, etc.
* Multi-language - You can configure the languages you want in the plug-in to adapt to the structure of languages you have mounted in your plug-in.
* Automatic verification of the domain - Security system to prevent phishing of your page we face your users. They will know that only your page is the real one with an icon that indicates it in the app.

= 1.2.1. =
* Bug fixing.

= 1.2.2. =
* This update improves the way the log-in button is displayed. An adaptation has been made so that it integrates better into the design of your website.

== Additional info ==
 
Together with the readme.txt file, the installation and configuration manual for Biocryptology Login in WordPress is attached.
