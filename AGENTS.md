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
7. Un commit ne touche pas `src/Domain/` et `src/Infrastructure/` ensemble (gardé par hook).

## Décisions figées — ne pas rouvrir sans nouvelle donnée

| Décision | Où |
|---|---|
| Niveau = **minimum** des quatre axes ; cellule = minimum, pas valeur exacte | spec 00 § 2 |
| **Médiane**, jamais maximum (Taille, En parallèle) | specs 01, 04 |
| Intervention = médiane des commits correctifs après ouverture, seuils 3/2/1/0 ; **aucune pièce ne donne l'auteur par commit**, le ratio humain/IA par branche est incalculable | spec 03 |
| `declaratif.md` hors calcul ; `session.md`, `code/` **non lus** ; `pull-requests.json` écarté | specs 00 § 3, 03 |
| Champ absent (`null`) ≠ zéro : jamais coercé, axe non observable → fourchette + note | spec 05 § Signal absent |
| Harness cumule : behavior sans mémoire n'existe pas ; boucle = relance **et** borne | spec 02 |
| Gold Intervention inatteignable par construction (cadrage automatisé non observable) | spec 03 |
| Codex revoit **une fois** à l'ouverture ; `+1` = verdict favorable ; merge par `--auto`, jamais synchrone | spec 08, CLAUDE.md § Flow |

## Où en est le projet

Mis à jour à chaque fin de chantier — une ligne, pas un historique (l'historique est `ROADMAP.md`).

- **2026-08-30** : chantiers 0–12 mergés. Profils `venec` et `lancelot` (sans niveau) ajoutés
  (#35). En cours : 15 (auto-merge par Actions, #37), 13 (note « sur la borne », ratio absent,
  spec #36). À spécifier : 14 (boucles resserrées). Reste : vidéo (Jonathan).

## Stack et commandes

PHP 8.5, `symfony/console ^7.4`, PHPUnit 13. Tout tourne dans Docker (PHP local insuffisant).

| Commande | Rôle |
|---|---|
| `make build` | construit l'image et installe les dépendances |
| `make test` | PHPUnit |
| `make lint` | PHPStan |
| `make dup` | détection de duplication |
| `make demo` | évalue les quatre profils de `profiles/` |
| `make fmt FILE=…` | formate un fichier PHP |

## Conventions

- Code, identifiants et commentaires en **anglais** ; README, `docs/` et **tout texte destiné à
  l'utilisateur** (claims d'`Evidence`, `Note`, gestes, messages d'exception, rendu) en
  **français**. Les identifiants de pointeurs restent tels quels.
- Conventional Commits. Une PR = une issue. Rebase sur `main`, jamais de merge de `main`.
- `ROADMAP.md` et `docs/journal.md` sont **append-only**.
- `composer.json` n'est modifié que par un chantier à la fois.
- `.brief/` est privé : jamais lu par la CI, jamais committé, jamais cité dans le code.

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
6. **Aucune clé, jeton ou secret** dans le diff, ni référence à `.brief/`.
7. **Pas de duplication** de blocs : préférer une fonction commune.
8. Signaler, sans bloquer, ce qui contredit `docs/specs/`.
