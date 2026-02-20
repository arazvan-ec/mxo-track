#!/usr/bin/env bash
set -euo pipefail

LOCK_FILE="backend/composer.lock"

if [[ ! -f "$LOCK_FILE" ]]; then
  echo "❌ No existe $LOCK_FILE. Ejecuta composer install/update en backend/."
  exit 1
fi

php -r '
$lock=json_decode(file_get_contents("backend/composer.lock"), true, 512, JSON_THROW_ON_ERROR);
$pkgs=array_merge($lock["packages"] ?? [], $lock["packages-dev"] ?? []);
$bad=[];
foreach($pkgs as $p){
  $name=$p["name"] ?? "";
  $version=$p["version"] ?? "";
  if(str_starts_with($name, "symfony/") && preg_match("/^v?8\\./", $version)){
    $bad[]="$name:$version";
  }
}
if($bad){
  fwrite(STDERR, "❌ Política Symfony 7.4 violada. Componentes 8.x detectados:\n - ".implode("\n - ",$bad)."\n");
  exit(1);
}
echo "✅ Política Symfony 7.4 OK: no hay componentes symfony/* en 8.x en composer.lock\n";
';
