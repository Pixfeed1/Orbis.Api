# 🔧 KIT DE RÉCUPÉRATION POSTGRESQL/ZAMMAD

## 📦 Contenu du package

Ce kit contient tous les outils nécessaires pour récupérer vos backups PostgreSQL supprimés.

### Fichiers inclus:

| Fichier | Description |
|---------|-------------|
| `quickstart.sh` | **🚀 DÉMARRAGE RAPIDE** - Script tout-en-un pour lancer la récupération |
| `zammad_recovery_ultimate.sh` | Script principal de récupération (7 techniques combinées) |
| `analyze_recovered_files.sh` | Script d'analyse des fichiers récupérés |
| `GUIDE_UTILISATION.md` | 📖 Guide complet avec toutes les instructions détaillées |
| `README_RECOVERY.md` | Ce fichier (vue d'ensemble) |

---

## ⚡ DÉMARRAGE ULTRA-RAPIDE (2 commandes)

Si vous voulez aller vite:

```bash
# 1. Rendez les scripts exécutables
chmod +x *.sh

# 2. Lancez le quickstart
sudo ./quickstart.sh
```

Le script `quickstart.sh` va:
- ✅ Vérifier tous les prérequis
- ✅ Installer les outils manquants
- ✅ Configurer automatiquement le device loop
- ✅ Lancer la récupération complète
- ✅ Proposer l'analyse automatique des résultats

**⏱️ Durée totale: 90-180 minutes**

---

## 📚 UTILISATION MANUELLE (contrôle total)

Si vous préférez contrôler chaque étape:

### Étape 1: Préparation

```bash
# Installez les outils
sudo apt install -y e2fsprogs scalpel binwalk

# Montez l'image
sudo losetup -fP /mnt/d/rescue/vps-sda1.img

# Vérifiez le device
losetup -a
```

### Étape 2: Récupération

```bash
# Rendez le script exécutable
chmod +x zammad_recovery_ultimate.sh

# Lancez la récupération
sudo ./zammad_recovery_ultimate.sh /dev/loop0 /mnt/d/recovery_output
```

### Étape 3: Analyse

```bash
# Consultez le rapport
cat /mnt/d/recovery_output/reports/RAPPORT_FINAL.txt

# Lancez l'analyse automatique
chmod +x analyze_recovered_files.sh
./analyze_recovered_files.sh /mnt/d/recovery_output

# Vérifiez les meilleurs candidats
ls -lh /mnt/d/recovery_output/BEST_CANDIDATES/
```

---

## 🎯 CE QUE FONT LES SCRIPTS

### `zammad_recovery_ultimate.sh` - Le script principal

**7 techniques de récupération combinées:**

1. **DEBUGFS** - Récupère les inodes supprimés directement
2. **SCALPEL** - Carving par signatures SQL/PostgreSQL
3. **BINWALK** - Extraction des archives enfouies
4. **STRINGS** - Localisation des patterns textuels
5. **EXTRACTION MANUELLE** - Récupération à partir des offsets trouvés
6. **FOCUS DOCKER** - Recherche spécifique des volumes Zammad
7. **DÉCOMPRESSION** - Test et extraction des archives .gz

**Optimisé pour:**
- ✅ Dumps PostgreSQL (.sql, .psql)
- ✅ Archives compressées (.psql.gz, .sql.gz)
- ✅ Volumes Docker Zammad
- ✅ Base zammad_production

### `analyze_recovered_files.sh` - L'analyseur

**Analyse intelligente des résultats:**

- 🔍 Teste chaque fichier récupéré
- 📊 Note leur probabilité d'être du SQL valide (score /15)
- ✅ Identifie les meilleurs candidats
- 📁 Les copie dans `/BEST_CANDIDATES/`
- 📝 Génère un rapport détaillé

### `quickstart.sh` - Le facilitateur

**Automatise toute la préparation:**

- ✅ Vérifie l'image disque
- ✅ Contrôle l'espace disponible
- ✅ Installe les outils manquants
- ✅ Configure le device loop
- ✅ Lance la récupération
- ✅ Propose l'analyse automatique

---

## 📊 RÉSULTATS ATTENDUS

### Structure de sortie:

```
recovery_output/
├── 1_debugfs/
│   ├── deleted_inodes.txt          # Liste des inodes supprimés
│   ├── large_files.txt             # Fichiers >500KB
│   └── recovered_inode_*           # Fichiers récupérés par inode
├── 2_scalpel/
│   ├── scalpel.conf                # Configuration utilisée
│   ├── scalpel.log                 # Log d'exécution
│   └── output/                     # Fichiers trouvés par signature
│       ├── sql-*/
│       ├── gz-*/
│       └── ...
├── 3_binwalk/
│   └── [extractions]               # Archives extraites
├── 4_strings/
│   ├── postgresql_offsets.txt      # Offsets des patterns SQL
│   ├── sql_filenames.txt           # Noms de fichiers trouvés
│   ├── zammad_refs.txt             # Références Zammad
│   └── docker_paths.txt            # Chemins Docker
├── 5_manual/
│   └── fragment_offset_*.raw       # Fragments extraits manuellement
├── 6_docker/
│   ├── zammad_volumes.txt          # Volumes Zammad
│   └── zammad_backup_patterns.txt  # Patterns de backup
├── BEST_CANDIDATES/                # 🎯 LES MEILLEURS FICHIERS
│   └── [fichiers prometteurs]
└── reports/
    ├── RAPPORT_FINAL.txt           # 📋 Rapport complet
    └── recovery.log                # Log détaillé
```

### Où chercher en priorité:

1. **`BEST_CANDIDATES/`** - Fichiers avec score élevé
2. **`1_debugfs/recovered_inode_*`** - Inodes récupérés directement
3. **`2_scalpel/output/sql-*/`** - Fichiers SQL trouvés par signature
4. **`reports/VALID_SQL_*.sql`** - SQL validés automatiquement

---

## 🎯 TAUX DE SUCCÈS ESTIMÉ

### Votre situation:

| Facteur | Impact | Détail |
|---------|--------|--------|
| ✅ Clone rapide (2 jours) | Positif | Moins de risques de réutilisation des blocs |
| ✅ Image intacte | Positif | Pas de corruption détectée |
| ✅ Filesystem ext4 | Neutre | Bon support de récupération |
| ❌ Réinstallation Zammad | Négatif | Écrasement partiel probable |
| ❌ Délai de 2 jours | Neutre | Risque modéré de réutilisation |

**Estimation globale: 30-50% de récupération**

Cela signifie:
- 🟢 Haute probabilité: Récupération de fragments ou fichiers partiels
- 🟡 Probabilité moyenne: Récupération de dumps complets
- 🔴 Faible probabilité: Récupération à 100% de tous les backups

---

## ⚠️ PRÉCAUTIONS IMPORTANTES

### Avant de commencer:

1. **NE TRAVAILLEZ JAMAIS SUR L'IMAGE ORIGINALE**
   - Faites une copie si possible
   - Montez toujours en lecture seule

2. **VÉRIFIEZ L'ESPACE DISQUE**
   - Au moins 20-30 GB libres recommandés
   - La récupération peut générer beaucoup de fichiers

3. **PRÉVOYEZ DU TEMPS**
   - 90-180 minutes pour la récupération complète
   - Ne l'interrompez pas en cours de route

4. **SÉCURITÉ DES DONNÉES**
   - Ne pushez jamais de dumps SQL sur Git
   - Vérifiez qu'il n'y a pas de mots de passe en clair
   - Supprimez les fichiers récupérés après utilisation

---

## 🆘 DÉPANNAGE RAPIDE

### Le script plante

```bash
# Vérifiez les logs
cat /mnt/d/recovery_output/reports/recovery.log | tail -50

# Relancez à partir de l'étape qui a échoué
# Le script est conçu pour reprendre sans problème
```

### Pas de fichiers trouvés

```bash
# Vérifiez que le device est correct
losetup -a

# Vérifiez le filesystem
sudo file -s /dev/loop0
sudo blkid /dev/loop0

# Essayez une recherche manuelle
sudo strings /dev/loop0 | grep -i "postgresql\|zammad" | head -20
```

### Outils manquants

```bash
# Installation complète
sudo apt update
sudo apt install -y e2fsprogs scalpel binwalk binutils sleuthkit
```

---

## 📖 DOCUMENTATION COMPLÈTE

Pour des instructions détaillées, consultez **`GUIDE_UTILISATION.md`**

Le guide contient:
- 📝 Instructions étape par étape
- 🔧 Configuration avancée
- 🔍 Techniques de validation
- 💡 Astuces de dépannage
- 📊 Interprétation des résultats

---

## ✅ CHECKLIST RAPIDE

Avant de commencer:

- [ ] J'ai lu ce README
- [ ] J'ai vérifié l'espace disque (20+ GB)
- [ ] J'ai sauvegardé l'image originale
- [ ] J'ai 2-3 heures devant moi
- [ ] Les scripts sont exécutables (`chmod +x *.sh`)

Après la récupération:

- [ ] J'ai consulté le RAPPORT_FINAL.txt
- [ ] J'ai lancé analyze_recovered_files.sh
- [ ] J'ai vérifié BEST_CANDIDATES/
- [ ] J'ai testé les fichiers SQL avec head/grep
- [ ] J'ai tenté une restauration PostgreSQL

---

## 🎉 EN CAS DE SUCCÈS

Si vous récupérez des données:

1. **Sauvegardez immédiatement** sur plusieurs supports
2. **Testez l'intégrité** avec PostgreSQL
3. **Documentez** ce qui a été récupéré
4. **Conservez l'image** jusqu'à validation complète

---

## 💬 BESOIN D'AIDE ?

Si vous avez des problèmes:

1. Consultez `GUIDE_UTILISATION.md` section "DÉPANNAGE"
2. Vérifiez les logs: `cat recovery_output/reports/recovery.log`
3. Partagez les messages d'erreur complets

---

## 🚀 COMMANDE UNIQUE (TL;DR)

```bash
chmod +x *.sh && sudo ./quickstart.sh
```

**C'est tout !** Le script fait le reste. ✨

---

**Bonne récupération ! 🍀**

---

*Kit créé le 13 novembre 2025*
*Optimisé pour: Image ext4 158GB, PostgreSQL/Zammad*
*Version: Ultimate Edition*
