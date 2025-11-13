# 🔧 GUIDE D'UTILISATION - RÉCUPÉRATION POSTGRESQL/ZAMMAD

## 📋 Vue d'ensemble

Ce guide vous accompagne pour récupérer vos backups PostgreSQL supprimés de votre image disque `vps-sda1.img`.

**Votre situation:**
- ✅ Image disque: 158 GB (ext4)
- ✅ Données supprimées: 10 novembre 2025
- ✅ Clone effectué: 12 novembre 2025
- ⚠️ Zammad réinstallé le soir même (risque d'écrasement partiel)

**Chances de récupération estimées: 30-50%**

---

## 🚀 ÉTAPE 1: Préparation

### 1.1 Vérification des outils requis

```bash
# Vérifiez que tous les outils sont installés
sudo apt update
sudo apt install -y e2fsprogs scalpel binwalk sleuthkit

# Vérification
which debugfs scalpel binwalk strings grep dd
```

Si un outil manque:
```bash
# debugfs (inclus dans e2fsprogs)
sudo apt install -y e2fsprogs

# scalpel
sudo apt install -y scalpel

# binwalk
sudo apt install -y binwalk

# strings (inclus dans binutils)
sudo apt install -y binutils
```

### 1.2 Vérification de l'espace disque

```bash
# Vérifiez que vous avez AU MOINS 30 GB libres
df -h /mnt/d

# Si manque d'espace, libérez de la place ou changez le répertoire de sortie
```

### 1.3 Montage de l'image en lecture seule

```bash
# Démontez d'abord si déjà monté
sudo umount /mnt/vps 2>/dev/null
sudo losetup -d /dev/loop0 2>/dev/null

# Montez l'image en loop (sans monter le filesystem)
sudo losetup -fP /mnt/d/rescue/vps-sda1.img

# Vérifiez le device assigné
losetup -a
# Devrait afficher: /dev/loop0: [...]
```

**⚠️ IMPORTANT: Ne montez PAS le filesystem (pas de `mount`)!**

---

## 🎯 ÉTAPE 2: Exécution du script de récupération

### 2.1 Téléchargement des scripts

Les scripts sont déjà créés. Assurez-vous qu'ils sont exécutables:

```bash
chmod +x zammad_recovery_ultimate.sh
chmod +x analyze_recovered_files.sh
```

### 2.2 Lancement de la récupération

```bash
# Syntaxe complète:
sudo ./zammad_recovery_ultimate.sh <image_ou_device> <repertoire_sortie> [device_loop]

# Dans votre cas:
sudo ./zammad_recovery_ultimate.sh /dev/loop0 /mnt/d/recovery_output
```

**Ce que fait le script:**

1. ✅ **Analyse debugfs** (5-10 min): Récupère les inodes supprimés
2. ✅ **Scalpel carving** (30-60 min): Recherche par signatures SQL
3. ✅ **Binwalk extraction** (20-40 min): Extrait les archives enfouies
4. ✅ **Analyse strings** (10-20 min): Localise les patterns PostgreSQL
5. ✅ **Extraction manuelle** (15-30 min): Extrait les meilleurs candidats
6. ✅ **Focus Docker** (5-10 min): Cible les volumes Zammad
7. ✅ **Décompression** (5-10 min): Teste les archives trouvées

**Durée totale estimée: 90-180 minutes**

### 2.3 Surveillance de la progression

Pendant l'exécution, surveillez:

```bash
# Dans un autre terminal:
tail -f /mnt/d/recovery_output/reports/recovery.log

# Vérifiez l'espace disque restant:
watch -n 60 df -h /mnt/d
```

---

## 📊 ÉTAPE 3: Analyse des résultats

Une fois le script terminé:

### 3.1 Consultation du rapport

```bash
# Lisez le rapport complet
cat /mnt/d/recovery_output/reports/RAPPORT_FINAL.txt

# Ou avec pagination:
less /mnt/d/recovery_output/reports/RAPPORT_FINAL.txt
```

### 3.2 Analyse automatique approfondie

```bash
# Lancez le script d'analyse
./analyze_recovered_files.sh /mnt/d/recovery_output

# Ce script va:
# • Tester tous les fichiers récupérés
# • Noter leur probabilité d'être des dumps SQL valides
# • Copier les meilleurs candidats dans BEST_CANDIDATES/
```

### 3.3 Vérification des meilleurs candidats

```bash
cd /mnt/d/recovery_output/BEST_CANDIDATES

# Listez les fichiers prometteurs
ls -lh

# Pour chaque fichier, testez:
head -100 [nom_fichier]
file [nom_fichier]
```

---

## 🔍 ÉTAPE 4: Validation des fichiers SQL

### 4.1 Test rapide de validité

```bash
# Vérifiez si le fichier contient des marqueurs PostgreSQL
grep -i "postgresql\|pg_dump\|create table\|zammad" [fichier.sql] | head -20

# Vérifiez la structure
head -50 [fichier.sql]
tail -50 [fichier.sql]
```

### 4.2 Test de restauration (recommandé)

```bash
# Créez une base de test
sudo -u postgres psql -c "CREATE DATABASE test_recovery;"

# Testez la restauration
sudo -u postgres psql -d test_recovery -f [fichier.sql]

# Si succès:
echo "✅ Fichier SQL valide et restaurable!"

# Si erreurs partielles, c'est normal (données partielles)
# Vérifiez quand même ce qui a été restauré:
sudo -u postgres psql -d test_recovery -c "\dt"
sudo -u postgres psql -d test_recovery -c "SELECT COUNT(*) FROM [table];"
```

### 4.3 Recherche de données spécifiques

Si vous cherchez des données précises:

```bash
# Recherchez dans tous les fichiers récupérés
grep -r "mot_clé_important" /mnt/d/recovery_output/

# Exemples:
grep -r "zammad_production" /mnt/d/recovery_output/
grep -r "CREATE TABLE tickets" /mnt/d/recovery_output/
grep -r "INSERT INTO users" /mnt/d/recovery_output/
```

---

## 🎯 ÉTAPE 5: Exploration manuelle approfondie

Si les scripts automatiques n'ont pas tout trouvé:

### 5.1 Analyse des inodes debugfs

```bash
cd /mnt/d/recovery_output/1_debugfs

# Examinez la liste des inodes supprimés
cat deleted_inodes.txt | sort -k6 -nr | head -20

# Testez les fichiers récupérés un par un
for f in recovered_inode_*; do
    echo "=== Fichier: $f ==="
    file "$f"
    strings "$f" | head -100 | grep -i "sql\|postgresql\|zammad"
    echo ""
done
```

### 5.2 Analyse des résultats scalpel

```bash
cd /mnt/d/recovery_output/2_scalpel/output

# Listez tous les dossiers créés
ls -la

# Explorez chaque type:
for dir in sql* gz* tar*; do
    if [ -d "$dir" ]; then
        echo "=== $dir ==="
        ls -lh "$dir"
    fi
done
```

### 5.3 Extraction manuelle à partir des offsets

```bash
cd /mnt/d/recovery_output/4_strings

# Consultez les offsets PostgreSQL trouvés
cat postgresql_offsets.txt

# Pour chaque offset prometteur, extrayez manuellement:
# Exemple: offset = 123456789
sudo dd if=/dev/loop0 of=/mnt/d/manual_extract.raw bs=1 skip=123456789 count=104857600

# Testez le contenu
strings manual_extract.raw | head -200
```

---

## 💡 DÉPANNAGE

### Problème: "debugfs: command not found"

```bash
sudo apt install -y e2fsprogs
```

### Problème: "scalpel: command not found"

```bash
sudo apt install -y scalpel
```

### Problème: "Permission denied"

```bash
# Assurez-vous d'utiliser sudo
sudo ./zammad_recovery_ultimate.sh [...]
```

### Problème: "Device /dev/loop0 not found"

```bash
# Remontez l'image:
sudo losetup -fP /mnt/d/rescue/vps-sda1.img
losetup -a  # vérifiez le device assigné
```

### Problème: "No space left on device"

```bash
# Libérez de l'espace ou changez le répertoire de sortie:
sudo ./zammad_recovery_ultimate.sh /dev/loop0 /autre/chemin/avec/espace
```

### Problème: Le script est très lent

**C'est NORMAL !** La récupération sur 158 GB prend du temps:
- Scalpel seul peut prendre 60-90 minutes
- Total: 90-180 minutes selon votre disque

---

## 📝 CHECKLIST DE VÉRIFICATION

Avant de conclure, vérifiez:

- [ ] J'ai consulté `/mnt/d/recovery_output/reports/RAPPORT_FINAL.txt`
- [ ] J'ai exécuté `analyze_recovered_files.sh`
- [ ] J'ai vérifié `/mnt/d/recovery_output/BEST_CANDIDATES/`
- [ ] J'ai testé les fichiers SQL avec `head`, `grep`, `file`
- [ ] J'ai tenté une restauration PostgreSQL sur une base test
- [ ] J'ai exploré manuellement les dossiers 1_debugfs/ et 2_scalpel/
- [ ] J'ai cherché mes données spécifiques avec `grep -r`

---

## 🎉 EN CAS DE SUCCÈS

Si vous récupérez des données:

1. **Sauvegardez immédiatement** les fichiers valides ailleurs
2. **Documentez** ce qui a été récupéré (quelles tables, combien de lignes)
3. **Testez** l'intégrité des données récupérées
4. **Ne supprimez PAS** l'image source avant d'être sûr

---

## ❌ SI RIEN N'EST RÉCUPÉRÉ

Si malheureusement aucune donnée n'est trouvée:

### Causes probables:
1. La réinstallation Zammad a écrasé les blocs
2. Le système ext4 a réutilisé l'espace rapidement
3. Les données étaient dans des volumes temporaires

### Derniers recours:

#### 1. Recherche hexadécimale brute

```bash
# Cherchez des chaînes très spécifiques que vous savez présentes
sudo xxd /dev/loop0 | grep -i "votre_chaine_unique"
```

#### 2. Analyse forensique avancée (Autopsy)

```bash
sudo apt install -y autopsy sleuthkit
# Ouvrez l'image dans Autopsy pour analyse GUI
```

#### 3. Services professionnels

Si les données sont critiques, considérez:
- Services de récupération de données professionnels
- Coût: 500€ - 3000€ selon complexité

---

## 📞 BESOIN D'AIDE ?

Si vous rencontrez des problèmes:

1. Vérifiez les logs: `cat /mnt/d/recovery_output/reports/recovery.log`
2. Partagez les messages d'erreur complets
3. Indiquez à quelle étape le script s'est arrêté

---

## 🔐 SÉCURITÉ

**⚠️ RAPPELS IMPORTANTS:**

- Ne travaillez JAMAIS sur l'image originale sans copie de sauvegarde
- Montez toujours en lecture seule quand possible
- Ne pushez JAMAIS de dumps SQL sur Git/GitHub
- Vérifiez qu'aucun mot de passe n'est en clair dans les dumps

---

## ✅ RÉSUMÉ RAPIDE (TL;DR)

```bash
# 1. Préparation
sudo apt install -y e2fsprogs scalpel binwalk
sudo losetup -fP /mnt/d/rescue/vps-sda1.img

# 2. Récupération (90-180 min)
sudo ./zammad_recovery_ultimate.sh /dev/loop0 /mnt/d/recovery_output

# 3. Analyse
./analyze_recovered_files.sh /mnt/d/recovery_output

# 4. Vérification
cat /mnt/d/recovery_output/reports/RAPPORT_FINAL.txt
ls -lh /mnt/d/recovery_output/BEST_CANDIDATES/

# 5. Test des fichiers SQL
head -100 /mnt/d/recovery_output/BEST_CANDIDATES/[fichier]
sudo -u postgres psql -d test_db -f [fichier.sql]
```

**Bonne chance ! 🍀**
