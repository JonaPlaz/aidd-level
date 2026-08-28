# 06 — Sortie et progression

Sortie minimale imposée par le sujet : « un niveau · ce qui a mené là · comment progresser ».
Texte seul (tranché le 2026-08-26), pas de `--json`.

## Cinq règles (validées le 2026-08-25)

1. **L'explication sort du calcul.** Le niveau est le minimum des axes ; l'axe qui plafonne est
   du même coup la cause et le chemin. Rien n'est reconstruit après coup.
2. **« Pourquoi P plutôt que Q »**, avec Q = le niveau immédiatement supérieur (Miller, 2019 :
   sélective, contrastive). Structure contrainte : niveau atteint, niveau visé, axe qui empêche.
3. **Ne montrer que l'axe qui bloque.** Les autres vont dans « acquis ». Un ex æquo se dit
   comme tel (`arthur` : Harness et Intervention ex æquo à Copper), il ne se moyenne pas.
4. **Chaque affirmation cite où le fait a été observé** : fichier › champ = valeur. Une ligne
   sans pointeur est un défaut de l'outil (`Evidence` sans pointeur ne se construit pas).
5. **Dire le geste, pas la case manquante** (Karimi et al., recourse). Ordre par
   actionnabilité causale, jamais par colonne de grille :

| Axe | Actionnabilité | Forme |
|---|---|---|
| Harness | actionnable | geste précis et vérifiable, **toujours en premier** |
| En parallèle | actionnable, conditionné par Harness | en second |
| Intervention | mutable, non actionnable | nommer ce qu'il faut cadrer/automatiser en amont |
| Taille | mutable, non actionnable | dire que la taille suit le dispositif ; jamais « fais des PR plus grosses » |

## Table des gestes (`Recommendation`)

Une table écrite d'avance, un geste par (axe, niveau visé). Pas de rédaction par LLM : la
décision se calcule.

| Axe | Vers | Geste |
|---|---|---|
| Harness | Blue | écrire et versionner un fichier mémoire à la racine du dépôt (conventions, architecture, ce qu'il ne faut pas toucher) et le tenir à jour à chaque erreur répétée |
| Harness | Green/Copper | ajouter au moins une règle, un agent ou un hook versionné, et câbler le hook dans la configuration pour qu'il s'exécute sans coopération du modèle |
| Harness | Silver/Gold | ajouter une relance automatique bornée (N essais visibles) dans la CI ou un script, sur une commande du projet |
| En parallèle | Copper+ | isoler chaque chantier (worktree ou équivalent) et mener au moins trois fronts en même temps, habituellement — après le harness |
| Intervention | Blue | écrire ce qui est attendu avant de générer (cas limites inclus) pour que les corrections après ouverture diminuent |
| Intervention | Green/Copper | tests avant le code et validation de la compréhension avant la première ligne ; remonter une correction répétée dans les règles plutôt que dans le code |
| Intervention | Silver | automatiser la validation (tests, lint, duplication) pour qu'aucune reprise humaine ne soit nécessaire après ouverture |
| Taille | tout niveau | ne rien décréter : la taille habituelle monte quand le dispositif tient ; geste renvoyé à Harness |
| Harness | Red (depuis White) | commencer à produire avec l'IA sur de vraies tâches et signer ses commits (`Co-Authored-By`) — c'est le premier fait mesurable |
| Intervention | Red (depuis White) | même geste que Harness → Red : rien à reprendre tant que rien n'est produit avec l'IA |
| En parallèle | Red à Green (depuis White) | mener un chantier avec l'IA jusqu'au merge ; un seul suffit jusqu'à Green |
| Intervention | Gold | **aucun geste** : « cadrage compris » n'est pas observable dans les pièces fournies (spec 03) — la recommandation dit que l'axe plafonne à Silver par construction, elle n'invente pas de tâche |

Complété le 2026-08-29 (remarque Codex sur la PR #22) : la table couvre désormais toutes les
paires (axe, niveau visé) que la règle du minimum peut produire ; aucune ne lève d'exception.

**La preuve attendue.** Chaque geste porte le **champ qui devra bouger** pour le valider —
c'est le pointeur de la « prochaine quête » : Harness → Blue : `context_files.agents_md` ;
→ Green/Copper : `context_files.{rules,skills,hooks,agents}` ; → Silver/Gold :
`repo-context/ › bounded retry` ; En parallèle : `parallelism.median_concurrent_branches` ;
Intervention : `pull_requests.median_correction_commits_after_open` ; Taille :
`pull_requests.median_files_changed`. `Recommendation` porte ce champ (`proofField`).

Les gestes reprennent le vocabulaire de la grille et des pratiques constatées dans les
profils fournis (`bohort`, `leodagan`, `arthur`), pas un guide externe.

## Format de sortie

Icône + nom du niveau (jamais la couleur seule — utilisateur daltonien parmi les lecteurs,
et la vidéo est muette). Trois blocs fixes, puis les notes :

```
🥉 Copper — arthur (développeur indépendant)
Niveau atteint : Copper · niveau visé : Silver

Ce qui a mené là — l'axe qui plafonne : Harness et Intervention (ex æquo)
  Harness : behavior sans boucles
    git-activity.json › context_files.agents_md = true
    git-activity.json › context_files.{rules:0, skills:4, hooks:0, agents:2}
    repo-context/ › aucune relance bornée trouvée
  Intervention : aux étapes clés
    git-activity.json › pull_requests.median_correction_commits_after_open = 1
  Acquis pour Silver : Taille XL (median_files_changed = 29), En parallèle 4 (médiane)

Comment monter d'un cran — vers Silver
  1. Harness : ajouter une relance automatique bornée dans la CI …
  2. Intervention : automatiser la validation pour qu'aucune reprise …

Notes
  · pic observé : max_concurrent_branches = 7, non retenu
  · prérequis qualité : duplication 2,4 %, couverture 85 % (sonar-measures.json)
  · pièces : declaratif.md absent (« n'a pas répondu au questionnaire », profile.json › note)
```

## Gamification — deux gestes, pas davantage

Tranché : « gamification limitée à deux gestes ». Interprétation proposée, à valider :

1. **La barre des sept niveaux** en tête de sortie, position marquée par une icône, axe
   bloquant nommé sous la barre — « comment passer au niveau suivant » visible d'un coup d'œil.
2. **La prochaine quête** : le geste n° 1 du plan, formulé comme une tâche unique et
   vérifiable, avec la preuve qui la validera (le champ qui doit bouger).

Écarté : badges, scores, comparaison entre profils (la grille n'est pas un classement).

## Raccord avec les statuts

- `non évaluable` : prérequis manquant, ce qui a été lu, piste technique.
- `évalué, confiance basse` : fourchette et manque chiffré ; « comment monter » devient
  double — le geste, et ce qu'il faut pour lever le doute.
- `évalué` : niveau, axe, geste.

## Tests

Rendu de chaque profil fourni comparé à un fichier attendu (`tests/expected/*.txt`) ·
ex æquo `arthur` rendu comme tel · toute ligne de preuve contient ` › ` et une valeur ·
ordre Harness > En parallèle > Intervention > Taille respecté quand plusieurs bloquent.
