# AGENTS.md — mémoire projet

## But

`aidd-level` évalue le niveau AI-Driven Development d'un profil de développeur (grille AIDD,
White → Gold) et rend en texte : le niveau, ce qui a mené là, comment monter d'un cran.
Aucun appel LLM à l'exécution, aucune clé d'API, aucune persistance, sortie texte seule.

Les spécifications font foi : `docs/specs/`. Une décision se prend dans une spec, jamais dans
une implémentation. `docs/calibration.md` est la preuve chiffrée sur les quatre profils fournis.

## Architecture — hexagonale, trois couches, namespace `AiddLevel\`

- `src/Domain/` — calcul pur : `Level`, `Axis`, `Profile`, `Evidence`, `AxisVerdict`,
  `AxisEvaluator` (point d'extension), `LevelRule`, `Assessment`, `Recommendation`, port
  `ProfileSource`, constantes `*Thresholds` et `SampleFloors`.
  **N'importe jamais `Application\` ni `Infrastructure\`.**
- `src/Application/` — `EvaluateProfile` et `EvaluateProfileHandler`.
- `src/Infrastructure/` — `DirectoryProfileSource`, lecteurs JSON/Markdown, `EvaluateCommand`
  (symfony/console), `TextRenderer`.

## Règles non négociables

1. `Domain/` ne dépend de rien d'extérieur (gardé par hook).
2. Tout seuil vit dans une constante nommée, avec sa justification en commentaire à côté.
3. Toute ligne d'explication porte un pointeur vérifiable (`fichier › champ = valeur`).
4. Le déclaratif (`declaratif.md`) n'entre dans aucun calcul.
5. La médiane décide, jamais le maximum.
6. Un profil incomplet ne fait jamais planter : il sort un statut (`évalué`, `évalué,
   confiance basse`, `non évaluable`).
7. Un commit touche une seule couche (`src/Domain/`, `src/Application/`, `src/Infrastructure/`,
   `tests/`) ou la documentation ; il ne touche jamais `src/Domain/` et `src/Infrastructure/`
   ensemble (gardé par hook `guard-git`).
8. Lire la spec concernée dans `docs/specs/` avant toute implémentation ; ne jamais implémenter
   un comportement qui la contredit — signaler l'écart et s'arrêter.

## Décisions figées — ne pas rouvrir sans nouvelle donnée

| Décision | Où |
|---|---|
| Niveau = **minimum** des quatre axes ; cellule = minimum, pas valeur exacte | spec 00 § 2 |
| **Médiane**, jamais maximum (Taille, En parallèle) | specs 01, 04 |
| Intervention = médiane des commits correctifs après ouverture, bandes `≥ 3` Red, `≥ 2` Blue, `> 0` Copper (0,5 inclus), `= 0` Silver ; **aucune pièce ne donne l'auteur par commit**, le ratio humain/IA par branche est incalculable | spec 03 |
| `declaratif.md` hors calcul ; `session.md`, `code/` **non lus** ; `pull-requests.json` écarté | specs 00 § 3, 03 |
| Champ absent (`null`) ≠ zéro : jamais coercé, axe non observable → fourchette + note | spec 05 § Signal absent |
| Harness cumule : behavior sans mémoire n'existe pas ; boucle = relance **et** borne | spec 02 |
| Gold Intervention inatteignable par construction (cadrage automatisé non observable) | spec 03 |
| Codex revoit **une fois** à l'ouverture ; `+1` = verdict favorable ; seule re-revue : `@codex review` si la correction change un seuil, un niveau ou la règle du minimum ; merge par `--auto`, jamais synchrone | spec 08, § Flow d'une PR |

## Où en est le projet

Mis à jour à chaque fin de chantier — une ligne, pas un historique (l'historique est `ROADMAP.md`).

- **2026-08-30** : chantiers 0–18 mergés (14 : #50 `9b8e0e1` ; 17 : #51 `b84bcd9` ; 18 : #53
  `fddc825`). Profils `venec` et `lancelot` (sans niveau) ajoutés (#35). Reste : vidéo
  (Jonathan, issue #12) et le relevé du cron `auto-merge-after-codex` prévu le 2026-08-31
  (spec 08 § 12.3, ouvrir une issue si le compte de runs planifiés est toujours nul).

## Stack et commandes

PHP 8.5, `symfony/console ^7.4`, PHPUnit 13. Tout tourne dans Docker (PHP local insuffisant).
Table des cibles : `README.md § Commandes`.

Les commandes du projet passent par `make` (Docker) ; ne pas lancer `php`, `composer` ni
`vendor/bin/*` directement.

## Conventions

- Code, identifiants et commentaires en **anglais** ; README, `docs/` et **tout texte destiné à
  l'utilisateur** (claims d'`Evidence`, `Note`, gestes, messages d'exception, rendu) en
  **français**. Les identifiants de pointeurs restent tels quels.
- Conventional Commits, messages de commit en anglais, sujet de 72 caractères maximum. Une
  PR = une issue.
- Branche de feature courte, rebase sur `main`, jamais de merge de `main` dans la branche
  (`git fetch origin && git rebase origin/main && git push --force-with-lease` ; `--force` nu
  est refusé par hook). Une tentative de rebase automatique ; en cas de conflit, s'arrêter et
  journaliser.
- `ROADMAP.md` et `docs/journal.md` sont **append-only** : ajouter des lignes en fin de
  fichier, ne jamais réécrire. Une ligne de journal sans pointeur (SHA, PR, run, chemin) ne
  vaut rien.
- `composer.json` n'est modifié que par un chantier à la fois.
- Tout commit produit par un agent porte le trailer de coauteur (chaîne exacte dans
  `CLAUDE.md`) — sans lui, le dépôt se note lui-même à tort (constaté : ratio 0,48 sur
  `profiles/self`).

## Flow d'une PR — imposé par le harnais

- **Toute PR naît d'une issue et passe par `/feature <n°>`**, docs comprises. Le skill est
  invocable par la session ; le hook `guard-git` refuse `gh pr create` hors d'un run du skill
  (verrou par issue, `node .claude/hooks/feature-lock.js lock|unlock <n°>`) et tout
  `gh pr merge` synchrone (seuls `--auto` et `--disable-auto` passent).
- Ce que le skill garantit, dans l'ordre : label `to-review` ; attente du verdict Codex
  (`eyes` = en cours, `+1` = sans remarque, revue `COMMENTED` = remarques inline) ; une passe
  de correction ; **rebase et push d'abord**, puis une réponse tracée par remarque citant le SHA
  présent sur la branche ; `@codex review` seulement si la correction change un seuil, un
  niveau ou la règle du minimum ; `gh pr merge --auto --squash --delete-branch` dans tous les
  cas ; ligne au journal avec pointeur.
- Le cron `auto-merge-after-codex` (chantier 15) n'est qu'un filet pour un 👍 resté sans suite.
- **`/roadmap` ouvre les fronts prêts** (dépendances mergées sur GitHub, spec présente, aucun
  verrou ni chevauchement de sorties) et lance un agent `front` par front, en arrière-plan ;
  `/feature` reste le cycle unitaire qu'il précharge (chantier 17).
- Trois mots reconnus par la session pour la roadmap : « **pause roadmap** » (plus de nouveau
  front, les fronts en cours vont au bout), « **stop roadmap** » (idem, plus arrêt des fronts
  en cours et désarmement des PR), « **reprends la roadmap** » (seule façon de repartir).

## Définition de fini

Tests verts (`make test`), PHPStan sans erreur, duplication sous le seuil, chaque décision de
scoring touchée couverte par un test, `docs/calibration.md` toujours vrai, PR revue, mergée en
squash, branche supprimée.

## Code Review Rules

Points à vérifier sur chaque pull request, dans cet ordre :

1. **Aucun seuil en dur** dans la logique : toute valeur numérique de décision vit dans une
   constante nommée de `src/Domain/` avec sa justification.
2. **Aucune ligne de sortie sans pointeur** : chaque `Evidence` cite fichier, champ et valeur.
3. **`src/Domain/` n'importe rien** de `AiddLevel\Application` ni `AiddLevel\Infrastructure`.
4. **Chaque décision de scoring modifiée a un test** qui la fige (profil de référence → niveau
   attendu, ou cas dégradé → statut).
5. **La médiane, jamais le maximum**, sur Taille et En parallèle.
7. **Pas de duplication** de blocs : préférer une fonction commune.
8. Signaler, sans bloquer, ce qui contredit `docs/specs/`.
9. **Une spec ne contredit pas sa propre fin** : aucune question ouverte, aucune section
   d'arbitrage qui décide, aucun seuil ni nom de constante cité en deux valeurs différentes
   dans le même fichier.
10. **La doc suit le code** : une PR qui rend faux un panneau de documentation
    (`.claude/README.md`, `src/README.md`, `profiles/README.md`, `docs/sortie.md`,
    `README.md`) le corrige dans la même PR ; la table chemin → panneau est en
    `docs/specs/00-vue-ensemble.md § 7.5`.
