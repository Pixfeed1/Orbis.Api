# Colisly Parcel Forwarding

Extension WordPress/WooCommerce de gestion des clients et des colis pour une activité de réception, stockage, regroupement et expédition (réexpédition, notamment vers les DOM-TOM).

Le plugin se trouve dans [`colisly/`](colisly/) et suit les standards du répertoire officiel WordPress.org (WordPress Coding Standards, i18n, nonces, échappement, tables préfixées créées via `dbDelta`).

## Fonctionnalités

### Module clients
- Fiche client liée à un utilisateur WordPress/WooCommerce, référence unique courte (`CL000001`)
- Recherche par référence, nom, prénom, e-mail ou téléphone
- Fiche centralisant colis en stock, colis expédiés, expéditions, documents, historique
- Indicateurs automatiques : colis en stock, poids total, expéditions réalisées, frais de stockage dus, dernière réception, dernière expédition
- 15 jours de stockage gratuit (configurable) puis frais automatiques par jour

### Module colis
- Recherche du client avec affichage immédiat de ses colis en stock
- N° de suivi, poids, dimensions (admin uniquement), photo (facultative), commentaire interne (jamais visible client), regroupement autorisé/interdit, transporteurs autorisés
- Référence unique `COL000001`, date de réception, créateur, statut initial « disponible »
- Statuts : disponible, commandé, en attente de paiement, payé, en préparation, expédié, détruit, annulé
- Tarif calculé automatiquement au poids (paliers configurables) dès l'enregistrement

### Espace client (Mon compte WooCommerce)
- Mes colis, Mes expéditions, Mes documents, Demande d'expédition
- Règles appliquées côté serveur : colis non regroupable expédié seul, transporteurs limités à ceux autorisés

## Développement / tests

Environnement Docker (WordPress + WooCommerce + MariaDB) :

```bash
# Déposer un zip WooCommerce dans tests/woocommerce.zip (sinon téléchargé depuis wordpress.org)
./bin/test-env.sh
```

Le script démarre les conteneurs, installe WordPress et WooCommerce, active le plugin et exécute la suite de tests de bout en bout (`tests/smoke-test.php`, 116 assertions).

- Site : http://localhost:8080 — admin : `admin` / `admin`
- PHPCS : `phpcs` depuis la racine du dépôt (standards WordPress, ruleset `phpcs.xml.dist`)
- Plugin Check : `wp plugin check colisly`

## Publication sur WordPress.org

L'extension est hébergée dans le répertoire officiel :

- Page publique : https://wordpress.org/plugins/colisly
- Dépôt SVN : https://plugins.svn.wordpress.org/colisly

Les traductions françaises (`languages-fr/`) sont volontairement **hors** du paquet :
le répertoire les distribue via [translate.wordpress.org](https://translate.wordpress.org/).

Pour publier une version, mettre à jour `Version:` dans `colisly/colisly.php`
**et** `Stable tag:` dans `colisly/readme.txt`, puis :

```bash
./bin/svn-release.sh 1.6.0
```

Le script vérifie la cohérence des deux numéros de version, synchronise `trunk/`,
crée le tag `tags/<version>/` et commite. Il demande le mot de passe SVN
WordPress.org (distinct du mot de passe du compte, à définir dans
« Account & Security » du profil).
