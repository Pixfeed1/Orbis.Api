# Audit complet de Colisly Parcel Forwarding

Environnement neuf : WordPress 7.0.4, WooCommerce 11.0.1, thème Twenty Twenty-Five.
Base vidée avant l'audit. Tout est passé par l'interface réelle, pas par la base.
Captures desktop (1440) et mobile (390) dans `shots/`.

## Ce qui fonctionne, vérifié

| Domaine | Résultat |
| --- | --- |
| Activation | Aucune erreur PHP. Dépendance WooCommerce déclarée : elle ne peut plus être désactivée. |
| Tarification | Exacte sur toutes les bornes : 0,5→7,50 · 1→7,50 · 1,001→15 · 5→15 · 5,001→25 · 10→25 · 12→35 |
| Virgule décimale | « 2,5 » interprété comme 2,500 kg |
| Poids invalide | 0 et négatif refusés côté serveur, champ `required` côté navigateur |
| Groupage | Colis non regroupable annoté, refus serveur avec message explicite |
| Transporteurs | Intersection correcte : un colis limité à Colissimo désactive les autres |
| Estimation en direct | 47,30 $, identique au total de la commande |
| Commande | Une ligne de frais par colis, une ligne de stockage, transporteur en ligne de livraison |
| Frais de stockage | 30 jours - 15 gratuits = 15 jours × 1 = 15,00, ligne dédiée |
| Synchronisation | payée → expédition payée ; expédiée → commande terminée ; annulée → colis remis en stock |
| Confidentialité | Commentaire interne, dimensions et prix invisibles du client |
| Fichiers privés | Fichier 403, listing 403, anonyme redirigé, client propriétaire 403, nonce vérifié |

## Défauts trouvés et corrigés en 1.6.6

1. **Aucun lien Réglages** sur la ligne de l'extension, contrairement à la convention WordPress.
2. **Message mensonger** : ajouter un client déjà enregistré affichait « Client record created. »
3. **Palier tarifaire à poids nul accepté**, décalant silencieusement tous les colis au palier suivant.
4. **Aucune alerte sur les deux blocages WooCommerce** : mode « bientôt disponible » actif par défaut et zéro passerelle de paiement. Le client était renvoyé vers une page de paiement inaccessible, sans explication.
5. **Dimensions illisibles sur mobile** : le libellé « Height » restait sur une ligne, son champ sur la suivante.
6. **Ambiguïté des transporteurs** : ne rien cocher signifie « aucune restriction », l'inverse de ce qu'on attend. Le texte d'aide le dit maintenant.

## Test rendu fiable

`tests/smoke-test.php` supposait que le transporteur « chronopost » existe. Sur un site dont les
transporteurs ont été personnalisés, l'assertion échouait alors que le code était correct.
Le test lit désormais deux transporteurs de la configuration en cours.

## Points laissés en l'état

- Une liste de transporteurs vide vaut « tous autorisés ». C'est documenté dans le code et
  cohérent, mais cela reste un piège : le texte d'aide le signale, la sémantique n'a pas changé
  pour ne pas modifier le comportement des installations existantes.
- Les six indicateurs de la fiche client s'empilent un par ligne sur mobile. Lisible, mais long
  à faire défiler avant d'atteindre les onglets.
- Le formulaire de demande d'expédition n'affiche pas le numéro de suivi des colis.
