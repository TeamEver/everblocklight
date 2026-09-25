#!/usr/bin/env bash
#
# Test d'installation réel d'everblocklight dans une boutique PrestaShop (images Docker officielles).
#
# Usage : tests/install/run.sh <tag image prestashop/prestashop>   ex. 8.2-8.1, 9.1-8.4
# Variables optionnelles : PS_PORT (8080), KEEP_CONTAINERS=1 (ne pas supprimer les conteneurs à la fin)
#
set -euo pipefail

PS_TAG="${1:?Usage: $0 <tag prestashop/prestashop, ex. 8.2-8.1>}"
PS_PORT="${PS_PORT:-8080}"
MODULE_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
NAME="evbl-ci-$(echo "$PS_TAG" | tr -c 'a-zA-Z0-9' '-' | sed 's/-*$//')"
NET="$NAME-net"; DB="$NAME-db"; PS="$NAME-ps"
DB_PASS=admin
WORK="$(mktemp -d)"
FAILURES=0

log()  { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
ok()   { printf '  \033[32m✔\033[0m %s\n' "$*"; }
ko()   { printf '  \033[31m✘ %s\033[0m\n' "$*"; FAILURES=$((FAILURES + 1)); }
sql()  { docker exec "$DB" mysql -uroot -p"$DB_PASS" -N -B prestashop -e "$1" 2>/dev/null; }
ps_console() { docker exec -u www-data "$PS" php -d memory_limit=-1 bin/console "$@"; }
expect_eq() { # libellé, attendu, obtenu
  if [ "$2" = "$3" ]; then ok "$1 ($3)"; else ko "$1 : attendu '$2', obtenu '$3'"; fi
}

cleanup() {
  if [ "${KEEP_CONTAINERS:-0}" != "1" ]; then
    docker rm -f "$PS" "$DB" >/dev/null 2>&1 || true
    docker network rm "$NET" >/dev/null 2>&1 || true
  fi
  rm -rf "$WORK"
}
trap cleanup EXIT

log "Démarrage MySQL + PrestaShop ($PS_TAG)"
cleanup; WORK="$(mktemp -d)"
docker network create "$NET" >/dev/null
docker run -d --name "$DB" --network "$NET" \
  -e MYSQL_ROOT_PASSWORD="$DB_PASS" -e MYSQL_DATABASE=prestashop \
  mysql:8.0 >/dev/null
docker run -d --name "$PS" --network "$NET" -p "$PS_PORT:80" \
  -e DB_SERVER="$DB" -e DB_PASSWD="$DB_PASS" -e DB_NAME=prestashop \
  -e PS_INSTALL_AUTO=1 -e PS_DOMAIN="localhost:$PS_PORT" \
  `# PS 9 : sans dossier admin/, l'installateur attend admin-dev (le rename() d'admin/ échoue sur overlayfs)` \
  -e PS_FOLDER_ADMIN=admin-dev \
  -e PS_DEV_MODE=0 -e PS_LANGUAGE=fr -e PS_COUNTRY=FR \
  "prestashop/prestashop:$PS_TAG" >/dev/null

log "Attente de la fin de l'installation de PrestaShop (max 15 min)"
for _ in $(seq 1 180); do
  # Logs lus dans une variable : un « docker logs | grep -q » échoue sous pipefail (SIGPIPE)
  PS_LOGS="$(docker logs "$PS" 2>&1 || true)"
  case "$PS_LOGS" in *'Starting web server now'*) break ;; esac
  if [ -z "$(docker ps -q -f name="^${PS}$")" ]; then echo "$PS_LOGS" | tail -50; echo "Le conteneur PrestaShop s'est arrêté"; exit 1; fi
  sleep 5
done
case "$PS_LOGS" in
  *'PrestaShop installation failed'*) echo "$PS_LOGS" | tail -50; echo "Échec de l'installation PrestaShop"; exit 1 ;;
  *'Starting web server now'*) ;;
  *) echo "$PS_LOGS" | tail -50; echo "Timeout installation PrestaShop"; exit 1 ;;
esac
PS_VERSION="$(sql "SELECT value FROM ps_configuration WHERE name='PS_VERSION_DB'")"
ok "PrestaShop $PS_VERSION installé"

log "Copie du module (sans outillage de développement)"
tar -C "$(dirname "$MODULE_DIR")" --exclude='everblocklight/tests' --exclude='everblocklight/.git' \
  --exclude='everblocklight/.github' -cf - "$(basename "$MODULE_DIR")" \
  | docker exec -i "$PS" tar -C /var/www/html/modules -xf -
docker exec "$PS" chown -R www-data:www-data /var/www/html/modules/everblocklight

log "Installation du module"
if ps_console prestashop:module install everblocklight; then ok "prestashop:module install"; else ko "prestashop:module install a échoué"; fi

log "Contrôles en base après installation"
expect_eq "Module enregistré et actif" "1" "$(sql "SELECT active FROM ps_module WHERE name='everblocklight'")"
expect_eq "Tables du module" "4" "$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='prestashop' AND table_name LIKE 'ps\_everblocklight%'")"
for hook in displayHeader actionOutputHTMLBefore actionAdminControllerSetMedia actionEmailAddAfterContent displayHome; do
  expect_eq "Hook $hook" "1" "$(sql "SELECT COUNT(*) FROM ps_hook_module hm JOIN ps_hook h ON h.id_hook=hm.id_hook JOIN ps_module m ON m.id_module=hm.id_module WHERE m.name='everblocklight' AND h.name='$hook' AND hm.id_shop=1")"
done
expect_eq "Onglets admin" "6" "$(sql "SELECT COUNT(*) FROM ps_tab WHERE module='everblocklight'")"
expect_eq "Bloc d'exemple créé désactivé" "0" "$(sql "SELECT active FROM ps_everblocklight WHERE name='exemple' AND id_shop=1")"
expect_eq "Configuration préfixée créée" "1" "$( [ "$(sql "SELECT COUNT(*) FROM ps_configuration WHERE name LIKE 'EVERBLOCKLIGHT\_%'")" -gt 0 ] && echo 1 || echo 0)"

log "Contrôles Symfony (conteneur, routes, commande console)"
if ps_console cache:clear --no-warmup >"$WORK/cache.log" 2>&1 && ps_console cache:warmup >>"$WORK/cache.log" 2>&1; then
  ok "Conteneur Symfony compilé avec les services du module"
else
  cat "$WORK/cache.log"; ko "cache:clear / cache:warmup a échoué"
fi
ROUTES="$(ps_console debug:router 2>/dev/null | grep -c 'admin_everblocklight_' || true)"
expect_eq "Routes admin chargées" "21" "$ROUTES"
if ps_console everblocklight:tools:execute --list >"$WORK/cmd.log" 2>&1; then ok "Commande everblocklight:tools:execute"; else cat "$WORK/cmd.log"; ko "Commande console en erreur"; fi

log "Rendu front d'un bloc et de shortcodes"
if docker exec -i -u www-data "$PS" php < "$MODULE_DIR/tests/install/create_fixtures.php"; then
  ok "Bloc et shortcode enregistrés via les repositories du module (chemin de l'admin)"
else
  ko "Enregistrement d'un bloc / shortcode via le module impossible"
fi
# Appel de chauffe : le premier affichage compile les caches (Smarty, CCC du thème en PS 9)
curl -s -L -o /dev/null "http://localhost:$PS_PORT/" || true
HTTP_CODE="$(curl -s -L -o "$WORK/home.html" -w '%{http_code}' "http://localhost:$PS_PORT/")"
expect_eq "Page d'accueil" "200" "$HTTP_CODE"
grep -q 'id="ci-everblocklight"' "$WORK/home.html" && ok "Bloc rendu sur displayHome" || ko "Bloc absent de la page d'accueil"
grep -q 'class="alert alert-success" role="alert">CI-ALERT-OK' "$WORK/home.html" && ok "Shortcode [alert] rendu" || ko "Shortcode [alert] non rendu"
grep -q 'CI-SHORTCODE-OK' "$WORK/home.html" && ok "Shortcode personnalisé rendu" || ko "Shortcode personnalisé non rendu"
grep -q '\[alert\|\[ci_shortcode\]' "$WORK/home.html" && ko "Shortcode laissé brut dans la page" || ok "Aucun shortcode brut restant"
# Le JS peut être servi seul ou concaténé par le cache CCC du thème (activé par défaut en PS 9) :
# on inspecte donc le contenu des scripts réellement chargés par la page.
JS_FOUND=0
for attempt in 1 2 3; do
  [ "$attempt" -gt 1 ] && { sleep 3; curl -s -L -o "$WORK/home.html" "http://localhost:$PS_PORT/" || true; }
  for src in $(grep -Eo '<script[^>]+src="[^"]+"' "$WORK/home.html" | sed -E 's/.*src="([^"]+)".*/\1/' | grep -E "localhost:$PS_PORT|^/" || true); do
    case "$src" in /*) src="http://localhost:$PS_PORT$src" ;; esac
    JS_CONTENT="$(curl -s "$src" || true)"
    case "$JS_CONTENT" in *everblocklight_modal_link*) JS_FOUND=1; break ;; esac
  done
  [ "$JS_FOUND" -eq 1 ] && break
done
[ "$JS_FOUND" -eq 1 ] && ok "JS front du module chargé" || ko "JS front du module (everblocklight.js) non chargé"
if grep -Eq '(Fatal error|Warning|Notice|Deprecated)</b>:|Uncaught ' "$WORK/home.html"; then
  grep -Eo '(Fatal error|Warning|Notice|Deprecated)</b>:.{0,200}' "$WORK/home.html" | head -5; ko "Erreur PHP affichée en front"
else
  ok "Aucune erreur PHP affichée en front"
fi
ERRLOG="$(docker exec "$PS" sh -c 'grep -hi "everblocklight" /var/www/html/var/logs/*.log 2>/dev/null | grep -iE "error|critical|exception" | tail -5' || true)"
if [ -n "$ERRLOG" ]; then echo "$ERRLOG"; ko "Erreurs du module dans var/logs"; else ok "Aucune erreur du module dans var/logs"; fi

log "Désinstallation du module"
if ps_console prestashop:module uninstall everblocklight; then ok "prestashop:module uninstall"; else ko "prestashop:module uninstall a échoué"; fi
expect_eq "Module retiré" "0" "$(sql "SELECT COUNT(*) FROM ps_module WHERE name='everblocklight'")"
expect_eq "Tables supprimées" "0" "$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='prestashop' AND table_name LIKE 'ps\_everblocklight%'")"
expect_eq "Configuration nettoyée" "0" "$(sql "SELECT COUNT(*) FROM ps_configuration WHERE name LIKE 'EVERBLOCKLIGHT\_%'")"
expect_eq "Onglets admin supprimés" "0" "$(sql "SELECT COUNT(*) FROM ps_tab WHERE module='everblocklight'")"
HTTP_CODE="$(curl -s -L -o /dev/null -w '%{http_code}' "http://localhost:$PS_PORT/")"
expect_eq "Page d'accueil après désinstallation" "200" "$HTTP_CODE"

log "Résultat PrestaShop $PS_VERSION : $FAILURES échec(s)"
[ "$FAILURES" -eq 0 ]
