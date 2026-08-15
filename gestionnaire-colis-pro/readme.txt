=== Gestionnaire Colis Pro ===
Contributors: pixfeed
Tags: woocommerce, parcels, shipping, logistics, clients
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Client and parcel management for a parcel receiving, storage, grouping and forwarding business, natively integrated with WooCommerce.

== Description ==

Gestionnaire Colis Pro provides a complete client and parcel management system for a parcel forwarding business: receiving, storage, grouping and shipping of parcels (for example towards the French overseas territories). The plugin interface is currently provided in French.

= Client management module =

* Every client is linked to a WordPress/WooCommerce user and gets an internal record with a short unique reference (CL000001).
* Search clients by reference, first name, last name, e-mail address or phone number.
* The client record centralizes everything: client information, reference, parcels in stock, shipped parcels, shipments, transmitted documents and the full operation history.
* Automatically computed indicators: parcels currently in stock, total stored weight, shipments done, storage fees due, last parcel reception, last shipment.
* Free storage period per parcel (15 days by default, configurable), then automatic storage fees per day.
* From the client record: create a parcel, browse parcels, prepare a shipment, check documents and read the history.

= Parcel creation module =

* Search the client by internal reference, name or e-mail, with an immediate view of their parcels still in stock.
* Complete form: carrier tracking number, real weight, dimensions (visible to administrators only), optional reception photo, internal comment reserved for the staff, grouping allowed or forbidden, allowed carriers per parcel.
* Unique parcel number generated automatically (COL000001), reception date, creator and initial "available" status.
* Life cycle: available, ordered, awaiting payment, paid, preparing, shipped, destroyed, cancelled.
* The parcel price is computed automatically from its weight (configurable tiers) and stored at reception time.

= Customer side =

Customers only see the information meant for them (parcel number, reception date, tracking number, weight, status, grouping allowed) in their WooCommerce My Account area: "Mes colis" (my parcels), "Mes expéditions" (my shipments), "Mes documents" (my documents) and "Demande d'expédition" (shipment request). Internal comments and dimensions are never displayed to customers.

= Native WooCommerce payments =

Each shipment request creates a detailed WooCommerce order: one fee line per parcel, a storage fee line and the chosen carrier as a native shipping line priced from its configurable tariff (base price + price per kg). The customer is redirected to the standard WooCommerce payment page and pays with any payment gateway enabled in the shop. Statuses stay synchronized both ways: order paid → shipment "paid"; shipment "shipped" → order completed; order cancelled → parcels returned to stock. The shipment request form shows each carrier tariff, disables carriers that are not allowed for the selection, and displays a live total estimate (parcels + storage + transport).

= Native WooCommerce e-mails =

Two real WooCommerce e-mails are registered under WooCommerce → Settings → Emails: "Parcel received" (sent to the customer) and "Shipment requested" (sent to the staff, with configurable recipients). They use the shop e-mail template and can be overridden from the theme. Without WooCommerce, a plain-text fallback keeps notifications working.

= Private documents =

Client documents and reception photos are stored in a protected directory (direct access denied, randomized file names) and served only through an authenticated download endpoint: a customer can only download their own documents marked as visible; reception photos are restricted to the staff.

= Extensible =

The architecture is built to grow: actions and filters (`gcp_parcel_created`, `gcp_shipment_requested`, `gcp_carriers`, `gcp_parcel_price`, `gcp_carrier_price`, …) allow adding import/export, statistics, loyalty programs, a REST API or multi-warehouse support without touching the core.

== Installation ==

1. Upload the `gestionnaire-colis-pro` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin. WooCommerce must be installed and active.
3. Go to "Colis Pro → Réglages" to configure the pricing tiers, storage fees and carriers.
4. Create a client record from "Colis Pro → Clients", then register parcels from "Colis Pro → Nouveau colis".

== Frequently Asked Questions ==

= Can the customer change the grouping permission? =

No. This decision is made by the staff only, when the parcel is received, and can never be changed by the customer. A parcel whose grouping is forbidden must be shipped alone.

= How are storage fees computed? =

Every parcel gets a free storage period (15 days by default). Beyond it, fees are computed automatically per day and per parcel using the amount defined in the settings.

= Are documents private? =

Yes. Client documents and reception photos are stored in a protected directory (direct access denied, randomized file names) and served only through an authenticated download endpoint: a customer can only download their own documents marked as visible; reception photos are restricted to the staff.

= Is any data removed on uninstall? =

Not by default. Add the `gcp_remove_data_on_uninstall` option with the value `yes` to remove the tables, settings and private files when the plugin is deleted.

== Screenshots ==

1. Clients list with multi-criteria search.
2. Client record: indicators and tabs (parcels, shipments, documents, history).
3. Parcel creation form with live client search and the client's current stock.
4. Customer "My parcels" screen in the WooCommerce My Account area.

== Changelog ==

= 1.3.1 =
* The readme is now written in English, as required by the WordPress.org plugin directory.
* Development files are no longer shipped inside the plugin folder.

= 1.3.0 =
* Carrier tariffs (base price + price per kg) configurable in the settings; transport is billed on the native shipping line of the WooCommerce order and included in the shipment total.
* Shipment request: each carrier shows its tariff, incompatible carriers are disabled, and a live total estimate (parcels + storage + transport) is displayed during selection.
* Native WooCommerce e-mails: "Parcel received" (customer) and "Shipment requested" (staff, configurable recipients) registered under WooCommerce → Settings → Emails, with HTML/plain templates that can be overridden from the theme; wp_mail fallback without WooCommerce.

= 1.2.0 =
* Native WooCommerce payments: each shipment request creates a WooCommerce order (one fee line per parcel, storage fees, carrier as shipping line); the customer is redirected to the standard payment page and a WooCommerce customer invoice e-mail (with payment link) can be sent automatically.
* Two-way status synchronization: order paid → shipment paid; shipment shipped → order completed; order cancelled → shipment cancelled and parcels returned to stock.
* Client record: the "Order" column links each shipment to its WooCommerce order; the customer area shows a "Pay" button while the order awaits payment.

= 1.1.0 =
* Security: client documents and reception photos are now stored in a private directory (.htaccess + random names) and served through an authenticated download endpoint with ownership checks (nonce + capability). Restricted file types (images, PDF, Office).
* Fix: weights and dimensions typed with a decimal comma ("2,5") are now interpreted correctly.

= 1.0.0 =
* Initial release: client management (CL references), parcel creation (COL references), automatic weight-based pricing, automatic storage fees, grouping rules, carrier restrictions, shipment requests, documents, history and e-mail notifications.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
