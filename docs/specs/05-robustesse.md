# 05 — Robustesse

Critères couverts : « le bon niveau ? — même quand il manque des données » (SUJET.md) et « ne
plante pas sur un profil incomplet, et il assume quand il n'est pas sûr » (README). La source
autorise explicitement le refus motivé : « exiger des informations minimales sans lesquelles
tu refuses de te prononcer, en le disant clairement ».

Deux mécanismes séparés, jamais fusionnés, parce que la piste qu'ils donnent diffère :

| | Gate (avant) | Gradient (après, par axe) |
|---|---|---|
| Répond à | peut-on lire quelque chose ? | assez de matière pour trancher cet axe ? |
| Nature | binaire, global, bloquant | graduel, indépendant par axe |
| Piste si échec | technique — fournir la pièce | produit — plus d'historique |

## Gate — prérequis, dans l'ordre, chacun bloquant

1. le chemin existe et est un dossier lisible ;
2. `profile.json` présent et JSON valide ;
3. `git-activity.json` présent et JSON valide — **colonne vertébrale, aucun axe sans lui** ;
4. `git-activity.json › pull_requests.total ≥ 1`.

Réécrit le 2026-08-28 : la version antérieure supposait un dépôt git. Un champ manquant
**à l'intérieur** de `git-activity.json` n'est pas un échec de gate : il redescend l'axe
concerné en confiance basse (ci-dessous), ou le déclare non observable.

## Filtre White

`commits.ai_coauthored_ratio = 0` **et** aucun compteur de `context_files` → White sur les
quatre axes, sans calcul. `perceval` à 0,04 n'est pas White.

## Trois statuts de sortie

- **`non évaluable`** — gate cassé. Sortie : le prérequis manquant nommé, ce qui a été lu quand
  même (`profile.json` si lisible), la piste pour débloquer. Jamais un vide ni une exception.
- **`évalué, confiance basse`** — gate passé, échantillon insuffisant sur au moins un axe.
  Sortie : une **fourchette** par axe concerné [plancher confirmé, plafond observé non
  confirmé] et le manque chiffré ; le niveau global est le minimum des planchers, et son
  plafond est le minimum des plafonds.
- **`évalué`** — échantillon suffisant partout. Niveau, axe qui plafonne, geste.

Un profil qui échoue au gate n'arrête pas l'évaluation des autres profils du lot.

## Planchers d'échantillon (`SampleFloors`)

| Constante | Valeur | Porte sur | Origine |
|---|---|---|---|
| `MIN_PR_SAMPLE` | 5 | Taille ; Intervention sauf « jamais » | adaptation assumée |
| `MIN_PR_SAMPLE_ABSENCE` | 12 | Intervention « jamais » (Silver) — seule affirmation d'absence, un contre-exemple la réfute | adaptation assumée |
| `PARALLELISM_MIN_PR` | 5 | En parallèle | adaptation assumée |
| — | preuve, pas volume | Harness | grille (« ce qui est en place ») |

Aucune n'est sourcée. Les quatre profils fournis ont 48 à 154 PR : **le comportement en
confiance basse n'est validé par aucun d'eux** — les fixtures maison le couvrent (§ 00, § 8
et `fixtures/`). L'unité est la PR, jamais le commit.

## Cohérence annoncé / présent

`profile.json › available` liste les pièces annoncées. Comparé au dossier réel, dans les deux
sens : annoncée absente → note « pièce annoncée, absente » ; présente non annoncée → note.
Signal gratuit, jamais bloquant.

## Sonar — prérequis, hors calcul

« La qualité du code n'est pas un axe : c'est le prérequis. » Vérifié : en faire un plafond ne
changerait aucun des quatre verdicts. Rendu en **note de prérequis** quand
`duplicated_lines_density` ou `coverage` sont présents (utile sur `perceval` : 18,4 % de
duplication, 37 % de couverture). Aucun seuil : les valeurs sont citées, pas jugées.

## Déclaratif — écart, hors calcul

`declaratif.md` n'entre dans aucun calcul. Quand il est présent, l'outil cite l'écart entre
ce qui est dit et ce qui est mesuré, pour adresser le plan à quelqu'un qui se croit ailleurs :
`perceval` se dit « plutôt avancé », « plusieurs features complètes quasi entièrement
générées », pour un ratio de 0,04 ; `bohort` dit « quelques règles dans un dossier dédié »
pour `rules_count = 0`. Le fichier est du texte libre : l'outil **ne l'analyse pas**, il le
mentionne comme pièce présente et rappelle qu'elle est « non vérifiée » (mention de la source
elle-même). L'écart cité est calculé sur les seuls champs mesurés ; les citations du texte
sont réservées au README et à la méthode.

## Tests

Dossier inexistant · dossier vide · `profile.json` corrompu · `git-activity.json` absent ·
`total = 0` → quatre sorties `non évaluable`, distinctes, sans exception · `available`
incohérent → note · chaque fixture de confiance basse des § 01, 03, 04 · lot de trois
dossiers dont un cassé → deux évalués, un non évaluable.
