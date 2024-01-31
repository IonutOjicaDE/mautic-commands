#!/bin/bash
cp -f comenzi.php comenzi.php.bak
cp -f comenzi.json comenzi.json.bak
wget https://raw.githubusercontent.com/IonutOjicaDE/mautic-commands/main/comenzi.php
wget https://raw.githubusercontent.com/IonutOjicaDE/mautic-commands/main/comenzi.json
cmp -s comenzi.php comenzi.php.bak
if [ $? -eq 0 ]; then
  rm comenzi.php.bak
fi
cmp -s comenzi.json comenzi.json.bak
if [ $? -eq 0 ]; then
  rm comenzi.json.bak
fi