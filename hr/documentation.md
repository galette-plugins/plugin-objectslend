---
title: Dokumentacija
description: Posudba i najam predmeta
---

Dodatak omogućuje:

* upravljanje predmetima (opis, veličina, duljina, cijena i slično)
* grupiranje predmeta po kategorijama,
* praćenje stanja predmeta i dostupnosti na zalihi,
* upravljanje posudbom i/ili najmom predmeta,
* izradu zapisa uplata,
* …

## Instalacija

Najprije preuzmite dodatak:

* [Preuzmite najnoviji dodatak
  ObjectsLend!](https://github.com/galette-plugins/plugin-objectslend/releases/latest)
* [Preuzmite noćnu razvojnu verziju dodatka
  ObjectsLend!](https://github.com/galette-plugins/plugin-objectslend/releases/tag/nightly)

Raspakirajte preuzetu arhivu u Galetteovu mapu `plugins`. Primjerice, na Linuxu
(zamijenite `{url}` i `{version}` odgovarajućim vrijednostima):

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-objectslend-{version}.tar.bz2
```

## Inicijalizacija baze podataka

Dodatak zahtijeva nekoliko tablica u bazi. Pogledajte [sučelje za upravljanje
dodacima
Galettea](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

Gotovo; dodatak ObjectsLend je instaliran :)

## Uporaba dodatka

Nakon instalacije u Galetteovu izborniku pojavljuje se odjeljak `Posudba
predmeta`.

Pri instalaciji se dodaju zadani statusi. Ako vam ne odgovaraju, možete
definirati vlastite.

![Popis statusa predmeta](images/status.png)

Definirajte statuse, kategorije i predmete. Korisnici mogu posuditi predmet uz
navođenje razloga te ga vratiti uz navođenje lokacije.

Administratori i uprava mogu pregledati povijest posudbi na stranici predmeta.

### Postavke

Ponašanje dodatka možete prilagoditi postavkama.

![Postavke dodatka](images/plugin_preferences.png)

![Postavke slika](images/images_preferences.png)

![Postavke prikaza](images/display_preferences.png)

Ovdje određujete mogu li članovi posuđivati predmete, treba li se izraditi zapis
uplate i koje vrste i opisa te prikazuju li se slike u popisu predmeta i kolike
su minijature.

Možete uključiti prikaz fotografija u punoj veličini.

> **Napomena** — Dodano u verziji 0.5.
> 
> Ranije verzije uvijek su smanjivale fotografije i čuvale samo minijaturu. Za
> prikaz u punoj veličini ponovno učitajte fotografije.
