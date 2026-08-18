# Traduction française

WordPress.org distribue les traductions via translate.wordpress.org : les
fichiers `.po`/`.mo` ne doivent donc pas être livrés dans le paquet du plugin.

Ils sont conservés ici pour les installations qui n'utilisent pas le répertoire
officiel (installation par ZIP). Pour les activer sur un site :

1. copier `colisly-fr_FR.mo` dans `wp-content/languages/plugins/` ;
2. régler le site en français (Réglages → Général → Langue du site).

Le fichier modèle `languages/*.pot` reste dans le plugin pour permettre de
régénérer ou compléter les traductions.
