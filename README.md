# Presentation
Utility that allows you to run different Mautic usefull commands without `ssh`.

The utility `commands.php` will be accessible at https://mautic-subdomain/commands.php . You enter the password you received as `MAUTIC_COMMANDS_PASSWORD` and you can choose the command you want to execute. You have also the possibility to personalize the command to execute.

This utility is open sourced on github and can be updated/downgraded with a click from inside the utility.

Brute force atacks are not working with this utility, because after each wrong password entered, the blocking time is doubled. _This functionality would be great to be included in Mautic and Adminer._

As an inspiration I used the script made public here https://github.com/virgilwashere/mautic-cron-commands

If my work has been useful to you, do not hesitate to offer me a strawberry milk 😃

[!["Buy Me A Coffee"](https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png)](https://www.buymeacoffee.com/ionutojica)
