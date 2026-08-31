# Scénario de la vidéo (une minute environ)

Version courte, arbitrée par Jonathan le 2026-08-31 (remplace le flux harnais complet :
`docs/specs/08-harnais.md § 10`, amendé le même jour) : montrer ce que l'outil fait, du
README au verdict, en trois plans. Son libre ; le texte à l'écran doit rester lisible sans
le son. Capture d'écran Windows, terminal plein écran, police lisible.

## Préparation avant d'enregistrer

- Conteneur lancé (`make up`), dépôt à jour (`git pull`).
- Le README du dépôt ouvert sur GitHub dans un onglet.
- Vérifier une fois `make evaluate arthur` hors caméra : la sortie affichée doit être la
  version narrative (tableau « Niveau par axe » visible).

## Les trois plans

- **Plan 1 (~15 s) — le README.** L'onglet GitHub sur le haut du README : le titre, la
  phrase de but, la section « Installation et lancement » (`make up`, puis
  `make evaluate arthur`). Défilement lent jusqu'à la section « Ce que l'outil lit ».
- **Plan 2 (~30 s) — une évaluation réelle.** Le terminal : taper `make evaluate arthur`,
  laisser toute la sortie s'afficher. Deux pauses de lecture : le tableau « Niveau par
  axe », puis « Ce qui manque pour 🥈 Silver ».
- **Plan 3 (~10 s, optionnel) — un profil incomplet.** `make evaluate venec` : l'outil ne
  plante pas, il dit ce qui manque, ce que ça empêche, et comment débloquer.

## Après le tournage

L'issue #12 se ferme quand la vidéo est déposée à l'endroit convenu du rendu.
