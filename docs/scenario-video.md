# Scénario de la vidéo (2 minutes maximum)

Vidéo muette, lisible sans le son : texte à l'écran, pas de voix
(`docs/specs/08-harnais.md § 10`). Elle montre le harnais du projet de bout en bout — une
issue, le skill, la PR, la revue Codex, le merge automatique, puis l'évaluation du dépôt
lui-même — pas une démonstration de commande isolée. Ce document fixe le minutage et les
gestes, pas le texte affiché par l'outil : la sortie de l'évaluation bouge encore (chantier 75
en cours sur le rendu), elle n'est pas recopiée ici.

## Préparation avant d'enregistrer

- Conteneur lancé (`make up`).
- Une issue prête, encore non traitée, visible sur GitHub.
- Terminal plein écran, police lisible.
- Logiciel d'incrustation de texte prêt (la vidéo est muette, tout passe par le texte à
  l'écran).
- Dépôt propre, aucune commande fantôme dans l'historique du shell.

## Minutage

- **0:00–0:15 — Issue étiquetée.** Écran GitHub sur l'issue choisie. Texte à l'écran :
  « aidd-level évalue le niveau AI-Driven Development d'un développeur, du dossier de mesures
  au verdict — sans appel à un modèle à l'exécution. » Zoomer sur le label posé sur l'issue.
- **0:15–0:35 — Skill lancé.** Terminal : lancer `/feature <n°>` sur cette issue. Texte à
  l'écran : « le skill du dépôt enchaîne implémentation, PR et revue ; un seul point d'arrêt
  humain, une spec nouvelle à valider. »
- **0:35–0:55 — PR ouverte, revue Codex.** Écran GitHub sur la PR fraîchement ouverte, label
  `to-review`. Couper au moment où la revue Codex apparaît (`+1`, ou commentaires en ligne).
  Texte à l'écran : « une revue Codex à l'ouverture ; chaque remarque reçoit une réponse
  tracée. »
- **0:55–1:15 — Merge automatique.** Écran GitHub sur le merge : `mergedAt` qui se remplit, la
  branche supprimée. Texte à l'écran : « jamais de merge synchrone ; le merge attend le vert
  de la CI. »
- **1:15–1:45 — Le dépôt s'évalue lui-même.** Terminal : lancer l'évaluation de
  `profiles/self` sur le dépôt qui vient de produire ce commit. Laisser la sortie s'afficher
  en entier, sans coupure, assez longtemps pour être lisible à l'arrêt sur image. Texte à
  l'écran : « le projet s'évalue avec son propre outil, sur ses propres commits. »
- **1:45–2:00 — Clôture.** Texte à l'écran : « aucun appel à un modèle à l'exécution : des
  seuils nommés, des preuves pointées, du texte. Méthode complète dans `docs/methode.md`. »
