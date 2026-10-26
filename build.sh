#!/usr/bin/env bash
#
# Сборка и проверки модуля shef.pristine.
#
#   ./build.sh            проверки + архив shef.pristine.zip
#   ./build.sh --check    только проверки
#   ./build.sh --version  напечатать версию модуля (install/version.php)
#   ./build.sh --notes    примечания к выпуску: секция CHANGELOG текущей версии
#
# Файл, не попавший ни в SHIP, ни в KEEP, роняет сборку: новый файл не уедет на
# портал молча. KEEP обязан совпадать с export-ignore в .gitattributes — иначе
# zip из build.sh и пакет Composer соберутся по разным правилам.

set -euo pipefail
cd "$(dirname "$0")"

MODULE_ID='shef.pristine'

SHIP=(
	'CHANGELOG.md'
	'LICENSE'
	'README.md'
	'composer.json'
	'include.php'
	'config/'
	'install/'
	'lang/'
	'lib/'
)

KEEP=(
	'.gitattributes'
	'.github/'
	'.gitignore'
	'CLAUDE.md'
	'build.sh'
	'docs/'
	'examples/'
	'tests/'
)

version() { sed -n "s/.*'VERSION' => '\([^']*\)'.*/\1/p" install/version.php; }

case "${1:-}" in
	--version)
		version
		exit 0
		;;
	--notes)
		# Секция «## <версия> — дата» CHANGELOG до следующей «## ».
		v="$(version)"
		notes="$(awk -v v="$v" '$0 ~ "^## "v"( |$)" {on=1; next} /^## / {on=0} on' CHANGELOG.md)"
		[ -n "$(echo "$notes" | tr -d '[:space:]')" ] || { echo "в CHANGELOG.md нет секции $v" >&2; exit 1; }
		echo "$notes"
		exit 0
		;;
	''|--check) ;;
	*)
		echo "Использование: $0 [--check|--version|--notes]" >&2
		exit 2
		;;
esac

failed=0
fail() { echo "[FAIL] $*"; failed=1; }
ok() { echo "[ OK ] $*"; }

# Файлы репозитория: в git — отслеживаемые, иначе — всё, кроме .git и архива.
files() {
	if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
		git ls-files --cached --others --exclude-standard
	else
		find . -type f ! -path './.git/*' ! -name '*.zip' | sed 's#^\./##'
	fi | LC_ALL=C sort
}

# Каждый файл — ровно в одном списке.
while IFS= read -r f; do
	hit=0
	for p in "${SHIP[@]}" "${KEEP[@]}"; do
		[[ "$f" == "$p" || ( "$p" == */ && "$f" == "$p"* ) ]] && hit=1 && break
	done
	[ "$hit" = 1 ] || fail "файл вне SHIP и KEEP: $f"
done < <(files)
[ "$failed" = 0 ] && ok 'все файлы в SHIP или KEEP'

# .gitattributes: export-ignore ровно у KEEP.
if [ -f .gitattributes ]; then
	want="$(printf '/%s\n' "${KEEP[@]}" | LC_ALL=C sort)"
	have="$(awk '$2 == "export-ignore" {print $1}' .gitattributes | LC_ALL=C sort)"
	[ "$want" = "$have" ] && ok '.gitattributes совпадает с KEEP' || fail ".gitattributes: export-ignore не совпадает с KEEP (нужно: $(echo $want))"
fi

# Версия и секция CHANGELOG.
v="$(version)"
[[ "$v" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] && grep -q "^## $v" CHANGELOG.md && ok "версия $v есть в CHANGELOG.md" || fail "версия «$v» не x.y.z или нет секции в CHANGELOG.md"

# PHP без синтаксических ошибок.
while IFS= read -r f; do
	php -l "$f" >/dev/null 2>&1 || fail "синтаксис: $f"
done < <(files | grep '\.php$')
[ "$failed" = 0 ] && ok 'PHP без синтаксических ошибок'

# Тесты на заглушках.
for t in tests/*_test.php; do
	if php "$t" >/tmp/pristine-test.log 2>&1; then ok "$t"; else cat /tmp/pristine-test.log; fail "$t"; fi
done

[ "$failed" = 0 ] || { echo 'Проверки не прошли.'; exit 1; }
[ "${1:-}" = '--check' ] && { echo 'Готово.'; exit 0; }

stage=$(mktemp -d)
mkdir -p "$stage/$MODULE_ID"
for p in "${SHIP[@]}"; do cp -r "$p" "$stage/$MODULE_ID/"; done
rm -f "$MODULE_ID.zip"
(cd "$stage" && zip -qr - "$MODULE_ID") > "$MODULE_ID.zip"
rm -rf "$stage"
echo "Архив: $MODULE_ID.zip"
