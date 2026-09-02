=== Parcel Forwarding & Package Consolidation for WooCommerce ===
Contributors: pixfeed
Tags: package forwarding, parcel forwarding, mail forwarding, reshipping, freight forwarder
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Run a package forwarding business on WooCommerce: parcel intake, storage fees, consolidation, reshipping and a client portal.

== Description ==

Colisly turns a WooCommerce store into a working package forwarding platform. Each client gets a reference and an address to shop with. Parcels arriving at your warehouse are logged, held, charged for storage past the free period, grouped on request and reshipped as a single shipment, paid through your own checkout.

It runs on your hosting, with your carrier contracts and your prices. Nothing leaves your database.

= Who it is for =

* Anyone starting a package forwarding or reshipping service and looking for the software side of it
* Existing forwarders still running the warehouse on a spreadsheet and an inbox
* Freight forwarders and consolidators who need a customer-facing portal rather than an ERP
* Shops that receive, hold and reship parcels for customers living abroad

Commercial package forwarding software in this category is sold as one-time licences running into four figures, or as monthly SaaS holding your client data. Colisly is free and GPL.

= Operations =

* Client records with unique references (CL000001), multi-criteria search, and a free storage period set per your policy (15 days by default)
* Parcel intake with generated numbers (COL000001), weight, dimensions, photos, internal notes and per-parcel carrier restrictions
* Storage fees calculated automatically once the free period ends
* Consolidation: several parcels held in stock grouped into one outgoing shipment, which is what the trade rests on
* Weight-based pricing tiers and carrier tariffs you define yourself, so any carrier or negotiated contract can be used

= Client side =

* A dedicated area in the WooCommerce My Account page listing parcels, shipments and documents
* Internal fields stay internal: your notes, your dimensions and your cost prices are never exposed
* A shipment request becomes a native WooCommerce order with itemised handling, storage and carrier lines, paid at your usual checkout with your usual gateways
* Private document storage with authenticated downloads, for customs declarations, commercial invoices and proof of delivery

= Practical points =

* Complete French translation included
* Personal data export and erasure through the native WordPress privacy tools
* Hooks and filters throughout, so the workflow can be extended
* Automatic data migration between versions, and optional data removal on uninstall

A typical use is a service reshipping from mainland France towards the French overseas territories, but nothing in the plugin is tied to a country, a currency or a carrier.

== Installation ==

1. Upload the `colisly` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin. WooCommerce must be installed and active.
3. Go to "Colisly → Settings" to configure the pricing tiers, storage fees and carriers.
4. Create a client record from "Colisly → Clients", then register parcels from "Colisly → New parcel".

== Frequently Asked Questions ==

= Can I start a package forwarding business with WordPress? =

Yes, and that is what Colisly is built for. The plugin covers the operational side: client accounts and references, parcel intake, storage fees, consolidation, shipment requests and billing. You supply the warehouse address, the carrier contracts and your pricing.

= How is this different from paid package forwarding software? =

Proprietary platforms in this category are sold as one-time licences running into four figures, or as a monthly subscription where your client records live on someone else's server. Colisly is free, GPL, and runs on your own hosting next to your existing WooCommerce store.

= I run my forwarding service on a spreadsheet. What changes? =

That is where most users come from. The spreadsheet becomes searchable client records, parcel numbers that generate themselves, storage fees that calculate themselves, and a client area that answers "where is my parcel" without an email.

= Which carriers are supported? =

Any of them. Carrier tariffs and weight tiers are defined by you rather than pulled from a fixed integration, which matters in this trade because the margin usually sits in a negotiated or regional contract, not in a public API.

= Can several parcels be consolidated into one shipment? =

Yes, and it is the core of the workflow. The client picks the parcels held in stock, requests one shipment, and the plugin builds a single WooCommerce order combining handling, storage and carrier charges. A parcel can also be flagged as one that must travel alone.

= How do clients pay? =

Through your existing checkout. A shipment request creates a native WooCommerce order, so your payment gateways, taxes and order emails apply with nothing new to configure.

= Do I need WooCommerce? =

Yes. WooCommerce provides the account, order and payment layer that Colisly builds on.

= Can the customer change the grouping permission? =

No. Whether a parcel may be grouped is decided at reception by the operator, since it depends on the contents and the carrier. Grouping is allowed by default. The client chooses which of the groupable parcels to include in a shipment request.

= How are storage fees computed? =

Each parcel is stored free for a configurable period, 15 days by default. Past that, the fee set in the settings applies and is added automatically to the shipment order.

= Are documents private? =

Yes. Documents are stored outside the public uploads flow and downloaded through an authenticated request, so only the client they belong to can retrieve them. Documents not shared with the client stay internal.

= Is the plugin GDPR-ready? =

Yes. Colisly plugs into the native WordPress personal data tools, and the export covers everything the eraser deletes, including internal notes and unshared documents.

= Is any data removed on uninstall? =

Only if you ask for it. Data removal on uninstall is opt-in from the settings, and it also clears the plugin capability and its options.

== Screenshots ==

1. Clients list with multi-criteria search.
2. Client record: indicators and tabs (parcels, shipments, documents, history).
3. Parcel creation form with live client search and the client's current stock.
4. Customer "My parcels" screen in the WooCommerce My Account area.
5. Settings: free storage days, storage fee, weight-based pricing tiers and carrier tariffs.
6. Per-carrier weight brackets, for carriers that publish a grid rather than a price per kilo.

== Changelog ==

= 1.7.0 =
* New: optional shipment insurance. Cover levels are set in the settings, a cover amount and what it costs, and the client picks one when requesting a shipment. It appears as its own line on the WooCommerce order and in the client's shipment list. The price is always read back from the settings rather than taken from the form, so a posted amount can never decide what is billed. No cover level configured means no insurance is offered at all, which is how every existing site starts.
* New: a parcel already in stock can be corrected. Reception happens at the counter, often in a hurry, and until now a wrong weight or a mistyped tracking number had no way back: only the status could be changed. Since the weight sets the price, a typo was billed as it stood. Tracking number, weight, dimensions, photo, internal comment, grouping and allowed carriers are all editable, and correcting the weight recomputes the price.
* Editing stops the moment the parcel leaves stock. A parcel sitting in a shipment the client may already have paid is refused rather than silently repriced, and its client can never be changed after reception. Every correction is written to the client history, naming what changed.
* The carrier table now says when its two prices apply: they are labelled beyond brackets, and a line under the heading states that a carrier is normally priced with a bracket grid and that these two are the fallback. Read on their own they looked like the only carrier pricing there was.
* Fix: the estimate shown to the client on the shipment request ignored the weight brackets added in 1.6.9 and fell back to base price + price per kg. A shipment the checkout billed 45 was announced at 17. The estimate now applies the same rule as the server, and a carrier priced by bracket no longer advertises a price per kg it never charges.

= 1.6.10 =
* Fix: on the shipment request screen the parcel table lost its labels. The stacking added in 1.6.8 hides the table header, and each cell is meant to carry its own label instead; this table was the one that did not. Clients saw a bare checkbox followed by three unexplained values. The two other account tables were already correct, which is why it was missed.

= 1.6.9 =
* New: each carrier can be given its own grid of weight brackets. Carriers rarely bill per kilo, they publish a grid, and a 6 kg parcel at 45 EUR next to a 15 kg one at 150 EUR fits on no straight line. The first bracket whose maximum weight is greater than or equal to the shipment weight sets the price. A carrier left without a grid keeps billing base price + price per kg exactly as before, so nothing changes on existing sites.
* Beyond the last bracket the price falls back to base price + price per kg, but it can now only ever charge more than the last bracket, never less. A grid stopping at 15 kg used to make a 16 kg shipment cheaper than a 15 kg one.
* Fix: the settings tables grew by exactly one blank row per save, so filling in six weight brackets meant saving six times, and nothing on screen said a seventh row was possible at all. Both tables, and every carrier grid, now have an Add a row button.

= 1.6.8 =
* Fix: in the customer account, the parcels and shipments tables were wider than the account column of most themes, so the last column sat off-screen behind a horizontal scrollbar nobody thinks to look for. WooCommerce only stacks these tables under a 768px viewport, but what constrains them is the column, not the window: at a 1600px viewport the six parcel columns still had to fit in 680px. They now stack on the container's own width.
* New screenshot of the settings screen in the plugin directory listing.

= 1.6.7 =
* Fix: a parcel created without stating a grouping decision was stored as "must be shipped alone", against both the column default and the reception form, where grouping is allowed. Grouping is what the whole trade rests on, so the omission now means allowed.
* Privacy: the personal data export now covers what the eraser deletes. Internal notes on the client record, the internal comment on each parcel and the documents that are not shared with the client were being erased on request but never disclosed on access.
* Fix: refusing an action returned HTTP 500, which reads as a server failure to hosts and monitoring. It now returns 403.
* Fix: the client list printed every page number. It now collapses long ranges and shows the number of records.
* Fix: uninstalling with data removal enabled left the colisly_manage capability on every role and one option behind.

= 1.6.6 =
* New: a Settings shortcut on the plugins screen.
* New: a warning on the plugin screens when the store is in coming soon mode or has no payment method enabled, since shipment requests end on the WooCommerce payment page and would otherwise fail with no explanation.
* Fix: adding a client who already has a record announced a creation that did not happen.
* Fix: a pricing tier capped at zero was accepted and silently moved every parcel to the next tier. It is now dropped like an empty row.
* Fix: on narrow screens the parcel dimensions wrapped between a label and its field.
* The allowed carriers help text now states that leaving none checked places no restriction.

== Upgrade Notice ==

= 1.7.0 =
Parcels in stock can now be corrected after reception, and shipments can be
insured. Two columns are added to the shipments table on update; existing
shipments keep their data and show no insurance.

= 1.6.10 =
Restores the labels on the shipment request table, lost in 1.6.8 on themes with
a narrow content column. No database change.

= 1.6.9 =
Carriers can now be priced by weight bracket rather than per kilo. Existing
carriers are untouched. No database change.

= 1.6.8 =
Fixes a column left off-screen in the customer account on themes with a narrow
content column. No database change.

= 1.6.7 =
Privacy export completeness and three fixes found in a second audit. No database change.

= 1.6.6 =
Usability fixes found during a full walkthrough. No database change.
