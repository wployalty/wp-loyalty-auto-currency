#!/bin/bash
echo "WPLoyalty Auto Currency Pack"
current_dir="$PWD"
composer_lock_pack=$current_dir"/composer.lock"

plugin_name="wp-loyalty-auto-currency"
pack_folder=$current_dir"/../compressed_pack"
plugin_compress_folder=$pack_folder"/"$plugin_name

composer_run() {
  # shellcheck disable=SC2164
  cd "$current_dir"
  rm "$composer_lock_pack"
  composer install --no-dev
  composer update --no-dev
  echo "Composer Done"
}
update_ini_file() {
  cd "$current_dir"
  wp i18n make-pot . "i18n/languages/$plugin_name.pot" --slug="$plugin_name" --domain="$plugin_name" --include=$plugin_name".php",/App/ --headers='{"Last-Translator":"Wployalty <support@wployalty.net>","Language-Team":"Wployalty <support@wployalty.net>"}' --allow-root
  echo "Update ini done"
}
copy_folder() {
  if [ -d "$pack_folder" ]; then
    rm -r "$pack_folder"
  fi
  mkdir "$pack_folder"
  mkdir "$plugin_compress_folder"
  move_dir=("App" "Assets" "i18n" "vendor" "readme.txt" $plugin_name".php")
  # shellcheck disable=SC2068
  for dir in ${move_dir[@]}; do
    cp -r "$current_dir/$dir" "$plugin_compress_folder/$dir"
  done
}
zip_folder() {
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
