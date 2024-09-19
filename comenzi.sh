#!/bin/bash
# Update of commands.php

# Script name with extension and absolute path
path_scriptName_ext=$(readlink -f "$0")
# The absolute path of the script, terminated with "/"
path_script=$(dirname "$path_scriptName_ext")/
# Script name with extension, but no path
scriptName_ext=$(basename "$path_scriptName_ext")
# Script name without extension
scriptName="${scriptName_ext%.sh}"

# Check if the undo operation is requested
case "$1" in
  undo|revert|restaureaza|reface)
    echo "$(date +%H:%M:%S) Undo is performed..."

    # Check and replace the .sh file if there is a backup
    if [ -f "${path_scriptName_ext}.bak" ]; then
      mv "${path_scriptName_ext}.bak" "${path_scriptName_ext}"
      echo "The bash script has been restored to the previous version."
    else
      echo "There is no backup for the bash script."
    fi

    # Check and replace the .php file if there is a backup
    if [ -f "${path_script}${scriptName}.php.bak" ]; then
      mv "${path_script}${scriptName}.php.bak" "${path_script}${scriptName}.php"
      echo "The PHP script has been restored to the previous version."
    else
      echo "There is no backup for the PHP script."
    fi
  ;;
  *)
    # Continue with update logic if no undo is requested

    echo "$(date +%H:%M:%S) Download current versions"
    wget "https://raw.githubusercontent.com/IonutOjicaDE/mautic-commands/main/$scriptName_ext?$(date +%s)" -O "${path_scriptName_ext}.new"
    wget "https://raw.githubusercontent.com/IonutOjicaDE/mautic-commands/main/${scriptName}.php?$(date +%s)" -O "${path_script}${scriptName}.php.new"

    # Check if the .sh file is different and update it
    if ! cmp -s "${path_scriptName_ext}.new" "${path_scriptName_ext}"; then
      mv "${path_scriptName_ext}" "${path_scriptName_ext}.bak"
      mv "${path_scriptName_ext}.new" "${path_scriptName_ext}"
      echo "The new version of the bash script has been implemented."
    else
      echo "You already have the latest version of the bash script implemented."
    fi

    # Check if the .php file is different and update it
    if ! cmp -s "${path_script}${scriptName}.php.new" "${path_script}${scriptName}.php"; then
      mv "${path_script}${scriptName}.php" "${path_script}${scriptName}.php.bak"
      mv "${path_script}${scriptName}.php.new" "${path_script}${scriptName}.php"
      echo "The new version of the php script has been implemented."
    else
      echo "You already have the latest version of the php script implemented."
    fi
  ;;
esac
