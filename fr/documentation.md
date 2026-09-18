---
title: Documentation
description: Prêt et location d'objets
---

Ce plugin fournit :

* gestion d'objets (description, taille, poids, prix, ...)
* regroupement d'objets par catégories,
* gestion des statuts des objets, ainsi que leur présence ou non en stock,
* gestion des prêts et/ou location d'objets,
* génération de contributions,
* ...

## Installation

Tout d'abord, téléchargez le plugin :

* [Obtenir le dernier plugin ObjectsLend
  !](https://github.com/galette-plugins/plugin-objectslend/releases/latest)
* [Obtenir la nightly du plugin ObjectysLend
  !](https://github.com/galette-plugins/plugin-objectslend/releases/tag/nightly)

Extrayez l'archive téléchargée dans le dossier `plugins` de Galette. Par
exemple, sous linux (en remplaçant `{url}` et `{version}` par les valeurs
adéquates) :

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-objectslend-{version}.tar.bz2
```

## Initialisation de la base de données

Pour fonctionner, ce plugin requiert des tables dans la base de données.
Référez-vous [à l'interface de gestion des plugins de
Galette](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

Et c'est terminé, le plugin ObjectsLend est installé :)

## Utilisation du plugin

Une fois le plugin installé, un groupe `Prêt d'objets` est ajouté au menu de
Galette.

Des statuts par défaut sont fournis à l'installation, mais ils pourraient ne pas
convenir à vos besoins, vous pourrez bien sûr définir les vôtres.

![La liste des statuts d'objet](images/status.png)

Définissez des statuts, créez des catégories et des objets ; les utilisateurs
pourront emprunter des objets avec une raison, puis pourront les rendre avec le
bouton retour et une localisation.

Un historique des prêts est fourni pour les administrateurs et membres du bureau
depuis la page d'un objet.

### Préférences

Plusieurs préférences vous permettent de modifier le comportement du plugin.

![Les préférences du plugin](images/plugin_preferences.png)

![Les préférences des images](images/images_preferences.png)

![Les préférences d'affichage](images/display_preferences.png)

Depuis cet écran, vous pourrez définir si les adhérents peuvent emprunter des
objets ou non, si cela doit donner lieu à une nouvelle contribution (ainsi que
son type et une description), si l'image doit être affichée dans la liste des
objets et la taille des miniatures.

Il est possible d'activer l'affichage des photos en pleine taille.

> **Note** — Ajouté dans la version 0.5.
> 
> Les photos envoyées avec une version antérieure du plugin étaient
> systématiquement retaillées, et seule la miniature était stockée. Si vous
> souhaitez utiliser l'affichage en pleine taille, vous devrez envoyer de
> nouveau les photos.
