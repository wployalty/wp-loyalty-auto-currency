#!/bin/bash
echo "Multicurrency"
current_dir="$PWD"
plugin_name="wp-loyalty-auto-currency"
pack_folder=$current_dir"/../compressed_pack"
plugin_compress_folder=$pack_folder"/"$plugin_name
composer_run() {
  # shellcheck disable=SC2164
  cd "$current_dir"
  composer install --no-dev
  composer update --no-dev
  echo "Compress Done"
  # shellcheck disable=SC2164
  cd "$current_dir"
}
update_ini_file() {
  # shellcheck disable=SC2164
  cd "$current_dir"
  wp i18n make-pot . "i18n/languages/$plugin_name.pot" --slug="$plugin_name" --domain="$plugin_name" --include=$plugin_name".php",/App/ --headers='{"Last-Translator":"wployalty <support@wployalty.net>","Language-Team":"wployalty <support@wployalty.net>"}' --allow-root
  # shellcheck disable=SC2164
  cd "$current_dir"
  echo "Update ini done"
}
copy_folder() {
  if [ -d "$pack_folder" ]; then
    rm -r "$pack_folder"
  fi
  mkdir "$pack_folder"
  mkdir "$plugin_compress_folder"
  # shellcheck disable=SC2206
  move_dir=("App" "Assets" "i18n" "vendor" "composer.json" "readme.txt" $plugin_name".php")
  # shellcheck disable=SC2068
  for dir in ${move_dir[@]}; do
    cp -r "$current_dir/$dir" "$plugin_compress_folder/$dir"
  done
}
zip_folder() {
  # shellcheck disable=SC2164
  cd "$pack_folder"
  rm "$plugin_name".zip
  zip -r "$plugin_name".zip $plugin_name -q
  zip -d "$plugin_name".zip __MACOSX/\*
  zip -d "$plugin_name".zip \*/.DS_Store
}
echo "Composer Run:"
composer_run
echo "Update ini"
update_ini_file
echo "Copy Folder:"
copy_folder
echo "Zip Folder:"
zip_folder
echo "End"