# Le harnais tel qu'il a réellement tourné

La spec 08 décrit l'intention ; ce fichier décrit le fait. Les pointeurs sont dans
`docs/journal.md` et dans l'historique des PR.

## Ce qui a tenu

- **Specs avant tout** : premier commit `f65e92a` = specs + outillage, zéro code. Chaque
  chantier a implémenté une spec validée ; chaque écart a été signalé par l'agent, pas tranché.
- **Amorçage depuis le local** : `git init`, `gh repo create --source=. --push`, labels,
  protection de branche par `gh api` (HTTP 200 du premier coup — doute 5 levé).
- **Codex revoit à l'ouverture de PR** : constaté sur #13, 12 min après le push (doutes 1 et 3
  levés). Forme : commentaires inline, jamais de check — le verrou vit dans le skill.
- **Hooks** : `guard-layers`, `guard-git`, `format`, `journal`, chacun testé par violation
  avant d'être versionné, puis durcis par Codex (options globales git, chaînes `&&`,
  refspecs `+`, `--mirror`, `git add … && git commit`).
- **Parallélisme calculé** : six fronts ouverts après le noyau (chantiers 2–7), sans conflit
  de fichiers — le découpage par axe a tenu. Intégration en cascade séquentielle (check
  `strict`), ~3 min par PR.
- **Le tool sur lui-même** : `profiles/self/` régénéré par script en fin de chantier (20 PR),
  verdict Blue par Taille (voir `docs/methode.md`) — après une première lecture Red due à un
  script qui comptait les rebases comme des corrections, corrigée sur remarque Codex.

## Ce qui a été coupé ou revu

| Prévu | Réel | Pointeur |
|---|---|---|
| Une passe de correction par PR | Illimitée (PR #15, Codex en redemandait de fondées), puis **une revue par PR** quand le quota est tombé en une soirée | journal 2026-08-29 |
| Merge après verdict Codex | #1 et #13 mergés à la main avant le verdict (erreur de conduite), #14 mergé sans re-revue (Codex ne re-revoit pas sur push : `@codex review`) | journal, PR #14 |
| Agent `dev` propriétaire de la boucle | Le skill possède la boucle, l'agent rend la main à la PR ouverte | PR #14 |
| `deny` sur `git push --force` | Bloquait `--force-with-lease`, requis par le rebase : remplacé par le hook `guard-git` | PR #16 |
| `maxTurns = 80` | Atteint deux fois (chantiers 3 et 7) en passe de correction ; relance resserrée, fini en < 10 tours | journal |
| Agents chargés à la création | La session démarrée avant `.claude/agents/` ne les voyait pas : chantier 1 sur un agent générique ; chargés à chaud ensuite | journal |
| Trailer `Co-Authored-By` sur chaque commit | Absent des commits d'agents (ratio 0,48 mesuré sur `self`) : règle ajoutée après coup | `CLAUDE.md` |
| Cascade en fond faisant `checkout main` dans le checkout principal | Un commit du chantier 11 a atterri sur `main` local pendant que le fond changeait de branche ; réparé par `reset`, script corrigé pour ne jamais toucher au checkout principal | journal |
| Suppression des worktrees | Refusée (`vendor/` créé en root par Docker) : nettoyage via conteneur | journal |
| Correction en CI (`claude-code-action`) | Jamais montée : correction en local, aucun secret dans le dépôt | spec 08 |

## Délais mesurés

Revue Codex : 12 min (#13), 3 à 10 min ensuite. `REVIEW_WAIT_MAX = 20 min` n'a jamais été
atteint hors quota. Quota de revues Codex : épuisé après ~25 revues en une soirée.

## Le mode manuel, tel que prévu

Le point de non-retour du brief (« une heure d'écart, la boucle passe en manuel ») n'a pas été
franchi : à chaque casse, le repli prévu a été appliqué (merge à la main, revue à la demande,
relance d'agent), et la boucle a repris.
