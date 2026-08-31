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

Niveau par axe
  +--------------+---------------------------+--------------------------------------------+
  | Axe          | Niveau                    | Constat                                    |
  +--------------+---------------------------+--------------------------------------------+
  | Harness      | 🥉 Copper ← niveau global | context engineering acquis                 |
  | En parallèle | 🥇 Gold                   | d'habitude 4 chantiers menés en même temps |
  |              |                           | (médiane)                                  |
  | Intervention | 🥉 Copper ← niveau global | d'habitude 1 corrections après l'ouverture |
  |              |                           | d'une PR (médiane)                         |
  | Taille       | 🥇 Gold                   | ses PR touchent d'habitude 29 fichiers     |
  |              |                           | (médiane)                                  |
  +--------------+---------------------------+--------------------------------------------+

Niveau suivant : 🥈 Silver — il faut que Harness et Intervention y montent tous les deux — c'est
                 l'axe le plus bas qui donne le niveau attribué.


Déjà acquis pour 🥈 Silver
--------------------------
  En parallèle — 🥇 Gold
    d'habitude 4 chantiers menés en même temps (médiane) : au moins le seuil de 3 de Gold.
      git-activity.json — l'activité git du profil, déjà agrégée : PR, commits, branches et fichiers
                          de contexte, sur la période du fichier.
      vérifier dans : git-activity.json › parallelism.median_concurrent_branches = 4

  Taille — 🥇 Gold
    ses PR touchent d'habitude 29 fichiers (médiane) : taille XL (> 20), satisfait de Green à Gold.
      vérifier dans : git-activity.json › pull_requests.median_files_changed = 29


Ce qui manque pour 🥈 Silver
----------------------------
  1. Harness (à faire en premier) — ajouter une relance automatique bornée (N essais visibles) dans
                                    la CI ou un script, sur une commande du projet
     Ce qui le prouvera : repo-context/ › bounded retry
     Aujourd'hui : repo-context/ › bounded retry = none found

  2. Intervention — automatiser la validation (tests, lint, duplication) pour qu'aucune reprise
                    humaine ne soit nécessaire après ouverture
     Ce qui le prouvera : pull_requests.median_correction_commits_after_open


Les preuves des axes qui limitent
---------------------------------
  Harness — 🥉 Copper : l'un des deux axes qui limitent le niveau
    context engineering acquis : un fichier mémoire est versionné à la racine du dépôt ; Green et
    Copper demandent en plus une règle, un agent ou un hook versionné.
      vérifier dans : git-activity.json › context_files.agents_md = true
    behavior acquis : 0 règles, 4 skills, 0 hooks, 2 agents ; au moins un compteur non nul, c'est le
    « behavior » que Green et Copper demandent, Silver et Gold demandent en plus une boucle bornée.
      vérifier dans : git-activity.json › context_files = {rules:0, skills:4, hooks:0, agents:2}
    behavior : preuve structurelle trouvée dans repo-context/
      repo-context/ — la copie des fichiers de configuration IA trouvés à la racine du dépôt.
      vérifier dans : repo-context/.claude/agents/migration-auditor.md › file = present
    boucles : aucune relance bornée trouvée
      vérifier dans : repo-context/ › bounded retry = none found

  Intervention — 🥉 Copper : l'un des deux axes qui limitent le niveau
    d'habitude 1 corrections après l'ouverture d'une PR (médiane) : aux étapes clés (0 < médiane <
    2).
      vérifier dans : git-activity.json › pull_requests.median_correction_commits_after_open = 1
    taille de l'échantillon de PR (plancher 5 ; plancher « jamais » 12)
      vérifier dans : git-activity.json › pull_requests.total = 154
```

Lecture bloc par bloc (docs/specs/06-sortie-et-progression.md § 5) :

- **l'en-tête, quatre lignes** — l'identité d'abord (`profile_id — role`) ; le niveau ; l'échelle
  des sept niveaux, icône et nom, le niveau atteint entre crochets ; la fiabilité de l'évaluation (le statut canonique,
  puis ce qu'il veut dire) ; puis, après le tableau, la ligne « Niveau suivant » et sa condition de passage — ici deux
  axes limitent à la fois, nommés l'un et l'autre : c'est l'axe le plus bas qui donne le
  niveau attribué.
- le **tableau de synthèse** des quatre axes (colonnes Axe / Niveau / Constat, `← niveau
  global` sur la rangée dont le niveau est le niveau final), juste sous l'en-tête ;
- « Ce qui limite le niveau » — puis chaque axe qui bloque en détail : toutes ses `Evidence`, chacune avec sa
  phrase et son échelle, puis son pointeur `fichier › champ = valeur` sur sa propre ligne. La
  légende d'une pièce (`git-activity.json`, `repo-context/`…) précède son premier pointeur, une
  seule fois par sortie.
- « Déjà acquis pour X » — rendu avant ce qui limite : les axes qui ne bloquent pas, avec le niveau atteint et l'`Evidence`
  qui l'a décidé ; un axe en fourchette y garderait sa mention `(fourchette)` et sa ligne
  `pour trancher`.
- « Ce qui manque pour X » — un geste par axe qui limite, dans l'ordre d'actionnabilité
  (Harness d'abord) ; le premier porte la mention « à faire en premier » et la ligne
  « Aujourd'hui : ». Les notes ne sortent plus sur un profil évalué (2026-08-31) : elles ne
  disaient rien que les blocs pointés ne disent déjà.

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

Pas de sortie `--json`. Pas de jugement de qualité : Sonar n'entre jamais dans le niveau (et n'apparaît plus sur un
profil évalué). Pas de
contenu déclaratif interprété : `declaratif.md` n'entre dans aucun calcul, seule sa présence
est notée.
