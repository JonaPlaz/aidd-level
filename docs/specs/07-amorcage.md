# 07 — Amorçage

Spécification du premier skill du harnais, exécuté **une fois**, sans autre geste humain que
la validation des specs. Tout part du dossier local `/home/jplaz/dev/aidd-level`, rien ne passe
par l'interface web GitHub. Exigence de Jonathan, 2026-08-28.

Vérifié le 2026-08-28 sur la machine : `gh` 2.87.3 connecté au compte `JonaPlaz`
(protocole https, jeton `gho_…` porteur de `repo` et `workflow` d'après le brief — **non
revérifié par `gh auth status -t`**, à lire au premier `push` de workflow), Docker 29.7.2,
PHP local 8.3.6 (insuffisant, cf. § 00 : Docker obligatoire).

## Précondition

`docs/specs/` et `docs/calibration.md` existent et sont validés. Le dossier ne contient
rien d'autre que `.brief/` et `docs/`. Pas de `.git`.

## Étape 1 — fichiers racine

| Fichier | Contenu |
|---|---|
| `.gitignore` | `.brief/` **en première ligne** · `vendor/` · `.claude/worktrees/` · `.claude/settings.local.json` · `.phpunit.cache/` · `composer.phar` · `*.local` |
| `.worktreeinclude` | `.brief/` — copié dans chaque worktree (syntaxe `.gitignore`, vérifié doc *worktrees*) ; sans lui les agents en worktree travaillent à l'aveugle |
| `LICENSE` | MIT, `Copyright (c) 2026 Jonathan Plazanet` |
| `AGENTS.md` | mémoire projet lue par les deux outils : but de l'outil, architecture trois couches, règles non négociables (§ 00, § 5), commandes (`make test lint dup demo`), définition de fini. **Section `## Code Review Rules`** lue par Codex : vérifier qu'aucun seuil n'est en dur, qu'aucune ligne de sortie n'est sans pointeur, que `Domain/` n'importe rien d'extérieur, qu'un test couvre chaque décision de scoring touchée, qu'aucune clé ni secret n'apparaît |
| `CLAUDE.md` | `@AGENTS.md` puis les règles propres à Claude Code : langue (code anglais, docs français), un commit = une couche ou docs, rebase sur `main` jamais de merge, `ROADMAP.md` et `docs/journal.md` append-only, `composer.json` modifié par un seul chantier à la fois, interdiction de toucher `.brief/` |
| `README.md` | squelette : titre, notice trois lignes (installer, lancer, sortie) avec la commande Docker de § 00, section « en construction » ; le détail arrive avec le code |
| `ROADMAP.md` | en-tête et format (§ 08) ; le contenu est dérivé à l'étape 5 du brief |
| `docs/journal.md` | en-tête et format de ligne (§ 08) ; première ligne : « amorçage lancé » avec le SHA du commit initial ajouté après coup par le skill |
| `Makefile` | cibles `build`, `test`, `lint`, `dup`, `demo`, `fmt` — toutes passent par `docker compose run --rm php …` ; **c'est ce que la boucle relance** |
| `compose.yaml` · `Dockerfile` | image `php:8.5-cli-alpine`, Composer 2.10 copié depuis `composer:2.10`, `COPY` du projet, `ENTRYPOINT ["bin/aidd-level"]` |
| `composer.json` | `php: ^8.5`, `symfony/console: ^7.4`, dev : `phpunit/phpunit: ^13`, `phpstan/phpstan` (**version non vérifiée** pour PHP 8.5), outil de duplication (**non vérifié** : `phpcpd` est archivé, candidats `systemsdk/phpcpd` ou `jscpd` — à trancher au montage, § 08) ; `composer.lock` généré dans Docker et committé |
| `phpunit.xml.dist` | `tests/` en un seul suite, cache dans `.phpunit.cache/` |
| `bin/aidd-level` | exécutable PHP qui monte l'application console |
| `profiles/` | les quatre profils copiés depuis `laivel-up@89b9e35` + `ATTRIBUTION.md` (MIT, AI-Driven Dev contributors) |

Rien de `src/` ni de `tests/` : le code n'est jamais le premier geste. Le harnais (`.claude/`,
`.github/`) arrive à l'étape 6 du brief, par sa propre spec.

Le partage `AGENTS.md` / `CLAUDE.md` décrit ci-dessus est celui du jour 1, non réécrit : voir
spec 08 § 12 pour son évolution vers une source unique. Même réserve pour le `README.md` : le
squelette du jour 1 (« section en construction », « le détail arrive avec le code ») est
l'amorçage, pas la cible — voir spec 00 § 7.3 pour ce que le README porte au rendu.

## Étape 2 — dépôt local

```
git init -b main
git add -A            # .brief/ ignoré — vérifier par `git status --ignored` avant le commit
git commit -m "chore: bootstrap repository from validated specs"
```

**Le premier commit porte les specs** : l'historique montre le contexte avant le code.
Vérification bloquante : `git ls-files | grep -c '^\.brief/'` doit rendre `0`.

## Étape 3 — dépôt distant

```
gh repo create aidd-level --public --source=. --remote=origin --push \
  --description "Évalue le niveau AI-Driven Development d'un profil — hackathon LAIVEL UP"
```

Flags vérifiés (`gh` 2.87.3). `--license` et `--gitignore` sont des gabarits pour dépôt vide,
inutilisables avec `--source` : `LICENSE` est écrit en local (étape 1).

## Étape 4 — labels

`gh label create to-implement --color 0E8A16 --description "Prêt pour l'agent dev"` ·
`gh label create to-review --color FBCA04 --description "PR ouverte, en attente de review"` ·
`gh label create blocked --color B60205 --description "Borne atteinte, décision humaine"`.

## Étape 4 bis — réglages du dépôt

`gh repo edit --enable-auto-merge --delete-branch-on-merge`. Constaté le 2026-08-28 sur la
PR #14 : sans ce réglage, `gh pr merge --auto` répond « Auto merge is not allowed for this
repository ». Ajouté après coup à l'amorçage.

## Étape 5 — protection de `main`

`gh ruleset` est en lecture seule (vérifié) ; l'API classique s'emploie :

```
gh api -X PUT repos/JonaPlaz/aidd-level/branches/main/protection --input - <<'JSON'
{
  "required_status_checks": null,
  "enforce_admins": false,
  "required_pull_request_reviews": { "required_approving_review_count": 0 },
  "restrictions": null,
  "required_linear_history": true,
  "allow_force_pushes": false,
  "allow_deletions": false
}
JSON
```

- `required_status_checks: null` **à l'amorçage** : la CI n'existe pas encore, un check
  requis absent bloquerait la PR jetable de l'étape 4 du brief. Le check `ci` devient requis
  au montage du harnais (§ 08), par un second `PUT`.
- `required_approving_review_count: 0` : projet solo, aucun agent ne s'auto-approuve, et
  rien ne prouve que Codex approuve (**non vérifié**). La PR reste obligatoire.
- `enforce_admins: false` : c'est la borne 3 — Jonathan peut passer outre si la boucle casse.
- `required_linear_history: true` : politique « rebase, jamais de merge de `main` ».

**Lire le code retour** (doute 5 du brief) : un `422` ou `404` est journalisé et la
protection est posée à la main, une fois — ce n'est pas un livrable.

## Étape 6 — fin et annonce

Le skill écrit dans `docs/journal.md` une ligne par étape avec son pointeur (SHA, URL du
dépôt, réponse HTTP), puis **s'arrête et annonce le seul geste manuel restant** :
l'activation de la revue Codex (réglage web, *Revue du code*, revue automatique à l'ouverture
de PR). Aucune commande ne l'automatise.

## Ce qui n'est pas dans ce skill

La PR jetable (étape 4 du brief) : elle se fait après l'amorçage, sur ce dépôt, par le skill
de démarrage de feature en mode trivial (§ 08, *PR jetable*).

## Tests

Le skill est idempotent sur ses vérifications (refuse de tourner si `.git` existe) ; chaque
commande externe lit son code retour ; `.brief/` absent de `git ls-files` ; `gh repo view`
rend le dépôt public ; `gh api …/protection` rend `200`.
