# Les sorties de l'outil (`docs/sortie.md`)

Panneau descriptif : ce que `aidd-level` imprime, annoté bloc par bloc. Le format et les
gestes restent en spec — ce panneau montre et pointe. Sources : `docs/specs/06-sortie-et-progression.md` et `docs/specs/05-robustesse.md` (les règles),
`src/Infrastructure/Render/TextRenderer.php` (le code) et `tests/expected/` (le rendu figé,
`arthur.txt` compris — l'instantané d'une exécution réelle, docs/specs/06 § 12 test 14).

## La sortie réelle, annotée bloc par bloc

Sortie de `make evaluate arthur` (profil fourni par le sujet), copiée telle quelle :

```
arthur — développeur indépendant
Niveau AIDD : 🥉 Copper
Échelle des niveaux : ❖ White  🔺 Red  🔹 Blue  🟢 Green  [🥉 Copper]  🥈 Silver  🥇 Gold
Fiabilité : évalué — les quatre axes ont assez de matière pour être tranchés.
Niveau suivant : 🥈 Silver — il faut que Harness et Intervention y montent tous les deux ; le niveau
                 est le plus bas des quatre axes, un axe haut n'en compense pas un bas.


Ce qui a mené là
----------------
  +--------------+--------------------+----------------------------------------------------+
  | Axe          | Niveau             | Constat                                            |
  +--------------+--------------------+----------------------------------------------------+
  | Harness      | 🥉 Copper (bloque) | context engineering acquis : un fichier mémoire    |
  |              |                    | est versionné à la racine du dépôt ; Green et      |
  |              |                    | Copper demandent en plus une règle, un agent ou un |
  |              |                    | hook versionné.                                    |
  | En parallèle | 🥇 Gold            | 4 chantiers de front en médiane, habituellement :  |
  |              |                    | au moins le seuil de 3 de Gold.                    |
  | Intervention | 🥉 Copper (bloque) | médiane 1 corrections après ouverture : aux étapes |
  |              |                    | clés (0 < médiane < 2).                            |
  | Taille       | 🥇 Gold            | 29 fichiers modifiés en médiane : bande XL (> 20), |
  |              |                    | satisfait de Green à Gold.                         |
  +--------------+--------------------+----------------------------------------------------+

  Harness — 🥉 Copper : l'un des deux axes qui bloquent
    context engineering acquis : un fichier mémoire est versionné à la racine du dépôt ; Green et
    Copper demandent en plus une règle, un agent ou un hook versionné.
      git-activity.json — l'activité git du profil, déjà agrégée : PR, commits, branches et fichiers
                          de contexte, sur la période du fichier.
      git-activity.json › context_files.agents_md = true
    behavior acquis : 0 règles, 4 skills, 0 hooks, 2 agents ; au moins un compteur non nul, c'est le
    « behavior » que Green et Copper demandent, Silver et Gold demandent en plus une boucle bornée.
      git-activity.json › context_files = {rules:0, skills:4, hooks:0, agents:2}
    behavior : preuve structurelle trouvée dans repo-context/
      repo-context/ — la copie des fichiers de configuration IA trouvés à la racine du dépôt.
      repo-context/.claude/agents/migration-auditor.md › file = present
    boucles : aucune relance bornée trouvée
      repo-context/ › bounded retry = none found

  Intervention — 🥉 Copper : l'un des deux axes qui bloquent
    médiane 1 corrections après ouverture : aux étapes clés (0 < médiane < 2).
      git-activity.json › pull_requests.median_correction_commits_after_open = 1
    taille de l'échantillon de PR (plancher 5 ; plancher « jamais » 12)
      git-activity.json › pull_requests.total = 154


Déjà acquis pour 🥈 Silver
--------------------------
  En parallèle — 🥇 Gold
    4 chantiers de front en médiane, habituellement : au moins le seuil de 3 de Gold.
      git-activity.json › parallelism.median_concurrent_branches = 4

  Taille — 🥇 Gold
    29 fichiers modifiés en médiane : bande XL (> 20), satisfait de Green à Gold.
      git-activity.json › pull_requests.median_files_changed = 29


Comment monter d'un cran — vers 🥈 Silver
-----------------------------------------
  1. Harness (à faire en premier) — ajouter une relance automatique bornée (N essais visibles) dans
                                    la CI ou un script, sur une commande du projet
     Ce qui le prouvera : repo-context/ › bounded retry
     Aujourd'hui : repo-context/ › bounded retry = none found

  2. Intervention — automatiser la validation (tests, lint, duplication) pour qu'aucune reprise
                    humaine ne soit nécessaire après ouverture
     Ce qui le prouvera : pull_requests.median_correction_commits_after_open


Notes
-----
Écarté du calcul
  · Intervention : merged_without_human_edit_after_open = 46/154 (corrobore, ne décide pas)
    (git-activity.json › pull_requests.merged_without_human_edit_after_open = 46)
  · En parallèle : pic observé : max 7, non retenu
    (git-activity.json › parallelism.max_concurrent_branches = 7)
  · La personne n'a pas répondu au questionnaire déclaratif.
    profile.json — l'identité du profil et la liste des pièces annoncées.
    (profile.json › note = La personne n'a pas répondu au questionnaire déclaratif.)

Qualité, citée sans jugement
  · prérequis qualité, cité sans jugement : duplication = 2.4 %
    sonar-measures.json — les mesures de qualité fournies avec le profil, citées sans jugement.
    (sonar-measures.json › duplicated_lines_density = 2.4)
  · prérequis qualité, cité sans jugement : couverture = 85 %
    (sonar-measures.json › coverage = 85)
```

Lecture bloc par bloc (docs/specs/06-sortie-et-progression.md § 5) :

- **l'en-tête, cinq lignes** — l'identité d'abord (`profile_id — role`) ; le niveau ; l'échelle
  des sept niveaux, icône et nom, le niveau atteint entre crochets ; la fiabilité de l'évaluation (le statut canonique,
  puis ce qu'il veut dire) ; le niveau suivant et sa condition de passage — ici deux axes
  bloquent à la fois, nommés l'un et l'autre, avec la phrase qui rappelle que le niveau est le
  minimum des quatre axes.
- « Ce qui a mené là » — un tableau de synthèse des quatre axes (colonnes Axe / Niveau /
  Constat, `(bloque)` sur ceux qui plafonnent), puis chaque axe qui bloque en détail : toutes ses `Evidence`, chacune avec sa
  phrase et son échelle, puis son pointeur `fichier › champ = valeur` sur sa propre ligne. La
  légende d'une pièce (`git-activity.json`, `repo-context/`…) précède son premier pointeur, une
  seule fois par sortie.
- « Déjà acquis pour X » — les axes qui ne bloquent pas, avec le niveau atteint et l'`Evidence`
  qui l'a décidé ; un axe en fourchette y garderait sa mention `(fourchette)` et sa ligne
  `pour trancher`.
- « Comment monter d'un cran » — un geste par axe bloquant, dans l'ordre d'actionnabilité
  (Harness d'abord) ; le premier porte la mention « à faire en premier » et la ligne
  « Aujourd'hui : » — c'est la « prochaine quête », qui n'a plus de bloc à elle.
- « Notes » — trois familles fixes, chacune une fois : Écarté du calcul (pics non retenus,
  signaux qui corroborent sans décider), Pièces du dossier, Qualité citée sans jugement. Une
  note qui redit un fait déjà rendu plus haut ne sort pas.

## Les trois statuts

- `évalué` — niveau et axe (ou axes) plafonnant certains. La ligne *Fiabilité* le dit en toutes
  lettres : « les quatre axes ont assez de matière pour être tranchés. » Rendu figé dans
  `tests/expected/evaluated.txt`.
- `évalué, confiance basse` — une fourchette est rendue à la place d'un niveau unique, par
  deux chemins distincts : l'échantillon est trop court pour trancher entre deux niveaux (le
  manque se compte en PR), ou le champ qui déciderait l'axe est absent de `git-activity.json`
  — fourchette ouverte jusqu'à White, même quand le compte de PR est largement suffisant
  (fixture `absent-signals`). La ligne *Fiabilité* nomme tous les axes en fourchette. Rendu
  figé dans `tests/expected/low-confidence.txt`.
- `non évaluable` — un prérequis manque, chacun avec son message et sa piste : dossier
  illisible, `profile.json` illisible ou absent, `git-activity.json` illisible ou absent,
  `pull_requests.total` absent, ou `pull_requests.total` sous le plancher (zéro PR sur la
  période). La sortie dit trois choses dans l'ordre : ce qui manque (avec son pointeur), ce
  que ça empêche, et le geste pour débloquer. Rendu figé dans
  `tests/expected/not-assessable.txt`. N'arrête jamais l'évaluation des autres dossiers passés
  en argument.

## Le format de pointeur

Chaque ligne d'explication porte `fichier › champ = valeur` (règle 3 d'`AGENTS.md`) : une
affirmation sans pointeur ne se construit pas, dans le code comme dans le rendu. La phrase
passe toujours avant le pointeur (docs/specs/06 § 2) ; seule la ligne de pointeur peut dépasser
la largeur de repli — elle n'est jamais tronquée.

## Ce que l'outil n'imprime jamais

Pas de sortie `--json`. Pas de jugement de qualité : Sonar est cité, jamais jugé. Pas de
contenu déclaratif interprété : `declaratif.md` n'entre dans aucun calcul, seule sa présence
est notée.
