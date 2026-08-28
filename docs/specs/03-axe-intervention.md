# 03 — Axe Intervention

Définition source : « quand la personne intervient dans le travail de l'IA. Cadrer, c'est
choisir la tâche et dire ce qui est attendu. Monter d'un niveau, c'est reprendre moins ».

## Signal

`git-activity.json → pull_requests.median_correction_commits_after_open`. Il traduit
littéralement la colonne « Ce qu'on observe » : « beaucoup de commits correctifs après
ouverture » (Red), « quelques » (Blue), « presque aucun » (Green/Copper), « sans aucun commit
d'un humain » (Silver).

Le trailer `Co-Authored-By` par commit, prévu avant le 28, est **écarté** : aucun commit
individuel n'est lisible. `commits.ai_coauthored_ratio` sert uniquement de **filtre
White/Red** (§ 05).

## Seuils (`InterventionThresholds`)

| Médiane | Valeur | Niveau |
|---|---|---|
| ≥ 3 | après coup, sur la majorité | Red |
| = 2 | après coup, sur une partie | Blue |
| = 1 | aux étapes clés | Copper (Green et Copper partagent la cellule) |
| = 0, échantillon ≥ `MIN_PR_SAMPLE_ABSENCE` (12) | jamais, une fois la tâche cadrée | **Silver** |
| = 0, échantillon < 12 | fourchette [Copper, Silver], manque chiffré | confiance basse |

Validés sur les quatre profils (4, 2, 0, 1 → Red, Blue, Silver, Copper). ⚠️ Silver n'est
éprouvé par aucun profil fourni : `leodagan` est à 0 mais plafonné ailleurs. **Non vérifié.**

**Gold — « jamais, cadrage compris »** : aucune donnée fournie ne distingue un cadrage humain
d'un cadrage par agent. L'axe **plafonne à Silver** par construction, dit en note : « Gold
sur cet axe demanderait la preuve que le cadrage lui-même est automatisé ; non observable
dans les pièces fournies ». C'est une limite assumée, pas un seuil inventé.

Signal absent (`null`) : règle commune de la spec 05 § *Signal absent* — `Range(White, plafond
de l'axe, 0)` et une note par champ manquant.

## Corroboration, jamais décision

`merged_without_human_edit_after_open` est **non monotone** avec le niveau (3/63, 10/48,
37/71, 46/154 → 4,8 %, 20,8 %, 52 %, 29,9 %). Rendu en note, pondération nulle.
`pull-requests.json` : **écarté** le 2026-08-29 (remarque Codex sur la PR #18). Son champ
`commits` compte tous les commits d'une PR, pas ceux d'après ouverture : `bohort` 7,5 pour une
médiane de 2, `leodagan` 6 pour 0 — les deux champs ne se comparent pas, aucune note n'en
découle.

## Preuves rendues

- `git-activity.json › pull_requests.median_correction_commits_after_open = 2 → après coup, sur une partie`
- `git-activity.json › pull_requests.total = 48 (plancher 5 ; plancher « jamais » 12)`
- note : `merged_without_human_edit_after_open = 10/48 (corrobore, ne décide pas)`

## Actionnabilité

Mutable, **non actionnable** : la reprise est une conséquence. Ne jamais écrire « reprends
moins » ; nommer ce qu'il faut cadrer ou automatiser en amont (spec écrite avec cas limites,
tests avant le code, règle de projet) pour que la reprise devienne inutile. Voir § 06.

## Tests

Quatre profils → valeurs ci-dessus · fixture médiane 0 avec `total = 8` → fourchette
[Copper, Silver], « 4 PR manquantes » · fixture médiane 0 avec `total = 30` → Silver.
