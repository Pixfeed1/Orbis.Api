Traduction française du readme.txt, destinée au sous-projet
« Stable Readme » sur https://translate.wordpress.org/projects/wp-plugins/colisly/

Les en-têtes (Contributors, Tags, Requires at least…) ne se traduisent pas :
ils n'apparaissent pas dans le projet de traduction.

La structure ci-dessous suit exactement celle de colisly/readme.txt, section par
section, pour que chaque chaîne de GlotPress se retrouve au même endroit.

================================================================================
DESCRIPTION COURTE
================================================================================

Réexpédition de colis et de paquets pour WooCommerce : fiches clients,
réception, frais de stockage, groupage et expédition.

================================================================================
== Description ==
================================================================================

Colisly Parcel Forwarding fournit un système complet de gestion des clients et
des colis pour une activité de réexpédition : réception, stockage, groupage et
réacheminement de colis (vers les DOM-TOM, par exemple). Il convient aux
réexpéditeurs et aux services d'adresse virtuelle dont la boutique tourne déjà
sous WooCommerce. L'interface est en anglais et peut être traduite dans
n'importe quelle langue via translate.wordpress.org.

= Module de gestion des clients =

* Chaque client est rattaché à un compte WordPress/WooCommerce et dispose d'une
  fiche interne avec une référence courte et unique (CL000001).
* Recherche des clients par référence, prénom, nom, adresse e-mail ou numéro de
  téléphone.
* La fiche client centralise tout : informations du client, référence, colis en
  stock, colis expédiés, expéditions, documents transmis et historique complet
  des opérations.
* Indicateurs calculés automatiquement : colis actuellement en stock, poids
  total stocké, expéditions réalisées, frais de stockage dus, dernière réception
  de colis, dernière expédition.
* Période de stockage gratuit par colis (15 jours par défaut, configurable),
  puis frais de stockage automatiques par jour.
* Depuis la fiche client : créer un colis, parcourir les colis, préparer une
  expédition, consulter les documents et lire l'historique.

= Module de création de colis =

* Recherche du client par référence interne, nom ou e-mail, avec affichage
  immédiat de ses colis encore en stock.
* Formulaire complet : numéro de suivi du transporteur, poids réel, dimensions
  (visibles par les administrateurs uniquement), photo de réception facultative,
  commentaire interne réservé à l'équipe, groupage autorisé ou interdit,
  transporteurs autorisés colis par colis.
* Numéro de colis unique généré automatiquement (COL000001), date de réception,
  créateur et statut initial « disponible ».
* Cycle de vie : disponible, commandé, en attente de paiement, payé, en
  préparation, expédié, détruit, annulé.
* Le tarif du colis est calculé automatiquement à partir de son poids (paliers
  configurables) et enregistré au moment de la réception.

= Côté client =

Les clients ne voient que les informations qui leur sont destinées (numéro de
colis, date de réception, numéro de suivi, poids, statut, groupage autorisé)
depuis leur espace Mon compte WooCommerce : « Mes colis », « Mes expéditions »,
« Mes documents » et « Demande d'expédition » (mes-colis, mes-expeditions,
mes-documents et demande-expedition sur les sites francophones). Les
commentaires internes et les dimensions ne leur sont jamais affichés.

= Paiements WooCommerce natifs =

Chaque demande d'expédition crée une commande WooCommerce détaillée : une ligne
de frais par colis, une ligne de frais de stockage et le transporteur choisi
sous forme de ligne de livraison native, tarifée depuis son barème configurable
(prix de base + prix au kilo). Le client est redirigé vers la page de paiement
WooCommerce standard et règle avec n'importe quelle passerelle activée sur la
boutique. Les statuts restent synchronisés dans les deux sens : commande payée →
expédition « payée » ; expédition « expédiée » → commande terminée ; commande
annulée → colis remis en stock. Le formulaire de demande d'expédition affiche le
tarif de chaque transporteur, désactive ceux qui ne sont pas autorisés pour la
sélection en cours, et affiche une estimation totale en direct (colis + stockage
+ transport).

= E-mails WooCommerce natifs =

Deux véritables e-mails WooCommerce sont enregistrés sous WooCommerce →
Réglages → E-mails : « Colis réceptionné » (envoyé au client) et « Demande
d'expédition » (envoyé à l'équipe, avec destinataires configurables). Ils
utilisent le modèle d'e-mail de la boutique et peuvent être surchargés depuis le
thème. Sans WooCommerce, un repli en texte brut maintient les notifications.

= Documents privés =

Les documents des clients et les photos de réception sont stockés dans un
répertoire protégé (accès direct refusé, noms de fichiers aléatoires) et servis
uniquement via un point de téléchargement authentifié : un client ne peut
télécharger que ses propres documents marqués comme visibles ; les photos de
réception sont réservées à l'équipe.

= Extensible =

L'architecture est conçue pour évoluer : des actions et des filtres
(`colisly_parcel_created`, `colisly_shipment_requested`, `colisly_carriers`,
`colisly_parcel_price`, `colisly_carrier_price`, …) permettent d'ajouter
import/export, statistiques, programmes de fidélité, API REST ou gestion
multi-entrepôts sans toucher au cœur.

================================================================================
== Installation ==
================================================================================

1. Déposez le dossier `colisly` dans `/wp-content/plugins/`, ou installez
   l'extension depuis l'écran Extensions.
2. Activez l'extension. WooCommerce doit être installé et actif.
3. Rendez-vous dans « Colisly → Réglages » pour configurer les paliers
   tarifaires, les frais de stockage et les transporteurs.
4. Créez une fiche client depuis « Colisly → Clients », puis enregistrez des
   colis depuis « Colisly → Nouveau colis ».

================================================================================
== Frequently Asked Questions ==
================================================================================

= Le client peut-il modifier l'autorisation de groupage ? =

Non. Cette décision revient exclusivement à l'équipe, au moment de la réception
du colis, et le client ne peut jamais la modifier. Un colis dont le groupage est
interdit doit être expédié seul.

= Comment les frais de stockage sont-ils calculés ? =

Chaque colis bénéficie d'une période de stockage gratuit (15 jours par défaut).
Au-delà, les frais sont calculés automatiquement par jour et par colis, selon le
montant défini dans les réglages.

= Les documents sont-ils privés ? =

Oui. Les fichiers ne transitent jamais par la médiathèque publique : ils sont
placés dans un répertoire protégé et ne sont servis qu'après connexion, avec
vérification du propriétaire. Un client ne peut télécharger que ses propres
documents ; les photos de réception sont réservées à l'équipe.

= L'extension est-elle conforme au RGPD ? =

Oui. Elle enregistre un exportateur et un effaceur de données personnelles
auprès des outils de confidentialité natifs de WordPress (Outils → Exporter /
Effacer les données personnelles), et la suppression d'un compte utilisateur
supprime toutes ses données liées à l'extension, fichiers privés compris.

= Des données sont-elles supprimées à la désinstallation ? =

Pas par défaut : supprimer l'extension laisse vos clients, vos colis et vos
fichiers intacts. Un administrateur du site peut demander un nettoyage complet
en réglant l'option `colisly_remove_data_on_uninstall` sur `yes` avant de
supprimer l'extension, par exemple avec WP-CLI :
`wp option update colisly_remove_data_on_uninstall yes`.

================================================================================
== Screenshots ==
================================================================================

1. Liste des clients avec recherche multi-critères.
2. Fiche client : indicateurs et onglets (colis, expéditions, documents,
   historique).
3. Formulaire de création de colis, avec recherche client en direct et stock
   actuel du client.
4. Écran « Mes colis » du client, dans l'espace Mon compte WooCommerce.

================================================================================
== Changelog ==
================================================================================

= 1.6.8 =
* Correction : dans l'espace client, les tableaux des colis et des expéditions
  étaient plus larges que la colonne de contenu de la plupart des thèmes, si
  bien que la dernière colonne se retrouvait hors écran, derrière une barre de
  défilement horizontale que personne ne pense à chercher. WooCommerce
  n'empile ces tableaux que sous 768 px de fenêtre, or ce qui les contraint
  c'est la colonne, pas la fenêtre : avec une fenêtre de 1600 px, les six
  colonnes devaient encore tenir dans 680 px. L'empilement se déclenche
  désormais sur la largeur du conteneur lui-même.
* Nouvelle capture de l'écran des réglages sur la fiche du répertoire.

= 1.6.7 =
* Correction : un colis créé sans que la décision de groupage soit précisée
  était enregistré en « doit être expédié seul », à rebours de la valeur par
  défaut de la colonne comme du formulaire de réception, où le groupage est
  autorisé. Le groupage est le fondement même du métier : l'omission vaut
  désormais autorisation.
* Confidentialité : l'export des données personnelles couvre maintenant tout ce
  que l'effacement supprime. Les notes internes de la fiche client, le
  commentaire interne de chaque colis et les documents non partagés avec le
  client étaient effacés sur demande sans jamais être communiqués en cas de
  demande d'accès.
* Correction : le refus d'une action renvoyait un code HTTP 500, que les
  hébergeurs et les outils de supervision interprètent comme une panne du
  serveur. Le code renvoyé est désormais 403.
* Correction : la liste des clients affichait tous les numéros de page. Les
  longues séries sont maintenant repliées et le nombre de fiches est indiqué.
* Correction : la désinstallation avec suppression des données laissait le droit
  colisly_manage sur chaque rôle ainsi qu'une option résiduelle.

= 1.6.6 =
* Nouveau : un raccourci Réglages sur l'écran des extensions.
* Nouveau : un avertissement sur les écrans de l'extension lorsque la boutique
  est en mode « bientôt disponible » ou qu'aucun moyen de paiement n'est actif.
  Les demandes d'expédition se terminent sur la page de paiement WooCommerce et
  échouaient jusqu'ici sans la moindre explication.
* Correction : ajouter un client possédant déjà une fiche annonçait une création
  qui n'avait pas eu lieu.
* Correction : un palier tarifaire plafonné à zéro était accepté et décalait
  silencieusement tous les colis au palier suivant. Il est désormais écarté
  comme une ligne vide.
* Correction : sur les écrans étroits, les dimensions du colis se coupaient
  entre un libellé et son champ.
* Le texte d'aide des transporteurs autorisés précise maintenant que n'en
  cocher aucun ne pose aucune restriction.

= 1.6.5 =
* Correction : dans l'espace client, seul le numéro de suivi peut désormais se
  couper en milieu de chaîne. En 1.6.4 toutes les cellules le pouvaient, ce qui
  éclatait les dates et les références sur plusieurs lignes.

= 1.6.4 =
* Correction : dans les réglages, les champs des paliers tarifaires et des
  transporteurs débordaient de leurs cellules ; la liste des colis et les
  tableaux client étaient rognés, masquant leur dernière colonne. Les longs
  numéros de suivi se coupent maintenant pour que les tableaux client tiennent
  dans la colonne du compte, et les larges tableaux d'administration défilent
  au lieu d'être tronqués.

= 1.6.3 =
* Formulation de la fiche du répertoire : les étiquettes et la description
  emploient désormais les termes du métier « package forwarding »,
  « reshipping » et « consolidation », afin que les opérateurs qui les
  recherchent trouvent l'extension.

= 1.6.2 =
* Les instructions d'installation mentionnaient un menu d'administration
  « Colis Pro », qui n'existe plus ; le menu s'appelle « Colisly ».

= 1.6.1 =
* Les URL de l'extension et de l'auteur pointent désormais vers la page du
  répertoire des extensions et vers le site de l'auteur, au lieu d'un dépôt de
  développement.

= 1.6.0 =
* Extension renommée en « Colisly Parcel Forwarding » (auparavant
  « Gestionnaire Colis Pro »), avec un domaine de traduction correspondant, afin
  de respecter les exigences de nommage du répertoire des extensions : un nom
  distinctif plutôt que purement descriptif.
* Préfixe interne renommé de « gcp » en « colisly » pour atteindre le minimum de
  quatre caractères exigé pour les préfixes d'extension. Les installations
  existantes sont migrées automatiquement : tables, options, droit, répertoire
  de fichiers privés et métadonnées de commande WooCommerce sont tous repris.
* Correction de l'échappement d'URL dans l'e-mail « demande d'expédition » en
  texte brut (esc_url au lieu de esc_url_raw).
* Les fichiers de traduction ne sont plus inclus : les traductions sont
  distribuées via translate.wordpress.org, et l'appel à load_plugin_textdomain()
  a été retiré (inutile depuis WordPress 4.6).

= 1.5.0 =
* Confidentialité (RGPD) : l'extension s'intègre désormais aux outils de
  confidentialité natifs de WordPress. L'exportateur de données personnelles
  inclut la fiche client, les colis, les expéditions et les documents ;
  l'effaceur supprime les documents et les fichiers privés, vide le numéro de
  téléphone, les notes, les numéros de suivi et les photos de réception, et
  signale que les enregistrements de colis et d'expéditions sont conservés à
  titre de pièces comptables. La suppression d'un compte utilisateur WordPress
  supprime toutes ses données et ses fichiers privés.

= 1.4.0 =
* Internationalisation : toutes les chaînes sources sont désormais en anglais ;
  la traduction française complète est fournie avec l'extension (.po/.mo
  fr_FR), afin que les sites francophones conservent exactement la même
  interface. Les contextes de traduction préservent les accords grammaticaux
  français sur les statuts.
* Les slugs des points d'entrée Mon compte sont désormais traduisibles et
  filtrables (les sites francophones conservent mes-colis, mes-expeditions,
  mes-documents, demande-expedition ; les autres langues obtiennent my-parcels,
  my-shipments, my-documents, shipment-request par défaut).
* Transporteurs internationaux par défaut (DHL, UPS, FedEx, Colissimo) sur les
  nouvelles installations.
* Nouveau réglage permettant d'appliquer les taxes de la boutique aux commandes
  d'expédition (hors taxes par défaut).
* La pagination de la recherche client s'exécute désormais en SQL (COUNT +
  LIMIT/OFFSET) et supporte les grandes bases de clients.
* L'estimation en direct du formulaire de demande d'expédition suit la locale du
  navigateur pour le formatage des nombres.

= 1.3.1 =
* Le readme est désormais rédigé en anglais, comme l'exige le répertoire des
  extensions WordPress.org.
* Les fichiers de développement ne sont plus livrés dans le dossier de
  l'extension.

= 1.3.0 =
* Tarifs des transporteurs (prix de base + prix au kilo) configurables dans les
  réglages ; le transport est facturé sur la ligne de livraison native de la
  commande WooCommerce et inclus dans le total de l'expédition.
* Demande d'expédition : chaque transporteur affiche son tarif, les
  transporteurs incompatibles sont désactivés, et une estimation totale en
  direct (colis + stockage + transport) s'affiche pendant la sélection.
* E-mails WooCommerce natifs : « Colis réceptionné » (client) et « Demande
  d'expédition » (équipe, destinataires configurables) enregistrés sous
  WooCommerce → Réglages → E-mails, avec des modèles HTML et texte brut
  surchargeables depuis le thème ; repli sur wp_mail sans WooCommerce.

= 1.2.0 =
* Paiements WooCommerce natifs : chaque demande d'expédition crée une commande
  WooCommerce (une ligne de frais par colis, les frais de stockage, le
  transporteur en ligne de livraison) ; le client est redirigé vers la page de
  paiement standard et un e-mail de facture client WooCommerce (avec lien de
  paiement) peut être envoyé automatiquement.
* Synchronisation des statuts dans les deux sens : commande payée → expédition
  payée ; expédition expédiée → commande terminée ; commande annulée →
  expédition annulée et colis remis en stock.
* Fiche client : la colonne « Commande » relie chaque expédition à sa commande
  WooCommerce ; l'espace client affiche un bouton « Payer » tant que la commande
  est en attente de paiement.

= 1.1.0 =
* Sécurité : les documents des clients et les photos de réception sont désormais
  stockés dans un répertoire privé (.htaccess + noms aléatoires) et servis via
  un point de téléchargement authentifié avec vérification du propriétaire
  (nonce + droit). Types de fichiers restreints (images, PDF, Office).
* Correction : les poids et dimensions saisis avec une virgule décimale
  (« 2,5 ») sont maintenant interprétés correctement.

= 1.0.0 =
* Version initiale : gestion des clients (références CL), création de colis
  (références COL), tarification automatique au poids, frais de stockage
  automatiques, règles de groupage, restrictions de transporteurs, demandes
  d'expédition, documents, historique et notifications par e-mail.

================================================================================
== Upgrade Notice ==
================================================================================

= 1.6.8 =
Corrige une colonne hors écran dans l'espace client sur les thèmes à colonne
de contenu étroite. Aucun changement de base de données.

= 1.6.7 =
Export de données personnelles complété et trois corrections relevées lors d'un
second audit. Aucun changement de base de données.

= 1.6.6 =
Corrections d'ergonomie relevées lors d'un parcours complet. Aucun changement
de base de données.

= 1.6.5 =
Correction de mise en page dans les tableaux client. Aucun changement de base
de données.

= 1.6.4 =
Corrections de mise en page sur l'écran des réglages et sur les tableaux
larges. Aucun changement de base de données.

= 1.6.3 =
Formulation de la fiche du répertoire uniquement. Aucun changement de base de
données ni de comportement.

= 1.6.2 =
Correction de documentation uniquement. Aucun changement de base de données ni
de comportement.

= 1.6.1 =
Version de maintenance : URL de l'extension et de l'auteur mises à jour. Aucun
changement de base de données ni de comportement.

= 1.6.0 =
Extension renommée et préfixe interne mis à jour ; les données existantes sont
migrées automatiquement lors de la mise à jour.
