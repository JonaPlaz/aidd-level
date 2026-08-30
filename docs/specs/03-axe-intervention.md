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

## Médiane sur la borne — le verdict tient, sa fragilité est dite

Ajouté le 2026-08-30 (`lancelot`, PR #35). Les médianes de compteurs entiers tombent sur des
entiers et les seuils sont des entiers : un verdict **sur la borne exacte** est le cas courant,
pas l'exception (`lancelot` 3 = borne Red, `bohort` 2 = borne Blue, `arthur` 1 = borne Copper).
Le niveau ne change pas — 3 **est** « sur la majorité » — mais la sortie ne doit pas lire
`lancelot` (marge nulle) du même ton que `perceval` (4, marge d'un point).

Règle : quand la médiane est **égale** à la constante qui ouvre son niveau
(`MEDIAN_MAJORITY_MIN`, `MEDIAN_PARTIAL`, `MEDIAN_KEY_STEPS`), une note, pointée, nomme la
borne et le niveau voisin :

- `Intervention : médiane 3 sur la borne exacte Red/Blue ; en dessous, l'axe serait Blue`
  (`git-activity.json › pull_requests.median_correction_commits_after_open = 3`)

Elle ne dit **pas** combien de PR feraient basculer : la distribution n'est pas fournie
(`pull-requests.json` écarté, ci-dessous). Statut inchangé (`évalué`) : ce n'est ni un
échantillon court ni un signal absent. Médiane 0 n'est pas concernée (Silver est déjà gardé
par le plancher d'échantillon). Les autres axes ne sont pas concernés par ce chantier :
Taille et En parallèle rendent des bandes, pas des crans d'un point.

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
Médiane absente → `Range(White, Silver, 0)` + note pointée « = absent » · bornes exactes :
`total = 12` médiane 0 → Silver, `total = 11` → fourchette, `total = 5` médiane 2 → Blue,
`total = 4` → fourchette · médiane sur la borne : 3, 2, 1 → note « sur la borne exacte »
(`lancelot`, `bohort`, `arthur`) ; 4 et 0 → pas de note ; 2,5 → Blue sans note.
