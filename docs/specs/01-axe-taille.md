# 01 — Axe Taille

Définition source : « la taille **habituelle** des features livrées avec l'IA, pas la plus
grosse jamais faite. S petite ou triviale · M complexité moyenne · L multi-étapes · XL
multi-modules ».

## Signal

`git-activity.json → pull_requests.median_files_changed`, en premier. Repli :
`pull_requests.median_lines_changed` si le premier est absent ou nul. Une médiane, jamais
un maximum, jamais `size_distribution` (qui illustre).

**Pourquoi les fichiers d'abord** : L et XL sont définis comme « multi-étapes » et
« multi-modules », donc structurels ; le nombre de fichiers colle à la définition, les lignes
à la seule volumétrie. Éprouvé le 2026-08-28 : `bohort` a 251,5 lignes (bande L) pour 7
fichiers (bande M) et il est Blue = M. **L'ordre inverse le déclasse.**

## Seuils (`SizeThresholds`)

| Palier | Fichiers (signal) | Lignes (repli) | Origine |
|---|---|---|---|
| S | 1–2 | ≤ 60 | médiane pré-IA ≈ 66 lignes (Brodzinski, 2026) |
| M | 3–8 | 61–210 | médiane post-IA 210 lignes (idem) |
| L | 9–20 | 211–1000 | seuil Salesforce « 20 fichiers et 1000 lignes » où la review casse |
| XL | > 20 | > 1000 | idem |

Ni la grille, ni le framework AIDD, ni le manifeste AIDD ne donnent de bornes : **adaptation
assumée**, pas standard. Validée sur les quatre profils (2, 7, 13, 29 fichiers → S, M, L, XL).

## Correspondance palier → niveau

Le plus haut niveau dont la cellule est satisfaite (« chaque cellule est un minimum ») :

| Palier | Niveau atteint sur l'axe |
|---|---|
| S | Red |
| M | Blue |
| L | Gold (L satisfait « L » de Green et « L-XL » de Copper à Gold) |
| XL | Gold |

Précision par rapport au brief, qui note « L → Copper » dans sa table de calibration : la
valeur du minimum est identique (Copper reste atteint), seule la lecture de l'axe comme
« satisfait jusqu'à Gold » change, et c'est ce qu'exige la règle du minimum de cellule.

White : voir § 05 (filtre d'entrée, jamais décidé par la taille seule).

## Confiance

`pull_requests.total < SampleFloors::MIN_PR_SAMPLE` (5) → verdict en fourchette : plancher =
niveau du palier observé, plafond = Gold, manque chiffré = `5 − total` PR. Voir § 05.

Signal absent : règle commune de la spec 05 § *Signal absent* — **seulement quand le signal et
son repli manquent tous deux** (`median_files_changed` et `median_lines_changed` `null`) ;
`median_files_changed` absent avec des lignes présentes = repli, pas absence. Alors
`Range(White, Gold, 0)` et une note par champ manquant, chacune pointée.

## Preuves rendues

- `git-activity.json › pull_requests.median_files_changed = 13 → L`
- `git-activity.json › pull_requests.total = 71 (échantillon suffisant, plancher 5)`
- si repli : `median_files_changed absent, repli sur median_lines_changed = …`

## Actionnabilité

Mutable, non actionnable : la taille habituelle monte parce que le dispositif tient, elle ne
se décrète pas. Jamais recommandée en premier (§ 06).

## Tests

`perceval` → S/Red · `bohort` → M/Blue · `leodagan` → L · `arthur` → XL · fixture avec
`median_files_changed` absent et lignes = 300 → L · fixture `total = 3` → fourchette. · les
deux médianes absentes → `Range(White, Gold, 0)` avec deux notes pointées · `median_files_changed
= 0` → repli sur les lignes, note citant `= 0`.
