# frenchRevolutionaryCalendar
traiter les dates du calendrier révolutionnaire dans CollectiveAccess

## conf files & prerequisites / fichiers de configuration et pré-requis

Ce plugin nécessite que les fichiers de parsing des dates pour le français (app/lib/parsers/TimeExpressionParser/fr_FR.lang) soit paramétrer d'une certaine manière.

Vous en trouverez une copie dans le dossier prerequisites.

Pour faciliter l'utilisation de ce plugin, vous pouvez utiliser le fichier datetime.conf disponible dans le dossier prerequisites en remplacement de votre fichier de configuration actuelle. Il récupère un certain nombre d'erreurs de saisie, gère les périodes littérales (quart de siècle, moitié de siècle) et certaines périodes en toutes lettres (époque romaine, François Ier, etc.).