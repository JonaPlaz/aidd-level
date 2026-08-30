# Les sorties de l'outil (`docs/sortie.md`)

Panneau descriptif : ce que `aidd-level` imprime, annoté bloc par bloc. Le format et les
gestes restent en spec — ce panneau montre et pointe. Sources : `docs/specs/06-sortie-et-progression.md` et `docs/specs/05-robustesse.md` (les règles),
`src/Infrastructure/Render/TextRenderer.php` (le code) et `tests/expected/` (le rendu figé).

## La sortie réelle, annotée bloc par bloc

Sortie de `make evaluate arthur` (profil fourni par le sujet), copiée telle quelle :

```
❖ 🔺 🔹 🟢 [🥉] 🥈 🥇
axe bloquant : Harness et Intervention (ex æquo)
🥉 Copper — arthur (développeur indépendant)
Niveau atteint : Copper · niveau visé : Silver

Ce qui a mené là — l'axe qui plafonne : Harness et Intervention (ex æquo)
  Harness : context engineering : fichier mémoire présent
    git-activity.json › context_files.agents_md = true
    git-activity.json › context_files = {rules:0, skills:4, hooks:0, agents:2}
    repo-context/.claude/agents/migration-auditor.md › file = present
  Intervention : aux étapes clés
    git-activity.json › pull_requests.median_correction_commits_after_open = 1
    git-activity.json › pull_requests.total = 154

Acquis pour Silver
  Taille : XL → satisfait de Green à Gold
    git-activity.json › pull_requests.median_files_changed = 29
  En parallèle : au moins trois chantiers de front, habituellement
    git-activity.json › parallelism.median_concurrent_branches = 4

Comment monter d'un cran — vers Silver
  1. Harness : ajouter une relance automatique bornée (N essais visibles) dans la CI ou un script,
               sur une commande du projet
  2. Intervention : automatiser la validation (tests, lint, duplication) pour qu'aucune reprise
                    humaine ne soit nécessaire après ouverture

Prochaine quête
  Harness : ajouter une relance automatique bornée (N essais visibles) dans la CI ou un script, sur
            une commande du projet.
  champ à faire bouger : repo-context/ › bounded retry
  preuve actuelle : git-activity.json › context_files.agents_md = true

Notes
  · Taille : échantillon suffisant (plancher 5) : pull_requests.total = 154.
    (git-activity.json › pull_requests.total = 154)
  · Harness : boucles : aucune relance bornée trouvée
    (repo-context/ › bounded retry = none found)
  · Intervention : Gold sur cet axe demanderait la preuve que le cadrage lui-même est automatisé ;
    non observable dans les pièces fournies.
    (git-activity.json › pull_requests.median_correction_commits_after_open = 1)
  · Intervention : merged_without_human_edit_after_open = 46/154 (corrobore, ne décide pas)
    (git-activity.json › pull_requests.merged_without_human_edit_after_open = 46)
  · En parallèle : pic observé : max 7, non retenu
    (git-activity.json › parallelism.max_concurrent_branches = 7)
  · prérequis qualité, cité sans jugement : duplication = 2.4 %
    (sonar-measures.json › duplicated_lines_density = 2.4)
  · prérequis qualité, cité sans jugement : couverture = 85 %
    (sonar-measures.json › coverage = 85)
  · La personne n'a pas répondu au questionnaire déclaratif.
    (profile.json › note = La personne n'a pas répondu au questionnaire déclaratif.)
```

Lecture bloc par bloc :

- les quatre premières lignes — la frise des sept niveaux (le niveau atteint entre crochets),
  l'axe qui plafonne, l'identité et le niveau atteint, puis le niveau visé au cran suivant.
- « Ce qui a mené là » — un fait par ligne pour chaque axe plafonnant, avec son pointeur.
- « Acquis » — les axes qui satisfont déjà le niveau visé, sans plus de détail que la cellule
  atteinte.
- « Comment monter d'un cran » — le ou les gestes, ordonnés par actionnabilité.
- « Prochaine quête » — le geste le plus actionnable, isolé et reformulé pour l'action.
- « Notes » — ce qui corrobore sans jamais décider : pics écartés, prérequis qualité,
  déclaratif.

## Les trois statuts

- `évalué` — niveau et axe plafonnant certains ; rendu figé dans
  `tests/expected/evaluated.txt`.
- `évalué, confiance basse` — une fourchette est rendue à la place d'un niveau unique, par
  deux chemins distincts : l'échantillon est trop court pour trancher entre deux niveaux (le
  manque se compte en PR), ou le champ qui déciderait l'axe est absent de `git-activity.json`
  — fourchette ouverte jusqu'à White, même quand le compte de PR est largement suffisant
  (fixture `absent-signals`). Rendu figé dans `tests/expected/low-confidence.txt`.
- `non évaluable` — un prérequis manque, chacun avec son message et sa piste : dossier
  illisible, `profile.json` illisible ou absent, `git-activity.json` illisible ou absent,
  `pull_requests.total` absent, ou `pull_requests.total` sous le plancher (zéro PR sur la
  période). Rendu figé dans `tests/expected/not-assessable.txt`. N'arrête jamais l'évaluation
  des autres dossiers passés en argument.

## Le format de pointeur

Chaque ligne d'explication porte `fichier › champ = valeur` (règle 3 d'`AGENTS.md`) : une
affirmation sans pointeur ne se construit pas, dans le code comme dans le rendu.

## Ce que l'outil n'imprime jamais

Pas de sortie `--json`. Pas de jugement de qualité : Sonar est cité, jamais jugé. Pas de
contenu déclaratif interprété : `declaratif.md` n'entre dans aucun calcul, seule sa présence
est notée.
