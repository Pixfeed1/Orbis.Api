# Traduction française

WordPress.org distribue les traductions via translate.wordpress.org : les
fichiers `.po`/`.mo` ne doivent donc pas être livrés dans le paquet du plugin.

Ils sont conservés ici pour les installations qui n'utilisent pas le répertoire
officiel (installation par ZIP). Pour les activer sur un site :

1. copier `colisly-fr_FR.mo` dans `wp-content/languages/plugins/` ;
2. régler le site en français (Réglages → Général → Langue du site).

Le fichier modèle `languages/*.pot` reste dans le plugin pour permettre de
régénérer ou compléter les traductions.

## Traduction du readme

`readme-fr_FR.txt` est la traduction française de `colisly/readme.txt`. Elle ne
sert pas au plugin lui-même : elle alimente le sous-projet **Stable Readme** sur
translate.wordpress.org, qui contrôle l'affichage de la page publique
https://wordpress.org/plugins/colisly pour les visiteurs francophones.

Sa structure suit section par section celle du readme anglais, afin que chaque
chaîne de GlotPress se retrouve au même endroit. Les en-têtes (Contributors,
Tags, Requires at least…) ne sont pas traduisibles et n'y figurent pas.
