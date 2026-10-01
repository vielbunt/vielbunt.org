#!/usr/bin/env bash
# Fährt ein frisches WordPress (Playground, läuft komplett in Node) mit dem
# Theme hoch und lädt ein paar Seiten. Schlägt fehl bei PHP-Fehlern, Warnungen
# oder wenn wichtige Bausteine der Startseite fehlen.
# Lokal genauso aufrufbar: THEME_SLUG=... SMOKE_MARKERS="..." bash .github/smoke-test.sh
set -euo pipefail

: "${THEME_SLUG:?THEME_SLUG fehlt}"
: "${SMOKE_MARKERS:=vb-hero vb-quick}"
PORT="${SMOKE_PORT:-9411}"
BASE="http://127.0.0.1:$PORT"
WORK="$(mktemp -d)"
trap 'kill "${PID:-0}" 2>/dev/null || true; rm -rf "$WORK"' EXIT

cat > "$WORK/blueprint.json" <<JSON
{
  "steps": [
    { "step": "defineWpConfigConsts", "consts": { "WP_DEBUG": true, "WP_DEBUG_DISPLAY": true } },
    { "step": "installTheme", "themeData": { "resource": "wordpress.org/themes", "slug": "twentytwentyfive" }, "options": { "activate": false } },
    { "step": "activateTheme", "themeFolderName": "$THEME_SLUG" },
    { "step": "runPHP", "code": "<?php require '/wordpress/wp-load.php'; \$p = wp_insert_post(['post_type'=>'page','post_title'=>'Start','post_status'=>'publish']); update_option('show_on_front','page'); update_option('page_on_front',\$p); wp_insert_post(['post_title'=>date('d.m.', strtotime('+3 days')).': Testtermin','post_content'=>'Hallo','post_status'=>'publish']); wp_insert_post(['post_title'=>'Neuigkeit ohne Datum','post_content'=>'Text','post_status'=>'publish']); wp_insert_post(['post_type'=>'page','post_title'=>'Unterseite','post_content'=>'Inhalt','post_status'=>'publish']); update_option('permalink_structure','/%postname%/'); flush_rewrite_rules();" }
  ]
}
JSON

npx -y @wp-playground/cli@3.1.56 server \
  --port "$PORT" \
  --blueprint "$WORK/blueprint.json" \
  --mount-dir "$PWD" "/wordpress/wp-content/themes/$THEME_SLUG" \
  > "$WORK/server.log" 2>&1 &
PID=$!

for _ in $(seq 1 120); do
  grep -q "Ready!" "$WORK/server.log" && break
  if ! kill -0 "$PID" 2>/dev/null; then cat "$WORK/server.log"; echo "Playground ist abgestürzt"; exit 1; fi
  sleep 2
done
grep -q "Ready!" "$WORK/server.log" || { cat "$WORK/server.log"; echo "Playground kam nicht hoch"; exit 1; }

fail=0
check() {
  local path="$1"; shift
  local code
  code=$(curl -sS -L -o "$WORK/page.html" -w '%{http_code} %{url_effective}' "$BASE$path")
  if [ "${code%% *}" != "200" ]; then echo "FEHLER $path: HTTP $code"; fail=1; return; fi
  if grep -qiE '<b>(Fatal error|Warning|Notice|Deprecated|Parse error)</b>|critical error' "$WORK/page.html"; then
    echo "FEHLER $path: PHP meldet was:"; grep -iE '<b>(Fatal error|Warning|Notice|Deprecated|Parse error)</b>|critical error' "$WORK/page.html" | head -5; fail=1; return
  fi
  for m in "$@"; do
    grep -q "$m" "$WORK/page.html" || { echo "FEHLER $path: '$m' fehlt"; fail=1; }
  done
  echo "ok    $path"
}

# shellcheck disable=SC2086
check "/" $SMOKE_MARKERS
check "/unterseite/"
check "/neuigkeit-ohne-datum/"
check "/?s=test"

code=$(curl -sS -o /dev/null -w '%{http_code}' "$BASE/wp-json/")
[ "$code" = "200" ] && echo "ok    /wp-json/" || { echo "FEHLER /wp-json/: HTTP $code"; fail=1; }

exit "$fail"
