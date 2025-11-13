#!/bin/bash
################################################################################
# QUICKSTART - Démarrage rapide de la récupération
# Ce script automatise toute la préparation et lance la récupération
################################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}   🚀 QUICKSTART - RÉCUPÉRATION POSTGRESQL/ZAMMAD${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo ""

# Configuration par défaut
IMAGE_PATH="/mnt/d/rescue/vps-sda1.img"
OUTPUT_DIR="/mnt/d/recovery_output"

# Vérification root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}❌ Ce script doit être exécuté avec sudo${NC}"
    echo "Usage: sudo ./quickstart.sh"
    exit 1
fi

echo -e "${YELLOW}[1/6] Vérification de l'image disque...${NC}"
if [ ! -f "$IMAGE_PATH" ]; then
    echo -e "${RED}❌ Image introuvable: $IMAGE_PATH${NC}"
    echo ""
    echo "Veuillez spécifier le chemin de votre image:"
    read -p "Chemin de l'image .img: " IMAGE_PATH

    if [ ! -f "$IMAGE_PATH" ]; then
        echo -e "${RED}❌ Image toujours introuvable. Abandon.${NC}"
        exit 1
    fi
fi

echo -e "${GREEN}✅ Image trouvée: $IMAGE_PATH${NC}"
ls -lh "$IMAGE_PATH"
echo ""

echo -e "${YELLOW}[2/6] Vérification de l'espace disque...${NC}"
output_mount=$(df "$OUTPUT_DIR" 2>/dev/null | tail -1 | awk '{print $4}')
output_mount_gb=$((output_mount / 1024 / 1024))

if [ "$output_mount_gb" -lt 20 ]; then
    echo -e "${RED}⚠️  ATTENTION: Seulement ${output_mount_gb}GB disponibles${NC}"
    echo "Au moins 20-30 GB recommandés pour la récupération"
    read -p "Continuer quand même ? (oui/non): " confirm

    if [ "$confirm" != "oui" ]; then
        echo "Veuillez spécifier un autre répertoire de sortie:"
        read -p "Répertoire de sortie: " OUTPUT_DIR
    fi
fi

echo -e "${GREEN}✅ Espace disponible: ${output_mount_gb}GB${NC}"
echo ""

echo -e "${YELLOW}[3/6] Vérification des outils requis...${NC}"

missing_tools=()

for tool in debugfs scalpel binwalk strings grep dd file; do
    if ! command -v "$tool" &> /dev/null; then
        missing_tools+=("$tool")
        echo -e "${RED}  ❌ $tool manquant${NC}"
    else
        echo -e "${GREEN}  ✅ $tool${NC}"
    fi
done

if [ ${#missing_tools[@]} -gt 0 ]; then
    echo ""
    echo -e "${YELLOW}Installation des outils manquants...${NC}"

    apt update -qq
    apt install -y e2fsprogs scalpel binwalk binutils coreutils findutils

    echo -e "${GREEN}✅ Outils installés${NC}"
fi
echo ""

echo -e "${YELLOW}[4/6] Configuration du device loop...${NC}"

# Nettoyer les anciens montages
losetup -D 2>/dev/null || true

# Monter l'image en loop
losetup -fP "$IMAGE_PATH"
LOOP_DEVICE=$(losetup -j "$IMAGE_PATH" | cut -d: -f1)

if [ -z "$LOOP_DEVICE" ]; then
    echo -e "${RED}❌ Échec du montage en loop${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Image montée sur: $LOOP_DEVICE${NC}"
echo ""

echo -e "${YELLOW}[5/6] Vérification du filesystem...${NC}"
file -s "$LOOP_DEVICE"
blkid "$LOOP_DEVICE" 2>/dev/null || true
echo ""

echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}   📋 CONFIGURATION FINALE${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo ""
echo "  • Image source   : $IMAGE_PATH"
echo "  • Device loop    : $LOOP_DEVICE"
echo "  • Sortie         : $OUTPUT_DIR"
echo "  • Espace dispo   : ${output_mount_gb}GB"
echo ""
echo -e "${YELLOW}⏱️  Durée estimée: 90-180 minutes${NC}"
echo ""

read -p "🚀 Lancer la récupération maintenant ? (oui/non): " start

if [ "$start" != "oui" ]; then
    echo ""
    echo -e "${YELLOW}ℹ️  Récupération annulée.${NC}"
    echo ""
    echo "Pour lancer manuellement:"
    echo "  sudo ./zammad_recovery_ultimate.sh $LOOP_DEVICE $OUTPUT_DIR"
    echo ""
    echo "Pour nettoyer le device loop:"
    echo "  sudo losetup -d $LOOP_DEVICE"
    exit 0
fi

echo ""
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}   ⚡ LANCEMENT DE LA RÉCUPÉRATION${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo ""

# Vérifier que le script principal existe
if [ ! -f "./zammad_recovery_ultimate.sh" ]; then
    echo -e "${RED}❌ Script principal introuvable: zammad_recovery_ultimate.sh${NC}"
    echo "Assurez-vous que tous les scripts sont dans le même répertoire."
    exit 1
fi

chmod +x ./zammad_recovery_ultimate.sh 2>/dev/null || true

echo -e "${YELLOW}[6/6] Exécution du script de récupération...${NC}"
echo ""

# Lancer la récupération
./zammad_recovery_ultimate.sh "$LOOP_DEVICE" "$OUTPUT_DIR" "$LOOP_DEVICE"

# Récupération terminée
echo ""
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}   ✅ RÉCUPÉRATION TERMINÉE !${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo ""

echo -e "${YELLOW}📊 Prochaines étapes:${NC}"
echo ""
echo "1️⃣  Consultez le rapport:"
echo "   cat $OUTPUT_DIR/reports/RAPPORT_FINAL.txt"
echo ""
echo "2️⃣  Lancez l'analyse automatique:"
echo "   ./analyze_recovered_files.sh $OUTPUT_DIR"
echo ""
echo "3️⃣  Vérifiez les meilleurs candidats:"
echo "   ls -lh $OUTPUT_DIR/BEST_CANDIDATES/"
echo ""
echo "4️⃣  Testez les fichiers SQL récupérés:"
echo "   head -100 $OUTPUT_DIR/BEST_CANDIDATES/[fichier]"
echo ""

read -p "🔍 Lancer l'analyse automatique maintenant ? (oui/non): " analyze

if [ "$analyze" = "oui" ]; then
    echo ""
    echo -e "${YELLOW}Lancement de l'analyse...${NC}"
    echo ""

    if [ -f "./analyze_recovered_files.sh" ]; then
        chmod +x ./analyze_recovered_files.sh 2>/dev/null || true
        ./analyze_recovered_files.sh "$OUTPUT_DIR"
    else
        echo -e "${RED}❌ Script d'analyse introuvable${NC}"
    fi
fi

echo ""
echo -e "${GREEN}🎉 Processus terminé !${NC}"
echo ""
echo -e "${YELLOW}💡 Aide supplémentaire:${NC}"
echo "   Consultez GUIDE_UTILISATION.md pour plus de détails"
echo ""
echo -e "${YELLOW}🧹 Nettoyage (quand vous avez fini):${NC}"
echo "   sudo losetup -d $LOOP_DEVICE"
echo ""

exit 0
