# Deuxième audit — version 1.6.6, zones non couvertes la première fois

Permissions, falsification d'URL, documents, historique, RGPD, montée en charge,
désactivation et désinstallation. Installation vidée entre les phases.

## Ce qui a tenu

| Domaine | Résultat |
| --- | --- |
| Rôles | `colisly_manage` sur administrateur et responsable de boutique uniquement. Éditeur et client refusés sur les 4 écrans. |
| Actions forgées | Les 4 actions `admin-post` rejetées sans jeton valide. |
| Documents | `.php` refusé à l'envoi. Le client ne voit que ses documents partagés, jamais les internes. |
| Lien volé | Un client utilisant le lien complet d'un autre, jeton compris, reçoit 403. Les nonces sont liés à l'utilisateur. |
| Falsification d'URL | Aucune fuite en forçant `client=2` côté administration ou côté client. |
| Montée en charge | 414 clients et 2057 colis : environ 1 s par écran, aucune erreur, aucune saturation mémoire. |
| Désactivation | Données intactes, site fonctionnel, réactivation sans perte. |
| Désinstallation sans opt-in | Rien n'est supprimé, conformément à la documentation. |

## Cinq défauts trouvés et corrigés en 1.6.7

1. **Groupage par défaut inversé.** Un colis créé sans décision explicite était
   stocké « à expédier seul », contre le défaut de la colonne (`1`) et celui du
   formulaire de réception (case cochée). Le groupage porte toute la marge du
   métier ; l'absence de décision vaut désormais autorisation.

2. **Export RGPD incomplet.** Les notes internes de la fiche, le commentaire
   interne des colis et les documents non partagés étaient **effacés** par
   l'effaceur mais **absents** de l'export. L'extension les traitait comme des
   données du client pour les supprimer, et les cachait quand il demandait à les
   consulter. Le droit d'accès ne prévoit pas d'exemption pour « interne ».

3. **Refus d'accès en HTTP 500.** Onze `wp_die()` sans code de réponse
   renvoyaient 500, lu comme une panne serveur par les hébergeurs et les outils
   de supervision. Passés en 403, comme le faisait déjà la classe de
   téléchargement.

4. **Pagination non tenable.** La liste des clients imprimait tous les numéros de
   page : 41 liens à 800 clients, une centaine au-delà. Remplacée par
   `paginate_links()`, avec abréviation et nombre total affiché.

5. **Résidus après désinstallation.** L'option `colisly_order_meta_migrated` et
   la capacité `colisly_manage` survivaient à une désinstallation complète.

## Une erreur de méthode, corrigée

Au premier audit, j'avais rendu le test des transporteurs « adaptatif » : il
lisait la configuration en place au lieu de la fixer. Cela a introduit un
couplage à l'ordre d'installation, et trois assertions échouaient au premier
lancement sur un site vierge avant de passer aux suivants.

La suite pose maintenant elle-même sa configuration de transporteurs. Elle passe
dès le premier lancement sur une installation neuve.
