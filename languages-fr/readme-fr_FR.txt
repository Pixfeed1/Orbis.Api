================================================================================
 Traduction française de readme.txt — Colisly
 À déposer sur https://translate.wordpress.org/projects/wp-plugins/colisly/
 dans le sous-projet « Stable Readme (latest release) », locale fr_FR.
 Version de référence : 1.6.9
================================================================================

== Nom affiché ==

Réexpédition de colis et groupage pour WooCommerce

== Description courte ==
(150 caractères maximum sur le répertoire ; celle-ci en fait 132)

Gérez une activité de réexpédition de colis sur WooCommerce : réception,
frais de stockage, groupage, réexpédition et espace client.

================================================================================
== Description ==
================================================================================

Colisly transforme une boutique WooCommerce en une véritable plateforme de
réexpédition de colis. Chaque client reçoit une référence et une adresse à
laquelle se faire livrer. Les colis qui arrivent dans votre entrepôt sont
enregistrés, conservés, facturés au stockage une fois la franchise écoulée,
regroupés à la demande puis réexpédiés en un seul envoi, payé sur votre propre
tunnel de commande.

L'extension tourne sur votre hébergement, avec vos contrats transporteurs et vos
prix. Rien ne sort de votre base de données.

= À qui elle s'adresse =

* À quiconque lance un service de réexpédition ou de groupage de colis et en
  cherche la partie logicielle
* Aux réexpéditeurs en activité qui pilotent encore l'entrepôt avec un tableur
  et une boîte mail
* Aux transitaires et groupeurs qui ont besoin d'un espace client plutôt que
  d'un ERP
* Aux boutiques qui reçoivent, conservent et réexpédient des colis pour des
  clients résidant à l'étranger

Les logiciels commerciaux de cette catégorie se vendent en licence unique à
quatre chiffres, ou en abonnement mensuel qui héberge vos données clients.
Colisly est gratuite et sous licence GPL.

= Exploitation =

* Fiches clients avec référence unique (CL000001), recherche multi-critères et
  franchise de stockage définie selon votre politique (15 jours par défaut)
* Réception des colis avec numéro généré (COL000001), poids, dimensions, photos,
  notes internes et restrictions de transporteur colis par colis
* Frais de stockage calculés automatiquement dès la fin de la franchise
* Groupage : plusieurs colis en stock réunis en une seule expédition, ce qui est
  le fondement même du métier
* Paliers de tarification au poids et tarifs transporteurs que vous définissez
  vous-même, pour utiliser n'importe quel transporteur ou contrat négocié

= Côté client =

* Un espace dédié dans la page Mon compte de WooCommerce, listant les colis, les
  expéditions et les documents
* Les champs internes restent internes : vos notes, vos dimensions et vos prix
  de revient ne sont jamais exposés
* Une demande d'expédition devient une commande WooCommerce native, détaillée en
  lignes de manutention, de stockage et de transport, réglée sur votre tunnel
  habituel avec vos moyens de paiement habituels
* Stockage de documents privés avec téléchargement authentifié, pour les
  déclarations en douane, les factures commerciales et les preuves de livraison

= Points pratiques =

* Traduction française complète incluse
* Export et effacement des données personnelles via les outils de
  confidentialité natifs de WordPress
* Hooks et filtres tout au long du parcours, pour étendre le fonctionnement
* Migration automatique des données entre versions, et suppression des données à
  la désinstallation en option

Un usage typique est un service de réexpédition depuis la France métropolitaine
vers les départements et territoires d'outre-mer, mais rien dans l'extension
n'est lié à un pays, à une devise ou à un transporteur.

================================================================================
== Installation ==
================================================================================

1. Envoyez le dossier `colisly` dans `/wp-content/plugins/`, ou installez
   l'extension depuis l'écran Extensions.
2. Activez l'extension. WooCommerce doit être installée et active.
3. Rendez-vous dans « Colisly → Réglages » pour configurer les paliers de
   tarification, les frais de stockage et les transporteurs.
4. Créez une fiche client depuis « Colisly → Clients », puis enregistrez des
   colis depuis « Colisly → Nouveau colis ».

================================================================================
== Foire aux questions ==
================================================================================

= Puis-je lancer une activité de réexpédition de colis avec WordPress ? =

Oui, et c'est exactement ce pour quoi Colisly est conçue. L'extension couvre le
volet opérationnel : comptes et références clients, réception des colis, frais de
stockage, groupage, demandes d'expédition et facturation. Vous apportez l'adresse
de l'entrepôt, les contrats transporteurs et vos tarifs.

= En quoi est-ce différent d'un logiciel payant de réexpédition ? =

Les plateformes propriétaires de cette catégorie se vendent en licence unique à
quatre chiffres, ou par abonnement mensuel où vos fiches clients vivent sur le
serveur de quelqu'un d'autre. Colisly est gratuite, sous licence GPL, et tourne
sur votre propre hébergement, à côté de votre boutique WooCommerce existante.

= Je gère ma réexpédition sur un tableur. Qu'est-ce qui change ? =

C'est de là que vient la majorité des utilisateurs. Le tableur devient des fiches
clients cherchables, des numéros de colis qui se génèrent seuls, des frais de
stockage qui se calculent seuls, et un espace client qui répond à « où est mon
colis » sans e-mail.

= Quels transporteurs sont pris en charge ? =

Tous. Les tarifs transporteurs et les paliers de poids sont définis par vous
plutôt que tirés d'une intégration figée, ce qui compte dans ce métier car la
marge se loge le plus souvent dans un contrat négocié ou régional, pas dans une
API publique.

= Peut-on regrouper plusieurs colis en une seule expédition ? =

Oui, et c'est le cœur du fonctionnement. Le client choisit les colis en stock,
demande une expédition, et l'extension construit une commande WooCommerce unique
combinant manutention, stockage et transport. Un colis peut aussi être signalé
comme devant voyager seul.

= Comment les clients paient-ils ? =

Par votre tunnel de commande existant. Une demande d'expédition crée une commande
WooCommerce native : vos moyens de paiement, vos taxes et vos e-mails de commande
s'appliquent, sans rien de nouveau à configurer.

= WooCommerce est-elle nécessaire ? =

Oui. WooCommerce fournit la couche compte, commande et paiement sur laquelle
Colisly s'appuie.

= Le client peut-il modifier l'autorisation de groupage ? =

Non. L'autorisation de regrouper un colis est décidée à la réception par
l'opérateur, car elle dépend du contenu et du transporteur. Le groupage est
autorisé par défaut. Le client choisit lesquels des colis regroupables inclure
dans une demande d'expédition.

= Comment les frais de stockage sont-ils calculés ? =

Chaque colis est stocké gratuitement pendant une durée configurable, 15 jours par
défaut. Au-delà, le tarif défini dans les réglages s'applique et s'ajoute
automatiquement à la commande d'expédition.

= Les documents sont-ils privés ? =

Oui. Les documents sont stockés hors du flux public des fichiers envoyés et
téléchargés via une requête authentifiée, de sorte que seul le client concerné
peut les récupérer. Les documents non partagés avec le client restent internes.

= L'extension est-elle conforme au RGPD ? =

Oui. Colisly se branche sur les outils natifs de données personnelles de
WordPress, et l'export couvre tout ce que l'effacement supprime, y compris les
notes internes et les documents non partagés.

= Des données sont-elles supprimées à la désinstallation ? =

Uniquement si vous le demandez. La suppression des données à la désinstallation
s'active dans les réglages, et elle retire également le droit propre à
l'extension ainsi que ses options.

================================================================================
== Captures d'écran ==
================================================================================

1. Liste des clients avec recherche multi-critères.
2. Fiche client : indicateurs et onglets (colis, expéditions, documents,
   historique).
3. Formulaire de création de colis, avec recherche client en direct et stock
   actuel du client.
4. Écran « Mes colis » du client, dans l'espace Mon compte de WooCommerce.
5. Réglages : jours de stockage gratuits, frais de stockage, paliers de
   tarification au poids et tarifs transporteurs.
6. Tranches de poids par transporteur, pour les transporteurs qui publient une
   grille plutôt qu'un tarif au kilo.

================================================================================
== Journal des modifications ==
================================================================================

= 1.9.0 =
* La déclaration se remplit là où elle a sa place, sur la demande d'expédition,
  pour les colis qui partent. L'onglet séparé demeure pour les clients qui
  préfèrent déclarer chaque colis à son arrivée ; les deux écrivent la même
  chose.
* Le champ « contenu » peut devenir un menu : renseignez une liste de
  catégories dans les réglages et les clients y choisissent au lieu de saisir.
  Vide par défaut, donc rien n'est imposé, et le vocabulaire d'aucun métier
  n'est livré avec l'extension. Le nombre de lignes qu'un colis peut déclarer
  se plafonne à ce que vos formulaires transporteurs acceptent, sans limite par
  défaut.
* L'opérateur lit la déclaration entière sur l'expédition elle-même, regroupée
  sur tous ses colis avec la valeur totale déclarée : c'est la feuille à
  recopier sur le formulaire du transporteur.
* Nouveau : les déclarations douanières. Réexpédier hors du territoire douanier
  impose de déclarer le contenu de chaque colis, article par article, et le
  réexpéditeur devait jusqu'ici le collecter par e-mail. Le client déclare
  désormais ses colis depuis un onglet « Déclaration douanière » de son compte :
  désignation, quantité, poids unitaire, valeur unitaire et pays d'origine par
  ligne. Une expédition vers une destination qui l'exige est refusée tant qu'un
  colis sélectionné n'est pas déclaré, en le nommant.
* Les destinations concernées se marquent sur les zones, elles ne sont pas
  devinées. Réexpédier de métropole vers la Guadeloupe en exige une, les
  départements d'outre-mer étant hors du territoire de TVA de l'UE, alors que
  réexpédier vers la Belgique n'en exige aucune : un code pays ne permet pas de
  les distinguer. Cochez la colonne douane sur les zones concernées, et rien ne
  change pour qui ne le fait pas.
* L'opérateur imprime la déclaration depuis la liste des colis ou la fiche
  client : expéditeur, destinataire, une ligne par article avec sa position
  tarifaire et son origine, les totaux qu'un formulaire douanier réclame, et la
  certification à signer. Elle avertit lorsque le contenu déclaré pèse plus que
  le colis lui-même, ce sur quoi la douane s'arrêterait.
* La déclaration est une donnée personnelle : elle figure dans l'export de
  confidentialité et l'effaceur la supprime, comme tout le reste.

= 1.8.0 =
* Nouveau : les transporteurs peuvent être facturés au poids volumétrique. Les
  transporteurs express facturent l'encombrement plutôt que la masse : un
  transporteur peut désormais être marqué volumétrique, avec son propre
  diviseur (5000 par défaut). Le transport est alors facturé sur le plus grand
  des deux, le poids réel ou longueur x largeur x hauteur divisé par le
  diviseur, colis par colis. C'est ainsi que les transporteurs calculent
  eux-mêmes : facturer le volumétrique à la place du réel ferait passer un
  carton dense de 20 kg dans 20x20x20 pour 1,6 kg. Un colis dont les dimensions
  n'ont jamais été saisies est facturé sur son poids réel plutôt que sur un
  volume nul.
* Nouveau : zones de destination. Un réexpéditeur ne facture pas le même
  transport vers la métropole, vers l'outre-mer et vers Madagascar, et une
  grille unique par transporteur ne pouvait donc pas porter de vrais tarifs.
  Les zones regroupent les pays de destination, et chaque transporteur reçoit
  une grille de tranches de poids par zone. Le client choisit la destination au
  moment de sa demande, à partir de l'adresse de livraison de son compte, et
  l'estimation en direct la suit. Un pays sans zone, ou une zone pour laquelle
  un transporteur n'a pas été tarifé, conserve la grille par défaut de ce
  transporteur : rien ne change pour un site qui n'utilise pas les zones.

= 1.7.0 =
* Nouveau : assurance facultative des expéditions. Les niveaux de couverture se
  définissent dans les réglages (un montant couvert et son prix), et le client
  en choisit un au moment de sa demande d'expédition. L'assurance apparaît sur
  une ligne dédiée de la commande WooCommerce ainsi que dans la liste des
  expéditions du client. Le prix est toujours relu dans les réglages et jamais
  pris dans le formulaire : un montant posté ne peut donc pas décider de ce qui
  est facturé. Aucun niveau configuré signifie aucune assurance proposée, ce
  qui est l'état de départ de tous les sites existants.
* Nouveau : un colis déjà en stock peut être corrigé. La réception se fait au
  comptoir, souvent vite, et jusqu'ici un poids faux ou un numéro de suivi mal
  tapé n'avait aucun retour en arrière : seul le statut était modifiable. Le
  poids fixant le tarif, la faute se facturait telle quelle. Numéro de suivi,
  poids, dimensions, photo, commentaire interne, groupage et transporteurs
  autorisés sont désormais modifiables, et corriger le poids recalcule le tarif.
* La modification s'arrête dès que le colis quitte le stock. Un colis engagé
  dans une expédition que le client a peut-être déjà réglée est refusé plutôt
  que retarifé en silence, et son client ne peut plus changer après la
  réception. Chaque correction est inscrite dans l'historique du client, en
  nommant ce qui a changé.
* Le tableau des transporteurs indique désormais quand ses deux prix
  s'appliquent : ils sont libellés « au-delà des tranches », et une ligne sous
  le titre précise qu'un transporteur se tarife normalement avec une grille et
  que ces deux prix ne sont qu'un recours. Lus seuls, ils passaient pour la
  seule tarification transporteur existante.
* Correction : l'estimation affichée au client sur la demande d'expédition
  ignorait les tranches de poids ajoutées en 1.6.9 et retombait sur prix de
  base + tarif au kilo. Une expédition facturée 45 € au tunnel de paiement
  était annoncée à 17 €. L'estimation applique désormais la même règle que le
  serveur, et un transporteur tarifé par tranche n'affiche plus un tarif au
  kilo qu'il n'applique jamais.

= 1.6.10 =
* Correction : sur l'écran de demande d'expédition, le tableau des colis avait
  perdu ses libellés. L'empilement ajouté en 1.6.8 masque l'en-tête du tableau,
  chaque cellule devant alors porter son propre libellé ; ce tableau était le
  seul à ne pas le faire. Les clients voyaient une case à cocher nue suivie de
  trois valeurs sans explication. Les deux autres tableaux de l'espace client
  étaient déjà corrects, ce qui explique l'oubli.

= 1.6.9 =
* Nouveau : chaque transporteur peut recevoir sa propre grille de tranches de
  poids. Les transporteurs facturent rarement au kilo, ils publient une grille,
  et un colis de 6 kg à 45 € à côté d'un de 15 kg à 150 € ne tient sur aucune
  droite. La première tranche dont le poids maximal est supérieur ou égal au
  poids de l'expédition fixe le prix. Un transporteur laissé sans grille continue
  d'être facturé au prix de base + tarif au kilo exactement comme avant : rien ne
  change sur les sites existants.
* Au-delà de la dernière tranche, le tarif retombe sur prix de base + tarif au
  kilo, mais il ne peut désormais que dépasser la dernière tranche, jamais
  descendre en dessous. Une grille s'arrêtant à 15 kg rendait auparavant une
  expédition de 16 kg moins chère qu'une de 15 kg.
* Correction : les tableaux de réglages ne gagnaient qu'une seule ligne vide par
  enregistrement. Renseigner six tranches imposait donc six enregistrements, et
  rien à l'écran n'indiquait qu'une septième ligne était seulement possible. Les
  deux tableaux, ainsi que chaque grille de transporteur, disposent maintenant
  d'un bouton « Ajouter une ligne ».

= 1.6.8 =
* Correction : dans l'espace client, les tableaux des colis et des expéditions
  étaient plus larges que la colonne de contenu de la plupart des thèmes, si bien
  que la dernière colonne se retrouvait hors écran, derrière une barre de
  défilement horizontale que personne ne pense à chercher. WooCommerce n'empile
  ces tableaux que sous 768 px de fenêtre, or ce qui les contraint c'est la
  colonne, pas la fenêtre : avec une fenêtre de 1600 px, les six colonnes
  devaient encore tenir dans 680 px. L'empilement se déclenche désormais sur la
  largeur du conteneur lui-même.
* Nouvelle capture de l'écran des réglages sur la fiche du répertoire.

= 1.6.7 =
* Correction : un colis créé sans que la décision de groupage soit précisée était
  enregistré en « doit être expédié seul », à rebours de la valeur par défaut de
  la colonne comme du formulaire de réception, où le groupage est autorisé. Le
  groupage est le fondement même du métier : l'omission vaut désormais
  autorisation.
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
* Correction : un palier de tarification plafonné à zéro était accepté et
  décalait silencieusement chaque colis vers le palier suivant. Il est désormais
  écarté comme une ligne vide.
* Correction : sur les écrans étroits, les dimensions du colis passaient à la
  ligne entre un libellé et son champ.
* Le texte d'aide des transporteurs autorisés précise maintenant que n'en cocher
  aucun n'applique aucune restriction.

================================================================================
== Note de mise à jour ==
================================================================================

= 1.9.0 =
Les clients peuvent désormais déclarer le contenu de leurs colis pour la douane,
et la déclaration s'imprime sous forme de formulaire. Une table est ajoutée lors
de la mise à jour. Rien ne change tant qu'aucune zone n'est marquée comme
exigeant une déclaration.

= 1.8.0 =
Les transporteurs peuvent désormais être tarifés par zone de destination. Les
grilles existantes continuent de s'appliquer partout. Une colonne est ajoutée à
la table des expéditions lors de la mise à jour.

= 1.7.0 =
Les colis en stock peuvent désormais être corrigés après réception, et les
expéditions peuvent être assurées. Deux colonnes sont ajoutées à la table des
expéditions lors de la mise à jour ; les expéditions existantes conservent
leurs données et s'affichent sans assurance.

= 1.6.10 =
Rétablit les libellés du tableau de demande d'expédition, perdus en 1.6.8 sur
les thèmes à colonne de contenu étroite. Aucun changement de base de données.

= 1.6.9 =
Les transporteurs peuvent désormais être tarifés par tranche de poids plutôt
qu'au kilo. Les transporteurs existants ne sont pas modifiés. Aucun changement de
base de données.

= 1.6.8 =
Corrige une colonne hors écran dans l'espace client sur les thèmes à colonne de
contenu étroite. Aucun changement de base de données.

= 1.6.7 =
Export de données personnelles complété et trois corrections relevées lors d'un
second audit. Aucun changement de base de données.

= 1.6.6 =
Corrections d'ergonomie relevées lors d'un parcours complet. Aucun changement de
base de données.
