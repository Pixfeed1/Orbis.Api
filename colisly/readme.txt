=== Parcel Forwarding & Package Consolidation for WooCommerce ===
Contributors: pixfeed
Tags: package forwarding, parcel forwarding, mail forwarding, reshipping, freight forwarder
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.14.1
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

= 1.14.1 =
* Fixed: the carriers table in the settings was squeezed into fields two characters wide since 1.14.0 added its three limit columns, a width cap that suited seven columns being kept for ten. The table now takes the width it needs and scrolls sideways when the screen has less.

= 1.14.0 =
* New: carrier limits. Each carrier can carry a maximum weight, a maximum length and a maximum girth (length plus twice the width plus twice the height), the figures carriers publish and refuse beyond at the counter. The weight applies to the whole shipment, since grouped parcels leave in one carton; the dimensions to each parcel, on those entered at reception. A carrier the ticked parcels exceed is greyed out in the client's list, and a request that would force it is refused naming the parcel and the limit. A parcel whose dimensions were never entered is not refused on a measurement nobody took. All three are optional and empty by default.
* The weight bracket page reads in the order it applies: each carrier shows its zone grids first, then a grid titled "all other destinations", with a note saying to leave it empty when every destination served is in a zone. Nothing changes in how prices are computed; the page only says what it does.

= 1.13.1 =
* Fixed: a carrier enabled but never priced was offered to clients at 0.00 and the order went through at that price, the fallback formula with an empty base and an empty price per kg being simply zero. A carrier with no rate for the client's destination is no longer offered, a request that would force it is refused naming the carrier, and the settings warn about enabled carriers that have no rate anywhere. A bracket explicitly set to zero still counts as a price: a forwarder who includes transport in his service typed it on purpose.

= 1.13.0 =
* New: purchase invoices for customs. Customs outside the EU ask for the commercial invoice next to the declaration, and only the client has it. He attaches it to each parcel, on the shipment request or on the customs tab, PDF or image, several if needed. The forwarder finds the invoices on the parcel and on the shipment, the printed customs form states how many are attached, and the client reads them back from his documents. Stored in the private directory like every other document, served only through the authenticated download, covered by the privacy export and eraser.
* Every carrier now shows what it would charge for the parcels ticked, right in the carrier list, so the client compares before choosing rather than trying them one by one. The figure follows the same brackets, zones and volumetric rule as the checkout.
* A declared line must carry a value. Customs assess duty on it, so a line without one declared nothing they could use; it is now refused with the parcel and the contents named, on the request as on the customs tab. The form asks for the value as soon as the contents are filled.
* Two columns and one index are added to the documents table on update.

= 1.12.0 =
* A customer without a client record yet can be picked straight from the parcel creation form. The search now offers the shop's registered users alongside the clients, marked as new, and the record is created with the first parcel. Until now every new customer had to be created by hand on the Clients tab before his first parcel could be booked in, which is not where the operator is standing when the parcel arrives. The Clients tab keeps its manual creation for whoever wants a record ahead of time.

= 1.11.1 =
* Fixed: the client search, on the parcel creation form and on the parcels list, read the WordPress first and last name only. A customer created by WooCommerce, at checkout or from its Customers screen, carries a billing name and usually no WordPress name at all, so the operator typed the name he saw on every order and found nobody, while the account sat in the Clients tab. The search now matches the billing and delivery names, the company, the phone and the login, and a first name and last name typed together find the client whichever order they come in.
* Clients are named by their billing name when WordPress only knows them by their login, on the Clients tab, the parcels list and the search results alike. "fabrice-1" is not a name anybody recognises.

= 1.11.0 =
* Fixed: the declaration form offered a single blank line whatever the limit set in the settings. A cap of three lines was a promise the form never kept, since the client could only ever declare one item per submission, and on the shipment request there is no second submission. A cap now gives the client exactly that many lines; without a cap he gets a few and a button for the rest.
* The quantity, the unit weight and the country of origin can each be turned off. They are what a real CN23 form needs line by line, but a forwarder who only wants to know what a parcel holds before copying it onto his carrier's own form needs none of the three, and three columns filled for nothing are three columns filled badly. All three stay asked by default, so a site collecting them keeps collecting them.

= 1.10.0 =
* The shipment request now shows the delivery address the parcels are actually reshipped to, and a request can no longer be sent while that address is incomplete, with the missing lines named. The form used to ask for a destination country and nothing else, so a request could reach the forwarder with no street to deliver to, and an account that only ever filled a billing address produced an order carrying no destination at all.
* The destination is the address itself rather than a separate menu beside it. The two could disagree, the transport being priced for one country while the label was printed for another, and only the label was true.
* A client can withdraw his own shipment request as long as it is unpaid. It used to be a dead end: the request sat in the orders to pay with nothing on offer but paying it. Withdrawing puts the parcels back in stock and cancels the unpaid order with them.
* Zone countries can be picked by name from the list of countries the shop knows, and the codes already in the field are spelled out underneath, unrecognised ones flagged. Two-letter codes are what a carrier grid is keyed on, but nobody is expected to know that YT is Mayotte.
* The customs declaration on the request form now appears only when the destination actually requires one, instead of for every client as soon as a single zone asked for declarations.

= 1.9.1 =
* The declaration is filled where it belongs, on the shipment request, for the parcels being sent. The separate tab stays for clients who prefer to declare each parcel as it arrives; both write the same thing.
* The contents field can be turned into a menu: fill a list of categories in the settings and clients pick from it instead of typing. Empty by default, so nothing is imposed, and no trade's vocabulary ships with the plugin. The number of lines a parcel may declare can be capped to what your carrier forms hold, uncapped by default.
* The operator reads the whole declaration on the shipment itself, gathered across its parcels with the total declared value, which is the sheet to copy onto a carrier's own form.

= 1.9.0 =
* New: customs declarations. Reshipping outside the customs territory needs the contents of each parcel declared, item by item, and until now the forwarder had to collect that by e-mail. The client now declares his parcels from a Customs declaration tab in his account: description, quantity, unit weight, unit value and country of origin per line. A shipment to a destination that requires one is refused while a selected parcel is still undeclared, with the parcel named.
* Which destinations require a declaration is set on the zones, not guessed. Reshipping from mainland France to Guadeloupe needs one, since the overseas departments sit outside the EU VAT territory, while reshipping to Belgium needs none; a country code cannot tell those apart. Tick the customs column on the zones concerned and nothing changes for anyone who does not.
* The operator prints the declaration from the parcel list or the client record: sender, recipient, one line per item with its tariff number and origin, the totals a customs form asks for, and the certification to sign. It warns when the declared contents weigh more than the parcel itself, which customs would stop on.
* The declaration is personal data: it is disclosed in the privacy export and removed by the eraser, like everything else the plugin holds.

= 1.8.0 =
* New: carriers can be billed on volumetric weight. Express carriers price bulk rather than mass, so a carrier can now be marked volumetric with its own divisor, 5000 by default. The transport is then billed on whichever is greater, the real weight or length x width x height divided by the divisor, parcel by parcel. That is how the carriers themselves compute it: billing the volumetric weight instead of the real one would charge a dense 20 kg box in a small carton as 1.6 kg. A parcel whose dimensions were never entered is billed on its real weight rather than on a volume of nothing.
* New: destination zones. A forwarder does not charge the same to reship to mainland France, to the overseas departments and to Madagascar, and a single grid per carrier could never hold real tariffs. Zones group destination countries, and each carrier gets a weight bracket grid per zone. The client picks the destination when requesting a shipment, starting from the shipping address on his account, and the live estimate follows it. A country in no zone, or a zone a carrier was never priced for, keeps that carrier's default grid, so nothing changes for a site that does not use zones.

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

= 1.14.1 =
Fixes the squeezed carriers table in the settings. No database change.

= 1.14.0 =
Carriers can carry weight and dimension limits, and the bracket page reads
in the order it applies. No database change.

= 1.13.1 =
A carrier with no rate for a destination is no longer offered at 0.00. No
database change.

= 1.13.0 =
Clients can attach purchase invoices for customs, every carrier shows its
price in the list, and a declared line must carry a value. Two columns are
added to the documents table on update.

= 1.12.0 =
New customers can be picked directly on the parcel form; their client record
is created with their first parcel. No database change.

= 1.11.1 =
The client search now finds customers by their billing name, company, phone
and login, and names them by their billing name when WordPress only knows
their login. No database change.

= 1.11.0 =
Fixes a declaration form that offered one line whatever the limit set, and lets
the quantity, unit weight and country of origin be turned off. No database
change.

= 1.10.0 =
The shipment request shows and requires a complete delivery address, clients
can withdraw an unpaid request themselves, and zone countries are picked by
name. No database change.

= 1.9.1 =
The customs declaration is now filled on the shipment request, the contents
field can be a menu of your own categories, and the declaration is readable on
the shipment. No database change.

= 1.9.0 =
Clients can now declare the contents of their parcels for customs, and the
declaration prints as a form. One table is added on update. Nothing changes
until a zone is marked as requiring a declaration.

= 1.8.0 =
Carriers can now be priced per destination zone. Existing grids keep applying
everywhere. One column is added to the shipments table on update.

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
