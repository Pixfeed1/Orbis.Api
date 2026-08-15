=== Gestionnaire Colis Pro ===
Contributors: pixfeed
Tags: woocommerce, colis, expedition, logistique, clients
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gestion des clients et des colis pour une activité de réception, stockage, regroupement et expédition de colis, intégrée à WooCommerce.

== Description ==

Gestionnaire Colis Pro fournit un système complet de gestion des clients et des colis pour une activité de réexpédition (réception, stockage, regroupement et expédition de colis, notamment vers les DOM-TOM).

= Module de gestion des clients =

* Chaque client est associé à un utilisateur WordPress/WooCommerce et dispose d'une fiche interne avec une référence unique courte (CL000001).
* Recherche d'un client par référence, nom, prénom, adresse e-mail ou numéro de téléphone.
* La fiche client centralise : informations du client, référence, colis en stock, colis expédiés, expéditions, documents transmis et historique des opérations.
* Indicateurs calculés automatiquement : nombre de colis en stock, poids total stocké, nombre d'expéditions réalisées, frais de stockage dus, dernière réception, dernière expédition.
* Stockage gratuit pendant 15 jours (configurable), puis frais calculés automatiquement selon les réglages.
* Depuis la fiche client : créer un colis, consulter les colis, préparer une expédition, consulter les documents, accéder à l'historique.

= Module de création des colis =

* Recherche du client par référence interne, nom ou e-mail avec affichage immédiat de ses colis encore en stock.
* Formulaire complet : numéro de suivi, poids réel, dimensions (visibles uniquement des administrateurs), photo à la réception (facultative), commentaire interne réservé à l'administration, autorisation ou interdiction du regroupement, transporteurs autorisés.
* Numéro unique de colis généré automatiquement (COL000001), date de réception, créateur et statut initial « disponible ».
* Cycle de vie : disponible, commandé, en attente de paiement, payé, en préparation, expédié, détruit, annulé.
* Tarif calculé automatiquement selon le poids (paliers configurables) et enregistré dès la réception.

= Côté client =

Le client ne voit que les informations utiles (numéro de colis, date de réception, numéro de suivi, poids, statut, regroupement autorisé) dans son espace Mon compte WooCommerce : « Mes colis », « Mes expéditions », « Mes documents » et « Demande d'expédition ». Les commentaires internes et les dimensions ne sont jamais affichés au client.

= Extensible =

Architecture pensée pour évoluer : actions et filtres (`gcp_parcel_created`, `gcp_shipment_requested`, `gcp_carriers`, `gcp_parcel_price`, …) pour ajouter import/export, statistiques, fidélité, API REST ou multi-entrepôts sans modifier le cœur.

== Installation ==

1. Téléversez le dossier `gestionnaire-colis-pro` dans `/wp-content/plugins/`, ou installez-le depuis l'écran Extensions.
2. Activez l'extension. WooCommerce doit être installé et actif.
3. Rendez-vous dans « Colis Pro → Réglages » pour configurer les paliers de tarification, les frais de stockage et les transporteurs.
4. Créez une fiche client depuis « Colis Pro → Clients », puis enregistrez vos colis depuis « Colis Pro → Nouveau colis ».

== Frequently Asked Questions ==

= Le client peut-il modifier l'autorisation de regroupement ? =

Non. Cette décision est prise uniquement par l'administration lors de la réception du colis et n'est jamais modifiable par le client. Un colis dont le regroupement est interdit doit être expédié seul.

= Comment sont calculés les frais de stockage ? =

Chaque colis bénéficie d'une période de stockage gratuite (15 jours par défaut). Au-delà, les frais sont calculés automatiquement par jour et par colis selon le montant défini dans les réglages.

= Les données sont-elles supprimées à la désinstallation ? =

Par défaut, non. Ajoutez l'option `gcp_remove_data_on_uninstall` avec la valeur `yes` pour supprimer les tables et réglages lors de la désinstallation.

== Screenshots ==

1. Liste des clients avec recherche multi-critères.
2. Fiche client : indicateurs et onglets (colis, expéditions, documents, historique).
3. Formulaire de création d'un colis avec recherche du client et stock affiché.
4. Espace client « Mes colis » dans Mon compte WooCommerce.

== Changelog ==

= 1.0.0 =
* Version initiale : gestion des clients (références CL), création des colis (références COL), tarification automatique au poids, frais de stockage automatiques, regroupement, restrictions de transporteurs, demandes d'expédition, documents, historique et notifications e-mail.

== Upgrade Notice ==

= 1.0.0 =
Version initiale.
