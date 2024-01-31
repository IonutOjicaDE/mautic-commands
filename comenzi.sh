#!/bin/bash
full_script_name_ext=$(readlink -f "$0")
script_dir=$(dirname "$full_script_name_ext")/
script_name_ext=$(basename "$full_script_name_ext")
script_name="${script_name_ext%.sh}"

mv "$full_script_name_ext" "${full_script_name_ext}.bak"
mv "${script_dir}${script_name}.php" "${script_dir}${script_name}.php.bak"

wget "https://raw.githubusercontent.com/IonutOjicaDE/mautic-commands/main/$script_name_ext" -O "$script_name_ext"
wget "https://raw.githubusercontent.com/IonutOjicaDE/mautic-commands/main/${script_name}.php" -O "${script_dir}${script_name}.php"

cmp -s "$full_script_name_ext" "${full_script_name_ext}.bak" && rm "${full_script_name_ext}.bak"
cmp -s "${script_dir}${script_name}.php" "${script_dir}${script_name}.php.bak" && rm "${script_dir}${script_name}.php.bak"
