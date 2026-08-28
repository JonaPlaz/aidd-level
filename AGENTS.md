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

- Code, identifiants et commentaires en **anglais** ; README et `docs/` en **français**.
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
