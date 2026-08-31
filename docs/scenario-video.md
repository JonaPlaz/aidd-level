# Scénario de la vidéo (2 minutes maximum)

Vidéo de démonstration d'`aidd-level`, tournée par Jonathan. Ce document fixe le minutage et
les gestes, pas le texte exact affiché par l'outil : la sortie citée ci-dessous bouge encore
(chantier 75 en cours, réordonne l'en-tête en identité/niveau/échelle et met la synthèse en
tableau). Le scénario décrit ce qu'on montre, pas une capture figée.

## Préparation avant d'enregistrer

- Conteneur lancé (`make up`).
- Terminal plein écran, police lisible.
- Dépôt propre, aucune commande fantôme dans l'historique du shell.

## Minutage

- **0:00–0:15 — Le but.** Face caméra ou en voix off : « aidd-level évalue le niveau
  AI-Driven Development d'un développeur sur une grille de sept niveaux, White à Gold. On lui
  donne un dossier de mesures ; il rend le niveau, les preuves, et quoi faire pour monter. »
- **0:15–0:30 — Premier profil.** Taper `make evaluate arthur`. Laisser la sortie s'afficher
  en entier, sans commentaire par-dessus.
- **0:30–1:10 — Lecture de la sortie**, dans l'ordre où elle apparaît à l'écran :
  - l'identité, puis le niveau atteint (Copper) ;
  - l'échelle des sept niveaux ;
  - la synthèse par axe : deux axes bloquent (Harness, Intervention), deux sont déjà au
    niveau Gold ;
  - montrer une preuve au curseur : chaque affirmation cite `fichier › champ = valeur`, tout
    est vérifiable ;
  - finir sur la section « comment monter d'un cran » : le premier geste, marqué comme
    prioritaire.
- **1:10–1:30 — Profil incomplet.** Taper `make evaluate venec`. Commenter : profil
  incomplet, l'outil ne plante pas — il dit ce qui manque, ce que ça empêche, et comment
  débloquer.
- **1:30–1:50 — Calibration.** Taper `make demo`. Commenter : « les quatre profils fournis
  tombent exactement sur les niveaux attribués par le sujet — quatre sur quatre, avec des
  seuils posés avant d'avoir lu leurs données » (`docs/calibration.md`).
- **1:50–2:00 — Clôture.** « Aucun appel à un modèle à l'exécution : des seuils nommés, des
  preuves pointées, du texte. La méthode complète est dans `docs/methode.md`. »
