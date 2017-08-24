=== Plugin Name ===
Contributors: Collne Inc.
Tags: Welcart, e-Commerce, shopping, cart, eShop, store, admin, calendar, manage, plugin, shortcode, widgets, membership
Requires at least: 4.0
Tested up to: 4.8
Stable tag: 1.9.3

Welcart assists you to build online shop system.
It is developed in Japan.

== Description ==

Welcart is a program made in Japan, which assists you to build e-commerce system on Wordpress.

It has not only simple shopping cart function but also other functions necessary to run your store, such as "Order management function" and "membership function".
Welcart corresponds to various types of Wordpress template. 8 wedgets included in the program will help your blog to become an online shop.

= Cart system =

A cart responds to the complex sales; the sales of multiple items each of which you require individual selections of specific conditions. Also, "duties pack discount" function makes it possible to set different prices for supplier purchase.

Furthermore, it has many other functions useful for your store. For example, you can have "Membership system", "selection of shipping condition", and grant "special benefit" to your customers.

= Admin screen =

Orders made on online shop will be transmitted to manager by e-mail. At the same time, orders will be registered in database so that you will be able to check their details on order management screen. On the order management screen, you can edit contents of customer's order. You can send various e-mails such as estimate, confirmation of changes, shipping, from order screen by just clicking icons. This system reduces business burden of manager.
 


[Welcart Documents](http://en.welcart.com/documents/).

[Welcart Community(Japanese)](http://www.welcart.com/wc-com/).

== Installation ==

1. Upload the entire `usc-e-shop` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

= Attention =
 
In the process of activation of plugin, Welcart writes data on tables such as postmeta, options, and terms. When you install a blog existing already, to avoid unexpected damages or any other unexpected situation, backing up is strongly recommended.

Welcart is not responsible or does not have any guarantee for any kind of damage that you get by using or installing Welcart. 
All the procedures must be done with self-responsibility of each user.


== Frequently Asked Questions ==

Please see [Welcart Forum](http://www.welcart.com/community/forums).

== Screenshots ==

1. Page for Item List on the admin screen
2. Page for editing orders on the admin screen
3. An attached default theme

== Changelog ==

= V1.9 =
5 Oct 2016
-----------
* Add a new settlement module 'WelcartPay'
* Fixed a bug that the sub-image is not recognized

= V1.8 =
8 Apr 2016
-----------
* Corresponding to WordPress 4.5
* Add a new order list function
* Add a new member list function

= V1.7 =
29 Jan 2016
-----------
* Specification Change of "wc_templates" of child theme.
* Session checking additional when user opens multiple tabs in the confirmation page.
* Some bug fixes.

= V1.6 =
30 Oct 2015
-----------
* Added the function to search an address using the japanese postal codes.
* Added the function to output the Ganbar-Tencho-CSV data.[Extensions]
* Added the option to describe customer name at the beginning of e-mail message.
* Changed the specification so that it can register the trucking number and shipping company name,in order edit screen.
* Fixed the bug that reservations post is not published in that time, when the bulk product registration.

= V1.5 =
1 Oct 2015
-----------
* When the scope of the point of the "applied to a trade total amount and fees", change the specification so that it is paid with consumption tax, including full point
* Add check boxes and radio buttons in the product options
* Compatible with PayPal WebPaymentPlus
* Some vulnerability fixes

= V1.4 =
30 May 2014
-----------
* PHP5.4 formal correspondence
* The changes to the database structure of orders related
* Change the specifications so as to enable the trade name search by an order list 
* Change the specifications so that the registration of Welcart member can be from the management screen
* Equipped with a standard transformer Berri settlement 
* Added a user rights only Welcart

= V1.3 =
15 Mar 2013
-----------
* Japanese credit payment service has been added.

= V1.2 =
8 Sep 2012
-----------
* It functions and adds a product details page to OGP correspondence
* It functions to set the amount of purchase upper limit in case of the C.O.D. and adds it
* I add the recomputation functions such as a consumption tax or the point of the order data editing
* In the case of a new sign-in, I add the option which I e-mail a manager, and is transmitted
* I add backup & reset possible function with an option level (basic setting, others)

= V1.1 =
25 Jan 2012
-----------
* Change specifications Registration also allows large amounts of data at once, CSV export and import of product data.
* Added the ability to disable 'usces_cart.css'.
* Added the ability to randomize the order number string.
* Added the ability to choose the applicable rules of product Sub-Image.
* Changed the specification to order that can be changed by dragging the SKU and product options.
* Changed the specification to order that can be changed by dragging common options and payment methods.

= V1.0 =
30 Apr 2011
-----------
* Welcart Default theme is upgraded to 1.1. wc_templates is implemented.
* Global currency and form entry is supported.
* Requested delivery date calculation function is added.
* PayPal Express Checkout function is included.
* PDF (Packing list ext) export function is modified; invoice and receipt are added.
