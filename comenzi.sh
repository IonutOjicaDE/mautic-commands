#!/bin/bash
# Actualizarea scriptului comenzi.php

# Numele scriptului cu extensie si calea absoluta
path_scriptName_ext=$(readlink -f "$0")
# Calea absoluta a scriptului, terminata cu "/"
path_script=$(dirname "$path_scriptName_ext")/
# Numele scriptului cu extensie, dar fara cale
scriptName_ext=$(basename "$path_scriptName_ext")
# Numele scriptului fara extensie
scriptName="${scriptName_ext%.sh}"

# Verifica daca este solicitata operatiunea de undo
case "$1" in
  undo|revert|restaureaza|reface)
    echo "$(date +%H:%M:%S) Se efectueaza undo..."

    # Verifica si inlocuieste fisierul .sh daca exista backup
    if [ -f "${path_scriptName_ext}.bak" ]; then
      mv "${path_scriptName_ext}.bak" "${path_scriptName_ext}"
      echo "Scriptul bash a fost restaurat la versiunea anterioara."
    else
      echo "Nu exista backup pentru scriptul bash."
    fi

    # Verifica si inlocuieste fisierul .php daca exista backup
    if [ -f "${path_script}${scriptName}.php.bak" ]; then
      mv "${path_script}${scriptName}.php.bak" "${path_script}${scriptName}.php"
      echo "Scriptul PHP a fost restaurat la versiunea anterioara."
    else
      echo "Nu exista backup pentru scriptul PHP."
    fi
  ;;
  *)
    # Continua cu logica de actualizare daca nu este cerut undo

    echo "$(date +%H:%M:%S) Descarc versiunile actuale"
    wget "https://raw.githubusercontent.com/IonutOjicaDE/mautic-commands/main/$scriptName_ext?$(date +%s)" -O "${path_scriptName_ext}.new"
    wget "https://raw.githubusercontent.com/IonutOjicaDE/mautic-commands/main/${scriptName}.php?$(date +%s)" -O "${path_script}${scriptName}.php.new"

    # Verifica daca fisierul .sh este diferit si actualizeaza
    if ! cmp -s "${path_scriptName_ext}.new" "${path_scriptName_ext}"; then
      mv "${path_scriptName_ext}" "${path_scriptName_ext}.bak"
      mv "${path_scriptName_ext}.new" "${path_scriptName_ext}"
      echo "Versiunea noua de script bash a fost implementata."
    else
      echo "Ai deja ultima versiune de script bash implementata."
    fi

    # Verifica daca fisierul .php este diferit si actualizeaza
    if ! cmp -s "${path_script}${scriptName}.php.new" "${path_script}${scriptName}.php"; then
      mv "${path_script}${scriptName}.php" "${path_script}${scriptName}.php.bak"
      mv "${path_script}${scriptName}.php.new" "${path_script}${scriptName}.php"
      echo "Versiunea noua de script php a fost implementata."
    else
      echo "Ai deja ultima versiune de script php implementata."
    fi
  ;;
esac
