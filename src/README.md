# L'architecture du projet (`src/`)

Panneau descriptif : l'architecture hexagonale, ses seuils et son point d'extension. Les
décisions de scoring vivent dans les specs, pas ici. Sources :
`docs/specs/00-vue-ensemble.md` § 4, `docs/specs/01-axe-taille.md` à
`docs/specs/04-axe-parallele.md`, `docs/specs/05-robustesse.md`,
`docs/specs/06-sortie-et-progression.md`.

## Trois couches, un seul sens

```
Infrastructure  →  Application  →  Domain
```

`src/Domain/` n'importe jamais rien des deux autres couches — le hook `guard-layers` refuse
tout `use` qui l'enfreindrait (règle 1 d'`AGENTS.md`).

- `src/Domain/` — le calcul pur : types, évaluateurs par axe, règle du minimum.
- `src/Application/` — le use case `EvaluateProfile` et son handler.
- `src/Infrastructure/` — lecture du dossier de profil, commande console, rendu texte.

## Où vivent les seuils

Deux emplacements, et deux seulement : `src/Domain/Threshold/` et
`src/Domain/Axis/Harness/LoopThresholds.php`. Une constante nommée par seuil, son origine en
commentaire juste à côté. Aucune valeur n'est reproduite dans ce panneau : elle vit dans le
code et dans la spec qui la justifie.

## Le point d'extension

`AxisEvaluator` (`src/Domain/AxisEvaluator.php`) — un évaluateur par axe. Ajouter un axe est
une nouvelle classe qui l'implémente ; la règle du minimum, portée par `LevelRule`, ne bouge
pas.

## Le chemin d'un appel

`bin/aidd-level` → `ApplicationFactory` → `EvaluateProfile` / `EvaluateProfileHandler` → les
évaluateurs d'axe → `LevelRule` → `TextRenderer`.

## Où sont les tests qui figent les décisions

`tests/` — un test par décision de scoring. `tests/Calibration/` — les niveaux attribués par
les organisateurs sur les profils fournis. `tests/expected/` — le rendu figé par statut.
`fixtures/` — les dossiers de test qui couvrent ce que les six profils du dépôt ne prouvent
pas.
