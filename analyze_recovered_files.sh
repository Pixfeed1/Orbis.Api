#!/bin/bash
################################################################################
# SCRIPT D'ANALYSE DES FICHIERS RÉCUPÉRÉS
# À utiliser après l'exécution de zammad_recovery_ultimate.sh
################################################################################

RECOVERY_DIR="${1:-/mnt/d/recovery_output}"

if [ ! -d "$RECOVERY_DIR" ]; then
    echo "❌ Répertoire $RECOVERY_DIR introuvable"
    echo "Usage: $0 <recovery_directory>"
    exit 1
fi

echo "════════════════════════════════════════════════════════════"
echo "  🔍 ANALYSE DES FICHIERS RÉCUPÉRÉS"
echo "════════════════════════════════════════════════════════════"
echo ""

# Fonction pour tester si un fichier est un dump PostgreSQL valide
test_postgresql_file() {
    local file=$1
    local score=0

    # Vérifier les signatures PostgreSQL
    if head -100 "$file" 2>/dev/null | grep -qi "postgresql.*database.*dump"; then
        ((score+=3))
    fi

    if head -100 "$file" 2>/dev/null | grep -qi "pg_dump"; then
        ((score+=2))
    fi

    if head -100 "$file" 2>/dev/null | grep -qi "CREATE TABLE\|CREATE DATABASE\|INSERT INTO"; then
        ((score+=2))
    fi

    if head -100 "$file" 2>/dev/null | grep -qi "zammad"; then
        ((score+=3))
    fi

    if grep -q "PGDMP" "$file" 2>/dev/null; then
        ((score+=5))
    fi

    echo $score
}

echo "[1/5] 🔎 Analyse des fichiers debugfs..."
echo "─────────────────────────────────────────────────────────────"

if [ -d "$RECOVERY_DIR/1_debugfs" ]; then
    echo "Fichiers récupérés par inode:"
    echo ""

    find "$RECOVERY_DIR/1_debugfs" -name "recovered_inode_*" -type f 2>/dev/null | while read file; do
        size=$(ls -lh "$file" | awk '{print $5}')
        filetype=$(file -b "$file" 2>/dev/null | cut -d',' -f1)
        score=$(test_postgresql_file "$file")

        echo "📄 $(basename "$file")"
        echo "   Taille: $size"
        echo "   Type: $filetype"
        echo "   Score SQL: $score/15"

        if [ $score -gt 5 ]; then
            echo "   ✅ CANDIDAT PROMETTEUR!"
            # Copier dans le dossier des meilleurs candidats
            mkdir -p "$RECOVERY_DIR/BEST_CANDIDATES"
            cp "$file" "$RECOVERY_DIR/BEST_CANDIDATES/"
        fi

        # Afficher les premières lignes
        echo "   Aperçu:"
        head -5 "$file" 2>/dev/null | sed 's/^/      /'
        echo ""
    done
else
    echo "   Aucun fichier debugfs trouvé"
fi

echo ""
echo "[2/5] 🗃️  Analyse des fichiers scalpel..."
echo "─────────────────────────────────────────────────────────────"

if [ -d "$RECOVERY_DIR/2_scalpel/output" ]; then
    sql_count=$(find "$RECOVERY_DIR/2_scalpel/output" -name "*.sql" -o -name "*sql*" 2>/dev/null | wc -l)
    gz_count=$(find "$RECOVERY_DIR/2_scalpel/output" -name "*.gz" 2>/dev/null | wc -l)

    echo "Fichiers SQL trouvés: $sql_count"
    echo "Archives .gz trouvées: $gz_count"
    echo ""

    # Analyser les fichiers SQL
    find "$RECOVERY_DIR/2_scalpel/output" -type f \( -name "*.sql" -o -name "*sql*" \) 2>/dev/null | head -20 | while read file; do
        size=$(ls -lh "$file" | awk '{print $5}')
        score=$(test_postgresql_file "$file")

        echo "📄 $(basename "$file")"
        echo "   Taille: $size"
        echo "   Score SQL: $score/15"

        if [ $score -gt 5 ]; then
            echo "   ✅ CANDIDAT PROMETTEUR!"
            mkdir -p "$RECOVERY_DIR/BEST_CANDIDATES"
            cp "$file" "$RECOVERY_DIR/BEST_CANDIDATES/"
        fi

        head -3 "$file" 2>/dev/null | sed 's/^/      /'
        echo ""
    done

    # Tester les archives .gz
    if [ $gz_count -gt 0 ]; then
        echo "Test des archives .gz:"
        find "$RECOVERY_DIR/2_scalpel/output" -name "*.gz" 2>/dev/null | head -10 | while read gzfile; do
            echo "   📦 $(basename "$gzfile")"

            # Tester la validité
            if gzip -t "$gzfile" 2>/dev/null; then
                echo "      ✅ Archive valide"

                # Décompresser et tester le contenu
                temp_sql="${gzfile}_decompressed.sql"
                gunzip -c "$gzfile" > "$temp_sql" 2>/dev/null

                score=$(test_postgresql_file "$temp_sql")
                if [ $score -gt 5 ]; then
                    echo "      ✅ CONTIENT DU SQL! Score: $score/15"
                    mkdir -p "$RECOVERY_DIR/BEST_CANDIDATES"
                    cp "$temp_sql" "$RECOVERY_DIR/BEST_CANDIDATES/$(basename "$gzfile" .gz).sql"
                fi

                rm -f "$temp_sql"
            else
                echo "      ❌ Archive corrompue"
            fi
            echo ""
        done
    fi
else
    echo "   Aucun résultat scalpel trouvé"
fi

echo ""
echo "[3/5] 📦 Analyse des extractions binwalk..."
echo "─────────────────────────────────────────────────────────────"

if [ -d "$RECOVERY_DIR/3_binwalk" ]; then
    extracted=$(find "$RECOVERY_DIR/3_binwalk" -type f 2>/dev/null | wc -l)
    echo "Fichiers extraits: $extracted"

    if [ $extracted -gt 0 ]; then
        echo "Recherche de fichiers SQL dans les extractions..."
        find "$RECOVERY_DIR/3_binwalk" -type f 2>/dev/null | while read file; do
            if file "$file" | grep -qi "text\|ascii\|sql"; then
                score=$(test_postgresql_file "$file")

                if [ $score -gt 3 ]; then
                    echo "   📄 $(basename "$file") - Score: $score/15"
                    mkdir -p "$RECOVERY_DIR/BEST_CANDIDATES"
                    cp "$file" "$RECOVERY_DIR/BEST_CANDIDATES/"
                fi
            fi
        done
    fi
else
    echo "   Aucune extraction binwalk"
fi

echo ""
echo "[4/5] ✂️  Analyse des fragments manuels..."
echo "─────────────────────────────────────────────────────────────"

if [ -d "$RECOVERY_DIR/5_manual" ]; then
    echo "Fragments extraits:"

    find "$RECOVERY_DIR/5_manual" -type f -name "*.raw" 2>/dev/null | head -20 | while read file; do
        size=$(ls -lh "$file" | awk '{print $5}')

        # Vérifier s'il contient du SQL
        if strings "$file" | head -100 | grep -qi "postgresql\|create table\|pgdmp"; then
            echo "   📄 $(basename "$file") ($size)"
            echo "      ✅ Contient probablement du SQL"

            # Extraire la partie texte
            strings "$file" > "${file}.txt"

            score=$(test_postgresql_file "${file}.txt")
            echo "      Score: $score/15"

            if [ $score -gt 5 ]; then
                mkdir -p "$RECOVERY_DIR/BEST_CANDIDATES"
                cp "${file}.txt" "$RECOVERY_DIR/BEST_CANDIDATES/$(basename "$file" .raw).sql"
            fi
        fi
    done
else
    echo "   Aucun fragment manuel"
fi

echo ""
echo "[5/5] 🐳 Analyse des références Docker..."
echo "─────────────────────────────────────────────────────────────"

if [ -f "$RECOVERY_DIR/6_docker/zammad_volumes.txt" ]; then
    volumes=$(wc -l < "$RECOVERY_DIR/6_docker/zammad_volumes.txt")
    echo "Références aux volumes Zammad: $volumes"

    if [ $volumes -gt 0 ]; then
        echo "Volumes trouvés:"
        head -20 "$RECOVERY_DIR/6_docker/zammad_volumes.txt" | sed 's/^/   /'
    fi
fi

if [ -f "$RECOVERY_DIR/6_docker/zammad_backup_patterns.txt" ]; then
    backups=$(wc -l < "$RECOVERY_DIR/6_docker/zammad_backup_patterns.txt")
    echo "Patterns de backup Zammad: $backups"

    if [ $backups -gt 0 ]; then
        echo "Top patterns:"
        head -10 "$RECOVERY_DIR/6_docker/zammad_backup_patterns.txt" | sed 's/^/   /'
    fi
fi

echo ""
echo "════════════════════════════════════════════════════════════"
echo "  ✅ ANALYSE TERMINÉE"
echo "════════════════════════════════════════════════════════════"
echo ""

# Rapport des meilleurs candidats
if [ -d "$RECOVERY_DIR/BEST_CANDIDATES" ]; then
    best_count=$(ls -1 "$RECOVERY_DIR/BEST_CANDIDATES" 2>/dev/null | wc -l)

    if [ $best_count -gt 0 ]; then
        echo "🎯 MEILLEURS CANDIDATS TROUVÉS: $best_count fichiers"
        echo ""
        echo "📂 Emplacement: $RECOVERY_DIR/BEST_CANDIDATES/"
        echo ""
        echo "Liste des fichiers prometteurs:"
        ls -lh "$RECOVERY_DIR/BEST_CANDIDATES/" | tail -n +2 | while read line; do
            echo "   $line"
        done
        echo ""
        echo "💡 Testez ces fichiers avec:"
        echo "   head -100 $RECOVERY_DIR/BEST_CANDIDATES/[fichier]"
        echo "   psql -U postgres -d test_restore -f $RECOVERY_DIR/BEST_CANDIDATES/[fichier]"
    else
        echo "⚠️  Aucun candidat prometteur identifié automatiquement"
        echo ""
        echo "Vérifiez manuellement:"
        echo "  • $RECOVERY_DIR/1_debugfs/"
        echo "  • $RECOVERY_DIR/2_scalpel/"
        echo "  • $RECOVERY_DIR/5_manual/"
    fi
else
    echo "ℹ️  Aucun candidat automatiquement identifié"
    echo ""
    echo "Examinez manuellement les répertoires de récupération"
fi

echo ""
echo "📋 Rapport complet: $RECOVERY_DIR/reports/RAPPORT_FINAL.txt"
echo ""

exit 0
