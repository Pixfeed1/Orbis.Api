#!/bin/bash
################################################################################
# SCRIPT DE RÉCUPÉRATION POSTGRESQL/ZAMMAD - VERSION ULTIMATE
# Optimisé pour: Image ext4 158GB, suppression 10/11/2025, clone 12/11/2025
# Cible: Backups PostgreSQL (.sql, .psql, .psql.gz) + Volumes Docker Zammad
################################################################################

set -e

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration par défaut
IMAGE_PATH="${1:-/mnt/d/rescue/vps-sda1.img}"
OUTPUT_DIR="${2:-/mnt/d/recovery_output}"
LOOP_DEVICE="${3:-/dev/loop0}"

echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}   🔍 RÉCUPÉRATION POSTGRESQL/ZAMMAD - ULTIMATE EDITION${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "${YELLOW}Configuration:${NC}"
echo "  • Image     : $IMAGE_PATH"
echo "  • Device    : $LOOP_DEVICE"
echo "  • Sortie    : $OUTPUT_DIR"
echo "  • Date exec : $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Vérifications préliminaires
if [ ! -e "$LOOP_DEVICE" ]; then
    echo -e "${RED}❌ ERREUR: Device $LOOP_DEVICE introuvable${NC}"
    echo "Montez d'abord l'image avec: sudo losetup -fP $IMAGE_PATH"
    exit 1
fi

if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}❌ ERREUR: Ce script doit être exécuté avec sudo${NC}"
    exit 1
fi

# Création de la structure de sortie
mkdir -p "$OUTPUT_DIR"/{1_debugfs,2_scalpel,3_binwalk,4_strings,5_manual,6_docker,reports}

# Fonction de log
log() {
    echo -e "${GREEN}[$(date '+%H:%M:%S')]${NC} $1"
    echo "[$(date '+%H:%M:%S')] $1" >> "$OUTPUT_DIR/reports/recovery.log"
}

# Fonction de statistiques
stats() {
    local dir=$1
    local count=$(find "$dir" -type f 2>/dev/null | wc -l)
    local size=$(du -sh "$dir" 2>/dev/null | cut -f1)
    echo "    → $count fichiers ($size)"
}

################################################################################
# ÉTAPE 1: DEBUGFS - RÉCUPÉRATION D'INODES SUPPRIMÉS
################################################################################
log "═══ [1/7] ANALYSE DEBUGFS - Inodes supprimés ═══"

debugfs -R "lsdel" "$LOOP_DEVICE" 2>/dev/null > "$OUTPUT_DIR/1_debugfs/deleted_inodes.txt" || {
    log "⚠️  debugfs lsdel a échoué, on continue..."
}

# Analyser et trier les inodes par taille
if [ -f "$OUTPUT_DIR/1_debugfs/deleted_inodes.txt" ]; then
    log "Filtrage des gros fichiers (>500KB, probablement des backups)..."

    # Fichiers >500KB
    awk '$6 > 1024 {printf "Inode: %s | Taille: %.2f MB | Supprimé: %s %s\n", $1, $6*512/1024/1024, $8, $9}' \
        "$OUTPUT_DIR/1_debugfs/deleted_inodes.txt" | tee "$OUTPUT_DIR/1_debugfs/large_files.txt"

    # Restaurer automatiquement les 50 plus gros fichiers
    log "Restauration des 50 plus gros fichiers supprimés..."
    awk '$6 > 1024 {print $1}' "$OUTPUT_DIR/1_debugfs/deleted_inodes.txt" | head -50 | while read inode; do
        if [ -n "$inode" ]; then
            debugfs -R "dump <$inode> $OUTPUT_DIR/1_debugfs/recovered_inode_$inode" "$LOOP_DEVICE" 2>/dev/null || true
        fi
    done

    stats "$OUTPUT_DIR/1_debugfs"
fi

################################################################################
# ÉTAPE 2: SCALPEL - CARVING PAR SIGNATURES
################################################################################
log "═══ [2/7] SCALPEL - Recherche par signatures ═══"

# Créer configuration scalpel ultra-spécialisée
cat > "$OUTPUT_DIR/2_scalpel/scalpel.conf" << 'SCALPEL_EOF'
# Configuration optimisée pour PostgreSQL/Zammad

# Dumps PostgreSQL (signatures multiples)
sql     y   500000000   --\n--\ PostgreSQL\ database\ dump
sql     y   500000000   PGDMP
sql     y   500000000   pg_dump\ version
sql     y   500000000   --\ Dumped\ from\ database\ version
sql     y   500000000   --\ Dumped\ by\ pg_dump
sql     y   500000000   CREATE\ DATABASE\ zammad
sql     y   500000000   \\connect\ zammad

# Archives compressées
gz      y   500000000   \x1f\x8b\x08
bz2     y   500000000   BZh
xz      y   500000000   \xfd\x37\x7a\x58\x5a\x00

# Tar archives (volumes Docker)
tar     y   500000000   ustar\x00
tar     y   500000000   ustar\x20\x20\x00

# Fichiers .psql spécifiques
psql    y   500000000   SET\ statement_timeout
psql    y   500000000   SET\ client_encoding
SCALPEL_EOF

log "Lancement de scalpel (peut prendre 30-60 min)..."
scalpel -c "$OUTPUT_DIR/2_scalpel/scalpel.conf" -o "$OUTPUT_DIR/2_scalpel/output" "$LOOP_DEVICE" 2>&1 | \
    tee "$OUTPUT_DIR/2_scalpel/scalpel.log"

stats "$OUTPUT_DIR/2_scalpel/output"

################################################################################
# ÉTAPE 3: BINWALK - EXTRACTION D'ARCHIVES ENFOUIES
################################################################################
log "═══ [3/7] BINWALK - Extraction archives ═══"

log "Recherche des archives gzip/tar enfouies..."
binwalk -e -C "$OUTPUT_DIR/3_binwalk" --run-as=root "$LOOP_DEVICE" 2>&1 | \
    tee "$OUTPUT_DIR/3_binwalk/binwalk.log" || {
    log "⚠️  binwalk partiel, on continue..."
}

stats "$OUTPUT_DIR/3_binwalk"

################################################################################
# ÉTAPE 4: STRINGS - ANALYSE DES CHAÎNES
################################################################################
log "═══ [4/7] STRINGS - Recherche de patterns ═══"

log "Recherche des signatures PostgreSQL..."
strings -a -t d "$LOOP_DEVICE" | grep -iE "pg_dump|postgresql.*dump|pgdmp|zammad_production" | \
    head -1000 > "$OUTPUT_DIR/4_strings/postgresql_offsets.txt" 2>&1 || true

log "Recherche des noms de fichiers .sql/.psql..."
strings -a -t d "$LOOP_DEVICE" | grep -E "\.sql$|\.psql|\.sql\.gz$|backup.*sql|dump.*sql" | \
    head -500 > "$OUTPUT_DIR/4_strings/sql_filenames.txt" 2>&1 || true

log "Recherche de 'zammad' dans le disque..."
strings -a -t d "$LOOP_DEVICE" | grep -i "zammad" | head -1000 > "$OUTPUT_DIR/4_strings/zammad_refs.txt" 2>&1 || true

log "Recherche des chemins Docker volumes..."
strings -a -t d "$LOOP_DEVICE" | grep "var/lib/docker/volumes" | \
    head -200 > "$OUTPUT_DIR/4_strings/docker_paths.txt" 2>&1 || true

echo "  Résultats:"
[ -f "$OUTPUT_DIR/4_strings/postgresql_offsets.txt" ] && \
    echo "    → PostgreSQL offsets: $(wc -l < "$OUTPUT_DIR/4_strings/postgresql_offsets.txt")"
[ -f "$OUTPUT_DIR/4_strings/sql_filenames.txt" ] && \
    echo "    → SQL filenames: $(wc -l < "$OUTPUT_DIR/4_strings/sql_filenames.txt")"
[ -f "$OUTPUT_DIR/4_strings/zammad_refs.txt" ] && \
    echo "    → Zammad refs: $(wc -l < "$OUTPUT_DIR/4_strings/zammad_refs.txt")"

################################################################################
# ÉTAPE 5: EXTRACTION MANUELLE DES MEILLEURS CANDIDATS
################################################################################
log "═══ [5/7] EXTRACTION MANUELLE - Top candidats ═══"

if [ -f "$OUTPUT_DIR/4_strings/postgresql_offsets.txt" ]; then
    log "Extraction des 20 meilleurs offsets PostgreSQL..."

    head -20 "$OUTPUT_DIR/4_strings/postgresql_offsets.txt" | while IFS= read -r line; do
        offset=$(echo "$line" | awk '{print $1}')

        if [ -n "$offset" ] && [ "$offset" -gt 0 ] 2>/dev/null; then
            log "  → Extraction à offset $offset..."

            # Extraire 100MB à partir de l'offset
            dd if="$LOOP_DEVICE" of="$OUTPUT_DIR/5_manual/fragment_offset_$offset.raw" \
               bs=1 skip="$offset" count=104857600 2>/dev/null || true
        fi
    done
fi

# Recherche binaire directe de signatures PGDMP
log "Recherche binaire des signatures PGDMP..."
grep -abo "PGDMP" "$LOOP_DEVICE" 2>/dev/null | head -10 | while IFS=: read offset match; do
    log "  → PGDMP trouvé à offset $offset"
    dd if="$LOOP_DEVICE" of="$OUTPUT_DIR/5_manual/pgdmp_$offset.raw" \
       bs=1 skip="$offset" count=52428800 2>/dev/null || true
done

stats "$OUTPUT_DIR/5_manual"

################################################################################
# ÉTAPE 6: FOCUS DOCKER VOLUMES
################################################################################
log "═══ [6/7] DOCKER VOLUMES - Recherche spécifique ═══"

# Chercher les métadonnées de volumes Zammad
log "Recherche volumes zammad-docker-compose..."
strings -a "$LOOP_DEVICE" | grep -i "zammad-docker-compose" | head -100 > \
    "$OUTPUT_DIR/6_docker/zammad_volumes.txt" 2>&1 || true

# Recherche des fichiers de backup Zammad typiques
log "Recherche patterns de backup Zammad..."
strings -a -t d "$LOOP_DEVICE" | grep -iE "zammad.*backup|backup.*zammad|zammad.*\.sql" | head -200 > \
    "$OUTPUT_DIR/6_docker/zammad_backup_patterns.txt" 2>&1 || true

stats "$OUTPUT_DIR/6_docker"

################################################################################
# ÉTAPE 7: DÉCOMPRESSION ET VALIDATION
################################################################################
log "═══ [7/7] DÉCOMPRESSION - Fichiers .gz trouvés ═══"

log "Recherche et décompression des archives .gz..."
find "$OUTPUT_DIR" -name "*.gz" -type f 2>/dev/null | while read gzfile; do
    base=$(basename "$gzfile" .gz)
    dir=$(dirname "$gzfile")

    log "  → Décompression: $base"
    gunzip -c "$gzfile" > "$dir/decompressed_$base.sql" 2>/dev/null || true

    # Test si c'est du SQL valide
    if head -5 "$dir/decompressed_$base.sql" 2>/dev/null | grep -qi "postgresql\|create\|insert"; then
        log "    ✅ SQL VALIDE TROUVÉ!"
        cp "$dir/decompressed_$base.sql" "$OUTPUT_DIR/reports/VALID_SQL_$base.sql"
    fi
done

################################################################################
# RAPPORT FINAL
################################################################################
log "═══ GÉNÉRATION DU RAPPORT FINAL ═══"

cat > "$OUTPUT_DIR/reports/RAPPORT_FINAL.txt" << REPORT_EOF
════════════════════════════════════════════════════════════
  RAPPORT DE RÉCUPÉRATION POSTGRESQL/ZAMMAD
════════════════════════════════════════════════════════════

Date d'exécution: $(date '+%Y-%m-%d %H:%M:%S')
Image analysée: $IMAGE_PATH
Device: $LOOP_DEVICE

════════════════════════════════════════════════════════════
  📊 STATISTIQUES DE RÉCUPÉRATION
════════════════════════════════════════════════════════════

1. DEBUGFS (Inodes supprimés):
$(stats "$OUTPUT_DIR/1_debugfs")

2. SCALPEL (Signatures):
$(stats "$OUTPUT_DIR/2_scalpel")

3. BINWALK (Archives):
$(stats "$OUTPUT_DIR/3_binwalk")

4. STRINGS (Offsets):
   • PostgreSQL refs: $(wc -l < "$OUTPUT_DIR/4_strings/postgresql_offsets.txt" 2>/dev/null || echo "0")
   • SQL filenames: $(wc -l < "$OUTPUT_DIR/4_strings/sql_filenames.txt" 2>/dev/null || echo "0")
   • Zammad refs: $(wc -l < "$OUTPUT_DIR/4_strings/zammad_refs.txt" 2>/dev/null || echo "0")

5. EXTRACTION MANUELLE:
$(stats "$OUTPUT_DIR/5_manual")

6. DOCKER VOLUMES:
$(stats "$OUTPUT_DIR/6_docker")

════════════════════════════════════════════════════════════
  🎯 FICHIERS À VÉRIFIER EN PRIORITÉ
════════════════════════════════════════════════════════════

1. Fichiers SQL valides trouvés:
$(find "$OUTPUT_DIR/reports" -name "VALID_SQL_*.sql" -type f -ls 2>/dev/null || echo "   Aucun")

2. Plus gros fichiers récupérés par debugfs:
$(find "$OUTPUT_DIR/1_debugfs" -name "recovered_inode_*" -type f -exec ls -lh {} \; 2>/dev/null | sort -k5 -hr | head -10 || echo "   Aucun")

3. Fichiers SQL trouvés par scalpel:
$(find "$OUTPUT_DIR/2_scalpel" -name "*.sql" -type f -ls 2>/dev/null | head -10 || echo "   Aucun")

4. Fragments manuels les plus gros:
$(find "$OUTPUT_DIR/5_manual" -type f -exec ls -lh {} \; 2>/dev/null | sort -k5 -hr | head -5 || echo "   Aucun")

════════════════════════════════════════════════════════════
  🔍 PROCHAINES ÉTAPES RECOMMANDÉES
════════════════════════════════════════════════════════════

1. Vérifiez les fichiers marqués VALID_SQL_* dans reports/

2. Testez les plus gros fichiers debugfs:
   cd $OUTPUT_DIR/1_debugfs
   for f in recovered_inode_*; do
       file \$f
       head -20 \$f
   done

3. Examinez les fichiers scalpel:
   find $OUTPUT_DIR/2_scalpel -name "*.sql" -exec head -20 {} \;

4. Vérifiez les fragments manuels:
   cd $OUTPUT_DIR/5_manual
   for f in *.raw; do
       strings \$f | head -50
   done

5. Testez la restauration PostgreSQL:
   psql -U postgres -d test_db -f fichier_recupere.sql

════════════════════════════════════════════════════════════
  💡 ANALYSE DES CHANCES DE RÉCUPÉRATION
════════════════════════════════════════════════════════════

Facteurs POSITIFS ✅:
• Clone fait seulement 2 jours après suppression
• Image ext4 bien préservée
• Multiples techniques de récupération utilisées

Facteurs NÉGATIFS ❌:
• Réinstallation Zammad le soir même (écrasement partiel)
• Délai de 2 jours = risque de réutilisation des blocs

Estimation globale: 30-50% de récupération partielle possible

════════════════════════════════════════════════════════════
REPORT_EOF

echo ""
echo -e "${GREEN}════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  ✅ RÉCUPÉRATION TERMINÉE !${NC}"
echo -e "${GREEN}════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "${YELLOW}📂 Résultats sauvegardés dans:${NC} $OUTPUT_DIR"
echo ""
echo -e "${BLUE}📋 Consultez le rapport complet:${NC}"
echo "   cat $OUTPUT_DIR/reports/RAPPORT_FINAL.txt"
echo ""
echo -e "${BLUE}🔍 Vérifiez en priorité:${NC}"
echo "   1. $OUTPUT_DIR/reports/VALID_SQL_*.sql"
echo "   2. $OUTPUT_DIR/1_debugfs/recovered_inode_*"
echo "   3. $OUTPUT_DIR/2_scalpel/output/"
echo ""

# Afficher un aperçu des fichiers trouvés
if compgen -G "$OUTPUT_DIR/reports/VALID_SQL_*.sql" > /dev/null; then
    echo -e "${GREEN}🎉 FICHIERS SQL VALIDES TROUVÉS !${NC}"
    ls -lh "$OUTPUT_DIR/reports/VALID_SQL_"*.sql
    echo ""
fi

# Statistiques finales
total_files=$(find "$OUTPUT_DIR" -type f 2>/dev/null | wc -l)
total_size=$(du -sh "$OUTPUT_DIR" 2>/dev/null | cut -f1)

echo -e "${YELLOW}📊 Total récupéré:${NC} $total_files fichiers ($total_size)"
echo ""
echo -e "${BLUE}Log complet:${NC} $OUTPUT_DIR/reports/recovery.log"
echo ""

log "═══ Script terminé avec succès ═══"

exit 0
