# Colisly Parcel Forwarding

Extension WordPress/WooCommerce de gestion des clients et des colis pour une activité de réception, stockage, regroupement et expédition (réexpédition, notamment vers les DOM-TOM).

Le plugin se trouve dans [`colisly-parcel-forwarding/`](colisly-parcel-forwarding/) et suit les standards du répertoire officiel WordPress.org (WordPress Coding Standards, i18n, nonces, échappement, tables préfixées créées via `dbDelta`).

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

Le script démarre les conteneurs, installe WordPress et WooCommerce, active le plugin et exécute la suite de tests de bout en bout (`tests/smoke-test.php`, 58 assertions).

- Site : http://localhost:8080 — admin : `admin` / `admin`
- PHPCS : `cd colisly-parcel-forwarding && phpcs` (standards WordPress, ruleset `phpcs.xml.dist`)
